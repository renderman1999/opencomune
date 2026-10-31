<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param object|null $ordine
 * @return array<string, mixed>|null
 */
function opencomune_build_booking_calendar_event($booking_id, $ordine = null) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0 || !function_exists('opencomune_get_tour_booking_by_id')) {
        return null;
    }

    $booking = opencomune_get_tour_booking_by_id($booking_id);
    if (!$booking) {
        return null;
    }

    $tour_id = (int) ($booking->tour_id ?? 0);
    if ($tour_id <= 0) {
        return null;
    }

    $date_ymd = sanitize_text_field((string) ($booking->booking_date ?? ''));
    $time_hm = sanitize_text_field((string) ($booking->booking_time ?? ''));
    if ($time_hm !== '' && function_exists('opencomune_format_booking_time_for_display')) {
        $time_hm = opencomune_format_booking_time_for_display($time_hm);
    }

    if ($ordine && !empty($ordine->checkout_token) && function_exists('opencomune_get_checkout_session_payload_by_token')) {
        $payload = opencomune_get_checkout_session_payload_by_token($ordine->checkout_token);
        if (is_array($payload)) {
            if ($date_ymd === '' && !empty($payload['date'])) {
                $date_ymd = sanitize_text_field((string) $payload['date']);
            }
            if ($time_hm === '' && !empty($payload['time'])) {
                $time_hm = sanitize_text_field((string) $payload['time']);
            }
        }
    }

    if ($date_ymd === '' || $time_hm === '') {
        return null;
    }

    $tz = wp_timezone();
    try {
        $start = new DateTimeImmutable($date_ymd . ' ' . $time_hm, $tz);
    } catch (Exception $e) {
        return null;
    }

    $duration_minutes = 60;
    if (function_exists('opencomune_get_tour_duration_parts') && function_exists('opencomune_tour_duration_parts_total_minutes')) {
        $total = opencomune_tour_duration_parts_total_minutes(opencomune_get_tour_duration_parts($tour_id));
        if ($total > 0) {
            $duration_minutes = $total;
        }
    }

    $end = $start->modify('+' . (int) $duration_minutes . ' minutes');

    $title = get_the_title($tour_id);
    if ($title === '') {
        $title = __('Esperienza', 'opencomune');
    }

    $location = opencomune_get_booking_calendar_location($tour_id);
    $permalink = get_permalink($tour_id);
    $description_lines = [
        sprintf(__('Prenotazione #%d', 'opencomune'), $booking_id),
    ];
    if ($permalink) {
        $description_lines[] = $permalink;
    }
    $customer = trim((string) ($booking->nome ?? ''));
    if ($customer !== '') {
        $description_lines[] = sprintf(__('Cliente: %s', 'opencomune'), $customer);
    }

    $host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    if (!$host) {
        $host = 'opencomune.local';
    }

    return [
        'uid' => 'booking-' . $booking_id . '@' . $host,
        'title' => $title,
        'description' => implode("\n", $description_lines),
        'location' => $location,
        'url' => $permalink ?: home_url('/'),
        'start' => $start,
        'end' => $end,
        'timezone' => $tz->getName(),
    ];
}

function opencomune_get_booking_calendar_location($tour_id) {
    $tour_id = (int) $tour_id;
    if ($tour_id <= 0) {
        return '';
    }

    if (function_exists('opencomune_get_esperienza_meeting_points')) {
        $points = opencomune_get_esperienza_meeting_points($tour_id);
        if (!empty($points) && is_array($points[0])) {
            $point = $points[0];
            $chunk = trim((string) ($point['title'] ?? ''));
            $address = trim((string) ($point['address'] ?? ''));
            if ($address !== '') {
                return $chunk !== '' ? ($chunk . ' — ' . $address) : $address;
            }
            if ($chunk !== '') {
                return $chunk;
            }
        }
    }

    $address = trim((string) get_post_meta($tour_id, 'tour_address', true));
    if ($address !== '') {
        return $address;
    }

    return trim((string) get_post_meta($tour_id, 'citta', true));
}

function opencomune_escape_ics_text($value) {
    $value = str_replace(["\r\n", "\r", "\n"], '\n', (string) $value);
    $value = str_replace(['\\', ';', ','], ['\\\\', '\;', '\,'], $value);

    return $value;
}

/**
 * @param array<string, mixed> $event
 */
function opencomune_format_ics_datetime_utc(DateTimeInterface $dt) {
    $utc = $dt->setTimezone(new DateTimeZone('UTC'));

    return $utc->format('Ymd\THis\Z');
}

/**
 * @param array<string, mixed> $event
 */
function opencomune_build_booking_ics_content(array $event) {
    if (empty($event['start']) || empty($event['end']) || !($event['start'] instanceof DateTimeInterface)) {
        return '';
    }

    $start = $event['start'];
    $end = $event['end'];
    $tz_name = (string) ($event['timezone'] ?? wp_timezone_string());
    $tz_param = $tz_name !== '' ? ';TZID=' . opencomune_escape_ics_text($tz_name) : '';

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//OpenComune//Booking//IT',
        'CALSCALE:GREGORIAN',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:' . opencomune_escape_ics_text((string) ($event['uid'] ?? uniqid('booking-', true))),
        'DTSTAMP:' . gmdate('Ymd\THis\Z'),
        'DTSTART' . $tz_param . ':' . $start->format('Ymd\THis'),
        'DTEND' . $tz_param . ':' . $end->format('Ymd\THis'),
        'SUMMARY:' . opencomune_escape_ics_text((string) ($event['title'] ?? '')),
    ];

    if (!empty($event['description'])) {
        $lines[] = 'DESCRIPTION:' . opencomune_escape_ics_text((string) $event['description']);
    }
    if (!empty($event['location'])) {
        $lines[] = 'LOCATION:' . opencomune_escape_ics_text((string) $event['location']);
    }
    if (!empty($event['url'])) {
        $lines[] = 'URL:' . opencomune_escape_ics_text((string) $event['url']);
    }

    $lines[] = 'END:VEVENT';
    $lines[] = 'END:VCALENDAR';

    return implode("\r\n", $lines) . "\r\n";
}

/**
 * @param object|null $ordine
 * @return string|null Percorso file .ics temporaneo.
 */
function opencomune_get_booking_ics_attachment_path($booking_id, $ordine = null) {
    $event = opencomune_build_booking_calendar_event($booking_id, $ordine);
    if (!$event) {
        return null;
    }

    $ics = opencomune_build_booking_ics_content($event);
    if ($ics === '') {
        return null;
    }

    $filename = sprintf(
        'esperienza-prenotazione-%d-%s.ics',
        (int) $booking_id,
        wp_generate_password(6, false, false)
    );
    $path = trailingslashit(get_temp_dir()) . $filename;
    if (file_put_contents($path, $ics) === false) {
        return null;
    }

    return $path;
}

/**
 * @param array<string, mixed> $event
 */
function opencomune_get_google_calendar_add_url(array $event) {
    if (empty($event['start']) || empty($event['end']) || !($event['start'] instanceof DateTimeInterface)) {
        return '';
    }

    $params = [
        'action' => 'TEMPLATE',
        'text' => (string) ($event['title'] ?? ''),
        'dates' => opencomune_format_ics_datetime_utc($event['start']) . '/' . opencomune_format_ics_datetime_utc($event['end']),
        'details' => (string) ($event['description'] ?? ''),
        'location' => (string) ($event['location'] ?? ''),
    ];

    return add_query_arg(array_filter($params), 'https://calendar.google.com/calendar/render');
}

/**
 * @param array<string, mixed> $event
 */
function opencomune_get_outlook_calendar_add_url(array $event) {
    if (empty($event['start']) || empty($event['end']) || !($event['start'] instanceof DateTimeInterface)) {
        return '';
    }

    $params = [
        'path' => '/calendar/action/compose',
        'rru' => 'addevent',
        'subject' => (string) ($event['title'] ?? ''),
        'body' => (string) ($event['description'] ?? ''),
        'location' => (string) ($event['location'] ?? ''),
        'startdt' => $event['start']->format('Y-m-d\TH:i:s'),
        'enddt' => $event['end']->format('Y-m-d\TH:i:s'),
    ];

    return add_query_arg(array_filter($params), 'https://outlook.live.com/calendar/0/deeplink/compose');
}

/**
 * @param array<string, mixed> $event
 */
function opencomune_html_email_calendar_block(array $event) {
    $google = opencomune_get_google_calendar_add_url($event);
    $outlook = opencomune_get_outlook_calendar_add_url($event);
    if ($google === '' && $outlook === '') {
        return '';
    }

    $font = esc_attr(opencomune_get_html_email_font_stack());
    $html = opencomune_html_email_section_title(__('Aggiungi al calendario', 'opencomune'));
    $html .= '<p style="margin:0 0 12px;font-family:' . $font . ';font-size:15px;line-height:1.55;color:#374151;">'
        . esc_html(__('Salva data e ora dell’esperienza sul tuo calendario (in allegato trovi anche un file .ics per Apple Calendar, Outlook e altre app).', 'opencomune'))
        . '</p>';
    $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">';

    if ($google !== '') {
        $html .= '<tr><td style="padding:0 0 8px;">'
            . '<a href="' . esc_url($google) . '" style="font-family:' . $font . ';font-size:15px;font-weight:600;color:#1d4ed8;text-decoration:none;">'
            . esc_html(__('Google Calendar', 'opencomune'))
            . '</a></td></tr>';
    }
    if ($outlook !== '') {
        $html .= '<tr><td style="padding:0 0 8px;">'
            . '<a href="' . esc_url($outlook) . '" style="font-family:' . $font . ';font-size:15px;font-weight:600;color:#1d4ed8;text-decoration:none;">'
            . esc_html(__('Outlook / Microsoft 365', 'opencomune'))
            . '</a></td></tr>';
    }

    $html .= '</table>';

    return $html;
}

/**
 * @param object|null $ordine
 * @return array<int, string>
 */
function opencomune_get_booking_confirmation_email_attachments($booking_id, $ordine = null) {
    $attachments = [];
    if ($booking_id > 0 && function_exists('opencomune_get_booking_ticket_pdf_attachments')) {
        $attachments = opencomune_get_booking_ticket_pdf_attachments($booking_id, $ordine);
    }
    if ($booking_id > 0) {
        $ics = opencomune_get_booking_ics_attachment_path($booking_id, $ordine);
        if ($ics) {
            $attachments[] = $ics;
        }
    }

    return $attachments;
}
