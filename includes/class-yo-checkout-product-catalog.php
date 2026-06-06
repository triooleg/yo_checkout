<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Product_Catalog_Service {
    private $callbacks = [];
    private $cache = [];
    private $source_text_cache = [];

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

    private function clean_title($title) {
        $clean = $this->call('clean_product_title_for_display', $title);
        return is_string($clean) ? $clean : trim(wp_strip_all_tags((string)$title));
    }

    private function canonical_product_id($value, $title = '') {
        $product_id = $this->call('canonical_product_id', $value, $title);
        if (is_string($product_id)) return $product_id;
        if (class_exists('YO_Checkout_Product_Identity_Service')) {
            return YO_Checkout_Product_Identity_Service::canonical_product_id($value, $title);
        }
        return preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field((string)$value));
    }

    private function sanitize_product_id($value) {
        $product_id = $this->call('sanitize_product_id', $value);
        if (is_string($product_id)) return $product_id;
        if (class_exists('YO_Checkout_Product_Identity_Service')) {
            return YO_Checkout_Product_Identity_Service::sanitize_product_id($value);
        }
        return preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field((string)$value));
    }

    public function trusted_cart_item(array $item) {
        $fallback_title = $this->clean_title(sanitize_text_field($item['title'] ?? ''));
        $product_id = $this->canonical_product_id($item['product_id'] ?? ($item['feed_id'] ?? ''), $fallback_title);
        $fallback_price = round(floatval($item['price_eur'] ?? 0), 2);
        $fallback_original = round(floatval($item['original_price_eur'] ?? $fallback_price), 2);
        if ($fallback_original < $fallback_price) $fallback_original = $fallback_price;
        $fallback_product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
        $fallback_promo_discount = round(floatval($item['promo_discount_eur'] ?? 0), 2);
        $fallback_total_discount = round(floatval($item['discount_eur'] ?? ($fallback_product_discount + $fallback_promo_discount)), 2);
        $fallback_weight = $this->sanitize_weight($item['weight_kg'] ?? ($item['product_weight_kg'] ?? ($item['weight'] ?? 0)));
        $fallback_image = esc_url_raw($item['image_url'] ?? '');

        $resolved = $this->resolve($product_id, $fallback_title);
        if (!empty($resolved['found']) && floatval($resolved['price_eur'] ?? 0) > 0) {
            $price = round(floatval($resolved['price_eur']), 2);
            $original = round(floatval($resolved['original_price_eur'] ?? $price), 2);
            if ($original < $price) $original = $price;
            $product_discount = round(max(0, $original - $price), 2);
            return [
                'product_id' => $resolved['product_id'] ?: $product_id,
                'feed_id' => $resolved['product_id'] ?: $product_id,
                'title' => $resolved['title'] ?: $fallback_title,
                'price_eur' => number_format($price, 2, '.', ''),
                'original_price_eur' => number_format($original, 2, '.', ''),
                'discount_eur' => number_format($product_discount, 2, '.', ''),
                'product_discount_eur' => number_format($product_discount, 2, '.', ''),
                'promo_discount_eur' => '0.00',
                'weight_kg' => !empty($resolved['weight_kg']) ? number_format(floatval($resolved['weight_kg']), 3, '.', '') : ($fallback_weight > 0 ? number_format($fallback_weight, 3, '.', '') : ''),
                'image_url' => esc_url_raw($resolved['image_url'] ?: $fallback_image),
                'catalog_status' => 'trusted',
                'catalog_source' => $resolved['source'] ?? '',
                'catalog_mismatch' => wp_json_encode($this->mismatches([
                    'title' => $fallback_title,
                    'price_eur' => $fallback_price,
                    'original_price_eur' => $fallback_original,
                    'discount_eur' => $fallback_total_discount,
                    'weight_kg' => $fallback_weight,
                    'image_url' => $fallback_image,
                ], $resolved)),
            ];
        }

        if ($fallback_title === '' || $fallback_price <= 0) return [];
        if ($fallback_product_discount < 0) $fallback_product_discount = 0;
        if ($fallback_promo_discount < 0) $fallback_promo_discount = 0;
        if ($fallback_total_discount < 0) $fallback_total_discount = 0;
        if ($fallback_total_discount < ($fallback_product_discount + $fallback_promo_discount)) {
            $fallback_total_discount = round($fallback_product_discount + $fallback_promo_discount, 2);
        }

        return [
            'product_id' => $product_id,
            'feed_id' => $product_id,
            'title' => $fallback_title,
            'price_eur' => number_format($fallback_price, 2, '.', ''),
            'original_price_eur' => number_format($fallback_original, 2, '.', ''),
            'discount_eur' => number_format($fallback_total_discount, 2, '.', ''),
            'product_discount_eur' => number_format($fallback_product_discount, 2, '.', ''),
            'promo_discount_eur' => number_format($fallback_promo_discount, 2, '.', ''),
            'weight_kg' => $fallback_weight > 0 ? number_format($fallback_weight, 3, '.', '') : '',
            'image_url' => $fallback_image,
            'catalog_status' => 'fallback',
            'catalog_source' => '',
            'catalog_mismatch' => $product_id !== '' ? wp_json_encode(['unresolved_product_id' => $product_id]) : '',
        ];
    }

    public function resolve($product_id, $title = '') {
        $title = $this->clean_title($title);
        $product_id = $this->canonical_product_id($product_id, $title);
        $cache_key = strtolower($product_id ?: ('title:' . $title));
        if ($cache_key === '') return ['found' => false];
        if (isset($this->cache[$cache_key])) return $this->cache[$cache_key];

        $page_id = $this->source_page_id();
        if (!$page_id) return $this->cache[$cache_key] = ['found' => false, 'reason' => 'missing_source_page'];

        foreach ($this->source_texts($page_id, $product_id, $title) as $source) {
            $candidate = $this->candidate_from_text($source['text'], $product_id, $title);
            if (!empty($candidate['found'])) {
                $candidate['source'] = 'page:' . $page_id . ':' . $source['source'];
                return $this->cache[$cache_key] = $candidate;
            }
        }

        return $this->cache[$cache_key] = [
            'found' => false,
            'reason' => 'not_found',
            'product_id' => $product_id,
            'title' => $title,
        ];
    }

    private function source_page_id() {
        $settings = $this->settings();
        $page_id = absint($settings['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        return $page_id;
    }

    private function source_texts($page_id, $product_id = '', $title = '') {
        $cache_key = $page_id . ':' . strtolower((string)$product_id) . ':' . md5((string)$title);
        if (isset($this->source_text_cache[$cache_key])) return $this->source_text_cache[$cache_key];

        $texts = [];
        $content = (string)get_post_field('post_content', $page_id);
        foreach ($this->source_fragments_from_raw($content, $product_id, $title) as $fragment) {
            $texts[] = ['source' => 'post_content', 'text' => $fragment];
        }

        $meta = get_post_meta($page_id);
        if (is_array($meta)) {
            foreach ($meta as $key => $values) {
                foreach ((array)$values as $value) {
                    foreach ($this->source_fragments_from_raw((string)$value, $product_id, $title) as $fragment) {
                        $texts[] = ['source' => 'meta:' . $key, 'text' => $fragment];
                    }
                }
            }
        }

        return $this->source_text_cache[$cache_key] = $texts;
    }

    private function source_fragments_from_raw($raw, $product_id = '', $title = '') {
        $raw = (string)$raw;
        if ($raw === '') return [];

        $needles = array_filter(array_unique([
            (string)$product_id,
            $this->clean_title($title),
        ]));
        if (!$needles) return [];

        $fragments = [];
        foreach ($needles as $needle) {
            if ($needle === '') continue;
            $offset = 0;
            while (($pos = stripos($raw, $needle, $offset)) !== false) {
                $start = max(0, $pos - 9000);
                $length = min(strlen($raw) - $start, 22000);
                $fragment = substr($raw, $start, $length);
                if ($fragment !== '') $fragments[] = $fragment;
                $offset = $pos + max(1, strlen($needle));
                if (count($fragments) >= 8) break 2;
            }
        }

        return array_values(array_unique($fragments));
    }

    private function candidate_from_text($text, $product_id, $title = '') {
        $text = (string)$text;
        $variants = array_values(array_unique([
            $text,
            wp_unslash($text),
            html_entity_decode(wp_unslash($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ]));

        foreach ($variants as $variant) {
            $positions = $this->product_id_positions($variant, $product_id);
            foreach ($positions as $position) {
                $card = $this->extract_card_text($variant, $position);
                $candidate = $this->parse_card_text($card, $product_id, $title);
                if (!empty($candidate['found'])) return $candidate;
            }
        }
        return ['found' => false];
    }

    private function product_id_positions($text, $product_id) {
        $positions = [];
        if ($product_id === '') return $positions;
        $offset = 0;
        while (($pos = stripos($text, $product_id, $offset)) !== false) {
            $positions[] = $pos;
            $offset = $pos + strlen($product_id);
        }
        return $positions;
    }

    private function extract_card_text($text, $position) {
        $prefix = substr($text, 0, $position);
        $class_pos = max(
            strripos($prefix, 'el-item'),
            strripos($prefix, 'data-feed-id'),
            strripos($prefix, 'product_id'),
            strripos($prefix, 'feed_id')
        );
        $start = max(0, $position - 7000);
        if ($class_pos !== false) {
            $div_pos = strripos(substr($text, 0, $class_pos), '<div');
            if ($div_pos !== false) $start = $div_pos;
            else $start = max(0, $class_pos - 3000);
        }

        $next = stripos($text, 'el-item', $position + 1);
        $end = $next !== false ? $next : min(strlen($text), $position + 9000);
        if ($next !== false) {
            $next_div = strripos(substr($text, 0, $next), '<div');
            if ($next_div !== false && $next_div > $position) $end = $next_div;
        }
        if ($end <= $start) $end = min(strlen($text), $position + 9000);
        return substr($text, $start, $end - $start);
    }

    private function parse_card_text($card, $product_id, $fallback_title = '') {
        $card = html_entity_decode(wp_unslash((string)$card), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $has_explicit_product_id = ($product_id !== '' && stripos($card, $product_id) !== false);

        $title = $this->extract_title($card);
        if ($title === '') $title = $fallback_title;
        $title = $this->clean_title($title);
        $derived_product_id = $this->canonical_product_id('', $title);
        if ($product_id !== '' && !$has_explicit_product_id && strcasecmp($derived_product_id, $product_id) !== 0) {
            return ['found' => false];
        }

        $prices = $this->extract_prices($card);
        if (!$prices) return ['found' => false];
        $price = $prices['price'];
        $original = $prices['original'];
        if ($original < $price) $original = $price;

        return [
            'found' => true,
            'product_id' => $this->sanitize_product_id($product_id),
            'title' => $title,
            'price_eur' => number_format($price, 2, '.', ''),
            'original_price_eur' => number_format($original, 2, '.', ''),
            'discount_eur' => number_format(max(0, $original - $price), 2, '.', ''),
            'weight_kg' => $this->extract_weight($card),
            'image_url' => $this->extract_image_url($card),
        ];
    }

    private function extract_title($card) {
        if (preg_match('/<h[1-6][^>]*class=["\'][^"\']*el-title[^"\']*["\'][^>]*>(.*?)<\/h[1-6]>/is', $card, $m)) {
            return $m[1];
        }
        if (preg_match('/(?:title|content|caption)["\']?\s*[:=]\s*["\']([^"\']{8,180})["\']/is', $card, $m)) {
            return $m[1];
        }
        return '';
    }

    private function extract_prices($card) {
        $sale_new = $this->first_price_near_class($card, 'sale-new-btn');
        $sale_old = $this->first_price_near_class($card, 'sale-old-btn');
        if ($sale_new > 0) {
            return [
                'price' => $sale_new,
                'original' => $sale_old > 0 ? max($sale_old, $sale_new) : $sale_new,
            ];
        }

        $main = $this->first_price_near_class($card, 'yo-main-buy-btn');
        if ($main > 0) {
            return ['price' => $main, 'original' => max($main, $sale_old)];
        }

        if (!preg_match_all('/data-eur\s*=\s*["\']?([0-9]+(?:[.,][0-9]+)?)/i', $card, $matches)) return [];
        $values = array_values(array_filter(array_map(function($value) {
            return round(floatval(str_replace(',', '.', (string)$value)), 2);
        }, $matches[1]), function($value) {
            return $value > 0;
        }));
        if (!$values) return [];
        $price = end($values);
        $original = max($values);
        return ['price' => $price, 'original' => max($original, $price)];
    }

    private function first_price_near_class($card, $class) {
        $pos = stripos($card, $class);
        if ($pos === false) return 0;
        $slice = substr($card, $pos, 1800);
        if (preg_match('/data-eur\s*=\s*["\']?([0-9]+(?:[.,][0-9]+)?)/i', $slice, $m)) {
            return round(floatval(str_replace(',', '.', $m[1])), 2);
        }
        return 0;
    }

    private function extract_weight($card) {
        if (preg_match('/data-weight\s*=\s*["\']?([0-9]+(?:[.,][0-9]+)?)/i', $card, $m)) {
            return number_format(max(0, floatval(str_replace(',', '.', $m[1]))), 3, '.', '');
        }
        return '';
    }

    private function extract_image_url($card) {
        if (preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/is', $card, $m)) {
            return $this->normalize_url($m[1]);
        }
        if (preg_match('/(?:image|src|thumbnail)["\']?\s*[:=]\s*["\']([^"\']+\.(?:jpg|jpeg|png|webp))["\']/is', $card, $m)) {
            return $this->normalize_url($m[1]);
        }
        return '';
    }

    private function normalize_url($url) {
        $url = html_entity_decode(wp_unslash((string)$url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') return '';
        if (strpos($url, '//') === 0) $url = 'https:' . $url;
        if (strpos($url, '/') === 0) $url = home_url($url);
        return esc_url_raw($url);
    }

    private function sanitize_weight($value) {
        $weight = str_replace(',', '.', (string)$value);
        return is_numeric($weight) ? max(0, round(floatval($weight), 3)) : 0;
    }

    private function mismatches(array $fallback, array $resolved) {
        $out = [];
        foreach (['price_eur', 'original_price_eur', 'discount_eur', 'weight_kg'] as $key) {
            $left = round(floatval($fallback[$key] ?? 0), 3);
            $right = round(floatval($resolved[$key] ?? 0), 3);
            if (abs($left - $right) >= 0.01) $out[$key] = ['browser' => $left, 'server' => $right];
        }
        $fallback_title = $this->clean_title($fallback['title'] ?? '');
        $server_title = $this->clean_title($resolved['title'] ?? '');
        if ($fallback_title !== '' && $server_title !== '' && strcasecmp($fallback_title, $server_title) !== 0) {
            $out['title'] = ['browser' => $fallback_title, 'server' => $server_title];
        }
        return $out;
    }
}
