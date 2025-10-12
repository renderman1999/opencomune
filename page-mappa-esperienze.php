<?php
/* Template Name: Mappa Esperienze */
get_header();
?>
<div class="md:hidden w-full flex items-center px-4 pt-4 pb-2 justify-between">
  <button id="btn-tutto-mobile" class="bg-blue-600 text-white px-3 py-1 rounded active" type="button">Tutto</button>
  <button id="btn-vicino-mobile" class="bg-gray-200 text-gray-800 px-3 py-1 rounded" type="button">Vicino a me</button>
  <button id="mobile-filter-toggle" class="ml-auto text-2xl p-2" aria-label="Filtri"><i class="bi bi-funnel"></i></button>
  <div id="vicino-loader-mobile" class="ml-2 hidden"><span class="inline-block animate-spin border-2 border-blue-600 border-t-transparent rounded-full w-5 h-5 align-middle"></span></div>
</div>
<div id="tour-map-container" class="flex min-h-screen bg-gray-50">
    <!-- MOBILE FILTER BAR (sempre visibile su mobile, fuori dal container mappa) -->

<!-- MOBILE FILTER PANEL (slide-down, solo mobile, fuori dal container mappa) -->
<style>
#mobile-filter-panel.closed {
  max-height: 0 !important;
  opacity: 0;
  pointer-events: none;
  transition: max-height 0.3s, opacity 0.3s;
}
#mobile-filter-panel.open {
  max-height: 600px !important;
  opacity: 1;
  pointer-events: auto;
  transition: max-height 0.3s, opacity 0.3s;
}
</style>
<div id="mobile-filter-panel" class="md:hidden fixed left-0 right-0 top-[60px] bg-white shadow-lg rounded-b-xl px-4 py-4 z-50 closed" style="top: 90px !important;">
  <div class="mb-4">
    <label class="block font-semibold mb-1">Categoria</label>
    <select id="filter-categoria-mobile" class="w-full border rounded p-2">
      <option value="">Tutte</option>
    </select>
  </div>
  <div class="mb-4">
    <label class="block font-semibold mb-1">Lingua</label>
    <select id="filter-lingua-mobile" class="w-full border rounded p-2">
      <option value="">Tutte</option>
    </select>
  </div>
  <div class="flex gap-2">
    <button id="mobile-apply-filters" class="bg-blue-600 text-white px-4 py-2 rounded w-full">Applica</button>
    <button id="reset-filtri-mobile" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded w-full">Reset</button>
  </div>
</div>
  <!-- SIDEBAR DESKTOP -->
  <aside id="tour-map-sidebar" class="w-[350px] bg-white border-r border-gray-200 p-8 overflow-y-auto max-h-[90vh] hidden md:block">
    <div class="mb-4 flex gap-2" id="vicino-tutto-group-desktop">
      <button id="btn-tutto-desktop" class="bg-blue-600 text-white px-3 py-1 rounded active" type="button">Tutto</button>
      <button id="btn-vicino-desktop" class="bg-gray-200 text-gray-800 px-3 py-1 rounded" type="button">Vicino a me</button>
      <div id="vicino-loader-desktop" class="ml-2 hidden"><span class="inline-block animate-spin border-2 border-blue-600 border-t-transparent rounded-full w-5 h-5 align-middle"></span></div>
    </div>
    <h2 class="text-xl font-bold mb-4">Filtra le esperienze</h2>
    <div class="mb-4">
      <label class="block font-semibold mb-1">Categoria</label>
      <select id="filter-categoria-desktop" class="w-full border rounded p-2">
        <option value="">Tutte</option>
      </select>
    </div>
    <div class="mb-4">
      <label class="block font-semibold mb-1">Lingua</label>
      <select id="filter-lingua-desktop" class="w-full border rounded p-2">
        <option value="">Tutte</option>
      </select>
    </div>
    <button id="reset-filtri-desktop" class="mt-4 bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Reset</button>
    <div id="tour-list" class="mt-8"></div>
  </aside>

  <div id="tour-map" class="flex-1 min-h-[80vh]"></div>
</div>

<!-- Modal dettagli tour -->
<div id="tour-modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
  <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4 p-6 relative">
    <button id="close-modal" class="absolute top-2 right-2 text-gray-500 hover:text-red-600 text-2xl">&times;</button>
    <div id="modal-content"></div>
  </div>
</div>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr(opencomune_get_google_maps_api_key()); ?>"></script>
<script>
const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
const isMobile = () => window.innerWidth < 900;
let allTours = [];
let map, markers = [], infoWindow;
let filterVicino = false;
let userPosition = null;

// Includi Select2 se non già incluso
if (!window.jQuery) {
  var jq = document.createElement('script');
  jq.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
  document.head.appendChild(jq);
}
var select2css = document.createElement('link');
select2css.rel = 'stylesheet';
select2css.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
document.head.appendChild(select2css);
var select2js = document.createElement('script');
select2js.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
document.head.appendChild(select2js);

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

function createMarker(tour) {
  const marker = new google.maps.Marker({
    position: { lat: parseFloat(tour.lat), lng: parseFloat(tour.lon) },
    map,
    title: tour.titolo,
    optimized: false,
    zIndex: 1
  });
  marker.addListener('click', () => openTourModal(tour));
  markers.push(marker);
}

function openTourModal(tour) {
  const modal = document.getElementById('tour-modal');
  const content = document.getElementById('modal-content');
  content.innerHTML = `
    <div class="flex gap-4 items-center mb-4">
      <img src="${tour.tour_img || 'https://via.placeholder.com/80x80?text=Tour'}" class="rounded-full object-cover shadow w-20 h-20">
      <div>
        <h3 class="text-xl font-bold mb-1">${tour.titolo}</h3>
        <div class="text-gray-600 text-sm mb-1">${tour.citta || ''}</div>
        <div class="text-gray-500 text-xs">${tour.categoria || ''} &middot; ${tour.lingue || ''}</div>
      </div>
    </div>
    <div class="mb-2">${tour.desc_breve || ''}</div>
    <a href="${tour.link}" class="inline-block mt-2 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700" target="_blank">Vai al dettaglio</a>
  `;
  modal.classList.remove('hidden');
}

document.getElementById('close-modal').onclick = () => document.getElementById('tour-modal').classList.add('hidden');
document.getElementById('tour-modal').onclick = e => { if (e.target === e.currentTarget) document.getElementById('tour-modal').classList.add('hidden'); };

function renderSidebar(tours) {
  const langSet = new Set();
  tours.forEach(t => {
    if (t.lingue) {
      const lingueStr = Array.isArray(t.lingue) ? t.lingue.join(',') : t.lingue;
      lingueStr.split(',').forEach(l => langSet.add(l.trim()));
    }
  });
  
  // Carica le categorie delle esperienze dalla tassonomia
  loadCategorieEsperienze().then(categorie => {
    // Aggiorna categorie
    document.getElementById('filter-categoria-desktop').innerHTML = '<option value="">Tutte</option>' + categorie.map(c => `<option value="${c.slug}">${c.name}</option>`).join('');
    document.getElementById('filter-categoria-mobile').innerHTML = '<option value="">Tutte</option>' + categorie.map(c => `<option value="${c.slug}">${c.name}</option>`).join('');
  });
  
  // Aggiorna lingue
  document.getElementById('filter-lingua-desktop').innerHTML = '<option value="">Tutte</option>' + Array.from(langSet).map(l => `<option value="${l}">${l}</option>`).join('');
  document.getElementById('filter-lingua-mobile').innerHTML = '<option value="">Tutte</option>' + Array.from(langSet).map(l => `<option value="${l}">${l}</option>`).join('');
  
  // Lista esperienze solo su desktop
  const list = document.getElementById('tour-list');
  if (list) {
    list.innerHTML = tours.map(t => `
      <div class="mb-4 p-2 border-b flex gap-3 items-center cursor-pointer hover:bg-gray-50" onclick="focusTour(${t.id})">
        <img src="${t.tour_img || 'https://via.placeholder.com/40x40?text=Esperienza'}" class="rounded-full object-cover shadow w-12 h-12">
        <div>
          <div class="font-semibold">${t.titolo}</div>
          <div class="text-xs text-gray-500">${t.categoria || ''}</div>
        </div>
      </div>
    `).join('');
  }
}

window.focusTour = function(id) {
  const t = allTours.find(t => t.id == id);
  if (t) {
    map.panTo({lat: parseFloat(t.lat), lng: parseFloat(t.lon)});
    openTourModal(t);
  }
}

// --- Gestione filtri desktop ---
document.getElementById('filter-categoria-desktop').addEventListener('change', applyFilters);
document.getElementById('filter-lingua-desktop').addEventListener('change', applyFilters);
document.getElementById('reset-filtri-desktop').addEventListener('click', function() {
  document.getElementById('filter-categoria-desktop').value = '';
  document.getElementById('filter-lingua-desktop').value = '';
  applyFilters();
});

document.getElementById('btn-tutto-desktop').onclick = function() {
  filterVicino = false;
  this.classList.add('bg-blue-600','text-white','active');
  document.getElementById('btn-vicino-desktop').classList.remove('bg-blue-600','text-white','active');
  applyFilters();
};
document.getElementById('btn-vicino-desktop').onclick = function() {
  const loader = document.getElementById('vicino-loader-desktop');
  loader.classList.remove('hidden');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      loader.classList.add('hidden');
      userPosition = {lat: pos.coords.latitude, lon: pos.coords.longitude};
      filterVicino = true;
      document.getElementById('btn-vicino-desktop').classList.add('bg-blue-600','text-white','active');
      document.getElementById('btn-tutto-desktop').classList.remove('bg-blue-600','text-white','active');
      applyFilters();
    }, function() {
      loader.classList.add('hidden');
      alert('Impossibile ottenere la posizione.');
    });
  } else {
    loader.classList.add('hidden');
    alert('Geolocalizzazione non supportata dal browser.');
  }
};

// --- Gestione filtri mobile ---
const mobileFilterPanel = document.getElementById('mobile-filter-panel');
document.getElementById('mobile-apply-filters').onclick = function() {
  applyFilters();
  mobileFilterPanel.classList.remove('open');
  mobileFilterPanel.classList.add('closed');
};
document.getElementById('reset-filtri-mobile').onclick = function() {
  document.getElementById('filter-categoria-mobile').value = '';
  document.getElementById('filter-lingua-mobile').value = '';
};
document.getElementById('btn-tutto-mobile').onclick = function() {
  filterVicino = false;
  this.classList.add('bg-blue-600','text-white','active');
  document.getElementById('btn-vicino-mobile').classList.remove('bg-blue-600','text-white','active');
  applyFilters();
};
document.getElementById('btn-vicino-mobile').onclick = function() {
  const loader = document.getElementById('vicino-loader-mobile');
  loader.classList.remove('hidden');
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
      loader.classList.add('hidden');
      userPosition = {lat: pos.coords.latitude, lon: pos.coords.longitude};
      filterVicino = true;
      document.getElementById('btn-vicino-mobile').classList.add('bg-blue-600','text-white','active');
      document.getElementById('btn-tutto-mobile').classList.remove('bg-blue-600','text-white','active');
      applyFilters();
    }, function() {
      loader.classList.add('hidden');
      alert('Impossibile ottenere la posizione.');
    });
  } else {
    loader.classList.add('hidden');
    alert('Geolocalizzazione non supportata dal browser.');
  }
};
document.getElementById('mobile-filter-toggle').onclick = function() {
  if (mobileFilterPanel.classList.contains('closed')) {
    mobileFilterPanel.classList.remove('closed');
    mobileFilterPanel.classList.add('open');
  } else {
    mobileFilterPanel.classList.remove('open');
    mobileFilterPanel.classList.add('closed');
  }
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

function applyFilters() {
  let filtered = allTours;
  const cat = isMobile() ? document.getElementById('filter-categoria-mobile').value : document.getElementById('filter-categoria-desktop').value;
  const lang = isMobile() ? document.getElementById('filter-lingua-mobile').value : document.getElementById('filter-lingua-desktop').value;
  
  if (cat) {
    filtered = filtered.filter(t => {
      // Controlla se l'esperienza ha la categoria selezionata
      const categorie = t.categorie_array || [];
      return categorie.some(c => c.trim() === cat);
    });
  }
  
  if (lang) {
    filtered = filtered.filter(t => {
      const lingueStr = Array.isArray(t.lingue) ? t.lingue.join(',') : (t.lingue || '');
      return lingueStr.split(',').map(x=>x.trim()).includes(lang);
    });
  }
  
  if (filterVicino && userPosition) {
    filtered = filtered.filter(t => {
      if (!t.lat || !t.lon) return false;
      return distanceKm(userPosition.lat, userPosition.lon, parseFloat(t.lat), parseFloat(t.lon)) <= 20;
    });
  }
  
  renderSidebar(filtered);
  markers.forEach(m => m.setMap(null));
  markers = [];
  filtered.forEach(createMarker);
  if (filtered.length > 0) {
    const bounds = new google.maps.LatLngBounds();
    filtered.forEach(t => {
      if (t.lat && t.lon) bounds.extend(new google.maps.LatLng(parseFloat(t.lat), parseFloat(t.lon)));
    });
    map.fitBounds(bounds);
  }
}

function initMap() {
  map = new google.maps.Map(document.getElementById('tour-map'), {
    center: { lat: 43.7696, lng: 11.2558 },
    zoom: 6,
    mapTypeControl: false,
    streetViewControl: false,
    fullscreenControl: false,
    zoomControl: true
  });
  infoWindow = new google.maps.InfoWindow();
  fetchTours().then(tours => {
    allTours = tours;
    renderSidebar(tours);
    tours.forEach(createMarker);
    // Centra la mappa su tutti i marker trovati
    if (tours.length > 0) {
      const bounds = new google.maps.LatLngBounds();
      tours.forEach(t => {
        if (t.lat && t.lon) bounds.extend(new google.maps.LatLng(parseFloat(t.lat), parseFloat(t.lon)));
      });
      map.fitBounds(bounds);
    }
  });
}

window.initMap = initMap;
window.onload = () => {
  if (typeof google !== 'undefined' && google.maps) initMap();
  else {
    const s = document.createElement('script');
    s.src = 'https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr(opencomune_get_google_maps_api_key()); ?>&callback=initMap';
    document.body.appendChild(s);
  }
};
</script>
<?php get_footer(); ?> 