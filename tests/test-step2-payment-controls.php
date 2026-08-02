<?php

$root = dirname(__DIR__);
$main = file_get_contents($root . '/yoleotard-checkout-invoice.php');
$js = file_get_contents($root . '/assets/yo-checkout.js');
$css = file_get_contents($root . '/assets/yo-checkout.css');

function fail_step2_controls($message) {
    fwrite(STDERR, $message . "\n");
    exit(1);
}

if (strpos($main, 'id="yo-accept-terms" class="uk-checkbox" type="checkbox" checked') === false) {
    fail_step2_controls('Step 2 terms checkbox must be checked by default.');
}
if (strpos($js, "if(t) t.checked=true; updateTermsButtons();") === false) {
    fail_step2_controls('Step 2 transition must keep the terms checkbox checked.');
}
if (strpos($js, "if(t) t.checked=false; updateTermsButtons();") !== false) {
    fail_step2_controls('Step 2 transition must not reset the terms checkbox.');
}
if (strpos($main, 'id="yo-pay-bank" class="uk-button uk-button-secondary yo-method-btn yo-bank-method-btn"') === false) {
    fail_step2_controls('Bank invoice button must use its visible secondary style.');
}
if (strpos($css, '.yo-bank-method-btn{') === false || strpos($css, '.yo-bank-method-btn:hover') === false) {
    fail_step2_controls('Bank invoice button base and interaction styles must exist.');
}

echo "Step 2 payment controls tests passed.\n";