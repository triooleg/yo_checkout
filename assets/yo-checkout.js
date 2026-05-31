(function(){
  document.addEventListener('DOMContentLoaded', function () {
    let selectedProduct = {};
    let cartItems = [];
    let appliedCartPromo = null;
    let current = {localId:'', orderId:'', invoiceId:'', customer:{}, cardFee:null, bankTotal:null, shipping:null, shippingOptions:[]};
    let bankInvoiceInProgress = false;
    function yoDebug(msg, obj){
      // Visible Step 3 debug panel removed in v4.0.24.
      // Keep optional console diagnostics only when explicitly enabled by admin in browser.
      try{
        if(window.localStorage && localStorage.getItem('yo_checkout_debug_console') === '1'){
          if(typeof obj !== 'undefined') console.log('[YO Checkout]', msg, obj);
          else console.log('[YO Checkout]', msg);
        }
      }catch(e){}
    }
    function yoDebugState(label){
      yoDebug(label || 'state', {
        invoiceId: current.invoiceId || '',
        localId: current.localId || getStoredDraftLocalId() || '',
        orderId: current.orderId || getStoredKeycrmOrderId() || '',
        storedDraft: getStoredDraftLocalId() || '',
        iframe: (document.getElementById('yo-payment-frame') || {}).src || ''
      });
    }
    let paymentTimer = null;
    let paymentPollingActive = false;
    let activePaymentInvoiceId = '';
    let activePaymentLocalId = '';
    let reservationServerOffsetMs = 0;
    const BUYER_STORAGE_KEY = 'yo_checkout_buyer_id_v1';
    const CHECKOUT_SESSION_KEY = 'yo_checkout_session_id_v1';
    const CHECKOUT_DRAFT_KEY = 'yo_checkout_local_order_id_v1';
    const CHECKOUT_KEYCRM_ORDER_KEY = 'yo_checkout_keycrm_order_id_v1';
    const CHECKOUT_CART_MARKER_KEY = 'yo_checkout_cart_keycrm_marker_v1';
    function buyerId(){
      try{
        let id = localStorage.getItem(BUYER_STORAGE_KEY);
        if(!id){ id = 'yo_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12); localStorage.setItem(BUYER_STORAGE_KEY, id); }
        return id;
      }catch(e){ return 'yo_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12); }
    }
    function checkoutSessionId(){
      try{
        let id = localStorage.getItem(CHECKOUT_SESSION_KEY);
        if(!id){ id = 'yos_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12); localStorage.setItem(CHECKOUT_SESSION_KEY, id); }
        return id;
      }catch(e){ return 'yos_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12); }
    }
    function getStoredDraftLocalId(){
      try{ return localStorage.getItem(CHECKOUT_DRAFT_KEY) || ''; }catch(e){ return ''; }
    }
    function storeDraftLocalId(id){
      try{ if(id) localStorage.setItem(CHECKOUT_DRAFT_KEY, String(id)); }catch(e){}
    }
    function getCookie(name){
      try{ const m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.$?*|{}()\[\]\\\/+^]/g, '\\$&') + '=([^;]*)')); return m ? decodeURIComponent(m[1]) : ''; }catch(e){ return ''; }
    }
    function setCookie(name, value, days){
      try{ const maxAge = Math.max(1, days || 2) * 24 * 60 * 60; document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax'; }catch(e){}
    }
    function getStoredKeycrmOrderId(){
      try{ return localStorage.getItem(CHECKOUT_KEYCRM_ORDER_KEY) || sessionStorage.getItem(CHECKOUT_KEYCRM_ORDER_KEY) || getCookie('yo_checkout_keycrm_order_id') || ''; }catch(e){ return getCookie('yo_checkout_keycrm_order_id') || ''; }
    }
    function getCartKeycrmMarker(){
      try{
        const raw = localStorage.getItem(CHECKOUT_CART_MARKER_KEY) || sessionStorage.getItem(CHECKOUT_CART_MARKER_KEY) || '';
        if(raw){ const obj = JSON.parse(raw); if(obj && obj.keycrm_order_id) return obj; }
      }catch(e){}
      try{
        const cartRaw = localStorage.getItem('yo_checkout_cart_v1') || '';
        if(cartRaw){
          const cart = JSON.parse(cartRaw);
          if(cart && cart.keycrm_order_id){
            return {
              keycrm_order_id:String(cart.keycrm_order_id),
              local_id: cart.local_id ? String(cart.local_id) : (getStoredDraftLocalId() || getCookie('yo_checkout_local_order_id') || ''),
              checkout_session_id: cart.checkout_session_id || checkoutSessionId(),
              buyer_id: cart.buyer_id || buyerId()
            };
          }
        }
      }catch(e){}
      const oid = getStoredKeycrmOrderId();
      return oid ? {keycrm_order_id:String(oid), local_id:(getStoredDraftLocalId() || getCookie('yo_checkout_local_order_id') || ''), checkout_session_id:checkoutSessionId(), buyer_id:buyerId()} : {};
    }
    function storeCartKeycrmMarker(orderId, localId){
      if(!orderId) return;
      const marker = {
        keycrm_order_id:String(orderId),
        local_id: localId ? String(localId) : (current.localId || getStoredDraftLocalId() || ''),
        checkout_session_id: checkoutSessionId(),
        buyer_id: buyerId(),
        saved_at: Date.now()
      };
      try{ localStorage.setItem(CHECKOUT_CART_MARKER_KEY, JSON.stringify(marker)); sessionStorage.setItem(CHECKOUT_CART_MARKER_KEY, JSON.stringify(marker)); }catch(e){}
      try{
        const cartRaw = localStorage.getItem('yo_checkout_cart_v1') || '';
        const cart = cartRaw ? JSON.parse(cartRaw) : {};
        if(cart && typeof cart === 'object'){
          cart.keycrm_order_id = String(orderId);
          cart.local_id = marker.local_id || '';
          cart.checkout_session_id = marker.checkout_session_id;
          cart.buyer_id = marker.buyer_id;
          cart.marker_saved_at = marker.saved_at;
          localStorage.setItem('yo_checkout_cart_v1', JSON.stringify(cart));
        }
      }catch(e){}
      setCookie('yo_checkout_keycrm_order_id', String(orderId), 2);
      if(marker.local_id) setCookie('yo_checkout_local_order_id', String(marker.local_id), 2);
    }
    function storeKeycrmOrderId(id){
      try{ if(id){ localStorage.setItem(CHECKOUT_KEYCRM_ORDER_KEY, String(id)); sessionStorage.setItem(CHECKOUT_KEYCRM_ORDER_KEY, String(id)); setCookie('yo_checkout_keycrm_order_id', String(id), 2); storeCartKeycrmMarker(id, current.localId || getStoredDraftLocalId() || ''); } }catch(e){ if(id) setCookie('yo_checkout_keycrm_order_id', String(id), 2); }
    }

    function rememberKeycrmFromResponse(resp){
      try{
        const d = resp && resp.data ? resp.data : resp;
        if(!d) return;
        const marker = d.cartMarker || d.cart_marker || null;
        const oid = (marker && marker.keycrm_order_id) || d.orderId || d.order_id || '';
        const lid = (marker && marker.local_id) || d.localId || d.local_id || current.localId || getStoredDraftLocalId() || '';
        if(lid){ current.localId = String(lid); storeDraftLocalId(lid); }
        if(oid){ current.orderId = String(oid); storeKeycrmOrderId(oid); storeCartKeycrmMarker(oid, lid); saveCart(); }
      }catch(e){}
    }

    function clearCheckoutDraftSession(){
      try{ localStorage.removeItem(CHECKOUT_DRAFT_KEY); localStorage.removeItem(CHECKOUT_SESSION_KEY); localStorage.removeItem(CHECKOUT_KEYCRM_ORDER_KEY); localStorage.removeItem(CHECKOUT_CART_MARKER_KEY); }catch(e){}
      current.localId = ''; current.orderId = ''; current.invoiceId = '';
      paymentPollingActive = false; activePaymentInvoiceId = ''; activePaymentLocalId = '';
    }
    function promoEnabled(){ return !!window.YOCheckout?.promoEnabled; }

    function byId(id){ return document.getElementById(id); }
    function show(id){ const el=byId(id); if(el) el.style.display='block'; }
    function hide(id){ const el=byId(id); if(el) el.style.display='none'; }
    function setStep(step){
      ['yo-step-cart','yo-step-loading','yo-step-method','yo-step-payment','yo-step-bank','yo-step-success'].forEach(hide);
      ['yo-pill-1','yo-pill-2','yo-pill-3','yo-pill-4'].forEach(function(id){ const el=byId(id); if(el) el.classList.remove('active'); });
      if(step===1){show('yo-step-cart'); byId('yo-pill-1').classList.add('active');}
      if(step==='loading'){show('yo-step-loading'); byId('yo-pill-1').classList.add('active');}
      if(step===2){show('yo-step-method'); byId('yo-pill-2').classList.add('active');}
      if(step===3){show('yo-step-payment'); byId('yo-pill-3').classList.add('active'); yoDebugState('entered step 3');}
      if(step==='bank'){show('yo-step-bank'); byId('yo-pill-4').classList.add('active');}
      if(step===4){show('yo-step-success'); byId('yo-pill-4').classList.add('active'); yoDebugState('entered step 4');}
    }
    function cleanPaymentButton(btn){
      if(!btn) return;
      btn.href='#'; btn.removeAttribute('data-type'); btn.removeAttribute('data-caption'); btn.removeAttribute('aria-label'); btn.removeAttribute('uk-lightbox'); btn.classList.add('yo-main-buy-btn');
    }
    function getPromoBadgeStyles(){
      const cfg = window.YOCheckout || {};
      return {
        color: cfg.promoBadgeTextColor || '#ffffff',
        fontSize: (parseFloat(cfg.promoBadgeFontSize || 12) || 12) + 'px',
        backgroundColor: cfg.promoBadgeBgColor || '#0b8f2f',
        opacity: String(Math.max(0, Math.min(100, parseFloat(cfg.promoBadgeOpacity || 100))) / 100)
      };
    }
    function findMeasurementsBlock(card){
      // Find the first visible measurement line and place the promo badge
      // immediately before it. Important: do not return a large YOOtheme
      // wrapper such as .el-content, otherwise the badge can jump near the image.
      const re = /\b(Chest|Bust|Waist|Hips|Torso|Girth|Height)\s*:/i;
      const nodes = Array.from(card.querySelectorAll('p, li, div, span'));
      for(const node of nodes){
        if(node.closest('a, button, script, style, .yo-promo-badge')) continue;
        const txt = (node.textContent || '').replace(/\s+/g, ' ').trim();
        if(!re.test(txt)) continue;
        if(txt.length > 220) continue;

        // Prefer the exact small line/container that holds the measurement.
        const smallBlock = node.closest('p, li, .uk-margin-small, .uk-margin-small-top, .uk-margin-small-bottom');
        if(smallBlock && card.contains(smallBlock)) return smallBlock;

        // If YOOtheme uses nested divs, climb only while the parent text is
        // still basically the same line. This prevents selecting the whole card.
        let block = node;
        while(block.parentElement && block.parentElement !== card){
          const parent = block.parentElement;
          const parentText = (parent.textContent || '').replace(/\s+/g, ' ').trim();
          if(parentText.length > 260) break;
          if(parent.querySelector('img, video, picture, a.el-link, button')) break;
          block = parent;
        }
        return block;
      }
      return null;
    }
    function findUnitsBlock(card){
      const nodes = Array.from(card.querySelectorAll('p, div, span'));
      for(const node of nodes){
        const txt = (node.textContent || '').replace(/\s+/g, ' ').trim();
        if(/^Units\s*:/i.test(txt)) return node.closest('p, div') || node;
      }
      return null;
    }

    function isProductCard(card){
      if(!card) return false;
      // YOOtheme also uses .el-item in footer/menu grids. Promo badges must be
      // added only to product cards that really contain a leotard buy button,
      // price, title and product image.
      const hasPrice = !!card.querySelector('.yo-price[data-eur]');
      const hasTitle = !!card.querySelector('.el-title');
      const hasImage = !!card.querySelector('img');
      const hasBuyButton = Array.from(card.querySelectorAll('a.el-link.uk-button, a.uk-button, button.uk-button')).some(function(btn){
        return /buy/i.test((btn.textContent || '').replace(/\s+/g, ' ')) || !!btn.querySelector('.yo-price[data-eur]');
      });
      return hasPrice && hasTitle && hasImage && hasBuyButton;
    }
    function closestProductCardFromNode(node){
      if(!node) return null;
      let el = node.nodeType === 1 ? node : node.parentElement;
      while(el && el !== document.body){
        if(el.matches && (el.matches('.el-item') || el.matches('.uk-card') || el.matches('li')) && isProductCard(el)){
          return el;
        }
        el = el.parentElement;
      }
      return null;
    }
    function cleanupInvalidPromoBadges(){
      document.querySelectorAll('.yo-promo-badge').forEach(function(badge){
        const card = badge.closest('.el-item');
        if(!card || !isProductCard(card) || hasActiveSaleDiscount(card)) badge.remove();
      });
    }
    function hasActiveSaleDiscount(card){
      if(!card) return false;
      if(card.querySelector('.sale-new-btn, .yo-sale-new-btn, .yo-sale-btn, .uk-button-danger')) return true;
      const links = Array.from(card.querySelectorAll('a.el-link.uk-button, a.uk-button, button.uk-button'));
      return links.some(function(btn){
        const txt = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if(/-\s*\d+(?:[.,]\d+)?\s*(€|EUR|%)/i.test(txt)) return true;
        const cls = String(btn.className || '');
        return /(^|\s)(uk-button-danger|uk-button-red|sale|discount)(\s|$)/i.test(cls) && /buy|€|eur|-\s*\d/i.test(txt);
      });
    }
    function removePromoBadge(card){
      card.querySelectorAll('.yo-promo-badge').forEach(function(el){ el.remove(); });
    }
    function normalizePromoBadgeSpace(badge, card, anchor){
      // Keep the promo badge visually before the measurements block, but remove it
      // from the document flow. This prevents product cards from becoming taller
      // and also avoids height jumps when badges are removed on sale products.
      if(!badge || !card) return;
      card.style.position = card.style.position || 'relative';
      badge.style.margin = '0';
      badge.style.padding = '2px 16px';
      badge.style.position = 'absolute';
      badge.style.zIndex = '3';
      badge.style.transform = 'none';
      badge.style.marginBottom = '0';
      requestAnimationFrame(function(){
        const target = anchor && card.contains(anchor) ? anchor : findMeasurementsBlock(card);
        if(!target) return;
        const cardRect = card.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const badgeH = Math.ceil(badge.offsetHeight || 0);
        const left = Math.max(0, Math.round(targetRect.left - cardRect.left));
        const top = Math.max(0, Math.round(targetRect.top - cardRect.top - badgeH - 8));
        badge.style.left = left + 'px';
        badge.style.top = top + 'px';
      });
    }
    function placePromoBadge(card){
      if(!promoEnabled()) { removePromoBadge(card); return; }
      if(hasActiveSaleDiscount(card)) { removePromoBadge(card); return; }
      const existingBadge = card.querySelector('.yo-promo-badge');
      const measurements = findMeasurementsBlock(card);
      if(existingBadge){ normalizePromoBadgeSpace(existingBadge, card, measurements); return; }
      const badge = document.createElement('div');
      badge.className = 'yo-promo-badge uk-label yo-promo-badge-before-measurements';
      badge.textContent = window.YOCheckout?.promoBadgeText || 'Discount by promo code';
      const styles = getPromoBadgeStyles();
      Object.keys(styles).forEach(function(k){ badge.style[k] = styles[k]; });

      if(measurements){
        card.appendChild(badge);
        normalizePromoBadgeSpace(badge, card, measurements);
        return;
      }
      const units = findUnitsBlock(card);
      card.appendChild(badge);
      normalizePromoBadgeSpace(badge, card, units || null);
    }
    function titleFromCard(card){
      return (card && card.querySelector('.el-title') ? card.querySelector('.el-title').textContent : '').replace(/\s+/g, ' ').trim();
    }
    function clearReservedState(card){
      if(!card) return;
      card.classList.remove('yo-reserved-card');
      card.querySelectorAll('.yo-reserved-overlay-badge').forEach(function(el){ el.remove(); });
      card.querySelectorAll('.yo-main-buy-btn').forEach(function(btn){
        if(btn.dataset.yoReservedDisabled){
          btn.removeAttribute('aria-disabled');
          btn.style.pointerEvents = '';
          btn.style.cursor = '';
          btn.dataset.yoReservedDisabled = '';
        }
      });
    }
    function formatReservationCountdown(totalSeconds){
      totalSeconds = Math.max(0, Math.floor(totalSeconds || 0));
      const minutes = Math.floor(totalSeconds / 60);
      const seconds = totalSeconds % 60;
      return String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');
    }
    function reservedBadgeText(baseText, expires){
      const nowSeconds = Math.floor((Date.now() + reservationServerOffsetMs) / 1000);
      return (baseText || (window.YOCheckout?.reservationBadgeText || 'Reserved')) + ' ' + formatReservationCountdown((parseInt(expires, 10) || 0) - nowSeconds);
    }
    function updateReservedCountdowns(){
      const baseText = window.YOCheckout?.reservationBadgeText || 'Reserved';
      document.querySelectorAll('.yo-reserved-overlay-badge[data-yo-reservation-expires]').forEach(function(badge){
        const expires = parseInt(badge.dataset.yoReservationExpires || '0', 10) || 0;
        const nowSeconds = Math.floor((Date.now() + reservationServerOffsetMs) / 1000);
        if(expires <= nowSeconds){
          const card = badge.closest('.yo-reserved-card');
          if(card) clearReservedState(card);
          return;
        }
        badge.textContent = reservedBadgeText(baseText, expires);
      });
    }
    function applyReservedState(card, text, expires){
      if(!card) return;
      card.classList.add('yo-reserved-card');
      card.style.position = card.style.position || 'relative';
      let badge = card.querySelector('.yo-reserved-overlay-badge');
      if(!badge){
        badge = document.createElement('div');
        badge.className = 'yo-reserved-overlay-badge';
        card.appendChild(badge);
      }
      badge.dataset.yoReservationExpires = String(expires || '');
      badge.dataset.yoReservationText = text || (window.YOCheckout?.reservationBadgeText || 'Reserved');
      badge.textContent = expires ? reservedBadgeText(badge.dataset.yoReservationText, expires) : (text || (window.YOCheckout?.reservationBadgeText || 'Reserved'));
      card.querySelectorAll('.yo-main-buy-btn').forEach(function(btn){
        btn.setAttribute('aria-disabled','true');
        btn.style.pointerEvents = 'none';
        btn.style.cursor = 'not-allowed';
        btn.dataset.yoReservedDisabled = '1';
      });
    }
    function refreshReservedCards(){
      const fd = new FormData();
      fd.append('buyer_id', buyerId());
      post('yo_checkout_get_reservations', fd).then(function(data){
        if(!data || !data.success || !data.data || !Array.isArray(data.data.items)) return;
        if(data.data.now) reservationServerOffsetMs = (parseInt(data.data.now, 10) * 1000) - Date.now();
        const serverNow = parseInt(data.data.now || Math.floor((Date.now() + reservationServerOffsetMs) / 1000), 10) || Math.floor(Date.now()/1000);
        const active = data.data.items.filter(function(row){ return row && row.title && row.expires && row.expires > serverNow; });
        productCardsOnPage().forEach(function(card){
          const cardTitle = titleFromCard(card);
          let reserved = null;
          for(const row of active){
            if(sameProductTitle(cardTitle, row.title)){ reserved = row; break; }
          }
          if(reserved) applyReservedState(card, data.data.badgeText || window.YOCheckout?.reservationBadgeText || 'Reserved', reserved.expires);
          else clearReservedState(card);
        });
        updateReservedCountdowns();
      }).catch(function(){});
    }
    function reserveProduct(product){
      const fd = new FormData();
      fd.append('title', product.title || '');
      fd.append('buyer_id', buyerId());
      return post('yo_checkout_reserve_item', fd);
    }
    function clearReservedStateForTitle(title){
      if(!title) return;
      const seen = new Set();
      const candidates = [];
      // Use the normal product-card list first, then add a broader fallback.
      // Sale cards can contain two .yo-price nodes (old + new price), so older
      // strict selectors may miss them and leave the grey reservation overlay
      // until page refresh.
      productCardsOnPage().forEach(function(card){
        if(card && !seen.has(card)){ seen.add(card); candidates.push(card); }
      });
      document.querySelectorAll('.el-item, .uk-card, li').forEach(function(card){
        if(card && !seen.has(card) && isProductCard(card)){ seen.add(card); candidates.push(card); }
      });
      candidates.forEach(function(card){
        const cardTitle = titleFromCard(card);
        if(sameProductTitle(cardTitle, title) || textMatchesTitle(cardTitle, title) || textMatchesTitle(title, cardTitle)){
          clearReservedState(card);
        }
      });
      updateReservedCountdowns();
    }
    function releaseReservationForTitle(title){
      if(!title) return;
      // Clear the visual reserved state immediately. This is important for sale/discount
      // cards because their YOOtheme button markup can differ from regular cards, so
      // waiting for the async refresh can leave a grey overlay until page reload.
      clearReservedStateForTitle(title);
      const fd = new FormData();
      fd.append('title', title);
      fd.append('buyer_id', buyerId());
      post('yo_checkout_release_reservation', fd).then(function(){
        clearReservedStateForTitle(title);
        setTimeout(refreshReservedCards, 120);
      }).catch(function(){
        setTimeout(refreshReservedCards, 250);
      });
    }
    function showReservationErrorMessage(message){
      const plainMessage = message || 'This item is currently reserved or no longer available. Please choose another model.';
      const htmlMessage = '<div class="yo-cart-duplicate-notice yo-cart-reservation-notice"><span class="yo-cart-duplicate-icon">!</span><span>'+escHtml(plainMessage)+'</span></div>';
      if(window.UIkit && typeof window.UIkit.notification === 'function') window.UIkit.notification({message: htmlMessage, status:'primary', pos:'top-center', timeout:5200});
      else alert(plainMessage);
    }

    function addPayButtons(){
      cleanupInvalidPromoBadges();
      document.querySelectorAll('.el-item').forEach(function(card){
        if(!isProductCard(card)) { removePromoBadge(card); return; }
        const oldSaleButton = card.querySelector('.sale-old-btn');
        const saleButton = card.querySelector('.sale-new-btn, .yo-sale-new-btn, .yo-sale-btn, .uk-button-danger');
        const regularButton = card.querySelector('a.el-link.uk-button:not(.sale-old-btn):not(.sale-new-btn):not(.yo-sale-new-btn):not(.yo-sale-btn):not(.uk-button-danger)');
        if(oldSaleButton){ oldSaleButton.href='#'; oldSaleButton.removeAttribute('uk-lightbox'); oldSaleButton.classList.remove('yo-main-buy-btn'); oldSaleButton.style.pointerEvents='none'; oldSaleButton.style.cursor='default'; }
        if(saleButton || hasActiveSaleDiscount(card)){
          // Do not create invisible/off-screen promo badges for products that
          // already have the red sale button. Remove any old badges immediately.
          removePromoBadge(card);
          if(saleButton) cleanPaymentButton(saleButton);
          return;
        }
        if(regularButton){
          cleanPaymentButton(regularButton);
          const priceNode = regularButton.querySelector('.yo-price[data-eur]');
          if(!priceNode) { removePromoBadge(card); return; }
          if(!regularButton.dataset.yoTextReady){
            regularButton.innerHTML='<span class="yo-cart-icon"><svg width="16" height="16" viewBox="0 0 20 20" aria-hidden="true"><circle cx="7.3" cy="17.3" r="1.4"></circle><circle cx="13.3" cy="17.3" r="1.4"></circle><polyline fill="none" stroke="currentColor" stroke-width="1.1" points="0 2 3.2 4 5.3 12.5 16 12.5 18 6.5 8 6.5"></polyline></svg></span> Buy now • <span class="yo-price" data-eur="'+priceNode.getAttribute('data-eur')+'">'+priceNode.textContent+'</span>';
            regularButton.dataset.yoTextReady='1';
          }
          // Show promo badge only for regular products without the red sale button.
          placePromoBadge(card);
        } else {
          removePromoBadge(card);
        }
      });
      cleanupInvalidPromoBadges();
    }
    window.addEventListener('resize', function(){
      document.querySelectorAll('.el-item .yo-promo-badge').forEach(function(badge){
        const card = badge.closest('.el-item');
        if(card) normalizePromoBadgeSpace(badge, card, findMeasurementsBlock(card));
      });
    });

    function post(action, formData){
      formData = formData || new FormData();
      formData.append('action', action);
      formData.append('nonce', YOCheckout.nonce);
      return fetch(YOCheckout.ajaxUrl, {method:'POST', body:formData}).then(function(r){
        return r.text().then(function(t){
          let parsed = null;
          try{ parsed = JSON.parse(t); }
          catch(e){
            const err = new Error((t || 'Connection error').slice(0, 1200));
            err.action = action;
            err.status = r.status;
            err.statusText = r.statusText || '';
            err.responseText = t || '';
            err.url = YOCheckout.ajaxUrl;
            return Promise.reject(err);
          }
          if(!r.ok){
            const err = new Error(parsed?.data?.message || parsed?.message || t || 'Connection error');
            err.action = action;
            err.status = r.status;
            err.statusText = r.statusText || '';
            err.responseText = t || '';
            err.data = parsed;
            err.url = YOCheckout.ajaxUrl;
            return Promise.reject(err);
          }
          return parsed;
        });
      });
    }
    function checkoutDebugId(prefix){
      return String(prefix || 'yo') + '-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
    }
    function checkoutAjaxErrorMessage(action, err, context){
      err = err || {};
      const lines = ['Connection error'];
      lines.push('AJAX action: ' + (err.action || action || 'unknown'));
      if(err.status) lines.push('HTTP status: ' + err.status + (err.statusText ? ' ' + err.statusText : ''));
      if(context && context.debugId) lines.push('Debug ID: ' + context.debugId);
      if(context && context.localId) lines.push('Local ID: ' + context.localId);
      if(context && context.orderId) lines.push('KeyCRM/order marker: ' + context.orderId);
      if(context && typeof context.cartItems !== 'undefined') lines.push('Cart items: ' + context.cartItems);
      const data = err.data && err.data.data ? err.data.data : null;
      if(data && data.debugId && (!context || data.debugId !== context.debugId)) lines.push('Server debug ID: ' + data.debugId);
      if(data && data.message) lines.push('Server message: ' + data.message);
      if(data && data.details) lines.push('Server details: ' + JSON.stringify(data.details, null, 2).slice(0, 1000));
      const body = String(err.responseText || err.message || '').trim();
      if(body) lines.push('Response: ' + body.slice(0, 1200));
      lines.push('Check plugin admin log: Settings -> YOleotard Checkout -> Hiding / Last auto-hide log, search for checkout-debug.');
      console.error('[YO Checkout AJAX error]', {action: action, error: err, context: context});
      return lines.join('\n\n');
    }
    function money(v){ return (parseFloat(v || 0) || 0).toFixed(2); }
    function yoEstimatedDeliveryDate(){
      const days = Math.max(0, parseInt(window.YOCheckout?.googleReviewsDeliveryDays || 14, 10) || 14);
      const d = new Date();
      d.setDate(d.getDate() + days);
      return d.toISOString().slice(0, 10);
    }
    function yoGoogleReviewCountryCode(country){
      const raw = String(country || '').trim();
      if(/^[A-Za-z]{2}$/.test(raw)) return raw.toUpperCase();
      const key = raw.toLowerCase().replace(/[^a-zа-яіїєґ ]+/gi, ' ').replace(/\s+/g, ' ').trim();
      const map = {
        'malaysia':'MY','united kingdom':'GB','uk':'GB','great britain':'GB','england':'GB',
        'united states':'US','usa':'US','us':'US','america':'US','canada':'CA','australia':'AU',
        'italy':'IT','italia':'IT','france':'FR','germany':'DE','deutschland':'DE','spain':'ES','espana':'ES','españa':'ES',
        'poland':'PL','polska':'PL','norway':'NO','norge':'NO','sweden':'SE','denmark':'DK','finland':'FI',
        'netherlands':'NL','belgium':'BE','austria':'AT','switzerland':'CH','ireland':'IE','portugal':'PT',
        'ukraine':'UA','украина':'UA','україна':'UA','czech republic':'CZ','czechia':'CZ','slovakia':'SK',
        'romania':'RO','bulgaria':'BG','greece':'GR','croatia':'HR','slovenia':'SI','hungary':'HU',
        'estonia':'EE','latvia':'LV','lithuania':'LT','colombia':'CO','mexico':'MX','japan':'JP','singapore':'SG'
      };
      return map[key] || raw.toUpperCase();
    }
    function updateTermsButtons(){
      const ok = !!byId('yo-accept-terms')?.checked;
      ['yo-pay-card','yo-pay-bank'].forEach(function(id){ const b=byId(id); if(b) b.disabled = !ok; });
    }
    function escHtml(v){
      return String(v == null ? '' : v).replace(/[&<>"']/g, function(ch){
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#039;'})[ch];
      });
    }
    const CART_STORAGE_KEY = 'yo_checkout_cart_v1';
    const CART_STORAGE_TTL = 2 * 24 * 60 * 60 * 1000;
    function saveCart(){
      try{
        if(!cartItems.length){
          // Do not clear checkout draft/session here. If a KeyCRM order was already created and
          // the customer removes all models, goes back, then adds different models before paying,
          // the next Step 2 submission must update the same KeyCRM order instead of creating a duplicate.
          localStorage.removeItem(CART_STORAGE_KEY);
          return;
        }
        const marker = getCartKeycrmMarker();
        const payload = {savedAt:Date.now(), items:cartItems};
        if(promoEnabled() && appliedCartPromo && cartEligiblePromoBase() > 0){ payload.applied_cart_promo = appliedCartPromo; }
        if(marker && marker.keycrm_order_id){
          payload.keycrm_order_id = marker.keycrm_order_id;
          payload.local_id = marker.local_id || current.localId || getStoredDraftLocalId() || '';
          payload.checkout_session_id = marker.checkout_session_id || checkoutSessionId();
          payload.buyer_id = marker.buyer_id || buyerId();
          payload.marker_saved_at = Date.now();
        }
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(payload));
      }catch(e){}
    }
    function loadCart(){
      try{
        const raw = localStorage.getItem(CART_STORAGE_KEY);
        if(!raw) return;
        const data = JSON.parse(raw);
        if(!data || !Array.isArray(data.items) || !data.savedAt || (Date.now() - data.savedAt) > CART_STORAGE_TTL){
          localStorage.removeItem(CART_STORAGE_KEY);
          return;
        }
        const seenLoad = {};
        cartItems = data.items.filter(function(item){
          if(!item || !item.title || parseFloat(item.price_eur || 0) <= 0) return false;
          const key = normalizeCartItemKey(item);
          if(seenLoad[key]) return false;
          seenLoad[key] = true;
          return true;
        });
        if(data.keycrm_order_id){
          storeCartKeycrmMarker(data.keycrm_order_id, data.local_id || getStoredDraftLocalId() || '');
          if(data.local_id) storeDraftLocalId(data.local_id);
        }
        appliedCartPromo = null;
        if(promoEnabled() && data.applied_cart_promo && cartEligiblePromoBase() > 0){
          appliedCartPromo = data.applied_cart_promo;
        }
      }catch(e){ try{ localStorage.removeItem(CART_STORAGE_KEY); }catch(_){} }
    }


    function decodeLooseUnicodeSequences(text){
      text = String(text == null ? '' : text);
      text = text.replace(/\\u([0-9a-fA-F]{4})/g, function(_, h){ return String.fromCharCode(parseInt(h, 16)); });
      text = text.replace(/(^|[^A-Za-z0-9_])u([0-9a-fA-F]{4})/g, function(_, p, h){ return p + String.fromCharCode(parseInt(h, 16)); });
      return text;
    }
    function normalizeMatchText(text){
      text = decodeLooseUnicodeSequences(text);
      const div = document.createElement('div');
      div.innerHTML = text;
      text = div.textContent || div.innerText || text;
      text = decodeLooseUnicodeSequences(text);
      text = text.replace(/[’‘`´]/g, "'").replace(/[“”«»]/g, '"').replace(/[–—−]/g, '-').replace(/\u00a0/g, ' ');
      text = text.replace(/[^\p{L}\p{N}'" \-]+/gu, ' ').replace(/\s+/g, ' ').trim().toLowerCase();
      return text;
    }
    function importantTitleWords(text){
      const generic = {new:1,author:1,authors:1,designer:1,leotard:1,leotards:1,duo:1,dress:1,for:1,height:1,cm:1,size:1,model:1,the:1,and:1,with:1,per:1,pair:1};
      return normalizeMatchText(text).split(/\s+/).filter(function(w){ return w && !generic[w] && !/^\d{2,3}$/.test(w) && w.length >= 3; });
    }
    function titleAliasesForMatching(title){
      const raw = decodeLooseUnicodeSequences(title);
      const aliases = [];
      const full = normalizeMatchText(raw);
      if(full) aliases.push(full);
      const re = /["«“]([^"»”]{3,})["»”]/g;
      let m;
      while((m = re.exec(raw))){
        const q = normalizeMatchText(m[1]);
        if(q) aliases.push(q);
      }
      const words = importantTitleWords(raw);
      if(words.length >= 2){
        aliases.push(words.slice(0, Math.min(4, words.length)).join(' '));
        for(let i=0;i<words.length-1;i++) aliases.push(words[i] + ' ' + words[i+1]);
      }
      return Array.from(new Set(aliases.filter(function(a){ return a && a.length >= 3; })));
    }

    function productIdentityKey(title){
      const raw = decodeLooseUnicodeSequences(title || '');
      const quoted = [];
      const re = /["«“]([^"»”]{3,})["»”]/g;
      let m;
      while((m = re.exec(raw))){
        const q = normalizeMatchText(m[1]);
        if(q) quoted.push(q);
      }
      if(quoted.length) return quoted[0];
      const words = importantTitleWords(raw);
      if(words.length >= 2) return words.slice(0, Math.min(4, words.length)).join(' ');
      return normalizeMatchText(raw);
    }
    function cardProductIdentityKey(card){
      return productIdentityKey(titleFromCard(card));
    }
    function sameProductTitle(cardTitle, reservationTitle){
      const a = productIdentityKey(cardTitle);
      const b = productIdentityKey(reservationTitle);
      return !!a && !!b && a === b;
    }
    function textMatchesTitle(text, title){
      const hay = normalizeMatchText(text);
      if(!hay) return false;
      const aliases = titleAliasesForMatching(title);
      for(const alias of aliases){ if(alias && hay.indexOf(alias) !== -1) return true; }
      const words = importantTitleWords(title);
      let hits = 0;
      words.forEach(function(w){ if(hay.indexOf(w) !== -1) hits++; });
      if(hits >= 2) return true;
      return false;
    }
    function productCardsOnPage(){
      // Build the card list from the actual buy buttons. This is more reliable
      // with YOOtheme responsive grids because some columns/cards get additional
      // wrappers and the last item in a row can have a different DOM shape.
      // Important: discounted/sale cards can contain two price nodes (old price
      // + red sale price), so do not require exactly one .yo-price.
      const seen = new Set();
      const cards = [];
      document.querySelectorAll('.yo-main-buy-btn, a.el-link.uk-button, a.uk-button, button.uk-button').forEach(function(btn){
        if(btn.closest('#yo-pay-modal')) return;
        const card = closestProductCardFromNode(btn);
        if(!card || seen.has(card)) return;
        const titleCount = card.querySelectorAll('.el-title').length;
        const priceCount = card.querySelectorAll('.yo-price[data-eur]').length;
        if(titleCount < 1 || priceCount < 1) return;
        if(!isProductCard(card)) return;
        seen.add(card);
        cards.push(card);
      });
      return cards;
    }
    function localUnavailableCartKeysFromPublishedDom(){
      const cards = productCardsOnPage();
      if(!cards.length) return {}; // Do not remove anything on pages without the product grid.
      const unavailable = {};
      cartItems.forEach(function(item){
        const key = normalizeCartItemKey(item);
        let found = false;
        for(const card of cards){
          if(sameProductTitle(titleFromCard(card), item.title)){ found = true; break; }
        }
        if(!found) unavailable[key] = true;
      });
      return unavailable;
    }

    function showCartCleanupMessage(removedCount){
      const plainMessage = removedCount === 1
        ? 'One item was removed from your cart because it is no longer available.'
        : removedCount + ' items were removed from your cart because they are no longer available.';
      const htmlMessage = '<div class="yo-cart-duplicate-notice yo-cart-cleanup-notice"><span class="yo-cart-duplicate-icon">!</span><span>'+escHtml(plainMessage)+'</span></div>';
      if(window.UIkit && typeof window.UIkit.notification === 'function'){
        window.UIkit.notification({message: htmlMessage, status:'primary', pos:'top-center', timeout:5200});
      } else {
        alert(plainMessage);
      }
    }
    function validateCartAvailability(showNotice){
      if(!cartItems.length) return Promise.resolve({removed:0});
      const beforeCount = cartItems.length;
      const fd = new FormData();
      fd.append('cart_items_json', JSON.stringify(cartItems.map(function(item){ return {title:item.title}; })));
      fd.append('buyer_id', buyerId());
      return post('yo_checkout_validate_cart_items', fd).then(function(data){
        const availableByKey = {};
        if(data && data.success && data.data && Array.isArray(data.data.items)){
          data.data.items.forEach(function(row){
            if(!row) return;
            // Server validation is the single source of truth. Do not remove items
            // based on the visible DOM here: YOOtheme responsive rows, cart pages,
            // and reserved overlays can make a valid product look absent locally.
            if(row.key) availableByKey[row.key] = !!row.available;
            if(row.title){
              availableByKey[normalizeCartItemKey({title: row.title})] = !!row.available;
              availableByKey[productIdentityKey(row.title)] = !!row.available;
            }
          });
        }
        const removedTitles = [];
        cartItems = cartItems.filter(function(item){
          const fullKey = normalizeCartItemKey(item);
          const identityKey = productIdentityKey(item && item.title ? item.title : '');
          let keep = true;
          if(!fullKey && !identityKey) keep = false;
          else if(Object.prototype.hasOwnProperty.call(availableByKey, fullKey)) keep = !!availableByKey[fullKey];
          else if(Object.prototype.hasOwnProperty.call(availableByKey, identityKey)) keep = !!availableByKey[identityKey];
          // If the server did not return a row for this item, keep it rather than
          // deleting a customer's cart item by mistake. The final payment step has
          // a stricter server-side availability check.
          if(!keep && item && item.title) removedTitles.push(item.title);
          return keep;
        });
        // If a cart item is removed as unavailable, release this visitor's active
        // reservation too. Otherwise the public countdown can keep running after
        // the item disappeared from the cart.
        removedTitles.forEach(function(title){ releaseReservationForTitle(title); });
        const removed = beforeCount - cartItems.length;
        if(removed > 0){
          appliedCartPromo = null;
          if(byId('yo-promo-code')) byId('yo-promo-code').value='';
          if(byId('yo-promo-message')) byId('yo-promo-message').textContent='';
          saveCart();
          updateFloatingCart();
          renderCartSummary();
          if(showNotice) showCartCleanupMessage(removed);
        }
        return {removed: removed};
      }).catch(function(){ return {removed:0}; });
    }

    function ensureFloatingCart(){
      let btn = byId('yo-floating-cart');
      if(btn) return btn;
      btn = document.createElement('button');
      btn.id = 'yo-floating-cart';
      btn.type = 'button';
      btn.className = 'yo-floating-cart yo-hidden';
      btn.setAttribute('aria-label', 'Open cart');
      btn.innerHTML = '<span class="yo-floating-cart-icon" uk-icon="icon: cart; ratio: 1.25" aria-hidden="true"></span><span id="yo-floating-cart-count" class="yo-floating-cart-count">0</span>'; if(window.UIkit && window.UIkit.update) window.UIkit.update(btn);
      document.body.appendChild(btn);
      btn.addEventListener('click', openCartModal);
      return btn;
    }
    function cartEligiblePromoBase(){
      return cartItems.reduce(function(sum, item){
        const itemDiscount = parseFloat(item.product_discount_eur || 0) || 0;
        if(itemDiscount > 0) return sum;
        return sum + (parseFloat(item.price_eur || item.original_price_eur || 0) || 0);
      }, 0);
    }
    function syncAppliedCartPromo(){
      if(appliedCartPromo && cartEligiblePromoBase() <= 0){
        appliedCartPromo = null;
        if(byId('yo-promo-code')) byId('yo-promo-code').value='';
        if(byId('yo-promo-message')) byId('yo-promo-message').textContent='';
      }
    }
    function cartTotals(){
      syncAppliedCartPromo();
      const original = cartItems.reduce((sum, item) => sum + (parseFloat(item.original_price_eur || item.price_eur || 0) || 0), 0);
      const productDiscount = cartItems.reduce((sum, item) => sum + (parseFloat(item.product_discount_eur || 0) || 0), 0);
      const regularFinal = cartItems.reduce((sum, item) => sum + (parseFloat(item.price_eur || 0) || 0), 0);
      const promoBase = cartEligiblePromoBase();
      const promoDiscount = appliedCartPromo ? Math.min((parseFloat(appliedCartPromo.discount_eur || 0) || 0), Math.max(0, promoBase - 1)) : 0;
      const finalTotal = Math.max(1, regularFinal - promoDiscount);
      return {original: original, productDiscount: productDiscount, promoDiscount: promoDiscount, promoBase: promoBase, regularFinal: regularFinal, finalTotal: finalTotal, totalDiscount: productDiscount + promoDiscount};
    }
    function cartItemPromoDiscount(item){
      if(!promoEnabled()) return 0;
      if(!appliedCartPromo) return 0;
      if((parseFloat(item.product_discount_eur || 0) || 0) > 0) return 0;
      const eligibleBase = cartEligiblePromoBase();
      if(eligibleBase <= 0) return 0;
      const totalPromo = Math.min((parseFloat(appliedCartPromo.discount_eur || 0) || 0), Math.max(0, eligibleBase - 1));
      const base = parseFloat(item.price_eur || item.original_price_eur || 0) || 0;
      return +(totalPromo * (base / eligibleBase)).toFixed(2);
    }
    function cartItemTotalDiscount(item){
      return +(Math.max(0, parseFloat(item.product_discount_eur || item.discount_eur || 0) || 0) + cartItemPromoDiscount(item)).toFixed(2);
    }
    function cartHasProductDiscount(){
      return cartItems.some(function(item){ return (parseFloat(item.product_discount_eur || 0) || 0) > 0; });
    }
    function buildSelectedProductFromCart(){
      const totals = cartTotals();
      const title = cartItems.length === 1 ? cartItems[0].title : cartItems.map(function(item, i){ return (i + 1) + '. ' + item.title; }).join(' | ');
      selectedProduct = {
        title: title,
        price_eur: money(totals.finalTotal),
        original_price_eur: money(totals.original || totals.finalTotal),
        discount_eur: money(totals.totalDiscount),
        product_discount_eur: money(totals.productDiscount),
        promo_applied: promoEnabled() && !!appliedCartPromo,
        promo_code_applied: (promoEnabled() && appliedCartPromo) ? appliedCartPromo.code : '',
        image_url: cartItems[0]?.image_url || '',
        image_urls: cartItems.map(function(item){ return item.image_url || ''; }).filter(Boolean),
        shipping_weight_kg: cartTotalWeight()
      };
      return selectedProduct;
    }

    function renderModalProductImages(){
      const wrap = byId('yo-modal-imgs');
      const legacyImg = byId('yo-modal-img');
      if(!wrap && !legacyImg) return;
      const images = cartItems.map(function(item){ return item.image_url || ''; }).filter(Boolean);
      if(!images.length){
        if(wrap) wrap.innerHTML = '';
        if(legacyImg) legacyImg.removeAttribute('src');
        return;
      }
      if(wrap){
        wrap.innerHTML = images.map(function(src){ return '<img src="'+escHtml(src)+'" alt="" class="yo-product-img">'; }).join('');
      } else if(legacyImg){
        legacyImg.src = images[0];
      }
    }
    function setCartEmptyView(isEmpty){
      const form = byId('yo-pay-form');
      const productHead = byId('yo-modal-product-head');
      const promo = byId('yo-promo-box');
      if(form) form.classList.toggle('yo-hidden', !!isEmpty);
      if(productHead) productHead.classList.toggle('yo-hidden', !!isEmpty);
      if(promo && isEmpty) promo.classList.add('yo-hidden');
    }
    function updateFloatingCart(){
      const btn = ensureFloatingCart();
      const count = byId('yo-floating-cart-count');
      if(count) count.textContent = String(cartItems.length);
      if(cartItems.length) btn.classList.remove('yo-hidden'); else btn.classList.add('yo-hidden');
    }
    function renderCartSummary(){
      const box = byId('yo-cart-summary');
      if(!box) return;
      if(!cartItems.length){
        selectedProduct = {};
        box.innerHTML = '<div class="yo-cart-empty">Your cart is empty.</div>';
        const titleEl = byId('yo-modal-title');
        const priceEl = byId('yo-modal-price');
        const imgEl = byId('yo-modal-img');
        if(titleEl) titleEl.textContent = 'Cart is empty';
        if(priceEl) priceEl.textContent = '';
        if(imgEl) imgEl.removeAttribute('src');
        const imgs = byId('yo-modal-imgs'); if(imgs) imgs.innerHTML='';
        const productHead = byId('yo-modal-product-head'); if(productHead) productHead.classList.remove('yo-cart-preview-many');
        setCartEmptyView(true);
        updateFloatingCart();
        return;
      }
      buildSelectedProductFromCart();
      setCartEmptyView(false);
      const totals = cartTotals();
      const rows = cartItems.map(function(item, i){
        const productDiscount = parseFloat(item.product_discount_eur || item.discount_eur || 0) || 0;
        const promoDiscount = cartItemPromoDiscount(item);
        const totalItemDiscount = +(productDiscount + promoDiscount).toFixed(2);
        const finalItem = Math.max(0, (parseFloat(item.price_eur || 0) || 0) - promoDiscount);
        let discountText = '—';
        if(totalItemDiscount > 0){
          const parts = [];
          if(productDiscount > 0) parts.push('Product -€'+money(productDiscount));
          if(promoDiscount > 0) parts.push('Promo -€'+money(promoDiscount));
          discountText = parts.join('<br>');
        }
        return '<div class="yo-cart-item yo-cart-item-grid" data-cart-index="'+i+'">' +
          '<div class="yo-cart-item-title">'+escHtml(i + 1 + '. ' + item.title)+'</div>' +
          '<div class="yo-cart-item-price">€'+money(item.original_price_eur || item.price_eur)+'</div>' +
          '<div class="yo-cart-item-discount '+(totalItemDiscount > 0 ? 'has-discount' : '')+'">'+discountText+'</div>' +
          '<div class="yo-cart-item-final">€'+money(finalItem)+'</div>' +
          '<button type="button" class="yo-cart-remove uk-icon-button" data-cart-remove="'+i+'" aria-label="Remove item" uk-icon="icon: trash; ratio: .85"></button>' +
        '</div>';
      }).join('');
      box.innerHTML = '<div class="yo-cart-summary-head"><strong>Cart</strong><span>'+cartItems.length+' order'+(cartItems.length === 1 ? '' : 's')+'</span></div>' +
        '<div class="yo-cart-table-head"><span>Model</span><span>Price</span><span>Discount</span><span>Total</span><span></span></div>' +
        rows +
        (totals.productDiscount > 0 ? '<div class="yo-cart-row"><span>Product discounts</span><strong>-€'+money(totals.productDiscount)+'</strong></div>' : '') +
        (totals.promoDiscount > 0 ? '<div class="yo-cart-row"><span>Promo code discount</span><strong>-€'+money(totals.promoDiscount)+'</strong></div>' : '') +
        '<div class="yo-cart-total"><span>Total</span><strong>€'+money(totals.finalTotal)+'</strong></div>';
      const titleEl = byId('yo-modal-title');
      const priceEl = byId('yo-modal-price');
      const imgEl = byId('yo-modal-img');
      const productHead = byId('yo-modal-product-head');
      if(productHead) productHead.classList.toggle('yo-cart-preview-many', cartItems.length > 2);
      if(titleEl) titleEl.textContent = cartItems.length === 1 ? cartItems[0].title : cartItems.length + ' selected leotards';
      if(priceEl) priceEl.textContent = money(totals.finalTotal) + ' €';
      renderModalProductImages();
      renderPromoBox();
      if(window.UIkit && window.UIkit.update) window.UIkit.update(box);
    }
    function removeCartItem(index){
      if(index < 0 || index >= cartItems.length) return;
      const removedTitle = cartItems[index] && cartItems[index].title ? cartItems[index].title : '';
      cartItems.splice(index, 1);
      if(removedTitle) releaseReservationForTitle(removedTitle);
      syncAppliedCartPromo();
      if(!appliedCartPromo && byId('yo-promo-code')) byId('yo-promo-code').value='';
      if(byId('yo-promo-message')) byId('yo-promo-message').textContent='';
      saveCart();
      updateFloatingCart();
      renderCartSummary();
    }
    function normalizeCartItemKey(product){
      return String(product && product.title ? product.title : '')
        .toLowerCase()
        .replace(/[\u201c\u201d\u00ab\u00bb"']/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    }
    function cartContainsProduct(product){
      const key = normalizeCartItemKey(product);
      if(!key) return false;
      return cartItems.some(function(item){ return normalizeCartItemKey(item) === key; });
    }
    function cartNoticeStyleVars(iconColor){
      const cfg = window.YOCheckout || {};
      const shadow = Math.max(0, Math.min(80, parseInt(cfg.cartNoticeShadowOpacity || 34, 10))) / 100;
      return '--yo-cart-notice-bg:'+(cfg.cartNoticeBgColor || '#07194b')+';'
        +'--yo-cart-notice-text:'+(cfg.cartNoticeTextColor || '#ffffff')+';'
        +'--yo-cart-notice-border:'+(cfg.cartNoticeBorderColor || '#1e87f0')+';'
        +'--yo-cart-notice-icon:'+(iconColor || cfg.cartNoticeIconColor || '#27c970')+';'
        +'--yo-cart-notice-radius:'+Math.max(0, Math.min(40, parseInt(cfg.cartNoticeBorderRadius || 14, 10)))+'px;'
        +'--yo-cart-notice-shadow-opacity:'+shadow+';';
    }
    function showAlreadyInCartMessage(){
      const plainMessage = window.YOCheckout?.cartDuplicateNotificationText || 'This item is already in the cart. Only one copy of each product can be added.';
      const htmlMessage = '<div class="yo-cart-duplicate-notice" style="'+cartNoticeStyleVars('#ff3b6b')+'"><span class="yo-cart-duplicate-icon">!</span><span>'+escHtml(plainMessage)+'</span></div>';
      if(window.UIkit && typeof window.UIkit.notification === 'function'){
        window.UIkit.notification({
          message: htmlMessage,
          status: 'primary',
          pos: window.YOCheckout?.cartAddedNotificationPosition || 'top-center',
          timeout: parseInt(window.YOCheckout?.cartDuplicateNotificationTimeout || 4200, 10)
        });
      } else {
        alert(plainMessage);
      }
    }
    function showAddedToCartMessage(product){
      if(window.YOCheckout && window.YOCheckout.cartAddedNotificationEnabled === false) return;
      const plainMessage = window.YOCheckout?.cartAddedNotificationTitle || 'Added to cart';
      const title = product && product.title ? String(product.title) : '';
      const htmlMessage = '<div class="yo-cart-added-notice" style="'+cartNoticeStyleVars()+'"><span class="yo-cart-added-icon">✓</span><span><strong>'+escHtml(plainMessage)+'</strong>' + (title ? '<small>'+escHtml(title)+'</small>' : '') + '</span></div>';
      if(window.UIkit && typeof window.UIkit.notification === 'function'){
        window.UIkit.notification({
          message: htmlMessage,
          status: 'primary',
          pos: window.YOCheckout?.cartAddedNotificationPosition || 'top-center',
          timeout: parseInt(window.YOCheckout?.cartAddedNotificationTimeout || 3200, 10)
        });
      } else {
        alert(plainMessage + (title ? '\n' + title : ''));
      }
    }
    function addToCart(product){
      if(cartContainsProduct(product)){
        showAlreadyInCartMessage();
        openCartModal();
        return false;
      }
      cartItems.push(product);
      syncAppliedCartPromo();
      if(!appliedCartPromo && byId('yo-promo-code')) byId('yo-promo-code').value='';
      if(byId('yo-promo-message')) byId('yo-promo-message').textContent='';
      saveCart();
      updateFloatingCart();
      renderCartSummary();
      const btn = ensureFloatingCart();
      btn.classList.add('yo-cart-pulse');
      setTimeout(function(){ btn.classList.remove('yo-cart-pulse'); }, 450);
      refreshReservedCards();
      showAddedToCartMessage(product);
      return true;
    }
    function openCartModal(){
      if(!cartItems.length) return;
      validateCartAvailability(true).then(function(){
        if(!cartItems.length) return;
        buildSelectedProductFromCart();
        current={localId:(current.localId || getStoredDraftLocalId() || ''), orderId:(current.orderId || getStoredKeycrmOrderId() || ''), invoiceId:'', customer:current.customer || {}, cardFee:null, bankTotal:null, shipping:null, shippingOptions:[]};
        checkoutSessionId();
        const frame = byId('yo-payment-frame'); if(frame) frame.src='about:blank';
        renderCartSummary();
        setStep(1);
        if(window.UIkit) UIkit.modal('#yo-pay-modal').show();
      });
    }

    function countryList(){
      return Array.isArray(window.YOCheckout?.countries) ? window.YOCheckout.countries : [];
    }
    function initCountryAutocomplete(){
      const input = byId('yo-country-input');
      const list = byId('yo-country-suggestions');
      if(!input || !list || input.dataset.yoAutocompleteReady) return;
      input.dataset.yoAutocompleteReady = '1';
      const countries = countryList();
      let activeIndex = -1;
      function close(){ list.innerHTML=''; list.classList.remove('is-open'); activeIndex=-1; }
      function pick(value){ input.value=value; input.dataset.validCountry=value; close(); input.dispatchEvent(new Event('change', {bubbles:true})); }
      function matches(query){
        const q = String(query || '').trim().toLowerCase();
        if(!q) return countries.slice(0, 12);
        const starts = countries.filter(c => c.toLowerCase().startsWith(q));
        const contains = countries.filter(c => !c.toLowerCase().startsWith(q) && c.toLowerCase().includes(q));
        return starts.concat(contains).slice(0, 12);
      }
      function render(){
        const values = matches(input.value);
        if(!values.length){ list.innerHTML='<div class="yo-country-empty">No country found</div>'; list.classList.add('is-open'); return; }
        list.innerHTML = values.map(function(c, i){ return '<button type="button" class="yo-country-option" role="option" data-index="'+i+'" data-country="'+escHtml(c)+'">'+escHtml(c)+'</button>'; }).join('');
        list.classList.add('is-open');
        activeIndex = -1;
      }
      input.addEventListener('input', function(){ input.dataset.validCountry=''; render(); });
      input.addEventListener('focus', render);
      input.addEventListener('keydown', function(e){
        const opts = Array.from(list.querySelectorAll('.yo-country-option'));
        if(!list.classList.contains('is-open') && ['ArrowDown','ArrowUp'].includes(e.key)){ render(); return; }
        if(e.key === 'ArrowDown'){
          e.preventDefault(); activeIndex = Math.min(opts.length-1, activeIndex+1); opts.forEach(o=>o.classList.remove('is-active')); if(opts[activeIndex]) opts[activeIndex].classList.add('is-active');
        } else if(e.key === 'ArrowUp'){
          e.preventDefault(); activeIndex = Math.max(0, activeIndex-1); opts.forEach(o=>o.classList.remove('is-active')); if(opts[activeIndex]) opts[activeIndex].classList.add('is-active');
        } else if(e.key === 'Enter' && activeIndex >= 0 && opts[activeIndex]){
          e.preventDefault(); pick(opts[activeIndex].dataset.country || opts[activeIndex].textContent.trim());
        } else if(e.key === 'Escape'){
          close();
        }
      });
      list.addEventListener('mousedown', function(e){
        const opt = e.target.closest('.yo-country-option');
        if(!opt) return;
        e.preventDefault();
        pick(opt.dataset.country || opt.textContent.trim());
      });
      input.addEventListener('blur', function(){ setTimeout(function(){
        const exact = countries.find(c => c.toLowerCase() === String(input.value || '').trim().toLowerCase());
        if(exact) input.value = exact;
        close();
      }, 180); });
      const form = input.closest('form');
      if(form){
        form.addEventListener('submit', function(e){
          const exact = countries.find(c => c.toLowerCase() === String(input.value || '').trim().toLowerCase());
          if(exact) input.value = exact;
        }, true);
      }
    }

    function renderCardFeeNote(){
      const box = byId('yo-checkout-receipt');
      if(!box || !current.cardFee) return;
      const f=current.cardFee;
      const b=current.bankTotal || {total:(parseFloat(f.product||f.base||0)+parseFloat(f.shipping||0))};
      const original = parseFloat(selectedProduct.original_price_eur || f.original || f.product || selectedProduct.price_eur || 0) || 0;
      const finalPrice = parseFloat(f.product || selectedProduct.price_eur || 0) || 0;
      const discount = Math.max(0, parseFloat(selectedProduct.discount_eur || (original - finalPrice) || 0) || 0);
      const discountLabel = selectedProduct.promo_applied ? 'Promo code discount' : 'Discount';
      let rows = '<div class=\"yo-receipt-title\"><strong>Order summary</strong></div>' +
        '<div class=\"yo-receipt-product\">'+escHtml(selectedProduct.title || current.customer.product || 'Selected leotard')+'</div>' +
        '<div class=\"yo-receipt-row\"><span>Regular leotard price</span><strong>€'+money(original || finalPrice)+'</strong></div>';
      if(discount > 0){
        rows += '<div class=\"yo-receipt-row yo-discount-row\"><span>'+escHtml(discountLabel)+'</span><strong>-€'+money(discount)+'</strong></div>' +
          '<div class=\"yo-receipt-row\"><span>Leotard price after discount</span><strong>€'+money(finalPrice)+'</strong></div>';
      }
      if(current.shippingOptions && current.shippingOptions.length > 1){
        rows += '<div class=\"yo-shipping-options\"><div class=\"yo-shipping-title\"><strong>Choose delivery option</strong></div>';
        current.shippingOptions.forEach(function(opt){
          const checked = current.shipping && current.shipping.selected_key && String(current.shipping.selected_key) === String(opt.key) ? ' checked' : '';
          rows += '<label class=\"yo-shipping-option\"><input type=\"radio\" name=\"yo_shipping_option\" value=\"'+escHtml(opt.key || '')+'\"'+checked+'> <span>'+escHtml(opt.label || 'Delivery')+'</span><strong>€'+money(opt.amount || opt.amount_eur || 0)+'</strong></label>';
        });
        rows += '</div>';
      }
      rows += '<div class=\"yo-receipt-row\"><span>Delivery to '+escHtml(current.shipping?.country || 'your country')+(current.shipping?.selected_label ? ' · '+escHtml(current.shipping.selected_label) : '')+'</span><strong>€'+money(f.shipping || 0)+'</strong></div>' +
        '<div class=\"yo-receipt-row\"><span>Card payment service fee '+money(f.percent)+'%</span><strong>€'+money(f.fee)+'</strong></div>' +
        '<div class=\"yo-receipt-total\"><span>Total card payment</span><strong>€'+money(f.total)+'</strong></div>' +
        '<p class=\"yo-receipt-note\">Card payments are processed by a third-party provider. If you choose SEPA/SWIFT invoice, the card payment service fee is not added. Bank invoice total: €'+money(b.total)+'.</p>';
      box.innerHTML = rows;
      box.querySelectorAll('input[name="yo_shipping_option"]').forEach(function(input){
        input.addEventListener('change', function(){ updateShippingOption(this.value); });
      });
      renderPromoBox();
    }
    function updateShippingOption(key){
      if(!current.localId || !key) return;
      const fd = new FormData();
      fd.append('local_id', current.localId);
      fd.append('shipping_key', key);
      post('yo_checkout_update_shipping_option', fd).then(function(data){
        if(data.success){
          current.shipping = data.data.shipping || current.shipping;
          current.shippingOptions = data.data.shippingOptions || current.shippingOptions || [];
          current.cardFee = data.data.cardFee || current.cardFee;
          current.bankTotal = data.data.bankTotal || current.bankTotal;
          renderCardFeeNote();
        } else {
          alert(data.data?.message || 'Shipping option error');
        }
      }).catch(function(){ alert('Connection error while updating shipping option'); });
    }
    function renderPromoBox(){
      const box = byId('yo-promo-box');
      const input = byId('yo-promo-code');
      const btn = byId('yo-apply-promo');
      const msg = byId('yo-promo-message');
      const available = byId('yo-available-promo');
      if(!box) return;
      if(!promoEnabled()) { box.classList.add('yo-hidden'); if(available) available.textContent=''; return; }
      box.classList.remove('yo-hidden');
      const eligibleBase = cartEligiblePromoBase();
      syncAppliedCartPromo();
      const activePromoCode = appliedCartPromo && appliedCartPromo.code ? String(appliedCartPromo.code).trim() : '';
      if(available){
        const code = activePromoCode || String(window.YOCheckout?.promoCode || '').trim();
        available.classList.remove('yo-available-promo-used');
        if(code && eligibleBase > 0 && activePromoCode){
          available.textContent = 'Promo code used: ' + code;
          available.classList.add('yo-available-promo-used');
        } else {
          available.textContent = (code && eligibleBase > 0) ? ('Available promo code: ' + code) : '';
        }
      }
      if(eligibleBase <= 0){
        if(input) input.disabled = true;
        if(btn) btn.disabled = true;
        if(msg) { msg.className='yo-promo-message yo-promo-warning'; msg.textContent='All selected products already have an active discount.'; }
        return;
      }
      if(appliedCartPromo || selectedProduct.promo_applied){
        if(input) { input.disabled = true; if(activePromoCode) input.value = activePromoCode; }
        if(btn) btn.disabled = true;
        if(msg) { msg.className='yo-promo-message yo-promo-success'; msg.textContent='Promo code applied.'; }
        return;
      }
      if(input) input.disabled = false;
      if(btn) btn.disabled = false;
      if(msg) { msg.className='yo-promo-message'; msg.textContent=''; }
    }
    function parseEuro(value){
      if(!value) return 0;
      const n = String(value).replace(',', '.').replace(/[^0-9.]/g, '');
      return parseFloat(n) || 0;
    }
    function parseProductWeight(value){
      if(value === undefined || value === null || value === '') return 0;
      const n = String(value).replace(',', '.').replace(/[^0-9.]/g, '');
      return parseFloat(n) || 0;
    }
    function extractWeightFromText(text){
      if(!text) return 0;
      const s = String(text);
      const patterns = [
        /data-weight\s*=\s*["']([^"']+)["']/i,
        /data-product-weight\s*=\s*["']([^"']+)["']/i,
        /data-weight=&quot;([^&]+)&quot;/i,
        /data-product-weight=&quot;([^&]+)&quot;/i
      ];
      for(const re of patterns){
        const m = s.match(re);
        if(m && m[1]){
          const w = parseProductWeight(m[1]);
          if(w > 0) return +w.toFixed(3);
        }
      }
      return 0;
    }
    function productWeightFromCard(card, priceNode){
      const ariaNode = card && card.querySelector('[aria-label*="data-weight"], [aria-label*="data-product-weight"]');
      const candidates = [
        priceNode && priceNode.getAttribute('data-weight'),
        priceNode && priceNode.getAttribute('data-product-weight'),
        card && card.getAttribute('data-weight'),
        card && card.getAttribute('data-product-weight'),
        card && card.querySelector('[data-weight]') && card.querySelector('[data-weight]').getAttribute('data-weight'),
        card && card.querySelector('[data-product-weight]') && card.querySelector('[data-product-weight]').getAttribute('data-product-weight'),
        ariaNode && extractWeightFromText(ariaNode.getAttribute('aria-label')),
        card && extractWeightFromText(card.innerHTML)
      ];
      for(const v of candidates){
        const w = parseProductWeight(v);
        if(w > 0) return +w.toFixed(3);
      }
      return 0;
    }
    function cartTotalWeight(){
      const total = cartItems.reduce(function(sum, item){ return sum + (parseProductWeight(item.weight_kg || item.product_weight_kg || item.weight || 0) || 0); }, 0);
      return total > 0 ? +total.toFixed(3) : 0;
    }
    function findProductPrices(card, btn, priceNode){
      const finalPrice = parseEuro(priceNode.getAttribute('data-eur') || priceNode.textContent);
      let originalPrice = finalPrice;
      const oldSaleNode = card.querySelector('.sale-old-btn .yo-price[data-eur], .sale-old-btn [data-eur]');
      if (oldSaleNode) {
        originalPrice = parseEuro(oldSaleNode.getAttribute('data-eur') || oldSaleNode.textContent) || finalPrice;
      }
      if (originalPrice < finalPrice) originalPrice = finalPrice;
      const discount = Math.max(0, +(originalPrice - finalPrice).toFixed(2));
      return { finalPrice: finalPrice.toFixed(2), originalPrice: originalPrice.toFixed(2), discount: discount.toFixed(2) };
    }
    document.addEventListener('click', function(e){
      const removeBtn = e.target.closest('[data-cart-remove]');
      if(!removeBtn) return;
      e.preventDefault();
      removeCartItem(parseInt(removeBtn.getAttribute('data-cart-remove'), 10));
    });

    document.addEventListener('click', function(e){
      const btn=e.target.closest('.yo-main-buy-btn'); if(!btn) return;
      e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
      const card=closestProductCardFromNode(btn); if(!card) return;
      const priceNode=btn.querySelector('.yo-price[data-eur]');
      const titleNode=card.querySelector('.el-title');
      const imgNode=card.querySelector('img');
      if(!priceNode || !titleNode) return;
      const prices = findProductPrices(card, btn, priceNode);
      const product = {
        title:titleNode.textContent.trim(),
        price_eur:prices.finalPrice,
        original_price_eur:prices.originalPrice,
        discount_eur:prices.discount,
        product_discount_eur:prices.discount,
        image_url:imgNode?imgNode.src:'',
        weight_kg: productWeightFromCard(card, priceNode)
      };
      btn.disabled = true;
      reserveProduct(product).then(function(data){
        if(data && data.success){
          const expires = data.data && data.data.expires ? data.data.expires : null;
          const badgeText = data.data && data.data.badgeText ? data.data.badgeText : (window.YOCheckout?.reservationBadgeText || 'Reserved');
          applyReservedState(card, badgeText, expires);
          addToCart(product);
          refreshReservedCards();
        } else {
          btn.disabled = false;
          showReservationErrorMessage(data && data.data && data.data.message ? data.data.message : 'This item is currently reserved or no longer available. Please choose another model.');
          refreshReservedCards();
        }
      }).catch(function(){ btn.disabled = false; showReservationErrorMessage('Connection error. Please try again.'); });
    }, true);
    byId('yo-pay-form')?.addEventListener('submit', function(e){
      e.preventDefault();
      validateCartAvailability(true).then(function(){
        if(!cartItems.length){ setStep(1); return; }
        const fd=new FormData(e.target);
        const cartMarker = getCartKeycrmMarker();
        const draftLocalId = current.localId || (cartMarker && cartMarker.local_id ? cartMarker.local_id : '') || getStoredDraftLocalId() || getCookie('yo_checkout_local_order_id');
        if(draftLocalId) fd.append('local_id', draftLocalId);
        fd.append('checkout_session_id', checkoutSessionId());
        fd.append('buyer_id', buyerId());
        const storedKeycrmOrderId = current.orderId || (cartMarker && cartMarker.keycrm_order_id ? cartMarker.keycrm_order_id : '') || getStoredKeycrmOrderId();
        if(storedKeycrmOrderId) fd.append('keycrm_order_id', storedKeycrmOrderId);
        if(cartMarker && cartMarker.keycrm_order_id){ fd.append('cart_marker_keycrm_order_id', cartMarker.keycrm_order_id); if(cartMarker.local_id) fd.append('cart_marker_local_id', cartMarker.local_id); fd.append('cart_marker_json', JSON.stringify(cartMarker)); }
        buildSelectedProductFromCart();
        fd.append('title', selectedProduct.title); fd.append('price_eur', selectedProduct.price_eur); fd.append('original_price_eur', selectedProduct.original_price_eur || selectedProduct.price_eur); fd.append('discount_eur', selectedProduct.discount_eur || '0.00'); fd.append('image_url', selectedProduct.image_url);
        if(selectedProduct.shipping_weight_kg){ fd.append('shipping_weight_kg', String(selectedProduct.shipping_weight_kg)); }
        if(selectedProduct.promo_applied && selectedProduct.promo_code_applied){ fd.append('promo_code_applied', selectedProduct.promo_code_applied); }
        fd.append('cart_items_count', String(cartItems.length));
        fd.append('cart_items_json', JSON.stringify(cartItems.map(function(item){
          const promoDiscount = cartItemPromoDiscount(item);
          const productDiscount = parseFloat(item.product_discount_eur || item.discount_eur || 0) || 0;
          return {title:item.title, price_eur:item.price_eur, original_price_eur:item.original_price_eur, discount_eur:money(productDiscount + promoDiscount), product_discount_eur:money(productDiscount), promo_discount_eur:money(promoDiscount), image_url:item.image_url, weight_kg:item.weight_kg || item.product_weight_kg || item.weight || ''};
        })));
        current.customer={name:fd.get('full_name'), email:fd.get('email'), phone:fd.get('phone'), product:selectedProduct.title, amount:selectedProduct.price_eur};
        setStep('loading');
        post('yo_checkout_create_order', fd).then(function(data){
          if(data.success){ rememberKeycrmFromResponse(data); current.localId=data.data.localId; storeDraftLocalId(current.localId); current.orderId=data.data.orderId || current.orderId || getStoredKeycrmOrderId(); if(current.orderId){ storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId); } current.cardFee=data.data.cardFee || null; current.shipping=data.data.shipping || null; current.shippingOptions=data.data.shippingOptions || []; current.bankTotal=data.data.bankTotal || null; current.customer.amount = current.cardFee ? money(current.cardFee.total) : selectedProduct.price_eur; const t=byId('yo-accept-terms'); if(t) t.checked=false; updateTermsButtons(); renderCardFeeNote(); setStep(2); }
          else { alert((data.data?.message || 'Order error')+'\n\n'+JSON.stringify(data.data?.details || data, null, 2)); setStep(1); }
        }).catch(function(){ alert('Connection error'); setStep(1); });
      });
    });

    function stopPaymentPolling(){
      if(paymentTimer){ clearInterval(paymentTimer); paymentTimer = null; }
    }
    byId('yo-back-to-details')?.addEventListener('click', function(){
      stopPaymentPolling();
      const frame = byId('yo-payment-frame');
      if(frame) frame.src='about:blank';
      if(current.orderId){ storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId || getStoredDraftLocalId() || ''); } if(current.localId) storeDraftLocalId(current.localId);
      setStep(1);
      setTimeout(initCountryAutocomplete, 50);
    });
    byId('yo-back-to-method-from-payment')?.addEventListener('click', function(){
      stopPaymentPolling();
      const frame = byId('yo-payment-frame');
      if(frame) frame.src='about:blank';
      setStep(2);
    });
    byId('yo-back-to-method-from-bank')?.addEventListener('click', function(){
      setStep(2);
    });
    byId('yo-accept-terms')?.addEventListener('change', updateTermsButtons);
    byId('yo-apply-promo')?.addEventListener('click', function(){
      buildSelectedProductFromCart();
      const eligibleBase = cartEligiblePromoBase();
      if(eligibleBase <= 0){ renderPromoBox(); return; }
      const code = String(byId('yo-promo-code')?.value || '').trim();
      const msg = byId('yo-promo-message');
      if(!code){ if(msg){ msg.className='yo-promo-message yo-promo-warning'; msg.textContent='Please enter promo code.'; } return; }
      const fd = new FormData();
      // Cart promo is calculated only against products without an existing product discount.
      // Do not send local_id here: an existing draft order may already contain product-level
      // discounts, and the server would reject the promo as if the whole cart was discounted.
      fd.append('promo_code', code);
      fd.append('base_price', money(eligibleBase));
      const btn = byId('yo-apply-promo'); if(btn) btn.disabled = true;
      if(msg){ msg.className='yo-promo-message'; msg.textContent='Checking promo code...'; }
      post('yo_checkout_apply_promo_code', fd).then(function(data){
        if(data.success){
          appliedCartPromo = {code: code, discount_eur: data.data.discount_eur};
          selectedProduct.price_eur = data.data.price_eur;
          selectedProduct.discount_eur = data.data.discount_eur;
          selectedProduct.original_price_eur = data.data.original_price_eur || selectedProduct.original_price_eur;
          selectedProduct.promo_applied = true;
          selectedProduct.promo_code_applied = code;
          current.cardFee = data.data.cardFee || current.cardFee;
          current.shipping = data.data.shipping || current.shipping;
          current.shippingOptions = data.data.shippingOptions || current.shippingOptions || [];
          current.bankTotal = data.data.bankTotal || current.bankTotal;
          current.customer.amount = current.cardFee ? money(current.cardFee.total) : selectedProduct.price_eur;
          saveCart();
          renderCartSummary();
          renderCardFeeNote();
          renderPromoBox();
        } else {
          if(btn) btn.disabled = false;
          if(msg){ msg.className='yo-promo-message yo-promo-warning'; msg.textContent=data.data?.message || 'Promo code was not applied.'; }
        }
      }).catch(function(){ if(btn) btn.disabled=false; if(msg){ msg.className='yo-promo-message yo-promo-warning'; msg.textContent='Connection error'; } });
    });

    byId('yo-pay-card')?.addEventListener('click', function(){
      if(!byId('yo-accept-terms')?.checked){ alert('Please confirm that you agree to the Terms & Conditions before continuing.'); return; }
      validateCartAvailability(true).then(function(v){
      if(v && v.removed > 0){ setStep(1); return; }
      const fd=new FormData(); fd.append('local_id', current.localId); fd.append('checkout_session_id', checkoutSessionId()); fd.append('buyer_id', buyerId()); if(current.orderId || getStoredKeycrmOrderId()) fd.append('keycrm_order_id', current.orderId || getStoredKeycrmOrderId()); const cartMarker = getCartKeycrmMarker(); if(cartMarker && cartMarker.keycrm_order_id){ fd.append('cart_marker_keycrm_order_id', cartMarker.keycrm_order_id); if(cartMarker.local_id) fd.append('cart_marker_local_id', cartMarker.local_id); fd.append('cart_marker_json', JSON.stringify(cartMarker)); }
      setStep('loading');
      post('yo_checkout_start_card_payment', fd).then(function(data){
        if(data.success && data.data.pageUrl){ rememberKeycrmFromResponse(data); current.invoiceId=data.data.invoiceId || current.invoiceId || ''; if(data.data.localId){ current.localId=String(data.data.localId); storeDraftLocalId(current.localId); } else if(data.data.cartMarker && data.data.cartMarker.local_id){ current.localId=String(data.data.cartMarker.local_id); storeDraftLocalId(current.localId); } current.orderId=''; byId('yo-payment-frame').src=data.data.pageUrl; yoDebug('start_card_payment success', {invoiceId: current.invoiceId, localId: current.localId, orderId: current.orderId, pageUrl: data.data.pageUrl}); setStep(3); startPaymentPolling(); }
        else { alert((data.data?.message || 'Payment error')+'\n\n'+JSON.stringify(data.data?.details || data, null, 2)); setStep(2); }
      }).catch(function(){ alert('Connection error'); setStep(2); });
      });
    });
    byId('yo-pay-bank')?.addEventListener('click', function(){
      if(bankInvoiceInProgress) return;
      if(!byId('yo-accept-terms')?.checked){ alert('Please confirm that you agree to the Terms & Conditions before continuing.'); return; }
      bankInvoiceInProgress = true;
      const bankDebugId = checkoutDebugId('bank');
      const bankBtn = byId('yo-pay-bank');
      if(bankBtn) bankBtn.disabled = true;
      validateCartAvailability(true).then(function(v){
      if(v && v.removed > 0){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; setStep(1); return; }
      if(!cartItems.length){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; setStep(1); return; }
      const form = byId('yo-pay-form');
      if(!form){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert('Order form is missing. Please refresh the page.'); setStep(1); return; }
      const orderFd = new FormData(form);
      const cartMarkerForOrder = getCartKeycrmMarker();
      const draftLocalId = current.localId || (cartMarkerForOrder && cartMarkerForOrder.local_id ? cartMarkerForOrder.local_id : '') || getStoredDraftLocalId() || getCookie('yo_checkout_local_order_id');
      if(draftLocalId) orderFd.append('local_id', draftLocalId);
      orderFd.append('yo_checkout_debug_id', bankDebugId);
      orderFd.append('checkout_session_id', checkoutSessionId());
      orderFd.append('buyer_id', buyerId());
      const storedKeycrmOrderId = current.orderId || (cartMarkerForOrder && cartMarkerForOrder.keycrm_order_id ? cartMarkerForOrder.keycrm_order_id : '') || getStoredKeycrmOrderId();
      if(storedKeycrmOrderId) orderFd.append('keycrm_order_id', storedKeycrmOrderId);
      if(cartMarkerForOrder && cartMarkerForOrder.keycrm_order_id){ orderFd.append('cart_marker_keycrm_order_id', cartMarkerForOrder.keycrm_order_id); if(cartMarkerForOrder.local_id) orderFd.append('cart_marker_local_id', cartMarkerForOrder.local_id); orderFd.append('cart_marker_json', JSON.stringify(cartMarkerForOrder)); }
      buildSelectedProductFromCart();
      orderFd.append('title', selectedProduct.title); orderFd.append('price_eur', selectedProduct.price_eur); orderFd.append('original_price_eur', selectedProduct.original_price_eur || selectedProduct.price_eur); orderFd.append('discount_eur', selectedProduct.discount_eur || '0.00'); orderFd.append('image_url', selectedProduct.image_url);
      if(selectedProduct.shipping_weight_kg){ orderFd.append('shipping_weight_kg', String(selectedProduct.shipping_weight_kg)); }
      if(selectedProduct.promo_applied && selectedProduct.promo_code_applied){ orderFd.append('promo_code_applied', selectedProduct.promo_code_applied); }
      orderFd.append('cart_items_count', String(cartItems.length));
      orderFd.append('cart_items_json', JSON.stringify(cartItems.map(function(item){
        const promoDiscount = cartItemPromoDiscount(item);
        const productDiscount = parseFloat(item.product_discount_eur || item.discount_eur || 0) || 0;
        return {title:item.title, price_eur:item.price_eur, original_price_eur:item.original_price_eur, discount_eur:money(productDiscount + promoDiscount), product_discount_eur:money(productDiscount), promo_discount_eur:money(promoDiscount), image_url:item.image_url, weight_kg:item.weight_kg || item.product_weight_kg || item.weight || ''};
      })));
      setStep('loading');
      post('yo_checkout_create_order', orderFd).then(function(orderData){
        if(!orderData.success){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert((orderData.data?.message || 'Order error')+'\n\n'+JSON.stringify(orderData.data?.details || orderData, null, 2)); setStep(1); return; }
        rememberKeycrmFromResponse(orderData);
        current.localId = orderData.data.localId;
        storeDraftLocalId(current.localId);
        current.orderId = orderData.data.orderId || current.orderId || getStoredKeycrmOrderId();
        if(current.orderId){ storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId); }
        current.cardFee = orderData.data.cardFee || null;
        current.shipping = orderData.data.shipping || null;
        current.shippingOptions = orderData.data.shippingOptions || [];
        current.bankTotal = orderData.data.bankTotal || null;
        submitBankInvoice(0);
      }).catch(function(err){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert(checkoutAjaxErrorMessage('yo_checkout_create_order', err, {debugId: bankDebugId, localId: draftLocalId, orderId: storedKeycrmOrderId, cartItems: cartItems.length})); setStep(2); });
      function submitBankInvoice(attempt){
        attempt = attempt || 0;
        const fd=new FormData(); fd.append('local_id', current.localId); fd.append('yo_checkout_debug_id', bankDebugId); fd.append('checkout_session_id', checkoutSessionId()); fd.append('buyer_id', buyerId()); if(current.orderId || getStoredKeycrmOrderId()) fd.append('keycrm_order_id', current.orderId || getStoredKeycrmOrderId()); const cartMarker = getCartKeycrmMarker(); if(cartMarker && cartMarker.keycrm_order_id){ fd.append('cart_marker_keycrm_order_id', cartMarker.keycrm_order_id); if(cartMarker.local_id) fd.append('cart_marker_local_id', cartMarker.local_id); fd.append('cart_marker_json', JSON.stringify(cartMarker)); }
        setStep('loading');
        post('yo_checkout_create_bank_invoice', fd).then(function(data){
          if(data.success && data.data && data.data.preparing && attempt < 12){
            setTimeout(function(){ submitBankInvoice(attempt + 1); }, Math.max(1, parseInt(data.data.retryAfter || 2, 10) || 2) * 1000);
            return;
          }
          if(data.success && (data.data.invoiceUrl || data.data.htmlUrl)){
            rememberKeycrmFromResponse(data);
            if(data.data.orderId){ current.orderId=data.data.orderId; storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId); }
            const link = data.data.invoiceUrl || data.data.htmlUrl;
            byId('yo-bank-invoice-link').href = link;
            byId('yo-bank-invoice-link').textContent = data.data.invoiceUrl ? 'Download PDF invoice' : 'Open invoice';
            if(byId('yo-bank-invoice-html-link')) byId('yo-bank-invoice-html-link').href = data.data.htmlUrl || link;
            setStep('bank');
            clearCartAfterInvoiceOrder();
            if(!data.data.invoiceUrl && data.data.pdfMessage){
              console.warn('YOleotard invoice PDF was not generated:', data.data.pdfMessage);
            }
          }
          else { bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert((data.data?.message || 'Invoice error')+'\n\n'+JSON.stringify(data.data?.details || data, null, 2)); setStep(2); }
        }).catch(function(err){
          if(attempt < 2){ setTimeout(function(){ submitBankInvoice(attempt + 1); }, 2000); return; }
          bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert(checkoutAjaxErrorMessage('yo_checkout_create_bank_invoice', err, {debugId: bankDebugId, localId: current.localId, orderId: current.orderId || getStoredKeycrmOrderId(), cartItems: cartItems.length})); setStep(2);
        });
      }
      }).catch(function(err){ bankInvoiceInProgress = false; if(bankBtn) bankBtn.disabled = false; alert(checkoutAjaxErrorMessage('yo_checkout_validate_cart_items', err, {debugId: bankDebugId, localId: current.localId, orderId: current.orderId || getStoredKeycrmOrderId(), cartItems: cartItems.length})); setStep(2); });
    });
    function releaseReservationsForItems(items){
      const seen = new Set();
      (items || []).forEach(function(item){
        const title = item && item.title ? String(item.title) : '';
        const key = title.toLowerCase().trim();
        if(title && !seen.has(key)){ seen.add(key); releaseReservationForTitle(title); }
      });
    }
    function hideProductCard(card){
      if(!card) return;
      const wrapper = card.closest('[data-tag]');
      const target = (wrapper && wrapper.querySelectorAll('.el-item').length === 1) ? wrapper : card;
      target.classList.add('yo-paid-card-hidden');
      target.style.display = 'none';
    }
    function hidePurchasedProductsForItems(items){
      const titles = (items || []).map(function(item){ return item && item.title ? String(item.title) : ''; }).filter(Boolean);
      if(!titles.length) return;
      const seen = new Set();
      const candidates = [];
      productCardsOnPage().forEach(function(card){
        if(card && !seen.has(card)){ seen.add(card); candidates.push(card); }
      });
      document.querySelectorAll('.el-item, .uk-card, li').forEach(function(card){
        if(card && !seen.has(card) && isProductCard(card)){ seen.add(card); candidates.push(card); }
      });
      candidates.forEach(function(card){
        const cardTitle = titleFromCard(card);
        for(const title of titles){
          if(sameProductTitle(cardTitle, title) || textMatchesTitle(cardTitle, title) || textMatchesTitle(title, cardTitle)){
            hideProductCard(card);
            break;
          }
        }
      });
    }
    function clearCartAfterSuccessfulPurchase(){
      const purchasedItems = cartItems.slice();
      releaseReservationsForItems(purchasedItems);
      hidePurchasedProductsForItems(purchasedItems);
      cartItems = [];
      appliedCartPromo = null;
      try { localStorage.removeItem(CART_STORAGE_KEY); } catch(e) {}
      clearCheckoutDraftSession();
      updateFloatingCart();
      renderCartSummary();
      setTimeout(refreshReservedCards, 400);
    }
    function clearCartAfterInvoiceOrder(){
      const invoicedItems = cartItems.slice();
      releaseReservationsForItems(invoicedItems);
      cartItems = [];
      appliedCartPromo = null;
      try { localStorage.removeItem(CART_STORAGE_KEY); } catch(e) {}
      clearCheckoutDraftSession();
      updateFloatingCart();
      renderCartSummary();
      setTimeout(refreshReservedCards, 400);
    }
    let paymentCompletedShown = false;
    function setTextSafe(id, value){ const el = byId(id); if(el) el.textContent = value == null ? '' : String(value); }
    function showSuccess(){
      yoDebugState('showSuccess called');
      if(paymentCompletedShown){ yoDebug('showSuccess skipped: already shown'); return; }
      paymentCompletedShown = true;
      const cust = current.customer || {};
      setTextSafe('yo-success-order-number', current.orderId || current.localId || current.invoiceId || '');
      setTextSafe('yo-success-name', cust.name || '');
      setTextSafe('yo-success-email', cust.email || '');
      setTextSafe('yo-success-phone', cust.phone || '');
      setTextSafe('yo-success-product', cust.product || (cartItems[0] && cartItems[0].title) || '');
      setTextSafe('yo-success-amount', cust.amount || (current.cardFee && current.cardFee.total ? money(current.cardFee.total) : ''));
      const frame = byId('yo-payment-frame'); if(frame) frame.src='about:blank';
      paymentPollingActive = false;
      activePaymentInvoiceId = '';
      activePaymentLocalId = '';
      setStep(4);
      try{
        if(window.YOCheckout?.googleReviewsOptinEnabled && typeof window.YOCheckoutGoogleReviews === 'function'){
          const reviewPayload = {
            order_id: current.orderId || current.localId || current.invoiceId,
            email: cust.email || '',
            delivery_country: yoGoogleReviewCountryCode(cust.country || ''),
            estimated_delivery_date: yoEstimatedDeliveryDate()
          };
          setTimeout(function(){ window.YOCheckoutGoogleReviews(reviewPayload); }, 400);
          setTimeout(function(){ window.YOCheckoutGoogleReviews(reviewPayload); }, 1800);
        }
      }catch(e){ console.warn('YO checkout reviews opt-in skipped', e); }
      clearCartAfterSuccessfulPurchase();
    }
    function waitForFinalOrderThenShowSuccess(reason){
      const localId = current.localId || activePaymentLocalId || getStoredDraftLocalId() || '';
      const invoiceId = current.invoiceId || activePaymentInvoiceId || '';
      let attempts = 0;
      const maxAttempts = 24; // about 36 seconds
      yoDebug('waitForFinalOrderThenShowSuccess started', {reason: reason || 'paid', invoiceId: invoiceId, localId: localId});
      function pollFinal(){
        attempts++;
        const fd = new FormData();
        fd.append('local_id', localId);
        fd.append('invoice_id', invoiceId);
        return post('yo_checkout_final_order_status', fd).then(function(data){
          const payload = data && data.success ? data.data : null;
          yoDebug('final_order_status response', payload || data);
          if(payload && payload.localId){ current.localId = String(payload.localId); storeDraftLocalId(current.localId); }
          if(payload && payload.amount){ current.customer = current.customer || {}; current.customer.amount = money(payload.amount); }
          if(payload && payload.orderId && /^\d+$/.test(String(payload.orderId))){
            current.orderId = String(payload.orderId);
            storeKeycrmOrderId(current.orderId);
            storeCartKeycrmMarker(current.orderId, current.localId || localId || '');
          }
          if(payload && payload.ready && current.orderId){
            showSuccess();
            return true;
          }
          if(attempts >= maxAttempts){
            yoDebug('final_order_status timeout: showing success with available order id', {orderId: current.orderId || '', localId: current.localId || localId || ''});
            showSuccess();
            return true;
          }
          setTimeout(pollFinal, 1500);
          return false;
        }).catch(function(err){
          yoDebug('final_order_status error', {error: (err && err.message) ? err.message : String(err), attempt: attempts});
          if(attempts >= maxAttempts){ showSuccess(); return true; }
          setTimeout(pollFinal, 1500);
          return false;
        });
      }
      return pollFinal();
    }

    function checkPaymentOnce(reason){
      const activeInvoice = activePaymentInvoiceId || current.invoiceId || '';
      const activeLocal = activePaymentLocalId || current.localId || '';
      if(!paymentPollingActive || (!activeInvoice && !activeLocal)){
        yoDebug('checkPaymentOnce skipped: no active payment session', {reason: reason || 'poll', invoiceId: current.invoiceId || '', localId: current.localId || getStoredDraftLocalId() || '', activeInvoiceId: activePaymentInvoiceId || '', activeLocalId: activePaymentLocalId || ''});
        return Promise.resolve(false);
      }
      yoDebug('checkPaymentOnce request', {reason: reason || 'poll', invoiceId: activeInvoice, localId: activeLocal});
      const fd=new FormData();
      fd.append('invoice_id', activeInvoice);
      fd.append('local_id', activeLocal);
      fd.append('poll_reason', reason || 'poll');
      return post('yo_checkout_check_payment_status', fd).then(function(data){
        yoDebug('checkPaymentOnce response', data && data.success ? data.data : data);
        if(data && data.success && data.data && data.data.localId){
          const returnedLocal = String(data.data.localId);
          if(activeLocal && returnedLocal !== String(activeLocal)){
            yoDebug('checkPaymentOnce ignored: returned localId does not match active payment', {returnedLocalId: returnedLocal, activeLocalId: activeLocal, reason: reason || 'poll'});
            return false;
          }
          current.localId=returnedLocal; storeDraftLocalId(current.localId);
        }
        if(data && data.success && data.data && data.data.orderId){ current.orderId=String(data.data.orderId); storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId || getStoredDraftLocalId() || ''); }
        if(data && data.success && data.data && data.data.paid){
          if(activePaymentInvoiceId && current.invoiceId && String(activePaymentInvoiceId) !== String(current.invoiceId)){
            yoDebug('checkPaymentOnce ignored: invoice mismatch', {activeInvoiceId: activePaymentInvoiceId, currentInvoiceId: current.invoiceId});
            return false;
          }
          if(paymentTimer) { clearInterval(paymentTimer); paymentTimer=null; }
          waitForFinalOrderThenShowSuccess(reason || 'paid');
          return true;
        }
        return false;
      }).catch(function(err){ yoDebug('checkPaymentOnce error', {reason: reason || 'poll', error: (err && err.message) ? err.message : String(err)}); console.warn('YO checkout payment poll failed', reason || 'poll', err); return false; });
    }
    window.addEventListener('message', function(event){
      const msg = event.data || {};
      if(!msg || msg.type !== 'yo_wayforpay_return') return;
      if(msg.invoiceId && current.invoiceId && msg.invoiceId !== current.invoiceId) return;
      checkPaymentOnce('wayforpay-message');
      setTimeout(function(){ checkPaymentOnce('wayforpay-message-delay'); }, 2500);
    });
    window.addEventListener('focus', function(){ if(paymentPollingActive) checkPaymentOnce('window-focus'); });
    document.addEventListener('visibilitychange', function(){ if(!document.hidden && paymentPollingActive) checkPaymentOnce('visibility'); });
    function startPaymentPolling(){
      if(!current.invoiceId && !current.localId){ yoDebug('startPaymentPolling skipped: missing active invoice/local id'); return; }
      paymentPollingActive = true;
      activePaymentInvoiceId = current.invoiceId || '';
      activePaymentLocalId = current.localId || getStoredDraftLocalId() || '';
      yoDebugState('startPaymentPolling');
      yoDebug('active payment session started', {invoiceId: activePaymentInvoiceId, localId: activePaymentLocalId});
      if(paymentTimer) clearInterval(paymentTimer);
      paymentCompletedShown = false;
      setTimeout(function(){ checkPaymentOnce('start-900'); }, 900);
      setTimeout(function(){ checkPaymentOnce('start-2500'); }, 2500);
      setTimeout(function(){ checkPaymentOnce('start-5000'); }, 5000);
      setTimeout(function(){ checkPaymentOnce('start-9000'); }, 9000);
      let attempts=0;
      paymentTimer=setInterval(function(){
        attempts++;
        const fd=new FormData(); fd.append('invoice_id', activePaymentInvoiceId || current.invoiceId || ''); fd.append('local_id', activePaymentLocalId || current.localId || ''); fd.append('poll_reason', 'interval-'+attempts);
        post('yo_checkout_check_payment_status', fd).then(function(data){
          yoDebug('interval response', data && data.success ? data.data : data);
          if(data.success && data.data.paid){
            if(data.data.localId){
              const returnedLocal = String(data.data.localId);
              if(activePaymentLocalId && returnedLocal !== String(activePaymentLocalId)){
                yoDebug('interval paid ignored: returned localId does not match active payment', {returnedLocalId: returnedLocal, activeLocalId: activePaymentLocalId});
                return;
              }
              current.localId=returnedLocal; storeDraftLocalId(current.localId);
            }
            if(data.data.orderId){ current.orderId=String(data.data.orderId); storeKeycrmOrderId(current.orderId); storeCartKeycrmMarker(current.orderId, current.localId || getStoredDraftLocalId() || ''); }
            clearInterval(paymentTimer); paymentTimer=null;
            showSuccess();
          }
        });
        if(attempts>=60) clearInterval(paymentTimer);
      }, 3000);
    }
    const paymentFrameEl = byId('yo-payment-frame');
    if(paymentFrameEl){
      paymentFrameEl.addEventListener('load', function(){
        if(paymentPollingActive){
          yoDebugState('payment iframe load');
          setTimeout(function(){ checkPaymentOnce('iframe-load-600'); }, 600);
          setTimeout(function(){ checkPaymentOnce('iframe-load-1800'); }, 1800);
        } else {
          yoDebug('payment iframe load ignored: no active payment session', {invoiceId: current.invoiceId || '', localId: current.localId || getStoredDraftLocalId() || '', iframe: paymentFrameEl.src || ''});
        }
      });
    }
    initCountryAutocomplete(); loadCart();
    try{ const __m = getCartKeycrmMarker(); if(__m && __m.keycrm_order_id){ current.orderId = String(__m.keycrm_order_id); if(__m.local_id) current.localId = String(__m.local_id); } }catch(e){} ensureFloatingCart(); updateFloatingCart(); renderCartSummary(); validateCartAvailability(true); addPayButtons(); refreshReservedCards(); setInterval(updateReservedCountdowns, 1000); setInterval(refreshReservedCards, 60000); setTimeout(function(){ addPayButtons(); refreshReservedCards(); },500); setTimeout(function(){ addPayButtons(); refreshReservedCards(); },1500); setTimeout(function(){ addPayButtons(); refreshReservedCards(); },3000);
  });
})();
