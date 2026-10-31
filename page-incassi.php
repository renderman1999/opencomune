<?php
/* Template Name: Dashboard Incassi */

if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$is_admin = opencomune_user_can_manage_all_esperienze();

opencomune_enqueue_dashboard_incassi_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-incassi-page p-6<?php echo $is_admin ? ' oc-incassi-page--admin' : ' oc-incassi-page--partner'; ?>">
            <div class="flex flex-wrap items-start justify-between gap-3 mb-6">
                <div>
                    <h1 class="oc-dashboard-section__title text-2xl mb-1"><?php esc_html_e('Incassi', 'opencomune'); ?></h1>
                    <p class="text-gray-600 mb-0">
                        <?php
                        if ($is_admin) {
                            esc_html_e('Pagamenti registrati, commissioni piattaforma e andamento nel tempo.', 'opencomune');
                        } else {
                            esc_html_e('Pagamenti delle tue esperienze, netto e commissioni piattaforma.', 'opencomune');
                        }
                        ?>
                    </p>
                </div>
                <button type="button" id="incassiRefreshBtn" class="btn-primary whitespace-nowrap">
                    <i class="bi bi-arrow-clockwise mr-2" aria-hidden="true"></i>
                    <?php esc_html_e('Aggiorna', 'opencomune'); ?>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <select id="filterIncassiDays" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="30"><?php esc_html_e('Ultimi 30 giorni', 'opencomune'); ?></option>
                    <option value="90" selected><?php esc_html_e('Ultimi 90 giorni', 'opencomune'); ?></option>
                    <option value="365"><?php esc_html_e('Ultimo anno', 'opencomune'); ?></option>
                    <option value="0"><?php esc_html_e('Tutto', 'opencomune'); ?></option>
                </select>
                <?php if ($is_admin) : ?>
                <select id="filterIncassiPartner" class="px-4 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                    <option value=""><?php esc_html_e('Tutti i partner', 'opencomune'); ?></option>
                </select>
                <?php endif; ?>
                <?php if (!$is_admin) : ?>
                <select id="filterIncassiExperience" class="px-4 py-2 border border-gray-300 rounded-lg text-sm min-w-[200px]">
                    <option value=""><?php esc_html_e('Tutte le esperienze', 'opencomune'); ?></option>
                </select>
                <?php endif; ?>
                <select id="filterIncassiStatus" class="px-4 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="settled" selected><?php esc_html_e('Pagamenti e rimborsi', 'opencomune'); ?></option>
                    <option value="completed"><?php esc_html_e('Solo completati', 'opencomune'); ?></option>
                    <option value="refunded"><?php esc_html_e('Solo rimborsi', 'opencomune'); ?></option>
                    <option value="all"><?php esc_html_e('Tutti gli stati', 'opencomune'); ?></option>
                    <option value="pending"><?php esc_html_e('In attesa', 'opencomune'); ?></option>
                    <option value="cancelled"><?php esc_html_e('Annullati ordine', 'opencomune'); ?></option>
                </select>
            </div>

            <div id="incassiLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <p class="mt-2 text-gray-500"><?php esc_html_e('Caricamento incassi...', 'opencomune'); ?></p>
            </div>

            <div id="incassiContent" style="display: none;">
                <div class="oc-incassi-stats mb-6">
                    <div class="oc-incassi-stat">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Pagamenti', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatCount">0</strong>
                    </div>
                    <div class="oc-incassi-stat oc-incassi-stat--refund">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Rimborsi', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatRefundCount">0</strong>
                        <span class="oc-incassi-stat__sub" id="incassiStatRefundTotal">0</span>
                    </div>
                    <div class="oc-incassi-stat">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Totale incassato', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatGross">0</strong>
                    </div>
                    <div class="oc-incassi-stat oc-incassi-stat--highlight">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Netto dopo rimborsi', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatNetAfter">0</strong>
                    </div>
                    <?php if ($is_admin) : ?>
                    <div class="oc-incassi-stat">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Guadagno commissioni', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatCommission">0</strong>
                    </div>
                    <div class="oc-incassi-stat">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Netto partner (lordo)', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatNet">0</strong>
                    </div>
                    <?php else : ?>
                    <div class="oc-incassi-stat">
                        <span class="oc-incassi-stat__label"><?php esc_html_e('Commissioni piattaforma', 'opencomune'); ?></span>
                        <strong class="oc-incassi-stat__value" id="incassiStatCommission">0</strong>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="oc-incassi-charts mb-6">
                    <div class="oc-incassi-chart-card">
                        <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Incassi per mese', 'opencomune'); ?></h2>
                        <canvas id="incassiChartMonthly" height="220" aria-label="<?php esc_attr_e('Grafico incassi mensili', 'opencomune'); ?>"></canvas>
                    </div>
                    <div class="oc-incassi-chart-card">
                        <h2 class="oc-booking-detail__card-title" id="incassiChartSecondaryTitle">
                            <?php
                            echo $is_admin
                                ? esc_html__('Commissioni per partner', 'opencomune')
                                : esc_html__('Netto per esperienza', 'opencomune');
                            ?>
                        </h2>
                        <canvas id="incassiChartPartners" height="220" aria-label="<?php esc_attr_e('Grafico riepilogo', 'opencomune'); ?>"></canvas>
                    </div>
                </div>

                <div id="incassiPaymentsList"></div>
            </div>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
