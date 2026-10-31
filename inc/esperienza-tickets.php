<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_esperienza_ticket_settings($post_id) {
    $post_id = (int) $post_id;
    $message = get_post_meta($post_id, 'tour_ticket_message', true);

    return [
        'ticket_per_participant' => get_post_meta($post_id, 'tour_ticket_per_participant', true) === '1',
        'ticket_message' => is_string($message) ? $message : '',
        'override_barcode_format' => get_post_meta($post_id, 'tour_ticket_override_barcode', true) === '1',
    ];
}

function opencomune_save_esperienza_ticket_settings($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $per_participant = !empty($post_data['tour_ticket_per_participant']);
    $override_barcode = !empty($post_data['tour_ticket_override_barcode']);
    $message = isset($post_data['tour_ticket_message'])
        ? wp_kses_post(wp_unslash($post_data['tour_ticket_message']))
        : '';

    update_post_meta($post_id, 'tour_ticket_per_participant', $per_participant ? '1' : '0');
    update_post_meta($post_id, 'tour_ticket_override_barcode', $override_barcode ? '1' : '0');
    update_post_meta($post_id, 'tour_ticket_message', $message);

    return opencomune_get_esperienza_ticket_settings($post_id);
}

function opencomune_ticket_preview_code_prefix($supplier_name) {
    $letters = preg_replace('/[^a-zA-Z]/', '', (string) $supplier_name);
    $prefix = strtoupper(substr($letters, 0, 3));

    return $prefix !== '' ? $prefix : 'EXP';
}

function opencomune_format_operating_hours_for_ticket(array $config) {
    if (empty($config['enabled'])) {
        return __('Orario di avvio non abilitato per questa esperienza.', 'opencomune');
    }

    $weekdays = opencomune_get_operating_hours_weekdays();
    $open_parts = [];
    $enabled_days = 0;

    foreach ($weekdays as $key => $label) {
        $day = isset($config['default'][$key]) && is_array($config['default'][$key])
            ? $config['default'][$key]
            : [];
        if (!empty($day['enabled'])) {
            $enabled_days++;
            $open = isset($day['open']) ? (string) $day['open'] : '';
            $close = isset($day['close']) ? (string) $day['close'] : '';
            if ($open !== '' && $close !== '') {
                $open_parts[] = $label . ' ' . $open . '–' . $close;
            }
        }
    }

    if ($enabled_days === 0 || empty($open_parts)) {
        return __('Chiuso lun–dom', 'opencomune');
    }

    return implode('; ', $open_parts);
}

function opencomune_ticket_preview_start_time_label($post_id, $time_override = '') {
    $time = $time_override;
    if ($time === '') {
        $times = opencomune_get_esperienza_start_times((int) $post_id);
        if (!empty($times[0]['time'])) {
            $time = (string) $times[0]['time'];
        }
    }
    if ($time === '') {
        $time = '12:00';
    }

    $timestamp = strtotime('next sunday ' . $time);
    if ($timestamp === false) {
        $timestamp = time();
    }

    return sprintf(
        /* translators: %s: formatted date and time */
        __('Inizio: %s', 'opencomune'),
        wp_date('D, j F Y - H:i', $timestamp)
    );
}

function opencomune_get_ticket_preview_context($post_id) {
    $post_id = (int) $post_id;
    $author_id = $post_id > 0 ? (int) get_post_field('post_author', $post_id) : get_current_user_id();
    $partner = function_exists('opencomune_get_partner_by_user_id')
        ? opencomune_get_partner_by_user_id($author_id)
        : null;
    $user = $author_id > 0 ? get_userdata($author_id) : wp_get_current_user();

    $supplier_name = ($partner && !empty($partner->nome_attivita))
        ? (string) $partner->nome_attivita
        : ($user && $user->display_name ? (string) $user->display_name : (string) get_bloginfo('name'));

    $prefix = opencomune_ticket_preview_code_prefix($supplier_name);
    $numeric_ref = $post_id > 0 ? $post_id : 123;
    $ticket_code = $prefix . '-T' . $numeric_ref;
    $booking_ref = '(' . $prefix . '-' . $numeric_ref . ')';

    $categories = opencomune_get_esperienza_pricing_categories($post_id);
    $default_category = __('Adulti', 'opencomune');
    foreach ($categories as $row) {
        if (!empty($row['is_default'])) {
            $default_category = $row['label'];
            break;
        }
    }
    if ($default_category === __('Adulti', 'opencomune') && !empty($categories[0]['label'])) {
        $default_category = $categories[0]['label'];
    }

    $prezzo_raw = get_post_meta($post_id, 'prezzo', true);
    if ($prezzo_raw === '' || $prezzo_raw === false) {
        $prezzo_raw = get_post_meta($post_id, 'tour_price', true);
    }
    $unit_price = is_numeric($prezzo_raw) && (float) $prezzo_raw > 0 ? (float) $prezzo_raw : 100.0;
    $sample_qty = 2;
    $subtotal = $unit_price * $sample_qty;

    $phone = ($partner && !empty($partner->telefono_attivita))
        ? (string) $partner->telefono_attivita
        : '';
    $email = ($partner && !empty($partner->email_ufficiale))
        ? (string) $partner->email_ufficiale
        : ($user && $user->user_email ? (string) $user->user_email : '');
    $web = ($partner && !empty($partner->sito_web))
        ? (string) $partner->sito_web
        : (string) home_url('/');

    $logo = ($partner && !empty($partner->logo_url)) ? (string) $partner->logo_url : '';

    $hours = opencomune_get_esperienza_operating_hours($post_id);
    $cancellation = $post_id > 0 ? (string) get_post_meta($post_id, 'tour_cancellation_policy', true) : '';
    if (trim(wp_strip_all_tags($cancellation)) === '') {
        $cancellation = __('Le prenotazioni non sono rimborsabili. Tutte le vendite sono definitive.', 'opencomune');
    } elseif ($post_id > 0 && function_exists('opencomune_filter_esperienza_rich_text')) {
        $cancellation = opencomune_filter_esperienza_rich_text($cancellation, $post_id);
    }

    $title = $post_id > 0 ? get_the_title($post_id) : __('test esperienza', 'opencomune');
    if ($title === '') {
        $title = __('test esperienza', 'opencomune');
    }

    return [
        'ticket_code' => $ticket_code,
        'booking_ref' => $booking_ref,
        'payment_status' => __('Pagato interamente', 'opencomune'),
        'customer_name' => 'Rossi, Mario',
        'customer_email' => 'mario.rossi@example.com',
        'customer_phone' => '+39 333 1234567',
        'supplier_name' => $supplier_name,
        'supplier_vat' => '',
        'supplier_phone' => $phone,
        'supplier_email' => $email,
        'supplier_web' => $web,
        'supplier_logo' => $logo,
        'experience_title' => $title,
        'rate_label' => __('Tariffa standard', 'opencomune'),
        'start_time' => opencomune_ticket_preview_start_time_label($post_id),
        'quantity_summary' => $default_category . ' : ' . $sample_qty,
        'invoice_line_title' => $supplier_name . ': ' . $title . ' (' . $ticket_code . ')',
        'invoice_line_category' => $default_category,
        'invoice_qty' => (string) $sample_qty,
        'invoice_unit' => '€' . number_format($unit_price, 2, '.', ''),
        'invoice_subtotal' => '€' . number_format($subtotal, 2, '.', ''),
        'invoice_total' => '€' . number_format($subtotal, 2, '.', ''),
        'cancellation_policy' => $cancellation,
        'operating_hours' => opencomune_format_operating_hours_for_ticket($hours),
        'sample_note' => __('Anteprima con dati di esempio per cliente, quantità e pagamento.', 'opencomune'),
        'qr_url' => 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($ticket_code),
        'weekday_labels' => opencomune_get_operating_hours_weekdays(),
    ];
}

function opencomune_enqueue_esperienza_tickets_assets($post_id = 0) {
    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';

    wp_enqueue_style(
        'opencomune-esperienza-rich-content',
        get_template_directory_uri() . '/assets/css/esperienza-rich-content.css',
        [],
        $ver
    );

    wp_enqueue_style(
        'opencomune-esperienza-tickets',
        get_template_directory_uri() . '/assets/css/esperienza-tickets.css',
        ['opencomune-esperienza-rich-content'],
        $ver
    );

    wp_enqueue_script(
        'opencomune-esperienza-tickets',
        get_template_directory_uri() . '/assets/js/esperienza-tickets.js',
        [],
        $ver,
        true
    );

    wp_localize_script('opencomune-esperienza-tickets', 'opencomuneEsperienzaTickets', [
        'preview' => opencomune_get_ticket_preview_context((int) $post_id),
        'strings' => [
            'previewTitle' => __('Anteprima biglietto', 'opencomune'),
            'rateLabel' => __('Tariffa standard', 'opencomune'),
            'startPrefix' => __('Inizio:', 'opencomune'),
            'closedAllWeek' => __('Chiuso lun–dom', 'opencomune'),
            'hoursDisabled' => __('Orario di avvio non abilitato per questa esperienza.', 'opencomune'),
            'defaultCancellation' => __('Le prenotazioni non sono rimborsabili. Tutte le vendite sono definitive.', 'opencomune'),
            'defaultCategory' => __('Adulti', 'opencomune'),
            'sampleExperienceTitle' => __('test esperienza', 'opencomune'),
            'sampleNote' => __('Anteprima con dati di esempio per cliente, quantità e pagamento.', 'opencomune'),
        ],
    ]);
}

function opencomune_load_composer_autoload() {
    static $loaded = false;
    if ($loaded) {
        return true;
    }

    $autoload = ABSPATH . 'vendor/autoload.php';
    if (!is_readable($autoload)) {
        return false;
    }

    require_once $autoload;
    $loaded = true;

    return true;
}

/**
 * @param object|null $ordine Riga tour_ordini (opzionale).
 * @return array<string, mixed>|null
 */
function opencomune_build_booking_ticket_context($booking_id, $ordine = null) {
    $booking_id = (int) $booking_id;
    if ($booking_id <= 0 || !function_exists('opencomune_get_tour_booking_by_id')) {
        return null;
    }

    $booking = opencomune_get_tour_booking_by_id($booking_id);
    if (!$booking) {
        return null;
    }

    $tour_id = (int) $booking->tour_id;
    $context = opencomune_get_ticket_preview_context($tour_id);
    $settings = opencomune_get_esperienza_ticket_settings($tour_id);

    $cancellation_raw = (string) get_post_meta($tour_id, 'tour_cancellation_policy', true);
    if (trim(wp_strip_all_tags($cancellation_raw)) !== '') {
        $context['cancellation_policy'] = function_exists('opencomune_filter_esperienza_rich_text')
            ? opencomune_filter_esperienza_rich_text($cancellation_raw, $tour_id)
            : $cancellation_raw;
    }

    $prefix = opencomune_ticket_preview_code_prefix($context['supplier_name']);
    $ticket_code = $prefix . '-T' . $booking_id;
    $context['ticket_code'] = $ticket_code;
    $context['booking_ref'] = '(' . $prefix . '-' . $booking_id . ')';
    $context['sample_note'] = '';
    $context['ticket_message'] = $settings['ticket_message'];

    $context['customer_name'] = trim((string) ($booking->nome ?? ''));
    $context['customer_email'] = trim((string) ($booking->email ?? ''));
    $phone = trim((string) ($booking->telefono ?? ''));
    if ($phone === '' && $ordine && !empty($ordine->telefono)) {
        $phone = trim((string) $ordine->telefono);
    }
    $context['customer_phone'] = $phone;

    if ($ordine && !empty($ordine->status) && sanitize_key((string) $ordine->status) === 'completed') {
        $context['payment_status'] = __('Pagato interamente', 'opencomune');
    } elseif ($booking->status === 'confermata') {
        $context['payment_status'] = __('Confermato', 'opencomune');
    }

    $date_ymd = (string) ($booking->booking_date ?? '');
    $time_hm = (string) ($booking->booking_time ?? '');
    if ($date_ymd !== '' && $time_hm !== '') {
        $timestamp = strtotime($date_ymd . ' ' . $time_hm);
        if ($timestamp) {
            $context['start_time'] = sprintf(
                __('Inizio: %s', 'opencomune'),
                wp_date('D, j F Y - H:i', $timestamp)
            );
            $context['invoice_line_datetime'] = wp_date('D, j F Y - H:i', $timestamp);
        }
    }

    $participants = max(1, (int) ($booking->participants ?? 1));
    $total = 0.0;
    $category_label = '';
    $unit_price = 0.0;

    if ($ordine && isset($ordine->totale)) {
        $total = max(0, round((float) $ordine->totale, 2));
    }

    $payload = null;
    if ($ordine && !empty($ordine->checkout_token) && function_exists('opencomune_get_checkout_session_payload_by_token')) {
        $payload = opencomune_get_checkout_session_payload_by_token($ordine->checkout_token);
    }

    if (is_array($payload) && !empty($payload['totals']['lines'])) {
        $summary_parts = [];
        foreach ($payload['totals']['lines'] as $line) {
            if (!is_array($line)) {
                continue;
            }
            $label = (string) ($line['label'] ?? '');
            $qty = (int) ($line['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $summary_parts[] = $label . ' : ' . $qty;
            if ($category_label === '') {
                $category_label = $label;
                $unit_price = (float) ($line['unit_price'] ?? 0);
            }
        }
        if ($summary_parts !== []) {
            $context['quantity_summary'] = implode(', ', $summary_parts);
        }
        if ($total <= 0 && isset($payload['totals']['total'])) {
            $total = max(0, round((float) $payload['totals']['total'], 2));
        }
        if (!empty($payload['totals']['participants'])) {
            $participants = max(1, (int) $payload['totals']['participants']);
        }
    }

    if ($category_label === '') {
        $categories = opencomune_get_esperienza_pricing_categories($tour_id);
        foreach ($categories as $row) {
            if (!empty($row['is_default'])) {
                $category_label = (string) $row['label'];
                break;
            }
        }
        if ($category_label === '' && !empty($categories[0]['label'])) {
            $category_label = (string) $categories[0]['label'];
        }
        if ($category_label === '') {
            $category_label = __('Adulti', 'opencomune');
        }
        $context['quantity_summary'] = $category_label . ' : ' . $participants;
    }

    if ($unit_price <= 0 && $participants > 0 && $total > 0) {
        $unit_price = round($total / $participants, 2);
    }
    if ($unit_price <= 0) {
        $prezzo_raw = get_post_meta($tour_id, 'prezzo', true);
        if ($prezzo_raw === '' || $prezzo_raw === false) {
            $prezzo_raw = get_post_meta($tour_id, 'tour_price', true);
        }
        $unit_price = is_numeric($prezzo_raw) && (float) $prezzo_raw > 0 ? (float) $prezzo_raw : 0.0;
    }
    if ($total <= 0 && $unit_price > 0) {
        $total = round($unit_price * $participants, 2);
    }

    $title = get_the_title($tour_id);
    $context['experience_title'] = $title !== '' ? $title : $context['experience_title'];
    $context['invoice_line_title'] = $context['supplier_name'] . ': ' . $context['experience_title'] . ' (' . $ticket_code . ')';
    $context['invoice_line_category'] = $category_label;
    $context['invoice_qty'] = (string) $participants;
    $context['invoice_unit'] = '€' . number_format($unit_price, 2, '.', '');
    $context['invoice_subtotal'] = '€' . number_format($total, 2, '.', '');
    $context['invoice_total'] = '€' . number_format($total, 2, '.', '');
    $context['qr_url'] = 'https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=' . rawurlencode($ticket_code);

    return $context;
}

function opencomune_render_booking_ticket_html(array $context) {
    $ticket = $context;
    ob_start();
    include get_template_directory() . '/template-parts/booking-ticket-document-html.php';

    return (string) ob_get_clean();
}

/**
 * @param object|null $ordine
 * @return string|null Percorso file PDF temporaneo.
 */
function opencomune_generate_booking_ticket_pdf_path($booking_id, $ordine = null) {
    $context = opencomune_build_booking_ticket_context($booking_id, $ordine);
    if (!$context || !opencomune_load_composer_autoload() || !class_exists('Dompdf\Dompdf')) {
        return null;
    }

    $html = opencomune_render_booking_ticket_html($context);
    if ($html === '') {
        return null;
    }

    $options = new Dompdf\Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf\Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $booking_id = (int) $booking_id;
    $filename = sprintf(
        'biglietto-prenotazione-%d-%s.pdf',
        $booking_id > 0 ? $booking_id : 0,
        wp_generate_password(8, false, false)
    );
    $pdf_path = trailingslashit(get_temp_dir()) . $filename;
    $written = file_put_contents($pdf_path, $dompdf->output());
    if ($written === false) {
        return null;
    }

    return $pdf_path;
}

/**
 * @param object|null $ordine
 * @return array<int, string> Allegati PDF (percorsi temporanei).
 */
function opencomune_get_booking_ticket_pdf_attachments($booking_id, $ordine = null) {
    $path = opencomune_generate_booking_ticket_pdf_path($booking_id, $ordine);
    if (!$path || !is_readable($path)) {
        return [];
    }

    return [$path];
}

function opencomune_format_ticket_settings_for_api($post_id) {
    $settings = opencomune_get_esperienza_ticket_settings($post_id);

    return [
        'ticket_per_participant' => (bool) $settings['ticket_per_participant'],
        'ticket_message' => $settings['ticket_message'],
        'override_barcode_format' => (bool) $settings['override_barcode_format'],
    ];
}
