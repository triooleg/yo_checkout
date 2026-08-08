(function(){
  'use strict';

  const config = window.YOManagerPurchase || {};
  if(!config.enabled) return;

  function clean(value){
    return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
  }

  function productId(card, title){
    const raw = card.getAttribute('data-product-id') || card.getAttribute('data-feed-id') || card.id || '';
    const saved = String(raw).replace(/[^A-Za-z0-9_-]/g, '');
    if(saved) return saved;
    return clean(title).toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 120);
  }

  function productData(card, buyButton){
    const title = clean(card.querySelector('.el-title')?.textContent || 'YOleotard model');
    const image = card.querySelector('img');
    const priceNode = buyButton?.querySelector('.yo-price[data-eur]') || card.querySelector('.sale-new-btn .yo-price[data-eur], .yo-price[data-eur]');
    const price = clean(priceNode?.getAttribute('data-eur') || priceNode?.textContent || '');
    return {
      id: productId(card, title),
      title: title,
      image: image?.currentSrc || image?.src || '',
      price: price,
      url: window.location.href.split('#')[0] + '#' + encodeURIComponent(productId(card, title))
    };
  }

  function messageFor(product){
    const lines = [
      'Hello! I need help buying this YOleotard model:',
      product.title
    ];
    if(product.price) lines.push('Price: EUR ' + product.price.replace(/[^0-9.,]/g, ''));
    lines.push('Product page: ' + product.url);
    if(product.image) lines.push('Photo: ' + product.image);
    lines.push('Please help me place the order.');
    return lines.join('\n');
  }

  function icon(name){
    const span = document.createElement('span');
    span.setAttribute('uk-icon', 'icon: ' + name + '; ratio: 0.85');
    span.setAttribute('aria-hidden', 'true');
    return span;
  }

  function menuLink(className, label, iconName, href){
    const link = document.createElement('a');
    link.className = 'yo-manager-channel ' + className;
    link.href = href;
    link.target = '_blank';
    link.rel = 'noopener';
    link.appendChild(icon(iconName));
    const text = document.createElement('span');
    text.textContent = label;
    link.appendChild(text);
    return link;
  }

  function legacyCopy(message){
    const field = document.createElement('textarea');
    field.value = message;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    let copied = false;
    try { copied = document.execCommand('copy'); } catch(error) {}
    field.remove();
    return copied;
  }

  function copyMessage(message){
    if(navigator.clipboard?.writeText){
      return navigator.clipboard.writeText(message).then(function(){ return true; }).catch(function(){ return legacyCopy(message); });
    }
    return Promise.resolve(legacyCopy(message));
  }

  function showNotice(message){
    let notice = document.querySelector('.yo-manager-notice');
    if(!notice){
      notice = document.createElement('div');
      notice.className = 'yo-manager-notice';
      notice.setAttribute('role', 'status');
      notice.setAttribute('aria-live', 'polite');
      document.body.appendChild(notice);
    }
    notice.textContent = message;
    notice.classList.add('is-visible');
    window.clearTimeout(showNotice.timer);
    showNotice.timer = window.setTimeout(function(){ notice.classList.remove('is-visible'); }, 3200);
  }

  function closeAll(except){
    document.querySelectorAll('.yo-manager-help.is-open').forEach(function(help){
      if(help === except) return;
      help.classList.remove('is-open');
      help.querySelector('.yo-manager-help-toggle')?.setAttribute('aria-expanded', 'false');
    });
  }

  function buildHelp(card, buyButton, discounted){
    const product = productData(card, buyButton);
    if(!product.id) return null;
    const message = messageFor(product);
    const wrap = document.createElement('div');
    wrap.className = 'yo-manager-help' + (discounted ? ' yo-manager-help-discount' : '');
    wrap.dataset.yoProductId = product.id;
    wrap.dataset.yoDiscounted = discounted ? '1' : '0';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'yo-manager-help-toggle';
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Open manager contact options');
    toggle.appendChild(icon('commenting'));
    const label = document.createElement('span');
    label.textContent = 'Need help buying?';
    toggle.appendChild(label);
    const chevron = icon('chevron-up');
    chevron.classList.add('yo-manager-chevron');
    toggle.appendChild(chevron);

    const menu = document.createElement('div');
    menu.className = 'yo-manager-menu';
    menu.setAttribute('role', 'menu');
    menu.setAttribute('aria-label', 'Contact a YOleotard manager');

    const menuTitle = document.createElement('p');
    menuTitle.className = 'yo-manager-menu-title';
    menuTitle.textContent = 'Choose a convenient way to contact us';
    menu.appendChild(menuTitle);

    const channelList = document.createElement('div');
    channelList.className = 'yo-manager-channel-list';
    menu.appendChild(channelList);

    const whatsappNumber = String(config.whatsappNumber || '').replace(/[^0-9]/g, '');
    if(whatsappNumber){
      channelList.appendChild(menuLink('is-whatsapp', 'WhatsApp', 'whatsapp', 'https://wa.me/' + whatsappNumber + '?text=' + encodeURIComponent(message)));
    }

    const instagram = String(config.instagramUsername || '').replace(/[^A-Za-z0-9._-]/g, '');
    if(instagram){
      const instagramLink = menuLink('is-instagram', 'Instagram', 'instagram', 'https://ig.me/m/' + encodeURIComponent(instagram));
      instagramLink.addEventListener('click', function(){
        copyMessage(message).then(function(copied){
          showNotice(copied
            ? 'Product details copied. Paste them into Instagram Direct.'
            : 'Instagram opened. Please send the product page link to our manager.');
        });
      });
      instagramLink.title = 'The product message will be copied so you can paste it into Instagram Direct.';
      channelList.appendChild(instagramLink);
    }

    const facebook = String(config.facebookUsername || '').replace(/[^A-Za-z0-9._-]/g, '');
    if(facebook){
      const ref = 'yo_product_' + product.id;
      const messengerLink = menuLink('is-messenger', 'Messenger', 'facebook', 'https://m.me/' + encodeURIComponent(facebook) + '?ref=' + encodeURIComponent(ref));
      messengerLink.addEventListener('click', function(){
        copyMessage(message).then(function(copied){
          showNotice(copied
            ? 'Product details copied. Paste them into Messenger if the card is not added automatically.'
            : 'Messenger opened. Please send the product page link to our manager.');
        });
      });
      messengerLink.title = 'The product card is sent automatically when Meta delivers the referral. The text is also copied as a fallback.';
      channelList.appendChild(messengerLink);
    }

    toggle.addEventListener('click', function(event){
      event.preventDefault();
      event.stopPropagation();
      const opening = !wrap.classList.contains('is-open');
      closeAll(wrap);
      wrap.classList.toggle('is-open', opening);
      toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
    });
    wrap.appendChild(toggle);
    wrap.appendChild(menu);
    return wrap;
  }

  function prepareCard(card){
    if(!card) return;
    if(card.classList.contains('yo-reserved-card') || card.classList.contains('yo-invoice-reserved-card') || card.classList.contains('uk-card-default')){
      card.querySelectorAll('.yo-manager-help').forEach(function(help){ help.remove(); });
      card.querySelectorAll('.yo-manager-actions-host').forEach(function(host){ host.classList.remove('yo-manager-actions-host', 'has-discount'); });
      return;
    }
    const saleButton = card.querySelector('.sale-new-btn, .yo-sale-new-btn, .yo-sale-btn, .uk-button-danger');
    const buyButton = saleButton || card.querySelector('.yo-main-buy-btn');
    if(!buyButton || !card.querySelector('.el-title') || !card.querySelector('img')) return;
    const host = buyButton.parentElement;
    if(!host) return;
    const discounted = !!saleButton;
    const existing = card.querySelector('.yo-manager-help');
    if(existing && existing.parentElement === host && existing.dataset.yoDiscounted === (discounted ? '1' : '0')) return;
    if(existing){
      const oldHost = existing.parentElement;
      existing.remove();
      if(oldHost) oldHost.classList.remove('yo-manager-actions-host', 'has-discount');
    }
    const help = buildHelp(card, buyButton, discounted);
    if(!help) return;
    host.classList.add('yo-manager-actions-host');
    host.classList.toggle('has-discount', discounted);
    if(discounted){
      host.appendChild(help);
    } else {
      buyButton.insertAdjacentElement('afterend', help);
    }
  }

  function scan(){
    document.querySelectorAll('.el-item').forEach(prepareCard);
  }

  document.addEventListener('click', function(event){
    if(!event.target.closest('.yo-manager-help')) closeAll();
  });
  document.addEventListener('keydown', function(event){
    if(event.key === 'Escape') closeAll();
  });

  if(document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
  else scan();
  [300, 900, 1800, 3200].forEach(function(delay){ setTimeout(scan, delay); });

  const observer = new MutationObserver(function(mutations){
    if(mutations.some(function(mutation){ return mutation.addedNodes.length > 0; })) scan();
  });
  observer.observe(document.documentElement, {childList:true, subtree:true});
})();
