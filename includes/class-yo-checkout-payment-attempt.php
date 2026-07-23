<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Payment_Attempt_Service {
    private $order_post_type = '';

    private $copy_meta_keys = [
        'title',
        'price_eur',
        'original_price_eur',
        'discount_eur',
        'image_url',
        'product_id',
        'full_name',
        'phone',
        'email',
        'address',
        'additional_address',
        'city',
        'zip_code',
        'country',
        'created_at',
        'shipping_cost_eur',
        'shipping_source',
        'shipping_weight_kg',
        'shipping_selected_key',
        'shipping_selected_label',
        'shipping_selected_service',
        'shipping_selected_method',
        'shipping_delivery_days',
        'promo_code_applied',
        'promo_discount_type',
        'promo_discount_value',
        'cart_items_count',
        'cart_items_json',
        'checkout_session_id',
        'checkout_submission_id',
        'checkout_submission_number',
        'checkout_submission_root_id',
        'browser_buyer_id',
        'product_catalog_status',
        'product_catalog_summary',
    ];

    public function __construct($order_post_type) {
        $this->order_post_type = (string)$order_post_type;
    }

    public function create($source_local_id) {
        $source_local_id = absint($source_local_id);
        if (!$source_local_id || get_post_type($source_local_id) !== $this->order_post_type) {
            return new WP_Error('payment_attempt_source_missing', 'Checkout order for the payment attempt was not found');
        }
        if (get_post_meta($source_local_id, 'paid', true) === '1') {
            return new WP_Error('payment_attempt_source_paid', 'A paid checkout order cannot start another payment attempt');
        }

        $root_local_id = absint(get_post_meta($source_local_id, 'payment_attempt_root_id', true));
        if (!$root_local_id) $root_local_id = $source_local_id;
        $attempt_number = $this->next_attempt_number($root_local_id);
        $full_name = sanitize_text_field(get_post_meta($source_local_id, 'full_name', true));

        $attempt_local_id = wp_insert_post([
            'post_type' => $this->order_post_type,
            'post_status' => 'publish',
            'post_title' => 'Payment attempt #' . $attempt_number . ($full_name !== '' ? ' - ' . $full_name : ''),
        ]);
        if (is_wp_error($attempt_local_id) || !$attempt_local_id) {
            return new WP_Error(
                'payment_attempt_create_failed',
                'A separate payment attempt could not be created',
                is_wp_error($attempt_local_id) ? $attempt_local_id->get_error_message() : ''
            );
        }

        foreach ($this->copy_meta_keys as $meta_key) {
            $values = get_post_meta($source_local_id, $meta_key, false);
            foreach ((array)$values as $value) {
                add_post_meta($attempt_local_id, $meta_key, wp_slash($value));
            }
        }

        update_post_meta($attempt_local_id, 'paid', '0');
        update_post_meta($attempt_local_id, 'payment_attempt_root_id', $root_local_id);
        update_post_meta($attempt_local_id, 'payment_attempt_source_id', $source_local_id);
        update_post_meta($attempt_local_id, 'payment_attempt_number', $attempt_number);
        update_post_meta($attempt_local_id, 'payment_attempt_created_at', current_time('mysql'));
        update_post_meta($source_local_id, 'latest_payment_attempt_id', $attempt_local_id);

        return $attempt_local_id;
    }

    private function next_attempt_number($root_local_id) {
        $attempt_ids = get_posts([
            'post_type' => $this->order_post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => 'payment_attempt_root_id',
            'meta_value' => absint($root_local_id),
        ]);
        return count((array)$attempt_ids) + 1;
    }
}
