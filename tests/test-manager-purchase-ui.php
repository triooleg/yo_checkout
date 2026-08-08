<?php

$root = dirname(__DIR__);
$js = file_get_contents($root . '/assets/yo-manager-purchase.js');
$css = file_get_contents($root . '/assets/yo-manager-purchase.css');

function require_fragment($source, $fragment, $message) {
    if (strpos($source, $fragment) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $fragment . "\n");
        exit(1);
    }
}

require_fragment($js, "label.textContent = 'Need help buying?'", 'The approved manager button label must remain unchanged.');
require_fragment($js, "'https://wa.me/'", 'WhatsApp must receive a prefilled product message.');
require_fragment($js, "'https://ig.me/m/'", 'Instagram Direct must be available.');
require_fragment($js, "'https://m.me/'", 'Facebook Messenger must use an m.me referral link.');
require_fragment($js, "'yo_product_' + product.id", 'Messenger links must carry the selected product ID.');
require_fragment($js, "navigator.clipboard?.writeText", 'Instagram should copy the product message for pasting into Direct.');
require_fragment($css, '.yo-manager-help-discount', 'Discounted product positioning must have a dedicated CSS rule.');
require_fragment($css, 'position:absolute', 'Discount help and menu placement must not change card height.');
require_fragment($css, 'bottom:calc(100% + 8px)', 'The manager menu must open upward.');
require_fragment($css, '.yo-manager-actions-host:not(.has-discount)', 'Regular products must keep help next to the Buy now button.');

echo "Manager purchase UI tests passed.\n";
