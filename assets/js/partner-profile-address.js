(function () {
    function getAddressComponent(components, type, useShort) {
        if (!components || !components.length) {
            return '';
        }
        const match = components.find(function (component) {
            return component.types && component.types.indexOf(type) !== -1;
        });
        if (!match) {
            return '';
        }
        return useShort ? (match.short_name || match.long_name || '') : (match.long_name || '');
    }

    function extractAreaQuartiere(components) {
        return getAddressComponent(components, 'sublocality_level_1')
            || getAddressComponent(components, 'sublocality')
            || getAddressComponent(components, 'neighborhood')
            || getAddressComponent(components, 'administrative_area_level_2');
    }

    function extractComune(components) {
        return getAddressComponent(components, 'locality')
            || getAddressComponent(components, 'postal_town')
            || getAddressComponent(components, 'administrative_area_level_3');
    }

    function extractStreetAddress(place) {
        const components = place.address_components || [];
        const route = getAddressComponent(components, 'route');
        const streetNumber = getAddressComponent(components, 'street_number');
        const street = [route, streetNumber].filter(Boolean).join(' ').trim();
        if (street) {
            return street;
        }
        return place.formatted_address || '';
    }

    function setStatus(message, isError) {
        const statusEl = document.getElementById('partner-indirizzo-geocode-status');
        if (!statusEl) {
            return;
        }
        if (!message) {
            statusEl.style.display = 'none';
            statusEl.textContent = '';
            return;
        }
        statusEl.style.display = 'block';
        statusEl.textContent = message;
        statusEl.style.color = isError ? '#dc2626' : '#059669';
    }

    function resolveIndirizzoValue(place, addressInput) {
        const formatted = (place.formatted_address || '').trim();
        const useFull = addressInput && addressInput.dataset.useFullAddress === '1';
        if (useFull && formatted) {
            return formatted;
        }
        return extractStreetAddress(place) || formatted || (addressInput ? addressInput.value : '');
    }

    function notifyPartnerAddressCoordinates(lat, lng) {
        document.dispatchEvent(new CustomEvent('opencomune-partner-address-coords', {
            detail: { lat, lng },
        }));
    }

    function applyPlaceToPartnerFields(place) {
        if (!place || !place.geometry || !place.geometry.location) {
            setStatus('Seleziona un indirizzo valido dai suggerimenti Google.', true);
            return;
        }

        const components = place.address_components || [];
        const indirizzoInput = document.getElementById('indirizzo');
        const capInput = document.getElementById('cap');
        const comuneInput = document.getElementById('comune');
        const areaInput = document.getElementById('area_quartiere');
        const latInput = document.getElementById('latitudine');
        const lngInput = document.getElementById('longitudine');

        const lat = place.geometry.location.lat();
        const lng = place.geometry.location.lng();

        if (indirizzoInput) {
            indirizzoInput.value = resolveIndirizzoValue(place, indirizzoInput);
        }
        if (capInput) {
            capInput.value = getAddressComponent(components, 'postal_code');
        }
        if (comuneInput) {
            comuneInput.value = extractComune(components);
        }
        if (areaInput) {
            const area = extractAreaQuartiere(components);
            if (area) {
                areaInput.value = area;
            }
        }
        if (latInput) {
            latInput.value = Number(lat).toFixed(6);
        }
        if (lngInput) {
            lngInput.value = Number(lng).toFixed(6);
        }

        notifyPartnerAddressCoordinates(lat, lng);
        setStatus('Indirizzo e coordinate aggiornati.', false);
    }

    function geocodePartnerAddress(address) {
        if (!address || typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
            return;
        }

        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({
            address: address,
            componentRestrictions: { country: 'IT' },
        }, function (results, status) {
            if (status === 'OK' && results && results[0]) {
                applyPlaceToPartnerFields(results[0]);
            } else {
                setStatus('Impossibile calcolare i dati per questo indirizzo.', true);
            }
        });
    }

    function initPartnerProfileAddressAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            setStatus('Google Maps non disponibile. Verifica la API key nelle impostazioni.', true);
            return;
        }

        const addressInput = document.getElementById('indirizzo');
        if (!addressInput || addressInput.dataset.autocompleteReady === '1') {
            return;
        }
        addressInput.dataset.autocompleteReady = '1';

        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            types: ['geocode', 'establishment'],
            componentRestrictions: { country: 'it' },
            fields: ['address_components', 'formatted_address', 'geometry', 'name'],
        });

        autocomplete.addListener('place_changed', function () {
            applyPlaceToPartnerFields(autocomplete.getPlace());
        });

        addressInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });

        addressInput.addEventListener('blur', function () {
            if (addressInput.dataset.pacClick === '1') {
                addressInput.dataset.pacClick = '';
                return;
            }
            const query = (addressInput.value || '').trim();
            if (!query) {
                return;
            }
            const latInput = document.getElementById('latitudine');
            const hasCoords = latInput && latInput.value.trim() !== '';
            if (!hasCoords) {
                window.setTimeout(function () {
                    geocodePartnerAddress(query);
                }, 200);
            }
        });
    }

    document.addEventListener('mousedown', function (e) {
        const addressInput = document.getElementById('indirizzo');
        if (addressInput && e.target && e.target.closest && e.target.closest('.pac-container')) {
            addressInput.dataset.pacClick = '1';
        }
    });

    function boot() {
        if (window.opencomunePartnerMapsLoaded) {
            initPartnerProfileAddressAutocomplete();
        } else {
            document.addEventListener('opencomune-partner-maps-ready', initPartnerProfileAddressAutocomplete, { once: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
