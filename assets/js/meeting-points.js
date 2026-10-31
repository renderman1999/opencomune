(function () {
    const root = document.getElementById('oc-exp-meeting-points-root');
    if (!root) {
        return;
    }

    const cfg = window.opencomuneMeetingPointsConfig || {};
    const strings = cfg.strings || {};
    const payloadInput = document.getElementById('tour_meeting_points_payload');
    const listEl = document.getElementById('oc-exp-meeting-points-list');
    const emptyEl = document.getElementById('oc-exp-meeting-points-empty');
    const modal = document.getElementById('oc-exp-mp-modal');
    const modalTitle = document.getElementById('oc-exp-mp-modal-title');
    const titleInput = document.getElementById('oc-exp-mp-title');
    const searchInput = document.getElementById('oc-exp-mp-address-search');
    const manualFields = document.getElementById('oc-exp-mp-manual-fields');
    const manualAddress = document.getElementById('oc-exp-mp-address-manual');
    const latInput = document.getElementById('oc-exp-mp-lat');
    const lngInput = document.getElementById('oc-exp-mp-lng');
    const manualToggle = document.getElementById('oc-exp-mp-toggle-manual');
    const mapEl = document.getElementById('oc-exp-mp-map');
    const form = document.getElementById('nuova-esperienza-form');

    let items = [];
    let editingId = null;
    let manualMode = false;
    let map = null;
    let marker = null;
    let autocomplete = null;
    let geocoder = null;
    let selectedPlace = null;
    let autocompleteBound = false;
    let lastSearchInputValue = '';
    let meetingPacActive = false;

    const placesUtil = window.opencomunePlacesAutocomplete || {};
    const resolveAddressFromPlace = placesUtil.resolveAddressFromPlace || function () { return ''; };
    const resolveVisibleAddress = placesUtil.resolveVisibleAddress || function (_p, c) { return (c || '').trim(); };
    const pacItemLabel = placesUtil.pacItemLabel || function () { return ''; };

    function loadItems() {
        try {
            items = JSON.parse(payloadInput?.value || '[]');
            if (!Array.isArray(items)) {
                items = [];
            }
        } catch (e) {
            items = [];
        }
    }

    function syncPayload() {
        if (payloadInput) {
            payloadInput.value = JSON.stringify(items);
        }
        document.dispatchEvent(new CustomEvent('opencomune-meeting-points-changed'));
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function render() {
        if (!listEl) {
            return;
        }
        listEl.innerHTML = '';
        const isEmpty = !items.length;
        if (emptyEl) {
            emptyEl.classList.toggle('is-hidden', !isEmpty);
        }

        items.forEach(function (row) {
            const li = document.createElement('li');
            li.className = 'oc-exp-meeting-points__row';
            li.innerHTML =
                '<div><strong>' + escapeHtml(row.title) + '</strong><span>' + escapeHtml(row.address) + '</span></div>'
                + '<div class="oc-exp-meeting-points__row-actions">'
                + '<button type="button" class="oc-exp-pricing__icon-btn" data-mp-edit="' + escapeHtml(row.id) + '"><i class="bi bi-pencil"></i></button>'
                + '<button type="button" class="oc-exp-pricing__delete" data-mp-remove="' + escapeHtml(row.id) + '"><i class="bi bi-x-lg"></i></button>'
                + '</div>';
            listEl.appendChild(li);
        });

        listEl.querySelectorAll('[data-mp-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-mp-edit'));
            });
        });
        listEl.querySelectorAll('[data-mp-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm(strings.confirmDelete || 'Rimuovere?')) {
                    return;
                }
                items = items.filter(function (r) { return r.id !== btn.getAttribute('data-mp-remove'); });
                render();
                syncPayload();
            });
        });
    }

    function ensureMap(center) {
        if (!mapEl || typeof google === 'undefined' || !google.maps) {
            return;
        }
        mapEl.hidden = false;
        const lat = center?.lat || 41.9;
        const lng = center?.lng || 12.5;
        if (!map) {
            map = new google.maps.Map(mapEl, {
                center: { lat, lng },
                zoom: center ? 15 : 6,
                mapTypeControl: false,
                streetViewControl: false,
            });
            marker = new google.maps.Marker({ map, position: { lat, lng } });
        } else {
            map.setCenter({ lat, lng });
            map.setZoom(center ? 15 : 6);
            marker.setPosition({ lat, lng });
        }
    }

    function markMeetingPacClick() {
        if (searchInput) {
            searchInput.dataset.pacClick = '1';
        }
    }

    function clearMeetingPacState() {
        if (searchInput) {
            delete searchInput.dataset.pacClick;
            delete searchInput.dataset.pacSelection;
        }
    }

    function applyPlace(place, pacSelection) {
        if (!place || !place.geometry || !place.geometry.location) {
            alert(strings.selectPlace || 'Seleziona un indirizzo valido.');
            return;
        }
        const pac = (pacSelection || searchInput?.dataset.pacSelection || '').trim();
        const address = resolveAddressFromPlace(
            place,
            pac,
            searchInput?.value || '',
            lastSearchInputValue
        );
        if (!address) {
            alert(strings.selectPlace || 'Seleziona un indirizzo valido.');
            return;
        }
        selectedPlace = {
            address: address,
            latitude: place.geometry.location.lat(),
            longitude: place.geometry.location.lng(),
        };
        if (searchInput) {
            searchInput.value = address;
        }
        lastSearchInputValue = address;
        clearMeetingPacState();
        ensureMap({ lat: selectedPlace.latitude, lng: selectedPlace.longitude });
    }

    function getGeocoder() {
        if (!geocoder && typeof google !== 'undefined' && google.maps) {
            geocoder = new google.maps.Geocoder();
        }
        return geocoder;
    }

    function geocodeAddress(address, callback) {
        const geo = getGeocoder();
        if (!geo || !address) {
            callback(null);
            return;
        }
        geo.geocode({ address: address, componentRestrictions: { country: 'it' } }, function (results, status) {
            if (status !== 'OK' || !results || !results[0]) {
                callback(null);
                return;
            }
            callback(results[0]);
        });
    }

    function applyGeocodeResult(result, preserveInputAddress) {
        if (!result || !result.geometry || !result.geometry.location) {
            return false;
        }
        const pac = (searchInput?.dataset.pacSelection || '').trim();
        const inputText = (searchInput?.value || '').trim();
        const address = preserveInputAddress
            ? resolveVisibleAddress(pac, inputText, lastSearchInputValue)
            : resolveAddressFromPlace(result, pac, inputText, lastSearchInputValue);

        selectedPlace = {
            address: address,
            latitude: result.geometry.location.lat(),
            longitude: result.geometry.location.lng(),
        };
        if (searchInput && address) {
            searchInput.value = address;
        }
        lastSearchInputValue = address;
        clearMeetingPacState();
        ensureMap({ lat: selectedPlace.latitude, lng: selectedPlace.longitude });
        return true;
    }

    function initAutocomplete() {
        if (!searchInput || typeof google === 'undefined' || !google.maps?.places) {
            return;
        }
        if (autocompleteBound) {
            return;
        }
        autocompleteBound = true;
        lastSearchInputValue = (searchInput.value || '').trim();

        searchInput.addEventListener('focus', function () {
            meetingPacActive = true;
        });

        searchInput.addEventListener('input', function () {
            if (searchInput.dataset.pacClick === '1') {
                return;
            }
            lastSearchInputValue = (searchInput.value || '').trim();
        });

        autocomplete = new google.maps.places.Autocomplete(searchInput, {
            types: ['geocode', 'establishment'],
            fields: ['formatted_address', 'geometry', 'name', 'address_components'],
            componentRestrictions: { country: 'it' },
        });

        autocomplete.addListener('place_changed', function () {
            const pacSelection = (searchInput?.dataset.pacSelection || '').trim();
            const place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                const query = resolveVisibleAddress(
                    pacSelection,
                    searchInput?.value || '',
                    lastSearchInputValue
                );
                if (query) {
                    geocodeAddress(query, function (result) {
                        if (!applyGeocodeResult(result, true)) {
                            alert(strings.selectPlace || 'Seleziona un indirizzo valido dai suggerimenti Google.');
                        }
                    });
                }
                return;
            }
            applyPlace(place, pacSelection);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                markMeetingPacClick();
            }
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        searchInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                meetingPacActive = false;
            }, 400);
            if (manualMode || searchInput.dataset.pacClick === '1') {
                return;
            }
            const query = resolveVisibleAddress('', searchInput.value || '', lastSearchInputValue);
            if (!query) {
                return;
            }
            if (selectedPlace && selectedPlace.address === query) {
                return;
            }
            window.setTimeout(function () {
                geocodeAddress(query, function (result) {
                    applyGeocodeResult(result, true);
                });
            }, 200);
        });
    }

    document.addEventListener('mousedown', function (e) {
        if (!searchInput || !meetingPacActive || (modal && modal.hidden)) {
            return;
        }
        const pacContainer = e.target && e.target.closest && e.target.closest('.pac-container');
        if (!pacContainer) {
            return;
        }
        markMeetingPacClick();
        const pacItem = e.target.closest('.pac-item');
        if (pacItem) {
            const label = pacItemLabel(pacItem);
            if (label) {
                searchInput.dataset.pacSelection = label;
            }
        }
    }, true);

    function setManualMode(on) {
        manualMode = on;
        if (manualFields) {
            manualFields.classList.toggle('is-hidden', !on);
        }
        if (manualToggle) {
            manualToggle.textContent = on
                ? (strings.useAutocomplete || 'Usa la ricerca Google')
                : (strings.manual || 'Inserimento manuale');
        }
        if (!on) {
            initAutocomplete();
        }
    }

    function resetModalFields() {
        selectedPlace = null;
        if (titleInput) {
            titleInput.value = '';
        }
        if (searchInput) {
            searchInput.value = '';
        }
        if (manualAddress) {
            manualAddress.value = '';
        }
        if (latInput) {
            latInput.value = '';
        }
        if (lngInput) {
            lngInput.value = '';
        }
        setManualMode(false);
    }

    function openModal(id) {
        editingId = id || null;
        const row = editingId ? items.find(function (r) { return r.id === editingId; }) : null;
        resetModalFields();
        if (modalTitle) {
            modalTitle.textContent = editingId
                ? (strings.editTitle || 'Modifica punto di inizio')
                : (strings.addTitle || 'Aggiungi punto di inizio');
        }
        if (row) {
            if (titleInput) {
                titleInput.value = row.title;
            }
            if (searchInput) {
                searchInput.value = row.address;
            }
            selectedPlace = {
                address: row.address,
                latitude: parseFloat(row.latitude) || 0,
                longitude: parseFloat(row.longitude) || 0,
            };
            if (selectedPlace.latitude || selectedPlace.longitude) {
                ensureMap({ lat: selectedPlace.latitude || 41.9, lng: selectedPlace.longitude || 12.5 });
            }
        }
        if (modal) {
            modal.hidden = false;
        }
        bootMaps(function () {
            window.setTimeout(function () {
                initAutocomplete();
                if (map) {
                    google.maps.event.trigger(map, 'resize');
                }
                if (!row) {
                    ensureMap(null);
                } else if (selectedPlace && (selectedPlace.latitude || selectedPlace.longitude)) {
                    ensureMap({
                        lat: selectedPlace.latitude || 41.9,
                        lng: selectedPlace.longitude || 12.5,
                    });
                }
                if (searchInput) {
                    searchInput.focus();
                }
            }, 80);
        });
    }

    function closeModal() {
        editingId = null;
        if (modal) {
            modal.hidden = true;
        }
    }

    function collectAddress() {
        if (manualMode) {
            const address = (manualAddress?.value || '').trim();
            const lat = parseFloat(latInput?.value || '');
            const lng = parseFloat(lngInput?.value || '');
            if (!address) {
                return null;
            }
            return {
                address,
                latitude: Number.isFinite(lat) ? lat : 0,
                longitude: Number.isFinite(lng) ? lng : 0,
            };
        }
        const query = (searchInput?.value || '').trim();
        if (query) {
            if (!selectedPlace || selectedPlace.address !== query) {
                return {
                    address: query,
                    latitude: selectedPlace?.latitude || 0,
                    longitude: selectedPlace?.longitude || 0,
                };
            }
        }
        if (!selectedPlace || !selectedPlace.address) {
            return null;
        }
        return selectedPlace;
    }

    function saveModal() {
        const title = (titleInput?.value || '').trim();
        if (!title) {
            titleInput?.focus();
            return;
        }
        const loc = collectAddress();
        if (!loc) {
            const query = manualMode
                ? (manualAddress?.value || '').trim()
                : (searchInput?.value || '').trim();
            if (!manualMode && query) {
                geocodeAddress(query, function (result) {
                    if (!applyGeocodeResult(result, true)) {
                        alert(strings.selectPlace || 'Indirizzo obbligatorio.');
                        return;
                    }
                    saveModal();
                });
                return;
            }
            alert(strings.selectPlace || 'Indirizzo obbligatorio.');
            return;
        }

        if (editingId) {
            items = items.map(function (r) {
                if (r.id !== editingId) {
                    return r;
                }
                return Object.assign({}, r, {
                    title,
                    address: loc.address,
                    latitude: loc.latitude,
                    longitude: loc.longitude,
                });
            });
        } else {
            items.push({
                id: 'mp_' + Date.now(),
                title,
                address: loc.address,
                latitude: loc.latitude,
                longitude: loc.longitude,
                sort_order: items.length,
            });
        }

        closeModal();
        render();
        syncPayload();
    }

    function bootMaps(cb) {
        if (window.opencomuneNuovaEsperienzaMapsLoaded) {
            cb();
            return;
        }
        document.addEventListener('opencomune-nuova-esperienza-maps-ready', cb, { once: true });
    }

    document.getElementById('oc-exp-meeting-points-add')?.addEventListener('click', function () {
        openModal(null);
    });
    document.getElementById('oc-exp-mp-save')?.addEventListener('click', saveModal);
    document.querySelectorAll('[data-mp-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    manualToggle?.addEventListener('click', function () {
        setManualMode(!manualMode);
    });

    if (manualToggle) {
        manualToggle.textContent = strings.manual || '';
    }

    if (form) {
        form.addEventListener('submit', syncPayload);
    }

    loadItems();
    render();
})();
