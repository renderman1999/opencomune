(function () {
    const form = document.getElementById('partnerRegistrationForm');
    if (!form) {
        return;
    }

    let map = null;
    let marker = null;

    function getCoords() {
        const latInput = document.getElementById('latitudine');
        const lngInput = document.getElementById('longitudine');
        const lat = parseFloat(latInput?.value || '');
        const lng = parseFloat(lngInput?.value || '');
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return null;
        }
        return { lat, lng };
    }

    function setMarkerPosition(lat, lng) {
        if (!map || typeof L === 'undefined') {
            return;
        }
        const position = [lat, lng];
        map.setView(position, 16);
        if (marker) {
            marker.setLatLng(position);
        } else {
            marker = L.marker(position).addTo(map);
        }
    }

    function initMap() {
        const mapEl = document.getElementById('map');
        if (!mapEl || map || typeof L === 'undefined') {
            return;
        }

        const coords = getCoords();
        const center = coords ? [coords.lat, coords.lng] : [40.8195, 16.4214];
        const zoom = coords ? 16 : 8;

        map = L.map('map').setView(center, zoom);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        }).addTo(map);

        if (coords) {
            marker = L.marker(center).addTo(map);
        }
    }

    document.addEventListener('opencomune-partner-address-coords', function (e) {
        const detail = e.detail || {};
        const lat = parseFloat(detail.lat);
        const lng = parseFloat(detail.lng);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }
        if (!map) {
            initMap();
        }
        setMarkerPosition(lat, lng);
    });

    document.addEventListener('opencomune-partner-registration-step', function (e) {
        if (!e.detail || e.detail.step !== 2) {
            return;
        }
        window.setTimeout(function () {
            initMap();
            const coords = getCoords();
            if (coords) {
                setMarkerPosition(coords.lat, coords.lng);
            }
            if (map) {
                map.invalidateSize();
            }
        }, 120);
    });
})();
