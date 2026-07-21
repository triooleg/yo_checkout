<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Submission_Service {
    private $order_post_type = '';

    public function __construct($order_post_type) {
        $this->order_post_type = (string)$order_post_type;
    }

    public function create($previous_local_id, $full_name, $submission_id) {
        $previous_local_id = absint($previous_local_id);
        $submission_id = sanitize_text_field($submission_id);
        if ($submission_id === '') {
            return new WP_Error('checkout_submission_id_missing', 'Checkout submission ID is missing');
        }

        $submission_root_id = $previous_local_id ? absint(get_post_meta($previous_local_id, 'checkout_submission_root_id', true)) : 0;
        if (!$submission_root_id) $submission_root_id = $previous_local_id;
        $previous_number = $previous_local_id ? absint(get_post_meta($previous_local_id, 'checkout_submission_number', true)) : 0;
        $submission_number = $previous_number + 1;

        $local_id = wp_insert_post([
            'post_type' => $this->order_post_type,
            'post_status' => 'publish',
            'post_title' => 'YOleotard checkout submission - ' . sanitize_text_field($full_name),
        ]);
        if (is_wp_error($local_id) || !$local_id) {
            return new WP_Error(
                'checkout_submission_create_failed',
                'A separate checkout submission could not be created',
                is_wp_error($local_id) ? $local_id->get_error_message() : ''
            );
        }

        update_post_meta($local_id, 'checkout_submission_id', $submission_id);
        update_post_meta($local_id, 'checkout_submission_number', $submission_number);
        update_post_meta($local_id, 'checkout_submission_root_id', $submission_root_id ?: $local_id);
        update_post_meta($local_id, 'checkout_previous_local_id', $previous_local_id);
        update_post_meta($local_id, 'paid', '0');
        update_post_meta($local_id, 'keycrm_created', '0');

        if ($previous_local_id && get_post_type($previous_local_id) === $this->order_post_type) {
            $is_payment_attempt = absint(get_post_meta($previous_local_id, 'payment_attempt_number', true)) > 0;
            $is_completed = get_post_meta($previous_local_id, 'paid', true) === '1'
                || get_post_meta($previous_local_id, 'bank_invoice_created', true) === '1';
            if (!$is_payment_attempt && !$is_completed) {
                update_post_meta($previous_local_id, 'checkout_superseded_by', $local_id);
            }
        }

        return $local_id;
    }
}
