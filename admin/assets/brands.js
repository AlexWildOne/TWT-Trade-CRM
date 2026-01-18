/* global google, jQuery */
(function ($) {
  'use strict';

  function cfg() {
    var c = window.TWT_TCRM_BRAND || {};
    var sel = c.selectors || {};
    return {
      hasKey: !!c.hasKey,
      missingKeyMsg: (c.i18n && c.i18n.missingKey) ? c.i18n.missingKey : 'Falta configurar a Google Maps Browser Key nas definições do plugin.',
      address: sel.address || '#twt_brand_address',
      lat: sel.lat || '#twt_brand_lat',
      lng: sel.lng || '#twt_brand_lng',
      placeId: sel.placeId || '#twt_brand_place_id',
      latRead: sel.latRead || '#twt-brand-lat-read',
      lngRead: sel.lngRead || '#twt-brand-lng-read',
      placeRead: sel.placeRead || '#twt-brand-place-read'
    };
  }

  function num(v) {
    var n = parseFloat(v);
    return isNaN(n) ? null : n;
  }

  function setVal(selector, val) {
    var $el = $(selector);
    if (!$el.length) return;
    $el.val(val === null || val === undefined ? '' : String(val));
  }

  function setText(selector, val) {
    var $el = $(selector);
    if (!$el.length) return;
    $el.text(val === null || val === undefined || val === '' ? '-' : String(val));
  }

  function hasGooglePlaces() {
    try {
      return !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete);
    } catch (e) {
      return false;
    }
  }

  function showMissingKeyNotice() {
    var c = cfg();
    var $addr = $(c.address);
    if (!$addr.length) return;

    var $wrap = $addr.closest('.postbox, .inside, .wrap');
    if (!$wrap.length) $wrap = $addr.parent();

    if ($wrap.find('.twt-tcrm-missing-key').length) return;

    $wrap.prepend(
      '<div class="notice notice-warning twt-tcrm-missing-key" style="margin:0 0 12px 0;">' +
        '<p>' + String(c.missingKeyMsg) + '</p>' +
      '</div>'
    );
  }

  function initAutocomplete() {
    var c = cfg();

    var $addr = $(c.address);
    if (!$addr.length) return;

    if (!c.hasKey) {
      showMissingKeyNotice();
      return;
    }

    if (!hasGooglePlaces()) return;

    var addrEl = $addr[0];
    if (addrEl.dataset.twtAcInit === '1') return;
    addrEl.dataset.twtAcInit = '1';

    var ac = new google.maps.places.Autocomplete(addrEl, {
      types: ['geocode']
    });

    if (typeof ac.setFields === 'function') {
      ac.setFields(['place_id', 'geometry', 'formatted_address', 'name']);
    }

    function clearGeo() {
      setVal(c.lat, '');
      setVal(c.lng, '');
      setVal(c.placeId, '');
      setText(c.latRead, '-');
      setText(c.lngRead, '-');
      setText(c.placeRead, '-');
    }

    // se o user editar manualmente, limpa coords
    $addr.on('input', function () {
      if (!addrEl.dataset.twtManEdit) {
        addrEl.dataset.twtManEdit = '1';
        clearGeo();
      }
    });

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place || !place.geometry || !place.geometry.location) return;

      var loc = place.geometry.location;
      var la = (typeof loc.lat === 'function') ? loc.lat() : null;
      var ln = (typeof loc.lng === 'function') ? loc.lng() : null;

      if (la !== null && ln !== null) {
        setVal(c.lat, la);
        setVal(c.lng, ln);
        setText(c.latRead, la.toFixed(6));
        setText(c.lngRead, ln.toFixed(6));
      }

      if (place.place_id) {
        setVal(c.placeId, place.place_id);
        setText(c.placeRead, place.place_id);
      } else {
        setVal(c.placeId, '');
        setText(c.placeRead, '-');
      }

      if (place.formatted_address) {
        $addr.val(place.formatted_address);
      }

      // reset flag de "manual edit"
      addrEl.dataset.twtManEdit = '';
    });

    // popula reads iniciais se já existir
    var lat0 = num($(c.lat).val());
    var lng0 = num($(c.lng).val());
    if (lat0 !== null) setText(c.latRead, lat0.toFixed(6));
    if (lng0 !== null) setText(c.lngRead, lng0.toFixed(6));
    var pid0 = $(c.placeId).val() || '';
    if (pid0) setText(c.placeRead, pid0);
  }

  function bootWithRetry() {
    var tries = 0;
    function tick() {
      tries++;
      initAutocomplete();

      if (hasGooglePlaces()) return;

      if (tries < 25) {
        setTimeout(tick, 250);
      } else {
        var c = cfg();
        if (c.hasKey) {
          console.warn('Google Maps/Places não carregou. Verifica: API key, referrers, e Places API ativa.');
        }
      }
    }
    tick();
  }

  $(function () {
    var c = cfg();
    if (!$(c.address).length) return;

    if (!c.hasKey) {
      showMissingKeyNotice();
      return;
    }

    if (hasGooglePlaces()) {
      initAutocomplete();
    } else {
      bootWithRetry();
    }
  });

})(jQuery);