<?php
/**
 * Dashboard prenotazioni partner / admin.
 */

function opencomune_ensure_booking_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'tour_prenotazioni_log';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table) {
        return;
    }

    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL,
        action varchar(32) NOT NULL,
        status_from varchar(32) DEFAULT NULL,
        status_to varchar(32) DEFAULT NULL,
        reason text NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY created_at (created_at)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/**
 * @return array<int, array<string, string>>
 */
function opencomune_get_booking_activity_log($booking_id) {
    global $wpdb;
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return [];
    }

    opencomune_ensure_booking_log_table();
    $table = $wpdb->prefix . 'tour_prenotazioni_log';
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY created_at DESC, id DESC",
        $booking_id
    ));

    if (!$rows) {
        return [];
    }

    $out = [];
    foreach ($rows as $row) {
        $user_label = '';
        if (!empty($row->user_id)) {
            $user = get_userdata((int) $row->user_id);
            $user_label = $user && $user->exists() ? $user->display_name : ('#' . (int) $row->user_id);
        }

        $action = (string) $row->action;
        $action_labels = [
            'cancelled' => __('Annullamento', 'opencomune'),
            'payment_refund' => __('Rimborso pagamento', 'opencomune'),
            'request_submitted' => __('Richiesta inviata', 'opencomune'),
            'request_approved' => __('Richiesta approvata', 'opencomune'),
            'request_rejected' => __('Richiesta rifiutata', 'opencomune'),
        ];

        $out[] = [
            'action' => $action,
            'action_label' => $action_labels[$action] ?? $action,
            'status_from' => (string) ($row->status_from ?? ''),
            'status_to' => (string) ($row->status_to ?? ''),
            'status_from_label' => opencomune_booking_status_label($row->status_from ?? ''),
            'status_to_label' => opencomune_booking_status_label($row->status_to ?? ''),
            'reason' => (string) $row->reason,
            'user' => $user_label,
            'created_at' => $row->created_at ? date_i18n('d/m/Y H:i', strtotime($row->created_at)) : '',
        ];
    }

    return $out;
}

function opencomune_insert_booking_log_entry($booking_id, $action, $reason, $status_from, $status_to) {
    global $wpdb;
    opencomune_ensure_booking_log_table();
    $table = $wpdb->prefix . 'tour_prenotazioni_log';

    $inserted = $wpdb->insert(
        $table,
        [
            'booking_id' => (int) $booking_id,
            'action' => sanitize_key((string) $action),
            'status_from' => sanitize_key((string) $status_from),
            'status_to' => sanitize_key((string) $status_to),
            'reason' => sanitize_textarea_field((string) $reason),
            'user_id' => get_current_user_id(),
        ],
        ['%d', '%s', '%s', '%s', '%s', '%d']
    ) !== false;

    if ($inserted && function_exists('opencomune_log_booking_operation_from_log_entry')) {
        opencomune_log_booking_operation_from_log_entry($booking_id, $action, $reason, $status_from, $status_to);
    }

    return $inserted;
}

/**
 * @return array|WP_Error
 */
function opencomune_cancel_booking_with_reason($booking_id, $reason) {
    $booking_id = (int) $booking_id;
    $reason = trim(sanitize_textarea_field((string) $reason));

    if ($reason === '') {
        return new WP_Error('reason_required', __('La motivazione dell\'annullamento è obbligatoria.', 'opencomune'));
    }

    if (mb_strlen($reason) < 5) {
        return new WP_Error('reason_short', __('Descrivi la motivazione con almeno 5 caratteri.', 'opencomune'));
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return new WP_Error('not_found', __('Prenotazione non trovata', 'opencomune'));
    }

    $old_status = (string) $booking->status;
    if ($old_status === 'cancellata') {
        return opencomune_get_booking_detail_payload($booking_id);
    }

    $result = opencomune_update_booking_status($booking_id, 'cancellata');
    if (is_wp_error($result)) {
        return $result;
    }

    opencomune_insert_booking_log_entry($booking_id, 'cancelled', $reason, $old_status, 'cancellata');

    return opencomune_get_booking_detail_payload($booking_id);
}

function opencomune_booking_status_label($status) {
    $status = sanitize_key((string) $status);
    $map = [
        'confermata' => __('Confermata', 'opencomune'),
        'pending_payment' => __('In attesa pagamento', 'opencomune'),
        'pending_approval' => __('In attesa approvazione', 'opencomune'),
        'cancellata' => __('Cancellata', 'opencomune'),
    ];

    return $map[$status] ?? $status;
}

function opencomune_get_booking_by_id($booking_id) {
    global $wpdb;
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return null;
    }

    $table = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $booking_id));
}

function opencomune_get_partner_booking_detail_url($booking_id) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return opencomune_get_dashboard_url() . '#section-prenotazioni';
    }

    return add_query_arg('id', $booking_id, home_url('/prenotazione-partner/'));
}

function opencomune_get_dashboard_bookings_list_url() {
    return opencomune_get_dashboard_url() . '#section-prenotazioni';
}

/**
 * Partner vede solo le proprie esperienze; admin tutte (0 = nessun filtro autore).
 */
function opencomune_get_dashboard_bookings_author_scope() {
    if (opencomune_user_can_manage_all_esperienze()) {
        return 0;
    }

    return (int) get_current_user_id();
}

function opencomune_get_partner_bookings_seen_at() {
    $user_id = (int) get_current_user_id();
    if ($user_id <= 0) {
        return null;
    }

    $raw = get_user_meta($user_id, 'opencomune_partner_bookings_seen_at', true);
    if ($raw === '' || $raw === false) {
        return null;
    }

    return (string) $raw;
}

function opencomune_mark_partner_bookings_seen() {
    $user_id = (int) get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    update_user_meta($user_id, 'opencomune_partner_bookings_seen_at', current_time('mysql', true));
}

/**
 * Conteggi per badge sidebar Prenotazioni (stessi stati della tabella).
 *
 * @return array{pending_approval:int,pending_payment:int,confermata:int}
 */
function opencomune_get_dashboard_booking_sidebar_badge_counts() {
    global $wpdb;

    if (!opencomune_user_can_access_dashboard()) {
        return [
            'pending_approval' => 0,
            'pending_payment' => 0,
            'confermata' => 0,
        ];
    }

    $table = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return [
            'pending_approval' => 0,
            'pending_payment' => 0,
            'confermata' => 0,
        ];
    }

    $author_id = opencomune_get_dashboard_bookings_author_scope();
    $from = "FROM {$table} p INNER JOIN {$wpdb->posts} t ON p.tour_id = t.ID AND t.post_type = 'esperienze'";
    $author_clause = '';
    $author_params = [];
    if ($author_id > 0) {
        $author_clause = ' AND t.post_author = %d';
        $author_params[] = $author_id;
    }

    $run_count = static function ($status, $since_clause = '', $since_params = []) use ($wpdb, $from, $author_clause, $author_params) {
        $sql = "SELECT COUNT(*) {$from} WHERE p.status = %s{$author_clause}{$since_clause}";
        $params = array_merge([$status], $author_params, $since_params);

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    };

    $pending_approval = $run_count('pending_approval');

    $seen_at = opencomune_get_partner_bookings_seen_at();
    if ($seen_at !== null) {
        $since_clause = ' AND p.data_prenotazione > %s';
        $since_params = [$seen_at];
    } else {
        $since_clause = ' AND p.data_prenotazione >= DATE_SUB(NOW(), INTERVAL 14 DAY)';
        $since_params = [];
    }

    $pending_payment = $run_count('pending_payment', $since_clause, $since_params);
    $confermata = $run_count('confermata', $since_clause, $since_params);

    return [
        'pending_approval' => $pending_approval,
        'pending_payment' => $pending_payment,
        'confermata' => $confermata,
    ];
}

add_action('wp_ajax_opencomune_mark_partner_bookings_seen', 'opencomune_mark_partner_bookings_seen_callback');
function opencomune_mark_partner_bookings_seen_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    opencomune_mark_partner_bookings_seen();

    wp_send_json_success([
        'badges' => opencomune_get_dashboard_booking_sidebar_badge_counts(),
    ]);
}

add_action('wp_ajax_opencomune_get_booking_sidebar_badge_counts', 'opencomune_get_booking_sidebar_badge_counts_callback');
function opencomune_get_booking_sidebar_badge_counts_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    wp_send_json_success([
        'badges' => opencomune_get_dashboard_booking_sidebar_badge_counts(),
    ]);
}

function opencomune_ensure_partner_booking_detail_page() {
    $slug = 'prenotazione-partner';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Dettaglio prenotazione', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-prenotazione-partner.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_partner_booking_detail_page', 21);

function opencomune_get_booking_payment_order($booking_id) {
    global $wpdb;
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0 || !function_exists('opencomune_tour_ordini_table_name')) {
        return null;
    }

    opencomune_ensure_tour_ordini_table();
    $table = opencomune_tour_ordini_table_name();

    $order = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE booking_id = %d ORDER BY id DESC LIMIT 1",
        $booking_id
    ));
    if ($order) {
        return $order;
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || $booking->email === '') {
        return null;
    }

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE tour_id = %d AND email = %s
         ORDER BY id DESC LIMIT 1",
        (int) $booking->tour_id,
        $booking->email
    ));
}

/**
 * Stato prenotazione non modificabile (annullamento o ordine rimborsato/annullato).
 *
 * @param object|null $order Row tour_ordini.
 */
function opencomune_booking_status_change_is_locked($booking_id, $order = null) {
    $booking = opencomune_get_booking_by_id((int) $booking_id);
    if (!$booking) {
        return true;
    }

    if ($booking->status === 'cancellata') {
        return true;
    }

    if ($order === null) {
        $order = opencomune_get_booking_payment_order((int) $booking_id);
    }

    if (!$order) {
        return false;
    }

    $order_status = sanitize_key((string) ($order->status ?? ''));
    if (in_array($order_status, ['refunded', 'cancelled'], true)) {
        return true;
    }

    if (function_exists('opencomune_ensure_tour_ordini_refund_columns')) {
        opencomune_ensure_tour_ordini_refund_columns();
    }
    if (sanitize_key((string) ($order->refund_status ?? '')) === 'refunded') {
        return true;
    }

    return false;
}

function opencomune_booking_status_change_locked_message($booking_id, $order = null) {
    $booking = opencomune_get_booking_by_id((int) $booking_id);
    if ($booking && $booking->status === 'cancellata') {
        return __('Stato bloccato: prenotazione annullata.', 'opencomune');
    }

    if ($order === null) {
        $order = opencomune_get_booking_payment_order((int) $booking_id);
    }

    if ($order) {
        $order_status = sanitize_key((string) ($order->status ?? ''));
        if ($order_status === 'refunded' || sanitize_key((string) ($order->refund_status ?? '')) === 'refunded') {
            return __('Stato bloccato: pagamento rimborsato.', 'opencomune');
        }
        if ($order_status === 'cancelled') {
            return __('Stato bloccato: pagamento annullato.', 'opencomune');
        }
    }

    return __('Stato prenotazione non modificabile.', 'opencomune');
}

function opencomune_get_booking_detail_payload($booking_id) {
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || !opencomune_user_can_manage_booking($booking_id)) {
        return null;
    }

    $tour_id = (int) $booking->tour_id;
    $experience = get_post($tour_id);
    $slot_data = $booking->booking_date;
    $slot_ora = $booking->booking_time;

    if ($slot_data === '' || $slot_ora === '') {
        global $wpdb;
        $cal = $wpdb->prefix . 'tour_calendari';
        $event = $wpdb->get_row($wpdb->prepare(
            "SELECT data, ora FROM {$cal} WHERE id = %d AND tour_id = %d",
            (int) $booking->event_id,
            $tour_id
        ));
        if ($event) {
            $slot_data = $event->data;
            $slot_ora = $event->ora;
        }
    }

    $order = opencomune_get_booking_payment_order($booking_id);
    $order_payload = function_exists('opencomune_format_tour_order_for_partner_detail')
        ? opencomune_format_tour_order_for_partner_detail($order, $tour_id, (string) $booking->status)
        : null;

    if (
        $order_payload
        && function_exists('opencomune_booking_commission_is_frozen')
        && opencomune_booking_commission_is_frozen($booking)
        && empty($order_payload['commission_snapshot_stored'])
    ) {
        $order_payload['commission_snapshot_stored'] = true;
        $order_payload['commission_percent'] = (float) $booking->commission_percent;
        $order_payload['commission_amount'] = (float) $booking->commission_amount;
        $order_payload['net_amount'] = (float) $booking->net_amount;
        $order_payload['commission_amount_formatted'] = number_format_i18n((float) $booking->commission_amount, 2) . ' EUR';
        $order_payload['net_amount_formatted'] = number_format_i18n((float) $booking->net_amount, 2) . ' EUR';
    }

    $is_admin = opencomune_user_can_manage_all_esperienze();
    $is_cancelled = $booking->status === 'cancellata';
    $status_change_locked = opencomune_booking_status_change_is_locked($booking_id, $order);

    return [
        'id' => (int) $booking->id,
        'tour_id' => $tour_id,
        'event_id' => (int) $booking->event_id,
        'esperienza_title' => $experience ? $experience->post_title : __('(Esperienza rimossa)', 'opencomune'),
        'esperienza_url' => $experience ? get_permalink($experience) : '',
        'edit_esperienza_url' => $experience ? opencomune_get_esperienza_wizard_url($tour_id) : '',
        'nome' => (string) $booking->nome,
        'email' => (string) $booking->email,
        'telefono' => (string) $booking->telefono,
        'participants' => (int) $booking->participants,
        'note' => (string) $booking->note,
        'status' => (string) $booking->status,
        'status_label' => opencomune_booking_status_label($booking->status),
        'metodo_pagamento' => (string) ($booking->metodo_pagamento ?? ''),
        'slot' => opencomune_format_booking_slot_display($slot_data, $slot_ora),
        'booking_date' => (string) $slot_data,
        'booking_time' => (string) $slot_ora,
        'data_prenotazione' => $booking->data_prenotazione
            ? date_i18n('d/m/Y H:i', strtotime($booking->data_prenotazione))
            : '',
        'order' => $order_payload,
        'can_edit' => !$is_cancelled || $is_admin,
        'can_change_status' => $is_admin && !$status_change_locked,
        'status_change_locked' => $status_change_locked,
        'status_change_locked_message' => $status_change_locked
            ? opencomune_booking_status_change_locked_message($booking_id, $order)
            : '',
        'list_url' => opencomune_get_dashboard_bookings_list_url(),
        'activity_log' => opencomune_get_booking_activity_log($booking_id),
        'can_cancel' => in_array($booking->status, ['confermata', 'pending_payment'], true)
            && opencomune_user_can_manage_booking($booking_id),
        'can_approve_request' => $booking->status === 'pending_approval'
            && opencomune_user_can_manage_booking($booking_id),
        'can_reject_request' => $booking->status === 'pending_approval'
            && opencomune_user_can_manage_booking($booking_id),
        'can_refund' => !empty($order_payload['can_refund'])
            && opencomune_user_can_manage_booking($booking_id),
    ];
}

function opencomune_user_can_manage_booking($booking_id) {
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return false;
    }

    return opencomune_user_can_edit_esperienza((int) $booking->tour_id);
}

function opencomune_format_booking_slot_display($date_raw, $time_raw) {
    $date_raw = trim((string) $date_raw);
    $time_raw = trim((string) $time_raw);

    if ($date_raw === '') {
        return '—';
    }

    $ts = strtotime($date_raw);
    $date_part = $ts ? date_i18n('d/m/Y', $ts) : $date_raw;

    if ($time_raw === '') {
        return $date_part;
    }

    if (preg_match('/^(\d{1,2}:\d{2})/', $time_raw, $m)) {
        $time_raw = $m[1];
    }

    return $date_part . ' ' . $time_raw;
}

/**
 * @return array{where:string,params:array,from:string}
 */
function opencomune_build_dashboard_bookings_query_parts(array $args) {
    global $wpdb;

    $table_prenotazioni = $wpdb->prefix . 'tour_prenotazioni';
    $table_calendari = $wpdb->prefix . 'tour_calendari';

    $days = max(0, (int) ($args['days'] ?? 90));
    $status = sanitize_key((string) ($args['status'] ?? ''));
    $author_id = (int) ($args['author_id'] ?? 0);
    $guest_name = trim(sanitize_text_field((string) ($args['guest_name'] ?? '')));

    $where = ['1=1'];
    $params = [];

    if ($days > 0) {
        $where[] = 'p.data_prenotazione >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $params[] = $days;
    }

    if ($status !== '' && in_array($status, ['confermata', 'pending_payment', 'pending_approval', 'cancellata'], true)) {
        $where[] = 'p.status = %s';
        $params[] = $status;
    }

    if ($author_id > 0) {
        $where[] = 't.post_author = %d';
        $params[] = $author_id;
    }

    if ($guest_name !== '') {
        $like = '%' . $wpdb->esc_like($guest_name) . '%';
        $where[] = 'p.nome LIKE %s';
        $params[] = $like;
    }

    $search = trim(sanitize_text_field((string) ($args['search'] ?? '')));
    if ($search !== '') {
        $like = '%' . $wpdb->esc_like($search) . '%';
        $where[] = '(p.nome LIKE %s OR p.email LIKE %s OR t.post_title LIKE %s OR CAST(p.id AS CHAR) LIKE %s)';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $from = "
        FROM {$table_prenotazioni} p
        LEFT JOIN {$wpdb->posts} t ON p.tour_id = t.ID
        LEFT JOIN {$table_calendari} c ON p.event_id = c.id
    ";

    return [
        'where' => implode(' AND ', $where),
        'params' => $params,
        'from' => $from,
        'table_prenotazioni' => $table_prenotazioni,
    ];
}

/**
 * @return array{items:array<int,object>,pagination:array<string,int>}
 */
function opencomune_query_dashboard_bookings(array $args = []) {
    global $wpdb;

    $defaults = [
        'days' => 90,
        'status' => '',
        'author_id' => 0,
        'guest_name' => '',
        'page' => 1,
        'per_page' => 15,
    ];
    $args = wp_parse_args($args, $defaults);

    $parts = opencomune_build_dashboard_bookings_query_parts($args);
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $parts['table_prenotazioni'])) !== $parts['table_prenotazioni']) {
        return [
            'items' => [],
            'pagination' => [
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'total_pages' => 0,
            ],
        ];
    }

    $per_page = min(100, max(1, (int) $args['per_page']));
    $page = max(1, (int) $args['page']);
    $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : ($page - 1) * $per_page;

    $count_sql = "SELECT COUNT(*) {$parts['from']} WHERE {$parts['where']}";
    $total = $parts['params']
        ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $parts['params']))
        : (int) $wpdb->get_var($count_sql);

    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;
    if (!isset($args['offset']) && $total_pages > 0 && $page > $total_pages) {
        $page = $total_pages;
        $offset = ($page - 1) * $per_page;
    }

    $order_sql = opencomune_dashboard_bookings_order_sql(
        (string) ($args['order_by'] ?? 'data_prenotazione'),
        (string) ($args['order_dir'] ?? 'DESC')
    );

    $select_sql = "
        SELECT p.*, t.post_title AS esperienza_title, t.post_author AS esperienza_author,
            COALESCE(c.data, p.booking_date) AS slot_data,
            COALESCE(c.ora, p.booking_time) AS slot_ora
        {$parts['from']}
        WHERE {$parts['where']}
        ORDER BY {$order_sql}
        LIMIT %d OFFSET %d
    ";

    $select_params = array_merge($parts['params'], [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($select_sql, $select_params)) ?: [];

    return [
        'items' => $rows,
        'pagination' => [
            'total' => $total,
            'per_page' => $per_page,
            'current_page' => $page,
            'total_pages' => $total_pages,
        ],
    ];
}

/**
 * @return int
 */
function opencomune_count_dashboard_bookings(array $args = []) {
    global $wpdb;

    $parts = opencomune_build_dashboard_bookings_query_parts($args);
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $parts['table_prenotazioni'])) !== $parts['table_prenotazioni']) {
        return 0;
    }

    $count_sql = "SELECT COUNT(*) {$parts['from']} WHERE {$parts['where']}";
    if ($parts['params']) {
        return (int) $wpdb->get_var($wpdb->prepare($count_sql, $parts['params']));
    }

    return (int) $wpdb->get_var($count_sql);
}

function opencomune_dashboard_bookings_order_sql($order_by, $order_dir) {
    $order_dir = strtoupper($order_dir) === 'ASC' ? 'ASC' : 'DESC';
    $allowed = [
        'id' => 'p.id',
        'esperienza_title' => 't.post_title',
        'partner_id' => 't.post_author',
        'nome' => 'p.nome',
        'participants' => 'p.participants',
        'status' => 'p.status',
        'data_prenotazione' => 'p.data_prenotazione',
    ];

    if ($order_by === 'slot') {
        return "COALESCE(c.data, p.booking_date) {$order_dir}, COALESCE(c.ora, p.booking_time) {$order_dir}";
    }

    $column = $allowed[$order_by] ?? 'p.data_prenotazione';

    return "{$column} {$order_dir}";
}

/**
 * @return array{order_by:string,order_dir:string}
 */
function opencomune_bookings_dashboard_order_from_datatables($column_index, $order_dir, $show_partner) {
    $columns = $show_partner
        ? ['id', 'esperienza_title', 'partner_id', 'slot', 'nome', 'participants', 'status', 'data_prenotazione']
        : ['id', 'esperienza_title', 'slot', 'nome', 'participants', 'status', 'data_prenotazione'];

    $index = max(0, (int) $column_index);
    $key = $columns[$index] ?? 'data_prenotazione';
    $dir = strtolower((string) $order_dir) === 'asc' ? 'ASC' : 'DESC';

    return [
        'order_by' => $key,
        'order_dir' => $dir,
    ];
}

function opencomune_bookings_dashboard_datatables_response() {
    $draw = (int) ($_POST['draw'] ?? 0);
    $start = max(0, (int) ($_POST['start'] ?? 0));
    $length = max(1, min(100, (int) ($_POST['length'] ?? 15)));

    $search_post = isset($_POST['search']) && is_array($_POST['search']) ? wp_unslash($_POST['search']) : [];
    $search_value = sanitize_text_field((string) ($search_post['value'] ?? ''));

    $days = isset($_POST['days']) ? (int) $_POST['days'] : 90;
    $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    $guest_name = isset($_POST['guest_name']) ? sanitize_text_field(wp_unslash($_POST['guest_name'])) : '';

    $author_id = 0;
    if (opencomune_user_is_partner_only()) {
        $author_id = get_current_user_id();
    }

    $show_partner = opencomune_user_can_manage_all_esperienze();
    $order_post = isset($_POST['order'][0]) && is_array($_POST['order'][0]) ? wp_unslash($_POST['order'][0]) : [];
    $order_column = (int) ($order_post['column'] ?? 0);
    $order_dir = sanitize_text_field((string) ($order_post['dir'] ?? 'desc'));
    $order = opencomune_bookings_dashboard_order_from_datatables($order_column, $order_dir, $show_partner);

    $base_args = [
        'days' => $days,
        'status' => $status,
        'author_id' => $author_id,
    ];

    $records_total = opencomune_count_dashboard_bookings($base_args);

    $filtered_args = array_merge($base_args, [
        'guest_name' => $guest_name,
        'search' => $search_value,
    ]);
    $records_filtered = opencomune_count_dashboard_bookings($filtered_args);

    $result = opencomune_query_dashboard_bookings(array_merge($filtered_args, [
        'per_page' => $length,
        'offset' => $start,
        'order_by' => $order['order_by'],
        'order_dir' => $order['order_dir'],
    ]));

    $rows = [];
    foreach ($result['items'] as $row) {
        $rows[] = opencomune_format_booking_for_dashboard($row);
    }

    wp_send_json([
        'draw' => $draw,
        'recordsTotal' => $records_total,
        'recordsFiltered' => $records_filtered,
        'data' => $rows,
    ]);
}

function opencomune_format_booking_for_dashboard($row) {
    $booking_id = (int) $row->id;
    $can_cancel = in_array($row->status, ['confermata', 'pending_payment'], true)
        && opencomune_user_can_manage_booking($booking_id);

    $can_refund = false;
    $is_refunded = false;
    $refund_amount_formatted = '';
    if ($row->status === 'cancellata' && function_exists('opencomune_tour_order_refund_eligibility')) {
        $order = opencomune_get_booking_payment_order($booking_id);
        $eligibility = opencomune_tour_order_refund_eligibility($order, 'cancellata');
        $can_refund = !empty($eligibility['can_refund']) && opencomune_user_can_manage_booking($booking_id);
        $is_refunded = ($eligibility['refund_status'] ?? '') === 'refunded';
        $refund_amount_formatted = (string) ($eligibility['refund_amount_formatted'] ?? '');
        if ($refund_amount_formatted === '' && $order && isset($order->totale)) {
            $refund_amount_formatted = number_format_i18n((float) $order->totale, 2) . ' EUR';
        }
    }

    $partner_id = (int) ($row->esperienza_author ?? 0);
    $partner_name = '';
    if ($partner_id > 0) {
        $partner_user = get_userdata($partner_id);
        $partner_name = $partner_user && $partner_user->exists()
            ? $partner_user->display_name
            : ('#' . $partner_id);
    }

    return [
        'id' => $booking_id,
        'tour_id' => (int) $row->tour_id,
        'partner_id' => $partner_id,
        'partner_name' => $partner_name,
        'esperienza_title' => $row->esperienza_title ?: __('(Esperienza rimossa)', 'opencomune'),
        'nome' => $row->nome,
        'email' => $row->email,
        'telefono' => $row->telefono,
        'participants' => (int) $row->participants,
        'slot' => opencomune_format_booking_slot_display($row->slot_data, $row->slot_ora),
        'status' => $row->status,
        'status_label' => opencomune_booking_status_label($row->status),
        'metodo_pagamento' => isset($row->metodo_pagamento) ? (string) $row->metodo_pagamento : '',
        'notes' => $row->note,
        'data_prenotazione' => $row->data_prenotazione
            ? date_i18n('d/m/Y H:i', strtotime($row->data_prenotazione))
            : '',
        'can_cancel' => $can_cancel,
        'can_approve_request' => $row->status === 'pending_approval'
            && opencomune_user_can_manage_booking($booking_id),
        'can_reject_request' => $row->status === 'pending_approval'
            && opencomune_user_can_manage_booking($booking_id),
        'can_refund' => $can_refund,
        'is_refunded' => $is_refunded,
        'refund_amount_formatted' => $refund_amount_formatted,
        'detail_url' => opencomune_get_partner_booking_detail_url($booking_id),
    ];
}

/**
 * @param array<string, mixed> $fields
 */
function opencomune_save_booking_details($booking_id, array $fields) {
    global $wpdb;

    $booking_id = (int) $booking_id;
    if ($booking_id <= 0 || !opencomune_user_can_manage_booking($booking_id)) {
        return new WP_Error('forbidden', __('Accesso negato', 'opencomune'));
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return new WP_Error('not_found', __('Prenotazione non trovata', 'opencomune'));
    }

    $is_admin = opencomune_user_can_manage_all_esperienze();
    if ($booking->status === 'cancellata' && !$is_admin) {
        return new WP_Error('read_only', __('Prenotazione annullata: non modificabile.', 'opencomune'));
    }

    $nome = isset($fields['nome']) ? sanitize_text_field($fields['nome']) : $booking->nome;
    $email = isset($fields['email']) ? sanitize_email($fields['email']) : $booking->email;
    $telefono = isset($fields['telefono']) ? sanitize_text_field($fields['telefono']) : $booking->telefono;
    $note = isset($fields['note']) ? sanitize_textarea_field($fields['note']) : $booking->note;
    $participants = isset($fields['participants']) ? max(1, (int) $fields['participants']) : (int) $booking->participants;
    $new_status = isset($fields['status']) ? sanitize_key((string) $fields['status']) : $booking->status;

    if ($nome === '' || $email === '') {
        return new WP_Error('invalid_data', __('Nome e email sono obbligatori.', 'opencomune'));
    }

    if (!$is_admin) {
        if (isset($fields['status']) && sanitize_key((string) $fields['status']) === 'cancellata') {
            $new_status = 'cancellata';
        } else {
            $new_status = $booking->status;
        }
    } elseif (!in_array($new_status, ['confermata', 'pending_payment', 'pending_approval', 'cancellata'], true)) {
        return new WP_Error('invalid_status', __('Stato non valido', 'opencomune'));
    }

    if (
        $is_admin
        && $new_status !== $booking->status
        && opencomune_booking_status_change_is_locked($booking_id)
    ) {
        return new WP_Error(
            'status_locked',
            opencomune_booking_status_change_locked_message($booking_id)
        );
    }

    $old_status = $booking->status;

    if ($new_status === 'cancellata' && $old_status !== 'cancellata') {
        $reason = isset($fields['cancellation_reason']) ? trim((string) $fields['cancellation_reason']) : '';
        if ($reason === '') {
            return new WP_Error('reason_required', __('La motivazione dell\'annullamento è obbligatoria.', 'opencomune'));
        }

        return opencomune_cancel_booking_with_reason($booking_id, $reason);
    }
    $old_participants = (int) $booking->participants;

    if ($new_status !== 'cancellata') {
        $availability = opencomune_assert_booking_participants_available($booking, $participants, $booking_id);
        if (is_wp_error($availability)) {
            return $availability;
        }
    }

    if ($old_status === 'cancellata' && in_array($new_status, ['confermata', 'pending_payment'], true)) {
        $reactivate = clone $booking;
        $reactivate->status = $new_status;
        $reactivate->participants = $participants;
        $check = opencomune_assert_booking_participants_available($reactivate, $participants, $booking_id);
        if (is_wp_error($check)) {
            return $check;
        }
    }

    if (
        $old_status === 'confermata'
        && $new_status === 'confermata'
        && $participants !== $old_participants
        && opencomune_tour_uses_legacy_calendar_slots((int) $booking->tour_id)
    ) {
        $delta = $participants - $old_participants;
        if ($delta !== 0) {
            if ($delta > 0) {
                $slot = opencomune_get_legacy_slot_context_for_booking($booking);
                $probe = opencomune_assert_booking_participants_available($booking, $participants, $booking_id);
                if (is_wp_error($probe)) {
                    return $probe;
                }
                opencomune_adjust_legacy_calendar_spots(
                    (int) $booking->tour_id,
                    (int) $booking->event_id,
                    -$delta,
                    $slot ?: []
                );
            } else {
                $slot = opencomune_get_legacy_slot_context_for_booking($booking);
                opencomune_adjust_legacy_calendar_spots(
                    (int) $booking->tour_id,
                    (int) $booking->event_id,
                    abs($delta),
                    $slot ?: []
                );
            }
        }
    }

    $table = $wpdb->prefix . 'tour_prenotazioni';
    $updated = $wpdb->update(
        $table,
        [
            'nome' => $nome,
            'email' => $email,
            'telefono' => $telefono,
            'note' => $note,
            'participants' => $participants,
            'status' => $new_status,
        ],
        ['id' => $booking_id],
        ['%s', '%s', '%s', '%s', '%d', '%s'],
        ['%d']
    );

    if ($updated === false) {
        return new WP_Error('db_error', __('Errore durante il salvataggio', 'opencomune'));
    }

    if ($old_status === 'cancellata' && $new_status === 'confermata') {
        $saved = opencomune_get_booking_by_id($booking_id);
        if ($saved) {
            $reserve = opencomune_reserve_booking_capacity($saved);
            if (is_wp_error($reserve)) {
                return $reserve;
            }
        }
    }

    return opencomune_get_booking_detail_payload($booking_id);
}

function opencomune_update_booking_status($booking_id, $new_status) {
    global $wpdb;

    $booking_id = (int) $booking_id;
    $new_status = sanitize_key((string) $new_status);
    $allowed = ['confermata', 'pending_payment', 'pending_approval', 'cancellata'];
    if (!in_array($new_status, $allowed, true)) {
        return new WP_Error('invalid_status', __('Stato non valido', 'opencomune'));
    }

    if (!opencomune_user_can_manage_booking($booking_id)) {
        return new WP_Error('forbidden', __('Accesso negato', 'opencomune'));
    }

    if (opencomune_user_is_partner_only() && $new_status !== 'cancellata') {
        return new WP_Error('forbidden', __('Puoi solo annullare la prenotazione', 'opencomune'));
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return new WP_Error('not_found', __('Prenotazione non trovata', 'opencomune'));
    }

    $old_status = $booking->status;

    if ($new_status === 'cancellata' && $old_status === 'cancellata') {
        return true;
    }

    if (opencomune_user_is_partner_only() && !in_array($old_status, ['confermata', 'pending_payment', 'pending_approval'], true)) {
        return new WP_Error('invalid_transition', __('Questa prenotazione non può essere annullata', 'opencomune'));
    }

    if ($new_status === 'cancellata' && $old_status !== 'cancellata') {
        opencomune_release_booking_capacity($booking);
    } elseif ($old_status === 'cancellata' && in_array($new_status, ['confermata', 'pending_payment'], true)) {
        $reactivate = clone $booking;
        $reactivate->status = $new_status;
        $check = opencomune_assert_booking_participants_available($reactivate, (int) $booking->participants, $booking_id);
        if (is_wp_error($check)) {
            return $check;
        }
    }

    $table = $wpdb->prefix . 'tour_prenotazioni';
    $updated = $wpdb->update(
        $table,
        ['status' => $new_status],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );

    if ($updated === false) {
        return new WP_Error('db_error', __('Errore durante l\'aggiornamento', 'opencomune'));
    }

    if ($old_status === 'cancellata' && $new_status === 'confermata') {
        $saved = opencomune_get_booking_by_id($booking_id);
        if ($saved) {
            $reserve = opencomune_reserve_booking_capacity($saved);
            if (is_wp_error($reserve)) {
                return $reserve;
            }
        }
    }

    return true;
}

add_action('wp_ajax_opencomune_get_booking_detail', 'opencomune_get_booking_detail_callback');
function opencomune_get_booking_detail_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $payload = opencomune_get_booking_detail_payload($id);
    if (!$payload) {
        wp_send_json_error(['message' => __('Prenotazione non trovata', 'opencomune')]);
    }

    wp_send_json_success($payload);
}

add_action('wp_ajax_opencomune_save_booking_detail', 'opencomune_save_booking_detail_callback');
function opencomune_save_booking_detail_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $fields = [
        'nome' => isset($_POST['nome']) ? wp_unslash($_POST['nome']) : '',
        'email' => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
        'telefono' => isset($_POST['telefono']) ? wp_unslash($_POST['telefono']) : '',
        'note' => isset($_POST['note']) ? wp_unslash($_POST['note']) : '',
        'participants' => isset($_POST['participants']) ? (int) $_POST['participants'] : 1,
        'status' => isset($_POST['status']) ? wp_unslash($_POST['status']) : '',
    ];

    $result = opencomune_save_booking_details($id, $fields);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message' => __('Prenotazione aggiornata', 'opencomune'),
        'booking' => $result,
    ]);
}

add_action('wp_ajax_opencomune_get_prenotazioni', 'opencomune_get_prenotazioni_callback');
function opencomune_get_prenotazioni_callback() {
    if (isset($_POST['draw'])) {
        $draw = (int) ($_POST['draw'] ?? 0);
        if (!opencomune_user_can_access_dashboard()) {
            wp_send_json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
            ]);
        }

        $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'opencomune_dashboard_bookings')) {
            wp_send_json([
                'draw' => $draw,
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => __('Sessione scaduta. Ricarica la pagina.', 'opencomune'),
            ]);
        }

        opencomune_bookings_dashboard_datatables_response();
        return;
    }

    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $days = isset($_POST['days']) ? (int) $_POST['days'] : 90;
    $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    $author_id = 0;

    if (opencomune_user_is_partner_only()) {
        $author_id = get_current_user_id();
    }

    $page = isset($_POST['page']) ? (int) $_POST['page'] : 1;
    $guest_name = isset($_POST['guest_name']) ? sanitize_text_field(wp_unslash($_POST['guest_name'])) : '';

    $result = opencomune_query_dashboard_bookings([
        'days' => $days,
        'status' => $status,
        'author_id' => $author_id,
        'guest_name' => $guest_name,
        'page' => $page,
        'per_page' => 15,
    ]);

    $formatted = [];
    foreach ($result['items'] as $row) {
        $formatted[] = opencomune_format_booking_for_dashboard($row);
    }

    wp_send_json_success([
        'bookings' => $formatted,
        'pagination' => $result['pagination'],
    ]);
}

add_action('wp_ajax_opencomune_refund_booking_payment', 'opencomune_refund_booking_payment_callback');
function opencomune_refund_booking_payment_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => __('Prenotazione non valida', 'opencomune')]);
    }

    $result = function_exists('opencomune_process_booking_payment_refund')
        ? opencomune_process_booking_payment_refund($id)
        : new WP_Error('missing', __('Rimborso non disponibile.', 'opencomune'));

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success($result);
}

add_action('wp_ajax_opencomune_cancel_booking', 'opencomune_cancel_booking_callback');
function opencomune_cancel_booking_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $reason = isset($_POST['cancellation_reason']) ? wp_unslash($_POST['cancellation_reason']) : '';

    if ($id <= 0) {
        wp_send_json_error(['message' => __('ID prenotazione non valido', 'opencomune')]);
    }

    $result = opencomune_cancel_booking_with_reason($id, $reason);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message' => __('Prenotazione annullata', 'opencomune'),
        'booking' => $result,
    ]);
}

add_action('wp_ajax_opencomune_update_booking_status', 'opencomune_update_booking_status_callback');
function opencomune_update_booking_status_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $status = isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : '';
    $reason = isset($_POST['cancellation_reason']) ? wp_unslash($_POST['cancellation_reason']) : '';

    if ($id <= 0) {
        wp_send_json_error(['message' => __('ID prenotazione non valido', 'opencomune')]);
    }

    if ($status === 'cancellata') {
        $result = opencomune_cancel_booking_with_reason($id, $reason);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Prenotazione annullata', 'opencomune'),
            'status' => 'cancellata',
            'status_label' => opencomune_booking_status_label('cancellata'),
            'booking' => $result,
        ]);
    }

    $result = opencomune_update_booking_status($id, $status);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'message' => __('Prenotazione aggiornata', 'opencomune'),
        'status' => $status,
        'status_label' => opencomune_booking_status_label($status),
    ]);
}

add_action('wp_ajax_opencomune_toggle_prenotazione_status', 'opencomune_toggle_prenotazione_status_callback');
function opencomune_toggle_prenotazione_status_callback() {
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

    if ($id <= 0) {
        wp_send_json_error(['message' => __('ID prenotazione non valido', 'opencomune')]);
    }

    $map = [
        'confirmed' => 'confermata',
        'pending' => 'pending_payment',
        'cancelled' => 'cancellata',
    ];

    if (!isset($map[$status])) {
        wp_send_json_error(['message' => __('Stato non valido', 'opencomune')]);
    }

    if (!opencomune_user_can_manage_all_esperienze()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    $result = opencomune_update_booking_status($id, $map[$status]);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    $label = opencomune_booking_status_label($map[$status]);
    wp_send_json_success(['message' => sprintf(__('Prenotazione: %s', 'opencomune'), $label)]);
}

add_action('wp_ajax_opencomune_delete_prenotazione', 'opencomune_delete_prenotazione_callback');
function opencomune_delete_prenotazione_callback() {
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        wp_send_json_error(['message' => __('ID prenotazione non valido', 'opencomune')]);
    }

    if (!opencomune_user_can_manage_all_esperienze()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    wp_send_json_error(['message' => __('Usa l\'annullamento con motivazione dalla dashboard prenotazioni.', 'opencomune')]);
}
