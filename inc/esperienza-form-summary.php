<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_esperienza_summary_display_id($internal_id) {
    $num = abs(crc32((string) $internal_id)) % 900000;

    return 100000 + $num;
}

function opencomune_esperienza_summary_operating_hours_label($post_id) {
    $config = opencomune_get_esperienza_operating_hours((int) $post_id);
    if (empty($config['enabled'])) {
        return __('No', 'opencomune');
    }

    return __('Sì', 'opencomune');
}

function opencomune_esperienza_summary_availability_calendar_label($post_id) {
    if (!function_exists('opencomune_get_esperienza_availability_rules')) {
        return __('Non configurato', 'opencomune');
    }

    $rules = opencomune_get_esperienza_availability_rules((int) $post_id);

    return !empty($rules)
        ? sprintf(
            _n('%d regola attiva', '%d regole attive', count($rules), 'opencomune'),
            count($rules)
        )
        : __('Non configurato', 'opencomune');
}

/**
 * @return array<int, array{label: string, step: string, done: bool}>
 */
function opencomune_get_esperienza_form_summary_recommendations($post_id) {
    $post_id = (int) $post_id;
    $items = [];

    $citta = (string) get_post_meta($post_id, 'citta', true);
    $address = (string) get_post_meta($post_id, 'tour_address', true);
    $lat = (string) get_post_meta($post_id, 'tour_latitude', true);
    if ($citta === '' && $address === '' && $lat === '') {
        $items[] = [
            'label' => __('Nessuna località selezionata', 'opencomune'),
            'step' => 'location',
            'done' => false,
        ];
    }

    if (!has_post_thumbnail($post_id)) {
        $items[] = [
            'label' => __('Manca l\'immagine principale', 'opencomune'),
            'step' => 'media',
            'done' => false,
        ];
    }

    $gallery = get_post_meta($post_id, 'galleria', true);
    if (!is_array($gallery) || count(array_filter(array_map('intval', $gallery))) === 0) {
        $items[] = [
            'label' => __('Nessuna foto in galleria', 'opencomune'),
            'step' => 'media',
            'done' => false,
        ];
    }

    $meeting_points = opencomune_get_esperienza_meeting_points($post_id);
    if (empty($meeting_points)) {
        $items[] = [
            'label' => __('Nessun punto di incontro configurato', 'opencomune'),
            'step' => 'meeting-points',
            'done' => false,
        ];
    }

    if (function_exists('opencomune_get_esperienza_category_slugs')) {
        $experience_categories = opencomune_get_esperienza_category_slugs($post_id);
        if ($experience_categories === []) {
            $items[] = [
                'label' => __('Nessuna categoria selezionata (Categoria e tag)', 'opencomune'),
                'step' => 'category-tags',
                'done' => false,
            ];
        }
    }

    if (function_exists('opencomune_get_esperienza_itinerary_steps')) {
        $steps = opencomune_get_esperienza_itinerary_steps($post_id);
        if (empty($steps)) {
            $items[] = [
                'label' => __('Nessuna tappa nell\'itinerario', 'opencomune'),
                'step' => 'itinerary',
                'done' => false,
            ];
        }
    }

    $categories = opencomune_get_esperienza_pricing_categories($post_id);
    if (empty($categories)) {
        $items[] = [
            'label' => __('Nessuna categoria prezzo', 'opencomune'),
            'step' => 'pricing',
            'done' => false,
        ];
    }

    return $items;
}

function opencomune_get_esperienza_form_summary_data($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze') {
        return [];
    }

    $status = $post->post_status;
    $status_label = $status === 'publish'
        ? __('Attiva', 'opencomune')
        : ($status === 'draft' ? __('Bozza', 'opencomune') : ucfirst($status));

    $settings = function_exists('opencomune_get_esperienza_experience_settings')
        ? opencomune_get_esperienza_experience_settings($post_id)
        : ['experience_type' => 'tour_activity'];

    $schedule_type = opencomune_get_esperienza_booking_schedule_type($post_id);
    $schedule_defs = opencomune_get_booking_schedule_type_definitions();
    $schedule_label = $schedule_defs[$schedule_type]['label'] ?? $schedule_type;

    $capacity_mode = opencomune_get_esperienza_capacity_mode($post_id);
    $capacity_defs = opencomune_get_capacity_mode_definitions();
    $capacity_label = $capacity_defs[$capacity_mode]['label'] ?? $capacity_mode;

    $pickup_mode = opencomune_get_esperienza_pickup_mode($post_id);
    $pickup_defs = opencomune_get_pickup_mode_definitions();
    $pickup_label = $pickup_defs[$pickup_mode]['label'] ?? $pickup_mode;

    $pricing = [];
    foreach (opencomune_get_esperienza_pricing_categories($post_id) as $row) {
        $pricing[] = [
            'public_id' => (int) $row['public_id'],
            'label' => $row['label'],
            'is_default' => !empty($row['is_default']),
        ];
    }

    $start_times = [];
    foreach (opencomune_get_esperienza_start_times($post_id) as $row) {
        $start_times[] = [
            'display_id' => opencomune_esperienza_summary_display_id($row['id']),
            'time' => $row['time'],
            'external_label' => $row['external_label'],
        ];
    }

    $media = [];
    $featured_id = (int) get_post_thumbnail_id($post_id);
    if ($featured_id > 0) {
        $thumb = wp_get_attachment_image_url($featured_id, 'medium');
        if ($thumb) {
            $media[] = ['id' => $featured_id, 'url' => $thumb, 'is_cover' => true];
        }
    }

    $gallery_meta = get_post_meta($post_id, 'galleria', true);
    $gallery_ids = is_array($gallery_meta) ? array_values(array_filter(array_map('intval', $gallery_meta))) : [];
    foreach ($gallery_ids as $attachment_id) {
        if ($attachment_id === $featured_id) {
            continue;
        }
        $thumb = wp_get_attachment_image_url($attachment_id, 'medium');
        if ($thumb) {
            $media[] = ['id' => $attachment_id, 'url' => $thumb, 'is_cover' => false];
        }
    }

    $meeting_points = [];
    foreach (opencomune_get_esperienza_meeting_points($post_id) as $point) {
        $meeting_points[] = [
            'title' => $point['title'],
            'address' => $point['address'],
        ];
    }

    $itinerary = [];
    if (function_exists('opencomune_get_esperienza_itinerary_steps')) {
        foreach (opencomune_get_esperienza_itinerary_steps($post_id) as $step) {
            $itinerary[] = [
                'title' => $step['title'] ?? '',
                'address' => $step['address'] ?? '',
            ];
        }
    }

    return [
        'title' => get_the_title($post_id),
        'status' => $status,
        'status_label' => $status_label,
        'experience_type_label' => opencomune_get_experience_type_label($settings['experience_type'] ?? 'tour_activity'),
        'booking_schedule_label' => $schedule_label,
        'availability_rows' => [
            ['label' => __('Tipo prenotazione', 'opencomune'), 'value' => $schedule_label],
            ['label' => __('Capacità', 'opencomune'), 'value' => $capacity_label],
            ['label' => __('Orari di avvio', 'opencomune'), 'value' => opencomune_esperienza_summary_operating_hours_label($post_id)],
            ['label' => __('Calendario', 'opencomune'), 'value' => opencomune_esperienza_summary_availability_calendar_label($post_id)],
        ],
        'pricing_categories' => $pricing,
        'start_times' => $start_times,
        'media' => $media,
        'pickup_mode_label' => $pickup_label,
        'meeting_points' => $meeting_points,
        'itinerary_steps' => $itinerary,
        'recommendations' => opencomune_get_esperienza_form_summary_recommendations($post_id),
    ];
}
