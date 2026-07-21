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
    101 => ['post_type' => 'yo_invoice_order', 'post_status' => 'publish'],
    102 => ['post_type' => 'yo_invoice_order', 'post_status' => 'publish'],
    103 => ['post_type' => 'yo_invoice_order', 'post_status' => 'publish'],
];
$GLOBALS['test_meta'] = [
    101 => ['paid' => ['0']],
    102 => ['paid' => ['0'], 'payment_attempt_number' => ['1']],
    103 => ['paid' => ['1']],
];
$GLOBALS['next_post_id'] = 200;

function absint($value) { return abs((int)$value); }
function sanitize_text_field($value) { return trim((string)$value); }
function is_wp_error($value) { return $value instanceof WP_Error; }
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
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['test_meta'][$post_id][$key] = [$value];
    return true;
}

require_once dirname(__DIR__) . '/includes/class-yo-checkout-submission.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$service = new YO_Checkout_Submission_Service('yo_invoice_order');

$first = $service->create(101, 'Test Buyer', 'yosub_first');
assert_same(200, $first, 'First form submission must create a new local order');
assert_same('yosub_first', get_post_meta($first, 'checkout_submission_id', true), 'Submission ID must be stored');
assert_same(1, get_post_meta($first, 'checkout_submission_number', true), 'First submission number must be 1');
assert_same(101, get_post_meta($first, 'checkout_submission_root_id', true), 'First submission root must be the previous local draft');
assert_same(101, get_post_meta($first, 'checkout_previous_local_id', true), 'Previous local ID must be lineage only');
assert_same(200, get_post_meta(101, 'checkout_superseded_by', true), 'Previous ordinary draft must be closed for reuse');

$second = $service->create($first, 'Test Buyer', 'yosub_second');
assert_same(201, $second, 'Second form submission must receive another local order');
assert_same(2, get_post_meta($second, 'checkout_submission_number', true), 'Second submission number must be 2');
assert_same(101, get_post_meta($second, 'checkout_submission_root_id', true), 'Submission chain must retain its server root');
assert_same(201, get_post_meta($first, 'checkout_superseded_by', true), 'First submission must remain as immutable history');

$after_payment_attempt = $service->create(102, 'Test Buyer', 'yosub_after_payment');
assert_same(202, $after_payment_attempt, 'A new submission after a payment attempt must still get a new local order');
assert_same('', get_post_meta(102, 'checkout_superseded_by', true), 'Payment attempt must remain active and immutable');

$after_paid = $service->create(103, 'Test Buyer', 'yosub_after_paid');
assert_same(203, $after_paid, 'A new purchase after a paid order must get a new local order');
assert_same('', get_post_meta(103, 'checkout_superseded_by', true), 'Paid order must never be superseded');

$missing = $service->create($second, 'Test Buyer', '');
assert_same('checkout_submission_id_missing', $missing->get_error_code(), 'Missing submission ID must be rejected');

echo "Checkout submission service tests passed.\n";
