<?php
/**
 * Indirizzo con autocomplete Google e coordinate automatiche.
 *
 * @var array $args
 */

$tour_address = $args['tour_address'] ?? '';
$tour_latitude = $args['tour_latitude'] ?? '';
$tour_longitude = $args['tour_longitude'] ?? '';
?>

<style>
.pac-container {
    z-index: 10000 !important;
}
</style>

<div class="mt-6">
    <label for="tour_address" class="form-label">
        Indirizzo Completo
    </label>
    <input type="text"
           id="tour_address"
           name="tour_address"
           class="form-input"
           autocomplete="off"
           placeholder="Inizia a digitare l'indirizzo..."
           value="<?php echo esc_attr($tour_address); ?>">
    <p class="text-sm text-gray-500 mt-1">Seleziona un suggerimento da Google Maps: latitudine e longitudine verranno impostate automaticamente.</p>
    <p id="tour-address-geocode-status" class="text-sm mt-2" style="display:none;"></p>
</div>

<div class="mt-6">
    <label class="form-label">Coordinate</label>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="tour_latitude" class="form-label">Latitudine</label>
            <input type="number"
                   id="tour_latitude"
                   name="tour_latitude"
                   class="form-input"
                   step="any"
                   readonly
                   style="background-color: #f9fafb;"
                   placeholder="Impostata automaticamente"
                   value="<?php echo esc_attr($tour_latitude); ?>">
        </div>
        <div>
            <label for="tour_longitude" class="form-label">Longitudine</label>
            <input type="number"
                   id="tour_longitude"
                   name="tour_longitude"
                   class="form-input"
                   step="any"
                   readonly
                   style="background-color: #f9fafb;"
                   placeholder="Impostata automaticamente"
                   value="<?php echo esc_attr($tour_longitude); ?>">
        </div>
    </div>
</div>

<script>
(function() {
    function setGeocodeStatus(message, type) {
        const statusEl = document.getElementById('tour-address-geocode-status');
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
        statusEl.style.color = type === 'error' ? '#dc2626' : '#059669';
    }

    function updateCoordinates(lat, lng, formattedAddress) {
        const latInput = document.getElementById('tour_latitude');
        const lngInput = document.getElementById('tour_longitude');
        const addressInput = document.getElementById('tour_address');

        if (latInput) {
            latInput.value = Number(lat).toFixed(6);
        }
        if (lngInput) {
            lngInput.value = Number(lng).toFixed(6);
        }
        if (formattedAddress && addressInput) {
            addressInput.value = formattedAddress;
        }

        setGeocodeStatus('Coordinate aggiornate correttamente.', 'success');
    }

    function geocodeAddress(address) {
        if (!address || typeof google === 'undefined' || !google.maps || !google.maps.Geocoder) {
            return;
        }

        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({
            address: address,
            componentRestrictions: { country: 'IT' }
        }, function(results, status) {
            if (status === 'OK' && results && results[0] && results[0].geometry) {
                const location = results[0].geometry.location;
                updateCoordinates(
                    location.lat(),
                    location.lng(),
                    results[0].formatted_address
                );
            } else {
                setGeocodeStatus('Impossibile calcolare le coordinate per questo indirizzo.', 'error');
            }
        });
    }

    function initPartnerAddressAutocomplete() {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
            return;
        }

        const addressInput = document.getElementById('tour_address');
        if (!addressInput || addressInput.dataset.autocompleteReady === '1') {
            return;
        }

        addressInput.dataset.autocompleteReady = '1';

        const autocomplete = new google.maps.places.Autocomplete(addressInput, {
            types: ['geocode'],
            componentRestrictions: { country: 'it' },
            fields: ['formatted_address', 'geometry', 'name']
        });

        autocomplete.addListener('place_changed', function() {
            const place = autocomplete.getPlace();
            if (!place || !place.geometry || !place.geometry.location) {
                setGeocodeStatus('Seleziona un indirizzo valido dai suggerimenti.', 'error');
                return;
            }

            updateCoordinates(
                place.geometry.location.lat(),
                place.geometry.location.lng(),
                place.formatted_address || addressInput.value
            );
        });

        addressInput.addEventListener('blur', function() {
            const latInput = document.getElementById('tour_latitude');
            const lngInput = document.getElementById('tour_longitude');
            const hasCoordinates = latInput && lngInput && latInput.value && lngInput.value;

            if (!hasCoordinates && addressInput.value.trim()) {
                geocodeAddress(addressInput.value.trim());
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            if (window.opencomunePartnerMapsLoaded) {
                initPartnerAddressAutocomplete();
            } else {
                document.addEventListener('opencomune-partner-maps-ready', initPartnerAddressAutocomplete, { once: true });
            }
        });
    } else if (window.opencomunePartnerMapsLoaded) {
        initPartnerAddressAutocomplete();
    } else {
        document.addEventListener('opencomune-partner-maps-ready', initPartnerAddressAutocomplete, { once: true });
    }
})();
</script>
