<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_ai_provider_labels() {
    return [
        'openai' => __('OpenAI', 'opencomune'),
        'deepseek' => __('DeepSeek', 'opencomune'),
    ];
}

function opencomune_get_partner_ai_settings($user_id) {
    $user_id = (int) $user_id;
    $defaults = [
        'enabled' => false,
        'provider' => 'openai',
        'openai_api_key' => '',
        'deepseek_api_key' => '',
    ];

    if ($user_id <= 0) {
        return $defaults;
    }

    $stored = get_user_meta($user_id, 'opencomune_partner_ai_settings', true);
    if (!is_array($stored)) {
        return $defaults;
    }

    return array_merge($defaults, $stored);
}

function opencomune_save_partner_ai_settings($user_id, array $input) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return new WP_Error('invalid_user', __('Utente non valido.', 'opencomune'));
    }

    $providers = array_keys(opencomune_get_ai_provider_labels());
    $provider = sanitize_key($input['ai_provider'] ?? 'openai');
    if (!in_array($provider, $providers, true)) {
        $provider = 'openai';
    }

    $settings = [
        'enabled' => !empty($input['ai_translation_enabled']),
        'provider' => $provider,
        'openai_api_key' => sanitize_text_field($input['openai_api_key'] ?? ''),
        'deepseek_api_key' => sanitize_text_field($input['deepseek_api_key'] ?? ''),
    ];

    update_user_meta($user_id, 'opencomune_partner_ai_settings', $settings);

    return $settings;
}

function opencomune_partner_ai_is_configured($user_id) {
    $settings = opencomune_get_partner_ai_settings($user_id);
    if (empty($settings['enabled'])) {
        return false;
    }

    $provider = $settings['provider'] ?? 'openai';

    if ($provider === 'deepseek') {
        return trim((string) ($settings['deepseek_api_key'] ?? '')) !== '';
    }

    return trim((string) ($settings['openai_api_key'] ?? '')) !== '';
}

function opencomune_get_dashboard_partner_ai_url() {
    return home_url('/profilo-partner-ai/');
}
