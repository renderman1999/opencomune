<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_payment_gateway_labels() {
    return [
        'sumup' => __('SumUp', 'opencomune'),
    ];
}

function opencomune_get_global_payment_gateway_slug() {
    return 'sumup';
}

function opencomune_get_admin_payment_settings_url() {
    return home_url('/profilo-partner-pagamenti/');
}

/**
 * @return array{payments_enabled:bool,sumup_api_key:string,sumup_merchant_code:string,sumup_environment:string,has_api_key:bool}
 */
function opencomune_get_site_sumup_settings_for_form() {
    $api_key = (string) get_option('sumup_api_key', '');

    return [
        'payments_enabled' => opencomune_payments_are_globally_enabled(),
        'sumup_api_key' => '',
        'sumup_merchant_code' => (string) get_option('sumup_merchant_code', ''),
        'sumup_environment' => (string) get_option('sumup_environment', 'test'),
        'has_api_key' => $api_key !== '',
    ];
}

/**
 * @return true|WP_Error
 */
function opencomune_save_site_sumup_payment_settings(array $input) {
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('Accesso negato', 'opencomune'));
    }

    $payments_enabled = !empty($input['opencomune_site_payments_enabled']);
    update_option('opencomune_site_payments_enabled', $payments_enabled ? '1' : '0');
    update_option('opencomune_default_payment_gateway', 'sumup');

    $sumup_api_key = sanitize_text_field($input['sumup_api_key'] ?? '');
    if ($sumup_api_key === '') {
        $sumup_api_key = (string) get_option('sumup_api_key', '');
    }
    $sumup_merchant_code = sanitize_text_field($input['sumup_merchant_code'] ?? '');
    $sumup_environment = sanitize_key($input['sumup_environment'] ?? 'test');
    $sumup_environment = in_array($sumup_environment, ['test', 'live'], true) ? $sumup_environment : 'test';

    if ($payments_enabled) {
        $key_check = opencomune_validate_sumup_api_key($sumup_api_key);
        if (is_wp_error($key_check)) {
            return $key_check;
        }
        if ($sumup_merchant_code === '') {
            return new WP_Error('sumup_merchant_missing', __('Inserisci il SumUp Merchant Code.', 'opencomune'));
        }
    }

    update_option('sumup_api_key', $sumup_api_key);
    update_option('sumup_merchant_code', $sumup_merchant_code);
    update_option('sumup_environment', $sumup_environment);

    return true;
}

function opencomune_payments_are_globally_enabled() {
    return get_option('opencomune_site_payments_enabled', '1') === '1';
}

/**
 * SumUp configurato a livello sito (chiave segreta + merchant).
 */
function opencomune_is_sumup_globally_configured() {
    $api_key = trim((string) get_option('sumup_api_key', ''));
    $merchant_code = trim((string) get_option('sumup_merchant_code', ''));
    if ($api_key === '' || $merchant_code === '') {
        return false;
    }

    if (function_exists('opencomune_validate_sumup_api_key')) {
        return opencomune_validate_sumup_api_key($api_key) === true;
    }

    return strpos($api_key, 'sup_sk_') === 0;
}

function opencomune_site_online_payments_available() {
    return opencomune_payments_are_globally_enabled() && opencomune_is_sumup_globally_configured();
}

function opencomune_get_site_payment_gateway_config() {
    return [
        'source' => 'site',
        'enabled' => opencomune_site_online_payments_available(),
        'default_gateway' => opencomune_get_global_payment_gateway_slug(),
        'sumup' => [
            'api_key' => (string) get_option('sumup_api_key', ''),
            'merchant_code' => (string) get_option('sumup_merchant_code', ''),
            'environment' => (string) get_option('sumup_environment', 'test'),
        ],
    ];
}

/**
 * Valida formato chiave API SumUp (deve essere secret key sup_sk_, non public sup_pk_).
 *
 * @return true|WP_Error
 */
function opencomune_validate_sumup_api_key($api_key) {
    $api_key = trim((string) $api_key);
    if ($api_key === '') {
        return new WP_Error('sumup_key_missing', __('Inserisci la SumUp API Key (chiave segreta).', 'opencomune'));
    }
    if (strpos($api_key, 'sup_pk_') === 0) {
        return new WP_Error(
            'sumup_key_public',
            __('Hai inserito la chiave pubblica SumUp (sup_pk_). Per i pagamenti serve la chiave segreta (sup_sk_) da SumUp → Impostazioni → Per sviluppatori → Toolkit → API Keys → Crea.', 'opencomune')
        );
    }
    if (strpos($api_key, 'sup_sk_') !== 0) {
        return new WP_Error(
            'sumup_key_invalid',
            __('La SumUp API Key non è valida: deve iniziare con sup_sk_.', 'opencomune')
        );
    }

    return true;
}

/**
 * @deprecated I pagamenti sono globali: non usare impostazioni per partner.
 */
function opencomune_get_partner_payment_settings($user_id) {
    return [
        'use_site_defaults' => true,
        'payments_enabled' => opencomune_site_online_payments_available(),
        'default_gateway' => opencomune_get_global_payment_gateway_slug(),
        'sumup_api_key' => '',
        'sumup_merchant_code' => '',
    ];
}

/**
 * @deprecated Solo amministratori tramite wp-admin.
 *
 * @return true|WP_Error|array
 */
function opencomune_save_partner_payment_settings($user_id, array $input) {
    if (!current_user_can('manage_options')) {
        return new WP_Error(
            'forbidden',
            __('I pagamenti online sono gestiti globalmente dall\'amministratore del sito (SumUp).', 'opencomune')
        );
    }

    return opencomune_save_site_sumup_payment_settings($input);
}

/**
 * Config effettiva per un pagamento: sempre SumUp globale (solo admin).
 */
function opencomune_resolve_payment_gateway_config_for_experience($experience_id, $gateway = '') {
    $site = opencomune_get_site_payment_gateway_config();
    $gateway = opencomune_get_global_payment_gateway_slug();

    return opencomune_flatten_gateway_config($site, $gateway);
}

function opencomune_flatten_gateway_config(array $config, $gateway) {
    $gateway = opencomune_get_global_payment_gateway_slug();
    $credentials = $config['sumup'] ?? [];

    return [
        'source' => 'site',
        'gateway' => $gateway,
        'enabled' => !empty($config['enabled']),
        'credentials' => $credentials,
    ];
}

add_action('after_setup_theme', static function () {
    if (get_option('opencomune_default_payment_gateway', '') !== 'sumup') {
        update_option('opencomune_default_payment_gateway', 'sumup');
    }
}, 15);
