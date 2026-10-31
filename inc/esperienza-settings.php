<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_experience_type_definitions() {
    return [
        'tour_activity' => [
            'label' => __('Tour / Attività', 'opencomune'),
            'description' => __('Se la tua esperienza è un tour o una attività, seleziona questa opzione.', 'opencomune'),
        ],
        'attraction' => [
            'label' => __('Attrazione', 'opencomune'),
            'description' => __('Qualcosa da vedere? Paesaggi e luoghi storici? Scegli attrazione.', 'opencomune'),
        ],
        'event' => [
            'label' => __('Evento', 'opencomune'),
            'description' => __('Se la tua esperienza è un evento, questa è l\'opzione giusta.', 'opencomune'),
        ],
        'transportation' => [
            'label' => __('Trasporto', 'opencomune'),
            'description' => __('Se il prodotto è un servizio di trasporto, seleziona questa opzione.', 'opencomune'),
        ],
    ];
}

function opencomune_normalize_experience_type($type) {
    $allowed = array_keys(opencomune_get_experience_type_definitions());
    $type = sanitize_key($type);

    return in_array($type, $allowed, true) ? $type : 'tour_activity';
}

function opencomune_get_esperienza_experience_settings($post_id) {
    $post_id = (int) $post_id;
    $type = get_post_meta($post_id, 'tour_experience_type', true);
    if ($type === '' || $type === false) {
        $legacy = (string) get_post_meta($post_id, 'tipo_esperienza', true);
        $type = opencomune_guess_experience_type_from_legacy($legacy);
    }

    return [
        'experience_type' => opencomune_normalize_experience_type($type),
        'private_experience' => get_post_meta($post_id, 'tour_private_experience', true) === '1',
        'customized_bookings' => get_post_meta($post_id, 'tour_customized_bookings', true) === '1',
    ];
}

function opencomune_guess_experience_type_from_legacy($legacy) {
    $legacy = strtolower(trim($legacy));
    if ($legacy === '') {
        return 'tour_activity';
    }
    if (strpos($legacy, 'attraz') !== false) {
        return 'attraction';
    }
    if (strpos($legacy, 'event') !== false) {
        return 'event';
    }
    if (strpos($legacy, 'trasport') !== false || strpos($legacy, 'transport') !== false) {
        return 'transportation';
    }

    return 'tour_activity';
}

function opencomune_get_experience_type_label($type) {
    $definitions = opencomune_get_experience_type_definitions();
    $type = opencomune_normalize_experience_type($type);

    return $definitions[$type]['label'] ?? $definitions['tour_activity']['label'];
}

function opencomune_save_esperienza_experience_settings($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $type = opencomune_normalize_experience_type($post_data['tour_experience_type'] ?? 'tour_activity');
    $private = !empty($post_data['tour_private_experience']);
    $customized = !empty($post_data['tour_customized_bookings']);

    update_post_meta($post_id, 'tour_experience_type', $type);
    update_post_meta($post_id, 'tour_private_experience', $private ? '1' : '0');
    update_post_meta($post_id, 'tour_customized_bookings', $customized ? '1' : '0');
    update_post_meta($post_id, 'tipo_esperienza', opencomune_get_experience_type_label($type));

    return opencomune_get_esperienza_experience_settings($post_id);
}

function opencomune_enqueue_experience_settings_assets() {
    $theme = wp_get_theme();
    wp_enqueue_style(
        'opencomune-experience-settings',
        get_template_directory_uri() . '/assets/css/experience-settings.css',
        [],
        $theme->get('Version') ?: '1.0'
    );
}

function opencomune_format_experience_settings_for_api($post_id) {
    $settings = opencomune_get_esperienza_experience_settings($post_id);
    $type = $settings['experience_type'];

    return [
        'experience_type' => $type,
        'experience_type_label' => opencomune_get_experience_type_label($type),
        'private_experience' => (bool) $settings['private_experience'],
        'customized_bookings' => (bool) $settings['customized_bookings'],
    ];
}
