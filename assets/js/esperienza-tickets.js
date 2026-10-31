(function () {
    const cfg = window.opencomuneEsperienzaTickets || {};
    const basePreview = cfg.preview || {};
    const strings = cfg.strings || {};
    const weekdayLabels = basePreview.weekday_labels || {};

    function getEditorHtml(fieldId) {
        if (typeof window.tinymce !== 'undefined') {
            const editor = window.tinymce.get(fieldId);
            if (editor) {
                return editor.getContent();
            }
        }
        const textarea = document.getElementById(fieldId);
        return textarea ? textarea.value : '';
    }

    function htmlHasVisibleText(html) {
        return String(html || '').replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() !== '';
    }

    function getCancellationPolicyHtml() {
        const fromEditor = getEditorHtml('tour_cancellation_policy');
        if (htmlHasVisibleText(fromEditor)) {
            return fromEditor;
        }
        if (htmlHasVisibleText(basePreview.cancellation_policy)) {
            return basePreview.cancellation_policy;
        }
        return strings.defaultCancellation || basePreview.cancellation_policy || '';
    }

    function getInputValue(selector) {
        const el = document.querySelector(selector);
        return el && el.value ? el.value.trim() : '';
    }

    function parseJsonInput(id) {
        const el = document.getElementById(id);
        if (!el || !el.value) {
            return null;
        }
        try {
            return JSON.parse(el.value);
        } catch (e) {
            return null;
        }
    }

    function getDefaultPricingCategory(items) {
        if (!Array.isArray(items) || !items.length) {
            return strings.defaultCategory || 'Adulti';
        }
        const def = items.find(function (row) {
            return row.is_default;
        });
        return (def && def.label) || items[0].label || strings.defaultCategory || 'Adulti';
    }

    function formatOperatingHoursFromForm() {
        const enabled = document.getElementById('tour_operating_hours_enabled');
        if (!enabled || !enabled.checked) {
            return strings.hoursDisabled || '';
        }

        const payload = parseJsonInput('tour_operating_hours_payload');
        const days = payload && payload.default ? payload.default : {};
        const parts = [];
        let enabledCount = 0;

        Object.keys(weekdayLabels).forEach(function (key) {
            const day = days[key] || {};
            if (day.enabled) {
                enabledCount += 1;
                if (day.open && day.close) {
                    parts.push(weekdayLabels[key] + ' ' + day.open + '–' + day.close);
                }
            }
        });

        if (!enabledCount || !parts.length) {
            return strings.closedAllWeek || 'Chiuso lun–dom';
        }

        return parts.join('; ');
    }

    function formatStartTimeLabel(timeValue) {
        const time = timeValue || '12:00';
        const now = new Date();
        const day = now.getDay();
        const daysUntilSunday = day === 0 ? 7 : 7 - day;
        const target = new Date(now);
        target.setDate(target.getDate() + daysUntilSunday);
        const parts = time.split(':');
        target.setHours(parseInt(parts[0], 10) || 12, parseInt(parts[1], 10) || 0, 0, 0);

        const formatted = target.toLocaleString(document.documentElement.lang || 'it-IT', {
            weekday: 'short',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });

        return (strings.startPrefix || 'Inizio:') + ' ' + formatted;
    }

    function getStartTimeFromForm() {
        const payload = parseJsonInput('tour_start_times_payload');
        if (Array.isArray(payload) && payload.length && payload[0].time) {
            return payload[0].time;
        }
        return '';
    }

    function buildPreviewState() {
        const state = Object.assign({}, basePreview);
        const title = getInputValue('#tour_title') || getInputValue('input[name="tour_title"]') || state.experience_title || strings.sampleExperienceTitle || '';
        const ticketMessage = getEditorHtml('tour_ticket_message');
        const cancellation = getCancellationPolicyHtml();
        const pricingItems = parseJsonInput('tour_pricing_categories_payload');
        const categoryLabel = getDefaultPricingCategory(pricingItems);
        const sampleQty = 2;
        const unitMatch = String(state.invoice_unit || '€100.00').replace(/[^\d.,]/g, '').replace(',', '.');
        const unitPrice = parseFloat(unitMatch) || 100;
        const subtotal = unitPrice * sampleQty;

        state.experience_title = title;
        state.invoice_line_title = (state.supplier_name || '') + ': ' + title + ' (' + state.ticket_code + ')';
        state.start_time = formatStartTimeLabel(getStartTimeFromForm());
        state.rate_label = strings.rateLabel || state.rate_label;
        state.quantity_summary = categoryLabel + ' : ' + sampleQty;
        state.invoice_line_category = categoryLabel;
        state.invoice_line_datetime = state.start_time.replace(/^[^:]+:\s*/, '');
        state.invoice_qty = String(sampleQty);
        state.invoice_unit = '€' + unitPrice.toFixed(2);
        state.invoice_subtotal = '€' + subtotal.toFixed(2);
        state.invoice_total = '€' + subtotal.toFixed(2);
        state.ticket_message = ticketMessage;
        state.cancellation_policy = htmlHasVisibleText(cancellation)
            ? cancellation
            : (strings.defaultCancellation || state.cancellation_policy);
        state.operating_hours = formatOperatingHoursFromForm() || state.operating_hours;
        state.qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' + encodeURIComponent(state.ticket_code || 'EXP-T123');
        state.sample_note = strings.sampleNote || state.sample_note;

        return state;
    }

    function setFieldText(root, field, value, asHtml) {
        const nodes = root.querySelectorAll('[data-oc-field="' + field + '"]');
        nodes.forEach(function (node) {
            if (asHtml) {
                node.innerHTML = value || '';
            } else {
                node.textContent = value || '';
            }
            const row = node.closest('p');
            if (row && value === '') {
                row.hidden = true;
            } else if (row) {
                row.hidden = false;
            }
        });
    }

    function setLinkField(root, field, href, text) {
        const node = root.querySelector('[data-oc-field="' + field + '"]');
        if (!node) {
            return;
        }
        const row = node.closest('p');
        if (!text) {
            if (row) {
                row.hidden = true;
            }
            return;
        }
        if (row) {
            row.hidden = false;
        }
        node.textContent = text;
        if (href) {
            node.setAttribute('href', href.indexOf('tel:') === 0 || href.indexOf('mailto:') === 0 ? href : href);
        }
    }

    function renderPreview(state) {
        const root = document.getElementById('oc-ticket-doc');
        if (!root) {
            return;
        }

        const qr = root.querySelector('[data-oc-field="qr"]');
        if (qr) {
            qr.src = state.qr_url;
            qr.alt = state.ticket_code;
        }

        setFieldText(root, 'ticket_code', state.ticket_code);
        setFieldText(root, 'ticket_code_inline', state.ticket_code);
        setFieldText(root, 'booking_ref', state.booking_ref);
        setFieldText(root, 'payment_status', state.payment_status);
        setFieldText(root, 'customer_name', state.customer_name);
        setFieldText(root, 'customer_email', state.customer_email);
        setLinkField(
            root,
            'customer_phone_link',
            state.customer_phone ? 'tel:' + String(state.customer_phone).replace(/\s/g, '') : '',
            state.customer_phone
        );
        setFieldText(root, 'supplier_name', state.supplier_name);

        const vatNode = root.querySelector('[data-oc-field="supplier_vat"]');
        if (vatNode) {
            if (state.supplier_vat) {
                vatNode.textContent = state.supplier_vat;
                vatNode.hidden = false;
            } else {
                vatNode.hidden = true;
            }
        }

        setLinkField(root, 'supplier_phone_link', state.supplier_phone ? 'tel:' + state.supplier_phone.replace(/\s/g, '') : '', state.supplier_phone);
        setLinkField(root, 'supplier_email_link', state.supplier_email ? 'mailto:' + state.supplier_email : '', state.supplier_email);
        setLinkField(root, 'supplier_web_link', state.supplier_web, state.supplier_web);

        setFieldText(root, 'experience_title', state.experience_title);
        setFieldText(root, 'rate_label', state.rate_label);
        setFieldText(root, 'start_time', state.start_time);
        setFieldText(root, 'quantity_summary', state.quantity_summary);

        const messageEl = root.querySelector('[data-oc-field="ticket_message"]');
        if (messageEl) {
            const html = state.ticket_message || '';
            if (htmlHasVisibleText(html)) {
                messageEl.innerHTML = html;
                messageEl.hidden = false;
            } else {
                messageEl.innerHTML = '';
                messageEl.hidden = true;
            }
        }

        setFieldText(root, 'invoice_line_title', state.invoice_line_title);
        setFieldText(root, 'invoice_line_datetime', state.invoice_line_datetime);
        setFieldText(root, 'invoice_line_category', state.invoice_line_category);
        setFieldText(root, 'invoice_qty', state.invoice_qty);
        setFieldText(root, 'invoice_unit', state.invoice_unit);
        setFieldText(root, 'invoice_subtotal', state.invoice_subtotal);
        setFieldText(root, 'invoice_total', state.invoice_total);
        setFieldText(root, 'sample_note', state.sample_note);

        const cancellationEl = root.querySelector('[data-oc-field="cancellation_policy"]');
        if (cancellationEl) {
            cancellationEl.innerHTML = state.cancellation_policy || '';
        }

        setFieldText(root, 'operating_hours', state.operating_hours);
    }

    function openPreview() {
        const modal = document.getElementById('oc-exp-ticket-preview-modal');
        if (!modal) {
            return;
        }
        renderPreview(buildPreviewState());
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('oc-exp-tickets-modal-open');
    }

    function closePreview() {
        const modal = document.getElementById('oc-exp-ticket-preview-modal');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('oc-exp-tickets-modal-open');
    }

    document.addEventListener('DOMContentLoaded', function () {
        const openBtn = document.getElementById('oc-exp-ticket-preview-open');
        if (openBtn) {
            openBtn.addEventListener('click', openPreview);
        }
        document.querySelectorAll('[data-oc-ticket-preview-close]').forEach(function (el) {
            el.addEventListener('click', closePreview);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closePreview();
            }
        });
    });
})();
