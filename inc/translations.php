<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_post_meta_with_legacy($post_id, $primary_key, $legacy_key = '') {
    $value = get_post_meta($post_id, $primary_key, true);
    if (($value === '' || $value === null) && $legacy_key !== '') {
        $value = get_post_meta($post_id, $legacy_key, true);
    }

    return $value;
}

function opencomune_get_supported_api_languages() {
    return ['it', 'en'];
}

function opencomune_normalize_api_language($lang) {
    $lang = strtolower(sanitize_text_field((string) $lang));
    if ($lang === '') {
        return 'it';
    }

    if (strpos($lang, 'en') === 0) {
        return 'en';
    }

    if (strpos($lang, 'it') === 0) {
        return 'it';
    }

    return 'it';
}

function opencomune_get_esperienza_translation_field_defs() {
    return [
        'title' => [
            'meta' => 'title_en',
            'type' => 'text',
            'label' => 'Title',
            'label_it' => 'Titolo esperienza',
            'placeholder' => 'Experience title in English',
            'rows' => 1,
        ],
        'descrizione_breve' => [
            'meta' => 'descrizione_breve_en',
            'type' => 'textarea',
            'label' => 'Short description',
            'label_it' => 'Descrizione breve',
            'placeholder' => 'Short description in English',
            'rows' => 3,
        ],
        'content' => [
            'meta' => 'content_en',
            'type' => 'html',
            'label' => 'Full description',
            'label_it' => 'Descrizione completa',
            'placeholder' => 'Full description in English',
            'rows' => 8,
        ],
        'highlights' => [
            'meta' => 'tour_highlights_en',
            'type' => 'html',
            'label' => 'Highlights',
            'label_it' => 'Punti salienti',
            'placeholder' => 'Highlights in English',
            'rows' => 4,
        ],
        'itinerary' => [
            'meta' => 'tour_itinerary_en',
            'type' => 'html',
            'label' => 'Itinerary',
            'label_it' => 'Itinerario',
            'placeholder' => 'Itinerary in English',
            'rows' => 4,
        ],
        'included' => [
            'meta' => 'tour_whats_included_en',
            'type' => 'html',
            'label' => 'What is included',
            'label_it' => 'Cosa include',
            'placeholder' => 'Included items in English',
            'rows' => 4,
        ],
        'not_included' => [
            'meta' => 'tour_whats_not_included_en',
            'type' => 'html',
            'label' => 'What is not included',
            'label_it' => 'Cosa non include',
            'placeholder' => 'Excluded items in English',
            'rows' => 4,
        ],
        'requirements' => [
            'meta' => 'tour_requirements_en',
            'type' => 'html',
            'label' => 'Requirements',
            'label_it' => 'Requisiti',
            'placeholder' => 'Requirements in English',
            'rows' => 4,
        ],
        'cancellation_policy' => [
            'meta' => 'tour_cancellation_policy_en',
            'type' => 'html',
            'label' => 'Cancellation policy',
            'label_it' => 'Politica di cancellazione',
            'placeholder' => 'Cancellation policy in English',
            'rows' => 4,
        ],
    ];
}

function opencomune_sanitize_esperienza_translation_value($value, $type) {
    switch ($type) {
        case 'text':
            return sanitize_text_field($value);
        case 'textarea':
            return sanitize_textarea_field($value);
        case 'html':
        default:
            return wp_kses_post($value);
    }
}

function opencomune_get_esperienza_translation_input_name($field_key) {
    return 'translation_en_' . $field_key;
}

function opencomune_get_esperienza_translation_value($post_id, $field_key, $lang = 'en') {
    $lang = opencomune_normalize_api_language($lang);
    $defs = opencomune_get_esperienza_translation_field_defs();

    if (!isset($defs[$field_key])) {
        return '';
    }

    if ($lang === 'it') {
        return opencomune_get_esperienza_italian_field_value($post_id, $field_key);
    }

    $meta_value = get_post_meta($post_id, $defs[$field_key]['meta'], true);
    if ($meta_value !== '' && $meta_value !== null) {
        return $meta_value;
    }

    return opencomune_get_esperienza_italian_field_value($post_id, $field_key);
}

function opencomune_get_esperienza_italian_field_value($post_id, $field_key) {
    switch ($field_key) {
        case 'title':
            return get_the_title($post_id);
        case 'descrizione_breve':
            return opencomune_get_esperienza_descrizione_breve($post_id);
        case 'content':
            return get_post_field('post_content', $post_id);
        case 'highlights':
            return get_post_meta($post_id, 'tour_highlights', true);
        case 'itinerary':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_itinerary', 'itinerario');
        case 'included':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_whats_included', 'include');
        case 'not_included':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_whats_not_included', 'non_include');
        case 'requirements':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_requirements', 'note');
        case 'cancellation_policy':
            return get_post_meta($post_id, 'tour_cancellation_policy', true);
        default:
            return '';
    }
}

function opencomune_get_esperienza_meeting_point($post_id) {
    return get_post_meta($post_id, 'tour_meeting_point', true);
}

function opencomune_get_esperienza_address($post_id) {
    return opencomune_get_post_meta_with_legacy($post_id, 'tour_address', 'indirizzo_ritrovo');
}

function opencomune_get_esperienza_english_field_value($post_id, $field_key) {
    $defs = opencomune_get_esperienza_translation_field_defs();
    if (!isset($defs[$field_key])) {
        return '';
    }

    return (string) get_post_meta($post_id, $defs[$field_key]['meta'], true);
}

function opencomune_esperienza_has_english_translation($post_id) {
    foreach (array_keys(opencomune_get_esperienza_translation_field_defs()) as $field_key) {
        if (opencomune_get_esperienza_english_field_value($post_id, $field_key) !== '') {
            return true;
        }
    }

    return false;
}

function opencomune_get_esperienza_translation_bundle($post_id, $lang) {
    $lang = opencomune_normalize_api_language($lang);
    $bundle = [];

    foreach (array_keys(opencomune_get_esperienza_translation_field_defs()) as $field_key) {
        if ($lang === 'en') {
            $english = opencomune_get_esperienza_english_field_value($post_id, $field_key);
            $bundle[$field_key] = $english !== ''
                ? $english
                : opencomune_get_esperienza_italian_field_value($post_id, $field_key);
            continue;
        }

        $bundle[$field_key] = opencomune_get_esperienza_italian_field_value($post_id, $field_key);
    }

    return $bundle;
}

function opencomune_get_esperienza_translations_payload($post_id) {
    return [
        'it' => opencomune_get_esperienza_translation_bundle($post_id, 'it'),
        'en' => opencomune_get_esperienza_translation_bundle($post_id, 'en'),
    ];
}

function opencomune_get_esperienza_translation_availability($post_id) {
    $availability = [];

    foreach (opencomune_get_supported_api_languages() as $lang) {
        if ($lang === 'it') {
            $availability[$lang] = true;
            continue;
        }

        $has_translation = false;
        foreach (array_keys(opencomune_get_esperienza_translation_field_defs()) as $field_key) {
            if (opencomune_get_esperienza_english_field_value($post_id, $field_key) !== '') {
                $has_translation = true;
                break;
            }
        }
        $availability[$lang] = $has_translation;
    }

    return $availability;
}

function opencomune_get_esperienza_translation_ui_map() {
    return [
        'title' => [
            'it_field' => 'tour_title',
            'it_name' => 'tour_title',
            'it_id' => 'tour_title_ref',
            'it_label' => 'Titolo esperienza',
        ],
        'descrizione_breve' => [
            'it_field' => 'tour_excerpt',
            'it_name' => 'tour_excerpt',
            'it_id' => 'tour_excerpt_ref',
            'it_label' => 'Descrizione breve',
        ],
        'content' => [
            'it_field' => 'tour_description',
            'it_name' => 'tour_description',
            'it_id' => 'tour_description_ref',
            'it_label' => 'Descrizione completa',
        ],
        'highlights' => [
            'it_field' => 'tour_highlights',
            'it_name' => 'tour_highlights',
            'it_id' => 'tour_highlights_ref',
            'it_label' => 'Punti salienti',
        ],
        'itinerary' => [
            'it_field' => 'tour_itinerary',
            'it_name' => 'tour_itinerary',
            'it_id' => 'tour_itinerary_ref',
            'it_label' => 'Itinerario',
        ],
        'included' => [
            'it_field' => 'tour_whats_included',
            'it_name' => 'tour_whats_included',
            'it_id' => 'tour_whats_included_ref',
            'it_label' => 'Cosa include',
        ],
        'not_included' => [
            'it_field' => 'tour_whats_not_included',
            'it_name' => 'tour_whats_not_included',
            'it_id' => 'tour_whats_not_included_ref',
            'it_label' => 'Cosa non include',
        ],
        'requirements' => [
            'it_field' => 'tour_requirements',
            'it_name' => 'tour_requirements',
            'it_id' => 'tour_requirements_ref',
            'it_label' => 'Requisiti',
        ],
        'cancellation_policy' => [
            'it_field' => 'tour_cancellation_policy',
            'it_name' => 'tour_cancellation_policy',
            'it_id' => 'tour_cancellation_policy_ref',
            'it_label' => 'Politica di cancellazione',
        ],
    ];
}

function opencomune_render_esperienza_english_translation_fields($post_id) {
    $post_id = (int) $post_id;
    $defs = opencomune_get_esperienza_translation_field_defs();
    $map = opencomune_get_esperienza_translation_ui_map();

    echo '<div class="opencomune-english-translation-fields">';
    foreach ($map as $en_key => $ui) {
        if (!isset($defs[$en_key])) {
            continue;
        }

        $def = $defs[$en_key];

        opencomune_render_esperienza_bilingual_field([
            'section' => 'translations',
            'en_key' => $en_key,
            'english_only' => true,
            'it_name' => $ui['it_name'],
            'it_id' => $ui['it_id'],
            'it_label' => $ui['it_label'],
            'it_value' => opencomune_get_esperienza_form_it_field_value($post_id, $ui['it_field']),
            'en_value' => opencomune_get_esperienza_english_field_value($post_id, $en_key),
            'type' => $def['type'],
            'rows' => (int) ($def['rows'] ?? 4),
            'ai_translate_actions' => function_exists('opencomune_partner_ai_is_configured')
                && opencomune_partner_ai_is_configured(get_current_user_id()),
        ]);
    }
    echo '</div>';
}

function opencomune_save_esperienza_translations_from_post($post_id, $post_data) {
    foreach (opencomune_get_esperienza_translation_field_defs() as $field_key => $def) {
        $input_name = opencomune_get_esperienza_translation_input_name($field_key);
        if (!array_key_exists($input_name, $post_data)) {
            continue;
        }

        $value = opencomune_sanitize_esperienza_translation_value($post_data[$input_name] ?? '', $def['type']);
        if ($value === '') {
            delete_post_meta($post_id, $def['meta']);
        } else {
            update_post_meta($post_id, $def['meta'], $value);
        }
    }
}

function opencomune_format_esperienza_dates_label_for_lang($post_id, $lang = 'it') {
    $lang = opencomune_normalize_api_language($lang);
    $schedule = function_exists('opencomune_get_esperienza_schedule_dates')
        ? opencomune_get_esperienza_schedule_dates($post_id)
        : [
            'start' => get_post_meta($post_id, 'tour_start_date', true),
            'end' => get_post_meta($post_id, 'tour_end_date', true),
            'mode' => get_post_meta($post_id, 'tour_date_mode', true) ?: 'single',
        ];

    $start = $schedule['start'] ?? '';
    $end = $schedule['end'] ?? '';
    $date_mode = $schedule['mode'] ?? 'single';

    if (!$start) {
        return '';
    }

    if ($lang === 'en') {
        if ($date_mode === 'range' && $end) {
            return date('M j, Y', strtotime($start)) . ' - ' . date('M j, Y', strtotime($end));
        }

        return date('M j, Y', strtotime($start));
    }

    if (function_exists('opencomune_format_esperienza_dates_label')) {
        return opencomune_format_esperienza_dates_label($post_id);
    }

    if ($date_mode === 'range' && $end) {
        return date_i18n('j M Y', strtotime($start)) . ' - ' . date_i18n('j M Y', strtotime($end));
    }

    return date_i18n('j M Y', strtotime($start));
}

function opencomune_get_term_localized_name($term, $lang = 'it') {
    if (!($term instanceof WP_Term)) {
        return '';
    }

    $lang = opencomune_normalize_api_language($lang);
    if ($lang === 'en') {
        $english = get_term_meta($term->term_id, 'name_en', true);
        if ($english !== '') {
            return $english;
        }
    }

    return $term->name;
}

function opencomune_get_term_translation_payload($term) {
    if (!($term instanceof WP_Term)) {
        return [
            'it' => '',
            'en' => '',
        ];
    }

    return [
        'it' => $term->name,
        'en' => (string) get_term_meta($term->term_id, 'name_en', true),
    ];
}

function opencomune_get_esperienza_form_it_field_value($post_id, $field_name) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($field_name, $_POST)) {
        return wp_unslash($_POST[$field_name]);
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    switch ($field_name) {
        case 'tour_title':
            return get_the_title($post_id);
        case 'tour_excerpt':
            $desc = get_post_meta($post_id, 'descrizione_breve', true);
            return $desc !== '' ? $desc : get_post_field('post_excerpt', $post_id);
        case 'tour_description':
            return get_post_field('post_content', $post_id);
        case 'tour_highlights':
            return get_post_meta($post_id, 'tour_highlights', true);
        case 'tour_itinerary':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_itinerary', 'itinerario');
        case 'tour_whats_included':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_whats_included', 'include');
        case 'tour_whats_not_included':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_whats_not_included', 'non_include');
        case 'tour_requirements':
            return opencomune_get_post_meta_with_legacy($post_id, 'tour_requirements', 'note');
        case 'tour_cancellation_policy':
            return get_post_meta($post_id, 'tour_cancellation_policy', true);
        default:
            return '';
    }
}

function opencomune_get_esperienza_form_en_field_value($post_id, $field_key) {
    $input_name = opencomune_get_esperienza_translation_input_name($field_key);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists($input_name, $_POST)) {
        return wp_unslash($_POST[$input_name]);
    }

    if ((int) $post_id > 0) {
        return opencomune_get_esperienza_english_field_value($post_id, $field_key);
    }

    return '';
}

function opencomune_print_esperienza_bilingual_form_assets() {
    static $printed = false;
    if ($printed) {
        return;
    }
    $printed = true;
    ?>
    <style>
    .opencomune-bilingual-section-tabs {
        display: inline-flex;
        gap: 0.25rem;
        padding: 0.25rem;
        border-radius: 9999px;
        background: #f3f4f6;
        margin-bottom: 1rem;
    }
    .opencomune-bilingual-section-tabs button {
        border: 0;
        background: transparent;
        color: #4b5563;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 0.35rem 0.85rem;
        border-radius: 9999px;
        cursor: pointer;
    }
    .opencomune-bilingual-section-tabs button.is-active {
        background: #2563eb;
        color: #fff;
    }
    .opencomune-bilingual-col-label {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: #6b7280;
        margin-bottom: 0.5rem;
    }
    .opencomune-bilingual-col-label-en {
        color: #1d4ed8;
    }
    .opencomune-bilingual-field + .opencomune-bilingual-field {
        margin-top: 1.25rem;
    }
    @media (max-width: 767px) {
        .opencomune-bilingual-section:not(.opencomune-show-en) .opencomune-bilingual-col-en {
            display: none;
        }
        .opencomune-bilingual-section.opencomune-show-en .opencomune-bilingual-col-it {
            display: none;
        }
    }
    @media (min-width: 768px) {
        .opencomune-bilingual-section-tabs {
            display: none;
        }
    }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.opencomune-bilingual-section-tabs').forEach(function(tabBar) {
            var section = tabBar.closest('.opencomune-bilingual-section');
            if (!section) {
                return;
            }
            tabBar.querySelectorAll('button[data-lang]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var lang = button.getAttribute('data-lang');
                    tabBar.querySelectorAll('button[data-lang]').forEach(function(item) {
                        item.classList.toggle('is-active', item === button);
                    });
                    section.classList.toggle('opencomune-show-en', lang === 'en');
                });
            });
        });
    });
    </script>
    <?php
}

function opencomune_render_esperienza_bilingual_field($args) {
    opencomune_print_esperienza_bilingual_form_assets();
    get_template_part('template-parts/esperienza-bilingual-field', null, $args);
}

function opencomune_render_esperienza_bilingual_section_start($section_key, $title = '') {
    opencomune_print_esperienza_bilingual_form_assets();
    ?>
    <div class="opencomune-bilingual-section" data-section="<?php echo esc_attr($section_key); ?>">
        <div class="opencomune-bilingual-section-tabs" aria-label="<?php echo esc_attr($title !== '' ? $title : 'Language tabs'); ?>">
            <button type="button" class="is-active" data-lang="it">IT</button>
            <button type="button" data-lang="en">EN</button>
        </div>
    <?php
}

function opencomune_render_esperienza_bilingual_section_end() {
    echo '</div>';
}

function opencomune_render_esperienza_translation_fields($post_id = 0) {
    get_template_part('template-parts/esperienza-form-bilingual-base', null, [
        'post_id' => (int) $post_id,
    ]);
}

function opencomune_register_categoria_translation_fields() {
    $taxonomy = 'categorie_esperienze';

    add_action($taxonomy . '_add_form_fields', 'opencomune_render_categoria_translation_add_field');
    add_action($taxonomy . '_edit_form_fields', 'opencomune_render_categoria_translation_edit_field');
    add_action('created_' . $taxonomy, 'opencomune_save_categoria_translation_meta');
    add_action('edited_' . $taxonomy, 'opencomune_save_categoria_translation_meta');
}
add_action('init', 'opencomune_register_categoria_translation_fields', 20);

function opencomune_render_categoria_translation_add_field() {
    ?>
    <div class="form-field">
        <label for="name_en">Nome in inglese</label>
        <input type="text" name="name_en" id="name_en" value="">
        <p class="description">Nome categoria mostrato nelle API con <code>lang=en</code>.</p>
    </div>
    <?php
}

function opencomune_render_categoria_translation_edit_field($term) {
    $name_en = get_term_meta($term->term_id, 'name_en', true);
    ?>
    <tr class="form-field">
        <th scope="row"><label for="name_en">Nome in inglese</label></th>
        <td>
            <input type="text" name="name_en" id="name_en" value="<?php echo esc_attr($name_en); ?>" class="regular-text">
            <p class="description">Nome categoria mostrato nelle API con <code>lang=en</code>.</p>
        </td>
    </tr>
    <?php
}

function opencomune_save_categoria_translation_meta($term_id) {
    if (!isset($_POST['name_en'])) {
        return;
    }

    $name_en = sanitize_text_field(wp_unslash($_POST['name_en']));
    if ($name_en === '') {
        delete_term_meta($term_id, 'name_en');
        return;
    }

    update_term_meta($term_id, 'name_en', $name_en);
}
