(function () {
  'use strict';

  const cfg = window.opencomunePublicMap || {};
  const ajaxurl = cfg.ajaxUrl || '';
const isMobile = () => window.innerWidth < 900;
let allTours = [];
let visibleTours = [];
let map;
let markers = [];
let filterVicino = false;
let userPosition = null;

function setFilterPillActive(activeBtn, inactiveBtn) {
  if (activeBtn) {
    activeBtn.classList.add('is-active');
  }
  if (inactiveBtn) {
    inactiveBtn.classList.remove('is-active');
  }
}

function setLoaderVisible(loaderEl, visible) {
  if (!loaderEl) {
    return;
  }
  loaderEl.classList.toggle('is-hidden', !visible);
}

// Carica le categorie delle esperienze dalla tassonomia WordPress
function loadCategorieEsperienze() {
  return fetch(ajaxurl + '?action=opencomune_get_categorie_esperienze')
    .then(r => r.json())
    .then(data => data.success ? data.data : []);
}

function fetchTours() {
  return fetch(ajaxurl + '?action=opencomune_get_all_tours')
    .then(r => r.json())
    .then(data => data.success ? data.data : []);
}

const MARKER_SIZE = 48;

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function decodeHtmlEntities(value) {
  if (value === null || value === undefined || value === '') {
    return '';
  }

  const textarea = document.createElement('textarea');
  textarea.innerHTML = String(value);
  return textarea.value;
}

function formatDisplayText(value) {
  const decoded = decodeHtmlEntities(value);
  const plain = decoded.replace(/<[^>]*>/g, '');
  return escapeHtml(plain);
}

let activePopup = null;
let mapClickListener = null;

function closeActivePopup() {
  if (activePopup) {
    activePopup.setMap(null);
    activePopup = null;
  }
}

function createExperienceMarkerOverlay(tour, position, onSelect) {
  function MarkerOverlay() {
    this.position = position;
    this.tour = tour;
    this.div = null;
    this.listeners = [];
  }

  MarkerOverlay.prototype = new google.maps.OverlayView();

  MarkerOverlay.prototype.onAdd = function () {
    const div = document.createElement('div');
    div.className = 'oc-gmap-experience-marker map-experience-marker';
    div.setAttribute('role', 'button');
    div.setAttribute('tabindex', '0');
    div.title = decodeHtmlEntities(tour.titolo || '');

    if (tour.tour_img) {
      const img = document.createElement('div');
      img.className = 'map-experience-marker-img';
      img.setAttribute('aria-hidden', 'true');
      img.style.backgroundImage = 'url("' + String(tour.tour_img).replace(/"/g, '\\"') + '")';
      div.appendChild(img);
    } else {
      div.innerHTML =
        '<div class="map-experience-marker-placeholder" aria-hidden="true"><i class="bi bi-images" aria-hidden="true"></i></div>';
    }

    const activate = (event) => {
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      onSelect(this);
    };

    div.addEventListener('click', activate);
    div.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        activate(event);
      }
    });

    this.div = div;
    this.getPanes().overlayMouseTarget.appendChild(div);
  };

  MarkerOverlay.prototype.draw = function () {
    const projection = this.getProjection();
    if (!projection || !this.div) {
      return;
    }
    const point = projection.fromLatLngToDivPixel(
      new google.maps.LatLng(this.position.lat, this.position.lng)
    );
    if (!point) {
      return;
    }
    const anchor = MARKER_SIZE / 2;
    this.div.style.left = Math.round(point.x - anchor) + 'px';
    this.div.style.top = Math.round(point.y - anchor) + 'px';
  };

  MarkerOverlay.prototype.onRemove = function () {
    if (this.div) {
      this.div.remove();
      this.div = null;
    }
  };

  return new MarkerOverlay();
}

function createExperiencePopupOverlay(position, html) {
  function PopupOverlay() {
    this.position = position;
    this.html = html;
    this.div = null;
    this.listeners = [];
  }

  PopupOverlay.prototype = new google.maps.OverlayView();

  PopupOverlay.prototype.onAdd = function () {
    const wrap = document.createElement('div');
    wrap.className = 'oc-gmap-experience-popup oc-map-esperienza-popup';
    wrap.innerHTML =
      '<button type="button" class="oc-gmap-experience-popup__close" aria-label="Chiudi">&times;</button>' +
      '<div class="oc-gmap-experience-popup__body">' +
      html +
      '</div>' +
      '<div class="oc-gmap-experience-popup__tip" aria-hidden="true"></div>';

    wrap.querySelector('.oc-gmap-experience-popup__close')?.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      closeActivePopup();
    });

    wrap.addEventListener('click', (event) => {
      event.stopPropagation();
    });

    this.div = wrap;
    this.getPanes().floatPane.appendChild(wrap);

    const redraw = () => this.draw();
    this.listeners.push(google.maps.event.addListener(this.getMap(), 'idle', redraw));
    this.listeners.push(google.maps.event.addListener(this.getMap(), 'zoom_changed', redraw));
    this.listeners.push(google.maps.event.addListener(this.getMap(), 'dragend', redraw));

    wrap.querySelectorAll('img').forEach((img) => {
      img.addEventListener('load', redraw);
    });
  };

  PopupOverlay.prototype.draw = function () {
    const projection = this.getProjection();
    if (!projection || !this.div) {
      return;
    }
    const point = projection.fromLatLngToDivPixel(
      new google.maps.LatLng(this.position.lat, this.position.lng)
    );
    if (!point) {
      return;
    }
    const width = this.div.offsetWidth || 340;
    const height = this.div.offsetHeight || 200;
    const anchor = MARKER_SIZE / 2;
    this.div.style.left = Math.round(point.x - width / 2) + 'px';
    this.div.style.top = Math.round(point.y - anchor - height - 8) + 'px';
  };

  PopupOverlay.prototype.onRemove = function () {
    this.listeners.forEach((listener) => google.maps.event.removeListener(listener));
    this.listeners = [];
    if (this.div) {
      this.div.remove();
      this.div = null;
    }
  };

  return new PopupOverlay();
}

function openTourPopup(tour, position) {
  closeActivePopup();
  if (!tour.popup_html) {
    openTourModal(tour);
    return;
  }
  activePopup = createExperiencePopupOverlay(position, tour.popup_html);
  activePopup.setMap(map);
  window.setTimeout(() => {
    if (activePopup) {
      activePopup.draw();
    }
  }, 0);
  google.maps.event.addListenerOnce(map, 'idle', () => {
    if (activePopup) {
      activePopup.draw();
    }
  });
}

function selectTourMarker(markerOverlay) {
  const tour = markerOverlay.tour;
  const position = markerOverlay.position;
  map.panTo(position);
  if (map.getZoom() < 12) {
    map.setZoom(12);
  }
  if (tour.popup_html) {
    openTourPopup(tour, position);
  } else {
    openTourModal(tour);
  }
}

function renderTourCover(tour, sizeRem) {
  if (tour.tour_img) {
    return `<img src="${escapeHtml(tour.tour_img)}" alt="" style="width:${sizeRem};height:${sizeRem};border-radius:9999px;object-fit:cover;box-shadow:0 2px 8px rgba(0,0,0,0.15);">`;
  }

  return `<div style="width:${sizeRem};height:${sizeRem};border-radius:9999px;background:#f3f4f6;color:#9ca3af;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,0.15);"><i class="bi bi-images" style="font-size:2rem;line-height:1;" aria-hidden="true"></i></div>`;
}

function createMarker(tour) {
  const lat = parseFloat(tour.lat);
  const lng = parseFloat(tour.lon);
  if (Number.isNaN(lat) || Number.isNaN(lng)) {
    return;
  }

  const position = { lat, lng };
  const overlay = createExperienceMarkerOverlay(tour, position, selectTourMarker);
  overlay.setMap(map);
  markers.push(overlay);
}

function closeTourModal() {
  const modal = document.getElementById('tour-modal');
  if (!modal) {
    return;
  }
  modal.classList.add('is-hidden');
  modal.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

function openTourModal(tour) {
  const modal = document.getElementById('tour-modal');
  const content = document.getElementById('modal-content');
  if (!modal || !content || !tour) {
    return;
  }

  const title = formatDisplayText(tour.titolo || '');
  const city = formatDisplayText(tour.citta || '');
  const datesLabel = formatDisplayText(tour.dates_label || '');
  const category = formatDisplayText(tour.categoria || '');
  const languages = formatDisplayText(tour.lingue || '');
  const description = formatDisplayText(tour.desc_breve || '');
  const link = escapeHtml(tour.link || '#');

  content.innerHTML = `
    <div style="display:flex;gap:1rem;align-items:center;margin-bottom:1rem;">
      ${renderTourCover(tour, '5rem')}
      <div>
        <h3 id="tour-modal-title" style="font-size:1.25rem;font-weight:700;margin:0 0 0.25rem;line-height:1.3;">${title}</h3>
        <div style="color:#4b5563;font-size:0.875rem;margin-bottom:0.25rem;">${city}</div>
        ${datesLabel ? `<div style="color:var(--link-accent,#b57a00);font-size:0.875rem;font-weight:600;margin-bottom:0.25rem;"><i class="bi bi-calendar-event" aria-hidden="true"></i> ${datesLabel}</div>` : ''}
        <div style="color:#6b7280;font-size:0.75rem;">${category}${languages ? ' &middot; ' + languages : ''}</div>
      </div>
    </div>
    <div style="color:#374151;font-size:0.95rem;line-height:1.5;margin-bottom:1rem;">${description}</div>
    <a href="${link}" class="oc-public-map__modal-cta" target="_blank" rel="noopener noreferrer">Vai al dettaglio</a>
  `;

  modal.classList.remove('is-hidden');
  modal.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
}

document.getElementById('close-modal').onclick = closeTourModal;
document.getElementById('tour-modal').onclick = e => {
  if (e.target === e.currentTarget) {
    closeTourModal();
  }
};
document.querySelector('#tour-modal .tour-modal-panel')?.addEventListener('click', e => {
  e.stopPropagation();
});
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    const modal = document.getElementById('tour-modal');
    if (modal && !modal.classList.contains('is-hidden')) {
      closeTourModal();
    }
  }
});

function initFilterOptions() {
  const selectedCategoriaDesktop = document.getElementById('filter-categoria-desktop').value;
  const selectedCategoriaMobile = document.getElementById('filter-categoria-mobile').value;

  return loadCategorieEsperienze().then(categorie => {
    const options = '<option value="">Tutte</option>' + categorie.map(c => `<option value="${escapeHtml(c.slug)}">${formatDisplayText(c.name)}</option>`).join('');
    document.getElementById('filter-categoria-desktop').innerHTML = options;
    document.getElementById('filter-categoria-mobile').innerHTML = options;
    document.getElementById('filter-categoria-desktop').value = selectedCategoriaDesktop;
    document.getElementById('filter-categoria-mobile').value = selectedCategoriaMobile;
  });
}

function setDesktopFiltersCollapsed(collapsed) {
  const column = document.getElementById('map-filters-column');
  const container = document.getElementById('tour-map-container');
  if (!column || !container) {
    return;
  }

  column.classList.toggle('is-collapsed', collapsed);
  container.classList.toggle('is-filters-collapsed', collapsed);

  if (map && typeof google !== 'undefined' && google.maps) {
    setTimeout(() => google.maps.event.trigger(map, 'resize'), 320);
  }
}

function updateMapResults(tours) {
  if (!map) {
    return;
  }

  visibleTours = Array.isArray(tours) ? tours : [];
  closeActivePopup();
  markers.forEach(m => m.setMap(null));
  markers = [];
  visibleTours.forEach(createMarker);

  if (visibleTours.length > 0) {
    const bounds = new google.maps.LatLngBounds();
    visibleTours.forEach(t => {
      const lat = parseFloat(t.lat);
      const lng = parseFloat(t.lon);
      if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
        bounds.extend({ lat, lng });
      }
    });
    if (!bounds.isEmpty()) {
      map.fitBounds(bounds, 48);
    }
  }
}

// --- Gestione filtri desktop ---
document.getElementById('filter-categoria-desktop').addEventListener('change', applyFilters);
document.getElementById('desktop-filter-close')?.addEventListener('click', () => setDesktopFiltersCollapsed(true));
document.getElementById('desktop-filter-open')?.addEventListener('click', () => setDesktopFiltersCollapsed(false));
document.getElementById('reset-filtri-desktop').addEventListener('click', function() {
  // Reset filtri
  document.getElementById('filter-categoria-desktop').value = '';
  
  // Reset pulsanti "Tutto" e "Vicino"
  setFilterPillActive(document.getElementById('btn-tutto-desktop'), document.getElementById('btn-vicino-desktop'));
  filterVicino = false;
  userPosition = null;
  
  // Reset mappa allo stato originale
  resetMapToOriginal();
});

document.getElementById('btn-tutto-desktop').onclick = function() {
  filterVicino = false;
  setFilterPillActive(this, document.getElementById('btn-vicino-desktop'));
  applyFilters();
};
document.getElementById('btn-vicino-desktop').onclick = function() {
  const loader = document.getElementById('vicino-loader-desktop');
  setLoaderVisible(loader, true);
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      setLoaderVisible(loader, false);
      userPosition = {lat: pos.coords.latitude, lon: pos.coords.longitude};
      filterVicino = true;
      setFilterPillActive(document.getElementById('btn-vicino-desktop'), document.getElementById('btn-tutto-desktop'));
      applyFilters();
    }, function() {
      setLoaderVisible(loader, false);
      alert('Impossibile ottenere la posizione.');
    });
  } else {
    setLoaderVisible(loader, false);
    alert('Geolocalizzazione non supportata dal browser.');
  }
};

// --- Gestione filtri mobile ---
const mobileFilterPanel = document.getElementById('mobile-filter-panel');

// Pulsante chiusura
function setMobileFilterPanelOpen(open) {
  if (!mobileFilterPanel) {
    return;
  }
  mobileFilterPanel.classList.toggle('is-open', open);
}

document.getElementById('mobile-close-filters').onclick = function() {
  setMobileFilterPanelOpen(false);
};

document.getElementById('mobile-apply-filters').onclick = function() {
  applyFilters();
  setMobileFilterPanelOpen(false);
};
document.getElementById('reset-filtri-mobile').onclick = function() {
  // Reset filtri
  document.getElementById('filter-categoria-mobile').value = '';
  
  // Reset pulsanti "Tutto" e "Vicino"
  setFilterPillActive(document.getElementById('btn-tutto-mobile'), document.getElementById('btn-vicino-mobile'));
  filterVicino = false;
  userPosition = null;
  
  // Reset mappa allo stato originale
  resetMapToOriginal();
};
document.getElementById('btn-tutto-mobile').onclick = function() {
  filterVicino = false;
  setFilterPillActive(this, document.getElementById('btn-tutto-mobile'));
  applyFilters();
};
document.getElementById('btn-vicino-mobile').onclick = function() {
  const loader = document.getElementById('vicino-loader-mobile');
  setLoaderVisible(loader, true);
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      setLoaderVisible(loader, false);
      userPosition = {lat: pos.coords.latitude, lon: pos.coords.longitude};
      filterVicino = true;
      setFilterPillActive(document.getElementById('btn-vicino-mobile'), document.getElementById('btn-tutto-mobile'));
      applyFilters();
    }, function() {
      setLoaderVisible(loader, false);
      alert('Impossibile ottenere la posizione.');
    });
  } else {
    setLoaderVisible(loader, false);
    alert('Geolocalizzazione non supportata dal browser.');
  }
};
document.getElementById('mobile-filter-toggle').onclick = function() {
  setMobileFilterPanelOpen(!mobileFilterPanel.classList.contains('is-open'));
};

function distanceKm(lat1, lon1, lat2, lon2) {
  // Haversine formula
  const R = 6371;
  const dLat = (lat2-lat1)*Math.PI/180;
  const dLon = (lon2-lon1)*Math.PI/180;
  const a = Math.sin(dLat/2)*Math.sin(dLat/2) + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLon/2)*Math.sin(dLon/2);
  const c = 2*Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R*c;
}

function resetMapToOriginal() {
  updateMapResults(allTours);
}

function applyFilters() {
  let filtered = allTours;
  const cat = isMobile() ? document.getElementById('filter-categoria-mobile').value : document.getElementById('filter-categoria-desktop').value;
  
  // Filtro categoria: solo se è selezionata una categoria specifica
  if (cat && cat !== '' && cat !== 'Tutte' && cat !== 'tutte') {
    filtered = filtered.filter(t => {
      // Controlla se l'esperienza ha la categoria selezionata (confronta slug con slug)
      const categorie_slugs = t.categorie_slugs || [];
      return categorie_slugs.some(slug => slug.trim() === cat);
    });
  }

  if (filterVicino && userPosition) {
    filtered = filtered.filter(t => {
      if (!t.lat || !t.lon) return false;
      return distanceKm(userPosition.lat, userPosition.lon, parseFloat(t.lat), parseFloat(t.lon)) <= 20;
    });
  }
  
  updateMapResults(filtered);
}

function showMapHint() {
  const hintElement = document.getElementById('two-finger-hint');
  if (!hintElement) {
    return;
  }

  const isMobileViewport = window.innerWidth < 900;
  if (!isMobileViewport) {
    const desktopHint = hintElement.querySelector('.oc-public-map__hint-desktop');
    if (desktopHint && cfg.hintDesktop) {
      desktopHint.textContent = cfg.hintDesktop;
    }
  }

  hintElement.classList.add('is-visible');
  window.setTimeout(() => {
    hintElement.classList.remove('is-visible');
  }, 3000);
}

function initMap() {
  const mapEl = document.getElementById('tour-map');
  if (!mapEl) {
    return;
  }

  if (!cfg.mapsReady || typeof google === 'undefined' || !google.maps) {
    mapEl.innerHTML =
      '<p class="oc-public-map__map-error">' +
      escapeHtml(cfg.mapsKeyMissing || 'Google Maps non disponibile.') +
      '</p>';
    initFilterOptions();
    fetchTours().then(tours => {
      allTours = tours;
    });
    return;
  }

  map = new google.maps.Map(mapEl, {
    center: { lat: 43.7696, lng: 11.2558 },
    zoom: 6,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: true,
    gestureHandling: 'cooperative',
    clickableIcons: false,
  });

  if (mapClickListener) {
    google.maps.event.removeListener(mapClickListener);
  }
  mapClickListener = map.addListener('click', closeActivePopup);

  window.setTimeout(showMapHint, 3000);

  initFilterOptions();
  fetchTours().then(tours => {
    allTours = tours;
    updateMapResults(tours);
  });
}

function bootstrapPublicMap() {
  const modal = document.getElementById('tour-modal');
  if (modal && modal.parentElement !== document.body) {
    document.body.appendChild(modal);
  }

  if (cfg.mapsReady && !window.opencomunePublicMapMapsLoaded) {
    document.addEventListener('opencomune-public-map-ready', initMap, { once: true });
    return;
  }

  initMap();
}

document.addEventListener('DOMContentLoaded', bootstrapPublicMap);
})();