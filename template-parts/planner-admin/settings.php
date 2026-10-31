<?php
if (!defined('ABSPATH')) {
    exit;
}
$data = opencomune_planner_get_admin_view_data();
?>
<form method="post" class="oc-profilo-partner-form max-w-xl" action="<?php echo esc_url(opencomune_planner_admin_form_action('settings')); ?>">
    <?php wp_nonce_field('opencomune_planner_settings'); ?>
    <input type="hidden" name="oc_planner_action" value="save_settings">
    <div class="form-section">
        <h2 class="section-title"><?php esc_html_e('Impostazioni wizard', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="planner_enabled" value="1" <?php checked($data['enabled']); ?>>
                    <span><?php esc_html_e('Wizard attivo sul sito pubblico', 'opencomune'); ?></span>
                </label>
            </div>
            <div>
                <label class="form-label" for="planner_max_days"><?php esc_html_e('Durata massima (giorni)', 'opencomune'); ?></label>
                <input class="form-input max-w-[8rem]" type="number" min="1" max="7" id="planner_max_days" name="planner_max_days" value="<?php echo esc_attr((string) $data['max_days']); ?>">
            </div>
            <div>
                <label class="form-label" for="planner_notify_email"><?php esc_html_e('Email notifiche richieste', 'opencomune'); ?></label>
                <input class="form-input" type="email" id="planner_notify_email" name="planner_notify_email" value="<?php echo esc_attr($data['notify_email']); ?>">
            </div>
        </div>
    </div>
    <div class="oc-profilo-partner-actions">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva impostazioni', 'opencomune'); ?></button>
    </div>
</form>
