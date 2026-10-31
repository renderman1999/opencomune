<?php
$tpl_args = (isset($args) && is_array($args)) ? $args : [];
$ai_settings = isset($tpl_args['ai_settings']) && is_array($tpl_args['ai_settings'])
    ? $tpl_args['ai_settings']
    : opencomune_get_partner_ai_settings(get_current_user_id());
$provider_labels = opencomune_get_ai_provider_labels();
$selected_provider = isset($ai_settings['provider']) ? sanitize_key($ai_settings['provider']) : 'openai';
if (!isset($provider_labels[$selected_provider])) {
    $selected_provider = 'openai';
}
$ai_enabled = !empty($ai_settings['enabled']);
?>
<form method="POST" class="oc-profilo-partner-payment-form" id="partner-ai-settings-form">
    <div class="form-section">
        <h2 class="section-title">
            <i class="bi bi-stars mr-2"></i>
            <?php esc_html_e('Traduzione automatica (AI)', 'opencomune'); ?>
        </h2>
        <p class="text-sm text-gray-600 mb-4">
            <?php esc_html_e('Configura le API key per tradurre le esperienze in inglese dalla dashboard. Le chiavi restano associate al tuo account partner.', 'opencomune'); ?>
        </p>

        <div class="oc-dashboard-trans-modal__ai oc-partner-ai-enable-bar mb-4">
            <label class="oc-dashboard-trans-modal__ai-switch" for="ai_translation_enabled">
                <input
                    type="checkbox"
                    id="ai_translation_enabled"
                    name="ai_translation_enabled"
                    value="1"
                    class="oc-dashboard-trans-modal__ai-input"
                    <?php checked($ai_enabled); ?>
                >
                <span class="oc-dashboard-trans-modal__ai-track" aria-hidden="true">
                    <span class="oc-dashboard-trans-modal__ai-thumb"></span>
                </span>
                <span class="oc-dashboard-trans-modal__ai-label"><?php esc_html_e('Usa la traduzione automatica tramite AI', 'opencomune'); ?></span>
            </label>
        </div>

        <div id="partner-ai-options" class="<?php echo $ai_enabled ? '' : 'hidden'; ?>">
        <div class="oc-partner-gateway-picker-wrap mb-4">
            <p class="form-label" id="partner-ai-provider-label"><?php esc_html_e('Provider AI', 'opencomune'); ?></p>
            <input type="hidden" name="ai_provider" id="ai_provider" value="<?php echo esc_attr($selected_provider); ?>">
            <ul class="oc-partner-gateway-picker oc-partner-gateway-picker--inline" role="list" aria-labelledby="partner-ai-provider-label">
                <?php foreach ($provider_labels as $key => $label) :
                    $is_selected = $selected_provider === $key;
                    $icon = $key === 'openai' ? 'bi-cpu' : 'bi-lightning-charge';
                    $desc = $key === 'openai'
                        ? __('Modelli OpenAI (es. GPT-4o mini).', 'opencomune')
                        : __('API compatibile OpenAI su DeepSeek.', 'opencomune');
                    ?>
                    <li>
                        <button
                            type="button"
                            class="oc-partner-gateway-picker__card<?php echo $is_selected ? ' is-selected' : ''; ?>"
                            data-ai-provider="<?php echo esc_attr($key); ?>"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>"
                        >
                            <span class="oc-partner-gateway-picker__icon" aria-hidden="true">
                                <i class="bi <?php echo esc_attr($icon); ?>"></i>
                            </span>
                            <span class="oc-partner-gateway-picker__text">
                                <span class="oc-partner-gateway-picker__title"><?php echo esc_html($label); ?></span>
                                <span class="oc-partner-gateway-picker__desc"><?php echo esc_html($desc); ?></span>
                            </span>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="oc-partner-gateway-config">
            <div class="partner-gateway-fields partner-ai-provider-fields<?php echo $selected_provider !== 'openai' ? ' hidden' : ''; ?>" data-ai-provider-panel="openai">
                <div>
                    <label for="openai_api_key" class="form-label"><?php esc_html_e('OpenAI API Key', 'opencomune'); ?></label>
                    <input type="password" id="openai_api_key" name="openai_api_key" class="form-input" autocomplete="off"
                           value="<?php echo esc_attr($ai_settings['openai_api_key']); ?>"
                           placeholder="sk-...">
                </div>
            </div>
            <div class="partner-gateway-fields partner-ai-provider-fields<?php echo $selected_provider !== 'deepseek' ? ' hidden' : ''; ?>" data-ai-provider-panel="deepseek">
                <div>
                    <label for="deepseek_api_key" class="form-label"><?php esc_html_e('DeepSeek API Key', 'opencomune'); ?></label>
                    <input type="password" id="deepseek_api_key" name="deepseek_api_key" class="form-input" autocomplete="off"
                           value="<?php echo esc_attr($ai_settings['deepseek_api_key']); ?>"
                           placeholder="sk-...">
                </div>
            </div>
        </div>
        <p class="text-sm text-gray-500 mt-3">
            <?php esc_html_e('Il provider selezionato sopra viene usato per «Traduci automaticamente» nel modal traduzioni.', 'opencomune'); ?>
        </p>
        </div>

        <div class="oc-profilo-partner-payment-actions">
            <button type="submit" name="save_ai_settings" class="btn-primary">
                <?php esc_html_e('Salva impostazioni AI', 'opencomune'); ?>
            </button>
        </div>
    </div>
</form>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('partner-ai-settings-form');
    const enableCheckbox = document.getElementById('ai_translation_enabled');
    const optionsWrap = document.getElementById('partner-ai-options');
    const hidden = document.getElementById('ai_provider');
    const cards = form ? form.querySelectorAll('.oc-partner-gateway-picker__card[data-ai-provider]') : [];
    const panels = form ? form.querySelectorAll('[data-ai-provider-panel]') : [];

    function syncAiOptionsVisibility() {
        if (!optionsWrap || !enableCheckbox) {
            return;
        }
        optionsWrap.classList.toggle('hidden', !enableCheckbox.checked);
    }

    if (enableCheckbox) {
        enableCheckbox.addEventListener('change', syncAiOptionsVisibility);
    }

    function selectProvider(value) {
        const provider = value || 'openai';
        if (hidden) {
            hidden.value = provider;
        }
        cards.forEach(function (card) {
            const active = card.getAttribute('data-ai-provider') === provider;
            card.classList.toggle('is-selected', active);
            card.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            panel.classList.toggle('hidden', panel.getAttribute('data-ai-provider-panel') !== provider);
        });
    }

    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            selectProvider(card.getAttribute('data-ai-provider'));
        });
    });
});
</script>
