<?php
$tpl_args = isset($args) && is_array($args) ? $args : [];
$settings = isset($tpl_args['settings']) && is_array($tpl_args['settings']) ? $tpl_args['settings'] : opencomune_get_site_sumup_settings_for_form();
$has_api_key = !empty($settings['has_api_key']);
?>

<form method="post" class="oc-profilo-partner-form oc-sumup-settings-form">
    <?php wp_nonce_field('opencomune_site_sumup_settings', 'opencomune_site_sumup_nonce'); ?>
    <input type="hidden" name="save_site_sumup_settings" value="1">

    <div class="form-section">
        <h2 class="section-title">
            <i class="bi bi-credit-card mr-2" aria-hidden="true"></i>
            <?php esc_html_e('SumUp', 'opencomune'); ?>
        </h2>

        <div class="grid grid-cols-1 gap-6 max-w-2xl">
            <div>
                <label class="form-label flex items-center gap-2">
                    <input type="checkbox" name="opencomune_site_payments_enabled" value="1" <?php checked(!empty($settings['payments_enabled'])); ?>>
                    <?php esc_html_e('Abilita pagamenti online SumUp sul sito', 'opencomune'); ?>
                </label>
                <p class="text-sm text-gray-500 mt-1 mb-0"><?php esc_html_e('Le esperienze con pagamento online obbligatorio o opzionale useranno questo account.', 'opencomune'); ?></p>
            </div>

            <div>
                <label for="sumup_api_key" class="form-label"><?php esc_html_e('API Key (segreta)', 'opencomune'); ?></label>
                <input type="password" id="sumup_api_key" name="sumup_api_key" class="form-input" autocomplete="off"
                       placeholder="<?php echo esc_attr($has_api_key ? '••••••••••••••••' : ''); ?>">
                <p class="text-sm text-gray-500 mt-1 mb-0">
                    <?php esc_html_e('Chiave sup_sk_… (non sup_pk_). Lascia vuoto per mantenere la chiave già salvata.', 'opencomune'); ?>
                    <a href="https://developer.sumup.com/tools/authorization/api-keys/" target="_blank" rel="noopener noreferrer" class="text-blue-700"><?php esc_html_e('Documentazione', 'opencomune'); ?></a>
                </p>
            </div>

            <div>
                <label for="sumup_merchant_code" class="form-label"><?php esc_html_e('Merchant Code', 'opencomune'); ?></label>
                <input type="text" id="sumup_merchant_code" name="sumup_merchant_code" class="form-input"
                       value="<?php echo esc_attr($settings['sumup_merchant_code']); ?>" autocomplete="off">
            </div>

            <div>
                <label for="sumup_environment" class="form-label"><?php esc_html_e('Ambiente', 'opencomune'); ?></label>
                <select id="sumup_environment" name="sumup_environment" class="form-input">
                    <option value="test" <?php selected($settings['sumup_environment'], 'test'); ?>><?php esc_html_e('Test', 'opencomune'); ?></option>
                    <option value="live" <?php selected($settings['sumup_environment'], 'live'); ?>><?php esc_html_e('Produzione', 'opencomune'); ?></option>
                </select>
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="btn-primary"><?php esc_html_e('Salva impostazioni', 'opencomune'); ?></button>
        </div>
    </div>
</form>
