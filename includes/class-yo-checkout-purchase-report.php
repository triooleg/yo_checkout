<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Purchase_Report_Service {
    private $callbacks = [];

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
    }

    private function call($name, ...$args) {
        return isset($this->callbacks[$name]) && is_callable($this->callbacks[$name])
            ? call_user_func_array($this->callbacks[$name], $args)
            : null;
    }

    public function save_snapshot($local_id, $stage, array $extra = []) {
        $local_id = absint($local_id);
        if (!$local_id) return;

        $data = $this->call('get_order_data', $local_id);
        if (!is_array($data)) $data = [];
        $items = $this->call('cart_items_from_order_data', $data);
        if (!is_array($items) || !$items) {
            $items = [[
                'title' => sanitize_text_field($data['title'] ?? ''),
                'price_eur' => sanitize_text_field($data['price_eur'] ?? ''),
                'product_id' => sanitize_text_field($data['product_id'] ?? ''),
            ]];
        }

        $snapshot = [
            'stage' => sanitize_key($stage),
            'stage_at' => current_time('mysql'),
            'customer' => [
                'full_name' => sanitize_text_field($data['full_name'] ?? ''),
                'email' => sanitize_email($data['email'] ?? ''),
                'phone' => sanitize_text_field($data['phone'] ?? ''),
                'address' => sanitize_text_field($data['address'] ?? ''),
                'additional_address' => sanitize_text_field($data['additional_address'] ?? ''),
                'city' => sanitize_text_field($data['city'] ?? ''),
                'zip_code' => sanitize_text_field($data['zip_code'] ?? ''),
                'country' => sanitize_text_field($data['country'] ?? ''),
            ],
            'order' => [
                'local_id' => $local_id,
                'keycrm_order_id' => sanitize_text_field(get_post_meta($local_id, 'order_id', true)),
                'buyer_id' => sanitize_text_field(get_post_meta($local_id, 'buyer_id', true)),
                'payment_provider' => sanitize_text_field(get_post_meta($local_id, 'payment_provider', true)),
                'payment_type' => sanitize_text_field(get_post_meta($local_id, 'payment_type', true)),
                'payment_method_choice' => sanitize_text_field($extra['payment_method_choice'] ?? get_post_meta($local_id, 'checkout_payment_method_choice', true)),
                'terms_confirmed' => !empty($extra['terms_confirmed']) || get_post_meta($local_id, 'checkout_terms_confirmed', true) === '1',
                'paid' => get_post_meta($local_id, 'paid', true) === '1',
                'paid_at' => sanitize_text_field(get_post_meta($local_id, 'paid_at', true)),
            ],
            'totals' => [
                'price_eur' => sanitize_text_field($data['price_eur'] ?? ''),
                'original_price_eur' => sanitize_text_field($data['original_price_eur'] ?? ''),
                'discount_eur' => sanitize_text_field($data['discount_eur'] ?? ''),
                'shipping_cost_eur' => sanitize_text_field($data['shipping_cost_eur'] ?? ''),
                'card_fee_amount' => sanitize_text_field($data['card_fee_amount'] ?? ''),
                'card_total_amount' => sanitize_text_field($data['card_total_amount'] ?? ''),
                'bank_total_amount' => sanitize_text_field($data['bank_total_amount'] ?? ''),
            ],
            'items' => $this->sanitize_items($items),
        ];

        if (!empty($extra['payment_method_choice'])) update_post_meta($local_id, 'checkout_payment_method_choice', sanitize_text_field($extra['payment_method_choice']));
        if (!empty($extra['terms_confirmed'])) update_post_meta($local_id, 'checkout_terms_confirmed', '1');
        update_post_meta($local_id, 'checkout_stage', sanitize_key($stage));
        update_post_meta($local_id, 'checkout_stage_updated_at', $snapshot['stage_at']);
        update_post_meta($local_id, 'checkout_snapshot_json', wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function render_page($order_post_type) {
        if (!current_user_can('manage_options')) wp_die('Access denied');

        $status = sanitize_key($_GET['status'] ?? 'all');
        $orders = get_posts([
            'post_type' => $order_post_type,
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        echo '<div class="wrap yo-purchase-report"><h1>YOleotard Purchases Report</h1>';
        echo '<p>This table shows checkout drafts, invoice orders, and paid card orders saved by the checkout plugin.</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=yo-checkout-invoice')) . '">Checkout settings</a></p>';
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['Created','Updated','Order','Status','Payment','Customer','Contacts','Products','Totals','Shipping','Provider IDs','Saved data'] as $head) {
            echo '<th>' . esc_html($head) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $shown = 0;
        foreach ($orders as $order) {
            $row = $this->row_data($order);
            if (!$this->matches_status($row, $status)) continue;
            $shown++;
            echo '<tr>';
            echo '<td>' . esc_html($order->post_date) . '</td>';
            echo '<td>' . esc_html(get_post_meta($order->ID, 'checkout_stage_updated_at', true) ?: $order->post_modified) . '</td>';
            echo '<td><strong>#' . esc_html($row['keycrm_order_id'] ?: $order->ID) . '</strong><br><code>local #' . esc_html($order->ID) . '</code></td>';
            echo '<td>' . $this->status_badge($row['status']) . '<br><small>' . esc_html($row['stage']) . '</small></td>';
            echo '<td>' . esc_html($row['payment']) . '<br><small>' . esc_html($row['choice']) . '</small></td>';
            echo '<td>' . esc_html($row['full_name']) . '<br><small>' . esc_html($row['country']) . '</small></td>';
            echo '<td>' . esc_html($row['email']) . '<br>' . esc_html($row['phone']) . '</td>';
            echo '<td>' . $this->products_html($row['items']) . '</td>';
            echo '<td>' . $this->totals_html($row) . '</td>';
            echo '<td>' . esc_html($row['shipping']) . '</td>';
            echo '<td>' . $this->provider_ids_html($row) . '</td>';
            echo '<td>' . $this->snapshot_html($row['snapshot']) . '</td>';
            echo '</tr>';
        }

        if (!$shown) echo '<tr><td colspan="12">No purchases found.</td></tr>';
        echo '</tbody></table></div>';
    }

    private function row_data($order) {
        $data = $this->call('get_order_data', $order->ID);
        if (!is_array($data)) $data = [];
        $items = $this->call('cart_items_from_order_data', $data);
        if (!is_array($items) || !$items) $items = [['title' => $data['title'] ?? '', 'price_eur' => $data['price_eur'] ?? '', 'product_id' => $data['product_id'] ?? '']];

        $provider = get_post_meta($order->ID, 'payment_provider', true);
        $type = get_post_meta($order->ID, 'payment_type', true);
        $paid = get_post_meta($order->ID, 'paid', true) === '1';
        $status = $paid ? 'paid' : (get_post_meta($order->ID, 'bank_invoice_created', true) === '1' ? 'invoice' : 'draft');

        return [
            'status' => $status,
            'stage' => get_post_meta($order->ID, 'checkout_stage', true) ?: ($paid ? 'paid' : 'draft'),
            'choice' => get_post_meta($order->ID, 'checkout_payment_method_choice', true),
            'payment' => trim(($provider ?: 'not selected') . ($type ? ' / ' . $type : '')),
            'keycrm_order_id' => get_post_meta($order->ID, 'order_id', true),
            'full_name' => $data['full_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'country' => $data['country'] ?? '',
            'items' => $this->sanitize_items($items),
            'price_eur' => $data['price_eur'] ?? '',
            'card_total_amount' => $data['card_total_amount'] ?? '',
            'bank_total_amount' => $data['bank_total_amount'] ?? '',
            'shipping' => ($data['shipping_cost_eur'] ?? '') !== '' ? 'EUR ' . $data['shipping_cost_eur'] : '',
            'mono_invoice_id' => get_post_meta($order->ID, 'mono_invoice_id', true),
            'western_bid_invoice' => get_post_meta($order->ID, 'western_bid_invoice', true),
            'bank_invoice_url' => get_post_meta($order->ID, 'invoice_pdf_url', true) ?: get_post_meta($order->ID, 'invoice_html_url', true),
            'snapshot' => get_post_meta($order->ID, 'checkout_snapshot_json', true),
        ];
    }

    private function sanitize_items($items) {
        $out = [];
        foreach ((array)$items as $item) {
            if (!is_array($item)) continue;
            $out[] = [
                'title' => sanitize_text_field($item['title'] ?? ''),
                'product_id' => sanitize_text_field($item['product_id'] ?? ($item['feed_id'] ?? '')),
                'price_eur' => sanitize_text_field($item['price_eur'] ?? ''),
            ];
        }
        return $out;
    }

    private function matches_status($row, $status) {
        if ($status === 'paid') return $row['status'] === 'paid';
        if ($status === 'invoice') return $row['status'] === 'invoice';
        if ($status === 'draft') return $row['status'] === 'draft';
        return true;
    }

    private function status_badge($status) {
        $labels = ['paid' => 'Paid', 'invoice' => 'Invoice', 'draft' => 'Draft'];
        $colors = ['paid' => '#147a3d', 'invoice' => '#1d4ed8', 'draft' => '#6b7280'];
        $status = isset($labels[$status]) ? $status : 'draft';
        return '<span style="display:inline-block;border-radius:999px;padding:3px 9px;background:' . esc_attr($colors[$status]) . ';color:#fff;font-weight:600;">' . esc_html($labels[$status]) . '</span>';
    }

    private function products_html($items) {
        if (!$items) return '-';
        $out = '<ol style="margin:0;padding-left:18px;">';
        foreach ($items as $item) {
            $out .= '<li>' . esc_html($item['title']) . '<br><small><code>' . esc_html($item['product_id']) . '</code> EUR ' . esc_html($item['price_eur']) . '</small></li>';
        }
        return $out . '</ol>';
    }

    private function totals_html($row) {
        $parts = [];
        if ($row['price_eur'] !== '') $parts[] = 'Items EUR ' . $row['price_eur'];
        if ($row['card_total_amount'] !== '') $parts[] = 'Card EUR ' . $row['card_total_amount'];
        if ($row['bank_total_amount'] !== '') $parts[] = 'Invoice EUR ' . $row['bank_total_amount'];
        return $parts ? esc_html(implode(' | ', $parts)) : '-';
    }

    private function provider_ids_html($row) {
        $out = [];
        if ($row['mono_invoice_id']) $out[] = '<code>Mono: ' . esc_html($row['mono_invoice_id']) . '</code>';
        if ($row['western_bid_invoice']) $out[] = '<code>WB: ' . esc_html($row['western_bid_invoice']) . '</code>';
        if ($row['bank_invoice_url']) $out[] = '<a href="' . esc_url($row['bank_invoice_url']) . '" target="_blank" rel="noopener">Invoice</a>';
        return $out ? implode('<br>', $out) : '-';
    }

    private function snapshot_html($snapshot) {
        if (!$snapshot) return '-';
        $decoded = json_decode((string)$snapshot, true);
        $label = is_array($decoded) && !empty($decoded['stage_at']) ? $decoded['stage_at'] : 'Saved snapshot';
        return '<details><summary>' . esc_html($label) . '</summary><pre style="white-space:pre-wrap;max-width:420px;">' . esc_html(wp_json_encode($decoded ?: $snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre></details>';
    }
}
