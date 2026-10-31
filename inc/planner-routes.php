<?php
/**
 * Percorsi planner: Google Routes API con fallback OSRM.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param list<array{lat: float, lng: float}> $points
 * @return array{minutes: int, encoded_polyline: string|null}
 */
function opencomune_planner_compute_route_path(array $points) {
    if (count($points) < 2) {
        return ['minutes' => 0, 'encoded_polyline' => null];
    }

    $fallback_minutes = 0;
    for ($i = 1, $len = count($points); $i < $len; $i++) {
        $fallback_minutes += opencomune_planner_travel_minutes(
            $points[$i - 1]['lat'],
            $points[$i - 1]['lng'],
            $points[$i]['lat'],
            $points[$i]['lng']
        );
    }

    $google = opencomune_planner_google_routes($points);
    if ($google !== null && !empty($google['encoded_polyline'])) {
        return $google;
    }

    $osrm = opencomune_planner_osrm_route($points);
    if ($osrm !== null && !empty($osrm['encoded_polyline'])) {
        return $osrm;
    }

    return ['minutes' => $fallback_minutes, 'encoded_polyline' => null];
}

/**
 * Punti percorso solo tra le tappe (senza punto di partenza utente).
 *
 * @param list<array<string, mixed>> $stops
 * @return list<array{lat: float, lng: float}>
 */
function opencomune_planner_route_points_from_stops(array $stops) {
    $points = [];
    foreach ($stops as $stop) {
        if (!is_array($stop)) {
            continue;
        }
        $lat = (float) ($stop['lat'] ?? 0);
        $lng = (float) ($stop['lng'] ?? 0);
        if ($lat === 0.0 && $lng === 0.0) {
            continue;
        }
        $points[] = ['lat' => $lat, 'lng' => $lng];
    }

    return $points;
}

/**
 * @param list<array<string, mixed>> $stops
 */
function opencomune_planner_route_polyline_for_stops(array $stops) {
    $points = opencomune_planner_route_points_from_stops($stops);
    if (count($points) < 2) {
        return null;
    }
    $route = opencomune_planner_compute_route_path($points);

    return $route['encoded_polyline'] ?? null;
}

/**
 * Punto di partenza + tappe (senza duplicati consecutivi).
 *
 * @param list<array<string, mixed>> $stops
 * @return list<array{lat: float, lng: float}>
 */
function opencomune_planner_route_points_for_day($start_lat, $start_lng, array $stops) {
    $points = [];
    $start_lat = (float) $start_lat;
    $start_lng = (float) $start_lng;
    if ($start_lat !== 0.0 || $start_lng !== 0.0) {
        $points[] = ['lat' => $start_lat, 'lng' => $start_lng];
    }

    foreach (opencomune_planner_route_points_from_stops($stops) as $point) {
        $last = $points[count($points) - 1] ?? null;
        if (
            $last !== null
            && abs($last['lat'] - $point['lat']) < 0.00001
            && abs($last['lng'] - $point['lng']) < 0.00001
        ) {
            continue;
        }
        $points[] = $point;
    }

    return $points;
}

/**
 * Percorso stradale dal punto di partenza alle tappe del giorno.
 *
 * @param list<array<string, mixed>> $stops
 */
function opencomune_planner_route_polyline_for_day($start_lat, $start_lng, array $stops) {
    $points = opencomune_planner_route_points_for_day($start_lat, $start_lng, $stops);
    if (count($points) < 2) {
        return null;
    }

    $stops_only = opencomune_planner_route_points_from_stops($stops);
    $cache_seed = wp_json_encode([
        'start' => ['lat' => (float) $start_lat, 'lng' => (float) $start_lng],
        'stops' => $stops_only,
    ]);
    $cache_key = 'oc_planner_route_' . md5((string) $cache_seed);
    $cached = get_transient($cache_key);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $route = opencomune_planner_compute_route_path($points);
    $encoded = $route['encoded_polyline'] ?? null;
    if (is_string($encoded) && $encoded !== '') {
        set_transient($cache_key, $encoded, DAY_IN_SECONDS);
    }

    return $encoded;
}

/**
 * @param list<array{lat: float, lng: float}> $points
 * @return array{minutes: int, encoded_polyline: string}|null
 */
function opencomune_planner_google_routes(array $points) {
    $key = opencomune_get_google_maps_api_key();
    if ($key === '') {
        return null;
    }

    $loc = static function (array $p) {
        return [
            'location' => [
                'latLng' => [
                    'latitude' => $p['lat'],
                    'longitude' => $p['lng'],
                ],
            ],
        ];
    };

    $origin = $points[0];
    $destination = $points[count($points) - 1];
    $intermediates = [];
    for ($i = 1; $i < count($points) - 1; $i++) {
        $intermediates[] = $loc($points[$i]);
    }

    $response = wp_remote_post(
        'https://routes.googleapis.com/directions/v2:computeRoutes',
        [
            'timeout' => 20,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $key,
                'X-Goog-FieldMask' => 'routes.duration,routes.polyline.encodedPolyline',
            ],
            'body' => wp_json_encode([
                'origin' => $loc($origin),
                'destination' => $loc($destination),
                'intermediates' => $intermediates,
                'travelMode' => 'DRIVE',
                'polylineQuality' => 'HIGH_QUALITY',
                'languageCode' => 'it',
            ]),
        ]
    );

    if (is_wp_error($response)) {
        return null;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code !== 200) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    $route = $data['routes'][0] ?? null;
    if (!is_array($route)) {
        return null;
    }

    $encoded = $route['polyline']['encodedPolyline'] ?? '';
    if ($encoded === '') {
        return null;
    }

    $duration = (string) ($route['duration'] ?? '');
    $minutes = 0;
    if ($duration !== '' && str_ends_with($duration, 's')) {
        $minutes = max(5, (int) round((float) rtrim($duration, 's') / 60));
    }

    return [
        'minutes' => $minutes,
        'encoded_polyline' => $encoded,
    ];
}

/**
 * @param list<array{lat: float, lng: float}> $points
 * @return array{minutes: int, encoded_polyline: string}|null
 */
function opencomune_planner_osrm_route(array $points) {
    $coords = implode(';', array_map(static function ($p) {
        return $p['lng'] . ',' . $p['lat'];
    }, $points));

    $url = 'https://router.project-osrm.org/route/v1/driving/' . $coords . '?overview=full&geometries=polyline';
    $response = wp_remote_get($url, [
        'timeout' => 15,
        'headers' => ['User-Agent' => 'opencomune-planner/1.0'],
    ]);

    if (is_wp_error($response) || (int) wp_remote_retrieve_response_code($response) !== 200) {
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'][0]['geometry'])) {
        return null;
    }

    $route = $data['routes'][0];
    return [
        'minutes' => max(5, (int) round((float) ($route['duration'] ?? 0) / 60)),
        'encoded_polyline' => (string) $route['geometry'],
    ];
}
