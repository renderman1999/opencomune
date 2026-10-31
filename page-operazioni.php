<?php
/* Template Name: Dashboard Operazioni */

if (!opencomune_user_can_manage_all_esperienze()) {
    wp_safe_redirect(home_url('/dashboard-partner/'));
    exit;
}

opencomune_enqueue_dashboard_operations_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-operations-page p-6">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="oc-dashboard-section__title text-2xl mb-1"><?php esc_html_e('Operazioni', 'opencomune'); ?></h1>
                    <p class="text-gray-600 mb-0"><?php esc_html_e('Pagamenti, accessi, annullamenti, rimborsi e altre attività della piattaforma.', 'opencomune'); ?></p>
                </div>
                <button type="button" id="operationsRefreshBtn" class="btn-primary whitespace-nowrap">
                    <i class="bi bi-arrow-clockwise mr-2" aria-hidden="true"></i>
                    <?php esc_html_e('Aggiorna', 'opencomune'); ?>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <select id="filterOperationsDays" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="30"><?php esc_html_e('Ultimi 30 giorni', 'opencomune'); ?></option>
                    <option value="90" selected><?php esc_html_e('Ultimi 90 giorni', 'opencomune'); ?></option>
                    <option value="365"><?php esc_html_e('Ultimo anno', 'opencomune'); ?></option>
                    <option value="0"><?php esc_html_e('Tutto', 'opencomune'); ?></option>
                </select>
                <select id="filterOperationsAction" class="px-4 py-2 border border-gray-300 rounded-lg text-sm min-w-[220px]">
                    <option value=""><?php esc_html_e('Tutte le operazioni', 'opencomune'); ?></option>
                    <option value="payment_received"><?php esc_html_e('Pagamenti ricevuti', 'opencomune'); ?></option>
                    <option value="payment_refund"><?php esc_html_e('Rimborsi', 'opencomune'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Annullamenti', 'opencomune'); ?></option>
                    <option value="partner_login"><?php esc_html_e('Accessi partner', 'opencomune'); ?></option>
                    <option value="admin_login"><?php esc_html_e('Accessi amministratore', 'opencomune'); ?></option>
                </select>
                <select id="filterOperationsPartner" class="px-4 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                    <option value=""><?php esc_html_e('Tutti i partner', 'opencomune'); ?></option>
                </select>
            </div>

            <div id="operationsLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <p class="mt-2 text-gray-500"><?php esc_html_e('Caricamento registro...', 'opencomune'); ?></p>
            </div>

            <div id="operationsContent" style="display: none;">
                <p class="text-sm text-gray-600 mb-4" id="operationsCountLabel"></p>
                <div id="operationsList"></div>
            </div>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
