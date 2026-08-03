<?php

$css = file_get_contents(dirname(__DIR__) . '/assets/yo-checkout.css');
if ($css === false) {
    fwrite(STDERR, "Unable to read checkout CSS.\n");
    exit(1);
}

$selector = '.yo-invoice-reserved-card .yo-invoice-reserved-buy-btn > [uk-icon*="play-circle"]';
if (strpos($css, $selector) === false) {
    fwrite(STDERR, "Reserved-card play-circle suppression selector is missing.\n");
    exit(1);
}

if (!preg_match('/' . preg_quote($selector, '/') . '\s*\{[^}]*display\s*:\s*none\s*!important\s*;/s', $css)) {
    fwrite(STDERR, "Reserved-card play-circle must be hidden without affecting normal cards.\n");
    exit(1);
}

echo "Reserved preview icon regression checks passed.\n";