<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_planner_admin_base_url($view = 'destinations', $args = []) {
    $query = array_merge([opencomune_planner_admin_view_query_var() => $view], $args);
    return add_query_arg($query, opencomune_get_planner_dashboard_url());
}

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_planner_admin_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return [];
    }

    $action = isset($_POST['oc_planner_action']) ? sanitize_key(wp_unslash($_POST['oc_planner_action'])) : '';
    if ($action === '') {
        return [];
    }

    if (!opencomune_user_can_manage_all_esperienze()) {
        return ['error' => __('Non autorizzato.', 'opencomune')];
    }

    $nonce_actions = [
        'save_settings' => 'opencomune_planner_settings',
        'save_city' => 'opencomune_planner_city',
        'delete_city' => 'opencomune_planner_city',
        'save_interest' => 'opencomune_planner_interest',
        'delete_interest' => 'opencomune_planner_interest',
        'save_focus' => 'opencomune_planner_focus',
        'delete_focus' => 'opencomune_planner_focus',
        'save_poi' => 'opencomune_planner_poi',
        'delete_poi' => 'opencomune_planner_poi',
        'import_interests' => 'opencomune_planner_import_interests',
        'save_request' => 'opencomune_planner_request',
        'resend_request' => 'opencomune_planner_request',
        'delete_request' => 'opencomune_planner_request',
    ];

    if (!isset($nonce_actions[$action])) {
        return ['error' => __('Azione non valida.', 'opencomune')];
    }

    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, $nonce_actions[$action])) {
        return ['error' => __('Sessione scaduta. Ricarica la pagina.', 'opencomune')];
    }

    switch ($action) {
        case 'save_settings':
            update_option('opencomune_planner_enabled', isset($_POST['planner_enabled']) ? '1' : '0');
            $max_days = isset($_POST['planner_max_days']) ? (int) $_POST['planner_max_days'] : 2;
            update_option('opencomune_planner_max_days', max(1, min(7, $max_days)));
            update_option('opencomune_planner_notify_email', sanitize_email(wp_unslash($_POST['planner_notify_email'] ?? '')));
            return [
                'success' => __('Impostazioni salvate.', 'opencomune'),
                'redirect' => opencomune_planner_admin_base_url('settings'),
            ];

        case 'import_interests':
            $n = opencomune_planner_import_interests_from_taxonomy();
            return [
                'success' => sprintf(
                    /* translators: %d: count */
                    _n('%d interesse importato.', '%d interessi importati.', $n, 'opencomune'),
                    $n
                ),
                'redirect' => opencomune_planner_admin_base_url('interests'),
            ];

        case 'save_city':
            return opencomune_planner_admin_save_city();
        case 'delete_city':
            return opencomune_planner_admin_delete_city();
        case 'save_interest':
            return opencomune_planner_admin_save_interest();
        case 'delete_interest':
            return opencomune_planner_admin_delete_interest();
        case 'save_focus':
            return opencomune_planner_admin_save_focus();
        case 'delete_focus':
            return opencomune_planner_admin_delete_focus();
        case 'save_poi':
            return opencomune_planner_admin_save_poi();
        case 'delete_poi':
            return opencomune_planner_admin_delete_poi();
        case 'save_request':
            return opencomune_planner_admin_save_request();
        case 'resend_request':
            return opencomune_planner_admin_resend_request_to_client();
        case 'delete_request':
            return opencomune_planner_admin_delete_request();
    }

    return [];
}

/**
 * Ripopola il form città dopo un POST fallito (senza redirect).
 *
 * @return array<string, mixed>|null
 */
function opencomune_planner_admin_city_draft_from_request() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return null;
    }
    $action = isset($_POST['oc_planner_action']) ? sanitize_key(wp_unslash($_POST['oc_planner_action'])) : '';
    if ($action !== 'save_city') {
        return null;
    }

    $lat = isset($_POST['lat']) ? (float) $_POST['lat'] : 0.0;
    $lng = isset($_POST['lng']) ? (float) $_POST['lng'] : 0.0;

    return [
        'id' => isset($_POST['city_id']) ? (int) $_POST['city_id'] : 0,
        'name' => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
        'slug' => sanitize_title(wp_unslash($_POST['slug'] ?? '')),
        'address' => sanitize_text_field(wp_unslash($_POST['address'] ?? '')),
        'lat' => $lat,
        'lng' => $lng,
        'image' => opencomune_planner_sanitize_image_from_request(),
        'sort_order' => max(0, (int) ($_POST['sort_order'] ?? 0)),
        'active' => isset($_POST['active']) ? 1 : 0,
    ];
}

function opencomune_planner_admin_save_city() {
    global $wpdb;
    opencomune_planner_catalog_maybe_create_tables();
    $table = opencomune_planner_city_table();
    $id = isset($_POST['city_id']) ? (int) $_POST['city_id'] : 0;
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $slug = opencomune_planner_catalog_slugify($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = opencomune_planner_catalog_slugify($name);
    }
    $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
    $lat = (float) ($_POST['lat'] ?? 0);
    $lng = (float) ($_POST['lng'] ?? 0);
    $image = opencomune_planner_sanitize_image_from_request();
    $sort_order = max(0, (int) ($_POST['sort_order'] ?? 0));
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $slug === '' || $address === '') {
        return ['error' => __('Nome, slug e indirizzo sono obbligatori.', 'opencomune')];
    }
    if (!is_finite($lat) || !is_finite($lng) || (abs($lat) < 1e-8 && abs($lng) < 1e-8)) {
        return ['error' => __('Indica coordinate valide (latitudine e longitudine).', 'opencomune')];
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'address' => $address,
        'lat' => $lat,
        'lng' => $lng,
        'image' => $image !== '' ? $image : '',
        'sort_order' => $sort_order,
        'active' => $active,
    ];

    if ($id > 0) {
        $prev = opencomune_planner_get_city_by_id($id);
        if (!$prev) {
            return ['error' => __('Città non trovata.', 'opencomune')];
        }
        $updated = $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d'], ['%d']);
        if ($updated === false) {
            return ['error' => __('Aggiornamento non riuscito. Verifica che lo slug sia univoco.', 'opencomune')];
        }
        if ($prev['slug'] !== $slug) {
            $wpdb->update(
                opencomune_planner_poi_table(),
                ['city' => $slug],
                ['city' => $prev['slug']],
                ['%s'],
                ['%s']
            );
        }
    } else {
        $inserted = $wpdb->insert($table, $data, ['%s', '%s', '%s', '%f', '%f', '%s', '%d', '%d']);
        if ($inserted === false) {
            return ['error' => __('Salvataggio non riuscito. Verifica che lo slug sia univoco.', 'opencomune')];
        }
        $id = (int) $wpdb->insert_id;
        if ($id <= 0) {
            return ['error' => __('Salvataggio non riuscito.', 'opencomune')];
        }
    }

    return [
        'success' => __('Città salvata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('cities'),
    ];
}

/**
 * Gestisce i POST del pianificatore su init (prima di redirect che possono perdere il body).
 */
function opencomune_planner_admin_process_post_on_init() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['oc_planner_action'])) {
        return;
    }

    $result = opencomune_planner_admin_handle_post();
    $GLOBALS['opencomune_planner_admin_post_result'] = $result;

    if (empty($result['redirect'])) {
        return;
    }

    $redirect = $result['redirect'];
    if (!empty($result['success'])) {
        $redirect = add_query_arg('oc_msg', rawurlencode($result['success']), $redirect);
    }
    if (!empty($result['error'])) {
        $redirect = add_query_arg('oc_err', rawurlencode($result['error']), $redirect);
    }

    nocache_headers();
    wp_safe_redirect($redirect);
    exit;
}
add_action('init', 'opencomune_planner_admin_process_post_on_init', 20);

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_planner_admin_get_post_result() {
    if (isset($GLOBALS['opencomune_planner_admin_post_result']) && is_array($GLOBALS['opencomune_planner_admin_post_result'])) {
        return $GLOBALS['opencomune_planner_admin_post_result'];
    }
    return [];
}

function opencomune_planner_admin_delete_city() {
    global $wpdb;
    $id = (int) ($_POST['city_id'] ?? 0);
    $city = opencomune_planner_get_city_by_id($id);
    if (!$city) {
        return ['error' => __('Città non trovata.', 'opencomune')];
    }
    $count = opencomune_planner_count_poi_by_city_slug($city['slug']);
    if ($count > 0) {
        return [
            'error' => sprintf(
                /* translators: %d: count */
                __('Impossibile eliminare: %d destinazioni usano ancora questa città.', 'opencomune'),
                $count
            ),
        ];
    }
    $wpdb->delete(opencomune_planner_city_table(), ['id' => $id], ['%d']);
    return [
        'success' => __('Città eliminata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('cities'),
    ];
}

function opencomune_planner_admin_save_interest() {
    global $wpdb;
    $table = opencomune_planner_interest_table();
    $id = (int) ($_POST['interest_id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $slug = opencomune_planner_catalog_slugify($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = opencomune_planner_catalog_slugify($name);
    }
    $image = opencomune_planner_sanitize_image_from_request();
    $sort_order = max(0, (int) ($_POST['sort_order'] ?? 0));
    $active = isset($_POST['active']) ? 1 : 0;
    $is_food = isset($_POST['is_food']) ? 1 : 0;

    if ($name === '' || $slug === '') {
        return ['error' => __('Nome e slug obbligatori.', 'opencomune')];
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'image' => $image !== '' ? $image : null,
        'sort_order' => $sort_order,
        'active' => $active,
        'is_food' => $is_food,
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s', '%d', '%d', '%d'], ['%d']);
    } else {
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%d', '%d', '%d']);
    }

    return [
        'success' => __('Interesse salvato.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('interests'),
    ];
}

function opencomune_planner_admin_delete_interest() {
    global $wpdb;
    $id = (int) ($_POST['interest_id'] ?? 0);
    $wpdb->delete(opencomune_planner_interest_table(), ['id' => $id], ['%d']);
    return [
        'success' => __('Interesse eliminato.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('interests'),
    ];
}

function opencomune_planner_admin_save_focus() {
    global $wpdb;
    $table = opencomune_planner_focus_table();
    $id = (int) ($_POST['focus_id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $slug = opencomune_planner_catalog_slugify($_POST['slug'] ?? '');
    if ($slug === '') {
        $slug = opencomune_planner_catalog_slugify($name);
    }
    $image = opencomune_planner_sanitize_image_from_request();
    $sort_order = max(0, (int) ($_POST['sort_order'] ?? 0));
    $active = isset($_POST['active']) ? 1 : 0;

    if ($name === '' || $slug === '') {
        return ['error' => __('Nome e slug obbligatori.', 'opencomune')];
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'image' => $image !== '' ? $image : null,
        'sort_order' => $sort_order,
        'active' => $active,
    ];

    if ($id > 0) {
        $wpdb->update($table, $data, ['id' => $id], ['%s', '%s', '%s', '%d', '%d'], ['%d']);
    } else {
        $wpdb->insert($table, $data, ['%s', '%s', '%s', '%d', '%d']);
    }

    return [
        'success' => __('Focus salvato.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('focuses'),
    ];
}

function opencomune_planner_admin_delete_focus() {
    global $wpdb;
    $id = (int) ($_POST['focus_id'] ?? 0);
    $wpdb->delete(opencomune_planner_focus_table(), ['id' => $id], ['%d']);
    return [
        'success' => __('Focus eliminato.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('focuses'),
    ];
}

function opencomune_planner_admin_save_poi() {
    global $wpdb;
    $table = opencomune_planner_poi_table();
    $id = (int) ($_POST['poi_id'] ?? 0);
    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
    if ($slug === '') {
        $slug = sanitize_title($name);
    }
    $description = sanitize_textarea_field(wp_unslash($_POST['description'] ?? ''));
    $city = sanitize_title(wp_unslash($_POST['city'] ?? ''));
    $address = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
    $lat = (float) ($_POST['lat'] ?? 0);
    $lng = (float) ($_POST['lng'] ?? 0);
    $duration = max(15, (int) ($_POST['duration_min'] ?? 60));
    $cover = opencomune_planner_sanitize_image_from_request('cover_image', 'cover_image_attachment_id');
    $popularity = max(0, (int) ($_POST['popularity'] ?? 0));
    $active = isset($_POST['active']) ? 1 : 0;
    $categories = isset($_POST['categories']) && is_array($_POST['categories'])
        ? array_map('sanitize_title', wp_unslash($_POST['categories']))
        : [];
    $valid_slugs = array_keys(opencomune_planner_esperienze_category_slug_name_map());
    $categories = array_values(array_intersect($categories, $valid_slugs));

    if ($name === '' || $slug === '' || $city === '') {
        return ['error' => __('Nome, slug e città obbligatori.', 'opencomune')];
    }
    if ($lat === 0.0 && $lng === 0.0) {
        return ['error' => __('Coordinate obbligatorie.', 'opencomune')];
    }

    $data = [
        'name' => $name,
        'slug' => $slug,
        'description' => $description !== '' ? $description : null,
        'city' => $city,
        'address' => $address !== '' ? $address : null,
        'lat' => $lat,
        'lng' => $lng,
        'duration_min' => $duration,
        'cover_image' => $cover !== '' ? $cover : null,
        'categories' => implode(',', $categories),
        'popularity' => $popularity,
        'active' => $active,
    ];

    if ($id > 0) {
        $wpdb->update(
            $table,
            $data,
            ['id' => $id],
            ['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%d', '%d'],
            ['%d']
        );
    } else {
        $wpdb->insert(
            $table,
            $data,
            ['%s', '%s', '%s', '%s', '%s', '%f', '%f', '%d', '%s', '%s', '%d', '%d']
        );
        $id = (int) $wpdb->insert_id;
    }

    return [
        'success' => __('Destinazione salvata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('destinations'),
    ];
}

function opencomune_planner_admin_delete_poi() {
    global $wpdb;
    $id = (int) ($_POST['poi_id'] ?? 0);
    $wpdb->delete(opencomune_planner_poi_table(), ['id' => $id], ['%d']);
    return [
        'success' => __('Destinazione eliminata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('destinations'),
    ];
}
