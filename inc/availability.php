<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_availability_weekday_keys() {
    return ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
}

function opencomune_availability_rule_type_definitions() {
    return [
        'weekly' => __('Ripeti settimanalmente nei giorni selezionati', 'opencomune'),
        'yearly_months' => __('Ripeti ogni anno nei mesi selezionati', 'opencomune'),
        'date_range' => __('Valida tra le date selezionate', 'opencomune'),
        'single_date' => __('Valida in una data specifica', 'opencomune'),
    ];
}

function opencomune_availability_color_palette() {
    return ['#7C3AED', '#6366F1', '#2563EB', '#0891B2', '#059669', '#84CC16', '#EAB308', '#F97316', '#EF4444', '#EC4899', '#8B5CF6', '#14B8A6'];
}

function opencomune_normalize_availability_rules($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $allowed_types = array_keys(opencomune_availability_rule_type_definitions());
    $allowed_colors = opencomune_availability_color_palette();
    $weekdays = opencomune_availability_weekday_keys();
    $rules = [];

    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $type = sanitize_key($item['rule_type'] ?? '');
        if (!in_array($type, $allowed_types, true)) {
            continue;
        }

        $color = sanitize_hex_color($item['color'] ?? '') ?: '#EAB308';
        if (!in_array(strtoupper($color), array_map('strtoupper', $allowed_colors), true)) {
            $color = '#EAB308';
        }

        $weekday_values = [];
        foreach ((array) ($item['weekdays'] ?? []) as $day) {
            $day = sanitize_key($day);
            if (in_array($day, $weekdays, true)) {
                $weekday_values[] = $day;
            }
        }

        $months = [];
        foreach ((array) ($item['months'] ?? []) as $month) {
            $month = (int) $month;
            if ($month >= 1 && $month <= 12) {
                $months[] = $month;
            }
        }

        $id = sanitize_text_field($item['id'] ?? '');
        if ($id === '') {
            $id = 'av_' . uniqid('', true);
        }

        $rules[] = [
            'id' => $id,
            'rule_type' => $type,
            'effective_from' => sanitize_text_field($item['effective_from'] ?? ''),
            'effective_until' => sanitize_text_field($item['effective_until'] ?? ''),
            'weekdays' => array_values(array_unique($weekday_values)),
            'months' => array_values(array_unique($months)),
            'date_from' => sanitize_text_field($item['date_from'] ?? ''),
            'date_to' => sanitize_text_field($item['date_to'] ?? ''),
            'single_date' => sanitize_text_field($item['single_date'] ?? ''),
            'min_participants' => max(0, (int) ($item['min_participants'] ?? 0)),
            'max_participants' => max(0, (int) ($item['max_participants'] ?? 0)),
            'all_start_times' => !empty($item['all_start_times']),
            'start_time_ids' => array_values(array_filter(array_map('sanitize_text_field', (array) ($item['start_time_ids'] ?? [])))),
            'internal_label' => sanitize_text_field($item['internal_label'] ?? ''),
            'color' => $color,
        ];
    }

    return $rules;
}

function opencomune_get_esperienza_availability_rules($post_id) {
    $stored = get_post_meta((int) $post_id, 'tour_availability_rules', true);

    return opencomune_normalize_availability_rules(is_array($stored) ? $stored : []);
}

function opencomune_save_esperienza_availability_rules($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $payload = $post_data['tour_availability_rules_payload'] ?? '';
    $decoded = [];
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode(wp_unslash($payload), true);
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $rules = opencomune_normalize_availability_rules($decoded);
    update_post_meta($post_id, 'tour_availability_rules', $rules);
    opencomune_sync_legacy_schedule_dates_from_availability($post_id);

    return $rules;
}

/**
 * Deriva tour_start_date / tour_end_date dal calendario (unica fonte di verità per le date vendibili).
 */
function opencomune_get_esperienza_availability_date_bounds($post_id) {
    $post_id = (int) $post_id;
    $rules = opencomune_get_esperienza_availability_rules($post_id);
    if (empty($rules)) {
        return null;
    }

    $from = wp_date('Y-m-d');
    $to = wp_date('Y-m-d', strtotime('+3 years'));
    $occurrences = opencomune_expand_availability_rules($post_id, $from, $to);
    if (empty($occurrences)) {
        return null;
    }

    $dates = [];
    foreach ($occurrences as $row) {
        if (!empty($row['date'])) {
            $dates[] = $row['date'];
        }
    }
    if (empty($dates)) {
        return null;
    }

    sort($dates);

    return [
        'start' => $dates[0],
        'end' => $dates[count($dates) - 1],
        'mode' => $dates[0] !== $dates[count($dates) - 1] ? 'range' : 'single',
    ];
}

function opencomune_sync_legacy_schedule_dates_from_availability($post_id) {
    $post_id = (int) $post_id;
    $bounds = opencomune_get_esperienza_availability_date_bounds($post_id);
    if ($bounds === null) {
        return;
    }

    update_post_meta($post_id, 'tour_start_date', $bounds['start']);
    update_post_meta($post_id, 'tour_end_date', $bounds['end']);
    update_post_meta($post_id, 'tour_date_mode', $bounds['mode']);
}

function opencomune_availability_weekday_key_from_date($date_ymd) {
    $ts = strtotime($date_ymd . ' 12:00:00');
    if (!$ts) {
        return '';
    }

    $map = [
        'Sun' => 'sunday',
        'Mon' => 'monday',
        'Tue' => 'tuesday',
        'Wed' => 'wednesday',
        'Thu' => 'thursday',
        'Fri' => 'friday',
        'Sat' => 'saturday',
    ];
    $key = gmdate('D', $ts);

    return $map[$key] ?? '';
}

function opencomune_availability_experience_bounds($post_id) {
    $start = get_post_meta($post_id, 'tour_start_date', true);
    $end = get_post_meta($post_id, 'tour_end_date', true);

    return [
        'start' => $start ?: '',
        'end' => $end ?: '',
    ];
}

function opencomune_availability_clamp_range($from, $to, $max_days = 120) {
    $from_ts = strtotime($from);
    $to_ts = strtotime($to);
    if (!$from_ts || !$to_ts || $to_ts < $from_ts) {
        $from_ts = strtotime('today');
        $to_ts = strtotime('+60 days', $from_ts);
    }

    $max_to = strtotime('+' . (int) $max_days . ' days', $from_ts);
    if ($to_ts > $max_to) {
        $to_ts = $max_to;
    }

    return [gmdate('Y-m-d', $from_ts), gmdate('Y-m-d', $to_ts)];
}

function opencomune_availability_rule_applies_on_date(array $rule, $date_ymd) {
    $bounds = [
        'from' => $rule['effective_from'] ?: ($rule['date_from'] ?: ''),
        'until' => $rule['effective_until'] ?: ($rule['date_to'] ?: ''),
    ];

    if ($rule['rule_type'] === 'single_date') {
        return $rule['single_date'] === $date_ymd;
    }

    if ($bounds['from'] !== '' && $date_ymd < $bounds['from']) {
        return false;
    }
    if ($bounds['until'] !== '' && $date_ymd > $bounds['until']) {
        return false;
    }

    switch ($rule['rule_type']) {
        case 'weekly':
            $weekday = opencomune_availability_weekday_key_from_date($date_ymd);
            return $weekday !== '' && in_array($weekday, $rule['weekdays'], true);
        case 'yearly_months':
            $month = (int) gmdate('n', strtotime($date_ymd . ' 12:00:00'));
            return !empty($rule['months']) && in_array($month, $rule['months'], true);
        case 'date_range':
            if ($rule['date_from'] === '' || $rule['date_to'] === '') {
                return false;
            }
            return $date_ymd >= $rule['date_from'] && $date_ymd <= $rule['date_to'];
        default:
            return false;
    }
}

function opencomune_availability_start_times_for_rule($post_id, array $rule) {
    $all = opencomune_get_esperienza_start_times($post_id);
    if (empty($all)) {
        return [['id' => '_default', 'time' => '09:00', 'duration_hours' => 0, 'duration_minutes' => 0]];
    }

    if (!empty($rule['all_start_times']) || empty($rule['start_time_ids'])) {
        return $all;
    }

    $selected = [];
    foreach ($all as $row) {
        if (in_array($row['id'], $rule['start_time_ids'], true)) {
            $selected[] = $row;
        }
    }

    return $selected;
}

function opencomune_availability_operating_hours_allows($post_id, $date_ymd, $time_hm) {
    $config = opencomune_get_esperienza_operating_hours($post_id);
    if (empty($config['enabled'])) {
        return true;
    }

    $weekday = opencomune_availability_weekday_key_from_date($date_ymd);
    $day = $config['default'][$weekday] ?? null;
    if (!$day || empty($day['enabled'])) {
        return false;
    }

    if ($day['open'] === '' || $day['close'] === '') {
        return true;
    }

    return $time_hm >= $day['open'] && $time_hm <= $day['close'];
}

function opencomune_availability_cap_max_participants($post_id, $rule_max) {
    $capacity = opencomune_get_esperienza_capacity_payload($post_id);
    $rule_max = max(0, (int) $rule_max);

    if ($capacity['mode'] === 'limited' && $capacity['max_participants'] !== null) {
        $global_max = (int) $capacity['max_participants'];
        if ($global_max > 0) {
            return $rule_max > 0 ? min($rule_max, $global_max) : $global_max;
        }
    }

    return $rule_max;
}

function opencomune_availability_occurrence_id($tour_id, $date_ymd, $time_hm, $rule_id) {
    return (int) sprintf('%u', crc32((int) $tour_id . '|' . $date_ymd . '|' . $time_hm . '|' . $rule_id));
}

function opencomune_expand_availability_rules($post_id, $from, $to, $rules_override = null) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    static $cache = [];
    [$from, $to] = opencomune_availability_clamp_range($from, $to);
    $rules = $rules_override !== null
        ? opencomune_normalize_availability_rules($rules_override)
        : opencomune_get_esperienza_availability_rules($post_id);
    $cache_key = $post_id . '|' . $from . '|' . $to . '|' . md5(wp_json_encode($rules));
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }
    if (empty($rules)) {
        $cache[$cache_key] = [];
        return [];
    }

    $exp_bounds = opencomune_availability_experience_bounds($post_id);
    $booking_schedule = opencomune_get_esperienza_booking_schedule_type($post_id);
    $map = [];

    $cursor = strtotime($from . ' 00:00:00');
    $end_ts = strtotime($to . ' 23:59:59');

    while ($cursor <= $end_ts) {
        $date_ymd = gmdate('Y-m-d', $cursor);

        if ($exp_bounds['start'] !== '' && $date_ymd < $exp_bounds['start']) {
            $cursor = strtotime('+1 day', $cursor);
            continue;
        }
        if ($exp_bounds['end'] !== '' && $date_ymd > $exp_bounds['end']) {
            break;
        }

        foreach ($rules as $rule) {
            if (!opencomune_availability_rule_applies_on_date($rule, $date_ymd)) {
                continue;
            }

            $times = opencomune_availability_start_times_for_rule($post_id, $rule);
            foreach ($times as $start_time) {
                $time_hm = opencomune_sanitize_operating_hours_time($start_time['time'] ?? '');
                if ($time_hm === '' && $booking_schedule === 'datetime') {
                    continue;
                }
                if ($booking_schedule === 'date_only') {
                    $time_hm = '00:00';
                }
                if ($booking_schedule === 'pass') {
                    $time_hm = '00:00';
                }

                if (!opencomune_availability_operating_hours_allows($post_id, $date_ymd, $time_hm)) {
                    continue;
                }

                $max_participants = opencomune_availability_cap_max_participants($post_id, $rule['max_participants']);
                $slot_key = $date_ymd . '|' . $time_hm;
                $map[$slot_key] = [
                    'id' => opencomune_availability_occurrence_id($post_id, $date_ymd, $time_hm, $rule['id']),
                    'tour_id' => $post_id,
                    'date' => $date_ymd,
                    'time' => $time_hm,
                    'rule_id' => $rule['id'],
                    'min_participants' => max(0, (int) $rule['min_participants']),
                    'max_participants' => $max_participants,
                    'internal_label' => $rule['internal_label'],
                    'external_label' => '',
                    'color' => $rule['color'],
                    'bookable' => true,
                    'requires_approval' => opencomune_get_esperienza_capacity_mode($post_id) === 'on_request',
                ];
            }
        }

        $cursor = strtotime('+1 day', $cursor);
    }

    $occurrences = array_values($map);
    usort($occurrences, static function ($a, $b) {
        return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
    });

    $cache[$cache_key] = $occurrences;

    return $occurrences;
}

function opencomune_availability_has_future($post_id) {
    $today = gmdate('Y-m-d');
    $to = gmdate('Y-m-d', strtotime('+18 months'));
    foreach (opencomune_expand_availability_rules($post_id, $today, $to) as $row) {
        if ($row['date'] >= $today) {
            return true;
        }
    }

    return false;
}

function opencomune_ensure_prenotazioni_booking_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $columns = $wpdb->get_col("DESC {$table}", 0);
    if (!in_array('booking_date', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN booking_date DATE NULL AFTER event_id");
    }
    if (!in_array('booking_time', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN booking_time TIME NULL AFTER booking_date");
    }
    if (!in_array('rule_id', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN rule_id VARCHAR(64) NULL AFTER booking_time");
    }
    if (!in_array('metodo_pagamento', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN metodo_pagamento varchar(20) DEFAULT 'guida' AFTER participants");
    }

    $event_id_type = $wpdb->get_row("SHOW COLUMNS FROM {$table} LIKE 'event_id'");
    if ($event_id_type && isset($event_id_type->Type) && stripos((string) $event_id_type->Type, 'bigint') === false) {
        $wpdb->query("ALTER TABLE {$table} MODIFY COLUMN event_id bigint(20) unsigned NOT NULL");
    }
}

function opencomune_normalize_booking_slot_time_hm($time_raw) {
    $time_raw = trim((string) $time_raw);
    if ($time_raw === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}:\d{2})/', $time_raw, $matches)) {
        $hm = function_exists('opencomune_sanitize_operating_hours_time')
            ? opencomune_sanitize_operating_hours_time($matches[1])
            : $matches[1];

        return $hm !== '' ? $hm : $matches[1];
    }

    return '';
}

/**
 * @return array<string, mixed>|null
 */
function opencomune_enrich_availability_occurrence_row($tour_id, array $row) {
    $tour_id = (int) $tour_id;
    $booked = opencomune_count_booked_participants_for_slot($tour_id, $row['date'], $row['time']);
    $max = (int) $row['max_participants'];
    $row['booked_participants'] = $booked;
    $row['available_spots'] = $max > 0 ? max(0, $max - $booked) : 9999;

    return $row;
}

/**
 * @return array<string, mixed>|null
 */
function opencomune_resolve_availability_occurrence_by_slot($tour_id, $date_ymd, $time_hm, $rule_id = '') {
    $tour_id = (int) $tour_id;
    $date_ymd = trim((string) $date_ymd);
    $time_hm = opencomune_normalize_booking_slot_time_hm($time_hm);
    $rule_id = trim((string) $rule_id);

    if ($tour_id <= 0 || $date_ymd === '') {
        return null;
    }

    $from = gmdate('Y-m-d', strtotime($date_ymd . ' -14 days'));
    $to = gmdate('Y-m-d', strtotime($date_ymd . ' +14 days'));

    foreach (opencomune_expand_availability_rules($tour_id, $from, $to) as $row) {
        if ((string) ($row['date'] ?? '') !== $date_ymd) {
            continue;
        }
        $row_time = opencomune_normalize_booking_slot_time_hm($row['time'] ?? '');
        if ($time_hm !== '' && $row_time !== '' && $row_time !== $time_hm) {
            continue;
        }
        if ($rule_id !== '' && (string) ($row['rule_id'] ?? '') !== $rule_id) {
            continue;
        }

        return opencomune_enrich_availability_occurrence_row($tour_id, $row);
    }

    return null;
}

/**
 * @param object $booking
 * @return array<string, mixed>|null
 */
function opencomune_resolve_availability_occurrence_for_booking($booking) {
    if (!$booking) {
        return null;
    }

    $tour_id = (int) $booking->tour_id;
    if ($tour_id <= 0 || empty(opencomune_get_esperienza_availability_rules($tour_id))) {
        return null;
    }

    $event_id = (int) $booking->event_id;
    if ($event_id > 0) {
        $occurrence = opencomune_resolve_availability_occurrence($tour_id, $event_id);
        if ($occurrence) {
            return $occurrence;
        }
    }

    if (!empty($booking->booking_date)) {
        return opencomune_resolve_availability_occurrence_by_slot(
            $tour_id,
            $booking->booking_date,
            $booking->booking_time ?? '',
            $booking->rule_id ?? ''
        );
    }

    return null;
}

function opencomune_sync_booking_event_id_from_slot($booking_id) {
    global $wpdb;

    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return;
    }

    $occurrence = opencomune_resolve_availability_occurrence_for_booking($booking);
    if (!$occurrence || empty($occurrence['id'])) {
        return;
    }

    $correct_id = (int) $occurrence['id'];
    if ($correct_id <= 0 || (int) $booking->event_id === $correct_id) {
        return;
    }

    $table = $wpdb->prefix . 'tour_prenotazioni';
    $wpdb->update(
        $table,
        ['event_id' => $correct_id],
        ['id' => $booking_id],
        ['%d'],
        ['%d']
    );
}

function opencomune_count_booked_participants_for_slot($tour_id, $date_ymd, $time_hm, $exclude_booking_id = 0) {
    return opencomune_count_booked_participants_for_slot_excluding(
        (int) $tour_id,
        $date_ymd,
        $time_hm,
        (int) $exclude_booking_id
    );
}

function opencomune_count_booked_participants_for_slot_excluding($tour_id, $date_ymd, $time_hm, $exclude_booking_id = 0) {
    global $wpdb;
    opencomune_ensure_prenotazioni_booking_columns();
    $table = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0;
    }

    $tour_id = (int) $tour_id;
    $exclude_booking_id = (int) $exclude_booking_id;
    $date_ymd = (string) $date_ymd;
    $time_hm = (string) $time_hm;

    if ($exclude_booking_id > 0) {
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(participants), 0) FROM {$table}
             WHERE tour_id = %d AND status IN ('confermata', 'pending_payment')
             AND booking_date = %s AND booking_time = %s AND id != %d",
            $tour_id,
            $date_ymd,
            $time_hm,
            $exclude_booking_id
        ));
    } else {
        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(participants), 0) FROM {$table}
             WHERE tour_id = %d AND status IN ('confermata', 'pending_payment')
             AND booking_date = %s AND booking_time = %s",
            $tour_id,
            $date_ymd,
            $time_hm
        ));
    }

    return max(0, (int) $sum);
}

function opencomune_tour_uses_legacy_calendar_slots($tour_id) {
    return empty(opencomune_get_esperienza_availability_rules((int) $tour_id));
}

function opencomune_resolve_availability_occurrence($tour_id, $occurrence_id, $from = null, $to = null) {
    $tour_id = (int) $tour_id;
    $occurrence_id = (int) $occurrence_id;
    if ($tour_id <= 0 || $occurrence_id <= 0) {
        return null;
    }

    if ($from === null) {
        $from = gmdate('Y-m-d', strtotime('-7 days'));
    }
    if ($to === null) {
        $to = gmdate('Y-m-d', strtotime('+18 months'));
    }

    foreach (opencomune_expand_availability_rules($tour_id, $from, $to) as $row) {
        if ((int) $row['id'] === $occurrence_id) {
            return opencomune_enrich_availability_occurrence_row($tour_id, $row);
        }
    }

    return null;
}

function opencomune_calendario_events_for_tour($tour_id, $from = null, $to = null, $rules_override = null) {
    $tour_id = (int) $tour_id;
    if ($tour_id <= 0) {
        return [];
    }

    $rules = $rules_override !== null
        ? opencomune_normalize_availability_rules($rules_override)
        : opencomune_get_esperienza_availability_rules($tour_id);
    if (!empty($rules)) {
        if ($from === null) {
            $from = gmdate('Y-m-01');
        }
        if ($to === null) {
            $to = gmdate('Y-m-t', strtotime($from . ' +2 months'));
        }

        $events = [];
        foreach (opencomune_expand_availability_rules($tour_id, $from, $to, $rules) as $row) {
            $booked = opencomune_count_booked_participants_for_slot($tour_id, $row['date'], $row['time']);
            $max = (int) $row['max_participants'];
            $available = $max > 0 ? max(0, $max - $booked) : 0;
            $label = $row['internal_label'] !== '' ? $row['internal_label'] : __('Disponibile', 'opencomune');
            $title = $label;
            if ($max > 0) {
                $title .= ' (' . $available . ' ' . __('posti', 'opencomune') . ')';
            }

            $start = $row['date'] . 'T' . $row['time'];
            $events[] = [
                'id' => (int) $row['id'],
                'title' => $title,
                'start' => $start,
                'color' => $row['color'],
                'available_spots' => $available,
                'max_participants' => $max,
                'rule_id' => $row['rule_id'],
                'bookable' => !empty($row['bookable']) && ($max <= 0 || $available > 0),
                'requires_approval' => !empty($row['requires_approval']),
            ];
        }

        return $events;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }

    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, data, ora, posti_disponibili, note FROM {$table} WHERE tour_id = %d ORDER BY data, ora",
        $tour_id
    ));

    $events = [];
    foreach ($results as $row) {
        $events[] = [
            'id' => (int) $row->id,
            'title' => ($row->note ? $row->note : __('Disponibile', 'opencomune')) . ($row->posti_disponibili ? ' (' . (int) $row->posti_disponibili . ' posti)' : ''),
            'start' => $row->data . 'T' . $row->ora,
            'color' => '#2563EB',
            'available_spots' => (int) $row->posti_disponibili,
            'bookable' => true,
        ];
    }

    return $events;
}

function opencomune_resolve_booking_event($tour_id, $event_id, $participants = 1, array $slot_hint = []) {
    $tour_id = (int) $tour_id;
    $event_id = (int) $event_id;
    $participants = max(1, (int) $participants);

    if (!empty(opencomune_get_esperienza_availability_rules($tour_id))) {
        $occurrence = opencomune_resolve_availability_occurrence($tour_id, $event_id);
        if (!$occurrence && !empty($slot_hint['booking_date'])) {
            $occurrence = opencomune_resolve_availability_occurrence_by_slot(
                $tour_id,
                $slot_hint['booking_date'],
                $slot_hint['booking_time'] ?? '',
                $slot_hint['rule_id'] ?? ''
            );
        }
        if (!$occurrence) {
            return new WP_Error('event_not_found', __('Slot non trovato.', 'opencomune'));
        }
        $max = (int) $occurrence['max_participants'];
        if ($max > 0 && (int) $occurrence['available_spots'] < $participants) {
            return new WP_Error('no_spots', __('Posti non sufficienti.', 'opencomune'));
        }

        $resolved_event_id = (int) $occurrence['id'];

        return [
            'source' => 'rules',
            'event_id' => $resolved_event_id,
            'booking_date' => $occurrence['date'],
            'booking_time' => $occurrence['time'],
            'rule_id' => $occurrence['rule_id'],
            'email_date' => $occurrence['date'],
            'email_time' => $occurrence['time'],
        ];
    }

    global $wpdb;
    $table_calendario = $wpdb->prefix . 'tour_calendari';
    $evento = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_calendario} WHERE id = %d AND tour_id = %d",
        $event_id,
        $tour_id
    ));

    if (!$evento) {
        return new WP_Error('event_not_found', __('Evento non trovato.', 'opencomune'));
    }

    if ((int) $evento->posti_disponibili > 0 && (int) $evento->posti_disponibili < $participants) {
        return new WP_Error('no_spots', __('Posti non sufficienti.', 'opencomune'));
    }

    return [
        'source' => 'legacy',
        'event_id' => $event_id,
        'booking_date' => $evento->data,
        'booking_time' => $evento->ora,
        'rule_id' => '',
        'email_date' => $evento->data,
        'email_time' => $evento->ora,
        'legacy_posti' => (int) $evento->posti_disponibili,
    ];
}

function opencomune_insert_tour_prenotazione_row(array $data) {
    global $wpdb;
    opencomune_ensure_prenotazioni_booking_columns();

    $table_prenotazioni = $wpdb->prefix . 'tour_prenotazioni';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_prenotazioni (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_id mediumint(9) NOT NULL,
        event_id bigint(20) unsigned NOT NULL,
        booking_date DATE NULL,
        booking_time TIME NULL,
        rule_id VARCHAR(64) NULL,
        nome varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        telefono varchar(20),
        note text,
        participants int(11) DEFAULT 1,
        metodo_pagamento varchar(20) DEFAULT 'guida',
        data_prenotazione datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) DEFAULT 'confermata',
        PRIMARY KEY (id),
        INDEX (tour_id),
        INDEX (event_id),
        INDEX (email)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $defaults = [
        'tour_id' => 0,
        'event_id' => 0,
        'booking_date' => null,
        'booking_time' => null,
        'rule_id' => null,
        'nome' => '',
        'email' => '',
        'telefono' => '',
        'note' => '',
        'participants' => 1,
        'metodo_pagamento' => 'guida',
        'status' => 'confermata',
    ];

    $row = wp_parse_args($data, $defaults);
    $insert = [
        'tour_id' => (int) $row['tour_id'],
        'event_id' => (int) $row['event_id'],
        'nome' => $row['nome'],
        'email' => $row['email'],
        'telefono' => $row['telefono'],
        'note' => $row['note'],
        'participants' => max(1, (int) $row['participants']),
        'status' => $row['status'],
    ];

    if (array_key_exists('metodo_pagamento', $row)) {
        $insert['metodo_pagamento'] = $row['metodo_pagamento'];
    }
    if (!empty($row['booking_date'])) {
        $insert['booking_date'] = $row['booking_date'];
    }
    if (!empty($row['booking_time'])) {
        $insert['booking_time'] = $row['booking_time'];
    }
    if (!empty($row['rule_id'])) {
        $insert['rule_id'] = $row['rule_id'];
    }

    $result = $wpdb->insert($table_prenotazioni, $insert);

    if ($result === false) {
        return false;
    }

    $booking_id = (int) $wpdb->insert_id;
    opencomune_sync_booking_event_id_from_slot($booking_id);

    return $booking_id;
}

function opencomune_after_booking_decrement_legacy($tour_id, $event_id, $participants, array $slot) {
    opencomune_adjust_legacy_calendar_spots((int) $tour_id, (int) $event_id, -max(1, (int) $participants), $slot);
}

function opencomune_restore_legacy_calendar_spots($tour_id, $event_id, $participants, array $slot = []) {
    opencomune_adjust_legacy_calendar_spots((int) $tour_id, (int) $event_id, max(1, (int) $participants), $slot);
}

function opencomune_adjust_legacy_calendar_spots($tour_id, $event_id, $delta, array $slot = []) {
    $tour_id = (int) $tour_id;
    $event_id = (int) $event_id;
    $delta = (int) $delta;
    if ($delta === 0 || $tour_id <= 0 || $event_id <= 0) {
        return;
    }

    global $wpdb;
    $table_calendario = $wpdb->prefix . 'tour_calendari';

    $legacy_posti = (int) ($slot['legacy_posti'] ?? 0);
    if ($legacy_posti <= 0) {
        $evento = $wpdb->get_row($wpdb->prepare(
            "SELECT posti_disponibili FROM {$table_calendario} WHERE id = %d AND tour_id = %d",
            $event_id,
            $tour_id
        ));
        if (!$evento) {
            return;
        }
        $legacy_posti = (int) $evento->posti_disponibili;
        if ($legacy_posti <= 0) {
            return;
        }
    }

    $amount = abs($delta);
    if ($delta < 0) {
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table_calendario} SET posti_disponibili = GREATEST(0, posti_disponibili - %d) WHERE id = %d AND tour_id = %d",
            $amount,
            $event_id,
            $tour_id
        ));
        return;
    }

    $wpdb->query($wpdb->prepare(
        "UPDATE {$table_calendario} SET posti_disponibili = posti_disponibili + %d WHERE id = %d AND tour_id = %d",
        $amount,
        $event_id,
        $tour_id
    ));
}

function opencomune_get_legacy_slot_context_for_booking($booking) {
    if (!$booking) {
        return null;
    }

    global $wpdb;
    $table_calendario = $wpdb->prefix . 'tour_calendari';
    $evento = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_calendario} WHERE id = %d AND tour_id = %d",
        (int) $booking->event_id,
        (int) $booking->tour_id
    ));

    if (!$evento) {
        return null;
    }

    return [
        'source' => 'legacy',
        'event_id' => (int) $booking->event_id,
        'booking_date' => $evento->data,
        'booking_time' => $evento->ora,
        'rule_id' => '',
        'legacy_posti' => (int) $evento->posti_disponibili,
    ];
}

/**
 * @param object $booking Row from tour_prenotazioni.
 */
function opencomune_assert_booking_participants_available($booking, $participants, $exclude_booking_id = 0) {
    $participants = max(1, (int) $participants);
    $tour_id = (int) $booking->tour_id;
    $exclude_booking_id = (int) $exclude_booking_id;

    if (!empty(opencomune_get_esperienza_availability_rules($tour_id))) {
        $date = $booking->booking_date;
        $time = $booking->booking_time;
        if ($date === '' || $time === '') {
            return new WP_Error('invalid_slot', __('Slot prenotazione non valido.', 'opencomune'));
        }

        $occurrence = opencomune_resolve_availability_occurrence_for_booking($booking);
        if (!$occurrence) {
            return new WP_Error('event_not_found', __('Slot non trovato.', 'opencomune'));
        }

        $max = (int) $occurrence['max_participants'];
        if ($max > 0) {
            $booked = opencomune_count_booked_participants_for_slot_excluding($tour_id, $date, $time, $exclude_booking_id);
            if ($booked + $participants > $max) {
                return new WP_Error('no_spots', __('Posti non sufficienti per questo slot.', 'opencomune'));
            }
        }

        return true;
    }

    $slot = opencomune_get_legacy_slot_context_for_booking($booking);
    if (!$slot) {
        return true;
    }

    $legacy_posti = (int) $slot['legacy_posti'];
    if ($legacy_posti <= 0) {
        return true;
    }

    if ($legacy_posti < $participants) {
        return new WP_Error('no_spots', __('Posti non sufficienti per questo slot.', 'opencomune'));
    }

    return true;
}

/**
 * Libera capacità quando una prenotazione attiva viene annullata.
 *
 * @param object $booking
 */
function opencomune_release_booking_capacity($booking) {
    if (!$booking || $booking->status === 'cancellata') {
        return;
    }

    if (!in_array($booking->status, ['confermata', 'pending_payment'], true)) {
        return;
    }

    if (!opencomune_tour_uses_legacy_calendar_slots((int) $booking->tour_id)) {
        return;
    }

    if ($booking->status !== 'confermata') {
        return;
    }

    $slot = opencomune_get_legacy_slot_context_for_booking($booking);
    if (!$slot) {
        return;
    }

    opencomune_restore_legacy_calendar_spots(
        (int) $booking->tour_id,
        (int) $booking->event_id,
        (int) $booking->participants,
        $slot
    );
}

/**
 * @param object $booking
 */
function opencomune_reserve_booking_capacity($booking) {
    if (!$booking || $booking->status === 'cancellata') {
        return;
    }

    $check = opencomune_assert_booking_participants_available(
        $booking,
        (int) $booking->participants,
        (int) $booking->id
    );
    if (is_wp_error($check)) {
        return $check;
    }

    if (!opencomune_tour_uses_legacy_calendar_slots((int) $booking->tour_id)) {
        return true;
    }

    if ($booking->status !== 'confermata') {
        return true;
    }

    $slot = opencomune_get_legacy_slot_context_for_booking($booking);
    if (!$slot) {
        return true;
    }

    opencomune_after_booking_decrement_legacy(
        (int) $booking->tour_id,
        (int) $booking->event_id,
        (int) $booking->participants,
        $slot
    );

    return true;
}

add_action('wp_ajax_opencomune_preview_availability', 'opencomune_ajax_preview_availability');
function opencomune_ajax_preview_availability() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => __('Non autorizzato', 'opencomune')]);
    }

    $tour_id = (int) ($_POST['tour_id'] ?? 0);
    if ($tour_id <= 0 || !opencomune_user_can_edit_esperienza($tour_id)) {
        wp_send_json_error(['message' => __('Non autorizzato', 'opencomune')]);
    }

    $from = sanitize_text_field($_POST['from'] ?? gmdate('Y-m-01'));
    $to = sanitize_text_field($_POST['to'] ?? gmdate('Y-m-t', strtotime($from)));
    $rules_override = null;
    if (!empty($_POST['rules_payload'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['rules_payload']), true);
        if (is_array($decoded)) {
            $rules_override = $decoded;
        }
    }

    $events = opencomune_calendario_events_for_tour($tour_id, $from, $to, $rules_override);
    $today = gmdate('Y-m-d');
    $has_future = false;
    $horizon = gmdate('Y-m-d', strtotime('+18 months'));
    $rules_for_future = $rules_override !== null
        ? opencomune_normalize_availability_rules($rules_override)
        : null;
    foreach (opencomune_expand_availability_rules($tour_id, $today, $horizon, $rules_for_future) as $row) {
        if (($row['date'] ?? '') >= $today) {
            $has_future = true;
            break;
        }
    }

    wp_send_json_success([
        'events' => $events,
        'has_future' => $has_future,
    ]);
}

function opencomune_enqueue_availability_calendar_editor($post_id, $form_id = 'modifica-esperienza-form') {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $handle = 'opencomune-availability-calendar';
    $theme = wp_get_theme();
    wp_enqueue_script(
        $handle,
        get_template_directory_uri() . '/assets/js/availability-calendar.js',
        [],
        $theme->get('Version') ?: '1.0',
        true
    );

    $start_times = [];
    foreach (opencomune_get_esperienza_start_times($post_id) as $row) {
        $start_times[] = [
            'id' => $row['id'],
            'time' => $row['time'],
        ];
    }

    wp_add_inline_script(
        $handle,
        'window.opencomuneAvailabilityCalendar = ' . wp_json_encode([
            'tourId' => $post_id,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'formId' => $form_id,
            'weekdayLabels' => ['L', 'M', 'M', 'G', 'V', 'S', 'D'],
            'colors' => opencomune_availability_color_palette(),
            'startTimes' => $start_times,
            'emptyRulesText' => __('Nessuna regola. Aggiungi la prima regola di disponibilita.', 'opencomune'),
        ]) . ';',
        'before'
    );
}

function opencomune_get_availability_api_payload($post_id, $from = null, $to = null) {
    $post_id = (int) $post_id;
    if ($from === null) {
        $from = gmdate('Y-m-01');
    }
    if ($to === null) {
        $to = gmdate('Y-m-t', strtotime($from));
    }

    $occurrences = opencomune_expand_availability_rules($post_id, $from, $to);
    foreach ($occurrences as &$row) {
        $booked = opencomune_count_booked_participants_for_slot($post_id, $row['date'], $row['time']);
        $max = (int) $row['max_participants'];
        $row['booked_participants'] = $booked;
        $row['available_spots'] = $max > 0 ? max(0, $max - $booked) : null;
    }
    unset($row);

    return [
        'rules' => opencomune_get_esperienza_availability_rules($post_id),
        'has_future' => opencomune_availability_has_future($post_id),
        'from' => $from,
        'to' => $to,
        'occurrences' => $occurrences,
    ];
}
