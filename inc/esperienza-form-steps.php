<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_esperienza_form_step_groups() {
    return [
        [
            'id' => 'summary',
            'label' => __('Riepilogo', 'opencomune'),
            'requires_saved' => true,
            'default_open' => true,
            'steps' => [
                [
                    'id' => 'summary',
                    'label' => __('Riepilogo', 'opencomune'),
                ],
            ],
        ],
        [
            'id' => 'overview',
            'label' => __('Panoramica', 'opencomune'),
            'default_open' => true,
            'steps' => [
                [
                    'id' => 'base',
                    'label' => __('Informazioni principali', 'opencomune'),
                ],
            ],
        ],
        [
            'id' => 'experience',
            'label' => __('Esperienza', 'opencomune'),
            'default_open' => true,
            'steps' => [
                ['id' => 'duration', 'label' => __('Durata', 'opencomune')],
                ['id' => 'details', 'label' => __('Dettagli e contenuti', 'opencomune')],
                ['id' => 'category-tags', 'label' => __('Categoria e tag', 'opencomune')],
                ['id' => 'media', 'label' => __('Media', 'opencomune')],
                ['id' => 'location', 'label' => __('Location', 'opencomune')],
            ],
        ],
        [
            'id' => 'availability',
            'label' => __('Disponibilità', 'opencomune'),
            'default_open' => true,
            'steps' => [
                ['id' => 'booking-schedule', 'label' => __('Orario, data o pass', 'opencomune')],
                ['id' => 'operating-hours', 'label' => __('Orario di avvio', 'opencomune')],
                ['id' => 'start-times', 'label' => __('Orario di inizio', 'opencomune')],
                ['id' => 'capacity', 'label' => __('Capacità', 'opencomune')],
                [
                    'id' => 'availability',
                    'label' => __('Calendario', 'opencomune'),
                    'requires_saved' => true,
                ],
            ],
        ],
        [
            'id' => 'pricing',
            'label' => __('Prezzo', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'pricing', 'label' => __('Categorie prezzo', 'opencomune')],
            ],
        ],
        [
            'id' => 'inclusion',
            'label' => __('Inclusioni e requisiti', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'inclusion', 'label' => __('Inclusione', 'opencomune')],
                ['id' => 'exclusion', 'label' => __('Esclusione', 'opencomune')],
                ['id' => 'before-start', 'label' => __('Prima di iniziare', 'opencomune')],
                ['id' => 'what-to-bring', 'label' => __('Cosa portare', 'opencomune')],
            ],
        ],
        [
            'id' => 'meeting_pickup',
            'label' => __('Punto di incontro e prelievo', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'pickup-arrival', 'label' => __('Come arrivare', 'opencomune')],
                ['id' => 'meeting-points', 'label' => __('Punto di incontro', 'opencomune')],
                ['id' => 'itinerary', 'label' => __('Itinerario', 'opencomune')],
            ],
        ],
        [
            'id' => 'experience_settings',
            'label' => __('Impostazione esperienza', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'experience-settings', 'label' => __('Impostazioni prodotto', 'opencomune')],
                ['id' => 'booking-channel', 'label' => __('Prenotazione e pagamento', 'opencomune')],
            ],
        ],
        [
            'id' => 'tickets',
            'label' => __('Biglietti', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'tickets', 'label' => __('Impostazioni biglietto', 'opencomune')],
            ],
        ],
        [
            'id' => 'identification',
            'label' => __('Identificazione esperienza', 'opencomune'),
            'default_open' => false,
            'steps' => [
                ['id' => 'experience-identification', 'label' => __('Identificatori interni', 'opencomune')],
            ],
        ],
        [
            'id' => 'advanced',
            'label' => __('Avanzate', 'opencomune'),
            'default_open' => false,
            'optional_group' => true,
            'steps' => [
                ['id' => 'allegati', 'label' => __('Allegati', 'opencomune')],
                ['id' => 'policies', 'label' => __('Politiche', 'opencomune')],
            ],
        ],
    ];
}

function opencomune_get_esperienza_form_flat_steps($post_id = 0) {
    $post_id = (int) $post_id;
    $flat = [];
    foreach (opencomune_get_esperienza_form_step_groups() as $group) {
        if (!empty($group['requires_saved']) && $post_id <= 0) {
            continue;
        }
        foreach ($group['steps'] as $step) {
            if (!empty($step['requires_saved']) && $post_id <= 0) {
                continue;
            }
            $flat[] = array_merge($step, ['group_id' => $group['id']]);
        }
    }

    return $flat;
}

/**
 * Step con campi obbligatori per il salvataggio (indicatori rossi in sidebar se incompleti).
 *
 * @param int $post_id Esperienza salvata (0 = creazione).
 * @return string[]
 */
function opencomune_get_esperienza_form_required_step_ids($post_id = 0) {
    $required = ['base', 'duration', 'category-tags', 'media', 'experience-settings'];

    if ((int) $post_id > 0) {
        $required[] = 'availability';
    }

    return $required;
}

/**
 * Anteprima singola immagine nella galleria del wizard esperienza.
 */
function opencomune_esperienza_wizard_gallery_preview_item($attachment_id) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return;
    }
    $gallery_thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
    if (!$gallery_thumb) {
        return;
    }
    ?>
    <div class="oc-exp-gallery-preview__item" data-id="<?php echo $attachment_id; ?>">
        <button type="button" class="oc-exp-gallery-preview__remove" aria-label="<?php esc_attr_e('Rimuovi immagine', 'opencomune'); ?>">
            <i class="bi bi-trash3" aria-hidden="true"></i>
        </button>
        <img src="<?php echo esc_url($gallery_thumb); ?>" alt="" class="oc-exp-gallery-preview__img">
    </div>
    <?php
}

function opencomune_enqueue_esperienza_wizard_assets($config = []) {
    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';

    wp_enqueue_style(
        'opencomune-esperienza-wizard',
        get_template_directory_uri() . '/assets/css/esperienza-wizard.css',
        [],
        $ver
    );

    wp_enqueue_script(
        'opencomune-esperienza-wizard',
        get_template_directory_uri() . '/assets/js/esperienza-wizard.js',
        [],
        $ver,
        true
    );

    $experience_id = isset($config['experienceId']) ? (int) $config['experienceId'] : 0;

    wp_localize_script('opencomune-esperienza-wizard', 'opencomuneEsperienzaWizard', array_merge([
        'flatSteps' => opencomune_get_esperienza_form_flat_steps($experience_id),
        'requiredStepIds' => opencomune_get_esperienza_form_required_step_ids($experience_id),
        'dashboardUrl' => opencomune_get_dashboard_url(),
        'strings' => [
            'searchPlaceholder' => __('Cerca step…', 'opencomune'),
            'preview' => __('Anteprima', 'opencomune'),
            'backDashboard' => __('Torna alla dashboard', 'opencomune'),
            'prev' => __('Indietro', 'opencomune'),
            'next' => __('Avanti', 'opencomune'),
            'save' => __('Salva', 'opencomune'),
            'saveAndExit' => __('Salva e torna alla dashboard', 'opencomune'),
            'requiresSave' => __('Salva prima l\'esperienza per configurare questo step.', 'opencomune'),
        ],
    ], $config));
}
