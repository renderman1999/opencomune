<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/dashboard-esperienze-query.php';

function opencomune_get_esperienze_dashboard_url() {
    return home_url('/esperienze-partner/');
}

function opencomune_ensure_esperienze_dashboard_page() {
    $slug = 'esperienze-partner';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Esperienze', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-esperienze-partner.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_esperienze_dashboard_page', 21);

function opencomune_enqueue_dashboard_esperienze_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    opencomune_enqueue_dashboard_layout_assets();
    opencomune_enqueue_dashboard_esperienze_table_assets();

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
        'opencomune-dashboard-esperienze-page',
        $theme_uri . '/assets/js/dashboard-esperienze-page.js',
        ['sweetalert2'],
        file_exists($theme_dir . '/assets/js/dashboard-esperienze-page.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-esperienze-page.js') : '1.0',
        true
    );

    wp_enqueue_script(
        'opencomune-dashboard-esperienze-datatable',
        $theme_uri . '/assets/js/dashboard-esperienze-datatable.js',
        ['jquery', 'datatables', 'opencomune-dashboard-esperienze-page', 'opencomune-dashboard-esperienze-table'],
        file_exists($theme_dir . '/assets/js/dashboard-esperienze-datatable.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-esperienze-datatable.js') : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-esperienze-page', 'opencomuneDashboardEsperienze', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'editExperienceBase' => home_url('/nuova-esperienza/'),
    ]);
}
