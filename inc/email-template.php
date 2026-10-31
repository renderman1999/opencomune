<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_html_email_font_stack() {
    return "'Titillium Web', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
}

function opencomune_get_html_email_headers() {
    return ['Content-Type: text/html; charset=UTF-8'];
}

/**
 * @param array{preheader?:string,title?:string,cta_url?:string,cta_label?:string} $args
 */
function opencomune_wrap_html_email($title, $body_html, $args = []) {
    $site_name = get_bloginfo('name');
    $site_url = home_url('/');
    $year = gmdate('Y');
    $preheader = isset($args['preheader']) ? (string) $args['preheader'] : '';
    $font = opencomune_get_html_email_font_stack();
    $title_esc = esc_html($title);
    $body_html = (string) $body_html;

    $cta_block = '';
    if (!empty($args['cta_url']) && !empty($args['cta_label'])) {
        $cta_block = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:28px auto 0;">'
            . '<tr><td align="center" style="border-radius:8px;background-color:#1d4ed8;">'
            . '<a href="' . esc_url($args['cta_url']) . '" style="display:inline-block;padding:12px 22px;font-family:' . esc_attr($font) . ';font-size:15px;font-weight:600;color:#ffffff;text-decoration:none;">'
            . esc_html($args['cta_label'])
            . '</a></td></tr></table>';
    }

    $preheader_html = $preheader !== ''
        ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">' . esc_html($preheader) . '</div>'
        : '';

    return '<!DOCTYPE html><html lang="it"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $title_esc . '</title>'
        . '<link href="https://fonts.googleapis.com/css2?family=Titillium+Web:wght@400;600;700&amp;display=swap" rel="stylesheet">'
        . '</head><body style="margin:0;padding:0;background-color:#f3f4f6;">'
        . $preheader_html
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f3f4f6;padding:24px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">'
        . '<tr><td style="background-color:#1e3a8a;border-radius:12px 12px 0 0;padding:22px 28px;text-align:center;">'
        . '<a href="' . esc_url($site_url) . '" style="font-family:' . esc_attr($font) . ';font-size:22px;font-weight:700;color:#ffffff;text-decoration:none;letter-spacing:0.02em;">'
        . esc_html($site_name)
        . '</a></td></tr>'
        . '<tr><td style="background-color:#ffffff;padding:32px 28px 28px;font-family:' . esc_attr($font) . ';font-size:16px;line-height:1.6;color:#374151;">'
        . '<h1 style="margin:0 0 18px;font-family:' . esc_attr($font) . ';font-size:22px;line-height:1.3;font-weight:700;color:#111827;">' . $title_esc . '</h1>'
        . $body_html
        . $cta_block
        . '</td></tr>'
        . '<tr><td style="background-color:#e5e7eb;border-radius:0 0 12px 12px;padding:18px 28px;text-align:center;font-family:' . esc_attr($font) . ';font-size:13px;line-height:1.5;color:#6b7280;">'
        . '<p style="margin:0 0 6px;">&copy; ' . esc_html($year) . ' ' . esc_html($site_name) . '</p>'
        . '<p style="margin:0;"><a href="' . esc_url($site_url) . '" style="color:#1d4ed8;text-decoration:none;">' . esc_html($site_url) . '</a></p>'
        . '</td></tr></table></td></tr></table></body></html>';
}

function opencomune_html_email_paragraph($text) {
    return '<p style="margin:0 0 14px;font-family:' . esc_attr(opencomune_get_html_email_font_stack()) . ';font-size:16px;line-height:1.6;color:#374151;">'
        . wp_kses_post($text)
        . '</p>';
}

function opencomune_html_email_details_table(array $rows) {
    $font = esc_attr(opencomune_get_html_email_font_stack());
    $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:18px 0 8px;border-collapse:collapse;background-color:#f9fafb;border-radius:8px;overflow:hidden;">';
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['label'])) {
            continue;
        }
        $label = esc_html((string) $row['label']);
        $value = nl2br(esc_html((string) ($row['value'] ?? '')), false);
        $html .= '<tr>'
            . '<td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-family:' . $font . ';font-size:14px;font-weight:600;color:#4b5563;width:38%;vertical-align:top;">' . $label . '</td>'
            . '<td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-family:' . $font . ';font-size:14px;color:#111827;vertical-align:top;">' . $value . '</td>'
            . '</tr>';
    }
    $html .= '</table>';

    return $html;
}

function opencomune_html_email_section_title($text) {
    return '<h2 style="margin:22px 0 10px;font-family:' . esc_attr(opencomune_get_html_email_font_stack()) . ';font-size:17px;line-height:1.35;font-weight:700;color:#111827;">'
        . esc_html($text)
        . '</h2>';
}

function opencomune_html_email_bullet_list(array $items) {
    if ($items === []) {
        return '';
    }
    $font = esc_attr(opencomune_get_html_email_font_stack());
    $html = '<ul style="margin:0 0 14px 18px;padding:0;font-family:' . $font . ';font-size:15px;line-height:1.55;color:#374151;">';
    foreach ($items as $item) {
        $html .= '<li style="margin:0 0 6px;">' . esc_html((string) $item) . '</li>';
    }
    $html .= '</ul>';

    return $html;
}

function opencomune_send_html_email($to, $subject, $body_html, $args = []) {
    $title = isset($args['title']) ? (string) $args['title'] : $subject;
    $html = opencomune_wrap_html_email($title, $body_html, $args);
    $attachments = [];
    if (!empty($args['attachments']) && is_array($args['attachments'])) {
        foreach ($args['attachments'] as $file) {
            if (is_string($file) && $file !== '' && is_readable($file)) {
                $attachments[] = $file;
            }
        }
    }

    $headers = opencomune_get_html_email_headers();
    if (!empty($args['reply_to']) && is_email($args['reply_to'])) {
        $reply = sanitize_email($args['reply_to']);
        $reply_name = isset($args['reply_to_name']) ? sanitize_text_field((string) $args['reply_to_name']) : '';
        if ($reply_name !== '') {
            $headers[] = 'Reply-To: ' . sprintf('%s <%s>', $reply_name, $reply);
        } else {
            $headers[] = 'Reply-To: ' . $reply;
        }
    }

    $sent = wp_mail($to, $subject, $html, $headers, $attachments);

    foreach ($attachments as $file) {
        if (is_string($file) && is_file($file)) {
            @unlink($file);
        }
    }

    return $sent;
}

function opencomune_invia_email_conferma_pagamento($ordine) {
    if (!$ordine || empty($ordine->email)) {
        return false;
    }

    $tour_title = get_the_title((int) $ordine->tour_id);
    $tour_link = get_permalink((int) $ordine->tour_id);
    $subject = sprintf(__('Prenotazione confermata - %s', 'opencomune'), $tour_title);

    $details = function_exists('opencomune_build_payment_confirmation_email_details')
        ? opencomune_build_payment_confirmation_email_details($ordine)
        : [
            'experience_rows' => [],
            'participant_lines' => [],
            'payment_rows' => [],
        ];

    $body = opencomune_html_email_paragraph(
        sprintf(
            /* translators: %s: customer name */
            __('Gentile %s,', 'opencomune'),
            esc_html($ordine->nome)
        )
    );
    $body .= opencomune_html_email_paragraph(__('La tua prenotazione e il pagamento sono stati registrati con successo.', 'opencomune'));

    if (!empty($details['experience_rows'])) {
        $body .= opencomune_html_email_section_title(__('Dettagli esperienza', 'opencomune'));
        $body .= opencomune_html_email_details_table($details['experience_rows']);
    }

    if (!empty($details['participant_lines'])) {
        $body .= opencomune_html_email_section_title(__('Partecipanti', 'opencomune'));
        $body .= opencomune_html_email_bullet_list($details['participant_lines']);
    }

    if (!empty($details['payment_rows'])) {
        $body .= opencomune_html_email_section_title(__('Pagamento', 'opencomune'));
        $body .= opencomune_html_email_details_table($details['payment_rows']);
    }

    $booking_id = (int) ($ordine->booking_id ?? 0);
    if ($booking_id > 0 && function_exists('opencomune_build_booking_calendar_event')) {
        $calendar_event = opencomune_build_booking_calendar_event($booking_id, $ordine);
        if (is_array($calendar_event)) {
            $body .= opencomune_html_email_calendar_block($calendar_event);
        }
    }

    $body .= opencomune_html_email_paragraph(__('In allegato trovi il biglietto in PDF e l’invito calendario (.ics).', 'opencomune'));
    $body .= opencomune_html_email_paragraph(__('Grazie per aver scelto i nostri servizi.', 'opencomune'));

    $attachments = [];
    if ($booking_id > 0 && function_exists('opencomune_get_booking_confirmation_email_attachments')) {
        $attachments = opencomune_get_booking_confirmation_email_attachments($booking_id, $ordine);
    }

    return opencomune_send_html_email($ordine->email, $subject, $body, [
        'title' => __('Pagamento confermato', 'opencomune'),
        'preheader' => __('La tua prenotazione è confermata.', 'opencomune'),
        'cta_url' => $tour_link ?: home_url('/'),
        'cta_label' => __('Vedi esperienza', 'opencomune'),
        'attachments' => $attachments,
    ]);
}

function opencomune_build_prenotazione_client_email_html($nome, $cognome, $tour_title, $data, $orario, $prenotazione_id, $metodo_text) {
    $body = opencomune_html_email_paragraph(sprintf(__('Gentile %s %s,', 'opencomune'), esc_html($nome), esc_html($cognome)));
    $body .= opencomune_html_email_paragraph(
        sprintf(__('La tua prenotazione per «%s» è stata confermata.', 'opencomune'), esc_html($tour_title))
    );
    $body .= opencomune_html_email_details_table([
        ['label' => __('Data', 'opencomune'), 'value' => date_i18n('d/m/Y', strtotime($data))],
        ['label' => __('Orario', 'opencomune'), 'value' => $orario],
        ['label' => __('N. prenotazione', 'opencomune'), 'value' => '#' . (int) $prenotazione_id],
        ['label' => __('Pagamento', 'opencomune'), 'value' => $metodo_text],
    ]);
    $body .= opencomune_html_email_paragraph(__('Grazie per aver scelto i nostri servizi.', 'opencomune'));

    return $body;
}

function opencomune_build_prenotazione_guida_email_html($nome, $cognome, $tour_title, $data, $orario, $prenotazione_id, $email, $telefono, $metodo_text, $note = '') {
    $body = opencomune_html_email_paragraph(__('Hai ricevuto una nuova prenotazione.', 'opencomune'));
    $rows = [
        ['label' => __('Esperienza', 'opencomune'), 'value' => $tour_title],
        ['label' => __('Cliente', 'opencomune'), 'value' => trim($nome . ' ' . $cognome)],
        ['label' => __('Email', 'opencomune'), 'value' => $email],
        ['label' => __('Telefono', 'opencomune'), 'value' => $telefono],
        ['label' => __('Data', 'opencomune'), 'value' => date_i18n('d/m/Y', strtotime($data))],
        ['label' => __('Orario', 'opencomune'), 'value' => $orario],
        ['label' => __('N. prenotazione', 'opencomune'), 'value' => '#' . (int) $prenotazione_id],
        ['label' => __('Pagamento', 'opencomune'), 'value' => $metodo_text],
    ];
    if ($note !== '') {
        $rows[] = ['label' => __('Note', 'opencomune'), 'value' => $note];
    }
    $body .= opencomune_html_email_details_table($rows);

    return $body;
}

function opencomune_booking_request_email_details_rows($booking_id) {
    $booking = opencomune_get_booking_by_id((int) $booking_id);
    if (!$booking) {
        return [];
    }

    $tour_title = get_the_title((int) $booking->tour_id);
    $date = $booking->booking_date ? date_i18n('d/m/Y', strtotime($booking->booking_date)) : '';
    $time = $booking->booking_time ? substr((string) $booking->booking_time, 0, 5) : '';

    return [
        ['label' => __('Esperienza', 'opencomune'), 'value' => $tour_title],
        ['label' => __('Cliente', 'opencomune'), 'value' => (string) $booking->nome],
        ['label' => __('Email', 'opencomune'), 'value' => (string) $booking->email],
        ['label' => __('Telefono', 'opencomune'), 'value' => (string) $booking->telefono],
        ['label' => __('Data', 'opencomune'), 'value' => $date],
        ['label' => __('Orario', 'opencomune'), 'value' => $time],
        ['label' => __('Partecipanti', 'opencomune'), 'value' => (string) (int) $booking->participants],
        ['label' => __('N. prenotazione', 'opencomune'), 'value' => '#' . (int) $booking->id],
    ];
}

function opencomune_send_booking_request_submitted_emails($booking_id, $checkout_token = '') {
    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || !is_email($booking->email)) {
        return false;
    }

    $tour_title = get_the_title((int) $booking->tour_id);
    $rows = opencomune_booking_request_email_details_rows($booking_id);

    $client_body = opencomune_html_email_paragraph(
        sprintf(__('Gentile %s,', 'opencomune'), esc_html($booking->nome))
    );
    $client_body .= opencomune_html_email_paragraph(
        sprintf(
            __('Abbiamo inviato la tua richiesta di prenotazione per «%s». Il partner la valuterà e ti avviseremo via email.', 'opencomune'),
            esc_html($tour_title)
        )
    );
    $client_body .= opencomune_html_email_details_table($rows);

    opencomune_send_html_email($booking->email, sprintf(__('Richiesta inviata - %s', 'opencomune'), $tour_title), $client_body, [
        'title' => __('Richiesta inviata', 'opencomune'),
        'preheader' => __('In attesa di approvazione del partner.', 'opencomune'),
        'cta_url' => get_permalink((int) $booking->tour_id),
        'cta_label' => __('Vedi esperienza', 'opencomune'),
    ]);

    $partner_email = opencomune_get_experience_partner_notification_email((int) $booking->tour_id);
    if ($partner_email === '') {
        return true;
    }

    $detail_url = opencomune_get_partner_booking_detail_url($booking_id);
    $partner_body = opencomune_html_email_paragraph(__('Hai ricevuto una nuova richiesta di prenotazione da approvare o rifiutare.', 'opencomune'));
    $partner_body .= opencomune_html_email_details_table($rows);
    if ($booking->note !== '') {
        $partner_body .= opencomune_html_email_section_title(__('Note del cliente', 'opencomune'));
        $partner_body .= opencomune_html_email_paragraph(nl2br(esc_html($booking->note)));
    }

    opencomune_send_html_email($partner_email, sprintf(__('Richiesta prenotazione - %s', 'opencomune'), $tour_title), $partner_body, [
        'title' => __('Nuova richiesta', 'opencomune'),
        'preheader' => __('Approva o rifiuta dalla dashboard.', 'opencomune'),
        'cta_url' => $detail_url,
        'cta_label' => __('Gestisci richiesta', 'opencomune'),
    ]);

    return true;
}

function opencomune_send_booking_request_approved_payment_email($booking_id, $checkout_token) {
    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || !is_email($booking->email)) {
        return false;
    }

    $checkout_token = sanitize_text_field((string) $checkout_token);
    $checkout_url = $checkout_token !== ''
        ? opencomune_get_checkout_session_url($checkout_token)
        : '';

    $tour_title = get_the_title((int) $booking->tour_id);
    $rows = opencomune_booking_request_email_details_rows($booking_id);

    $body = opencomune_html_email_paragraph(
        sprintf(__('Gentile %s,', 'opencomune'), esc_html($booking->nome))
    );
    $body .= opencomune_html_email_paragraph(
        sprintf(
            __('La tua richiesta per «%s» è stata approvata. Completa il pagamento per confermare la prenotazione.', 'opencomune'),
            esc_html($tour_title)
        )
    );
    $body .= opencomune_html_email_details_table($rows);

    $args = [
        'title' => __('Richiesta approvata', 'opencomune'),
        'preheader' => __('Procedi al pagamento.', 'opencomune'),
    ];
    if ($checkout_url !== '') {
        $args['cta_url'] = $checkout_url;
        $args['cta_label'] = __('Paga ora', 'opencomune');
    }

    return opencomune_send_html_email(
        $booking->email,
        sprintf(__('Richiesta approvata - %s', 'opencomune'), $tour_title),
        $body,
        $args
    );
}

function opencomune_send_booking_request_approved_confirmed_email($booking_id) {
    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || !is_email($booking->email)) {
        return false;
    }

    $tour_title = get_the_title((int) $booking->tour_id);
    $date = $booking->booking_date ? date_i18n('d/m/Y', strtotime($booking->booking_date)) : '';
    $time = $booking->booking_time ? substr((string) $booking->booking_time, 0, 5) : '';

    $parts = preg_split('/\s+/', trim((string) $booking->nome), 2);
    $nome = $parts[0] ?? '';
    $cognome = $parts[1] ?? '';

    $metodo_text = __('Confermata dal partner', 'opencomune');
    $subject = sprintf(__('Conferma prenotazione - %s', 'opencomune'), $tour_title);
    $client_body = opencomune_build_prenotazione_client_email_html($nome, $cognome, $tour_title, $date, $time, $booking_id, $metodo_text);

    return opencomune_send_html_email($booking->email, $subject, $client_body, [
        'title' => __('Prenotazione confermata', 'opencomune'),
        'preheader' => __('La richiesta è stata approvata.', 'opencomune'),
        'cta_url' => get_permalink((int) $booking->tour_id),
        'cta_label' => __('Vedi esperienza', 'opencomune'),
    ]);
}

function opencomune_send_booking_request_rejected_email($booking_id, $reason) {
    $booking_id = (int) $booking_id;
    $booking = opencomune_get_booking_by_id($booking_id);
    if (!$booking || !is_email($booking->email)) {
        return false;
    }

    $tour_title = get_the_title((int) $booking->tour_id);
    $rows = opencomune_booking_request_email_details_rows($booking_id);

    $body = opencomune_html_email_paragraph(
        sprintf(__('Gentile %s,', 'opencomune'), esc_html($booking->nome))
    );
    $body .= opencomune_html_email_paragraph(
        sprintf(
            __('Il partner non ha potuto accettare la tua richiesta per «%s».', 'opencomune'),
            esc_html($tour_title)
        )
    );
    if ($reason !== '') {
        $body .= opencomune_html_email_section_title(__('Motivo', 'opencomune'));
        $body .= opencomune_html_email_paragraph(nl2br(esc_html($reason)));
    }
    $body .= opencomune_html_email_details_table($rows);

    return opencomune_send_html_email(
        $booking->email,
        sprintf(__('Richiesta non accettata - %s', 'opencomune'), $tour_title),
        $body,
        [
            'title' => __('Richiesta rifiutata', 'opencomune'),
            'preheader' => __('Puoi cercare un\'altra data o esperienza.', 'opencomune'),
            'cta_url' => get_permalink((int) $booking->tour_id),
            'cta_label' => __('Vedi esperienza', 'opencomune'),
        ]
    );
}
