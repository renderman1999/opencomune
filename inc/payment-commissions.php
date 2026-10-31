<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_commission_settings_url() {
    return home_url('/commissioni/');
}

function opencomune_get_payment_commission_percent() {
    $value = get_option('opencomune_payment_commission_percent', '0');

    return max(0, min(100, (float) $value));
}

/**
 * @return true|WP_Error
 */
function opencomune_save_payment_commission_percent($percent) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('Accesso negato', 'opencomune'));
    }

    if ($percent === '' || $percent === null) {
        $percent = 0;
    }

    if (!is_numeric($percent)) {
        return new WP_Error('invalid_percent', __('Inserisci una percentuale valida.', 'opencomune'));
    }

    $percent = round((float) $percent, 2);
    if ($percent < 0 || $percent > 100) {
        return new WP_Error('invalid_percent', __('La percentuale deve essere tra 0 e 100.', 'opencomune'));
    }

    update_option('opencomune_payment_commission_percent', (string) $percent);

    return true;
}

function opencomune_calculate_payment_commission_amount($gross_total, $percent = null) {
    $gross_total = max(0, round((float) $gross_total, 2));
    if ($percent === null) {
        $percent = opencomune_get_payment_commission_percent();
    }
    $percent = max(0, min(100, (float) $percent));

    if ($gross_total <= 0 || $percent <= 0) {
        return 0.0;
    }

    return round($gross_total * ($percent / 100), 2);
}

function opencomune_ensure_tour_ordini_commission_columns() {
    global $wpdb;
    opencomune_ensure_tour_ordini_table();
    $table = opencomune_tour_ordini_table_name();
    $columns = $wpdb->get_col("DESC {$table}", 0);

    if (!in_array('commission_percent', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN commission_percent decimal(5,2) NULL DEFAULT NULL AFTER totale");
    }
    if (!in_array('commission_amount', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN commission_amount decimal(10,2) NULL DEFAULT NULL AFTER commission_percent");
    }
    if (!in_array('net_amount', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN net_amount decimal(10,2) NULL DEFAULT NULL AFTER commission_amount");
    }
}

function opencomune_ensure_prenotazioni_commission_columns() {
    global $wpdb;

    if (!function_exists('opencomune_ensure_prenotazioni_booking_columns')) {
        return;
    }

    opencomune_ensure_prenotazioni_booking_columns();
    $table = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $columns = $wpdb->get_col("DESC {$table}", 0);
    if (!in_array('commission_percent', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN commission_percent decimal(5,2) NULL DEFAULT NULL AFTER metodo_pagamento");
    }
    if (!in_array('commission_amount', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN commission_amount decimal(10,2) NULL DEFAULT NULL AFTER commission_percent");
    }
    if (!in_array('net_amount', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN net_amount decimal(10,2) NULL DEFAULT NULL AFTER commission_amount");
    }
}

function opencomune_order_commission_is_frozen($order) {
    return isset($order->commission_amount)
        && $order->commission_amount !== null
        && $order->commission_amount !== '';
}

function opencomune_booking_commission_is_frozen($booking) {
    return isset($booking->commission_amount)
        && $booking->commission_amount !== null
        && $booking->commission_amount !== '';
}

/**
 * Valori commissione salvati sull'ordine (nessun ricalcolo con la percentuale attuale).
 *
 * @param object $order Riga ordine con totale e campi commission_*.
 * @return array{gross:float,percent:float|null,commission:float,net:float,has_stored:bool}
 */
function opencomune_get_order_commission_breakdown($order) {
    $gross = isset($order->totale) ? max(0, round((float) $order->totale, 2)) : 0.0;

    $has_stored = opencomune_order_commission_is_frozen($order);
    $stored_commission = $has_stored ? max(0, round((float) $order->commission_amount, 2)) : null;
    $stored_percent = isset($order->commission_percent) && $order->commission_percent !== null && $order->commission_percent !== ''
        ? max(0, min(100, round((float) $order->commission_percent, 2)))
        : null;
    $stored_net = isset($order->net_amount) && $order->net_amount !== null && $order->net_amount !== ''
        ? max(0, round((float) $order->net_amount, 2))
        : null;

    if ($has_stored) {
        $commission = $stored_commission;
        if ($stored_percent !== null) {
            $percent = $stored_percent;
        } elseif ($gross > 0) {
            $percent = round($commission / $gross * 100, 2);
        } else {
            $percent = null;
        }
        $net = $stored_net !== null ? $stored_net : round(max(0, $gross - $commission), 2);
    } else {
        $percent = null;
        $commission = 0.0;
        $net = $gross;
    }

    return [
        'gross' => $gross,
        'percent' => $percent,
        'commission' => $commission,
        'net' => $net,
        'has_stored' => $has_stored,
    ];
}

/**
 * Copia commissione dall'ordine alla prenotazione collegata (snapshot immutabile).
 */
function opencomune_sync_order_commission_to_booking($order) {
    if (is_string($order)) {
        $order = opencomune_get_tour_order_by_order_id($order);
    }
    if (!$order || !opencomune_order_commission_is_frozen($order)) {
        return;
    }

    $booking_id = (int) ($order->booking_id ?? 0);
    if ($booking_id <= 0) {
        return;
    }

    global $wpdb;
    opencomune_ensure_prenotazioni_commission_columns();
    $table = $wpdb->prefix . 'tour_prenotazioni';

    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $booking_id));
    if (!$booking || opencomune_booking_commission_is_frozen($booking)) {
        return;
    }

    $wpdb->update(
        $table,
        [
            'commission_percent' => (float) $order->commission_percent,
            'commission_amount' => (float) $order->commission_amount,
            'net_amount' => (float) $order->net_amount,
        ],
        ['id' => $booking_id],
        ['%f', '%f', '%f'],
        ['%d']
    );
}

/**
 * Allinea prenotazioni senza commissione ma con ordine già valorizzato (migrazione).
 */
function opencomune_sync_booking_commissions_from_orders($limit = 300) {
    global $wpdb;

    opencomune_ensure_tour_ordini_commission_columns();
    opencomune_ensure_prenotazioni_commission_columns();

    $ordini = opencomune_tour_ordini_table_name();
    $prenotazioni = $wpdb->prefix . 'tour_prenotazioni';
    $limit = max(1, min(500, (int) $limit));

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT o.order_id FROM {$ordini} o
             INNER JOIN {$prenotazioni} b ON b.id = o.booking_id
             WHERE o.status IN ('completed', 'refunded')
             AND o.commission_amount IS NOT NULL
             AND b.commission_amount IS NULL
             LIMIT %d",
            $limit
        )
    );

    foreach ($rows ?: [] as $row) {
        opencomune_sync_order_commission_to_booking((string) $row->order_id);
    }
}

/**
 * Salva commissione sull'ordine e sulla prenotazione al momento del pagamento completato.
 * Non sovrascrive valori già salvati se la percentuale piattaforma cambia in seguito.
 *
 * @param object|string $order Row o order_id.
 */
function opencomune_apply_commission_to_order($order) {
    if (is_string($order)) {
        $order = opencomune_get_tour_order_by_order_id($order);
    }
    if (!$order) {
        return;
    }

    opencomune_ensure_tour_ordini_commission_columns();

    if (!opencomune_order_commission_is_frozen($order)) {
        $gross = isset($order->totale) ? (float) $order->totale : 0.0;
        $percent = opencomune_get_payment_commission_percent();
        $commission = opencomune_calculate_payment_commission_amount($gross, $percent);
        $net = round(max(0, $gross - $commission), 2);

        global $wpdb;
        $wpdb->update(
            opencomune_tour_ordini_table_name(),
            [
                'commission_percent' => $percent,
                'commission_amount' => $commission,
                'net_amount' => $net,
            ],
            ['order_id' => (string) $order->order_id],
            ['%f', '%f', '%f'],
            ['%s']
        );

        $order = opencomune_get_tour_order_by_order_id((string) $order->order_id);
    }

    if ($order) {
        opencomune_sync_order_commission_to_booking($order);
    }
}

function opencomune_maybe_run_commission_booking_sync() {
    if (get_option('opencomune_commission_booking_sync_v1')) {
        return;
    }

    opencomune_sync_booking_commissions_from_orders();
    update_option('opencomune_commission_booking_sync_v1', '1', false);
}

add_action('after_setup_theme', 'opencomune_maybe_run_commission_booking_sync', 22);

function opencomune_ensure_commission_settings_page() {
    $slug = 'commissioni';
    $page = get_page_by_path($slug);
    if ($page instanceof WP_Post) {
        return;
    }

    $page_id = wp_insert_post([
        'post_title' => __('Commissioni', 'opencomune'),
        'post_name' => $slug,
        'post_status' => 'publish',
        'post_type' => 'page',
        'post_content' => '',
    ], true);

    if (!is_wp_error($page_id) && $page_id > 0) {
        update_post_meta($page_id, '_wp_page_template', 'page-commissioni.php');
    }
}

add_action('after_setup_theme', 'opencomune_ensure_commission_settings_page', 21);
