(function () {
  'use strict';

  function getCfg() {
    var cfg = window.TWT_TCRM_LOC || {};
    var sel = (cfg.selectors || {});
    return {
      addressId: (sel.address || '#twt_location_address').replace('#', ''),
      latId: (sel.lat || '#twt_location_lat').replace('#', ''),
      lngId: (sel.lng || '#twt_location_lng').replace('#', ''),
      placeId: (sel.placeId || '#twt_location_place_id').replace('#', '')
    };
  }

  function byId(id) { return document.getElementById(id); }

  function hasPlaces() {
    return !!(window.google && google.maps && google.maps.places && google.maps.places.Autocomplete);
  }

  function initAutocomplete() {
    var cfg = getCfg();

    var input = byId(cfg.addressId);
    if (!input) return;

    if (!hasPlaces()) {
      return;
    }

    // Evita inicializar 2 vezes no mesmo input
    if (input.dataset.twtGmapsInit === '1') return;
    input.dataset.twtGmapsInit = '1';

    var ac = new google.maps.places.Autocomplete(input, {
      // Melhor compatibilidade: usa 'geocode' para moradas e locais gerais
      // Se quiseres lojas com nome, troca para ['establishment'] ou dá opção no BO
      types: ['geocode']
    });

    // Alguns ambientes não aceitam fields no constructor, então fazemos via setFields se existir
    if (typeof ac.setFields === 'function') {
      ac.setFields(['place_id', 'formatted_address', 'geometry', 'name']);
    }

    ac.addListener('place_changed', function () {
      var place = ac.getPlace();
      if (!place) return;

      var latEl = byId(cfg.latId);
      var lngEl = byId(cfg.lngId);
      var pidEl = byId(cfg.placeId);

      if (place.formatted_address) input.value = place.formatted_address;
      if (pidEl && place.place_id) pidEl.value = place.place_id;

      if (place.geometry && place.geometry.location) {
        var lat = place.geometry.location.lat();
        var lng = place.geometry.location.lng();
        if (latEl) latEl.value = String(lat);
        if (lngEl) lngEl.value = String(lng);
      }
    });
  }

  function retryInit(maxTries, delayMs) {
    var tries = 0;

    function tick() {
      tries++;
      if (hasPlaces()) {
        initAutocomplete();
        return;
      }
      if (tries < maxTries) {
        setTimeout(tick, delayMs);
        return;
      }
      console.warn('Google Maps Places não está carregado. Verifica: key, referrers, e Places API activa.');
    }

    tick();
  }

  // Callback recomendado: coloca no URL do Google Maps ?callback=twtTcrmInitGmaps
  window.twtTcrmInitGmaps = function () {
    initAutocomplete();
  };

  // Fallback: tenta depois do DOM e faz retry curto
  function boot() {
    if (hasPlaces()) {
      initAutocomplete();
    } else {
      retryInit(20, 250); // 5s no total
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();