<?php
/**
 * Campi data e prenotazione per form esperienze partner.
 *
 * @var array $args
 */

$tour_date_mode = $args['tour_date_mode'] ?? 'single';
$tour_start_date = $args['tour_start_date'] ?? '';
$tour_end_date = $args['tour_end_date'] ?? '';
$mostra_form_prenotazione = $args['mostra_form_prenotazione'] ?? '1';
$end_date_visible = ($tour_date_mode === 'range');
?>

<div class="md:col-span-2">
    <span class="form-label">Tipo data <span class="form-required">*</span></span>
    <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem;">
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="radio" name="tour_date_mode" value="single" class="tour-date-mode-radio" <?php checked($tour_date_mode, 'single'); ?>>
            <span>Data singola</span>
        </label>
        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
            <input type="radio" name="tour_date_mode" value="range" class="tour-date-mode-radio" <?php checked($tour_date_mode, 'range'); ?>>
            <span>Periodo (data inizio e fine)</span>
        </label>
    </div>
</div>

<div>
    <label for="tour_start_date" class="form-label">
        <span id="tour-start-date-label"><?php echo $end_date_visible ? 'Data inizio' : 'Data esperienza'; ?></span>
        <span class="form-required">*</span>
    </label>
    <input type="date" id="tour_start_date" name="tour_start_date" class="form-input" required
           value="<?php echo esc_attr($tour_start_date); ?>">
</div>

<div id="tour-end-date-wrap" style="<?php echo $end_date_visible ? '' : 'display:none;'; ?>">
    <label for="tour_end_date" class="form-label">
        Data fine <span class="form-required">*</span>
    </label>
    <input type="date" id="tour_end_date" name="tour_end_date" class="form-input"
           value="<?php echo esc_attr($tour_end_date); ?>" <?php echo $end_date_visible ? 'required' : ''; ?>>
</div>

<div class="md:col-span-2">
    <span class="form-label">Prenotazione sulla pagina pubblica</span>
    <label style="display: flex; align-items: flex-start; gap: 0.5rem; margin-top: 0.5rem; cursor: pointer;">
        <input type="checkbox" name="mostra_form_prenotazione" value="1" style="margin-top: 0.2rem;"
            <?php checked($mostra_form_prenotazione, '1'); ?>>
        <span>
            Mostra il form di prenotazione nella pagina pubblica dell'esperienza
            <span style="display: block; font-size: 0.875rem; color: #6b7280; font-weight: normal; margin-top: 0.25rem;">
                Se disattivato, i visitatori non potranno prenotare direttamente dalla piattaforma (potrai indicare un link esterno nella sezione Punto di Incontro).
            </span>
        </span>
    </label>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modeRadios = document.querySelectorAll('.tour-date-mode-radio');
    const endWrap = document.getElementById('tour-end-date-wrap');
    const endInput = document.getElementById('tour_end_date');
    const startInput = document.getElementById('tour_start_date');
    const startLabel = document.getElementById('tour-start-date-label');
    const durationInput = document.getElementById('tour_duration');

    function updateDurationFromDates() {
        if (!durationInput || !startInput) {
            return;
        }

        const start = startInput.value;
        if (!start) {
            durationInput.value = '';
            return;
        }

        const selected = document.querySelector('.tour-date-mode-radio:checked');
        const isRange = selected && selected.value === 'range';
        const end = endInput ? endInput.value : '';

        if (!isRange || !end) {
            durationInput.value = '1 giorno';
            return;
        }

        const startDate = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');
        const diffDays = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24)) + 1;

        if (diffDays <= 0) {
            durationInput.value = '';
            return;
        }

        durationInput.value = diffDays === 1 ? '1 giorno' : diffDays + ' giorni';
    }

    function updateDateModeUI() {
        const selected = document.querySelector('.tour-date-mode-radio:checked');
        const isRange = selected && selected.value === 'range';

        if (endWrap) {
            endWrap.style.display = isRange ? '' : 'none';
        }
        if (endInput) {
            endInput.required = isRange;
            if (!isRange) {
                endInput.value = '';
            }
        }
        if (startLabel) {
            startLabel.textContent = isRange ? 'Data inizio' : 'Data esperienza';
        }

        updateDurationFromDates();
    }

    modeRadios.forEach(function(radio) {
        radio.addEventListener('change', updateDateModeUI);
    });

    if (startInput) {
        startInput.addEventListener('change', updateDurationFromDates);
        startInput.addEventListener('input', updateDurationFromDates);
    }
    if (endInput) {
        endInput.addEventListener('change', updateDurationFromDates);
        endInput.addEventListener('input', updateDurationFromDates);
    }

    const partnerForm = document.getElementById('nuova-esperienza-form') || document.getElementById('modifica-esperienza-form');
    if (partnerForm) {
        partnerForm.addEventListener('submit', updateDurationFromDates);
    }

    updateDateModeUI();
});
</script>
