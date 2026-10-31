<?php
/* Template Name: Profilo Partner Pagamenti */

if (!opencomune_user_can_manage_all_esperienze()) {
    wp_safe_redirect(home_url('/profilo-partner/'));
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_site_sumup_settings'])) {
    if (
        !isset($_POST['opencomune_site_sumup_nonce'])
        || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_site_sumup_nonce'])), 'opencomune_site_sumup_settings')
    ) {
        $error_message = __('Sessione scaduta. Riprova.', 'opencomune');
    } else {
        $save_result = opencomune_save_site_sumup_payment_settings($_POST);
        if (is_wp_error($save_result)) {
            $error_message = $save_result->get_error_message();
        } else {
            $success_message = __('Impostazioni SumUp salvate.', 'opencomune');
        }
    }
}

$settings = opencomune_get_site_sumup_settings_for_form();

opencomune_enqueue_dashboard_layout_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-profilo-partner-page p-6">
            <div class="mb-8">
                <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Pagamenti SumUp (sito)', 'opencomune'); ?></h1>
                <p class="text-gray-600 mb-0"><?php esc_html_e('Configurazione globale del checkout per tutte le esperienze. Solo gli amministratori possono modificarla.', 'opencomune'); ?></p>
                <p class="mt-2">
                    <a href="<?php echo esc_url(home_url('/dashboard-partner/')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
                        <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
                        <?php esc_html_e('Torna alla dashboard', 'opencomune'); ?>
                    </a>
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

            <?php
            get_template_part('template-parts/dashboard', 'site-sumup-settings', [
                'settings' => $settings,
            ]);
            ?>

            <div class="oc-booking-detail__card mt-6">
                <h2 class="oc-booking-detail__card-title"><?php esc_html_e('URL di ritorno checkout', 'opencomune'); ?></h2>
                <dl class="oc-booking-detail__meta">
                    <dt><?php esc_html_e('Pagamento riuscito', 'opencomune'); ?></dt>
                    <dd><code class="oc-booking-detail__code"><?php echo esc_html(home_url('/pagamento-confermato/')); ?></code></dd>
                    <dt><?php esc_html_e('Pagamento annullato', 'opencomune'); ?></dt>
                    <dd><code class="oc-booking-detail__code"><?php echo esc_html(home_url('/pagamento-annullato/')); ?></code></dd>
                </dl>
            </div>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
