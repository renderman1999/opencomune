(function (window) {
    'use strict';

    function pacItemLabel(item) {
        if (!item) {
            return '';
        }
        const row = item.cloneNode(true);
        row.querySelectorAll('.pac-icon, .pac-logo').forEach(function (el) {
            el.remove();
        });
        return (row.textContent || '').replace(/\s+/g, ' ').trim();
    }

    /**
     * Indirizzo da mostrare/salvare dopo una scelta Places (priorità: etichetta PAC, formatted_address, input).
     */
    function resolveAddressFromPlace(place, pacSelection, inputValue, lastTyped) {
        const pac = (pacSelection || '').trim();
        const formatted = place && place.formatted_address ? String(place.formatted_address).trim() : '';
        const name = place && place.name ? String(place.name).trim() : '';
        const current = (inputValue || '').trim();
        const typed = (lastTyped || '').trim();

        if (pac) {
            return pac;
        }
        if (formatted) {
            return formatted;
        }
        if (current && (!name || current !== name)) {
            return current;
        }
        if (typed && typed.length > current.length) {
            return typed;
        }
        return current || name || '';
    }

    function resolveVisibleAddress(pacSelection, currentInputValue, lastTyped) {
        const pac = (pacSelection || '').trim();
        const current = (currentInputValue || '').trim();
        const typed = (lastTyped || '').trim();

        if (pac) {
            return pac;
        }
        if (typed && current && typed.length > current.length) {
            return typed;
        }
        return current || typed;
    }

    window.opencomunePlacesAutocomplete = {
        pacItemLabel: pacItemLabel,
        resolveAddressFromPlace: resolveAddressFromPlace,
        resolveVisibleAddress: resolveVisibleAddress,
    };
})(window);
