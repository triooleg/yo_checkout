<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Promo_Service {
    private $callbacks = [];

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
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

    public function is_configured() {
        $s = $this->settings();
        return trim((string)($s['promo_code'] ?? '')) !== '';
    }

    public function is_expired($s = null) {
        $s = is_array($s) ? $s : $this->settings();
        $date = trim((string)($s['promo_expires_at'] ?? ''));
        if ($date === '') return false;
        $expires_ts = strtotime($date . ' 23:59:59');
        return $expires_ts && current_time('timestamp') > $expires_ts;
    }

    public function calculate_discount($base_price) {
        $s = $this->settings();
        if (!$this->is_configured()) return new WP_Error('promo_empty', 'Promo code is not active');
        if ($this->is_expired($s)) return new WP_Error('promo_expired', 'This promo code has expired');
        $value = floatval(str_replace(',', '.', (string)($s['promo_discount_value'] ?? 0)));
        if ($value <= 0) return new WP_Error('promo_value', 'Promo discount is not configured');
        $type = ($s['promo_discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $base_price = round(floatval($base_price), 2);
        $discount = ($type === 'fixed') ? $value : ($base_price * $value / 100);
        $discount = round(min(max(0, $discount), max(0, $base_price - 1)), 2);
        if ($discount <= 0) return new WP_Error('promo_zero', 'Promo discount is not available for this amount');
        return [
            'discount' => number_format($discount, 2, '.', ''),
            'type' => $type,
            'value' => number_format($value, 2, '.', ''),
        ];
    }

    public function apply_to_order_data(array $data) {
        if (empty($data['promo_code_applied'])) return $data;

        $s = $this->settings();
        if (!$this->is_configured() || strcasecmp($data['promo_code_applied'], trim((string)($s['promo_code'] ?? ''))) !== 0 || $this->is_expired($s)) {
            return new WP_Error('promo_invalid', 'Promo code is no longer valid');
        }

        $cart_items = [];
        if (!empty($data['cart_items_json'])) {
            $decoded = json_decode((string)$data['cart_items_json'], true);
            if (is_array($decoded)) $cart_items = $decoded;
        }

        if ($cart_items) {
            $regular_final = 0;
            $original_total = 0;
            $product_discount_total = 0;
            $eligible_promo_base = 0;
            foreach ($cart_items as $item) {
                $item_price = round(floatval($item['price_eur'] ?? 0), 2);
                $item_original = round(floatval($item['original_price_eur'] ?? $item_price), 2);
                if ($item_original < $item_price) $item_original = $item_price;
                $item_product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
                $regular_final += $item_price;
                $original_total += $item_original;
                $product_discount_total += max(0, $item_product_discount);
                if ($item_product_discount <= 0) $eligible_promo_base += $item_price;
            }
            if ($eligible_promo_base <= 0) return new WP_Error('promo_ineligible', 'Promo code can be applied only to products without an active discount');
            $calc = $this->calculate_discount($eligible_promo_base);
            if (is_wp_error($calc)) return $calc;
            $promo_discount = floatval($calc['discount']);
            $promo_remaining = $promo_discount;
            $eligible_seen = 0;
            $cart_count = count($cart_items);
            foreach ($cart_items as $idx => &$cart_item) {
                $item_price = round(floatval($cart_item['price_eur'] ?? 0), 2);
                $item_product_discount = round(floatval($cart_item['product_discount_eur'] ?? 0), 2);
                $item_promo_discount = 0;
                if ($item_product_discount <= 0 && $eligible_promo_base > 0) {
                    $eligible_seen += $item_price;
                    if ($idx === $cart_count - 1 || abs($eligible_seen - $eligible_promo_base) < 0.01) {
                        $item_promo_discount = round($promo_remaining, 2);
                    } else {
                        $item_promo_discount = round($promo_discount * ($item_price / $eligible_promo_base), 2);
                        $promo_remaining = round($promo_remaining - $item_promo_discount, 2);
                    }
                }
                $cart_item['promo_discount_eur'] = number_format(max(0, $item_promo_discount), 2, '.', '');
                $cart_item['discount_eur'] = number_format(max(0, $item_product_discount + $item_promo_discount), 2, '.', '');
            }
            unset($cart_item);
            $data['cart_items_json'] = wp_json_encode($cart_items);
            $data['original_price_eur'] = round($original_total, 2);
            $data['price_eur'] = round(max(1, $regular_final - $promo_discount), 2);
            $data['discount_eur'] = round($product_discount_total + $promo_discount, 2);
            $data['promo_discount_type'] = $calc['type'];
            $data['promo_discount_value'] = $calc['value'];
            return $data;
        }

        $calc = $this->calculate_discount($data['original_price_eur']);
        if (is_wp_error($calc)) return $calc;
        $expected_discount = floatval($calc['discount']);
        $expected_price = round(max(1, floatval($data['original_price_eur']) - $expected_discount), 2);
        $data['price_eur'] = $expected_price;
        $data['discount_eur'] = $expected_discount;
        $data['promo_discount_type'] = $calc['type'];
        $data['promo_discount_value'] = $calc['value'];
        return $data;
    }

    public function ajax_apply_promo_code($order_post_type) {
        $this->call('verify_nonce');
        $local_id = absint($_POST['local_id'] ?? 0);
        $s = $this->settings();
        if (!$this->is_configured()) wp_send_json_error(['message'=>'Promo code is not active']);
        $code = sanitize_text_field($_POST['promo_code'] ?? '');
        if ($code === '' || strcasecmp($code, trim((string)$s['promo_code'])) !== 0) wp_send_json_error(['message'=>'Invalid promo code']);
        if ($this->is_expired($s)) wp_send_json_error(['message'=>'This promo code has expired']);

        if ($local_id) {
            if (get_post_type($local_id) !== $order_post_type) wp_send_json_error(['message'=>'Order not found']);
            $this->call('verify_order_access', $local_id);
            if (get_post_meta($local_id, 'keycrm_created', true) === '1' || get_post_meta($local_id, 'paid', true) === '1') wp_send_json_error(['message'=>'Promo code cannot be changed after payment method is selected']);
            $d = $this->call('get_order_data', $local_id);
            if (!is_array($d)) $d = [];
            if (floatval($d['discount_eur'] ?? 0) > 0) wp_send_json_error(['message'=>'This product already has an active discount']);
            $original = floatval(($d['original_price_eur'] ?? 0) ?: ($d['price_eur'] ?? 0));
        } else {
            $original = floatval($_POST['base_price'] ?? 0);
            if ($original <= 0) wp_send_json_error(['message'=>'Order amount is missing']);
        }

        $calc = $this->calculate_discount($original);
        if (is_wp_error($calc)) wp_send_json_error(['message'=>$calc->get_error_message()]);
        $discount = floatval($calc['discount']);
        $new_price = round(max(1, $original - $discount), 2);

        $response = [
            'price_eur' => number_format($new_price, 2, '.', ''),
            'discount_eur' => number_format($discount, 2, '.', ''),
            'original_price_eur' => number_format($original, 2, '.', ''),
            'message' => 'Promo code applied',
        ];

        if ($local_id) {
            update_post_meta($local_id, 'price_eur', number_format($new_price, 2, '.', ''));
            update_post_meta($local_id, 'discount_eur', number_format($discount, 2, '.', ''));
            update_post_meta($local_id, 'promo_code_applied', trim((string)$s['promo_code']));
            update_post_meta($local_id, 'promo_discount_type', $calc['type']);
            update_post_meta($local_id, 'promo_discount_value', $calc['value']);
            foreach (['shipping_price','shipping_country','shipping_source','card_fee_percent','card_fee_amount','card_total_amount','bank_total_amount','payment_provider','payment_type'] as $meta_key) delete_post_meta($local_id, $meta_key);
            $provider_preview = $this->call('card_provider_for_order', $local_id);
            $shipping_preview = $this->call('shipping_data', $local_id);
            $fee_preview = $this->call('card_fee_data', $local_id, $provider_preview);
            $response['cardProvider'] = $provider_preview;
            $response['cardFee'] = $fee_preview;
            $response['shipping'] = $shipping_preview;
            $response['bankTotal'] = $this->call('bank_total_data', $local_id);
        }
        wp_send_json_success($response);
    }
}
