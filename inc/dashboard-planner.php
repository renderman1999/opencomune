<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/planner-admin-datatables.php';
require_once __DIR__ . '/planner-admin-media.php';

function opencomune_planner_dashboard_page_slug() {
    return 'dashboard-pianificatore';
}

function opencomune_get_planner_dashboard_url() {
    return home_url('/' . opencomune_planner_dashboard_page_slug() . '/');
}

function opencomune_planner_admin_view_query_var() {
    return 'oc_planner_view';
}

function opencomune_planner_admin_get_view() {
    $key = opencomune_planner_admin_view_query_var();
    if (isset($_GET[$key])) {
        return sanitize_key(wp_unslash($_GET[$key]));
    }
    if (isset($_GET['view'])) {
        return sanitize_key(wp_unslash($_GET['view']));
    }
    return 'destinations';
}

function opencomune_planner_admin_get_record_id() {
    return isset($_GET['id']) ? (int) $_GET['id'] : 0;
}

function opencomune_planner_admin_form_action($view = 'destinations', $args = []) {
    return opencomune_planner_admin_base_url($view, $args);
}

add_filter('query_vars', static function (array $vars) {
    $vars[] = opencomune_planner_admin_view_query_var();
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
    if (!preg_match('#/(dashboard-pianificatore|pianificatore)$#', $path)) {
        return $redirect_url;
    }
    $view_key = opencomune_planner_admin_view_query_var();
    if (isset($_GET[$view_key]) || isset($_GET['view']) || isset($_GET['id'])) {
        return false;
    }
    return $redirect_url;
}, 10, 2);

/**
 * Vecchio slug /pianificatore/ con query admin → dashboard corretta.
 */
function opencomune_planner_maybe_redirect_legacy_dashboard_url() {
    if (is_admin()) {
        return;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        return;
    }

    $view_key = opencomune_planner_admin_view_query_var();
    $has_planner_query = isset($_GET[$view_key]) || isset($_GET['view']);
    if (!$has_planner_query) {
        return;
    }

    if (is_page(opencomune_planner_dashboard_page_slug())) {
        return;
    }

    $args = [];
    if (isset($_GET[$view_key])) {
        $args[$view_key] = sanitize_key(wp_unslash($_GET[$view_key]));
    } elseif (isset($_GET['view'])) {
        $args[$view_key] = sanitize_key(wp_unslash($_GET['view']));
    }
    if (isset($_GET['id'])) {
        $args['id'] = (int) $_GET['id'];
    }
    if (isset($_GET['oc_msg'])) {
        $args['oc_msg'] = sanitize_text_field(wp_unslash($_GET['oc_msg']));
    }
    if (isset($_GET['oc_err'])) {
        $args['oc_err'] = sanitize_text_field(wp_unslash($_GET['oc_err']));
    }

    wp_safe_redirect(add_query_arg($args, opencomune_get_planner_dashboard_url()));
    exit;
}
add_action('template_redirect', 'opencomune_planner_maybe_redirect_legacy_dashboard_url', 1);

function opencomune_ensure_planner_dashboard_page() {
    $slug = opencomune_planner_dashboard_page_slug();
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        $template = get_post_meta($page->ID, '_wp_page_template', true);
        if ($template !== 'page-pianificatore.php') {
            update_post_meta($page->ID, '_wp_page_template', 'page-pianificatore.php');
        }
        return;
    }

    $legacy = get_page_by_path('pianificatore');
    if ($legacy instanceof WP_Post) {
        $template = get_post_meta($legacy->ID, '_wp_page_template', true);
        if ($template === 'page-pianificatore.php') {
            wp_update_post([
                'ID' => $legacy->ID,
                'post_name' => $slug,
            ]);
            return;
        }
    }

    $page_id = wp_insert_post([
        'post_title' => __('Pianificatore (admin)', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-pianificatore.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_planner_dashboard_page', 21);

function opencomune_enqueue_dashboard_planner_assets($with_map = false) {
    opencomune_enqueue_dashboard_layout_assets();

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    wp_enqueue_script('jquery');
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

    wp_enqueue_script(
        'opencomune-dashboard-planner-datatables',
        $theme_uri . '/assets/js/dashboard-planner-datatables.js',
        ['jquery', 'datatables'],
        file_exists($theme_dir . '/assets/js/dashboard-planner-datatables.js')
            ? (string) filemtime($theme_dir . '/assets/js/dashboard-planner-datatables.js')
            : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-planner-datatables', 'opencomunePlannerAdminDt', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_planner_admin_dt'),
        'i18n' => [
            'delete' => __('Elimina', 'opencomune'),
            'view' => __('Vedi', 'opencomune'),
            'food' => 'food',
            'deleteCityConfirm' => __('Eliminare la città "%s"?', 'opencomune'),
            'deleteInterestConfirm' => __('Eliminare l\'interesse "%s"?', 'opencomune'),
            'deleteFocusConfirm' => __('Eliminare il tipo "%s"?', 'opencomune'),
            'deletePoiConfirm' => __('Eliminare la destinazione "%s"?', 'opencomune'),
            'deleteRequestConfirm' => __('Eliminare la richiesta di "%s"?', 'opencomune'),
            'editTitle' => __('Modifica', 'opencomune'),
            'deleteTitle' => __('Elimina', 'opencomune'),
            'viewTitle' => __('Apri', 'opencomune'),
        ],
    ]);

    $view = opencomune_planner_admin_get_view();
    if (in_array($view, ['city-edit', 'interest-edit', 'focus-edit', 'poi-edit'], true)) {
        opencomune_enqueue_planner_admin_media_assets();
    }

    if (!$with_map) {
        return;
    }

    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key === '') {
        return;
    }

    wp_enqueue_script(
        'google-maps',
        'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places',
        [],
        null,
        true
    );

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $js = $theme_dir . '/assets/js/dashboard-planner-map.js';
    if (is_readable($js)) {
        wp_enqueue_script(
            'opencomune-dashboard-planner-map',
            $theme_uri . '/assets/js/dashboard-planner-map.js',
            ['google-maps'],
            (string) filemtime($js),
            true
        );
    }
}
