<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_operations_dashboard_url() {
    return home_url('/operazioni/');
}

function opencomune_ensure_operations_dashboard_page() {
    $slug = 'operazioni';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Operazioni', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-operazioni.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_operations_dashboard_page', 21);

/**
 * @return array<int, array{id:int,label:string}>
 */
function opencomune_get_operations_partner_filter_options() {
    if (!function_exists('opencomune_get_incassi_partner_filter_options')) {
        return [];
    }

    return opencomune_get_incassi_partner_filter_options();
}

add_action('wp_ajax_opencomune_get_operations_log', 'opencomune_get_operations_log_callback');
function opencomune_get_operations_log_callback() {
    if (!opencomune_user_can_manage_all_esperienze()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_operations_dashboard', 'nonce');

    $rows = opencomune_query_platform_operations_log([
        'days' => isset($_POST['days']) ? (int) $_POST['days'] : 90,
        'action' => isset($_POST['action_filter']) ? sanitize_key(wp_unslash($_POST['action_filter'])) : '',
        'partner_id' => isset($_POST['partner_id']) ? (int) $_POST['partner_id'] : 0,
        'limit' => isset($_POST['limit']) ? (int) $_POST['limit'] : 200,
    ]);

    wp_send_json_success([
        'items' => opencomune_format_platform_operations_log_rows($rows),
        'count' => count($rows),
    ]);
}

function opencomune_enqueue_dashboard_operations_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    opencomune_enqueue_dashboard_layout_assets();

    wp_enqueue_script(
        'opencomune-dashboard-operations',
        $theme_uri . '/assets/js/dashboard-operations.js',
        [],
        file_exists($theme_dir . '/assets/js/dashboard-operations.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-operations.js') : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-operations', 'opencomuneOperationsDashboard', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_operations_dashboard'),
        'partners' => opencomune_get_operations_partner_filter_options(),
        'i18n' => [
            'loadError' => __('Impossibile caricare il registro operazioni', 'opencomune'),
            'noItems' => __('Nessuna operazione nel periodo selezionato', 'opencomune'),
            'colDate' => __('Data', 'opencomune'),
            'colAction' => __('Operazione', 'opencomune'),
            'colBooking' => __('Prenotazione', 'opencomune'),
            'colExperience' => __('Esperienza', 'opencomune'),
            'colPartner' => __('Partner', 'opencomune'),
            'colUser' => __('Utente', 'opencomune'),
            'colDetail' => __('Dettaglio', 'opencomune'),
            'openBooking' => __('Apri prenotazione', 'opencomune'),
            'noBooking' => __('—', 'opencomune'),
        ],
    ]);
}
