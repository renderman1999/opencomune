(function () {
  'use strict';

  const cfg = window.opencomuneHomeMap || {};

  function initHomeMap() {
    const el = document.getElementById('tours-map');
    if (!el) {
      return;
    }

    if (!cfg.mapsReady || typeof google === 'undefined' || !google.maps) {
      return;
    }

    const map = new google.maps.Map(el, {
      center: cfg.center || { lat: 41.8719, lng: 12.5674 },
      zoom: cfg.zoom || 6,
      gestureHandling: 'cooperative',
      mapTypeControl: false,
      streetViewControl: false,
      fullscreenControl: false,
      zoomControl: true,
      clickableIcons: false,
    });

    const bounds = new google.maps.LatLngBounds();
    const items = Array.isArray(cfg.markers) ? cfg.markers : [];

    items.forEach(function (item) {
      const lat = parseFloat(item.lat);
      const lng = parseFloat(item.lng);
      if (Number.isNaN(lat) || Number.isNaN(lng)) {
        return;
      }

      const position = { lat, lng };
      bounds.extend(position);

      new google.maps.Marker({
        map: map,
        position: position,
        title: item.title || '',
      });
    });

    if (items.length > 0 && bounds && typeof bounds.isEmpty === 'function' && !bounds.isEmpty()) {
      map.fitBounds(bounds, 48);
    }
  }

  function bootstrapHomeMap() {
    if (cfg.mapsReady && !window.opencomuneHomeMapMapsLoaded) {
      document.addEventListener('opencomune-home-map-ready', initHomeMap, { once: true });
      return;
    }
    initHomeMap();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrapHomeMap);
  } else {
    bootstrapHomeMap();
  }
})();
