(function () {
    'use strict';

    function init() {
        if (typeof google === 'undefined' || !google.maps) {
            return;
        }
        const mapEl = document.getElementById('oc-planner-admin-map');
        const latInput = document.querySelector('.oc-planner-admin-lat');
        const lngInput = document.querySelector('.oc-planner-admin-lng');
        const addressInput = document.querySelector('.oc-planner-admin-address');
        if (!mapEl || !latInput || !lngInput) {
            return;
        }

        let lat = parseFloat(latInput.value, 10);
        let lng = parseFloat(lngInput.value, 10);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            lat = 41.9028;
            lng = 12.4964;
        }

        const center = { lat: lat, lng: lng };
        const map = new google.maps.Map(mapEl, {
            center: center,
            zoom: 12,
            mapTypeControl: false,
            streetViewControl: false,
        });
        const marker = new google.maps.Marker({ position: center, map: map, draggable: true });
        sync(center);

        function sync(pos) {
            latInput.value = String(pos.lat);
            lngInput.value = String(pos.lng);
        }

        marker.addListener('dragend', function () {
            const p = marker.getPosition();
            sync({ lat: p.lat(), lng: p.lng() });
        });

        map.addListener('click', function (e) {
            marker.setPosition(e.latLng);
            sync({ lat: e.latLng.lat(), lng: e.latLng.lng() });
        });

        if (addressInput && google.maps.places) {
            const ac = new google.maps.places.Autocomplete(addressInput, {
                fields: ['geometry', 'formatted_address'],
            });
            ac.addListener('place_changed', function () {
                const place = ac.getPlace();
                if (!place.geometry || !place.geometry.location) {
                    return;
                }
                const p = {
                    lat: place.geometry.location.lat(),
                    lng: place.geometry.location.lng(),
                };
                marker.setPosition(p);
                map.panTo(p);
                sync(p);
            });
        }

        const form = mapEl.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                const pos = marker.getPosition();
                if (!pos) {
                    return;
                }
                const latVal = parseFloat(latInput.value, 10);
                const lngVal = parseFloat(lngInput.value, 10);
                if (!Number.isFinite(latVal) || !Number.isFinite(lngVal)) {
                    sync({ lat: pos.lat(), lng: pos.lng() });
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
