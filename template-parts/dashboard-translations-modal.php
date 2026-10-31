<div id="ocDashboardTranslationsModal" class="oc-dashboard-trans-modal" hidden>
    <div class="oc-dashboard-trans-modal__backdrop" data-close-trans-modal></div>
    <div class="oc-dashboard-trans-modal__panel" role="dialog" aria-modal="true" aria-labelledby="ocDashboardTranslationsTitle">
        <div class="oc-dashboard-trans-modal__header">
            <div>
                <h3 id="ocDashboardTranslationsTitle"><?php esc_html_e('Traduzioni inglese', 'opencomune'); ?></h3>
                <p id="ocDashboardTranslationsSubtitle" class="oc-dashboard-trans-modal__subtitle"></p>
            </div>
            <button type="button" class="oc-dashboard-trans-modal__close" data-close-trans-modal aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <div id="ocDashboardTranslationsAiBar" class="oc-dashboard-trans-modal__ai" hidden>
            <label class="oc-dashboard-trans-modal__ai-switch" for="ocDashboardTranslationsAiToggle">
                <input type="checkbox" id="ocDashboardTranslationsAiToggle" class="oc-dashboard-trans-modal__ai-input">
                <span class="oc-dashboard-trans-modal__ai-track" aria-hidden="true">
                    <span class="oc-dashboard-trans-modal__ai-thumb"></span>
                </span>
                <span class="oc-dashboard-trans-modal__ai-label"><?php esc_html_e('Usa AI per la traduzione', 'opencomune'); ?></span>
            </label>
        </div>
        <p class="oc-dashboard-trans-modal__hint"><?php esc_html_e('Compila i campi in inglese. Il testo italiano e mostrato solo come riferimento.', 'opencomune'); ?></p>
        <form id="ocDashboardTranslationsForm" class="oc-dashboard-trans-modal__body">
            <input type="hidden" name="post_id" id="ocDashboardTranslationsPostId" value="">
            <div id="ocDashboardTranslationsFields" class="oc-dashboard-trans-modal__fields"></div>
            <div class="oc-dashboard-trans-modal__footer">
                <button type="button" class="oc-dashboard-btn oc-dashboard-btn--secondary" data-close-trans-modal><?php esc_html_e('Chiudi', 'opencomune'); ?></button>
                <button type="submit" class="oc-dashboard-btn oc-dashboard-btn--primary" id="ocDashboardTranslationsSaveBtn">
                    <?php esc_html_e('Salva traduzioni', 'opencomune'); ?>
                </button>
            </div>
        </form>
        <div id="ocDashboardTranslationsLoading" class="oc-dashboard-trans-modal__loading" hidden>
            <div class="oc-dashboard-trans-modal__spinner" aria-hidden="true"></div>
        </div>
    </div>
</div>
