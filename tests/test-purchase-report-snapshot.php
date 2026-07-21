<?php

define('ABSPATH', __DIR__);

$GLOBALS['snapshot_meta'] = [];

function absint($value) { return abs((int)$value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string)$value)); }
function sanitize_text_field($value) { return trim((string)$value); }
function sanitize_email($value) { return trim((string)$value); }
function current_time($type) { return '2026-07-21 21:15:00'; }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_slash($value) { return is_string($value) ? addslashes($value) : $value; }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function update_post_meta($post_id, $key, $value) {
    $GLOBALS['snapshot_meta'][$post_id][$key] = wp_unslash($value);
    return true;
}
function get_post_meta($post_id, $key, $single = false) {
    return $GLOBALS['snapshot_meta'][$post_id][$key] ?? '';
}
function esc_html($value) { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-purchase-report.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}
function assert_true($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

$order_data = [
    'full_name' => 'Snapshot Buyer',
    'email' => 'snapshot@example.com',
    'phone' => '123456',
    'country' => 'United Kingdom',
    'price_eur' => '100.00',
    'cart_items_json' => '[{"title":"New \\"Test\\" leotard"}]',
];
$items = [[
    'title' => 'New "Test" leotard',
    'product_id' => 'new-test-leotard',
    'price_eur' => '100.00',
]];
$service = new YO_Checkout_Purchase_Report_Service([
    'get_order_data' => function($local_id) use ($order_data) { return $order_data; },
    'cart_items_from_order_data' => function($data) use ($items) { return $items; },
]);

$service->save_snapshot(501, 'card_payment_selected', ['payment_method_choice' => 'card', 'terms_confirmed' => true]);
$stored = get_post_meta(501, 'checkout_snapshot_json', true);
$decoded = json_decode($stored, true);
assert_true(is_array($decoded), 'Snapshot must remain valid JSON after WordPress unslashes meta values');
assert_same('New "Test" leotard', $decoded['items'][0]['title'], 'Snapshot must preserve quotes in product titles');
assert_same('card_payment_selected', $decoded['stage'], 'Snapshot stage must be preserved');

$method = new ReflectionMethod(YO_Checkout_Purchase_Report_Service::class, 'snapshot_html');
$legacy = '{"stage":"card_payment_selected","items":[{"title":"New "Test" leotard"}]}';
$html = $method->invoke($service, $legacy);
assert_true(strpos($html, '\\&quot;') === false, 'Legacy invalid JSON must not be encoded a second time with visible backslashes');
assert_true(strpos($html, 'New &quot;Test&quot; leotard') !== false, 'Legacy snapshot text must remain readable');

echo "Purchase report snapshot tests passed.\n";
