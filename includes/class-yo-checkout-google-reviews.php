<?php
if (!defined('ABSPATH')) exit;

class YO_Checkout_Google_Reviews_Service {
    private $settings_callback;

    public function __construct(callable $settings_callback) {
        $this->settings_callback = $settings_callback;
    }

    private function settings() {
        $settings = call_user_func($this->settings_callback);
        return is_array($settings) ? $settings : [];
    }

    public function render_scripts() {
        $s = $this->settings();
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
}
