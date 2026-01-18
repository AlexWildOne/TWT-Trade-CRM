(function ($) {
  'use strict';

  function cfg() {
    var c = window.TWT_TCRM_LOC || {};
    var sel = c.selectors || {};
    return {
      hasKey: !!c.hasKey,
      missingKeyMsg: (c.i18n && c.i18n.missingKey) ? c.i18n.missingKey : 'Falta configurar a Google Maps Browser Key nas definições do plugin.',
      address: sel.address || '#twt_location_address',
      lat: sel.lat || '#twt_location_lat',
      lng: sel.lng || '#twt_location_lng',
      placeId: sel.placeId || '#twt_location_place_id',
      radius: sel.radius || '#twt_location_radius_m',
      map: sel.map || '#twt-location-map',
      latRead: sel.latRead || '#twt-loc-lat-read',
      lngRead: sel.lngRead || '#twt-loc-lng-read',
      placeRead: sel.placeRead || '#twt-loc-place-read'
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

  function getRadius() {
    var c = cfg();
    var r = parseInt($(c.radius).val(), 10);
    if (!r || r <= 0) r = 80;
    if (r > 2000) r = 2000;
    return r;
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
    var $mapEl = $(c.map);
    if (!$mapEl.length) return;

    var $wrap = $mapEl.closest('.twt-loc-map-wrap');
    if (!$wrap.length) $wrap = $mapEl.parent();

    if ($wrap.find('.twt-tcrm-missing-key').length) return;

    $wrap.prepend(
      '<div class="notice notice-warning twt-tcrm-missing-key" style="margin:0 0 12px 0;">' +
        '<p>' + String(c.missingKeyMsg) + '</p>' +
      '</div>'
    );
  }

  function initLocationMapAndAutocomplete() {
    var c = cfg();

    var $mapEl = $(c.map);
    var $addr = $(c.address);

    if (!$mapEl.length || !$addr.length) return;

    if (!c.hasKey) {
      showMissingKeyNotice();
      return;
    }

    if (!hasGooglePlaces()) return;

    // Evitar dupla inicialização do mapa no mesmo elemento (muito comum no WP admin com metaboxes)
    var mapEl = $mapEl[0];
    if (mapEl && mapEl.dataset && mapEl.dataset.twtMapInit === '1') {
      return;
    }
    if (mapEl && mapEl.dataset) {
      mapEl.dataset.twtMapInit = '1';
    }

    var lat = num($(c.lat).val());
    var lng = num($(c.lng).val());

    var fallbackCenter = { lat: 38.7223, lng: -9.1393 }; // Lisboa
    var center = (lat !== null && lng !== null) ? { lat: lat, lng: lng } : fallbackCenter;

    var map = new google.maps.Map(mapEl, {
      center: center,
      zoom: (lat !== null && lng !== null) ? 16 : 12,
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false
    });

    var marker = new google.maps.Marker({
      position: center,
      map: map,
      draggable: true
    });

    var circle = new google.maps.Circle({
      map: map,
      radius: getRadius(),
      center: center,
      fillOpacity: 0.12,
      strokeOpacity: 0.35,
      strokeWeight: 2
    });

    function syncUIFromLatLng(latLng) {
      var la = latLng.lat();
      var ln = latLng.lng();

      setVal(c.lat, la);
      setVal(c.lng, ln);

      setText(c.latRead, la.toFixed(6));
      setText(c.lngRead, ln.toFixed(6));
    }

    syncUIFromLatLng(marker.getPosition());
    setText(c.placeRead, $(c.placeId).val() || '-');

    marker.addListener('dragend', function () {
      var pos = marker.getPosition();
      circle.setCenter(pos);
      map.panTo(pos);
      syncUIFromLatLng(pos);

      setVal(c.placeId, '');
      setText(c.placeRead, '-');
    });

    $(c.radius).on('input change', function () {
      circle.setRadius(getRadius());
    });

    // Evitar dupla inicialização no mesmo input
    var addrEl = $addr[0];
    if (!addrEl.dataset.twtAcInit) {
      addrEl.dataset.twtAcInit = '1';

      var ac = new google.maps.places.Autocomplete(addrEl, {
        types: ['geocode']
      });

      if (typeof ac.setFields === 'function') {
        ac.setFields(['place_id', 'geometry', 'formatted_address', 'name']);
      }

      ac.addListener('place_changed', function () {
        var place = ac.getPlace();
        if (!place || !place.geometry || !place.geometry.location) return;

        var loc = place.geometry.location;

        if (place.place_id) {
          setVal(c.placeId, place.place_id);
          setText(c.placeRead, place.place_id);
        } else {
          setVal(c.placeId, '');
          setText(c.placeRead, '-');
        }

        marker.setPosition(loc);
        circle.setCenter(loc);

        map.setCenter(loc);
        map.setZoom(16);

        syncUIFromLatLng(loc);

        if (place.formatted_address) {
          $addr.val(place.formatted_address);
        }
      });
    }

    if (navigator.geolocation && (lat === null || lng === null)) {
      navigator.geolocation.getCurrentPosition(function (pos) {
        if (!pos || !pos.coords) return;
        var p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        map.setCenter(p);
        map.setZoom(13);
        marker.setPosition(p);
        circle.setCenter(p);
        syncUIFromLatLng(marker.getPosition());
      }, function () {
        // ignora
      }, { timeout: 3000 });
    }
  }

  function bootWithRetry() {
    var tries = 0;
    function tick() {
      tries++;
      initLocationMapAndAutocomplete();

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
    if (!$(c.map).length) return;

    if (!c.hasKey) {
      showMissingKeyNotice();
      return;
    }

    if (hasGooglePlaces()) {
      initLocationMapAndAutocomplete();
    } else {
      bootWithRetry();
    }
  });

})(jQuery);