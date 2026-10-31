(function () {
    'use strict';

    const cfg = window.opencomuneEsperienzaItineraryMap || {};
    const strings = cfg.strings || {};
    const showLabel = strings.showMap || 'Visualizza su mappa';
    const hideLabel = strings.hideMap || 'Nascondi mappa';

    const mapInstances = {};

    function resolveCoords(latRaw, lngRaw, address, callback) {
        let lat = parseFloat(latRaw);
        let lng = parseFloat(lngRaw);
        if (!Number.isNaN(lat) && !Number.isNaN(lng) && (lat !== 0 || lng !== 0)) {
            callback(lat, lng);
            return;
        }
        const query = (address || '').trim();
        if (!query) {
            callback(null, null);
            return;
        }
        if (typeof google !== 'undefined' && google.maps && google.maps.Geocoder) {
            const geocoder = new google.maps.Geocoder();
            geocoder.geocode({ address: query }, function (results, status) {
                if (status === 'OK' && results && results[0] && results[0].geometry) {
                    const loc = results[0].geometry.location;
                    callback(loc.lat(), loc.lng());
                    return;
                }
                callback(null, null);
            });
            return;
        }
        callback(null, null);
    }

    function initLeafletMap(mapId, lat, lng, popupHtml) {
        if (typeof L === 'undefined') {
            return null;
        }
        if (mapInstances[mapId]) {
            const existing = mapInstances[mapId];
            window.setTimeout(function () {
                existing.invalidateSize();
            }, 80);
            return existing;
        }
        const el = document.getElementById(mapId);
        if (!el) {
            return null;
        }
        const map = L.map(mapId).setView([lat, lng], 15);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 19,
        }).addTo(map);
        const marker = L.marker([lat, lng]).addTo(map);
        if (popupHtml) {
            marker.bindPopup(popupHtml);
        }
        mapInstances[mapId] = map;
        window.setTimeout(function () {
            map.invalidateSize();
        }, 80);
        return map;
    }

    function setToggleLabel(btn, open) {
        btn.textContent = open ? hideLabel : showLabel;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    document.querySelectorAll('[data-itinerary-map-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const mapId = btn.getAttribute('data-map-id');
            const panel = mapId ? document.getElementById(mapId + '-panel') : null;
            if (!panel) {
                return;
            }
            const isOpen = !panel.hidden;
            if (isOpen) {
                panel.hidden = true;
                setToggleLabel(btn, false);
                return;
            }
            panel.hidden = false;
            setToggleLabel(btn, true);

            const lat = btn.getAttribute('data-lat');
            const lng = btn.getAttribute('data-lng');
            const address = btn.getAttribute('data-address') || '';
            const title = btn.getAttribute('data-step-title') || '';
            const popupHtml =
                '<div style="padding:8px;"><strong>' +
                title.replace(/</g, '&lt;') +
                '</strong>' +
                (address ? '<br>' + address.replace(/</g, '&lt;') : '') +
                '</div>';

            resolveCoords(lat, lng, address, function (resolvedLat, resolvedLng) {
                if (resolvedLat === null || resolvedLng === null) {
                    return;
                }
                initLeafletMap(mapId, resolvedLat, resolvedLng, popupHtml);
            });
        });
    });
})();
