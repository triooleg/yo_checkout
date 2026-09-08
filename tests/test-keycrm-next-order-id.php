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
$response_body = ['data' => [['id' => 665]]];

function wp_remote_request($url, $args) {
    global $requested_url, $response_body;
    $requested_url = $url;
    return ['response' => ['code' => 200], 'body' => json_encode($response_body)];
}
function wp_remote_retrieve_body($response) { return $response['body']; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_json_encode($value) { return json_encode($value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function absint($value) { return abs((int)$value); }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-keycrm.php';

function fail_next_order_id($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$service = new YO_Checkout_KeyCRM_Service([
    'settings' => function() { return ['keycrm_token' => 'test-token']; },
]);

$next_id = $service->next_order_id();
if ($next_id !== 666) fail_next_order_id('KeyCRM next order ID was not calculated as latest ID plus one');
if ($requested_url !== 'https://openapi.keycrm.app/v1/order?limit=1&sort=-id') {
    fail_next_order_id('KeyCRM latest-order query is incorrect');
}

$response_body = ['data' => []];
$missing = $service->next_order_id();
if (!($missing instanceof WP_Error) || $missing->get_error_code() !== 'keycrm_next_order_missing') {
    fail_next_order_id('Empty KeyCRM response must stop invoice prediction');
}

echo "KeyCRM next order ID tests passed.\n";
