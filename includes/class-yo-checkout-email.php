<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Email_Service {
    private $callbacks = [];

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
    }

    private function call($name, ...$args) {
        return isset($this->callbacks[$name]) && is_callable($this->callbacks[$name])
            ? call_user_func_array($this->callbacks[$name], $args)
            : null;
    }

    private function settings() {
        $settings = $this->call('settings');
        return is_array($settings) ? $settings : [];
    }

    private function order_data($local_id) {
        $data = $this->call('get_order_data', $local_id);
        return is_array($data) ? $data : [];
    }

    private function mail_headers($s) {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field($s['from_name'] ?? '') . ' <' . sanitize_email($s['from_email'] ?? '') . '>'
        ];
    }

    private function file_path_to_url($path) {
        $url = $this->call('file_path_to_url', $path);
        return is_string($url) ? $url : '';
    }

    private function invoice_logo_src($for_pdf = false) {
        $logo = $this->call('invoice_logo_src', $for_pdf);
        return is_string($logo) ? $logo : '';
    }

    private function invoice_number($order_id) {
        $invoice = $this->call('invoice_number', $order_id);
        return is_string($invoice) ? $invoice : (string)$order_id;
    }

    private function bank_details_for_country($country) {
        $details = $this->call('bank_details_for_country', $country);
        return is_array($details) ? $details : ['type' => 'SEPA'];
    }

    private function cart_items_from_order_data($data) {
        $items = $this->call('cart_items_from_order_data', $data);
        return is_array($items) ? $items : [];
    }

    private function clean_product_title_for_display($title) {
        $clean = $this->call('clean_product_title_for_display', $title);
        return is_string($clean) ? $clean : (string)$title;
    }

    private function email_logo_html() {
        $logo = $this->invoice_logo_src(false);
        if (!$logo) return '';
        return '<div style="text-align:center;margin:0 0 18px 0;">'
            . '<img src="' . esc_url($logo) . '" alt="YOleotard" style="max-width:150px;max-height:64px;width:auto;height:auto;display:inline-block;">'
            . '</div>';
    }

    private function money_html($amount) {
        return '&euro;' . number_format((float)$amount, 2, '.', '');
    }

    private function email_address_html($d) {
        $parts = [];
        foreach (['address','additional_address','city','zip_code','country'] as $key) {
            if (!empty($d[$key])) $parts[] = esc_html($d[$key]);
        }
        return implode('<br>', $parts);
    }

    private function email_products_html($d) {
        $items = $this->cart_items_from_order_data($d);
        if (!$items) return '<strong>' . esc_html($d['title'] ?? '') . '</strong>';

        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
        foreach ($items as $idx => $item) {
            if (!is_array($item)) continue;
            $title = $this->clean_product_title_for_display($item['title'] ?? 'Selected leotard');
            $image = esc_url($item['image_url'] ?? '');
            $border = $idx > 0 ? 'border-top:1px solid #e5e7eb;' : '';
            $html .= '<tr>';
            if ($image) {
                $html .= '<td width="72" valign="top" style="padding:' . ($idx > 0 ? '10px' : '0') . ' 12px 10px 0;' . $border . '">';
                $html .= '<img src="' . $image . '" alt="' . esc_attr($title) . '" width="64" height="64" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;display:block;">';
                $html .= '</td>';
            }
            $html .= '<td valign="middle" style="padding:' . ($idx > 0 ? '10px' : '0') . ' 0 10px 0;' . $border . 'text-align:left;"><strong>' . esc_html($title) . '</strong></td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    private function email_order_table_html($d, $show_status = true) {
        $original = (float)(($d['original_price_eur'] ?? 0) ?: ($d['price_eur'] ?? 0));
        $discount = (float)(($d['discount_eur'] ?? 0) ?: 0);
        $shipping = (float)(($d['shipping_cost_eur'] ?? 0) ?: 0);
        $service_fee = (float)(($d['card_fee_amount'] ?? 0) ?: 0);
        $is_card = in_array(($d['payment_type'] ?? ''), ['card'], true);
        $total = (float)($d['price_eur'] ?? 0) + $shipping + ($is_card ? $service_fee : 0);
        $rows = '';
        $rows .= '<tr><td style="padding:10px 0;color:#64748b;vertical-align:top;">Product</td><td style="padding:10px 0;text-align:left;">' . $this->email_products_html($d) . '</td></tr>';
        $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Items total</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">' . $this->money_html($original) . '</td></tr>';
        if ($discount > 0) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Discount</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">- ' . $this->money_html($discount) . '</td></tr>';
        }
        if ($shipping > 0) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Shipping</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">' . $this->money_html($shipping) . '</td></tr>';
        }
        if ($is_card && $service_fee > 0) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Card payment service fee</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">' . $this->money_html($service_fee) . '</td></tr>';
        } elseif (!$is_card) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Card payment service fee</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">&euro;0.00</td></tr>';
        }
        $rows .= '<tr><td style="padding:12px 0;color:#111827;border-top:2px solid #111827;font-size:16px;"><strong>Total</strong></td><td style="padding:12px 0;text-align:right;border-top:2px solid #111827;font-size:18px;"><strong>' . $this->money_html($total) . '</strong></td></tr>';
        if ($show_status) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Payment status</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;"><strong>Awaiting bank transfer</strong></td></tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">' . $rows . '</table>';
    }

    private function build_bank_invoice_email_html($local_id, $invoice_url) {
        $d = $this->order_data($local_id);
        $invoice = $this->invoice_number($d['order_id'] ?? '');
        $bank_details = $this->bank_details_for_country($d['country'] ?? '');
        $bank_type = get_post_meta($local_id, 'bank_invoice_type', true) ?: ($bank_details['type'] ?? 'SEPA');
        $logo = $this->email_logo_html();
        $button = $invoice_url ? '<p style="margin:22px 0 4px 0;text-align:center;"><a href="' . esc_url($invoice_url) . '" style="background:#111827;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;display:inline-block;font-weight:700;">Download invoice</a></p>' : '';
        $address = $this->email_address_html($d);

        return '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 14px;">'
            . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 6px 18px rgba(15,23,42,.06);">'
            . $logo
            . '<h1 style="margin:0 0 10px 0;font-size:26px;line-height:1.25;color:#111827;">Your YOleotard order has been created</h1>'
            . '<p style="margin:0 0 18px 0;color:#475569;font-size:15px;line-height:1.6;">Thank you for your order. Your invoice has been prepared and attached to this email. The invoice total includes Nova Post delivery. Card payment service fee is not added for SEPA/SWIFT invoice payments.</p>'
            . '<div style="background:#eef6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin:18px 0;">'
            . '<p style="margin:0 0 6px 0;font-size:15px;">Order number: <strong>&#8470; ' . esc_html($d['order_id'] ?? '') . '</strong></p>'
            . '<p style="margin:0 0 6px 0;font-size:15px;">Invoice number: <strong>#' . esc_html($invoice) . '</strong></p>'
            . '<p style="margin:0;font-size:15px;">Payment method: <strong>Bank transfer / ' . esc_html($bank_type) . '</strong></p>'
            . '</div>'
            . '<h2 style="font-size:18px;margin:24px 0 10px 0;">Payment instructions</h2>'
            . '<p style="font-size:15px;line-height:1.6;margin:0 0 12px 0;">To complete your purchase, please make the payment using the bank details provided in the attached invoice.</p>'
            . '<p style="font-size:15px;line-height:1.6;margin:0 0 12px 0;"><strong>Please include the invoice number in the payment description:</strong><br>Payment for custom leotard by invoice #' . esc_html($invoice) . '</p>'
            . $button
            . '<h2 style="font-size:18px;margin:26px 0 10px 0;">Order details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;background:#ffffff;">' . $this->email_order_table_html($d, true) . '</div>'
            . '<h2 style="font-size:18px;margin:26px 0 10px 0;">Customer and delivery details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;background:#fafafa;font-size:14px;line-height:1.6;">'
            . '<p style="margin:0 0 8px 0;"><strong>Name:</strong> ' . esc_html($d['full_name'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Email:</strong> ' . esc_html($d['email'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Phone:</strong> ' . esc_html($d['phone'] ?? '') . '</p>'
            . '<p style="margin:0;"><strong>Delivery address:</strong><br>' . $address . '</p>'
            . '</div>'
            . '<div style="background:#f8fafc;border-left:4px solid #111827;margin:24px 0 0 0;padding:16px 18px;border-radius:8px;">'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">As soon as the payment is received to the specified bank account, we will process and ship your order to the delivery address provided during checkout. After shipment, we will send you the tracking number and tracking link for your parcel.</p>'
            . '</div>'
            . '<p style="margin:24px 0 0 0;font-size:15px;line-height:1.6;">If you have any questions regarding payment or delivery, please reply to this email.</p>'
            . '<p style="margin:20px 0 0 0;font-size:15px;line-height:1.6;">Best regards,<br><strong>YOleotard Atelier</strong><br>Made in Ukraine</p>'
            . '</div>'
            . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:16px 0 0 0;">YOleotard &middot; https://yoleotard.com</p>'
            . '</div></body></html>';
    }

    private function tracking_social_links_html() {
        $links = [
            'Facebook' => 'https://www.facebook.com/YOLeotard/',
            'Instagram' => 'https://www.instagram.com/yoleotard/',
            'TikTok' => 'https://www.tiktok.com/@yoleotard',
        ];
        $items = [];
        foreach ($links as $label => $url) {
            $items[] = '<a href="' . esc_url($url) . '" style="color:#475569;text-decoration:underline;">' . esc_html($label) . '</a>';
        }
        return '<div style="border-top:1px solid #e5e7eb;margin-top:26px;padding-top:18px;text-align:center;color:#64748b;font-size:13px;line-height:1.8;">'
            . '<strong style="color:#111827;">Follow YOleotard</strong><br>'
            . implode(' &middot; ', $items)
            . '</div>';
    }

    private function build_tracking_email_html($local_id, $tracking_url) {
        $d = $this->order_data($local_id);
        $logo = $this->email_logo_html();
        $order_number = sanitize_text_field($d['order_id'] ?? '');
        if ($order_number === '') $order_number = (string)absint($local_id);
        $name = sanitize_text_field($d['full_name'] ?? '');
        $greeting = $name !== '' ? 'Dear ' . $name . ',' : 'Dear customer,';

        return '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 14px;">'
            . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 6px 18px rgba(15,23,42,.06);">'
            . $logo
            . '<h1 style="margin:0 0 10px 0;font-size:26px;line-height:1.25;color:#111827;">Your YOleotard order #' . esc_html($order_number) . ' is on its way</h1>'
            . '<p style="margin:0 0 18px 0;color:#475569;font-size:15px;line-height:1.6;">' . esc_html($greeting) . '</p>'
            . '<p style="margin:0 0 18px 0;color:#475569;font-size:15px;line-height:1.6;">Thank you for your order and for choosing YOleotard. Your parcel has been prepared and shipped.</p>'
            . '<div style="background:#eef6ff;border:1px solid #bfdbfe;border-radius:12px;padding:16px 18px;margin:18px 0;">'
            . '<p style="margin:0 0 6px 0;font-size:15px;">Order number: <strong>&#8470; ' . esc_html($order_number) . '</strong></p>'
            . '<p style="margin:0;font-size:15px;">Shipment status: <strong>Shipped</strong></p>'
            . '</div>'
            . '<p style="font-size:15px;line-height:1.6;margin:0 0 12px 0;">You can follow the delivery progress using the tracking link below.</p>'
            . '<p style="margin:24px 0;text-align:center;"><a href="' . esc_url($tracking_url) . '" style="background:#111827;color:#ffffff;text-decoration:none;padding:13px 22px;border-radius:8px;display:inline-block;font-weight:700;">Track your order</a></p>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;background:#fafafa;">'
            . '<p style="margin:0 0 7px 0;color:#64748b;font-size:12px;">If the button does not open, use this tracking link:</p>'
            . '<p style="margin:0;overflow-wrap:anywhere;font-size:14px;"><a href="' . esc_url($tracking_url) . '" style="color:#1d4ed8;">' . esc_html($tracking_url) . '</a></p>'
            . '</div>'
            . '<p style="margin:24px 0 0 0;font-size:15px;line-height:1.6;">Best regards,<br><strong>YOleotard Atelier</strong><br>Made in Ukraine</p>'
            . $this->tracking_social_links_html()
            . '</div>'
            . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:16px 0 0 0;">YOleotard &middot; <a href="https://yoleotard.com" style="color:#64748b;">yoleotard.com</a></p>'
            . '</div></body></html>';
    }

    public function send_tracking_email($local_id, $tracking_url) {
        $local_id = absint($local_id);
        $d = $this->order_data($local_id);
        $to = sanitize_email($d['email'] ?? '');
        $tracking_url = esc_url_raw($tracking_url, ['http', 'https']);
        if (!$to || !is_email($to) || !$tracking_url || !wp_http_validate_url($tracking_url)) return false;

        $order_number = sanitize_text_field($d['order_id'] ?? '');
        if ($order_number === '') $order_number = (string)$local_id;
        $subject = 'YOleotard order #' . $order_number . ' is on its way';
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: YOleotard <no-reply@yoleotard.com>',
        ];
        $force_html = function() { return 'text/html'; };
        add_filter('wp_mail_content_type', $force_html, 999);
        $sent = wp_mail($to, $subject, $this->build_tracking_email_html($local_id, $tracking_url), $headers);
        remove_filter('wp_mail_content_type', $force_html, 999);
        return $sent;
    }
    private function send_html_mail($to, $subject, $message, $attachments = []) {
        $s = $this->settings();
        $headers = $this->mail_headers($s);
        $force_html = function() { return 'text/html'; };
        add_filter('wp_mail_content_type', $force_html, 999);
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        remove_filter('wp_mail_content_type', $force_html, 999);
        return $sent;
    }

    public function send_bank_invoice_email($local_id, $attachment_path) {
        $local_id = absint($local_id);
        $s = $this->settings();
        $d = $this->order_data($local_id);
        $subject = str_replace('{order_id}', $d['order_id'] ?? '', $s['email_bank_subject'] ?? 'Your YOleotard order has been created');
        $invoice_url = get_post_meta($local_id, 'invoice_pdf_url', true) ?: get_post_meta($local_id, 'invoice_html_url', true);
        if (!$invoice_url) $invoice_url = $this->file_path_to_url($attachment_path);
        $msg = $this->build_bank_invoice_email_html($local_id, $invoice_url);
        $attachments = [];
        if ($attachment_path && file_exists($attachment_path)) $attachments[] = $attachment_path;

        // Main customer email: rich HTML invoice instructions, not the old short text template.
        $sent = $this->send_html_mail($d['email'] ?? '', $subject, $msg, $attachments);
        if (!$sent) {
            delete_post_meta($local_id, 'bank_invoice_email_sent');
            update_post_meta($local_id, 'bank_invoice_email_error', 'Customer bank invoice email was not sent by wp_mail.');
            return false;
        }

        if (!empty($s['admin_email'])) {
            $admin_msg = '<p style="font-family:Arial,Helvetica,sans-serif;font-size:14px;"><strong>Admin copy.</strong> The customer received the invoice-payment email below.</p>' . $msg;
            $this->send_html_mail($s['admin_email'], 'Copy: ' . $subject, $admin_msg, $attachments);
        }

        delete_post_meta($local_id, 'bank_invoice_email_error');
        return true;
    }

    private function build_paid_email_html($local_id) {
        $d = $this->order_data($local_id);
        $logo = $this->email_logo_html();
        $address = $this->email_address_html($d);
        return '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 14px;">'
            . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 6px 18px rgba(15,23,42,.06);">'
            . $logo
            . '<h1 style="margin:0 0 10px 0;font-size:26px;line-height:1.25;color:#111827;">Payment successful</h1>'
            . '<p style="margin:0 0 18px 0;color:#475569;font-size:15px;line-height:1.6;">Thank you for your order. We have received your payment. The total below includes delivery and, for card payments, the payment service fee.</p>'
            . '<div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;padding:16px 18px;margin:18px 0;">'
            . '<p style="margin:0 0 6px 0;font-size:15px;">Order number: <strong>&#8470; ' . esc_html($d['order_id'] ?? '') . '</strong></p>'
            . '<p style="margin:0;font-size:15px;">Payment status: <strong>Paid</strong></p>'
            . '</div>'
            . '<h2 style="font-size:18px;margin:24px 0 10px 0;">Order details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;background:#ffffff;">' . $this->email_order_table_html($d, false) . '</div>'
            . '<h2 style="font-size:18px;margin:26px 0 10px 0;">Customer and delivery details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;background:#fafafa;font-size:14px;line-height:1.6;">'
            . '<p style="margin:0 0 8px 0;"><strong>Name:</strong> ' . esc_html($d['full_name'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Email:</strong> ' . esc_html($d['email'] ?? '') . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Phone:</strong> ' . esc_html($d['phone'] ?? '') . '</p>'
            . '<p style="margin:0;"><strong>Delivery address:</strong><br>' . $address . '</p>'
            . '</div>'
            . '<div style="background:#f8fafc;border-left:4px solid #111827;margin:24px 0 0 0;padding:16px 18px;border-radius:8px;">'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">Our manager will contact you via WhatsApp and email. After shipment, we will send you the tracking number and tracking link for your parcel.</p>'
            . '</div>'
            . '<p style="margin:20px 0 0 0;font-size:15px;line-height:1.6;">Best regards,<br><strong>YOleotard Atelier</strong><br>Made in Ukraine</p>'
            . '</div>'
            . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:16px 0 0 0;">YOleotard &middot; https://yoleotard.com</p>'
            . '</div></body></html>';
    }

    public function send_paid_email($local_id) {
        $local_id = absint($local_id);
        if (get_post_meta($local_id, 'paid_email_sent', true) === '1') return true;
        $s = $this->settings();
        $d = $this->order_data($local_id);
        $subject = str_replace('{order_id}', $d['order_id'] ?? '', $s['email_paid_subject'] ?? 'Your YOleotard payment is confirmed');
        $msg = $this->build_paid_email_html($local_id);
        $sent = $this->send_html_mail($d['email'] ?? '', $subject, $msg);
        if (!$sent) {
            update_post_meta($local_id, 'paid_email_error', 'Customer paid email was not sent by wp_mail.');
            return false;
        }
        if (!empty($s['admin_email'])) $this->send_html_mail($s['admin_email'], 'Copy: ' . $subject, $msg);
        delete_post_meta($local_id, 'paid_email_error');
        update_post_meta($local_id, 'paid_email_sent', '1');
        return true;
    }
}
