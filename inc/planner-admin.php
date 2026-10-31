<?php
/**
 * Gestione pianificatore (dati e salvataggio; UI in dashboard).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * URL gestione pianificatore nella dashboard frontend.
 */
function opencomune_get_planner_admin_url() {
    return opencomune_get_planner_dashboard_url();
}

/**
 * @return string|WP_Error|null Messaggio di successo, errore, o null se nessun submit.
 */
function opencomune_planner_process_settings_save() {
    if (!opencomune_user_can_manage_all_esperienze()) {
        return new WP_Error('forbidden', __('Non autorizzato.', 'opencomune'));
    }

    if (!isset($_POST['opencomune_planner_save'])) {
        return null;
    }

    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'opencomune_planner_settings')) {
        return new WP_Error('invalid_nonce', __('Sessione scaduta. Ricarica la pagina e riprova.', 'opencomune'));
    }

    update_option('opencomune_planner_enabled', isset($_POST['planner_enabled']) ? '1' : '0');
    $max_days = isset($_POST['planner_max_days']) ? (int) $_POST['planner_max_days'] : 2;
    update_option('opencomune_planner_max_days', max(1, min(7, $max_days)));
    update_option('opencomune_planner_notify_email', sanitize_email(wp_unslash($_POST['planner_notify_email'] ?? '')));

    return __('Impostazioni salvate.', 'opencomune');
}

/**
 * @return array{enabled: bool, max_days: int, notify_email: string, with_geo: int, published: int, requests: list<array<string, mixed>>, wizard_url: string}
 */
function opencomune_planner_get_admin_view_data() {
    $enabled = opencomune_planner_is_enabled();
    $max_days = opencomune_planner_max_days();
    $notify = get_option('opencomune_planner_notify_email', '');
    if ($notify === '' || !is_email($notify)) {
        $notify = get_option('opencomune_email_ufficio', '');
    }
    if ($notify === '' || !is_email($notify)) {
        $notify = get_option('admin_email');
    }

    $with_geo = 0;
    $published = 0;
    $q = new WP_Query([
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
    ]);
    foreach ($q->posts as $post_id) {
        $published++;
        $lat = (float) get_post_meta($post_id, 'tour_latitude', true);
        $lng = (float) get_post_meta($post_id, 'tour_longitude', true);
        if ($lat !== 0.0 || $lng !== 0.0) {
            $with_geo++;
        }
    }
    wp_reset_postdata();

    global $wpdb;
    $table = opencomune_planner_table_name();
    $requests = [];
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        $requests = $wpdb->get_results(
            "SELECT token, start_date, end_date, start_address, contact_name, contact_email, created_at
             FROM {$table}
             ORDER BY created_at DESC
             LIMIT 50",
            ARRAY_A
        );
    }

    return [
        'enabled' => $enabled,
        'max_days' => $max_days,
        'notify_email' => (string) $notify,
        'with_geo' => $with_geo,
        'published' => $published,
        'requests' => is_array($requests) ? $requests : [],
        'wizard_url' => home_url('/pianifica-itinerario/'),
    ];
}

/**
 * Reindirizza la vecchia pagina wp-admin alla dashboard.
 */
function opencomune_planner_redirect_legacy_wp_admin_page() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    if ($page !== 'opencomune-planner') {
        return;
    }
    wp_safe_redirect(opencomune_get_planner_dashboard_url());
    exit;
}
add_action('admin_init', 'opencomune_planner_redirect_legacy_wp_admin_page');
