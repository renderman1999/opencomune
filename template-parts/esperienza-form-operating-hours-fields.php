<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$config = opencomune_get_esperienza_operating_hours($post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $config['enabled'] = !empty($_POST['tour_operating_hours_enabled']);
    if (!empty($_POST['tour_operating_hours_payload'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['tour_operating_hours_payload']), true);
        if (is_array($decoded)) {
            $normalized = opencomune_normalize_operating_hours($decoded);
            $config['default'] = $normalized['default'];
            $config['seasonal'] = $normalized['seasonal'];
        }
    }
}

$payload_json = wp_json_encode([
    'default' => $config['default'],
    'seasonal' => $config['seasonal'],
]);
?>

<div class="oc-exp-operating-hours" id="oc-exp-operating-hours-root">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('L’esperienza è offerta solo in orari specifici?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-operating-hours__lead">
        <?php esc_html_e('Orari di apertura o di servizio (utile per attrazioni o pass). Per tour con partenze programmate usa Orario di inizio e Calendario; queste informazioni compaiono anche sul biglietto.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-operating-hours__enable-row">
        <div>
            <span class="oc-exp-operating-hours__enable-title"><?php esc_html_e('Abilita orari di servizio', 'opencomune'); ?></span>
            <p class="oc-exp-inclusion__hint mb-0">
                <?php esc_html_e('Mostra gli orari di servizio per questa esperienza nelle pagine prodotto.', 'opencomune'); ?>
            </p>
        </div>
        <label class="oc-exp-switch">
            <input type="checkbox" id="tour_operating_hours_enabled" name="tour_operating_hours_enabled" value="1" <?php checked($config['enabled']); ?>>
            <span class="oc-exp-switch__track" aria-hidden="true"></span>
        </label>
    </div>

    <div id="oc-exp-oh-config" class="oc-exp-oh-config<?php echo $config['enabled'] ? '' : ' is-hidden'; ?>">
        <div class="oc-exp-oh-card">
            <h3 class="oc-exp-oh-card__title"><?php esc_html_e('Orari di servizio predefiniti', 'opencomune'); ?></h3>
            <p class="oc-exp-inclusion__hint">
                <?php esc_html_e('Questi orari valgono sempre, salvo override con periodi stagionali sotto.', 'opencomune'); ?>
            </p>
            <div class="oc-exp-oh-card__body" data-oh-section="default">
                <?php opencomune_render_operating_hours_days_list($config['default']); ?>
            </div>
        </div>

        <div id="oc-exp-oh-seasonal-list" class="oc-exp-oh-seasonal-list">
            <?php foreach ($config['seasonal'] as $index => $seasonal) : ?>
                <div class="oc-exp-oh-card oc-exp-oh-seasonal" data-seasonal-block>
                    <div class="oc-exp-oh-seasonal__header">
                        <h3 class="oc-exp-oh-card__title mb-0"><?php esc_html_e('Orari stagionali', 'opencomune'); ?></h3>
                        <button type="button" class="oc-exp-oh-seasonal__remove text-sm text-red-600">
                            <?php esc_html_e('Rimuovi', 'opencomune'); ?>
                        </button>
                    </div>
                    <div class="oc-exp-oh-seasonal__meta">
                        <div>
                            <label class="form-label"><?php esc_html_e('Etichetta', 'opencomune'); ?></label>
                            <input type="text" class="form-input oc-exp-oh-seasonal-label" value="<?php echo esc_attr($seasonal['label']); ?>" placeholder="<?php esc_attr_e('es. Estate', 'opencomune'); ?>">
                        </div>
                        <div>
                            <label class="form-label"><?php esc_html_e('Data inizio', 'opencomune'); ?></label>
                            <input type="date" class="form-input oc-exp-oh-seasonal-start" value="<?php echo esc_attr($seasonal['start_date']); ?>">
                        </div>
                        <div>
                            <label class="form-label"><?php esc_html_e('Data fine', 'opencomune'); ?></label>
                            <input type="date" class="form-input oc-exp-oh-seasonal-end" value="<?php echo esc_attr($seasonal['end_date']); ?>">
                        </div>
                    </div>
                    <div class="oc-exp-oh-card__body" data-oh-section="seasonal">
                        <?php opencomune_render_operating_hours_days_list($seasonal['days']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" id="oc-exp-oh-add-seasonal" class="oc-exp-oh-add-seasonal btn-primary">
            <?php esc_html_e('Aggiungi orari stagionali', 'opencomune'); ?>
        </button>
    </div>

    <input type="hidden" name="tour_operating_hours_payload" id="tour_operating_hours_payload" value="<?php echo esc_attr($payload_json); ?>">
</div>

<template id="oc-exp-oh-seasonal-template">
    <div class="oc-exp-oh-card oc-exp-oh-seasonal" data-seasonal-block>
        <div class="oc-exp-oh-seasonal__header">
            <h3 class="oc-exp-oh-card__title mb-0"><?php esc_html_e('Orari stagionali', 'opencomune'); ?></h3>
            <button type="button" class="oc-exp-oh-seasonal__remove text-sm text-red-600">
                <?php esc_html_e('Rimuovi', 'opencomune'); ?>
            </button>
        </div>
        <div class="oc-exp-oh-seasonal__meta">
            <div>
                <label class="form-label"><?php esc_html_e('Etichetta', 'opencomune'); ?></label>
                <input type="text" class="form-input oc-exp-oh-seasonal-label" value="" placeholder="<?php esc_attr_e('es. Estate', 'opencomune'); ?>">
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Data inizio', 'opencomune'); ?></label>
                <input type="date" class="form-input oc-exp-oh-seasonal-start" value="">
            </div>
            <div>
                <label class="form-label"><?php esc_html_e('Data fine', 'opencomune'); ?></label>
                <input type="date" class="form-input oc-exp-oh-seasonal-end" value="">
            </div>
        </div>
        <div class="oc-exp-oh-card__body" data-oh-section="seasonal">
            <?php opencomune_render_operating_hours_days_list(opencomune_get_default_operating_hours_day_slots()); ?>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.getElementById('oc-exp-operating-hours-root');
    if (!root) {
        return;
    }

    const enableToggle = document.getElementById('tour_operating_hours_enabled');
    const configPanel = document.getElementById('oc-exp-oh-config');
    const payloadInput = document.getElementById('tour_operating_hours_payload');
    const seasonalList = document.getElementById('oc-exp-oh-seasonal-list');
    const addSeasonalBtn = document.getElementById('oc-exp-oh-add-seasonal');
    const seasonalTemplate = document.getElementById('oc-exp-oh-seasonal-template');
    const form = document.getElementById('nuova-esperienza-form');

    function bindDayRow(row) {
        const checkbox = row.querySelector('.oc-exp-oh-day-enabled');
        if (!checkbox || checkbox.dataset.bound === '1') {
            return;
        }
        checkbox.dataset.bound = '1';
        checkbox.addEventListener('change', function () {
            row.classList.toggle('is-active', checkbox.checked);
        });
    }

    function bindDayRows(scope) {
        (scope || root).querySelectorAll('.oc-exp-oh-day').forEach(bindDayRow);
    }

    function readDaysFromSection(sectionEl) {
        const days = {};
        sectionEl.querySelectorAll('.oc-exp-oh-day').forEach(function (row) {
            const key = row.getAttribute('data-day');
            if (!key) {
                return;
            }
            days[key] = {
                enabled: !!row.querySelector('.oc-exp-oh-day-enabled')?.checked,
                open: row.querySelector('.oc-exp-oh-day-open')?.value || '',
                close: row.querySelector('.oc-exp-oh-day-close')?.value || ''
            };
        });
        return days;
    }

    function collectOperatingHoursPayload() {
        const defaultSection = root.querySelector('[data-oh-section="default"]');
        const payload = {
            default: defaultSection ? readDaysFromSection(defaultSection) : {},
            seasonal: []
        };

        root.querySelectorAll('[data-seasonal-block]').forEach(function (block) {
            const daysSection = block.querySelector('[data-oh-section="seasonal"]');
            payload.seasonal.push({
                label: block.querySelector('.oc-exp-oh-seasonal-label')?.value || '',
                start_date: block.querySelector('.oc-exp-oh-seasonal-start')?.value || '',
                end_date: block.querySelector('.oc-exp-oh-seasonal-end')?.value || '',
                days: daysSection ? readDaysFromSection(daysSection) : {}
            });
        });

        return payload;
    }

    function syncPayload() {
        if (!payloadInput) {
            return;
        }
        payloadInput.value = JSON.stringify(collectOperatingHoursPayload());
    }

    if (enableToggle && configPanel) {
        enableToggle.addEventListener('change', function () {
            configPanel.classList.toggle('is-hidden', !enableToggle.checked);
        });
    }

    bindDayRows(root);

    if (addSeasonalBtn && seasonalList && seasonalTemplate) {
        addSeasonalBtn.addEventListener('click', function () {
            const clone = seasonalTemplate.content.cloneNode(true);
            seasonalList.appendChild(clone);
            bindDayRows(seasonalList.lastElementChild);
            syncPayload();
        });
    }

    root.addEventListener('click', function (event) {
        const removeBtn = event.target.closest('.oc-exp-oh-seasonal__remove');
        if (!removeBtn) {
            return;
        }
        const block = removeBtn.closest('[data-seasonal-block]');
        if (block) {
            block.remove();
            syncPayload();
        }
    });

    root.addEventListener('change', syncPayload);
    root.addEventListener('input', syncPayload);

    if (form) {
        form.addEventListener('submit', function () {
            syncPayload();
        });
    }

    syncPayload();
});
</script>
