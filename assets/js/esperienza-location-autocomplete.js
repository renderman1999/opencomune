(function () {
    const displayInput = document.getElementById('tour_location_autocomplete');
    if (!displayInput) {
        return;
    }

    const placesUtil = window.opencomunePlacesAutocomplete || {};
    const resolveAddressFromPlace = placesUtil.resolveAddressFromPlace || function () { return ''; };
    const resolveVisibleAddress = placesUtil.resolveVisibleAddress || function (_p, c) { return (c || '').trim(); };
    const pacItemLabel = placesUtil.pacItemLabel || function () { return ''; };

    let appliedDisplayText = '';
    let lastLocationInputValue = '';
    let locationPacActive = false;

    function setLocationStatus(message, isError) {
        const statusEl = document.getElementById('tour-location-status');
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.hidden = true;
            statusEl.textContent = '';
            return;
        }
        statusEl.hidden = false;
        statusEl.textContent = message;
        statusEl.classList.toggle('is-error', !!isError);
    }

    function extractCityName(place) {
        const components = place.address_components || [];
        const pick = function (type) {
            const match = components.find(function (component) {
                return component.types && component.types.indexOf(type) !== -1;
            });
            return match ? match.long_name : '';
        };

        return pick('locality')
            || pick('administrative_area_level_3')
            || pick('postal_town')
            || '';
    }

    function rememberLocationInputValue() {
        return (displayInput.value || '').trim() || (lastLocationInputValue || '').trim();
    }

    function markPacClick() {
        displayInput.dataset.pacClick = '1';
    }

    function clearPacSelectionState() {
        delete displayInput.dataset.pacClick;
        delete displayInput.dataset.pacSelection;
    }

    function applyPlace(place, pacSelection) {
        const cityInput = document.getElementById('citta');
        const addressInput = document.getElementById('tour_address');
        const latInput = document.getElementById('tour_latitude');
        const lngInput = document.getElementById('tour_longitude');

        if (!place || !place.geometry || !place.geometry.location) {
            setLocationStatus('Seleziona una località valida dai suggerimenti.', true);
            return;
        }

        const pac = (pacSelection || displayInput.dataset.pacSelection || '').trim();
        const address = resolveAddressFromPlace(
            place,
            pac,
            displayInput.value || '',
            lastLocationInputValue
        );
        const city = extractCityName(place) || (place.name || '').trim();
        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        if (cityInput) {
            cityInput.value = city;
        }
        if (addressInput) {
            addressInput.value = address;
        }
        if (latInput) {
            latInput.value = Number(lat).toFixed(6);
        }
        if (lngInput) {
            lngInput.value = Number(lng).toFixed(6);
        }
        displayInput.value = address || city;

        appliedDisplayText = address || city;
        lastLocationInputValue = appliedDisplayText;
        clearPacSelectionState();
        setLocationStatus('Località impostata correttamente.', false);
    }

    function geocodeAddress(address, callback) {
        if (!address || typeof google === 'undefined' || !google.maps) {
            callback(null);
            return;
        }
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ address: address, componentRestrictions: { country: 'it' } }, function (results, status) {
            if (status !== 'OK' || !results || !results[0]) {
                callback(null);
                return;
            }
            callback(results[0]);
        });
    }

    function clearLocation() {
        ['citta', 'tour_address', 'tour_latitude', 'tour_longitude', 'tour_location_autocomplete'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.value = '';
            }
        });
        appliedDisplayText = '';
        lastLocationInputValue = '';
        clearPacSelectionState();
        setLocationStatus('', false);
    }

    function initLocationAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            setLocationStatus('Google Maps non disponibile. Verifica la API key nelle impostazioni.', true);
            return;
        }

        if (displayInput.dataset.autocompleteReady === '1') {
            return;
        }
        displayInput.dataset.autocompleteReady = '1';
        lastLocationInputValue = rememberLocationInputValue();
        appliedDisplayText = lastLocationInputValue;

        displayInput.addEventListener('focus', function () {
            locationPacActive = true;
        });

        displayInput.addEventListener('input', function () {
            if (displayInput.dataset.pacClick === '1') {
                return;
            }
            lastLocationInputValue = (displayInput.value || '').trim();
        });

        const autocomplete = new google.maps.places.Autocomplete(displayInput, {
            types: ['geocode', 'establishment'],
            componentRestrictions: { country: 'it' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name'],
        });

        autocomplete.addListener('place_changed', function () {
            const pacSelection = (displayInput.dataset.pacSelection || '').trim();
            const place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                const visibleAddress = resolveVisibleAddress(
                    pacSelection,
                    displayInput.value || '',
                    lastLocationInputValue
                );
                if (visibleAddress) {
                    geocodeAddress(visibleAddress, function (result) {
                        if (!result) {
                            setLocationStatus('Seleziona un indirizzo valido dai suggerimenti Google.', true);
                            clearPacSelectionState();
                            return;
                        }
                        applyPlace(result, pacSelection || visibleAddress);
                    });
                } else {
                    setLocationStatus('Seleziona una località valida dai suggerimenti.', true);
                    clearPacSelectionState();
                }
                return;
            }
            applyPlace(place, pacSelection);
        });

        displayInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === 'Tab') {
                markPacClick();
            }
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        displayInput.addEventListener('blur', function () {
            window.setTimeout(function () {
                locationPacActive = false;
            }, 400);
            if (displayInput.dataset.pacClick === '1') {
                return;
            }
            const query = resolveVisibleAddress('', displayInput.value || '', lastLocationInputValue);
            if (!query || query === appliedDisplayText) {
                return;
            }
            window.setTimeout(function () {
                geocodeAddress(query, function (result) {
                    if (result) {
                        applyPlace(result, '');
                    }
                });
            }, 200);
        });

        document.addEventListener('mousedown', function (e) {
            if (!locationPacActive) {
                return;
            }
            const pacContainer = e.target && e.target.closest && e.target.closest('.pac-container');
            if (!pacContainer) {
                return;
            }
            markPacClick();
            const pacItem = e.target.closest('.pac-item');
            if (pacItem) {
                const label = pacItemLabel(pacItem);
                if (label) {
                    displayInput.dataset.pacSelection = label;
                }
            }
        }, true);

        const clearBtn = document.getElementById('tourLocationClearBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearLocation);
        }
    }

    function boot() {
        if (window.opencomuneNuovaEsperienzaMapsLoaded) {
            initLocationAutocomplete();
        } else {
            document.addEventListener('opencomune-nuova-esperienza-maps-ready', initLocationAutocomplete, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
