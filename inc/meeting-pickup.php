<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_pickup_mode_definitions() {
    return [
        'meet_on_location' => [
            'label' => __('Incontro sul posto', 'opencomune'),
            'description' => __('L\'esperienza non prevede servizio di prelievo: i partecipanti devono raggiungere il punto di incontro.', 'opencomune'),
        ],
        'pickup_only' => [
            'label' => __('Solo prelievo', 'opencomune'),
            'description' => __('I partecipanti devono essere prelevati nei punti di prelievo selezionati.', 'opencomune'),
        ],
        'meet_or_pickup' => [
            'label' => __('Incontro sul posto o prelievo', 'opencomune'),
            'description' => __('I partecipanti possono venire in sede o richiedere il prelievo.', 'opencomune'),
        ],
    ];
}

function opencomune_normalize_pickup_mode($mode) {
    $allowed = array_keys(opencomune_get_pickup_mode_definitions());
    $mode = sanitize_key($mode);

    return in_array($mode, $allowed, true) ? $mode : 'meet_on_location';
}

function opencomune_get_esperienza_pickup_mode($post_id) {
    $stored = get_post_meta((int) $post_id, 'tour_pickup_mode', true);

    return opencomune_normalize_pickup_mode($stored ?: 'meet_on_location');
}

function opencomune_normalize_meeting_points_list($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = sanitize_text_field($item['title'] ?? '');
        $address = sanitize_text_field($item['address'] ?? '');
        if ($title === '' || $address === '') {
            continue;
        }

        $id = sanitize_text_field($item['id'] ?? '');
        if ($id === '') {
            $id = 'mp_' . uniqid('', true);
        }

        $items[] = [
            'id' => $id,
            'title' => $title,
            'address' => $address,
            'latitude' => round((float) ($item['latitude'] ?? 0), 6),
            'longitude' => round((float) ($item['longitude'] ?? 0), 6),
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $index,
        ];
    }

    usort($items, static function ($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });

    return $items;
}

function opencomune_get_esperienza_meeting_points($post_id) {
    $post_id = (int) $post_id;
    $stored = get_post_meta($post_id, 'tour_meeting_points', true);
    $items = opencomune_normalize_meeting_points_list(is_array($stored) ? $stored : []);

    if (empty($items) && $post_id > 0) {
        $legacy_title = get_post_meta($post_id, 'tour_meeting_point', true);
        $legacy_address = get_post_meta($post_id, 'tour_address', true);
        if ($legacy_title !== '' || $legacy_address !== '') {
            $items[] = [
                'id' => 'mp_legacy',
                'title' => $legacy_title !== '' ? $legacy_title : __('Punto di incontro', 'opencomune'),
                'address' => $legacy_address !== '' ? $legacy_address : $legacy_title,
                'latitude' => (float) get_post_meta($post_id, 'tour_latitude', true),
                'longitude' => (float) get_post_meta($post_id, 'tour_longitude', true),
                'sort_order' => 0,
            ];
        }
    }

    return $items;
}

function opencomune_get_esperienza_use_pickup_group($post_id) {
    return get_post_meta((int) $post_id, 'tour_use_pickup_group', true) === '1';
}

function opencomune_save_esperienza_meeting_pickup($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $mode = opencomune_normalize_pickup_mode($post_data['tour_pickup_mode'] ?? 'meet_on_location');
    update_post_meta($post_id, 'tour_pickup_mode', $mode);

    $use_group = !empty($post_data['tour_use_pickup_group']);
    update_post_meta($post_id, 'tour_use_pickup_group', $use_group ? '1' : '0');

    $payload = $post_data['tour_meeting_points_payload'] ?? '';
    $decoded = [];
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode(wp_unslash($payload), true);
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $points = opencomune_normalize_meeting_points_list($decoded);
    update_post_meta($post_id, 'tour_meeting_points', $points);

    if (!empty($points)) {
        $first = $points[0];
        update_post_meta($post_id, 'tour_meeting_point', $first['title']);
        if ($first['address'] !== '') {
            update_post_meta($post_id, 'tour_address', $first['address']);
        }
        if ($first['latitude'] || $first['longitude']) {
            update_post_meta($post_id, 'tour_latitude', $first['latitude']);
            update_post_meta($post_id, 'tour_longitude', $first['longitude']);
        }
    }
}

function opencomune_format_meeting_pickup_for_api($post_id) {
    return [
        'pickup_mode' => opencomune_get_esperienza_pickup_mode($post_id),
        'use_pickup_group' => opencomune_get_esperienza_use_pickup_group($post_id),
        'meeting_points' => opencomune_get_esperienza_meeting_points($post_id),
    ];
}

function opencomune_enqueue_meeting_pickup_assets() {
    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';
    $api_key = opencomune_get_google_maps_api_key();

    wp_enqueue_style(
        'opencomune-meeting-pickup',
        get_template_directory_uri() . '/assets/css/meeting-pickup.css',
        [],
        $ver
    );

    $places_addr_js = get_template_directory() . '/assets/js/places-autocomplete-address.js';
    wp_enqueue_script(
        'opencomune-places-autocomplete-address',
        get_template_directory_uri() . '/assets/js/places-autocomplete-address.js',
        [],
        is_readable($places_addr_js) ? (string) filemtime($places_addr_js) : $ver,
        true
    );

    $meeting_points_js = get_template_directory() . '/assets/js/meeting-points.js';
    wp_enqueue_script(
        'opencomune-meeting-points',
        get_template_directory_uri() . '/assets/js/meeting-points.js',
        ['opencomune-places-autocomplete-address'],
        is_readable($meeting_points_js) ? (string) filemtime($meeting_points_js) : $ver,
        true
    );

    wp_add_inline_script(
        'opencomune-meeting-points',
        'window.opencomuneMeetingPointsConfig = ' . wp_json_encode([
            'mapsApiKey' => $api_key,
            'strings' => [
                'addTitle' => __('Aggiungi punto di inizio', 'opencomune'),
                'editTitle' => __('Modifica punto di inizio', 'opencomune'),
                'empty' => __('Non ci sono punti di incontro per questa esperienza.', 'opencomune'),
                'confirmDelete' => __('Rimuovere questo punto di incontro?', 'opencomune'),
                'selectPlace' => __('Seleziona un indirizzo valido dai suggerimenti Google.', 'opencomune'),
                'manual' => __('Non trovi il tuo indirizzo? Clicca qui per inserirlo manualmente.', 'opencomune'),
                'useAutocomplete' => __('Usa la ricerca Google', 'opencomune'),
            ],
        ]) . ';',
        'before'
    );
}

function opencomune_enqueue_esperienza_location_autocomplete_assets() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $rel = '/assets/js/esperienza-location-autocomplete.js';
    $path = get_template_directory() . $rel;
    if (!is_readable($path)) {
        return;
    }

    $places_addr_js = get_template_directory() . '/assets/js/places-autocomplete-address.js';
    wp_enqueue_script(
        'opencomune-places-autocomplete-address',
        get_template_directory_uri() . '/assets/js/places-autocomplete-address.js',
        [],
        is_readable($places_addr_js) ? (string) filemtime($places_addr_js) : (string) filemtime($path),
        true
    );

    wp_enqueue_script(
        'opencomune-esperienza-location',
        get_template_directory_uri() . $rel,
        ['google-maps', 'opencomune-places-autocomplete-address'],
        (string) filemtime($path),
        true
    );
}
