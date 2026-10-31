(function () {
    const root = document.getElementById('oc-exp-pricing-root');
    if (!root) {
        return;
    }

    const strings = window.opencomunePricingCategoriesStrings || {};
    const payloadInput = document.getElementById('tour_pricing_categories_payload');
    const listEl = document.getElementById('oc-exp-pricing-list');
    const emptyEl = document.getElementById('oc-exp-pricing-empty');
    const modal = document.getElementById('oc-exp-pricing-modal');
    const modalTitle = document.getElementById('oc-exp-pricing-modal-title');
    const labelInput = document.getElementById('oc-exp-pricing-label');
    const descInput = document.getElementById('oc-exp-pricing-description');
    const priceInput = document.getElementById('oc-exp-pricing-price');
    const minAgeInput = document.getElementById('oc-exp-pricing-min-age');
    const maxAgeInput = document.getElementById('oc-exp-pricing-max-age');
    const form = document.getElementById('nuova-esperienza-form');

    let items = [];
    let editingId = null;
    let dragIndex = null;

    function loadItems() {
        try {
            const raw = (payloadInput?.value || '').trim();
            items = JSON.parse(raw || '[]');
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
        document.dispatchEvent(new CustomEvent('opencomune-pricing-categories-changed'));
    }

    window.opencomuneSyncPricingCategoriesPayload = function () {
        syncPayload();
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function formatPriceEuro(amount) {
        const value = parseFloat(String(amount ?? '0').replace(',', '.'));
        if (Number.isNaN(value) || value <= 0) {
            return strings.free || 'Gratuito';
        }
        return '€ ' + value.toFixed(2).replace('.', ',');
    }

    function formatAgeSuffix(row) {
        if (row.min_age != null && row.max_age != null) {
            return ' (' + row.min_age + ' - ' + row.max_age + ')';
        }
        if (row.min_age != null) {
            return ' (' + row.min_age + '+)';
        }
        if (row.max_age != null) {
            return ' (≤ ' + row.max_age + ')';
        }
        return '';
    }

    function render() {
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        const showEmpty = !items.length;
        if (emptyEl) {
            emptyEl.hidden = !showEmpty;
        }

        items.forEach(function (row, index) {
            const li = document.createElement('li');
            li.className = 'oc-exp-pricing__row';
            li.setAttribute('data-id', row.id);
            li.draggable = true;

            const defaultBadge = row.is_default
                ? '<span class="oc-exp-pricing__badge">' + escapeHtml(strings.default || 'Predefinita') + '</span>'
                : '<button type="button" class="oc-exp-pricing__set-default" data-set-default="' + escapeHtml(row.id) + '">'
                    + escapeHtml(strings.setDefault || 'Imposta come predefinita') + '</button>';

            li.innerHTML =
                '<button type="button" class="oc-exp-pricing__drag" title="Riordina" aria-label="Riordina"><i class="bi bi-grip-vertical"></i></button>'
                + '<div class="oc-exp-pricing__label-wrap"><strong>'
                + escapeHtml(row.label) + escapeHtml(formatAgeSuffix(row))
                + '</strong> <span class="oc-exp-pricing__price">' + escapeHtml(formatPriceEuro(row.price)) + '</span>'
                + ' <span class="oc-exp-pricing__id">| #' + escapeHtml(String(row.public_id || '')) + '</span></div>'
                + defaultBadge
                + '<button type="button" class="oc-exp-pricing__icon-btn" data-edit="' + escapeHtml(row.id) + '" aria-label="Modifica"><i class="bi bi-pencil"></i></button>'
                + '<button type="button" class="oc-exp-pricing__delete" data-remove="' + escapeHtml(row.id) + '" aria-label="Elimina"><i class="bi bi-x-lg"></i></button>';

            li.addEventListener('dragstart', function () {
                dragIndex = index;
                li.classList.add('is-dragging');
            });
            li.addEventListener('dragend', function () {
                dragIndex = null;
                li.classList.remove('is-dragging');
            });
            li.addEventListener('dragover', function (e) {
                e.preventDefault();
            });
            li.addEventListener('drop', function (e) {
                e.preventDefault();
                if (dragIndex === null || dragIndex === index) {
                    return;
                }
                const moved = items.splice(dragIndex, 1)[0];
                items.splice(index, 0, moved);
                items.forEach(function (item, i) {
                    item.sort_order = i;
                });
                render();
                syncPayload();
            });

            listEl.appendChild(li);
        });

        listEl.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.getAttribute('data-edit'));
            });
        });
        listEl.querySelectorAll('[data-remove]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-remove');
                if (!window.confirm(strings.confirmDelete || 'Rimuovere questa categoria?')) {
                    return;
                }
                const removed = items.filter(function (r) { return r.id === id; })[0];
                items = items.filter(function (r) { return r.id !== id; });
                if (removed && removed.is_default && items.length) {
                    items[0].is_default = true;
                }
                render();
                syncPayload();
            });
        });
        listEl.querySelectorAll('[data-set-default]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const id = btn.getAttribute('data-set-default');
                items.forEach(function (r) {
                    r.is_default = r.id === id;
                });
                render();
                syncPayload();
            });
        });
    }

    function openModal(id) {
        editingId = id || null;
        const row = editingId ? items.find(function (r) { return String(r.id) === String(editingId); }) : null;
        if (modalTitle) {
            modalTitle.textContent = editingId
                ? (strings.editTitle || 'Modifica categoria prezzo')
                : (strings.createTitle || 'Crea categoria prezzo');
        }
        if (labelInput) {
            labelInput.value = row ? row.label : '';
        }
        if (descInput) {
            descInput.value = row ? (row.description || '') : '';
        }
        if (priceInput) {
            priceInput.value = row && row.price != null && row.price !== '' ? String(row.price) : '';
        }
        if (minAgeInput) {
            minAgeInput.value = row && row.min_age != null ? row.min_age : '';
        }
        if (maxAgeInput) {
            maxAgeInput.value = row && row.max_age != null ? row.max_age : '';
        }
        if (modal) {
            modal.hidden = false;
        }
    }

    function closeModal() {
        editingId = null;
        if (modal) {
            modal.hidden = true;
        }
    }

    function saveModal() {
        const label = (labelInput?.value || '').trim();
        if (!label) {
            labelInput?.focus();
            return;
        }

        const minAge = minAgeInput?.value !== '' ? parseInt(minAgeInput.value, 10) : null;
        const maxAge = maxAgeInput?.value !== '' ? parseInt(maxAgeInput.value, 10) : null;
        const priceRaw = (priceInput?.value ?? '').trim();
        if (priceRaw === '') {
            priceInput?.focus();
            return;
        }
        const price = parseFloat(priceRaw.replace(',', '.'));
        if (Number.isNaN(price) || price < 0) {
            priceInput?.focus();
            return;
        }

        if (editingId) {
            const editKey = String(editingId);
            items = items.map(function (r) {
                if (String(r.id) !== editKey) {
                    return r;
                }
                return Object.assign({}, r, {
                    label,
                    price: Math.round(price * 100) / 100,
                    description: (descInput?.value || '').trim(),
                    min_age: Number.isFinite(minAge) ? minAge : null,
                    max_age: Number.isFinite(maxAge) ? maxAge : null,
                });
            });
        } else {
            const id = 'pc_' + Date.now();
            const newRow = {
                id,
                public_id: Math.floor(100000 + (Math.abs(crc32(id)) % 900000)),
                label,
                price: Math.round(price * 100) / 100,
                description: (descInput?.value || '').trim(),
                min_age: Number.isFinite(minAge) ? minAge : null,
                max_age: Number.isFinite(maxAge) ? maxAge : null,
                is_default: items.length === 0,
                sort_order: items.length,
            };
            items.push(newRow);
        }

        closeModal();
        render();
        syncPayload();
    }

    function crc32(str) {
        let crc = 0 ^ -1;
        for (let i = 0; i < str.length; i++) {
            crc = (crc >>> 8) ^ table[(crc ^ str.charCodeAt(i)) & 0xff];
        }
        return (crc ^ -1) >>> 0;
    }

    const table = (function () {
        const t = [];
        for (let i = 0; i < 256; i++) {
            let c = i;
            for (let k = 0; k < 8; k++) {
                c = (c & 1) ? (0xedb88320 ^ (c >>> 1)) : (c >>> 1);
            }
            t[i] = c;
        }
        return t;
    })();

    document.getElementById('oc-exp-pricing-create')?.addEventListener('click', function () {
        openModal(null);
    });
    document.getElementById('oc-exp-pricing-save')?.addEventListener('click', saveModal);
    document.querySelectorAll('[data-pricing-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    if (form) {
        form.addEventListener('submit', syncPayload, true);
    }

    loadItems();
    render();
})();
