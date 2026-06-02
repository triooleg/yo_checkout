<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Product_Identity_Service {
    public static function sanitize_product_id($value) {
        return preg_replace('/[^A-Za-z0-9_-]/', '', sanitize_text_field((string)$value));
    }

    public static function product_id_from_title($title) {
        $title = self::normalize_title($title);
        if ($title === '') return '';

        $slug = self::slugify($title);
        return self::sanitize_product_id($slug);
    }

    public static function canonical_product_id($provided_id, $title = '') {
        $provided_id = self::sanitize_product_id($provided_id);
        if ($provided_id !== '') return $provided_id;
        return self::product_id_from_title($title);
    }

    private static function normalize_title($title) {
        $title = html_entity_decode((string)$title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = str_replace(
            ['вЂњ','вЂќ','вЂћ','В«','В»','“','”','„','«','»','вЂ','вЂ™','`','´','вЂ“','вЂ”','−','–','—','&nbsp;'],
            ['"','"','"','"','"','"','"','"','"','"',"'","'","'","'",'-','-','-','-','-',' '],
            $title
        );
        $title = str_replace(['’','‘','‚','‛','“','”','„','«','»','–','—','−',"\xc2\xa0"], ["'","'","'","'",'"','"','"','"','"','-','-','-',' '], $title);
        $title = wp_strip_all_tags($title);
        $title = preg_replace('/\s+(?:for\s+)?height\s+\d{2,3}\s*[-–—−]\s*\d{2,3}\s*$/iu', '', $title);
        $title = preg_replace('/\s+/u', ' ', $title);
        return trim($title);
    }

    private static function slugify($text) {
        if (function_exists('remove_accents')) {
            $text = remove_accents($text);
        }
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '_', $text);
        $text = trim((string)$text, '_');
        $text = preg_replace('/_+/u', '_', $text);
        return $text ?: '';
    }
}
