<?php
/**
 * Form impostazioni pagamento partner (checkout esperienze).
 */
$tpl_args = (isset($args) && is_array($args)) ? $args : [];
$partner_payment = isset($tpl_args['partner_payment']) && is_array($tpl_args['partner_payment'])
    ? $tpl_args['partner_payment']
    : opencomune_get_partner_payment_settings(get_current_user_id());
$gateway_labels = isset($tpl_args['gateway_labels']) && is_array($tpl_args['gateway_labels'])
    ? $tpl_args['gateway_labels']
    : opencomune_get_payment_gateway_labels();

$gateway_icons = [
    'paypal' => 'bi-paypal',
    'nexi' => 'bi-credit-card-2-front',
    'sumup' => 'bi-bag-check',
];

$gateway_descriptions = [
    'paypal' => __('Accetta pagamenti con il tuo account PayPal Business.', 'opencomune'),
    'nexi' => __('Carte di credito e debito tramite Nexi XPay.', 'opencomune'),
    'sumup' => __('Pagamenti con il tuo account merchant SumUp.', 'opencomune'),
];

$selected_gateway = isset($partner_payment['default_gateway']) ? sanitize_key($partner_payment['default_gateway']) : 'paypal';
if (!isset($gateway_labels[$selected_gateway])) {
    $selected_gateway = (string) array_key_first($gateway_labels);
}
?>
<form method="POST" class="oc-profilo-partner-payment-form" id="partner-payment-settings-form">
    <div class="form-section">
        <h2 class="section-title">
            <i class="bi bi-credit-card mr-2"></i>
            <?php esc_html_e('Pagamenti online (checkout esperienze)', 'opencomune'); ?>
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            <?php esc_html_e('Puoi attivare un solo sistema di pagamento per le tue esperienze (PayPal, Nexi o SumUp). Le credenziali si collegano al checkout quando nell’esperienza è attivo il pagamento online.', 'opencomune'); ?>
        </p>
        <label class="flex items-start gap-2 mb-4">
            <input type="checkbox" id="use_site_defaults" name="use_site_defaults" value="1" <?php checked(!empty($partner_payment['use_site_defaults'])); ?>>
            <span>
                <?php esc_html_e('Usa il gateway configurato a livello portale', 'opencomune'); ?>
                <span class="block text-xs text-gray-500 font-normal mt-1"><?php esc_html_e('Consigliato se non hai un conto merchant dedicato.', 'opencomune'); ?></span>
            </span>
        </label>
        <div id="partner-payment-custom" class="border-t border-gray-200 pt-4 mt-4">
            <label class="flex items-start gap-2 mb-4">
                <input type="checkbox" id="payments_enabled" name="payments_enabled" value="1" <?php checked(!empty($partner_payment['payments_enabled'])); ?>>
                <span><?php esc_html_e('Usa il mio account di pagamento (un solo provider)', 'opencomune'); ?></span>
            </label>
            <div class="oc-partner-gateway-picker-wrap" id="partner-gateway-select-wrap">
                <p class="form-label" id="partner-gateway-picker-label"><?php esc_html_e('Provider di pagamento', 'opencomune'); ?></p>
                <input type="hidden" name="default_gateway" id="default_gateway" value="<?php echo esc_attr($selected_gateway); ?>">
                <ul class="oc-partner-gateway-picker" role="list" aria-labelledby="partner-gateway-picker-label">
                    <?php foreach ($gateway_labels as $key => $label) :
                        $is_selected = $selected_gateway === $key;
                        $icon = $gateway_icons[$key] ?? 'bi-wallet2';
                        $description = $gateway_descriptions[$key] ?? '';
                        ?>
                        <li>
                            <button
                                type="button"
                                class="oc-partner-gateway-picker__card<?php echo $is_selected ? ' is-selected' : ''; ?>"
                                data-gateway="<?php echo esc_attr($key); ?>"
                                aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                            >
                                <span class="oc-partner-gateway-picker__icon" aria-hidden="true">
                                    <i class="bi <?php echo esc_attr($icon); ?>"></i>
                                </span>
                                <span class="oc-partner-gateway-picker__text">
                                    <span class="oc-partner-gateway-picker__title"><?php echo esc_html($label); ?></span>
                                    <?php if ($description !== '') : ?>
                                        <span class="oc-partner-gateway-picker__desc"><?php echo esc_html($description); ?></span>
                                    <?php endif; ?>
                                </span>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="oc-partner-gateway-config">
            <div class="partner-gateway-fields<?php echo $selected_gateway !== 'paypal' ? ' hidden' : ''; ?>" data-gateway="paypal">
                <div>
                    <label class="form-label">PayPal Client ID</label>
                    <input type="text" name="paypal_client_id" class="form-input" value="<?php echo esc_attr($partner_payment['paypal_client_id']); ?>">
                </div>
                <div>
                    <label class="form-label">PayPal Secret</label>
                    <input type="password" name="paypal_client_secret" class="form-input" value="<?php echo esc_attr($partner_payment['paypal_client_secret']); ?>" autocomplete="off">
                </div>
                <div>
                    <label class="form-label"><?php esc_html_e('Ambiente PayPal', 'opencomune'); ?></label>
                    <select name="paypal_environment" class="form-input">
                        <option value="sandbox" <?php selected($partner_payment['paypal_environment'], 'sandbox'); ?>><?php esc_html_e('Sandbox (test)', 'opencomune'); ?></option>
                        <option value="live" <?php selected($partner_payment['paypal_environment'], 'live'); ?>><?php esc_html_e('Live', 'opencomune'); ?></option>
                    </select>
                </div>
            </div>
            <div class="partner-gateway-fields<?php echo $selected_gateway !== 'nexi' ? ' hidden' : ''; ?>" data-gateway="nexi">
                <div>
                    <label class="form-label">Nexi Merchant ID</label>
                    <input type="text" name="nexi_merchant_id" class="form-input" value="<?php echo esc_attr($partner_payment['nexi_merchant_id']); ?>">
                </div>
                <div>
                    <label class="form-label">Nexi API Key</label>
                    <input type="password" name="nexi_api_key" class="form-input" value="<?php echo esc_attr($partner_payment['nexi_api_key']); ?>" autocomplete="off">
                </div>
                <div>
                    <label class="form-label"><?php esc_html_e('Ambiente Nexi', 'opencomune'); ?></label>
                    <select name="nexi_environment" class="form-input">
                        <option value="test" <?php selected($partner_payment['nexi_environment'], 'test'); ?>><?php esc_html_e('Test', 'opencomune'); ?></option>
                        <option value="live" <?php selected($partner_payment['nexi_environment'], 'live'); ?>><?php esc_html_e('Live', 'opencomune'); ?></option>
                    </select>
                </div>
            </div>
            <div class="partner-gateway-fields<?php echo $selected_gateway !== 'sumup' ? ' hidden' : ''; ?>" data-gateway="sumup">
                <div>
                    <label class="form-label">SumUp API Key (chiave segreta)</label>
                    <input type="password" name="sumup_api_key" class="form-input" value="" placeholder="<?php echo esc_attr($partner_payment['sumup_api_key'] !== '' ? '••••••••••••' : ''); ?>" autocomplete="off">
                    <p class="text-sm text-gray-500 mt-1"><?php esc_html_e('Usa la chiave segreta che inizia con sup_sk_ (non la chiave pubblica sup_pk_). SumUp → Impostazioni → Per sviluppatori → Toolkit → API Keys.', 'opencomune'); ?></p>
                </div>
                <div>
                    <label class="form-label">SumUp Merchant Code</label>
                    <input type="text" name="sumup_merchant_code" class="form-input" value="<?php echo esc_attr($partner_payment['sumup_merchant_code']); ?>">
                </div>
            </div>
            </div>
        </div>
        <div class="oc-profilo-partner-payment-actions">
            <button type="submit" name="save_payment_settings" class="btn-primary">
                <?php esc_html_e('Salva impostazioni pagamento', 'opencomune'); ?>
            </button>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const useSiteDefaults = document.getElementById('use_site_defaults');
    const paymentsEnabled = document.getElementById('payments_enabled');
    const customWrap = document.getElementById('partner-payment-custom');
    const gatewayHidden = document.getElementById('default_gateway');
    const gatewayCards = document.querySelectorAll('.oc-partner-gateway-picker__card');
    const gatewayPanels = document.querySelectorAll('.partner-gateway-fields');

    function selectGateway(gateway) {
        const value = gateway || 'paypal';
        if (gatewayHidden) {
            gatewayHidden.value = value;
        }
        gatewayCards.forEach(function (card) {
            const isActive = card.getAttribute('data-gateway') === value;
            card.classList.toggle('is-selected', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        gatewayPanels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-gateway') !== value);
        });
    }

    function syncPartnerPaymentUi() {
        const siteMode = useSiteDefaults && useSiteDefaults.checked;
        if (customWrap) {
            customWrap.style.opacity = siteMode ? '0.45' : '1';
            customWrap.querySelectorAll('input, select, button').forEach(function (el) {
                if (el === useSiteDefaults || el.type === 'submit' || el.type === 'hidden') {
                    return;
                }
                el.disabled = siteMode;
            });
        }
        gatewayCards.forEach(function (card) {
            card.disabled = !!siteMode;
        });
        if (gatewayHidden) {
            selectGateway(gatewayHidden.value);
        }
    }

    gatewayCards.forEach(function (card) {
        card.addEventListener('click', function () {
            if (card.disabled) {
                return;
            }
            selectGateway(card.getAttribute('data-gateway'));
        });
    });

    if (useSiteDefaults) {
        useSiteDefaults.addEventListener('change', syncPartnerPaymentUi);
    }
    if (paymentsEnabled) {
        paymentsEnabled.addEventListener('change', syncPartnerPaymentUi);
    }
    syncPartnerPaymentUi();
});
</script>
