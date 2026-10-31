<?php
/* Template Name: Dashboard Commissioni */

if (!opencomune_user_can_manage_all_esperienze()) {
    wp_safe_redirect(home_url('/dashboard-partner/'));
    exit;
}

$success_message = '';
$error_message = '';
$commission_percent = opencomune_get_payment_commission_percent();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_commission_settings'])) {
    if (
        !isset($_POST['opencomune_commission_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_commission_nonce'])), 'opencomune_commission_settings')
    ) {
        $error_message = __('Sessione scaduta. Riprova.', 'opencomune');
    } else {
        $raw = isset($_POST['commission_percent']) ? wp_unslash($_POST['commission_percent']) : '0';
        $save_result = opencomune_save_payment_commission_percent($raw);
        if (is_wp_error($save_result)) {
            $error_message = $save_result->get_error_message();
        } else {
            $commission_percent = opencomune_get_payment_commission_percent();
            $success_message = __('Impostazioni commissioni salvate.', 'opencomune');
        }
    }
}

$example_gross = 100.0;
$example_commission = opencomune_calculate_payment_commission_amount($example_gross, $commission_percent);
$example_net = round($example_gross - $example_commission, 2);

opencomune_enqueue_dashboard_layout_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-profilo-partner-page p-6">
            <div class="mb-8">
                <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Commissioni', 'opencomune'); ?></h1>
                <p class="text-gray-600 mb-0">
                    <?php esc_html_e('Percentuale trattenuta su ogni pagamento online completato. La commissione viene calcolata al momento della conferma del pagamento.', 'opencomune'); ?>
                </p>
                
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="error-message mb-4">
                    <i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message !== '') : ?>
                <div class="success-message mb-4">
                    <i class="bi bi-check-circle mr-2" aria-hidden="true"></i>
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="oc-profilo-partner-form max-w-xl">
                <?php wp_nonce_field('opencomune_commission_settings', 'opencomune_commission_nonce'); ?>
                <input type="hidden" name="save_commission_settings" value="1">

                <div class="form-section">
                    <label for="commission_percent" class="form-label"><?php esc_html_e('Percentuale commissione', 'opencomune'); ?></label>
                    <div class="flex items-center gap-2 max-w-xs">
                        <input type="number" id="commission_percent" name="commission_percent" class="form-input"
                               min="0" max="100" step="0.01"
                               value="<?php echo esc_attr(number_format($commission_percent, 2, '.', '')); ?>" required>
                        <span class="text-gray-600 font-medium">%</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2 mb-0">
                        <?php esc_html_e('Esempio su 100,00 EUR: commissione', 'opencomune'); ?>
                        <strong><?php echo esc_html(number_format_i18n($example_commission, 2)); ?> EUR</strong>,
                        <?php esc_html_e('netto partner', 'opencomune'); ?>
                        <strong><?php echo esc_html(number_format_i18n($example_net, 2)); ?> EUR</strong>.
                    </p>
                </div>

                <div class="mt-6">
                    <button type="submit" class="btn-primary"><?php esc_html_e('Salva', 'opencomune'); ?></button>
                </div>
            </form>

            <div class="oc-booking-detail__card mt-8 max-w-2xl">
                <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Come funziona', 'opencomune'); ?></h2>
                <ul class="text-sm text-gray-600 mb-0 pl-5 list-disc space-y-2">
                    <li><?php esc_html_e('La percentuale si applica al totale pagato dal cliente (ordine completato).', 'opencomune'); ?></li>
                    <li><?php esc_html_e('Importo commissione e netto sono salvati su ordine e prenotazione al momento del pagamento e non cambiano se modifichi la percentuale.', 'opencomune'); ?></li>
                    <li><?php esc_html_e('Modifiche successive alla percentuale non ricalcolano gli ordini già pagati.', 'opencomune'); ?></li>
                </ul>
            </div>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
