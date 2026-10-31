<?php
/**
 * Pianificatore itinerario basato su esperienze pubblicate.
 */

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_planner_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'oc_planner_trip_request';
}

function opencomune_planner_maybe_create_table() {
    global $wpdb;
    $table = opencomune_planner_table_name();

    $charset = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        token varchar(32) NOT NULL,
        start_date date NOT NULL,
        end_date date NOT NULL,
        start_time varchar(16) NOT NULL,
        start_address varchar(500) NOT NULL,
        start_lat double NOT NULL,
        start_lng double NOT NULL,
        city_slug varchar(64) NOT NULL DEFAULT '',
        traveler_type varchar(64) NOT NULL DEFAULT '',
        visitor_origin varchar(64) NOT NULL DEFAULT '',
        categories text NOT NULL,
        days_json longtext NOT NULL,
        contact_name varchar(255) DEFAULT NULL,
        contact_email varchar(255) DEFAULT NULL,
        contact_phone varchar(64) DEFAULT NULL,
        contact_notes text DEFAULT NULL,
        client_email_message text DEFAULT NULL,
        email_sent_at datetime DEFAULT NULL,
        client_email_sent_at datetime DEFAULT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY token (token),
        KEY created_at (created_at)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    opencomune_planner_maybe_upgrade_trip_request_table();
}

/**
 * Aggiunge colonne mancanti su installazioni esistenti (dbDelta non sempre le applica).
 */
function opencomune_planner_maybe_upgrade_trip_request_table() {
    global $wpdb;
    $table = opencomune_planner_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $columns = $wpdb->get_col("DESCRIBE {$table}", 0);
    if (!is_array($columns)) {
        return;
    }

    if (!in_array('visitor_origin', $columns, true)) {
        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN visitor_origin varchar(64) NOT NULL DEFAULT '' AFTER traveler_type"
        );
    }

    if (!in_array('client_email_message', $columns, true)) {
        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN client_email_message text DEFAULT NULL AFTER contact_notes"
        );
    }

    if (!in_array('client_email_sent_at', $columns, true)) {
        $wpdb->query(
            "ALTER TABLE {$table} ADD COLUMN client_email_sent_at datetime DEFAULT NULL AFTER email_sent_at"
        );
    }
}
add_action('after_switch_theme', 'opencomune_planner_maybe_create_table');
add_action('init', 'opencomune_planner_maybe_create_table', 5);

function opencomune_planner_itinerary_page_slug() {
    return 'itinerario';
}

function opencomune_planner_itinerary_page_url($token = '') {
    $url = home_url('/' . opencomune_planner_itinerary_page_slug() . '/');
    if ($token !== '') {
        $url = add_query_arg('token', sanitize_text_field((string) $token), $url);
    }

    return $url;
}

function opencomune_ensure_planner_itinerary_page() {
    $slug = opencomune_planner_itinerary_page_slug();
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        $template = get_page_template_slug((int) $page->ID);
        if ($template !== 'page-itinerario.php') {
            update_post_meta((int) $page->ID, '_wp_page_template', 'page-itinerario.php');
        }

        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Itinerario', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta((int) $page_id, '_wp_page_template', 'page-itinerario.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_planner_itinerary_page', 21);

add_filter('query_vars', static function (array $vars) {
    $vars[] = 'token';

    return $vars;
});

add_filter('redirect_canonical', static function ($redirect_url, $requested_url) {
    if ($redirect_url === false || $redirect_url === null) {
        return $redirect_url;
    }

    $path = (string) wp_parse_url($requested_url, PHP_URL_PATH);
    if ($path === '') {
        return $redirect_url;
    }

    $path = untrailingslashit($path);
    $itinerary_slug = opencomune_planner_itinerary_page_slug();
    if ($path !== '/' . $itinerary_slug) {
        return $redirect_url;
    }

    if (isset($_GET['token'])) {
        return false;
    }

    return $redirect_url;
}, 10, 2);

function opencomune_planner_is_enabled() {
    return get_option('opencomune_planner_enabled', '1') === '1';
}

function opencomune_planner_public_url() {
    return home_url('/pianificatore-esperienze/');
}

function opencomune_planner_max_days() {
    return max(1, min(7, (int) get_option('opencomune_planner_max_days', 2)));
}

function opencomune_planner_traveler_types() {
    return [
        ['id' => 'couple', 'name' => __('Coppia', 'opencomune')],
        ['id' => 'family', 'name' => __('Famiglia', 'opencomune')],
        ['id' => 'friends', 'name' => __('Amici', 'opencomune')],
        ['id' => 'solo', 'name' => __('Singolo', 'opencomune')],
    ];
}

function opencomune_planner_city_slug($label) {
    return sanitize_title((string) $label);
}

function opencomune_planner_candidate_key(array $item) {
    $source = (string) ($item['source'] ?? 'esperienza');
    if ($source === 'poi') {
        return 'poi:' . (int) $item['id'];
    }
    return 'exp:' . (int) $item['id'];
}

function opencomune_planner_haversine_km($lat1, $lng1, $lat2, $lng2) {
    $r = 6371;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) ** 2
        + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($d_lng / 2) ** 2;
    return $r * (2 * atan2(sqrt($a), sqrt(1 - $a)));
}

function opencomune_planner_travel_minutes($from_lat, $from_lng, $to_lat, $to_lng) {
    $km = opencomune_planner_haversine_km($from_lat, $from_lng, $to_lat, $to_lng);
    return max(8, (int) round(($km / 35) * 60));
}

function opencomune_planner_add_minutes($hhmm, $minutes) {
    $parts = array_map('intval', explode(':', $hhmm));
    $total = ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0) + (int) $minutes;
    $h = (int) floor($total / 60);
    $m = $total % 60;
    return sprintf('%02d:%02d', $h, $m);
}

function opencomune_planner_to_minutes($hhmm) {
    $parts = array_map('intval', explode(':', $hhmm));
    return ($parts[0] ?? 0) * 60 + ($parts[1] ?? 0);
}

function opencomune_planner_each_date($start, $end) {
    $out = [];
    $d = new DateTime($start);
    $last = new DateTime($end);
    while ($d <= $last) {
        $out[] = $d->format('Y-m-d');
        $d->modify('+1 day');
    }
    return $out;
}

/**
 * Indirizzo testuale per una tappa planner (esperienza o POI).
 *
 * @param array<string, mixed> $item
 */
function opencomune_planner_resolve_item_address(array $item) {
    $source = (string) ($item['source'] ?? 'esperienza');
    if ($source === 'poi') {
        return sanitize_text_field((string) ($item['address'] ?? ''));
    }

    $post_id = (int) ($item['id'] ?? 0);
    if ($post_id <= 0) {
        return '';
    }

    if (function_exists('opencomune_get_esperienza_meeting_points')) {
        $points = opencomune_get_esperienza_meeting_points($post_id);
        if ($points !== []) {
            $first = $points[0];
            $line = trim((string) ($first['address'] ?? ''));
            if ($line !== '') {
                return sanitize_text_field($line);
            }
        }
    }

    foreach (['tour_meeting_point', 'indirizzo_ritrovo', 'tour_address'] as $meta_key) {
        $raw = get_post_meta($post_id, $meta_key, true);
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }
        if (function_exists('opencomune_decode_map_display_text')) {
            $line = opencomune_decode_map_display_text($raw, true);
        } else {
            $line = wp_strip_all_tags($raw);
        }
        $line = trim((string) $line);
        if ($line !== '') {
            return sanitize_text_field($line);
        }
    }

    if (function_exists('opencomune_get_esperienza_citta_label')) {
        $city = trim((string) opencomune_get_esperienza_citta_label($post_id));
        if ($city !== '') {
            return sanitize_text_field($city);
        }
    }

    return '';
}

/**
 * @return list<array{id: int, title: string, lat: float, lng: float, duration_min: int, city_slug: string, category_slugs: string[], permalink: string, cover: string, popularity: int}>
 */
function opencomune_planner_load_esperienze_candidates($city_slug = '', $category_slugs = []) {
    $query = new WP_Query([
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => 300,
        'no_found_rows' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $category_slugs = array_map('strval', $category_slugs);
    $items = [];

    foreach ($query->posts as $post) {
        $post_id = (int) $post->ID;
        $lat = (float) get_post_meta($post_id, 'tour_latitude', true);
        $lng = (float) get_post_meta($post_id, 'tour_longitude', true);
        if ($lat === 0.0 && $lng === 0.0) {
            continue;
        }

        $city_label = function_exists('opencomune_get_esperienza_citta_label')
            ? opencomune_get_esperienza_citta_label($post_id)
            : '';
        $item_city = opencomune_planner_city_slug($city_label);
        if ($city_slug !== '' && $item_city !== $city_slug) {
            continue;
        }

        $terms = wp_get_post_terms($post_id, 'categorie_esperienze', ['fields' => 'all']);
        if (is_wp_error($terms)) {
            $terms = [];
        }
        $term_slugs = [];
        foreach ($terms as $term) {
            $term_slugs[] = $term->slug;
        }

        if ($category_slugs !== []) {
            $match = false;
            foreach ($term_slugs as $slug) {
                if (in_array($slug, $category_slugs, true)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }
        }

        $duration = 90;
        if (function_exists('opencomune_tour_duration_parts_total_minutes')) {
            $mins = opencomune_tour_duration_parts_total_minutes(
                opencomune_get_tour_duration_parts($post_id)
            );
            if ($mins > 0) {
                $duration = min(240, max(45, $mins));
            }
        }

        $cover = '';
        if (function_exists('opencomune_get_esperienza_thumbnail_url')) {
            $cover = (string) opencomune_get_esperienza_thumbnail_url($post_id, 'medium');
        }

        $items[] = [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'lat' => $lat,
            'lng' => $lng,
            'duration_min' => $duration,
            'city_slug' => $item_city,
            'category_slugs' => $term_slugs,
            'permalink' => get_permalink($post_id),
            'cover' => $cover,
            'popularity' => (int) get_post_meta($post_id, 'rating', true),
            'source' => 'esperienza',
            'address' => opencomune_planner_resolve_item_address([
                'source' => 'esperienza',
                'id' => $post_id,
            ]),
        ];
    }

    wp_reset_postdata();
    return $items;
}

function opencomune_planner_nn_order($start_lat, $start_lng, array $items) {
    $left = $items;
    $ordered = [];
    $cur_lat = $start_lat;
    $cur_lng = $start_lng;

    while ($left !== []) {
        $best = 0;
        $best_d = PHP_FLOAT_MAX;
        foreach ($left as $i => $row) {
            $d = opencomune_planner_haversine_km($cur_lat, $cur_lng, $row['lat'], $row['lng']);
            if ($d < $best_d) {
                $best_d = $d;
                $best = $i;
            }
        }
        $next = $left[$best];
        unset($left[$best]);
        $left = array_values($left);
        $ordered[] = $next;
        $cur_lat = $next['lat'];
        $cur_lng = $next['lng'];
    }

    return $ordered;
}

function opencomune_planner_resolve_start_from_city($city_slug) {
    $city_slug = sanitize_title((string) $city_slug);
    $cities = function_exists('opencomune_planner_config_cities_payload')
        ? opencomune_planner_config_cities_payload()
        : [];

    foreach ($cities as $city) {
        if (($city['id'] ?? '') === $city_slug && !empty($city['center'])) {
            $center = $city['center'];
            return [
                'lat' => (float) ($center['lat'] ?? 0),
                'lng' => (float) ($center['lng'] ?? 0),
                'address' => sanitize_text_field((string) ($center['address'] ?? $city['name'] ?? '')),
            ];
        }
    }

    if ($cities !== [] && !empty($cities[0]['center'])) {
        $center = $cities[0]['center'];

        return [
            'lat' => (float) ($center['lat'] ?? 0),
            'lng' => (float) ($center['lng'] ?? 0),
            'address' => sanitize_text_field((string) ($center['address'] ?? $cities[0]['name'] ?? '')),
        ];
    }

    return [
        'lat' => 41.9028,
        'lng' => 12.4964,
        'address' => 'Roma',
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array{token: string, days: array<int, array<string, mixed>>}|WP_Error
 */
function opencomune_planner_generate_trip(array $input) {
    if (!opencomune_planner_is_enabled()) {
        return new WP_Error('planner_disabled', __('Il pianificatore non è attivo.', 'opencomune'), ['status' => 403]);
    }

    $start_date = sanitize_text_field($input['startDate'] ?? '');
    $end_date = sanitize_text_field($input['endDate'] ?? '');
    $start_time = sanitize_text_field($input['startTime'] ?? '10:00');
    $start_address = sanitize_text_field($input['startLocation'] ?? '');
    $lat = (float) ($input['lat'] ?? 0);
    $lng = (float) ($input['lng'] ?? 0);
    $city_slug = sanitize_title((string) ($input['city'] ?? ''));
    $traveler = sanitize_key((string) ($input['travelerType'] ?? 'couple'));
    $visitor_origin = sanitize_key((string) ($input['visitorOrigin'] ?? ''));
    $categories = isset($input['categories']) && is_array($input['categories'])
        ? array_map('sanitize_title', $input['categories'])
        : [];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
        return new WP_Error('invalid_dates', __('Date non valide.', 'opencomune'), ['status' => 400]);
    }
    if ($lat === 0.0 && $lng === 0.0) {
        $resolved = opencomune_planner_resolve_start_from_city($city_slug);
        $lat = (float) ($resolved['lat'] ?? 0);
        $lng = (float) ($resolved['lng'] ?? 0);
        if ($start_address === '') {
            $start_address = (string) ($resolved['address'] ?? '');
        }
    }
    if ($lat === 0.0 && $lng === 0.0) {
        return new WP_Error('invalid_location', __('Impossibile determinare il punto di partenza.', 'opencomune'), ['status' => 400]);
    }
    if (count($categories) < 2) {
        return new WP_Error('invalid_categories', __('Seleziona almeno due interessi.', 'opencomune'), ['status' => 400]);
    }

    $today = new DateTime('today');
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    if ($start < $today || $end < $start) {
        return new WP_Error('invalid_range', __('Intervallo date non valido.', 'opencomune'), ['status' => 400]);
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

    $pool = opencomune_planner_load_all_candidates($city_slug, $categories);
    if ($pool === []) {
        $pool = opencomune_planner_load_all_candidates('', $categories);
    }
    if ($pool === []) {
        return new WP_Error('no_experiences', __('Nessuna destinazione disponibile per i filtri scelti.', 'opencomune'), ['status' => 404]);
    }

    $scored = $pool;
    usort($scored, static function ($a, $b) use ($lat, $lng, $categories) {
        $da = opencomune_planner_haversine_km($lat, $lng, $a['lat'], $a['lng']);
        $db = opencomune_planner_haversine_km($lat, $lng, $b['lat'], $b['lng']);
        $aa = count(array_intersect($a['category_slugs'], $categories));
        $ab = count(array_intersect($b['category_slugs'], $categories));
        $sa = $a['popularity'] + $aa * 20 - $da * 3;
        $sb = $b['popularity'] + $ab * 20 - $db * 3;
        return $sb <=> $sa;
    });

    $used = [];
    $days = [];
    $dates = opencomune_planner_each_date($start_date, $end_date);
    $end_by = '20:00';

    foreach ($dates as $date) {
        $day_pool = array_values(array_filter($scored, static function ($row) use ($used) {
            $key = opencomune_planner_candidate_key($row);
            return !in_array($key, $used, true);
        }));
        $picked = opencomune_planner_nn_order($lat, $lng, array_slice($day_pool, 0, 10));
        $picked = array_slice($picked, 0, 4);

        $cursor = substr($start_time, 0, 5);
        $pos_lat = $lat;
        $pos_lng = $lng;
        $stops = [];

        foreach ($picked as $item) {
            if (opencomune_planner_to_minutes($cursor) >= opencomune_planner_to_minutes($end_by)) {
                break;
            }
            $item_key = opencomune_planner_candidate_key($item);
            if (in_array($item_key, $used, true)) {
                continue;
            }

            $travel = opencomune_planner_travel_minutes($pos_lat, $pos_lng, $item['lat'], $item['lng']);
            $arrive = opencomune_planner_add_minutes($cursor, $travel);
            if (opencomune_planner_to_minutes($arrive) > opencomune_planner_to_minutes($end_by)) {
                continue;
            }
            $leave = opencomune_planner_add_minutes($arrive, $item['duration_min']);

            $source = (string) ($item['source'] ?? 'esperienza');
            $stops[] = [
                'esperienzaId' => $source === 'esperienza' ? (int) $item['id'] : null,
                'poiId' => $source === 'poi' ? (int) $item['id'] : null,
                'name' => $item['title'],
                'address' => opencomune_planner_resolve_item_address($item),
                'lat' => $item['lat'],
                'lng' => $item['lng'],
                'arrive' => $arrive,
                'leave' => $leave,
                'durationMin' => $item['duration_min'],
                'coverImage' => $item['cover'],
                'permalink' => $item['permalink'],
                'source' => $source,
            ];
            $used[] = $item_key;
            $cursor = $leave;
            $pos_lat = $item['lat'];
            $pos_lng = $item['lng'];
        }

        $encoded_polyline = null;
        if ($stops !== []) {
            $encoded_polyline = opencomune_planner_route_polyline_for_day($lat, $lng, $stops);
        }

        $days[] = [
            'date' => $date,
            'title' => $date,
            'stops' => $stops,
            'encodedPolyline' => $encoded_polyline,
        ];
    }

    $token = wp_generate_password(16, false, false);
    global $wpdb;
    $wpdb->insert(
        opencomune_planner_table_name(),
        [
            'token' => $token,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'start_time' => substr($start_time, 0, 5),
            'start_address' => $start_address,
            'start_lat' => $lat,
            'start_lng' => $lng,
            'city_slug' => $city_slug,
            'traveler_type' => $traveler,
            'visitor_origin' => $visitor_origin,
            'categories' => implode(',', $categories),
            'days_json' => wp_json_encode($days),
        ],
        ['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s']
    );

    return [
        'token' => $token,
        'days' => $days,
    ];
}

function opencomune_planner_get_config_payload() {
    $cities = function_exists('opencomune_planner_config_cities_payload')
        ? opencomune_planner_config_cities_payload()
        : [];
    $categories = function_exists('opencomune_planner_config_categories_payload')
        ? opencomune_planner_config_categories_payload()
        : [];
    $traveler_types = function_exists('opencomune_planner_config_traveler_types_payload')
        ? opencomune_planner_config_traveler_types_payload()
        : opencomune_planner_traveler_types();

    $default = ['lat' => 41.9028, 'lng' => 12.4964, 'address' => 'Roma'];
    if ($cities !== []) {
        $default = $cities[0]['center'];
    }

    return [
        'enabled' => opencomune_planner_is_enabled(),
        'maxDays' => opencomune_planner_max_days(),
        'cities' => $cities,
        'travelerTypes' => $traveler_types,
        'categories' => $categories,
        'defaultStart' => $default,
    ];
}

function opencomune_planner_get_trip_by_token($token) {
    global $wpdb;

    $token = sanitize_text_field($token);
    if ($token === '') {
        return null;
    }
    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_table_name() . ' WHERE token = %s', $token),
        ARRAY_A
    );
    if (!$row) {
        return null;
    }

    return opencomune_planner_trip_payload_from_row($row);
}

/**
 * @param array<int, array<string, mixed>> $days
 * @return array<int, array<string, mixed>>
 */
function opencomune_planner_enrich_itinerary_days_for_display(array $days, $start_lat = null, $start_lng = null) {
    foreach ($days as $day_index => $day) {
        if (!isset($day['stops']) || !is_array($day['stops'])) {
            continue;
        }
        foreach ($day['stops'] as $stop_index => $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $days[$day_index]['stops'][$stop_index]['card'] = opencomune_planner_itinerary_stop_card(
                $stop,
                (string) ($day['date'] ?? '')
            );
        }

        if ($day['stops'] === []) {
            continue;
        }

        $polyline = opencomune_planner_route_polyline_for_day($start_lat, $start_lng, $day['stops']);
        if (is_string($polyline) && $polyline !== '') {
            $days[$day_index]['encodedPolyline'] = $polyline;
        }
    }

    return $days;
}

/**
 * @return array<int, array{time: string, label: string, bookable: bool, availableSpots: int|null, eventId: int}>
 */
function opencomune_planner_itinerary_available_slots_for_day($post_id, $date_ymd) {
    $post_id = (int) $post_id;
    $date_ymd = sanitize_text_field((string) $date_ymd);
    if ($post_id <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_ymd)) {
        return [];
    }

    if (!function_exists('opencomune_calendario_events_for_tour')) {
        return [];
    }

    $events = opencomune_calendario_events_for_tour($post_id, $date_ymd, $date_ymd);
    $by_time = [];

    foreach ($events as $event) {
        if (!is_array($event)) {
            continue;
        }
        $start = (string) ($event['start'] ?? '');
        if ($start === '' || strpos($start, $date_ymd) !== 0) {
            continue;
        }

        $time = '00:00';
        if (strpos($start, 'T') !== false) {
            $time = substr($start, (int) strpos($start, 'T') + 1, 5);
        }

        $bookable = !empty($event['bookable']);
        $available_spots = array_key_exists('available_spots', $event)
            ? (int) $event['available_spots']
            : null;
        $max_participants = isset($event['max_participants']) ? (int) $event['max_participants'] : 0;
        if ($max_participants > 0 && $available_spots !== null && $available_spots <= 0) {
            $bookable = false;
        }

        if (!$bookable) {
            continue;
        }

        $label = opencomune_planner_itinerary_slot_display_label($post_id, $time);
        $slot = [
            'time' => $time,
            'label' => $label,
            'bookable' => true,
            'availableSpots' => $available_spots,
            'eventId' => (int) ($event['id'] ?? 0),
        ];

        if (!isset($by_time[$time])) {
            $by_time[$time] = $slot;
            continue;
        }

        $prev_spots = $by_time[$time]['availableSpots'];
        if ($available_spots !== null && ($prev_spots === null || $available_spots > $prev_spots)) {
            $by_time[$time] = $slot;
        }
    }

    $slots = array_values($by_time);
    usort($slots, static function ($a, $b) {
        return strcmp((string) $a['time'], (string) $b['time']);
    });

    return $slots;
}

function opencomune_planner_itinerary_slot_display_label($post_id, $time_hm) {
    $time_hm = substr(sanitize_text_field((string) $time_hm), 0, 5);
    if (
        $time_hm === '00:00'
        && function_exists('opencomune_get_esperienza_booking_schedule_type')
    ) {
        $schedule = opencomune_get_esperienza_booking_schedule_type((int) $post_id);
        if ($schedule === 'date_only' || $schedule === 'pass') {
            return __('Disponibile', 'opencomune');
        }
    }

    return $time_hm;
}

/**
 * @param array<string, mixed> $stop
 * @return array<string, mixed>
 */
function opencomune_planner_itinerary_stop_card(array $stop, $day_date = '') {
    $time_label = '';
    if (!empty($stop['arrive']) && !empty($stop['leave'])) {
        $time_label = (string) $stop['arrive'] . ' – ' . (string) $stop['leave'];
    }

    $post_id = !empty($stop['esperienzaId']) ? (int) $stop['esperienzaId'] : 0;
    $cover = (string) ($stop['coverImage'] ?? '');
    $title = (string) ($stop['name'] ?? '');
    $permalink = (string) ($stop['permalink'] ?? '');
    $excerpt = '';
    $max_label = '';
    $duration_label = '';
    $city = '';
    $price_label = '';
    $available_slots = [];
    $address = sanitize_text_field((string) ($stop['address'] ?? ''));

    if ($post_id > 0 && get_post_status($post_id) === 'publish') {
        $title = get_the_title($post_id);
        $permalink = (string) get_permalink($post_id);
        if ($cover === '' && function_exists('opencomune_get_esperienza_thumbnail_url')) {
            $cover = (string) opencomune_get_esperienza_thumbnail_url($post_id, 'large');
        }
        if (function_exists('opencomune_get_home_esperienza_card_meta')) {
            $meta = opencomune_get_home_esperienza_card_meta($post_id);
            $max_label = (string) ($meta['max_label'] ?? '');
            $duration_label = (string) ($meta['duration_label'] ?? '');
            $city = (string) ($meta['city'] ?? '');
            $price_label = (string) ($meta['price_label'] ?? '');
        }
        $excerpt = get_the_excerpt($post_id);
        if (trim($excerpt) === '') {
            $excerpt = wp_trim_words(get_post_field('post_content', $post_id), 22);
        }
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $excerpt), 20);
        if ($day_date !== '') {
            $available_slots = opencomune_planner_itinerary_available_slots_for_day($post_id, $day_date);
        }
        if ($address === '') {
            $address = opencomune_planner_resolve_item_address([
                'source' => 'esperienza',
                'id' => $post_id,
            ]);
        }
    } elseif (!empty($stop['poiId']) && function_exists('opencomune_planner_get_poi_by_id')) {
        $poi = opencomune_planner_get_poi_by_id((int) $stop['poiId']);
        if (is_array($poi) && $address === '') {
            $address = sanitize_text_field((string) ($poi['address'] ?? ''));
        }
    }

    if ($duration_label === '' && !empty($stop['durationMin'])) {
        $duration_label = sprintf(
            /* translators: %d: duration in minutes */
            __('%d min', 'opencomune'),
            (int) $stop['durationMin']
        );
    }

    return [
        'timeLabel' => $time_label,
        'title' => $title,
        'address' => $address,
        'permalink' => $permalink,
        'coverImage' => $cover,
        'excerpt' => $excerpt,
        'maxLabel' => $max_label,
        'durationLabel' => $duration_label,
        'city' => $city,
        'priceLabel' => $price_label,
        'availableSlots' => $available_slots,
    ];
}

function opencomune_planner_register_rest_routes() {
    register_rest_route('opencomune/v1', '/planner/config', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static function () {
            return rest_ensure_response(opencomune_planner_get_config_payload());
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/generate', [
        'methods' => WP_REST_Server::CREATABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            $body = $request->get_json_params();
            if (!is_array($body)) {
                $body = [];
            }
            $result = opencomune_planner_generate_trip($body);
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)', [
        'methods' => WP_REST_Server::READABLE,
        'permission_callback' => '__return_true',
        'callback' => static function (WP_REST_Request $request) {
            $trip = opencomune_planner_get_trip_by_token($request['token']);
            if ($trip === null) {
                return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
            }
            return rest_ensure_response($trip);
        },
    ]);

    register_rest_route('opencomune/v1', '/planner/itinerary/(?P<token>[a-zA-Z0-9_-]+)/contact', [
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
            $result = opencomune_planner_submit_contact((string) $request['token'], $body);
            if (is_wp_error($result)) {
                return $result;
            }
            return rest_ensure_response($result);
        },
    ]);
}
add_action('rest_api_init', 'opencomune_planner_register_rest_routes');

function opencomune_is_planner_public_page() {
    return is_page_template(['page-pianifica-itinerario.php', 'page-itinerario.php']);
}

/**
 * Pianificatore pubblico con header compatto dedicato e senza footer sito.
 */
function opencomune_planner_use_flow_chrome() {
    return opencomune_is_planner_public_page() && opencomune_planner_is_enabled();
}

function opencomune_planner_add_flow_body_class($classes) {
    if (opencomune_planner_use_flow_chrome()) {
        $classes[] = 'oc-planner-wizard-flow';
    }
    return $classes;
}

function opencomune_planner_register_flow_body_class() {
    add_filter('body_class', 'opencomune_planner_add_flow_body_class');
}
add_action('wp', 'opencomune_planner_register_flow_body_class');

function opencomune_enqueue_planner_public_assets() {
    if (!opencomune_is_planner_public_page() || !opencomune_planner_is_enabled()) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    $css = $theme_dir . '/assets/css/planner-public.css';
    if (is_readable($css)) {
        wp_enqueue_style(
            'opencomune-planner-public',
            $theme_uri . '/assets/css/planner-public.css',
            ['opencomune-public-theme'],
            (string) filemtime($css)
        );
    }

    $api_key = opencomune_get_google_maps_api_key();
    $wizard_deps = [];

    $alerts_js = $theme_dir . '/assets/js/planner-alerts.js';
    if (is_readable($alerts_js)) {
        wp_enqueue_script(
            'opencomune-planner-alerts',
            $theme_uri . '/assets/js/planner-alerts.js',
            [],
            (string) filemtime($alerts_js),
            true
        );
        $wizard_deps[] = 'opencomune-planner-alerts';
    }

    if ($api_key !== '' && is_page_template('page-pianifica-itinerario.php')) {
        wp_register_script('opencomune-planner-maps-bootstrap', false, [], null, true);
        wp_enqueue_script('opencomune-planner-maps-bootstrap');
        wp_add_inline_script(
            'opencomune-planner-maps-bootstrap',
            'window.opencomunePlannerMapsReady=function(){window.opencomunePlannerMapsLoaded=true;document.dispatchEvent(new Event("opencomune-planner-maps-ready"));};'
        );
        wp_enqueue_script(
            'google-maps-planner-wizard',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&loading=async&libraries=places&callback=opencomunePlannerMapsReady',
            ['opencomune-planner-maps-bootstrap'],
            null,
            true
        );
        $wizard_deps[] = 'google-maps-planner-wizard';
    }

    if ($api_key !== '' && is_page_template('page-itinerario.php')) {
        wp_register_script('opencomune-planner-itinerary-maps-bootstrap', false, [], null, true);
        wp_enqueue_script('opencomune-planner-itinerary-maps-bootstrap');
        wp_add_inline_script(
            'opencomune-planner-itinerary-maps-bootstrap',
            'window.opencomunePlannerItineraryMapsReady=function(){window.opencomunePlannerItineraryMapsLoaded=true;document.dispatchEvent(new Event("opencomune-planner-itinerary-maps-ready"));};'
        );
        wp_enqueue_script(
            'google-maps',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&loading=async&libraries=places,geometry&callback=opencomunePlannerItineraryMapsReady',
            ['opencomune-planner-itinerary-maps-bootstrap'],
            null,
            true
        );
    }

    $wizard_js = $theme_dir . '/assets/js/planner-wizard.js';
    if (is_page_template('page-pianifica-itinerario.php') && is_readable($wizard_js)) {
        if (!in_array('opencomune-planner-alerts', $wizard_deps, true) && wp_script_is('opencomune-planner-alerts', 'registered')) {
            $wizard_deps[] = 'opencomune-planner-alerts';
        }
        wp_enqueue_script(
            'opencomune-planner-wizard',
            $theme_uri . '/assets/js/planner-wizard.js',
            $wizard_deps,
            (string) filemtime($wizard_js),
            true
        );
        wp_localize_script('opencomune-planner-wizard', 'opencomunePlanner', [
            'restUrl' => esc_url_raw(rest_url('opencomune/v1/planner')),
            'itineraryPage' => esc_url_raw(opencomune_planner_itinerary_page_url()),
            'publicUrl' => esc_url_raw(opencomune_planner_public_url()),
            'mapsReady' => $api_key !== '',
            'landingCities' => function_exists('opencomune_planner_config_cities_payload')
                ? opencomune_planner_config_cities_payload()
                : [],
            'nonce' => wp_create_nonce('wp_rest'),
            'i18n' => [
                'errorGeneric' => __('Impossibile generare l\'itinerario. Riprova.', 'opencomune'),
                'pickCategories' => __('Seleziona almeno due categorie.', 'opencomune'),
                'pickDates' => __('Seleziona le date del viaggio.', 'opencomune'),
                'pickCity' => __('Seleziona una città.', 'opencomune'),
                'pickLandingCity' => __('Seleziona dove vuoi andare.', 'opencomune'),
                'pickStart' => __('Indica il punto di partenza del percorso.', 'opencomune'),
                'pickOrigin' => __('Seleziona il paese di provenienza.', 'opencomune'),
                'landingCityPlaceholder' => __('Dove vuoi andare?', 'opencomune'),
                'generating' => __('Generazione in corso...', 'opencomune'),
                'dismissAlert' => __('Chiudi', 'opencomune'),
            ],
        ]);
    }

    $view_js = $theme_dir . '/assets/js/planner-itinerary.js';
    if (is_page_template('page-itinerario.php') && is_readable($view_js)) {
        if (function_exists('opencomune_enqueue_turnstile_script')) {
            opencomune_enqueue_turnstile_script();
        }
        $itinerary_deps = ['opencomune-planner-alerts'];
        if ($api_key !== '') {
            $itinerary_deps[] = 'google-maps';
        }
        if (function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured()) {
            $itinerary_deps[] = 'cloudflare-turnstile';
        }
        wp_enqueue_script(
            'opencomune-planner-itinerary',
            $theme_uri . '/assets/js/planner-itinerary.js',
            $itinerary_deps,
            (string) filemtime($view_js),
            true
        );
        wp_localize_script('opencomune-planner-itinerary', 'opencomunePlannerItinerary', [
            'restUrl' => esc_url_raw(rest_url('opencomune/v1/planner')),
            'plannerUrl' => esc_url_raw(opencomune_planner_public_url()),
            'termsUrl' => esc_url_raw(home_url('/termini-e-condizioni/')),
            'token' => isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '',
            'nonce' => wp_create_nonce('wp_rest'),
            'maxDays' => opencomune_planner_max_days(),
            'turnstileRequired' => function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured(),
            'turnstileSiteKey' => function_exists('opencomune_get_turnstile_site_key') ? opencomune_get_turnstile_site_key() : '',
            'i18n' => [
                'contactTitle' => __('Informazioni turistiche', 'opencomune'),
                'contactLead' => __('Compila il modulo per inviare il percorso al team.', 'opencomune'),
                'name' => __('Nome', 'opencomune'),
                'email' => __('Email', 'opencomune'),
                'phone' => __('Telefono', 'opencomune'),
                'notes' => __('Informazioni aggiuntive', 'opencomune'),
                'required' => __('Obbligatorio', 'opencomune'),
                'nameHint' => __('Obbligatorio e almeno 3 caratteri', 'opencomune'),
                'termsLabel' => __('Ho letto e accetto i', 'opencomune'),
                'termsLink' => __('termini e condizioni d\'uso', 'opencomune'),
                'termsRequired' => __('Devi accettare i termini e condizioni.', 'opencomune'),
                'back' => __('Indietro', 'opencomune'),
                'submit' => __('Invia richiesta', 'opencomune'),
                'success' => __('Richiesta inviata. Ti contatteremo al più presto.', 'opencomune'),
                'errorGeneric' => __('Invio non riuscito. Riprova.', 'opencomune'),
                'alreadySent' => __('Hai già inviato una richiesta per questo itinerario.', 'opencomune'),
                'turnstileMessage' => __('Completa la verifica anti-spam prima di inviare.', 'opencomune'),
                'bookNow' => __('Prenota ora', 'opencomune'),
                'metaAria' => __('Dettagli esperienza', 'opencomune'),
                'dayTabs' => __('Giorni dell\'itinerario', 'opencomune'),
                'mapAria' => __('Mappa dell\'itinerario', 'opencomune'),
                'removeStop' => __('Rimuovi tappa', 'opencomune'),
                'noStops' => __('Nessuna tappa programmata.', 'opencomune'),
                'slotsLabel' => __('Orari disponibili', 'opencomune'),
                'restart' => __('Riavvia', 'opencomune'),
                'restartAria' => __('Ricomincia la pianificazione da capo', 'opencomune'),
                'sendRoute' => __('Invia percorso', 'opencomune'),
                'closeModal' => __('Chiudi', 'opencomune'),
                'changeStartEnd' => __('Modifica date', 'opencomune'),
                'changeDatesTitle' => __('Modifica date del viaggio', 'opencomune'),
                'changeDatesLead' => __('Aggiorna l\'intervallo di date dell\'itinerario. Se accorci il soggiorno, rimuovi prima le tappe nei giorni in eccesso.', 'opencomune'),
                'changeDatesStart' => __('Data inizio', 'opencomune'),
                'changeDatesEnd' => __('Data fine', 'opencomune'),
                'changeDatesSave' => __('Salva date', 'opencomune'),
                'changeDatesSuccess' => __('Date aggiornate.', 'opencomune'),
                'changeDatesError' => __('Impossibile aggiornare le date.', 'opencomune'),
                'toggleSidebarShow' => __('Mostra timeline', 'opencomune'),
                'toggleSidebarHide' => __('Nascondi timeline', 'opencomune'),
                'startPoint' => __('PUNTO DI PARTENZA', 'opencomune'),
                'endPoint' => __('PUNTO DI ARRIVO', 'opencomune'),
                'dayShort' => __('Giorno %d', 'opencomune'),
                'dismissAlert' => __('Chiudi', 'opencomune'),
                'addStopTitle' => __('Aggiungi al percorso', 'opencomune'),
                'addStopLead' => __('Scegli una destinazione o un\'esperienza da inserire nel giorno selezionato.', 'opencomune'),
                'addStopEmpty' => __('Al momento non ci sono altre destinazioni da suggerire per questo orario. Prova un altro punto «+» nella timeline, oppure lascia più spazio tra le tappe.', 'opencomune'),
                'addStopLoading' => __('Caricamento...', 'opencomune'),
                'addStopError' => __('Impossibile aggiungere la tappa. Riprova.', 'opencomune'),
                'addStopAria' => __('Aggiungi tappa al percorso', 'opencomune'),
                'addStopTypePoi' => __('Destinazione', 'opencomune'),
                'addStopTypeExperience' => __('Esperienza', 'opencomune'),
                'addStopSuccess' => __('Tappa aggiunta al percorso.', 'opencomune'),
                'dragStop' => __('Trascina per riordinare', 'opencomune'),
                'reorderStopError' => __('Impossibile riordinare le tappe. Verifica gli orari disponibili.', 'opencomune'),
            ],
        ]);
    }
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_planner_public_assets', 40);
