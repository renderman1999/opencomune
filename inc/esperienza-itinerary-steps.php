<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param mixed $raw
 * @return array<int, array<string, mixed>>
 */
function opencomune_normalize_esperienza_itinerary_steps_list($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $title = sanitize_text_field($item['title'] ?? '');
        if ($title === '') {
            continue;
        }

        $id = sanitize_text_field($item['id'] ?? '');
        if ($id === '') {
            $id = 'it_' . uniqid('', true);
        }

        $gallery_ids = [];
        if (!empty($item['gallery_ids']) && is_array($item['gallery_ids'])) {
            foreach ($item['gallery_ids'] as $gid) {
                $gid = (int) $gid;
                if ($gid > 0) {
                    $gallery_ids[] = $gid;
                }
            }
            $gallery_ids = array_values(array_unique($gallery_ids));
        }

        $items[] = [
            'id' => $id,
            'title' => $title,
            'description' => wp_kses_post((string) ($item['description'] ?? '')),
            'address' => sanitize_text_field($item['address'] ?? ''),
            'latitude' => round((float) ($item['latitude'] ?? 0), 6),
            'longitude' => round((float) ($item['longitude'] ?? 0), 6),
            'gallery_ids' => $gallery_ids,
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : (int) $index,
        ];
    }

    usort($items, static function ($a, $b) {
        return ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0);
    });

    return $items;
}

/**
 * @return array<int, array<string, mixed>>
 */
function opencomune_get_esperienza_itinerary_steps($post_id) {
    $post_id = (int) $post_id;
    $stored = get_post_meta($post_id, 'tour_itinerary_steps', true);

    return opencomune_normalize_esperienza_itinerary_steps_list(is_array($stored) ? $stored : []);
}

function opencomune_save_esperienza_itinerary_steps($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $payload = $post_data['tour_itinerary_steps_payload'] ?? '';
    if ($payload === '') {
        delete_post_meta($post_id, 'tour_itinerary_steps');
        return;
    }

    $decoded = json_decode(wp_unslash((string) $payload), true);
    if (!is_array($decoded)) {
        return;
    }

    $steps = opencomune_normalize_esperienza_itinerary_steps_list($decoded);
    update_post_meta($post_id, 'tour_itinerary_steps', $steps);
}

function opencomune_enqueue_esperienza_itinerary_public_map() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';
    $js = get_template_directory() . '/assets/js/esperienza-itinerary-step-map.js';
    if (!is_readable($js)) {
        return;
    }

    wp_enqueue_script(
        'opencomune-esperienza-itinerary-step-map',
        get_template_directory_uri() . '/assets/js/esperienza-itinerary-step-map.js',
        [],
        (string) filemtime($js),
        true
    );
    wp_add_inline_script(
        'opencomune-esperienza-itinerary-step-map',
        'window.opencomuneEsperienzaItineraryMap = ' . wp_json_encode([
            'strings' => [
                'showMap' => __('Visualizza su mappa', 'opencomune'),
                'hideMap' => __('Nascondi mappa', 'opencomune'),
            ],
        ]) . ';',
        'before'
    );
}

function opencomune_enqueue_esperienza_itinerary_steps_assets() {
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;

    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';

    wp_enqueue_editor();

    wp_enqueue_style(
        'opencomune-esperienza-itinerary-steps',
        get_template_directory_uri() . '/assets/css/esperienza-itinerary-steps.css',
        ['opencomune-meeting-pickup'],
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

    $js = get_template_directory() . '/assets/js/esperienza-itinerary-steps.js';
    wp_enqueue_script(
        'opencomune-esperienza-itinerary-steps',
        get_template_directory_uri() . '/assets/js/esperienza-itinerary-steps.js',
        ['jquery', 'opencomune-places-autocomplete-address', 'editor'],
        is_readable($js) ? (string) filemtime($js) : $ver,
        true
    );

    wp_add_inline_script(
        'opencomune-esperienza-itinerary-steps',
        'window.opencomuneItineraryStepsConfig = ' . wp_json_encode([
            'strings' => [
                'addTitle' => __('Aggiungi tappa', 'opencomune'),
                'editTitle' => __('Modifica tappa', 'opencomune'),
                'empty' => __('Non ci sono tappe nell\'itinerario.', 'opencomune'),
                'confirmDelete' => __('Rimuovere questa tappa?', 'opencomune'),
                'selectPlace' => __('Seleziona un indirizzo valido dai suggerimenti Google.', 'opencomune'),
                'manual' => __('Non trovi il tuo indirizzo? Clicca qui per inserirlo manualmente.', 'opencomune'),
                'useAutocomplete' => __('Usa la ricerca Google', 'opencomune'),
                'titleRequired' => __('Inserisci un titolo per la tappa.', 'opencomune'),
                'galleryAdd' => __('Aggiungi immagini', 'opencomune'),
            ],
        ]) . ';',
        'before'
    );
}
