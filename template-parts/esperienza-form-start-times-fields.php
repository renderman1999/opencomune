<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$start_times = opencomune_get_esperienza_start_times($post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tour_start_times_payload'])) {
    $decoded = json_decode(wp_unslash((string) $_POST['tour_start_times_payload']), true);
    if (is_array($decoded)) {
        $start_times = opencomune_normalize_start_times_list($decoded);
    }
}

$payload_json = wp_json_encode($start_times);
?>

<div class="oc-exp-start-times" id="oc-exp-start-times-root">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Quando inizia l’esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-start-times__lead">
        <?php esc_html_e('Partenze fisse dell’esperienza (es. 10:00, 15:00). Le date in cui ogni orario è vendibile si definiscono nel Calendario. Non usare questo passo per gli orari di apertura generici (vedi Orario di avvio).', 'opencomune'); ?>
    </p>

    <div class="oc-exp-start-times__table-wrap">
        <table class="oc-exp-start-times__table" aria-describedby="oc-exp-start-times-empty-msg">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col"><?php esc_html_e('Orario', 'opencomune'); ?></th>
                    <th scope="col"><?php esc_html_e('Durata', 'opencomune'); ?></th>
                    <th scope="col"><?php esc_html_e('Tariffe collegate', 'opencomune'); ?></th>
                    <th scope="col">
                        <?php esc_html_e('Tipi risorsa collegati', 'opencomune'); ?>
                        <span class="oc-exp-start-times__th-icon" aria-hidden="true">+</span>
                    </th>
                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Azioni', 'opencomune'); ?></span></th>
                </tr>
            </thead>
            <tbody id="oc-exp-start-times-tbody"></tbody>
        </table>
        <p id="oc-exp-start-times-empty-msg" class="oc-exp-start-times__empty<?php echo empty($start_times) ? '' : ' is-hidden'; ?>">
            <?php esc_html_e('Non ci sono orari di inizio impostati per questa esperienza.', 'opencomune'); ?>
        </p>
    </div>

    <button type="button" id="oc-exp-start-times-add" class="oc-exp-start-times__add-btn">
        <?php esc_html_e('+ Aggiungi orario di inizio', 'opencomune'); ?>
    </button>

    <input type="hidden" name="tour_start_times_payload" id="tour_start_times_payload" value="<?php echo esc_attr($payload_json); ?>">
</div>

<div id="oc-exp-start-times-modal" class="oc-exp-start-times-modal" hidden aria-hidden="true">
    <div class="oc-exp-start-times-modal__backdrop" data-start-times-close></div>
    <div class="oc-exp-start-times-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-exp-start-times-modal-title">
        <div class="oc-exp-start-times-modal__header">
            <h3 id="oc-exp-start-times-modal-title" class="oc-exp-start-times-modal__title">
                <?php esc_html_e('Aggiungi un nuovo orario di inizio', 'opencomune'); ?>
            </h3>
            <button type="button" class="oc-exp-start-times-modal__close" data-start-times-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>
        <div class="oc-exp-start-times-modal__body">
            <div class="oc-exp-start-times-modal__field">
                <label class="form-label" for="oc-st-hour"><?php esc_html_e('Orario di inizio', 'opencomune'); ?></label>
                <p class="oc-exp-inclusion__hint"><?php esc_html_e('Formato 24 ore (es. 10:00, 15:30).', 'opencomune'); ?></p>
                <div class="oc-exp-start-times-modal__time-row">
                    <span class="bi bi-clock oc-exp-start-times-modal__clock" aria-hidden="true"></span>
                    <select id="oc-st-hour" class="form-input" aria-label="<?php esc_attr_e('Ora (24 ore)', 'opencomune'); ?>">
                        <?php for ($h = 0; $h < 24; $h++) : ?>
                            <option value="<?php echo sprintf('%02d', $h); ?>" <?php selected($h, 10); ?>><?php echo sprintf('%02d', $h); ?></option>
                        <?php endfor; ?>
                    </select>
                    <span class="oc-exp-start-times-modal__time-sep" aria-hidden="true">:</span>
                    <select id="oc-st-minute" class="form-input" aria-label="<?php esc_attr_e('Minuti', 'opencomune'); ?>">
                        <?php for ($m = 0; $m < 60; $m += 5) : ?>
                            <option value="<?php echo sprintf('%02d', $m); ?>"><?php echo sprintf('%02d', $m); ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="oc-exp-start-times-modal__field">
                <label class="form-label" for="oc-st-duration-hours"><?php esc_html_e('Durata', 'opencomune'); ?></label>
                <div class="oc-exp-start-times-modal__duration">
                    <div>
                        <input type="number" id="oc-st-duration-hours" class="form-input" min="0" value="1">
                        <span class="oc-exp-start-times-modal__unit"><?php esc_html_e('Ore', 'opencomune'); ?></span>
                    </div>
                    <div>
                        <input type="number" id="oc-st-duration-minutes" class="form-input" min="0" max="59" value="0">
                        <span class="oc-exp-start-times-modal__unit"><?php esc_html_e('Minuti', 'opencomune'); ?></span>
                    </div>
                </div>
            </div>

            <div class="oc-exp-start-times-modal__field">
                <label class="form-label" for="oc-st-internal-label"><?php esc_html_e('Etichetta interna (solo per te)', 'opencomune'); ?></label>
                <p class="oc-exp-inclusion__hint">
                    <?php esc_html_e('Utile se hai più partenze allo stesso orario. Solo la tua organizzazione la vede.', 'opencomune'); ?>
                </p>
                <input type="text" id="oc-st-internal-label" class="form-input" value="">
            </div>

            <div class="oc-exp-start-times-modal__field">
                <label class="form-label" for="oc-st-external-label"><?php esc_html_e('Etichetta esterna (visibile ai clienti)', 'opencomune'); ?></label>
                <p class="oc-exp-inclusion__hint">
                    <?php esc_html_e('Mostrata accanto all’orario di partenza nei widget e nelle prenotazioni.', 'opencomune'); ?>
                </p>
                <input type="text" id="oc-st-external-label" class="form-input" maxlength="24" value="">
                <span id="oc-st-external-counter" class="oc-exp-start-times-modal__counter">0/24</span>
            </div>

            <details class="oc-exp-start-times-modal__advanced">
                <summary><?php esc_html_e('Impostazioni avanzate', 'opencomune'); ?></summary>
                <p class="oc-exp-inclusion__hint mt-2">
                    <?php esc_html_e('Tariffe e tipi risorsa collegati saranno disponibili in un aggiornamento successivo.', 'opencomune'); ?>
                </p>
            </details>
        </div>
        <div class="oc-exp-start-times-modal__footer">
            <button type="button" class="btn-secondary" data-start-times-close><?php esc_html_e('Annulla', 'opencomune'); ?></button>
            <button type="button" id="oc-exp-start-times-save" class="btn-primary"><?php esc_html_e('Aggiungi orario', 'opencomune'); ?></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('oc-exp-start-times-root');
    const modal = document.getElementById('oc-exp-start-times-modal');
    const tbody = document.getElementById('oc-exp-start-times-tbody');
    const payloadInput = document.getElementById('tour_start_times_payload');
    const emptyMsg = document.getElementById('oc-exp-start-times-empty-msg');
    const addBtn = document.getElementById('oc-exp-start-times-add');
    const saveBtn = document.getElementById('oc-exp-start-times-save');
    const form = document.getElementById('nuova-esperienza-form');
    const externalLabel = document.getElementById('oc-st-external-label');
    const externalCounter = document.getElementById('oc-st-external-counter');

    if (!root || !modal || !tbody || !payloadInput) {
        return;
    }

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    let items = [];
    try {
        items = JSON.parse(payloadInput.value || '[]');
        if (!Array.isArray(items)) {
            items = [];
        }
    } catch (e) {
        items = [];
    }

    let editingId = null;

    function formatTime24(hour, minute) {
        let h = parseInt(hour, 10);
        if (Number.isNaN(h) || h < 0) {
            h = 0;
        }
        if (h > 23) {
            h = 23;
        }
        const hStr = String(h).padStart(2, '0');
        let m = parseInt(minute, 10);
        if (Number.isNaN(m) || m < 0) {
            m = 0;
        }
        if (m > 59) {
            m = 59;
        }
        m = Math.round(m / 5) * 5;
        if (m > 59) {
            m = 55;
        }
        return hStr + ':' + String(m).padStart(2, '0');
    }

    function parseTime24(time24) {
        const parts = String(time24 || '10:00').split(':');
        let hour = parseInt(parts[0], 10);
        if (Number.isNaN(hour) || hour < 0) {
            hour = 0;
        }
        if (hour > 23) {
            hour = 23;
        }
        let minute = parseInt((parts[1] || '00').substring(0, 2), 10);
        if (Number.isNaN(minute) || minute < 0) {
            minute = 0;
        }
        if (minute > 59) {
            minute = 59;
        }
        minute = Math.round(minute / 5) * 5;
        if (minute > 59) {
            minute = 55;
        }
        return {
            hour: String(hour).padStart(2, '0'),
            minute: String(minute).padStart(2, '0'),
        };
    }

    function formatTimeItalianDisplay(time24) {
        const t = parseTime24(time24);
        return t.hour + ':' + t.minute;
    }

    function durationLabel(hours, minutes) {
        const h = parseInt(hours, 10) || 0;
        const min = parseInt(minutes, 10) || 0;
        const chunks = [];
        if (h > 0) {
            chunks.push(h === 1 ? '1 ora' : h + ' ore');
        }
        if (min > 0) {
            chunks.push(min === 1 ? '1 minuto' : min + ' minuti');
        }
        return chunks.length ? chunks.join(' ') : '0 minuti';
    }

    function syncPayload() {
        payloadInput.value = JSON.stringify(items);
        if (emptyMsg) {
            emptyMsg.classList.toggle('is-hidden', items.length > 0);
        }
    }

    function renderTable() {
        tbody.innerHTML = '';
        items.forEach(function (row, index) {
            const tr = document.createElement('tr');
            tr.innerHTML =
                '<td>' + (index + 1) + '</td>' +
                '<td>' + escapeHtml(formatTimeItalianDisplay(row.time)) + (row.external_label ? ' <span class="text-gray-500">(' + escapeHtml(row.external_label) + ')</span>' : '') + '</td>' +
                '<td>' + escapeHtml(durationLabel(row.duration_hours, row.duration_minutes)) + '</td>' +
                '<td class="text-gray-400">—</td>' +
                '<td class="text-gray-400">—</td>' +
                '<td class="oc-exp-start-times__actions">' +
                    '<button type="button" class="text-sm text-blue-600 oc-st-edit" data-id="' + escapeHtml(row.id) + '">Modifica</button> ' +
                    '<button type="button" class="text-sm text-red-600 oc-st-remove" data-id="' + escapeHtml(row.id) + '">Rimuovi</button>' +
                '</td>';
            tbody.appendChild(tr);
        });
        syncPayload();
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function resetModal() {
        editingId = null;
        document.getElementById('oc-st-hour').value = '10';
        document.getElementById('oc-st-minute').value = '00';
        document.getElementById('oc-st-duration-hours').value = '1';
        document.getElementById('oc-st-duration-minutes').value = '0';
        document.getElementById('oc-st-internal-label').value = '';
        document.getElementById('oc-st-external-label').value = '';
        updateExternalCounter();
        document.getElementById('oc-exp-start-times-modal-title').textContent = 'Aggiungi un nuovo orario di inizio';
        if (saveBtn) {
            saveBtn.textContent = 'Aggiungi orario';
        }
    }

    function fillModalFromRow(row) {
        const t = parseTime24(row.time);
        document.getElementById('oc-st-hour').value = t.hour;
        document.getElementById('oc-st-minute').value = t.minute;
        document.getElementById('oc-st-duration-hours').value = String(row.duration_hours || 0);
        document.getElementById('oc-st-duration-minutes').value = String(row.duration_minutes || 0);
        document.getElementById('oc-st-internal-label').value = row.internal_label || '';
        document.getElementById('oc-st-external-label').value = row.external_label || '';
        updateExternalCounter();
    }

    function openModal() {
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('oc-exp-start-times-modal-open');
    }

    function closeModal() {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('oc-exp-start-times-modal-open');
        resetModal();
    }

    function updateExternalCounter() {
        if (!externalLabel || !externalCounter) {
            return;
        }
        externalCounter.textContent = externalLabel.value.length + '/24';
    }

    function collectModalRow() {
        const time = formatTime24(
            document.getElementById('oc-st-hour').value,
            document.getElementById('oc-st-minute').value
        );
        const hours = Math.max(0, parseInt(document.getElementById('oc-st-duration-hours').value || '0', 10) || 0);
        const minutes = Math.max(0, Math.min(59, parseInt(document.getElementById('oc-st-duration-minutes').value || '0', 10) || 0));
        let external = (document.getElementById('oc-st-external-label').value || '').trim();
        if (external.length > 24) {
            external = external.substring(0, 24);
        }

        return {
            id: editingId || ('st_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8)),
            time: time,
            duration_hours: hours,
            duration_minutes: minutes,
            internal_label: (document.getElementById('oc-st-internal-label').value || '').trim(),
            external_label: external,
            linked_rates: [],
            linked_resource_types: []
        };
    }

    if (addBtn) {
        addBtn.addEventListener('click', function () {
            resetModal();
            openModal();
        });
    }

    modal.querySelectorAll('[data-start-times-close]').forEach(function (el) {
        el.addEventListener('click', closeModal);
    });

    if (saveBtn) {
        saveBtn.addEventListener('click', function () {
            const row = collectModalRow();
            if (editingId) {
                items = items.map(function (item) {
                    return item.id === editingId ? row : item;
                });
            } else {
                items.push(row);
            }
            renderTable();
            closeModal();
        });
    }

    tbody.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('.oc-st-remove');
        if (removeBtn) {
            const id = removeBtn.getAttribute('data-id');
            items = items.filter(function (item) { return item.id !== id; });
            renderTable();
            return;
        }
        const editBtn = event.target.closest('.oc-st-edit');
        if (editBtn) {
            const id = editBtn.getAttribute('data-id');
            const row = items.find(function (item) { return item.id === id; });
            if (!row) {
                return;
            }
            editingId = id;
            fillModalFromRow(row);
            document.getElementById('oc-exp-start-times-modal-title').textContent = 'Modifica orario di inizio';
            if (saveBtn) {
                saveBtn.textContent = 'Salva orario';
            }
            openModal();
        }
    });

    if (externalLabel) {
        externalLabel.addEventListener('input', updateExternalCounter);
    }

    if (form) {
        form.addEventListener('submit', function () {
            syncPayload();
        });
    }

    renderTable();
});
</script>
