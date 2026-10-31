<?php
/**
 * Modifica itinerario pubblico (aggiungi / rimuovi tappe, candidati).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $stop
 */
function opencomune_planner_stop_candidate_key(array $stop) {
    if (!empty($stop['poiId'])) {
        return 'poi:' . (int) $stop['poiId'];
    }
    if (!empty($stop['esperienzaId'])) {
        return 'exp:' . (int) $stop['esperienzaId'];
    }
    $source = (string) ($stop['source'] ?? '');
    if ($source === 'poi' && !empty($stop['id'])) {
        return 'poi:' . (int) $stop['id'];
    }
    if ($source === 'esperienza' && !empty($stop['id'])) {
        return 'exp:' . (int) $stop['id'];
    }
    return '';
}

/**
 * @param array<int, array<string, mixed>> $days
 * @return list<string>
 */
function opencomune_planner_trip_used_candidate_keys(array $days) {
    $used = [];
    foreach ($days as $day) {
        if (!is_array($day['stops'] ?? null)) {
            continue;
        }
        foreach ($day['stops'] as $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $key = opencomune_planner_stop_candidate_key($stop);
            if ($key !== '') {
                $used[] = $key;
            }
        }
    }
    return array_values(array_unique($used));
}

/**
 * @param array<string, mixed> $row Trip DB row.
 * @return list<string>
 */
function opencomune_planner_trip_category_slugs(array $row) {
    return array_values(array_filter(array_map('trim', explode(',', (string) ($row['categories'] ?? '')))));
}

/**
 * @param list<array<string, mixed>> $pool
 * @return array<string, mixed>|null
 */
function opencomune_planner_find_pool_candidate(array $pool, $source, $id) {
    $source = $source === 'poi' ? 'poi' : 'esperienza';
    $id = (int) $id;
    foreach ($pool as $item) {
        $item_source = (string) ($item['source'] ?? 'esperienza');
        if ($item_source === 'poi') {
            $item_source = 'poi';
        } else {
            $item_source = 'esperienza';
        }
        if ($item_source === $source && (int) $item['id'] === $id) {
            return $item;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $row
 * @return list<array<string, mixed>>
 */
function opencomune_planner_candidate_pool_for_trip_row(array $row) {
    $city = (string) ($row['city_slug'] ?? '');
    $categories = opencomune_planner_trip_category_slugs($row);
    $pool = opencomune_planner_load_all_candidates($city, $categories);
    if ($pool === [] && $categories !== []) {
        $pool = opencomune_planner_load_all_candidates($city, []);
    }
    if ($pool === [] && $city !== '') {
        $pool = opencomune_planner_load_all_candidates('', $categories);
    }
    if ($pool === [] && $city !== '') {
        $pool = opencomune_planner_load_all_candidates('', []);
    }
    return $pool;
}

/**
 * @param array<string, mixed> $item
 * @return array<string, mixed>
 */
function opencomune_planner_candidate_payload_for_api(array $item) {
    $source = (string) ($item['source'] ?? 'esperienza');
    if ($source !== 'poi') {
        $source = 'esperienza';
    }

    return [
        'key' => opencomune_planner_candidate_key($item),
        'source' => $source,
        'id' => (int) $item['id'],
        'title' => (string) ($item['title'] ?? ''),
        'coverImage' => (string) ($item['cover'] ?? ''),
        'durationMin' => (int) ($item['duration_min'] ?? 60),
        'address' => (string) ($item['address'] ?? ''),
        'citySlug' => (string) ($item['city_slug'] ?? ''),
    ];
}

/**
 * Fine giornata planner (stessa logica slot orari del frontend).
 */
function opencomune_planner_itinerary_day_end_by() {
    return '20:00';
}

function opencomune_planner_itinerary_day_end_hour() {
    return 20;
}

/**
 * @return int|null
 */
function opencomune_planner_itinerary_parse_time_hour($hhmm) {
    $hhmm = substr((string) $hhmm, 0, 5);
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', $hhmm, $m)) {
        return null;
    }
    $h = (int) $m[1];
    if ($h < 0 || $h > 23) {
        return null;
    }
    return $h;
}

/**
 * @param array<int, array<string, mixed>> $days
 */
function opencomune_planner_itinerary_route_start_hour(array $row, array $days) {
    $default = 10;
    $candidates = [];
    $from_trip = opencomune_planner_itinerary_parse_time_hour($row['start_time'] ?? '');
    if ($from_trip !== null) {
        $candidates[] = $from_trip;
    }
    foreach ($days as $day) {
        if (!is_array($day['stops'] ?? null)) {
            continue;
        }
        foreach ($day['stops'] as $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $h = opencomune_planner_itinerary_parse_time_hour($stop['arrive'] ?? '');
            if ($h !== null) {
                $candidates[] = $h;
            }
        }
    }
    if ($candidates === []) {
        return $default;
    }
    return max(0, min(23, min($candidates)));
}

function opencomune_planner_itinerary_ceil_to_hour_minutes($minutes) {
    $minutes = (int) $minutes;
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    if ($m > 0) {
        $h++;
    }
    return $h * 60;
}

/**
 * Minuti disponibili nello slot «+» (insertIndex / subIndex).
 *
 * @param array<int, array<string, mixed>> $days
 */
function opencomune_planner_itinerary_slot_max_duration_minutes(array $days, array $row, $day_index, $insert_index, $sub_index) {
    $day_index = (int) $day_index;
    $insert_index = (int) $insert_index;
    $sub_index = max(0, (int) $sub_index);
    $end_hour = opencomune_planner_itinerary_day_end_hour();
    $route_start = opencomune_planner_itinerary_route_start_hour($row, $days);
    $stops = is_array($days[$day_index]['stops'] ?? null) ? $days[$day_index]['stops'] : [];

    if ($stops !== []) {
        if ($insert_index <= 0) {
            $anchor = $route_start * 60;
            $first_arrive = opencomune_planner_to_minutes((string) ($stops[0]['arrive'] ?? '00:00'));
            $budget = max(0, $first_arrive - $anchor);
        } elseif ($insert_index < count($stops)) {
            $prev = $stops[$insert_index - 1];
            $prev_end = opencomune_planner_to_minutes((string) ($prev['leave'] ?? $prev['arrive'] ?? '00:00'));
            $prev_ceil = opencomune_planner_itinerary_ceil_to_hour_minutes($prev_end);
            $next_start = opencomune_planner_to_minutes((string) ($stops[$insert_index]['arrive'] ?? '00:00'));
            $budget = max(0, $next_start - $prev_ceil);
        } else {
            $last = $stops[count($stops) - 1];
            $last_end = opencomune_planner_to_minutes((string) ($last['leave'] ?? $last['arrive'] ?? '00:00'));
            $last_ceil_hour = intdiv(opencomune_planner_itinerary_ceil_to_hour_minutes($last_end), 60);
            $budget = max(0, 60 * ($end_hour - $last_ceil_hour));
        }
    } else {
        $budget = max(0, 60 * ($end_hour - $route_start));
    }

    return max(0, $budget - 60 * $sub_index);
}

/**
 * @param list<array<string, mixed>> $stops
 * @return list<array<string, mixed>>|WP_Error
 */
function opencomune_planner_reschedule_day_stops(array $stops, array $row) {
    $end_by = opencomune_planner_itinerary_day_end_by();
    $cursor = substr((string) ($row['start_time'] ?? '10:00'), 0, 5);
    $pos_lat = (float) ($row['start_lat'] ?? 0);
    $pos_lng = (float) ($row['start_lng'] ?? 0);
    $out = [];

    foreach ($stops as $stop) {
        if (!is_array($stop)) {
            continue;
        }
        $travel = opencomune_planner_travel_minutes(
            $pos_lat,
            $pos_lng,
            (float) ($stop['lat'] ?? 0),
            (float) ($stop['lng'] ?? 0)
        );
        $arrive = opencomune_planner_add_minutes($cursor, $travel);
        if (opencomune_planner_to_minutes($arrive) > opencomune_planner_to_minutes($end_by)) {
            return new WP_Error(
                'day_full',
                __('Non c\'è più tempo disponibile in questo giorno per questa sequenza di tappe.', 'opencomune'),
                ['status' => 400]
            );
        }
        $duration = (int) ($stop['durationMin'] ?? 60);
        if ($duration <= 0) {
            $duration = 60;
        }
        $leave = opencomune_planner_add_minutes($arrive, $duration);
        if (opencomune_planner_to_minutes($leave) > opencomune_planner_to_minutes($end_by)) {
            return new WP_Error(
                'day_full',
                __('Non c\'è più tempo disponibile in questo giorno per questa sequenza di tappe.', 'opencomune'),
                ['status' => 400]
            );
        }
        $stop['arrive'] = $arrive;
        $stop['leave'] = $leave;
        $out[] = $stop;
        $cursor = $leave;
        $pos_lat = (float) ($stop['lat'] ?? 0);
        $pos_lng = (float) ($stop['lng'] ?? 0);
    }

    return $out;
}

/**
 * @param array<int, array<string, mixed>> $days
 * @param array<string, mixed> $row
 * @return array<int, array<string, mixed>>|WP_Error
 */
function opencomune_planner_refresh_day_route(array $days, array $row, $day_index) {
    $day_index = (int) $day_index;
    $start_lat = (float) ($row['start_lat'] ?? 0);
    $start_lng = (float) ($row['start_lng'] ?? 0);
    $stops = is_array($days[$day_index]['stops'] ?? null) ? $days[$day_index]['stops'] : [];

    if ($stops === []) {
        $days[$day_index]['encodedPolyline'] = null;
        return $days;
    }

    $polyline = opencomune_planner_route_polyline_for_day($start_lat, $start_lng, $stops);
    $days[$day_index]['encodedPolyline'] = is_string($polyline) && $polyline !== '' ? $polyline : null;

    return $days;
}

/**
 * @return array{candidates: list<array<string, mixed>>}|WP_Error
 */
function opencomune_planner_get_trip_candidates(string $token, $max_duration_min = null) {
    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $used = opencomune_planner_trip_used_candidate_keys($days);
    $pool = opencomune_planner_candidate_pool_for_trip_row($row);
    $out = [];
    $max_duration_min = $max_duration_min !== null ? max(0, (int) $max_duration_min) : null;

    foreach ($pool as $item) {
        $key = opencomune_planner_candidate_key($item);
        if (in_array($key, $used, true)) {
            continue;
        }
        $duration = (int) ($item['duration_min'] ?? 60);
        if ($max_duration_min !== null && $max_duration_min > 0 && $duration > $max_duration_min) {
            continue;
        }
        $out[] = opencomune_planner_candidate_payload_for_api($item);
    }

    usort($out, static function ($a, $b) {
        return strcasecmp((string) ($a['title'] ?? ''), (string) ($b['title'] ?? ''));
    });

    return ['candidates' => $out];
}

/**
 * @param array<string, mixed> $item Pool candidate.
 * @return array<string, mixed>
 */
function opencomune_planner_build_stop_from_candidate(array $item) {
    $source = (string) ($item['source'] ?? 'esperienza');
    if ($source !== 'poi') {
        $source = 'esperienza';
    }

    return [
        'esperienzaId' => $source === 'esperienza' ? (int) $item['id'] : null,
        'poiId' => $source === 'poi' ? (int) $item['id'] : null,
        'name' => (string) ($item['title'] ?? ''),
        'address' => opencomune_planner_resolve_item_address($item),
        'lat' => (float) ($item['lat'] ?? 0),
        'lng' => (float) ($item['lng'] ?? 0),
        'durationMin' => (int) ($item['duration_min'] ?? 60),
        'coverImage' => (string) ($item['cover'] ?? ''),
        'permalink' => (string) ($item['permalink'] ?? ''),
        'source' => $source,
        'arrive' => '',
        'leave' => '',
    ];
}

/**
 * @param array<int, array<string, mixed>> $days
 * @param array<string, mixed> $row
 * @return array<int, array<string, mixed>>|WP_Error
 */
function opencomune_planner_insert_candidate_into_day(
    array $days,
    array $row,
    $day_index,
    array $candidate_item,
    $insert_index = null
) {
    $day_index = (int) $day_index;
    if (!isset($days[$day_index]) || !is_array($days[$day_index])) {
        return new WP_Error('invalid_day', __('Giorno non valido.', 'opencomune'), ['status' => 400]);
    }

    $used = opencomune_planner_trip_used_candidate_keys($days);
    $key = opencomune_planner_candidate_key($candidate_item);
    if (in_array($key, $used, true)) {
        return new WP_Error('already_in_trip', __('Questa destinazione è già nel percorso.', 'opencomune'), ['status' => 409]);
    }

    $stops = is_array($days[$day_index]['stops'] ?? null) ? $days[$day_index]['stops'] : [];
    $insert_at = $insert_index === null ? count($stops) : max(0, min(count($stops), (int) $insert_index));

    $stop = opencomune_planner_build_stop_from_candidate($candidate_item);
    array_splice($stops, $insert_at, 0, [$stop]);

    $rescheduled = opencomune_planner_reschedule_day_stops($stops, $row);
    if (is_wp_error($rescheduled)) {
        return $rescheduled;
    }

    $days[$day_index]['stops'] = $rescheduled;

    return opencomune_planner_refresh_day_route($days, $row, $day_index);
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_save_trip_days(string $token, array $days) {
    global $wpdb;

    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $updated = $wpdb->update(
        opencomune_planner_table_name(),
        ['days_json' => wp_json_encode($days)],
        ['token' => sanitize_text_field($token)],
        ['%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_Error('save_failed', __('Salvataggio non riuscito.', 'opencomune'), ['status' => 500]);
    }

    $trip = opencomune_planner_get_trip_by_token($token);
    if ($trip === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    return $trip;
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_add_stop_to_trip(string $token, $day_index, $source, $id, $insert_index = null) {
    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $pool = opencomune_planner_candidate_pool_for_trip_row($row);
    $candidate = opencomune_planner_find_pool_candidate($pool, $source, $id);
    if ($candidate === null) {
        return new WP_Error('not_found', __('Destinazione non disponibile.', 'opencomune'), ['status' => 404]);
    }

    $days = opencomune_planner_insert_candidate_into_day($days, $row, $day_index, $candidate, $insert_index);
    if (is_wp_error($days)) {
        return $days;
    }

    return opencomune_planner_save_trip_days($token, $days);
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_reorder_stops_in_trip(string $token, $day_index, $from_index, $to_index) {
    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $day_index = (int) $day_index;
    $from_index = (int) $from_index;
    $to_index = (int) $to_index;

    if (!isset($days[$day_index]['stops']) || !is_array($days[$day_index]['stops'])) {
        return new WP_Error('invalid_day', __('Giorno non valido.', 'opencomune'), ['status' => 400]);
    }

    $stops = $days[$day_index]['stops'];
    $count = count($stops);
    if ($from_index < 0 || $from_index >= $count || $to_index < 0 || $to_index >= $count) {
        return new WP_Error('invalid_stop', __('Tappa non trovata.', 'opencomune'), ['status' => 404]);
    }

    if ($from_index === $to_index) {
        return opencomune_planner_get_trip_by_token($token);
    }

    $moved = $stops[$from_index];
    array_splice($stops, $from_index, 1);
    array_splice($stops, $to_index, 0, [$moved]);

    $rescheduled = opencomune_planner_reschedule_day_stops($stops, $row);
    if (is_wp_error($rescheduled)) {
        return $rescheduled;
    }

    $days[$day_index]['stops'] = $rescheduled;
    $days = opencomune_planner_refresh_day_route($days, $row, $day_index);
    if (is_wp_error($days)) {
        return $days;
    }

    return opencomune_planner_save_trip_days($token, $days);
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_remove_stop_from_trip(string $token, $day_index, $stop_index) {
    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $day_index = (int) $day_index;
    $stop_index = (int) $stop_index;

    if (!isset($days[$day_index]['stops'][$stop_index])) {
        return new WP_Error('invalid_stop', __('Tappa non trovata.', 'opencomune'), ['status' => 404]);
    }

    array_splice($days[$day_index]['stops'], $stop_index, 1);

    $stops = $days[$day_index]['stops'] ?? [];
    if ($stops !== []) {
        $rescheduled = opencomune_planner_reschedule_day_stops($stops, $row);
        if (is_wp_error($rescheduled)) {
            return $rescheduled;
        }
        $days[$day_index]['stops'] = $rescheduled;
    }

    $days = opencomune_planner_refresh_day_route($days, $row, $day_index);
    if (is_wp_error($days)) {
        return $days;
    }

    return opencomune_planner_save_trip_days($token, $days);
}

/**
 * @param array<int, array<string, mixed>> $days
 * @return array<int, array<string, mixed>>|WP_Error
 */
function opencomune_planner_resize_days_for_dates(array $days, array $dates) {
    $new_days = [];
    foreach ($dates as $index => $date) {
        if (isset($days[$index]) && is_array($days[$index])) {
            $day = $days[$index];
            $day['date'] = $date;
            $day['title'] = $date;
            $new_days[] = $day;
            continue;
        }
        $new_days[] = [
            'date' => $date,
            'title' => $date,
            'stops' => [],
            'encodedPolyline' => null,
        ];
    }

    return $new_days;
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_update_trip_dates(string $token, $start_date, $end_date) {
    $start_date = sanitize_text_field((string) $start_date);
    $end_date = sanitize_text_field((string) $end_date);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        return new WP_Error('invalid_date', __('Date non valide.', 'opencomune'), ['status' => 400]);
    }

    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    if ($start > $end) {
        return new WP_Error('invalid_range', __('La data di fine deve essere successiva all\'inizio.', 'opencomune'), ['status' => 400]);
    }

    $day_count = (int) $start->diff($end)->days + 1;
    if ($day_count > opencomune_planner_max_days()) {
        return new WP_Error(
            'trip_too_long',
            sprintf(
                /* translators: %d: max days */
                __('Durata massima: %d giorni.', 'opencomune'),
                opencomune_planner_max_days()
            ),
            ['status' => 400]
        );
    }

    $row = opencomune_planner_get_trip_row_by_token($token);
    if ($row === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $dates = opencomune_planner_each_date($start_date, $end_date);
    if (count($dates) < count($days)) {
        for ($i = count($dates); $i < count($days); $i++) {
            $stops = $days[$i]['stops'] ?? [];
            if (is_array($stops) && $stops !== []) {
                return new WP_Error(
                    'days_with_stops',
                    __('Accorcia il viaggio solo dopo aver rimosso le tappe nei giorni in eccesso.', 'opencomune'),
                    ['status' => 400]
                );
            }
        }
    }

    $days = opencomune_planner_resize_days_for_dates($days, $dates);
    if (is_wp_error($days)) {
        return $days;
    }

    global $wpdb;
    $updated = $wpdb->update(
        opencomune_planner_table_name(),
        [
            'start_date' => $start_date,
            'end_date' => $end_date,
            'days_json' => wp_json_encode($days),
        ],
        ['token' => sanitize_text_field($token)],
        ['%s', '%s', '%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_Error('save_failed', __('Salvataggio non riuscito.', 'opencomune'), ['status' => 500]);
    }

    $trip = opencomune_planner_get_trip_by_token($token);
    if ($trip === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    return $trip;
}

function opencomune_planner_get_trip_row_by_token($token) {
    global $wpdb;

    $token = sanitize_text_field($token);
    if ($token === '') {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_table_name() . ' WHERE token = %s', $token),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

function opencomune_planner_register_itinerary_edit_rest_routes() {
    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/candidates', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            if (!opencomune_planner_is_enabled()) {
                return new WP_Error('disabled', __('Pianificatore non disponibile.', 'opencomune'), ['status' => 403]);
            }
            $max_duration = $request->get_param('maxDurationMin');
            $max_duration = $max_duration !== null && $max_duration !== '' ? (int) $max_duration : null;
            $result = opencomune_planner_get_trip_candidates((string) $request['token'], $max_duration);
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/stops', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            if (!opencomune_planner_is_enabled()) {
                return new WP_Error('disabled', __('Pianificatore non disponibile.', 'opencomune'), ['status' => 403]);
            }
            $body = $request->get_json_params();
            if (!is_array($body)) {
                $body = [];
            }
            $day_index = isset($body['dayIndex']) ? (int) $body['dayIndex'] : -1;
            $insert_index = array_key_exists('insertIndex', $body) ? (int) $body['insertIndex'] : null;
            $source = isset($body['source']) ? sanitize_key($body['source']) : '';
            $id = isset($body['id']) ? (int) $body['id'] : 0;
            if ($day_index < 0 || $id <= 0 || ($source !== 'poi' && $source !== 'esperienza')) {
                return new WP_Error('invalid_request', __('Richiesta non valida.', 'opencomune'), ['status' => 400]);
            }
            $result = opencomune_planner_add_stop_to_trip(
                (string) $request['token'],
                $day_index,
                $source,
                $id,
                $insert_index
            );
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/stops', [
        'methods' => WP_REST_Server::DELETABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            if (!opencomune_planner_is_enabled()) {
                return new WP_Error('disabled', __('Pianificatore non disponibile.', 'opencomune'), ['status' => 403]);
            }
            $body = $request->get_json_params();
            if (!is_array($body)) {
                $body = [];
            }
            $day_index = isset($body['dayIndex']) ? (int) $body['dayIndex'] : -1;
            $stop_index = isset($body['stopIndex']) ? (int) $body['stopIndex'] : -1;
            if ($day_index < 0 || $stop_index < 0) {
                return new WP_Error('invalid_request', __('Richiesta non valida.', 'opencomune'), ['status' => 400]);
            }
            $result = opencomune_planner_remove_stop_from_trip(
                (string) $request['token'],
                $day_index,
                $stop_index
            );
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/stops/reorder', [
        'methods' => WP_REST_Server::EDITABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            if (!opencomune_planner_is_enabled()) {
                return new WP_Error('disabled', __('Pianificatore non disponibile.', 'opencomune'), ['status' => 403]);
            }
            $body = $request->get_json_params();
            if (!is_array($body)) {
                $body = [];
            }
            $day_index = isset($body['dayIndex']) ? (int) $body['dayIndex'] : -1;
            $from_index = isset($body['fromIndex']) ? (int) $body['fromIndex'] : -1;
            $to_index = isset($body['toIndex']) ? (int) $body['toIndex'] : -1;
            if ($day_index < 0 || $from_index < 0 || $to_index < 0) {
                return new WP_Error('invalid_request', __('Richiesta non valida.', 'opencomune'), ['status' => 400]);
            }
            $result = opencomune_planner_reorder_stops_in_trip(
                (string) $request['token'],
                $day_index,
                $from_index,
                $to_index
            );
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/dates', [
        'methods' => WP_REST_Server::EDITABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            if (!opencomune_planner_is_enabled()) {
                return new WP_Error('disabled', __('Pianificatore non disponibile.', 'opencomune'), ['status' => 403]);
            }
            $body = $request->get_json_params();
            if (!is_array($body)) {
                $body = [];
            }
            $start_date = isset($body['startDate']) ? (string) $body['startDate'] : '';
            $end_date = isset($body['endDate']) ? (string) $body['endDate'] : '';
            $result = opencomune_planner_update_trip_dates((string) $request['token'], $start_date, $end_date);
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);
}
add_action('rest_api_init', 'opencomune_planner_register_itinerary_edit_rest_routes');
