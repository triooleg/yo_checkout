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
                'payment_attempt_number' => absint(get_post_meta($local_id, 'payment_attempt_number', true)),
                'payment_attempt_root_id' => absint(get_post_meta($local_id, 'payment_attempt_root_id', true)),
                'payment_attempt_source_id' => absint(get_post_meta($local_id, 'payment_attempt_source_id', true)),
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
        update_post_meta($local_id, 'checkout_snapshot_json', wp_slash(wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
    }

    public function render_page($order_post_type) {
        if (!current_user_can('manage_options')) wp_die('Access denied');

        $status = $this->current_status_filter();
        $per_page = $this->current_per_page();
        $current_page = max(1, absint($_GET['purchase_page'] ?? 1));
        $query_args = [
            'post_type' => $order_post_type,
            'post_status' => 'publish',
            'posts_per_page' => $per_page,
            'paged' => $current_page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];
        $meta_query = $this->status_meta_query($status);
        if ($meta_query) $query_args['meta_query'] = $meta_query;
        $orders_query = new WP_Query($query_args);
        $orders = $orders_query->posts;
        $total_pages = max(1, (int)$orders_query->max_num_pages);
        $current_page = min($current_page, $total_pages);

        echo '<div class="wrap yo-purchase-report"><h1>YOleotard Purchases Report</h1>';
        echo '<p>This table shows checkout drafts, invoice orders, and paid card orders saved by the checkout plugin.</p>';
        echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=yo-checkout-invoice')) . '">Checkout settings</a></p>';
        $this->render_controls($status, $per_page, $current_page, $total_pages, (int)$orders_query->found_posts);
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['Created','Updated','Order','Status','Payment','Customer','Contacts','Products','Totals','Shipping','Provider IDs','Saved data'] as $head) {
            echo '<th>' . esc_html($head) . '</th>';
        }
        echo '</tr></thead><tbody>';

        $shown = 0;
        foreach ($orders as $order) {
            $row = $this->row_data($order);
            $shown++;
            echo '<tr>';
            echo '<td>' . esc_html($order->post_date) . '</td>';
            echo '<td>' . esc_html(get_post_meta($order->ID, 'checkout_stage_updated_at', true) ?: $order->post_modified) . '</td>';
            echo '<td><strong>#' . esc_html($row['keycrm_order_id'] ?: $order->ID) . '</strong><br><code>local #' . esc_html($order->ID) . '</code>' . $this->attempt_html($row) . '</td>';
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
        echo '</tbody></table>';
        $this->render_controls($status, $per_page, $current_page, $total_pages, (int)$orders_query->found_posts);
        echo '</div>';
        wp_reset_postdata();
    }

    private function current_status_filter() {
        $status = sanitize_key($_GET['status'] ?? 'all');
        return in_array($status, ['all', 'paid', 'invoice', 'draft'], true) ? $status : 'all';
    }

    private function current_per_page() {
        $per_page = absint($_GET['per_page'] ?? 10);
        return in_array($per_page, [10, 20, 50], true) ? $per_page : 10;
    }

    private function status_meta_query($status) {
        if ($status === 'paid') {
            return [['key' => 'paid', 'value' => '1']];
        }
        if ($status === 'invoice') {
            return [
                'relation' => 'AND',
                ['key' => 'bank_invoice_created', 'value' => '1'],
                [
                    'relation' => 'OR',
                    ['key' => 'paid', 'compare' => 'NOT EXISTS'],
                    ['key' => 'paid', 'value' => '1', 'compare' => '!='],
                ],
            ];
        }
        if ($status === 'draft') {
            return [
                'relation' => 'AND',
                [
                    'relation' => 'OR',
                    ['key' => 'paid', 'compare' => 'NOT EXISTS'],
                    ['key' => 'paid', 'value' => '1', 'compare' => '!='],
                ],
                [
                    'relation' => 'OR',
                    ['key' => 'bank_invoice_created', 'compare' => 'NOT EXISTS'],
                    ['key' => 'bank_invoice_created', 'value' => '1', 'compare' => '!='],
                ],
            ];
        }
        return [];
    }

    private function render_controls($status, $per_page, $current_page, $total_pages, $total_items) {
        $base_args = [
            'page' => 'yo-checkout-purchases',
            'status' => $status,
            'per_page' => $per_page,
        ];
        echo '<div class="tablenav top" style="display:flex;gap:16px;align-items:center;justify-content:space-between;margin:12px 0;">';
        echo '<div class="alignleft actions">';
        echo '<form method="get" action="' . esc_url(admin_url('admin.php')) . '" style="display:flex;gap:8px;align-items:center;">';
        echo '<input type="hidden" name="page" value="yo-checkout-purchases">';
        echo '<label>Status <select name="status">';
        foreach (['all' => 'All', 'paid' => 'Paid', 'invoice' => 'Invoice', 'draft' => 'Draft'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Per page <select name="per_page">';
        foreach ([10, 20, 50] as $value) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($per_page, $value, false) . '>' . esc_html((string)$value) . '</option>';
        }
        echo '</select></label>';
        submit_button('Apply', 'secondary', '', false);
        echo '</form>';
        echo '</div>';

        echo '<div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html(number_format_i18n($total_items)) . ' items</span> ';
        if ($total_pages > 1) {
            $prev_url = $current_page > 1 ? add_query_arg(array_merge($base_args, ['purchase_page' => $current_page - 1]), admin_url('admin.php')) : '';
            $next_url = $current_page < $total_pages ? add_query_arg(array_merge($base_args, ['purchase_page' => $current_page + 1]), admin_url('admin.php')) : '';
            echo $prev_url ? '<a class="button" href="' . esc_url($prev_url) . '">&lsaquo;</a> ' : '<span class="button disabled">&lsaquo;</span> ';
            echo '<span class="paging-input">' . esc_html($current_page) . ' of ' . esc_html($total_pages) . '</span> ';
            echo $next_url ? '<a class="button" href="' . esc_url($next_url) . '">&rsaquo;</a>' : '<span class="button disabled">&rsaquo;</span>';
        } else {
            echo '<span class="paging-input">1 of 1</span>';
        }
        echo '</div></div>';
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
            'payment_attempt_number' => absint(get_post_meta($order->ID, 'payment_attempt_number', true)),
            'payment_attempt_root_id' => absint(get_post_meta($order->ID, 'payment_attempt_root_id', true)),
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

    private function attempt_html($row) {
        $number = absint($row['payment_attempt_number'] ?? 0);
        if (!$number) return '';
        $root = absint($row['payment_attempt_root_id'] ?? 0);
        return '<br><small>Payment attempt #' . esc_html($number) . ($root ? ' from local #' . esc_html($root) : '') . '</small>';
    }

    private function snapshot_html($snapshot) {
        if (!$snapshot) return '-';
        $decoded = json_decode((string)$snapshot, true);
        $label = is_array($decoded) && !empty($decoded['stage_at']) ? $decoded['stage_at'] : 'Saved snapshot';
        $display = is_array($decoded)
            ? wp_json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : (string)$snapshot;
        return '<details><summary>' . esc_html($label) . '</summary><pre style="white-space:pre-wrap;max-width:420px;">' . esc_html($display) . '</pre></details>';
    }
}
