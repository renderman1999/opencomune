(function () {
    const root = document.getElementById('oc-exp-itinerary-steps-root');
    if (!root) {
        return;
    }

    const cfg = window.opencomuneItineraryStepsConfig || {};
    const strings = cfg.strings || {};
    const payloadInput = document.getElementById('tour_itinerary_steps_payload');
    const tbody = document.getElementById('oc-exp-itinerary-steps-tbody');
    const emptyMsg = document.getElementById('oc-exp-itinerary-steps-empty-msg');
    const modal = document.getElementById('oc-exp-itinerary-steps-modal');
    const modalTitle = document.getElementById('oc-exp-itinerary-steps-modal-title');
    const titleInput = document.getElementById('oc-exp-it-step-title');
    const searchInput = document.getElementById('oc-exp-it-step-address-search');
    const manualFields = document.getElementById('oc-exp-it-step-manual-fields');
    const manualAddress = document.getElementById('oc-exp-it-step-address-manual');
    const latInput = document.getElementById('oc-exp-it-step-lat');
    const lngInput = document.getElementById('oc-exp-it-step-lng');
    const manualToggle = document.getElementById('oc-exp-it-step-toggle-manual');
    const mapEl = document.getElementById('oc-exp-it-step-map');
    const galleryPreview = document.getElementById('oc-exp-it-step-gallery-preview');
    const galleryAddBtn = document.getElementById('oc-exp-it-step-gallery-add');
    const form = document.getElementById('nuova-esperienza-form');
    const descriptionEditorId = 'oc_exp_it_step_description';

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
    let pacActive = false;
    let modalGalleryIds = [];
    let galleryFrame = null;

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
        items.forEach(function (row, index) {
            row.sort_order = index;
        });
        if (payloadInput) {
            payloadInput.value = JSON.stringify(items);
        }
        document.dispatchEvent(new CustomEvent('opencomune-itinerary-steps-changed'));
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function getDescriptionHtml() {
        if (window.tinymce) {
            const ed = window.tinymce.get(descriptionEditorId);
            if (ed && !ed.isHidden()) {
                return ed.getContent();
            }
        }
        const ta = document.getElementById(descriptionEditorId);
        return ta ? ta.value : '';
    }

    function setDescriptionTextarea(html) {
        const ta = document.getElementById(descriptionEditorId);
        if (ta) {
            ta.value = html || '';
        }
    }

    function destroyDescriptionEditor() {
        if (window.wp && wp.editor && typeof wp.editor.remove === 'function') {
            wp.editor.remove(descriptionEditorId);
        } else if (window.tinymce) {
            const ed = window.tinymce.get(descriptionEditorId);
            if (ed) {
                ed.remove();
            }
        }
    }

    function initDescriptionEditor(html) {
        destroyDescriptionEditor();
        setDescriptionTextarea(html || '');
        if (!window.wp || !wp.editor || typeof wp.editor.initialize !== 'function') {
            return;
        }
        wp.editor.initialize(descriptionEditorId, {
            tinymce: {
                wpautop: true,
                toolbar1: 'formatselect,bold,italic,link,bullist,numlist,undo,redo',
            },
            quicktags: true,
            mediaButtons: false,
        });
        if (window.tinymce) {
            const ed = window.tinymce.get(descriptionEditorId);
            if (ed) {
                ed.setContent(html || '');
            }
        }
    }

    function setDescriptionHtml(html) {
        if (window.tinymce && window.tinymce.get(descriptionEditorId)) {
            window.tinymce.get(descriptionEditorId).setContent(html || '');
            return;
        }
        setDescriptionTextarea(html);
    }

    function renderTable() {
        if (!tbody) {
            return;
        }
        tbody.innerHTML = '';
        const isEmpty = !items.length;
        if (emptyMsg) {
            emptyMsg.classList.toggle('is-hidden', !isEmpty);
        }

        items.forEach(function (row, index) {
            const tr = document.createElement('tr');
            const photoCount = Array.isArray(row.gallery_ids) ? row.gallery_ids.length : 0;
            tr.innerHTML =
                '<td>' + (index + 1) + '</td>'
                + '<td>' + escapeHtml(row.title) + '</td>'
                + '<td>' + escapeHtml(row.address || '—') + '</td>'
                + '<td>' + photoCount + '</td>'
                + '<td class="oc-exp-start-times__actions">'
                + '<button type="button" class="oc-exp-pricing__icon-btn" data-it-step-edit="' + escapeHtml(row.id) + '" aria-label="' + escapeHtml(strings.editTitle || 'Modifica') + '"><i class="bi bi-pencil"></i></button>'
                + '<button type="button" class="oc-exp-pricing__delete" data-it-step-remove="' + escapeHtml(row.id) + '" aria-label="' + escapeHtml(strings.confirmDelete || 'Rimuovi') + '"><i class="bi bi-x-lg"></i></button>'
                + '</td>';
            tbody.appendChild(tr);
        });

        tbody.querySelectorAll('[data-it-step-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-it-step-edit'));
            });
        });
        tbody.querySelectorAll('[data-it-step-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm(strings.confirmDelete || 'Rimuovere?')) {
                    return;
                }
                items = items.filter(function (r) {
                    return r.id !== btn.getAttribute('data-it-step-remove');
                });
                renderTable();
                syncPayload();
            });
        });
    }

    function renderGalleryPreview() {
        if (!galleryPreview) {
            return;
        }
        galleryPreview.innerHTML = '';
        if (!modalGalleryIds.length) {
            galleryPreview.classList.add('hidden');
            return;
        }
        galleryPreview.classList.remove('hidden');
        modalGalleryIds.forEach(function (id) {
            const item = document.createElement('div');
            item.className = 'oc-exp-gallery-preview__item';
            item.dataset.id = String(id);
            item.innerHTML =
                '<button type="button" class="oc-exp-gallery-preview__remove" aria-label="' + escapeHtml('Rimuovi') + '">'
                + '<i class="bi bi-trash3" aria-hidden="true"></i></button>'
                + '<img src="" alt="" class="oc-exp-gallery-preview__img" data-id="' + escapeHtml(String(id)) + '">';
            galleryPreview.appendChild(item);
            if (window.wp && wp.media && wp.media.attachment) {
                const att = wp.media.attachment(id);
                att.fetch().then(function () {
                    const url = att.get('sizes')?.thumbnail?.url || att.get('url');
                    const img = item.querySelector('img');
                    if (img && url) {
                        img.src = url;
                    }
                });
            }
        });

        galleryPreview.querySelectorAll('.oc-exp-gallery-preview__remove').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const wrap = btn.closest('.oc-exp-gallery-preview__item');
                const rid = parseInt(wrap?.dataset.id || '0', 10);
                modalGalleryIds = modalGalleryIds.filter(function (gid) { return gid !== rid; });
                renderGalleryPreview();
            });
        });
    }

    function openGalleryPicker() {
        if (!window.wp || !wp.media) {
            return;
        }
        if (galleryFrame) {
            galleryFrame.open();
            return;
        }
        galleryFrame = wp.media({
            title: strings.galleryAdd || 'Aggiungi immagini',
            button: { text: strings.galleryAdd || 'Aggiungi' },
            multiple: true,
            library: { type: 'image' },
        });
        galleryFrame.on('select', function () {
            const selection = galleryFrame.state().get('selection').toJSON();
            selection.forEach(function (att) {
                const id = parseInt(att.id, 10);
                if (id > 0 && modalGalleryIds.indexOf(id) === -1) {
                    modalGalleryIds.push(id);
                }
            });
            renderGalleryPreview();
        });
        galleryFrame.open();
    }

    function ensureMap(center) {
        if (!mapEl || typeof google === 'undefined' || !google.maps) {
            return;
        }
        mapEl.setAttribute('aria-hidden', 'false');
        const c = center || { lat: 41.9028, lng: 12.4964 };
        if (!map) {
            map = new google.maps.Map(mapEl, {
                center: c,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
            });
            marker = new google.maps.Marker({ map: map, draggable: true });
            marker.addListener('dragend', function () {
                const pos = marker.getPosition();
                selectedPlace = {
                    address: (searchInput?.value || manualAddress?.value || '').trim(),
                    latitude: pos.lat(),
                    longitude: pos.lng(),
                };
                if (latInput) {
                    latInput.value = String(pos.lat());
                }
                if (lngInput) {
                    lngInput.value = String(pos.lng());
                }
            });
        } else {
            map.setCenter(c);
        }
        marker.setPosition(c);
    }

    function geocodeAddress(query, cb) {
        if (typeof google === 'undefined' || !google.maps) {
            cb(null);
            return;
        }
        if (!geocoder) {
            geocoder = new google.maps.Geocoder();
        }
        geocoder.geocode({ address: query }, function (results, status) {
            cb(status === 'OK' && results && results[0] ? results[0] : null);
        });
    }

    function applyPlace(place, pacSelection) {
        const address = resolveAddressFromPlace(
            place,
            pacSelection,
            searchInput?.value || '',
            lastSearchInputValue
        );
        selectedPlace = {
            address: address,
            latitude: place.geometry.location.lat(),
            longitude: place.geometry.location.lng(),
        };
        if (searchInput && address) {
            searchInput.value = address;
        }
        ensureMap({ lat: selectedPlace.latitude, lng: selectedPlace.longitude });
    }

    function initAutocomplete() {
        if (!searchInput || typeof google === 'undefined' || !google.maps?.places || autocompleteBound) {
            return;
        }
        autocompleteBound = true;
        lastSearchInputValue = (searchInput.value || '').trim();
        searchInput.addEventListener('focus', function () {
            pacActive = true;
        });
        autocomplete = new google.maps.places.Autocomplete(searchInput, {
            types: ['geocode', 'establishment'],
            fields: ['formatted_address', 'geometry', 'name', 'address_components'],
            componentRestrictions: { country: 'it' },
        });
        autocomplete.addListener('place_changed', function () {
            const pac = (searchInput?.dataset.pacSelection || '').trim();
            const place = autocomplete.getPlace();
            if (!place.geometry || !place.geometry.location) {
                return;
            }
            applyPlace(place, pac);
        });
    }

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
            bootMaps(function () {
                initAutocomplete();
            });
        }
    }

    function resetModalFields() {
        selectedPlace = null;
        modalGalleryIds = [];
        if (titleInput) {
            titleInput.value = '';
        }
        setDescriptionTextarea('');
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
        renderGalleryPreview();
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
                address: address,
                latitude: Number.isFinite(lat) ? lat : 0,
                longitude: Number.isFinite(lng) ? lng : 0,
            };
        }
        const query = (searchInput?.value || '').trim();
        if (selectedPlace && selectedPlace.address) {
            return selectedPlace;
        }
        if (query) {
            return {
                address: query,
                latitude: selectedPlace?.latitude || 0,
                longitude: selectedPlace?.longitude || 0,
            };
        }
        return null;
    }

    function closeModal() {
        if (!modal) {
            return;
        }
        if (window.tinymce) {
            window.tinymce.triggerSave();
        }
        destroyDescriptionEditor();
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('oc-exp-start-times-modal-open');
        document.body.classList.remove('oc-exp-itinerary-steps-modal-open');
        editingId = null;
    }

    function openModal(id) {
        editingId = id || null;
        const row = editingId ? items.find(function (r) { return r.id === editingId; }) : null;
        let descHtml = '';
        resetModalFields();
        if (row) {
            if (modalTitle) {
                modalTitle.textContent = strings.editTitle || 'Modifica tappa';
            }
            if (titleInput) {
                titleInput.value = row.title || '';
            }
            if (searchInput) {
                searchInput.value = row.address || '';
            }
            modalGalleryIds = Array.isArray(row.gallery_ids) ? row.gallery_ids.slice() : [];
            renderGalleryPreview();
            descHtml = row.description || '';
            if (row.latitude || row.longitude) {
                selectedPlace = {
                    address: row.address || '',
                    latitude: row.latitude,
                    longitude: row.longitude,
                };
                bootMaps(function () {
                    ensureMap({ lat: row.latitude, lng: row.longitude });
                    initAutocomplete();
                });
            }
        } else if (modalTitle) {
            modalTitle.textContent = strings.addTitle || 'Aggiungi tappa';
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('oc-exp-start-times-modal-open');
        document.body.classList.add('oc-exp-itinerary-steps-modal-open');
        bootMaps(function () {
            initAutocomplete();
        });
        window.setTimeout(function () {
            initDescriptionEditor(descHtml);
        }, 0);
        titleInput?.focus();
    }

    function saveModal() {
        if (window.tinymce) {
            window.tinymce.triggerSave();
        }
        const title = (titleInput?.value || '').trim();
        if (!title) {
            alert(strings.titleRequired || 'Titolo obbligatorio.');
            titleInput?.focus();
            return;
        }
        const loc = collectAddress();
        const description = getDescriptionHtml();
        const payload = {
            title: title,
            description: description,
            address: loc ? loc.address : '',
            latitude: loc ? loc.latitude : 0,
            longitude: loc ? loc.longitude : 0,
            gallery_ids: modalGalleryIds.slice(),
        };

        if (editingId) {
            items = items.map(function (r) {
                if (r.id !== editingId) {
                    return r;
                }
                return Object.assign({}, r, payload);
            });
        } else {
            items.push(Object.assign({ id: 'it_' + Date.now() }, payload, { sort_order: items.length }));
        }

        closeModal();
        renderTable();
        syncPayload();
    }

    function bootMaps(cb) {
        if (window.opencomuneNuovaEsperienzaMapsLoaded) {
            cb();
            return;
        }
        document.addEventListener('opencomune-nuova-esperienza-maps-ready', cb, { once: true });
    }

    document.getElementById('oc-exp-itinerary-steps-add')?.addEventListener('click', function () {
        openModal(null);
    });
    document.getElementById('oc-exp-it-step-save')?.addEventListener('click', saveModal);
    modal?.querySelectorAll('[data-it-step-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });
    manualToggle?.addEventListener('click', function () {
        setManualMode(!manualMode);
    });
    galleryAddBtn?.addEventListener('click', openGalleryPicker);

    if (manualToggle) {
        manualToggle.textContent = strings.manual || '';
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (window.tinymce) {
                window.tinymce.triggerSave();
            }
            syncPayload();
        });
    }

    loadItems();
    renderTable();
})();
