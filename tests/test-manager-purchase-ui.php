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
require_fragment($js, 'function legacyCopy(message)', 'Instagram and Messenger need a clipboard fallback for browsers without the Clipboard API.');
require_fragment($js, "existing.dataset.yoDiscounted", 'Cards must be re-evaluated when sale controls appear after the initial scan.');
require_fragment($js, "menuTitle.textContent = 'Choose a convenient way to contact us'", 'The channel menu must match the approved prototype heading.');
require_fragment($js, "Paste them into Messenger if the card is not added automatically", 'Messenger must explain the clipboard fallback when Meta does not deliver a referral.');
require_fragment($css, '.yo-manager-help-discount', 'Discounted product positioning must have a dedicated CSS rule.');
require_fragment($css, 'position:absolute', 'Discount help and menu placement must not change card height.');
require_fragment($css, 'bottom:calc(100% + 8px)', 'The manager menu must open upward.');
require_fragment($css, '.yo-manager-actions-host:not(.has-discount)', 'Regular products must keep help next to the Buy now button.');
require_fragment($css, '.yo-manager-actions-host.has-discount', 'Sale controls must provide an absolute-positioning host.');
require_fragment($css, 'grid-template-columns:repeat(3,minmax(0,1fr))', 'The three manager channels must be displayed in one compact row.');

echo "Manager purchase UI tests passed.\n";
