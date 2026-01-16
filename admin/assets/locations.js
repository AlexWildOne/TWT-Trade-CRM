(function($){
  'use strict';

  function num(v){
    var n = parseFloat(v);
    return isNaN(n) ? null : n;
  }

  function setHidden(id, val){
    var $el = $('#' + id);
    if ($el.length) $el.val(val === null || val === undefined ? '' : String(val));
  }

  function setRead(id, val){
    var $el = $('#' + id);
    if ($el.length) $el.text(val === null || val === undefined || val === '' ? '-' : String(val));
  }

  function getRadius(){
    var r = parseInt($('#twt_location_radius_m').val(), 10);
    if (!r || r <= 0) r = 80;
    if (r > 2000) r = 2000;
    return r;
  }

  window.initTwtTcrmLocationMap = function(){
    try {
      if (!window.google || !google.maps || !google.maps.places) return;
    } catch(e){
      return;
    }

    var $mapEl = $('#twt-location-map');
    var $addr = $('#twt_location_address');

    if (!$mapEl.length || !$addr.length) return;

    var lat = num($('#twt_location_lat').val());
    var lng = num($('#twt_location_lng').val());

    var fallbackCenter = { lat: 38.7223, lng: -9.1393 };

    var center = (lat !== null && lng !== null) ? { lat: lat, lng: lng } : fallbackCenter;

    var map = new google.maps.Map($mapEl[0], {
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

    function syncUI(pos){
      setHidden('twt_location_lat', pos.lat());
      setHidden('twt_location_lng', pos.lng());

      setRead('twt-loc-lat-read', pos.lat().toFixed(6));
      setRead('twt-loc-lng-read', pos.lng().toFixed(6));
    }

    syncUI(marker.getPosition());
    setRead('twt-loc-place-read', $('#twt_location_place_id').val() || '-');

    marker.addListener('dragend', function(){
      var pos = marker.getPosition();
      circle.setCenter(pos);
      map.panTo(pos);
      syncUI(pos);

      setHidden('twt_location_place_id', '');
      setRead('twt-loc-place-read', '-');
    });

    $('#twt_location_radius_m').on('input change', function(){
      circle.setRadius(getRadius());
    });

    var ac = new google.maps.places.Autocomplete($addr[0], {
      fields: ['place_id', 'geometry', 'formatted_address', 'name'],
      types: ['geocode']
    });

    ac.addListener('place_changed', function(){
      var place = ac.getPlace();
      if (!place || !place.geometry || !place.geometry.location) return;

      var loc = place.geometry.location;

      setHidden('twt_location_place_id', place.place_id || '');
      setRead('twt-loc-place-read', place.place_id || '-');

      marker.setPosition(loc);
      circle.setCenter(loc);

      map.setCenter(loc);
      map.setZoom(16);

      syncUI(loc);

      if (place.formatted_address) {
        $addr.val(place.formatted_address);
      }
    });

    if (navigator.geolocation && (lat === null || lng === null)) {
      navigator.geolocation.getCurrentPosition(function(pos){
        if (!pos || !pos.coords) return;
        var p = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        map.setCenter(p);
        map.setZoom(13);
        marker.setPosition(p);
        circle.setCenter(p);
        syncUI(marker.getPosition());
      }, function(){
        // ignora
      }, { timeout: 3000 });
    }
  };

  $(function(){
    if (!window.TWT_TCRM_LOC) return;

    if (!TWT_TCRM_LOC.hasKey) {
      var $wrap = $('#twt-location-map').closest('.twt-loc-map-wrap');
      if ($wrap.length) {
        $wrap.prepend('<div class="notice notice-warning" style="margin:0 0 12px 0;"><p>' + (TWT_TCRM_LOC.i18n && TWT_TCRM_LOC.i18n.missingKey ? TWT_TCRM_LOC.i18n.missingKey : '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') + '</p></div>');
      }
      return;
    }
  });

})(jQuery);
