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

$requested_url = '';
$requested_args = [];
$response_body = ['data' => [['id' => 665]]];
$response_code = 200;
$stored_options = [];
$stored_transients = [];
$local_order_id = 0;

function wp_remote_request($url, $args) {
    global $requested_url, $requested_args, $response_body, $response_code;
    $requested_url = $url;
    $requested_args = $args;
    return ['response' => ['code' => $response_code], 'body' => json_encode($response_body)];
}
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int)$value); }
function get_option($key, $default = false) { global $stored_options; return $stored_options[$key] ?? $default; }
function update_option($key, $value, $autoload = null) { global $stored_options; $stored_options[$key] = $value; return true; }
function get_transient($key) { global $stored_transients; return $stored_transients[$key] ?? false; }
function set_transient($key, $value, $expiration) { global $stored_transients; $stored_transients[$key] = $value; return true; }
function get_posts($args) { global $local_order_id; return $local_order_id ? [123] : []; }
function get_post_meta($post_id, $key, $single = false) { global $local_order_id; return $key === 'order_id' ? $local_order_id : ''; }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-keycrm.php';

function fail_next_order_id($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$service = new YO_Checkout_KeyCRM_Service([
    'settings' => function() { return ['keycrm_token' => 'test-token']; },
], 'yo_invoice_order');

$next_id = $service->next_order_id();
if ($next_id !== 666) fail_next_order_id('KeyCRM next order ID was not calculated as latest ID plus one');
if ($requested_url !== 'https://openapi.keycrm.app/v1/order?limit=1&sort=-id') {
    fail_next_order_id('KeyCRM latest-order query is incorrect');
}
if (array_key_exists('body', $requested_args)) {
    fail_next_order_id('KeyCRM GET request must not send a JSON request body');
}
if (($stored_options['yo_checkout_keycrm_latest_order_id'] ?? 0) !== 665) {
    fail_next_order_id('Successful KeyCRM lookup was not remembered');
}

$stored_transients = [];
$response_code = 429;
$response_body = ['data' => []];
$fallback = $service->next_order_id();
if ($fallback !== 666) {
    fail_next_order_id('Remembered KeyCRM ID must keep payment startup available during a temporary API error');
}

$stored_options = [];
$stored_transients = [];
$local_order_id = 670;
$local_fallback = $service->next_order_id();
if ($local_fallback !== 671) {
    fail_next_order_id('Latest locally confirmed KeyCRM order ID was not used as a fallback');
}

$local_order_id = 0;
$stored_options = [];
$stored_transients = [];
$response_code = 200;
$missing = $service->next_order_id();
if (!($missing instanceof WP_Error) || $missing->get_error_code() !== 'keycrm_next_order_missing') {
    fail_next_order_id('Missing API and local history must still stop an unverifiable prediction');
}

echo "KeyCRM next order ID tests passed.\n";
