(function () {
    const root = document.getElementById('oc-availability-calendar-root');
    if (!root) {
        return;
    }

    const config = window.opencomuneAvailabilityCalendar || {};
    const tourId = parseInt(config.tourId, 10) || 0;
    const ajaxUrl = config.ajaxUrl || '';
    const form = document.getElementById(config.formId || 'modifica-esperienza-form');
    const payloadInput = document.getElementById('tour_availability_rules_payload');
    const weekdayKeys = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    const weekdayLabels = config.weekdayLabels || ['L', 'M', 'M', 'G', 'V', 'S', 'D'];
    const colors = config.colors || [];
    const startTimes = config.startTimes || [];

    let rules = [];
    try {
        rules = JSON.parse(payloadInput?.value || '[]');
        if (!Array.isArray(rules)) {
            rules = [];
        }
    } catch (e) {
        rules = [];
    }

    let viewDate = new Date();
    viewDate.setDate(1);
    let editingRuleId = null;

    const els = {
        alert: document.getElementById('oc-av-alert'),
        grid: document.getElementById('oc-av-grid'),
        monthLabel: document.getElementById('oc-av-month-label'),
        modal: document.getElementById('oc-av-modal'),
        rulesList: document.getElementById('oc-av-rules-list'),
    };

    function syncPayload() {
        if (payloadInput) {
            payloadInput.value = JSON.stringify(rules);
        }
        document.dispatchEvent(new CustomEvent('opencomune-availability-rules-changed'));
    }

    function monthRange(date) {
        const y = date.getFullYear();
        const m = date.getMonth();
        const from = new Date(y, m, 1);
        const to = new Date(y, m + 1, 0);
        const pad = (n) => String(n).padStart(2, '0');
        return {
            from: from.getFullYear() + '-' + pad(from.getMonth() + 1) + '-' + pad(from.getDate()),
            to: to.getFullYear() + '-' + pad(to.getMonth() + 1) + '-' + pad(to.getDate()),
        };
    }

    function fetchEvents() {
        const range = monthRange(viewDate);
        const body = new URLSearchParams();
        body.set('action', 'opencomune_preview_availability');
        body.set('tour_id', String(tourId));
        body.set('from', range.from);
        body.set('to', range.to);
        body.set('rules_payload', JSON.stringify(rules));

        return fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
        }).then((res) => res.json());
    }

    function renderMonth(events) {
        if (!els.grid || !els.monthLabel) {
            return;
        }

        const y = viewDate.getFullYear();
        const m = viewDate.getMonth();
        els.monthLabel.textContent = viewDate.toLocaleDateString('it-IT', { month: 'long', year: 'numeric' });

        const firstDay = new Date(y, m, 1);
        const startPad = (firstDay.getDay() + 6) % 7;
        const daysInMonth = new Date(y, m + 1, 0).getDate();

        const byDate = {};
        (events || []).forEach((ev) => {
            const d = (ev.start || '').split('T')[0];
            if (!byDate[d]) {
                byDate[d] = [];
            }
            byDate[d].push(ev);
        });

        els.grid.innerHTML = '';
        weekdayLabels.forEach((label) => {
            const head = document.createElement('div');
            head.className = 'oc-av-grid__head';
            head.textContent = label;
            els.grid.appendChild(head);
        });

        for (let i = 0; i < startPad; i++) {
            const empty = document.createElement('div');
            empty.className = 'oc-av-grid__cell is-muted';
            els.grid.appendChild(empty);
        }

        const today = new Date().toISOString().slice(0, 10);
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = y + '-' + String(m + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const cell = document.createElement('div');
            cell.className = 'oc-av-grid__cell';
            if (dateStr === today) {
                cell.classList.add('is-today');
            }

            const dayEvents = byDate[dateStr] || [];
            if (dayEvents.length) {
                cell.classList.add('has-events');
                const dots = document.createElement('div');
                dots.className = 'oc-av-grid__dots';
                dayEvents.slice(0, 3).forEach((ev) => {
                    const dot = document.createElement('span');
                    dot.style.background = ev.color || '#2563EB';
                    dots.appendChild(dot);
                });
                cell.appendChild(dots);
            }

            const num = document.createElement('span');
            num.className = 'oc-av-grid__day';
            num.textContent = String(day);
            cell.appendChild(num);

            cell.addEventListener('click', () => openModal({ single_date: dateStr }));
            els.grid.appendChild(cell);
        }
    }

    function refresh() {
        return fetchEvents().then((json) => {
            const events = json.success ? (json.data.events || []) : [];
            const hasFuture = json.success ? !!json.data.has_future : rules.length > 0;
            if (els.alert) {
                els.alert.classList.toggle('is-hidden', hasFuture);
            }
            renderMonth(events);
            renderRulesList();
            syncPayload();
        });
    }

    function renderRulesList() {
        if (!els.rulesList) {
            return;
        }
        els.rulesList.innerHTML = '';
        if (!rules.length) {
            els.rulesList.innerHTML = '<li class="text-sm text-gray-500">' + (config.emptyRulesText || '') + '</li>';
            return;
        }
        rules.forEach((rule) => {
            const li = document.createElement('li');
            li.className = 'oc-av-rules-list__item';
            li.innerHTML =
                '<span class="oc-av-rules-list__swatch" style="background:' + (rule.color || '#EAB308') + '"></span>' +
                '<span class="flex-1"><strong>' + escapeHtml(rule.internal_label || rule.rule_type) + '</strong></span>' +
                '<button type="button" class="text-sm text-blue-600" data-edit="' + escapeHtml(rule.id) + '">Modifica</button> ' +
                '<button type="button" class="text-sm text-red-600" data-remove="' + escapeHtml(rule.id) + '">Rimuovi</button>';
            els.rulesList.appendChild(li);
        });

        els.rulesList.querySelectorAll('[data-edit]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const rule = rules.find((r) => r.id === btn.getAttribute('data-edit'));
                if (rule) {
                    openModal(rule);
                }
            });
        });
        els.rulesList.querySelectorAll('[data-remove]').forEach((btn) => {
            btn.addEventListener('click', () => {
                rules = rules.filter((r) => r.id !== btn.getAttribute('data-remove'));
                refresh();
            });
        });
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    }

    function openModal(prefill) {
        editingRuleId = prefill && prefill.id ? prefill.id : null;
        const modal = els.modal;
        if (!modal) {
            return;
        }

        document.getElementById('oc-av-rule-type').value = prefill.rule_type || 'weekly';
        document.getElementById('oc-av-effective-from').value = prefill.effective_from || '';
        document.getElementById('oc-av-effective-until').value = prefill.effective_until || prefill.date_to || '';
        document.getElementById('oc-av-date-from').value = prefill.date_from || '';
        document.getElementById('oc-av-date-to').value = prefill.date_to || '';
        document.getElementById('oc-av-single-date').value = prefill.single_date || '';
        document.getElementById('oc-av-min-pax').value = prefill.min_participants != null ? prefill.min_participants : 1;
        document.getElementById('oc-av-max-pax').value = prefill.max_participants != null ? prefill.max_participants : 15;
        document.getElementById('oc-av-internal-label').value = prefill.internal_label || '';
        document.getElementById('oc-av-all-times').checked = prefill.all_start_times !== false;

        weekdayKeys.forEach((key) => {
            const input = document.querySelector('.oc-av-weekday[data-day="' + key + '"]');
            if (input) {
                input.checked = (prefill.weekdays || []).indexOf(key) >= 0;
            }
        });

        document.querySelectorAll('.oc-av-color').forEach((btn) => {
            btn.classList.toggle('is-selected', btn.getAttribute('data-color') === (prefill.color || '#EAB308'));
        });

        renderStartTimeChecks(prefill.start_time_ids || []);
        toggleRuleTypeFields();
        modal.hidden = false;
        document.body.classList.add('oc-av-modal-open');
    }

    function closeModal() {
        if (!els.modal) {
            return;
        }
        els.modal.hidden = true;
        document.body.classList.remove('oc-av-modal-open');
        editingRuleId = null;
    }

    function renderStartTimeChecks(selectedIds) {
        const wrap = document.getElementById('oc-av-start-times');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = '';
        if (!startTimes.length) {
            wrap.innerHTML = '<p class="text-sm text-gray-500">Nessun orario di inizio definito nel tab Orario di inizio.</p>';
            return;
        }
        startTimes.forEach((st) => {
            const label = document.createElement('label');
            label.className = 'oc-av-start-time-chip';
            label.innerHTML =
                '<input type="checkbox" class="oc-av-start-time-id" value="' + escapeHtml(st.id) + '"> ' +
                escapeHtml(st.time);
            const input = label.querySelector('input');
            input.checked = selectedIds.indexOf(st.id) >= 0;
            wrap.appendChild(label);
        });
    }

    function collectRuleFromModal() {
        const weekdays = [];
        document.querySelectorAll('.oc-av-weekday:checked').forEach((el) => weekdays.push(el.getAttribute('data-day')));

        const months = [];
        document.querySelectorAll('.oc-av-month:checked').forEach((el) => months.push(parseInt(el.value, 10)));

        const startTimeIds = [];
        document.querySelectorAll('.oc-av-start-time-id:checked').forEach((el) => startTimeIds.push(el.value));

        let color = '#EAB308';
        document.querySelectorAll('.oc-av-color.is-selected').forEach((btn) => {
            color = btn.getAttribute('data-color');
        });

        return {
            id: editingRuleId || ('av_' + Date.now()),
            rule_type: document.getElementById('oc-av-rule-type').value,
            effective_from: document.getElementById('oc-av-effective-from').value,
            effective_until: document.getElementById('oc-av-effective-until').value,
            weekdays,
            months,
            date_from: document.getElementById('oc-av-date-from').value,
            date_to: document.getElementById('oc-av-date-to').value,
            single_date: document.getElementById('oc-av-single-date').value,
            min_participants: parseInt(document.getElementById('oc-av-min-pax').value || '0', 10) || 0,
            max_participants: parseInt(document.getElementById('oc-av-max-pax').value || '0', 10) || 0,
            all_start_times: document.getElementById('oc-av-all-times').checked,
            start_time_ids: startTimeIds,
            internal_label: document.getElementById('oc-av-internal-label').value.trim(),
            color,
        };
    }

    function toggleRuleTypeFields() {
        const type = document.getElementById('oc-av-rule-type').value;
        document.querySelectorAll('[data-rule-fields]').forEach((el) => {
            const types = (el.getAttribute('data-rule-fields') || '').split(',');
            el.classList.toggle('is-hidden', types.indexOf(type) === -1);
        });
    }

    document.getElementById('oc-av-add-rule')?.addEventListener('click', () => openModal({ rule_type: 'weekly', all_start_times: true, min_participants: 1, max_participants: 15 }));
    document.getElementById('oc-av-prev-month')?.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth() - 1); refresh(); });
    document.getElementById('oc-av-next-month')?.addEventListener('click', () => { viewDate.setMonth(viewDate.getMonth() + 1); refresh(); });
    document.getElementById('oc-av-this-month')?.addEventListener('click', () => { viewDate = new Date(); viewDate.setDate(1); refresh(); });

    document.getElementById('oc-av-rule-type')?.addEventListener('change', toggleRuleTypeFields);
    document.querySelectorAll('[data-av-close]').forEach((el) => el.addEventListener('click', closeModal));

    document.getElementById('oc-av-save-rule')?.addEventListener('click', () => {
        const rule = collectRuleFromModal();
        if (editingRuleId) {
            rules = rules.map((r) => (r.id === editingRuleId ? rule : r));
        } else {
            rules.push(rule);
        }
        closeModal();
        refresh();
    });

    document.querySelectorAll('.oc-av-color').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.oc-av-color').forEach((b) => b.classList.remove('is-selected'));
            btn.classList.add('is-selected');
        });
    });

    if (form) {
        form.addEventListener('submit', syncPayload);
    }

    refresh();
})();
