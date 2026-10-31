<?php
/* Template Name: Profilo Partner AI */

if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai_settings'])) {
    $user_id = get_current_user_id();
    opencomune_save_partner_ai_settings($user_id, $_POST);
    $success_message = __('Impostazioni AI aggiornate.', 'opencomune');
}

$user_id = get_current_user_id();
$ai_settings = opencomune_get_partner_ai_settings($user_id);

opencomune_enqueue_dashboard_layout_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-profilo-partner-page p-6">
            <div class="mb-8">
                <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Traduzione automatica', 'opencomune'); ?></h1>
                <p class="text-gray-600"><?php esc_html_e('API key per la traduzione automatica delle esperienze in inglese.', 'opencomune'); ?></p>
                
            </div>

            <?php if ($error_message !== '') : ?>
                <div class="error-message">
                    <i class="bi bi-exclamation-triangle mr-2"></i>
                    <?php echo esc_html($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($success_message !== '') : ?>
                <div class="success-message">
                    <i class="bi bi-check-circle mr-2"></i>
                    <?php echo esc_html($success_message); ?>
                </div>
            <?php endif; ?>

            <?php
            get_template_part('template-parts/partner-ai-settings', 'form', [
                'ai_settings' => $ai_settings,
            ]);
            ?>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
