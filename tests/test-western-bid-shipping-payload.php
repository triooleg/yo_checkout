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

function sanitize_text_field($value) { return trim((string)$value); }
function sanitize_email($value) { return trim((string)$value); }
if (!function_exists('mb_substr')) {
    function mb_substr($value, $start, $length = null) {
        return $length === null ? substr($value, $start) : substr($value, $start, $length);
    }
}
function update_post_meta($post_id, $key, $value) { return true; }
function admin_url($path = '') { return 'https://www.yoleotard.com/wp-admin/' . ltrim($path, '/'); }
function rest_url($path = '') { return 'https://www.yoleotard.com/wp-json/' . ltrim($path, '/'); }
function home_url($path = '') { return 'https://www.yoleotard.com/' . ltrim($path, '/'); }
function add_query_arg(array $args, $url) { return $url . (strpos($url, '?') === false ? '?' : '&') . http_build_query($args); }

require_once dirname(__DIR__) . '/includes/class-yo-checkout-western-bid.php';

function assert_same($expected, $actual, $message) {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assert_not_error($value, $message) {
    if ($value instanceof WP_Error) {
        fwrite(STDERR, $message . ': ' . $value->get_error_code() . ' - ' . $value->get_error_message() . "\n");
        exit(1);
    }
}

function item_lines_total(array $fields) {
    $total = 0.0;
    for ($index = 1; isset($fields['amount_' . $index]); $index++) {
        $total += (float)$fields['amount_' . $index] * (float)($fields['quantity_' . $index] ?? 1);
    }
    return number_format(round($total, 2), 2, '.', '');
}

$orders = [
    1001 => [
        'full_name' => 'Test Buyer',
        'email' => 'buyer@example.com',
        'phone' => '60122338378',
        'address' => 'Test address',
        'additional_address' => '',
        'country' => 'Malaysia',
        'city' => 'KL',
        'zip_code' => '43200',
        'price_eur' => '310.00',
    ],
    1002 => [
        'full_name' => 'Test Buyer',
        'email' => 'buyer@example.com',
        'phone' => '44000000000',
        'address' => 'Test address',
        'additional_address' => '',
        'country' => 'United Kingdom',
        'city' => 'London',
        'zip_code' => 'SW1A 1AA',
        'price_eur' => '1.00',
    ],
    1003 => [
        'full_name' => 'Promo Buyer',
        'email' => 'promo@example.com',
        'phone' => '44000000001',
        'address' => 'Promo address',
        'additional_address' => '',
        'country' => 'United Kingdom',
        'city' => 'London',
        'zip_code' => 'SW1A 1AB',
        'price_eur' => '90.00',
    ],
];

$fees = [
    1001 => ['product' => '310.00', 'shipping' => '30.10', 'fee' => '6.94', 'total' => '347.04'],
    1002 => ['product' => '1.00', 'shipping' => '0.00', 'fee' => '0.00', 'total' => '1.00'],
    1003 => ['product' => '90.00', 'shipping' => '5.00', 'fee' => '1.94', 'total' => '96.94'],
];

$items = [
    1001 => [['title' => 'Pink Supernova', 'product_id' => 'pink-supernova', 'price_eur' => '310.00']],
    1002 => [['title' => 'Test leotard', 'product_id' => 'test-leotard', 'price_eur' => '1.00']],
    1003 => [['title' => 'Promo leotard', 'product_id' => 'promo-leotard', 'price_eur' => '100.00', 'promo_discount_eur' => '10.00']],
];

$service = new YO_Checkout_Western_Bid_Service([
    'settings' => function() {
        return ['western_bid_login' => 'merchant', 'western_bid_secret_key' => 'secret', 'western_bid_currency' => 'EUR', 'western_bid_gate' => 'stripe.com'];
    },
    'get_order_data' => function($local_id) use ($orders) { return $orders[$local_id]; },
    'card_fee_data' => function($local_id) use ($fees) { return $fees[$local_id]; },
    'cart_items_from_order_data' => function($order) use ($orders, $items) {
        foreach ($orders as $local_id => $candidate) {
            if ($candidate === $order) return $items[$local_id];
        }
        return [];
    },
    'country_to_iso2' => function($country) { return $country === 'Malaysia' ? 'MY' : 'GB'; },
    'clean_product_title_for_display' => function($title) { return $title; },
    'append_checkout_debug_log' => function() {},
    'rest_namespace' => function() { return 'yo-checkout/v1'; },
]);

$invoice = 'YO-WB-1001-1234567890';
$shipping_fields = $service->purchase_fields(1001, $invoice);
assert_not_error($shipping_fields, 'Shipping-enabled payload should be created');
assert_same('316.94', $shipping_fields['amount'], 'Western Bid amount must exclude delivery');
assert_same('30.10', $shipping_fields['shipping'], 'Delivery must be sent through the Western Bid shipping field');
assert_same('316.94', item_lines_total($shipping_fields), 'Western Bid amount must equal item line totals');
assert_same('347.04', number_format((float)$shipping_fields['amount'] + (float)$shipping_fields['shipping'], 2, '.', ''), 'Provider total must match the local card total');
assert_same(md5('merchant' . 'secret' . '316.94' . $invoice), $shipping_fields['wb_hash'], 'Western Bid hash must use amount excluding delivery');

$no_shipping_invoice = 'YO-WB-1002-1234567891';
$no_shipping_fields = $service->purchase_fields(1002, $no_shipping_invoice);
assert_not_error($no_shipping_fields, 'Shipping-disabled payload should be created');
assert_same('1.00', $no_shipping_fields['amount'], 'Shipping-disabled amount must remain unchanged');
assert_same('0.00', $no_shipping_fields['shipping'], 'Shipping-disabled payload must send zero delivery');
assert_same('1.00', item_lines_total($no_shipping_fields), 'Shipping-disabled item lines must match amount');

$promo_invoice = 'YO-WB-1003-1234567892';
$promo_fields = $service->purchase_fields(1003, $promo_invoice);
assert_not_error($promo_fields, 'Promo and shipping payload should be created');
assert_same('91.94', $promo_fields['amount'], 'Western Bid amount must include the discounted product and service fee, but exclude delivery');
assert_same('5.00', $promo_fields['shipping'], 'Promo order delivery must be sent separately');
assert_same('90.00', $promo_fields['amount_1'], 'Promo discount must reduce the product line amount');
assert_same('91.94', item_lines_total($promo_fields), 'Promo item lines must match Western Bid amount');
assert_same('96.94', number_format((float)$promo_fields['amount'] + (float)$promo_fields['shipping'], 2, '.', ''), 'Promo provider total must match the local card total');

echo "Western Bid shipping payload tests passed.\n";