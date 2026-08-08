<?php

define('ABSPATH', __DIR__);

class WP_REST_Request {
    private $params;
    public function __construct(array $params) { $this->params = $params; }
    public function get_param($key) { return array_key_exists($key, $this->params) ? $this->params[$key] : null; }
}

class WP_REST_Response {
    private $data;
    private $status;
    public function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
    public function get_data() { return $this->data; }
    public function get_status() { return $this->status; }
}

function sanitize_text_field($value) { return trim((string)$value); }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-messenger.php';

function assert_true($value, $message) {
    if (!$value) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$body = '{"object":"page","entry":[]}';
$secret = 'test-app-secret';
$signature = 'sha256=' . hash_hmac('sha256', $body, $secret);

assert_true(YO_Checkout_Messenger_Service::verify_signature_value($body, $signature, $secret), 'A valid Meta SHA-256 signature must pass.');
assert_true(!YO_Checkout_Messenger_Service::verify_signature_value($body . 'x', $signature, $secret), 'A modified body must fail signature verification.');
assert_true(!YO_Checkout_Messenger_Service::verify_signature_value($body, $signature, ''), 'An empty App Secret must fail closed.');
assert_true(!YO_Checkout_Messenger_Service::verify_signature_value($body, 'sha1=abc', $secret), 'Legacy or malformed signatures must be rejected.');

assert_same('solar_prism_125_130', YO_Checkout_Messenger_Service::referral_product_id('yo_product_solar_prism_125_130'), 'The product ID must be decoded from a Messenger referral.');
assert_same('', YO_Checkout_Messenger_Service::referral_product_id('yo_product_solar<script>prism'), 'Unsafe referral characters must reject the referral.');
assert_same('', YO_Checkout_Messenger_Service::referral_product_id('other_solar_prism'), 'Unknown referral prefixes must be ignored.');

$service = new YO_Checkout_Messenger_Service([
    'settings' => function() { return ['messenger_verify_token' => 'verify-token']; },
]);
$verified = $service->verify_webhook(new WP_REST_Request([
    'hub.mode' => 'subscribe',
    'hub.verify_token' => 'verify-token',
    'hub.challenge' => '123456',
]));
assert_same(200, $verified->get_status(), 'Meta dot-style verification parameters must be accepted.');
assert_same(123456, $verified->get_data(), 'Webhook verification must return the numeric challenge without JSON string quotes.');
$forbidden = $service->verify_webhook(new WP_REST_Request([
    'hub_mode' => 'subscribe',
    'hub_verify_token' => 'wrong-token',
    'hub_challenge' => '123456',
]));
assert_same(403, $forbidden->get_status(), 'An incorrect verify token must be rejected.');

$main = file_get_contents(dirname(__DIR__) . '/yoleotard-checkout-invoice.php');
assert_true(strpos($main, "'messenger_app_secret' =>") === false, 'App Secret must never be present in frontend localization.');
assert_true(strpos($main, "'messenger_page_access_token' =>") === false, 'Page Access Token must never be present in frontend localization.');
assert_true(strpos($main, "'whatsappNumber' =>") !== false, 'Public manager channel details should be localized for the frontend.');

echo "Messenger service tests passed.\n";
