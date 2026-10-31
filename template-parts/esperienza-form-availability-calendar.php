<?php
/**
 * Calendario disponibilita (solo modifica esperienza). Fonte: tour_availability_rules.
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
if ($post_id <= 0) {
    return;
}

$form_id = isset($args['form_id']) ? (string) $args['form_id'] : 'modifica-esperienza-form';
opencomune_enqueue_availability_calendar_editor($post_id, $form_id);

$rules = opencomune_get_esperienza_availability_rules($post_id);
$rule_types = opencomune_availability_rule_type_definitions();
$colors = opencomune_availability_color_palette();
$weekday_keys = opencomune_availability_weekday_keys();
$weekday_short = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
$month_labels = [];
for ($m = 1; $m <= 12; $m++) {
    $month_labels[$m] = date_i18n('F', mktime(12, 0, 0, $m, 1));
}
$has_future = opencomune_availability_has_future($post_id);
?>

<style>
.oc-av-grid {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 4px;
}
.oc-av-grid__head {
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    color: #6b7280;
    padding: 0.25rem;
}
.oc-av-grid__cell {
    position: relative;
    min-height: 3.25rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    cursor: pointer;
    background: #fff;
}
.oc-av-grid__cell.is-muted { background: #f9fafb; border-color: transparent; cursor: default; }
.oc-av-grid__cell.is-today { border-color: #2563eb; }
.oc-av-grid__cell.has-events { background: #f0f9ff; }
.oc-av-grid__day {
    position: absolute;
    top: 4px;
    right: 6px;
    font-size: 0.75rem;
    color: #374151;
}
.oc-av-grid__dots {
    display: flex;
    gap: 3px;
    padding: 1.5rem 4px 4px;
    flex-wrap: wrap;
}
.oc-av-grid__dots span {
    width: 6px;
    height: 6px;
    border-radius: 999px;
    display: block;
}
.oc-av-rules-list { list-style: none; padding: 0; margin: 0; }
.oc-av-rules-list__item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f3f4f6;
}
.oc-av-rules-list__swatch {
    width: 12px;
    height: 12px;
    border-radius: 3px;
    flex-shrink: 0;
}
.oc-av-modal {
    position: fixed;
    inset: 0;
    z-index: 100050;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.45);
    padding: 1rem;
}
.oc-av-modal[hidden] { display: none !important; }
.oc-av-modal__panel {
    background: #fff;
    border-radius: 12px;
    max-width: 32rem;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}
.oc-av-weekdays, .oc-av-months { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.oc-av-weekday-pill, .oc-av-month-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.8125rem;
    padding: 0.35rem 0.6rem;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    cursor: pointer;
}
.oc-av-colors { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.oc-av-color {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    border: 2px solid transparent;
    cursor: pointer;
}
.oc-av-color.is-selected { border-color: #111827; box-shadow: 0 0 0 2px #fff inset; }
.oc-av-start-times { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.oc-av-start-time-chip {
    font-size: 0.8125rem;
    padding: 0.35rem 0.6rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}
[data-rule-fields].is-hidden { display: none !important; }
#oc-av-alert.is-hidden { display: none; }
body.oc-av-modal-open { overflow: hidden; }
</style>

<div class="form-section p-6 mt-8" id="oc-availability-calendar-root">
    <h2 class="section-title">
        <i class="bi bi-calendar3 mr-2"></i>
        <?php esc_html_e('Calendario disponibilita', 'opencomune'); ?>
    </h2>
    <p class="text-sm text-gray-600 mb-4">
        <?php esc_html_e('Definisci quando l\'esperienza e prenotabile. Le regole si espandono automaticamente per le prenotazioni sul sito (nessuna duplicazione con la tabella legacy).', 'opencomune'); ?>
    </p>

    <input type="hidden" name="tour_availability_rules_payload" id="tour_availability_rules_payload" value="<?php echo esc_attr(wp_json_encode($rules)); ?>">

    <div id="oc-av-alert" class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 <?php echo $has_future ? 'is-hidden' : ''; ?>">
        <?php esc_html_e('Nessuna disponibilita futura: aggiungi almeno una regola valida.', 'opencomune'); ?>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2">
            <button type="button" class="btn-secondary text-sm" id="oc-av-prev-month" aria-label="<?php esc_attr_e('Mese precedente', 'opencomune'); ?>">&larr;</button>
            <span class="font-semibold capitalize" id="oc-av-month-label"></span>
            <button type="button" class="btn-secondary text-sm" id="oc-av-next-month" aria-label="<?php esc_attr_e('Mese successivo', 'opencomune'); ?>">&rarr;</button>
            <button type="button" class="text-sm text-blue-600 underline ml-2" id="oc-av-this-month"><?php esc_html_e('Oggi', 'opencomune'); ?></button>
        </div>
        <button type="button" class="btn-primary text-sm" id="oc-av-add-rule">
            <i class="bi bi-plus-circle mr-1"></i>
            <?php esc_html_e('Aggiungi regola', 'opencomune'); ?>
        </button>
    </div>

    <div id="oc-av-grid" class="oc-av-grid mb-6" aria-live="polite"></div>

    <h3 class="text-sm font-semibold text-gray-700 mb-2"><?php esc_html_e('Regole attive', 'opencomune'); ?></h3>
    <ul id="oc-av-rules-list" class="oc-av-rules-list"></ul>
</div>

<div id="oc-av-modal" class="oc-av-modal" hidden role="dialog" aria-modal="true" aria-labelledby="oc-av-modal-title">
    <div class="oc-av-modal__panel">
        <div class="flex justify-between items-start mb-4">
            <h3 id="oc-av-modal-title" class="text-lg font-semibold"><?php esc_html_e('Regola di disponibilita', 'opencomune'); ?></h3>
            <button type="button" class="text-gray-500 text-xl leading-none" data-av-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>

        <div class="space-y-4">
            <div>
                <label class="form-label" for="oc-av-rule-type"><?php esc_html_e('Tipo', 'opencomune'); ?></label>
                <select id="oc-av-rule-type" class="form-input">
                    <?php foreach ($rule_types as $type_key => $type_label) : ?>
                        <option value="<?php echo esc_attr($type_key); ?>"><?php echo esc_html($type_label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="form-label" for="oc-av-internal-label"><?php esc_html_e('Etichetta interna (opzionale)', 'opencomune'); ?></label>
                <input type="text" id="oc-av-internal-label" class="form-input" maxlength="120">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" data-rule-fields="weekly,yearly_months,date_range">
                <div>
                    <label class="form-label" for="oc-av-effective-from"><?php esc_html_e('Valida dal', 'opencomune'); ?></label>
                    <input type="date" id="oc-av-effective-from" class="form-input">
                </div>
                <div>
                    <label class="form-label" for="oc-av-effective-until"><?php esc_html_e('Valida fino al', 'opencomune'); ?></label>
                    <input type="date" id="oc-av-effective-until" class="form-input">
                </div>
            </div>

            <div data-rule-fields="weekly">
                <span class="form-label"><?php esc_html_e('Giorni della settimana', 'opencomune'); ?></span>
                <div class="oc-av-weekdays mt-2">
                    <?php foreach ($weekday_keys as $i => $day_key) : ?>
                        <label class="oc-av-weekday-pill">
                            <input type="checkbox" class="oc-av-weekday" data-day="<?php echo esc_attr($day_key); ?>">
                            <?php echo esc_html($weekday_short[$i] ?? $day_key); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div data-rule-fields="yearly_months">
                <span class="form-label"><?php esc_html_e('Mesi', 'opencomune'); ?></span>
                <div class="oc-av-months mt-2">
                    <?php foreach ($month_labels as $num => $label) : ?>
                        <label class="oc-av-month-pill">
                            <input type="checkbox" class="oc-av-month" value="<?php echo (int) $num; ?>">
                            <?php echo esc_html($label); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3" data-rule-fields="date_range">
                <div>
                    <label class="form-label" for="oc-av-date-from"><?php esc_html_e('Data inizio intervallo', 'opencomune'); ?></label>
                    <input type="date" id="oc-av-date-from" class="form-input">
                </div>
                <div>
                    <label class="form-label" for="oc-av-date-to"><?php esc_html_e('Data fine intervallo', 'opencomune'); ?></label>
                    <input type="date" id="oc-av-date-to" class="form-input">
                </div>
            </div>

            <div data-rule-fields="single_date">
                <label class="form-label" for="oc-av-single-date"><?php esc_html_e('Data', 'opencomune'); ?></label>
                <input type="date" id="oc-av-single-date" class="form-input">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label" for="oc-av-min-pax"><?php esc_html_e('Min partecipanti', 'opencomune'); ?></label>
                    <input type="number" min="0" id="oc-av-min-pax" class="form-input" value="1">
                </div>
                <div>
                    <label class="form-label" for="oc-av-max-pax"><?php esc_html_e('Max partecipanti', 'opencomune'); ?></label>
                    <input type="number" min="0" id="oc-av-max-pax" class="form-input" value="15">
                </div>
            </div>

            <div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" id="oc-av-all-times" checked>
                    <?php esc_html_e('Tutti gli orari di inizio configurati', 'opencomune'); ?>
                </label>
            </div>

            <div id="oc-av-start-times-wrap" data-rule-fields="weekly,yearly_months,date_range,single_date">
                <span class="form-label"><?php esc_html_e('Orari di inizio inclusi', 'opencomune'); ?></span>
                <div id="oc-av-start-times" class="oc-av-start-times mt-2"></div>
            </div>

            <div>
                <span class="form-label"><?php esc_html_e('Colore in calendario', 'opencomune'); ?></span>
                <div class="oc-av-colors mt-2">
                    <?php foreach ($colors as $hex) : ?>
                        <button type="button" class="oc-av-color<?php echo $hex === '#EAB308' ? ' is-selected' : ''; ?>" data-color="<?php echo esc_attr($hex); ?>" style="background:<?php echo esc_attr($hex); ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="flex justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
            <button type="button" class="btn-secondary" data-av-close><?php esc_html_e('Annulla', 'opencomune'); ?></button>
            <button type="button" class="btn-primary" id="oc-av-save-rule"><?php esc_html_e('Salva regola', 'opencomune'); ?></button>
        </div>
    </div>
</div>
