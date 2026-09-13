<?php

define('ABSPATH', __DIR__);

class WP_Error {
    private $code;
    private $message;

    public function __construct($code, $message) {
        $this->code = $code;
        $this->message = $message;
    }

    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

$stored_meta = [];
$stored_options = [];
$remote_payload = [];

function absint($value) { return abs((int)$value); }
function sanitize_text_field($value) { return trim((string)$value); }
function get_post_meta($post_id, $key, $single = false) { global $stored_meta; return $stored_meta[$post_id][$key] ?? ''; }
function update_post_meta($post_id, $key, $value) { global $stored_meta; $stored_meta[$post_id][$key] = $value; return true; }
function update_option($key, $value, $autoload = null) { global $stored_options; $stored_options[$key] = $value; return true; }
function get_option($key) { global $stored_options; return $stored_options[$key] ?? false; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_date($format) { return '26'; }
function wp_json_encode($value) { return json_encode($value); }
function home_url($path = '') { return 'https://www.yoleotard.com' . $path; }
function rest_url($path = '') { return 'https://www.yoleotard.com/wp-json/' . ltrim($path, '/'); }
function add_query_arg(array $args, $url) { return $url . '?' . http_build_query($args); }
function wp_remote_post($url, array $args) { global $remote_payload; $remote_payload = json_decode($args['body'], true); return ['body'=>json_encode(['invoiceId'=>'mono-test-id','pageUrl'=>'https://pay.example/mono-test-id'])]; }
function wp_remote_retrieve_body($response) { return $response['body'] ?? ''; }
function wp_schedule_single_event() { return true; }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-monobank.php';

function fail_monobank_reference($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$service = new YO_Checkout_Monobank_Service([
    'settings' => function() { return ['mono_token_mode'=>'live', 'mono_token'=>'token']; },
    'get_order_data' => function() { return []; },
    'card_fee_data' => function() { return ['percent'=>'0', 'fee'=>'0.00', 'total'=>'310.00']; },
    'next_keycrm_order_id' => function() { return 666; },
], 'yoleotard/v1', 'yo_checkout_order');

$result = $service->start_payment(22307);
if ($result instanceof WP_Error) fail_monobank_reference($result->get_error_message());
if (($result['orderId'] ?? '') !== '22307/26-666') fail_monobank_reference('Unexpected Monobank order reference');
if (($remote_payload['merchantPaymInfo']['reference'] ?? '') !== '22307/26-666') fail_monobank_reference('Monobank merchant reference is incorrect');
if (strpos((string)($remote_payload['merchantPaymInfo']['destination'] ?? ''), '#22307/26-666') === false) fail_monobank_reference('Monobank destination does not contain the order reference');
if (($stored_meta[22307]['monobank_expected_keycrm_order_id'] ?? 0) !== 666) fail_monobank_reference('Expected KeyCRM ID was not stored');

$failed_service = new YO_Checkout_Monobank_Service([
    'settings' => function() { return ['mono_token_mode'=>'live', 'mono_token'=>'token']; },
    'get_order_data' => function() { return []; },
    'card_fee_data' => function() { return ['percent'=>'0', 'fee'=>'0.00', 'total'=>'310.00']; },
    'next_keycrm_order_id' => function() { return new WP_Error('keycrm_down', 'KeyCRM is unavailable'); },
], 'yoleotard/v1', 'yo_checkout_order');

$failed = $failed_service->start_payment(22308);
if (!($failed instanceof WP_Error) || $failed->get_error_code() !== 'keycrm_down') {
    fail_monobank_reference('KeyCRM lookup failure must stop Monobank payment startup');
}

echo "Monobank order reference tests passed.\n";
