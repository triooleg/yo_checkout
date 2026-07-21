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

$GLOBALS['test_posts'] = [
    101 => ['post_type' => 'yo_invoice_order', 'post_status' => 'publish', 'post_title' => 'Draft'],
];
$GLOBALS['test_meta'] = [
    101 => [
        'title' => ['New "Test" leotard'],
        'full_name' => ['Test Buyer'],
        'price_eur' => ['100.00'],
        'cart_items_json' => ['[{"title":"New \\"Test\\" leotard","price_eur":"100.00"}]'],
        'shipping_selected_key' => ['nova-post'],
        'shipping_cost_eur' => ['27.00'],
        'order_id' => ['9001'],
        'buyer_id' => ['8001'],
        'keycrm_created' => ['1'],
        'paid' => ['0'],
        '_yo_checkout_access_token_hash' => ['must-not-copy'],
        'western_bid_invoice' => ['old-western-bid-invoice'],
        'mono_invoice_id' => ['old-monobank-invoice'],
        'checkout_snapshot_json' => ['old-snapshot'],
        'checkout_stage' => ['card_payment_selected'],
    ],
];
$GLOBALS['next_post_id'] = 200;

function absint($value) { return abs((int)$value); }
function sanitize_text_field($value) { return trim((string)$value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function current_time($type) { return '2026-07-21 21:00:00'; }
function wp_slash($value) { return is_array($value) ? array_map('wp_slash', $value) : addslashes((string)$value); }
function get_post_type($post_id) { return $GLOBALS['test_posts'][$post_id]['post_type'] ?? ''; }
function wp_insert_post($post) {
    $id = $GLOBALS['next_post_id']++;
    $GLOBALS['test_posts'][$id] = $post;
    return $id;
}
function get_post_meta($post_id, $key, $single = false) {
    $values = $GLOBALS['test_meta'][$post_id][$key] ?? [];
    return $single ? ($values[0] ?? '') : $values;
}
function add_post_meta($post_id, $key, $value) {
    $GLOBALS['test_meta'][$post_id][$key][] = is_string($value) ? stripslashes($value) : $value;
    return true;
}
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['test_meta'][$post_id][$key] = [$value];
    return true;
}
function get_posts($args) {
    $ids = [];
    foreach ($GLOBALS['test_posts'] as $id => $post) {
        if (($post['post_type'] ?? '') !== ($args['post_type'] ?? '')) continue;
        if (($post['post_status'] ?? '') !== ($args['post_status'] ?? 'publish')) continue;
        if ((string)get_post_meta($id, $args['meta_key'], true) !== (string)$args['meta_value']) continue;
        $ids[] = $id;
    }
    return $ids;
}

require_once dirname(__DIR__) . '/includes/class-yo-checkout-payment-attempt.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}
function assert_empty_meta($post_id, $key, $message) {
    assert_same('', get_post_meta($post_id, $key, true), $message);
}

$service = new YO_Checkout_Payment_Attempt_Service('yo_invoice_order');
$first = $service->create(101);
assert_same(200, $first, 'First attempt must create a new local order');
assert_same(1, get_post_meta($first, 'payment_attempt_number', true), 'First attempt number must be 1');
assert_same(101, get_post_meta($first, 'payment_attempt_root_id', true), 'First attempt root must be the original draft');
assert_same(101, get_post_meta($first, 'payment_attempt_source_id', true), 'First attempt source must be the original draft');
assert_same('9001', get_post_meta($first, 'order_id', true), 'Existing KeyCRM order may be reused by the new attempt');
assert_same('nova-post', get_post_meta($first, 'shipping_selected_key', true), 'Selected shipping must be copied');
assert_same(get_post_meta(101, 'cart_items_json', true), get_post_meta($first, 'cart_items_json', true), 'Copied cart JSON must survive WordPress meta unslashing unchanged');
assert_empty_meta($first, '_yo_checkout_access_token_hash', 'Guest access token hash must not be copied');
assert_empty_meta($first, 'western_bid_invoice', 'Previous Western Bid invoice must not be copied');
assert_empty_meta($first, 'mono_invoice_id', 'Previous Monobank invoice must not be copied');
assert_empty_meta($first, 'checkout_snapshot_json', 'Previous snapshot must not be copied');
assert_empty_meta($first, 'checkout_stage', 'Previous checkout stage must not be copied');

$second = $service->create(101);
assert_same(201, $second, 'A retry from the original draft must create another local order');
assert_same(2, get_post_meta($second, 'payment_attempt_number', true), 'Second attempt number must be 2');
assert_same(101, get_post_meta($second, 'payment_attempt_root_id', true), 'Second attempt must share the original root');

$third = $service->create($first);
assert_same(202, $third, 'A retry from a previous attempt must create another local order');
assert_same(3, get_post_meta($third, 'payment_attempt_number', true), 'Third attempt number must be 3');
assert_same(101, get_post_meta($third, 'payment_attempt_root_id', true), 'Chained retry must retain the original root');
assert_same($first, get_post_meta($third, 'payment_attempt_source_id', true), 'Chained retry must record its direct source');

update_post_meta($third, 'paid', '1');
$blocked = $service->create($third);
assert_same(true, $blocked instanceof WP_Error, 'A paid attempt must not be cloned');
assert_same('payment_attempt_source_paid', $blocked->get_error_code(), 'Paid attempt rejection must use the expected error code');

echo "Payment attempt service tests passed.\n";
