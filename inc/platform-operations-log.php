<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_operazioni_log_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'tour_operazioni_log';
}

function opencomune_ensure_operazioni_log_table() {
    global $wpdb;

    $table = opencomune_operazioni_log_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        opencomune_maybe_migrate_booking_logs_to_operazioni_log();

        return;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        action varchar(48) NOT NULL,
        message text NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        partner_id bigint(20) unsigned DEFAULT NULL,
        booking_id bigint(20) unsigned DEFAULT NULL,
        tour_id bigint(20) unsigned DEFAULT NULL,
        order_id varchar(64) DEFAULT NULL,
        amount decimal(10,2) DEFAULT NULL,
        status_from varchar(32) DEFAULT NULL,
        status_to varchar(32) DEFAULT NULL,
        guest_name varchar(191) DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY action (action),
        KEY created_at (created_at),
        KEY partner_id (partner_id),
        KEY booking_id (booking_id),
        KEY tour_id (tour_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    opencomune_maybe_migrate_booking_logs_to_operazioni_log();
}

function opencomune_maybe_migrate_booking_logs_to_operazioni_log() {
    if (get_option('opencomune_operazioni_log_migrated_v1')) {
        return;
    }

    if (!function_exists('opencomune_ensure_booking_log_table')) {
        return;
    }

    global $wpdb;
    opencomune_ensure_booking_log_table();
    $log_table = $wpdb->prefix . 'tour_prenotazioni_log';
    $bookings_table = $wpdb->prefix . 'tour_prenotazioni';
    $ops_table = opencomune_operazioni_log_table_name();

    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $log_table)) !== $log_table) {
        update_option('opencomune_operazioni_log_migrated_v1', 1, false);

        return;
    }

    $rows = $wpdb->get_results(
        "SELECT l.*, p.tour_id, p.nome AS guest_name, t.post_author AS partner_id
         FROM {$log_table} l
         INNER JOIN {$bookings_table} p ON p.id = l.booking_id
         LEFT JOIN {$wpdb->posts} t ON t.ID = p.tour_id AND t.post_type = 'esperienze'
         ORDER BY l.id ASC"
    );

    foreach ($rows ?: [] as $row) {
        $wpdb->insert(
            $ops_table,
            [
                'action' => sanitize_key((string) $row->action),
                'message' => (string) $row->reason,
                'user_id' => !empty($row->user_id) ? (int) $row->user_id : null,
                'partner_id' => !empty($row->partner_id) ? (int) $row->partner_id : null,
                'booking_id' => (int) $row->booking_id,
                'tour_id' => !empty($row->tour_id) ? (int) $row->tour_id : null,
                'status_from' => (string) ($row->status_from ?? ''),
                'status_to' => (string) ($row->status_to ?? ''),
                'guest_name' => (string) ($row->guest_name ?? ''),
                'created_at' => $row->created_at ?: current_time('mysql'),
            ],
            ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
        );
    }

    update_option('opencomune_operazioni_log_migrated_v1', 1, false);
}

/**
 * @param array<string, mixed> $args
 */
function opencomune_log_platform_operation(array $args) {
    global $wpdb;

    opencomune_ensure_operazioni_log_table();

    $action = sanitize_key((string) ($args['action'] ?? ''));
    if ($action === '') {
        return false;
    }

    $message = sanitize_textarea_field((string) ($args['message'] ?? ''));
    if ($message === '') {
        return false;
    }

    $inserted = $wpdb->insert(
        opencomune_operazioni_log_table_name(),
        [
            'action' => $action,
            'message' => $message,
            'user_id' => isset($args['user_id']) ? (int) $args['user_id'] : get_current_user_id(),
            'partner_id' => isset($args['partner_id']) ? (int) $args['partner_id'] : 0,
            'booking_id' => isset($args['booking_id']) ? (int) $args['booking_id'] : 0,
            'tour_id' => isset($args['tour_id']) ? (int) $args['tour_id'] : 0,
            'order_id' => isset($args['order_id']) ? sanitize_text_field((string) $args['order_id']) : '',
            'amount' => isset($args['amount']) ? (float) $args['amount'] : null,
            'status_from' => isset($args['status_from']) ? sanitize_key((string) $args['status_from']) : '',
            'status_to' => isset($args['status_to']) ? sanitize_key((string) $args['status_to']) : '',
            'guest_name' => isset($args['guest_name']) ? sanitize_text_field((string) $args['guest_name']) : '',
        ],
        ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%f', '%s', '%s', '%s']
    );

    return $inserted !== false;
}

/**
 * @param object $order Row tour_ordini.
 */
function opencomune_log_payment_received_operation($order) {
    if (!$order) {
        return;
    }

    $tour_id = (int) ($order->tour_id ?? 0);
    $booking_id = (int) ($order->booking_id ?? 0);
    $amount = isset($order->totale) ? (float) $order->totale : 0.0;
    $order_id = (string) ($order->order_id ?? '');
    $gateway = sanitize_key((string) ($order->gateway ?? ''));

    $partner_id = 0;
    $esperienza_title = '';
    if ($tour_id > 0) {
        $post = get_post($tour_id);
        if ($post) {
            $partner_id = (int) $post->post_author;
            $esperienza_title = get_the_title($post);
        }
    }

    $guest_name = (string) ($order->nome ?? '');
    if ($guest_name === '' && $booking_id > 0 && function_exists('opencomune_get_booking_by_id')) {
        $booking = opencomune_get_booking_by_id($booking_id);
        if ($booking) {
            $guest_name = (string) $booking->nome;
        }
    }

    $message = sprintf(
        /* translators: 1: amount, 2: gateway, 3: order id, 4: experience title */
        __('Pagamento ricevuto: %1$s via %2$s (ordine %3$s)%4$s', 'opencomune'),
        number_format_i18n($amount, 2) . ' EUR',
        $gateway !== '' ? strtoupper($gateway) : '—',
        $order_id !== '' ? $order_id : '—',
        $esperienza_title !== '' ? ' — ' . $esperienza_title : ''
    );

    opencomune_log_platform_operation([
        'action' => 'payment_received',
        'message' => $message,
        'user_id' => 0,
        'partner_id' => $partner_id,
        'booking_id' => $booking_id,
        'tour_id' => $tour_id,
        'order_id' => $order_id,
        'amount' => $amount,
        'guest_name' => $guest_name,
    ]);
}

function opencomune_log_booking_operation_from_log_entry($booking_id, $action, $reason, $status_from, $status_to) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return;
    }

    $booking = function_exists('opencomune_get_booking_by_id') ? opencomune_get_booking_by_id($booking_id) : null;
    if (!$booking) {
        return;
    }

    $tour_id = (int) $booking->tour_id;
    $partner_id = 0;
    if ($tour_id > 0) {
        $post = get_post($tour_id);
        if ($post) {
            $partner_id = (int) $post->post_author;
        }
    }

    opencomune_log_platform_operation([
        'action' => sanitize_key((string) $action),
        'message' => sanitize_textarea_field((string) $reason),
        'user_id' => get_current_user_id(),
        'partner_id' => $partner_id,
        'booking_id' => $booking_id,
        'tour_id' => $tour_id,
        'guest_name' => (string) $booking->nome,
        'status_from' => sanitize_key((string) $status_from),
        'status_to' => sanitize_key((string) $status_to),
    ]);
}

function opencomune_log_dashboard_user_login($user_login, $user) {
    if (!$user instanceof WP_User || !$user->exists()) {
        return;
    }

    if (!function_exists('opencomune_user_can_access_dashboard')) {
        return;
    }

    $can_dashboard = user_can($user, 'manage_options') || user_can($user, 'partner');
    if (!$can_dashboard) {
        return;
    }

    $is_admin = user_can($user, 'manage_options');
    $action = $is_admin ? 'admin_login' : 'partner_login';
    $label = $is_admin ? __('Amministratore', 'opencomune') : __('Partner', 'opencomune');

    opencomune_log_platform_operation([
        'action' => $action,
        'message' => sprintf(
            /* translators: 1: role label, 2: username, 3: email */
            __('Accesso %1$s: %2$s (%3$s)', 'opencomune'),
            $label,
            $user->display_name ?: $user_login,
            $user->user_email
        ),
        'user_id' => (int) $user->ID,
        'partner_id' => $is_admin ? 0 : (int) $user->ID,
    ]);
}

add_action('wp_login', 'opencomune_log_dashboard_user_login', 20, 2);

/**
 * @return array<int, string>
 */
function opencomune_get_platform_operation_action_labels() {
    return [
        'cancelled' => __('Annullamento prenotazione', 'opencomune'),
        'payment_refund' => __('Rimborso pagamento', 'opencomune'),
        'payment_received' => __('Pagamento ricevuto', 'opencomune'),
        'partner_login' => __('Accesso partner', 'opencomune'),
        'admin_login' => __('Accesso amministratore', 'opencomune'),
    ];
}

/**
 * @return array<int, string>
 */
function opencomune_get_platform_operation_filter_actions() {
    return ['cancelled', 'payment_refund', 'payment_received', 'partner_login', 'admin_login'];
}

/**
 * @return array<int, object>
 */
function opencomune_query_platform_operations_log(array $args = []) {
    global $wpdb;

    opencomune_ensure_operazioni_log_table();

    $table = opencomune_operazioni_log_table_name();
    $days = max(0, (int) ($args['days'] ?? 90));
    $action = sanitize_key((string) ($args['action'] ?? ''));
    $partner_id = (int) ($args['partner_id'] ?? 0);
    $limit = min(500, max(1, (int) ($args['limit'] ?? 200)));

    $where = ['1=1'];
    $params = [];

    if ($days > 0) {
        $where[] = 'o.created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $params[] = $days;
    }

    if ($action !== '' && in_array($action, opencomune_get_platform_operation_filter_actions(), true)) {
        $where[] = 'o.action = %s';
        $params[] = $action;
    }

    if ($partner_id > 0) {
        $where[] = 'o.partner_id = %d';
        $params[] = $partner_id;
    }

    $sql = "
        SELECT o.*, t.post_title AS esperienza_title
        FROM {$table} o
        LEFT JOIN {$wpdb->posts} t ON t.ID = o.tour_id AND t.post_type = 'esperienze'
        WHERE " . implode(' AND ', $where) . "
        ORDER BY o.created_at DESC, o.id DESC
        LIMIT %d
    ";
    $params[] = $limit;

    return $wpdb->get_results($wpdb->prepare($sql, $params)) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function opencomune_format_platform_operations_log_rows(array $rows) {
    $action_labels = opencomune_get_platform_operation_action_labels();

    $out = [];
    foreach ($rows as $row) {
        $booking_id = (int) ($row->booking_id ?? 0);
        $partner_id = (int) ($row->partner_id ?? 0);
        $partner_label = '';
        if ($partner_id > 0) {
            $user = get_userdata($partner_id);
            $partner_label = $user && $user->exists() ? $user->display_name : ('#' . $partner_id);
        }

        $actor_label = '';
        if (!empty($row->user_id)) {
            $actor = get_userdata((int) $row->user_id);
            $actor_label = $actor && $actor->exists() ? $actor->display_name : ('#' . (int) $row->user_id);
        }

        $action = (string) $row->action;
        $esperienza_title = (string) ($row->esperienza_title ?? '');
        if ($esperienza_title === '' && !empty($row->tour_id)) {
            $esperienza_title = get_the_title((int) $row->tour_id) ?: '';
        }

        $out[] = [
            'id' => (int) $row->id,
            'booking_id' => $booking_id,
            'booking_detail_url' => $booking_id > 0 && function_exists('opencomune_get_partner_booking_detail_url')
                ? opencomune_get_partner_booking_detail_url($booking_id)
                : '',
            'action' => $action,
            'action_label' => $action_labels[$action] ?? $action,
            'reason' => (string) $row->message,
            'message' => (string) $row->message,
            'status_from_label' => opencomune_booking_status_label($row->status_from ?? ''),
            'status_to_label' => opencomune_booking_status_label($row->status_to ?? ''),
            'guest_name' => (string) ($row->guest_name ?? ''),
            'esperienza_title' => $esperienza_title,
            'partner_name' => $partner_label,
            'user' => $actor_label,
            'amount_formatted' => isset($row->amount) && $row->amount !== null
                ? number_format_i18n((float) $row->amount, 2) . ' EUR'
                : '',
            'created_at' => $row->created_at ? date_i18n('d/m/Y H:i', strtotime($row->created_at)) : '',
        ];
    }

    return $out;
}
