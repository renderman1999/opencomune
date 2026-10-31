<?php
/**
 * Gestione richieste itinerario (dashboard admin).
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return array<string, mixed>|null
 */
function opencomune_planner_get_trip_row_by_id($id) {
    global $wpdb;

    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }

    $row = $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_table_name() . ' WHERE id = %d', $id),
        ARRAY_A
    );

    return is_array($row) ? $row : null;
}

/**
 * @return array<string, mixed>|null
 */
function opencomune_planner_get_trip_by_id($id) {
    $row = opencomune_planner_get_trip_row_by_id($id);
    if ($row === null) {
        return null;
    }

    return opencomune_planner_trip_payload_from_row($row);
}

/**
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function opencomune_planner_trip_payload_from_row(array $row) {
    $days = json_decode((string) ($row['days_json'] ?? ''), true);
    if (!is_array($days)) {
        $days = [];
    }

    $days = opencomune_planner_enrich_itinerary_days_for_display(
        $days,
        (float) ($row['start_lat'] ?? 0),
        (float) ($row['start_lng'] ?? 0)
    );

    return [
        'id' => (int) ($row['id'] ?? 0),
        'token' => (string) ($row['token'] ?? ''),
        'startDate' => (string) ($row['start_date'] ?? ''),
        'endDate' => (string) ($row['end_date'] ?? ''),
        'startTime' => (string) ($row['start_time'] ?? ''),
        'startAddress' => (string) ($row['start_address'] ?? ''),
        'startLat' => (float) ($row['start_lat'] ?? 0),
        'startLng' => (float) ($row['start_lng'] ?? 0),
        'city' => (string) ($row['city_slug'] ?? ''),
        'travelerType' => (string) ($row['traveler_type'] ?? ''),
        'visitorOrigin' => (string) ($row['visitor_origin'] ?? ''),
        'categories' => array_filter(array_map('trim', explode(',', (string) ($row['categories'] ?? '')))),
        'days' => $days,
        'contactName' => $row['contact_name'] ?? '',
        'contactEmail' => $row['contact_email'] ?? '',
        'contactPhone' => $row['contact_phone'] ?? '',
        'contactNotes' => $row['contact_notes'] ?? '',
        'clientEmailMessage' => (string) ($row['client_email_message'] ?? ''),
        'emailSentAt' => $row['email_sent_at'] ?? null,
        'clientEmailSentAt' => $row['client_email_sent_at'] ?? null,
        'createdAt' => (string) ($row['created_at'] ?? ''),
        'updatedAt' => (string) ($row['updated_at'] ?? ''),
    ];
}

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_planner_admin_save_request() {
    global $wpdb;

    $id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    $row = opencomune_planner_get_trip_row_by_id($id);
    if ($row === null) {
        return ['error' => __('Richiesta non trovata.', 'opencomune')];
    }

    $name = sanitize_text_field(wp_unslash($_POST['contact_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['contact_email'] ?? ''));
    $phone = sanitize_text_field(wp_unslash($_POST['contact_phone'] ?? ''));
    $notes = sanitize_textarea_field(wp_unslash($_POST['contact_notes'] ?? ''));
    $client_message = sanitize_textarea_field(wp_unslash($_POST['client_email_message'] ?? ''));

    if (strlen($name) < 3) {
        return ['error' => __('Il nome deve avere almeno 3 caratteri.', 'opencomune')];
    }
    if (!is_email($email)) {
        return ['error' => __('Email non valida.', 'opencomune')];
    }

    $updated = $wpdb->update(
        opencomune_planner_table_name(),
        [
            'contact_name' => $name,
            'contact_email' => $email,
            'contact_phone' => $phone !== '' ? $phone : null,
            'contact_notes' => $notes !== '' ? $notes : null,
            'client_email_message' => $client_message !== '' ? $client_message : null,
        ],
        ['id' => $id],
        ['%s', '%s', '%s', '%s', '%s'],
        ['%d']
    );

    if ($updated === false) {
        return ['error' => __('Salvataggio non riuscito.', 'opencomune')];
    }

    return [
        'success' => __('Richiesta aggiornata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('request-edit', ['id' => $id]),
    ];
}

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_planner_admin_resend_request_to_client() {
    global $wpdb;

    $id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    $row = opencomune_planner_get_trip_row_by_id($id);
    if ($row === null) {
        return ['error' => __('Richiesta non trovata.', 'opencomune')];
    }

    $trip = opencomune_planner_trip_payload_from_row($row);
    $contact = opencomune_planner_contact_from_trip_payload($trip);
    if ($contact['email'] === '' || !is_email($contact['email'])) {
        return ['error' => __('Indica un\'email cliente valida prima di inviare.', 'opencomune')];
    }

    $custom_message = (string) ($trip['clientEmailMessage'] ?? '');
    if (!opencomune_planner_send_client_itinerary_email($trip, $contact, $custom_message)) {
        return ['error' => __('Invio email al cliente non riuscito.', 'opencomune')];
    }

    $wpdb->update(
        opencomune_planner_table_name(),
        ['client_email_sent_at' => current_time('mysql')],
        ['id' => $id],
        ['%s'],
        ['%d']
    );

    return [
        'success' => __('Email inviata al cliente.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('request-edit', ['id' => $id]),
    ];
}

/**
 * @return array{success?: string, error?: string, redirect?: string}
 */
function opencomune_planner_admin_delete_request() {
    global $wpdb;

    $id = isset($_POST['request_id']) ? (int) $_POST['request_id'] : 0;
    if ($id <= 0 || opencomune_planner_get_trip_row_by_id($id) === null) {
        return ['error' => __('Richiesta non trovata.', 'opencomune')];
    }

    $deleted = $wpdb->delete(opencomune_planner_table_name(), ['id' => $id], ['%d']);
    if ($deleted === false) {
        return ['error' => __('Eliminazione non riuscita.', 'opencomune')];
    }

    return [
        'success' => __('Richiesta eliminata.', 'opencomune'),
        'redirect' => opencomune_planner_admin_base_url('requests'),
    ];
}

/**
 * @param array<string, mixed> $trip
 * @return array{name: string, email: string, phone: string, notes: string}
 */
function opencomune_planner_contact_from_trip_payload(array $trip) {
    return [
        'name' => sanitize_text_field((string) ($trip['contactName'] ?? '')),
        'email' => sanitize_email((string) ($trip['contactEmail'] ?? '')),
        'phone' => sanitize_text_field((string) ($trip['contactPhone'] ?? '')),
        'notes' => sanitize_textarea_field((string) ($trip['contactNotes'] ?? '')),
    ];
}
