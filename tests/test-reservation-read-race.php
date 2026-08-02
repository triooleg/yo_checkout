<?php

$plugin = file_get_contents(dirname(__DIR__) . '/yoleotard-checkout-invoice.php');
if ($plugin === false) {
    fwrite(STDERR, "Unable to read plugin source.\n");
    exit(1);
}

function method_source($source, $method, $next_method) {
    $start = strpos($source, 'function ' . $method . '(');
    $end = strpos($source, 'function ' . $next_method . '(', $start === false ? 0 : $start + 1);
    if ($start === false || $end === false || $end <= $start) {
        fwrite(STDERR, $method . "() boundaries were not found.\n");
        exit(1);
    }
    return substr($source, $start, $end - $start);
}

$clean_source = method_source($plugin, 'clean_reservations', 'reservation_key_for_title');
if (strpos($clean_source, 'update_option(') !== false) {
    fwrite(STDERR, "Reservation reads must not overwrite the shared option.\n");
    exit(1);
}

$ensure_source = method_source($plugin, 'ensure_reservation_for_item', 'ensure_reservation_for_title');
if (strpos($ensure_source, 'update_option(') === false) {
    fwrite(STDERR, "ensure_reservation_for_item() must persist reservation mutations.\n");
    exit(1);
}

$release_source = method_source($plugin, 'ajax_release_reservation', 'ajax_validate_cart_items');
if (strpos($release_source, 'update_option(') === false) {
    fwrite(STDERR, "ajax_release_reservation() must persist reservation mutations.\n");
    exit(1);
}

echo "Reservation read race regression checks passed.\n";