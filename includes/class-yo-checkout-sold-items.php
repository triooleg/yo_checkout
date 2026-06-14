<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Sold_Items_Service {
    const OPT = 'yo_checkout_invoice_settings';
    const CPT = 'yo_invoice_order';
    private $disabled_match_log = [];
    private $disabled_identity_keys = [];
    private $card_default_match_log = [];
    private $card_default_identity_keys = [];

    public function append_auto_hide_log($entry) {
        $saved = get_option(self::OPT, []);
        $old = (string)($saved['auto_hide_sold_log'] ?? '');
        $saved['auto_hide_sold_log'] = mb_substr((string)$entry . $old, 0, 5000);
        update_option(self::OPT, $saved, false);
    }

    public function order_label($local_id) {
        $local_id = absint($local_id);
        $keycrm_order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($local_id, 'order_id', true));
        if ($keycrm_order_id !== '') {
            return 'Order #' . $keycrm_order_id . ' (local #' . $local_id . ')';
        }
        return 'local order #' . $local_id . ' (KeyCRM order pending)';
    }

    public function auto_hide_sold_items_after_payment($local_id) {
        $local_id = absint($local_id);
        $s = self::settings();
        $label = $this->order_label($local_id);
        $this->append_auto_hide_log('[' . current_time('mysql') . '] Auto-hide started for ' . $label . ".\n");

        if (($s['auto_hide_sold_enabled'] ?? '1') !== '1') {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] ' . $label . ": auto-hide is disabled in settings.\n");
            return;
        }

        $d = $this->get_order_data($local_id);
        $items = $this->cart_items_from_order_data($d);
        $titles = [];
        foreach ($items as $item) {
            $title = trim(wp_strip_all_tags((string)($item['title'] ?? '')));
            $product_id = $this->sanitize_product_id($item['product_id'] ?? ($item['feed_id'] ?? ''));
            if ($title !== '') $titles[] = ['title' => $title, 'product_id' => $product_id];
        }
        $titles = $this->unique_sold_items($titles);
        if (!$titles) {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] ' . $label . ": no product title found for auto-hide. Builder status was not changed.\n");
            return;
        }

        if (($s['auto_hide_sold_frontend_fallback'] ?? '0') === '1') {
            $sold = get_option('yo_checkout_sold_hidden_titles', []);
            if (!is_array($sold)) $sold = [];
            foreach ($titles as $item) {
                $title = $item['title'];
                if (!in_array($title, $sold, true)) $sold[] = $title;
            }
            update_option('yo_checkout_sold_hidden_titles', array_values($sold), false);
        }

        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        $log_titles = array_map(function($item) { return $this->clean_log_text($item['title']) . (!empty($item['product_id']) ? ' [' . $item['product_id'] . ']' : ''); }, $titles);
        $log = '[' . current_time('mysql') . '] ' . $label . ': ' . implode(' | ', $log_titles) . "\n";
        if (!$page_id || get_post_type($page_id) === false) {
            $this->append_auto_hide_log($log . "No valid YOOtheme page ID. Builder status was not changed.\n");
            return;
        }

        $this->ensure_page_backup($page_id, $local_id);
        $this->disabled_match_log = [];
        $this->disabled_identity_keys = [];

        $changed_any = false;
        $post = get_post($page_id);
        if ($post) {
            $content = (string)$post->post_content;
            $new_content = $content;
            $changed_content = false;
            foreach ($titles as $sold_item) {
                $item_changed = false;
                $candidate_content = $this->disable_titles_in_yootheme_storage_string($new_content, [$sold_item], $item_changed);
                if ($item_changed && $candidate_content !== $new_content) {
                    $new_content = $candidate_content;
                    $changed_content = true;
                }
            }
            if ($changed_content && $new_content !== $content) {
                wp_update_post(wp_slash(['ID' => $page_id, 'post_content' => $new_content]));
                $changed_any = true;
                $log .= "Updated post_content.\n";
            }
        }

        $meta = get_post_meta($page_id);
        foreach ($meta as $meta_key => $values) {
            $meta_key = (string)$meta_key;
            if (strpos($meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            foreach ((array)$values as $value) {
                $changed_meta = false;
                $new_value = $value;
                foreach ($titles as $sold_item) {
                    $item_changed = false;
                    $candidate_value = $this->disable_titles_in_yootheme_storage_mixed($new_value, [$sold_item], $item_changed);
                    if ($item_changed && $candidate_value !== $new_value) {
                        $new_value = $candidate_value;
                        $changed_meta = true;
                    }
                }
                if ($changed_meta && $new_value !== $value) {
                    update_post_meta($page_id, $meta_key, $new_value);
                    $changed_any = true;
                    $log .= "Updated meta: " . $meta_key . "\n";
                    break;
                }
            }
        }

        if (!$changed_any) {
            $log .= "YOOtheme Builder JSON grid item was not found in the published page content/meta. Builder status was not changed.\n";
        } else {
            update_post_meta($local_id, 'auto_hide_sold_done', '1');
            if ($this->disabled_match_log) {
                foreach (array_values(array_unique($this->disabled_match_log)) as $match_line) {
                    $log .= "Disabled matched item: " . $match_line . "\n";
                }
            }
        }
        $this->append_auto_hide_log($log);
        if (function_exists('clean_post_cache')) clean_post_cache($page_id);
    }

    public function mark_bank_invoice_items_card_default($local_id, $cart_hash = '') {
        $local_id = absint($local_id);
        $cart_hash = sanitize_text_field((string)$cart_hash);
        if (!$local_id || get_post_type($local_id) !== self::CPT) return false;

        if ($cart_hash !== '' && hash_equals((string)get_post_meta($local_id, 'bank_invoice_card_default_hash', true), $cart_hash)) {
            return true;
        }

        $s = self::settings();
        $label = $this->order_label($local_id);
        $d = $this->get_order_data($local_id);
        $items = $this->cart_items_from_order_data($d);
        $titles = [];
        foreach ($items as $item) {
            $title = trim(wp_strip_all_tags((string)($item['title'] ?? '')));
            $product_id = $this->sanitize_product_id($item['product_id'] ?? ($item['feed_id'] ?? ''));
            if ($title !== '') $titles[] = ['title' => $title, 'product_id' => $product_id];
        }
        $titles = $this->unique_sold_items($titles);
        if (!$titles) {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] ' . $label . ": no product title found for bank invoice Card Default style. Builder style was not changed.\n");
            return false;
        }

        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        $log_titles = array_map(function($item) { return $this->clean_log_text($item['title']) . (!empty($item['product_id']) ? ' [' . $item['product_id'] . ']' : ''); }, $titles);
        $log = '[' . current_time('mysql') . '] ' . $label . ' bank invoice Card Default style: ' . implode(' | ', $log_titles) . "\n";
        if (!$page_id || get_post_type($page_id) === false) {
            $this->append_auto_hide_log($log . "No valid YOOtheme page ID. Builder style was not changed.\n");
            return false;
        }

        $this->ensure_page_backup($page_id, $local_id);
        $this->card_default_match_log = [];
        $this->card_default_identity_keys = [];

        $changed_any = false;
        $post = get_post($page_id);
        if ($post) {
            $content = (string)$post->post_content;
            $new_content = $content;
            $changed_content = false;
            foreach ($titles as $sold_item) {
                $item_changed = false;
                $candidate_content = $this->set_card_default_titles_in_yootheme_storage_string($new_content, [$sold_item], $item_changed);
                if ($item_changed && $candidate_content !== $new_content) {
                    $new_content = $candidate_content;
                    $changed_content = true;
                }
            }
            if ($changed_content && $new_content !== $content) {
                wp_update_post(wp_slash(['ID' => $page_id, 'post_content' => $new_content]));
                $changed_any = true;
                $log .= "Updated post_content.\n";
            }
        }

        $meta = get_post_meta($page_id);
        foreach ($meta as $meta_key => $values) {
            $meta_key = (string)$meta_key;
            if (strpos($meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            foreach ((array)$values as $value) {
                $changed_meta = false;
                $new_value = $value;
                foreach ($titles as $sold_item) {
                    $item_changed = false;
                    $candidate_value = $this->set_card_default_titles_in_yootheme_storage_mixed($new_value, [$sold_item], $item_changed);
                    if ($item_changed && $candidate_value !== $new_value) {
                        $new_value = $candidate_value;
                        $changed_meta = true;
                    }
                }
                if ($changed_meta && $new_value !== $value) {
                    update_post_meta($page_id, $meta_key, $new_value);
                    $changed_any = true;
                    $log .= "Updated meta: " . $meta_key . "\n";
                    break;
                }
            }
        }

        if (!$changed_any) {
            update_post_meta($local_id, 'bank_invoice_card_default_error', 'YOOtheme Builder JSON grid item was not found.');
            $log .= "YOOtheme Builder JSON grid item was not found. Builder style was not changed.\n";
        } else {
            update_post_meta($local_id, 'bank_invoice_card_default_done', '1');
            if ($cart_hash !== '') update_post_meta($local_id, 'bank_invoice_card_default_hash', $cart_hash);
            delete_post_meta($local_id, 'bank_invoice_card_default_error');
            if ($this->card_default_match_log) {
                foreach (array_values(array_unique($this->card_default_match_log)) as $match_line) {
                    $log .= "Card Default matched item: " . $match_line . "\n";
                }
            }
        }

        $this->append_auto_hide_log($log);
        if (function_exists('clean_post_cache')) clean_post_cache($page_id);
        return $changed_any;
    }

    public function is_yootheme_product_title_available($page_id, $title) {
        return $this->is_yootheme_product_available($page_id, $title, '');
    }

    public function is_yootheme_product_available($page_id, $title, $product_id = '') {
        $title = (string)$title;
        $product_id = $this->sanitize_product_id($product_id);
        if ($title === '' && $product_id === '') return false;

        $content = (string)get_post_field('post_content', $page_id);
        if ($content !== '') {
            $segment = $this->find_yootheme_layout_json_segment($content);
            if ($segment && !empty($segment['json'])) {
                $json = trim($segment['json']);
                $decoded = json_decode($json, true);
                if (!is_array($decoded)) {
                    $unslashed = wp_unslash($json);
                    if ($unslashed !== $json) $decoded = json_decode($unslashed, true);
                }
                if (is_array($decoded)) {
                    $available = $this->yootheme_product_availability_from_node($decoded, $title, $product_id);
                    if ($available !== null) return (bool)$available;
                }
            }

            if ($product_id !== '' && $this->storage_text_contains_product_id($content, $product_id)) return true;
            if ($title !== '' && $this->title_matches_sold_item($content, [['title' => $title, 'product_id' => $product_id]]) && stripos($content, '<li') !== false) return true;
        }

        $meta = get_post_meta($page_id);
        foreach ($meta as $meta_key => $values) {
            if (strpos((string)$meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            foreach ((array)$values as $value) {
                $found = $this->yootheme_product_availability_from_storage_value($value, $title, $product_id);
                if ($found !== null) return (bool)$found;
            }
        }

        return false;
    }

    public function render_sold_items_hider() {
        $s = self::settings();
        if (($s['auto_hide_sold_enabled'] ?? '1') !== '1') return;
        if (isset($s['auto_hide_sold_frontend_fallback']) && (string)$s['auto_hide_sold_frontend_fallback'] === '0') return;
        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        if ($page_id && !is_page($page_id) && !is_front_page()) return;
        $titles = get_option('yo_checkout_sold_hidden_titles', []);
        if (!is_array($titles) || !$titles) return;
        $titles = array_values(array_filter(array_map('sanitize_text_field', $titles)));
        if (!$titles) return;
        ?>
<script id="yo-sold-items-hider">
(function(){
  var soldTitles = <?php echo wp_json_encode($titles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  function decodeEscapes(s){
    return String(s||'')
      .replace(/\\u([0-9a-fA-F]{4})/g, function(_,h){ return String.fromCharCode(parseInt(h,16)); })
      .replace(/(^|[^\\])u([0-9a-fA-F]{4})/g, function(m,p,h){ return p + String.fromCharCode(parseInt(h,16)); });
  }
  function norm(s){
    return decodeEscapes(s)
      .toLowerCase()
      .replace(/[вЂ™вЂ`Вґ]/g,"'")
      .replace(/[вЂњвЂќВ«В»]/g,'"')
      .replace(/[вЂ“вЂ”в€’]/g,'-')
      .replace(/&nbsp;/g,' ')
      .replace(/[^a-z0-9Р°-СЏС–С—С”Т‘'" -]+/gi,' ')
      .replace(/\s+/g,' ')
      .trim();
  }
  function words(s){ return norm(s).split(' ').filter(function(w){ return w.length > 2 && !/^(new|for|the|and|height|leotard|author|author's|authors|dress|figure|skating)$/.test(w); }); }
  function profile(title){
    title = decodeEscapes(title);
    var n = norm(title);
    var quoted = [];
    var re = /["В«вЂњ]([^"В»вЂќ]{3,})["В»вЂќ]/g, m;
    while((m = re.exec(title))) quoted.push(norm(m[1]));
    var height = (n.match(/\b\d{2,3}\s*-\s*\d{2,3}\b/)||[''])[0];
    var keyWords = [];
    quoted.forEach(function(q){ keyWords = keyWords.concat(words(q)); });
    if(!keyWords.length) keyWords = words(n).slice(-5);
    return {raw:n, quoted:quoted.filter(Boolean), height:height, words:keyWords};
  }
  var profiles = soldTitles.map(profile).filter(function(p){ return p.raw || p.words.length; });
  function cardMatches(text, p){
    var t = norm(text);
    if(!t) return false;
    if(p.raw && (t.indexOf(p.raw) !== -1 || p.raw.indexOf(t) !== -1)) return true;
    var quoteMatch = p.quoted.length ? p.quoted.some(function(q){ return q && t.indexOf(q) !== -1; }) : false;
    var wordHits = p.words.filter(function(w){ return t.indexOf(w) !== -1; }).length;
    var heightOk = !p.height || t.indexOf(p.height) !== -1;
    return (quoteMatch && heightOk) || (wordHits >= Math.min(2, p.words.length || 2) && heightOk);
  }
  function forbidden(el){ return !!(el && el.closest && el.closest('header, footer, nav, .tm-header, .tm-footer, .uk-navbar, .uk-offcanvas, #wpadminbar')); }
  function productSignals(el){
    return !!(el && el.querySelector && (
      el.querySelector('.yo-buy-button, .yo-checkout-buy, [data-yo-title], [data-yo-price], .yo-price') ||
      (el.querySelector('img') && el.querySelector('h1,h2,h3,h4,h5,h6,.el-title,.uk-card-title'))
    ));
  }
  function findCard(el){
    if(!el || forbidden(el)) return null;
    var selectors = ['.el-item', '.uk-card', 'article', 'li', '.uk-grid > div', '.uk-grid > *', '[class*="uk-width"]'];
    for(var i=0;i<selectors.length;i++){
      var c = el.closest && el.closest(selectors[i]);
      if(c && !forbidden(c) && (c.textContent||'').length < 5000 && productSignals(c)) return c;
    }
    var cur = el;
    for(var d=0; cur && d<10; d++, cur=cur.parentElement){
      if(cur === document.body || forbidden(cur)) break;
      if((cur.textContent||'').length < 5000 && productSignals(cur)) return cur;
    }
    return null;
  }
  function hideCard(card){
    if(!card || card.dataset.yoSoldHidden === '1') return;
    card.dataset.yoSoldHidden = '1';
    card.style.setProperty('display','none','important');
  }
  function hideSold(){
    if(!profiles.length) return;
    document.querySelectorAll('[data-yo-title]').forEach(function(btn){
      if(profiles.some(function(p){ return cardMatches(btn.getAttribute('data-yo-title') || '', p); })) hideCard(findCard(btn));
    });
    document.querySelectorAll('h1,h2,h3,h4,h5,h6,.el-title,.uk-card-title').forEach(function(el){
      if(forbidden(el)) return;
      if(profiles.some(function(p){ return cardMatches(el.textContent || '', p); })) hideCard(findCard(el));
    });
    document.querySelectorAll('.el-item, .uk-card, article').forEach(function(card){
      if(forbidden(card) || card.dataset.yoSoldHidden === '1' || !productSignals(card)) return;
      if(profiles.some(function(p){ return cardMatches(card.textContent || '', p); })) hideCard(card);
    });
  }
  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', hideSold); else hideSold();
  setTimeout(hideSold, 300);
  setTimeout(hideSold, 1000);
  setTimeout(hideSold, 2500);
})();
</script>
        <?php
    }

    private static function settings() {
        return wp_parse_args(get_option(self::OPT, []), YO_Checkout_Invoice_Plugin::defaults());
    }

    private function get_order_data($local_id) {
        $keys = ['title','price_eur','original_price_eur','discount_eur','image_url','product_id','full_name','phone','email','address','additional_address','city','zip_code','country','created_at','buyer_id','order_id','mono_invoice_id','western_bid_invoice','wayforpay_order_reference','payment_provider','payment_type','card_fee_percent','card_fee_amount','card_total_amount','shipping_cost_eur','shipping_source','shipping_weight_kg','bank_total_amount','promo_code_applied','promo_discount_type','promo_discount_value','cart_items_count','cart_items_json','checkout_session_id','browser_buyer_id'];
        $out = [];
        foreach ($keys as $k) $out[$k] = get_post_meta($local_id, $k, true);
        return $out;
    }

    private function cart_items_from_order_data($d) {
        $items = [];
        if (!empty($d['cart_items_json'])) {
            $decoded = json_decode((string)$d['cart_items_json'], true);
            if (is_array($decoded)) $items = $decoded;
        }
        if (!$items) {
            $items = [[
                'title' => $d['title'] ?? 'Selected leotard',
                'price_eur' => $d['price_eur'] ?? 0,
                'original_price_eur' => $d['original_price_eur'] ?: ($d['price_eur'] ?? 0),
                'discount_eur' => $d['discount_eur'] ?? 0,
                'product_discount_eur' => $d['discount_eur'] ?? 0,
                'weight_kg' => $d['shipping_weight_kg'] ?? '',
                'image_url' => $d['image_url'] ?? '',
                'product_id' => $d['product_id'] ?? '',
                'feed_id' => $d['product_id'] ?? '',
            ]];
        }
        return array_values(array_filter($items, function($item){ return is_array($item) && !empty($item['title']); }));
    }

    private function sanitize_product_id($value) {
        if (class_exists('YO_Checkout_Product_Identity_Service')) {
            return YO_Checkout_Product_Identity_Service::sanitize_product_id($value);
        }
        return preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field((string)$value));
    }

    private function sold_item_title($item) {
        return is_array($item) ? (string)($item['title'] ?? '') : (string)$item;
    }

    private function sold_item_product_id($item) {
        return is_array($item) ? $this->sanitize_product_id($item['product_id'] ?? ($item['feed_id'] ?? '')) : '';
    }

    private function unique_sold_items($items) {
        $out = [];
        $seen = [];
        foreach ((array)$items as $item) {
            $title = trim(wp_strip_all_tags($this->sold_item_title($item)));
            $product_id = $this->sold_item_product_id($item);
            if ($title === '') continue;
            $key = $product_id !== '' ? ('id:' . strtolower($product_id)) : strtolower($this->normalize_match_text($title));
            if ($key === '' || isset($seen[$key])) continue;
            $seen[$key] = true;
            $out[] = ['title' => $title, 'product_id' => $product_id];
        }
        return $out;
    }

    private function ensure_page_backup($page_id, $local_id) {
        $backup_key = '_yo_checkout_autohide_backup_order_' . absint($local_id);
        if (get_post_meta($page_id, $backup_key, true)) return;

        $meta_backup = [];
        foreach (get_post_meta($page_id) as $meta_key => $values) {
            $meta_key = (string)$meta_key;
            if (strpos($meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            if ($meta_key === '_edit_lock' || $meta_key === '_edit_last') continue;
            $meta_backup[$meta_key] = $values;
        }

        update_post_meta($page_id, $backup_key, [
            'created_at' => current_time('mysql'),
            'local_order_id' => absint($local_id),
            'keycrm_order_id' => preg_replace('/[^0-9]/', '', (string)get_post_meta($local_id, 'order_id', true)),
            'post_content' => get_post_field('post_content', $page_id),
            'meta' => $meta_backup,
        ]);
    }

    private function decode_loose_unicode_sequences($text) {
        $text = (string)$text;
        $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m){
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
        }, $text);
        $text = preg_replace_callback('/(^|[^A-Za-z0-9_])u([0-9a-fA-F]{4})/', function($m){
            return $m[1] . mb_convert_encoding(pack('H*', $m[2]), 'UTF-8', 'UTF-16BE');
        }, $text);
        $text = preg_replace_callback('/u(00a0|00ab|00bb|2018|2019|201a|201b|201c|201d|201e|2013|2014|2212)/i', function($m){
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
        }, $text);
        return $text;
    }

    private function clean_log_text($text) {
        $text = $this->decode_loose_unicode_sequences((string)$text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->decode_loose_unicode_sequences($text);
        $text = str_replace(
            ['вЂњ','вЂќ','В«','В»','вЂ™','вЂ','`','Вґ','вЂ“','вЂ”','в€’','&nbsp;'],
            ['"','"','"','"',"'", "'", "'", "'", '-', '-', '-', ' '],
            $text
        );
        $text = str_replace(['’','‘','‚','‛','“','”','„','«','»','–','—','−',"\xc2\xa0"], ["'","'","'","'",'"','"','"','"','"','-','-','-',' '], $text);
        $text = wp_strip_all_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    private function normalize_match_text($text) {
        $text = $this->decode_loose_unicode_sequences((string)$text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->decode_loose_unicode_sequences($text);
        $text = str_replace(['вЂ™','вЂ','`','Вґ','вЂњ','вЂќ','В«','В»','вЂ“','вЂ”','в€’','&nbsp;'], ["'","'","'","'",'"','"','"','"','-','-','-',' '], $text);
        $text = str_replace(['’','‘','‚','‛','“','”','„','«','»','–','—','−',"\xc2\xa0"], ["'","'","'","'",'"','"','"','"','"','-','-','-',' '], $text);
        $text = preg_replace('/[^\p{L}\p{N}\'" -]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return mb_strtolower(trim($text));
    }

    private function important_title_words($text) {
        $norm = $this->normalize_match_text($text);
        if ($norm === '') return [];
        $generic = [
            'new'=>1,'author'=>1,'authors'=>1,'designer'=>1,'leotard'=>1,'leotards'=>1,'duo'=>1,'dress'=>1,
            'for'=>1,'height'=>1,'cm'=>1,'size'=>1,'model'=>1,'the'=>1,'and'=>1,'with'=>1,'per'=>1,'pair'=>1
        ];
        $parts = preg_split('/\s+/u', $norm, -1, PREG_SPLIT_NO_EMPTY);
        $words = [];
        foreach ($parts as $w) {
            if (isset($generic[$w])) continue;
            if (preg_match('/^\d{2,3}-\d{2,3}$/', $w)) continue;
            if (preg_match('/^\d{2,3}$/', $w)) continue;
            if (mb_strlen($w) < 3) continue;
            $words[$w] = true;
        }
        return array_keys($words);
    }

    private function title_height_range($text) {
        $norm = $this->normalize_match_text($text);
        if (preg_match('/\b(\d{2,3})\s*-\s*(\d{2,3})\b/u', $norm, $m)) return $m[1] . '-' . $m[2];
        return '';
    }

    private function quoted_model_names($title) {
        $raw = $this->decode_loose_unicode_sequences((string)$title);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $models = [];
        if (preg_match_all('/["Р’В«РІР‚Сљ]([^"Р’В»РІР‚Сњ]{3,})["Р’В»РІР‚Сњ]/u', $raw, $qm)) {
            foreach ($qm[1] as $q) {
                $q_norm = $this->normalize_match_text($q);
                if ($q_norm !== '') $models[] = $q_norm;
            }
        }
        if (preg_match_all('/["“”«»]([^"“”«»]{3,})["“”«»]/u', $raw, $qm)) {
            foreach ($qm[1] as $q) {
                $q_norm = $this->normalize_match_text($q);
                if ($q_norm !== '') $models[] = $q_norm;
            }
        }
        return array_values(array_unique($models));
    }

    private function product_identity_key($title) {
        $quoted = $this->quoted_model_names($title);
        if ($quoted) return $quoted[0] . '|' . $this->title_height_range($title);
        $words = $this->important_title_words($title);
        if (!$words) return '';
        return implode(' ', array_slice($words, 0, min(4, count($words)))) . '|' . $this->title_height_range($title);
    }

    private function title_aliases_for_matching($title) {
        $raw = $this->decode_loose_unicode_sequences((string)$title);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $aliases = [];
        $norm = $this->normalize_match_text($raw);
        if ($norm !== '') $aliases[] = $norm;

        if (preg_match_all('/["В«вЂњ]([^"В»вЂќ]{3,})["В»вЂќ]/u', $raw, $qm)) {
            foreach ($qm[1] as $q) {
                $q_norm = $this->normalize_match_text($q);
                if ($q_norm !== '') $aliases[] = $q_norm;
            }
        }

        if (preg_match_all('/["“”«»]([^"“”«»]{3,})["“”«»]/u', $raw, $qm)) {
            foreach ($qm[1] as $q) {
                $q_norm = $this->normalize_match_text($q);
                if ($q_norm !== '') $aliases[] = $q_norm;
            }
        }

        $words = $this->important_title_words($raw);
        if (count($words) >= 2) {
            $aliases[] = implode(' ', array_slice($words, 0, min(4, count($words))));
            for ($i = 0; $i < count($words) - 1; $i++) {
                $aliases[] = $words[$i] . ' ' . $words[$i + 1];
            }
        }

        return array_values(array_unique(array_filter($aliases, function($a){ return mb_strlen($a) >= 3; })));
    }

    private function title_matches_sold_item($text, $titles) {
        $hay = $this->normalize_match_text($text);
        if ($hay === '') return false;
        $hay_identity = $this->product_identity_key($text);
        $hay_height = $this->title_height_range($text);

        foreach ($titles as $sold_item) {
            $product_id = $this->sold_item_product_id($sold_item);
            if ($product_id !== '' && $this->storage_text_contains_product_id((string)$text, $product_id)) return true;
            $title = $this->sold_item_title($sold_item);
            $title_identity = $this->product_identity_key($title);
            if ($hay_identity !== '' && $title_identity !== '' && hash_equals($hay_identity, $title_identity)) return true;

            $title_height = $this->title_height_range($title);
            foreach ($this->quoted_model_names($title) as $model) {
                if ($model !== '' && strpos($hay, $model) !== false) {
                    if ($title_height === '' || $hay_height === '' || hash_equals($title_height, $hay_height) || strpos($hay, $title_height) !== false) return true;
                }
            }

            foreach ($this->title_aliases_for_matching($title) as $alias) {
                if ($alias !== '' && mb_strlen($alias) >= 8 && strpos($hay, $alias) !== false && ($title_height === '' || strpos($hay, $title_height) !== false || hash_equals($title_height, $hay_height))) return true;
            }

            $words = $this->important_title_words($title);
            $hits = 0;
            foreach ($words as $w) {
                if (strpos($hay, $w) !== false) $hits++;
            }
            if ($hits < 2) continue;
            if ($hits >= 2) {
                if ($title_height === '') return true;
                if ($hay_height !== '' && hash_equals($title_height, $hay_height)) return true;
                if (strpos($hay, $title_height) !== false) return true;
                continue;
            }

            if ($hits >= 1 && preg_match('/\b(\d{2,3})\s*[-вЂ“вЂ”]\s*(\d{2,3})\b/u', (string)$title, $hm)) {
                $height = $hm[1] . '-' . $hm[2];
                if (strpos($hay, $height) !== false) return true;
            }
        }
        return false;
    }

    private function matched_sold_item_identity_key($text, $titles) {
        foreach ($titles as $sold_item) {
            if ($this->title_matches_sold_item($text, [$sold_item])) {
                $product_id = $this->sold_item_product_id($sold_item);
                if ($product_id !== '') return 'id:' . strtolower($product_id);
                $title = $this->sold_item_title($sold_item);
                $key = $this->product_identity_key($title);
                return $key !== '' ? $key : $this->normalize_match_text($title);
            }
        }
        return '';
    }

    private function storage_text_contains_product_id($text, $product_id) {
        $product_id = $this->sanitize_product_id($product_id);
        if ($product_id === '') return false;
        $text = (string)$text;
        if ($text === '') return false;
        $quoted = preg_quote($product_id, '/');
        return (bool)preg_match('/(?:id|feed[-_]?id|product[-_]?id|data-feed-id|data-product-id)["\'\s:=\\\\\/]+'. $quoted . '(?:["\'\s>\\\\\/]|$)/i', $text);
    }

    private function yootheme_node_product_id_matches($node, $product_id) {
        $product_id = $this->sanitize_product_id($product_id);
        if (!is_array($node) || $product_id === '') return false;
        foreach (['id','feed_id','feedId','product_id','productId','data-feed-id','data-product-id'] as $key) {
            if (isset($node[$key]) && is_scalar($node[$key]) && strtolower($this->sanitize_product_id($node[$key])) === strtolower($product_id)) return true;
        }
        if (isset($node['props']) && is_array($node['props'])) {
            foreach (['id','feed_id','feedId','product_id','productId','data-feed-id','data-product-id'] as $key) {
                if (isset($node['props'][$key]) && is_scalar($node['props'][$key]) && strtolower($this->sanitize_product_id($node['props'][$key])) === strtolower($product_id)) return true;
            }
        }
        return $this->storage_text_contains_product_id(wp_json_encode($node), $product_id);
    }

    private function disable_titles_in_yootheme_storage_mixed($value, $titles, &$changed = false) {
        $changed = false;
        if (is_array($value)) {
            $copy = $value;
            $this->disable_titles_in_yootheme_node($copy, $titles, $changed);
            return $changed ? $copy : $value;
        }
        if (is_object($value)) {
            $arr = json_decode(wp_json_encode($value), true);
            if (is_array($arr)) {
                $this->disable_titles_in_yootheme_node($arr, $titles, $changed);
                return $changed ? $arr : $value;
            }
        }
        if (is_string($value) && strlen($value) >= 2) {
            return $this->disable_titles_in_yootheme_storage_string($value, $titles, $changed);
        }
        return $value;
    }

    private function set_card_default_titles_in_yootheme_storage_mixed($value, $titles, &$changed = false) {
        $changed = false;
        if (is_array($value)) {
            $copy = $value;
            $this->set_card_default_titles_in_yootheme_node($copy, $titles, $changed);
            return $changed ? $copy : $value;
        }
        if (is_object($value)) {
            $arr = json_decode(wp_json_encode($value), true);
            if (is_array($arr)) {
                $this->set_card_default_titles_in_yootheme_node($arr, $titles, $changed);
                return $changed ? $arr : $value;
            }
        }
        if (is_string($value) && strlen($value) >= 2) {
            return $this->set_card_default_titles_in_yootheme_storage_string($value, $titles, $changed);
        }
        return $value;
    }

    private function disable_titles_in_yootheme_storage_string($value, $titles, &$changed = false) {
        $changed = false;
        $raw = (string)$value;
        $trim = trim($raw);
        if ($trim === '') return $value;

        if ($trim[0] === '{' || $trim[0] === '[') {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $this->disable_titles_in_yootheme_node($decoded, $titles, $changed);
                if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $unslashed = wp_unslash($trim);
            if ($unslashed !== $trim) {
                $decoded = json_decode($unslashed, true);
                if (is_array($decoded)) {
                    $this->disable_titles_in_yootheme_node($decoded, $titles, $changed);
                    if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        $new = $this->disable_titles_in_yootheme_builder_comment($raw, $titles, $changed);

        return $changed ? $new : $value;
    }

    private function set_card_default_titles_in_yootheme_storage_string($value, $titles, &$changed = false) {
        $changed = false;
        $raw = (string)$value;
        $trim = trim($raw);
        if ($trim === '') return $value;

        if ($trim[0] === '{' || $trim[0] === '[') {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $this->set_card_default_titles_in_yootheme_node($decoded, $titles, $changed);
                if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $unslashed = wp_unslash($trim);
            if ($unslashed !== $trim) {
                $decoded = json_decode($unslashed, true);
                if (is_array($decoded)) {
                    $this->set_card_default_titles_in_yootheme_node($decoded, $titles, $changed);
                    if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        $new = $this->set_card_default_titles_in_yootheme_builder_comment($raw, $titles, $changed);

        return $changed ? $new : $value;
    }

    private function find_yootheme_layout_json_segment($content) {
        $content = (string)$content;
        $markers = [
            '{"type":"layout"',
            '{ "type":"layout"',
            '{"type" : "layout"',
            '{\\"type\\":\\"layout\\"',
        ];

        $pos = false;
        foreach ($markers as $marker) {
            $p = strpos($content, $marker);
            if ($p !== false && ($pos === false || $p < $pos)) $pos = $p;
        }
        if ($pos === false) return false;

        while ($pos > 0 && $content[$pos] !== '{') $pos--;
        if ($content[$pos] !== '{') return false;

        $len = strlen($content);
        $depth = 0;
        $in_string = false;
        $escape = false;
        for ($i = $pos; $i < $len; $i++) {
            $ch = $content[$i];
            if ($in_string) {
                if ($escape) {
                    $escape = false;
                    continue;
                }
                if ($ch === '\\') {
                    $escape = true;
                    continue;
                }
                if ($ch === '"') {
                    $in_string = false;
                    continue;
                }
                continue;
            }
            if ($ch === '"') {
                $in_string = true;
                continue;
            }
            if ($ch === '{') $depth++;
            if ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    return ['start' => $pos, 'end' => $i + 1, 'json' => substr($content, $pos, $i + 1 - $pos)];
                }
            }
        }
        return false;
    }

    private function disable_titles_in_yootheme_builder_comment($content, $titles, &$changed = false) {
        $changed = false;
        $content = (string)$content;

        $segment = $this->find_yootheme_layout_json_segment($content);
        if ($segment && !empty($segment['json'])) {
            $json = trim($segment['json']);
            $decoded = json_decode($json, true);
            $used_unslashed = false;
            if (!is_array($decoded)) {
                $unslashed = wp_unslash($json);
                if ($unslashed !== $json) {
                    $decoded = json_decode($unslashed, true);
                    $used_unslashed = is_array($decoded);
                }
            }
            if (is_array($decoded)) {
                $local_changed = false;
                $this->disable_titles_in_yootheme_node($decoded, $titles, $local_changed);
                if ($local_changed) {
                    $changed = true;
                    $new_json = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($used_unslashed) $new_json = wp_slash($new_json);
                    return substr($content, 0, $segment['start']) . $new_json . substr($content, $segment['end']);
                }
            }
        }

        $start = false;
        if (preg_match('/<!--\s*\{\\?"type\\?"\s*:\s*\\?"layout\\?"/s', $content, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
        }
        if ($start === false) return $content;

        $end = strrpos($content, '-->');
        if ($end === false || $end <= $start) return $content;

        $comment_open_end = strpos($content, '<!--', $start);
        if ($comment_open_end === false) return $content;
        $inner_start = $comment_open_end + 4;
        $inner = trim(substr($content, $inner_start, $end - $inner_start));

        $decoded = json_decode($inner, true);
        $used_unslashed = false;
        if (!is_array($decoded)) {
            $unslashed = wp_unslash($inner);
            if ($unslashed !== $inner) {
                $decoded = json_decode($unslashed, true);
                $used_unslashed = is_array($decoded);
            }
        }
        if (!is_array($decoded)) return $content;

        $local_changed = false;
        $this->disable_titles_in_yootheme_node($decoded, $titles, $local_changed);
        if (!$local_changed) return $content;

        $changed = true;
        $new_json = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($used_unslashed) $new_json = wp_slash($new_json);
        return substr($content, 0, $inner_start) . ' ' . $new_json . ' ' . substr($content, $end);
    }

    private function set_card_default_titles_in_yootheme_builder_comment($content, $titles, &$changed = false) {
        $changed = false;
        $content = (string)$content;

        $segment = $this->find_yootheme_layout_json_segment($content);
        if ($segment && !empty($segment['json'])) {
            $json = trim($segment['json']);
            $decoded = json_decode($json, true);
            $used_unslashed = false;
            if (!is_array($decoded)) {
                $unslashed = wp_unslash($json);
                if ($unslashed !== $json) {
                    $decoded = json_decode($unslashed, true);
                    $used_unslashed = is_array($decoded);
                }
            }
            if (is_array($decoded)) {
                $local_changed = false;
                $this->set_card_default_titles_in_yootheme_node($decoded, $titles, $local_changed);
                if ($local_changed) {
                    $changed = true;
                    $new_json = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    if ($used_unslashed) $new_json = wp_slash($new_json);
                    return substr($content, 0, $segment['start']) . $new_json . substr($content, $segment['end']);
                }
            }
        }

        $start = false;
        if (preg_match('/<!--\s*\{\\?"type\\?"\s*:\s*\\?"layout\\?"/s', $content, $m, PREG_OFFSET_CAPTURE)) {
            $start = $m[0][1];
        }
        if ($start === false) return $content;

        $end = strrpos($content, '-->');
        if ($end === false || $end <= $start) return $content;

        $comment_open_end = strpos($content, '<!--', $start);
        if ($comment_open_end === false) return $content;
        $inner_start = $comment_open_end + 4;
        $inner = trim(substr($content, $inner_start, $end - $inner_start));

        $decoded = json_decode($inner, true);
        $used_unslashed = false;
        if (!is_array($decoded)) {
            $unslashed = wp_unslash($inner);
            if ($unslashed !== $inner) {
                $decoded = json_decode($unslashed, true);
                $used_unslashed = is_array($decoded);
            }
        }
        if (!is_array($decoded)) return $content;

        $local_changed = false;
        $this->set_card_default_titles_in_yootheme_node($decoded, $titles, $local_changed);
        if (!$local_changed) return $content;

        $changed = true;
        $new_json = wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($used_unslashed) $new_json = wp_slash($new_json);
        return substr($content, 0, $inner_start) . ' ' . $new_json . ' ' . substr($content, $end);
    }

    private function disable_titles_in_yootheme_node(&$node, $titles, &$changed) {
        $this->disable_titles_in_yootheme_node_deep($node, $titles, $changed, 0);
    }

    private function set_card_default_titles_in_yootheme_node(&$node, $titles, &$changed) {
        $this->set_card_default_titles_in_yootheme_node_deep($node, $titles, $changed, 0);
    }

    private function is_yootheme_root_or_layout_node($node) {
        if (!is_array($node)) return false;
        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        return in_array($type, ['layout','section','row','column','grid','masonry','gallery','slider','slideshow'], true);
    }

    private function is_yootheme_disable_candidate($node, $depth) {
        if (!is_array($node) || $depth <= 0) return false;
        if ($this->is_yootheme_root_or_layout_node($node)) return false;

        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        if (in_array($type, ['grid_item','gallery_item','slider_item','slideshow_item'], true)) return true;

        return false;
    }

    private function apply_yootheme_disabled_status(&$node) {
        if (!is_array($node)) return;
        if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = [];

        $node['props']['status'] = 'disabled';
        $node['props']['_yo_checkout_auto_hidden'] = current_time('mysql');
    }

    private function apply_yootheme_card_default_style(&$node) {
        if (!is_array($node)) return;
        if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = [];

        $node['props']['style'] = 'default';
        $node['props']['_yo_checkout_bank_invoice_reserved'] = current_time('mysql');
    }

    private function yootheme_node_display_title($node) {
        if (!is_array($node)) return '';
        foreach (['title','name','label'] as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && trim($node[$key]) !== '') return $this->clean_log_text($node[$key]);
        }
        if (isset($node['props']) && is_array($node['props'])) {
            foreach (['title','name','label'] as $key) {
                if (isset($node['props'][$key]) && is_string($node['props'][$key]) && trim($node['props'][$key]) !== '') return $this->clean_log_text($node['props'][$key]);
            }
        }
        return '';
    }

    private function yootheme_node_self_matches_title($node, $titles) {
        if (!is_array($node)) return false;
        foreach ((array)$titles as $sold_item) {
            $product_id = $this->sold_item_product_id($sold_item);
            if ($product_id !== '' && $this->yootheme_node_product_id_matches($node, $product_id)) return true;
        }
        foreach (['title','name','label'] as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && $this->title_matches_sold_item($node[$key], $titles)) return true;
        }
        foreach (['content','text','meta','description'] as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && mb_strlen(wp_strip_all_tags($node[$key])) <= 260 && $this->title_matches_sold_item($node[$key], $titles)) return true;
        }
        if (isset($node['props']) && is_array($node['props'])) {
            foreach (['title','name','label'] as $key) {
                if (isset($node['props'][$key]) && is_string($node['props'][$key]) && $this->title_matches_sold_item($node['props'][$key], $titles)) return true;
            }
            foreach (['content','text','meta','description'] as $key) {
                if (isset($node['props'][$key]) && is_string($node['props'][$key]) && mb_strlen(wp_strip_all_tags($node['props'][$key])) <= 260 && $this->title_matches_sold_item($node['props'][$key], $titles)) return true;
            }
        }
        return false;
    }

    private function disable_titles_in_yootheme_node_deep(&$node, $titles, &$changed, $depth = 0) {
        if (!is_array($node)) return false;

        $self_match = $this->yootheme_node_self_matches_title($node, $titles);
        $child_match = false;

        foreach ($node as &$child) {
            if (!is_array($child)) continue;
            if ($this->disable_titles_in_yootheme_node_deep($child, $titles, $changed, $depth + 1)) {
                $child_match = true;
            }
        }
        unset($child);

        $matches = $self_match || $child_match;
        if ($matches && $this->is_yootheme_disable_candidate($node, $depth)) {
            $display_title = $this->yootheme_node_display_title($node);
            $match_text = $display_title !== '' ? $display_title : wp_json_encode($node);
            $identity_key = $this->matched_sold_item_identity_key($match_text, $titles);
            if ($identity_key !== '' && isset($this->disabled_identity_keys[$identity_key])) {
                return true;
            }
            $this->apply_yootheme_disabled_status($node);
            if ($identity_key !== '') $this->disabled_identity_keys[$identity_key] = true;
            if ($display_title !== '') $this->disabled_match_log[] = $display_title;
            $changed = true;
            return true;
        }

        return $matches;
    }

    private function set_card_default_titles_in_yootheme_node_deep(&$node, $titles, &$changed, $depth = 0) {
        if (!is_array($node)) return false;

        $self_match = $this->yootheme_node_self_matches_title($node, $titles);
        $child_match = false;

        foreach ($node as &$child) {
            if (!is_array($child)) continue;
            if ($this->set_card_default_titles_in_yootheme_node_deep($child, $titles, $changed, $depth + 1)) {
                $child_match = true;
            }
        }
        unset($child);

        $matches = $self_match || $child_match;
        if ($matches && $this->is_yootheme_disable_candidate($node, $depth)) {
            $display_title = $this->yootheme_node_display_title($node);
            $match_text = $display_title !== '' ? $display_title : wp_json_encode($node);
            $identity_key = $this->matched_sold_item_identity_key($match_text, $titles);
            if ($identity_key !== '' && isset($this->card_default_identity_keys[$identity_key])) {
                return true;
            }
            $this->apply_yootheme_card_default_style($node);
            if ($identity_key !== '') $this->card_default_identity_keys[$identity_key] = true;
            if ($display_title !== '') $this->card_default_match_log[] = $display_title;
            $changed = true;
            return true;
        }

        return $matches;
    }

    private function is_yootheme_node_disabled($node) {
        if (!is_array($node)) return false;
        $status = '';
        if (isset($node['props']) && is_array($node['props']) && isset($node['props']['status'])) {
            $status = strtolower(trim((string)$node['props']['status']));
        } elseif (isset($node['status'])) {
            $status = strtolower(trim((string)$node['status']));
        }
        if (in_array($status, ['disabled','disable','0','false','hidden'], true)) return true;

        $style = '';
        if (isset($node['props']) && is_array($node['props']) && isset($node['props']['style'])) {
            $style = strtolower(trim((string)$node['props']['style']));
        } elseif (isset($node['style'])) {
            $style = strtolower(trim((string)$node['style']));
        }
        return in_array($style, ['card-default','default'], true);
    }

    private function yootheme_product_availability_from_node($node, $title, $product_id = '') {
        if (!is_array($node)) return null;

        $titles = [['title' => $title, 'product_id' => $product_id]];
        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        $self_match = $this->yootheme_node_self_matches_title($node, $titles);

        if ($self_match && in_array($type, ['grid_item','gallery_item','slider_item','slideshow_item'], true)) {
            return !$this->is_yootheme_node_disabled($node);
        }

        foreach ($node as $child) {
            if (!is_array($child)) continue;
            $found = $this->yootheme_product_availability_from_node($child, $title, $product_id);
            if ($found !== null) return $found;
        }

        return null;
    }

    private function yootheme_product_availability_from_storage_value($value, $title, $product_id = '') {
        if (is_array($value)) {
            return $this->yootheme_product_availability_from_node($value, $title, $product_id);
        }
        if (!is_string($value) || trim($value) === '') return null;
        $raw = trim($value);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $maybe = maybe_unserialize($raw);
            if (is_array($maybe)) $decoded = $maybe;
        }
        if (is_array($decoded)) return $this->yootheme_product_availability_from_node($decoded, $title, $product_id);

        if (strpos($raw, '"type":"layout"') !== false || strpos($raw, '"grid_item"') !== false || strpos($raw, '"type"') !== false) {
            $segment = $this->find_yootheme_layout_json_segment($raw);
            if ($segment && !empty($segment['json'])) {
                $decoded = json_decode(trim($segment['json']), true);
                if (is_array($decoded)) return $this->yootheme_product_availability_from_node($decoded, $title, $product_id);
            }
        }
        return null;
    }
}
