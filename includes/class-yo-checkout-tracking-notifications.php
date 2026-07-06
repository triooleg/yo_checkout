<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Tracking_Notifications_Service {
    const PAGE_SLUG = 'yo-checkout-tracking';
    const ACTION = 'yo_checkout_send_tracking';
    private $callbacks = [];

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
    }

    private function call($name, ...$args) {
        return isset($this->callbacks[$name]) && is_callable($this->callbacks[$name])
            ? call_user_func_array($this->callbacks[$name], $args)
            : null;
    }

    public function render_page($order_post_type) {
        if (!current_user_can('manage_options')) wp_die('Access denied');
        $status = $this->current_status_filter();
        $current_page = max(1, absint($_GET['tracking_page'] ?? 1));
        $query = new WP_Query([
            'post_type' => $order_post_type,
            'post_status' => 'publish',
            'posts_per_page' => 20,
            'paged' => $current_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => $this->meta_query($status),
        ]);
        $notice = sanitize_key($_GET['tracking_notice'] ?? '');
        ?>
        <div class="wrap yo-tracking-page">
            <h1>Tracking Notifications</h1>
            <p>Send customers a shipment tracking link for paid card orders and completed bank invoice orders.</p>
            <?php $this->render_notice($notice); ?>
            <nav class="nav-tab-wrapper" aria-label="Tracking notification status">
                <?php foreach (['all' => 'All', 'pending' => 'Pending', 'sent' => 'Sent'] as $value => $label): ?>
                    <a class="nav-tab <?php echo $status === $value ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url($this->page_url(['tracking_status' => $value])); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="yo-tracking-list">
                <div class="yo-tracking-head" aria-hidden="true"><span>Order</span><span>Status</span><span>Customer</span><span>Products</span><span>Tracking</span></div>
                <?php if ($query->have_posts()): ?>
                    <?php foreach ($query->posts as $order): $this->render_order($order); endforeach; ?>
                <?php else: ?><div class="yo-tracking-empty">No matching customer orders found.</div><?php endif; ?>
            </div>
            <?php $this->render_pagination($current_page, (int)$query->max_num_pages, $status); ?>
        </div>
        <style>
            .yo-tracking-page .nav-tab-wrapper{margin:20px 0 14px}.yo-tracking-list{max-width:1500px;border:1px solid #c3c4c7;background:#fff}
            .yo-tracking-head,.yo-tracking-summary{display:grid;grid-template-columns:140px 120px minmax(190px,1.1fr) minmax(260px,1.5fr) 190px 18px;gap:16px;align-items:center}
            .yo-tracking-head{grid-template-columns:140px 120px minmax(190px,1.1fr) minmax(260px,1.5fr) 224px;padding:10px 14px;background:#f6f7f7;border-bottom:1px solid #c3c4c7;font-weight:600}
            .yo-tracking-order{border-bottom:1px solid #dcdcde}.yo-tracking-order:last-child{border-bottom:0}.yo-tracking-summary{padding:14px;cursor:pointer;list-style:none}
            .yo-tracking-summary::-webkit-details-marker{display:none}.yo-tracking-summary:after{content:'\25BC';color:#646970;font-size:11px}.yo-tracking-order[open]>.yo-tracking-summary:after{content:'\25B2'}
            .yo-tracking-summary:hover{background:#f6f7f7}.yo-tracking-summary>span{min-width:0;overflow-wrap:anywhere}.yo-tracking-badge{display:inline-block;padding:4px 9px;border-radius:12px;color:#fff;font-weight:600;font-size:12px}
            .yo-tracking-badge-paid{background:#147a3d}.yo-tracking-badge-invoice{background:#1d4ed8}.yo-tracking-sent{color:#147a3d;font-weight:600}.yo-tracking-pending{color:#996800;font-weight:600}
            .yo-tracking-detail{display:grid;grid-template-columns:minmax(260px,1fr) minmax(340px,1.25fr);gap:24px;padding:18px 34px;background:#f9f9f9;border-top:1px solid #dcdcde}
            .yo-tracking-detail h3{margin:0 0 10px;font-size:14px}.yo-tracking-detail p{margin:5px 0}.yo-tracking-form textarea{width:100%;min-height:72px;resize:vertical}.yo-tracking-form .button{margin-top:10px}
            .yo-tracking-empty{padding:24px;text-align:center;color:#646970}.yo-tracking-pagination{margin-top:16px}.yo-tracking-pagination .page-numbers{margin-right:4px}
            @media(max-width:1000px){.yo-tracking-head{display:none}.yo-tracking-summary{grid-template-columns:1fr 1fr}.yo-tracking-summary:after{grid-column:2}.yo-tracking-detail{grid-template-columns:1fr}}
        </style>
        <?php
        wp_reset_postdata();
    }

    public function handle_send($order_post_type) {
        if (!current_user_can('manage_options')) wp_die('Access denied');
        $local_id = absint($_POST['local_id'] ?? 0);
        check_admin_referer(self::ACTION . '_' . $local_id);
        if (!$local_id || get_post_type($local_id) !== $order_post_type || !$this->is_eligible($local_id)) $this->redirect('invalid_order');

        $email = sanitize_email(get_post_meta($local_id, 'email', true));
        $tracking_url = esc_url_raw(trim((string)wp_unslash($_POST['tracking_url'] ?? '')), ['http', 'https']);
        if (!$email || !is_email($email)) $this->redirect('invalid_email', $local_id);
        if (!$tracking_url || !wp_http_validate_url($tracking_url)) $this->redirect('invalid_url', $local_id);

        $sent = (bool)$this->call('send_tracking_email', $local_id, $tracking_url);
        if (!$sent) {
            update_post_meta($local_id, 'tracking_notification_error', 'wp_mail returned false');
            $this->redirect('send_failed', $local_id);
        }

        update_post_meta($local_id, 'tracking_url', $tracking_url);
        update_post_meta($local_id, 'tracking_notification_sent', '1');
        update_post_meta($local_id, 'tracking_notification_sent_at', current_time('mysql'));
        update_post_meta($local_id, 'tracking_notification_sent_to', $email);
        update_post_meta($local_id, 'tracking_notification_sent_by', get_current_user_id());
        delete_post_meta($local_id, 'tracking_notification_error');
        $this->redirect('sent', $local_id);
    }

    private function render_order($order) {
        $local_id = absint($order->ID);
        $paid = get_post_meta($local_id, 'paid', true) === '1';
        $sent = get_post_meta($local_id, 'tracking_notification_sent', true) === '1';
        $sent_at = sanitize_text_field(get_post_meta($local_id, 'tracking_notification_sent_at', true));
        $tracking_url = esc_url_raw(get_post_meta($local_id, 'tracking_url', true));
        $name = sanitize_text_field(get_post_meta($local_id, 'full_name', true));
        $email = sanitize_email(get_post_meta($local_id, 'email', true));
        $phone = sanitize_text_field(get_post_meta($local_id, 'phone', true));
        $order_id = sanitize_text_field(get_post_meta($local_id, 'order_id', true));
        $items = $this->items($local_id);
        $product_summary = implode(', ', array_slice(array_column($items, 'title'), 0, 2));
        if (count($items) > 2) $product_summary .= ' +' . (count($items) - 2);
        ?>
        <details class="yo-tracking-order" id="tracking-order-<?php echo esc_attr($local_id); ?>"<?php echo absint($_GET['tracking_order'] ?? 0) === $local_id ? ' open' : ''; ?>>
            <summary class="yo-tracking-summary">
                <span><strong><?php echo esc_html($order_id ? '#' . $order_id : 'Local #' . $local_id); ?></strong><br><small><?php echo esc_html(get_the_date('Y-m-d H:i', $order)); ?></small></span>
                <span><span class="yo-tracking-badge <?php echo $paid ? 'yo-tracking-badge-paid' : 'yo-tracking-badge-invoice'; ?>"><?php echo $paid ? 'Paid card' : 'Invoice'; ?></span></span>
                <span><strong><?php echo esc_html($name ?: 'Customer'); ?></strong><br><small><?php echo esc_html($email); ?></small></span>
                <span><?php echo esc_html($product_summary ?: 'Order products'); ?></span>
                <span class="<?php echo $sent ? 'yo-tracking-sent' : 'yo-tracking-pending'; ?>"><?php echo $sent ? 'Sent' . ($sent_at ? ' ' . $sent_at : '') : 'Pending'; ?></span>
            </summary>
            <div class="yo-tracking-detail">
                <section><h3>Order details</h3><p><strong>Customer:</strong> <?php echo esc_html($name); ?></p><p><strong>Email:</strong> <?php echo esc_html($email); ?></p><p><strong>Phone:</strong> <?php echo esc_html($phone); ?></p>
                    <p><strong>Products:</strong></p><ol><?php foreach ($items as $item): ?><li><?php echo esc_html($item['title']); ?></li><?php endforeach; ?></ol>
                    <?php if ($sent): ?><p class="yo-tracking-sent">Tracking email sent to <?php echo esc_html(get_post_meta($local_id, 'tracking_notification_sent_to', true)); ?>.</p><?php endif; ?>
                </section>
                <form class="yo-tracking-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <h3>Shipment tracking link</h3><input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>"><input type="hidden" name="local_id" value="<?php echo esc_attr($local_id); ?>"><?php wp_nonce_field(self::ACTION . '_' . $local_id); ?>
                    <label class="screen-reader-text" for="tracking-url-<?php echo esc_attr($local_id); ?>">Tracking URL</label><textarea id="tracking-url-<?php echo esc_attr($local_id); ?>" name="tracking_url" required placeholder="https://tracking.example.com/..."><?php echo esc_textarea($tracking_url); ?></textarea>
                    <button type="submit" class="button button-primary"><?php echo $sent ? 'Send again' : 'Send tracking email'; ?></button>
                </form>
            </div>
        </details>
        <?php
    }

    private function items($local_id) {
        $decoded = json_decode((string)get_post_meta($local_id, 'cart_items_json', true), true);
        $items = [];
        if (is_array($decoded)) foreach ($decoded as $item) {
            if (!is_array($item)) continue;
            $title = sanitize_text_field($item['title'] ?? '');
            if ($title !== '') $items[] = ['title' => $title];
        }
        if (!$items) {
            $title = sanitize_text_field(get_post_meta($local_id, 'title', true));
            if ($title !== '') $items[] = ['title' => $title];
        }
        return $items;
    }

    private function is_eligible($local_id) {
        return get_post_meta($local_id, 'paid', true) === '1' || get_post_meta($local_id, 'bank_invoice_created', true) === '1';
    }

    private function meta_query($status) {
        $eligible = ['relation' => 'OR', ['key' => 'paid', 'value' => '1'], ['key' => 'bank_invoice_created', 'value' => '1']];
        if ($status === 'sent') return ['relation' => 'AND', $eligible, ['key' => 'tracking_notification_sent', 'value' => '1']];
        if ($status === 'pending') return ['relation' => 'AND', $eligible, ['relation' => 'OR', ['key' => 'tracking_notification_sent', 'compare' => 'NOT EXISTS'], ['key' => 'tracking_notification_sent', 'value' => '1', 'compare' => '!=']]];
        return [$eligible];
    }

    private function current_status_filter() {
        $status = sanitize_key($_GET['tracking_status'] ?? 'all');
        return in_array($status, ['all', 'pending', 'sent'], true) ? $status : 'all';
    }

    private function render_notice($notice) {
        $messages = ['sent' => ['success', 'Tracking email was sent successfully.'], 'invalid_order' => ['error', 'This order is not eligible for a tracking notification.'], 'invalid_email' => ['error', 'The customer email address is missing or invalid.'], 'invalid_url' => ['error', 'Enter a valid tracking URL beginning with http:// or https://.'], 'send_failed' => ['error', 'WordPress could not send the tracking email. Please check the mail configuration and try again.']];
        if (!isset($messages[$notice])) return;
        [$type, $text] = $messages[$notice];
        echo '<div class="notice notice-' . esc_attr($type) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
    }

    private function render_pagination($current_page, $max_pages, $status) {
        if ($max_pages <= 1) return;
        echo '<div class="yo-tracking-pagination">' . wp_kses_post(paginate_links(['base' => add_query_arg('tracking_page', '%#%', $this->page_url(['tracking_status' => $status])), 'format' => '', 'current' => $current_page, 'total' => $max_pages, 'type' => 'plain', 'prev_text' => '&lsaquo;', 'next_text' => '&rsaquo;'])) . '</div>';
    }

    private function page_url(array $args = []) {
        return add_query_arg($args, admin_url('admin.php?page=' . self::PAGE_SLUG));
    }

    private function redirect($notice, $local_id = 0) {
        $args = ['tracking_notice' => sanitize_key($notice)];
        if ($local_id) $args['tracking_order'] = absint($local_id);
        wp_safe_redirect($this->page_url($args));
        exit;
    }
}
