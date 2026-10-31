<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Canale di vendita / prenotazione (equivalente semplificato Bókun booking channel + payment policy).
 */
function opencomune_get_esperienza_booking_payment_modes() {
    return [
        'on_site' => __('Pagamento in loco / alla guida', 'opencomune'),
        'online_optional' => __('Pagamento online opzionale', 'opencomune'),
        'online_required' => __('Pagamento online obbligatorio (checkout)', 'opencomune'),
    ];
}

function opencomune_get_default_booking_payment_mode() {
    return 'online_required';
}

function opencomune_normalize_booking_payment_mode($mode) {
    $allowed = array_keys(opencomune_get_esperienza_booking_payment_modes());
    $mode = sanitize_key($mode);

    return in_array($mode, $allowed, true) ? $mode : opencomune_get_default_booking_payment_mode();
}

function opencomune_partner_booking_payment_mode_is_fixed() {
    return !opencomune_user_can_manage_all_esperienze();
}

function opencomune_get_partner_fixed_booking_payment_mode() {
    return 'online_required';
}

function opencomune_esperienza_booking_payment_mode_enforced_for_post($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze') {
        return null;
    }

    $author_id = (int) $post->post_author;
    if ($author_id > 0 && user_can($author_id, 'manage_options')) {
        return null;
    }

    return opencomune_get_partner_fixed_booking_payment_mode();
}

function opencomune_get_esperienza_booking_channel_settings($post_id) {
    $post_id = (int) $post_id;

    $direct = get_post_meta($post_id, 'mostra_form_prenotazione', true);
    if ($direct === '' || $direct === false) {
        $direct = '1';
    }

    $payment_mode = get_post_meta($post_id, 'tour_booking_payment_mode', true);
    if ($payment_mode === '' || $payment_mode === false) {
        $payment_mode = opencomune_get_default_booking_payment_mode();
    }

    $payment_mode = opencomune_normalize_booking_payment_mode($payment_mode);
    $enforced = opencomune_esperienza_booking_payment_mode_enforced_for_post($post_id);
    if ($enforced !== null) {
        $payment_mode = $enforced;
    }

    return [
        'direct_booking_enabled' => $direct === '1',
        'external_booking_url' => (string) get_post_meta($post_id, 'tour_destination', true),
        'payment_mode' => $payment_mode,
    ];
}

function opencomune_experience_allows_online_payment($post_id) {
    if (function_exists('opencomune_site_online_payments_available') && !opencomune_site_online_payments_available()) {
        return false;
    }

    $settings = opencomune_get_esperienza_booking_channel_settings($post_id);

    return in_array($settings['payment_mode'], ['online_optional', 'online_required'], true);
}

function opencomune_experience_requires_online_payment($post_id, $checkout_total = null) {
    if ($checkout_total !== null && (float) $checkout_total <= 0) {
        return false;
    }

    if ($checkout_total === null && function_exists('opencomune_esperienza_is_free') && opencomune_esperienza_is_free($post_id)) {
        return false;
    }

    $settings = opencomune_get_esperienza_booking_channel_settings($post_id);
    if ($settings['payment_mode'] !== 'online_required') {
        return false;
    }

    if (function_exists('opencomune_site_online_payments_available') && !opencomune_site_online_payments_available()) {
        return false;
    }

    return true;
}

function opencomune_save_esperienza_booking_channel_settings($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $direct = !empty($post_data['mostra_form_prenotazione']) ? '1' : '0';
    if (opencomune_partner_booking_payment_mode_is_fixed()) {
        $payment_mode = opencomune_get_partner_fixed_booking_payment_mode();
    } else {
        $payment_mode = opencomune_normalize_booking_payment_mode(
            $post_data['tour_booking_payment_mode'] ?? opencomune_get_default_booking_payment_mode()
        );
    }
    $external_url = esc_url_raw($post_data['tour_external_booking_url'] ?? '');

    update_post_meta($post_id, 'mostra_form_prenotazione', $direct);
    update_post_meta($post_id, 'tour_booking_payment_mode', $payment_mode);

    if (array_key_exists('tour_external_booking_url', $post_data)) {
        if ($external_url !== '') {
            update_post_meta($post_id, 'tour_destination', $external_url);
        } elseif (isset($post_data['tour_destination'])) {
            update_post_meta($post_id, 'tour_destination', esc_url_raw($post_data['tour_destination']));
        }
    }

    return opencomune_get_esperienza_booking_channel_settings($post_id);
}

function opencomune_format_booking_channel_for_api($post_id) {
    $settings = opencomune_get_esperienza_booking_channel_settings($post_id);
    $is_free = function_exists('opencomune_esperienza_is_free') && opencomune_esperienza_is_free($post_id);

    return [
        'direct_booking_enabled' => (bool) $settings['direct_booking_enabled'],
        'external_booking_url' => $settings['external_booking_url'],
        'payment_mode' => $settings['payment_mode'],
        'online_payment_available' => !$is_free && opencomune_experience_allows_online_payment($post_id),
        'online_payment_required' => !$is_free && opencomune_experience_requires_online_payment($post_id),
        'is_free' => $is_free,
    ];
}

function opencomune_enqueue_booking_channel_assets() {
    $theme = wp_get_theme();
    wp_enqueue_style(
        'opencomune-experience-settings',
        get_template_directory_uri() . '/assets/css/experience-settings.css',
        [],
        $theme->get('Version') ?: '1.0'
    );
}
