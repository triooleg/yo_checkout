<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Western_Bid_Service {
    private $callbacks;

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
    }

    private function call($name, ...$args) {
        if (!isset($this->callbacks[$name]) || !is_callable($this->callbacks[$name])) return null;
        return call_user_func_array($this->callbacks[$name], $args);
    }

    private function invoice_candidates($invoice) {
        $invoice = sanitize_text_field((string)$invoice);
        $list = [];
        if ($invoice !== '') $list[] = $invoice;

        $login = preg_quote((string)$this->credentials()['login'], '/');
        if ($login !== '' && preg_match('/^' . $login . '[-_](.+)$/', $invoice, $m)) {
            $list[] = sanitize_text_field($m[1]);
        }
        if (preg_match('/(YO-WB-\d+-\d+)$/', $invoice, $m)) {
            $list[] = sanitize_text_field($m[1]);
        }

        return array_values(array_unique(array_filter($list)));
    }

    private function local_id_for_invoice($invoice) {
        foreach ($this->invoice_candidates($invoice) as $candidate) {
            $local_id = absint(get_option('yo_western_bid_order_' . $candidate));
            if ($local_id) return [$local_id, $candidate];
        }
        return [0, ''];
    }

    public function credentials() {
        $s = (array)$this->call('settings');
        return [
            'login' => trim((string)($s['western_bid_login'] ?? '')),
            'secret' => (string)($s['western_bid_secret_key'] ?? ''),
            'currency' => strtoupper(trim((string)($s['western_bid_currency'] ?? 'EUR'))) ?: 'EUR',
            'gate' => trim((string)($s['western_bid_gate'] ?? 'paypal')) ?: 'paypal',
        ];
    }

    public function start_payment($local_id) {
        $creds = $this->credentials();
        if ($creds['login'] === '' || $creds['secret'] === '') {
            return new WP_Error('western_bid_missing_credentials', 'Western Bid login or secret key is empty');
        }

        $local_id = absint($local_id);
        if (!$local_id) return new WP_Error('western_bid_order_missing', 'Order not found');

        $invoice = 'YO-WB-' . $local_id . '-' . time();
        update_post_meta($local_id, 'western_bid_invoice', $invoice);
        update_post_meta($local_id, 'payment_provider', 'western_bid');
        update_post_meta($local_id, 'payment_type', 'card');
        update_option('yo_western_bid_order_' . $invoice, $local_id, false);

        if (function_exists('wp_schedule_single_event')) {
            wp_schedule_single_event(time() + 120 * 60, 'yo_checkout_check_unpaid_order', [$local_id]);
        }

        $page_url = add_query_arg([
            'action' => 'yo_checkout_western_bid_form',
            'invoice' => rawurlencode($invoice),
        ], admin_url('admin-ajax.php'));

        return [
            'provider' => 'western_bid',
            'invoiceId' => $invoice,
            'pageUrl' => $page_url,
            'orderId' => 'WEB-' . $local_id,
            'cartMarker' => ['local_id' => $local_id],
        ];
    }

    public function ajax_form() {
        $invoice = sanitize_text_field(wp_unslash($_GET['invoice'] ?? ''));
        $local_id = $invoice ? absint(get_option('yo_western_bid_order_' . $invoice)) : 0;
        if (!$invoice || !$local_id || get_post_type($local_id) !== $this->call('cpt')) {
            status_header(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Western Bid order not found';
            exit;
        }

        $fields = $this->purchase_fields($local_id, $invoice);
        if (is_wp_error($fields)) {
            status_header(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($fields->get_error_message());
            exit;
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo $this->render_autosubmit_html($fields);
        exit;
    }

    public function ajax_return() {
        $invoice = sanitize_text_field(wp_unslash($_REQUEST['invoice'] ?? $_REQUEST['order_id'] ?? ''));
        [$local_id] = $invoice ? $this->local_id_for_invoice($invoice) : [0, ''];
        $status = sanitize_text_field(wp_unslash($_REQUEST['payment_status'] ?? $_REQUEST['status'] ?? ''));
        $paid = ($local_id && get_post_meta($local_id, 'paid', true) === '1');
        $message = $paid
            ? 'Payment successful. Returning to order confirmation...'
            : 'Your payment is being verified. Please wait...';
        if ($status && !$paid) {
            $message = 'Payment status: ' . $status . '. Please wait while we verify it.';
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment verification</title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#fff;color:#07194b;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}.box{padding:28px;max-width:520px}.spinner{width:34px;height:34px;border:4px solid #e5eefb;border-top-color:#1e87f0;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 18px}@keyframes spin{to{transform:rotate(360deg)}}p{font-size:16px;line-height:1.5}</style></head>
<body><div class="box"><div class="spinner"></div><p><?php echo esc_html($message); ?></p></div>
<script>
(function(){
  var payload={type:'yo_western_bid_return', invoiceId:<?php echo wp_json_encode($invoice); ?>, paid:<?php echo $paid ? 'true' : 'false'; ?>, status:<?php echo wp_json_encode($status); ?>};
  function notifyCheckout(){
    try{ if(window.opener && !window.opener.closed){ window.opener.postMessage(payload, '*'); } }catch(e){}
    try{ if(window.parent && window.parent!==window){ window.parent.postMessage(payload, '*'); } }catch(e){}
  }
  notifyCheckout();
  setTimeout(notifyCheckout, 1200);
  setTimeout(function(){ try{ if(window.opener && !window.opener.closed){ window.close(); } }catch(e){} }, 2600);
})();
</script></body></html>
        <?php
        exit;
    }

    public function handle_webhook(WP_REST_Request $req) {
        $data = $req->get_body_params();
        if (empty($data)) $data = $req->get_json_params();
        if (!is_array($data)) $data = [];

        $invoice = sanitize_text_field($data['invoice'] ?? '');
        $debug_id = $invoice ? ('wb-' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $invoice), -24)) : 'wb-missing-invoice';
        $this->call('append_checkout_debug_log', $debug_id, 'western_bid webhook received', [
            'invoice' => $invoice,
            'payment_status' => sanitize_text_field($data['payment_status'] ?? ''),
            'wb_result' => sanitize_text_field($data['wb_result'] ?? ''),
            'mc_gross' => sanitize_text_field($data['mc_gross'] ?? ''),
            'mc_currency' => sanitize_text_field($data['mc_currency'] ?? ''),
            'txn_id' => sanitize_text_field($data['txn_id'] ?? ''),
        ]);
        if ($invoice === '') return new WP_REST_Response(['status' => 'error', 'message' => 'Missing invoice'], 400);

        [$local_id, $local_invoice] = $this->local_id_for_invoice($invoice);
        if (!$local_id) {
            $this->call('append_checkout_debug_log', $debug_id, 'western_bid webhook order not found', [
                'invoice' => $invoice,
                'candidates' => implode(' | ', $this->invoice_candidates($invoice)),
            ]);
            return new WP_REST_Response(['status' => 'error', 'message' => 'Order not found'], 404);
        }
        if ($local_invoice !== '' && !hash_equals($local_invoice, $invoice)) {
            update_option('yo_western_bid_order_' . $invoice, $local_id, false);
            update_post_meta($local_id, 'western_bid_notify_invoice', $invoice);
        }

        update_post_meta($local_id, 'western_bid_last_notify', $this->safe_notify_snapshot($data));
        update_post_meta($local_id, 'western_bid_payment_status', sanitize_text_field($data['payment_status'] ?? ''));
        update_post_meta($local_id, 'western_bid_wb_result', sanitize_text_field($data['wb_result'] ?? ''));
        if (!empty($data['txn_id'])) update_post_meta($local_id, 'western_bid_txn_id', sanitize_text_field($data['txn_id']));

        $verified = $this->verify_notify($local_id, $invoice, $data);
        if (is_wp_error($verified)) {
            update_post_meta($local_id, 'western_bid_notify_error', $verified->get_error_message());
            $this->call('append_checkout_debug_log', $debug_id, 'western_bid webhook verification failed', [
                'local_id' => $local_id,
                'error' => $verified->get_error_message(),
                'error_data' => $verified->get_error_data(),
            ]);
            return new WP_REST_Response(['status' => 'error', 'message' => $verified->get_error_message()], 400);
        }

        if (get_post_meta($local_id, 'paid', true) !== '1') {
            update_post_meta($local_id, 'paid', '1');
            update_post_meta($local_id, 'paid_at', time());
        }
        update_post_meta($local_id, 'western_bid_notify_verified', '1');
        $this->call('queue_deferred_payment_finalizer', $local_id, $local_invoice ?: $invoice, 'western_bid_webhook_completed');
        $this->call('append_checkout_debug_log', $debug_id, 'western_bid webhook completed', [
            'local_id' => $local_id,
            'invoice' => $invoice,
            'local_invoice' => $local_invoice,
        ]);

        return new WP_REST_Response(['status' => 'accept', 'invoice' => $invoice, 'local_invoice' => $local_invoice], 200);
    }

    public function purchase_fields($local_id, $invoice) {
        $creds = $this->credentials();
        if ($creds['login'] === '' || $creds['secret'] === '') {
            return new WP_Error('western_bid_missing_credentials', 'Western Bid login or secret key is empty');
        }

        $d = (array)$this->call('get_order_data', $local_id);
        $fee = (array)$this->call('card_fee_data', $local_id, 'western_bid');
        update_post_meta($local_id, 'card_fee_percent', $fee['percent'] ?? '0.00');
        update_post_meta($local_id, 'card_fee_amount', $fee['fee'] ?? '0.00');
        update_post_meta($local_id, 'card_total_amount', $fee['total'] ?? '0.00');

        $amount = number_format(round(floatval($fee['total'] ?? 0), 2), 2, '.', '');
        if (floatval($amount) <= 0) return new WP_Error('western_bid_bad_amount', 'Western Bid amount is empty');

        $name_parts = preg_split('/\s+/', trim((string)($d['full_name'] ?? '')), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name = $name_parts[1] ?? '';
        $items = (array)$this->call('cart_items_from_order_data', $d);
        $country_raw = sanitize_text_field($d['country'] ?? '');
        $country_iso = strtoupper(trim((string)$this->call('country_to_iso2', $country_raw)));
        if (!preg_match('/^[A-Z]{2}$/', $country_iso)) $country_iso = $country_raw;
        $address1 = sanitize_text_field($d['address'] ?? '');
        $address2 = sanitize_text_field($d['additional_address'] ?? '');
        $city = sanitize_text_field($d['city'] ?? '');
        $zip = sanitize_text_field($d['zip_code'] ?? '');
        $phone = sanitize_text_field($d['phone'] ?? '');
        $email = sanitize_email($d['email'] ?? '');
        $shipping_cost = number_format(round(floatval($fee['shipping'] ?? 0), 2), 2, '.', '');

        $fields = [
            'charset' => 'utf-8',
            'wb_login' => $creds['login'],
            'wb_hash' => md5($creds['login'] . $creds['secret'] . $amount . $invoice),
            'invoice' => $invoice,
            'email' => $email,
            'phone' => $phone,
            'amount' => $amount,
            'shipping' => '0.00',
            'currency_code' => $creds['currency'],
            'return' => add_query_arg(['action' => 'yo_checkout_western_bid_return', 'invoice' => rawurlencode($invoice)], admin_url('admin-ajax.php')),
            'cancel_return' => add_query_arg(['action' => 'yo_checkout_western_bid_return', 'invoice' => rawurlencode($invoice), 'status' => 'cancelled'], admin_url('admin-ajax.php')),
            'notify_url' => rest_url($this->call('rest_namespace') . '/western-bid-webhook'),
            'gate' => $creds['gate'],
            'first_name' => sanitize_text_field($first_name),
            'last_name' => sanitize_text_field($last_name),
            'address1' => $address1,
            'address2' => $address2,
            'country' => $country_iso,
            'country_code' => $country_iso,
            'city' => $city,
            'state' => '',
            'zip' => $zip,
            'zip_code' => $zip,
            'address_override' => '1',
            'no_shipping' => '1',
            'shipping_cost' => $shipping_cost,
            'shipping_note' => 'Shipping is included in the order total',
        ];
        $this->call('append_checkout_debug_log', 'wb-' . substr(preg_replace('/[^A-Za-z0-9_-]/', '', $invoice), -24), 'western_bid form fields prepared', [
            'local_id' => $local_id,
            'invoice' => $invoice,
            'gate' => $creds['gate'],
            'amount' => $amount,
            'shipping_included' => $shipping_cost,
            'country_raw' => $country_raw,
            'country_sent' => $country_iso,
            'has_address' => $address1 !== '' ? 'yes' : 'no',
            'has_zip' => $zip !== '' ? 'yes' : 'no',
        ]);

        $index = 1;
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $title = (string)$this->call('clean_product_title_for_display', $item['title'] ?? '');
            if ($title === '') continue;
            $item_amount = number_format(round(floatval($item['price_eur'] ?? 0), 2), 2, '.', '');
            if (floatval($item_amount) <= 0) continue;
            $product_id = sanitize_text_field($item['product_id'] ?? ($item['feed_id'] ?? ('item-' . $index)));
            $fields['item_name_' . $index] = mb_substr($title, 0, 120);
            $fields['item_number_' . $index] = $product_id ?: ('item-' . $index);
            $fields['amount_' . $index] = $item_amount;
            $fields['quantity_' . $index] = '1';
            $fields['url_' . $index] = home_url('/');
            $fields['description_' . $index] = mb_substr($title, 0, 255);
            $index++;
            if ($index > 30) break;
        }

        $service_fee = round(floatval($fee['fee'] ?? 0), 2);
        if ($service_fee > 0 && $index <= 30) {
            $fields['item_name_' . $index] = 'Card payment service fee';
            $fields['item_number_' . $index] = 'card-service-fee';
            $fields['amount_' . $index] = number_format($service_fee, 2, '.', '');
            $fields['quantity_' . $index] = '1';
            $fields['url_' . $index] = home_url('/');
            $fields['description_' . $index] = 'Card payment service fee';
            $index++;
        }

        if ($index === 1) {
            $fields['item_name_1'] = 'YOleotard order';
            $fields['item_number_1'] = $invoice;
            $fields['amount_1'] = $amount;
            $fields['quantity_1'] = '1';
            $fields['url_1'] = home_url('/');
            $fields['description_1'] = 'YOleotard order';
        }

        return $fields;
    }

    private function render_autosubmit_html($fields) {
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Western Bid</title></head><body style="font-family:Arial,sans-serif;text-align:center;padding:30px;">'
            . '<p>Redirecting to secure Western Bid payment page...</p>'
            . '<form id="western-bid" method="post" accept-charset="utf-8" action="https://shop.westernbid.info">';
        foreach ($fields as $key => $value) {
            $html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
        }
        $html .= '</form><script>document.getElementById("western-bid").submit();</script></body></html>';
        return $html;
    }

    private function verify_notify($local_id, $invoice, array $data) {
        $creds = $this->credentials();
        $wb_result = (string)($data['wb_result'] ?? '');
        $payment_status = (string)($data['payment_status'] ?? '');
        $gross_raw = trim(str_replace(',', '.', (string)($data['mc_gross'] ?? '')));
        $gross = number_format(round(floatval($gross_raw), 2), 2, '.', '');
        $currency = strtoupper(trim((string)($data['mc_currency'] ?? '')));
        $hash = (string)($data['wb_hash'] ?? '');
        $gross_hash_candidates = array_values(array_unique(array_filter([$gross_raw, $gross], function($value) {
            return $value !== '';
        })));
        $hash_ok = false;
        foreach ($gross_hash_candidates as $gross_for_hash) {
            $expected_hash = md5($creds['login'] . $wb_result . $creds['secret'] . $gross_for_hash . $invoice);
            if ($hash !== '' && hash_equals($expected_hash, $hash)) {
                $hash_ok = true;
                break;
            }
        }

        if (!$hash_ok) {
            return new WP_Error('western_bid_bad_hash', 'Western Bid notify hash is invalid', [
                'gross_raw' => $gross_raw,
                'gross_normalized' => $gross,
            ]);
        }
        if ($wb_result !== 'VERIFIED') return new WP_Error('western_bid_not_verified', 'Western Bid notify is not verified');
        if ($payment_status !== 'Completed') return new WP_Error('western_bid_not_completed', 'Western Bid payment is not completed');

        $d = (array)$this->call('get_order_data', $local_id);
        $expected_amount = number_format(round(floatval($d['card_total_amount'] ?? 0), 2), 2, '.', '');
        if ($expected_amount === '0.00') {
            $fee = (array)$this->call('card_fee_data', $local_id, 'western_bid');
            $expected_amount = number_format(round(floatval($fee['total'] ?? 0), 2), 2, '.', '');
        }
        if (!hash_equals($expected_amount, $gross)) {
            return new WP_Error('western_bid_amount_mismatch', 'Western Bid paid amount does not match the order', [
                'expected_amount' => $expected_amount,
                'received_amount' => $gross,
            ]);
        }
        if ($currency === '') $currency = $creds['currency'];
        if ($currency !== $creds['currency']) {
            return new WP_Error('western_bid_currency_mismatch', 'Western Bid paid currency does not match the order', [
                'expected_currency' => $creds['currency'],
                'received_currency' => $currency,
            ]);
        }

        return true;
    }

    private function safe_notify_snapshot(array $data) {
        $safe = [];
        foreach ($data as $key => $value) {
            $key = sanitize_key($key);
            if (in_array($key, ['wb_hash'], true)) continue;
            $safe[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field((string)$value);
        }
        return wp_json_encode($safe);
    }
}
