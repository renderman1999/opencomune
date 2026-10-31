<?php
/**
 * Email richieste pianificatore.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $trip
 */
function opencomune_planner_format_days_text(array $trip) {
    $lines = [];
    foreach ($trip['days'] as $day) {
        $lines[] = $day['date'] ?? '';
        $stops = $day['stops'] ?? [];
        if ($stops === []) {
            $lines[] = '  (nessuna tappa)';
            continue;
        }
        foreach ($stops as $i => $stop) {
            $lines[] = sprintf(
                '  %d. %s-%s · %s (%d min)',
                $i + 1,
                $stop['arrive'] ?? '',
                $stop['leave'] ?? '',
                $stop['name'] ?? '',
                (int) ($stop['durationMin'] ?? 0)
            );
        }
        $lines[] = '';
    }
    return trim(implode("\n", $lines));
}

/**
 * @param array<string, mixed> $trip
 */
function opencomune_planner_format_days_html(array $trip) {
    if (!function_exists('opencomune_html_email_section_title')) {
        return '<pre>' . esc_html(opencomune_planner_format_days_text($trip)) . '</pre>';
    }

    $html = '';
    foreach ($trip['days'] as $day) {
        if (!is_array($day)) {
            continue;
        }
        $date = (string) ($day['date'] ?? '');
        if ($date !== '') {
            $html .= opencomune_html_email_section_title($date);
        }
        $stops = is_array($day['stops'] ?? null) ? $day['stops'] : [];
        if ($stops === []) {
            $html .= opencomune_html_email_paragraph(__('Nessuna tappa programmata.', 'opencomune'));
            continue;
        }
        $items = [];
        foreach ($stops as $i => $stop) {
            if (!is_array($stop)) {
                continue;
            }
            $items[] = sprintf(
                '%d. %s–%s · %s (%d min)',
                $i + 1,
                (string) ($stop['arrive'] ?? ''),
                (string) ($stop['leave'] ?? ''),
                (string) ($stop['name'] ?? ''),
                (int) ($stop['durationMin'] ?? 0)
            );
        }
        $html .= opencomune_html_email_bullet_list($items);
    }

    return $html;
}

function opencomune_planner_admin_notify_email_recipient() {
    $to = get_option('opencomune_planner_notify_email', '');
    if ($to === '' || !is_email($to)) {
        $to = get_option('opencomune_email_ufficio', '');
    }
    if ($to === '' || !is_email($to)) {
        $to = get_option('admin_email');
    }

    return is_email($to) ? $to : '';
}

/**
 * @param array<string, mixed> $trip
 */
function opencomune_planner_itinerary_public_url(array $trip) {
    return add_query_arg('token', $trip['token'], opencomune_planner_itinerary_page_url());
}

/**
 * @param array<string, mixed> $trip
 */
function opencomune_planner_admin_request_manage_url(array $trip) {
    $id = (int) ($trip['id'] ?? 0);
    if ($id > 0 && function_exists('opencomune_planner_admin_base_url')) {
        return opencomune_planner_admin_base_url('request-edit', ['id' => $id]);
    }

    return opencomune_planner_itinerary_public_url($trip);
}

/**
 * @param array<string, mixed> $trip
 * @param array{name: string, email: string, phone: string, notes?: string} $contact
 */
function opencomune_planner_send_admin_request_email(array $trip, array $contact) {
    $to = opencomune_planner_admin_notify_email_recipient();
    if ($to === '' || !function_exists('opencomune_send_html_email')) {
        return false;
    }

    $subject = sprintf(
        /* translators: 1: name, 2: start date, 3: end date */
        __('Richiesta itinerario – %1$s (%2$s → %3$s)', 'opencomune'),
        $contact['name'],
        $trip['startDate'],
        $trip['endDate']
    );

    $itinerary_url = opencomune_planner_itinerary_public_url($trip);
    $manage_url = opencomune_planner_admin_request_manage_url($trip);

    $rows = [
        ['label' => __('Nome', 'opencomune'), 'value' => $contact['name']],
        ['label' => __('Email', 'opencomune'), 'value' => $contact['email']],
        [
            'label' => __('Telefono', 'opencomune'),
            'value' => $contact['phone'] !== '' ? $contact['phone'] : '—',
        ],
        [
            'label' => __('Date', 'opencomune'),
            'value' => $trip['startDate'] . ' → ' . $trip['endDate'],
        ],
        [
            'label' => __('Partenza', 'opencomune'),
            'value' => $trip['startTime'] . ' – ' . $trip['startAddress'],
        ],
        ['label' => __('Token', 'opencomune'), 'value' => (string) ($trip['token'] ?? '')],
        ['label' => __('Link itinerario', 'opencomune'), 'value' => $itinerary_url],
    ];

    $body = opencomune_html_email_paragraph(__('Nuova richiesta dal pianificatore itinerario.', 'opencomune'));
    $body .= opencomune_html_email_details_table($rows);
    $body .= opencomune_html_email_section_title(__('Itinerario proposto', 'opencomune'));
    $body .= opencomune_planner_format_days_html($trip);

    if (!empty($contact['notes'])) {
        $body .= opencomune_html_email_section_title(__('Note visitatore', 'opencomune'));
        $body .= opencomune_html_email_paragraph(nl2br(esc_html((string) $contact['notes']), false));
    }

    $args = [
        'title' => __('Nuova richiesta itinerario', 'opencomune'),
        'preheader' => sprintf(
            /* translators: %s: visitor name */
            __('Richiesta da %s', 'opencomune'),
            $contact['name']
        ),
        'cta_url' => $manage_url,
        'cta_label' => __('Gestisci richiesta', 'opencomune'),
    ];

    if (is_email($contact['email'])) {
        $args['reply_to'] = $contact['email'];
        $args['reply_to_name'] = $contact['name'];
    }

    return opencomune_send_html_email($to, $subject, $body, $args);
}

/**
 * @param array<string, mixed> $trip
 * @param array{name: string, email: string, phone: string, notes?: string} $contact
 */
function opencomune_planner_send_client_itinerary_email(array $trip, array $contact, $custom_message = '') {
    if (!is_email($contact['email']) || !function_exists('opencomune_send_html_email')) {
        return false;
    }

    $itinerary_url = opencomune_planner_itinerary_public_url($trip);
    $site_name = get_bloginfo('name');

    $subject = sprintf(
        /* translators: 1: site name, 2: start date */
        __('Il tuo itinerario su %1$s – %2$s', 'opencomune'),
        $site_name,
        $trip['startDate']
    );

    $body = opencomune_html_email_paragraph(
        sprintf(
            /* translators: %s: customer name */
            __('Ciao %s,', 'opencomune'),
            esc_html($contact['name'])
        )
    );
    $body .= opencomune_html_email_paragraph(
        __('Ecco la proposta di itinerario personalizzata che hai richiesto.', 'opencomune')
    );
    $body .= opencomune_html_email_details_table([
        [
            'label' => __('Date del viaggio', 'opencomune'),
            'value' => $trip['startDate'] . ' → ' . $trip['endDate'],
        ],
        [
            'label' => __('Partenza', 'opencomune'),
            'value' => $trip['startTime'] . ' – ' . $trip['startAddress'],
        ],
    ]);
    $body .= opencomune_html_email_section_title(__('Programma', 'opencomune'));
    $body .= opencomune_planner_format_days_html($trip);

    $custom_message = trim((string) $custom_message);
    if ($custom_message !== '') {
        $body .= opencomune_html_email_section_title(__('Messaggio dal team', 'opencomune'));
        $body .= opencomune_html_email_paragraph(nl2br(esc_html($custom_message), false));
    }

    $body .= opencomune_html_email_paragraph(
        sprintf(__('A presto,<br>%s', 'opencomune'), esc_html($site_name))
    );

    return opencomune_send_html_email($contact['email'], $subject, $body, [
        'title' => __('Il tuo itinerario', 'opencomune'),
        'preheader' => __('Apri il percorso online e consulta il programma.', 'opencomune'),
        'cta_url' => $itinerary_url,
        'cta_label' => __('Apri il tuo percorso', 'opencomune'),
    ]);
}

/**
 * @param array<string, mixed> $trip
 * @param array{name: string, email: string, phone: string, notes?: string} $contact
 */
function opencomune_planner_send_planner_contact_emails(array $trip, array $contact, $custom_message = '') {
    $admin_ok = opencomune_planner_send_admin_request_email($trip, $contact);
    $client_ok = opencomune_planner_send_client_itinerary_email($trip, $contact, $custom_message);

    return $admin_ok && $client_ok;
}

/**
 * @return array<string, mixed>|WP_Error
 */
function opencomune_planner_submit_contact(string $token, array $contact_input) {
    $trip = opencomune_planner_get_trip_by_token($token);
    if ($trip === null) {
        return new WP_Error('not_found', __('Itinerario non trovato.', 'opencomune'), ['status' => 404]);
    }

    if (!empty($trip['emailSentAt'])) {
        return new WP_Error('already_sent', __('Hai già inviato una richiesta per questo itinerario.', 'opencomune'), ['status' => 409]);
    }

    if (function_exists('opencomune_verify_turnstile_response')) {
        $turnstile_token = sanitize_text_field($contact_input['turnstileToken'] ?? '');
        $turnstile_check = opencomune_verify_turnstile_response($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            return new WP_Error(
                $turnstile_check->get_error_code(),
                $turnstile_check->get_error_message(),
                ['status' => 400]
            );
        }
    }

    $name = sanitize_text_field($contact_input['name'] ?? '');
    $email = sanitize_email($contact_input['email'] ?? '');
    $phone = sanitize_text_field($contact_input['phone'] ?? '');
    $notes = sanitize_textarea_field($contact_input['notes'] ?? '');

    if (strlen($name) < 3) {
        return new WP_Error('invalid_name', __('Il nome deve avere almeno 3 caratteri.', 'opencomune'), ['status' => 400]);
    }
    if (!is_email($email)) {
        return new WP_Error('invalid_email', __('Email non valida.', 'opencomune'), ['status' => 400]);
    }
    if ($phone !== '' && strlen($phone) < 5) {
        return new WP_Error('invalid_phone', __('Telefono non valido.', 'opencomune'), ['status' => 400]);
    }
    if (empty($contact_input['acceptTerms'])) {
        return new WP_Error('terms_required', __('Devi accettare i termini e condizioni.', 'opencomune'), ['status' => 400]);
    }

    $contact = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'notes' => $notes,
    ];

    $custom_message = (string) ($trip['clientEmailMessage'] ?? '');

    global $wpdb;
    $table = opencomune_planner_table_name();
    $now = current_time('mysql');
    $phone_db = $phone !== '' ? $phone : null;
    $notes_db = $notes !== '' ? $notes : null;

    // Claim invio in modo atomico (evita doppie email su doppio click / richieste parallele).
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$table} SET contact_name = %s, contact_email = %s, contact_phone = %s, contact_notes = %s, email_sent_at = %s, client_email_sent_at = %s WHERE token = %s AND email_sent_at IS NULL",
            $name,
            $email,
            $phone_db,
            $notes_db,
            $now,
            $now,
            $token
        )
    );

    if ((int) $wpdb->rows_affected < 1) {
        return new WP_Error('already_sent', __('Hai già inviato una richiesta per questo itinerario.', 'opencomune'), ['status' => 409]);
    }

    $trip_for_mail = array_merge($trip, [
        'contactName' => $name,
        'contactEmail' => $email,
        'contactPhone' => $phone,
        'contactNotes' => $notes,
    ]);

    if (!opencomune_planner_send_planner_contact_emails($trip_for_mail, $contact, $custom_message)) {
        $wpdb->update(
            $table,
            [
                'email_sent_at' => null,
                'client_email_sent_at' => null,
            ],
            ['token' => $token],
            ['%s', '%s'],
            ['%s']
        );

        return new WP_Error('mail_failed', __('Invio email non riuscito. Riprova più tardi.', 'opencomune'), ['status' => 500]);
    }

    return [
        'ok' => true,
        'emailSentAt' => $now,
        'contactName' => $name,
    ];
}

/** @deprecated Use opencomune_planner_send_admin_request_email() */
function opencomune_planner_send_request_email(array $trip, array $contact) {
    return opencomune_planner_send_admin_request_email($trip, $contact);
}
