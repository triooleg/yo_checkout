<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_KeyCRM_Service {
    private $callbacks = [];
    private $order_post_type = '';

    public function __construct(array $callbacks = [], $order_post_type = '') {
        $this->callbacks = $callbacks;
        $this->order_post_type = (string)$order_post_type;
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

    private function get_order_data($local_id) {
        $data = $this->call('get_order_data', $local_id);
        return is_array($data) ? $data : [];
    }

    private function cart_items_from_order_data($data) {
        $items = $this->call('cart_items_from_order_data', $data);
        return is_array($items) ? $items : [];
    }

    private function clean_product_title_for_display($title) {
        $clean = $this->call('clean_product_title_for_display', $title);
        return is_string($clean) ? $clean : (string)$title;
    }

    public function keycrm_product_sku($title, $image_url = '', $price = '') {
            $title_clean = $this->clean_product_title_for_display($title);
            $base = sanitize_title($title_clean);
            if ($base === '') $base = 'yoleotard-item';
            $hash = substr(md5($title_clean . '|' . (string)$image_url . '|' . (string)$price), 0, 10);
            return substr('YO-' . $base . '-' . $hash, 0, 64);
        }

    public function keycrm_marker_cookie_order_id() {
            return preg_replace('/[^0-9]/', '', (string)($_COOKIE['yo_checkout_keycrm_order_id'] ?? ''));
        }

    public function posted_cart_marker_keycrm_order_id() {
            $id = preg_replace('/[^0-9]/', '', (string) wp_unslash($_POST['cart_marker_keycrm_order_id'] ?? ''));
            if ($id !== '') return $id;
            $raw = (string) wp_unslash($_POST['cart_marker_json'] ?? '');
            if ($raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['keycrm_order_id'])) {
                    $id = preg_replace('/[^0-9]/', '', (string)$decoded['keycrm_order_id']);
                    if ($id !== '') return $id;
                }
            }
            return '';
        }

    public function marker_hash_value($value) {
            $value = trim((string)$value);
            return $value === '' ? '' : md5(mb_strtolower($value));
        }

    public function hard_find_existing_keycrm_checkout_by_marker($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '', $exclude_local_id = 0) {
            $exclude_local_id = absint($exclude_local_id);
            $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
            if ($posted_keycrm_order_id !== '') {
                $by_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
                if ($by_order && (!$exclude_local_id || $by_order !== $exclude_local_id) && get_post_meta($by_order, 'paid', true) !== '1') return $by_order;
            }
            $email = sanitize_email($data['email'] ?? '');
            $phone_digits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
            $phone_tail = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
            $checkout_session_id = sanitize_text_field((string)$checkout_session_id);
            $browser_buyer_id = sanitize_text_field((string)$browser_buyer_id);
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'publish',
                'numberposts' => 200,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
            ]);
            foreach ((array)$ids as $id) {
                $id = absint($id);
                if (!$id || ($exclude_local_id && $id === $exclude_local_id)) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id === '') continue;
                $same_session = ($checkout_session_id !== '' && $checkout_session_id === (string)get_post_meta($id, 'checkout_session_id', true));
                $same_browser = ($browser_buyer_id !== '' && $browser_buyer_id === (string)get_post_meta($id, 'browser_buyer_id', true));
                $stored_email = sanitize_email((string)get_post_meta($id, 'email', true));
                $same_email = ($email !== '' && $stored_email !== '' && strcasecmp($email, $stored_email) === 0);
                $stored_phone_digits = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'phone', true));
                $stored_tail = strlen($stored_phone_digits) > 7 ? substr($stored_phone_digits, -7) : $stored_phone_digits;
                $same_phone = ($phone_tail !== '' && $stored_tail !== '' && $phone_tail === $stored_tail);
                if ($same_session || $same_browser || $same_email || $same_phone) return $id;
            }
            return 0;
    }

    public function find_recent_unpaid_keycrm_order_for_customer_broad($data = [], $exclude_local_id = 0) {
            $exclude_local_id = absint($exclude_local_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone_digits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
            $phone_tail = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
            if ($email === '' && $phone_tail === '') return 0;
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'publish',
                'numberposts' => 80,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'date_query' => [['after' => '6 hours ago']],
                'meta_query' => [
                    ['key' => 'order_id', 'compare' => 'EXISTS'],
                ],
            ]);
            foreach ((array)$ids as $id) {
                $id = absint($id);
                if (!$id || ($exclude_local_id && $id === $exclude_local_id)) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id === '') continue;
                $stored_email = sanitize_email((string)get_post_meta($id, 'email', true));
                $stored_phone_digits = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'phone', true));
                $stored_tail = strlen($stored_phone_digits) > 7 ? substr($stored_phone_digits, -7) : $stored_phone_digits;
                $same_email = ($email !== '' && $stored_email !== '' && strcasecmp($email, $stored_email) === 0);
                $same_phone = ($phone_tail !== '' && $stored_tail !== '' && $phone_tail === $stored_tail);
                if ($same_email || $same_phone) return $id;
            }
            return 0;
        }

    public function find_latest_unpaid_keycrm_order_by_contact($data = [], $exclude_local_id = 0) {
            $exclude_local_id = absint($exclude_local_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone_raw = sanitize_text_field((string)($data['phone'] ?? ''));
            $phone_digits = preg_replace('/[^0-9]/', '', $phone_raw);
            if ($email === '' && $phone_digits === '') return 0;
    
            $meta_or = ['relation' => 'OR'];
            if ($email !== '') $meta_or[] = ['key' => 'email', 'value' => $email];
            if ($phone_digits !== '') {
                // Phone numbers may be saved with spaces, +, or without formatting; use LIKE on last 7 digits.
                $needle = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
                if ($needle !== '') $meta_or[] = ['key' => 'phone', 'value' => $needle, 'compare' => 'LIKE'];
            }
            if (count($meta_or) < 2) return 0;
    
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'publish',
                'numberposts' => 25,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'date_query' => [['after' => '2 days ago']],
                'meta_query' => [
                    'relation' => 'AND',
                    $meta_or,
                    ['key' => 'order_id', 'compare' => 'EXISTS'],
                ],
            ]);
            foreach ((array)$ids as $id) {
                $id = absint($id);
                if (!$id || ($exclude_local_id && $id === $exclude_local_id)) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id !== '') return $id;
            }
            return 0;
        }

    public function find_hard_reusable_keycrm_local_id($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
            $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
            if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->posted_cart_marker_keycrm_order_id();
            if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
            if ($posted_keycrm_order_id !== '') {
                $by_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
                if ($by_order && get_post_type($by_order) === $this->order_post_type && get_post_meta($by_order, 'paid', true) !== '1') return $by_order;
            }
    
            $checkout_session_id = sanitize_text_field((string)$checkout_session_id);
            $browser_buyer_id = sanitize_text_field((string)$browser_buyer_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));
    
            $candidates = [];
            $add = function($ids) use (&$candidates) {
                foreach ((array)$ids as $id) {
                    $id = absint($id);
                    if ($id && !in_array($id, $candidates, true)) $candidates[] = $id;
                }
            };
    
            $option_order_keys = [];
            if ($checkout_session_id !== '') $option_order_keys[] = 'yo_checkout_keycrm_session_order_' . md5($checkout_session_id);
            if ($browser_buyer_id !== '') $option_order_keys[] = 'yo_checkout_keycrm_browser_order_' . md5($browser_buyer_id);
            if ($email !== '') $option_order_keys[] = 'yo_checkout_keycrm_email_order_' . md5(mb_strtolower($email));
            if ($phone !== '') $option_order_keys[] = 'yo_checkout_keycrm_phone_order_' . md5(mb_strtolower($phone));
            foreach ($option_order_keys as $key) {
                $oid = preg_replace('/[^0-9]/', '', (string)get_option($key, ''));
                if ($oid !== '') {
                    $local = $this->find_local_order_by_keycrm_order_id($oid);
                    if ($local) $add([$local]);
                }
            }
    
            $meta_or = ['relation' => 'OR'];
            if ($checkout_session_id !== '') $meta_or[] = ['key'=>'checkout_session_id', 'value'=>$checkout_session_id];
            if ($browser_buyer_id !== '') $meta_or[] = ['key'=>'browser_buyer_id', 'value'=>$browser_buyer_id];
            if ($email !== '') $meta_or[] = ['key'=>'email', 'value'=>$email];
            if ($phone !== '') $meta_or[] = ['key'=>'phone', 'value'=>$phone];
            if (count($meta_or) > 1) {
                $ids = get_posts([
                    'post_type' => $this->order_post_type,
                    'post_status' => 'publish',
                    'numberposts' => 50,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids',
                    'meta_query' => [
                        'relation' => 'AND',
                        $meta_or,
                        ['key'=>'order_id', 'value'=>'', 'compare'=>'!='],
                    ],
                ]);
                $add($ids);
            }
    
            foreach ($candidates as $id) {
                if (!$id || get_post_type($id) !== $this->order_post_type) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id === '') continue;
                return $id;
            }
            return 0;
        }

    public function find_latest_unpaid_keycrm_checkout_for_customer($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
            $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
            if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
            if ($posted_keycrm_order_id !== '') {
                $by_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
                if ($by_order && get_post_meta($by_order, 'paid', true) !== '1') return $by_order;
            }
    
            $checkout_session_id = sanitize_text_field((string)$checkout_session_id);
            $browser_buyer_id = sanitize_text_field((string)$browser_buyer_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));
    
            $candidates = [];
            $add = function($ids) use (&$candidates) {
                foreach ((array)$ids as $id) {
                    $id = absint($id);
                    if ($id && !in_array($id, $candidates, true)) $candidates[] = $id;
                }
            };
    
            foreach ([
                $checkout_session_id !== '' ? 'yo_checkout_keycrm_session_' . md5($checkout_session_id) : '',
                $browser_buyer_id !== '' ? 'yo_checkout_keycrm_browser_' . md5($browser_buyer_id) : '',
            ] as $option_key) {
                if ($option_key === '') continue;
                $mapped = absint(get_option($option_key, 0));
                if ($mapped) $add([$mapped]);
            }
    
            foreach ([
                $checkout_session_id !== '' ? get_option('yo_checkout_keycrm_session_order_' . md5($checkout_session_id), '') : '',
                $browser_buyer_id !== '' ? get_option('yo_checkout_keycrm_browser_order_' . md5($browser_buyer_id), '') : '',
                $email !== '' ? get_option('yo_checkout_keycrm_email_order_' . md5(mb_strtolower($email)), '') : '',
                $phone !== '' ? get_option('yo_checkout_keycrm_phone_order_' . md5(mb_strtolower($phone)), '') : '',
            ] as $mapped_order_id) {
                $mapped_order_id = preg_replace('/[^0-9]/', '', (string)$mapped_order_id);
                if ($mapped_order_id === '') continue;
                $local = $this->find_local_order_by_keycrm_order_id($mapped_order_id);
                if ($local) $add([$local]);
            }
    
            $meta_queries = [];
            if ($checkout_session_id !== '') $meta_queries[] = ['key'=>'checkout_session_id', 'value'=>$checkout_session_id];
            if ($browser_buyer_id !== '') $meta_queries[] = ['key'=>'browser_buyer_id', 'value'=>$browser_buyer_id];
            if ($email !== '') $meta_queries[] = ['key'=>'email', 'value'=>$email];
            if ($phone !== '') $meta_queries[] = ['key'=>'phone', 'value'=>$phone];
    
            foreach ($meta_queries as $mq) {
                $ids = get_posts([
                    'post_type' => $this->order_post_type,
                    'post_status' => 'publish',
                    'numberposts' => 20,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids',
                    'date_query' => [['after' => '2 days ago']],
                    'meta_query' => [
                        ['key'=>$mq['key'], 'value'=>$mq['value']],
                        ['key'=>'order_id', 'compare'=>'EXISTS'],
                    ],
                ]);
                $add($ids);
            }
    
            foreach ($candidates as $id) {
                if (get_post_type($id) !== $this->order_post_type) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id !== '') return $id;
            }
            return 0;
        }

    public function find_local_order_by_active_marker($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
            $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
            if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
            if ($posted_keycrm_order_id !== '') {
                $by_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
                if ($by_order && get_post_meta($by_order, 'paid', true) !== '1') return $by_order;
            }
    
            $keys = [];
            $session_hash = $this->marker_hash_value($checkout_session_id);
            $browser_hash = $this->marker_hash_value($browser_buyer_id);
            $email_hash = $this->marker_hash_value($data['email'] ?? '');
            $phone_clean = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));
            $phone_hash = $this->marker_hash_value($phone_clean);
            if ($session_hash) $keys[] = 'yo_checkout_keycrm_session_order_' . $session_hash;
            if ($browser_hash) $keys[] = 'yo_checkout_keycrm_browser_order_' . $browser_hash;
            if ($email_hash) $keys[] = 'yo_checkout_keycrm_email_order_' . $email_hash;
            if ($phone_hash) $keys[] = 'yo_checkout_keycrm_phone_order_' . $phone_hash;
    
            foreach ($keys as $key) {
                $order_id = preg_replace('/[^0-9]/', '', (string)get_option($key, ''));
                if ($order_id === '') continue;
                $local = $this->find_local_order_by_keycrm_order_id($order_id);
                if ($local && get_post_meta($local, 'paid', true) !== '1') return $local;
            }
            return 0;
        }

    public function find_local_order_by_keycrm_order_id($order_id) {
            $order_id = preg_replace('/[^0-9]/', '', (string)$order_id);
            if ($order_id === '') return 0;
            $mapped_id = absint(get_option('yo_checkout_keycrm_order_' . md5($order_id), 0));
            if ($mapped_id && get_post_type($mapped_id) === $this->order_post_type) return $mapped_id;
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'publish',
                'numberposts' => 1,
                'orderby' => 'date',
                'order' => 'DESC',
                'meta_key' => 'order_id',
                'meta_value' => $order_id,
                'fields' => 'ids',
                'date_query' => [['after' => '7 days ago']],
            ]);
            return !empty($ids[0]) ? absint($ids[0]) : 0;
        }

    public function find_reusable_unpaid_keycrm_local_id($data = [], $checkout_session_id = '', $browser_buyer_id = '', $exclude_local_id = 0, $posted_keycrm_order_id = '') {
            $exclude_local_id = absint($exclude_local_id);
            $candidates = [];
            $add_candidates = function($args) use (&$candidates) {
                $ids = get_posts(array_merge([
                    'post_type' => $this->order_post_type,
                    'post_status' => 'publish',
                    'numberposts' => 10,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'fields' => 'ids',
                    'date_query' => [['after' => '3 days ago']],
                ], $args));
                foreach ((array)$ids as $id) {
                    $id = absint($id);
                    if ($id && !in_array($id, $candidates, true)) $candidates[] = $id;
                }
            };
    
            $checkout_session_id = sanitize_text_field((string)$checkout_session_id);
            $browser_buyer_id = sanitize_text_field((string)$browser_buyer_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));
            $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
    
            if ($posted_keycrm_order_id !== '') $add_candidates(['meta_key'=>'order_id', 'meta_value'=>$posted_keycrm_order_id]);
    
            // Fast lookup from persistent marker options. This is more reliable than only searching
            // posts because the frontend can come back from a payment iframe with a new local draft.
            // The marker points to the first unpaid local checkout draft that already has a KeyCRM order ID.
            if ($checkout_session_id !== '') {
                $mapped = absint(get_option('yo_checkout_keycrm_session_' . md5($checkout_session_id), 0));
                if ($mapped && !in_array($mapped, $candidates, true)) $candidates[] = $mapped;
            }
            if ($browser_buyer_id !== '') {
                $mapped = absint(get_option('yo_checkout_keycrm_browser_' . md5($browser_buyer_id), 0));
                if ($mapped && !in_array($mapped, $candidates, true)) $candidates[] = $mapped;
                $mapped_order = preg_replace('/[^0-9]/', '', (string)get_option('yo_checkout_keycrm_browser_order_' . md5($browser_buyer_id), ''));
                if ($mapped_order !== '') $add_candidates(['meta_key'=>'order_id', 'meta_value'=>$mapped_order]);
            }
    
            if ($checkout_session_id !== '') $add_candidates(['meta_key'=>'checkout_session_id', 'meta_value'=>$checkout_session_id]);
            if ($browser_buyer_id !== '') $add_candidates(['meta_key'=>'browser_buyer_id', 'meta_value'=>$browser_buyer_id]);
            if ($email !== '') $add_candidates(['meta_key'=>'email', 'meta_value'=>$email]);
            if ($phone !== '') $add_candidates(['meta_key'=>'phone', 'meta_value'=>$phone]);
    
            foreach ($candidates as $id) {
                if ($exclude_local_id && $id === $exclude_local_id) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = trim((string)get_post_meta($id, 'order_id', true));
                if ($order_id === '') continue;
                return $id;
            }
            return 0;
        }

    public function remember_keycrm_checkout_marker($local_id) {
            $local_id = absint($local_id);
            if (!$local_id) return;
            $session = sanitize_text_field((string)get_post_meta($local_id, 'checkout_session_id', true));
            $browser = sanitize_text_field((string)get_post_meta($local_id, 'browser_buyer_id', true));
            if ($session !== '') update_option('yo_checkout_keycrm_session_' . md5($session), $local_id, false);
            if ($browser !== '') update_option('yo_checkout_keycrm_browser_' . md5($browser), $local_id, false);
            if (!headers_sent()) {
                setcookie('yo_checkout_local_order_id', (string)$local_id, time() + 2 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false);
            }
            $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($local_id, 'order_id', true));
            if ($order_id !== '') {
                update_option('yo_checkout_keycrm_order_' . md5($order_id), $local_id, false);
                if ($session !== '') update_option('yo_checkout_keycrm_session_order_' . md5($session), $order_id, false);
                if ($browser !== '') update_option('yo_checkout_keycrm_browser_order_' . md5($browser), $order_id, false);
                $email = sanitize_email((string)get_post_meta($local_id, 'email', true));
                $phone = preg_replace('/[^0-9+]/', '', (string)get_post_meta($local_id, 'phone', true));
                if ($email !== '') update_option('yo_checkout_keycrm_email_order_' . md5(mb_strtolower($email)), $order_id, false);
                if ($phone !== '') update_option('yo_checkout_keycrm_phone_order_' . md5(mb_strtolower($phone)), $order_id, false);
                if (!headers_sent()) {
                    setcookie('yo_checkout_keycrm_order_id', $order_id, time() + 2 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false);
                    setcookie('yo_checkout_local_order_id', (string)$local_id, time() + 2 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), false);
                }
            }
        }

    public function find_latest_open_keycrm_order_for_same_customer($data = [], $exclude_local_id = 0) {
            $exclude_local_id = absint($exclude_local_id);
            $email = sanitize_email($data['email'] ?? '');
            $phone_digits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
            $phone_tail = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
            $session = sanitize_text_field((string)($data['checkout_session_id'] ?? ''));
            $browser = sanitize_text_field((string)($data['browser_buyer_id'] ?? ''));
    
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'publish',
                'numberposts' => 120,
                'orderby' => 'date',
                'order' => 'DESC',
                'fields' => 'ids',
                'date_query' => [['after' => '24 hours ago']],
                'meta_query' => [['key' => 'order_id', 'compare' => 'EXISTS']],
            ]);
            foreach ((array)$ids as $id) {
                $id = absint($id);
                if (!$id || ($exclude_local_id && $id === $exclude_local_id)) continue;
                if (get_post_meta($id, 'paid', true) === '1') continue;
                $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
                if ($order_id === '') continue;
                $same_session = ($session !== '' && $session === (string)get_post_meta($id, 'checkout_session_id', true));
                $same_browser = ($browser !== '' && $browser === (string)get_post_meta($id, 'browser_buyer_id', true));
                $stored_email = sanitize_email((string)get_post_meta($id, 'email', true));
                $stored_phone_digits = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'phone', true));
                $stored_tail = strlen($stored_phone_digits) > 7 ? substr($stored_phone_digits, -7) : $stored_phone_digits;
                $same_email = ($email !== '' && $stored_email !== '' && strcasecmp($email, $stored_email) === 0);
                $same_phone = ($phone_tail !== '' && $stored_tail !== '' && $phone_tail === $stored_tail);
                if ($same_session || $same_browser || ($same_email && $same_phone)) return $id;
            }
            return 0;
        }

    public function ensure_keycrm_order($local_id, $mode = 'bank') {
            $ignore_stale_keycrm_marker = !empty($_POST['ignore_stale_keycrm_marker']);
            if ($ignore_stale_keycrm_marker) {
                delete_post_meta($local_id, 'order_id');
                delete_post_meta($local_id, 'buyer_id');
                update_post_meta($local_id, 'keycrm_created', '0');
            }
            $posted_keycrm_order_id = $ignore_stale_keycrm_marker ? '' : preg_replace('/[^0-9]/', '', (string) wp_unslash($_POST['keycrm_order_id'] ?? ''));
            if (!$ignore_stale_keycrm_marker && $posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->posted_cart_marker_keycrm_order_id();
            if (!$ignore_stale_keycrm_marker && $posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
            if ($posted_keycrm_order_id !== '' && trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
                $existing_by_keycrm_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
                if ($existing_by_keycrm_order && get_post_meta($existing_by_keycrm_order, 'paid', true) !== '1') {
                    $old_buyer_id = get_post_meta($existing_by_keycrm_order, 'buyer_id', true);
                    update_post_meta($local_id, 'order_id', $posted_keycrm_order_id);
                    if ($old_buyer_id) update_post_meta($local_id, 'buyer_id', $old_buyer_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                }
            }
            // If this local draft has no order_id yet, restore it from the persistent cart/browser marker.
            // This is the main protection against duplicate KeyCRM orders after the customer returns,
            // edits the cart and goes to payment again.
            if (!$ignore_stale_keycrm_marker && trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
                $d_for_marker = $this->get_order_data($local_id);
                $marker_order_id = '';
                if (!empty($d_for_marker['checkout_session_id'])) {
                    $marker_order_id = preg_replace('/[^0-9]/', '', (string)get_option('yo_checkout_keycrm_session_order_' . md5($d_for_marker['checkout_session_id']), ''));
                }
                if ($marker_order_id === '' && !empty($d_for_marker['browser_buyer_id'])) {
                    $marker_order_id = preg_replace('/[^0-9]/', '', (string)get_option('yo_checkout_keycrm_browser_order_' . md5($d_for_marker['browser_buyer_id']), ''));
                }
                if ($marker_order_id !== '') {
                    $existing_by_marker = $this->find_local_order_by_keycrm_order_id($marker_order_id);
                    if ($existing_by_marker) {
                        $old_buyer_id = get_post_meta($existing_by_marker, 'buyer_id', true);
                        if ($old_buyer_id) update_post_meta($local_id, 'buyer_id', $old_buyer_id);
                    }
                    update_post_meta($local_id, 'order_id', $marker_order_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                }
            }
    
            if (!$ignore_stale_keycrm_marker && trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
                $d_active = $this->get_order_data($local_id);
                $active_local = $this->find_local_order_by_active_marker($d_active, $d_active['checkout_session_id'] ?? '', $d_active['browser_buyer_id'] ?? '', $posted_keycrm_order_id);
                if ($active_local && $active_local !== $local_id) {
                    $active_order_id = get_post_meta($active_local, 'order_id', true);
                    $active_buyer_id = get_post_meta($active_local, 'buyer_id', true);
                    if ($active_order_id) update_post_meta($local_id, 'order_id', $active_order_id);
                    if ($active_buyer_id) update_post_meta($local_id, 'buyer_id', $active_buyer_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                }
            }
    
            // Explicit cart marker rule: if the browser/cart marker says that KeyCRM order already
            // exists, this local draft must be bound to that exact KeyCRM order and updated.
            // This is intentionally stronger than local_id/session lookup, because the customer can
            // return from the payment iframe, change the cart, and create a new local draft on the site.
            if ($posted_keycrm_order_id !== '') {
                $current_order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($local_id, 'order_id', true));
                if ($current_order_id === '' || $current_order_id !== $posted_keycrm_order_id) {
                    update_post_meta($local_id, 'order_id', $posted_keycrm_order_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                }
            }
    
            // Restore KeyCRM order ID from browser/cart marker before deciding to create a new one.
            if (!$ignore_stale_keycrm_marker && trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
                $d_for_marker = $this->get_order_data($local_id);
                $marker_order_id = $posted_keycrm_order_id;
                if ($marker_order_id === '' && !empty($d_for_marker['checkout_session_id'])) {
                    $marker_order_id = preg_replace('/[^0-9]/', '', (string)get_option('yo_checkout_keycrm_session_order_' . md5($d_for_marker['checkout_session_id']), ''));
                }
                if ($marker_order_id === '' && !empty($d_for_marker['browser_buyer_id'])) {
                    $marker_order_id = preg_replace('/[^0-9]/', '', (string)get_option('yo_checkout_keycrm_browser_order_' . md5($d_for_marker['browser_buyer_id']), ''));
                }
                if ($marker_order_id !== '') {
                    $old_local = $this->find_local_order_by_keycrm_order_id($marker_order_id);
                    $old_buyer_id = $old_local ? get_post_meta($old_local, 'buyer_id', true) : '';
                    if ($old_buyer_id) update_post_meta($local_id, 'buyer_id', $old_buyer_id);
                    update_post_meta($local_id, 'order_id', $marker_order_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                }
            }
    
            $existing_order_id = get_post_meta($local_id, 'order_id', true);
            if (!empty($existing_order_id)) {
                // If the customer returned from the payment step and changed the cart, update the
                // already-created KeyCRM order instead of creating a duplicate order.
                $this->remember_keycrm_checkout_marker($local_id);
                return $this->keycrm_update_existing_order($local_id, $mode);
            }
    
            // Safety net: the frontend may have created a fresh local draft after returning from
            // the payment screen. Before creating a new KeyCRM order, search for an existing unpaid
            // KeyCRM order linked to the same checkout_session_id / browser_buyer_id / email / phone.
            $current_data = $this->get_order_data($local_id);
            $reuse_local_id = $ignore_stale_keycrm_marker ? 0 : $this->find_reusable_unpaid_keycrm_local_id($current_data, $current_data['checkout_session_id'] ?? '', $current_data['browser_buyer_id'] ?? '', $local_id);
            if ($reuse_local_id) {
                $reuse_order_id = get_post_meta($reuse_local_id, 'order_id', true);
                $reuse_buyer_id = get_post_meta($reuse_local_id, 'buyer_id', true);
                if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
                if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
                update_post_meta($local_id, 'keycrm_created', '1');
                $this->remember_keycrm_checkout_marker($local_id);
                return $this->keycrm_update_existing_order($local_id, $mode);
            }
    
            $data = $this->get_order_data($local_id);
            $hard_reuse_id = $ignore_stale_keycrm_marker ? 0 : $this->find_hard_reusable_keycrm_local_id($data, $data['checkout_session_id'] ?? '', $data['browser_buyer_id'] ?? '', $posted_keycrm_order_id);
            if ($hard_reuse_id && $hard_reuse_id !== $local_id) {
                $reuse_order_id = get_post_meta($hard_reuse_id, 'order_id', true);
                $reuse_buyer_id = get_post_meta($hard_reuse_id, 'buyer_id', true);
                if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
                if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
                update_post_meta($local_id, 'keycrm_created', '1');
                $this->remember_keycrm_checkout_marker($local_id);
                return $this->keycrm_update_existing_order($local_id, $mode);
            }
    
            $contact_reuse_id = $ignore_stale_keycrm_marker ? 0 : $this->find_latest_unpaid_keycrm_order_by_contact($data, $local_id);
            if ($contact_reuse_id && $contact_reuse_id !== $local_id) {
                $reuse_order_id = get_post_meta($contact_reuse_id, 'order_id', true);
                $reuse_buyer_id = get_post_meta($contact_reuse_id, 'buyer_id', true);
                if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
                if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
                update_post_meta($local_id, 'keycrm_created', '1');
                $this->remember_keycrm_checkout_marker($local_id);
                return $this->keycrm_update_existing_order($local_id, $mode);
            }
    
            $broad_contact_reuse_id = $ignore_stale_keycrm_marker ? 0 : $this->find_recent_unpaid_keycrm_order_for_customer_broad($data, $local_id);
            if ($broad_contact_reuse_id && $broad_contact_reuse_id !== $local_id) {
                $reuse_order_id = get_post_meta($broad_contact_reuse_id, 'order_id', true);
                $reuse_buyer_id = get_post_meta($broad_contact_reuse_id, 'buyer_id', true);
                if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
                if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
                update_post_meta($local_id, 'keycrm_created', '1');
                $this->remember_keycrm_checkout_marker($local_id);
                return $this->keycrm_update_existing_order($local_id, $mode);
            }
    
            $s = $this->settings();
            $data = $this->get_order_data($local_id);
            $buyer_id = $this->keycrm_create_buyer($data, $s);
            if (is_wp_error($buyer_id)) return new WP_Error('keycrm_buyer', 'KeyCRM buyer was not created', $buyer_id->get_error_data());
            update_post_meta($local_id, 'buyer_id', $buyer_id);
            $data['buyer_id'] = $buyer_id;
            $order_id = $this->keycrm_create_order($data, $buyer_id, $s, $mode);
            if (is_wp_error($order_id) && $this->keycrm_error_invalid_buyer($order_id)) {
                $buyer_id = $this->keycrm_refresh_buyer_for_order_data($local_id, $data, $s);
                if (is_wp_error($buyer_id)) return $buyer_id;
                $order_id = $this->keycrm_create_order($data, $buyer_id, $s, $mode);
            }
            if (is_wp_error($order_id)) return $order_id;
            update_post_meta($local_id, 'order_id', $order_id);
            update_post_meta($local_id, 'keycrm_created', '1');
            $this->remember_keycrm_checkout_marker($local_id);
            wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $order_id . ' - ' . ($data['full_name'] ?? '')]);
            return true;
        }

    public function keycrm_create_buyer($d, $s) {
            $payload = ['full_name'=>$d['full_name'],'email'=>[$d['email']],'phone'=>[$d['phone']],'note'=>'Created from YOleotard website','shipping'=>[[
                'address'=>$d['address'],'additional_address'=>$d['additional_address'],'city'=>$d['city'],'zip_code'=>$d['zip_code'],'country'=>$d['country'],'recipient_full_name'=>$d['full_name'],'recipient_phone'=>$d['phone']
            ]]];
            $body = $this->keycrm_request('POST', '/buyer', $payload, $s);
            if (is_wp_error($body)) return $body;
            return $body['id'] ?? new WP_Error('keycrm_buyer', 'No buyer id', $body);
        }

    public function keycrm_build_order_payload($d, $buyer_id, $s, $mode = 'bank') {
            $tags = array_filter(array_map('intval', explode(',', (string)$s['keycrm_tag_id'])));
            $cart_items = $this->cart_items_from_order_data($d);
            $products = [];
            foreach ($cart_items as $item) {
                $item_discount = round(floatval($item['discount_eur'] ?? 0), 2);
                $product = [
                    'name' => $this->clean_product_title_for_display($item['title'] ?? 'Selected leotard'),
                    'price' => round(floatval($item['original_price_eur'] ?? ($item['price_eur'] ?? 0)), 2),
                    'quantity' => 1,
                    'picture' => esc_url_raw($item['image_url'] ?? ''),
                ];
                if ($item_discount > 0) {
                    $product['discount_amount'] = $item_discount;
                }
                $product_id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($item['product_id'] ?? ($item['feed_id'] ?? '')));
                if ($product_id !== '') $product['sku'] = $product_id;
                $products[] = $product;
            }
            if (!$products) $products[] = ['name'=>$this->clean_product_title_for_display($d['title']),'price'=>floatval($d['original_price_eur'] ?: $d['price_eur']),'quantity'=>1,'picture'=>$d['image_url']];
    
            $shipping_price = round(floatval($d['shipping_cost_eur'] ?? 0), 2);
            $service_fee = ($mode === 'card') ? round(floatval($d['card_fee_amount'] ?? 0), 2) : 0;
            $service_percent = ($mode === 'card') ? round(floatval($d['card_fee_percent'] ?? 0), 2) : 0;
            $buyer_comment = 'Order from YOleotard website';
            if (!empty($d['order_id'])) {
                $buyer_comment .= ' | Website checkout session updated for KeyCRM order #' . sanitize_text_field($d['order_id']) . '.';
            }
            if ($shipping_price > 0) {
                $buyer_comment .= ' | Nova Post shipping to ' . ($d['country'] ?? '') . ': €' . number_format($shipping_price, 2) . '.';
            }
            if ($service_fee > 0) {
                $buyer_comment .= ' | Card service fee: €' . number_format($service_fee, 2) . ' (' . number_format($service_percent, 2) . '%).';
            } elseif ($mode === 'bank') {
                $buyer_comment .= ' | SEPA/SWIFT invoice selected: card processing fee is not applied.';
            }
            if (!empty($d['promo_code_applied'])) {
                $buyer_comment .= ' | Promo code ' . sanitize_text_field($d['promo_code_applied']) . ': -€' . number_format(floatval($d['discount_eur']), 2) . '.';
            }
            $buyer_comment .= ' | Website total: €' . number_format(round(floatval($d['price_eur']) + $shipping_price + $service_fee, 2), 2) . '.';
    
            $payload = [
                'source_id'=>intval($s['keycrm_source_id']),
                'status_id'=>intval($s['keycrm_status_waiting']),
                'currency_id'=>intval($s['keycrm_currency_id']),
                'tags'=>$tags,
                'buyer'=>['id'=>$buyer_id,'full_name'=>$d['full_name'],'email'=>$d['email'],'phone'=>$d['phone']],
                'shipping'=>['address'=>$d['address'],'additional_address'=>$d['additional_address'],'city'=>$d['city'],'zip_code'=>$d['zip_code'],'country'=>$d['country'],'recipient_full_name'=>$d['full_name'],'recipient_phone'=>$d['phone']],
                'buyer_comment'=>$buyer_comment,
                'shipping_price'=>$shipping_price,
                'products'=>$products,
            ];
            if ($service_fee > 0) {
                $payload['taxes'] = $service_fee;
            }
            return $payload;
        }

    public function keycrm_get_order_products_for_sync($order_id, $s) {
            $order_id = preg_replace('/[^0-9]/', '', (string)$order_id);
            if ($order_id === '') return [];
            $body = $this->keycrm_request('GET', '/order/' . $order_id . '?include=products', [], $s);
            if (is_wp_error($body)) return [];
            $root = $body['data'] ?? $body;
            $products = [];
            foreach (['products','order_products','items'] as $key) {
                if (!empty($root[$key]) && is_array($root[$key])) { $products = $root[$key]; break; }
            }
            return is_array($products) ? $products : [];
        }

    public function keycrm_order_product_row_id($row) {
            if (!is_array($row)) return '';
            foreach (['id','order_product_id','order_productId','pivot_id','pivotId'] as $key) {
                if (!empty($row[$key]) && is_scalar($row[$key])) return preg_replace('/[^0-9]/', '', (string)$row[$key]);
            }
            return '';
        }

    public function keycrm_order_product_name($row) {
            if (!is_array($row)) return '';
            foreach (['name','product_name','title'] as $key) {
                if (!empty($row[$key]) && is_scalar($row[$key])) return (string)$row[$key];
            }
            if (!empty($row['product']) && is_array($row['product'])) {
                foreach (['name','title'] as $key) if (!empty($row['product'][$key]) && is_scalar($row['product'][$key])) return (string)$row['product'][$key];
            }
            return '';
        }

    public function keycrm_order_product_sku($row) {
            if (!is_array($row)) return '';
            foreach (['sku','article','code','product_sku','productSku'] as $key) {
                if (!empty($row[$key]) && is_scalar($row[$key])) return preg_replace('/[^A-Za-z0-9_-]/', '', (string)$row[$key]);
            }
            if (!empty($row['product']) && is_array($row['product'])) {
                foreach (['sku','article','code'] as $key) if (!empty($row['product'][$key]) && is_scalar($row['product'][$key])) return preg_replace('/[^A-Za-z0-9_-]/', '', (string)$row['product'][$key]);
            }
            return '';
        }

    public function keycrm_product_sync_key($name, $price = null) {
            $name = $this->clean_product_title_for_display((string)$name);
            $name = mb_strtolower($name);
            $name = preg_replace('/["\'\x{201c}\x{201d}\x{00ab}\x{00bb}]+/u', '', $name);
            $name = preg_replace('/\s+/u', ' ', trim($name));
            $price_part = ($price === null || $price === '') ? '' : ('|' . number_format(round(floatval($price), 2), 2, '.', ''));
            return $name . $price_part;
        }

    public function keycrm_prepare_products_for_full_sync($order_id, $payload, $s) {
            // KeyCRM uses products.*.id as the ID of the product row inside this order
            // (not the catalog product ID). When the website cart is edited, we try to
            // attach these row IDs to the current cart items so KeyCRM updates rows instead
            // of appending duplicates.
            if (empty($payload['products']) || !is_array($payload['products'])) return $payload;
            $existing = $this->keycrm_get_order_products_for_sync($order_id, $s);
            if (!$existing) return $payload;
    
            $map = [];
            $rows = [];
            foreach ($existing as $row) {
                if (!is_array($row)) continue;
                $row_id = $this->keycrm_order_product_row_id($row);
                $row_name = $this->keycrm_order_product_name($row);
                if ($row_id === '' || $row_name === '') continue;
                $row_sku = $this->keycrm_order_product_sku($row);
                $row_price = $row['price'] ?? ($row['price_sold'] ?? ($row['amount'] ?? null));
                $row_picture = $row['picture'] ?? ($row['image'] ?? ($row['image_url'] ?? ''));
                $row_data = ['id'=>$row_id, 'name'=>$row_name, 'sku'=>$row_sku, 'price'=>$row_price, 'picture'=>$row_picture, 'raw'=>$row];
                $rows[] = $row_data;
                if ($row_sku !== '') $map['sku|' . strtolower($row_sku)] = $row_data;
                $map[$this->keycrm_product_sync_key($row_name, $row_price)] = $row_data;
                $map[$this->keycrm_product_sync_key($row_name, null)] = $row_data;
                if ($row_picture) $map['img|' . md5((string)$row_picture)] = $row_data;
            }
    
            $used_ids = [];
            foreach ($payload['products'] as $i => $product) {
                if (!is_array($product)) continue;
                $key_price = $this->keycrm_product_sync_key($product['name'] ?? '', $product['price'] ?? null);
                $key_name = $this->keycrm_product_sync_key($product['name'] ?? '', null);
                $sku_key = !empty($product['sku']) ? ('sku|' . strtolower(preg_replace('/[^A-Za-z0-9_-]/', '', (string)$product['sku']))) : '';
                $img_key = !empty($product['picture']) ? ('img|' . md5((string)$product['picture'])) : '';
                $matched = ($sku_key && isset($map[$sku_key])) ? $map[$sku_key] : ($map[$key_price] ?? ($map[$key_name] ?? ($img_key && isset($map[$img_key]) ? $map[$img_key] : null)));
    
                // Fallback: if the product names are slightly different because of quote/unicode cleanup,
                // compare only the normalized name without price.
                if (!$matched) {
                    $wanted = $this->keycrm_product_sync_key($product['name'] ?? '', null);
                    foreach ($rows as $row) {
                        if (isset($used_ids[$row['id']])) continue;
                        $existing_key = $this->keycrm_product_sync_key($row['name'], null);
                        if ($wanted !== '' && ($wanted === $existing_key || strpos($wanted, $existing_key) !== false || strpos($existing_key, $wanted) !== false)) {
                            $matched = $row;
                            break;
                        }
                    }
                }
    
                // Final safe fallback for same-position edits. This helps avoid duplicates when KeyCRM
                // returns product rows without enough fields to match by name/image.
                if (!$matched && isset($rows[$i]) && empty($used_ids[$rows[$i]['id']])) {
                    $matched = $rows[$i];
                }
    
                if ($matched && !empty($matched['id'])) {
                    $payload['products'][$i]['id'] = intval($matched['id']);
                    $used_ids[$matched['id']] = true;
                }
            }
    
            // Do not send removed rows back with quantity = 0 here. The public KeyCRM API has been
            // unreliable for row deletion, and the important safety fix is preventing duplicates by
            // attaching IDs to matched rows before PUT /order/{id}.
    
            return $payload;
        }

    public function keycrm_try_clear_order_products_before_replace($order_id, $base_payload, $s) {
            // The public KeyCRM OpenAPI has PUT /order/{id}, but no documented DELETE product-row
            // endpoint. To avoid checkout-breaking DELETE errors, try a non-blocking clear pass with
            // products = [] before sending the current website cart. If KeyCRM ignores or rejects it,
            // checkout continues and the next PUT still updates matched rows by products.*.id.
            $order_id = preg_replace('/[^0-9]/', '', (string)$order_id);
            if ($order_id === '') return false;
            $payload = is_array($base_payload) ? $base_payload : [];
            $payload['products'] = [];
            unset($payload['taxes']);
            $result = $this->keycrm_request('PUT', '/order/' . $order_id, $payload, $s);
            return !is_wp_error($result);
        }

    public function keycrm_try_delete_order_product_row($order_id, $row_id, $s) {
            // Deprecated in v3.3.72: undocumented DELETE attempts caused connection errors on Step 3.
            // Product rows are now synchronized through PUT /order/{id} and products.*.id where possible.
            return false;
        }

    public function keycrm_update_existing_order($local_id, $mode = 'bank') {
            $s = $this->settings();
            $d = $this->get_order_data($local_id);
            if (empty($d['order_id'])) return true;
            $buyer_id = $d['buyer_id'] ?: get_post_meta($local_id, 'buyer_id', true);
            if (!$buyer_id) {
                $buyer_id = $this->keycrm_create_buyer($d, $s);
                if (is_wp_error($buyer_id)) return new WP_Error('keycrm_buyer', 'KeyCRM buyer was not created', $buyer_id->get_error_data());
                update_post_meta($local_id, 'buyer_id', $buyer_id);
                $d['buyer_id'] = $buyer_id;
            }
            $payload = $this->keycrm_build_order_payload($d, $buyer_id, $s, $mode);
            $payload = $this->keycrm_prepare_products_for_full_sync($d['order_id'], $payload, $s);
            $body = $this->keycrm_request('PUT', '/order/' . $d['order_id'], $payload, $s);
            if (is_wp_error($body) && $this->keycrm_error_invalid_buyer($body)) {
                $buyer_id = $this->keycrm_refresh_buyer_for_order_data($local_id, $d, $s);
                if (is_wp_error($buyer_id)) return $buyer_id;
                $payload = $this->keycrm_build_order_payload($d, $buyer_id, $s, $mode);
                $payload = $this->keycrm_prepare_products_for_full_sync($d['order_id'], $payload, $s);
                $body = $this->keycrm_request('PUT', '/order/' . $d['order_id'], $payload, $s);
            }
            if (is_wp_error($body) && $this->keycrm_error_needs_sku($body)) {
                // Keep the KeyCRM article column empty when possible. If KeyCRM requires SKU for this
                // specific update request, retry once with an internal fallback SKU so checkout is not blocked.
                $payload = $this->keycrm_payload_with_fallback_skus($payload);
                $body = $this->keycrm_request('PUT', '/order/' . $d['order_id'], $payload, $s);
            }
            if (is_wp_error($body) && $this->keycrm_error_is_order_not_found($body)) {
                // The website checkout draft can still contain an old KeyCRM ID if the test/order was
                // deleted in KeyCRM. In that case do not block Step 3: detach the stale ID and create
                // a fresh KeyCRM order for the current cart. Normal existing orders are still updated.
                delete_post_meta($local_id, 'order_id');
                update_post_meta($local_id, 'keycrm_created', '0');
                $new_order_id = $this->keycrm_create_order($d, $buyer_id, $s, $mode);
                if (is_wp_error($new_order_id) && $this->keycrm_error_invalid_buyer($new_order_id)) {
                    $buyer_id = $this->keycrm_refresh_buyer_for_order_data($local_id, $d, $s);
                    if (is_wp_error($buyer_id)) return $buyer_id;
                    $new_order_id = $this->keycrm_create_order($d, $buyer_id, $s, $mode);
                }
                if (is_wp_error($new_order_id)) return $new_order_id;
                update_post_meta($local_id, 'order_id', $new_order_id);
                update_post_meta($local_id, 'keycrm_created', '1');
                $this->remember_keycrm_checkout_marker($local_id);
                wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $new_order_id . ' - ' . ($d['full_name'] ?? '')]);
                return true;
            }
            if (is_wp_error($body) && isset($payload['taxes'])) {
                unset($payload['taxes']);
                $service_fee = ($mode === 'card') ? round(floatval($d['card_fee_amount'] ?? 0), 2) : 0;
                $service_percent = ($mode === 'card') ? round(floatval($d['card_fee_percent'] ?? 0), 2) : 0;
                if ($service_fee > 0) {
                    $payload['products'][] = ['name'=>'Card payment service fee (' . number_format($service_percent, 2) . '%)', 'price'=>$service_fee, 'quantity'=>1, 'picture'=>''];
                }
                $body = $this->keycrm_request('PUT', '/order/' . $d['order_id'], $payload, $s);
                if (is_wp_error($body) && $this->keycrm_error_invalid_buyer($body)) {
                    $buyer_id = $this->keycrm_refresh_buyer_for_order_data($local_id, $d, $s);
                    if (is_wp_error($buyer_id)) return $buyer_id;
                    $payload = $this->keycrm_build_order_payload($d, $buyer_id, $s, $mode);
                    $payload = $this->keycrm_prepare_products_for_full_sync($d['order_id'], $payload, $s);
                    $body = $this->keycrm_request('PUT', '/order/' . $d['order_id'], $payload, $s);
                }
                if (is_wp_error($body) && $this->keycrm_error_is_order_not_found($body)) {
                    delete_post_meta($local_id, 'order_id');
                    update_post_meta($local_id, 'keycrm_created', '0');
                    $new_order_id = $this->keycrm_create_order($d, $buyer_id, $s, $mode);
                    if (is_wp_error($new_order_id) && $this->keycrm_error_invalid_buyer($new_order_id)) {
                        $buyer_id = $this->keycrm_refresh_buyer_for_order_data($local_id, $d, $s);
                        if (is_wp_error($buyer_id)) return $buyer_id;
                        $new_order_id = $this->keycrm_create_order($d, $buyer_id, $s, $mode);
                    }
                    if (is_wp_error($new_order_id)) return $new_order_id;
                    update_post_meta($local_id, 'order_id', $new_order_id);
                    update_post_meta($local_id, 'keycrm_created', '1');
                    $this->remember_keycrm_checkout_marker($local_id);
                    wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $new_order_id . ' - ' . ($d['full_name'] ?? '')]);
                    return true;
                }
            }
            if (is_wp_error($body)) return $body;
            update_post_meta($local_id, 'keycrm_updated_at', time());
            return true;
        }

    public function keycrm_payload_has_zero_quantity_products($payload) {
            if (empty($payload['products']) || !is_array($payload['products'])) return false;
            foreach ($payload['products'] as $product) {
                if (is_array($product) && array_key_exists('quantity', $product) && floatval($product['quantity']) <= 0) return true;
            }
            return false;
        }

    public function keycrm_payload_without_zero_quantity_products($payload) {
            if (empty($payload['products']) || !is_array($payload['products'])) return $payload;
            $payload['products'] = array_values(array_filter($payload['products'], function($product) {
                if (!is_array($product)) return false;
                if (array_key_exists('quantity', $product) && floatval($product['quantity']) <= 0) return false;
                return true;
            }));
            return $payload;
        }

    public function keycrm_payload_with_fallback_skus($payload) {
            if (!is_array($payload)) return $payload;
            if (empty($payload['products']) || !is_array($payload['products'])) return $payload;
            foreach ($payload['products'] as $i => $product) {
                if (!is_array($product)) continue;
                if (!empty($product['sku'])) continue;
                $name = $product['name'] ?? ('YOleotard item ' . ($i + 1));
                $picture = $product['picture'] ?? '';
                $price = $product['price'] ?? '';
                $payload['products'][$i]['sku'] = $this->keycrm_product_sku($name, $picture, $price);
            }
            return $payload;
        }

    public function keycrm_error_needs_sku($err) {
            if (!is_wp_error($err)) return false;
            $data = $err->get_error_data();
            $txt = is_scalar($data) ? (string)$data : wp_json_encode($data);
            return (stripos((string)$txt, 'sku') !== false && stripos((string)$txt, 'required') !== false);
        }

    public function keycrm_error_invalid_buyer($err) {
            if (!is_wp_error($err)) return false;
            $data = $err->get_error_data();
            $txt = is_scalar($data) ? (string)$data : wp_json_encode($data);
            $txt = (string)$txt;
            return stripos($txt, 'buyer.id') !== false
                && (stripos($txt, 'invalid') !== false || stripos($txt, 'selected buyer') !== false);
        }

    public function keycrm_refresh_buyer_for_order_data($local_id, &$d, $s) {
            delete_post_meta($local_id, 'buyer_id');
            $buyer_id = $this->keycrm_create_buyer($d, $s);
            if (is_wp_error($buyer_id)) {
                return new WP_Error('keycrm_buyer', 'KeyCRM buyer was not created after invalid buyer retry', $buyer_id->get_error_data());
            }
            update_post_meta($local_id, 'buyer_id', $buyer_id);
            $d['buyer_id'] = $buyer_id;
            return $buyer_id;
        }

    public function keycrm_create_order($d, $buyer_id, $s, $mode = 'bank') {
            $tags = array_filter(array_map('intval', explode(',', (string)$s['keycrm_tag_id'])));
            $cart_items = $this->cart_items_from_order_data($d);
            $products = [];
            foreach ($cart_items as $item) {
                $item_discount = round(floatval($item['discount_eur'] ?? 0), 2);
                $product = [
                    'name' => $this->clean_product_title_for_display($item['title'] ?? 'Selected leotard'),
                    'price' => round(floatval($item['original_price_eur'] ?? ($item['price_eur'] ?? 0)), 2),
                    'quantity' => 1,
                    'picture' => esc_url_raw($item['image_url'] ?? ''),
                ];
                if ($item_discount > 0) {
                    // KeyCRM product-level discount: visible in the product row as "Скидка на товар".
                    $product['discount_amount'] = $item_discount;
                }
                $product_id = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($item['product_id'] ?? ($item['feed_id'] ?? '')));
                if ($product_id !== '') $product['sku'] = $product_id;
                $products[] = $product;
            }
            if (!$products) $products[] = ['name'=>$this->clean_product_title_for_display($d['title']),'price'=>floatval($d['original_price_eur'] ?: $d['price_eur']),'quantity'=>1,'picture'=>$d['image_url']];
            $shipping_price = round(floatval($d['shipping_cost_eur'] ?? 0), 2);
            $service_fee = ($mode === 'card') ? round(floatval($d['card_fee_amount'] ?? 0), 2) : 0;
            $service_percent = ($mode === 'card') ? round(floatval($d['card_fee_percent'] ?? 0), 2) : 0;
            $buyer_comment = 'Order from YOleotard website';
            if ($shipping_price > 0) {
                $buyer_comment .= ' | Nova Post shipping to ' . ($d['country'] ?? '') . ': €' . number_format($shipping_price, 2) . '.';
            }
            if ($service_fee > 0) {
                $buyer_comment .= ' | Card service fee: €' . number_format($service_fee, 2) . ' (' . number_format($service_percent, 2) . '%).';
            } elseif ($mode === 'bank') {
                $buyer_comment .= ' | SEPA/SWIFT invoice selected: card processing fee is not applied.';
            }
            if (!empty($d['promo_code_applied'])) {
                $buyer_comment .= ' | Promo code ' . sanitize_text_field($d['promo_code_applied']) . ': -€' . number_format(floatval($d['discount_eur']), 2) . '.';
            }
            $buyer_comment .= ' | Website total: €' . number_format(round(floatval($d['price_eur']) + $shipping_price + $service_fee, 2), 2) . '.';
            $payload = ['source_id'=>intval($s['keycrm_source_id']),'status_id'=>intval($s['keycrm_status_waiting']),'currency_id'=>intval($s['keycrm_currency_id']),'tags'=>$tags,
                'buyer'=>['id'=>$buyer_id,'full_name'=>$d['full_name'],'email'=>$d['email'],'phone'=>$d['phone']],
                'shipping'=>['address'=>$d['address'],'additional_address'=>$d['additional_address'],'city'=>$d['city'],'zip_code'=>$d['zip_code'],'country'=>$d['country'],'recipient_full_name'=>$d['full_name'],'recipient_phone'=>$d['phone']],
                'buyer_comment'=>$buyer_comment,'shipping_price'=>$shipping_price,'products'=>$products];
            // Discounts are sent per product row so they are visible in KeyCRM near each model.
            // Do not also send order-level discount_amount here, otherwise KeyCRM may double-count the discount.
            // KeyCRM OpenAPI expects `taxes` to be a FLOAT amount, not an array and not a percent.
            // The previous version sent an array with rate/amount, so KeyCRM interpreted it incorrectly
            // and showed only about €1 in Sales tax. Here we send exactly the calculated card fee,
            // e.g. €200 + 2% = taxes €4 and total €204.
            if ($service_fee > 0) {
                $payload['taxes'] = $service_fee;
            }
            $body = $this->keycrm_request('POST', '/order', $payload, $s);
            if (is_wp_error($body) && $this->keycrm_error_needs_sku($body)) {
                $payload = $this->keycrm_payload_with_fallback_skus($payload);
                $body = $this->keycrm_request('POST', '/order', $payload, $s);
            }
            if (is_wp_error($body) && ($service_fee > 0 || isset($payload['discount_amount']))) {
                unset($payload['taxes']);
                if (isset($payload['discount_amount'])) {
                    unset($payload['discount_amount']);
                    $payload['buyer_comment'] .= ' | Discount: €' . number_format(floatval($d['discount_eur']), 2) . ' already included in website total.';
                }
                if ($service_fee > 0) {
                    $payload['products'][] = ['name'=>'Card payment service fee (' . number_format($service_percent, 2) . '%)', 'price'=>$service_fee, 'quantity'=>1, 'picture'=>''];
                }
                $body = $this->keycrm_request('POST', '/order', $payload, $s);
                if (is_wp_error($body) && $this->keycrm_error_needs_sku($body)) {
                    $payload = $this->keycrm_payload_with_fallback_skus($payload);
                    $body = $this->keycrm_request('POST', '/order', $payload, $s);
                }
            }
            if (is_wp_error($body)) return $body;
            return $body['id'] ?? new WP_Error('keycrm_order', 'No order id', $body);
        }

    public function keycrm_error_is_order_not_found($error) {
            if (!is_wp_error($error)) return false;
            $data = $error->get_error_data();
            $code = intval($data['code'] ?? 0);
            $message = '';
            if (isset($data['body']['message'])) $message = (string)$data['body']['message'];
            return $code === 404 && stripos($message, 'Order') !== false && stripos($message, 'not found') !== false;
        }

    public function keycrm_request($method, $path, $payload, $s=null) {
            $s = $s ?: $this->settings();
            if (empty($s['keycrm_token'])) return new WP_Error('no_token','KeyCRM token is empty');
            $request_args = ['method'=>$method,'headers'=>['Authorization'=>'Bearer '.$s['keycrm_token'],'Content-Type'=>'application/json','Accept'=>'application/json'],'timeout'=>30];
            if (!in_array(strtoupper((string)$method), ['GET', 'HEAD'], true)) {
                $request_args['body'] = wp_json_encode($payload);
            }
            $resp = wp_remote_request('https://openapi.keycrm.app/v1' . $path, $request_args);
            if (is_wp_error($resp)) return $resp;
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            $code = wp_remote_retrieve_response_code($resp);
            if ($code < 200 || $code >= 300) return new WP_Error('keycrm_http','KeyCRM HTTP error', ['code'=>$code,'body'=>$body]);
            return $body;
        }

    private function latest_local_keycrm_order_id() {
            if ($this->order_post_type === '' || !function_exists('get_posts')) return 0;
            $ids = get_posts([
                'post_type' => $this->order_post_type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_key' => 'order_id',
                'orderby' => 'meta_value_num',
                'order' => 'DESC',
                'no_found_rows' => true,
            ]);
            if (!$ids) return 0;
            return absint(get_post_meta(absint($ids[0]), 'order_id', true));
        }

    public function remember_latest_order_id($order_id) {
            $order_id = absint($order_id);
            if (!$order_id) return;
            $stored = absint(get_option('yo_checkout_keycrm_latest_order_id', 0));
            if ($order_id > $stored) update_option('yo_checkout_keycrm_latest_order_id', $order_id, false);
            set_transient('yo_checkout_keycrm_latest_order_id', max($order_id, $stored), 15);
        }

    public function next_order_id() {
            $cached_latest_id = absint(get_transient('yo_checkout_keycrm_latest_order_id'));
            if ($cached_latest_id) return $cached_latest_id + 1;

            $body = $this->keycrm_request('GET', '/order?limit=1&sort=-id', []);
            if (!is_wp_error($body)) {
                $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
                $latest_id = isset($rows[0]['id']) ? absint($rows[0]['id']) : 0;
                if ($latest_id) {
                    $this->remember_latest_order_id($latest_id);
                    return $latest_id + 1;
                }
            }

            $remembered_id = absint(get_option('yo_checkout_keycrm_latest_order_id', 0));
            $local_id = $this->latest_local_keycrm_order_id();
            $fallback_id = max($remembered_id, $local_id);
            if ($fallback_id) return $fallback_id + 1;

            if (is_wp_error($body)) {
                return new WP_Error('keycrm_next_order_lookup_failed', 'Could not read the latest KeyCRM order', $body->get_error_data());
            }
            return new WP_Error('keycrm_next_order_missing', 'KeyCRM did not return the latest order ID', $body);
        }

    public function keycrm_add_payment($local_id, $status, $description) {
            $s = $this->settings(); $d=$this->get_order_data($local_id);
            $provider = get_post_meta($local_id, 'payment_provider', true) ?: 'monobank';
            if ($status === 'paid') {
                if ($provider === 'western_bid') {
                    $method = $s['keycrm_payment_method_western_bid'] ?? ($s['keycrm_payment_method_wayforpay'] ?? '8');
                } else {
                    $method = ($provider === 'wayforpay') ? ($s['keycrm_payment_method_wayforpay'] ?? '8') : ($s['keycrm_payment_method_card'] ?? '');
                }
                $amount = floatval($d['card_total_amount'] ?: (floatval($d['price_eur']) + floatval($d['shipping_cost_eur'])));
                $fee = floatval($d['card_fee_amount'] ?: 0);
                $percent = floatval($d['card_fee_percent'] ?: 0);
                if ($fee > 0) {
                    $description .= ' | Card service fee: €' . number_format($fee, 2) . ' (' . number_format($percent, 2) . '%). Product: €' . number_format(floatval($d['price_eur']), 2) . ', shipping: €' . number_format(floatval($d['shipping_cost_eur']), 2) . ', total charged: €' . number_format($amount, 2);
                }
            } else {
                $method = $s['keycrm_payment_method_bank'];
                $amount = floatval($d['price_eur']) + floatval($d['shipping_cost_eur']);
            }
            if (!$method) return;
            $payload = ['payment_method_id'=>intval($method),'amount'=>$amount,'status'=>$status,'description'=>$description];
            $this->keycrm_request('POST', '/order/' . $d['order_id'] . '/payment', $payload, $s);
        }

    public function keycrm_update_order_comment($local_id, $comment) {
            $s = $this->settings();
            $d = $this->get_order_data($local_id);
            if (empty($d['order_id'])) return;
            $this->keycrm_request('PUT', '/order/' . $d['order_id'], ['buyer_comment' => $comment], $s);
        }

    private function ensure_keycrm_card_order($local_id) {
            $local_id = absint($local_id);
            if (!$local_id) return new WP_Error('keycrm_local_order', 'Local checkout order was not found');

            $existing_order_id = absint(get_post_meta($local_id, 'order_id', true));
            if ($existing_order_id && get_post_meta($local_id, 'keycrm_after_payment_done', true) === '1') {
                return $existing_order_id;
            }
            if ($existing_order_id) {
                $updated = $this->keycrm_update_existing_order($local_id, 'card');
                if (is_wp_error($updated)) return $updated;
                $current_order_id = absint(get_post_meta($local_id, 'order_id', true));
                if ($current_order_id) {
                    update_post_meta($local_id, 'keycrm_created', '1');
                    update_post_meta($local_id, 'keycrm_card_order_ready', '1');
                    update_post_meta($local_id, 'keycrm_after_payment_done', '1');
                    $this->remember_keycrm_checkout_marker($local_id);
                    return $current_order_id;
                }
            }

            $s = $this->settings();
            delete_post_meta($local_id, 'buyer_id');
            delete_post_meta($local_id, 'order_id');
            update_post_meta($local_id, 'keycrm_created', '0');

            $d = $this->get_order_data($local_id);
            $buyer_id = $this->keycrm_create_buyer($d, $s);
            if (is_wp_error($buyer_id)) return new WP_Error('keycrm_buyer', 'KeyCRM buyer was not created', $buyer_id->get_error_data());

            update_post_meta($local_id, 'buyer_id', $buyer_id);
            $d['buyer_id'] = $buyer_id;
            $order_id = $this->keycrm_create_order($d, $buyer_id, $s, 'card');
            if (is_wp_error($order_id)) return $order_id;

            update_post_meta($local_id, 'order_id', $order_id);
            update_post_meta($local_id, 'keycrm_created', '1');
            update_post_meta($local_id, 'keycrm_card_order_ready', '1');
            update_post_meta($local_id, 'keycrm_after_payment_done', '1');
            $this->remember_keycrm_checkout_marker($local_id);
            wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $order_id . ' - ' . ($d['full_name'] ?? '')]);
            return $order_id;
        }

    public function ensure_keycrm_order_after_successful_card_payment($local_id) {
            return $this->ensure_keycrm_card_order($local_id);
        }
}
