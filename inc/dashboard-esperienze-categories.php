<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_esperienze_categories_dashboard_url() {
    return home_url('/categorie-esperienze/');
}

function opencomune_esperienze_categories_can_manage() {
    return opencomune_user_can_manage_all_esperienze() && current_user_can('manage_categories');
}

function opencomune_ensure_esperienze_categories_dashboard_page() {
    $slug = 'categorie-esperienze';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Categorie esperienze', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-categorie-esperienze.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_esperienze_categories_dashboard_page', 21);

add_filter('query_vars', static function (array $vars) {
    $vars[] = 'cat_edit';
    $vars[] = 'oc_msg';
    $vars[] = 'oc_err';

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
    if (!preg_match('#/categorie-esperienze$#', $path)) {
        return $redirect_url;
    }

    if (isset($_GET['cat_edit']) || isset($_GET['oc_msg']) || isset($_GET['oc_err'])) {
        return false;
    }

    return $redirect_url;
}, 10, 2);

function opencomune_esperienze_categories_form_url(array $args = []) {
    $url = opencomune_get_esperienze_categories_dashboard_url();
    if ($args !== []) {
        $url = add_query_arg($args, $url);
    }

    return $url;
}

function opencomune_esperienze_categories_get_edit_id() {
    $raw = get_query_var('cat_edit');
    if ($raw === '' || $raw === false) {
        if (!isset($_GET['cat_edit'])) {
            return null;
        }
        $raw = sanitize_text_field(wp_unslash((string) $_GET['cat_edit']));
    } else {
        $raw = sanitize_text_field((string) $raw);
    }

    if ($raw === 'new') {
        return 0;
    }

    $id = (int) $raw;

    return $id > 0 ? $id : null;
}

function opencomune_esperienze_categories_get_term_for_edit($term_id) {
    $term_id = (int) $term_id;
    if ($term_id <= 0) {
        return null;
    }

    $term = get_term($term_id, 'categorie_esperienze');
    if (!$term instanceof WP_Term || is_wp_error($term)) {
        return null;
    }

    return $term;
}

function opencomune_esperienza_category_get_image_url($term_id) {
    $url = get_term_meta((int) $term_id, 'image', true);

    return is_string($url) && $url !== '' ? esc_url($url) : '';
}

function opencomune_esperienza_category_get_image_thumbnail($term_id) {
    $url = opencomune_esperienza_category_get_image_url($term_id);
    if ($url === '') {
        return '';
    }

    if (!function_exists('opencomune_planner_image_attachment_id_from_url')) {
        require_once get_template_directory() . '/inc/planner-admin-media.php';
    }

    $attachment_id = opencomune_planner_image_attachment_id_from_url($url);
    if ($attachment_id > 0) {
        $thumb = wp_get_attachment_image_url($attachment_id, 'thumbnail');
        if (is_string($thumb) && $thumb !== '') {
            return $thumb;
        }
    }

    return $url;
}

function opencomune_esperienza_category_save_image($term_id, $url) {
    $term_id = (int) $term_id;
    if ($term_id <= 0) {
        return;
    }

    $url = esc_url_raw((string) $url);
    if ($url === '') {
        delete_term_meta($term_id, 'image');

        return;
    }

    update_term_meta($term_id, 'image', $url);
}

function opencomune_esperienze_categories_redirect_with_message($type, $message) {
    $args = ['oc_msg' => $message];
    if ($type === 'error') {
        unset($args['oc_msg']);
        $args['oc_err'] = $message;
    }

    wp_safe_redirect(opencomune_esperienze_categories_form_url($args));
    exit;
}

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_esperienze_categories_handle_post() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return [];
    }

    $action = sanitize_text_field(wp_unslash($_POST['oc_category_action'] ?? ''));
    if ($action === '') {
        return [];
    }

    if (!opencomune_esperienze_categories_can_manage()) {
        return [
            'error' => __('Non autorizzato.', 'opencomune'),
            'redirect' => home_url('/esperienze-partner/'),
        ];
    }

    if ($action === 'save') {
        check_admin_referer('opencomune_esperienza_category_save');

        $term_id = (int) ($_POST['term_id'] ?? 0);
        $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
        $name = opencomune_sanitize_taxonomy_display_name($name);
        $slug = sanitize_title(wp_unslash($_POST['slug'] ?? ''));
        $name_en = sanitize_text_field(wp_unslash($_POST['name_en'] ?? ''));

        if ($name === '') {
            return [
                'error' => __('Inserisci il nome della categoria.', 'opencomune'),
                'redirect' => opencomune_esperienze_categories_form_url([
                    'cat_edit' => $term_id > 0 ? (string) $term_id : 'new',
                ]),
            ];
        }

        $args = [];
        if ($slug !== '') {
            $args['slug'] = $slug;
        }

        if ($term_id > 0) {
            $existing = opencomune_esperienze_categories_get_term_for_edit($term_id);
            if ($existing === null) {
                return [
                    'error' => __('Categoria non trovata.', 'opencomune'),
                    'redirect' => opencomune_get_esperienze_categories_dashboard_url(),
                ];
            }

            $result = wp_update_term($term_id, 'categorie_esperienze', array_merge(['name' => $name], $args));
        } else {
            $result = wp_insert_term($name, 'categorie_esperienze', $args);
            if (!is_wp_error($result)) {
                $term_id = (int) ($result['term_id'] ?? 0);
            }
        }

        if (is_wp_error($result)) {
            return [
                'error' => $result->get_error_message(),
                'redirect' => opencomune_esperienze_categories_form_url([
                    'cat_edit' => $term_id > 0 ? (string) $term_id : 'new',
                ]),
            ];
        }

        if ($term_id <= 0 && !is_wp_error($result)) {
            $term_id = (int) ($result['term_id'] ?? 0);
        }

        if ($name_en === '') {
            delete_term_meta($term_id, 'name_en');
        } else {
            update_term_meta($term_id, 'name_en', $name_en);
        }

        if (!function_exists('opencomune_planner_sanitize_image_from_request')) {
            require_once get_template_directory() . '/inc/planner-admin-media.php';
        }
        $image_url = opencomune_planner_sanitize_image_from_request();
        opencomune_esperienza_category_save_image($term_id, $image_url);

        return [
            'success' => __('Categoria salvata.', 'opencomune'),
            'redirect' => opencomune_get_esperienze_categories_dashboard_url(),
        ];
    }

    if ($action === 'delete') {
        check_admin_referer('opencomune_esperienza_category_delete');

        $term_id = (int) ($_POST['term_id'] ?? 0);
        $term = opencomune_esperienze_categories_get_term_for_edit($term_id);
        if ($term === null) {
            return [
                'error' => __('Categoria non trovata.', 'opencomune'),
                'redirect' => opencomune_get_esperienze_categories_dashboard_url(),
            ];
        }

        $deleted = wp_delete_term($term_id, 'categorie_esperienze');
        if (is_wp_error($deleted)) {
            return [
                'error' => $deleted->get_error_message(),
                'redirect' => opencomune_get_esperienze_categories_dashboard_url(),
            ];
        }

        return [
            'success' => __('Categoria eliminata.', 'opencomune'),
            'redirect' => opencomune_get_esperienze_categories_dashboard_url(),
        ];
    }

    return [];
}

function opencomune_esperienze_categories_process_post_on_init() {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset($_POST['oc_category_action'])) {
        return;
    }

    $request_uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
    if ($request_uri === '' || strpos($request_uri, 'categorie-esperienze') === false) {
        return;
    }

    $result = opencomune_esperienze_categories_handle_post();
    if ($result === []) {
        return;
    }

    $redirect = !empty($result['redirect']) ? $result['redirect'] : opencomune_get_esperienze_categories_dashboard_url();
    if (!empty($result['success'])) {
        $redirect = add_query_arg('oc_msg', $result['success'], $redirect);
    }
    if (!empty($result['error'])) {
        $redirect = add_query_arg('oc_err', $result['error'], $redirect);
    }

    nocache_headers();
    wp_safe_redirect($redirect);
    exit;
}

add_action('init', 'opencomune_esperienze_categories_process_post_on_init', 20);

function opencomune_esperienze_categories_list_terms() {
    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    return is_wp_error($terms) ? [] : $terms;
}

/**
 * @return array<int, array<string, mixed>>
 */
function opencomune_esperienze_categories_datatables_rows() {
    $rows = [];
    foreach (opencomune_esperienze_categories_list_terms() as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }
        $term_id = (int) $term->term_id;
        $name_en = (string) get_term_meta($term_id, 'name_en', true);
        $thumbnail = opencomune_esperienza_category_get_image_thumbnail($term_id);
        $rows[] = [
            'id' => $term_id,
            'thumbnail' => $thumbnail,
            'name' => opencomune_sanitize_taxonomy_display_name((string) $term->name),
            'slug' => (string) $term->slug,
            'name_en' => $name_en,
            'name_en_label' => $name_en !== '' ? $name_en : '—',
            'count' => (int) $term->count,
            'edit_url' => opencomune_esperienze_categories_form_url(['cat_edit' => (string) $term_id]),
            'delete_action' => opencomune_get_esperienze_categories_dashboard_url(),
            'delete_nonce' => wp_create_nonce('opencomune_esperienza_category_delete'),
        ];
    }

    return $rows;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function opencomune_esperienze_categories_datatables_filter_rows(array $rows, $search) {
    $search = trim((string) $search);
    if ($search === '') {
        return $rows;
    }

    $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
    $keys = ['name', 'slug', 'name_en', 'name_en_label'];
    $filtered = [];

    foreach ($rows as $row) {
        $haystack = '';
        foreach ($keys as $key) {
            $haystack .= ' ' . (string) ($row[$key] ?? '');
        }
        $haystack .= ' ' . (string) ($row['count'] ?? '');
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
function opencomune_esperienze_categories_datatables_sort_rows(array $rows, $sort_key, $direction) {
    $sort_keys = ['name', 'slug', 'name_en', 'count'];
    if (!in_array($sort_key, $sort_keys, true)) {
        $sort_key = 'name';
    }

    $direction = strtolower((string) $direction) === 'asc' ? 'asc' : 'desc';

    usort($rows, static function ($a, $b) use ($sort_key, $direction) {
        $va = $a[$sort_key] ?? '';
        $vb = $b[$sort_key] ?? '';
        if ($sort_key === 'count') {
            $cmp = (int) $va <=> (int) $vb;
        } elseif (is_numeric($va) && is_numeric($vb)) {
            $cmp = (float) $va <=> (float) $vb;
        } else {
            $cmp = strnatcasecmp((string) $va, (string) $vb);
        }

        return $direction === 'asc' ? $cmp : -$cmp;
    });

    return $rows;
}

function opencomune_esperienze_categories_datatables_response() {
    $draw = (int) ($_POST['draw'] ?? 0);
    $start = max(0, (int) ($_POST['start'] ?? 0));
    $length = max(1, min(100, (int) ($_POST['length'] ?? 10)));

    $search_post = isset($_POST['search']) && is_array($_POST['search']) ? wp_unslash($_POST['search']) : [];
    $search_value = sanitize_text_field((string) ($search_post['value'] ?? ''));

    $order_post = isset($_POST['order'][0]) && is_array($_POST['order'][0]) ? wp_unslash($_POST['order'][0]) : [];
    $order_column = (int) ($order_post['column'] ?? 0);
    $order_dir = sanitize_text_field((string) ($order_post['dir'] ?? 'asc'));

    $sort_keys = ['', 'name', 'slug', 'name_en', 'count'];
    $sort_key = $sort_keys[$order_column] ?? 'name';
    if ($sort_key === '') {
        $sort_key = 'name';
    }

    $all = opencomune_esperienze_categories_datatables_rows();
    $records_total = count($all);

    $filtered = opencomune_esperienze_categories_datatables_filter_rows($all, $search_value);
    $filtered = opencomune_esperienze_categories_datatables_sort_rows($filtered, $sort_key, $order_dir);
    $records_filtered = count($filtered);

    $page_rows = array_slice($filtered, $start, $length);

    wp_send_json([
        'draw' => $draw,
        'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered,
        'data' => array_values($page_rows),
    ]);
}

add_action('wp_ajax_opencomune_esperienze_categories_datatables', 'opencomune_esperienze_categories_datatables_callback');
function opencomune_esperienze_categories_datatables_callback() {
    if (!opencomune_esperienze_categories_can_manage()) {
        wp_send_json([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
        ]);
    }

    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'opencomune_esperienze_categories_datatables')) {
        wp_send_json([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => __('Sessione scaduta. Ricarica la pagina.', 'opencomune'),
        ]);
    }

    opencomune_esperienze_categories_datatables_response();
}

function opencomune_enqueue_dashboard_esperienze_categories_form_assets() {
    opencomune_enqueue_dashboard_layout_assets();

    if (!function_exists('opencomune_enqueue_planner_admin_media_assets')) {
        require_once get_template_directory() . '/inc/planner-admin-media.php';
    }

    opencomune_enqueue_planner_admin_media_assets();
}

function opencomune_enqueue_dashboard_esperienze_categories_assets($with_datatable = true) {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    opencomune_enqueue_dashboard_layout_assets();

    if (!$with_datatable) {
        return;
    }
    wp_enqueue_style(
        'datatables',
        'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
        [],
        '1.13.6'
    );
    wp_enqueue_script(
        'datatables',
        'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
        ['jquery'],
        '1.13.6',
        true
    );

    $dt_js = $theme_dir . '/assets/js/dashboard-esperienze-categories-datatable.js';
    wp_enqueue_script(
        'opencomune-dashboard-esperienze-categories-datatable',
        $theme_uri . '/assets/js/dashboard-esperienze-categories-datatable.js',
        ['jquery', 'datatables'],
        is_readable($dt_js) ? (string) filemtime($dt_js) : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-esperienze-categories-datatable', 'opencomuneEsperienzeCategoriesDt', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_esperienze_categories_datatables'),
        'i18n' => [
            'editTitle' => __('Modifica', 'opencomune'),
            'deleteTitle' => __('Elimina', 'opencomune'),
            'deleteConfirm' => __('Eliminare la categoria "%s"?', 'opencomune'),
        ],
    ]);
}
