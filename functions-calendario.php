<?php

// Calendario pubblico: espansione regole (fonte unica) con fallback legacy tour_calendari.

add_action('wp_ajax_opencomune_add_calendario', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Non autorizzato', 'opencomune')]);
    }

    wp_send_json_error([
        'message' => __('Il calendario si gestisce dalla pagina Modifica esperienza (tab Calendario).', 'opencomune'),
    ]);
});

add_action('wp_ajax_opencomune_delete_calendario', function () {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Non autorizzato', 'opencomune')]);
    }

    wp_send_json_error([
        'message' => __('Il calendario si gestisce dalla pagina Modifica esperienza (tab Calendario).', 'opencomune'),
    ]);
});

function opencomune_ajax_get_calendario_events() {
    $tour_id = intval($_GET['tour_id'] ?? 0);
    if ($tour_id <= 0) {
        wp_send_json([]);
    }

    $from = sanitize_text_field($_GET['from'] ?? '');
    $to = sanitize_text_field($_GET['to'] ?? '');
    if ($from === '') {
        $from = gmdate('Y-m-01');
    }
    if ($to === '') {
        $to = gmdate('Y-m-t', strtotime($from . ' +2 months'));
    }

    wp_send_json(opencomune_calendario_events_for_tour($tour_id, $from, $to));
}

add_action('wp_ajax_opencomune_get_calendario', 'opencomune_ajax_get_calendario_events');
add_action('wp_ajax_nopriv_opencomune_get_calendario', 'opencomune_ajax_get_calendario_events');
