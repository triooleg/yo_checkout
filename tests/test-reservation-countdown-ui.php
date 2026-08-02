<?php

$source = file_get_contents(dirname(__DIR__) . '/assets/yo-checkout.js');
if ($source === false) {
    fwrite(STDERR, "Unable to read checkout JavaScript.\n");
    exit(1);
}

function js_function_source($source, $function, $next_function) {
    $start = strpos($source, 'function ' . $function . '(');
    $end = strpos($source, 'function ' . $next_function . '(', $start === false ? 0 : $start + 1);
    if ($start === false || $end === false || $end <= $start) {
        fwrite(STDERR, "Unable to locate JavaScript function boundaries for {$function}.\n");
        exit(1);
    }
    return substr($source, $start, $end - $start);
}

$refresh = js_function_source($source, 'refreshReservedCards', 'reserveProduct');
foreach (['const requestId = ++reservationRefreshRequestId;', 'if(requestId !== reservationRefreshRequestId) return;'] as $needle) {
    if (strpos($refresh, $needle) === false) {
        fwrite(STDERR, "Missing latest-response safeguard in refreshReservedCards(): {$needle}\n");
        exit(1);
    }
}

$timed_state = js_function_source($source, 'applyReservedState', 'applyInvoiceReservedState');
foreach (["card.dataset.yoServerUnavailable === '1'", 'clearInvoiceReservedState(card);'] as $needle) {
    if (strpos($timed_state, $needle) === false) {
        fwrite(STDERR, "Missing timed-badge precedence safeguard in applyReservedState(): {$needle}\n");
        exit(1);
    }
}

$availability = js_function_source($source, 'applyServerAvailabilityRowsToProductCards', 'refreshProductCardAvailability');
$timed_badge_guard = "card.classList.contains('yo-reserved-card') && card.querySelector('.yo-reserved-overlay-badge[data-yo-reservation-expires]')";
if (strpos($availability, $timed_badge_guard) === false) {
    fwrite(STDERR, "Availability rendering can cover an active reservation countdown.\n");
    exit(1);
}

foreach ([
    'setInterval(refreshReservedCards, RESERVATION_REFRESH_INTERVAL_MS)',
    "window.addEventListener('focus', refreshReservedCards)",
    "document.addEventListener('visibilitychange'",
] as $needle) {
    if (strpos($source, $needle) === false) {
        fwrite(STDERR, "Missing cross-browser refresh trigger: {$needle}\n");
        exit(1);
    }
}

if (!preg_match('/RESERVATION_REFRESH_INTERVAL_MS\s*=\s*(\d+)/', $source, $match) || (int) $match[1] > 5000) {
    fwrite(STDERR, "Cross-browser reservation refresh must run at least every five seconds.\n");
    exit(1);
}

echo "Reservation countdown UI regression checks passed.\n";