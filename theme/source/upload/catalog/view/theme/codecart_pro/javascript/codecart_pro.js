(function(){
  'use strict';
  document.addEventListener('click', function(e){
    var top = e.target.closest('.ccp-scroll-top');
    if(top){e.preventDefault(); window.scrollTo({top:0, behavior:'smooth'});}
  });
  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.ccp-product-card').forEach(function(card){
      var link = card.querySelector('.ccp-product-card__name a, .ccp-product-card__image a');
      if(!link){return;}
      card.addEventListener('dblclick', function(e){
        if(e.target.closest('button,a,input,select,textarea')){return;}
        window.location.href = link.href;
      });
    });
  });
})();


(function($){
  'use strict';
  function esc(text){return $('<div/>').text(text || '').html();}
  function showQuickModal(id){var el=document.getElementById(id); if(!el){return null;} if(window.bootstrap && bootstrap.Modal){var modal=bootstrap.Modal.getOrCreateInstance(el); modal.show(); return modal;} $(el).modal('show'); return null;}
  function addHidden($target,name,value){$('<input>',{type:'hidden',name:name,value:value}).appendTo($target);}
  function collectProductOptions($scope){
    var rows=[];
    $scope.find(':input[name^="option["]').each(function(){
      var $el=$(this); var type=($el.attr('type')||$el.prop('tagName')||'').toLowerCase();
      if((type==='radio'||type==='checkbox') && !$el.is(':checked')){return;}
      if($el.is('select') && !$el.val()){return;}
      var $group=$el.closest('.form-group');
      var label=$.trim($group.find('label.control-label:first').text() || $group.find('> label:first').text() || '');
      var value='';
      if($el.is('select')){value=$.trim($el.find('option:selected').text());}
      else if(type==='radio'||type==='checkbox'){value=$.trim($el.closest('label').text());}
      else{value=$.trim($el.val());}
      value=value.replace(/\s+/g,' ');
      if(label && value){rows.push({label:label,value:value});}
    });
    return rows;
  }
  function copyProductInputs($source,$hidden){
    if(!$source.length){return;}
    $source.find(':input[name^="option["]').each(function(){
      var $el=$(this); var type=($el.attr('type')||'').toLowerCase();
      if((type==='radio'||type==='checkbox') && !$el.is(':checked')){return;}
      if($el.is('select') && !$el.val()){return;}
      if($el.val() === null || $el.val() === ''){return;}
      if($.isArray($el.val())){$.each($el.val(),function(_,v){addHidden($hidden,$el.attr('name'),v);});}
      else{addHidden($hidden,$el.attr('name'),$el.val());}
    });
    var recurring=$source.find('[name="recurring_id"]').val();
    if(recurring){addHidden($hidden,'recurring_id',recurring);}
  }
  $(document).on('click','.ccp-js-quick-order',function(e){
    e.preventDefault(); e.stopPropagation();
    var $btn=$(this); var productId=parseInt($btn.data('product-id')||0,10); var name=$btn.data('product-name')||''; var source=$btn.data('source')||($btn.closest('.ccp-product-page').length?'product':($btn.closest('.ccp-product-card').length?'card':'button'));
    var $form=$('#ccp-quick-order-form'); if(!$form.length || !productId){return;}
    $form[0].reset();
    var $hidden=$form.find('.ccp-quick-order-hidden').empty();
    var $summary=$form.find('.ccp-quick-order-options').empty().removeClass('show');
    var qty=1; var $productScope=$btn.closest('#product');
    if(!$productScope.length && $('#product input[name="product_id"]').val()==String(productId)){$productScope=$('#product');}
    if($productScope.length){qty=parseInt($productScope.find('input[name="quantity"]').val()||1,10)||1; copyProductInputs($productScope,$hidden); var rows=collectProductOptions($productScope); if(rows.length){var html='<strong>'+esc((window.CodeCartProTheme&&window.CodeCartProTheme.textSelectedOptions)||'Selected options')+'</strong><ul>'; $.each(rows,function(_,row){html+='<li><span>'+esc(row.label)+'</span><b>'+esc(row.value)+'</b></li>';}); html+='</ul>'; $summary.html(html).addClass('show');}}
    else{var $card=$btn.closest('.ccp-product-card'); var cardQty=parseInt($card.find('.ccp-card-qty input').val()||1,10); if(cardQty>0){qty=cardQty;}}
    $form.find('input[name="product_id"]').val(productId);
    $form.find('input[name="quantity"]').val(qty);
    $form.find('input[name="source"]').val(source);
    $form.find('.ccp-quick-order-product').html('<strong>'+esc(name)+'</strong><span>× '+qty+'</span>');
    $form.find('.ccp-quick-message').removeClass('show alert-danger alert-success').text('');
    showQuickModal('ccp-quick-order-modal');
  });
  $(document).on('submit','#ccp-quick-order-form',function(e){
    e.preventDefault(); var $form=$(this); var $btn=$form.find('button[type="submit"]'); var $msg=$form.find('.ccp-quick-message');
    var oldHtml=$btn.html(); $btn.data('ccp-old-html',oldHtml).prop('disabled',true).html('<i class="fa fa-spinner fa-spin"></i> '+esc((window.CodeCartProTheme&&window.CodeCartProTheme.textLoading)||'Loading...')); $msg.removeClass('show alert-danger alert-success').text('');
    $.ajax({url:'index.php?route=extension/module/codecart_pro_quick/quickorder',type:'post',dataType:'json',data:$form.serialize()+'&page_url='+encodeURIComponent(window.location.href),success:function(json){if(json && json.success){$msg.addClass('show alert-success').text(json.success); $form.find('input[name="name"],input[name="phone"],input[name="email"],textarea[name="comment"]').val('');}else{$msg.addClass('show alert-danger').text((json && json.error)?json.error:((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));}},error:function(){$msg.addClass('show alert-danger').text(((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));},complete:function(){$btn.prop('disabled',false); if($btn.data('ccp-old-html')){$btn.html($btn.data('ccp-old-html'));}}});
  });
  $(document).on('change','#product input,#product select,#product textarea',function(){var $box=$('.ccp-product-price'); if(!$box.length){return;} $box.addClass('ccp-live-price-touched');});
})(jQuery);


(function($){
  'use strict';
  var cfg = window.CodeCartProTheme || {};
  window.CCP_PENDING_CART_POPUP = null;
  var popupReturnFocus = null;
  function normalizePopupTotal(totalText){
    totalText = $.trim(String(totalText || ''));
    if (totalText.indexOf('-') !== -1) { totalText = $.trim(totalText.split('-').pop()); }
    return totalText;
  }
  function showCartAddPopup(payload, totalText, serverItem){
    if (!cfg.cartAddPopup) { return false; }
    if (window.CodeCartProCloseCartDrawer) { window.CodeCartProCloseCartDrawer(); }
    $('#ccp-cart-drawer').removeClass('show').attr('aria-hidden','true').attr('inert','');
    $('body').removeClass('ccp-cart-open');
    var $popup = $('#ccp-cart-popup');
    if (!$popup.length || !payload) { return false; }
    var priceText = $.trim(payload.price || '');
    var summaryText = normalizePopupTotal(totalText || payload.total || priceText);
    $popup.find('.ccp-cart-popup__title').text(cfg.cartAddPopupTitle || 'Кошик');
    $popup.find('.ccp-cart-popup__name').text(payload.name || '').attr('href', payload.href || '#');
    $popup.find('.ccp-cart-popup__image').attr('src', payload.image || '').attr('alt', payload.name || '');
    $popup.find('.ccp-cart-popup__image-wrap').toggle(!!payload.image);
    $popup.find('.ccp-cart-popup__qty-value').text(serverItem ? serverItem.quantity : (payload.qty || 1));
    $popup.find('.ccp-cart-popup__price').text(serverItem ? serverItem.price : priceText);
    $popup.find('.ccp-cart-popup__total').text(serverItem ? serverItem.total : '');
    $popup.find('.ccp-cart-popup__summary-value').text(summaryText || priceText);
    $popup.find('.ccp-cart-popup__checkout').attr('href', cfg.checkoutUrl || 'index.php?route=checkout/checkout').text(cfg.cartAddPopupCheckout || 'Перейти до оформлення замовлення');
    $popup.find('.ccp-cart-popup__continue').text(cfg.cartAddPopupContinue || 'Продовжити покупки');
    if (!$popup.hasClass('is-visible')) { popupReturnFocus = payload.trigger || document.activeElement; }
    $popup.addClass('is-visible').attr('aria-hidden','false').removeAttr('inert');
    $('body').addClass('ccp-cart-popup-open');
    $popup.find('.ccp-cart-popup__close').trigger('focus');
    return true;
  }
  window.CodeCartProShowCartAddPopup = showCartAddPopup;
  function closeCartAddPopup(){
    $('#ccp-cart-popup').removeClass('is-visible').attr('aria-hidden','true').attr('inert','');
    $('body').removeClass('ccp-cart-popup-open');
    if (popupReturnFocus && document.contains(popupReturnFocus)) { popupReturnFocus.focus(); }
  }
  window.CodeCartProCloseCartAddPopup = closeCartAddPopup;
  $(document).on('keydown.ccpCartPopup', function(e){
    var popup = document.getElementById('ccp-cart-popup');
    if (!popup || !popup.classList.contains('is-visible')) { return; }
    if (e.key === 'Escape') { e.preventDefault(); closeCartAddPopup(); return; }
    if (e.key !== 'Tab') { return; }
    var controls = $(popup).find('a[href],button:not(:disabled),input:not(:disabled)').filter(':visible').toArray();
    if (!controls.length) { return; }
    if (e.shiftKey && document.activeElement === controls[0]) { e.preventDefault(); controls[controls.length-1].focus(); }
    else if (!e.shiftKey && document.activeElement === controls[controls.length-1]) { e.preventDefault(); controls[0].focus(); }
  });
  function cardPopupPayload($trigger){
    var $card = $trigger.closest('.ccp-product-card, .product-thumb');
    var $img = $card.find('img').first();
    var href = $card.data('href') || $card.find('.ccp-product-card__name a, .ccp-product-image-link, a[href*="product/product"]').first().attr('href') || '#';
    var priceText = $.trim($card.find('.ccp-product-card__price, .price, .ccp-price-current, .price-new').first().text());
    var qty = parseInt($card.find('.ccp-card-qty input').val() || '1', 10);
    if (isNaN(qty) || qty < 1) { qty = 1; }
    return {
      trigger: $trigger[0],
      name: $.trim($card.find('.ccp-product-card__name, .ccp-product-card__title, .caption h4, .name').first().text()),
      href: href,
      image: $img.attr('src') || '',
      qty: qty,
      price: priceText
    };
  }
  window.CodeCartProCardPopupPayload = cardPopupPayload;
  var liveTimer = null;

  function esc(text){return $('<div/>').text(text || '').html();}
  function liveBox($input){
    var $wrap = $input.closest('#search,.ccp-search-wrap,.input-group,form');
    if(!$wrap.length){$wrap = $input.parent();}
    $wrap.css('position','relative');
    var $box = $wrap.find('.ccp-live-search-box');
    if(!$box.length){$box = $('<div class="ccp-live-search-box"></div>').appendTo($wrap);}
    return $box;
  }
  function renderLive($box,json){
    if(!json || !json.total){$box.html('<div class="ccp-live-empty">'+esc((window.CodeCartProTheme&&window.CodeCartProTheme.textLiveEmpty)||'No results found')+'</div>').addClass('show');return;}
    var html = '';
    if(json.products && json.products.length){html += '<div class="ccp-live-title">'+esc((window.CodeCartProTheme&&window.CodeCartProTheme.textLiveProducts)||'Products')+'</div>'; json.products.forEach(function(p){html += '<a class="ccp-live-product" href="'+esc(p.href)+'"><img src="'+esc(p.image)+'" alt=""><span><strong>'+esc(p.name)+'</strong><em>'+esc(p.model)+'</em></span><b>'+(p.special ? esc(p.special) : esc(p.price))+'</b></a>';});}
    if(json.categories && json.categories.length){html += '<div class="ccp-live-title">'+esc((window.CodeCartProTheme&&window.CodeCartProTheme.textLiveCategories)||'Categories')+'</div>'; json.categories.forEach(function(c){html += '<a class="ccp-live-category" href="'+esc(c.href)+'"><i class="fa fa-folder-open"></i> '+esc(c.name)+'</a>';});}
    $box.html(html).addClass('show');
  }
  $(document).on('input','input[name="search"]',function(){
    if(!cfg.liveSearch){return;}
    var $input=$(this); var value=$.trim($input.val()); var min=parseInt(cfg.liveSearchMinChars || 2,10); var $box=liveBox($input);
    clearTimeout(liveTimer);
    if(value.length < min){$box.removeClass('show').empty();return;}
    liveTimer=setTimeout(function(){
      $.ajax({url:'index.php?route=extension/module/codecart_pro_search/live',type:'get',dataType:'json',data:{search:value},success:function(json){renderLive($box,json);},error:function(){$box.removeClass('show').empty();}});
    },220);
  });
  $(document).on('click',function(e){if(!$(e.target).closest('.ccp-live-search-box,input[name="search"]').length){$('.ccp-live-search-box').removeClass('show');}});
  // ccp-search-submit-safe: works with desktop and mobile search blocks without relying on duplicate #search ids.
  $(document).on('click','.ccp-search-wrap button,.ccp-search-wrap .btn',function(e){
    var $wrap=$(this).closest('.ccp-search-wrap,#search');
    var value=$.trim($wrap.find('input[name="search"]').first().val()||'');
    if(value){ window.location.href='index.php?route=product/search&search='+encodeURIComponent(value); }
  });
  $(document).on('keydown','.ccp-search-wrap input[name="search"]',function(e){
    if(e.keyCode===13){ e.preventDefault(); var value=$.trim($(this).val()||''); if(value){ window.location.href='index.php?route=product/search&search='+encodeURIComponent(value); } }
  });

  function showModal(id){var el=document.getElementById(id); if(!el){return;} if(window.bootstrap && bootstrap.Modal){bootstrap.Modal.getOrCreateInstance(el).show();} else {$(el).modal('show');}}
  $(document).on('click','.ccp-js-callback-open',function(e){e.preventDefault(); var $form=$('#ccp-callback-form'); if($form.length){$form[0].reset(); $form.find('.ccp-callback-message').removeClass('show alert-danger alert-success').text('');} showModal('ccp-callback-modal');});
  $(document).on('submit','#ccp-callback-form',function(e){e.preventDefault(); var $form=$(this); var $msg=$form.find('.ccp-callback-message'); var $btn=$form.find('button[type="submit"]'); $btn.prop('disabled',true); $msg.removeClass('show alert-danger alert-success').text(''); $.ajax({url:'index.php?route=extension/module/codecart_pro_interaction/callback',type:'post',dataType:'json',data:$form.serialize()+'&page_url='+encodeURIComponent(window.location.href),success:function(json){if(json && json.success){$msg.addClass('show alert-success').text(json.success); $form.find('input[name="name"],input[name="phone"],input[name="email"],textarea[name="comment"]').val('');}else{$msg.addClass('show alert-danger').text((json && json.error)?json.error:((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));}},error:function(){$msg.addClass('show alert-danger').text(((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));},complete:function(){$btn.prop('disabled',false);}});});
  $(document).on('submit','#ccp-subscribe-form',function(e){e.preventDefault(); var $form=$(this); var $msg=$form.find('.ccp-subscribe-message'); var $btn=$form.find('button[type="submit"]'); $btn.prop('disabled',true); $msg.removeClass('show error success').text(''); $.ajax({url:'index.php?route=extension/module/codecart_pro_interaction/subscribe',type:'post',dataType:'json',data:$form.serialize(),success:function(json){if(json && json.success){$msg.addClass('show success').text(json.success); $form.find('input[name="email"]').val('');}else{$msg.addClass('show error').text((json && json.error)?json.error:((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));}},error:function(){$msg.addClass('show error').text(((window.CodeCartProTheme&&window.CodeCartProTheme.textSendError)||'Sending error'));},complete:function(){$btn.prop('disabled',false);}});});

  $(function(){
    if(cfg.popupStatus && cfg.popupText && !sessionStorage.getItem('ccp_popup_closed')){
      setTimeout(function(){var $p=$('#ccp-popup-notification'); $p.find('.ccp-popup-text').html(cfg.popupText); $p.addClass('show');}, parseInt(cfg.popupDelay || 2500,10));
    }
  });
  $(document).on('click','.ccp-popup-close',function(){sessionStorage.setItem('ccp_popup_closed','1'); $('#ccp-popup-notification').removeClass('show');});
})(jQuery);


(function($){
  'use strict';
  function ccpQtyValue($input, delta){
    var min = parseInt($input.attr('min') || '1', 10); if(isNaN(min) || min < 1){min = 1;}
    var val = parseInt($input.val() || min, 10); if(isNaN(val)){val = min;}
    val = val + delta; if(val < min){val = min;}
    $input.val(val).trigger('change');
  }
  $(document).on('click','[data-ccp-qty="minus"]',function(e){e.preventDefault();e.stopPropagation();ccpQtyValue($(this).closest('.ccp-card-qty').find('input'),-1);});
  $(document).on('click','[data-ccp-qty="plus"]',function(e){e.preventDefault();e.stopPropagation();ccpQtyValue($(this).closest('.ccp-card-qty').find('input'),1);});
  $(document).on('click','[data-ccp-product-qty="minus"]',function(e){e.preventDefault();ccpQtyValue($('#input-quantity'),-1);});
  $(document).on('click','[data-ccp-product-qty="plus"]',function(e){e.preventDefault();ccpQtyValue($('#input-quantity'),1);});
  $(document).on('click','.ccp-card-cart',function(e){
    e.preventDefault(); e.stopPropagation(); if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
    var $btn = $(this);
    if ($btn.data('ccp-busy')) { return false; }
    var $card = $btn.closest('.ccp-product-card, .product-thumb');
    var productId = parseInt($btn.data('product-id') || $card.data('product-id') || 0, 10);
    if (!productId) { return false; }
    var qty = parseInt($card.find('.ccp-card-qty input').val() || '1',10); if(isNaN(qty) || qty < 1){qty = 1;}
    var oldHtml = $btn.html();
    var loadingText = (window.CodeCartProTheme && window.CodeCartProTheme.textLoading) ? window.CodeCartProTheme.textLoading : 'Loading...';
    var payload = window.CodeCartProCardPopupPayload ? window.CodeCartProCardPopupPayload($btn) : {name: $.trim($card.find('.ccp-product-card__name, .caption h4, .name').first().text()), href: $card.data('href') || '#', image: $card.find('img').first().attr('src') || '', qty: qty, price: $.trim($card.find('.ccp-product-card__price, .price, .price-new').first().text())};
    window.CCP_PENDING_CART_POPUP = payload;
    $btn.data('ccp-busy', true).prop('disabled', true).addClass('disabled').html('<i class="fa fa-spinner fa-spin"></i> ' + loadingText);
    var resetTimer = window.setTimeout(function(){
      $btn.data('ccp-busy', false).prop('disabled', false).removeClass('disabled').html(oldHtml);
      window.CCP_PENDING_CART_POPUP = null;
    }, 8000);
    $.ajax({
      url: 'index.php?route=checkout/cart/add',
      type: 'post',
      dataType: 'json',
      data: {product_id: productId, quantity: qty},
      success: function(json){
        if (json && json.success) {
          if ($('#cart > button').length && json.total) { $('#cart > button').html('<span id="cart-total">' + json.total + '</span>'); }
          var shown = false;
          if (window.CodeCartProTheme && window.CodeCartProTheme.cartAddPopup && window.CodeCartProShowCartAddPopup) {
            shown = window.CodeCartProShowCartAddPopup(payload, json.total, json.ccp_cart_item);
          }
          if (!shown && window.CodeCartProRefreshCartDrawer) { window.CodeCartProRefreshCartDrawer(true); }
          $btn.data('ccp-busy', false).prop('disabled', false).removeClass('disabled').html(oldHtml);
          if (window.CodeCartProSyncProductQty) { window.CodeCartProSyncProductQty(); }
        } else if (json && json.redirect) {
          window.location.href = json.redirect;
          return;
        } else if (json && json.error) {
          if (window.CodeCartProToast) { window.CodeCartProToast(json.error.option ? Object.values(json.error.option).join(' ') : (json.error.warning || 'Error'), 'danger'); }
        }
      },
      error: function(){
        if (window.cart && typeof cart.add === 'function') { try { cart.add(productId, qty); } catch(err) {} }
      },
      complete: function(){
        window.clearTimeout(resetTimer);
        $btn.data('ccp-busy', false).prop('disabled', false).removeClass('disabled').html(oldHtml);
        window.CCP_PENDING_CART_POPUP = null;
      }
    });
    return false;
  });
  $(document).on('click','.ccp-card-full-click',function(e){
    if(!window.CodeCartProTheme || !window.CodeCartProTheme.cardFullClick){return;}
    if($(e.target).closest('a,button,input,select,textarea,label').length){return;}
    var href = $(this).data('href'); if(href){window.location.href = href;}
  });
  $(document).on('click','.ccp-view-btn',function(e){
    e.preventDefault();
    var view = $(this).data('ccp-view');
    var $grid = $('.ccp-product-grid--category');
    $('.ccp-view-btn').removeClass('active'); $(this).addClass('active');
    $grid.toggleClass('ccp-view-list', view === 'list');
    try{localStorage.setItem('ccp_category_view', view);}catch(err){}
  });
  $(function(){
    var $grid = $('.ccp-product-grid--category');
    if (!$grid.length) { return; }
    var view = $('#product-category').attr('data-ccp-default-view') === 'list' ? 'list' : 'grid';
    try {
      var savedView = localStorage.getItem('ccp_category_view');
      if (savedView === 'grid' || savedView === 'list') { view = savedView; }
    } catch(err) {}
    $grid.toggleClass('ccp-view-list', view === 'list');
    $('.ccp-view-btn').removeClass('active');
    $('.ccp-view-btn[data-ccp-view="' + view + '"]').addClass('active');
  });
  function ccpGalleryVariantMap($gallery){
    var $img = $gallery.find('[data-ccp-gallery-main] img').first();
    var raw = $img.attr('data-awip-map') || '';
    if(!raw){ return {}; }
    try {
      var parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch(err) {
      return {};
    }
  }
  function ccpGalleryVariantFor(map, candidate){
    candidate = candidate || '';
    var original;
    var format;
    if(candidate && map[candidate]){
      return {original:candidate, formats:map[candidate] || {}};
    }
    for(original in map){
      if(!Object.prototype.hasOwnProperty.call(map, original)){ continue; }
      for(format in map[original]){
        if(Object.prototype.hasOwnProperty.call(map[original], format) && map[original][format] === candidate){
          return {original:original, formats:map[original] || {}};
        }
      }
    }
    return {original:candidate, formats:{}};
  }
  function ccpMimeForFormat(format){
    format = String(format || '').toLowerCase();
    if(format === 'jxl'){ return 'image/jxl'; }
    if(format === 'avif'){ return 'image/avif'; }
    if(format === 'webp'){ return 'image/webp'; }
    return '';
  }
  function ccpApplyPictureSources($picture, formats){
    if(!$picture || !$picture.length){ return; }
    var ordered = [];
    var used = {};
    var format;
    for(format in (formats || {})){
      if(Object.prototype.hasOwnProperty.call(formats, format)){ ordered.push(format); }
    }
    $picture.find('source').each(function(){
      var sourceFormat = ($(this).attr('data-awip-format') || '').toLowerCase();
      var next = formats && formats[sourceFormat] ? formats[sourceFormat] : '';
      if(next){ $(this).attr('srcset', next); used[sourceFormat] = true; }
      else { $(this).removeAttr('srcset'); }
    });
    var $fallback = $picture.children('img').first();
    $.each(ordered, function(_, sourceFormat){
      if(!formats[sourceFormat] || used[sourceFormat]){ return; }
      var mime = ccpMimeForFormat(sourceFormat);
      if(mime){ $('<source>').attr({'data-awip-format':sourceFormat,'type':mime,'srcset':formats[sourceFormat]}).insertBefore($fallback); }
    });
  }
  function ccpGalleryItems($gallery){
    var items = [];
    var map = ccpGalleryVariantMap($gallery);
    $gallery.find('[data-ccp-gallery-thumb]').each(function(){
      var $item = $(this);
      var large = $item.attr('data-ccp-large') || $item.attr('data-ccp-main') || $item.attr('href') || $item.find('img').attr('src') || '';
      var main = $item.attr('data-ccp-main') || large;
      var originalCandidate = large || main || $item.attr('data-ccp-thumb') || $item.find('img').attr('src') || '';
      var resolvedLarge = ccpGalleryVariantFor(map, large || originalCandidate);
      var resolvedMain = ccpGalleryVariantFor(map, main || originalCandidate);
      var thumb = $item.attr('data-ccp-thumb') || $item.find('img').attr('src') || main;
      var alt = $item.attr('data-ccp-alt') || $item.find('img').attr('alt') || '';
      var ratio = parseFloat($item.attr('data-ccp-ratio') || '0');
      var width = parseInt($item.attr('data-ccp-width') || '0', 10);
      var height = parseInt($item.attr('data-ccp-height') || '0', 10);
      if(!ratio && width > 0 && height > 0){ ratio = width / height; }
      if(!ratio || !isFinite(ratio)){ ratio = 4 / 3; }
      if(large || main){
        items.push({
          large:resolvedLarge.original || large,
          main:resolvedMain.original || main,
          original:resolvedLarge.original || resolvedMain.original || originalCandidate,
          formats:$.extend({}, resolvedMain.formats || {}, resolvedLarge.formats || {}),
          thumb:thumb,
          alt:alt,
          ratio:ratio,
          width:width,
          height:height
        });
      }
    });
    if(!items.length){
      var $main = $gallery.find('[data-ccp-gallery-main]').first();
      if($main.length){
        var candidate = $main.attr('data-ccp-large') || $main.attr('href') || $main.find('img').attr('src') || '';
        var resolved = ccpGalleryVariantFor(map, candidate);
        var mainRatio = parseFloat($main.attr('data-ccp-ratio') || '0') || (4 / 3);
        items.push({
          large:resolved.original || candidate,
          main:resolved.original || $main.attr('data-ccp-main') || candidate,
          original:resolved.original || candidate,
          formats:resolved.formats || {},
          thumb:$main.attr('data-ccp-thumb') || $main.find('img').attr('src') || '',
          alt:$main.attr('data-ccp-alt') || $main.find('img').attr('alt') || '',
          ratio:mainRatio,
          width:parseInt($main.attr('data-ccp-width') || '0',10),
          height:parseInt($main.attr('data-ccp-height') || '0',10)
        });
      }
    }
    return items;
  }
  function ccpGalleryRatioClass(ratio){
    ratio = parseFloat(ratio || 0) || (4 / 3);
    if(ratio >= 1.55){ return 'ccp-image-ratio-wide'; }
    if(ratio >= 1.15){ return 'ccp-image-ratio-landscape'; }
    if(ratio >= 0.82){ return 'ccp-image-ratio-balanced'; }
    return 'ccp-image-ratio-portrait';
  }
  function ccpApplyMainGalleryRatio($main, $stage, ratio){
    ratio = parseFloat(ratio || 0) || (4 / 3);
    var classes = 'ccp-image-ratio-wide ccp-image-ratio-landscape ccp-image-ratio-balanced ccp-image-ratio-portrait';
    $main.removeClass(classes).addClass(ccpGalleryRatioClass(ratio));
    if($stage && $stage.length){ $stage.css('--ccp-image-ratio-real', ratio); }
  }
  function ccpCenterActiveThumb($gallery, index, animate){
    var $track = $gallery.find('[data-ccp-gallery-thumbs]').first();
    var $active = $gallery.find('[data-ccp-gallery-thumb][data-ccp-index="'+index+'"]').first();
    if(!$track.length || !$active.length){ return; }
    var target = $active.position().left + $track.scrollLeft() - Math.max(0, ($track.innerWidth() - $active.outerWidth()) / 2);
    target = Math.max(0, Math.min(target, Math.max(0, $track[0].scrollWidth - $track.innerWidth())));
    if(animate){ $track.stop().animate({scrollLeft:target}, 180); }
    else { $track.scrollLeft(target); }
  }
  function ccpSetGalleryImage($gallery, index){
    var items = ccpGalleryItems($gallery);
    if(!items.length){ return; }
    index = parseInt(index || 0, 10);
    if(isNaN(index)){ index = 0; }
    if(index < 0){ index = items.length - 1; }
    if(index >= items.length){ index = 0; }
    var item = items[index];
    var $main = $gallery.find('[data-ccp-gallery-main]').first();
    var $stage = $gallery.find('.ccp-gallery-stage-wrap').first();
    $gallery.attr('data-ccp-current', index);
    ccpApplyMainGalleryRatio($main, $stage, item.ratio || (4 / 3));
    $gallery.find('[data-ccp-gallery-thumb]').removeClass('active').attr('aria-pressed','false').filter('[data-ccp-index="'+index+'"]').addClass('active').attr('aria-pressed','true');
    if($main.length){
      var fallback = item.main || item.original || item.large;
      $main.attr('href', item.large || fallback).attr('data-ccp-index', index).attr('data-ccp-main', fallback).attr('data-ccp-large', item.large || fallback).attr('data-ccp-ratio', item.ratio || (4 / 3));
      var $img = $main.find('img').first();
      var $picture = $img.closest('picture');
      ccpApplyPictureSources($picture, item.formats || {});
      $img.attr('src', fallback).attr('alt', item.alt || $img.attr('alt') || '');
      if(item.width > 0){ $img.attr('width', item.width); }
      if(item.height > 0){ $img.attr('height', item.height); }
      $img.off('load.ccpGalleryLayout').one('load.ccpGalleryLayout', function(){
        var naturalRatio = this.naturalWidth && this.naturalHeight ? (this.naturalWidth / this.naturalHeight) : (item.ratio || (4 / 3));
        ccpApplyMainGalleryRatio($main, $stage, naturalRatio);
      });
      if($img[0] && $img[0].complete){ $img.triggerHandler('load.ccpGalleryLayout'); }
      ccpCenterActiveThumb($gallery, index, true);
      if(item.large){ var preload = new Image(); preload.src = item.large; }
    }
  }
  function ccpLockGalleryScroll(){
    var html = document.documentElement;
    var body = document.body;
    if(!html || !body || body.getAttribute('data-ccp-gallery-lock') === '1'){ return; }
    body.setAttribute('data-ccp-gallery-lock','1');
    body.setAttribute('data-ccp-gallery-prev-overflow', body.style.overflow || '');
    html.setAttribute('data-ccp-gallery-prev-overflow', html.style.overflow || '');
    html.classList.add('ccp-gallery-open');
    body.classList.add('ccp-gallery-open');
    html.style.overflow = 'hidden';
    body.style.overflow = 'hidden';
  }
  function ccpUnlockGalleryScroll(){
    var html = document.documentElement;
    var body = document.body;
    if(!html || !body){ return; }
    html.classList.remove('ccp-gallery-open');
    body.classList.remove('ccp-gallery-open');
    if(body.getAttribute('data-ccp-gallery-lock') === '1'){
      body.style.overflow = body.getAttribute('data-ccp-gallery-prev-overflow') || '';
      html.style.overflow = html.getAttribute('data-ccp-gallery-prev-overflow') || '';
      body.removeAttribute('data-ccp-gallery-lock');
      body.removeAttribute('data-ccp-gallery-prev-overflow');
      html.removeAttribute('data-ccp-gallery-prev-overflow');
    }
  }
  function ccpOpenGallery($gallery, index){
    var $modal = $('[data-ccp-gallery-modal]').first();
    var items = ccpGalleryItems($gallery);
    if(!$modal.length || !items.length){ return; }
    if(!$modal.parent().is('body')){ $modal.appendTo('body'); }
    index = parseInt(index || 0, 10);
    if(isNaN(index)){ index = 0; }
    $modal.data('ccp-gallery-source', $gallery).data('ccp-gallery-items', items).data('ccp-gallery-index', index);
    ccpRenderGalleryModal($modal, index);
    $modal.addClass('show').attr('aria-hidden','false');
    ccpLockGalleryScroll();
    window.setTimeout(function(){ $modal.find('[data-ccp-gallery-close]').trigger('focus'); }, 0);
  }
  function ccpRenderGalleryModal($modal, index){
    var items = $modal.data('ccp-gallery-items') || [];
    if(!items.length){ return; }
    if(index < 0){ index = items.length - 1; }
    if(index >= items.length){ index = 0; }
    var item = items[index];
    $modal.data('ccp-gallery-index', index);
    var $img = $modal.find('[data-ccp-gallery-image]').first();
    var $picture = $img.closest('picture');
    ccpApplyPictureSources($picture, item.formats || {});
    $img.attr('src', item.large || item.original || item.main).attr('alt', item.alt || '');
    if(item.width > 0){ $img.attr('width', item.width); }
    if(item.height > 0){ $img.attr('height', item.height); }
    $modal.find('[data-ccp-gallery-stage]').css('--ccp-image-ratio-real', item.ratio || (4 / 3));
    $modal.find('[data-ccp-gallery-counter]').text((index + 1) + ' / ' + items.length);
    var $thumbs = $modal.find('[data-ccp-gallery-modal-thumbs]');
    if(!$thumbs.children().length || $thumbs.children().length !== items.length){
      $thumbs.empty();
      $.each(items,function(i,it){
        $('<button type="button" class="ccp-gallery-modal-thumb" data-index="'+i+'"><img src="'+(it.thumb || it.main || it.large)+'" alt=""></button>').appendTo($thumbs);
      });
    }
    $thumbs.find('.ccp-gallery-modal-thumb').removeClass('active').filter('[data-index="'+index+'"]').addClass('active');
    $modal.find('[data-ccp-gallery-prev],[data-ccp-gallery-next]').toggle(items.length > 1);
  }
  function ccpCloseGallery(){
    $('[data-ccp-gallery-modal]').removeClass('show').attr('aria-hidden','true').find('[data-ccp-gallery-image]').attr('src','');
    ccpUnlockGalleryScroll();
  }
  $(document).on('click','[data-ccp-gallery-thumb]',function(e){
    e.preventDefault();
    var $gallery = $(this).closest('[data-ccp-gallery]');
    ccpSetGalleryImage($gallery, $(this).attr('data-ccp-index'));
  });
  $(document).on('click','[data-ccp-gallery-main]',function(e){
    e.preventDefault();
    var $gallery = $(this).closest('[data-ccp-gallery]');
    ccpOpenGallery($gallery, $gallery.attr('data-ccp-current') || $(this).attr('data-ccp-index') || 0);
  });
  $(document).on('click','[data-ccp-gallery-close]',function(e){ e.preventDefault(); ccpCloseGallery(); });
  $(document).on('click','[data-ccp-gallery-modal]',function(e){ if($(e.target).is('[data-ccp-gallery-modal]')){ ccpCloseGallery(); } });
  $(document).on('click','[data-ccp-gallery-prev],[data-ccp-gallery-next]',function(e){
    e.preventDefault();
    var $modal = $(this).closest('[data-ccp-gallery-modal]');
    var index = parseInt($modal.data('ccp-gallery-index') || 0, 10) + ($(this).is('[data-ccp-gallery-prev]') ? -1 : 1);
    ccpRenderGalleryModal($modal, index);
  });
  $(document).on('click','.ccp-gallery-modal-thumb',function(e){
    e.preventDefault();
    ccpRenderGalleryModal($(this).closest('[data-ccp-gallery-modal]'), parseInt($(this).attr('data-index') || 0, 10));
  });
  $(document).on('keydown',function(e){
    var $modal = $('[data-ccp-gallery-modal].show');
    if(!$modal.length){ return; }
    if(e.key === 'Escape'){ ccpCloseGallery(); }
    if(e.key === 'ArrowLeft'){ $modal.find('[data-ccp-gallery-prev]').trigger('click'); }
    if(e.key === 'ArrowRight'){ $modal.find('[data-ccp-gallery-next]').trigger('click'); }
  });
  $(function(){
    $('#product-product [data-ccp-gallery]').each(function(){
      var $gallery = $(this);
      $gallery.find('.ccp-gallery-inline-nav').remove();
      ccpSetGalleryImage($gallery, parseInt($gallery.attr('data-ccp-current') || 0, 10));
    });
  });
})(jQuery);


(function($){
  'use strict';
  $(document).on('change','.ccp-review-photo-input',function(){
    var input=this; var files=input.files || []; var $wrap=$(input).closest('.ccp-review-upload'); var $hidden=$wrap.find('input[name="ccp_images"]'); var paths=$hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];
    if(!files.length){return;}
    $.each(files,function(i,file){
      var fd=new FormData(); fd.append('file',file);
      $.ajax({url:'index.php?route=extension/module/codecart_pro_review/upload',type:'post',dataType:'json',data:fd,processData:false,contentType:false,success:function(json){
        if(json && json.success){paths.push(json.path); $hidden.val(paths.join(',')); $wrap.find('.ccp-review-photo-preview').append('<span><img src="'+json.thumb+'" alt=""><button type="button" class="ccp-review-photo-remove" data-path="'+json.path+'">&times;</button></span>');}
        else {alert((json && json.error) ? json.error : ((window.CodeCartProTheme&&window.CodeCartProTheme.textUploadError)||'File upload error'));}
      },error:function(){alert((window.CodeCartProTheme&&window.CodeCartProTheme.textUploadError)||'File upload error');}});
    });
    $(input).val('');
  });
  $(document).on('click','.ccp-review-photo-remove',function(e){
    e.preventDefault(); var path=$(this).data('path'); var $wrap=$(this).closest('.ccp-review-upload'); var $hidden=$wrap.find('input[name="ccp_images"]'); var paths=$hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];
    paths=$.grep(paths,function(p){return p!==path;}); $hidden.val(paths.join(',')); $(this).closest('span').remove();
  });
  $(document).on('click','[data-ccp-review-vote]',function(e){
    e.preventDefault(); var $btn=$(this); var $box=$btn.closest('.ccp-review-votes'); var reviewId=$box.data('review-id'); var type=$btn.data('ccp-review-vote');
    if(!reviewId){return;}
    $.ajax({url:'index.php?route=extension/module/codecart_pro_review/vote',type:'post',dataType:'json',data:{review_id:reviewId,type:type},success:function(json){
      if(json && json.success){$box.find('[data-ccp-review-vote="plus"] span').text(json.votes_plus); $box.find('[data-ccp-review-vote="minus"] span').text(json.votes_minus); $box.addClass('ccp-review-voted');}
      else if(json && json.error){alert(json.error);}
    }});
  });
})(jQuery);


// CodeCart PRO Theme: product module carousel
$(document).on('click', '[data-ccp-scroll]', function(e){
    e.preventDefault();
    var $btn = $(this);
    var $section = $btn.closest('.ccp-product-module');
    var row = $section.find('.ccp-scroll-row').get(0);
    if (!row) { return; }
    var direction = $btn.data('ccp-scroll') === 'prev' ? -1 : 1;
    var amount = Math.max(280, Math.round(row.clientWidth * 0.85));
    row.scrollBy({left: direction * amount, behavior: 'smooth'});
});


// CodeCart PRO Theme: currency-safe live price and commercial timers
(function($){
  'use strict';
  function getMoneyMatch(template){
    template = String(template || '');
    return template.match(/\d[\d\s\u00a0.,]*\d|\d/);
  }
  function decimalsFromText(text){
    var match = getMoneyMatch(text);
    if(!match){ return 2; }
    var number = String(match[0] || '');
    var m = number.match(/[\.,](\d{1,4})$/);
    return m ? Math.min(4, m[1].length) : 0;
  }
  function decimalSepFromText(text){
    var match = getMoneyMatch(text);
    if(!match){ return '.'; }
    var number = String(match[0] || '');
    var dot = number.lastIndexOf('.');
    var comma = number.lastIndexOf(',');
    if(dot > -1 || comma > -1){ return comma > dot ? ',' : '.'; }
    return '.';
  }
  function thousandSepFromText(text, decimalSep){
    var match = getMoneyMatch(text);
    if(!match){ return ' '; }
    var number = String(match[0] || '');
    var intPart = number;
    var pos = number.lastIndexOf(decimalSep);
    if(pos > -1){ intPart = number.substring(0, pos); }
    if(intPart.indexOf('\u00a0') > -1){ return '\u00a0'; }
    if(intPart.indexOf(' ') > -1){ return ' '; }
    var other = decimalSep === ',' ? '.' : ',';
    if(intPart.indexOf(other) > -1){ return other; }
    return ' ';
  }
  function addThousands(value, sep){
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, sep || ' ');
  }
  function formatMoney(raw, format, originalText){
    var template = String(originalText || format || '');
    var match = getMoneyMatch(template);
    var decimals = decimalsFromText(template);
    var decimalSep = decimalSepFromText(template);
    var thousandSep = thousandSepFromText(template, decimalSep);
    var fixed = Number(raw || 0).toFixed(decimals < 0 ? 2 : decimals);
    var parts = fixed.split('.');
    var value = addThousands(parts[0], thousandSep);
    if(parts.length > 1 && decimals > 0){ value += decimalSep + parts[1]; }
    if(match){ return template.replace(match[0], value); }
    return value;
  }
  function parseOptionPriceText(text){
    text = String(text || '').replace(/\s+/g, ' ');
    var m = text.match(/([+\-=])\s*([0-9\s.,]+)\s*[^)]*\)?\s*$/);
    if(!m){ return null; }
    var raw = m[2].replace(/\s/g,'');
    if(raw.indexOf(',') !== -1 && raw.indexOf('.') !== -1){ raw = raw.replace(/\./g,'').replace(',', '.'); }
    else { raw = raw.replace(',', '.'); }
    var val = parseFloat(raw);
    if(isNaN(val)){ return null; }
    return {prefix:m[1], amount:val};
  }
  function getOptionMeta($el){
    var amountAttr = $el.attr('data-ccp-option-price');
    var prefixAttr = $el.attr('data-ccp-option-prefix') || '+';
    var amount = parseFloat(amountAttr || '');
    if(!isNaN(amount) && amount !== 0){ return {prefix:prefixAttr, amount:amount}; }
    var text = '';
    if($el.is('option')){ text = $el.text(); }
    else { text = $el.closest('label').text(); }
    return parseOptionPriceText(text);
  }
  function optionDelta(){
    var delta = 0;
    $('#product select option:selected, #product input[type="radio"]:checked, #product input[type="checkbox"]:checked').each(function(){
      var meta = getOptionMeta($(this));
      if(!meta){ return; }
      if (meta.prefix === '-') { delta -= meta.amount; }
      else if (meta.prefix === '=') { delta = meta.amount; }
      else { delta += meta.amount; }
    });
    return delta;
  }
  function parseMoneyValue(text){
    var match = getMoneyMatch(text);
    if(!match){ return 0; }
    var raw = String(match[0] || '').replace(/\s|\u00a0/g, '');
    var dot = raw.lastIndexOf('.');
    var comma = raw.lastIndexOf(',');
    if(dot > -1 && comma > -1){
      raw = comma > dot ? raw.replace(/\./g, '').replace(',', '.') : raw.replace(/,/g, '');
    } else if(comma > -1){
      raw = raw.replace(',', '.');
    }
    var value = parseFloat(raw);
    return isNaN(value) ? 0 : value;
  }
  function updateLivePrice(){
    if(!$('body').hasClass('ccp-enable-live-price')){ return; }
    var $box = $('.ccp-product-price').first();
    if (!$box.length) { return; }
    var $main = $box.find('[data-ccp-live-price-main], .price-main').first();
    if (!$main.length) { return; }
    var original = $main.data('ccp-original-text');
    if (!original) {
      original = $.trim($main.text());
      $main.data('ccp-original-text', original);
    }
    var base = parseFloat($box.attr('data-ccp-base-price-raw') || $box.data('ccp-base-price-raw') || 0);
    if (isNaN(base) || base <= 0) {
      base = parseFloat($box.attr('data-ccp-live-unit-raw') || 0);
    }
    if (isNaN(base) || base <= 0) {
      base = parseMoneyValue(original);
      if (base > 0) { $box.attr('data-ccp-live-unit-raw', base); }
    }
    if (isNaN(base) || base <= 0) { return; }
    var qty = parseInt($('#input-quantity').val() || '1', 10);
    if(isNaN(qty) || qty < 1){ qty = 1; }
    var total = Math.max(0, (base + optionDelta()) * qty);
    $main.text(formatMoney(total, $box.data('ccp-price-format'), original));
    $box.addClass('ccp-live-price-touched');
  }
  $(document).on('change input', '#product select, #product input[type="radio"], #product input[type="checkbox"], #product input[name="quantity"]', updateLivePrice);
  $(updateLivePrice);
  function tickTimers(){
    $('[data-ccp-sale-end]').each(function(){
      var $timer = $(this); var end = new Date(String($timer.data('ccp-sale-end')).replace(' ', 'T')).getTime();
      if (!end || isNaN(end)) { return; }
      var diff = Math.max(0, end - Date.now());
      var days = Math.floor(diff / 86400000); diff -= days * 86400000;
      var hours = Math.floor(diff / 3600000); diff -= hours * 3600000;
      var minutes = Math.floor(diff / 60000); diff -= minutes * 60000;
      var seconds = Math.floor(diff / 1000);
      $timer.find('[data-ccp-timer-days]').text(days);
      $timer.find('[data-ccp-timer-hours]').text(String(hours).padStart(2,'0'));
      $timer.find('[data-ccp-timer-minutes]').text(String(minutes).padStart(2,'0'));
      $timer.find('[data-ccp-timer-seconds]').text(String(seconds).padStart(2,'0'));
    });
  }
  $(function(){ tickTimers(); if ($('[data-ccp-sale-end]').length) { setInterval(tickTimers, 1000); } });
})(jQuery);


(function($){
  'use strict';
  var cfg = window.CodeCartProTheme || {};
  var ccpLastToast = {message:'',time:0};
  function cleanMessage(html){
    if(!html){return '';} 
    var $tmp = $('<div/>').html(html);
    $tmp.find('button,.close,.close').remove();
    return $.trim($tmp.html() || $tmp.text() || '');
  }
  function showToast(html, type){
    if(!cfg.cartToast){return;}
    var $stack = $('#ccp-toast-stack');
    if(!$stack.length){$stack = $('<div id="ccp-toast-stack" class="ccp-toast-stack ccp-toast-'+(cfg.notificationPosition || 'top-right')+'"></div>').appendTo('body');}
    var message = cleanMessage(html);
    if(!message){return;}
    var now = Date.now();
    if(ccpLastToast.message === message && (now - ccpLastToast.time) < 1200){return;}
    ccpLastToast = {message:message,time:now};
    var $toast = $('<div class="ccp-toast ccp-toast-'+(type || 'success')+'"><button type="button" class="ccp-toast-close" aria-label="Close">×</button><div class="ccp-toast-content"></div></div>');
    $toast.find('.ccp-toast-content').html(message);
    $stack.append($toast);
    setTimeout(function(){ $toast.addClass('show'); }, 10);
    setTimeout(function(){ $toast.removeClass('show'); setTimeout(function(){ $toast.remove(); }, 240); }, 5200);
  }
  function updateHeaderCartFromHtml(html){
    var $tmp = $('<div/>').html(html || '');
    var total = $tmp.find('#cart-total').first().html();
    if(total){
      $('#cart > button').html('<span id="cart-total">' + total + '</span>');
    }
  }

  function productFormData(){
    return $("#product input[type='text'], #product input[type='hidden'], #product input[type='radio']:checked, #product input[type='checkbox']:checked, #product select, #product textarea");
  }
  function syncProductQtyFromCart(){
    var $qty = $('#input-quantity');
    var productId = $("#product input[name='product_id']").val();
    if(!$qty.length || !productId){return;}
    $.ajax({url:'index.php?route=extension/module/codecart_pro_interaction/cartProductQuantity',type:'post',data:productFormData(),dataType:'json',cache:false,success:function(json){
      if(json && json.matched && parseInt(json.quantity,10) > 0){
        var qty = parseInt(json.quantity,10);
        if(!isNaN(qty) && qty > 0 && String($qty.val()) !== String(qty)){
          $qty.val(qty).trigger('change');
        }
      }
    }});
  }
  window.CodeCartProSyncProductQty = syncProductQtyFromCart;
  function cartKeyFromButton($btn){
    var onclick = String($btn.attr('onclick') || '');
    var m = onclick.match(/cart\.remove\(['"]([^'"]+)['"]\)/);
    return m ? m[1] : '';
  }
  function normalizeDrawerCart($content){
    $content.find('script').remove();
    $content.find('p.text-right').remove();
    $content.find('table').wrap(function(){
      return $(this).parent('.ccp-cart-table-wrap').length ? '' : '<div class="ccp-cart-table-wrap"></div>';
    });
    $content.find('img').addClass('ccp-cart-thumb');
    $content.find('table.table-striped tr').each(function(){
      var $row = $(this);
      var $qtyCell = $row.children('td').eq(2);
      var $removeBtn = $row.find('button[onclick*="cart.remove"]').first();
      var key = cartKeyFromButton($removeBtn);
      var qtyText = $.trim($qtyCell.text() || '');
      var qtyMatch = qtyText.match(/x\s*(\d+)/i) || qtyText.match(/(\d+)/);
      var qty = qtyMatch ? parseInt(qtyMatch[1], 10) : 1;
      if(!key || isNaN(qty) || qty < 1 || $qtyCell.find('.ccp-cart-qty').length){ return; }
      $qtyCell.html('<div class="ccp-cart-qty" data-ccp-cart-key="'+String(key).replace(/"/g,'&quot;')+'"><button type="button" data-ccp-cart-qty="minus" aria-label="Minus">−</button><input type="text" value="'+qty+'" inputmode="numeric" aria-label="Quantity"><button type="button" data-ccp-cart-qty="plus" aria-label="Plus">+</button></div>');
    });
  }
  function refreshDrawer(open){
    if(!cfg.cartDrawer){return;}
    var $drawer = $('#ccp-cart-drawer');
    if(!$drawer.length){return;}
    var $content = $drawer.find('[data-ccp-cart-content]');
    $content.html('<div class="ccp-loading-line"></div>');
    $.ajax({url:'index.php?route=common/cart/info',type:'get',dataType:'html',cache:false,success:function(html){
      updateHeaderCartFromHtml(html);
      var $tmp = $('<div/>').html(html);
      var $menu = $tmp.find('ul.dropdown-menu').first();
      if($menu.length){
        var $wrap = $('<div class="ccp-cart-mini"></div>');
        $wrap.append($menu.children().clone(false, false));
        $content.empty().append($wrap);
      } else {
        var $cart = $tmp.find('#cart').first();
        $content.html($cart.length ? $cart.html() : html);
      }
      normalizeDrawerCart($content);
      if(open){openDrawer();}
    },error:function(){
      $content.html('<div class="alert alert-warning">Cart could not be refreshed.</div>');
      if(open){openDrawer();}
    }});
  }
  function updateDrawerQty(key, qty){
    qty = parseInt(qty || 1, 10);
    if(isNaN(qty) || qty < 1){ qty = 1; }
    var data = 'key=' + encodeURIComponent(key) + '&quantity=' + encodeURIComponent(qty) + '&quantity[' + encodeURIComponent(key) + ']=' + encodeURIComponent(qty);
    $('#ccp-cart-drawer').addClass('ccp-cart-updating');
    $.ajax({url:'index.php?route=checkout/cart/edit',type:'post',data:data,cache:false,complete:function(){
      setTimeout(function(){
        refreshDrawer(false);
        $('#ccp-cart-drawer').removeClass('ccp-cart-updating');
      }, 250);
    }});
  }
  function openDrawer(){
    var $drawer = $('#ccp-cart-drawer');
    if(!$drawer.length){return;}
    $drawer.addClass('show').attr('aria-hidden','false').removeAttr('inert');
    $('body').addClass('ccp-cart-open');
  }
  function closeDrawer(){
    $('#ccp-cart-drawer').removeClass('show').attr('aria-hidden','true').attr('inert','');
    $('body').removeClass('ccp-cart-open');
  }
  window.CodeCartProCloseCartDrawer = closeDrawer;
  window.CodeCartProRefreshCartDrawer = refreshDrawer;
  $(function(){syncProductQtyFromCart();});
  $(document).on('change',"#product select, #product input[type='radio'], #product input[type='checkbox']",function(){setTimeout(syncProductQtyFromCart,120);});
  $(document).on('click','[data-ccp-cart-close]',function(e){e.preventDefault();closeDrawer();});
  $(document).on('click','#ccp-cart-drawer button[onclick*="cart.remove"],#ccp-cart-drawer button[onclick*="voucher.remove"]',function(){
    setTimeout(function(){ refreshDrawer(false); }, 650);
  });
  $(document).on('click','#ccp-cart-drawer [data-ccp-cart-qty]',function(e){
    e.preventDefault();
    var $box = $(this).closest('.ccp-cart-qty');
    var $input = $box.find('input');
    var key = $box.data('ccp-cart-key');
    var qty = parseInt($input.val() || '1', 10);
    if(isNaN(qty) || qty < 1){ qty = 1; }
    qty += ($(this).data('ccp-cart-qty') === 'plus') ? 1 : -1;
    if(qty < 1){ qty = 1; }
    $input.val(qty);
    updateDrawerQty(key, qty);
  });
  $(document).on('change','#ccp-cart-drawer .ccp-cart-qty input',function(){
    var $box = $(this).closest('.ccp-cart-qty');
    var key = $box.data('ccp-cart-key');
    var qty = parseInt($(this).val() || '1', 10);
    if(isNaN(qty) || qty < 1){ qty = 1; }
    $(this).val(qty);
    updateDrawerQty(key, qty);
  });
  $(document).on('click','.ccp-float-cart,.ccp-mobile-bar a[href*="checkout/cart"],.ccp-header-cart-link',function(e){
    if(!cfg.cartDrawer){return;}
    e.preventDefault();
    refreshDrawer(true);
  });
  $(document).ajaxSuccess(function(event, xhr, settings){
    var url = (settings && settings.url) ? settings.url : '';
    if(url.indexOf('checkout/cart/add') !== -1 || url.indexOf('account/wishlist/add') !== -1 || url.indexOf('product/compare/add') !== -1){
      var json = xhr.responseJSON;
      if(!json){try{json = JSON.parse(xhr.responseText);}catch(e){json = null;}}
      if(json && json.success){
        if(url.indexOf('checkout/cart/add') !== -1 && window.CCP_PRODUCT_ADD_ACTIVE){
          return;
        }
        if(url.indexOf('checkout/cart/add') !== -1 && cfg.directCheckoutAfterAdd && cfg.checkoutUrl && !cfg.cartAddPopup){
          if(window.CCP_CART_SYNC_PENDING){return;}
          window.location.href = cfg.checkoutUrl;
          return;
        }
        var popupShown = false;
        if(url.indexOf('checkout/cart/add') !== -1 && cfg.cartAddPopup && window.CCP_PENDING_CART_POPUP){
          popupShown = window.CodeCartProShowCartAddPopup ? window.CodeCartProShowCartAddPopup(window.CCP_PENDING_CART_POPUP, json.total, json.ccp_cart_item) : false;
          window.CCP_PENDING_CART_POPUP = null;
        }
        if(!popupShown){showToast(json.success, 'success');}
        if(url.indexOf('checkout/cart/add') !== -1){if(window.CCP_CART_SYNC_PENDING){setTimeout(function(){if(!window.CCP_CART_SYNC_PENDING){refreshDrawer(popupShown ? false : true);syncProductQtyFromCart();}},420);}else{refreshDrawer(popupShown ? false : true);syncProductQtyFromCart();}}
      } else if(json && json.error){
        if(url.indexOf('checkout/cart/add') !== -1){window.CCP_PENDING_CART_POPUP = null;}
        showToast(json.error.warning || json.error, 'danger');
      }
    }
  });
  $(document).on('click','.ccp-toast-close',function(){
    var $t=$(this).closest('.ccp-toast');$t.removeClass('show');setTimeout(function(){$t.remove();},240);
  });
  $(document).on('click touchend','[data-ccp-cart-popup-close]',function(e){
    e.preventDefault();
    e.stopPropagation();
    if (window.CodeCartProCloseCartAddPopup) { window.CodeCartProCloseCartAddPopup(); }
  });
  $(document).on('keydown',function(e){
    if(e.key === 'Escape' && window.CodeCartProCloseCartAddPopup){ window.CodeCartProCloseCartAddPopup(); }
  });
  $(document).on('click','[data-ccp-desc-more]',function(e){
    e.preventDefault();
    var $box=$(this).closest('.ccp-desc-collapsed');
    $box.toggleClass('is-open');
    $(this).text($box.hasClass('is-open') ? ($(this).data('text-less') || 'Hide') : ($(this).data('text-more') || 'Read more'));
  });
})(jQuery);


// CodeCart PRO Theme v8.7.7: accessible off-canvas catalogue and information panels.
(function($){
  'use strict';

  var $body = $('body');
  var lastTrigger = null;

  function removeBackdrop(){
    $('.ccp-mobile-menu-backdrop').remove();
  }

  function closePanels(restoreFocus){
    var $panels = $('#ccp-mobile-menu,#ccp-info-menu');
    $panels.removeClass('show').attr('aria-hidden','true').attr('inert','');
    $('[data-ccp-mobile-menu-toggle],[data-ccp-info-menu-toggle]').attr('aria-expanded','false');
    removeBackdrop();
    $body.removeClass('ccp-mobile-menu-open ccp-info-menu-open');
    if (restoreFocus !== false && lastTrigger && document.documentElement.contains(lastTrigger)) {
      try { lastTrigger.focus(); } catch (ignore) {}
    }
    lastTrigger = null;
  }

  function openPanel(selector, panelType, trigger){
    var $panel = $(selector);
    if (!$panel.length) return;

    closePanels(false);
    lastTrigger = trigger || null;

    var closeAttr = panelType === 'info' ? 'data-ccp-info-menu-close' : 'data-ccp-mobile-menu-close';
    $('<div class="ccp-mobile-menu-backdrop" aria-hidden="true"></div>').attr(closeAttr,'').appendTo($body);

    $panel.removeAttr('inert').attr('aria-hidden','false');
    if (lastTrigger) $(lastTrigger).attr('aria-expanded','true');
    $body.addClass('ccp-mobile-menu-open');
    if (panelType === 'info') $body.addClass('ccp-info-menu-open');

    window.requestAnimationFrame(function(){
      $panel.addClass('show');
      var $focus = $panel.find('[data-ccp-mobile-menu-close],[data-ccp-info-menu-close],a,button').filter(':visible').first();
      if ($focus.length) $focus.trigger('focus');
    });
  }

  function normalizeTopInfoButton(){
    var $strip = $('.ccp-tools-strip');
    var $btn = $strip.find('.ccp-tool-menu').first();
    if (!$strip.length || !$btn.length) return;
    $btn.removeAttr('data-ccp-mobile-menu-toggle')
      .attr('data-ccp-info-menu-toggle','')
      .attr('aria-controls','ccp-info-menu')
      .attr('aria-expanded','false')
      .addClass('ccp-tool-info-menu')
      .appendTo($strip);
  }

  normalizeTopInfoButton();
  $(normalizeTopInfoButton);

  $(document).on('click','[data-ccp-mobile-menu-toggle]',function(e){
    e.preventDefault();
    e.stopPropagation();
    openPanel('#ccp-mobile-menu','catalog',this);
  });

  $(document).on('click','[data-ccp-info-menu-toggle],.ccp-tools-strip .ccp-tool-info-menu',function(e){
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    openPanel('#ccp-info-menu','info',this);
    return false;
  });

  $(document).on('click','[data-ccp-mobile-menu-close],[data-ccp-info-menu-close]',function(e){
    e.preventDefault();
    closePanels(true);
  });

  $(document).on('keydown',function(e){
    if (e.key === 'Escape' || e.keyCode === 27) closePanels(true);
  });
})(jQuery);


// CodeCart PRO Theme: AJAX category navigation with safe fallback
(function($){
  'use strict';
  var busy = false;
  function enabled(){ return window.CodeCartProTheme && window.CodeCartProTheme.ajaxCatalog && $('.ccp-category-page').length; }
  function loadCategory(url, push){
    if(!enabled() || busy || !url){ window.location.href = url; return; }
    busy = true;
    var $page = $('.ccp-category-page');
    $page.addClass('ccp-ajax-loading');
    $.ajax({url:url, type:'get', dataType:'html', cache:true, success:function(html){
      var $html = $('<div/>').append($.parseHTML(html, document, true));
      var $newGrid = $html.find('.ccp-product-grid--category');
      var $newToolbar = $html.find('.ccp-toolbar');
      var $newPagination = $html.find('.ccp-pagination-row');
      if(!$newGrid.length){ window.location.href = url; return; }
      $('.ccp-product-grid--category').replaceWith($newGrid);
      if($newToolbar.length && $('.ccp-toolbar').length){ $('.ccp-toolbar').replaceWith($newToolbar); }
      if($newPagination.length && $('.ccp-pagination-row').length){ $('.ccp-pagination-row').replaceWith($newPagination); }
      var title = $html.find('title').first().text(); if(title){ document.title = title; }
      var canonical = $html.find('link[rel="canonical"]').attr('href');
      if(canonical){ var $c = $('link[rel="canonical"]'); if($c.length){$c.attr('href',canonical);}else{$('head').append('<link rel="canonical" href="'+canonical.replace(/"/g,'&quot;')+'">');} }
      if(push && window.history && history.pushState){ history.pushState({ccpAjaxCatalog:true}, title || document.title, url); }
      $('html,body').animate({scrollTop: Math.max(0, $('.ccp-category-page').offset().top - 80)}, 180);
      $(document).trigger('ccpAjaxCatalogLoaded');
    }, error:function(){ window.location.href = url; }, complete:function(){ busy=false; $('.ccp-category-page').removeClass('ccp-ajax-loading'); }});
  }
  $(document).on('change', '.ccp-category-page #input-sort, .ccp-category-page #input-limit', function(e){ e.preventDefault(); loadCategory($(this).val(), true); });
  $(document).on('click', '.ccp-category-page .pagination a', function(e){ e.preventDefault(); loadCategory($(this).attr('href'), true); });
  window.addEventListener('popstate', function(){ if(enabled()){ loadCategory(window.location.href, false); } });
})(jQuery);


// CodeCart PRO Theme: collapsible floating contacts
(function($){
  'use strict';
  $(document).on('click', '.ccp-float-toggle', function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.ccp-floating-contacts');
    var isOpen = !$wrap.hasClass('is-open');
    $wrap.toggleClass('is-open', isOpen);
    $(this).attr('aria-expanded', isOpen ? 'true' : 'false');
    $wrap.find('.ccp-floating-actions').attr('aria-hidden', isOpen ? 'false' : 'true');
  });
  $(document).on('click', function(e){
    if (!$(e.target).closest('.ccp-floating-contacts').length) {
      $('.ccp-floating-contacts.is-open').removeClass('is-open').find('.ccp-float-toggle').attr('aria-expanded','false');
      $('.ccp-floating-actions').attr('aria-hidden','true');
    }
  });
})(jQuery);

// CodeCart PRO Theme: static buy box + bottom sticky buy panel reveal
(function($){
  'use strict';
  function updateStickyBuyPanel(){
    var $panel = $('.ccp-mobile-buy-panel');
    var $buyBox = $('#product.ccp-buy-box');
    if (!$panel.length || !$buyBox.length) { return; }
    var rect = $buyBox[0].getBoundingClientRect();
    var shouldShow = rect.bottom < 0;
    $panel.toggleClass('is-visible', shouldShow);
  }
  $(function(){
    updateStickyBuyPanel();
    $(window).on('scroll.ccpStickyBuy resize.ccpStickyBuy orientationchange.ccpStickyBuy', updateStickyBuyPanel);
  });
  $(document).on('click','[data-ccp-mobile-cart]',function(e){
    e.preventDefault();
    var $cart = $('#button-cart');
    if ($cart.length) { $cart.trigger('click'); }
  });
})(jQuery);


// CodeCart PRO Theme: sticky buy quantity sync
(function($){
  'use strict';
  function normalizeQty(value, min){
    var qty = parseInt(value || min || '1', 10);
    min = parseInt(min || '1', 10);
    if (isNaN(min) || min < 1) { min = 1; }
    if (isNaN(qty) || qty < min) { qty = min; }
    return qty;
  }
  function syncStickyQty(){
    var $main = $('#input-quantity');
    var $sticky = $('[data-ccp-sticky-qty-input]');
    if (!$main.length || !$sticky.length) { return; }
    var qty = normalizeQty($main.val(), $main.attr('min'));
    if (String($sticky.val()) !== String(qty)) { $sticky.val(qty); }
  }
  $(document).on('click','[data-ccp-sticky-qty]',function(e){
    var action = $(this).attr('data-ccp-sticky-qty');
    if (action !== 'plus' && action !== 'minus') { return; }
    e.preventDefault();
    var $main = $('#input-quantity');
    if (!$main.length) { return; }
    var min = normalizeQty($main.attr('min'), 1);
    var qty = normalizeQty($main.val(), min);
    qty += action === 'plus' ? 1 : -1;
    qty = normalizeQty(qty, min);
    $main.val(qty).trigger('change');
    syncStickyQty();
  });
  $(document).on('change input','[data-ccp-sticky-qty-input]',function(){
    var $main = $('#input-quantity');
    if (!$main.length) { return; }
    var qty = normalizeQty($(this).val(), $main.attr('min'));
    $(this).val(qty);
    $main.val(qty).trigger('change');
  });
  $(document).on('change input','#input-quantity',syncStickyQty);
  $(syncStickyQty);
})(jQuery);

// CodeCart PRO Theme v8.7.7: reversible mobile banner visibility helper
(function($){
  'use strict';
  function resolveTarget($el){
    if (!$el || !$el.length) { return $(); }
    var $target = $el;
    var $viewport = $el.closest('.swiper-viewport');
    if ($viewport.length) { $target = $viewport; }
    var $module = $target.closest('.ccp-module, .module, .box, .panel');
    if ($module.length && !$module.is('body') && !$module.find('.product-thumb,.ccp-product-card').length) { $target = $module; }
    return $target;
  }
  function hideBlock($el){
    var $target = resolveTarget($el);
    if (!$target.length) { return; }
    $target.each(function(){
      var node = this;
      if (!node.hasAttribute('data-ccp-mobile-hidden-banner')) {
        node.setAttribute('data-ccp-mobile-prev-display', node.style.display || '');
      }
      node.setAttribute('data-ccp-mobile-hidden-banner', '1');
      node.style.display = 'none';
    });
  }
  function restoreHiddenBanners(){
    $('[data-ccp-mobile-hidden-banner="1"]').each(function(){
      var previous = this.getAttribute('data-ccp-mobile-prev-display');
      this.style.display = previous || '';
      this.removeAttribute('data-ccp-mobile-hidden-banner');
      this.removeAttribute('data-ccp-mobile-prev-display');
    });
  }
  function applyMobileBannerVisibility(){
    var cfg = window.CodeCartProTheme || {};
    var breakpoint = parseInt(cfg.mobileBannerBreakpoint || 767, 10);
    if (!breakpoint || breakpoint < 320) { breakpoint = 767; }

    // Always restore first so resize/orientation and changed settings are reversible.
    restoreHiddenBanners();
    if (window.innerWidth > breakpoint) { return; }

    if (cfg.mobileHideHomeSlider) { hideBlock($('.ccp-home-builder .ccp-hero')); }
    if (cfg.mobileHideHomeBanners) { hideBlock($('.ccp-home-builder .ccp-home-promos')); }
    if (cfg.mobileHideNativeSlideshow) { $('[id^="slideshow"]').each(function(){ hideBlock($(this)); }); }
    if (cfg.mobileHideNativeBanners) { $('[id^="banner"]').each(function(){ hideBlock($(this)); }); }
  }
  $(applyMobileBannerVisibility);
  $(window).on('resize.ccpMobileBanners orientationchange.ccpMobileBanners', applyMobileBannerVisibility);
  window.CodeCartProApplyMobileBannerVisibility = applyMobileBannerVisibility;
})(jQuery);


// CodeCart PRO Theme v8.7.7: native OpenCart Swiper layout without geometry overrides
(function($){
  'use strict';
  function applyNativeBannerLayout(){
    var cfg = window.CodeCartProTheme || {};
    var fit = cfg.nativeBannerFitMode || 'height';
    if ($.inArray(fit, ['width','height','cover']) === -1) { fit = 'height'; }

    $('.swiper-viewport, .slideshow, .ccp-native-slideshow').each(function(){
      var $root = $(this);
      if ($root.attr('data-ccp-native-banner-skip') === '1') { return; }
      if ($root.closest('.product-info, .product-layout, .product-thumb, .ccp-product-card, .ccp-product-gallery, .thumbnails, #product').length) { return; }
      if ($root.find('.product-thumb, .product-layout, .ccp-product-card').length) { return; }

      var $native = $root.is('[id^="slideshow"],[id^="banner"]') ? $root : $root.find('[id^="slideshow"],[id^="banner"]').first();
      if (!$native.length && !$root.hasClass('slideshow') && !$root.hasClass('ccp-native-slideshow')) { return; }

      var $images = $root.find('.swiper-slide img');
      if (!$images.length) { $images = $root.find('img'); }
      if (!$images.length) { return; }

      $root
        .removeClass('ccp-banner-cover-ready ccp-banner-fit-width ccp-banner-fit-height ccp-banner-fit-cover ccp-native-banner-fit-width ccp-native-banner-fit-height ccp-native-banner-fit-cover')
        .addClass('ccp-native-banner-ready ccp-native-banner-fit-' + fit);

      // Swiper owns wrapper/slide widths and transforms. Never touch their inline geometry.
      $images.addClass('ccp-native-banner-img');

      // Stage height can change with the selected fit mode; ask Swiper to refresh safely.
      var swiperNode = $native.get(0);
      var swiperInstance = swiperNode && (swiperNode.swiper || $native.data('swiper'));
      if (swiperInstance && typeof swiperInstance.update === 'function') {
        window.setTimeout(function(){
          try { swiperInstance.update(true); } catch (e) { try { swiperInstance.update(); } catch (ignore) {} }
        }, 0);
      }
    });
  }
  $(applyNativeBannerLayout);
  $(window).on('load.ccpBannerLayout resize.ccpBannerLayout orientationchange.ccpBannerLayout', applyNativeBannerLayout);
  $(document).on('ccpAjaxCatalogLoaded shown.bs.tab', applyNativeBannerLayout);
  window.CodeCartProApplyBannerCover = applyNativeBannerLayout;
})(jQuery);


// CodeCart PRO Theme v8.7.7: route-class fallback and social-link normalization.
(function($){
  'use strict';
  function addRouteClassFallback(){
    var map = {
      '#common-home':'common-home', '#product-product':'product-product', '#product-category':'product-category',
      '#product-search':'product-search', '#information-information':'information-information',
      '#checkout-cart':'checkout-cart', '#checkout-checkout':'checkout-checkout',
      '#account-account':'account-account', '#account-login':'account-login'
    };
    $.each(map, function(selector, className){
      if ($(selector).length) { $('body').addClass(className); }
    });
  }
  function normalizeSocialValue(value, service){
    value = $.trim(String(value || ''));
    if (!value) { return ''; }
    if (/^https?:\/\//i.test(value)) {
      try { return new URL(value, window.location.href).href; } catch (e) { return ''; }
    }
    if (service === 'telegram') {
      if (/^tg:\/\//i.test(value)) { return value; }
      var m = value.match(/^(?:t\.me|telegram\.me)\/([A-Za-z][A-Za-z0-9_]{4,31})\/?$/i);
      if (m) { return 'https://t.me/' + m[1]; }
      value = value.replace(/^@/, '');
      return /^[A-Za-z][A-Za-z0-9_]{4,31}$/.test(value) ? 'https://t.me/' + value : '';
    }
    if (service === 'viber') {
      if (/^viber:\/\//i.test(value)) { return value; }
      var viberDigits = value.replace(/\D+/g, '');
      return viberDigits.length >= 7 ? 'viber://chat?number=%2B' + viberDigits : '';
    }
    if (service === 'whatsapp') {
      if (/^whatsapp:\/\//i.test(value)) { return value; }
      var whatsappDigits = value.replace(/\D+/g, '');
      return whatsappDigits.length >= 7 ? 'https://wa.me/' + whatsappDigits : '';
    }
    return '';
  }
  function normalizeSocialLinks(){
    $('.ccp-float-telegram,.ccp-float-viber,.ccp-float-whatsapp').each(function(){
      var $link = $(this);
      var service = $link.hasClass('ccp-float-telegram') ? 'telegram' : ($link.hasClass('ccp-float-viber') ? 'viber' : 'whatsapp');
      var raw = $link.attr('data-ccp-social-value') || $link.attr('href') || '';
      var href = normalizeSocialValue(raw, service);
      if (!href) {
        $link.attr('aria-hidden','true').hide();
        return;
      }
      $link.attr('href', href).removeAttr('aria-hidden').show();
    });
  }
  $(function(){ addRouteClassFallback(); normalizeSocialLinks(); });
  window.CodeCartProNormalizeSocialLinks = normalizeSocialLinks;
})(jQuery);


// CodeCart PRO Theme: mobile swipe support for open product gallery
(function($){
  'use strict';
  var startX = 0;
  var startY = 0;
  var startTime = 0;
  $(document).on('touchstart.ccpGallerySwipe', '[data-ccp-gallery-modal].show', function(e){
    if ($(this).hasClass('ccp-gallery-zoomed') || (e.originalEvent && e.originalEvent.touches && e.originalEvent.touches.length > 1)) { startTime = 0; return; }
    if ($(e.target).closest('button,a,input,select,textarea,.ccp-gallery-lightbox__bar,.ccp-gallery-lightbox__thumbs').length) { return; }
    var t = e.originalEvent && e.originalEvent.touches ? e.originalEvent.touches[0] : null;
    if (!t) { return; }
    startX = t.clientX;
    startY = t.clientY;
    startTime = Date.now();
  });
  $(document).on('touchend.ccpGallerySwipe', '[data-ccp-gallery-modal].show', function(e){
    if ($(this).hasClass('ccp-gallery-zoomed') || !startTime) { startTime = 0; return; }
    var t = e.originalEvent && e.originalEvent.changedTouches ? e.originalEvent.changedTouches[0] : null;
    if (!t) { return; }
    var dx = t.clientX - startX;
    var dy = t.clientY - startY;
    var elapsed = Date.now() - startTime;
    startTime = 0;
    if (elapsed > 900) { return; }
    if (Math.abs(dx) < 45 || Math.abs(dx) < Math.abs(dy) * 1.25) { return; }
    e.preventDefault();
    var $modal = $(this);
    if (dx < 0) {
      $modal.find('[data-ccp-gallery-next]').trigger('click');
    } else {
      $modal.find('[data-ccp-gallery-prev]').trigger('click');
    }
  });
})(jQuery);

// CodeCart PRO Theme: native banner background fallback disabled; custom slideshow replaces unstable native slideshow integration.




(function(){
  'use strict';
  var STORAGE_KEY = 'ccp_dark_theme';
  var COOKIE_KEY = 'ccp_dark_theme';
  var lastTouch = 0;

  function ready(fn){
    if(document.readyState === 'loading'){
      document.addEventListener('DOMContentLoaded', fn, false);
    } else {
      fn();
    }
  }
  function cfg(){ return window.CodeCartProTheme || {}; }
  function isEnabled(){
    var c = cfg();
    return !(String(c.darkTheme) === '0' || String(c.darkToggle) === '0');
  }
  function buttons(){ return document.querySelectorAll('[data-ccp-dark-toggle],.ccp-dark-theme-toggle'); }
  function readCookie(){
    var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_KEY.replace(/([.$?*|{}()\[\]\\\/\+^])/g,'\\$1') + '=([^;]*)'));
    return match ? decodeURIComponent(match[1]) : null;
  }
  function readSaved(){
    var saved = null;
    try { saved = localStorage.getItem(STORAGE_KEY); } catch(e) {}
    if (saved === null) { saved = readCookie(); }
    return saved;
  }
  function writeSaved(on){
    try { localStorage.setItem(STORAGE_KEY, on ? '1' : '0'); } catch(e) {}
    document.cookie = COOKIE_KEY + '=' + (on ? '1' : '0') + '; path=/; max-age=31536000; SameSite=Lax';
  }
  function setButtonState(on){
    var list = buttons();
    for (var i = 0; i < list.length; i++) {
      list[i].setAttribute('aria-pressed', on ? 'true' : 'false');
      list[i].setAttribute('title', on ? 'Light theme' : 'Dark theme');
      list[i].style.display = '';
      var icon = list[i].querySelector('i');
      if (icon) { icon.className = on ? 'fa fa-sun-o' : 'fa fa-moon-o'; }
    }
  }
  function applyDark(on, persist){
    on = !!on;
    var html = document.documentElement;
    var body = document.body;
    if (html) {
      html.classList.toggle('ccp-dark-theme', on);
      html.style.colorScheme = on ? 'dark' : '';
    }
    if (body) {
      body.classList.add('ccp-theme');
      body.classList.toggle('ccp-dark-theme', on);
    }
    setButtonState(on);
    if (persist !== false) { writeSaved(on); }
    return on;
  }
  function hideDarkButtons(){
    applyDark(false, true);
    var list = buttons();
    for (var i = 0; i < list.length; i++) { list[i].style.display = 'none'; }
  }
  function initialDarkState(){
    var c = cfg();
    var saved = readSaved();
    if (saved === '1' || saved === 'true') { return true; }
    if (saved === '0' || saved === 'false') { return false; }
    return !!c.darkDefault;
  }
  function initDarkMode(){
    if (!isEnabled()) { hideDarkButtons(); return; }
    applyDark(initialDarkState(), false);
  }
  function handleToggle(e){
    var target = e.target;
    var btn = target && target.closest ? target.closest('[data-ccp-dark-toggle],.ccp-dark-theme-toggle') : null;
    if (!btn) { return; }
    if (e.type === 'click' && Date.now() - lastTouch < 500) {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
      return;
    }
    if (e.type === 'touchend') { lastTouch = Date.now(); }
    if (!isEnabled()) { hideDarkButtons(); return; }
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) { e.stopImmediatePropagation(); }
    var html = document.documentElement;
    var body = document.body;
    var active = !!((html && html.classList.contains('ccp-dark-theme')) || (body && body.classList.contains('ccp-dark-theme')));
    applyDark(!active, true);
  }

  window.CodeCartProDarkApply = applyDark;
  window.CodeCartProDarkToggle = function(){
    if (!isEnabled()) { hideDarkButtons(); return false; }
    var html = document.documentElement;
    var body = document.body;
    var active = !!((html && html.classList.contains('ccp-dark-theme')) || (body && body.classList.contains('ccp-dark-theme')));
    return applyDark(!active, true);
  };

  ready(initDarkMode);
  document.addEventListener('click', handleToggle, true);
  document.addEventListener('touchend', handleToggle, true);
})();


// CodeCart PRO Theme v8.x: duplicated topbar handlers removed; header controller is authoritative.


// CodeCart PRO Theme: product page thumbnail and related carousels
(function($){
  'use strict';
  function ccpUpdateThumbNav($wrap){
    if(!$wrap || !$wrap.length){ return; }
    var $track = $wrap.find('[data-ccp-gallery-thumbs]').first();
    if(!$track.length){ return; }
    var maxScroll = Math.max(0, $track[0].scrollWidth - $track.innerWidth());
    var scrollLeft = $track.scrollLeft();
    var hasOverflow = maxScroll > 4;
    $wrap.toggleClass('has-overflow', hasOverflow);
    $wrap.find('[data-ccp-thumb-nav="prev"]').prop('disabled', !hasOverflow || scrollLeft <= 4);
    $wrap.find('[data-ccp-thumb-nav="next"]').prop('disabled', !hasOverflow || scrollLeft >= maxScroll - 4);
  }
  function ccpScrollThumbs($button, direction){
    var $wrap = $button.closest('.ccp-product-thumbs-wrap');
    var $track = $wrap.find('[data-ccp-gallery-thumbs]').first();
    if(!$track.length){ return; }
    var $first = $track.children('[data-ccp-gallery-thumb]').first();
    var unit = $first.length ? ($first.outerWidth(true) || 124) : 124;
    var visible = Math.max(1, Math.floor($track.innerWidth() / Math.max(1, unit)));
    var shift = Math.max(unit, unit * Math.max(1, visible - 1));
    $track.stop().animate({scrollLeft: $track.scrollLeft() + (direction === 'next' ? shift : -shift)}, 220, function(){ ccpUpdateThumbNav($wrap); });
  }
  $(document).on('click','[data-ccp-thumb-nav]',function(e){
    e.preventDefault();
    ccpScrollThumbs($(this), $(this).attr('data-ccp-thumb-nav'));
  });
  $(document).on('scroll','[data-ccp-gallery-thumbs]',function(){
    ccpUpdateThumbNav($(this).closest('.ccp-product-thumbs-wrap'));
  });
  $(window).on('resize.ccpThumbNav orientationchange.ccpThumbNav', function(){
    $('.ccp-product-thumbs-wrap').each(function(){ ccpUpdateThumbNav($(this)); });
  });
  $(function(){
    $('.ccp-product-thumbs-wrap').each(function(){ ccpUpdateThumbNav($(this)); });
  });

  function ccpUpdateRelatedNav($wrap){
    if(!$wrap || !$wrap.length){ return; }
    var $track = $wrap.find('.ccp-product-grid--carousel').first();
    if(!$track.length){ return; }
    var maxScroll = Math.max(0, $track[0].scrollWidth - $track.outerWidth());
    var scrollLeft = $track.scrollLeft();
    $wrap.find('[data-ccp-carousel="prev"]').prop('disabled', scrollLeft <= 4);
    $wrap.find('[data-ccp-carousel="next"]').prop('disabled', scrollLeft >= maxScroll - 4);
  }
  $(document).on('click','[data-ccp-carousel]',function(e){
    e.preventDefault();
    var $wrap = $(this).closest('.ccp-related-carousel');
    var $track = $wrap.find('.ccp-product-grid--carousel').first();
    if(!$track.length){ return; }
    var shift = Math.max(260, Math.round($track.outerWidth() * 0.9));
    var direction = $(this).attr('data-ccp-carousel') === 'next' ? 1 : -1;
    $track.stop().animate({scrollLeft: $track.scrollLeft() + shift * direction}, 260, function(){ ccpUpdateRelatedNav($wrap); });
  });
  $(document).on('scroll','.ccp-product-grid--carousel',function(){
    ccpUpdateRelatedNav($(this).closest('.ccp-related-carousel'));
  });
  $(window).on('resize.ccpRelatedCarousel orientationchange.ccpRelatedCarousel', function(){
    $('.ccp-related-carousel').each(function(){ ccpUpdateRelatedNav($(this)); });
  });
  $(function(){
    $('.ccp-related-carousel').each(function(){ ccpUpdateRelatedNav($(this)); });
  });
})(jQuery);


// CodeCart PRO Theme: carousel polish
(function($){
  'use strict';
  function ccpNormalizeCarouselButtons(){
    $('.ccp-product-thumbs-wrap').each(function(){
      var $wrap = $(this);
      var $track = $wrap.find('[data-ccp-gallery-thumbs]').first();
      if (!$track.length) { return; }
      var need = $track[0].scrollWidth > $track.outerWidth() + 4;
      $wrap.toggleClass('has-overflow', need);
      if (!need) { $wrap.find('[data-ccp-thumb-nav]').prop('disabled', true); }
    });
    $('.ccp-related-carousel').each(function(){
      var $wrap = $(this);
      var $track = $wrap.find('.ccp-product-grid--carousel').first();
      if (!$track.length) { return; }
      var need = $track[0].scrollWidth > $track.outerWidth() + 4;
      $wrap.toggleClass('has-overflow', need);
      if (!need) { $wrap.find('[data-ccp-carousel]').prop('disabled', true); }
    });
  }
  $(window).on('load resize orientationchange', ccpNormalizeCarouselButtons);
  $(document).on('click', '[data-ccp-gallery-thumb]', function(){
    var $wrap = $(this).closest('.ccp-product-thumbs-wrap');
    var el = this;
    setTimeout(function(){ try { el.scrollIntoView({behavior:'smooth', block:'nearest', inline:'center'}); } catch(e) {} ccpNormalizeCarouselButtons(); }, 30);
  });
  $(ccpNormalizeCarouselButtons);
})(jQuery);


// CodeCart PRO Theme v7.1.0 product share modal
(function($){
  'use strict';
  function ccpCurrentShareUrl(){
    return window.location.href;
  }
  function ccpCurrentShareTitle(){
    return $.trim($('.ccp-product-title-top').first().text()) || document.title || '';
  }
  function ccpBuildShareLinks(){
    var url = encodeURIComponent(ccpCurrentShareUrl());
    var title = encodeURIComponent(ccpCurrentShareTitle());
    var map = {
      viber: 'viber://forward?text=' + title + '%20' + url,
      facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + url,
      telegram: 'https://t.me/share/url?url=' + url + '&text=' + title,
      whatsapp: 'https://api.whatsapp.com/send?text=' + title + '%20' + url,
      x: 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title
    };
    $('[data-ccp-share-link]').each(function(){
      var key = $(this).attr('data-ccp-share-link');
      if (map[key]) { $(this).attr('href', map[key]); }
    });
  }
  $(document).on('click','[data-ccp-share-open]',function(e){
    e.preventDefault();
    ccpBuildShareLinks();
    $('#ccpShareModal').addClass('ccp-share-open').attr('aria-hidden','false').removeAttr('inert'); $('body').addClass('ccp-share-lock');
  });

  $(document).on('click','[data-ccp-share-close]',function(e){
    e.preventDefault();
    $('#ccpShareModal').removeClass('ccp-share-open').attr('aria-hidden','true').attr('inert','');
    $('body').removeClass('ccp-share-lock');
  });
  $(document).on('click','#ccpShareModal',function(e){
    if (e.target === this) {
      $('#ccpShareModal').removeClass('ccp-share-open').attr('aria-hidden','true').attr('inert','');
      $('body').removeClass('ccp-share-lock');
    }
  });
  $(document).on('keyup',function(e){
    if (e.key === 'Escape') {
      $('#ccpShareModal').removeClass('ccp-share-open').attr('aria-hidden','true').attr('inert','');
      $('body').removeClass('ccp-share-lock');
    }
  });
  $(document).on('click','[data-ccp-copy-share]',function(e){
    e.preventDefault();
    var $btn = $(this);
    var text = ccpCurrentShareUrl();
    var done = function(){
      var original = $btn.text();
      $btn.text($btn.attr('data-ccp-copied') || 'Copied');
      setTimeout(function(){ $btn.text(original); }, 1800);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done);
      return;
    }
    var $tmp = $('<input type="text" class="ccp-share-temp" />').val(text).appendTo('body').select();
    try { document.execCommand('copy'); done(); } catch(err) {}
    $tmp.remove();
  });
})(jQuery);


// CodeCart PRO Theme: guarded mobile product gallery opening.
// A vertical page scroll over the product photo must not open the fullscreen gallery.
(function($){
  'use strict';
  var touch = {active:false, moved:false, startX:0, startY:0, startTime:0, main:null};
  var blockClickUntil = 0;
  var MOVE_LIMIT = 14;
  var TAP_TIME_LIMIT = 700;

  function closestMain(target){
    var node = target;
    while(node && node.nodeType === 1){
      if(node.matches && (node.matches('a[data-ccp-gallery-main]') || node.matches('.ccp-gallery-zoom'))){
        return node.matches('a[data-ccp-gallery-main]') ? node : node.closest('a[data-ccp-gallery-main]');
      }
      node = node.parentNode;
    }
    return null;
  }

  document.addEventListener('touchstart', function(e){
    var main = closestMain(e.target);
    if(!main || !e.touches || e.touches.length !== 1){ return; }
    touch.active = true;
    touch.moved = false;
    touch.main = main;
    touch.startX = e.touches[0].clientX;
    touch.startY = e.touches[0].clientY;
    touch.startTime = Date.now();
  }, {passive:true, capture:true});

  document.addEventListener('touchmove', function(e){
    if(!touch.active || !e.touches || e.touches.length !== 1){ return; }
    var dx = e.touches[0].clientX - touch.startX;
    var dy = e.touches[0].clientY - touch.startY;
    if(Math.sqrt(dx * dx + dy * dy) > MOVE_LIMIT){
      touch.moved = true;
    }
  }, {passive:true, capture:true});

  document.addEventListener('touchcancel', function(){
    touch.active = false;
    touch.moved = false;
    touch.main = null;
    blockClickUntil = Date.now() + 450;
  }, {passive:true, capture:true});

  document.addEventListener('touchend', function(e){
    if(!touch.active || !touch.main){ return; }
    var main = touch.main;
    var moved = touch.moved;
    var elapsed = Date.now() - touch.startTime;
    touch.active = false;
    touch.moved = false;
    touch.main = null;

    if(moved || elapsed > TAP_TIME_LIMIT){
      blockClickUntil = Date.now() + 550;
      return;
    }

    blockClickUntil = Date.now() + 550;
    e.preventDefault();
    e.stopPropagation();
    $(main).trigger('click');
  }, {passive:false, capture:true});

  document.addEventListener('click', function(e){
    var main = closestMain(e.target);
    if(!main){ return; }
    if(Date.now() < blockClickUntil){
      e.preventDefault();
      e.stopPropagation();
      if(e.stopImmediatePropagation){ e.stopImmediatePropagation(); }
    }
  }, true);
})(jQuery);

// CodeCart PRO Theme: pinch zoom and drag for product gallery lightbox on mobile devices.
(function(){
  'use strict';
  var states = typeof WeakMap !== 'undefined' ? new WeakMap() : null;
  function closest(el, selector){ while(el && el.nodeType === 1){ if(el.matches && el.matches(selector)){ return el; } el = el.parentNode; } return null; }
  function getState(modal){
    if(!states){ modal.__ccpZoomState = modal.__ccpZoomState || {}; return modal.__ccpZoomState; }
    var st = states.get(modal);
    if(!st){ st = {}; states.set(modal, st); }
    return st;
  }
  function clamp(value, min, max){ return Math.max(min, Math.min(max, value)); }
  function dist(a,b){ var x=a.clientX-b.clientX; var y=a.clientY-b.clientY; return Math.sqrt(x*x+y*y); }
  function mid(a,b){ return {x:(a.clientX+b.clientX)/2,y:(a.clientY+b.clientY)/2}; }
  function img(modal){ return modal ? modal.querySelector('[data-ccp-gallery-image]') : null; }
  function apply(modal){
    var st = getState(modal), image = img(modal);
    if(!image){ return; }
    st.scale = st.scale || 1;
    st.x = st.x || 0;
    st.y = st.y || 0;
    if(st.scale <= 1.01){ st.scale = 1; st.x = 0; st.y = 0; }
    image.style.transform = 'translate3d(' + st.x + 'px,' + st.y + 'px,0) scale(' + st.scale + ')';
    modal.classList.toggle('ccp-gallery-zoomed', st.scale > 1.01);
  }
  function reset(modal){
    if(!modal){ return; }
    var st = getState(modal);
    st.scale = 1; st.x = 0; st.y = 0; st.mode = '';
    modal.classList.remove('ccp-gallery-zoomed','ccp-gallery-pinching','ccp-gallery-dragging');
    apply(modal);
  }
  function ready(fn){ if(document.readyState !== 'loading'){ fn(); } else { document.addEventListener('DOMContentLoaded', fn); } }
  ready(function(){
    document.addEventListener('touchstart', function(e){
      var stage = closest(e.target, '[data-ccp-gallery-stage]');
      if(!stage){ return; }
      var modal = closest(stage, '[data-ccp-gallery-modal].show');
      if(!modal){ return; }
      var st = getState(modal);
      if(e.touches && e.touches.length === 2){
        st.mode = 'pinch';
        st.startDist = Math.max(1, dist(e.touches[0], e.touches[1]));
        st.startScale = st.scale || 1;
        st.startMid = mid(e.touches[0], e.touches[1]);
        st.startX = st.x || 0;
        st.startY = st.y || 0;
        modal.classList.add('ccp-gallery-pinching');
        e.preventDefault();
      } else if(e.touches && e.touches.length === 1 && (st.scale || 1) > 1.01){
        st.mode = 'drag';
        st.dragX = e.touches[0].clientX;
        st.dragY = e.touches[0].clientY;
        st.startX = st.x || 0;
        st.startY = st.y || 0;
        modal.classList.add('ccp-gallery-dragging');
        e.preventDefault();
      }
    }, {passive:false});
    document.addEventListener('touchmove', function(e){
      var stage = closest(e.target, '[data-ccp-gallery-stage]');
      if(!stage){ return; }
      var modal = closest(stage, '[data-ccp-gallery-modal].show');
      if(!modal){ return; }
      var st = getState(modal);
      if(st.mode === 'pinch' && e.touches && e.touches.length === 2){
        var currentDist = Math.max(1, dist(e.touches[0], e.touches[1]));
        var currentMid = mid(e.touches[0], e.touches[1]);
        st.scale = clamp((st.startScale || 1) * (currentDist / (st.startDist || currentDist)), 1, 4);
        st.x = (st.startX || 0) + (currentMid.x - st.startMid.x);
        st.y = (st.startY || 0) + (currentMid.y - st.startMid.y);
        apply(modal);
        e.preventDefault();
      } else if(st.mode === 'drag' && e.touches && e.touches.length === 1 && (st.scale || 1) > 1.01){
        st.x = (st.startX || 0) + (e.touches[0].clientX - st.dragX);
        st.y = (st.startY || 0) + (e.touches[0].clientY - st.dragY);
        apply(modal);
        e.preventDefault();
      }
    }, {passive:false});
    document.addEventListener('touchend', function(e){
      var modal = closest(e.target, '[data-ccp-gallery-modal].show');
      if(!modal){ return; }
      var st = getState(modal);
      if(!e.touches || e.touches.length === 0){
        st.mode = '';
        modal.classList.remove('ccp-gallery-pinching','ccp-gallery-dragging');
        apply(modal);
      }
    }, {passive:false});
    document.addEventListener('click', function(e){
      if(closest(e.target, '[data-ccp-gallery-prev],[data-ccp-gallery-next],.ccp-gallery-modal-thumb,[data-ccp-gallery-close]')){
        var modal = document.querySelector('[data-ccp-gallery-modal].show');
        setTimeout(function(){ reset(modal); }, 0);
      }
    }, true);
    document.addEventListener('dblclick', function(e){
      var stage = closest(e.target, '[data-ccp-gallery-stage]');
      if(!stage){ return; }
      var modal = closest(stage, '[data-ccp-gallery-modal].show');
      if(!modal){ return; }
      var st = getState(modal);
      if((st.scale || 1) > 1.01){ reset(modal); } else { st.scale = 2; st.x = 0; st.y = 0; apply(modal); }
      e.preventDefault();
    }, true);
    var observer = typeof MutationObserver !== 'undefined' ? new MutationObserver(function(list){
      list.forEach(function(m){
        if(m.type === 'attributes' && m.attributeName === 'src'){
          var modal = closest(m.target, '[data-ccp-gallery-modal]');
          reset(modal);
        }
      });
    }) : null;
    if(observer){
      document.querySelectorAll('[data-ccp-gallery-image]').forEach(function(image){ observer.observe(image, {attributes:true, attributeFilter:['src']}); });
    }
  });
})();




// CodeCart PRO Theme v8.x: redundant cart popup close guard removed; single close handler retained above.


// CodeCart PRO Theme v8.x: robust swipe guard, footer accordion, modal scroll cleanup and stable cart popup labels.
(function(){
  'use strict';
  if(window.CCP_FINAL_FIXES_V786){return;}
  window.CCP_FINAL_FIXES_V786=true;
  var touch={active:false,x:0,y:0,moved:false,startedOnBuy:false,t:0};
  var lastSwipeAt=0;
  function closest(el,selector){while(el&&el.nodeType===1){if(el.matches&&el.matches(selector))return el;el=el.parentElement;}return null;}
  function buyButton(el){return closest(el,'.ccp-card-cart,#button-cart,.ccp-product-buy-btn,.ccp-sticky-buy button,.ccp-sticky-buy .btn,.ccp-bottom-sticky-buy button,.ccp-bottom-sticky-buy .btn,.ccp-product-bottom-sticky button,.ccp-product-bottom-sticky .btn,[onclick*="cart.add"]');}
  function point(e){var p=(e.changedTouches&&e.changedTouches[0])||(e.touches&&e.touches[0])||e;return {x:p.clientX||0,y:p.clientY||0};}
  function stop(e){e.preventDefault();e.stopPropagation();if(e.stopImmediatePropagation)e.stopImmediatePropagation();return false;}
  function touchStart(e){var p=point(e);touch={active:true,x:p.x,y:p.y,moved:false,startedOnBuy:!!buyButton(e.target),t:Date.now()};}
  function touchMove(e){if(!touch.active)return;var p=point(e),dx=Math.abs(p.x-touch.x),dy=Math.abs(p.y-touch.y);if(dx>7||dy>7){touch.moved=true;lastSwipeAt=Date.now();}}
  function touchEnd(e){var btn=buyButton(e.target);var moved=touch.moved||((Date.now()-lastSwipeAt)<550);var started=touch.startedOnBuy;touch.active=false;if(btn&&moved){try{btn.setAttribute('data-ccp-swipe-blocked','1');}catch(err){}setTimeout(function(){try{btn.removeAttribute('data-ccp-swipe-blocked');}catch(err){}},700);lastSwipeAt=Date.now();return stop(e);} if(btn&&!started&&((Date.now()-lastSwipeAt)<450)){return stop(e);}}
  function clickGuard(e){var btn=buyButton(e.target);if(!btn)return;var blocked=(btn.getAttribute&&btn.getAttribute('data-ccp-swipe-blocked')==='1')||((Date.now()-lastSwipeAt)<520);if(blocked){return stop(e);}}
  document.addEventListener('touchstart',touchStart,true);
  document.addEventListener('touchmove',touchMove,true);
  document.addEventListener('touchend',touchEnd,true);
  document.addEventListener('touchcancel',function(){touch.active=false;lastSwipeAt=Date.now();},true);
  document.addEventListener('pointerdown',function(e){if(e.pointerType==='touch')touchStart(e);},true);
  document.addEventListener('pointermove',function(e){if(e.pointerType==='touch')touchMove(e);},true);
  document.addEventListener('pointerup',function(e){if(e.pointerType==='touch')touchEnd(e);},true);
  document.addEventListener('click',clickGuard,true);
  function footerAccordion(){
    var footer=document.querySelector('footer.ccp-footer,#footer.ccp-footer,footer,#footer');
    if(!footer||footer.getAttribute('data-ccp-footer-v786')==='1')return;
    footer.setAttribute('data-ccp-footer-v786','1');
    footer.classList.add('ccp-footer-accordion');
    footer.querySelectorAll('.row > [class*="col-"], .footer-column').forEach(function(col){
      var title=col.querySelector('.ccp-footer-title,h5,h4,h3');
      var list=col.querySelector('ul,.list-unstyled');
      if(!title||!list)return;
      col.classList.add('ccp-footer-section');
      title.classList.add('ccp-footer-heading');
      title.setAttribute('role','button');
      title.setAttribute('tabindex','0');
      title.setAttribute('aria-expanded',col.classList.contains('is-open')?'true':'false');
      function toggle(e){
        if(window.innerWidth>767)return;
        if(e){e.preventDefault();e.stopPropagation();}
        var open=col.classList.toggle('is-open');
        title.setAttribute('aria-expanded',open?'true':'false');
      }
      title.addEventListener('click',toggle,false);
      title.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '||e.keyCode===13||e.keyCode===32)toggle(e);},false);
    });
  }
  document.addEventListener('click',function(e){
    if(window.innerWidth>767)return;
    var title=closest(e.target,'.ccp-footer.ccp-footer-accordion .ccp-footer-heading');
    if(!title)return;
    var col=closest(title,'.ccp-footer-section');
    if(!col)return;
    e.preventDefault();
    e.stopPropagation();
    var open=col.classList.toggle('is-open');
    title.setAttribute('aria-expanded',open?'true':'false');
  },true);
  function cartPopupVisible(){var p=document.getElementById('ccp-cart-popup');return !!(p&&p.classList&&p.classList.contains('is-visible'));}
  function hasVisibleModal(){return !!document.querySelector('.modal.in,.modal.show');}
  function cleanupScrollLock(){if(hasVisibleModal()||cartPopupVisible()||document.querySelector('[data-ccp-gallery-modal].show'))return;if(document.body){document.body.classList.remove('modal-open','ccp-cart-popup-open','ccp-gallery-open');document.body.style.overflow='';document.body.style.paddingRight='';}document.documentElement.style.overflow='';document.querySelectorAll('.modal-backdrop').forEach(function(el){if(el.parentNode)el.parentNode.removeChild(el);});}
  function scheduleScrollCleanup(){setTimeout(cleanupScrollLock,80);setTimeout(cleanupScrollLock,260);}
  function run(){footerAccordion();scheduleScrollCleanup();}
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',run);}else{run();}
  window.addEventListener('load',run);
  document.addEventListener('click',function(e){if(closest(e.target,'[data-dismiss="modal"],[data-bs-dismiss="modal"],.modal .close,[data-ccp-cart-popup-close]'))scheduleScrollCleanup();},true);
  document.addEventListener('keyup',function(e){if(e.key==='Escape'||e.keyCode===27)scheduleScrollCleanup();},true);
  if(window.jQuery){try{window.jQuery(document).on('hidden.bs.modal','.modal',scheduleScrollCleanup);}catch(err){}}
})();


// CodeCart PRO Theme v8.x: category read-more button is outside collapsed text and never overlaps content.
(function($){
  'use strict';
  function refreshCategoryDescriptionButtons(){
    $('.ccp-category-page .ccp-desc-collapsed').each(function(){
      var box = this;
      var $box = $(box);
      var $btn = $box.children('[data-ccp-desc-more]').first();
      var content = $box.children('.ccp-desc-content').first()[0] || box;
      if (!$btn.length) { return; }
      if ($box.hasClass('is-open')) { $btn.css('display','inline-flex'); return; }
      var isOverflowing = content.scrollHeight > content.clientHeight + 8;
      if (!isOverflowing) {
        $box.addClass('ccp-desc-not-overflowing');
        $btn.hide();
      } else {
        $box.removeClass('ccp-desc-not-overflowing');
        $btn.css('display','inline-flex');
      }
    });
  }
  $(refreshCategoryDescriptionButtons);
  $(window).on('load resize', function(){ window.clearTimeout(window.CCP_DESC_REFRESH_TIMER_V791); window.CCP_DESC_REFRESH_TIMER_V791 = window.setTimeout(refreshCategoryDescriptionButtons, 120); });
})(jQuery);

// CodeCart PRO Theme v8.7.7: gallery ratio handling is centralized in ccpSetGalleryImage().



/* CodeCart PRO cached topbar dropdown controller */
(function(){
  'use strict';
  if (window.CCP_TOPBAR_CONTROLLER_V788) return;
  window.CCP_TOPBAR_CONTROLLER_V788 = true;

  function closest(el, selector){
    while (el && el.nodeType === 1) {
      if (el.matches && el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function nativeSubmit(form){
    if (!form) return;
    if (window.HTMLFormElement && HTMLFormElement.prototype && HTMLFormElement.prototype.submit) {
      HTMLFormElement.prototype.submit.call(form);
    } else if (form.submit) {
      form.submit();
    }
  }

  function getTopDropdownRoot(node){
    return closest(node, '.ccp-header-tools #form-currency, .ccp-header-tools #form-language, .ccp-header-tools .ccp-tool-account');
  }

  function closeTopDropdowns(except){
    var roots = document.querySelectorAll('.ccp-header-tools #form-currency, .ccp-header-tools #form-language, .ccp-header-tools .ccp-tool-account');
    for (var i = 0; i < roots.length; i++) {
      if (except && roots[i] === except) continue;
      roots[i].classList.remove('open', 'ccp-top-dropdown-open');
      var openNodes = roots[i].querySelectorAll('.open');
      for (var j = 0; j < openNodes.length; j++) openNodes[j].classList.remove('open');
      var toggles = roots[i].querySelectorAll('.dropdown-toggle');
      for (var k = 0; k < toggles.length; k++) toggles[k].setAttribute('aria-expanded', 'false');
    }
  }

  function openTopDropdown(toggle){
    var root = getTopDropdownRoot(toggle);
    var box = closest(toggle, '.btn-group, .dropdown, .ccp-tool-account');
    if (!root || !box) return false;
    var willOpen = !root.classList.contains('ccp-top-dropdown-open') && !box.classList.contains('open');
    closeTopDropdowns(root);
    root.classList.toggle('ccp-top-dropdown-open', willOpen);
    box.classList.toggle('open', willOpen);
    toggle.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    return true;
  }

  function submitChoice(button){
    var form = closest(button, '#form-currency, #form-language');
    if (!form) return false;
    // Standard OpenCart buttons use name=<language code>, while some overrides use value/data-code.
    var code = button.getAttribute('data-code') || button.value || button.getAttribute('name') || '';
    if (code === 'code') code = button.getAttribute('data-code') || button.value || '';
    if (!code) return false;
    var input = form.querySelector('input[name="code"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'code';
      form.appendChild(input);
    }
    input.value = code;
    closeTopDropdowns(null);
    nativeSubmit(form);
    return true;
  }

  function initTopbar(){
    var toggles = document.querySelectorAll('.ccp-header-tools #form-currency .dropdown-toggle, .ccp-header-tools #form-language .dropdown-toggle, .ccp-header-tools .ccp-tool-account > .dropdown-toggle');
    for (var i = 0; i < toggles.length; i++) {
      toggles[i].setAttribute('type', 'button');
      toggles[i].setAttribute('aria-haspopup', 'true');
      toggles[i].setAttribute('aria-expanded', 'false');
    }
  }

  document.addEventListener('click', function(e){
    var target = e.target;
    if (!target) return;

    var choice = closest(target, '.ccp-header-tools #form-currency .currency-select, .ccp-header-tools #form-language .language-select');
    if (choice) {
      if (submitChoice(choice)) {
        e.preventDefault();
        e.stopPropagation();
        if (e.stopImmediatePropagation) e.stopImmediatePropagation();
        return false;
      }
      return;
    }

    var nativeToggle = closest(target, '.ccp-header-tools #form-currency .dropdown-toggle, .ccp-header-tools #form-language .dropdown-toggle');
    if (nativeToggle) {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      openTopDropdown(nativeToggle);
      return false;
    }

    var accountToggle = closest(target, '.ccp-header-tools .ccp-tool-account > .dropdown-toggle');
    if (accountToggle) {
      e.preventDefault();
      e.stopPropagation();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      openTopDropdown(accountToggle);
      return false;
    }

    if (!closest(target, '.ccp-header-tools #form-currency, .ccp-header-tools #form-language, .ccp-header-tools .ccp-tool-account')) {
      closeTopDropdowns(null);
    }
  }, true);

  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' || e.keyCode === 27) closeTopDropdowns(null);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initTopbar);
  } else {
    initTopbar();
  }
})();


// CodeCart PRO Theme v8.7.7: information-only mode safety guard.
// Rendering is handled server-side in Twig. This guard only blocks late third-party purchase actions.
(function(){
  'use strict';

  function active(){
    return !!(
      (document.documentElement && document.documentElement.classList.contains('ccp-information-mode')) ||
      (document.body && document.body.classList.contains('ccp-information-mode')) ||
      (window.CodeCartProTheme && Number(window.CodeCartProTheme.informationMode) === 1)
    );
  }

  function configure(){
    if (!active()) return;
    window.CodeCartProTheme = window.CodeCartProTheme || {};
    window.CodeCartProTheme.informationMode = 1;
    window.CodeCartProTheme.quantitySwitch = 0;
    window.CodeCartProTheme.cartDrawer = 0;
    window.CodeCartProTheme.cartToast = 0;
    window.CodeCartProTheme.cartAddPopup = 0;
    window.CodeCartProTheme.directCheckoutAfterAdd = 0;
  }

  document.addEventListener('click',function(event){
    if (!active()) return;
    var target = event.target && event.target.closest ? event.target.closest(
      '#button-cart,.ccp-card-cart,.ccp-js-quick-order,[data-ccp-mobile-cart],' +
      'a[href*="route=checkout/cart"],a[href*="route=checkout/checkout"],' +
      'a[href$="/cart/"],a[href$="/checkout/"]'
    ) : null;
    if (!target) return;
    event.preventDefault();
    event.stopPropagation();
    if (event.stopImmediatePropagation) event.stopImmediatePropagation();
  },true);

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',configure); else configure();
})();


// CodeCart PRO Theme v8.7.7: remove only true duplicate product modules.
(function () {
  'use strict';
  function dedupeProductModules(scope) {
    scope = scope || document;
    var seen = {};
    var modules = document.querySelectorAll('[data-ccp-product-module-key]');
    for (var i = 0; i < modules.length; i++) {
      var key = (modules[i].getAttribute('data-ccp-product-module-key') || '').trim();
      if (!key) { continue; }
      if (seen[key]) {
        if (modules[i].parentNode) { modules[i].parentNode.removeChild(modules[i]); }
      } else {
        seen[key] = modules[i];
      }
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { dedupeProductModules(document); });
  } else {
    dedupeProductModules(document);
  }
  if (typeof MutationObserver !== 'undefined') {
    var timer = 0;
    new MutationObserver(function (mutations) {
      var hasAdded = false;
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].addedNodes && mutations[i].addedNodes.length) { hasAdded = true; break; }
      }
      if (!hasAdded) { return; }
      window.clearTimeout(timer);
      timer = window.setTimeout(function () { dedupeProductModules(document); }, 60);
    }).observe(document.documentElement, {childList:true, subtree:true});
  }
})();


/* CodeCart PRO Theme v8.7.7: remove empty native banner shells from sidebars. */
(function () {
  'use strict';
  function updateEmptySwipers() {
    var viewports = document.querySelectorAll('#column-left .swiper-viewport, #column-right .swiper-viewport');
    for (var i = 0; i < viewports.length; i++) {
      var wrapper = viewports[i].querySelector('.swiper-wrapper');
      var hasSlide = wrapper && wrapper.querySelector('.swiper-slide');
      viewports[i].classList.toggle('ccp-empty-swiper', !hasSlide);
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', updateEmptySwipers);
  } else {
    updateEmptySwipers();
  }
  window.setTimeout(updateEmptySwipers, 500);
  window.setTimeout(updateEmptySwipers, 1600);
})();



// Checkout sidebar follows the page within its own layout; header changes are measured.
(function(){
  function init(){
    if (!document.querySelector('.ccp-simple-checkout-grid,.csp-flow')) { return; }
    var scheduled=false;
    function measure(){
      scheduled=false;
      var offset=16;
      document.querySelectorAll('.ccp-header,.ccp-menu-wrap').forEach(function(el){
        var style=getComputedStyle(el);
        if (style.position==='sticky'||style.position==='fixed') { offset+=el.getBoundingClientRect().height; }
      });
      document.body.style.setProperty('--ccp-checkout-sticky-top',Math.round(offset)+'px');
    }
    function update(){if(!scheduled){scheduled=true;requestAnimationFrame(measure);}}
    update(); window.addEventListener('resize',update,{passive:true});
    if(window.ResizeObserver){var observer=new ResizeObserver(update);document.querySelectorAll('.ccp-header,.ccp-menu-wrap').forEach(function(el){observer.observe(el);});}
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
// A module video control is separate from the numbered image thumbnails.
(function(){
  function init(){
    var gallery=document.querySelector('[data-ccp-gallery]'), content=document.querySelector('#product-product #content');
    if(!gallery||!content){return;}
    function integrate(){
      var data=document.querySelector('.ccp-pvg-data');
      var triggers=content.querySelectorAll('.ccp-pvg-video-trigger');
      if(!data||!triggers.length){return;}
      var rail=gallery.querySelector('.ccp-gallery-videos');
      if(!rail){rail=document.createElement('div');rail.className='ccp-gallery-videos';}
      var position=data.getAttribute('data-video-position');
      var anchor=gallery.querySelector('.ccp-product-thumbs-wrap');
      if(rail.parentNode!==gallery){if(position==='start'||position==='first'||position==='before'){gallery.insertBefore(rail,anchor||gallery.firstChild);}else{gallery.appendChild(rail);}}
      triggers.forEach(function(trigger){if(!rail.contains(trigger)){rail.appendChild(trigger);}});
    }
    integrate();
    if(window.MutationObserver){new MutationObserver(integrate).observe(content,{childList:true,subtree:true});}
  }
  if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',init);}else{init();}
})();
