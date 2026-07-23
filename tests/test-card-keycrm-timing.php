<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/yoleotard-checkout-invoice.php');
$keycrm = file_get_contents($root . '/includes/class-yo-checkout-keycrm.php');

function fail_card_keycrm_timing($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

function source_section($source, $start, $end) {
    $start_pos = strpos($source, $start);
    if ($start_pos === false) fail_card_keycrm_timing('Start marker was not found: ' . $start);
    $end_pos = strpos($source, $end, $start_pos + strlen($start));
    if ($end_pos === false) fail_card_keycrm_timing('End marker was not found: ' . $end);
    return substr($source, $start_pos, $end_pos - $start_pos);
}

$card_start = source_section($main, 'public function ajax_start_card_payment()', 'private function start_monobank_payment');
if (strpos($card_start, 'ensure_keycrm_order_before_card_payment') !== false) {
    fail_card_keycrm_timing('Card payment start must not create a KeyCRM order before provider confirmation.');
}
foreach (["'order_id'", "'buyer_id'", "update_post_meta(\$local_id, 'keycrm_created', '0')"] as $required) {
    if (strpos($card_start, $required) === false) {
        fail_card_keycrm_timing('Card payment reset is missing: ' . $required);
    }
}

$paid_finalizer = source_section($main, 'private function process_successful_card_payment', 'private function send_paid_email');
if (strpos($paid_finalizer, 'ensure_keycrm_order_after_successful_card_payment') === false) {
    fail_card_keycrm_timing('Successful payment finalizer must create the KeyCRM order.');
}

$bank_invoice = source_section($main, 'public function ajax_create_bank_invoice()', 'public function ajax_check_payment_status');
if (strpos($bank_invoice, "ensure_keycrm_order(\$local_id, 'bank')") === false) {
    fail_card_keycrm_timing('Bank invoice flow must keep creating an unpaid KeyCRM order.');
}

if (strpos($main, 'ensure_keycrm_order_before_card_payment') !== false || strpos($keycrm, 'ensure_keycrm_order_before_card_payment') !== false) {
    fail_card_keycrm_timing('Obsolete pre-payment KeyCRM entry point is still present.');
}

echo "Card KeyCRM timing tests passed.\n";