<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Order_Access_Service {
    private $order_post_type = '';
    private $token_meta_key = '_yo_checkout_access_token_hash';
    private $created_meta_key = '_yo_checkout_access_token_created_at';

    public function __construct($order_post_type) {
        $this->order_post_type = (string)$order_post_type;
    }

    public function posted_token() {
        foreach (['access_token', 'order_access_token', 'yo_order_access_token'] as $key) {
            if (isset($_POST[$key])) {
                return sanitize_text_field(wp_unslash($_POST[$key]));
            }
        }
        return '';
    }

    public function has_token($local_id) {
        $local_id = absint($local_id);
        return $local_id > 0 && get_post_meta($local_id, $this->token_meta_key, true) !== '';
    }

    public function issue_token($local_id) {
        $local_id = absint($local_id);
        if (!$this->is_checkout_order($local_id)) return '';

        $token = $this->generate_token();
        update_post_meta($local_id, $this->token_meta_key, $this->hash_token($token));
        update_post_meta($local_id, $this->created_meta_key, time());
        return $token;
    }

    public function ensure_token_for_response($local_id, $posted_token = '') {
        $local_id = absint($local_id);
        if (!$this->is_checkout_order($local_id)) return '';

        $posted_token = sanitize_text_field((string)$posted_token);
        if ($this->has_token($local_id)) {
            return $this->verify($local_id, $posted_token) ? $posted_token : '';
        }

        return $this->issue_token($local_id);
    }

    public function verify($local_id, $token) {
        $local_id = absint($local_id);
        if (!$this->is_checkout_order($local_id)) {
            return false;
        }

        $stored_hash = (string)get_post_meta($local_id, $this->token_meta_key, true);
        if ($stored_hash === '') {
            // Legacy orders created before this service remain readable by their
            // existing checkout session until a new tokenized create-order pass occurs.
            return true;
        }

        $token = sanitize_text_field((string)$token);
        if ($token === '') return false;

        return hash_equals($stored_hash, $this->hash_token($token));
    }

    public function verify_posted_or_error($local_id) {
        $local_id = absint($local_id);
        if (!$this->verify($local_id, $this->posted_token())) {
            return new WP_Error('order_access_denied', 'Order access token is invalid or expired.');
        }
        return true;
    }

    public function response_fields($local_id, $token = '') {
        $local_id = absint($local_id);
        $token = sanitize_text_field((string)$token);
        if ($token === '') {
            $token = $this->ensure_token_for_response($local_id, $this->posted_token());
        }
        return [
            'accessToken' => $token,
            'cartMarkerAccessToken' => $token,
        ];
    }

    private function is_checkout_order($local_id) {
        return $local_id > 0 && get_post_type($local_id) === $this->order_post_type;
    }

    private function generate_token() {
        if (function_exists('random_bytes')) {
            try {
                return bin2hex(random_bytes(32));
            } catch (Exception $e) {
                // Fall through to the WordPress generator below.
            }
        }
        return wp_generate_password(64, false, false);
    }

    private function hash_token($token) {
        return hash_hmac('sha256', (string)$token, wp_salt('auth'));
    }
}
