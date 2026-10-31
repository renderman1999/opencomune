<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int, array<string, mixed>>
 */
function opencomune_planner_admin_datatables_rows($entity) {
    switch ($entity) {
        case 'cities':
            return opencomune_planner_admin_datatables_rows_cities();
        case 'interests':
            return opencomune_planner_admin_datatables_rows_interests();
        case 'focuses':
            return opencomune_planner_admin_datatables_rows_focuses();
        case 'pois':
            return opencomune_planner_admin_datatables_rows_pois();
        case 'requests':
            return opencomune_planner_admin_datatables_rows_requests();
        default:
            return [];
    }
}

/**
 * @return array<int, string>
 */
function opencomune_planner_admin_datatables_search_keys($entity) {
    $map = [
        'cities' => ['name', 'slug', 'status_label'],
        'interests' => ['name', 'slug', 'status_label'],
        'focuses' => ['name', 'slug', 'status_label'],
        'pois' => ['name', 'city_label', 'interests_label', 'status_label', 'address'],
        'requests' => ['created_at', 'trip_label', 'start_address', 'contact_label'],
    ];

    return $map[$entity] ?? ['name'];
}

/**
 * @return array<int, string>
 */
function opencomune_planner_admin_datatables_sort_keys($entity) {
    $map = [
        'cities' => ['name', 'slug', 'destinations_count', 'sort_order', 'status_label'],
        'interests' => ['name', 'slug', 'sort_order', 'status_label'],
        'focuses' => ['name', 'slug', 'sort_order', 'status_label'],
        'pois' => ['name', 'city_label', 'interests_label', 'duration_min', 'status_label'],
        'requests' => ['created_at', 'trip_label', 'start_address', 'contact_label'],
    ];

    return $map[$entity] ?? ['name'];
}

function opencomune_planner_admin_datatables_rows_cities() {
    $rows = [];
    foreach (opencomune_planner_get_cities(false) as $city) {
        $id = (int) $city['id'];
        $edit_url = opencomune_planner_admin_base_url('city-edit', ['id' => $id]);
        $rows[] = [
            'id' => $id,
            'name' => (string) $city['name'],
            'edit_url' => $edit_url,
            'slug' => (string) $city['slug'],
            'destinations_count' => opencomune_planner_count_poi_by_city_slug($city['slug']),
            'sort_order' => (int) $city['sort_order'],
            'status_label' => (int) $city['active'] === 1
                ? __('Attiva', 'opencomune')
                : __('Nascosta', 'opencomune'),
            'delete_action' => opencomune_planner_admin_form_action('cities'),
            'delete_nonce' => wp_create_nonce('opencomune_planner_city'),
        ];
    }

    return $rows;
}

function opencomune_planner_admin_datatables_rows_interests() {
    $rows = [];
    foreach (opencomune_planner_get_interests(false) as $row) {
        $id = (int) $row['id'];
        $rows[] = [
            'id' => $id,
            'name' => (string) $row['name'],
            'edit_url' => opencomune_planner_admin_base_url('interest-edit', ['id' => $id]),
            'slug' => (string) $row['slug'],
            'is_food' => (int) $row['is_food'] === 1,
            'sort_order' => (int) $row['sort_order'],
            'status_label' => (int) $row['active'] === 1
                ? __('Attivo', 'opencomune')
                : __('Nascosto', 'opencomune'),
            'delete_action' => opencomune_planner_admin_form_action('interests'),
            'delete_nonce' => wp_create_nonce('opencomune_planner_interest'),
        ];
    }

    return $rows;
}

function opencomune_planner_admin_datatables_rows_focuses() {
    $rows = [];
    foreach (opencomune_planner_get_focuses(false) as $row) {
        $id = (int) $row['id'];
        $rows[] = [
            'id' => $id,
            'name' => (string) $row['name'],
            'edit_url' => opencomune_planner_admin_base_url('focus-edit', ['id' => $id]),
            'slug' => (string) $row['slug'],
            'sort_order' => (int) $row['sort_order'],
            'status_label' => (int) $row['active'] === 1
                ? __('Attivo', 'opencomune')
                : __('Nascosto', 'opencomune'),
            'delete_action' => opencomune_planner_admin_form_action('focuses'),
            'delete_nonce' => wp_create_nonce('opencomune_planner_focus'),
        ];
    }

    return $rows;
}

function opencomune_planner_admin_datatables_rows_pois() {
    $city_names = opencomune_planner_city_name_map();
    $interest_names = opencomune_planner_interest_name_map();
    $rows = [];

    foreach (opencomune_planner_get_pois(false) as $poi) {
        $id = (int) $poi['id'];
        $cats = array_filter(array_map('trim', explode(',', (string) $poi['categories'])));
        $labels = [];
        foreach ($cats as $slug) {
            $labels[] = $interest_names[$slug] ?? $slug;
        }

        $rows[] = [
            'id' => $id,
            'name' => (string) $poi['name'],
            'address' => (string) ($poi['address'] ?? ''),
            'edit_url' => opencomune_planner_admin_base_url('poi-edit', ['id' => $id]),
            'city_label' => (string) ($city_names[$poi['city']] ?? $poi['city']),
            'interests_label' => $labels !== [] ? implode(', ', $labels) : '—',
            'duration_min' => (int) $poi['duration_min'],
            'status_label' => (int) $poi['active'] === 1
                ? __('Attiva', 'opencomune')
                : __('Nascosta', 'opencomune'),
            'delete_action' => opencomune_planner_admin_form_action('destinations'),
            'delete_nonce' => wp_create_nonce('opencomune_planner_poi'),
        ];
    }

    return $rows;
}

function opencomune_planner_admin_datatables_rows_requests() {
    global $wpdb;

    $table = opencomune_planner_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [];
    }

    $results = $wpdb->get_results(
        "SELECT id, token, start_date, end_date, start_address, contact_name, contact_email, email_sent_at, created_at
         FROM {$table}
         WHERE contact_email IS NOT NULL AND contact_email != ''
         ORDER BY COALESCE(email_sent_at, created_at) DESC",
        ARRAY_A
    );

    $rows = [];
    foreach ($results ?: [] as $row) {
        $contact = trim((string) ($row['contact_name'] ?? ''));
        $email = trim((string) ($row['contact_email'] ?? ''));
        if ($email !== '') {
            $contact_label = $contact !== '' ? $contact . ' – ' . $email : $email;
        } else {
            $contact_label = $contact !== '' ? $contact : '—';
        }

        $id = (int) ($row['id'] ?? 0);

        $rows[] = [
            'id' => $id,
            'name' => $contact_label,
            'created_at' => (string) ($row['email_sent_at'] ?? $row['created_at'] ?? ''),
            'trip_label' => (string) ($row['start_date'] ?? '') . ' → ' . (string) ($row['end_date'] ?? ''),
            'start_address' => (string) ($row['start_address'] ?? ''),
            'contact_label' => $contact_label,
            'edit_url' => $id > 0 ? opencomune_planner_admin_base_url('request-edit', ['id' => $id]) : '',
            'view_url' => add_query_arg('token', $row['token'], home_url('/itinerario/')),
            'delete_action' => opencomune_planner_admin_form_action('requests'),
            'delete_nonce' => wp_create_nonce('opencomune_planner_request'),
        ];
    }

    return $rows;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function opencomune_planner_admin_datatables_filter_rows(array $rows, $search, array $keys) {
    $search = trim((string) $search);
    if ($search === '') {
        return $rows;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
    $filtered = [];

    foreach ($rows as $row) {
        $haystack = '';
        foreach ($keys as $key) {
            $haystack .= ' ' . (string) ($row[$key] ?? '');
        }
        $haystack = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);
        if (strpos($haystack, $needle) !== false) {
            $filtered[] = $row;
        }
    }

    return $filtered;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function opencomune_planner_admin_datatables_sort_rows(array $rows, $sort_key, $direction) {
    $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

    usort($rows, static function ($a, $b) use ($sort_key, $direction) {
        $va = $a[$sort_key] ?? '';
        $vb = $b[$sort_key] ?? '';
        if (is_numeric($va) && is_numeric($vb)) {
            $cmp = (float) $va <=> (float) $vb;
        } else {
            $cmp = strnatcasecmp((string) $va, (string) $vb);
        }

        return $direction === 'asc' ? $cmp : -$cmp;
    });

    return $rows;
}

function opencomune_planner_admin_datatables_response() {
    $draw = (int) ($_POST['draw'] ?? 0);
    $start = max(0, (int) ($_POST['start'] ?? 0));
    $length = max(1, min(100, (int) ($_POST['length'] ?? 10)));
    $entity = sanitize_key((string) ($_POST['entity'] ?? ''));

    $allowed = ['cities', 'interests', 'focuses', 'pois', 'requests'];
    if (!in_array($entity, $allowed, true)) {
        wp_send_json([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    $search_post = isset($_POST['search']) && is_array($_POST['search']) ? wp_unslash($_POST['search']) : [];
    $search_value = sanitize_text_field((string) ($search_post['value'] ?? ''));

    $order_post = isset($_POST['order'][0]) && is_array($_POST['order'][0]) ? wp_unslash($_POST['order'][0]) : [];
    $order_column = (int) ($order_post['column'] ?? 0);
    $order_dir = sanitize_text_field((string) ($order_post['dir'] ?? 'asc'));

    $sort_keys = opencomune_planner_admin_datatables_sort_keys($entity);
    $sort_key = $sort_keys[$order_column] ?? ($sort_keys[0] ?? 'name');

    $all = opencomune_planner_admin_datatables_rows($entity);
    $records_total = count($all);

    $filtered = opencomune_planner_admin_datatables_filter_rows(
        $all,
        $search_value,
        opencomune_planner_admin_datatables_search_keys($entity)
    );
    $filtered = opencomune_planner_admin_datatables_sort_rows($filtered, $sort_key, $order_dir);
    $records_filtered = count($filtered);

    $page_rows = array_slice($filtered, $start, $length);

    wp_send_json([
        'draw' => $draw,
        'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered,
        'data' => array_values($page_rows),
    ]);
}

add_action('wp_ajax_opencomune_planner_admin_datatables', 'opencomune_planner_admin_datatables_callback');
function opencomune_planner_admin_datatables_callback() {
    if (!opencomune_user_can_manage_all_esperienze()) {
        wp_send_json([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'opencomune_planner_admin_dt')) {
        wp_send_json([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => __('Sessione scaduta. Ricarica la pagina.', 'opencomune'),
        ]);
    }

    opencomune_planner_admin_datatables_response();
}
