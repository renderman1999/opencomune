<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_experience_requires_booking_approval($experience_id) {
    $experience_id = (int) $experience_id;

    return $experience_id > 0
        && opencomune_get_esperienza_capacity_mode($experience_id) === 'on_request';
}

function opencomune_booking_statuses_that_hold_capacity() {
    return ['confermata', 'pending_payment'];
}

function opencomune_get_checkout_session_row_by_booking_id($booking_id) {
    opencomune_ensure_checkout_sessions_table();
    global $wpdb;

    $booking_id = (int) $booking_id;
    if ($booking_id <= 0) {
        return null;
    }

    $table = opencomune_checkout_sessions_table_name();

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE booking_id = %d
         AND status IN ('pending_approval', 'awaiting_payment', 'open')
         ORDER BY id DESC LIMIT 1",
        $booking_id
    ));
}

function opencomune_extend_checkout_session($token, $status, $expires_gmt, array $payload_patch = []) {
    global $wpdb;

    $session = opencomune_get_checkout_session_raw($token);
    if (!$session) {
        return new WP_Error('not_found', __('Sessione checkout non trovata.', 'opencomune'));
    }

    $payload = json_decode($session->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }
    foreach ($payload_patch as $key => $value) {
        $payload[$key] = $value;
    }

    $table = opencomune_checkout_sessions_table_name();
    $updated = $wpdb->update(
        $table,
        [
            'status' => sanitize_key($status),
            'expires_at' => $expires_gmt,
            'payload' => wp_json_encode($payload),
        ],
        ['session_token' => sanitize_text_field($token)],
        ['%s', '%s', '%s'],
        ['%s']
    );

    if ($updated === false) {
        return new WP_Error('db_error', __('Impossibile aggiornare la sessione checkout.', 'opencomune'));
    }

    return true;
}

/**
 * Raw row without expiry rules (internal).
 */
function opencomune_get_checkout_session_raw($token) {
    opencomune_ensure_checkout_sessions_table();
    global $wpdb;

    $token = sanitize_text_field($token);
    if ($token === '') {
        return null;
    }

    $table = opencomune_checkout_sessions_table_name();

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
        $token
    ));
}

function opencomune_get_experience_partner_notification_email($experience_id) {
    $experience_id = (int) $experience_id;
    $post = get_post($experience_id);
    if (!$post) {
        return '';
    }

    $author_id = (int) $post->post_author;
    if ($author_id <= 0) {
        return '';
    }

    if (function_exists('opencomune_get_partner_by_user_id')) {
        $partner = opencomune_get_partner_by_user_id($author_id);
        if ($partner && !empty($partner->email_ufficiale) && is_email($partner->email_ufficiale)) {
            return $partner->email_ufficiale;
        }
    }

    $user = get_userdata($author_id);

    return ($user && is_email($user->user_email)) ? $user->user_email : '';
}

function opencomune_finalize_checkout_session_booking_request($token) {
    $session = opencomune_get_checkout_session($token);
    if (!$session || $session['status'] !== 'open') {
        return new WP_Error('session_closed', __('Sessione checkout non valida o scaduta.', 'opencomune'), ['status' => 400]);
    }

    $payload = $session['payload'];
    $experience_id = (int) $session['experience_id'];
    if (!opencomune_experience_requires_booking_approval($experience_id)) {
        return new WP_Error('invalid_flow', __('Questa esperienza non richiede approvazione.', 'opencomune'), ['status' => 400]);
    }

    $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
    $event_id = (int) ($payload['event_id'] ?? 0);
    $participants = (int) ($payload['totals']['participants'] ?? 1);

    $nome = sanitize_text_field($contact['first_name'] ?? '');
    $cognome = sanitize_text_field($contact['last_name'] ?? '');
    $email = sanitize_email($contact['email'] ?? '');
    $telefono = sanitize_text_field($contact['phone'] ?? '');
    if ($telefono === '') {
        $phone_local = sanitize_text_field($contact['phone_local'] ?? '');
        if ($phone_local !== '') {
            $phone_prefix = sanitize_text_field($contact['phone_prefix'] ?? '+39');
            $telefono = trim($phone_prefix . ' ' . $phone_local);
        }
    }
    $note = sanitize_textarea_field($contact['notes'] ?? '');

    if ($nome === '' || $cognome === '' || $email === '' || $telefono === '') {
        return new WP_Error('contact_required', __('Compila i dati del contatto principale, incluso il telefono.', 'opencomune'), ['status' => 400]);
    }

    $slot = opencomune_resolve_booking_event($experience_id, $event_id, $participants);
    if (is_wp_error($slot)) {
        return $slot;
    }

    $requires_online = opencomune_experience_requires_online_payment($experience_id, (float) ($payload['totals']['total'] ?? 0));
    $metodo = $requires_online ? 'online' : 'guida';

    $booking_id = opencomune_insert_tour_prenotazione_row([
        'tour_id' => $experience_id,
        'event_id' => $event_id,
        'booking_date' => $slot['booking_date'],
        'booking_time' => $slot['booking_time'],
        'rule_id' => $slot['rule_id'],
        'nome' => trim($nome . ' ' . $cognome),
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'participants' => $participants,
        'metodo_pagamento' => $metodo,
        'status' => 'pending_approval',
    ]);

    if (!$booking_id) {
        return new WP_Error('booking_failed', __('Errore nel salvataggio della richiesta.', 'opencomune'), ['status' => 500]);
    }

    opencomune_insert_booking_log_entry(
        $booking_id,
        'request_submitted',
        __('Richiesta inviata dal checkout.', 'opencomune'),
        '',
        'pending_approval'
    );

    $expires = gmdate('Y-m-d H:i:s', time() + 30 * DAY_IN_SECONDS);
    $extend = opencomune_extend_checkout_session(
        $token,
        'pending_approval',
        $expires,
        array_merge($payload, ['booking_id' => $booking_id, 'step' => 'request_sent'])
    );
    if (is_wp_error($extend)) {
        return $extend;
    }

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $wpdb->update(
        $table,
        ['booking_id' => $booking_id],
        ['session_token' => sanitize_text_field($token)],
        ['%d'],
        ['%s']
    );

    opencomune_send_booking_request_submitted_emails($booking_id, $token);

    return [
        'booking_id' => $booking_id,
        'status' => 'pending_approval',
        'requires_payment' => false,
        'requires_approval' => true,
        'message' => __('Richiesta inviata. Riceverai una email quando il partner avrà risposto.', 'opencomune'),
    ];
}

function opencomune_approve_booking_request($booking_id) {
    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking) {
        return new WP_Error('not_found', __('Prenotazione non trovata.', 'opencomune'));
    }

    if ($booking->status !== 'pending_approval') {
        return new WP_Error('invalid_state', __('La richiesta non è in attesa di approvazione.', 'opencomune'));
    }

    if (!opencomune_user_can_manage_booking($booking_id)) {
        return new WP_Error('forbidden', __('Accesso negato.', 'opencomune'));
    }

    $check = opencomune_assert_booking_participants_available($booking, (int) $booking->participants, $booking_id);
    if (is_wp_error($check)) {
        return $check;
    }

    $experience_id = (int) $booking->tour_id;
    $checkout_total = opencomune_get_booking_checkout_total_estimate($booking_id);
    $requires_online = opencomune_experience_requires_online_payment($experience_id, $checkout_total);

    $session_row = opencomune_get_checkout_session_row_by_booking_id($booking_id);
    $checkout_token = $session_row ? (string) $session_row->session_token : '';

    global $wpdb;
    $table = $wpdb->prefix . 'tour_prenotazioni';
    $old_status = (string) $booking->status;

    if ($requires_online) {
        $updated = $wpdb->update(
            $table,
            ['status' => 'pending_payment'],
            ['id' => $booking_id],
            ['%s'],
            ['%d']
        );
        if ($updated === false) {
            return new WP_Error('db_error', __('Errore durante l\'approvazione.', 'opencomune'));
        }

        if ($checkout_token !== '') {
            $expires = gmdate('Y-m-d H:i:s', time() + 7 * DAY_IN_SECONDS);
            opencomune_extend_checkout_session($checkout_token, 'awaiting_payment', $expires, ['step' => 'payment']);
        }

        opencomune_insert_booking_log_entry(
            $booking_id,
            'request_approved',
            __('Richiesta approvata dal partner.', 'opencomune'),
            $old_status,
            'pending_payment'
        );

        opencomune_send_booking_request_approved_payment_email($booking_id, $checkout_token);

        return opencomune_get_booking_detail_payload($booking_id);
    }

    $updated = $wpdb->update(
        $table,
        ['status' => 'confermata'],
        ['id' => $booking_id],
        ['%s'],
        ['%d']
    );
    if ($updated === false) {
        return new WP_Error('db_error', __('Errore durante l\'approvazione.', 'opencomune'));
    }

    $saved = opencomune_get_booking_by_id($booking_id);
    if ($saved) {
        $event_id = (int) $saved->event_id;
        $participants = (int) $saved->participants;
        $slot = opencomune_resolve_booking_event($experience_id, $event_id, $participants);
        if (!is_wp_error($slot)) {
            opencomune_after_booking_decrement_legacy($experience_id, $event_id, $participants, $slot);
        }
        opencomune_reserve_booking_capacity($saved);
    }

    if ($checkout_token !== '') {
        opencomune_extend_checkout_session(
            $checkout_token,
            'completed',
            gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
            ['step' => 'completed']
        );
    }

    opencomune_insert_booking_log_entry(
        $booking_id,
        'request_approved',
        __('Richiesta approvata dal partner.', 'opencomune'),
        $old_status,
        'confermata'
    );

    opencomune_send_booking_request_approved_confirmed_email($booking_id);

    return opencomune_get_booking_detail_payload($booking_id);
}

function opencomune_reject_booking_request($booking_id, $reason) {
    $booking_id = (int) $booking_id;
    $reason = sanitize_textarea_field((string) $reason);
    if ($reason === '') {
        return new WP_Error('reason_required', __('Indica un motivo per il rifiuto.', 'opencomune'));
    }
    if (mb_strlen($reason) < 5) {
        return new WP_Error('reason_short', __('Descrivi il motivo con almeno 5 caratteri.', 'opencomune'));
    }

    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || $booking->status !== 'pending_approval') {
        return new WP_Error('invalid_state', __('La richiesta non è in attesa di approvazione.', 'opencomune'));
    }

    if (!opencomune_user_can_manage_booking($booking_id)) {
        return new WP_Error('forbidden', __('Accesso negato.', 'opencomune'));
    }

    $old_status = (string) $booking->status;
    $result = opencomune_update_booking_status($booking_id, 'cancellata');
    if (is_wp_error($result)) {
        return $result;
    }

    opencomune_insert_booking_log_entry(
        $booking_id,
        'request_rejected',
        $reason,
        $old_status,
        'cancellata'
    );

    $session_row = opencomune_get_checkout_session_row_by_booking_id($booking_id);
    if ($session_row && $session_row->session_token) {
        opencomune_cancel_checkout_session($session_row->session_token);
    }

    opencomune_send_booking_request_rejected_email($booking_id, $reason);

    return opencomune_get_booking_detail_payload($booking_id);
}

function opencomune_get_booking_checkout_total_estimate($booking_id) {
    $session_row = opencomune_get_checkout_session_row_by_booking_id($booking_id);
    if (!$session_row) {
        return 0.0;
    }

    $payload = json_decode($session_row->payload, true);
    if (!is_array($payload)) {
        return 0.0;
    }

    return (float) ($payload['totals']['total'] ?? 0);
}

function opencomune_respond_booking_request($booking_id, $decision, $reason = '') {
    $decision = sanitize_key((string) $decision);
    if ($decision === 'approve') {
        return opencomune_approve_booking_request($booking_id);
    }
    if ($decision === 'reject') {
        return opencomune_reject_booking_request($booking_id, $reason);
    }

    return new WP_Error('invalid_decision', __('Decisione non valida.', 'opencomune'));
}

add_action('wp_ajax_opencomune_respond_booking_request', 'opencomune_respond_booking_request_callback');
function opencomune_respond_booking_request_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    check_ajax_referer('opencomune_dashboard_bookings', 'nonce');

    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $decision = isset($_POST['decision']) ? sanitize_key(wp_unslash($_POST['decision'])) : '';
    $reason = isset($_POST['reason']) ? wp_unslash($_POST['reason']) : '';

    if ($id <= 0) {
        wp_send_json_error(['message' => __('ID prenotazione non valido', 'opencomune')]);
    }

    $result = opencomune_respond_booking_request($id, $decision, $reason);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    $message = $decision === 'approve'
        ? __('Richiesta approvata.', 'opencomune')
        : __('Richiesta rifiutata.', 'opencomune');

    wp_send_json_success([
        'message' => $message,
        'booking' => $result,
    ]);
}
