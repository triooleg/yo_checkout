<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Messenger_Service {
    private $callbacks = [];

    public function __construct(array $callbacks = []) {
        $this->callbacks = $callbacks;
    }

    public static function defaults() {
        return [
            'manager_button_enabled' => '0',
            'manager_whatsapp_number' => '380932354616',
            'manager_instagram_username' => 'yoleotard',
            'manager_facebook_username' => 'YOleotard',
            'messenger_app_id' => '',
            'messenger_app_secret' => '',
            'messenger_page_id' => '261203841288993',
            'messenger_page_access_token' => '',
            'messenger_verify_token' => '',
            'messenger_graph_version' => 'v24.0',
        ];
    }

    public static function sanitize_settings(array $input, array $current, array $out) {
        $defaults = self::defaults();
        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $input)) continue;
            $value = is_string($input[$key]) ? trim(wp_unslash($input[$key])) : '';
            if (in_array($key, ['messenger_app_secret', 'messenger_page_access_token'], true) && $value === '') {
                $out[$key] = (string)($current[$key] ?? '');
                continue;
            }
            if ($key === 'manager_button_enabled') {
                $out[$key] = $value === '1' ? '1' : '0';
            } elseif ($key === 'manager_whatsapp_number' || $key === 'messenger_page_id' || $key === 'messenger_app_id') {
                $out[$key] = preg_replace('/[^0-9]/', '', $value);
            } elseif ($key === 'manager_instagram_username' || $key === 'manager_facebook_username') {
                $out[$key] = preg_replace('/[^A-Za-z0-9._-]/', '', ltrim($value, '@'));
            } elseif ($key === 'messenger_graph_version') {
                $out[$key] = preg_match('/^v[0-9]{1,2}\.[0-9]$/', $value) ? $value : $default;
            } else {
                $out[$key] = sanitize_text_field($value);
            }
        }
        return $out;
    }

    public function ensure_verify_token() {
        $settings = $this->settings();
        $token = trim((string)($settings['messenger_verify_token'] ?? ''));
        if ($token !== '') return $token;

        $stored = get_option('yo_checkout_invoice_settings', []);
        if (!is_array($stored)) $stored = [];
        $token = wp_generate_password(40, false, false);
        $stored['messenger_verify_token'] = $token;
        update_option('yo_checkout_invoice_settings', $stored, false);
        return $token;
    }

    public function render_settings_fields($option_name, $namespace) {
        $settings = $this->settings();
        $verify_token = $this->ensure_verify_token();
        $callback_url = rest_url($namespace . '/messenger-webhook');
        $configured = $this->page_access_token() !== '' && $this->app_secret() !== '';
        ?>
        <h2>Manager purchase button</h2>
        <p class="description">The button is shown next to the regular Buy now button, or above discounted purchase controls. Its menu opens WhatsApp, Instagram Direct, or Facebook Messenger with the selected product details.</p>
        <table class="form-table">
            <?php $this->checkbox_field($option_name, 'manager_button_enabled', 'Show "Need help buying?" button on product cards'); ?>
            <?php $this->text_field($option_name, 'manager_whatsapp_number', 'WhatsApp number in international format'); ?>
            <?php $this->text_field($option_name, 'manager_instagram_username', 'Instagram username'); ?>
            <?php $this->text_field($option_name, 'manager_facebook_username', 'Facebook Page username for m.me link'); ?>
        </table>

        <h2>Meta Messenger API</h2>
        <div class="notice notice-<?php echo $configured ? 'success' : 'warning'; ?> inline"><p><strong>Messenger API status:</strong> <?php echo $configured ? 'credentials saved' : 'App Secret or Page Access Token is missing'; ?>.</p></div>
        <table class="form-table">
            <?php $this->text_field($option_name, 'messenger_app_id', 'Meta App ID'); ?>
            <?php $this->secret_field($option_name, 'messenger_app_secret', 'Meta App Secret'); ?>
            <?php $this->text_field($option_name, 'messenger_page_id', 'Facebook Page ID'); ?>
            <?php $this->secret_field($option_name, 'messenger_page_access_token', 'Page Access Token'); ?>
            <?php $this->text_field($option_name, 'messenger_graph_version', 'Graph API version'); ?>
            <tr>
                <th scope="row"><label for="messenger_verify_token">Webhook verify token</label></th>
                <td><input name="<?php echo esc_attr($option_name); ?>[messenger_verify_token]" id="messenger_verify_token" type="text" value="<?php echo esc_attr($verify_token); ?>" class="regular-text" autocomplete="off"><p class="description">Copy this exact value into the Meta Webhooks verification form.</p></td>
            </tr>
        </table>
        <p><strong>Webhook callback URL:</strong> <code><?php echo esc_html($callback_url); ?></code></p>
        <p class="description">In Meta subscribe the connected Page to <code>messaging_referrals</code>, <code>messages</code>, and <code>messaging_postbacks</code>. App Secret and Page Access Token are used only on the WordPress server and are never sent to the storefront browser.</p>
        <?php
    }

    public function register_routes($namespace) {
        register_rest_route($namespace, '/messenger-webhook', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'verify_webhook'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'receive_webhook'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    public function verify_webhook(WP_REST_Request $request) {
        $mode = $request->get_param('hub_mode');
        if ($mode === null) $mode = $request->get_param('hub.mode');
        $token = $request->get_param('hub_verify_token');
        if ($token === null) $token = $request->get_param('hub.verify_token');
        $challenge = $request->get_param('hub_challenge');
        if ($challenge === null) $challenge = $request->get_param('hub.challenge');

        $mode = sanitize_text_field((string)$mode);
        $token = sanitize_text_field((string)$token);
        $challenge = preg_replace('/[^0-9]/', '', (string)$challenge);
        if ($mode === 'subscribe' && $token !== '' && $challenge !== '' && hash_equals($this->ensure_verify_token(), $token)) {
            return new WP_REST_Response((int)$challenge, 200);
        }
        return new WP_REST_Response(['verified' => false], 403);
    }

    public function receive_webhook(WP_REST_Request $request) {
        $body = (string)$request->get_body();
        $signature = (string)$request->get_header('x-hub-signature-256');
        if (!self::verify_signature_value($body, $signature, $this->app_secret())) {
            return new WP_REST_Response(['received' => false], 403);
        }

        $payload = json_decode($body, true);
        if (!is_array($payload) || ($payload['object'] ?? '') !== 'page') {
            return new WP_REST_Response(['received' => true], 200);
        }

        foreach ((array)($payload['entry'] ?? []) as $entry) {
            foreach ((array)($entry['messaging'] ?? []) as $event) {
                $this->handle_referral_event(is_array($event) ? $event : []);
            }
        }
        return new WP_REST_Response(['received' => true], 200);
    }

    public static function verify_signature_value($body, $header, $secret) {
        $secret = (string)$secret;
        if ($secret === '' || !preg_match('/^sha256=([a-f0-9]{64})$/i', (string)$header, $matches)) return false;
        $expected = hash_hmac('sha256', (string)$body, $secret);
        return hash_equals(strtolower($expected), strtolower($matches[1]));
    }

    public static function referral_product_id($reference) {
        $reference = (string)$reference;
        if (strpos($reference, 'yo_product_') !== 0) return '';
        $product_id = substr($reference, 11);
        return preg_match('/^[A-Za-z0-9_-]{1,160}$/', $product_id) ? $product_id : '';
    }

    private function handle_referral_event(array $event) {
        $sender_id = preg_replace('/[^0-9]/', '', (string)($event['sender']['id'] ?? ''));
        $referral = isset($event['referral']) && is_array($event['referral']) ? $event['referral'] : [];
        $product_id = self::referral_product_id($referral['ref'] ?? '');
        if ($sender_id === '' || $product_id === '') return;

        $dedupe_key = 'yo_msg_ref_' . md5($sender_id . '|' . $product_id . '|' . (string)($event['timestamp'] ?? ''));
        if (get_transient($dedupe_key)) return;
        set_transient($dedupe_key, '1', HOUR_IN_SECONDS);

        $product = $this->resolve_product($product_id);
        $title = !empty($product['found']) ? sanitize_text_field($product['title'] ?? '') : '';
        if ($title === '') $title = 'Selected YOleotard model';
        $price = floatval($product['price_eur'] ?? 0);
        $subtitle = $price > 0
            ? sprintf('Need help buying this model? Price: EUR %s', number_format($price, 2, '.', ''))
            : 'Need help buying this model? Our manager will assist you.';
        $image_url = !empty($product['found']) ? esc_url_raw($product['image_url'] ?? '') : '';
        $product_url = home_url('/#' . rawurlencode($product_id));

        $element = [
            'title' => mb_substr($title, 0, 80),
            'subtitle' => mb_substr($subtitle, 0, 80),
            'default_action' => [
                'type' => 'web_url',
                'url' => $product_url,
                'webview_height_ratio' => 'full',
            ],
            'buttons' => [[
                'type' => 'web_url',
                'url' => $product_url,
                'title' => 'View model',
            ]],
        ];
        if ($image_url !== '') $element['image_url'] = $image_url;

        $this->send_message($sender_id, [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'generic',
                    'elements' => [$element],
                ],
            ],
        ]);
    }

    private function send_message($recipient_id, array $message) {
        $token = $this->page_access_token();
        if ($token === '') return false;
        $settings = $this->settings();
        $version = preg_match('/^v[0-9]{1,2}\.[0-9]$/', (string)($settings['messenger_graph_version'] ?? ''))
            ? (string)$settings['messenger_graph_version']
            : 'v24.0';
        $url = 'https://graph.facebook.com/' . rawurlencode($version) . '/me/messages?access_token=' . rawurlencode($token);
        $response = wp_remote_post($url, [
            'timeout' => 12,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => wp_json_encode([
                'recipient' => ['id' => $recipient_id],
                'messaging_type' => 'RESPONSE',
                'message' => $message,
            ]),
        ]);
        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) >= 200 && wp_remote_retrieve_response_code($response) < 300;
    }

    private function resolve_product($product_id) {
        $resolved = $this->call('resolve_product', $product_id);
        return is_array($resolved) ? $resolved : ['found' => false];
    }

    private function settings() {
        $settings = $this->call('settings');
        return is_array($settings) ? $settings : [];
    }

    private function app_secret() {
        if (defined('YO_CHECKOUT_MESSENGER_APP_SECRET') && YO_CHECKOUT_MESSENGER_APP_SECRET !== '') return (string)YO_CHECKOUT_MESSENGER_APP_SECRET;
        $settings = $this->settings();
        return trim((string)($settings['messenger_app_secret'] ?? ''));
    }

    private function page_access_token() {
        if (defined('YO_CHECKOUT_MESSENGER_PAGE_TOKEN') && YO_CHECKOUT_MESSENGER_PAGE_TOKEN !== '') return (string)YO_CHECKOUT_MESSENGER_PAGE_TOKEN;
        $settings = $this->settings();
        return trim((string)($settings['messenger_page_access_token'] ?? ''));
    }

    private function call($name, ...$args) {
        return isset($this->callbacks[$name]) && is_callable($this->callbacks[$name])
            ? call_user_func_array($this->callbacks[$name], $args)
            : null;
    }

    private function text_field($option_name, $key, $label) {
        $settings = $this->settings();
        printf('<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input name="%3$s[%1$s]" id="%1$s" type="text" value="%4$s" class="regular-text" autocomplete="off"></td></tr>', esc_attr($key), esc_html($label), esc_attr($option_name), esc_attr($settings[$key] ?? ''));
    }

    private function secret_field($option_name, $key, $label) {
        $settings = $this->settings();
        $saved = trim((string)($settings[$key] ?? '')) !== '';
        printf('<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input name="%3$s[%1$s]" id="%1$s" type="password" value="" class="regular-text" autocomplete="new-password" placeholder="%4$s"><p class="description">%5$s</p></td></tr>', esc_attr($key), esc_html($label), esc_attr($option_name), $saved ? 'Saved - leave blank to keep' : 'Paste value', $saved ? 'A value is stored. Leave this field empty to keep it unchanged.' : 'No value is stored yet.');
    }

    private function checkbox_field($option_name, $key, $label) {
        $settings = $this->settings();
        printf('<tr><th scope="row">Button status</th><td><input type="hidden" name="%1$s[%2$s]" value="0"><label><input name="%1$s[%2$s]" id="%2$s" type="checkbox" value="1" %3$s> %4$s</label></td></tr>', esc_attr($option_name), esc_attr($key), checked((string)($settings[$key] ?? '0'), '1', false), esc_html($label));
    }
}
