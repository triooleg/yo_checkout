<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Monobank_Service {
    private $callbacks = [];
    private $rest_namespace = '';
    private $order_post_type = '';

    public function __construct(array $callbacks = [], $rest_namespace = '', $order_post_type = '') {
        $this->callbacks = $callbacks;
        $this->rest_namespace = (string)$rest_namespace;
        $this->order_post_type = (string)$order_post_type;
    }

    private function call($name, ...$args) {
        return isset($this->callbacks[$name]) && is_callable($this->callbacks[$name])
            ? call_user_func_array($this->callbacks[$name], $args)
            : null;
    }

    private function settings() {
        $settings = $this->call('settings');
        return is_array($settings) ? $settings : [];
    }

    public function option_key($invoice_id) {
        return 'yo_mono_invoice_' . sanitize_text_field((string)$invoice_id);
    }

    public function local_id_for_invoice($invoice_id) {
        return absint(get_option($this->option_key($invoice_id)));
    }

    public function start_payment($local_id) {
        $local_id = absint($local_id);
        $s = $this->settings();
        if (empty($s['mono_token'])) return new WP_Error('mono_token_empty', 'Monobank token is empty');

        $data = $this->call('get_order_data', $local_id);
        if (!is_array($data)) $data = [];
        $fee = $this->call('card_fee_data', $local_id, 'monobank');
        if (!is_array($fee)) return new WP_Error('mono_fee', 'Monobank payment fee data was not calculated');

        $amount_cents = intval(round(floatval($fee['total']) * 100));
        update_post_meta($local_id, 'card_fee_percent', $fee['percent']);
        update_post_meta($local_id, 'card_fee_amount', $fee['fee']);
        update_post_meta($local_id, 'card_total_amount', $fee['total']);

        $checkout_ref = !empty($data['order_id']) ? $data['order_id'] : ('WEB-' . $local_id);
        $destination = 'Payment for ' . ($data['title'] ?? '') . ' by checkout # ' . $checkout_ref;
        $payload = [
            'amount' => $amount_cents,
            'ccy' => 978,
            'displayType' => 'iframe',
            'redirectUrl' => home_url('/confirm?order_id=' . urlencode((string)($data['order_id'] ?? ''))),
            'webHookUrl' => rest_url($this->rest_namespace . '/mono-webhook'),
            'paymentType' => 'debit',
            'merchantPaymInfo' => [
                'reference' => 'website_checkout_' . $local_id,
                'destination' => $destination,
                'comment' => $destination,
                'basketOrder' => [[
                    'name' => $data['title'] ?? '',
                    'qty' => 1,
                    'sum' => $amount_cents,
                    'total' => $amount_cents,
                    'unit' => 'pcs',
                    'code' => 'YO-WEB-' . $local_id,
                ]],
            ],
        ];

        $resp = wp_remote_post('https://api.monobank.ua/api/merchant/invoice/create', [
            'headers' => ['X-Token'=>$s['mono_token'], 'Content-Type'=>'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return $resp;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['pageUrl'])) {
            return new WP_Error('mono_invoice_not_created', 'Monobank invoice was not created', $body);
        }

        update_post_meta($local_id, 'mono_invoice_id', sanitize_text_field($body['invoiceId']));
        update_post_meta($local_id, 'payment_provider', 'monobank');
        update_post_meta($local_id, 'payment_type', 'card');
        update_option($this->option_key($body['invoiceId']), $local_id, false);
        wp_schedule_single_event(time() + 120*60, 'yo_checkout_check_unpaid_order', [$local_id]);

        return [
            'provider' => 'monobank',
            'invoiceId' => $body['invoiceId'],
            'pageUrl' => $body['pageUrl'],
            'orderId' => $checkout_ref,
            'cartMarker' => ['local_id'=>$local_id],
        ];
    }

    public function check_status($invoice_id) {
        $s = $this->settings();
        $invoice_id = sanitize_text_field((string)$invoice_id);
        $resp = wp_remote_get('https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . urlencode($invoice_id), [
            'headers' => ['X-Token'=>$s['mono_token'], 'Accept'=>'application/json'],
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return $resp;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($body) ? $body : [];
    }

    public function handle_webhook(WP_REST_Request $req) {
        $data = $req->get_json_params();
        $invoice_id = sanitize_text_field($data['invoiceId'] ?? '');
        $status = sanitize_text_field($data['status'] ?? '');
        if (!$invoice_id) return new WP_REST_Response(['ok'=>false], 400);

        $local_id = $this->local_id_for_invoice($invoice_id);
        if (!$local_id) return new WP_REST_Response(['ok'=>false], 404);

        if (in_array($status, ['success','paid'], true)) {
            if (get_post_meta($local_id, 'paid', true) !== '1') {
                update_post_meta($local_id, 'paid', '1');
                update_post_meta($local_id, 'paid_at', time());
            }
            try {
                $this->call('process_successful_card_payment', $local_id, $invoice_id);
            } catch (Throwable $e) {
                update_post_meta($local_id, 'mono_webhook_finalizer_error', $e->getMessage());
                $this->call('queue_deferred_payment_finalizer', $local_id, $invoice_id, 'mono_webhook_error_retry');
            }
        }

        return new WP_REST_Response(['ok'=>true], 200);
    }
}
