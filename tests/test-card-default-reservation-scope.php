<?php

$js = file_get_contents(dirname(__DIR__) . '/assets/yo-checkout.js');
if ($js === false) {
    fwrite(STDERR, "Could not read checkout script.\n");
    exit(1);
}

$required = [
    "function hasOwnCardDefaultPanel(card)",
    "card.classList.contains('uk-card-default')",
    "if(!hasOwnCardDefaultPanel(card))",
];
foreach ($required as $needle) {
    if (strpos($js, $needle) === false) {
        fwrite(STDERR, "Missing scoped Card Default reservation guard: {$needle}\n");
        exit(1);
    }
}

$start = strpos($js, 'function isInvoiceReservedCard(card)');
$end = $start === false ? false : strpos($js, 'function clearInvoiceReservedState', $start);
$function = ($start === false || $end === false) ? '' : substr($js, $start, $end - $start);
if ($function === '' || strpos($function, "querySelector('.uk-card-default')") !== false || strpos($function, 'yo-invoice-reserved-card') !== false) {
    fwrite(STDERR, "Invoice reservation must depend only on the product card's own Card Default style.\n");
    exit(1);
}

echo "Card Default reservation scope tests passed.\n";
