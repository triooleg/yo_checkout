<?php
/**
 * Plugin Name: YOleotard Checkout + Monobank + WayForPay + IBAN Invoice
 * Description: v4.0.25. Waits for the real KeyCRM order number before showing Step 4 after successful card payment.
 * Version: 4.0.25
 * Author: YOleotard / ChatGPT
 */

if (!defined('ABSPATH')) exit;

// Compatibility fallbacks: some hosting environments do not have mbstring enabled.
// Payment finalization and YOOtheme auto-hide must not crash because of missing mb_* functions.
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null, $encoding = null) {
        return $length === null ? substr((string)$string, (int)$start) : substr((string)$string, (int)$start, (int)$length);
    }
}
if (!function_exists('mb_strlen')) {
    function mb_strlen($string, $encoding = null) {
        return strlen((string)$string);
    }
}
if (!function_exists('mb_strtolower')) {
    function mb_strtolower($string, $encoding = null) {
        return strtolower((string)$string);
    }
}
if (!function_exists('mb_convert_encoding')) {
    function mb_convert_encoding($string, $to_encoding, $from_encoding = null) {
        if (function_exists('iconv')) {
            $converted = @iconv($from_encoding ?: 'UTF-8', $to_encoding ?: 'UTF-8', $string);
            if ($converted !== false) return $converted;
        }
        return (string)$string;
    }
}


class YO_Checkout_Invoice_Plugin {
    const OPT = 'yo_checkout_invoice_settings';
    const CPT = 'yo_invoice_order';
    const NS  = 'yoleotard/v1';

    public function __construct() {
        add_action('init', [$this, 'register_cpt']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'ensure_shipping_tables']);
        add_action('admin_post_yo_shipping_import_csv', [$this, 'admin_import_shipping_csv']);
        add_action('admin_post_yo_shipping_import_zone_rates', [$this, 'admin_import_shipping_zone_rates']);
        add_action('admin_post_yo_shipping_clear_rates', [$this, 'admin_clear_shipping_rates']);
        add_action('admin_notices', [$this, 'dompdf_admin_notice']);
        add_action('admin_post_yo_checkout_install_dompdf', [$this, 'install_dompdf']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        add_action('wp_footer', [$this, 'render_modal']);
        add_action('wp_footer', [$this, 'render_google_customer_reviews_scripts'], 60);
        // Precise frontend fallback: hides only matched product cards after successful payment.
        // It does not hide whole grids/sections, so it is safe to keep enabled.
        add_action('wp_footer', [$this, 'render_sold_items_hider'], 50);

        add_action('wp_ajax_yo_checkout_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_nopriv_yo_checkout_create_order', [$this, 'ajax_create_order']);
        add_action('wp_ajax_yo_checkout_apply_promo_code', [$this, 'ajax_apply_promo_code']);
        add_action('wp_ajax_nopriv_yo_checkout_apply_promo_code', [$this, 'ajax_apply_promo_code']);
        add_action('wp_ajax_yo_checkout_update_shipping_option', [$this, 'ajax_update_shipping_option']);
        add_action('wp_ajax_nopriv_yo_checkout_update_shipping_option', [$this, 'ajax_update_shipping_option']);
        add_action('wp_ajax_yo_checkout_validate_cart_items', [$this, 'ajax_validate_cart_items']);
        add_action('wp_ajax_nopriv_yo_checkout_validate_cart_items', [$this, 'ajax_validate_cart_items']);
        add_action('wp_ajax_yo_checkout_reserve_item', [$this, 'ajax_reserve_item']);
        add_action('wp_ajax_nopriv_yo_checkout_reserve_item', [$this, 'ajax_reserve_item']);
        add_action('wp_ajax_yo_checkout_release_reservation', [$this, 'ajax_release_reservation']);
        add_action('wp_ajax_nopriv_yo_checkout_release_reservation', [$this, 'ajax_release_reservation']);
        add_action('wp_ajax_yo_checkout_get_reservations', [$this, 'ajax_get_reservations']);
        add_action('wp_ajax_nopriv_yo_checkout_get_reservations', [$this, 'ajax_get_reservations']);
        add_action('wp_ajax_yo_checkout_start_card_payment', [$this, 'ajax_start_card_payment']);
        add_action('wp_ajax_nopriv_yo_checkout_start_card_payment', [$this, 'ajax_start_card_payment']);
        add_action('wp_ajax_yo_checkout_create_bank_invoice', [$this, 'ajax_create_bank_invoice']);
        add_action('wp_ajax_nopriv_yo_checkout_create_bank_invoice', [$this, 'ajax_create_bank_invoice']);
        add_action('wp_ajax_yo_checkout_check_payment_status', [$this, 'ajax_check_payment_status']);
        add_action('wp_ajax_nopriv_yo_checkout_check_payment_status', [$this, 'ajax_check_payment_status']);
        add_action('wp_ajax_yo_checkout_final_order_status', [$this, 'ajax_final_order_status']);
        add_action('wp_ajax_nopriv_yo_checkout_final_order_status', [$this, 'ajax_final_order_status']);
        add_action('wp_ajax_yo_checkout_wayforpay_form', [$this, 'ajax_wayforpay_form']);
        add_action('wp_ajax_nopriv_yo_checkout_wayforpay_form', [$this, 'ajax_wayforpay_form']);
        add_action('wp_ajax_yo_checkout_wayforpay_return', [$this, 'ajax_wayforpay_return']);
        add_action('wp_ajax_nopriv_yo_checkout_wayforpay_return', [$this, 'ajax_wayforpay_return']);

        add_action('rest_api_init', [$this, 'rest_routes']);
        add_action('yo_checkout_check_unpaid_order', [$this, 'check_unpaid_order']);
        add_action('yo_checkout_deferred_payment_finalizer', [$this, 'deferred_payment_finalizer'], 10, 2);
    }

    public static function default_shipping_rates_eur() {
        // Nova Post official international tariff table up to 2 kg converted from UAH to EUR using 51 UAH/EUR and rounded up.
        // Source tariff logic for <=2 kg: Europe zones = price up to 1 kg + next 1 kg; USA has separate 2 kg tariff.
        return "Poland=10\nCzech Republic=16\nLithuania=16\nGermany=16\nSlovakia=16\nAustria=22\nNetherlands=22\nEstonia=22\nItaly=22\nLatvia=22\nBelgium=27\nVatican=27\nUnited Kingdom=27\nGreat Britain=27\nUK=27\nDenmark=27\nSpain=27\nFrance=27\nLuxembourg=27\nMonaco=27\nSan Marino=27\nAlbania=50\nBosnia and Herzegovina=50\nIceland=50\nCyprus=50\nMalta=50\nNorth Macedonia=50\nSerbia=50\nMontenegro=50\nAndorra=32\nBulgaria=32\nGreece=32\nGibraltar=32\nIreland=32\nPortugal=32\nSlovenia=32\nFinland=32\nCroatia=32\nSweden=32\nLiechtenstein=47\nNorway=47\nTurkey=47\nSwitzerland=47\nUnited States=26\nUSA=26\nUS=26\nCanada=30\nChina=51\nHong Kong=51\nAustralia=53\nAzerbaijan=53\nAlgeria=53\nAmerican Samoa=53\nAngola=53\nAnguilla=53\nAntigua and Barbuda=53\nArgentina=53\nAruba=53\nAfghanistan=53\nBahamas=53\nBangladesh=53\nBarbados=53\nBahrain=53\nBelize=53\nBenin=53\nBermuda=53\nBolivia=53\nBonaire=53\nBotswana=53\nBrazil=53\nBritish Virgin Islands=53\nBrunei=53\nBurkina Faso=53\nBurundi=53\nBhutan=53\nVietnam=53\nVanuatu=53\nUS Virgin Islands=53\nVenezuela=53\nArmenia=53\nGabon=53\nHaiti=53\nGambia=53\nGhana=53\nGuinea=53\nGuinea-Bissau=53\nHonduras=53\nGeorgia=53\nGuyana=53\nGuadeloupe=53\nGuatemala=53\nGrenada=53\nGreenland=53\nGuam=53\nDjibouti=53\nDominica=53\nDominican Republic=53\nEcuador=53\nEritrea=53\nEswatini=53\nEthiopia=53\nEgypt=53\nZambia=53\nZimbabwe=53\nIsrael=53\nIndia=53\nIndonesia=53\nIraq=53\nJordan=53\nCape Verde=53\nKazakhstan=53\nCayman Islands=53\nCambodia=53\nCameroon=53\nQatar=53\nKenya=53\nKyrgyzstan=53\nColombia=53\nComoros=53\nCongo=53\nCosta Rica=53\nIvory Coast=53\nKuwait=53\nCook Islands=53\nCuracao=53\nLaos=53\nLesotho=53\nLiberia=53\nLebanon=53\nMauritius=53\nMauritania=53\nMadagascar=53\nMayotte=53\nMacau=53\nMalawi=53\nMalaysia=53\nMali=53\nMaldives=53\nMorocco=53\nMartinique=53\nMarshall Islands=53\nMexico=53\nMozambique=53\nMongolia=53\nNamibia=53\nNepal=53\nNiger=53\nNigeria=53\nNicaragua=53\nNew Zealand=53\nNew Caledonia=53\nUnited Arab Emirates=53\nUAE=53\nOman=53\nPakistan=53\nPalau=53\nPalestine=53\nPanama=53\nPapua New Guinea=53\nParaguay=53\nPeru=53\nSouth Africa=53\nNorthern Mariana Islands=53\nPuerto Rico=53\nRepublic of Congo=53\nSouth Korea=53\nKorea=53\nReunion=53\nRwanda=53\nEl Salvador=53\nSamoa=53\nSaudi Arabia=53\nSeychelles=53\nSaint Barthelemy=53\nSenegal=53\nSaint Martin=53\nSaint Vincent and the Grenadines=53\nSaint Kitts and Nevis=53\nSaint Lucia=53\nSingapore=53\nSolomon Islands=53\nEast Timor=53\nSierra Leone=53\nThailand=53\nTahiti=53\nTaiwan=53\nTanzania=53\nTurks and Caicos Islands=53\nTogo=53\nTonga=53\nTrinidad and Tobago=53\nTunisia=53\nUganda=53\nUzbekistan=53\nUruguay=53\nFaroe Islands=53\nFiji=53\nPhilippines=53\nFrench Guiana=53\nChad=53\nChile=53\nSri Lanka=53\nJamaica=53\nJapan=53";
    }

    public static function checkout_country_list() {
        // The checkout autocomplete must follow the editable Nova Post fallback tariff table
        // from admin settings, so adding/removing a country in the admin immediately changes
        // the country list shown to customers.
        $saved = get_option(self::OPT, []);
        $rates = isset($saved['shipping_rates_eur']) && trim((string)$saved['shipping_rates_eur']) !== ''
            ? (string)$saved['shipping_rates_eur']
            : self::default_shipping_rates_eur();

        $countries = [];
        foreach (preg_split('/\r\n|\r|\n/', $rates) as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos($line, '=') === false) continue;
            [$country, $price] = array_map('trim', explode('=', $line, 2));
            if ($country === '') continue;
            // Skip invalid tariff rows without numeric price.
            $normalized_price = str_replace(',', '.', $price);
            if ($normalized_price === '' || !is_numeric($normalized_price)) continue;
            if (!in_array($country, $countries, true)) $countries[] = $country;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'yo_shipping_rates';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
            $db_countries = $wpdb->get_col("SELECT DISTINCT country FROM {$table} WHERE country <> '' ORDER BY country ASC");
            foreach ((array)$db_countries as $db_country) {
                $db_country = trim((string)$db_country);
                if ($db_country !== '' && !in_array($db_country, $countries, true)) $countries[] = $db_country;
            }
        }
        sort($countries, SORT_NATURAL | SORT_FLAG_CASE);
        return $countries;
    }

    public static function defaults() {
        return [
            'mono_token' => '',
            'monobank_card_fee_percent' => '2',
            'shipping_enabled' => '1',
            'shipping_default_eur' => '0',
            'shipping_rates_eur' => self::default_shipping_rates_eur(),
            'shipping_service_mode' => 'auto_cheapest',
            'shipping_choice_enabled' => '1',
            'shipping_option_limit' => '5',
            'shipping_fx_mode' => 'auto_nbu',
            'shipping_fx_nbu_url' => 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json',
            'shipping_fx_cache_hours' => '12',
            'shipping_fx_markup_percent' => '0',
            'shipping_import_currency_to_eur' => 'USD=0.92
UAH=0.022
EUR=1',
            'shipping_structured_rates' => '',
            'shipping_structured_notes' => '',
            'nova_post_api_key' => '',
            'nova_post_api_mode' => 'api_fallback',
            'nova_post_api_endpoint' => 'https://api.novapost.com/v.1.0/shipments/calculations',
            'nova_post_api_auth_header' => 'Authorization: Bearer {api_key}',
            'nova_post_sender_country' => 'UA',
            'nova_post_sender_city' => 'Kamianske',
            'nova_post_sender_zip' => '51905',
            'nova_post_weight_kg' => '2',
            'nova_post_length_cm' => '35',
            'nova_post_width_cm' => '25',
            'nova_post_height_cm' => '10',
            'nova_post_currency' => 'EUR',
            'nova_post_incoterm' => 'DAP',
            'wayforpay_merchant_login' => '',
            'wayforpay_secret_key' => '',
            'wayforpay_currency' => 'EUR',
            'wayforpay_test_credentials_mode' => 'official',
            'wayforpay_test_result_mode' => 'simulate_success',
            'wayforpay_card_fee_percent' => '2',
            'wayforpay_countries' => 'United States,USA,US,United Kingdom,UK,Great Britain,England,Scotland,Wales,Northern Ireland,Malaysia,Singapore,Thailand,Indonesia,Philippines,Vietnam,India,China,Japan,South Korea,Hong Kong,Taiwan,United Arab Emirates,UAE,Saudi Arabia,Qatar,Kuwait,Bahrain,Oman',
            'test_mode' => '0',
            'test_card_provider' => 'auto',
            'keycrm_token' => '',
            'keycrm_source_id' => '12',
            'keycrm_currency_id' => '3',
            'keycrm_tag_id' => '4',
            'keycrm_status_waiting' => '23',
            'keycrm_status_paid' => '25',
            'keycrm_status_cancelled' => '13',
            'keycrm_payment_method_card' => '7',
            'keycrm_payment_method_wayforpay' => '8',
            'keycrm_payment_method_bank' => '',
            'cart_added_notification_enabled' => '1',
            'cart_added_notification_position' => 'top-center',
            'cart_added_notification_timeout' => '3200',
            'cart_duplicate_notification_timeout' => '4200',
            'cart_notice_bg_color' => '#07194b',
            'cart_notice_text_color' => '#ffffff',
            'cart_notice_border_color' => '#1e87f0',
            'cart_notice_icon_color' => '#27c970',
            'cart_notice_border_radius' => '14',
            'cart_notice_shadow_opacity' => '34',
            'cart_added_notification_title' => 'Added to cart',
            'cart_duplicate_notification_text' => 'This item is already in the cart. Only one copy of each product can be added.',
            'admin_email' => get_option('admin_email'),
            'from_email' => 'no-reply@' . wp_parse_url(home_url(), PHP_URL_HOST),
            'from_name' => 'YOleotard',
            'seller_name' => 'PE OHILKO YULIIA',
            'seller_address' => "51905, Ukraine,\nDnipropetrovska region,\nKamianske, Veresneva st. 9",
            'invoice_prefix' => '26-',
            'product_purpose' => 'custom leotard',
            'sepa_receiver' => 'PE OHILKO YULIIA',
            'sepa_iban' => '',
            'sepa_swift' => '',
            'sepa_bank' => '',
            'sepa_bank_address' => '',
            'swift_receiver' => 'PE OHILKO YULIIA',
            'swift_iban' => '',
            'swift_swift' => '',
            'swift_bank' => '',
            'swift_bank_address' => '',
            'sepa_countries' => 'Austria,Belgium,Bulgaria,Croatia,Cyprus,Czech Republic,Denmark,Estonia,Finland,France,Germany,Greece,Hungary,Iceland,Ireland,Italy,Latvia,Liechtenstein,Lithuania,Luxembourg,Malta,Monaco,Netherlands,Norway,Poland,Portugal,Romania,San Marino,Slovakia,Slovenia,Spain,Sweden,Switzerland,United Kingdom,Vatican City',
            'email_bank_subject' => 'YOleotard invoice for order № {order_id}',
            'email_paid_subject' => 'YOleotard order confirmation № {order_id}',
            'promo_code' => '',
            'promo_discount_value' => '',
            'promo_discount_type' => 'percent',
            'promo_expires_at' => '',
            'promo_badge_text' => 'Discount by promo code',
            'promo_badge_text_color' => '#ffffff',
            'promo_badge_font_size' => '12',
            'promo_badge_bg_color' => '#0b8f2f',
            'promo_badge_opacity' => '100',
            'google_reviews_optin_enabled' => '0',
            'google_reviews_merchant_id' => '5085053718',
            'google_reviews_delivery_days' => '14',
            'google_reviews_products_gtins' => '',
            'google_reviews_badge_enabled' => '0',
            'google_reviews_badge_position' => 'BOTTOM_RIGHT',
            'google_reviews_badge_region' => '',
            'auto_hide_sold_enabled' => '1',
            'auto_hide_sold_page_id' => '',
            'auto_hide_sold_log' => '',
            'auto_hide_sold_frontend_fallback' => '0',
            'reservation_minutes' => '30',
            'reservation_badge_text' => 'Reserved',
        ];
    }

    public static function settings() {
        return wp_parse_args(get_option(self::OPT, []), self::defaults());
    }

    public function register_cpt() {
        register_post_type(self::CPT, [
            'label' => 'YOleotard Orders',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post'
        ]);
    }

    public function admin_menu() {
        add_options_page('YOleotard Checkout', 'YOleotard Checkout', 'manage_options', 'yo-checkout-invoice', [$this, 'settings_page']);
    }

    private function shipping_rates_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'yo_shipping_rates';
    }

    public function ensure_shipping_tables() {
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            service VARCHAR(40) NOT NULL DEFAULT '',
            method VARCHAR(80) NOT NULL DEFAULT '',
            country VARCHAR(120) NOT NULL DEFAULT '',
            country_norm VARCHAR(120) NOT NULL DEFAULT '',
            zone VARCHAR(40) NOT NULL DEFAULT '',
            weight_to_kg DECIMAL(10,3) NOT NULL DEFAULT 0,
            price DECIMAL(12,2) NOT NULL DEFAULT 0,
            currency VARCHAR(10) NOT NULL DEFAULT 'EUR',
            delivery_days VARCHAR(40) NOT NULL DEFAULT '',
            source VARCHAR(120) NOT NULL DEFAULT 'manual_csv',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY service_country_weight (service, country_norm, weight_to_kg),
            KEY country_weight (country_norm, weight_to_kg),
            KEY country (country)
        ) {$charset_collate};";
        dbDelta($sql);
    }

    private function normalize_service_code($service) {
        $service = strtolower(trim((string)$service));
        $service = str_replace(['-', ' '], '_', $service);
        $service = preg_replace('/[^a-z0-9_]+/', '', $service);
        $map = [
            'nova_post' => 'nova_post', 'novapost' => 'nova_post', 'nova_poshta' => 'nova_post', 'novaposhta' => 'nova_post',
            'nova_global' => 'nova_global', 'novaglobal' => 'nova_global', 'npg' => 'nova_global',
            'ukrposhta' => 'ukrposhta', 'ukr_post' => 'ukrposhta', 'ukrposhta_ems' => 'ukrposhta',
        ];
        return $map[$service] ?? $service;
    }

    private function parse_shipping_csv_rows($csv_text, $source = 'manual_csv') {
        $rows = [];
        $csv_text = str_replace("\r\n", "\n", (string)$csv_text);
        $lines = preg_split('/\n/', $csv_text);
        if (!$lines) return [];
        $header = null;
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos(ltrim($line), '#') === 0) continue;
            $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
            $cols = str_getcsv($line, $delimiter);
            $cols = array_map('trim', $cols);
            if (!$header) {
                $first = strtolower($cols[0] ?? '');
                if (in_array($first, ['service','carrier','служба','перевізник'], true)) {
                    $header = array_map(function($v){ return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v))); }, $cols);
                    continue;
                }
                $header = ['service','country','weight_to_kg','price','currency','delivery_days','zone','method','source'];
            }
            $data = [];
            foreach ($cols as $i => $value) {
                $key = $header[$i] ?? '';
                if ($key !== '') $data[$key] = $value;
            }
            $service = $data['service'] ?? $data['carrier'] ?? $data['перевізник'] ?? '';
            $country = $data['country'] ?? $data['країна'] ?? $data['destination'] ?? '';
            $weight = $data['weight_to_kg'] ?? $data['weight_to'] ?? $data['weight'] ?? $data['вага'] ?? '';
            $price = $data['price'] ?? $data['cost'] ?? $data['amount'] ?? $data['ціна'] ?? '';
            $currency = $data['currency'] ?? $data['валюта'] ?? 'EUR';
            $days = $data['delivery_days'] ?? $data['days'] ?? $data['термін'] ?? '';
            $zone = $data['zone'] ?? $data['зона'] ?? '';
            $method = $data['method'] ?? $data['tariff'] ?? $data['тариф'] ?? '';
            $row_source = $data['source'] ?? $source;
            $weight = str_replace(',', '.', (string)$weight);
            $price = str_replace(',', '.', (string)$price);
            if ($service === '' || $country === '' || !is_numeric($weight) || !is_numeric($price)) continue;
            $rows[] = [
                'service' => $this->normalize_service_code($service),
                'method' => sanitize_text_field($method),
                'country' => sanitize_text_field($country),
                'country_norm' => $this->normalize_country_name($country),
                'zone' => sanitize_text_field($zone),
                'weight_to_kg' => max(0, (float)$weight),
                'price' => max(0, (float)$price),
                'currency' => strtoupper(sanitize_text_field($currency ?: 'EUR')),
                'delivery_days' => sanitize_text_field($days),
                'source' => sanitize_text_field($row_source ?: $source),
            ];
        }
        return $rows;
    }


    private function parse_csv_assoc_rows($csv_text) {
        $csv_text = str_replace("\r\n", "\n", (string)$csv_text);
        $lines = preg_split('/\n/', $csv_text);
        $header = null;
        $rows = [];
        foreach ((array)$lines as $line) {
            $line = trim((string)$line);
            if ($line === '' || strpos(ltrim($line), '#') === 0) continue;
            $delimiter = (substr_count($line, ';') > substr_count($line, ',')) ? ';' : ',';
            $cols = array_map('trim', str_getcsv($line, $delimiter));
            if (!$header) {
                $header = array_map(function($v){ return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$v))); }, $cols);
                continue;
            }
            $row = [];
            foreach ($cols as $i => $value) {
                $key = $header[$i] ?? '';
                if ($key !== '') $row[$key] = $value;
            }
            if ($row) $rows[] = $row;
        }
        return $rows;
    }

    private function read_uploaded_or_text_csv($file_key, $text_key) {
        if (!empty($_FILES[$file_key]['tmp_name']) && is_uploaded_file($_FILES[$file_key]['tmp_name'])) {
            return (string)file_get_contents($_FILES[$file_key]['tmp_name']);
        }
        return (string)wp_unslash($_POST[$text_key] ?? '');
    }

    public function admin_import_shipping_zone_rates() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('yo_shipping_import_zone_rates');
        $this->ensure_shipping_tables();

        $zones_csv = $this->read_uploaded_or_text_csv('shipping_zones_csv', 'shipping_zones_csv_text');
        $rates_csv = $this->read_uploaded_or_text_csv('shipping_rates_by_zone_csv', 'shipping_rates_by_zone_csv_text');
        $zones = $this->parse_csv_assoc_rows($zones_csv);
        $rates = $this->parse_csv_assoc_rows($rates_csv);

        $replace = !empty($_POST['replace_existing_zone_rates']);
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        if ($replace) $wpdb->query("TRUNCATE TABLE {$table}");

        $zone_map = [];
        foreach ($zones as $z) {
            $service = $this->normalize_service_code($z['service'] ?? 'nova_global');
            $method = sanitize_text_field($z['method'] ?? 'seller_one');
            $country = sanitize_text_field($z['country'] ?? $z['destination'] ?? '');
            $zone = sanitize_text_field($z['zone'] ?? '');
            if ($country === '' || $zone === '') continue;
            $zone_map[] = [
                'service' => $service,
                'method' => $method,
                'country' => $country,
                'country_norm' => $this->normalize_country_name($country),
                'zone' => $zone,
            ];
        }

        $rate_map = [];
        foreach ($rates as $r) {
            $service = $this->normalize_service_code($r['service'] ?? 'nova_global');
            $method = sanitize_text_field($r['method'] ?? 'seller_one');
            $zone = sanitize_text_field($r['zone'] ?? '');
            $weight = str_replace(',', '.', (string)($r['weight_to_kg'] ?? $r['weight_to'] ?? $r['weight'] ?? ''));
            $price = str_replace(',', '.', (string)($r['price'] ?? $r['cost'] ?? $r['amount'] ?? ''));
            if ($zone === '' || !is_numeric($weight) || !is_numeric($price)) continue;
            $key = $service . '|' . strtolower($method) . '|' . $zone;
            $rate_map[$key][] = [
                'service' => $service,
                'method' => $method,
                'zone' => $zone,
                'weight_to_kg' => max(0, (float)$weight),
                'price' => max(0, (float)$price),
                'currency' => strtoupper(sanitize_text_field($r['currency'] ?? 'EUR')),
                'delivery_days' => sanitize_text_field($r['delivery_days'] ?? $r['days'] ?? ''),
            ];
        }

        $inserted = 0;
        foreach ($zone_map as $z) {
            $key = $z['service'] . '|' . strtolower($z['method']) . '|' . $z['zone'];
            if (empty($rate_map[$key])) {
                // fallback for files where method spelling differs but service+zone match
                foreach ($rate_map as $rk => $rv) {
                    if (strpos($rk, $z['service'] . '|') === 0 && substr($rk, -strlen('|' . $z['zone'])) === '|' . $z['zone']) {
                        $rate_map[$key] = $rv;
                        break;
                    }
                }
            }
            foreach ((array)($rate_map[$key] ?? []) as $r) {
                $row = [
                    'service' => $z['service'],
                    'method' => $z['method'],
                    'country' => $z['country'],
                    'country_norm' => $z['country_norm'],
                    'zone' => $z['zone'],
                    'weight_to_kg' => $r['weight_to_kg'],
                    'price' => $r['price'],
                    'currency' => $r['currency'],
                    'delivery_days' => $r['delivery_days'],
                    'source' => sanitize_text_field($_POST['zone_rates_source'] ?? 'zone_rates_csv'),
                ];
                $ok = $wpdb->insert($table, $row, ['%s','%s','%s','%s','%s','%f','%f','%s','%s','%s']);
                if ($ok) $inserted++;
            }
        }

        wp_safe_redirect(add_query_arg([
            'page'=>'yo-checkout-invoice',
            'tab'=>'shipping',
            'shipping_zone_imported'=>$inserted,
            'shipping_zones_count'=>count($zone_map),
            'shipping_rate_groups'=>count($rate_map),
        ], admin_url('options-general.php')));
        exit;
    }

    public function admin_import_shipping_csv() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('yo_shipping_import_csv');
        $this->ensure_shipping_tables();
        $csv = '';
        if (!empty($_FILES['shipping_csv']['tmp_name']) && is_uploaded_file($_FILES['shipping_csv']['tmp_name'])) {
            $csv = file_get_contents($_FILES['shipping_csv']['tmp_name']);
        } else {
            $csv = wp_unslash($_POST['shipping_csv_text'] ?? '');
        }
        $replace = !empty($_POST['replace_existing']);
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        if ($replace) $wpdb->query("TRUNCATE TABLE {$table}");
        $rows = $this->parse_shipping_csv_rows($csv, 'admin_csv');
        $inserted = 0;
        foreach ($rows as $row) {
            $ok = $wpdb->insert($table, $row, ['%s','%s','%s','%s','%s','%f','%f','%s','%s','%s']);
            if ($ok) $inserted++;
        }
        wp_safe_redirect(add_query_arg(['page'=>'yo-checkout-invoice','tab'=>'shipping','shipping_imported'=>$inserted], admin_url('options-general.php')));
        exit;
    }

    public function admin_clear_shipping_rates() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('yo_shipping_clear_rates');
        $this->ensure_shipping_tables();
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        $wpdb->query("TRUNCATE TABLE {$table}");
        wp_safe_redirect(add_query_arg(['page'=>'yo-checkout-invoice','tab'=>'shipping','shipping_cleared'=>'1'], admin_url('options-general.php')));
        exit;
    }

    public function register_settings() {
        register_setting('yo_checkout_invoice_group', self::OPT, [$this, 'sanitize_settings']);
    }



    public function install_dompdf() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        check_admin_referer('yo_checkout_install_dompdf');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']) . 'yoleotard-checkout/vendor';
        $target = trailingslashit($base_dir) . 'dompdf';
        wp_mkdir_p($base_dir);
        $tmp = download_url('https://downloads.sourceforge.net/project/dompdf.mirror/v3.1.5/dompdf-3.1.5.zip', 60);
        if (is_wp_error($tmp)) {
            wp_safe_redirect(add_query_arg(['page'=>'yo-checkout-invoice','yo_dompdf'=>'download_error'], admin_url('options-general.php')));
            exit;
        }

        $unzip_dir = trailingslashit($base_dir) . 'dompdf-tmp';
        if (file_exists($unzip_dir)) $this->rrmdir($unzip_dir);
        wp_mkdir_p($unzip_dir);
        $result = unzip_file($tmp, $unzip_dir);
        @unlink($tmp);

        if (is_wp_error($result)) {
            wp_safe_redirect(add_query_arg(['page'=>'yo-checkout-invoice','yo_dompdf'=>'unzip_error'], admin_url('options-general.php')));
            exit;
        }

        if (file_exists($target)) $this->rrmdir($target);
        $source = $unzip_dir;
        $items = glob($unzip_dir . '/*');
        if (count($items) === 1 && is_dir($items[0])) $source = $items[0];
        rename($source, $target);
        if (file_exists($unzip_dir)) $this->rrmdir($unzip_dir);

        wp_safe_redirect(add_query_arg(['page'=>'yo-checkout-invoice','yo_dompdf'=>'installed'], admin_url('options-general.php')));
        exit;
    }

    private function rrmdir($dir) {
        if (!is_dir($dir)) return;
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getRealPath()) : @unlink($item->getRealPath());
        }
        @rmdir($dir);
    }

    public function dompdf_admin_notice() {
        if (!current_user_can('manage_options')) return;
        if ($this->dompdf_autoload_path()) return;
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || strpos($screen->id, 'yo-checkout-invoice') === false) return;
        echo '<div class="notice notice-warning"><p><strong>YOleotard Checkout:</strong> Dompdf library was not found in persistent storage <code>wp-content/uploads/yoleotard-checkout/vendor/dompdf/autoload.inc.php</code>. PDF generation will not work until Dompdf is added. HTML invoices will still be created.</p></div>';
    }

    private function dompdf_autoload_path() {
        $upload_dir = wp_upload_dir();
        $paths = [
            trailingslashit($upload_dir['basedir']) . 'yoleotard-checkout/vendor/dompdf/autoload.inc.php',
            plugin_dir_path(__FILE__) . 'lib/dompdf/autoload.inc.php', // legacy fallback
            plugin_dir_path(__FILE__) . 'vendor/autoload.php', // composer fallback
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) return $path;
        }
        return '';
    }

    public function sanitize_settings($input) {
        // IMPORTANT: settings are split into tabs. WordPress sends only fields
        // from the currently opened tab, so missing fields must keep their
        // previous values. Otherwise saving WayForPay/Monobank would erase
        // KeyCRM token and other settings.
        $defaults = self::defaults();
        $current = get_option(self::OPT, []);
        $out = wp_parse_args(is_array($current) ? $current : [], $defaults);

        if (!is_array($input)) return $out;

        foreach ($input as $key => $value) {
            if (!array_key_exists($key, $defaults)) continue;
            $out[$key] = is_string($value) ? wp_kses_post(trim($value)) : $value;
        }

        return $out;
    }

    public function settings_page() {
        $s = self::settings();
        $tab = sanitize_key($_GET['tab'] ?? 'bank');
        $tabs = [
            'bank' => 'Банковские данные',
            'shipping' => 'Доставка',
            'invoice' => 'Инвойс',
            'keycrm' => 'KeyCRM',
            'monobank' => 'Monobank',
            'wayforpay' => 'WayForPay',
            'promo' => 'Промокод',
            'sold_items' => 'Скрытие / резерв',
            'google_reviews' => 'Google отзывы',
        ];
        if (!isset($tabs[$tab])) $tab = 'bank';
        ?>
        <div class="wrap">
            <h1>YOleotard Checkout</h1>
            <div class="notice notice-info inline"><p><strong>Dompdf status:</strong> <?php echo $this->dompdf_autoload_path() ? '<span style="color:green">installed</span>' : '<span style="color:#b32d2e">not installed</span>'; ?>. PDF generation uses Dompdf with UTF-8 and DejaVu Sans. Dompdf is stored in <code>wp-content/uploads/yoleotard-checkout/vendor/dompdf/</code>, so it stays installed after plugin updates.</p>
            <?php if (!$this->dompdf_autoload_path()): ?>
                <p><a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=yo_checkout_install_dompdf'), 'yo_checkout_install_dompdf')); ?>">Install Dompdf into persistent storage</a></p>
            <?php endif; ?>
            </div>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label): ?>
                    <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(add_query_arg(['page'=>'yo-checkout-invoice','tab'=>$key], admin_url('options-general.php'))); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </h2>

            <form method="post" action="options.php">
                <?php settings_fields('yo_checkout_invoice_group'); ?>

                <?php if ($tab === 'bank'): ?>
                    <h2>SEPA / IBAN details</h2>
                    <table class="form-table">
                        <?php $this->field('sepa_receiver', 'SEPA receiver'); ?>
                        <?php $this->field('sepa_iban', 'SEPA IBAN'); ?>
                        <?php $this->field('sepa_swift', 'SEPA SWIFT/BIC'); ?>
                        <?php $this->field('sepa_bank', 'SEPA bank name'); ?>
                        <?php $this->textarea('sepa_bank_address', 'SEPA bank address'); ?>
                        <?php $this->textarea('sepa_countries', 'Countries treated as SEPA / IBAN'); ?>
                        <?php $this->field('swift_receiver', 'SWIFT receiver'); ?>
                        <?php $this->field('swift_iban', 'SWIFT IBAN / Account'); ?>
                        <?php $this->field('swift_swift', 'SWIFT/BIC'); ?>
                        <?php $this->field('swift_bank', 'SWIFT bank name'); ?>
                        <?php $this->textarea('swift_bank_address', 'SWIFT bank address'); ?>
                    </table>
                <?php elseif ($tab === 'shipping'): ?>
                    <h2>Shipping tariffs import</h2>
                    <table class="form-table">
                        <?php $this->select('shipping_enabled', 'Add shipping to checkout total', ['1'=>'On', '0'=>'Off']); ?>
                        <?php $this->select('shipping_service_mode', 'Service selection mode', ['auto_cheapest'=>'Auto: cheapest available service', 'auto_fastest'=>'Auto: fastest available service', 'nova_post'=>'Nova Post only', 'nova_global'=>'Nova Global only', 'ukrposhta'=>'Ukrposhta only']); ?>
                        <?php $this->select('shipping_choice_enabled', 'Show selectable shipping options in checkout', ['1'=>'On', '0'=>'Off']); ?>
                        <?php $this->field('shipping_option_limit', 'Maximum shipping options shown to customer'); ?>
                        <?php $this->field('nova_post_weight_kg', 'Default parcel weight, kg'); ?>
                        <?php $this->field('nova_post_length_cm', 'Default parcel length, cm'); ?>
                        <?php $this->field('nova_post_width_cm', 'Default parcel width, cm'); ?>
                        <?php $this->field('nova_post_height_cm', 'Default parcel height, cm'); ?>
                        <?php $this->field('shipping_default_eur', 'Default shipping cost if country is not found, EUR'); ?>
                        <?php $this->select('shipping_fx_mode', 'Currency conversion mode', ['auto_nbu'=>'Auto from NBU API with cache', 'manual'=>'Manual rates below']); ?>
                        <?php $this->field('shipping_fx_nbu_url', 'NBU exchange rate URL'); ?>
                        <?php $this->field('shipping_fx_cache_hours', 'FX cache time, hours'); ?>
                        <?php $this->field('shipping_fx_markup_percent', 'FX safety markup, %'); ?>
                        <?php $this->textarea('shipping_import_currency_to_eur', 'Manual currency conversion rates to EUR. One per line: USD=0.92'); ?>
                        <p class="description">Auto mode uses the same principle as the product card currency converter: it loads official NBU rates in UAH, caches them, and converts imported USD/UAH tariffs to EUR. Formula: amount in source currency × (source UAH rate / EUR UAH rate). Manual rates are used as fallback if the API is unavailable.</p>
                        <?php $this->textarea('shipping_structured_rates', 'Structured tariff import table. Format: service|country|weight_to_kg|price|currency|delivery_days|zone|method'); ?>
                        <p class="description">Use one row per country + service + weight bracket. Example: <code>nova_global|Malaysia|2|45.88|USD|5-6|10|Seller One</code>. The checkout converts USD/UAH to EUR by the rates above and chooses the row where customer country matches and parcel weight is less than or equal to weight_to_kg. This table is the recommended place for data parsed from PDF tables.</p>
                        <?php $this->textarea('shipping_rates_eur', 'Legacy simple fallback table, EUR. One country per line: France=12'); ?>
                        <p class="description">Legacy table is kept for compatibility and country autocomplete. Structured tariff table has priority. If it is empty or no row matches, the plugin uses this simple fallback table, then the default price.</p>
                        <?php $this->textarea('shipping_structured_notes', 'Internal notes about imported tariff PDFs'); ?>
                    </table>
                    <h3>Optional Nova Post API fallback</h3>
                    <table class="form-table">
                        <?php $this->select('nova_post_api_mode', 'Nova Post API mode', ['api_fallback'=>'Nova Post API first, then local tables', 'table_only'=>'Local tables only']); ?>
                        <?php $this->field('nova_post_api_key', 'Nova Post API key / token', 'password'); ?>
                        <?php $this->field('nova_post_api_endpoint', 'Nova Post API calculation endpoint'); ?>
                        <?php $this->field('nova_post_api_auth_header', 'API authorization header template'); ?>
                        <?php $this->field('nova_post_sender_country', 'Sender country ISO code'); ?>
                        <?php $this->field('nova_post_sender_city', 'Sender city'); ?>
                        <?php $this->field('nova_post_sender_zip', 'Sender ZIP / postal code'); ?>
                        <?php $this->field('nova_post_currency', 'Calculation currency'); ?>
                        <?php $this->field('nova_post_incoterm', 'Incoterm'); ?>
                    </table>
                <?php elseif ($tab === 'invoice'): ?>
                    <h2>Invoice and email</h2>
                    <table class="form-table">
                        <?php $this->field('admin_email', 'Admin copy email'); ?>
                        <?php $this->field('from_email', 'From email'); ?>
                        <?php $this->field('from_name', 'From name'); ?>
                        <?php $this->field('seller_name', 'Seller name'); ?>
                        <?php $this->textarea('seller_address', 'Seller address'); ?>
                        <?php $this->field('invoice_prefix', 'Invoice prefix'); ?>
                        <?php $this->field('product_purpose', 'Default payment purpose'); ?>
                        <?php $this->field('email_bank_subject', 'Bank invoice email subject'); ?>
                        <?php $this->field('email_paid_subject', 'Paid order email subject'); ?>
                    </table>
                <?php elseif ($tab === 'keycrm'): ?>
                    <h2>KeyCRM</h2>
                    <table class="form-table">
                        <?php $this->field('keycrm_token', 'KeyCRM API token', 'password'); ?>
                        <?php $this->field('keycrm_source_id', 'Source ID'); ?>
                        <?php $this->field('keycrm_currency_id', 'Currency ID'); ?>
                        <?php $this->field('keycrm_tag_id', 'Tag ID'); ?>
                        <?php $this->field('keycrm_status_waiting', 'Status: waiting payment'); ?>
                        <?php $this->field('keycrm_status_paid', 'Status: paid'); ?>
                        <?php $this->field('keycrm_status_cancelled', 'Status: cancelled if unpaid'); ?>
                        <?php $this->field('keycrm_payment_method_card', 'Payment method ID: Monobank card payment'); ?>
                        <?php $this->field('keycrm_payment_method_wayforpay', 'Payment method ID: WayForPay card payment'); ?>
                        <?php $this->field('keycrm_payment_method_bank', 'Payment method ID: SEPA/SWIFT bank transfer'); ?>
                    </table>
                <?php elseif ($tab === 'monobank'): ?>
                    <h2>Monobank</h2>
                    <table class="form-table">
                        <?php $this->field('mono_token', 'Monobank X-Token', 'password'); ?>
                        <?php $this->field('monobank_card_fee_percent', 'Monobank card service fee, %'); ?>
                    </table>
                    <p><strong>Monobank webhook URL:</strong> <code><?php echo esc_html(rest_url(self::NS . '/mono-webhook')); ?></code></p>
                <?php elseif ($tab === 'promo'): ?>
                    <h2>Promo code discount</h2>
                    <table class="form-table">
                        <?php $this->field('promo_code', 'Promo code'); ?>
                        <?php $this->field('promo_discount_value', 'Discount value'); ?>
                        <?php $this->select('promo_discount_type', 'Discount type', ['percent'=>'Percentage discount, %', 'fixed'=>'Fixed amount, EUR']); ?>
                        <?php $this->field('promo_expires_at', 'Expiration date', 'date'); ?>
                    </table>
                    <h2>Promo badge display</h2>
                    <table class="form-table">
                        <?php $this->field('promo_badge_text', 'Badge text'); ?>
                        <?php $this->field('promo_badge_text_color', 'Badge text color', 'color'); ?>
                        <?php $this->field('promo_badge_font_size', 'Badge text size, px', 'number'); ?>
                        <?php $this->field('promo_badge_bg_color', 'Badge background color', 'color'); ?>
                        <?php $this->field('promo_badge_opacity', 'Badge opacity, %', 'number'); ?>
                    </table>
                    <p class="description">If the promo code field is empty, the product badge and promo code field in checkout are hidden. The discount applies only before the expiration date. Products that already have a red sale discount do not accept an additional promo code.</p>
                <?php elseif ($tab === 'google_reviews'): ?>
                    <h2>Google Customer Reviews</h2>
                    <p class="description">These settings add the Google Customer Reviews survey opt-in on the final successful card-payment page. The merchant badge is optional and can be shown on the site if Google approves the account for the program.</p>
                    <table class="form-table">
                        <?php $this->select('google_reviews_optin_enabled', 'Show survey opt-in after successful card payment', ['1'=>'Enabled', '0'=>'Disabled']); ?>
                        <?php $this->field('google_reviews_merchant_id', 'Google Merchant ID'); ?>
                        <?php $this->field('google_reviews_delivery_days', 'Estimated delivery days after payment', 'number'); ?>
                        <?php $this->textarea('google_reviews_products_gtins', 'Optional product GTINs. One GTIN per line or comma-separated. Leave empty if products do not have GTINs.'); ?>
                    </table>
                    <h2>Google Customer Reviews badge</h2>
                    <table class="form-table">
                        <?php $this->select('google_reviews_badge_enabled', 'Show Google Customer Reviews badge', ['1'=>'Enabled', '0'=>'Disabled']); ?>
                        <?php $this->select('google_reviews_badge_position', 'Badge position', ['BOTTOM_RIGHT'=>'Bottom right', 'BOTTOM_LEFT'=>'Bottom left', 'INLINE'=>'Inline']); ?>
                        <?php $this->field('google_reviews_badge_region', 'Badge region / language code, for example en_US. Optional.'); ?>
                    </table>
                    <p class="description"><strong>Important:</strong> Google requires the checkout and confirmation page to be on your own domain. For the survey opt-in the plugin sends: Merchant ID, Order ID, customer email, delivery country, estimated delivery date, and optional GTINs.</p>
                <?php elseif ($tab === 'sold_items'): ?>
                    <h2>Auto-hide sold YOOtheme items</h2>
                    <table class="form-table">
                        <?php $this->select('auto_hide_sold_enabled', 'Auto-hide sold items after successful card payment', ['1'=>'Enabled', '0'=>'Disabled']); ?>
                        <?php $this->field('auto_hide_sold_page_id', 'YOOtheme page ID with product Grid'); ?>
                        <?php $this->select('auto_hide_sold_frontend_fallback', 'Precise frontend fallback hide sold titles', ['1'=>'Enabled', '0'=>'Disabled']); ?>
                        <?php $this->field('reservation_minutes', 'Reservation time after adding to cart, minutes', 'number'); ?>
                        <?php $this->field('reservation_badge_text', 'Reserved badge text'); ?>
                    </table>
                    <h2>Cart notification after adding product</h2>
                    <table class="form-table">
                        <?php $this->select('cart_added_notification_enabled', 'Show popup notification when product is added to cart', ['1'=>'Enabled', '0'=>'Disabled']); ?>
                        <?php $this->select('cart_added_notification_position', 'Popup position', ['top-center'=>'Top center', 'top-right'=>'Top right', 'bottom-center'=>'Bottom center', 'bottom-right'=>'Bottom right']); ?>
                        <?php $this->field('cart_notice_bg_color', 'Popup background color', 'color'); ?>
                        <?php $this->field('cart_notice_text_color', 'Popup text color', 'color'); ?>
                        <?php $this->field('cart_notice_border_color', 'Popup border color', 'color'); ?>
                        <?php $this->field('cart_notice_icon_color', 'Popup icon color', 'color'); ?>
                        <?php $this->field('cart_notice_border_radius', 'Popup border radius, px', 'number'); ?>
                        <?php $this->field('cart_notice_shadow_opacity', 'Popup shadow opacity, %', 'number'); ?>
                        <?php $this->field('cart_added_notification_title', 'Added-to-cart popup title'); ?>
                        <?php $this->field('cart_added_notification_timeout', 'Added-to-cart popup time, ms', 'number'); ?>
                        <?php $this->field('cart_duplicate_notification_text', 'Duplicate-product popup text'); ?>
                        <?php $this->field('cart_duplicate_notification_timeout', 'Duplicate-product popup time, ms', 'number'); ?>
                    </table>
                    <p class="description">If Page ID is empty, the plugin uses the WordPress front page. After a successful card payment, the plugin disables matching YOOtheme Builder Grid items by setting props.status = disabled. Frontend fallback is optional only for emergency visual hiding and is not required for the Builder status method. The original page content/meta is backed up in page custom fields before the first automatic change for each order.</p>
                    <h3>Last auto-hide log</h3>
                    <textarea readonly rows="8" class="large-text code"><?php echo esc_textarea($s['auto_hide_sold_log'] ?? ''); ?></textarea>
                <?php elseif ($tab === 'wayforpay'): ?>
                    <h2>WayForPay</h2>
                    <table class="form-table">
                        <?php $this->field('wayforpay_merchant_login', 'Merchant login'); ?>
                        <?php $this->field('wayforpay_secret_key', 'Merchant secret key', 'password'); ?>
                        <?php $this->field('wayforpay_currency', 'Currency'); ?>
                        <?php $this->field('wayforpay_card_fee_percent', 'WayForPay card service fee, %'); ?>
                        <?php $this->select('wayforpay_test_credentials_mode', 'WayForPay test credentials', ['official'=>'Use official WayForPay test merchant', 'merchant'=>'Use my merchant login and secret']); ?>
                        <?php $this->select('wayforpay_test_result_mode', 'WayForPay test result handling', ['simulate_success'=>'Simulate successful payment in plugin test mode', 'real_status'=>'Use real WayForPay status']); ?>
                        <?php $this->textarea('wayforpay_countries', 'Countries routed to WayForPay'); ?>
                        <?php $this->select('test_mode', 'Test mode', ['0'=>'Off', '1'=>'On']); ?>
                        <?php $this->select('test_card_provider', 'Test card provider', ['auto'=>'Auto by country', 'monobank'=>'Force Monobank', 'wayforpay'=>'Force WayForPay']); ?>
                    </table>
                    <p><strong>WayForPay service URL:</strong> <code><?php echo esc_html(rest_url(self::NS . '/wayforpay-webhook')); ?></code></p>
                    <p class="description">When test mode is enabled, card payments can be manually forced through Monobank or WayForPay. The simulation option only works when Test mode is On and official WayForPay test credentials are selected. SEPA/SWIFT invoice remains available for every country.</p>
                <?php endif; ?>

                <?php submit_button(); ?>
            </form>
            <?php if ($tab === 'shipping') $this->render_shipping_database_admin(); ?>
        </div>
        <?php
    }

    private function render_shipping_database_admin() {
        if (!current_user_can('manage_options')) return;
        $this->ensure_shipping_tables();
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $services = $wpdb->get_results("SELECT service, COUNT(*) AS cnt FROM {$table} GROUP BY service ORDER BY service ASC");
        $sample = $wpdb->get_results("SELECT service, method, country, zone, weight_to_kg, price, currency, delivery_days, source FROM {$table} ORDER BY id DESC LIMIT 15");
        $notice = '';
        if (isset($_GET['shipping_imported'])) $notice = '<div class="notice notice-success inline"><p>Imported rows: <strong>' . esc_html((string)absint($_GET['shipping_imported'])) . '</strong></p></div>';
        if (isset($_GET['shipping_zone_imported'])) $notice = '<div class="notice notice-success inline"><p>Zone import complete. Expanded rows: <strong>' . esc_html((string)absint($_GET['shipping_zone_imported'])) . '</strong>; countries/zones: <strong>' . esc_html((string)absint($_GET['shipping_zones_count'] ?? 0)) . '</strong>; rate groups: <strong>' . esc_html((string)absint($_GET['shipping_rate_groups'] ?? 0)) . '</strong>.</p></div>';
        if (isset($_GET['shipping_cleared'])) $notice = '<div class="notice notice-success inline"><p>Shipping tariff database was cleared.</p></div>';

        $test_country = sanitize_text_field(wp_unslash($_GET['yo_test_country'] ?? ''));
        $test_weight = str_replace(',', '.', sanitize_text_field(wp_unslash($_GET['yo_test_weight'] ?? '2')));
        $test_result = '';
        if ($test_country !== '' && is_numeric($test_weight)) {
            $candidate = $this->lookup_structured_shipping_rate($test_country, (float)$test_weight);
            if ($candidate) {
                $test_result = '<div class="notice notice-info inline"><p><strong>Test result:</strong> ' . esc_html($test_country) . ', ' . esc_html($test_weight) . ' kg → €' . esc_html(number_format((float)$candidate['amount_eur'], 2, '.', '')) . ' via ' . esc_html($candidate['service']) . ($candidate['method'] ? ' / ' . esc_html($candidate['method']) : '') . ($candidate['delivery_days'] ? ', ' . esc_html($candidate['delivery_days']) . ' days' : '') . '.</p></div>';
            } else {
                $test_result = '<div class="notice notice-warning inline"><p><strong>Test result:</strong> no structured tariff found. Checkout will use legacy fallback/default tariff.</p></div>';
            }
        }
        ?>
        <hr>
        <h2>Shipping tariff database</h2>
        <?php echo $notice; ?>
        <p><strong>Total rows in database:</strong> <?php echo esc_html((string)$count); ?></p>
        <?php if ($services): ?>
            <p><strong>Rows by service:</strong>
            <?php foreach ($services as $svc): ?>
                <code><?php echo esc_html($svc->service . ': ' . $svc->cnt); ?></code>
            <?php endforeach; ?>
            </p>
        <?php endif; ?>

        <h3>Import zone-based tariffs</h3>
        <p class="description">Use this for Nova Global Seller One files: one CSV maps <code>country → zone</code>, the second CSV maps <code>zone + weight → price</code>. The importer expands them into final country-level rows in the shipping database.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="background:#fff;border:1px solid #ccd0d4;padding:15px;max-width:960px;margin-bottom:18px;">
            <?php wp_nonce_field('yo_shipping_import_zone_rates'); ?>
            <input type="hidden" name="action" value="yo_shipping_import_zone_rates">
            <p><label><strong>Source name</strong><br><input type="text" name="zone_rates_source" value="nova-global-seller-one-01052026-max5kg" class="regular-text"></label></p>
            <p><label><strong>Zones CSV</strong> — columns: <code>service,method,country,zone</code><br><input type="file" name="shipping_zones_csv" accept=".csv,text/csv"></label></p>
            <p><textarea name="shipping_zones_csv_text" rows="4" class="large-text" placeholder="service,method,country,zone&#10;nova_global,seller_one,France,5"></textarea></p>
            <p><label><strong>Rates by zone CSV</strong> — columns: <code>service,method,zone,weight_to_kg,price,currency,delivery_days</code><br><input type="file" name="shipping_rates_by_zone_csv" accept=".csv,text/csv"></label></p>
            <p><textarea name="shipping_rates_by_zone_csv_text" rows="4" class="large-text" placeholder="service,method,zone,weight_to_kg,price,currency,delivery_days&#10;nova_global,seller_one,5,1,22.59,USD,5-6"></textarea></p>
            <p><label><input type="checkbox" name="replace_existing_zone_rates" value="1"> Replace all existing database tariffs before import</label></p>
            <?php submit_button('Import zones + rates', 'primary', 'submit', false); ?>
        </form>

        <h3>Import CSV</h3>
        <p class="description">Recommended columns: <code>service,country,weight_to_kg,price,currency,delivery_days,zone,method,source</code>. CSV may use comma or semicolon delimiter. This is the clean layer after PDF parsing.</p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" style="background:#fff;border:1px solid #ccd0d4;padding:15px;max-width:960px;">
            <?php wp_nonce_field('yo_shipping_import_csv'); ?>
            <input type="hidden" name="action" value="yo_shipping_import_csv">
            <p><input type="file" name="shipping_csv" accept=".csv,text/csv"></p>
            <p><textarea name="shipping_csv_text" rows="7" class="large-text" placeholder="nova_global,Malaysia,2,45.88,USD,5-6,10,Seller One,seller-one-01052026"></textarea></p>
            <p><label><input type="checkbox" name="replace_existing" value="1"> Replace all existing database tariffs before import</label></p>
            <?php submit_button('Import shipping CSV', 'secondary', 'submit', false); ?>
        </form>

        <h3>Test calculator</h3>
        <form method="get" action="<?php echo esc_url(admin_url('options-general.php')); ?>" style="background:#fff;border:1px solid #ccd0d4;padding:15px;max-width:960px;">
            <input type="hidden" name="page" value="yo-checkout-invoice">
            <input type="hidden" name="tab" value="shipping">
            <p>
                <label>Country <input type="text" name="yo_test_country" value="<?php echo esc_attr($test_country); ?>" class="regular-text" placeholder="France"></label>
                <label style="margin-left:15px;">Weight, kg <input type="text" name="yo_test_weight" value="<?php echo esc_attr((string)$test_weight); ?>" style="width:90px;"></label>
                <?php submit_button('Calculate', 'secondary', 'submit', false); ?>
            </p>
        </form>
        <?php echo $test_result; ?>

        <h3>Recent imported rows</h3>
        <table class="widefat striped" style="max-width:1100px;">
            <thead><tr><th>Service</th><th>Method</th><th>Country</th><th>Zone</th><th>Weight to kg</th><th>Price</th><th>Currency</th><th>Days</th><th>Source</th></tr></thead>
            <tbody>
            <?php if ($sample): foreach ($sample as $row): ?>
                <tr><td><?php echo esc_html($row->service); ?></td><td><?php echo esc_html($row->method); ?></td><td><?php echo esc_html($row->country); ?></td><td><?php echo esc_html($row->zone); ?></td><td><?php echo esc_html($row->weight_to_kg); ?></td><td><?php echo esc_html($row->price); ?></td><td><?php echo esc_html($row->currency); ?></td><td><?php echo esc_html($row->delivery_days); ?></td><td><?php echo esc_html($row->source); ?></td></tr>
            <?php endforeach; else: ?>
                <tr><td colspan="9">No database tariffs imported yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Clear all imported shipping tariffs?');" style="margin-top:15px;">
            <?php wp_nonce_field('yo_shipping_clear_rates'); ?>
            <input type="hidden" name="action" value="yo_shipping_clear_rates">
            <?php submit_button('Clear imported tariffs', 'delete', 'submit', false); ?>
        </form>
        <?php
    }

    private function field($key, $label, $type='text') {
        $s = self::settings();
        printf('<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><input name="%3$s[%1$s]" id="%1$s" type="%4$s" value="%5$s" class="regular-text" autocomplete="off"></td></tr>',
            esc_attr($key), esc_html($label), esc_attr(self::OPT), esc_attr($type), esc_attr($s[$key] ?? ''));
    }
    private function textarea($key, $label) {
        $s = self::settings();
        printf('<tr><th scope="row"><label for="%1$s">%2$s</label></th><td><textarea name="%3$s[%1$s]" id="%1$s" rows="5" class="large-text">%4$s</textarea></td></tr>',
            esc_attr($key), esc_html($label), esc_attr(self::OPT), esc_textarea($s[$key] ?? ''));
    }
    private function select($key, $label, $options) {
        $s = self::settings();
        echo '<tr><th scope="row"><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><select name="' . esc_attr(self::OPT) . '[' . esc_attr($key) . ']" id="' . esc_attr($key) . '">';
        foreach ($options as $value => $text) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($s[$key] ?? '', $value, false) . '>' . esc_html($text) . '</option>';
        }
        echo '</select></td></tr>';
    }

    public function enqueue() {
        $s = self::settings();
        wp_enqueue_script('yo-checkout-invoice', plugin_dir_url(__FILE__) . 'assets/yo-checkout.js', [], '4.0.19', true);
        wp_localize_script('yo-checkout-invoice', 'YOCheckout', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yo_checkout_nonce'),
            'countries' => self::checkout_country_list(),
            'promoEnabled' => $this->promo_is_configured() && !$this->promo_is_expired() && floatval(str_replace(',', '.', (string)($s['promo_discount_value'] ?? 0))) > 0,
            'promoCode' => sanitize_text_field($s['promo_code'] ?? ''),
            'promoBadgeText' => sanitize_text_field($s['promo_badge_text'] ?? 'Discount by promo code'),
            'promoBadgeTextColor' => sanitize_text_field($s['promo_badge_text_color'] ?? '#ffffff'),
            'promoBadgeFontSize' => max(8, min(30, floatval(str_replace(',', '.', (string)($s['promo_badge_font_size'] ?? 12))))),
            'promoBadgeBgColor' => sanitize_text_field($s['promo_badge_bg_color'] ?? '#0b8f2f'),
            'promoBadgeOpacity' => max(0, min(100, floatval(str_replace(',', '.', (string)($s['promo_badge_opacity'] ?? 100))))),
            'reservationMinutes' => max(1, min(1440, absint($s['reservation_minutes'] ?? 30))),
            'reservationBadgeText' => sanitize_text_field($s['reservation_badge_text'] ?? 'Reserved'),
            'cartAddedNotificationEnabled' => (string)($s['cart_added_notification_enabled'] ?? '1') === '1',
            'cartAddedNotificationPosition' => sanitize_text_field($s['cart_added_notification_position'] ?? 'top-center'),
            'cartAddedNotificationTimeout' => max(500, min(20000, absint($s['cart_added_notification_timeout'] ?? 3200))),
            'cartDuplicateNotificationTimeout' => max(500, min(20000, absint($s['cart_duplicate_notification_timeout'] ?? 4200))),
            'cartAddedNotificationTitle' => sanitize_text_field($s['cart_added_notification_title'] ?? 'Added to cart'),
            'cartDuplicateNotificationText' => sanitize_text_field($s['cart_duplicate_notification_text'] ?? 'This item is already in the cart. Only one copy of each product can be added.'),
            'cartNoticeBgColor' => sanitize_hex_color($s['cart_notice_bg_color'] ?? '#07194b') ?: '#07194b',
            'cartNoticeTextColor' => sanitize_hex_color($s['cart_notice_text_color'] ?? '#ffffff') ?: '#ffffff',
            'cartNoticeBorderColor' => sanitize_hex_color($s['cart_notice_border_color'] ?? '#1e87f0') ?: '#1e87f0',
            'cartNoticeIconColor' => sanitize_hex_color($s['cart_notice_icon_color'] ?? '#27c970') ?: '#27c970',
            'cartNoticeBorderRadius' => max(0, min(40, absint($s['cart_notice_border_radius'] ?? 14))),
            'cartNoticeShadowOpacity' => max(0, min(80, absint($s['cart_notice_shadow_opacity'] ?? 34))),
            'googleReviewsOptinEnabled' => (string)($s['google_reviews_optin_enabled'] ?? '0') === '1',
            'googleReviewsMerchantId' => sanitize_text_field($s['google_reviews_merchant_id'] ?? ''),
            'googleReviewsDeliveryDays' => max(0, absint($s['google_reviews_delivery_days'] ?? 14)),
        ]);
        wp_enqueue_style('yo-checkout-invoice', plugin_dir_url(__FILE__) . 'assets/yo-checkout.css', [], '3.3.88');
    }



    public function render_google_customer_reviews_scripts() {
        $s = self::settings();
        $merchant_id = preg_replace('/[^0-9]/', '', (string)($s['google_reviews_merchant_id'] ?? ''));
        if ($merchant_id === '') return;
        $optin_enabled = (string)($s['google_reviews_optin_enabled'] ?? '0') === '1';
        $badge_enabled = (string)($s['google_reviews_badge_enabled'] ?? '0') === '1';
        if (!$optin_enabled && !$badge_enabled) return;
        $gtins_raw = (string)($s['google_reviews_products_gtins'] ?? '');
        $gtins = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $gtins_raw))));
        $gtins = array_values(array_filter($gtins, function($v){ return $v !== ''; }));
        $badge_position = sanitize_text_field($s['google_reviews_badge_position'] ?? 'BOTTOM_RIGHT');
        if (!in_array($badge_position, ['BOTTOM_RIGHT','BOTTOM_LEFT','INLINE'], true)) $badge_position = 'BOTTOM_RIGHT';
        $badge_region = sanitize_text_field($s['google_reviews_badge_region'] ?? '');
        ?>
<?php if ($optin_enabled): ?>
<script src="https://apis.google.com/js/platform.js?onload=yoRenderGoogleCustomerReviewsOptIn" async defer></script>
<script>
window.yoGoogleCustomerReviewsQueue = window.yoGoogleCustomerReviewsQueue || [];
window.YOCheckoutGoogleReviews = function(orderData){
  orderData = orderData || {};
  var cfg = window.YOCheckout || {};
  var merchantId = String(cfg.googleReviewsMerchantId || '<?php echo esc_js($merchant_id); ?>').replace(/[^0-9]/g, '');
  if(!merchantId || !orderData.order_id || !orderData.email || !orderData.delivery_country || !orderData.estimated_delivery_date) return;
  var payload = {
    merchant_id: merchantId,
    order_id: String(orderData.order_id),
    email: String(orderData.email),
    delivery_country: String(orderData.delivery_country),
    estimated_delivery_date: String(orderData.estimated_delivery_date)
  };
  var gtins = <?php echo wp_json_encode($gtins, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  if(gtins && gtins.length){ payload.products = gtins.map(function(gtin){ return {gtin:String(gtin)}; }); }
  window.yoGoogleCustomerReviewsQueue.push(payload);
  if(window.gapi && window.gapi.load) window.yoRenderGoogleCustomerReviewsOptIn();
};
window.yoRenderGoogleCustomerReviewsOptIn = function(){
  if(!window.gapi || !window.gapi.load) return;
  window.gapi.load('surveyoptin', function(){
    var q = window.yoGoogleCustomerReviewsQueue || [];
    while(q.length){
      try { window.gapi.surveyoptin.render(q.shift()); } catch(e) { if(window.console) console.warn('Google Customer Reviews opt-in error', e); }
    }
  });
};
</script>
<?php endif; ?>
<?php if ($badge_enabled): ?>
<script id="merchantWidgetScript" src="https://www.gstatic.com/shopping/merchant/merchantwidget.js" defer></script>
<script>
(function(){
  var startBadge = function(){
    if(!window.merchantWidget || !window.merchantWidget.start) return;
    var opts = {merchant_id: <?php echo (int)$merchant_id; ?>, position: <?php echo wp_json_encode($badge_position); ?>};
    <?php if ($badge_region !== ''): ?>opts.region = <?php echo wp_json_encode($badge_region); ?>;<?php endif; ?>
    try { window.merchantWidget.start(opts); } catch(e) { if(window.console) console.warn('Google Customer Reviews badge error', e); }
  };
  var script = document.getElementById('merchantWidgetScript');
  if(script) script.addEventListener('load', startBadge);
  window.addEventListener('load', startBadge);
})();
</script>
<?php endif; ?>
        <?php
    }


    public function render_modal() { ?>
        <div id="yo-pay-modal" uk-modal="esc-close:false; bg-close:false">
          <div class="uk-modal-dialog uk-modal-body uk-border-rounded">
            <button class="uk-modal-close-default" type="button" uk-close></button>
            <div class="yo-steps">
              <span id="yo-pill-1" class="yo-step-pill active">1. Order details</span>
              <span id="yo-pill-2" class="yo-step-pill">2. Payment method</span>
              <span id="yo-pill-3" class="yo-step-pill">3. Payment</span>
              <span id="yo-pill-4" class="yo-step-pill">4. Confirmation</span>
            </div>
            <div id="yo-step-cart">
              <h3 class="uk-modal-title">Order details</h3>
              <div id="yo-modal-product-head" class="uk-flex uk-flex-middle uk-margin">
                <div id="yo-modal-imgs" class="yo-product-imgs"><img id="yo-modal-img" src="" alt="" class="yo-product-img"></div>
                <div><div id="yo-modal-title" class="yo-product-title"></div><div id="yo-modal-price" class="yo-product-price"></div></div>
              </div>
              <div id="yo-cart-summary" class="yo-cart-summary"></div>
              <div id="yo-promo-box" class="yo-promo-box yo-hidden">
                <label class="yo-promo-label" for="yo-promo-code">Promo code</label>
                <div id="yo-available-promo" class="yo-available-promo"></div>
                <div class="uk-grid-small" uk-grid>
                  <div class="uk-width-expand"><input id="yo-promo-code" class="uk-input" type="text" autocomplete="off" placeholder="Enter promo code"></div>
                  <div class="uk-width-auto"><button id="yo-apply-promo" class="uk-button uk-button-default" type="button">Apply</button></div>
                </div>
                <div id="yo-promo-message" class="yo-promo-message"></div>
              </div>
              <form id="yo-pay-form" class="uk-grid-small" uk-grid>
                <div class="uk-width-1-1"><input class="uk-input" name="full_name" placeholder="Full recipient name" required></div>
                <div class="uk-width-1-2@s"><input class="uk-input" name="phone" placeholder="Phone number, international format" required></div>
                <div class="uk-width-1-2@s"><input class="uk-input" type="email" name="email" placeholder="Email" required></div>
                <div class="uk-width-1-1"><input class="uk-input" name="address" placeholder="Shipping address" required></div>
                <div class="uk-width-1-1"><input class="uk-input" name="additional_address" placeholder="Additional address"></div>
                <div class="uk-width-1-3@s"><input class="uk-input" name="city" placeholder="City" required></div>
                <div class="uk-width-1-3@s"><input class="uk-input" name="zip_code" placeholder="ZIP / Postal code" required></div>
                <div class="uk-width-1-3@s yo-country-field"><input id="yo-country-input" class="uk-input" name="country" placeholder="Country" autocomplete="off" autocapitalize="words" spellcheck="false" required><div id="yo-country-suggestions" class="yo-country-suggestions" role="listbox" aria-label="Country suggestions"></div></div>
                <div class="uk-width-1-1 uk-margin-top"><button class="uk-button uk-button-primary uk-width-1-1" type="submit">Continue</button></div>
              </form>
            </div>
            <div id="yo-step-loading" class="yo-hidden yo-loading"><div uk-spinner="ratio: 2"></div><h4>Please wait...</h4></div>
            <div id="yo-step-method" class="yo-hidden">
              <button id="yo-back-to-details" class="yo-back-btn" type="button" aria-label="Back to order details">← Edit order details</button>
              <h3 class="uk-modal-title">Choose payment method</h3>
              <p class="yo-note">You can pay by card, Apple Pay or Google Pay. If card payment does not work, please choose bank transfer by SEPA/SWIFT invoice.</p>
              <div id="yo-checkout-receipt" class="yo-fee-note yo-checkout-receipt"></div>
              <label class="yo-terms-check"><input id="yo-accept-terms" class="uk-checkbox" type="checkbox"> I confirm my payment method choice and agree to the <a href="<?php echo esc_url(home_url('/terms-conditions/')); ?>" target="_blank" rel="noopener">Terms &amp; Conditions</a>.</label>
              <div class="yo-methods">
                <button id="yo-pay-card" class="uk-button uk-button-primary yo-method-btn" type="button" disabled>Pay by card / Apple Pay / Google Pay</button>
                <button id="yo-pay-bank" class="uk-button uk-button-default yo-method-btn" type="button" disabled>Pay by bank transfer / SEPA / SWIFT invoice</button>
              </div>
            </div>
            <div id="yo-step-payment" class="yo-hidden"><button id="yo-back-to-method-from-payment" class="yo-back-btn" type="button" aria-label="Back to payment method">← Back to payment method</button><h3 class="uk-modal-title">Card payment</h3><iframe id="yo-payment-frame" title="monopay" src="" allow="payment *"></iframe></div>
            <div id="yo-step-bank" class="yo-hidden yo-bank-box"><button id="yo-back-to-method-from-bank" class="yo-back-btn" type="button" aria-label="Back to payment method">← Back to payment method</button><h3>Your invoice is ready</h3><p>Please download the invoice and make a bank transfer using the payment details inside.</p><p><a id="yo-bank-invoice-html-link" class="uk-button uk-button-primary" href="#" target="_blank" rel="noopener">View invoice</a> <a id="yo-bank-invoice-link" class="uk-button uk-button-default" href="#" target="_blank" rel="noopener">Download PDF copy</a></p><p>A copy of this invoice has also been sent to your email.</p></div>
            <div id="yo-step-success" class="yo-hidden yo-success"><h3>Payment successful!</h3><p>Thank you for your order.<br>Your order number is <strong id="yo-success-order-number"></strong>.</p><div class="yo-summary"><p><strong>Customer:</strong> <span id="yo-success-name"></span></p><p><strong>Email:</strong> <span id="yo-success-email"></span></p><p><strong>Phone:</strong> <span id="yo-success-phone"></span></p><p><strong>Product:</strong> <span id="yo-success-product"></span></p><p><strong>Amount paid:</strong> <span id="yo-success-amount"></span> €</p></div><p><strong>Our manager will contact you via WhatsApp and email.</strong></p></div>
          </div>
        </div>
    <?php }

    private function verify_nonce() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'yo_checkout_nonce')) wp_send_json_error(['message' => 'Security check failed']);
    }

    private function promo_is_configured() {
        $s = self::settings();
        return trim((string)($s['promo_code'] ?? '')) !== '';
    }

    private function promo_is_expired($s = null) {
        $s = $s ?: self::settings();
        $date = trim((string)($s['promo_expires_at'] ?? ''));
        if ($date === '') return false;
        $expires_ts = strtotime($date . ' 23:59:59');
        return $expires_ts && current_time('timestamp') > $expires_ts;
    }

    private function calculate_promo_discount($base_price) {
        $s = self::settings();
        if (!$this->promo_is_configured()) return new WP_Error('promo_empty', 'Promo code is not active');
        if ($this->promo_is_expired($s)) return new WP_Error('promo_expired', 'This promo code has expired');
        $value = floatval(str_replace(',', '.', (string)($s['promo_discount_value'] ?? 0)));
        if ($value <= 0) return new WP_Error('promo_value', 'Promo discount is not configured');
        $type = ($s['promo_discount_type'] ?? 'percent') === 'fixed' ? 'fixed' : 'percent';
        $base_price = round(floatval($base_price), 2);
        $discount = ($type === 'fixed') ? $value : ($base_price * $value / 100);
        $discount = round(min(max(0, $discount), max(0, $base_price - 1)), 2);
        if ($discount <= 0) return new WP_Error('promo_zero', 'Promo discount is not available for this amount');
        return ['discount'=>number_format($discount, 2, '.', ''), 'type'=>$type, 'value'=>number_format($value, 2, '.', '')];
    }


    private function reservation_option_key() {
        return 'yo_checkout_product_reservations';
    }

    private function reservation_minutes() {
        $s = self::settings();
        return max(1, min(1440, absint($s['reservation_minutes'] ?? 30)));
    }

    private function clean_reservations($reservations = null) {
        if (!is_array($reservations)) $reservations = get_option($this->reservation_option_key(), []);
        if (!is_array($reservations)) $reservations = [];
        $now = time();
        $clean = [];
        foreach ($reservations as $key => $row) {
            if (!is_array($row)) continue;
            $expires = absint($row['expires'] ?? 0);
            $title = sanitize_text_field($row['title'] ?? '');
            $buyer = sanitize_text_field($row['buyer_id'] ?? '');
            if (!$key || !$title || !$buyer || $expires <= $now) continue;
            $clean[$key] = ['title'=>$title, 'buyer_id'=>$buyer, 'expires'=>$expires];
        }
        update_option($this->reservation_option_key(), $clean, false);
        return $clean;
    }

    private function reservation_key_for_title($title) {
        return md5($this->normalize_match_text((string)$title));
    }

    private function reservation_allows_title($title, $buyer_id = '') {
        $title = sanitize_text_field((string)$title);
        if ($title === '') return false;
        $reservations = $this->clean_reservations();
        $key = $this->reservation_key_for_title($title);
        if (empty($reservations[$key])) return true;
        $buyer_id = sanitize_text_field((string)$buyer_id);
        return $buyer_id !== '' && hash_equals((string)$reservations[$key]['buyer_id'], $buyer_id);
    }

    private function ensure_reservation_for_title($title, $buyer_id) {
        $title = sanitize_text_field((string)$title);
        $buyer_id = sanitize_text_field((string)$buyer_id);
        if ($title === '' || $buyer_id === '') return false;
        $reservations = $this->clean_reservations();
        $key = $this->reservation_key_for_title($title);
        if (!empty($reservations[$key]) && !hash_equals((string)$reservations[$key]['buyer_id'], $buyer_id)) return false;
        $reservations[$key] = [
            'title' => $title,
            'buyer_id' => $buyer_id,
            'expires' => time() + ($this->reservation_minutes() * MINUTE_IN_SECONDS),
        ];
        update_option($this->reservation_option_key(), $reservations, false);
        return true;
    }

    public function ajax_get_reservations() {
        $this->verify_nonce();
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        $reservations = $this->clean_reservations();
        $items = [];
        foreach ($reservations as $key => $row) {
            $items[] = [
                'key' => $key,
                'title' => $row['title'],
                'mine' => ($buyer_id !== '' && hash_equals((string)$row['buyer_id'], $buyer_id)),
                'expires' => absint($row['expires']),
            ];
        }
        wp_send_json_success(['items'=>$items, 'now'=>time(), 'badgeText'=>sanitize_text_field(self::settings()['reservation_badge_text'] ?? 'Reserved')]);
    }

    public function ajax_reserve_item() {
        $this->verify_nonce();
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        if ($title === '' || $buyer_id === '') wp_send_json_error(['message'=>'Reservation data is missing.']);

        $s = self::settings();
        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        if (!$page_id || !$this->is_yootheme_product_title_available($page_id, $title)) {
            wp_send_json_error(['message'=>'This item is no longer available. Please choose another model.']);
        }

        $key = $this->reservation_key_for_title($title);
        if (!$this->ensure_reservation_for_title($title, $buyer_id)) {
            wp_send_json_error(['message'=>'This item is currently reserved by another customer. Please choose another model.']);
        }
        $reservations = $this->clean_reservations();
        $expires = absint($reservations[$key]['expires'] ?? (time() + ($this->reservation_minutes() * MINUTE_IN_SECONDS)));
        wp_send_json_success(['key'=>$key, 'title'=>$title, 'expires'=>$expires, 'badgeText'=>sanitize_text_field($s['reservation_badge_text'] ?? 'Reserved')]);
    }

    public function ajax_release_reservation() {
        $this->verify_nonce();
        $title = sanitize_text_field(wp_unslash($_POST['title'] ?? ''));
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        if ($title === '' || $buyer_id === '') wp_send_json_success(['released'=>false]);
        $reservations = $this->clean_reservations();
        $key = $this->reservation_key_for_title($title);
        if (!empty($reservations[$key]) && hash_equals((string)$reservations[$key]['buyer_id'], $buyer_id)) {
            unset($reservations[$key]);
            update_option($this->reservation_option_key(), $reservations, false);
            wp_send_json_success(['released'=>true, 'key'=>$key]);
        }
        wp_send_json_success(['released'=>false, 'key'=>$key]);
    }

    public function ajax_validate_cart_items() {
        $this->verify_nonce();
        $raw = wp_unslash($_POST['cart_items_json'] ?? '[]');
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) $decoded = [];
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));

        $items = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) continue;
            $title = sanitize_text_field($item['title'] ?? '');
            if ($title === '') continue;
            $items[] = [
                'title' => $title,
                'key' => $this->normalize_match_text($title),
                'available' => false,
            ];
        }

        if (!$items) {
            wp_send_json_success(['items' => [], 'removed' => []]);
        }

        $s = self::settings();
        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));

        $result = [];
        $removed = [];
        foreach ($items as $item) {
            $available = $page_id ? $this->is_yootheme_product_title_available($page_id, $item['title']) : false;
            // Cart validation must not create/extend reservations and must not treat
            // this customer's own active reservation as sold/unavailable. It only
            // checks whether another customer owns an active reservation.
            if ($available && !$this->reservation_allows_title($item['title'], $buyer_id)) $available = false;
            $row = [
                'title' => $item['title'],
                'key' => $item['key'],
                'available' => (bool)$available,
            ];
            $result[] = $row;
            if (!$available) $removed[] = $row;
        }

        wp_send_json_success([
            'items' => $result,
            'removed' => $removed,
        ]);
    }

    public function ajax_apply_promo_code() {
        $this->verify_nonce();
        $local_id = absint($_POST['local_id'] ?? 0);
        $s = self::settings();
        if (!$this->promo_is_configured()) wp_send_json_error(['message'=>'Promo code is not active']);
        $code = sanitize_text_field($_POST['promo_code'] ?? '');
        if ($code === '' || strcasecmp($code, trim((string)$s['promo_code'])) !== 0) wp_send_json_error(['message'=>'Invalid promo code']);
        if ($this->promo_is_expired($s)) wp_send_json_error(['message'=>'This promo code has expired']);

        if ($local_id) {
            if (get_post_type($local_id) !== self::CPT) wp_send_json_error(['message'=>'Order not found']);
            if (get_post_meta($local_id, 'keycrm_created', true) === '1' || get_post_meta($local_id, 'paid', true) === '1') wp_send_json_error(['message'=>'Promo code cannot be changed after payment method is selected']);
            $d = $this->get_order_data($local_id);
            if (floatval($d['discount_eur']) > 0) wp_send_json_error(['message'=>'This product already has an active discount']);
            $original = floatval($d['original_price_eur'] ?: $d['price_eur']);
        } else {
            $original = floatval($_POST['base_price'] ?? 0);
            if ($original <= 0) wp_send_json_error(['message'=>'Order amount is missing']);
        }

        $calc = $this->calculate_promo_discount($original);
        if (is_wp_error($calc)) wp_send_json_error(['message'=>$calc->get_error_message()]);
        $discount = floatval($calc['discount']);
        $new_price = round(max(1, $original - $discount), 2);

        $response = ['price_eur'=>number_format($new_price,2,'.',''), 'discount_eur'=>number_format($discount,2,'.',''), 'original_price_eur'=>number_format($original,2,'.',''), 'message'=>'Promo code applied'];

        if ($local_id) {
            update_post_meta($local_id, 'price_eur', number_format($new_price, 2, '.', ''));
            update_post_meta($local_id, 'discount_eur', number_format($discount, 2, '.', ''));
            update_post_meta($local_id, 'promo_code_applied', trim((string)$s['promo_code']));
            update_post_meta($local_id, 'promo_discount_type', $calc['type']);
            update_post_meta($local_id, 'promo_discount_value', $calc['value']);
            foreach (['shipping_price','shipping_country','shipping_source','card_fee_percent','card_fee_amount','card_total_amount','bank_total_amount','payment_provider','payment_type'] as $meta_key) delete_post_meta($local_id, $meta_key);
            $provider_preview = $this->card_provider_for_order($local_id);
            $shipping_preview = $this->shipping_data($local_id);
            $fee_preview = $this->card_fee_data($local_id, $provider_preview);
            $response['cardProvider'] = $provider_preview;
            $response['cardFee'] = $fee_preview;
            $response['shipping'] = $shipping_preview;
            $response['bankTotal'] = $this->bank_total_data($local_id);
        }
        wp_send_json_success($response);
    }

    public function ajax_create_order() {
        $this->verify_nonce();
        $s = self::settings();
        $data = $this->sanitize_order_input();
        if (is_wp_error($data)) wp_send_json_error(['message' => $data->get_error_message()]);
        // This is the browser reservation/customer marker from localStorage, not the KeyCRM buyer ID.
        // Store it separately so we can find the same unpaid checkout draft even after the customer
        // returns from the card payment screen, refreshes the page, or the frontend loses local_id.
        $browser_buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        $buyer_id = $browser_buyer_id;
        $checkout_session_id = sanitize_text_field(wp_unslash($_POST['checkout_session_id'] ?? ''));
        $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string) wp_unslash($_POST['keycrm_order_id'] ?? ''));
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->posted_cart_marker_keycrm_order_id();
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();

        $posted_cart_marker_local_id = absint($_POST['cart_marker_local_id'] ?? 0);
        if (!$posted_cart_marker_local_id) {
            $raw_marker = (string) wp_unslash($_POST['cart_marker_json'] ?? '');
            if ($raw_marker !== '') {
                $decoded_marker = json_decode($raw_marker, true);
                if (is_array($decoded_marker) && !empty($decoded_marker['local_id'])) {
                    $posted_cart_marker_local_id = absint($decoded_marker['local_id']);
                }
            }
        }

        $absolute_marker_reuse_local_id = $this->hard_find_existing_keycrm_checkout_by_marker($data, $checkout_session_id, $browser_buyer_id, $posted_keycrm_order_id, 0);

        // Strong KeyCRM cart marker recovery:
        // If this browser/customer/email already has an unpaid website draft linked to a KeyCRM order,
        // reuse that draft immediately and overwrite its cart below. This prevents duplicate KeyCRM
        // orders when the customer returns from the payment step, adds/removes models, and goes to
        // payment again.
        $existing_keycrm_checkout_local_id = $absolute_marker_reuse_local_id ?: $this->find_latest_unpaid_keycrm_checkout_for_customer($data, $checkout_session_id, $browser_buyer_id, $posted_keycrm_order_id);
        // v3.3.65: hard fallback. If this customer/browser already has an unpaid KeyCRM order,
        // reuse that local draft before any new local/KeyCRM order can be created.
        $hard_reuse_local_id = $this->find_hard_reusable_keycrm_local_id($data, $checkout_session_id, $browser_buyer_id, $posted_keycrm_order_id);
        if ($hard_reuse_local_id) $existing_keycrm_checkout_local_id = $hard_reuse_local_id;
        // Last-resort server memory: if Step 3 already created an unpaid KeyCRM order for this
        // same customer in the last 48h, reuse that local draft even when the browser did not send
        // the marker from localStorage. This prevents duplicate KeyCRM orders after cart edits.
        $contact_reuse_local_id = $this->find_latest_unpaid_keycrm_order_by_contact($data, 0);
        if ($contact_reuse_local_id) $existing_keycrm_checkout_local_id = $contact_reuse_local_id;

        // Prevent checkout with stale localStorage cart items. If a model was disabled
        // in YOOtheme after the customer added it to the cart, stop checkout and force
        // the frontend cart to refresh/remove it.
        $cart_items_to_check = [];
        if (!empty($data['cart_items_json'])) {
            $decoded_cart = json_decode((string)$data['cart_items_json'], true);
            if (is_array($decoded_cart)) $cart_items_to_check = $decoded_cart;
        } elseif (!empty($data['title'])) {
            $cart_items_to_check = [['title' => $data['title']]];
        }
        if ($cart_items_to_check) {
            $page_id_for_check = absint($s['auto_hide_sold_page_id'] ?? 0);
            if (!$page_id_for_check) $page_id_for_check = absint(get_option('page_on_front'));
            foreach ($cart_items_to_check as $check_item) {
                $check_title = is_array($check_item) ? sanitize_text_field($check_item['title'] ?? '') : '';
                if ($check_title && $page_id_for_check && (!$this->is_yootheme_product_title_available($page_id_for_check, $check_title) || !$this->ensure_reservation_for_title($check_title, $buyer_id))) {
                    wp_send_json_error([
                        'message' => 'One or more items in your cart are no longer available. Please refresh the cart and choose another model.',
                        'unavailable_title' => $check_title,
                    ]);
                }
            }
        }

        // Step 1 stores/updates a local draft only. KeyCRM buyer/order is created after the customer
        // chooses a payment method on Step 2, so card provider fees and shipping can be sent correctly.
        // If the customer goes back from Step 2 and edits the form, update the same unpaid draft instead
        // of creating duplicate checkout drafts.
        $incoming_local_id = absint($_POST['local_id'] ?? 0);
        if ($existing_keycrm_checkout_local_id) {
            $incoming_local_id = $existing_keycrm_checkout_local_id;
        }
        // Strongest cart marker rule: if the browser/cart marker knows the local draft already
        // linked to KeyCRM, always reuse it and overwrite its cart contents below.
        if (!$incoming_local_id && $posted_cart_marker_local_id && get_post_type($posted_cart_marker_local_id) === self::CPT && get_post_meta($posted_cart_marker_local_id, 'paid', true) !== '1') {
            $incoming_local_id = $posted_cart_marker_local_id;
        }
        if (!$incoming_local_id && $posted_keycrm_order_id !== '') {
            $by_keycrm = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
            if ($by_keycrm && get_post_meta($by_keycrm, 'paid', true) !== '1') $incoming_local_id = $by_keycrm;
        }
        if (!$incoming_local_id) {
            $existing = [];
            if ($checkout_session_id !== '') {
                $existing = get_posts([
                    'post_type' => self::CPT,
                    'post_status' => 'publish',
                    'numberposts' => 1,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_key' => 'checkout_session_id',
                    'meta_value' => $checkout_session_id,
                    'fields' => 'ids',
                    'date_query' => [['after' => '2 days ago']],
                ]);
            }
            // Fallback: if the checkout session/local draft was lost after returning from the payment
            // provider, find the latest unpaid draft by the stable browser buyer marker.
            if (empty($existing) && $browser_buyer_id !== '') {
                $existing = get_posts([
                    'post_type' => self::CPT,
                    'post_status' => 'publish',
                    'numberposts' => 5,
                    'orderby' => 'date',
                    'order' => 'DESC',
                    'meta_key' => 'browser_buyer_id',
                    'meta_value' => $browser_buyer_id,
                    'fields' => 'ids',
                    'date_query' => [['after' => '2 days ago']],
                ]);
            }
            foreach ((array)$existing as $maybe_id) {
                $maybe_id = absint($maybe_id);
                if ($maybe_id && get_post_meta($maybe_id, 'paid', true) !== '1') {
                    $incoming_local_id = $maybe_id;
                    break;
                }
            }
        }
        // Server-side persistent marker lookup: if this customer/browser/email already has an unpaid
        // KeyCRM order from this checkout flow, always reuse that local draft and update KeyCRM.
        $active_marker_local_id = $this->find_local_order_by_active_marker($data, $checkout_session_id, $browser_buyer_id, $posted_keycrm_order_id);
        if ($active_marker_local_id) {
            $incoming_local_id = $active_marker_local_id;
        }

        // Extra-safe fallback: for the same customer in the same short checkout window, reuse
        // the latest unpaid website KeyCRM order and overwrite it with the edited cart.
        $broad_contact_local_id = $this->find_recent_unpaid_keycrm_order_for_customer_broad($data, $incoming_local_id);
        if ($broad_contact_local_id) {
            $incoming_local_id = $broad_contact_local_id;
        }

        // If the browser already knows the KeyCRM order ID from a previous Step 3 attempt,
        // bind the current edited cart back to that same local draft/order first.
        if ($posted_keycrm_order_id !== '') {
            $existing_by_keycrm_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
            if ($existing_by_keycrm_order && get_post_meta($existing_by_keycrm_order, 'paid', true) !== '1') {
                $incoming_local_id = $existing_by_keycrm_order;
            }
        }

        // If a KeyCRM order was already created in this checkout/browser/customer flow, reuse that
        // local draft even if the frontend accidentally sends a newer local draft without order_id.
        // This is the critical cart marker -> KeyCRM order link: edit the existing KeyCRM order,
        // do not create a duplicate when the customer returns and changes the cart.
        $reusable_keycrm_local_id = $this->find_reusable_unpaid_keycrm_local_id($data, $checkout_session_id, $browser_buyer_id, $incoming_local_id, $posted_keycrm_order_id);
        if ($reusable_keycrm_local_id) {
            $incoming_local_id = $reusable_keycrm_local_id;
        }

        if ($absolute_marker_reuse_local_id && get_post_type($absolute_marker_reuse_local_id) === self::CPT && get_post_meta($absolute_marker_reuse_local_id, 'paid', true) !== '1') {
            $incoming_local_id = $absolute_marker_reuse_local_id;
        }
        $local_id = 0;
        if ($incoming_local_id && get_post_type($incoming_local_id) === self::CPT && get_post_meta($incoming_local_id, 'paid', true) !== '1') {
            // Keep the same local checkout draft for the whole browser session, even if a KeyCRM
            // order has already been created. This prevents duplicate KeyCRM orders when the
            // customer goes back, changes the cart, and starts payment again.
            $local_id = $incoming_local_id;
            $existing_keycrm_order = get_post_meta($local_id, 'order_id', true);
            wp_update_post(['ID'=>$local_id, 'post_title'=>($existing_keycrm_order ? ('Order #' . $existing_keycrm_order . ' - ') : 'YOleotard checkout draft - ') . $data['full_name']]);
        } else {
            $local_id = wp_insert_post(['post_type'=>self::CPT,'post_status'=>'publish','post_title'=>'YOleotard checkout draft - ' . $data['full_name']]);
            if (is_wp_error($local_id) || !$local_id) wp_send_json_error(['message'=>'Local checkout draft was not created']);
            update_post_meta($local_id, 'paid', '0');
            update_post_meta($local_id, 'keycrm_created', '0');
        }
        foreach ($data as $k=>$v) update_post_meta($local_id, $k, $v);
        if ($checkout_session_id !== '') update_post_meta($local_id, 'checkout_session_id', $checkout_session_id);
        if ($browser_buyer_id !== '') update_post_meta($local_id, 'browser_buyer_id', $browser_buyer_id);
        if ($posted_keycrm_order_id !== '' && trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
            update_post_meta($local_id, 'order_id', $posted_keycrm_order_id);
            update_post_meta($local_id, 'keycrm_created', '1');
        }
        $this->remember_keycrm_checkout_marker($local_id);
        // Clear payment preview meta that depends on country/price/payment route; it will be recalculated below.
        foreach (['shipping_price','shipping_country','shipping_source','card_fee_percent','card_fee_amount','card_total_amount','bank_total_amount','payment_provider','payment_type'] as $meta_key) {
            delete_post_meta($local_id, $meta_key);
        }

        $provider_preview = $this->card_provider_for_order($local_id);
        $shipping_preview = $this->shipping_data($local_id);
        $fee_preview = $this->card_fee_data($local_id, $provider_preview);
        wp_send_json_success(['localId'=>$local_id,'orderId'=>get_post_meta($local_id,'order_id',true),'buyerId'=>get_post_meta($local_id,'buyer_id',true),'cartMarker'=>['keycrm_order_id'=>get_post_meta($local_id,'order_id',true),'local_id'=>$local_id],'cardProvider'=>$provider_preview,'cardFee'=>$fee_preview,'shipping'=>$shipping_preview,'shippingOptions'=>$this->shipping_options_for_order($local_id),'bankTotal'=>$this->bank_total_data($local_id)]);
    }


    public function ajax_update_shipping_option() {
        $this->verify_nonce();
        $local_id = absint($_POST['local_id'] ?? 0);
        if (!$local_id || get_post_type($local_id) !== self::CPT) wp_send_json_error(['message'=>'Order not found']);
        $shipping_key = sanitize_text_field(wp_unslash($_POST['shipping_key'] ?? ''));
        $options = $this->shipping_options_for_order($local_id);
        $found = null;
        foreach ($options as $opt) {
            if (!empty($opt['key']) && hash_equals((string)$opt['key'], $shipping_key)) { $found = $opt; break; }
        }
        if (!$found) wp_send_json_error(['message'=>'Shipping option was not found']);
        update_post_meta($local_id, 'shipping_selected_key', $found['key']);
        update_post_meta($local_id, 'shipping_selected_label', $found['label'] ?? '');
        update_post_meta($local_id, 'shipping_selected_service', $found['service'] ?? '');
        update_post_meta($local_id, 'shipping_selected_method', $found['method'] ?? '');
        update_post_meta($local_id, 'shipping_delivery_days', $found['delivery_days'] ?? '');
        $provider = $this->card_provider_for_order($local_id);
        $shipping = $this->shipping_data($local_id);
        $fee = $this->card_fee_data($local_id, $provider);
        wp_send_json_success(['shipping'=>$shipping, 'shippingOptions'=>$options, 'cardFee'=>$fee, 'bankTotal'=>$this->bank_total_data($local_id)]);
    }

    private function order_items_are_payable_for_buyer($local_id, $buyer_id) {
        $d = $this->get_order_data($local_id);
        $items = $this->cart_items_from_order_data($d);
        if (!$items) $items = [['title' => $d['title'] ?? '']];
        $settings = self::settings();
        $page_id = absint($settings['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        foreach ($items as $item) {
            $title = sanitize_text_field($item['title'] ?? '');
            if ($title === '') continue;
            if (!$page_id || !$this->is_yootheme_product_title_available($page_id, $title) || !$this->ensure_reservation_for_title($title, $buyer_id)) {
                return new WP_Error('item_unavailable', 'One or more items in your cart are reserved, sold, or no longer available. Please refresh the cart and choose another model.', ['title'=>$title]);
            }
        }
        return true;
    }

    public function ajax_start_card_payment() {
        $this->verify_nonce();
        $local_id = absint($_POST['local_id'] ?? 0);
        if (!$local_id || get_post_type($local_id) !== self::CPT) wp_send_json_error(['message'=>'Order not found']);
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        $payable = $this->order_items_are_payable_for_buyer($local_id, $buyer_id);
        if (is_wp_error($payable)) wp_send_json_error(['message'=>$payable->get_error_message(), 'details'=>$payable->get_error_data()]);

        // Recalculate and persist Nova Post shipping exactly when the customer selects card payment.
        // The card service fee is then calculated from product + delivery.
        $this->shipping_data($local_id);
        $provider = $this->card_provider_for_order($local_id);
        update_post_meta($local_id, 'payment_provider', $provider);
        update_post_meta($local_id, 'payment_type', 'card');
        $fee = $this->card_fee_data($local_id, $provider);
        update_post_meta($local_id, 'card_fee_percent', $fee['percent']);
        update_post_meta($local_id, 'card_fee_amount', $fee['fee']);
        update_post_meta($local_id, 'card_total_amount', $fee['total']);
        // Card payments must not create a KeyCRM order before the payment is actually successful.
        // A local checkout draft is enough for Monobank/WayForPay. The real KeyCRM buyer/order
        // is created later in process_successful_card_payment().
        if ($provider === 'wayforpay') {
            $this->start_wayforpay_payment($local_id);
            return;
        }
        $this->start_monobank_payment($local_id);
    }

    private function start_monobank_payment($local_id) {
        $s = self::settings();
        if (empty($s['mono_token'])) wp_send_json_error(['message'=>'Monobank token is empty']);
        $data = $this->get_order_data($local_id);
        $fee = $this->card_fee_data($local_id, 'monobank');
        $amount_cents = intval(round(floatval($fee['total']) * 100));
        update_post_meta($local_id, 'card_fee_percent', $fee['percent']);
        update_post_meta($local_id, 'card_fee_amount', $fee['fee']);
        update_post_meta($local_id, 'card_total_amount', $fee['total']);
        $checkout_ref = $data['order_id'] ?: ('WEB-' . $local_id);
        $destination = 'Payment for ' . $data['title'] . ' by checkout № ' . $checkout_ref;
        $payload = [
            'amount' => $amount_cents,
            'ccy' => 978,
            'displayType' => 'iframe',
            'redirectUrl' => home_url('/confirm?order_id=' . urlencode($data['order_id'])),
            'webHookUrl' => rest_url(self::NS . '/mono-webhook'),
            'paymentType' => 'debit',
            'merchantPaymInfo' => [
                'reference' => 'website_checkout_' . $local_id,
                'destination' => $destination,
                'comment' => $destination,
                'basketOrder' => [[
                    'name' => $data['title'], 'qty' => 1, 'sum' => $amount_cents, 'total' => $amount_cents, 'unit' => 'pcs', 'code' => 'YO-WEB-' . $local_id
                ]]
            ]
        ];
        $resp = wp_remote_post('https://api.monobank.ua/api/merchant/invoice/create', [
            'headers'=>['X-Token'=>$s['mono_token'],'Content-Type'=>'application/json'],
            'body'=>wp_json_encode($payload),'timeout'=>30
        ]);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['pageUrl'])) wp_send_json_error(['message'=>'Monobank invoice was not created','details'=>$body]);
        update_post_meta($local_id, 'mono_invoice_id', sanitize_text_field($body['invoiceId']));
        update_post_meta($local_id, 'payment_provider', 'monobank');
        update_post_meta($local_id, 'payment_type', 'card');
        update_option('yo_mono_invoice_' . $body['invoiceId'], $local_id, false);
        wp_schedule_single_event(time() + 120*60, 'yo_checkout_check_unpaid_order', [$local_id]);
        wp_send_json_success(['provider'=>'monobank','invoiceId'=>$body['invoiceId'], 'pageUrl'=>$body['pageUrl'], 'orderId'=>$checkout_ref, 'cartMarker'=>['local_id'=>$local_id]]);
    }

    private function start_wayforpay_payment($local_id) {
        $s = self::settings();
        $wfp_creds = $this->wayforpay_credentials();
        if (empty($wfp_creds['merchantAccount']) || empty($wfp_creds['secretKey'])) wp_send_json_error(['message'=>'WayForPay merchant login or secret key is empty']);
        $d = $this->get_order_data($local_id);
        $order_reference = 'YO-WEB-' . $local_id . '-' . time();
        update_post_meta($local_id, 'wayforpay_order_reference', $order_reference);
        update_post_meta($local_id, 'payment_provider', 'wayforpay');
        update_post_meta($local_id, 'payment_type', 'card');
        update_option('yo_wayforpay_order_' . $order_reference, $local_id, false);
        wp_schedule_single_event(time() + 120*60, 'yo_checkout_check_unpaid_order', [$local_id]);
        $page_url = add_query_arg([
            'action' => 'yo_checkout_wayforpay_form',
            'order_reference' => rawurlencode($order_reference),
        ], admin_url('admin-ajax.php'));
        // Do not use the REST endpoint for the HTML form here: WordPress REST encodes string responses as JSON,
        // which makes the iframe show only a quote/blank page instead of the auto-submit WayForPay form.
        wp_send_json_success(['provider'=>'wayforpay','invoiceId'=>$order_reference,'pageUrl'=>$page_url,'orderId'=>('WEB-' . $local_id), 'cartMarker'=>['local_id'=>$local_id]]);
    }

    public function ajax_create_bank_invoice() {
        $this->verify_nonce();
        $s = self::settings();
        $local_id = absint($_POST['local_id'] ?? 0);
        if (!$local_id || get_post_type($local_id) !== self::CPT) wp_send_json_error(['message'=>'Order not found']);
        $buyer_id = sanitize_text_field(wp_unslash($_POST['buyer_id'] ?? ''));
        $payable = $this->order_items_are_payable_for_buyer($local_id, $buyer_id);
        if (is_wp_error($payable)) wp_send_json_error(['message'=>$payable->get_error_message(), 'details'=>$payable->get_error_data()]);
        update_post_meta($local_id, 'payment_type', 'bank');
        update_post_meta($local_id, 'payment_provider', 'bank');

        // Recalculate and persist Nova Post shipping exactly when the customer selects SEPA/SWIFT invoice.
        // Bank invoice total must include product + delivery, but must NOT include card provider service fee.
        $bank_total = $this->bank_total_data($local_id);
        update_post_meta($local_id, 'bank_total_amount', $bank_total['total']);
        update_post_meta($local_id, 'card_fee_percent', '0.00');
        update_post_meta($local_id, 'card_fee_amount', '0.00');
        update_post_meta($local_id, 'card_total_amount', $bank_total['total']);

        $created = $this->ensure_keycrm_order($local_id, 'bank');
        if (is_wp_error($created)) wp_send_json_error(['message'=>'KeyCRM order was not created','details'=>$created->get_error_data() ?: $created->get_error_message()]);
        $order_data_for_bank = $this->get_order_data($local_id);
        $bank_for_order = $this->bank_details_for_country($order_data_for_bank['country']);
        update_post_meta($local_id, 'bank_invoice_type', $bank_for_order['type']);
        $this->keycrm_update_order_comment($local_id, 'Order from YOleotard website - Invoice ' . $bank_for_order['type']);
        $files = $this->generate_invoice_files($local_id);
        if (is_wp_error($files)) wp_send_json_error(['message'=>$files->get_error_message()]);
        update_post_meta($local_id, 'invoice_pdf_url', $files['pdf_url']);
        update_post_meta($local_id, 'invoice_pdf_path', $files['pdf_path']);
        update_post_meta($local_id, 'invoice_html_url', $files['html_url']);
        if (!empty($s['keycrm_payment_method_bank'])) $this->keycrm_add_payment($local_id, 'not_paid', 'Bank transfer invoice created');
        $this->send_bank_invoice_email($local_id, !empty($files['pdf_path']) ? $files['pdf_path'] : $files['html_path']);
        wp_send_json_success(['invoiceUrl'=>$files['pdf_url'], 'htmlUrl'=>$files['html_url'], 'pdfMessage'=>$files['pdf_message'] ?? '', 'bankType'=>$bank_for_order['type'], 'orderId'=>get_post_meta($local_id,'order_id',true), 'cartMarker'=>['keycrm_order_id'=>get_post_meta($local_id,'order_id',true),'local_id'=>$local_id]]);
    }


    public function ajax_final_order_status() {
        $this->verify_nonce();
        $local_id = absint($_POST['local_id'] ?? 0);
        $invoice_id = sanitize_text_field(wp_unslash($_POST['invoice_id'] ?? ''));
        if (!$local_id && $invoice_id !== '') {
            $local_id = absint(get_option('yo_mono_invoice_' . $invoice_id));
            if (!$local_id) $local_id = absint(get_option('yo_wayforpay_order_' . $invoice_id));
        }
        if (!$local_id || get_post_type($local_id) !== self::CPT) {
            wp_send_json_error(['message'=>'Local order not found','localId'=>$local_id,'invoiceId'=>$invoice_id]);
        }

        // Make sure background finalizer is queued. This endpoint is lightweight and safe to call repeatedly.
        if (get_post_meta($local_id, 'paid', true) === '1') {
            $this->queue_deferred_payment_finalizer($local_id, $invoice_id, 'final_order_status_poll');
        }

        $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($local_id, 'order_id', true));
        $keycrm_done = get_post_meta($local_id, 'keycrm_after_payment_done', true) === '1';
        $email_sent = get_post_meta($local_id, 'paid_email_sent', true) === '1';
        $auto_hide_done = get_post_meta($local_id, 'auto_hide_sold_done', true) === '1';
        $d = $this->get_order_data($local_id);
        wp_send_json_success([
            'localId' => $local_id,
            'paid' => get_post_meta($local_id, 'paid', true) === '1',
            'orderId' => $order_id,
            'keycrmDone' => $keycrm_done,
            'emailSent' => $email_sent,
            'autoHideDone' => $auto_hide_done,
            'ready' => ($order_id !== '' && $keycrm_done),
            'amount' => isset($d['card_total_amount']) ? $d['card_total_amount'] : '',
        ]);
    }

    public function ajax_check_payment_status() {
        $this->verify_nonce();
        $s = self::settings();
        $invoice_id = sanitize_text_field(wp_unslash($_POST['invoice_id'] ?? ''));
        $posted_local_id = absint($_POST['local_id'] ?? 0);

        $local_id = 0;
        $provider = 'monobank';

        if ($invoice_id !== '') {
            $local_id = absint(get_option('yo_mono_invoice_' . $invoice_id));
            if ($local_id) {
                $provider = 'monobank';
            } else {
                $local_id = absint(get_option('yo_wayforpay_order_' . $invoice_id));
                if ($local_id) $provider = 'wayforpay';
            }
            if (!$local_id) {
                $q = get_posts([
                    'post_type'      => self::CPT,
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'meta_query'     => [
                        'relation' => 'OR',
                        ['key' => 'mono_invoice_id', 'value' => $invoice_id],
                        ['key' => 'wayforpay_order_reference', 'value' => $invoice_id],
                    ],
                ]);
                if (!empty($q[0])) {
                    $local_id = absint($q[0]);
                    $provider = get_post_meta($local_id, 'payment_provider', true) ?: (get_post_meta($local_id, 'wayforpay_order_reference', true) === $invoice_id ? 'wayforpay' : 'monobank');
                    if ($provider === 'monobank') update_option('yo_mono_invoice_' . $invoice_id, $local_id, false);
                    if ($provider === 'wayforpay') update_option('yo_wayforpay_order_' . $invoice_id, $local_id, false);
                }
            }
        }

        if (!$local_id && $posted_local_id && get_post_type($posted_local_id) === self::CPT) {
            $local_id = $posted_local_id;
        }
        if (!$local_id) {
            wp_send_json_error(['message'=>'Invoice/local order mapping not found','invoice_id'=>$invoice_id,'local_id'=>$posted_local_id]);
        }

        $provider = get_post_meta($local_id, 'payment_provider', true) ?: $provider;
        if ($invoice_id === '') {
            $invoice_id = get_post_meta($local_id, 'mono_invoice_id', true) ?: get_post_meta($local_id, 'wayforpay_order_reference', true);
        }

        // v4.0.18: Some providers/webhooks can complete the post-payment actions before the browser polling
        // receives the paid flag. If email/KeyCRM markers are already present, normalize the local order as paid
        // and let the frontend continue to Step 4 instead of staying on the payment iframe.
        if (get_post_meta($local_id, 'paid', true) !== '1') {
            $has_paid_side_effects = (get_post_meta($local_id, 'paid_email_sent', true) === '1') || (get_post_meta($local_id, 'keycrm_after_payment_done', true) === '1');
            if ($has_paid_side_effects) {
                update_post_meta($local_id, 'paid', '1');
                if (!get_post_meta($local_id, 'paid_at', true)) update_post_meta($local_id, 'paid_at', time());
                $this->append_auto_hide_log('[' . current_time('mysql') . '] Payment poll normalized paid status for local order #' . $local_id . ' from side-effect markers. Reason: ' . sanitize_text_field($_POST['poll_reason'] ?? '') . ".
");
            }
        }

        if (get_post_meta($local_id, 'paid', true) === '1') {
            $this->queue_deferred_payment_finalizer($local_id, $invoice_id, 'ajax_already_paid_' . sanitize_text_field($_POST['poll_reason'] ?? ''));
            wp_send_json_success(['paid'=>true,'status'=>'success','provider'=>$provider,'localId'=>$local_id,'orderId'=>get_post_meta($local_id,'order_id',true)]);
        }

        if ($provider === 'wayforpay') {
            if ($this->wayforpay_should_simulate_test_success()) {
                update_post_meta($local_id, 'wayforpay_test_simulated_success', '1');
                $this->process_successful_card_payment($local_id, $invoice_id ?: ('WEB-' . $local_id));
                wp_send_json_success(['paid'=>true,'status'=>'Approved','provider'=>'wayforpay','simulated'=>true,'localId'=>$local_id,'orderId'=>get_post_meta($local_id,'order_id',true)]);
            }
            $wfp_status = $invoice_id ? $this->check_wayforpay_status($invoice_id) : null;
            if (is_array($wfp_status)) {
                if (($wfp_status['transactionStatus'] ?? '') === 'Approved') {
                    if (get_post_meta($local_id, 'paid', true) !== '1') {
                        update_post_meta($local_id, 'paid', '1');
                        update_post_meta($local_id, 'paid_at', time());
                    }
                    $this->queue_deferred_payment_finalizer($local_id, $invoice_id, 'ajax_wayforpay_approved_nonblocking');
                    wp_send_json_success(['paid'=>true,'status'=>'Approved','provider'=>'wayforpay','localId'=>$local_id,'orderId'=>get_post_meta($local_id,'order_id',true),'finalizer'=>'queued']);
                }
                wp_send_json_success(['paid'=>false,'status'=>($wfp_status['transactionStatus'] ?? 'waiting'),'reason'=>($wfp_status['reason'] ?? ''),'reasonCode'=>($wfp_status['reasonCode'] ?? ''),'provider'=>'wayforpay','localId'=>$local_id]);
            }
            wp_send_json_success(['paid'=>false,'status'=>'waiting','provider'=>'wayforpay','localId'=>$local_id]);
        }

        if ($invoice_id === '') {
            wp_send_json_success(['paid'=>false,'status'=>'waiting_local_only','provider'=>$provider,'localId'=>$local_id]);
        }
        $resp = wp_remote_get('https://api.monobank.ua/api/merchant/invoice/status?invoiceId=' . urlencode($invoice_id), [
            'headers'=>['X-Token'=>$s['mono_token'],'Accept'=>'application/json'], 'timeout'=>30
        ]);
        if (is_wp_error($resp)) {
            wp_send_json_success(['paid'=>false,'status'=>'mono_status_error','provider'=>'monobank','localId'=>$local_id,'error'=>$resp->get_error_message()]);
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $status = $body['status'] ?? '';
        if ($status === 'success') {
            // v4.0.21: do not run KeyCRM/email/YOOtheme autohide inside the polling AJAX response.
            // On this hosting it can trigger a WP critical error after the payment is already successful,
            // preventing the browser from receiving paid:true and moving to Step 4. Mark as paid,
            // return immediately, and run the heavy finalizer through webhook/cron/background.
            if (get_post_meta($local_id, 'paid', true) !== '1') {
                update_post_meta($local_id, 'paid', '1');
                update_post_meta($local_id, 'paid_at', time());
            }
            if ($invoice_id !== '') update_post_meta($local_id, 'mono_invoice_id', $invoice_id);
            $this->queue_deferred_payment_finalizer($local_id, $invoice_id, 'ajax_mono_success_nonblocking');
            wp_send_json_success(['paid'=>true,'status'=>'success','provider'=>'monobank','localId'=>$local_id,'orderId'=>get_post_meta($local_id,'order_id',true),'finalizer'=>'queued']);
        }
        wp_send_json_success(['paid'=>false,'status'=>$status ?: 'waiting','provider'=>'monobank','localId'=>$local_id]);
    }

    public function rest_routes() {
        register_rest_route(self::NS, '/mono-webhook', ['methods'=>'POST','callback'=>[$this,'mono_webhook'],'permission_callback'=>'__return_true']);
        register_rest_route(self::NS, '/wayforpay-form', ['methods'=>'GET','callback'=>[$this,'wayforpay_form'],'permission_callback'=>'__return_true']);
        register_rest_route(self::NS, '/wayforpay-webhook', ['methods'=>'POST','callback'=>[$this,'wayforpay_webhook'],'permission_callback'=>'__return_true']);
    }
    public function mono_webhook(WP_REST_Request $req) {
        $data = $req->get_json_params();
        $invoice_id = sanitize_text_field($data['invoiceId'] ?? '');
        $status = sanitize_text_field($data['status'] ?? '');
        if (!$invoice_id) return new WP_REST_Response(['ok'=>false], 400);
        $local_id = get_option('yo_mono_invoice_' . $invoice_id);
        if (!$local_id) return new WP_REST_Response(['ok'=>false], 404);
        if (in_array($status, ['success','paid'], true)) {
            if (get_post_meta($local_id, 'paid', true) !== '1') {
                update_post_meta($local_id, 'paid', '1');
                update_post_meta($local_id, 'paid_at', time());
            }
            try {
                // Always call the idempotent finalizer. If already paid, it will only run missing autohide.
                $this->process_successful_card_payment($local_id, $invoice_id);
            } catch (Throwable $e) {
                update_post_meta($local_id, 'mono_webhook_finalizer_error', $e->getMessage());
                $this->queue_deferred_payment_finalizer($local_id, $invoice_id, 'mono_webhook_error_retry');
            }
        }
        return new WP_REST_Response(['ok'=>true], 200);
    }



    public function ajax_wayforpay_return() {
        $order_reference = sanitize_text_field(wp_unslash($_REQUEST['orderReference'] ?? $_REQUEST['order_reference'] ?? ''));
        $local_id = $order_reference ? get_option('yo_wayforpay_order_' . $order_reference) : 0;
        $status = sanitize_text_field(wp_unslash($_REQUEST['transactionStatus'] ?? ''));
        $message = 'Your payment is being verified. Please wait...';
        $paid = false;

        if ($order_reference && $local_id && get_post_type($local_id) === self::CPT) {
            $data = [];
            foreach ($_REQUEST as $key => $value) {
                $data[$key] = is_array($value) ? array_map('sanitize_text_field', wp_unslash($value)) : sanitize_text_field(wp_unslash($value));
            }
            if (($status === 'Approved' && $this->verify_wayforpay_callback($data) && get_post_meta($local_id, 'paid', true) !== '1') || $this->wayforpay_should_simulate_test_success()) {
                if (get_post_meta($local_id, 'paid', true) !== '1') {
                    update_post_meta($local_id, 'wayforpay_test_simulated_success', '1');
                    $this->process_successful_card_payment($local_id, $order_reference);
                }
                $paid = true;
                $message = 'Payment successful. Returning to order confirmation...';
            } elseif (get_post_meta($local_id, 'paid', true) === '1') {
                $paid = true;
                $message = 'Payment successful. Returning to order confirmation...';
            } elseif ($status && $status !== 'Approved') {
                $message = 'Payment status: ' . esc_html($status) . '. Please wait or try another payment method.';
            }
        } else {
            $message = 'Payment is being verified. Please wait...';
        }

        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        ?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment verification</title>
<style>body{font-family:Arial,sans-serif;margin:0;background:#fff;color:#07194b;display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}.box{padding:28px;max-width:520px}.spinner{width:34px;height:34px;border:4px solid #e5eefb;border-top-color:#1e87f0;border-radius:50%;animation:spin 1s linear infinite;margin:0 auto 18px}@keyframes spin{to{transform:rotate(360deg)}}p{font-size:16px;line-height:1.5}</style></head>
<body><div class="box"><div class="spinner"></div><p><?php echo esc_html($message); ?></p></div>
<script>
(function(){
  var payload={type:'yo_wayforpay_return', invoiceId:<?php echo wp_json_encode($order_reference); ?>, paid:<?php echo $paid ? 'true' : 'false'; ?>, status:<?php echo wp_json_encode($status); ?>};
  try{ if(window.parent && window.parent!==window){ window.parent.postMessage(payload, '*'); } }catch(e){}
  setTimeout(function(){ try{ if(window.parent && window.parent!==window){ window.parent.postMessage(payload, '*'); } }catch(e){} }, 2000);
})();
</script></body></html>
        <?php
        exit;
    }

    public function ajax_wayforpay_form() {
        $order_reference = sanitize_text_field(wp_unslash($_GET['order_reference'] ?? ''));
        $local_id = get_option('yo_wayforpay_order_' . $order_reference);
        if (!$order_reference || !$local_id || get_post_type($local_id) !== self::CPT) {
            status_header(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'WayForPay order not found';
            exit;
        }
        $fields = $this->wayforpay_purchase_fields($local_id, $order_reference);
        if (is_wp_error($fields)) {
            status_header(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo esc_html($fields->get_error_message());
            exit;
        }
        nocache_headers();
        header('Content-Type: text/html; charset=utf-8');
        echo $this->render_wayforpay_autosubmit_html($fields);
        exit;
    }

    private function render_wayforpay_autosubmit_html($fields) {
        $html = '<!doctype html><html><head><meta charset="utf-8"><title>WayForPay</title></head><body style="font-family:Arial,sans-serif;text-align:center;padding:30px;">'
            . '<p>Redirecting to secure WayForPay payment page...</p>'
            . '<form id="wfp" method="post" action="https://secure.wayforpay.com/pay">';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) $html .= '<input type="hidden" name="' . esc_attr($key) . '[]" value="' . esc_attr($item) . '">';
            } else {
                $html .= '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
            }
        }
        $html .= '</form><script>document.getElementById("wfp").submit();</script></body></html>';
        return $html;
    }

    public function wayforpay_form(WP_REST_Request $req) {
        $order_reference = sanitize_text_field($req->get_param('order_reference'));
        $local_id = get_option('yo_wayforpay_order_' . $order_reference);
        if (!$order_reference || !$local_id || get_post_type($local_id) !== self::CPT) {
            return new WP_REST_Response('WayForPay order not found', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $fields = $this->wayforpay_purchase_fields($local_id, $order_reference);
        if (is_wp_error($fields)) return new WP_REST_Response($fields->get_error_message(), 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        return new WP_REST_Response($this->render_wayforpay_autosubmit_html($fields), 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function wayforpay_webhook(WP_REST_Request $req) {
        $data = $req->get_json_params();
        if (empty($data)) $data = $req->get_body_params();
        $order_reference = sanitize_text_field($data['orderReference'] ?? '');
        $status = sanitize_text_field($data['transactionStatus'] ?? '');
        if (!$order_reference) return new WP_REST_Response(['reason'=>'Bad Request','reasonCode'=>1112], 400);
        $local_id = get_option('yo_wayforpay_order_' . $order_reference);
        if (!$local_id) return new WP_REST_Response(['reason'=>'Order not found','reasonCode'=>1112], 404);
        if ($status === 'Approved' && $this->verify_wayforpay_callback($data) && get_post_meta($local_id, 'paid', true) !== '1') {
            $this->process_successful_card_payment($local_id, $order_reference);
        }
        $time = time();
        $signature = $this->wayforpay_signature([$order_reference, 'accept', $time]);
        return new WP_REST_Response(['orderReference'=>$order_reference,'status'=>'accept','time'=>$time,'signature'=>$signature], 200);
    }

    private function wayforpay_should_simulate_test_success() {
        $s = self::settings();
        return (($s['test_mode'] ?? '0') === '1'
            && ($s['wayforpay_test_credentials_mode'] ?? 'official') === 'official'
            && ($s['wayforpay_test_result_mode'] ?? 'simulate_success') === 'simulate_success');
    }

    private function wayforpay_credentials() {
        $s = self::settings();
        if (($s['test_mode'] ?? '0') === '1' && ($s['wayforpay_test_credentials_mode'] ?? 'official') === 'official') {
            return [
                'merchantAccount' => 'test_merch_n1',
                'secretKey' => 'flk3409refn54t54t*FNJRET',
                'isOfficialTest' => true,
            ];
        }
        return [
            'merchantAccount' => trim((string)($s['wayforpay_merchant_login'] ?? '')),
            'secretKey' => (string)($s['wayforpay_secret_key'] ?? ''),
            'isOfficialTest' => false,
        ];
    }

    private function wayforpay_secret_for_account($merchant_account) {
        $creds = $this->wayforpay_credentials();
        if ($merchant_account === 'test_merch_n1') return 'flk3409refn54t54t*FNJRET';
        return $creds['secretKey'];
    }

    private function check_wayforpay_status($order_reference) {
        $creds = $this->wayforpay_credentials();
        if (empty($creds['merchantAccount']) || empty($creds['secretKey']) || empty($order_reference)) return false;
        $payload = [
            'transactionType' => 'CHECK_STATUS',
            'merchantAccount' => $creds['merchantAccount'],
            'orderReference' => $order_reference,
            'merchantSignature' => hash_hmac('md5', $creds['merchantAccount'] . ';' . $order_reference, $creds['secretKey']),
            'apiVersion' => 1,
        ];
        $resp = wp_remote_post('https://api.wayforpay.com/api', [
            'headers' => ['Content-Type'=>'application/json','Accept'=>'application/json'],
            'body' => wp_json_encode($payload),
            'timeout' => 30,
        ]);
        if (is_wp_error($resp)) return false;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (!is_array($body)) return false;
        $sig = $body['merchantSignature'] ?? '';
        if ($sig) {
            $expected = hash_hmac('md5', implode(';', [
                $body['merchantAccount'] ?? '',
                $body['orderReference'] ?? '',
                $body['amount'] ?? '',
                $body['currency'] ?? '',
                $body['authCode'] ?? '',
                $body['cardPan'] ?? '',
                $body['transactionStatus'] ?? '',
                $body['reasonCode'] ?? '',
            ]), $this->wayforpay_secret_for_account($body['merchantAccount'] ?? ''));
            if (!hash_equals($expected, $sig)) return false;
        }
        return $body;
    }

    private function wayforpay_purchase_fields($local_id, $order_reference) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        $fee = $this->card_fee_data($local_id, 'wayforpay');
        update_post_meta($local_id, 'card_fee_percent', $fee['percent']);
        update_post_meta($local_id, 'card_fee_amount', $fee['fee']);
        update_post_meta($local_id, 'card_total_amount', $fee['total']);
        $amount = number_format(floatval($fee['total']), 2, '.', '');
        $currency = strtoupper(trim($s['wayforpay_currency'] ?: 'EUR'));
        $creds = $this->wayforpay_credentials();
        $product_name = mb_substr($this->clean_product_title_for_display($d['title'] ?: ('YOleotard order ' . $d['order_id'])), 0, 128);
        $fields = [
            'merchantAccount' => $creds['merchantAccount'],
            'merchantAuthType' => 'SimpleSignature',
            'merchantDomainName' => wp_parse_url(home_url(), PHP_URL_HOST),
            'merchantTransactionType' => 'AUTO',
            'merchantTransactionSecureType' => 'AUTO',
            'apiVersion' => '1',
            'language' => 'EN',
            'serviceUrl' => rest_url(self::NS . '/wayforpay-webhook'),
            'returnUrl' => add_query_arg(['action'=>'yo_checkout_wayforpay_return','order_reference'=>rawurlencode($order_reference)], admin_url('admin-ajax.php')),
            'orderReference' => $order_reference,
            'orderDate' => time(),
            'amount' => $amount,
            'currency' => $currency,
            'productName' => [$product_name],
            'productPrice' => [$amount],
            'productCount' => [1],
            'clientFirstName' => $d['full_name'],
            'clientEmail' => $d['email'],
            'clientPhone' => $d['phone'],
            'clientAddress' => trim($d['address'] . ' ' . $d['additional_address']),
            'clientCity' => $d['city'],
            'clientZipCode' => $d['zip_code'],
            'clientCountry' => $d['country'],
        ];
        $fields['merchantSignature'] = $this->wayforpay_signature([
            $fields['merchantAccount'], $fields['merchantDomainName'], $fields['orderReference'], $fields['orderDate'], $fields['amount'], $fields['currency'], $product_name, 1, $amount
        ]);
        return $fields;
    }

    private function wayforpay_signature($parts, $secret_key = null) {
        $creds = $this->wayforpay_credentials();
        $secret_key = $secret_key ?: $creds['secretKey'];
        return hash_hmac('md5', implode(';', array_map('strval', $parts)), $secret_key);
    }

    private function verify_wayforpay_callback($data) {
        $signature = $data['merchantSignature'] ?? '';
        if (!$signature) return false;
        $merchant_account = $data['merchantAccount'] ?? '';
        $expected = $this->wayforpay_signature([
            $merchant_account, $data['orderReference'] ?? '', $data['amount'] ?? '', $data['currency'] ?? '', $data['authCode'] ?? '', $data['cardPan'] ?? '', $data['transactionStatus'] ?? '', $data['reasonCode'] ?? ''
        ], $this->wayforpay_secret_for_account($merchant_account));
        return hash_equals($expected, $signature);
    }

    private function card_provider_for_order($local_id) {
        $s = self::settings();
        $forced = ($s['test_mode'] === '1') ? strtolower(trim($s['test_card_provider'])) : 'auto';
        if (in_array($forced, ['monobank','wayforpay'], true)) return $forced;
        $d = $this->get_order_data($local_id);
        return $this->is_wayforpay_country($d['country']) ? 'wayforpay' : 'monobank';
    }

    private function is_wayforpay_country($country) {
        $s = self::settings();
        $needle = trim(wp_strip_all_tags((string)$country));
        $list = array_filter(array_map('trim', explode(',', wp_strip_all_tags($s['wayforpay_countries'] ?? ''))));
        foreach ($list as $c) if (strcasecmp($c, $needle) === 0) return true;
        return false;
    }

    private function sanitize_order_input() {
        $data = [
            'title'=>$this->clean_product_title_for_display(sanitize_text_field($_POST['title'] ?? '')),
            'price_eur'=>floatval($_POST['price_eur'] ?? 0),
            'original_price_eur'=>floatval($_POST['original_price_eur'] ?? ($_POST['price_eur'] ?? 0)),
            'discount_eur'=>floatval($_POST['discount_eur'] ?? 0),
            'shipping_weight_kg'=>max(0, floatval(str_replace(',', '.', (string)($_POST['shipping_weight_kg'] ?? 0)))),
            'image_url'=>esc_url_raw($_POST['image_url'] ?? ''),
            'full_name'=>sanitize_text_field($_POST['full_name'] ?? ''),
            'phone'=>sanitize_text_field($_POST['phone'] ?? ''),
            'email'=>sanitize_email($_POST['email'] ?? ''),
            'address'=>sanitize_text_field($_POST['address'] ?? ''),
            'additional_address'=>sanitize_text_field($_POST['additional_address'] ?? ''),
            'city'=>sanitize_text_field($_POST['city'] ?? ''),
            'zip_code'=>sanitize_text_field($_POST['zip_code'] ?? ''),
            'country'=>sanitize_text_field($_POST['country'] ?? ''),
            'cart_items_count'=>max(1, absint($_POST['cart_items_count'] ?? 1)),
            'cart_items_json'=>$this->sanitize_cart_items_json($_POST['cart_items_json'] ?? ''),
            'promo_code_applied'=>sanitize_text_field($_POST['promo_code_applied'] ?? ''),
            'created_at'=>current_time('timestamp'),
        ];
        foreach (['title','full_name','phone','email','address','city','zip_code','country'] as $k) if (!$data[$k]) return new WP_Error('missing', 'Required fields are missing');
        if ($data['price_eur'] <= 0) return new WP_Error('price', 'Product price is missing');
        if ($data['original_price_eur'] <= 0) $data['original_price_eur'] = $data['price_eur'];
        if ($data['original_price_eur'] < $data['price_eur']) $data['original_price_eur'] = $data['price_eur'];
        $calculated_discount = round($data['original_price_eur'] - $data['price_eur'], 2);
        if ($data['discount_eur'] <= 0 && $calculated_discount > 0) $data['discount_eur'] = $calculated_discount;
        if ($data['discount_eur'] < 0) $data['discount_eur'] = 0;
        if (!empty($data['promo_code_applied'])) {
            $s = self::settings();
            if (!$this->promo_is_configured() || strcasecmp($data['promo_code_applied'], trim((string)($s['promo_code'] ?? ''))) !== 0 || $this->promo_is_expired($s)) {
                return new WP_Error('promo_invalid', 'Promo code is no longer valid');
            }
            $cart_items = [];
            if (!empty($data['cart_items_json'])) {
                $decoded = json_decode((string)$data['cart_items_json'], true);
                if (is_array($decoded)) $cart_items = $decoded;
            }
            if ($cart_items) {
                $regular_final = 0;
                $original_total = 0;
                $product_discount_total = 0;
                $eligible_promo_base = 0;
                foreach ($cart_items as $item) {
                    $item_price = round(floatval($item['price_eur'] ?? 0), 2);
                    $item_original = round(floatval($item['original_price_eur'] ?? $item_price), 2);
                    if ($item_original < $item_price) $item_original = $item_price;
                    $item_product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
                    $regular_final += $item_price;
                    $original_total += $item_original;
                    $product_discount_total += max(0, $item_product_discount);
                    if ($item_product_discount <= 0) $eligible_promo_base += $item_price;
                }
                if ($eligible_promo_base <= 0) return new WP_Error('promo_ineligible', 'Promo code can be applied only to products without an active discount');
                $calc = $this->calculate_promo_discount($eligible_promo_base);
                if (is_wp_error($calc)) return $calc;
                $promo_discount = floatval($calc['discount']);
                $promo_remaining = $promo_discount;
                $eligible_seen = 0;
                $cart_count = count($cart_items);
                foreach ($cart_items as $idx => &$cart_item) {
                    $item_price = round(floatval($cart_item['price_eur'] ?? 0), 2);
                    $item_product_discount = round(floatval($cart_item['product_discount_eur'] ?? 0), 2);
                    $item_promo_discount = 0;
                    if ($item_product_discount <= 0 && $eligible_promo_base > 0) {
                        $eligible_seen += $item_price;
                        if ($idx === $cart_count - 1 || abs($eligible_seen - $eligible_promo_base) < 0.01) {
                            $item_promo_discount = round($promo_remaining, 2);
                        } else {
                            $item_promo_discount = round($promo_discount * ($item_price / $eligible_promo_base), 2);
                            $promo_remaining = round($promo_remaining - $item_promo_discount, 2);
                        }
                    }
                    $cart_item['promo_discount_eur'] = number_format(max(0, $item_promo_discount), 2, '.', '');
                    $cart_item['discount_eur'] = number_format(max(0, $item_product_discount + $item_promo_discount), 2, '.', '');
                }
                unset($cart_item);
                $data['cart_items_json'] = wp_json_encode($cart_items);
                $data['original_price_eur'] = round($original_total, 2);
                $data['price_eur'] = round(max(1, $regular_final - $promo_discount), 2);
                $data['discount_eur'] = round($product_discount_total + $promo_discount, 2);
                $data['promo_discount_type'] = $calc['type'];
                $data['promo_discount_value'] = $calc['value'];
            } else {
                $calc = $this->calculate_promo_discount($data['original_price_eur']);
                if (is_wp_error($calc)) return $calc;
                $expected_discount = floatval($calc['discount']);
                $expected_price = round(max(1, floatval($data['original_price_eur']) - $expected_discount), 2);
                $data['price_eur'] = $expected_price;
                $data['discount_eur'] = $expected_discount;
                $data['promo_discount_type'] = $calc['type'];
                $data['promo_discount_value'] = $calc['value'];
            }
        }
        if ($data['shipping_weight_kg'] <= 0 && !empty($data['cart_items_json'])) {
            $decoded_weight_items = json_decode((string)$data['cart_items_json'], true);
            if (is_array($decoded_weight_items)) {
                $total_weight = 0;
                foreach ($decoded_weight_items as $wi) {
                    if (!is_array($wi)) continue;
                    $w = str_replace(',', '.', (string)($wi['weight_kg'] ?? ($wi['product_weight_kg'] ?? ($wi['weight'] ?? 0))));
                    $total_weight += is_numeric($w) ? max(0, floatval($w)) : 0;
                }
                if ($total_weight > 0) $data['shipping_weight_kg'] = round($total_weight, 3);
            }
        }
        if ($data['shipping_weight_kg'] <= 0) $data['shipping_weight_kg'] = max(0.1, floatval(str_replace(',', '.', (string)(self::settings()['nova_post_weight_kg'] ?? 2))));
        return $data;
    }

    private function clean_product_title_for_display($title) {
        $title = (string)$title;
        if ($title === '') return '';

        // Decode real JSON unicode escapes like \u00ab and also broken/plain variants like u00ab
        // that can appear after frontend sanitizing or when values are passed through AJAX/KeyCRM.
        if (method_exists($this, 'decode_loose_unicode_sequences')) {
            $title = $this->decode_loose_unicode_sequences($title);
        }

        $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (method_exists($this, 'decode_loose_unicode_sequences')) {
            $title = $this->decode_loose_unicode_sequences($title);
        }

        // Normalize common quotation/dash variants for a clean KeyCRM product name.
        $title = str_replace(['«','»','“','”','„'], ['“','”','“','”','“'], $title);
        $title = str_replace(['‘','’','`','´'], ["'","'","'","'"], $title);
        $title = str_replace(['–','—','−'], '-', $title);

        // Remove leftover naked unicode escape fragments if any unknown one remained.
        $title = preg_replace('/\\\\?u[0-9a-fA-F]{4}/', '', $title);
        $title = wp_strip_all_tags($title);
        $title = preg_replace('/\s+/u', ' ', $title);
        return trim($title);
    }

    private function keycrm_product_sku($title, $image_url = '', $price = '') {
        $title_clean = $this->clean_product_title_for_display($title);
        $base = sanitize_title($title_clean);
        if ($base === '') $base = 'yoleotard-item';
        $hash = substr(md5($title_clean . '|' . (string)$image_url . '|' . (string)$price), 0, 10);
        return substr('YO-' . $base . '-' . $hash, 0, 64);
    }

    private function sanitize_cart_items_json($raw) {
        $raw = wp_unslash((string)$raw);
        if ($raw === '') return '';
        $items = json_decode($raw, true);
        if (!is_array($items)) return '';
        $clean = [];
        $seen_titles = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $title = $this->clean_product_title_for_display(sanitize_text_field($item['title'] ?? ''));
            $price = round(floatval($item['price_eur'] ?? 0), 2);
            if ($title === '' || $price <= 0) continue;
            $title_key = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(['“','”','«','»','"',"'"], '', $title))));
            if ($title_key !== '' && isset($seen_titles[$title_key])) continue;
            if ($title_key !== '') $seen_titles[$title_key] = true;
            $original = round(floatval($item['original_price_eur'] ?? $price), 2);
            if ($original < $price) $original = $price;
            $product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
            $promo_discount = round(floatval($item['promo_discount_eur'] ?? 0), 2);
            $total_discount = round(floatval($item['discount_eur'] ?? ($product_discount + $promo_discount)), 2);
            $weight = str_replace(',', '.', (string)($item['weight_kg'] ?? ($item['product_weight_kg'] ?? ($item['weight'] ?? 0))));
            $weight = is_numeric($weight) ? max(0, round(floatval($weight), 3)) : 0;
            if ($product_discount < 0) $product_discount = 0;
            if ($promo_discount < 0) $promo_discount = 0;
            if ($total_discount < 0) $total_discount = 0;
            if ($total_discount < ($product_discount + $promo_discount)) $total_discount = round($product_discount + $promo_discount, 2);
            $clean[] = [
                'title' => $title,
                'price_eur' => number_format($price, 2, '.', ''),
                'original_price_eur' => number_format($original, 2, '.', ''),
                'discount_eur' => number_format($total_discount, 2, '.', ''),
                'product_discount_eur' => number_format($product_discount, 2, '.', ''),
                'promo_discount_eur' => number_format($promo_discount, 2, '.', ''),
                'weight_kg' => $weight > 0 ? number_format($weight, 3, '.', '') : '',
                'image_url' => esc_url_raw($item['image_url'] ?? ''),
            ];
        }
        return $clean ? wp_json_encode($clean) : '';
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
            ]];
        }
        return array_values(array_filter($items, function($item){ return is_array($item) && !empty($item['title']); }));
    }

    private function get_order_data($local_id) {
        $keys = ['title','price_eur','original_price_eur','discount_eur','image_url','full_name','phone','email','address','additional_address','city','zip_code','country','created_at','buyer_id','order_id','mono_invoice_id','wayforpay_order_reference','payment_provider','payment_type','card_fee_percent','card_fee_amount','card_total_amount','shipping_cost_eur','shipping_source','shipping_weight_kg','bank_total_amount','promo_code_applied','promo_discount_type','promo_discount_value','cart_items_count','cart_items_json','checkout_session_id','browser_buyer_id'];
        $out=[]; foreach($keys as $k) $out[$k]=get_post_meta($local_id,$k,true); return $out;
    }



    private function keycrm_marker_cookie_order_id() {
        return preg_replace('/[^0-9]/', '', (string)($_COOKIE['yo_checkout_keycrm_order_id'] ?? ''));
    }

    private function posted_cart_marker_keycrm_order_id() {
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

    private function marker_hash_value($value) {
        $value = trim((string)$value);
        return $value === '' ? '' : md5(mb_strtolower($value));
    }


    private function hard_find_existing_keycrm_checkout_by_marker($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '', $exclude_local_id = 0) {
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
            'post_type' => self::CPT,
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

    private function find_recent_unpaid_keycrm_order_for_customer_broad($data = [], $exclude_local_id = 0) {
        $exclude_local_id = absint($exclude_local_id);
        $email = sanitize_email($data['email'] ?? '');
        $phone_digits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
        $phone_tail = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
        if ($email === '' && $phone_tail === '') return 0;
        $ids = get_posts([
            'post_type' => self::CPT,
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

    private function find_latest_unpaid_keycrm_order_by_contact($data = [], $exclude_local_id = 0) {
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
            'post_type' => self::CPT,
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

    private function find_hard_reusable_keycrm_local_id($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
        $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string)$posted_keycrm_order_id);
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->posted_cart_marker_keycrm_order_id();
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
        if ($posted_keycrm_order_id !== '') {
            $by_order = $this->find_local_order_by_keycrm_order_id($posted_keycrm_order_id);
            if ($by_order && get_post_type($by_order) === self::CPT && get_post_meta($by_order, 'paid', true) !== '1') return $by_order;
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
                'post_type' => self::CPT,
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
            if (!$id || get_post_type($id) !== self::CPT) continue;
            if (get_post_meta($id, 'paid', true) === '1') continue;
            $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
            if ($order_id === '') continue;
            return $id;
        }
        return 0;
    }

    private function find_latest_unpaid_keycrm_checkout_for_customer($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
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
                'post_type' => self::CPT,
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
            if (get_post_type($id) !== self::CPT) continue;
            if (get_post_meta($id, 'paid', true) === '1') continue;
            $order_id = preg_replace('/[^0-9]/', '', (string)get_post_meta($id, 'order_id', true));
            if ($order_id !== '') return $id;
        }
        return 0;
    }

    private function find_local_order_by_active_marker($data = [], $checkout_session_id = '', $browser_buyer_id = '', $posted_keycrm_order_id = '') {
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

    private function find_local_order_by_keycrm_order_id($order_id) {
        $order_id = preg_replace('/[^0-9]/', '', (string)$order_id);
        if ($order_id === '') return 0;
        $mapped_id = absint(get_option('yo_checkout_keycrm_order_' . md5($order_id), 0));
        if ($mapped_id && get_post_type($mapped_id) === self::CPT) return $mapped_id;
        $ids = get_posts([
            'post_type' => self::CPT,
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

    private function find_reusable_unpaid_keycrm_local_id($data = [], $checkout_session_id = '', $browser_buyer_id = '', $exclude_local_id = 0, $posted_keycrm_order_id = '') {
        $exclude_local_id = absint($exclude_local_id);
        $candidates = [];
        $add_candidates = function($args) use (&$candidates) {
            $ids = get_posts(array_merge([
                'post_type' => self::CPT,
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

    private function remember_keycrm_checkout_marker($local_id) {
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


    private function find_latest_open_keycrm_order_for_same_customer($data = [], $exclude_local_id = 0) {
        $exclude_local_id = absint($exclude_local_id);
        $email = sanitize_email($data['email'] ?? '');
        $phone_digits = preg_replace('/[^0-9]/', '', (string)($data['phone'] ?? ''));
        $phone_tail = strlen($phone_digits) > 7 ? substr($phone_digits, -7) : $phone_digits;
        $session = sanitize_text_field((string)($data['checkout_session_id'] ?? ''));
        $browser = sanitize_text_field((string)($data['browser_buyer_id'] ?? ''));

        $ids = get_posts([
            'post_type' => self::CPT,
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

    private function ensure_keycrm_order($local_id, $mode = 'bank') {
        $posted_keycrm_order_id = preg_replace('/[^0-9]/', '', (string) wp_unslash($_POST['keycrm_order_id'] ?? ''));
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->posted_cart_marker_keycrm_order_id();
        if ($posted_keycrm_order_id === '') $posted_keycrm_order_id = $this->keycrm_marker_cookie_order_id();
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
        if (trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
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

        if (trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
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
        if (trim((string)get_post_meta($local_id, 'order_id', true)) === '') {
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
        $reuse_local_id = $this->find_reusable_unpaid_keycrm_local_id($current_data, $current_data['checkout_session_id'] ?? '', $current_data['browser_buyer_id'] ?? '', $local_id);
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
        $hard_reuse_id = $this->find_hard_reusable_keycrm_local_id($data, $data['checkout_session_id'] ?? '', $data['browser_buyer_id'] ?? '', $posted_keycrm_order_id);
        if ($hard_reuse_id && $hard_reuse_id !== $local_id) {
            $reuse_order_id = get_post_meta($hard_reuse_id, 'order_id', true);
            $reuse_buyer_id = get_post_meta($hard_reuse_id, 'buyer_id', true);
            if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
            if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
            update_post_meta($local_id, 'keycrm_created', '1');
            $this->remember_keycrm_checkout_marker($local_id);
            return $this->keycrm_update_existing_order($local_id, $mode);
        }

        $contact_reuse_id = $this->find_latest_unpaid_keycrm_order_by_contact($data, $local_id);
        if ($contact_reuse_id && $contact_reuse_id !== $local_id) {
            $reuse_order_id = get_post_meta($contact_reuse_id, 'order_id', true);
            $reuse_buyer_id = get_post_meta($contact_reuse_id, 'buyer_id', true);
            if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
            if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
            update_post_meta($local_id, 'keycrm_created', '1');
            $this->remember_keycrm_checkout_marker($local_id);
            return $this->keycrm_update_existing_order($local_id, $mode);
        }

        $broad_contact_reuse_id = $this->find_recent_unpaid_keycrm_order_for_customer_broad($data, $local_id);
        if ($broad_contact_reuse_id && $broad_contact_reuse_id !== $local_id) {
            $reuse_order_id = get_post_meta($broad_contact_reuse_id, 'order_id', true);
            $reuse_buyer_id = get_post_meta($broad_contact_reuse_id, 'buyer_id', true);
            if ($reuse_order_id) update_post_meta($local_id, 'order_id', $reuse_order_id);
            if ($reuse_buyer_id) update_post_meta($local_id, 'buyer_id', $reuse_buyer_id);
            update_post_meta($local_id, 'keycrm_created', '1');
            $this->remember_keycrm_checkout_marker($local_id);
            return $this->keycrm_update_existing_order($local_id, $mode);
        }

        $s = self::settings();
        $data = $this->get_order_data($local_id);
        $buyer_id = $this->keycrm_create_buyer($data, $s);
        if (is_wp_error($buyer_id)) return new WP_Error('keycrm_buyer', 'KeyCRM buyer was not created', $buyer_id->get_error_data());
        update_post_meta($local_id, 'buyer_id', $buyer_id);
        $data['buyer_id'] = $buyer_id;
        $order_id = $this->keycrm_create_order($data, $buyer_id, $s, $mode);
        if (is_wp_error($order_id)) return $order_id;
        update_post_meta($local_id, 'order_id', $order_id);
        update_post_meta($local_id, 'keycrm_created', '1');
        $this->remember_keycrm_checkout_marker($local_id);
        wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $order_id . ' - ' . ($data['full_name'] ?? '')]);
        return true;
    }

    private function keycrm_create_buyer($d, $s) {
        $payload = ['full_name'=>$d['full_name'],'email'=>[$d['email']],'phone'=>[$d['phone']],'note'=>'Created from YOleotard website','shipping'=>[[
            'address'=>$d['address'],'additional_address'=>$d['additional_address'],'city'=>$d['city'],'zip_code'=>$d['zip_code'],'country'=>$d['country'],'recipient_full_name'=>$d['full_name'],'recipient_phone'=>$d['phone']
        ]]];
        $body = $this->keycrm_request('POST', '/buyer', $payload, $s);
        if (is_wp_error($body)) return $body;
        return $body['id'] ?? new WP_Error('keycrm_buyer', 'No buyer id', $body);
    }
    private function keycrm_build_order_payload($d, $buyer_id, $s, $mode = 'bank') {
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

    private function keycrm_get_order_products_for_sync($order_id, $s) {
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

    private function keycrm_order_product_row_id($row) {
        if (!is_array($row)) return '';
        foreach (['id','order_product_id','order_productId','pivot_id','pivotId'] as $key) {
            if (!empty($row[$key]) && is_scalar($row[$key])) return preg_replace('/[^0-9]/', '', (string)$row[$key]);
        }
        return '';
    }

    private function keycrm_order_product_name($row) {
        if (!is_array($row)) return '';
        foreach (['name','product_name','title'] as $key) {
            if (!empty($row[$key]) && is_scalar($row[$key])) return (string)$row[$key];
        }
        if (!empty($row['product']) && is_array($row['product'])) {
            foreach (['name','title'] as $key) if (!empty($row['product'][$key]) && is_scalar($row['product'][$key])) return (string)$row['product'][$key];
        }
        return '';
    }

    private function keycrm_product_sync_key($name, $price = null) {
        $name = $this->clean_product_title_for_display((string)$name);
        $name = mb_strtolower($name);
        $name = preg_replace('/["\'\x{201c}\x{201d}\x{00ab}\x{00bb}]+/u', '', $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        $price_part = ($price === null || $price === '') ? '' : ('|' . number_format(round(floatval($price), 2), 2, '.', ''));
        return $name . $price_part;
    }

    private function keycrm_prepare_products_for_full_sync($order_id, $payload, $s) {
        // v3.3.75: keep Step 3 stable. Do not call extra KeyCRM product-row sync endpoints here.
        // KeyCRM safely updates the main order through PUT /order/{id}; product-row deletion/zeroing
        // is not reliable in the public API and previously caused a frontend "Connection error".
        return $payload;

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
            $row_price = $row['price'] ?? ($row['price_sold'] ?? ($row['amount'] ?? null));
            $row_picture = $row['picture'] ?? ($row['image'] ?? ($row['image_url'] ?? ''));
            $row_data = ['id'=>$row_id, 'name'=>$row_name, 'price'=>$row_price, 'picture'=>$row_picture, 'raw'=>$row];
            $rows[] = $row_data;
            $map[$this->keycrm_product_sync_key($row_name, $row_price)] = $row_data;
            $map[$this->keycrm_product_sync_key($row_name, null)] = $row_data;
            if ($row_picture) $map['img|' . md5((string)$row_picture)] = $row_data;
        }

        $used_ids = [];
        foreach ($payload['products'] as $i => $product) {
            if (!is_array($product)) continue;
            $key_price = $this->keycrm_product_sync_key($product['name'] ?? '', $product['price'] ?? null);
            $key_name = $this->keycrm_product_sync_key($product['name'] ?? '', null);
            $img_key = !empty($product['picture']) ? ('img|' . md5((string)$product['picture'])) : '';
            $matched = $map[$key_price] ?? ($map[$key_name] ?? ($img_key && isset($map[$img_key]) ? $map[$img_key] : null));

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

        // KeyCRM OpenAPI documents PUT /order/{id}, but it does not expose a separate
        // public DELETE endpoint for product rows. To make the CRM order follow the
        // current website cart as closely as the API allows, send removed rows back
        // with quantity = 0. If KeyCRM accepts this as a removal/zeroing operation,
        // the old product stops affecting the order. If KeyCRM rejects quantity 0,
        // keycrm_update_existing_order() retries once without these zero rows.
        foreach ($rows as $row) {
            if (empty($row['id']) || isset($used_ids[$row['id']])) continue;
            $payload['products'][] = [
                'id' => intval($row['id']),
                'name' => $this->clean_product_title_for_display($row['name'] ?: 'Removed from website cart'),
                'price' => 0,
                'quantity' => 0,
                'picture' => '',
            ];
        }

        return $payload;
    }

    private function keycrm_try_clear_order_products_before_replace($order_id, $base_payload, $s) {
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

    private function keycrm_try_delete_order_product_row($order_id, $row_id, $s) {
        // Deprecated in v3.3.72: undocumented DELETE attempts caused connection errors on Step 3.
        // Product rows are now synchronized through PUT /order/{id} and products.*.id where possible.
        return false;
    }

    private function keycrm_update_existing_order($local_id, $mode = 'bank') {
        $s = self::settings();
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
            if (is_wp_error($body) && $this->keycrm_error_is_order_not_found($body)) {
                delete_post_meta($local_id, 'order_id');
                update_post_meta($local_id, 'keycrm_created', '0');
                $new_order_id = $this->keycrm_create_order($d, $buyer_id, $s, $mode);
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

    private function keycrm_payload_has_zero_quantity_products($payload) {
        if (empty($payload['products']) || !is_array($payload['products'])) return false;
        foreach ($payload['products'] as $product) {
            if (is_array($product) && array_key_exists('quantity', $product) && floatval($product['quantity']) <= 0) return true;
        }
        return false;
    }

    private function keycrm_payload_without_zero_quantity_products($payload) {
        if (empty($payload['products']) || !is_array($payload['products'])) return $payload;
        $payload['products'] = array_values(array_filter($payload['products'], function($product) {
            if (!is_array($product)) return false;
            if (array_key_exists('quantity', $product) && floatval($product['quantity']) <= 0) return false;
            return true;
        }));
        return $payload;
    }

    private function keycrm_payload_with_fallback_skus($payload) {
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

    private function keycrm_error_needs_sku($err) {
        if (!is_wp_error($err)) return false;
        $data = $err->get_error_data();
        $txt = is_scalar($data) ? (string)$data : wp_json_encode($data);
        return (stripos((string)$txt, 'sku') !== false && stripos((string)$txt, 'required') !== false);
    }

    private function keycrm_create_order($d, $buyer_id, $s, $mode = 'bank') {
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

    private function keycrm_error_is_order_not_found($error) {
        if (!is_wp_error($error)) return false;
        $data = $error->get_error_data();
        $code = intval($data['code'] ?? 0);
        $message = '';
        if (isset($data['body']['message'])) $message = (string)$data['body']['message'];
        return $code === 404 && stripos($message, 'Order') !== false && stripos($message, 'not found') !== false;
    }
    private function keycrm_request($method, $path, $payload, $s=null) {
        $s = $s ?: self::settings();
        if (empty($s['keycrm_token'])) return new WP_Error('no_token','KeyCRM token is empty');
        $resp = wp_remote_request('https://openapi.keycrm.app/v1' . $path, ['method'=>$method,'headers'=>['Authorization'=>'Bearer '.$s['keycrm_token'],'Content-Type'=>'application/json','Accept'=>'application/json'],'body'=>wp_json_encode($payload),'timeout'=>30]);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $code = wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code >= 300) return new WP_Error('keycrm_http','KeyCRM HTTP error', ['code'=>$code,'body'=>$body]);
        return $body;
    }
    private function normalize_country_name($country) {
        return mb_strtolower(trim(wp_strip_all_tags((string)$country)));
    }

    private function country_to_iso2($country) {
        $c = $this->normalize_country_name($country);
        $map = [
            'united states'=>'US','usa'=>'US','us'=>'US','united states of america'=>'US',
            'united kingdom'=>'GB','uk'=>'GB','great britain'=>'GB','england'=>'GB','scotland'=>'GB','wales'=>'GB','northern ireland'=>'GB',
            'france'=>'FR','germany'=>'DE','italy'=>'IT','spain'=>'ES','poland'=>'PL','norway'=>'NO','ukraine'=>'UA',
            'malaysia'=>'MY','singapore'=>'SG','thailand'=>'TH','indonesia'=>'ID','philippines'=>'PH','vietnam'=>'VN',
            'india'=>'IN','china'=>'CN','japan'=>'JP','south korea'=>'KR','korea'=>'KR','hong kong'=>'HK','taiwan'=>'TW',
            'austria'=>'AT','belgium'=>'BE','bulgaria'=>'BG','croatia'=>'HR','cyprus'=>'CY','czech republic'=>'CZ','czechia'=>'CZ',
            'denmark'=>'DK','estonia'=>'EE','finland'=>'FI','greece'=>'GR','hungary'=>'HU','ireland'=>'IE','latvia'=>'LV',
            'lithuania'=>'LT','luxembourg'=>'LU','malta'=>'MT','netherlands'=>'NL','portugal'=>'PT','romania'=>'RO',
            'slovakia'=>'SK','slovenia'=>'SI','sweden'=>'SE','switzerland'=>'CH','canada'=>'CA','australia'=>'AU','new zealand'=>'NZ',
            'united arab emirates'=>'AE','uae'=>'AE','saudi arabia'=>'SA','qatar'=>'QA','kuwait'=>'KW','bahrain'=>'BH','oman'=>'OM',
        ];
        if (preg_match('/^[a-z]{2}$/', $c)) return strtoupper($c);
        return $map[$c] ?? strtoupper(substr(preg_replace('/[^a-z]/', '', $c), 0, 2));
    }

    private function extract_amount_from_response($data) {
        if (!is_array($data)) return null;
        $keys = ['amount','cost','price','deliveryCost','delivery_cost','total','totalCost','total_cost','sum','value'];
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_numeric($data[$key])) return floatval($data[$key]);
        }
        foreach (['data','result','calculation','shipment','price','prices','items','services'] as $node) {
            if (!isset($data[$node])) continue;
            $v = $data[$node];
            if (is_array($v)) {
                if (array_keys($v) === range(0, count($v) - 1)) {
                    foreach ($v as $row) {
                        $found = $this->extract_amount_from_response(is_array($row) ? $row : []);
                        if ($found !== null) return $found;
                    }
                } else {
                    $found = $this->extract_amount_from_response($v);
                    if ($found !== null) return $found;
                }
            }
        }
        return null;
    }

    private function order_shipping_weight_kg($local_id) {
        $s = self::settings();
        $default = max(0.1, floatval(str_replace(',', '.', (string)($s['nova_post_weight_kg'] ?? 2))));
        $stored = str_replace(',', '.', (string)get_post_meta($local_id, 'shipping_weight_kg', true));
        if (is_numeric($stored) && floatval($stored) > 0) return max(0.1, round(floatval($stored), 3));
        $d = $this->get_order_data($local_id);
        if (!empty($d['cart_items_json'])) {
            $items = json_decode((string)$d['cart_items_json'], true);
            if (is_array($items)) {
                $total = 0;
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $w = str_replace(',', '.', (string)($item['weight_kg'] ?? ($item['product_weight_kg'] ?? ($item['weight'] ?? 0))));
                    $total += is_numeric($w) ? max(0, floatval($w)) : 0;
                }
                if ($total > 0) return max(0.1, round($total, 3));
            }
        }
        return $default;
    }

    private function nova_post_api_shipping_amount($country, $local_id) {
        $s = self::settings();
        if (($s['nova_post_api_mode'] ?? 'api_fallback') !== 'api_fallback') return null;
        $api_key = trim((string)($s['nova_post_api_key'] ?? ''));
        $endpoint = trim((string)($s['nova_post_api_endpoint'] ?? ''));
        if ($api_key === '' || $endpoint === '') return null;

        $d = $this->get_order_data($local_id);
        $weight = $this->order_shipping_weight_kg($local_id);
        $length = max(1, floatval(str_replace(',', '.', (string)($s['nova_post_length_cm'] ?? 35))));
        $width  = max(1, floatval(str_replace(',', '.', (string)($s['nova_post_width_cm'] ?? 25))));
        $height = max(1, floatval(str_replace(',', '.', (string)($s['nova_post_height_cm'] ?? 10))));
        $currency = strtoupper(trim((string)($s['nova_post_currency'] ?? 'EUR')) ?: 'EUR');
        $cost = max(1, round(floatval($d['price_eur'] ?? 0), 2));
        $recipient_iso = $this->country_to_iso2($country);
        $sender_iso = strtoupper(trim((string)($s['nova_post_sender_country'] ?? 'UA')) ?: 'UA');

        $payload = [
            'sender' => [
                'countryCode' => $sender_iso,
                'city' => trim((string)($s['nova_post_sender_city'] ?? 'Kamianske')),
                'postalCode' => trim((string)($s['nova_post_sender_zip'] ?? '51905')),
            ],
            'recipient' => [
                'countryCode' => $recipient_iso,
                'country' => $country,
                'city' => trim((string)($d['city'] ?? '')),
                'postalCode' => trim((string)($d['zip_code'] ?? '')),
            ],
            'places' => [[
                'weight' => $weight,
                'length' => $length,
                'width' => $width,
                'height' => $height,
            ]],
            'parcels' => [[
                'weight' => $weight,
                'length' => $length,
                'width' => $width,
                'height' => $height,
            ]],
            'weight' => $weight,
            'dimensions' => ['length'=>$length, 'width'=>$width, 'height'=>$height],
            'invoice' => [
                'currency' => $currency,
                'cost' => $cost,
                'incoterm' => trim((string)($s['nova_post_incoterm'] ?? 'DAP')) ?: 'DAP',
            ],
            'currency' => $currency,
            'cost' => $cost,
            'payerType' => 'Sender',
        ];

        $headers = ['Content-Type' => 'application/json', 'Accept' => 'application/json'];
        $auth = trim((string)($s['nova_post_api_auth_header'] ?? 'Authorization: Bearer {api_key}'));
        if ($auth !== '') {
            $auth = str_replace('{api_key}', $api_key, $auth);
            if (strpos($auth, ':') !== false) {
                [$hn, $hv] = array_map('trim', explode(':', $auth, 2));
                if ($hn !== '' && $hv !== '') $headers[$hn] = $hv;
            }
        }

        $response = wp_remote_post($endpoint, [
            'timeout' => 20,
            'headers' => $headers,
            'body' => wp_json_encode($payload),
        ]);
        if (is_wp_error($response)) {
            update_post_meta($local_id, 'shipping_source', 'Nova Post API error: ' . $response->get_error_message());
            return null;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            update_post_meta($local_id, 'shipping_source', 'Nova Post API HTTP ' . $code . '; fallback table used');
            return null;
        }
        $amount = $this->extract_amount_from_response($json);
        if ($amount === null || $amount < 0) {
            update_post_meta($local_id, 'shipping_source', 'Nova Post API response without price; fallback table used');
            return null;
        }
        update_post_meta($local_id, 'shipping_source', 'Nova Post API');
        return round(floatval($amount), 2);
    }


    private function manual_currency_to_eur_rate($currency) {
        $s = self::settings();
        $currency = strtoupper(trim((string)$currency));
        if ($currency === 'EUR' || $currency === '') return 1.0;
        foreach (preg_split('/\r\n|\r|\n/', (string)($s['shipping_import_currency_to_eur'] ?? '')) as $line) {
            if (strpos($line, '=') === false) continue;
            [$c, $v] = array_map('trim', explode('=', $line, 2));
            if (strtoupper($c) !== $currency) continue;
            $rate = str_replace(',', '.', $v);
            if (is_numeric($rate) && floatval($rate) > 0) return floatval($rate);
        }
        return 0.0;
    }

    private function get_nbu_fx_rates_uah() {
        $s = self::settings();
        $cache_key = 'yo_checkout_shipping_nbu_fx_rates_v4';
        $cached = get_transient($cache_key);
        if (is_array($cached) && !empty($cached['EUR'])) return $cached;

        $url = esc_url_raw(trim((string)($s['shipping_fx_nbu_url'] ?? '')));
        if ($url === '') $url = 'https://bank.gov.ua/NBUStatService/v1/statdirectory/exchange?json';
        $response = wp_remote_get($url, ['timeout' => 12, 'headers' => ['Accept' => 'application/json']]);
        if (is_wp_error($response)) return [];
        $code = wp_remote_retrieve_response_code($response);
        if ($code < 200 || $code >= 300) return [];
        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) return [];

        $rates = ['UAH' => 1.0];
        foreach ($data as $row) {
            if (!is_array($row) || empty($row['cc']) || !isset($row['rate'])) continue;
            $cc = strtoupper(sanitize_text_field((string)$row['cc']));
            $rate = (float)$row['rate'];
            if (preg_match('/^[A-Z]{3}$/', $cc) && $rate > 0) $rates[$cc] = $rate;
        }
        if (empty($rates['EUR'])) return [];

        $hours = max(1, min(72, (int)($s['shipping_fx_cache_hours'] ?? 12)));
        set_transient($cache_key, $rates, $hours * HOUR_IN_SECONDS);
        update_option('yo_checkout_shipping_fx_last_update', current_time('mysql'));
        return $rates;
    }

    private function currency_to_eur_rate($currency) {
        $s = self::settings();
        $currency = strtoupper(trim((string)$currency));
        if ($currency === 'EUR' || $currency === '') return 1.0;

        $rate = 0.0;
        if (($s['shipping_fx_mode'] ?? 'auto_nbu') === 'auto_nbu') {
            $rates = $this->get_nbu_fx_rates_uah();
            if (!empty($rates['EUR']) && !empty($rates[$currency])) {
                $rate = (float)$rates[$currency] / (float)$rates['EUR'];
            }
        }

        if ($rate <= 0) $rate = $this->manual_currency_to_eur_rate($currency);
        if ($rate <= 0) $rate = 1.0;

        $markup = (float)str_replace(',', '.', (string)($s['shipping_fx_markup_percent'] ?? 0));
        if ($markup < 0) $markup = 0;
        if ($markup > 30) $markup = 30;
        if ($markup > 0) $rate = $rate * (1 + ($markup / 100));
        return $rate;
    }

    private function db_shipping_candidates($country, $weight_kg) {
        global $wpdb;
        $table = $this->shipping_rates_table_name();
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) return [];
        $s = self::settings();
        $needle = $this->normalize_country_name($country);
        if ($needle === '') return [];
        $mode = strtolower(trim((string)($s['shipping_service_mode'] ?? 'auto_cheapest')));
        $allowed_service = in_array($mode, ['nova_post','nova_global','ukrposhta'], true) ? $mode : '';
        if ($allowed_service) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE country_norm = %s AND service = %s AND weight_to_kg >= %f ORDER BY weight_to_kg ASC, price ASC LIMIT 100", $needle, $allowed_service, (float)$weight_kg));
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE country_norm = %s AND weight_to_kg >= %f ORDER BY weight_to_kg ASC, price ASC LIMIT 150", $needle, (float)$weight_kg));
        }
        $candidates = [];
        foreach ((array)$rows as $row) {
            $rate = $this->currency_to_eur_rate($row->currency);
            $candidates[] = [
                'service' => $row->service,
                'country' => $row->country,
                'weight_to_kg' => (float)$row->weight_to_kg,
                'price' => (float)$row->price,
                'currency' => strtoupper((string)$row->currency),
                'amount_eur' => round(((float)$row->price) * $rate, 2),
                'delivery_days' => (string)$row->delivery_days,
                'zone' => (string)$row->zone,
                'method' => (string)$row->method,
                'source' => 'database',
            ];
        }
        return $candidates;
    }

    private function structured_shipping_candidates($country, $weight_kg) {
        $s = self::settings();
        $needle = $this->normalize_country_name($country);
        if ($needle === '') return [];
        $mode = strtolower(trim((string)($s['shipping_service_mode'] ?? 'auto_cheapest')));
        $allowed_service = in_array($mode, ['nova_post','nova_global','ukrposhta'], true) ? $mode : '';
        $rows = preg_split('/\r\n|\r|\n/', (string)($s['shipping_structured_rates'] ?? ''));
        $candidates = [];
        foreach ($rows as $line) {
            $line = trim((string)$line);
            if ($line === '' || $line[0] === '#') continue;
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 5) continue;
            $service = strtolower(preg_replace('/[^a-z0-9_]+/', '_', $parts[0]));
            $row_country = $parts[1];
            if ($allowed_service && $service !== $allowed_service) continue;
            if ($this->normalize_country_name($row_country) !== $needle) continue;
            $weight_to = str_replace(',', '.', $parts[2]);
            $price = str_replace(',', '.', $parts[3]);
            if (!is_numeric($weight_to) || !is_numeric($price)) continue;
            if (floatval($weight_to) + 0.0001 < floatval($weight_kg)) continue;
            $currency = strtoupper($parts[4] ?: 'EUR');
            $rate = $this->currency_to_eur_rate($currency);
            $amount_eur = round(floatval($price) * $rate, 2);
            $candidates[] = [
                'service' => $service,
                'country' => $row_country,
                'weight_to_kg' => floatval($weight_to),
                'price' => floatval($price),
                'currency' => $currency,
                'amount_eur' => $amount_eur,
                'delivery_days' => $parts[5] ?? '',
                'zone' => $parts[6] ?? '',
                'method' => $parts[7] ?? '',
                'source' => 'settings_textarea',
            ];
        }
        usort($candidates, function($a, $b) use ($mode) {
            if ($mode === 'auto_fastest') {
                $ad = intval(preg_replace('/[^0-9].*/', '', (string)$a['delivery_days'])) ?: 999;
                $bd = intval(preg_replace('/[^0-9].*/', '', (string)$b['delivery_days'])) ?: 999;
                if ($ad !== $bd) return $ad <=> $bd;
            }
            if ($a['amount_eur'] != $b['amount_eur']) return $a['amount_eur'] <=> $b['amount_eur'];
            return $a['weight_to_kg'] <=> $b['weight_to_kg'];
        });
        return $candidates;
    }

    private function filter_shipping_candidates_by_weight_bracket($candidates, $weight_kg) {
        if (!$candidates) return [];
        $weight_kg = max(0.001, (float)$weight_kg);
        $best = [];
        foreach ($candidates as $candidate) {
            $to = isset($candidate['weight_to_kg']) ? (float)$candidate['weight_to_kg'] : 0.0;
            if ($to > 0 && ($to + 0.0001) < $weight_kg) continue;

            // One final tariff row per real delivery option.
            // Different weight rows of the same service/method must not be shown as separate customer choices.
            $bucket = implode('|', [
                strtolower(trim((string)($candidate['service'] ?? ''))),
                strtolower(trim((string)($candidate['method'] ?? ''))),
                strtolower(trim((string)($candidate['country'] ?? ''))),
                strtolower(trim((string)($candidate['zone'] ?? ''))),
                strtolower(trim((string)($candidate['delivery_days'] ?? ''))),
                strtolower(trim((string)($candidate['source'] ?? ''))),
            ]);

            if (!isset($best[$bucket])) {
                $best[$bucket] = $candidate;
                continue;
            }

            $old_to = isset($best[$bucket]['weight_to_kg']) ? (float)$best[$bucket]['weight_to_kg'] : 999999;
            $new_to = $to > 0 ? $to : 999999;
            $old_amount = isset($best[$bucket]['amount_eur']) ? (float)$best[$bucket]['amount_eur'] : 999999;
            $new_amount = isset($candidate['amount_eur']) ? (float)$candidate['amount_eur'] : 999999;

            // Prefer the smallest weight bracket that still covers the parcel; if equal, cheaper price.
            if ($new_to < $old_to || (abs($new_to - $old_to) < 0.0001 && $new_amount < $old_amount)) {
                $best[$bucket] = $candidate;
            }
        }
        return array_values($best);
    }

    private function sort_shipping_candidates($candidates) {
        $s = self::settings();
        $mode = strtolower(trim((string)($s['shipping_service_mode'] ?? 'auto_cheapest')));
        usort($candidates, function($a, $b) use ($mode) {
            if ($mode === 'auto_fastest') {
                $ad = intval(preg_replace('/[^0-9].*/', '', (string)($a['delivery_days'] ?? ''))) ?: 999;
                $bd = intval(preg_replace('/[^0-9].*/', '', (string)($b['delivery_days'] ?? ''))) ?: 999;
                if ($ad !== $bd) return $ad <=> $bd;
            }
            if (($a['amount_eur'] ?? 0) != ($b['amount_eur'] ?? 0)) return ($a['amount_eur'] ?? 0) <=> ($b['amount_eur'] ?? 0);
            return ($a['weight_to_kg'] ?? 0) <=> ($b['weight_to_kg'] ?? 0);
        });
        return $candidates;
    }

    private function shipping_option_key($candidate) {
        return substr(md5(implode('|', [
            $candidate['service'] ?? '', $candidate['method'] ?? '', $candidate['country'] ?? '', $candidate['zone'] ?? '',
            $candidate['weight_to_kg'] ?? '', $candidate['price'] ?? '', $candidate['currency'] ?? '', $candidate['delivery_days'] ?? '',
            $candidate['amount_eur'] ?? '', $candidate['source'] ?? ''
        ])), 0, 16);
    }

    private function decorate_shipping_options($matches, $limit = 5) {
        if (!$matches) return [];
        $matches = $this->sort_shipping_candidates($matches);
        $unique = [];
        foreach ($matches as $m) {
            $bucket = trim(
                ($m['service'] ?? '') . '|' .
                ($m['method'] ?? '') . '|' .
                ($m['delivery_days'] ?? '') . '|' .
                number_format((float)($m['amount_eur'] ?? $m['price'] ?? 0), 2, '.', '')
            );
            if (isset($unique[$bucket])) continue;
            $m['key'] = $this->shipping_option_key($m);
            $m['label'] = $this->shipping_option_label($m);
            $m['amount'] = number_format((float)($m['amount_eur'] ?? 0), 2, '.', '');
            $unique[$bucket] = $m;
            if (count($unique) >= $limit) break;
        }
        return array_values($unique);
    }

    private function shipping_option_label($candidate) {
        $service = strtolower((string)($candidate['service'] ?? ''));
        $method = trim((string)($candidate['method'] ?? ''));
        $days = trim((string)($candidate['delivery_days'] ?? ''));
        $name = $service === 'nova_global' ? 'Nova Global' : ($service === 'nova_post' ? 'Nova Post' : ($service === 'ukrposhta' ? 'Ukrposhta' : ucwords(str_replace('_', ' ', $service))));
        if ($method !== '') $name .= ' / ' . ucwords(str_replace(['_', '-'], ' ', $method));
        if ($days !== '') $name .= ' · ' . $days . ' days';
        return $name;
    }

    private function structured_shipping_options($country, $weight_kg) {
        $s = self::settings();
        $limit = max(1, min(10, absint($s['shipping_option_limit'] ?? 5)));
        $matches = array_merge(
            $this->db_shipping_candidates($country, $weight_kg),
            $this->structured_shipping_candidates($country, $weight_kg)
        );
        $matches = $this->filter_shipping_candidates_by_weight_bracket($matches, $weight_kg);
        return $this->decorate_shipping_options($matches, $limit);
    }

    private function lookup_structured_shipping_rate($country, $weight_kg) {
        $options = $this->structured_shipping_options($country, $weight_kg);
        return $options ? $options[0] : null;
    }

    private function shipping_options_for_order($local_id) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        if (($s['shipping_enabled'] ?? '1') !== '1') return [];
        $country = trim((string)($d['country'] ?? ''));
        $weight = $this->order_shipping_weight_kg($local_id);
        $options = $this->structured_shipping_options($country, $weight);
        if (!$options) {
            $fallback = $this->lookup_fallback_shipping_rate($country);
            if ($fallback === null) $fallback = $this->default_shipping_amount_eur();
            if ($fallback > 0) {
                $candidate = ['service'=>'fallback','method'=>'manual','country'=>$country,'weight_to_kg'=>$weight,'price'=>$fallback,'currency'=>'EUR','amount_eur'=>round($fallback,2),'delivery_days'=>'','zone'=>'','source'=>'fallback'];
                $candidate['key'] = $this->shipping_option_key($candidate);
                $candidate['label'] = 'Standard delivery';
                $candidate['amount'] = number_format((float)$candidate['amount_eur'], 2, '.', '');
                $options[] = $candidate;
            }
        }
        return $options;
    }

    private function selected_shipping_candidate($local_id) {
        $options = $this->shipping_options_for_order($local_id);
        if (!$options) return null;
        $selected_key = (string)get_post_meta($local_id, 'shipping_selected_key', true);
        foreach ($options as $opt) {
            if (!empty($opt['key']) && hash_equals((string)$opt['key'], $selected_key)) return $opt;
        }
        return $options[0];
    }

    private function lookup_fallback_shipping_rate($country) {
        $s = self::settings();
        $needle = $this->normalize_country_name($country);
        if ($needle === '') return null;
        $rates = preg_split('/\r\n|\r|\n/', (string)($s['shipping_rates_eur'] ?? ''));
        foreach ($rates as $line) {
            if (strpos($line, '=') === false) continue;
            [$c, $v] = array_map('trim', explode('=', $line, 2));
            if ($c === '') continue;
            if ($this->normalize_country_name($c) === $needle) {
                $price = str_replace(',', '.', (string)$v);
                return is_numeric($price) ? floatval($price) : null;
            }
        }
        return null;
    }

    private function default_shipping_amount_eur() {
        $s = self::settings();
        $default = str_replace(',', '.', (string)($s['shipping_default_eur'] ?? 0));
        return is_numeric($default) ? max(0, floatval($default)) : 0;
    }

    private function shipping_data($local_id) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        if (($s['shipping_enabled'] ?? '1') !== '1') {
            $weight = $this->order_shipping_weight_kg($local_id);
            return ['country'=>$d['country'] ?? '', 'amount'=>'0.00', 'currency'=>'EUR', 'source'=>'disabled', 'weight_kg'=>$weight];
        }
        $country = trim((string)($d['country'] ?? ''));
        $weight = $this->order_shipping_weight_kg($local_id);

        // First check the editable Nova Post fallback table from the admin panel.
        // If the customer typed a country with a mistake or a country that is not in the dropdown,
        // do not send an uncertain country to the API; use the admin default shipping price instead.
        $fallback_amount = $this->lookup_fallback_shipping_rate($country);
        $selected = $this->selected_shipping_candidate($local_id);
        $country_is_known = ($fallback_amount !== null || $selected !== null);

        $amount = null;
        $source = '';
        if ($selected !== null) {
            $amount = (float)$selected['amount_eur'];
            $source = 'Selected shipping: ' . ($selected['label'] ?? ($selected['service'] ?? ''));
            if (!empty($selected['source'])) $source .= ' / ' . $selected['source'];
            update_post_meta($local_id, 'shipping_selected_key', $selected['key'] ?? '');
            update_post_meta($local_id, 'shipping_selected_label', $selected['label'] ?? '');
            update_post_meta($local_id, 'shipping_selected_service', $selected['service'] ?? '');
            update_post_meta($local_id, 'shipping_selected_method', $selected['method'] ?? '');
            update_post_meta($local_id, 'shipping_delivery_days', $selected['delivery_days'] ?? '');
        } elseif ($country_is_known) {
            $amount = $this->nova_post_api_shipping_amount($country, $local_id);
            $source = $amount !== null ? 'Nova Post API' : '';
        }

        if ($amount === null) {
            if ($fallback_amount !== null) {
                $amount = $fallback_amount;
                $source = 'Legacy fallback country tariff table';
            } else {
                $amount = $this->default_shipping_amount_eur();
                $source = $amount > 0 ? 'Fallback default tariff for unknown country' : 'not set: unknown country and default shipping is 0';
            }
        }

        if ($amount < 0) $amount = 0;
        $amount = round($amount, 2);
        update_post_meta($local_id, 'shipping_cost_eur', number_format($amount, 2, '.', ''));
        update_post_meta($local_id, 'shipping_source', $source);
        update_post_meta($local_id, 'shipping_weight_kg', $weight);
        return ['country'=>$country, 'amount'=>number_format($amount, 2, '.', ''), 'currency'=>'EUR', 'source'=>$source, 'weight_kg'=>$weight, 'selected_key'=>get_post_meta($local_id, 'shipping_selected_key', true), 'selected_label'=>get_post_meta($local_id, 'shipping_selected_label', true), 'delivery_days'=>get_post_meta($local_id, 'shipping_delivery_days', true)];
    }

    private function bank_total_data($local_id) {
        $d = $this->get_order_data($local_id);
        $shipping = $this->shipping_data($local_id);
        $base = round(floatval($d['price_eur']), 2);
        $ship = round(floatval($shipping['amount'] ?? 0), 2);
        return ['base'=>number_format($base,2,'.',''), 'shipping'=>number_format($ship,2,'.',''), 'total'=>number_format(round($base+$ship,2),2,'.',''), 'currency'=>'EUR'];
    }

    private function card_fee_percent_for_provider($provider) {
        $s = self::settings();
        $key = ($provider === 'wayforpay') ? 'wayforpay_card_fee_percent' : 'monobank_card_fee_percent';
        $percent = floatval(str_replace(',', '.', (string)($s[$key] ?? 0)));
        if ($percent < 0) $percent = 0;
        if ($percent > 20) $percent = 20;
        return $percent;
    }

    private function card_fee_data($local_id, $provider = '') {
        $d = $this->get_order_data($local_id);
        $provider = $provider ?: (get_post_meta($local_id, 'payment_provider', true) ?: $this->card_provider_for_order($local_id));
        $shipping = $this->shipping_data($local_id);
        $product = round(floatval($d['price_eur']), 2);
        $ship = round(floatval($shipping['amount'] ?? 0), 2);
        $base = round($product + $ship, 2);
        $percent = $this->card_fee_percent_for_provider($provider);
        // Reverse calculation: if provider keeps 2% from charged amount, customer pays base/(1-0.02).
        $total = ($percent > 0 && $percent < 100) ? round($base / (1 - ($percent / 100)), 2) : $base;
        $fee = round($total - $base, 2);
        return [
            'provider' => $provider,
            'percent' => number_format($percent, 2, '.', ''),
            'product' => number_format($product, 2, '.', ''),
            'shipping' => number_format($ship, 2, '.', ''),
            'base' => number_format($base, 2, '.', ''),
            'fee' => number_format($fee, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'currency' => 'EUR',
        ];
    }

    private function keycrm_add_payment($local_id, $status, $description) {
        $s = self::settings(); $d=$this->get_order_data($local_id);
        $provider = get_post_meta($local_id, 'payment_provider', true) ?: 'monobank';
        if ($status === 'paid') {
            $method = ($provider === 'wayforpay') ? ($s['keycrm_payment_method_wayforpay'] ?? '8') : ($s['keycrm_payment_method_card'] ?? '');
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
    private function keycrm_update_order_comment($local_id, $comment) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        if (empty($d['order_id'])) return;
        $this->keycrm_request('PUT', '/order/' . $d['order_id'], ['buyer_comment' => $comment], $s);
    }
    private function ensure_keycrm_order_after_successful_card_payment($local_id) {
        // Create KeyCRM only once and only after the card provider confirms payment.
        // Never reuse browser/local buyer markers as KeyCRM buyer.id.
        $existing_order_id = absint(get_post_meta($local_id, 'order_id', true));
        if ($existing_order_id && get_post_meta($local_id, 'keycrm_after_payment_done', true) === '1') {
            return $existing_order_id;
        }

        $s = self::settings();
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
        update_post_meta($local_id, 'keycrm_after_payment_done', '1');
        $this->remember_keycrm_checkout_marker($local_id);
        wp_update_post(['ID'=>$local_id, 'post_title'=>'Order #' . $order_id . ' - ' . ($d['full_name'] ?? '')]);
        return $order_id;
    }

    private function queue_deferred_payment_finalizer($local_id, $invoice_id = '', $source = '') {
        $local_id = absint($local_id);
        if (!$local_id) return;
        update_post_meta($local_id, 'deferred_finalizer_last_source', sanitize_text_field((string)$source));
        update_post_meta($local_id, 'deferred_finalizer_last_invoice', sanitize_text_field((string)$invoice_id));
        // Schedule only if it is not already queued. Webhook may also run this independently.
        if (function_exists('wp_next_scheduled') && !wp_next_scheduled('yo_checkout_deferred_payment_finalizer', [$local_id, (string)$invoice_id])) {
            wp_schedule_single_event(time() + 5, 'yo_checkout_deferred_payment_finalizer', [$local_id, (string)$invoice_id]);
        }
    }

    public function deferred_payment_finalizer($local_id, $invoice_id = '') {
        $local_id = absint($local_id);
        if (!$local_id || get_post_type($local_id) !== self::CPT) return;
        try {
            $this->process_successful_card_payment($local_id, sanitize_text_field((string)$invoice_id));
        } catch (Throwable $e) {
            update_post_meta($local_id, 'deferred_finalizer_error', $e->getMessage());
            $this->append_auto_hide_log('[' . current_time('mysql') . '] Deferred finalizer fatal prevented for local order #' . $local_id . ': ' . $e->getMessage() . "\n");
        }
    }

    private function finalize_after_payment_if_needed($local_id, $invoice_id = '', $source = '') {
        $local_id = absint($local_id);
        if (!$local_id) return;
        if (get_post_meta($local_id, 'paid', true) !== '1') return;
        if (get_post_meta($local_id, 'auto_hide_sold_done', true) === '1') return;
        $this->append_auto_hide_log('[' . current_time('mysql') . '] Finalizer check from ' . sanitize_text_field($source) . ' for local order #' . $local_id . ".
");
        $this->auto_hide_sold_items_after_payment($local_id);
    }

    private function process_successful_card_payment($local_id, $invoice_id) {
        $local_id = absint($local_id);
        $invoice_id = sanitize_text_field((string)$invoice_id);
        if (!$local_id) return;

        if (!add_post_meta($local_id, '_yo_payment_processing_lock', time() . ':' . $invoice_id, true)) {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] Payment finalizer lock is active for local order #' . $local_id . ". Skipped duplicate run.
");
            if (get_post_meta($local_id, 'paid', true) === '1' || get_post_meta($local_id, 'paid_email_sent', true) === '1') {
                if (get_post_meta($local_id, 'paid', true) !== '1') update_post_meta($local_id, 'paid', '1');
                $this->finalize_after_payment_if_needed($local_id, $invoice_id, 'processing_lock_side_effects');
            }
            return;
        }

        try {
            // v4.0.23: the polling AJAX marks the order as paid before queueing this finalizer
            // so Step 4 can open immediately. Therefore this method must NOT skip KeyCRM/email
            // when paid=1. Run every side effect independently with its own idempotent marker.
            $s = self::settings();
            $provider = get_post_meta($local_id, 'payment_provider', true) ?: 'monobank';
            $label = $provider === 'wayforpay' ? 'WayForPay' : 'Monobank';

            if (get_post_meta($local_id, 'paid', true) !== '1') {
                update_post_meta($local_id, 'paid', '1');
            }
            if (!get_post_meta($local_id, 'paid_at', true)) {
                update_post_meta($local_id, 'paid_at', time());
            }

            $created_order_id = absint(get_post_meta($local_id, 'order_id', true));
            if (get_post_meta($local_id, 'keycrm_after_payment_done', true) !== '1') {
                $created = $this->ensure_keycrm_order_after_successful_card_payment($local_id);
                if (is_wp_error($created)) {
                    update_post_meta($local_id, 'keycrm_after_payment_error', wp_json_encode($created->get_error_data() ?: $created->get_error_message()));
                    $created_order_id = 0;
                } else {
                    $created_order_id = absint($created);
                }
            }

            if ($created_order_id) {
                if (get_post_meta($local_id, 'keycrm_paid_payment_added', true) !== '1') {
                    $this->keycrm_add_payment($local_id, 'paid', 'Paid via ' . $label . ' transaction ' . $invoice_id);
                    update_post_meta($local_id, 'keycrm_paid_payment_added', '1');
                }
                if (get_post_meta($local_id, 'keycrm_paid_status_set', true) !== '1') {
                    $this->keycrm_request('PUT', '/order/' . $created_order_id, ['status_id'=>intval($s['keycrm_status_paid'])], $s);
                    update_post_meta($local_id, 'keycrm_paid_status_set', '1');
                }
            }

            if (get_post_meta($local_id, 'paid_email_sent', true) !== '1') {
                $this->send_paid_email($local_id);
            }

            if (get_post_meta($local_id, 'auto_hide_sold_done', true) !== '1') {
                try {
                    $this->auto_hide_sold_items_after_payment($local_id);
                } catch (Throwable $e) {
                    $this->append_auto_hide_log('[' . current_time('mysql') . '] Auto-hide fatal prevented for local order #' . $local_id . ': ' . $e->getMessage() . "\n");
                    update_post_meta($local_id, 'auto_hide_sold_error', $e->getMessage());
                }
            }
        } catch (Throwable $e) {
            // The browser must still receive a JSON response after a successful payment.
            // Store the error for debugging, but do not let a KeyCRM/email/autohide problem break Step 4.
            update_post_meta($local_id, 'payment_finalizer_error', $e->getMessage());
            $this->append_auto_hide_log('[' . current_time('mysql') . '] Payment finalizer fatal prevented for local order #' . $local_id . ': ' . $e->getMessage() . "\n");
            if (get_post_meta($local_id, 'paid', true) !== '1' && (get_post_meta($local_id, 'paid_email_sent', true) === '1' || get_post_meta($local_id, 'keycrm_after_payment_done', true) === '1')) {
                update_post_meta($local_id, 'paid', '1');
                if (!get_post_meta($local_id, 'paid_at', true)) update_post_meta($local_id, 'paid_at', time());
            }
        } finally {
            delete_post_meta($local_id, '_yo_payment_processing_lock');
        }
    }

    private function auto_hide_sold_items_after_payment($local_id) {
        $s = self::settings();
        $this->append_auto_hide_log('[' . current_time('mysql') . '] Auto-hide started for local order #' . absint($local_id) . ".
");
        if (($s['auto_hide_sold_enabled'] ?? '1') !== '1') {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] Order #' . (get_post_meta($local_id, 'order_id', true) ?: $local_id) . ": auto-hide is disabled in settings.
");
            return;
        }
        $d = $this->get_order_data($local_id);
        $items = $this->cart_items_from_order_data($d);
        $titles = [];
        foreach ($items as $item) {
            $title = trim(wp_strip_all_tags((string)($item['title'] ?? '')));
            if ($title !== '') $titles[] = $title;
        }
        $titles = array_values(array_unique($titles));
        if (!$titles) {
            $this->append_auto_hide_log('[' . current_time('mysql') . '] Order #' . (get_post_meta($local_id, 'order_id', true) ?: $local_id) . ": no product title found for auto-hide. Builder status was not changed.\n");
            return;
        }

        // Frontend fallback is optional. It is not used for the real YOOtheme Builder status change.
        if (($s['auto_hide_sold_frontend_fallback'] ?? '0') === '1') {
            $sold = get_option('yo_checkout_sold_hidden_titles', []);
            if (!is_array($sold)) $sold = [];
            foreach ($titles as $title) if (!in_array($title, $sold, true)) $sold[] = $title;
            update_option('yo_checkout_sold_hidden_titles', array_values($sold), false);
        }

        $page_id = absint($s['auto_hide_sold_page_id'] ?? 0);
        if (!$page_id) $page_id = absint(get_option('page_on_front'));
        $log = '[' . current_time('mysql') . '] Order #' . (get_post_meta($local_id, 'order_id', true) ?: $local_id) . ': ' . implode(' | ', $titles) . "\n";
        if (!$page_id || get_post_type($page_id) === false) {
            $this->append_auto_hide_log($log . "No valid YOOtheme page ID. Builder status was not changed.\n");
            return;
        }

        $backup_key = '_yo_checkout_autohide_backup_order_' . absint($local_id);
        if (!get_post_meta($page_id, $backup_key, true)) {
            update_post_meta($page_id, $backup_key, [
                'created_at' => current_time('mysql'),
                'post_content' => get_post_field('post_content', $page_id),
                'meta' => get_post_meta($page_id),
            ]);
        }

        $changed_any = false;
        $post = get_post($page_id);
        if ($post) {
            $content = (string)$post->post_content;
            $new_content = $this->disable_titles_in_yootheme_storage_string($content, $titles, $changed_content);
            if ($changed_content && $new_content !== $content) {
                wp_update_post(wp_slash(['ID'=>$page_id, 'post_content'=>$new_content]));
                $changed_any = true;
                $log .= "Updated post_content.\n";
            }
        }

        $meta = get_post_meta($page_id);
        foreach ($meta as $meta_key => $values) {
            if (strpos($meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            foreach ((array)$values as $value) {
                $changed_meta = false;
                $new_value = $this->disable_titles_in_yootheme_storage_mixed($value, $titles, $changed_meta);
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
        }
        $this->append_auto_hide_log($log);
        if (function_exists('clean_post_cache')) clean_post_cache($page_id);
    }

    private function append_auto_hide_log($entry) {
        $saved = get_option(self::OPT, []);
        $old = (string)($saved['auto_hide_sold_log'] ?? '');
        $saved['auto_hide_sold_log'] = mb_substr($entry . $old, 0, 5000);
        update_option(self::OPT, $saved, false);
    }

    private function decode_loose_unicode_sequences($text) {
        $text = (string)$text;
        // Handle both real JSON unicode escapes (\u00ab) and broken/plain variants (u00ab) that can appear after sanitizing/logging.
        // Fixed: literal backslash-u requires /\\\\u.../ in the PHP regex string.
        $text = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m){
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
        }, $text);
        $text = preg_replace_callback('/(^|[^A-Za-z0-9_])u([0-9a-fA-F]{4})/', function($m){
            return $m[1] . mb_convert_encoding(pack('H*', $m[2]), 'UTF-8', 'UTF-16BE');
        }, $text);
        return $text;
    }

    private function normalize_match_text($text) {
        $text = $this->decode_loose_unicode_sequences((string)$text);
        $text = wp_strip_all_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = $this->decode_loose_unicode_sequences($text);
        $text = str_replace(['’','‘','`','´','“','”','«','»','–','—','−','&nbsp;'], ["'","'","'","'",'"','"','"','"','-','-','-',' '], $text);
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
            if (preg_match('/^\d{2,3}$/', $w)) continue;
            if (mb_strlen($w) < 3) continue;
            $words[$w] = true;
        }
        return array_keys($words);
    }

    private function title_aliases_for_matching($title) {
        $raw = $this->decode_loose_unicode_sequences((string)$title);
        $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $aliases = [];
        $norm = $this->normalize_match_text($raw);
        if ($norm !== '') $aliases[] = $norm;

        // Match model name inside any common quote type, e.g. “Dynamic Pulse”, «Dynamic Pulse», "Dynamic Pulse".
        if (preg_match_all('/["«“]([^"»”]{3,})["»”]/u', $raw, $qm)) {
            foreach ($qm[1] as $q) {
                $q_norm = $this->normalize_match_text($q);
                if ($q_norm !== '') $aliases[] = $q_norm;
            }
        }

        // If the quote symbols were lost, use important adjacent words as a loose model-name alias.
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

        foreach ($titles as $title) {
            foreach ($this->title_aliases_for_matching($title) as $alias) {
                if ($alias !== '' && mb_strlen($alias) >= 3 && strpos($hay, $alias) !== false) return true;
            }

            // Strong loose match: at least two meaningful model words from the order title appear in the YOOtheme item title/content.
            $words = $this->important_title_words($title);
            $hits = 0;
            foreach ($words as $w) {
                if (strpos($hay, $w) !== false) $hits++;
            }
            if ($hits >= 2) return true;

            // Fallback for one-word model names: require the word plus the height range.
            if ($hits >= 1 && preg_match('/\b(\d{2,3})\s*[-–—]\s*(\d{2,3})\b/u', (string)$title, $hm)) {
                $height = $hm[1] . '-' . $hm[2];
                if (strpos($hay, $height) !== false) return true;
            }
        }
        return false;
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

    private function disable_titles_in_yootheme_storage_string($value, $titles, &$changed = false) {
        $changed = false;
        $raw = (string)$value;
        $trim = trim($raw);
        if ($trim === '') return $value;

        // Common YOOtheme storage: the whole meta value/content is JSON.
        if ($trim[0] === '{' || $trim[0] === '[') {
            $decoded = json_decode($trim, true);
            if (is_array($decoded)) {
                $this->disable_titles_in_yootheme_node($decoded, $titles, $changed);
                if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // Some exports/storage variants may contain slashed JSON.
            $unslashed = wp_unslash($trim);
            if ($unslashed !== $trim) {
                $decoded = json_decode($unslashed, true);
                if (is_array($decoded)) {
                    $this->disable_titles_in_yootheme_node($decoded, $titles, $changed);
                    if ($changed) return wp_json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }
        }

        // YOOtheme stores the editable Builder layout as JSON inside one large HTML comment.
        // Important: that JSON may contain inner HTML comments like <!-- LEFT: Units --> inside strings.
        // Therefore a normal /<!--(.*?)-->/ regex breaks too early. We extract the Builder
        // comment by locating the layout marker and the last closing "-->".
        $new = $this->disable_titles_in_yootheme_builder_comment($raw, $titles, $changed);

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

        // Move back to the opening brace if marker includes whitespace after it.
        while ($pos > 0 && $content[$pos] !== '{') $pos--;
        if ($content[$pos] !== '{') return false;

        // If this is normal JSON, find the matching closing brace while respecting strings.
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

        // First use a balanced JSON extractor. This is safer than HTML comment parsing because
        // YOOtheme item fields may contain strings with inner HTML comments like <!-- LEFT: Units -->.
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

        // Legacy comment fallback.
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

    private function remove_rendered_product_li_cards_by_titles($html, $titles, &$changed = false) {
        $changed = false;
        $html = (string)$html;
        if ($html === '' || stripos($html, '<li') === false) return $html;

        $out = '';
        $offset = 0;
        $len = strlen($html);

        while (preg_match('/<li\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $pos = $start + strlen($m[0][0]);
            $depth = 1;
            $end = null;

            while ($depth > 0 && preg_match('/<\/?li\b[^>]*>/i', $html, $tag, PREG_OFFSET_CAPTURE, $pos)) {
                $tag_text = $tag[0][0];
                $tag_pos = $tag[0][1];
                if (preg_match('/^<\/li/i', $tag_text)) {
                    $depth--;
                    if ($depth === 0) {
                        $end = $tag_pos + strlen($tag_text);
                        break;
                    }
                } else {
                    $depth++;
                }
                $pos = $tag_pos + strlen($tag_text);
            }

            if ($end === null || $end <= $start) {
                // Malformed HTML: keep the rest unchanged.
                break;
            }

            $block = substr($html, $start, $end - $start);
            $is_product_card = (stripos($block, '<h3') !== false && stripos($block, '<img') !== false)
                || stripos($block, 'yo-price') !== false
                || stripos($block, 'wp-content/uploads') !== false;
            $must_remove = $is_product_card && $this->title_matches_sold_item($block, $titles);

            $out .= substr($html, $offset, $start - $offset);
            if ($must_remove) {
                $changed = true;
                $out .= "
<!-- YOleotard checkout: sold product card removed from generated HTML -->
";
            } else {
                $out .= $block;
            }
            $offset = $end;
        }

        if (!$changed) return $html;
        return $out . substr($html, $offset);
    }

    private function disable_titles_in_yootheme_node(&$node, $titles, &$changed) {
        $this->disable_titles_in_yootheme_node_deep($node, $titles, $changed, 0);
    }

    private function is_yootheme_root_or_layout_node($node) {
        if (!is_array($node)) return false;
        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        return in_array($type, ['layout','section','row','column','grid','masonry','gallery','slider','slideshow'], true);
    }

    private function is_yootheme_disable_candidate($node, $depth) {
        if (!is_array($node) || $depth <= 0) return false;
        if ($this->is_yootheme_root_or_layout_node($node)) return false;

        // The product cards on the front page are YOOtheme Grid items.
        // Restrict automatic disabling to items/cards so a matched title cannot disable the whole Grid/Section.
        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        if (in_array($type, ['grid_item','gallery_item','slider_item','slideshow_item'], true)) return true;

        return false;
    }

    private function apply_yootheme_disabled_status(&$node) {
        if (!is_array($node)) return;
        if (!isset($node['props']) || !is_array($node['props'])) $node['props'] = [];

        // YOOtheme Pro stores the Builder checkbox "Status → Disable element" as props.status = "disabled".
        // Do not delete the item: keep it editable in Builder, but remove it from publication.
        $node['props']['status'] = 'disabled';
        $node['props']['_yo_checkout_auto_hidden'] = current_time('mysql');
    }

    private function yootheme_node_self_matches_title($node, $titles) {
        if (!is_array($node)) return false;
        foreach (['title','name','content','text','meta','description','label'] as $key) {
            if (isset($node[$key]) && is_string($node[$key]) && $this->title_matches_sold_item($node[$key], $titles)) return true;
        }
        if (isset($node['props']) && is_array($node['props'])) {
            foreach (['title','name','content','text','meta','description','label'] as $key) {
                if (isset($node['props'][$key]) && is_string($node['props'][$key]) && $this->title_matches_sold_item($node['props'][$key], $titles)) return true;
            }
        }
        return false;
    }

    private function disable_titles_in_yootheme_node_deep(&$node, $titles, &$changed, $depth = 0) {
        if (!is_array($node)) return false;

        $self_match = $this->yootheme_node_self_matches_title($node, $titles);
        $child_match = false;

        foreach ($node as $key => &$child) {
            if (!is_array($child)) continue;

            // YOOtheme Grid/Gallery items are often stored as children/items without repeating the whole title at the parent level.
            // If a descendant matches, disable the closest element/item parent, not the whole Grid/Section.
            if ($this->disable_titles_in_yootheme_node_deep($child, $titles, $changed, $depth + 1)) {
                $child_match = true;
            }
        }
        unset($child);

        $matches = $self_match || $child_match;
        if ($matches && $this->is_yootheme_disable_candidate($node, $depth)) {
            // Do not disable broad containers such as Grid/Section; only the matching item/element.
            $this->apply_yootheme_disabled_status($node);
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
        return in_array($status, ['disabled','disable','0','false','hidden'], true);
    }

    private function yootheme_product_availability_from_node($node, $title) {
        if (!is_array($node)) return null;

        $titles = [$title];
        $type = isset($node['type']) && is_string($node['type']) ? strtolower($node['type']) : '';
        $self_match = $this->yootheme_node_self_matches_title($node, $titles);

        if ($self_match && in_array($type, ['grid_item','gallery_item','slider_item','slideshow_item'], true)) {
            return !$this->is_yootheme_node_disabled($node);
        }

        foreach ($node as $child) {
            if (!is_array($child)) continue;
            $found = $this->yootheme_product_availability_from_node($child, $title);
            if ($found !== null) return $found;
        }

        return null;
    }

    private function is_yootheme_product_title_available($page_id, $title) {
        $title = (string)$title;
        if ($title === '') return false;

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
                    $available = $this->yootheme_product_availability_from_node($decoded, $title);
                    if ($available !== null) return (bool)$available;
                }
            }

            // If Builder JSON is not available for some reason, use the published HTML as a safe fallback.
            // A product is considered available only if a matching rendered card is still present in post_content.
            if ($this->title_matches_sold_item($content, [$title]) && stripos($content, '<li') !== false) return true;
        }

        // Also inspect YOOtheme-related page meta because some layouts can be stored there.
        $meta = get_post_meta($page_id);
        foreach ($meta as $meta_key => $values) {
            if (strpos((string)$meta_key, '_yo_checkout_autohide_backup_') === 0) continue;
            foreach ((array)$values as $value) {
                $found = $this->yootheme_product_availability_from_storage_value($value, $title);
                if ($found !== null) return (bool)$found;
            }
        }

        return false;
    }

    private function yootheme_product_availability_from_storage_value($value, $title) {
        if (is_array($value)) {
            return $this->yootheme_product_availability_from_node($value, $title);
        }
        if (!is_string($value) || trim($value) === '') return null;
        $raw = trim($value);

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $maybe = maybe_unserialize($raw);
            if (is_array($maybe)) $decoded = $maybe;
        }
        if (is_array($decoded)) return $this->yootheme_product_availability_from_node($decoded, $title);

        if (strpos($raw, '"type":"layout"') !== false || strpos($raw, '"grid_item"') !== false || strpos($raw, '"type"') !== false) {
            $segment = $this->find_yootheme_layout_json_segment($raw);
            if ($segment && !empty($segment['json'])) {
                $decoded = json_decode(trim($segment['json']), true);
                if (is_array($decoded)) return $this->yootheme_product_availability_from_node($decoded, $title);
            }
        }
        return null;
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
      .replace(/[’‘`´]/g,"'")
      .replace(/[“”«»]/g,'"')
      .replace(/[–—−]/g,'-')
      .replace(/&nbsp;/g,' ')
      .replace(/[^a-z0-9а-яіїєґ'" -]+/gi,' ')
      .replace(/\s+/g,' ')
      .trim();
  }
  function words(s){ return norm(s).split(' ').filter(function(w){ return w.length > 2 && !/^(new|for|the|and|height|leotard|author|author's|authors|dress|figure|skating)$/.test(w); }); }
  function profile(title){
    title = decodeEscapes(title);
    var n = norm(title);
    var quoted = [];
    var re = /["«“]([^"»”]{3,})["»”]/g, m;
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
    // For YOOtheme Grid the visible card is usually .el-item; for the generated fallback HTML it is <li>.
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

    public function check_unpaid_order($local_id) {
        if (!$local_id || get_post_meta($local_id,'paid',true)==='1') return;
        $s=self::settings(); $d=$this->get_order_data($local_id);
        if (!empty($d['order_id']) && !empty($s['keycrm_status_cancelled'])) $this->keycrm_request('PUT','/order/'.$d['order_id'],['status_id'=>intval($s['keycrm_status_cancelled'])],$s);
    }

    private function is_sepa_country($country) {
        $s=self::settings(); $list=array_map('trim', explode(',', wp_strip_all_tags($s['sepa_countries'])));
        foreach($list as $c) if (strcasecmp($c, trim($country))===0) return true;
        return false;
    }
    private function bank_details_for_country($country) {
        $s=self::settings(); $type=$this->is_sepa_country($country)?'sepa':'swift';
        return ['type'=>strtoupper($type),'receiver'=>$s[$type.'_receiver'],'iban'=>$s[$type.'_iban'],'swift'=>$s[$type.'_swift'],'bank'=>$s[$type.'_bank'],'bank_address'=>$s[$type.'_bank_address']];
    }
    private function invoice_number($order_id) { $s=self::settings(); return $s['invoice_prefix'] . $order_id; }

    private function generate_invoice_files($local_id) {
        $d=$this->get_order_data($local_id); $s=self::settings(); $bank=$this->bank_details_for_country($d['country']);
        $upload = wp_upload_dir(); $dir = trailingslashit($upload['basedir']) . 'yoleotard-invoices'; $url = trailingslashit($upload['baseurl']) . 'yoleotard-invoices';
        if (!wp_mkdir_p($dir)) return new WP_Error('mkdir','Could not create invoice directory');
        $safe_order = preg_replace('/[^0-9A-Za-z_-]/','', (string)$d['order_id']);
        $html_path = $dir . '/invoice-' . $safe_order . '.html'; $pdf_path = $dir . '/invoice-' . $safe_order . '.pdf';
        $html = $this->invoice_html($d,$s,$bank,false);
        file_put_contents($html_path, $html);
        $pdf_html = $this->invoice_html($d,$s,$bank,true);
        $pdf_ok = $this->dompdf_pdf($pdf_path, $pdf_html);
        $pdf_message = '';
        if (!$pdf_ok) {
            $pdf_message = $this->dompdf_autoload_path() ? 'Dompdf returned false. Please check wp-content/uploads/yoleotard-invoices write permissions and PHP error log.' : 'Dompdf is not installed. Install it from Settings → YOleotard Checkout, or use the HTML invoice link.';
        }
        return ['html_path'=>$html_path,'html_url'=>$url.'/invoice-'.$safe_order.'.html','pdf_path'=>$pdf_ok ? $pdf_path : '', 'pdf_url'=>$pdf_ok ? $url.'/invoice-'.$safe_order.'.pdf' : '', 'pdf_message'=>$pdf_message];
    }
    private function english_invoice_date($timestamp) {
        $timestamp = intval($timestamp) ?: time();
        // Do not use wp_date(), date_i18n() or strftime() here: WordPress/server locale can translate month names.
        // Manual English month map guarantees "May 14, 2026" even when the site locale is Russian/Ukrainian.
        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        $month = $months[intval(gmdate('n', $timestamp))] ?? 'May';
        return $month . ' ' . gmdate('d', $timestamp) . ', ' . gmdate('Y', $timestamp);
    }

    private function invoice_logo_src($for_pdf=false) {
        $logo_id = intval(get_theme_mod('custom_logo'));
        if (!$logo_id) {
            $site_icon_id = intval(get_option('site_icon'));
            if ($site_icon_id) $logo_id = $site_icon_id;
        }
        if (!$logo_id) return '';

        $src = wp_get_attachment_image_src($logo_id, 'full');
        if (empty($src[0])) return '';

        if (!$for_pdf) return esc_url($src[0]);

        $path = get_attached_file($logo_id);
        if (!$path || !file_exists($path) || !is_readable($path)) return esc_url($src[0]);

        $mime = wp_check_filetype($path)['type'] ?? 'image/png';
        $data = file_get_contents($path);
        if ($data === false) return esc_url($src[0]);

        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }

    private function invoice_html($d,$s,$bank,$for_pdf=false) {
        $date = $this->english_invoice_date(intval($d['created_at']) ?: time());
        $invoice = $this->invoice_number($d['order_id']);
        $final_value = floatval($d['price_eur']);
        $original_value = floatval($d['original_price_eur'] ?: $d['price_eur']);
        if ($original_value < $final_value) $original_value = $final_value;
        $discount_value = floatval($d['discount_eur']);
        if ($discount_value <= 0 && $original_value > $final_value) $discount_value = round($original_value - $final_value, 2);
        $shipping_value = round(floatval($d['shipping_cost_eur'] ?? 0), 2);
        $invoice_total_value = round($final_value + $shipping_value, 2);
        $price = number_format($final_value, 2);
        $original_price = number_format($original_value, 2);
        $discount_price = number_format($discount_value, 2);
        $currency_price = '€' . $price;
        $currency_original_price = '€' . $original_price;
        $currency_discount_price = '€' . $discount_price;
        $currency_shipping_price = '€' . number_format($shipping_value, 2);
        $currency_invoice_total = '€' . number_format($invoice_total_value, 2);
        $invoice_items = $this->cart_items_from_order_data($d);
        $invoice_rows = [];
        foreach ($invoice_items as $item) {
            $item_title = $this->clean_product_title_for_display($item['title'] ?? 'Selected leotard');
            $item_original = round(floatval($item['original_price_eur'] ?? ($item['price_eur'] ?? 0)), 2);
            $item_discount = round(floatval($item['discount_eur'] ?? 0), 2);
            $item_product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
            $item_promo_discount = round(floatval($item['promo_discount_eur'] ?? 0), 2);
            if ($item_discount <= 0 && ($item_product_discount + $item_promo_discount) > 0) {
                $item_discount = round($item_product_discount + $item_promo_discount, 2);
            }
            $item_final = round(max(0, $item_original - $item_discount), 2);
            if ($item_original <= 0) {
                $item_final = round(floatval($item['price_eur'] ?? 0), 2);
                $item_original = round($item_final + max(0, $item_discount), 2);
            }
            $invoice_rows[] = [
                'title' => $item_title,
                'qty' => 1,
                'original' => $item_original,
                'discount' => max(0, $item_discount),
                'final' => $item_final,
            ];
        }
        if (!$invoice_rows) {
            $invoice_rows[] = [
                'title' => $this->clean_product_title_for_display($d['title'] ?? 'Selected leotard'),
                'qty' => 1,
                'original' => $original_value,
                'discount' => $discount_value,
                'final' => $final_value,
            ];
        }
        $seller_addr = nl2br(esc_html($s['seller_address']));
        $bank_addr = nl2br(esc_html($bank['bank_address']));
        $description = 'Payment for ' . $s['product_purpose'] . ' by invoice #' . $invoice . ' from ' . $date;
        $logo_src = $this->invoice_logo_src($for_pdf);
        ob_start(); ?><!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Invoice <?php echo esc_html($invoice); ?></title>
<style>
    @page { size: A4; margin: <?php echo $for_pdf ? '6mm' : '14mm'; ?>; }
    * { box-sizing: border-box; }
    body { margin:0; background:<?php echo $for_pdf ? '#fff' : '#eef1f5'; ?>; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:13px; color:#202124; line-height:1.35; }
    .invoice-page { width:<?php echo $for_pdf ? 'auto' : '210mm'; ?>; min-height:<?php echo $for_pdf ? 'auto' : '297mm'; ?>; margin:<?php echo $for_pdf ? '0' : '18px auto'; ?>; background:#fff; padding:<?php echo $for_pdf ? '0' : '20mm 18mm'; ?>; box-shadow:<?php echo $for_pdf ? 'none' : '0 6px 24px rgba(0,0,0,.12)'; ?>; overflow:hidden; }
    .topline { width:100%; border-bottom:2px solid #111; padding-bottom:14px; margin-bottom:18px; }
    .brand { font-size:13px; color:#555; margin-top:4px; }
    h1 { font-size:26px; margin:0; letter-spacing:.3px; color:#111; }
    .invoice-logo { max-width:140px; max-height:58px; width:auto; height:auto; display:block; margin:0 0 8px auto; }
    h2 { font-size:18px; margin:0 0 8px; color:#111; }
    h3 { font-size:16px; margin:18px 0 8px; color:#111; }
    .date { text-align:right; white-space:nowrap; }
    .two-col { width:100%; border-collapse:separate; border-spacing:0 0; margin:16px 0; }
    .panel { border:1px solid #d7dce2; border-radius:8px; padding:12px 14px; min-height:105px; vertical-align:top; }
    .label { font-size:12px; text-transform:uppercase; letter-spacing:.04em; color:#667085; font-weight:700; margin-bottom:7px; }
    .strong { font-weight:700; color:#111; }
    table { width:100%; border-collapse:collapse; }
    .products { margin-top:8px; border:1px solid #cfd5dc; }
    .products th { background:#f3f5f8; color:#111; border-bottom:1px solid #cfd5dc; padding:10px 8px; font-size:13px; }
    .products td { padding:11px 8px; border-bottom:1px solid #e4e7eb; vertical-align:top; }
    .products tbody tr:last-child td { border-bottom:0; }
    .right { text-align:right; }
    .center { text-align:center; }
    .totals { width:45%; margin-left:auto; margin-top:14px; }
    .totals td { padding:5px 0; }
    .totals td:first-child { color:#4b5563; padding-right:18px; }
    .grand-total td { border-top:2px solid #111; padding-top:9px; font-size:16px; color:#111; }
    .payment-box { border:1px solid #cfd5dc; border-radius:8px; padding:12px 14px; background:#fbfcfe; }
    .payment-grid { width:100%; border-collapse:collapse; }
    .purpose { background:#f3f5f8; border-left:4px solid #111; padding:10px 12px; font-weight:700; margin:8px 0 12px; }
    .muted { color:#667085; }
    .footer { margin-top:24px; padding-top:12px; border-top:1px solid #d7dce2; font-size:12px; color:#667085; }
    @media print { body { background:#fff; } .invoice-page { width:auto; min-height:auto; margin:0; padding:0; box-shadow:none; } }
</style>
</head>
<body>
<div class="invoice-page">
    <table class="topline">
        <tr>
            <td style="width:70%; vertical-align:top;">
                <h1>INVOICE #<?php echo esc_html($invoice); ?></h1>
                <div class="brand">YOleotard website order № <?php echo esc_html($d['order_id']); ?></div>
            </td>
            <td style="width:30%; vertical-align:top;" class="date">
                <?php if ($logo_src): ?><img class="invoice-logo" src="<?php echo esc_attr($logo_src); ?>" alt="YOleotard logo"><?php endif; ?>
                <span class="muted">Invoice date</span><br><span class="strong"><?php echo esc_html($date); ?></span>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td class="panel" style="width:48%;">
                <div class="label">Seller</div>
                <div class="strong"><?php echo esc_html($s['seller_name']); ?></div>
                <div style="margin-top:6px;"><span class="strong">Address:</span><br><?php echo $seller_addr; ?></div>
            </td>
            <td style="width:4%; border:0;"></td>
            <td class="panel" style="width:48%;">
                <div class="label">Bill To</div>
                <div class="strong"><?php echo esc_html($d['full_name']); ?></div>
                <div style="margin-top:6px;"><?php echo esc_html($d['address']); ?><br><?php echo esc_html($d['city']); ?>, <?php echo esc_html($d['zip_code']); ?><br><?php echo esc_html($d['country']); ?></div>
                <div style="margin-top:6px;" class="muted"><?php echo esc_html($d['email']); ?> · <?php echo esc_html($d['phone']); ?></div>
            </td>
        </tr>
    </table>

    <h3>Order Items</h3>
    <table class="products">
        <thead>
            <tr>
                <th align="left">Product</th>
                <th class="center" style="width:50px;">Qty</th>
                <th class="right" style="width:95px;">Unit price</th>
                <th class="right" style="width:95px;">Discount</th>
                <th class="right" style="width:105px;">Line total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoice_rows as $row): ?>
                <tr>
                    <td><strong><?php echo esc_html($row['title']); ?></strong></td>
                    <td class="center"><?php echo esc_html($row['qty']); ?></td>
                    <td class="right">€<?php echo esc_html(number_format(floatval($row['original']), 2)); ?></td>
                    <td class="right">- €<?php echo esc_html(number_format(floatval($row['discount']), 2)); ?></td>
                    <td class="right">€<?php echo esc_html(number_format(floatval($row['final']), 2)); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Items total:</td><td class="right"><?php echo esc_html($currency_original_price); ?></td></tr>
            <tr><td><?php echo !empty($d['promo_code_applied']) ? 'Promo code discount:' : 'Discount:'; ?></td><td class="right">- <?php echo esc_html($currency_discount_price); ?></td></tr>
            <tr><td>Card payment service fee:</td><td class="right">€0.00</td></tr>
            <tr><td>Shipping:</td><td class="right"><?php echo esc_html($currency_shipping_price); ?></td></tr>
            <tr class="grand-total"><td><strong>Total:</strong></td><td class="right"><strong><?php echo esc_html($currency_invoice_total); ?></strong></td></tr>
        </table>
    </div>
    <p class="muted" style="margin:10px 0 0;text-align:right;">Card payment service fee is not added for SEPA/SWIFT invoice payments.</p>

    <h3>Payment Details — <?php echo esc_html($bank['type']); ?></h3>
    <div class="payment-box">
        <table class="payment-grid">
            <tr><td class="muted" style="width:160px; padding:3px 10px 3px 0;">Receiver</td><td class="strong"><?php echo esc_html($bank['receiver']); ?></td></tr>
            <tr><td class="muted" style="padding:3px 10px 3px 0;">IBAN / Account</td><td class="strong"><?php echo esc_html($bank['iban']); ?></td></tr>
            <tr><td class="muted" style="padding:3px 10px 3px 0;">SWIFT/BIC</td><td class="strong"><?php echo esc_html($bank['swift']); ?></td></tr>
            <?php if($bank['bank']): ?><tr><td class="muted" style="padding:3px 10px 3px 0;">Bank</td><td><?php echo esc_html($bank['bank']); ?></td></tr><?php endif; ?>
            <?php if($bank['bank_address']): ?><tr><td class="muted" style="padding:3px 10px 3px 0;">Bank address</td><td><?php echo $bank_addr; ?></td></tr><?php endif; ?>
        </table>
    </div>

    <h3>Payment Instructions</h3>
    <p>Please include the invoice number in the payment description:</p>
    <div class="purpose"><?php echo esc_html($description); ?></div>
    <p>If payment is made in parts, please specify:</p>
    <p>
        Payment for <?php echo esc_html($s['product_purpose']); ?> by invoice #<?php echo esc_html($invoice); ?> / Part 1 from <?php echo esc_html($date); ?><br>
        Payment for <?php echo esc_html($s['product_purpose']); ?> by invoice #<?php echo esc_html($invoice); ?> / Part 2 from <?php echo esc_html($date); ?><br>
        Payment for <?php echo esc_html($s['product_purpose']); ?> by invoice #<?php echo esc_html($invoice); ?> / Final from <?php echo esc_html($date); ?>
    </p>
    <div class="footer">This invoice was generated automatically after selecting bank transfer payment on the YOleotard website.</div>
</div>
</body>
</html><?php return ob_get_clean();
    }

    private function invoice_text_lines($d,$s,$bank) {
        $date = $this->english_invoice_date(intval($d['created_at']) ?: time());
        $inv = $this->invoice_number($d['order_id']);
        $shipping = round(floatval($d['shipping_cost_eur']), 2);
        $total = round(floatval($d['price_eur']) + $shipping, 2);
        $items_total = round(floatval($d['original_price_eur'] ?: $d['price_eur']), 2);
        $discount_total = round(floatval($d['discount_eur']), 2);
        $lines = [
            'INVOICE #' . $inv,
            'Invoice date: ' . $date,
            '',
            'Seller: ' . $s['seller_name'],
            'Address: ' . str_replace("\n", ', ', wp_strip_all_tags($s['seller_address'])),
            '',
            'Bill To: ' . $d['full_name'],
            $d['country'],
            '',
            'Order Items',
        ];
        $rows = $this->cart_items_from_order_data($d);
        foreach ($rows as $item) {
            $title = $this->clean_product_title_for_display($item['title'] ?? 'Selected leotard');
            $original = round(floatval($item['original_price_eur'] ?? ($item['price_eur'] ?? 0)), 2);
            $discount = round(floatval($item['discount_eur'] ?? 0), 2);
            $product_discount = round(floatval($item['product_discount_eur'] ?? 0), 2);
            $promo_discount = round(floatval($item['promo_discount_eur'] ?? 0), 2);
            if ($discount <= 0 && ($product_discount + $promo_discount) > 0) $discount = round($product_discount + $promo_discount, 2);
            if ($original <= 0) $original = round(floatval($item['price_eur'] ?? 0) + max(0, $discount), 2);
            $line_total = round(max(0, $original - $discount), 2);
            $lines[] = $title . ' | Qty: 1 | Unit price: EUR ' . number_format($original, 2) . ' | Discount: - EUR ' . number_format(max(0, $discount), 2) . ' | Line total: EUR ' . number_format($line_total, 2);
        }
        $lines = array_merge($lines, [
            'Items total: EUR ' . number_format($items_total, 2),
            (!empty($d['promo_code_applied']) ? 'Promo code discount: - EUR ' : 'Discount: - EUR ') . number_format($discount_total, 2),
            'Card payment service fee: EUR 0.00',
            'Shipping: EUR ' . number_format($shipping, 2),
            'Total: EUR ' . number_format($total, 2),
            '',
            'Payment Details (' . $bank['type'] . ')',
            'Receiver: ' . $bank['receiver'],
            'IBAN / Account: ' . $bank['iban'],
            'SWIFT/BIC: ' . $bank['swift'],
            'Bank: ' . $bank['bank'],
            'Bank address: ' . str_replace("\n", ', ', wp_strip_all_tags($bank['bank_address'])),
            '',
            'Payment Instructions',
            'Payment for ' . $s['product_purpose'] . ' by invoice #' . $inv . ' from ' . $date,
            '',
            'If payment is made in parts:',
            'Payment for ' . $s['product_purpose'] . ' by invoice #' . $inv . ' / Part 1 from ' . $date,
            'Payment for ' . $s['product_purpose'] . ' by invoice #' . $inv . ' / Part 2 from ' . $date,
            'Payment for ' . $s['product_purpose'] . ' by invoice #' . $inv . ' / Final from ' . $date,
        ]);
        return $lines;
    }
    private function dompdf_pdf($path, $html) {
        $autoload = $this->dompdf_autoload_path();
        if (!$autoload) {
            error_log('YOleotard Checkout: Dompdf autoload not found. PDF was not generated.');
            return false;
        }

        require_once $autoload;

        if (!class_exists('Dompdf\\Dompdf') && !class_exists('Dompdf\Dompdf')) {
            error_log('YOleotard Checkout: Dompdf class not found after autoload.');
            return false;
        }

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isFontSubsettingEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');
            $options->set('tempDir', wp_upload_dir()['basedir']);
            $options->set('fontCache', wp_upload_dir()['basedir'] . '/yoleotard-invoices-fonts');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($path, $dompdf->output());
            return file_exists($path) && filesize($path) > 1000;
        } catch (\Throwable $e) {
            error_log('YOleotard Checkout Dompdf error: ' . $e->getMessage());
            return false;
        }
    }

    private function mail_headers($s) {
        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sanitize_text_field($s['from_name']) . ' <' . sanitize_email($s['from_email']) . '>'
        ];
    }

    private function file_path_to_url($path) {
        $uploads = wp_upload_dir();
        $path = wp_normalize_path((string)$path);
        $base_dir = wp_normalize_path($uploads['basedir']);
        if ($path && strpos($path, $base_dir) === 0) {
            return $uploads['baseurl'] . str_replace($base_dir, '', $path);
        }
        return '';
    }

    private function email_logo_html() {
        $logo = $this->invoice_logo_src(false);
        if (!$logo) return '';
        return '<div style="text-align:center;margin:0 0 18px 0;">'
            . '<img src="' . esc_url($logo) . '" alt="YOleotard" style="max-width:150px;max-height:64px;width:auto;height:auto;display:inline-block;">'
            . '</div>';
    }

    private function money_html($amount) {
        return '€' . number_format((float)$amount, 2, '.', '');
    }

    private function email_address_html($d) {
        $parts = [];
        foreach (['address','additional_address','city','zip_code','country'] as $key) {
            if (!empty($d[$key])) $parts[] = esc_html($d[$key]);
        }
        return implode('<br>', $parts);
    }

    private function email_order_table_html($d, $show_status = true) {
        $original = (float)($d['original_price_eur'] ?: $d['price_eur']);
        $discount = (float)($d['discount_eur'] ?: 0);
        $shipping = (float)($d['shipping_cost_eur'] ?: 0);
        $service_fee = (float)($d['card_fee_amount'] ?: 0);
        $is_card = in_array(($d['payment_type'] ?? ''), ['card'], true);
        $total = (float)$d['price_eur'] + $shipping + ($is_card ? $service_fee : 0);
        $rows = '';
        $rows .= '<tr><td style="padding:10px 0;color:#64748b;">Product</td><td style="padding:10px 0;text-align:right;"><strong>' . esc_html($d['title']) . '</strong></td></tr>';
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
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Card payment service fee</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;">€0.00</td></tr>';
        }
        $rows .= '<tr><td style="padding:12px 0;color:#111827;border-top:2px solid #111827;font-size:16px;"><strong>Total</strong></td><td style="padding:12px 0;text-align:right;border-top:2px solid #111827;font-size:18px;"><strong>' . $this->money_html($total) . '</strong></td></tr>';
        if ($show_status) {
            $rows .= '<tr><td style="padding:10px 0;color:#64748b;border-top:1px solid #e5e7eb;">Payment status</td><td style="padding:10px 0;text-align:right;border-top:1px solid #e5e7eb;"><strong>Awaiting bank transfer</strong></td></tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">' . $rows . '</table>';
    }

    private function build_bank_invoice_email_html($local_id, $invoice_url) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        $invoice = $this->invoice_number($d['order_id']);
        $bank_type = get_post_meta($local_id, 'bank_invoice_type', true) ?: $this->bank_details_for_country($d['country'])['type'];
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
            . '<p style="margin:0 0 6px 0;font-size:15px;">Order number: <strong>№ ' . esc_html($d['order_id']) . '</strong></p>'
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
            . '<p style="margin:0 0 8px 0;"><strong>Name:</strong> ' . esc_html($d['full_name']) . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Email:</strong> ' . esc_html($d['email']) . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Phone:</strong> ' . esc_html($d['phone']) . '</p>'
            . '<p style="margin:0;"><strong>Delivery address:</strong><br>' . $address . '</p>'
            . '</div>'
            . '<div style="background:#f8fafc;border-left:4px solid #111827;margin:24px 0 0 0;padding:16px 18px;border-radius:8px;">'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">As soon as the payment is received to the specified bank account, we will process and ship your order to the delivery address provided during checkout. After shipment, we will send you the tracking number and tracking link for your parcel.</p>'
            . '</div>'
            . '<p style="margin:24px 0 0 0;font-size:15px;line-height:1.6;">If you have any questions regarding payment or delivery, please reply to this email.</p>'
            . '<p style="margin:20px 0 0 0;font-size:15px;line-height:1.6;">Best regards,<br><strong>YOleotard Atelier</strong><br>Made in Ukraine</p>'
            . '</div>'
            . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:16px 0 0 0;">YOleotard · https://yoleotard.com</p>'
            . '</div></body></html>';
    }

    private function send_html_mail($to, $subject, $message, $attachments = []) {
        $s = self::settings();
        $headers = $this->mail_headers($s);
        $force_html = function() { return 'text/html'; };
        add_filter('wp_mail_content_type', $force_html, 999);
        $sent = wp_mail($to, $subject, $message, $headers, $attachments);
        remove_filter('wp_mail_content_type', $force_html, 999);
        return $sent;
    }

    private function send_bank_invoice_email($local_id, $attachment_path) {
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        $subject = str_replace('{order_id}', $d['order_id'], $s['email_bank_subject']);
        $invoice_url = get_post_meta($local_id, 'invoice_pdf_url', true) ?: get_post_meta($local_id, 'invoice_html_url', true);
        if (!$invoice_url) $invoice_url = $this->file_path_to_url($attachment_path);
        $msg = $this->build_bank_invoice_email_html($local_id, $invoice_url);
        $attachments = [];
        if ($attachment_path && file_exists($attachment_path)) $attachments[] = $attachment_path;

        // Main customer email: rich HTML invoice instructions, not the old short text template.
        $this->send_html_mail($d['email'], $subject, $msg, $attachments);

        if (!empty($s['admin_email'])) {
            $admin_msg = '<p style="font-family:Arial,Helvetica,sans-serif;font-size:14px;"><strong>Admin copy.</strong> The customer received the invoice-payment email below.</p>' . $msg;
            $this->send_html_mail($s['admin_email'], 'Copy: ' . $subject, $admin_msg, $attachments);
        }
    }

    private function build_paid_email_html($local_id) {
        $d = $this->get_order_data($local_id);
        $logo = $this->email_logo_html();
        $address = $this->email_address_html($d);
        return '<!doctype html><html><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827;">'
            . '<div style="max-width:680px;margin:0 auto;padding:28px 14px;">'
            . '<div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:16px;padding:28px;box-shadow:0 6px 18px rgba(15,23,42,.06);">'
            . $logo
            . '<h1 style="margin:0 0 10px 0;font-size:26px;line-height:1.25;color:#111827;">Payment successful</h1>'
            . '<p style="margin:0 0 18px 0;color:#475569;font-size:15px;line-height:1.6;">Thank you for your order. We have received your payment. The total below includes delivery and, for card payments, the payment service fee.</p>'
            . '<div style="background:#ecfdf5;border:1px solid #bbf7d0;border-radius:12px;padding:16px 18px;margin:18px 0;">'
            . '<p style="margin:0 0 6px 0;font-size:15px;">Order number: <strong>№ ' . esc_html($d['order_id']) . '</strong></p>'
            . '<p style="margin:0;font-size:15px;">Payment status: <strong>Paid</strong></p>'
            . '</div>'
            . '<h2 style="font-size:18px;margin:24px 0 10px 0;">Order details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;background:#ffffff;">' . $this->email_order_table_html($d, false) . '</div>'
            . '<h2 style="font-size:18px;margin:26px 0 10px 0;">Customer and delivery details</h2>'
            . '<div style="border:1px solid #e5e7eb;border-radius:12px;padding:16px 18px;background:#fafafa;font-size:14px;line-height:1.6;">'
            . '<p style="margin:0 0 8px 0;"><strong>Name:</strong> ' . esc_html($d['full_name']) . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Email:</strong> ' . esc_html($d['email']) . '</p>'
            . '<p style="margin:0 0 8px 0;"><strong>Phone:</strong> ' . esc_html($d['phone']) . '</p>'
            . '<p style="margin:0;"><strong>Delivery address:</strong><br>' . $address . '</p>'
            . '</div>'
            . '<div style="background:#f8fafc;border-left:4px solid #111827;margin:24px 0 0 0;padding:16px 18px;border-radius:8px;">'
            . '<p style="margin:0;font-size:15px;line-height:1.6;">Our manager will contact you via WhatsApp and email. After shipment, we will send you the tracking number and tracking link for your parcel.</p>'
            . '</div>'
            . '<p style="margin:20px 0 0 0;font-size:15px;line-height:1.6;">Best regards,<br><strong>YOleotard Atelier</strong><br>Made in Ukraine</p>'
            . '</div>'
            . '<p style="text-align:center;color:#94a3b8;font-size:12px;margin:16px 0 0 0;">YOleotard · https://yoleotard.com</p>'
            . '</div></body></html>';
    }

    private function send_paid_email($local_id) {
        if (get_post_meta($local_id, 'paid_email_sent', true) === '1') return;
        $s = self::settings();
        $d = $this->get_order_data($local_id);
        $subject = str_replace('{order_id}', $d['order_id'], $s['email_paid_subject']);
        $msg = $this->build_paid_email_html($local_id);
        $this->send_html_mail($d['email'], $subject, $msg);
        if (!empty($s['admin_email'])) $this->send_html_mail($s['admin_email'], 'Copy: ' . $subject, $msg);
        update_post_meta($local_id, 'paid_email_sent', '1');
    }

}
new YO_Checkout_Invoice_Plugin();
