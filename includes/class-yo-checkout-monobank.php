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

    private function selected_token_mode($local_id = 0) {
        $local_id = absint($local_id);
        if ($local_id) {
            $stored = get_post_meta($local_id, 'mono_token_mode', true);
            if ($stored === 'test' || $stored === 'live') return $stored;
        }

        $s = $this->settings();
        return (($s['mono_token_mode'] ?? 'live') === 'test') ? 'test' : 'live';
    }

    private function token_for_mode($mode) {
        $s = $this->settings();
        $mode = ($mode === 'test') ? 'test' : 'live';
        $key = ($mode === 'test') ? 'mono_test_token' : 'mono_token';
        $token = trim((string)($s[$key] ?? ''));

        return [$token, $mode];
    }

    private function token_error($mode) {
        $mode = ($mode === 'test') ? 'test' : 'live';
        return new WP_Error(
            $mode === 'test' ? 'mono_test_token_empty' : 'mono_token_empty',
            $mode === 'test' ? 'Monobank test token is empty' : 'Monobank live token is empty'
        );
    }

    private function debug_log($debug_id, $message, array $context = []) {
        $this->call('append_checkout_debug_log', $debug_id, $message, $context);
    }

    private function pubkey_cache_key($mode) {
        $mode = ($mode === 'test') ? 'test' : 'live';
        return 'yo_mono_pubkey_' . $mode;
    }

    private function fetch_public_key($mode, $force_refresh = false) {
        [$token, $mode] = $this->token_for_mode($mode);
        if ($token === '') return $this->token_error($mode);

        $cache_key = $this->pubkey_cache_key($mode);
        if (!$force_refresh) {
            $cached = get_transient($cache_key);
            if (is_string($cached) && trim($cached) !== '') return trim($cached);
        }

        $resp = wp_remote_get('https://api.monobank.ua/api/merchant/pubkey', [
            'headers' => ['X-Token' => $token, 'Accept' => 'text/plain, application/json'],
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return $resp;

        $raw = trim((string)wp_remote_retrieve_body($resp));
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $raw = trim((string)($decoded['key'] ?? $decoded['pubkey'] ?? $decoded['publicKey'] ?? ''));
        }

        if ($raw === '') return new WP_Error('mono_pubkey_empty', 'Monobank public key response is empty');

        set_transient($cache_key, $raw, 7 * DAY_IN_SECONDS);
        return $raw;
    }

    private function verify_webhook_signature($raw_body, $x_sign, $mode, $force_refresh = false) {
        $raw_body = (string)$raw_body;
        $x_sign = trim((string)$x_sign);
        if ($raw_body === '') return new WP_Error('mono_webhook_empty_body', 'Monobank webhook body is empty');
        if ($x_sign === '') return new WP_Error('mono_webhook_missing_signature', 'Monobank webhook signature is missing');
        if (!function_exists('openssl_verify') || !function_exists('openssl_get_publickey')) {
            return new WP_Error('mono_webhook_openssl_missing', 'OpenSSL is required to verify Monobank webhook signatures');
        }

        $pubkey_base64 = $this->fetch_public_key($mode, $force_refresh);
        if (is_wp_error($pubkey_base64)) return $pubkey_base64;

        $public_key_pem = base64_decode((string)$pubkey_base64, true);
        $signature = base64_decode($x_sign, true);
        if ($public_key_pem === false || $public_key_pem === '') {
            return new WP_Error('mono_webhook_bad_pubkey', 'Monobank public key could not be decoded');
        }
        if ($signature === false || $signature === '') {
            return new WP_Error('mono_webhook_bad_signature', 'Monobank webhook signature could not be decoded');
        }

        $public_key = openssl_get_publickey($public_key_pem);
        if (!$public_key) return new WP_Error('mono_webhook_bad_pubkey', 'Monobank public key is invalid');

        $result = openssl_verify($raw_body, $signature, $public_key, OPENSSL_ALGO_SHA256);
        if (is_resource($public_key) && function_exists('openssl_free_key')) openssl_free_key($public_key);

        return $result === 1;
    }

    private function verify_webhook_signature_with_refresh($raw_body, $x_sign, $mode) {
        $first = $this->verify_webhook_signature($raw_body, $x_sign, $mode, false);
        if ($first === true) return true;
        if (is_wp_error($first)) {
            $refreshable_errors = ['mono_webhook_bad_pubkey', 'mono_pubkey_empty'];
            if (!in_array($first->get_error_code(), $refreshable_errors, true)) return $first;
        }

        delete_transient($this->pubkey_cache_key($mode));
        $second = $this->verify_webhook_signature($raw_body, $x_sign, $mode, true);
        if ($second === true) return true;
        return is_wp_error($second) ? $second : $first;
    }

    private function webhook_amount_matches_order($local_id, array $data) {
        $expected_total = get_post_meta($local_id, 'card_total_amount', true);
        $expected_cents = intval(round(floatval($expected_total) * 100));
        if ($expected_cents <= 0) return true;

        if (isset($data['ccy']) && intval($data['ccy']) !== 978) return false;

        $provider_amount = null;
        if (isset($data['finalAmount']) && is_numeric($data['finalAmount'])) {
            $provider_amount = intval($data['finalAmount']);
        } elseif (isset($data['amount']) && is_numeric($data['amount'])) {
            $provider_amount = intval($data['amount']);
        }

        if ($provider_amount === null) return true;
        return $provider_amount === $expected_cents;
    }

    public function option_key($invoice_id) {
        return 'yo_mono_invoice_' . sanitize_text_field((string)$invoice_id);
    }

    public function local_id_for_invoice($invoice_id) {
        return absint(get_option($this->option_key($invoice_id)));
    }

    private function cart_items_count_from_data(array $data) {
        $count = absint($data['cart_items_count'] ?? 0);
        if ($count > 0) return $count;

        $raw = isset($data['cart_items_json']) ? (string)$data['cart_items_json'] : '';
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return max(1, count($decoded));
        }

        return 1;
    }

    private function checkout_item_label(array $data) {
        $count = $this->cart_items_count_from_data($data);
        return $count > 1 ? ('custom leotard x' . $count) : 'custom leotard';
    }

    public function start_payment($local_id) {
        $local_id = absint($local_id);
        $mode = $this->selected_token_mode();
        [$token, $mode] = $this->token_for_mode($mode);
        if ($token === '') return $this->token_error($mode);

        $data = $this->call('get_order_data', $local_id);
        if (!is_array($data)) $data = [];
        $fee = $this->call('card_fee_data', $local_id, 'monobank');
        if (!is_array($fee)) return new WP_Error('mono_fee', 'Monobank payment fee data was not calculated');

        $amount_cents = intval(round(floatval($fee['total']) * 100));
        update_post_meta($local_id, 'card_fee_percent', $fee['percent']);
        update_post_meta($local_id, 'card_fee_amount', $fee['fee']);
        update_post_meta($local_id, 'card_total_amount', $fee['total']);

        if (!empty($data['order_id'])) {
            $checkout_ref = (string)$data['order_id'];
        } else {
            $next_keycrm_order_id = $this->call('next_keycrm_order_id');
            if (is_wp_error($next_keycrm_order_id)) return $next_keycrm_order_id;
            $next_keycrm_order_id = absint($next_keycrm_order_id);
            if (!$next_keycrm_order_id) {
                return new WP_Error('keycrm_next_order_id_missing', 'Could not determine the next KeyCRM order ID');
            }
            $checkout_ref = $local_id . '/' . wp_date('y') . '-' . $next_keycrm_order_id;
            update_post_meta($local_id, 'monobank_expected_keycrm_order_id', $next_keycrm_order_id);
        }
        $merchant_reference = $checkout_ref;
        $item_label = $this->checkout_item_label($data);
        $destination = 'Payment for ' . $item_label . ' #' . $checkout_ref;
        if (function_exists('mb_substr')) {
            $destination = mb_substr($destination, 0, 120);
        } else {
            $destination = substr($destination, 0, 120);
        }
        $payload = [
            'amount' => $amount_cents,
            'ccy' => 978,
            'displayType' => 'iframe',
            'redirectUrl' => add_query_arg([
                'yo_checkout_return' => 'card',
                'provider' => 'monobank',
                'invoice_id' => '',
            ], home_url('/')),
            'webHookUrl' => rest_url($this->rest_namespace . '/mono-webhook'),
            'paymentType' => 'debit',
            'merchantPaymInfo' => [
                'reference' => $merchant_reference,
                'destination' => $destination,
                'comment' => $destination,
                'basketOrder' => [[
                    'name' => $item_label,
                    'qty' => 1,
                    'sum' => $amount_cents,
                    'total' => $amount_cents,
                    'unit' => 'pcs',
                    'code' => !empty($data['order_id']) ? ('KEYCRM-' . $checkout_ref) : ('YO-WEB-' . $local_id),
                ]],
            ],
        ];

        $resp = wp_remote_post('https://api.monobank.ua/api/merchant/invoice/create', [
            'headers' => ['X-Token'=>$token, 'Content-Type'=>'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return $resp;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['pageUrl'])) {
            return new WP_Error('mono_invoice_not_created', 'Monobank invoice was not created', $body);
        }

        update_post_meta($local_id, 'mono_invoice_id', sanitize_text_field($body['invoiceId']));
        update_post_meta($local_id, 'mono_token_mode', $mode);
        update_post_meta($local_id, 'payment_provider', 'monobank');
        update_post_meta($local_id, 'payment_type', 'card');
        update_option($this->option_key($body['invoiceId']), $local_id, false);
        wp_schedule_single_event(time() + 120*60, 'yo_checkout_check_unpaid_order', [$local_id]);

        return [
            'provider' => 'monobank',
            'invoiceId' => $body['invoiceId'],
            'pageUrl' => $body['pageUrl'],
            'orderId' => $checkout_ref,
            'tokenMode' => $mode,
            'cartMarker' => ['local_id'=>$local_id],
        ];
    }

    public function check_status($invoice_id) {
        $invoice_id = sanitize_text_field((string)$invoice_id);
        $local_id = $this->local_id_for_invoice($invoice_id);
        $mode = $this->selected_token_mode($local_id);
        [$token, $mode] = $this->token_for_mode($mode);
        if ($token === '') return $this->token_error($mode);

        $resp = wp_remote_get('https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . urlencode($invoice_id), [
            'headers' => ['X-Token'=>$token, 'Accept'=>'application/json'],
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return $resp;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return is_array($body) ? $body : [];
    }

    public function handle_webhook(WP_REST_Request $req) {
        $raw_body = (string)$req->get_body();
        $data = json_decode($raw_body, true);
        if (!is_array($data)) $data = $req->get_json_params();
        if (!is_array($data)) $data = [];
        $invoice_id = sanitize_text_field($data['invoiceId'] ?? '');
        $status = sanitize_text_field($data['status'] ?? '');
        if (!$invoice_id) return new WP_REST_Response(['ok'=>false], 400);

        $local_id = $this->local_id_for_invoice($invoice_id);
        if (!$local_id) return new WP_REST_Response(['ok'=>false], 404);

        $mode = $this->selected_token_mode($local_id);
        $debug_id = 'mono-' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $invoice_id), -24);
        $verified = $this->verify_webhook_signature_with_refresh($raw_body, $req->get_header('x-sign'), $mode);
        if (is_wp_error($verified) || $verified !== true) {
            $error_message = is_wp_error($verified) ? $verified->get_error_message() : 'Monobank webhook signature is invalid';
            update_post_meta($local_id, 'mono_webhook_signature_error', $error_message);
            $this->debug_log($debug_id, 'monobank webhook signature failed', [
                'local_id' => $local_id,
                'invoice_id' => $invoice_id,
                'status' => $status,
                'token_mode' => $mode,
                'error' => $error_message,
            ]);
            return new WP_REST_Response(['ok'=>false, 'error'=>'invalid_signature'], 403);
        }

        delete_post_meta($local_id, 'mono_webhook_signature_error');
        $this->debug_log($debug_id, 'monobank webhook verified', [
            'local_id' => $local_id,
            'invoice_id' => $invoice_id,
            'status' => $status,
            'token_mode' => $mode,
        ]);

        if (in_array($status, ['success','paid'], true)) {
            if (!$this->webhook_amount_matches_order($local_id, $data)) {
                update_post_meta($local_id, 'mono_webhook_amount_error', 'Monobank webhook amount or currency mismatch');
                $this->debug_log($debug_id, 'monobank webhook amount mismatch', [
                    'local_id' => $local_id,
                    'invoice_id' => $invoice_id,
                    'expected_total' => get_post_meta($local_id, 'card_total_amount', true),
                    'provider_amount' => isset($data['finalAmount']) ? $data['finalAmount'] : ($data['amount'] ?? ''),
                    'provider_ccy' => $data['ccy'] ?? '',
                ]);
                return new WP_REST_Response(['ok'=>false, 'error'=>'amount_mismatch'], 409);
            }

            delete_post_meta($local_id, 'mono_webhook_amount_error');
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
