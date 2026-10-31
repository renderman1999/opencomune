<?php
/* Template Name: Dashboard Esperienze Partner */

if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$oc_dashboard_is_admin = opencomune_user_can_manage_all_esperienze();

opencomune_enqueue_dashboard_esperienze_assets();
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <div>
                    <h1 class="oc-dashboard-section__title text-2xl mb-1">
                        <?php
                        echo $oc_dashboard_is_admin
                            ? esc_html__('Esperienze nel sistema', 'opencomune')
                            : esc_html__('Le tue esperienze', 'opencomune');
                        ?>
                    </h1>
                    <p class="text-gray-600 mb-0 text-sm">
                        <?php esc_html_e('Cerca, modifica, pubblica e gestisci le traduzioni.', 'opencomune'); ?>
                    </p>
                </div>
                <a href="<?php echo esc_url(home_url('/nuova-esperienza/')); ?>" class="oc-dashboard-create-btn">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    <?php esc_html_e('Nuova esperienza', 'opencomune'); ?>
                </a>
            </div>

            <div class="oc-dashboard-esperienze-toolbar flex flex-wrap items-center gap-3 mb-4">
                <select id="filterStatus" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value=""><?php esc_html_e('Tutti gli stati', 'opencomune'); ?></option>
                    <option value="publish"><?php esc_html_e('Pubblicate', 'opencomune'); ?></option>
                    <option value="draft"><?php esc_html_e('Bozza', 'opencomune'); ?></option>
                    <option value="pending"><?php esc_html_e('In revisione', 'opencomune'); ?></option>
                </select>
                <select id="filterCategoria" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value=""><?php esc_html_e('Tutte le categorie', 'opencomune'); ?></option>
                </select>
                <button id="refreshData" type="button" class="btn-primary whitespace-nowrap">
                    <i class="bi bi-arrow-clockwise mr-2" aria-hidden="true"></i>
                    <?php esc_html_e('Aggiorna', 'opencomune'); ?>
                </button>
            </div>

            <div class="oc-dashboard-table-wrap oc-dashboard-esperienze-dt p-6">
                <table id="esperienzeTable" class="oc-dashboard-table display w-full">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('ID', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Immagine', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Titolo', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Categoria', 'opencomune'); ?></th>
                            <?php if ($oc_dashboard_is_admin) : ?>
                                <th scope="col"><?php esc_html_e('Autore', 'opencomune'); ?></th>
                            <?php endif; ?>
                            <th scope="col"><?php esc_html_e('Traduzione', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Azioni', 'opencomune'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </main>
</div>

<?php
get_template_part('template-parts/dashboard', 'qr-modal');
get_template_part('template-parts/dashboard', 'translations-modal');
?>

<div id="calendarModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto mx-auto z-50 p-10" style="display: none; max-width: 50vw !important;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/3 shadow-lg rounded-md bg-white" style="padding: 20px !important;">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="calendarModalTitle"><?php esc_html_e('Gestione Calendario', 'opencomune'); ?></h3>
                <button type="button" onclick="closeCalendarModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-xl" aria-hidden="true"></i>
                </button>
            </div>
            <div class="space-y-4">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-sm text-blue-900">
                        <?php esc_html_e('Il calendario si configura solo dalla pagina Modifica esperienza (regole di disponibilità).', 'opencomune'); ?>
                    </p>
                    <a id="calendarEditLink" href="#" class="inline-flex items-center mt-3 text-sm font-semibold text-blue-700 hover:text-blue-900">
                        <i class="bi bi-pencil-square mr-1" aria-hidden="true"></i>
                        <?php esc_html_e('Apri modifica esperienza', 'opencomune'); ?>
                    </a>
                </div>
                <div>
                    <h4 class="font-semibold mb-3"><?php esc_html_e('Anteprima prossime date', 'opencomune'); ?></h4>
                    <div id="calendarEventsList" class="space-y-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
