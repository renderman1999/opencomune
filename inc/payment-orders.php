<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_tour_ordini_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'tour_ordini';
}

function opencomune_ensure_tour_ordini_table() {
    global $wpdb;

    $table = opencomune_tour_ordini_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id varchar(64) NOT NULL,
        tour_id mediumint(9) NOT NULL,
        event_id mediumint(9) NOT NULL,
        booking_id bigint(20) unsigned NULL,
        checkout_token varchar(64) NULL,
        nome varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        telefono varchar(40),
        note text,
        totale decimal(10,2) NOT NULL,
        gateway varchar(20) NOT NULL,
        gateway_checkout_id varchar(64) NULL,
        status varchar(20) DEFAULT 'pending',
        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        data_pagamento datetime NULL,
        PRIMARY KEY (id),
        UNIQUE KEY order_id (order_id),
        KEY tour_id (tour_id),
        KEY email (email),
        KEY status (status),
        KEY booking_id (booking_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);

    $columns = $wpdb->get_col("DESC {$table}", 0);
    if (!in_array('booking_id', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN booking_id bigint(20) unsigned NULL AFTER event_id");
    }
    if (!in_array('checkout_token', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN checkout_token varchar(64) NULL AFTER booking_id");
    }
    if (!in_array('gateway_checkout_id', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN gateway_checkout_id varchar(64) NULL AFTER gateway");
    }

    opencomune_ensure_tour_ordini_refund_columns();
}

function opencomune_ensure_tour_ordini_refund_columns() {
    global $wpdb;

    $table = opencomune_tour_ordini_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return;
    }

    $columns = $wpdb->get_col("DESC {$table}", 0);
    if (!in_array('gateway_transaction_id', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN gateway_transaction_id varchar(64) NULL AFTER gateway_checkout_id");
    }
    if (!in_array('refund_status', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN refund_status varchar(20) NULL DEFAULT NULL AFTER data_pagamento");
    }
    if (!in_array('refund_amount', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN refund_amount decimal(10,2) NULL DEFAULT NULL AFTER refund_status");
    }
    if (!in_array('refund_at', $columns, true)) {
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN refund_at datetime NULL DEFAULT NULL AFTER refund_amount");
    }
}

function opencomune_get_tour_order_by_order_id($order_id) {
    global $wpdb;

    $order_id = sanitize_text_field((string) $order_id);
    if ($order_id === '') {
        return null;
    }

    opencomune_ensure_tour_ordini_table();
    $table = opencomune_tour_ordini_table_name();

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE order_id = %s LIMIT 1", $order_id));
}

function opencomune_get_checkout_session_payload_by_token($token) {
    $token = sanitize_text_field((string) $token);
    if ($token === '' || !function_exists('opencomune_checkout_sessions_table_name')) {
        return null;
    }

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $payload_json = $wpdb->get_var($wpdb->prepare(
        "SELECT payload FROM {$table} WHERE session_token = %s LIMIT 1",
        $token
    ));
    if (!$payload_json) {
        return null;
    }

    $payload = json_decode($payload_json, true);

    return is_array($payload) ? $payload : null;
}

function opencomune_get_tour_booking_by_id($booking_id) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return null;
    }

    global $wpdb;
    opencomune_ensure_prenotazioni_booking_columns();
    $table = $wpdb->prefix . 'tour_prenotazioni';

    return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $booking_id));
}

/**
 * Testo mostrato nella card SumUp Hosted Checkout (un solo campo description).
 */
function opencomune_build_sumup_checkout_description($experience_id, array $context = []) {
    $experience_id = (int) $experience_id;
    $booking_id = (int) ($context['booking_id'] ?? 0);
    $checkout_token = sanitize_text_field((string) ($context['checkout_token'] ?? ''));
    $fallback_name = trim((string) ($context['customer_name'] ?? ''));

    $payload = $checkout_token !== ''
        ? opencomune_get_checkout_session_payload_by_token($checkout_token)
        : null;
    $booking = $booking_id > 0 ? opencomune_get_tour_booking_by_id($booking_id) : null;

    $segments = [];
    $title = $experience_id > 0 ? get_the_title($experience_id) : '';
    if ($title !== '') {
        $segments[] = $title;
    }

    $date_ymd = is_array($payload) ? sanitize_text_field($payload['date'] ?? '') : '';
    $time_hm = is_array($payload) ? sanitize_text_field($payload['time'] ?? '') : '';
    if ($booking) {
        if ($date_ymd === '' && !empty($booking->booking_date)) {
            $date_ymd = $booking->booking_date;
        }
        if ($time_hm === '' && !empty($booking->booking_time)) {
            $time_hm = opencomune_format_booking_time_for_display($booking->booking_time);
        }
    }

    if ($date_ymd !== '') {
        $slot = date_i18n('d/m/Y', strtotime($date_ymd));
        if ($time_hm !== '') {
            $slot .= ' ' . opencomune_format_booking_time_for_display($time_hm);
        }
        $segments[] = $slot;
    }

    $participants = 0;
    if ($booking) {
        $participants = max(1, (int) $booking->participants);
    }
    if (is_array($payload['totals'] ?? null)) {
        $participants = max($participants, (int) ($payload['totals']['participants'] ?? 0));
    }
    if ($participants > 0) {
        $segments[] = sprintf(
            _n('%d partecipante', '%d partecipanti', $participants, 'opencomune'),
            $participants
        );
    }

    if ($experience_id > 0 && function_exists('opencomune_get_esperienza_meeting_points')) {
        $points = opencomune_get_esperienza_meeting_points($experience_id);
        if (!empty($points[0]) && is_array($points[0])) {
            $meeting = trim((string) ($points[0]['title'] ?? ''));
            if ($meeting !== '') {
                $segments[] = $meeting;
            }
        }
    }

    $description = implode(' · ', array_filter($segments));
    if ($description === '' && $fallback_name !== '') {
        $description = sprintf(__('Prenotazione (%s)', 'opencomune'), $fallback_name);
    }
    if ($description === '') {
        $description = __('Prenotazione esperienza', 'opencomune');
    }

    if (function_exists('mb_strlen') && mb_strlen($description) > 255) {
        $description = mb_substr($description, 0, 252) . '…';
    } elseif (strlen($description) > 255) {
        $description = substr($description, 0, 252) . '…';
    }

    return $description;
}

function opencomune_format_booking_time_for_display($time_value) {
    $time_value = trim((string) $time_value);
    if ($time_value === '') {
        return '';
    }
    $ts = strtotime($time_value);
    if ($ts) {
        return date_i18n('H:i', $ts);
    }

    return preg_replace('/:00$/', '', $time_value);
}

/**
 * Dettagli esperienza/prenotazione per email di conferma pagamento.
 *
 * @return array{experience_rows:array,participant_lines:array,payment_rows:array}
 */
function opencomune_build_payment_confirmation_email_details($ordine) {
    $experience_id = (int) ($ordine->tour_id ?? 0);
    $booking_id = (int) ($ordine->booking_id ?? 0);
    $payload = !empty($ordine->checkout_token)
        ? opencomune_get_checkout_session_payload_by_token($ordine->checkout_token)
        : null;
    $booking = $booking_id > 0 ? opencomune_get_tour_booking_by_id($booking_id) : null;

    if ($booking_id <= 0 && $booking === null && is_array($payload) && !empty($payload['booking_id'])) {
        $booking_id = (int) $payload['booking_id'];
        $booking = opencomune_get_tour_booking_by_id($booking_id);
    }

    $date_ymd = '';
    $time_hm = '';
    if (is_array($payload)) {
        $date_ymd = sanitize_text_field($payload['date'] ?? '');
        $time_hm = sanitize_text_field($payload['time'] ?? '');
    }
    if ($booking) {
        if ($date_ymd === '' && !empty($booking->booking_date)) {
            $date_ymd = $booking->booking_date;
        }
        if ($time_hm === '' && !empty($booking->booking_time)) {
            $time_hm = opencomune_format_booking_time_for_display($booking->booking_time);
        }
    }

    $participants_count = 0;
    if ($booking) {
        $participants_count = max(1, (int) $booking->participants);
    }
    if (is_array($payload['totals'] ?? null)) {
        $participants_count = max($participants_count, (int) ($payload['totals']['participants'] ?? 0));
    }

    $experience_rows = [];
    $tour_title = $experience_id > 0 ? get_the_title($experience_id) : '';
    if ($tour_title !== '') {
        $experience_rows[] = ['label' => __('Esperienza', 'opencomune'), 'value' => $tour_title];
    }

    if ($experience_id > 0) {
        $author_id = (int) get_post_field('post_author', $experience_id);
        if ($author_id > 0) {
            $organizer = get_the_author_meta('display_name', $author_id);
            if ($organizer !== '') {
                $experience_rows[] = ['label' => __('Organizzatore', 'opencomune'), 'value' => $organizer];
            }
        }

        if (function_exists('opencomune_get_tour_duration_parts') && function_exists('opencomune_format_tour_duration_label')) {
            $duration = opencomune_format_tour_duration_label(opencomune_get_tour_duration_parts($experience_id), 'it');
            if ($duration !== '') {
                $experience_rows[] = ['label' => __('Durata', 'opencomune'), 'value' => $duration];
            }
        }

        if (function_exists('opencomune_get_esperienza_meeting_points')) {
            $points = opencomune_get_esperienza_meeting_points($experience_id);
            if (!empty($points)) {
                $meeting_parts = [];
                foreach ($points as $point) {
                    if (!is_array($point)) {
                        continue;
                    }
                    $chunk = trim((string) ($point['title'] ?? ''));
                    $address = trim((string) ($point['address'] ?? ''));
                    if ($address !== '') {
                        $chunk = $chunk !== '' ? ($chunk . ' — ' . $address) : $address;
                    }
                    if ($chunk !== '') {
                        $meeting_parts[] = $chunk;
                    }
                }
                if ($meeting_parts !== []) {
                    $experience_rows[] = [
                        'label' => __('Punto di incontro', 'opencomune'),
                        'value' => implode("\n", $meeting_parts),
                    ];
                }
            }
        }
    }

    if ($date_ymd !== '') {
        $experience_rows[] = [
            'label' => __('Data', 'opencomune'),
            'value' => date_i18n('d/m/Y', strtotime($date_ymd)),
        ];
    }
    if ($time_hm !== '') {
        $display_time = opencomune_format_booking_time_for_display($time_hm);
        $experience_rows[] = ['label' => __('Orario', 'opencomune'), 'value' => $display_time];
    }
    if ($participants_count > 0) {
        $experience_rows[] = [
            'label' => __('Partecipanti', 'opencomune'),
            'value' => (string) $participants_count,
        ];
    }
    if ($booking_id > 0) {
        $experience_rows[] = ['label' => __('N. prenotazione', 'opencomune'), 'value' => '#' . $booking_id];
    }

    $telefono = trim((string) ($ordine->telefono ?? ''));
    if ($telefono === '' && $booking && !empty($booking->telefono)) {
        $telefono = trim((string) $booking->telefono);
    }
    if ($telefono !== '') {
        $experience_rows[] = ['label' => __('Telefono contatto', 'opencomune'), 'value' => $telefono];
    }

    $note = trim((string) ($ordine->note ?? ''));
    if ($note === '' && $booking && !empty($booking->note)) {
        $note = trim((string) $booking->note);
    }
    if ($note !== '') {
        $experience_rows[] = ['label' => __('Note', 'opencomune'), 'value' => $note];
    }

    $participant_lines = [];
    $lines = is_array($payload['totals']['lines'] ?? null) ? $payload['totals']['lines'] : [];
    foreach ($lines as $line) {
        if (!is_array($line)) {
            continue;
        }
        $qty = (int) ($line['quantity'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $label = trim((string) ($line['label'] ?? __('Partecipante', 'opencomune')));
        $participant_lines[] = $qty . ' × ' . $label;
    }

    $travelers = is_array($payload['travelers'] ?? null) ? $payload['travelers'] : [];
    foreach ($travelers as $traveler) {
        if (!is_array($traveler)) {
            continue;
        }
        if (!empty($traveler['minor_group']) && !empty($traveler['quantity'])) {
            $participant_lines[] = sprintf(
                __('+ %d %s', 'opencomune'),
                (int) $traveler['quantity'],
                (string) ($traveler['category_label'] ?? __('Minore', 'opencomune'))
            );
            continue;
        }
        $name = trim(((string) ($traveler['first_name'] ?? '')) . ' ' . ((string) ($traveler['last_name'] ?? '')));
        if ($name === '') {
            continue;
        }
        $cat = trim((string) ($traveler['category_label'] ?? ''));
        $participant_lines[] = $cat !== '' ? ($name . ' (' . $cat . ')') : $name;
    }

    $payment_date = !empty($ordine->data_pagamento)
        ? date_i18n('d/m/Y H:i', strtotime($ordine->data_pagamento))
        : date_i18n('d/m/Y H:i');
    $gateway_labels = function_exists('opencomune_get_payment_gateway_labels')
        ? opencomune_get_payment_gateway_labels()
        : [];
    $gateway_key = sanitize_key((string) ($ordine->gateway ?? ''));
    $gateway_label = $gateway_labels[$gateway_key] ?? strtoupper($gateway_key);

    $payment_rows = [
        ['label' => __('Ordine', 'opencomune'), 'value' => (string) $ordine->order_id],
        ['label' => __('Metodo', 'opencomune'), 'value' => $gateway_label],
        ['label' => __('Totale pagato', 'opencomune'), 'value' => number_format((float) $ordine->totale, 2, ',', '.') . ' EUR'],
        ['label' => __('Data pagamento', 'opencomune'), 'value' => $payment_date],
    ];

    return [
        'experience_rows' => $experience_rows,
        'participant_lines' => $participant_lines,
        'payment_rows' => $payment_rows,
    ];
}

function opencomune_store_sumup_checkout_id_for_order($order_id, $checkout_id) {
    $checkout_id = sanitize_text_field((string) $checkout_id);
    if ($checkout_id === '') {
        return;
    }

    global $wpdb;
    opencomune_ensure_tour_ordini_table();
    $wpdb->update(
        opencomune_tour_ordini_table_name(),
        ['gateway_checkout_id' => $checkout_id],
        ['order_id' => sanitize_text_field((string) $order_id)],
        ['%s'],
        ['%s']
    );
}

/**
 * @return array|WP_Error Checkout object from SumUp API.
 */
function opencomune_sumup_fetch_checkout($tour_id, $checkout_id, $checkout_reference = '') {
    $resolved = opencomune_resolve_payment_gateway_config_for_experience((int) $tour_id, 'sumup');
    $api_key = $resolved['credentials']['api_key'] ?? '';
    if ($api_key === '') {
        return new WP_Error('sumup_config', __('Credenziali SumUp non disponibili.', 'opencomune'));
    }

    $checkout_id = sanitize_text_field((string) $checkout_id);
    $url = $checkout_id !== ''
        ? 'https://api.sumup.com/v0.1/checkouts/' . rawurlencode($checkout_id)
        : '';

    if ($url === '' && $checkout_reference !== '') {
        $url = add_query_arg(
            'checkout_reference',
            sanitize_text_field($checkout_reference),
            'https://api.sumup.com/v0.1/checkouts'
        );
    }

    if ($url === '') {
        return new WP_Error('sumup_checkout', __('Checkout SumUp non trovato.', 'opencomune'));
    }

    $response = wp_remote_get(
        $url,
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/problem+json, application/json',
            ],
            'timeout' => 20,
        ]
    );

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code === 404) {
        return new WP_Error('sumup_not_found', __('Pagamento SumUp non trovato.', 'opencomune'));
    }

    if ($code < 200 || $code >= 300 || !is_array($body)) {
        return new WP_Error('sumup_verify', __('Impossibile verificare il pagamento SumUp.', 'opencomune'));
    }

    if (isset($body[0]) && is_array($body[0])) {
        $body = $body[0];
    }

    return $body;
}

function opencomune_sumup_checkout_is_paid_body(array $body) {
    $status = strtoupper((string) ($body['status'] ?? ''));
    if ($status === 'PAID') {
        return true;
    }

    if (!empty($body['transactions']) && is_array($body['transactions'])) {
        foreach ($body['transactions'] as $txn) {
            if (!is_array($txn)) {
                continue;
            }
            $txn_status = strtoupper((string) ($txn['status'] ?? ''));
            if (in_array($txn_status, ['SUCCESSFUL', 'PAID'], true)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * Dati SumUp per la dashboard partner (checkout + prima transazione rilevante).
 *
 * @return array<string, mixed>|WP_Error
 */
function opencomune_get_sumup_checkout_display_data($tour_id, $checkout_id, $checkout_reference = '') {
    $body = opencomune_sumup_fetch_checkout($tour_id, $checkout_id, $checkout_reference);
    if (is_wp_error($body)) {
        return $body;
    }

    $currency = strtoupper((string) ($body['currency'] ?? 'EUR'));
    $amount = isset($body['amount']) ? (float) $body['amount'] : 0.0;

    $transaction = null;
    if (!empty($body['transactions']) && is_array($body['transactions'])) {
        foreach ($body['transactions'] as $txn) {
            if (!is_array($txn)) {
                continue;
            }
            $transaction = $txn;
            $txn_status = strtoupper((string) ($txn['status'] ?? ''));
            if (in_array($txn_status, ['SUCCESSFUL', 'PAID'], true)) {
                break;
            }
        }
    }

    $txn_amount = $transaction && isset($transaction['amount']) ? (float) $transaction['amount'] : $amount;
    $txn_currency = $transaction ? strtoupper((string) ($transaction['currency'] ?? $currency)) : $currency;

    $paid_at = '';
    if ($transaction && !empty($transaction['timestamp'])) {
        $paid_at = date_i18n('d/m/Y H:i', strtotime((string) $transaction['timestamp']));
    } elseif (!empty($body['date'])) {
        $paid_at = date_i18n('d/m/Y H:i', strtotime((string) $body['date']));
    }

    return [
        'checkout_id' => (string) ($body['id'] ?? $checkout_id),
        'checkout_reference' => (string) ($body['checkout_reference'] ?? $checkout_reference),
        'checkout_status' => (string) ($body['status'] ?? ''),
        'merchant_code' => (string) ($body['merchant_code'] ?? ''),
        'amount' => $amount,
        'currency' => $currency,
        'amount_formatted' => number_format_i18n($amount, 2) . ' ' . $currency,
        'is_paid' => opencomune_sumup_checkout_is_paid_body($body),
        'paid_at' => $paid_at,
        'transaction_id' => $transaction ? (string) ($transaction['id'] ?? '') : '',
        'transaction_code' => $transaction ? (string) ($transaction['transaction_code'] ?? '') : '',
        'transaction_status' => $transaction ? (string) ($transaction['status'] ?? '') : '',
        'transaction_amount' => $txn_amount,
        'transaction_amount_formatted' => number_format_i18n($txn_amount, 2) . ' ' . $txn_currency,
        'payment_type' => $transaction ? (string) ($transaction['payment_type'] ?? '') : '',
        'installments_count' => $transaction && isset($transaction['installments_count'])
            ? (int) $transaction['installments_count']
            : 0,
    ];
}

function opencomune_tour_order_status_label($status) {
    $status = sanitize_key((string) $status);
    $map = [
        'pending' => __('In attesa', 'opencomune'),
        'completed' => __('Completato', 'opencomune'),
        'paid' => __('Pagato', 'opencomune'),
        'failed' => __('Fallito', 'opencomune'),
        'cancelled' => __('Annullato', 'opencomune'),
        'refunded' => __('Rimborsato', 'opencomune'),
    ];

    return $map[$status] ?? $status;
}

function opencomune_tour_order_status_badge_class($status) {
    $status = sanitize_key((string) $status);
    $map = [
        'completed' => 'oc-booking-status--confirmed',
        'paid' => 'oc-booking-status--confirmed',
        'pending' => 'oc-booking-status--pending',
        'refunded' => 'oc-booking-status--refunded',
        'cancelled' => 'oc-booking-status--cancelled',
        'failed' => 'oc-booking-status--failed',
    ];

    return $map[$status] ?? 'oc-booking-status--pending';
}

function opencomune_tour_order_refund_status_label($status) {
    $status = sanitize_key((string) $status);
    $map = [
        'refunded' => __('Rimborsato', 'opencomune'),
        'partial' => __('Rimborso parziale', 'opencomune'),
        'failed' => __('Rimborso fallito', 'opencomune'),
    ];

    return $map[$status] ?? $status;
}

function opencomune_store_gateway_transaction_id_for_order($order_id, $transaction_id) {
    $transaction_id = sanitize_text_field((string) $transaction_id);
    if ($transaction_id === '') {
        return;
    }

    global $wpdb;
    opencomune_ensure_tour_ordini_refund_columns();
    $wpdb->update(
        opencomune_tour_ordini_table_name(),
        ['gateway_transaction_id' => $transaction_id],
        ['order_id' => sanitize_text_field((string) $order_id)],
        ['%s'],
        ['%s']
    );
}

/**
 * @param object $order
 * @return string|WP_Error
 */
function opencomune_resolve_sumup_transaction_id_for_order($order, $tour_id = 0) {
    if (!$order) {
        return new WP_Error('no_order', __('Ordine non trovato.', 'opencomune'));
    }

    opencomune_ensure_tour_ordini_refund_columns();

    if (!empty($order->gateway_transaction_id)) {
        return (string) $order->gateway_transaction_id;
    }

    $tour_id = (int) $tour_id;
    if ($tour_id <= 0) {
        $tour_id = (int) ($order->tour_id ?? 0);
    }

    $sumup = opencomune_get_sumup_checkout_display_data(
        $tour_id,
        (string) ($order->gateway_checkout_id ?? ''),
        (string) ($order->order_id ?? '')
    );
    if (is_wp_error($sumup)) {
        return $sumup;
    }

    $transaction_id = (string) ($sumup['transaction_id'] ?? '');
    if ($transaction_id === '') {
        return new WP_Error('sumup_no_txn', __('Transazione SumUp non disponibile per il rimborso.', 'opencomune'));
    }

    opencomune_store_gateway_transaction_id_for_order($order->order_id, $transaction_id);

    return $transaction_id;
}

function opencomune_sync_sumup_transaction_id_for_order($order_id) {
    $order = opencomune_get_tour_order_by_order_id($order_id);
    if (!$order || sanitize_key((string) ($order->gateway ?? '')) !== 'sumup') {
        return;
    }

    opencomune_resolve_sumup_transaction_id_for_order($order, (int) $order->tour_id);
}

/**
 * @return true|WP_Error
 */
function opencomune_sumup_refund_transaction($tour_id, $transaction_id, $amount = null) {
    $transaction_id = sanitize_text_field((string) $transaction_id);
    if ($transaction_id === '') {
        return new WP_Error('sumup_txn', __('ID transazione mancante.', 'opencomune'));
    }

    $resolved = opencomune_resolve_payment_gateway_config_for_experience((int) $tour_id, 'sumup');
    $api_key = $resolved['credentials']['api_key'] ?? '';
    if ($api_key === '') {
        return new WP_Error('sumup_config', __('Credenziali SumUp non disponibili.', 'opencomune'));
    }

    $url = 'https://api.sumup.com/v0.1/me/refund/' . rawurlencode($transaction_id);
    $request_args = [
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ],
        'timeout' => 45,
    ];

    if ($amount !== null && (float) $amount > 0) {
        $request_args['body'] = wp_json_encode(['amount' => round((float) $amount, 2)]);
    }

    $response = wp_remote_request($url, $request_args);
    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body_raw = wp_remote_retrieve_body($response);
    $body = json_decode($body_raw, true);

    if ($code >= 200 && $code < 300) {
        return true;
    }

    $detail = '';
    if (is_array($body)) {
        $detail = (string) ($body['error_message'] ?? $body['message'] ?? $body['detail'] ?? '');
    }
    if ($detail === '' && $body_raw !== '') {
        $detail = wp_strip_all_tags($body_raw);
        if (strlen($detail) > 200) {
            $detail = substr($detail, 0, 200) . '…';
        }
    }

    if ($code === 401) {
        return new WP_Error(
            'sumup_unauthorized',
            __('SumUp ha rifiutato la richiesta di rimborso (autorizzazione).', 'opencomune')
        );
    }

    return new WP_Error(
        'sumup_refund',
        $detail !== ''
            ? sprintf(__('Rimborso SumUp non riuscito: %s', 'opencomune'), $detail)
            : __('Rimborso SumUp non riuscito.', 'opencomune')
    );
}

/**
 * @param object|null $order
 * @return array<string, mixed>
 */
function opencomune_tour_order_refund_eligibility($order, $booking_status) {
    $booking_status = sanitize_key((string) $booking_status);
    $base = [
        'can_refund' => false,
        'refund_amount' => 0.0,
        'refund_amount_formatted' => '',
        'refund_status' => '',
        'refund_status_label' => '',
        'refunded_at' => '',
        'refunded_amount_formatted' => '',
    ];

    if (!$order) {
        return $base;
    }

    opencomune_ensure_tour_ordini_refund_columns();

    $amount = isset($order->totale) ? (float) $order->totale : 0.0;
    $base['refund_amount'] = $amount;
    $base['refund_amount_formatted'] = number_format_i18n($amount, 2) . ' EUR';

    $refund_status = sanitize_key((string) ($order->refund_status ?? ''));
    $order_status = sanitize_key((string) ($order->status ?? ''));
    if ($refund_status === 'refunded' || $order_status === 'refunded') {
        $base['refund_status'] = 'refunded';
        $base['refund_status_label'] = opencomune_tour_order_refund_status_label('refunded');
        if (!empty($order->refund_at)) {
            $base['refunded_at'] = date_i18n('d/m/Y H:i', strtotime($order->refund_at));
        }
        $refunded_amount = isset($order->refund_amount) ? (float) $order->refund_amount : $amount;
        $base['refunded_amount_formatted'] = number_format_i18n($refunded_amount, 2) . ' EUR';

        return $base;
    }

    if ($booking_status !== 'cancellata') {
        return $base;
    }

    if ($order_status !== 'completed') {
        return $base;
    }

    if (sanitize_key((string) ($order->gateway ?? '')) !== 'sumup') {
        return $base;
    }

    if ($amount <= 0) {
        return $base;
    }

    $base['can_refund'] = true;

    return $base;
}

/**
 * @return array|WP_Error
 */
function opencomune_process_booking_payment_refund($booking_id) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0 || !opencomune_user_can_manage_booking($booking_id)) {
        return new WP_Error('forbidden', __('Non autorizzato.', 'opencomune'));
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return new WP_Error('not_found', __('Prenotazione non trovata.', 'opencomune'));
    }

    if ($booking->status !== 'cancellata') {
        return new WP_Error(
            'not_cancelled',
            __('Il rimborso è disponibile solo per prenotazioni annullate.', 'opencomune')
        );
    }

    $order = opencomune_get_booking_payment_order($booking_id);
    $eligibility = opencomune_tour_order_refund_eligibility($order, $booking->status);
    if (empty($eligibility['can_refund'])) {
        if (($eligibility['refund_status'] ?? '') === 'refunded') {
            return new WP_Error('already_refunded', __('Pagamento già rimborsato.', 'opencomune'));
        }

        return new WP_Error('not_refundable', __('Questo pagamento non può essere rimborsato.', 'opencomune'));
    }

    $tour_id = (int) $booking->tour_id;
    $transaction_id = opencomune_resolve_sumup_transaction_id_for_order($order, $tour_id);
    if (is_wp_error($transaction_id)) {
        return $transaction_id;
    }

    $refund_amount = isset($order->totale) ? (float) $order->totale : 0.0;
    $refund_result = opencomune_sumup_refund_transaction($tour_id, $transaction_id, $refund_amount);
    if (is_wp_error($refund_result)) {
        return $refund_result;
    }

    global $wpdb;
    opencomune_ensure_tour_ordini_refund_columns();
    $wpdb->update(
        opencomune_tour_ordini_table_name(),
        [
            'status' => 'refunded',
            'refund_status' => 'refunded',
            'refund_amount' => $refund_amount,
            'refund_at' => current_time('mysql'),
            'gateway_transaction_id' => $transaction_id,
        ],
        ['id' => (int) $order->id],
        ['%s', '%s', '%f', '%s', '%s'],
        ['%d']
    );

    $reason = sprintf(
        /* translators: 1: amount formatted, 2: transaction id */
        __('Rimborso SumUp di %1$s (transazione %2$s).', 'opencomune'),
        number_format_i18n($refund_amount, 2) . ' EUR',
        $transaction_id
    );
    opencomune_insert_booking_log_entry($booking_id, 'payment_refund', $reason, 'cancellata', 'cancellata');

    return [
        'message' => __('Rimborso inviato a SumUp con successo.', 'opencomune'),
        'refund_amount_formatted' => number_format_i18n($refund_amount, 2) . ' EUR',
        'detail' => opencomune_get_booking_detail_payload($booking_id),
    ];
}

/**
 * @param object $order Row from tour_ordini.
 * @return array<string, mixed>|null
 */
function opencomune_format_tour_order_for_partner_detail($order, $tour_id = 0, $booking_status = '') {
    if (!$order) {
        return null;
    }

    $tour_id = (int) $tour_id;
    if ($tour_id <= 0) {
        $tour_id = (int) ($order->tour_id ?? 0);
    }

    $gateway_labels = function_exists('opencomune_get_payment_gateway_labels')
        ? opencomune_get_payment_gateway_labels()
        : [];
    $gateway_key = sanitize_key((string) ($order->gateway ?? ''));
    $gateway_label = $gateway_labels[$gateway_key] ?? ($gateway_key !== '' ? strtoupper($gateway_key) : '—');

    $payload = [
        'id' => (int) $order->id,
        'order_id' => (string) ($order->order_id ?? ''),
        'status' => (string) ($order->status ?? ''),
        'status_label' => opencomune_tour_order_status_label($order->status ?? ''),
        'status_badge_class' => opencomune_tour_order_status_badge_class($order->status ?? ''),
        'amount' => isset($order->totale) ? (float) $order->totale : 0.0,
        'currency' => 'EUR',
        'amount_formatted' => number_format_i18n(isset($order->totale) ? (float) $order->totale : 0, 2) . ' EUR',
        'gateway' => $gateway_key,
        'gateway_label' => $gateway_label,
        'gateway_checkout_id' => (string) ($order->gateway_checkout_id ?? ''),
        'created_at' => !empty($order->data_creazione)
            ? date_i18n('d/m/Y H:i', strtotime($order->data_creazione))
            : '',
        'paid_at' => !empty($order->data_pagamento)
            ? date_i18n('d/m/Y H:i', strtotime($order->data_pagamento))
            : '',
        'commission_percent' => null,
        'commission_amount' => null,
        'net_amount' => null,
        'commission_snapshot_stored' => false,
        'sumup' => null,
        'sumup_error' => '',
    ];

    if (function_exists('opencomune_get_order_commission_breakdown')) {
        $breakdown = opencomune_get_order_commission_breakdown($order);
        $payload['commission_snapshot_stored'] = !empty($breakdown['has_stored']);
        if ($breakdown['has_stored']) {
            $payload['commission_percent'] = $breakdown['percent'];
            $payload['commission_amount'] = $breakdown['commission'];
            $payload['net_amount'] = $breakdown['net'];
            $payload['commission_amount_formatted'] = number_format_i18n($breakdown['commission'], 2) . ' EUR';
            $payload['net_amount_formatted'] = number_format_i18n($breakdown['net'], 2) . ' EUR';
        }
    }

    if ($gateway_key === 'sumup' && $tour_id > 0) {
        $sumup = opencomune_get_sumup_checkout_display_data(
            $tour_id,
            (string) ($order->gateway_checkout_id ?? ''),
            (string) ($order->order_id ?? '')
        );
        if (is_wp_error($sumup)) {
            $payload['sumup_error'] = $sumup->get_error_message();
        } else {
            $payload['sumup'] = $sumup;
        }
    }

    $refund_ctx = opencomune_tour_order_refund_eligibility($order, $booking_status);
    $payload = array_merge($payload, $refund_ctx);

    return $payload;
}

/**
 * @return true|WP_Error
 */
function opencomune_sumup_is_checkout_paid($tour_id, $checkout_id, $checkout_reference = '') {
    $body = opencomune_sumup_fetch_checkout($tour_id, $checkout_id, $checkout_reference);
    if (is_wp_error($body)) {
        return $body;
    }

    if (opencomune_sumup_checkout_is_paid_body($body)) {
        return true;
    }

    return new WP_Error('sumup_pending', __('Il pagamento SumUp non risulta ancora completato.', 'opencomune'));
}

function opencomune_finalize_booking_after_online_payment($booking_id) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return false;
    }

    global $wpdb;
    opencomune_ensure_prenotazioni_booking_columns();
    $table = $wpdb->prefix . 'tour_prenotazioni';
    $booking = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $booking_id));
    if (!$booking) {
        return false;
    }

    if ($booking->status === 'confermata') {
        return true;
    }

    if ($booking->status !== 'pending_payment') {
        return false;
    }

    $wpdb->update(
        $table,
        ['status' => 'confermata'],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );

    $tour_id = (int) $booking->tour_id;
    $event_id = (int) $booking->event_id;
    $participants = max(1, (int) $booking->participants);
    $slot = opencomune_resolve_booking_event($tour_id, $event_id, $participants);
    if (!is_wp_error($slot)) {
        opencomune_after_booking_decrement_legacy($tour_id, $event_id, $participants, $slot);
    }

    return true;
}

function opencomune_complete_checkout_session_after_payment($checkout_token, $booking_id = 0) {
    $checkout_token = sanitize_text_field((string) $checkout_token);
    if ($checkout_token === '' || !function_exists('opencomune_checkout_sessions_table_name')) {
        return;
    }

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
        $checkout_token
    ));
    if (!$row) {
        return;
    }

    $payload = json_decode($row->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    if ($booking_id > 0) {
        $payload['booking_id'] = $booking_id;
    }

    $wpdb->update(
        $table,
        [
            'status' => 'completed',
            'booking_id' => $booking_id > 0 ? $booking_id : (int) $row->booking_id,
            'payload' => wp_json_encode($payload),
        ],
        ['session_token' => $checkout_token],
        ['%s', '%d', '%s'],
        ['%s']
    );
}

/**
 * Registra pagamento completato e conferma prenotazione collegata.
 *
 * @return array{state:string,message:string,order:object|null,booking_id:int,tour_permalink:string,checkout_url:string}
 */
function opencomune_build_payment_cancel_url($order_id, $checkout_token = '', $gateway = 'sumup') {
    $args = array_filter([
        'order_id' => sanitize_text_field((string) $order_id),
        'gateway' => sanitize_key((string) $gateway),
        'checkout_token' => sanitize_text_field((string) $checkout_token),
    ], static function ($value) {
        return $value !== '';
    });

    return add_query_arg($args, home_url('/pagamento-annullato/'));
}

function opencomune_handle_payment_return($context) {
    $context = $context === 'cancel' ? 'cancel' : 'success';
    $order_id = sanitize_text_field(wp_unslash($_GET['order_id'] ?? $_GET['txn_id'] ?? ''));
    $gateway = sanitize_key(wp_unslash($_GET['gateway'] ?? ''));

    $result = [
        'state' => $context === 'cancel' ? 'cancelled' : 'unknown',
        'message' => '',
        'order' => null,
        'booking_id' => 0,
        'tour_permalink' => home_url('/'),
        'checkout_url' => '',
    ];

    if ($context === 'cancel') {
        $checkout_token = sanitize_text_field(wp_unslash($_GET['checkout_token'] ?? ''));
        if ($order_id !== '') {
            $ordine = opencomune_get_tour_order_by_order_id($order_id);
            if ($ordine) {
                $result['order'] = $ordine;
                if ($checkout_token === '' && !empty($ordine->checkout_token)) {
                    $checkout_token = (string) $ordine->checkout_token;
                }
                $tour_post = get_post((int) $ordine->tour_id);
                if ($tour_post) {
                    $result['tour_permalink'] = get_permalink($tour_post);
                }
            }
            global $wpdb;
            opencomune_ensure_tour_ordini_table();
            $wpdb->update(
                opencomune_tour_ordini_table_name(),
                ['status' => 'cancelled'],
                ['order_id' => $order_id],
                ['%s'],
                ['%s']
            );
        }
        if ($checkout_token !== '' && function_exists('opencomune_get_checkout_session_url')) {
            $session = function_exists('opencomune_get_checkout_session')
                ? opencomune_get_checkout_session($checkout_token)
                : null;
            if ($session) {
                $result['checkout_url'] = opencomune_get_checkout_session_url($checkout_token);
            }
        }
        $result['state'] = 'cancelled';
        $result['message'] = __('Il pagamento è stato annullato. La prenotazione non è stata confermata.', 'opencomune');
        if ($result['checkout_url'] !== '') {
            $result['message'] .= ' ' . __('Puoi riprendere il checkout e riprovare il pagamento.', 'opencomune');
        }

        return $result;
    }

    if ($order_id === '') {
        $result['message'] = __('Ordine non valido.', 'opencomune');

        return $result;
    }

    $ordine = opencomune_get_tour_order_by_order_id($order_id);
    if (!$ordine) {
        $result['message'] = __('Ordine di pagamento non trovato.', 'opencomune');

        return $result;
    }

    $result['order'] = $ordine;
    $result['booking_id'] = (int) ($ordine->booking_id ?? 0);
    if ($result['booking_id'] <= 0) {
        global $wpdb;
        opencomune_ensure_prenotazioni_booking_columns();
        $prenotazioni_table = $wpdb->prefix . 'tour_prenotazioni';
        $linked_booking_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prenotazioni_table}
             WHERE tour_id = %d AND email = %s AND status = 'pending_payment'
             ORDER BY id DESC LIMIT 1",
            (int) $ordine->tour_id,
            $ordine->email
        ));
        if ($linked_booking_id > 0) {
            $result['booking_id'] = $linked_booking_id;
            $wpdb->update(
                opencomune_tour_ordini_table_name(),
                ['booking_id' => $linked_booking_id],
                ['order_id' => $order_id],
                ['%d'],
                ['%s']
            );
        }
    }
    $tour_post = get_post((int) $ordine->tour_id);
    if ($tour_post) {
        $result['tour_permalink'] = get_permalink($tour_post);
    }

    if ($gateway === '' && !empty($ordine->gateway)) {
        $gateway = sanitize_key($ordine->gateway);
    }
    if ($gateway === '') {
        $gateway = function_exists('opencomune_get_global_payment_gateway_slug')
            ? opencomune_get_global_payment_gateway_slug()
            : 'sumup';
    }

    if ($ordine->status === 'completed') {
        $result['state'] = 'success';
        $result['message'] = __('Pagamento già registrato. La prenotazione è confermata. Ti abbiamo inviato un\'email di conferma.', 'opencomune');

        return $result;
    }

    if ($gateway === 'sumup') {
        $checkout_ref = preg_replace('/[^A-Za-z0-9_\-]/', '', $order_id);
        $paid = opencomune_sumup_is_checkout_paid(
            (int) $ordine->tour_id,
            (string) ($ordine->gateway_checkout_id ?? ''),
            $checkout_ref
        );
        if (is_wp_error($paid)) {
            if ($paid->get_error_code() === 'sumup_config') {
                $result['state'] = 'pending';
                $result['message'] = $paid->get_error_message();

                return $result;
            }

            $cancel_url = opencomune_build_payment_cancel_url(
                $order_id,
                (string) ($ordine->checkout_token ?? ''),
                'sumup'
            );
            if (!headers_sent()) {
                wp_safe_redirect($cancel_url);
                exit;
            }

            $result['state'] = 'cancelled';
            $result['message'] = $paid->get_error_message();
            if (!empty($ordine->checkout_token) && function_exists('opencomune_get_checkout_session_url')) {
                $result['checkout_url'] = opencomune_get_checkout_session_url((string) $ordine->checkout_token);
            }

            return $result;
        }
    }

    global $wpdb;
    opencomune_ensure_tour_ordini_table();
    $wpdb->update(
        opencomune_tour_ordini_table_name(),
        [
            'status' => 'completed',
            'data_pagamento' => current_time('mysql'),
        ],
        ['order_id' => $order_id],
        ['%s', '%s'],
        ['%s']
    );

    $ordine = opencomune_get_tour_order_by_order_id($order_id);
    $result['order'] = $ordine;

    if ($gateway === 'sumup' && function_exists('opencomune_sync_sumup_transaction_id_for_order')) {
        opencomune_sync_sumup_transaction_id_for_order($order_id);
    }

    if ($ordine && function_exists('opencomune_apply_commission_to_order')) {
        opencomune_apply_commission_to_order($ordine);
        $ordine = opencomune_get_tour_order_by_order_id($order_id);
        $result['order'] = $ordine;
    }

    if ($ordine && function_exists('opencomune_log_payment_received_operation')) {
        opencomune_log_payment_received_operation($ordine);
    }

    if ($result['booking_id'] > 0) {
        opencomune_finalize_booking_after_online_payment($result['booking_id']);
    }

    if (!empty($ordine->checkout_token)) {
        opencomune_complete_checkout_session_after_payment($ordine->checkout_token, $result['booking_id']);
    }

    if ($ordine && function_exists('opencomune_invia_email_conferma_pagamento')) {
        if ($result['booking_id'] > 0) {
            $ordine->booking_id = (int) $result['booking_id'];
        }
        opencomune_invia_email_conferma_pagamento($ordine);
    }

    $result['state'] = 'success';
    $result['message'] = __('Pagamento registrato con successo. La prenotazione è confermata.', 'opencomune');
    $result['message'] .= ' Ti abbiamo inviato un\'email di conferma.';
    return $result;
}

function opencomune_ensure_payment_return_pages() {
    $pages = [
        'pagamento-confermato' => [
            'title' => __('Pagamento confermato', 'opencomune'),
            'template' => 'page-pagamento-confermato.php',
        ],
        'pagamento-annullato' => [
            'title' => __('Pagamento annullato', 'opencomune'),
            'template' => 'page-pagamento-annullato.php',
        ],
    ];

    foreach ($pages as $slug => $config) {
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            continue;
        }

        $page_id = wp_insert_post([
            'post_title' => $config['title'],
            'post_name' => $slug,
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '',
        ], true);

        if (!is_wp_error($page_id) && $page_id > 0) {
            update_post_meta($page_id, '_wp_page_template', $config['template']);
        }
    }
}

add_action('after_setup_theme', 'opencomune_ensure_payment_return_pages', 20);

function opencomune_enqueue_payment_result_assets() {
    if (!is_page_template(['page-pagamento-confermato.php', 'page-pagamento-annullato.php'])) {
        return;
    }

    $path = get_template_directory() . '/assets/css/payment-result.css';
    wp_enqueue_style(
        'opencomune-payment-result',
        get_template_directory_uri() . '/assets/css/payment-result.css',
        [],
        is_readable($path) ? (string) filemtime($path) : '1.0.0'
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_payment_result_assets', 100);
