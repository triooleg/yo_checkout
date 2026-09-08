<?php

define('ABSPATH', __DIR__);

class WP_Error {
    private $code;
    private $message;
    private $data;

    public function __construct($code, $message, $data = null) {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
    public function get_error_data() { return $this->data; }
}

$stored_meta = [];
$stored_options = [];

function sanitize_text_field($value) { return trim((string)$value); }
function absint($value) { return abs((int)$value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_date($format) { return '26'; }
function update_post_meta($post_id, $key, $value) { global $stored_meta; $stored_meta[$post_id][$key] = $value; return true; }
function update_option($key, $value, $autoload = null) { global $stored_options; $stored_options[$key] = $value; return true; }
function wp_schedule_single_event() { return true; }
function admin_url($path = '') { return 'https://www.yoleotard.com/wp-admin/' . ltrim($path, '/'); }
function add_query_arg(array $args, $url) { return $url . '?' . http_build_query($args); }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-western-bid.php';

function fail_invoice_reference($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$service = new YO_Checkout_Western_Bid_Service([
    'settings' => function() { return ['western_bid_login' => 'merchant', 'western_bid_secret_key' => 'secret']; },
    'next_keycrm_order_id' => function() { return 666; },
]);

$result = $service->start_payment(22307);
if ($result instanceof WP_Error) fail_invoice_reference($result->get_error_message());
if (($result['invoiceId'] ?? '') !== '22307/26-666') fail_invoice_reference('Unexpected Western Bid invoice reference');
if (strpos((string)($result['pageUrl'] ?? ''), 'invoice=22307%2F26-666') === false) fail_invoice_reference('Invoice slash was not encoded exactly once');
if (($stored_meta[22307]['western_bid_expected_keycrm_order_id'] ?? 0) !== 666) fail_invoice_reference('Expected KeyCRM ID was not stored');
if (($stored_options['yo_western_bid_order_22307/26-666'] ?? 0) !== 22307) fail_invoice_reference('Invoice mapping was not stored');

$failed_service = new YO_Checkout_Western_Bid_Service([
    'settings' => function() { return ['western_bid_login' => 'merchant', 'western_bid_secret_key' => 'secret']; },
    'next_keycrm_order_id' => function() { return new WP_Error('keycrm_down', 'KeyCRM is unavailable'); },
]);
$failed = $failed_service->start_payment(22308);
if (!($failed instanceof WP_Error) || $failed->get_error_code() !== 'keycrm_down') {
    fail_invoice_reference('KeyCRM lookup failure must stop Western Bid payment startup');
}

echo "Western Bid invoice reference tests passed.\n";
