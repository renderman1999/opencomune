<?php
/**
 * Cloudflare Turnstile – impostazioni e verifica token.
 */

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_is_turnstile_enabled() {
    return get_option('opencomune_turnstile_enabled', '0') === '1';
}

function opencomune_get_turnstile_site_key() {
    return trim((string) get_option('opencomune_turnstile_site_key', ''));
}

function opencomune_get_turnstile_secret_key() {
    return trim((string) get_option('opencomune_turnstile_secret_key', ''));
}

function opencomune_is_turnstile_configured() {
    if (!opencomune_is_turnstile_enabled()) {
        return false;
    }

    return opencomune_get_turnstile_site_key() !== '' && opencomune_get_turnstile_secret_key() !== '';
}

/**
 * @return true|WP_Error
 */
function opencomune_verify_turnstile_response($token, $remote_ip = '') {
    if (!opencomune_is_turnstile_configured()) {
        return true;
    }

    $token = is_string($token) ? trim($token) : '';
    if ($token === '') {
        return new WP_Error('turnstile_missing', __('Verifica anti-spam non completata. Riprova.', 'opencomune'));
    }

    $secret = opencomune_get_turnstile_secret_key();
    $body = [
        'secret' => $secret,
        'response' => $token,
    ];

    if ($remote_ip === '') {
        $remote_ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    }
    if ($remote_ip !== '') {
        $body['remoteip'] = $remote_ip;
    }

    $response = wp_remote_post(
        'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        [
            'timeout' => 15,
            'body' => $body,
        ]
    );

    if (is_wp_error($response)) {
        return new WP_Error('turnstile_http', __('Impossibile verificare Turnstile. Riprova più tardi.', 'opencomune'));
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $data = json_decode((string) wp_remote_retrieve_body($response), true);

    if ($code !== 200 || !is_array($data)) {
        return new WP_Error('turnstile_invalid', __('Risposta Turnstile non valida.', 'opencomune'));
    }

    if (!empty($data['success'])) {
        return true;
    }

    $error_codes = isset($data['error-codes']) && is_array($data['error-codes'])
        ? implode(', ', array_map('sanitize_text_field', $data['error-codes']))
        : '';

    $message = __('Verifica Turnstile fallita.', 'opencomune');
    if ($error_codes !== '') {
        $message .= ' (' . $error_codes . ')';
    }

    return new WP_Error('turnstile_failed', $message);
}

function opencomune_enqueue_turnstile_script() {
    if (!opencomune_is_turnstile_configured()) {
        return;
    }

    wp_enqueue_script(
        'cloudflare-turnstile',
        'https://challenges.cloudflare.com/turnstile/v0/api.js',
        [],
        null,
        true
    );
}

function opencomune_render_turnstile_widget($wrapper_class = 'opencomune-turnstile') {
    if (!opencomune_is_turnstile_configured()) {
        return '';
    }

    $wrapper_class = trim($wrapper_class);
    $html = '<div class="' . esc_attr($wrapper_class) . '">';
    $html .= '<div class="cf-turnstile" data-sitekey="' . esc_attr(opencomune_get_turnstile_site_key()) . '"></div>';
    $html .= '</div>';

    return $html;
}
