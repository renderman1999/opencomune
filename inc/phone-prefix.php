<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Prefissi telefonici (stesso elenco del modal prenotazione in single-esperienza).
 *
 * @return array<string, string> value => label
 */
function opencomune_get_phone_prefix_options() {
    return [
        '+39' => '🇮🇹 +39',
        '+33' => '🇫🇷 +33',
        '+49' => '🇩🇪 +49',
        '+34' => '🇪🇸 +34',
        '+44' => '🇬🇧 +44',
        '+1' => '🇺🇸 +1',
        '+41' => '🇨🇭 +41',
        '+43' => '🇦🇹 +43',
        '+32' => '🇧🇪 +32',
        '+31' => '🇳🇱 +31',
    ];
}

function opencomune_get_default_phone_prefix() {
    return '+39';
}

/**
 * @return array{prefix: string, number: string}
 */
function opencomune_split_stored_phone($phone, $stored_prefix = '') {
    $default = opencomune_get_default_phone_prefix();
    $stored_prefix = trim((string) $stored_prefix);
    $phone = trim((string) $phone);

    if ($phone === '') {
        return [
            'prefix' => $stored_prefix !== '' ? $stored_prefix : $default,
            'number' => '',
        ];
    }

    if ($stored_prefix !== '') {
        return [
            'prefix' => $stored_prefix,
            'number' => $phone,
        ];
    }

    foreach (array_keys(opencomune_get_phone_prefix_options()) as $prefix) {
        if (strpos($phone, $prefix) === 0) {
            return [
                'prefix' => $prefix,
                'number' => trim(substr($phone, strlen($prefix))),
            ];
        }
    }

    return [
        'prefix' => $default,
        'number' => $phone,
    ];
}

function opencomune_format_phone_with_prefix($prefix, $number) {
    $number = trim((string) $number);
    $prefix = trim((string) $prefix);
    if ($prefix === '') {
        $prefix = opencomune_get_default_phone_prefix();
    }
    if ($number === '') {
        return '';
    }

    return $prefix . ' ' . $number;
}

/**
 * @param array{id?: string, name?: string, class?: string, selected?: string} $args
 */
function opencomune_render_phone_prefix_select(array $args = []) {
    $args = wp_parse_args($args, [
        'id' => '',
        'name' => 'phone_prefix',
        'class' => 'oc-phone-prefix-select',
        'selected' => opencomune_get_default_phone_prefix(),
    ]);

    $id_attr = $args['id'] !== '' ? ' id="' . esc_attr($args['id']) . '"' : '';
    $name_attr = $args['name'] !== '' ? ' name="' . esc_attr($args['name']) . '"' : '';

    echo '<select' . $id_attr . $name_attr . ' class="' . esc_attr($args['class']) . '" autocomplete="tel-country-code">';

    foreach (opencomune_get_phone_prefix_options() as $value => $label) {
        printf(
            '<option value="%s"%s>%s</option>',
            esc_attr($value),
            selected($args['selected'], $value, false),
            esc_html($label)
        );
    }

    echo '</select>';
}
