<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_ai_get_active_api_key($user_id) {
    $settings = opencomune_get_partner_ai_settings($user_id);
    $provider = $settings['provider'] ?? 'openai';

    if ($provider === 'deepseek') {
        return [
            'provider' => 'deepseek',
            'api_key' => trim((string) ($settings['deepseek_api_key'] ?? '')),
            'endpoint' => 'https://api.deepseek.com/chat/completions',
            'model' => 'deepseek-chat',
        ];
    }

    return [
        'provider' => 'openai',
        'api_key' => trim((string) ($settings['openai_api_key'] ?? '')),
        'endpoint' => 'https://api.openai.com/v1/chat/completions',
        'model' => 'gpt-4o-mini',
    ];
}

function opencomune_ai_chat_completion($user_id, array $messages) {
    $config = opencomune_ai_get_active_api_key($user_id);
    if ($config['api_key'] === '') {
        return new WP_Error('ai_not_configured', __('Configura una API key nella sezione AI del profilo.', 'opencomune'));
    }

    $response = wp_remote_post($config['endpoint'], [
        'timeout' => 90,
        'headers' => [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
        ],
        'body' => wp_json_encode([
            'model' => $config['model'],
            'messages' => $messages,
            'temperature' => 0.3,
        ]),
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($code < 200 || $code >= 300) {
        $message = '';
        if (is_array($body) && isset($body['error']['message'])) {
            $message = (string) $body['error']['message'];
        }
        if ($message === '') {
            $message = __('Errore dal provider AI.', 'opencomune');
        }
        return new WP_Error('ai_http_error', $message);
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || trim($content) === '') {
        return new WP_Error('ai_empty', __('Risposta AI vuota.', 'opencomune'));
    }

    return trim($content);
}

function opencomune_ai_translate_esperienza_field($post_id, $field_key, $user_id) {
    $post_id = (int) $post_id;
    $field_key = sanitize_key($field_key);
    $defs = opencomune_get_esperienza_translation_field_defs();
    $map = opencomune_get_esperienza_translation_ui_map();

    if (!isset($defs[$field_key], $map[$field_key])) {
        return new WP_Error('invalid_field', __('Campo non valido.', 'opencomune'));
    }

    $def = $defs[$field_key];
    $ui = $map[$field_key];
    $source = (string) opencomune_get_esperienza_form_it_field_value($post_id, $ui['it_field']);

    if (trim(wp_strip_all_tags($source)) === '') {
        return new WP_Error('empty_source', __('Il testo italiano di questo campo e vuoto.', 'opencomune'));
    }

    $label_it = $def['label_it'] ?? $field_key;
    $type = $def['type'] ?? 'text';

    $system = 'You are a professional translator for tourism and cultural experiences. '
        . 'Translate the user content from Italian to English. '
        . 'Keep the same tone and marketing style. Do not add explanations.';

    if ($type === 'html') {
        $system .= ' Preserve valid HTML tags and structure where present.';
    } else {
        $system .= ' Return only the translated text without markdown fences or quotes.';
    }

    $user_prompt = sprintf(
        "Field: %s\n\nItalian text:\n%s",
        $label_it,
        $source
    );

    $translated = opencomune_ai_chat_completion($user_id, [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => $user_prompt],
    ]);

    if (is_wp_error($translated)) {
        return $translated;
    }

    return opencomune_sanitize_esperienza_translation_value($translated, $type);
}

add_action('wp_ajax_opencomune_ai_translate_esperienza_field', 'opencomune_ai_translate_esperienza_field_callback');
function opencomune_ai_translate_esperienza_field_callback() {
    check_ajax_referer('opencomune_dashboard_translations', 'nonce');

    $post_id = (int) ($_POST['post_id'] ?? 0);
    $field_key = sanitize_key($_POST['field_key'] ?? '');
    $user_id = get_current_user_id();

    if (!opencomune_user_can_edit_esperienza($post_id)) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    if (!opencomune_partner_ai_is_configured($user_id)) {
        wp_send_json_error(['message' => __('API AI non configurate.', 'opencomune')]);
    }

    $result = opencomune_ai_translate_esperienza_field($post_id, $field_key, $user_id);
    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
    }

    wp_send_json_success([
        'field_key' => $field_key,
        'value' => $result,
        'input_name' => opencomune_get_esperienza_translation_input_name($field_key),
    ]);
}
