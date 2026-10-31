<?php
/* Template Name: Dashboard Categorie Esperienze */

if (!opencomune_esperienze_categories_can_manage()) {
    wp_safe_redirect(home_url());
    exit;
}

$edit_param = opencomune_esperienze_categories_get_edit_id();
$is_form = $edit_param !== null;
$edit_term = null;
$is_new = false;

if ($is_form) {
    if ($edit_param === 0) {
        $is_new = true;
    } else {
        $edit_term = opencomune_esperienze_categories_get_term_for_edit($edit_param);
        if ($edit_term === null) {
            $is_form = false;
        }
    }
}

$success_message = isset($_GET['oc_msg']) ? sanitize_text_field(wp_unslash($_GET['oc_msg'])) : '';
$error_message = isset($_GET['oc_err']) ? sanitize_text_field(wp_unslash($_GET['oc_err'])) : '';

$show_categories_list = !($is_form && ($is_new || $edit_term instanceof WP_Term));
if ($show_categories_list) {
    opencomune_enqueue_dashboard_esperienze_categories_assets(true);
} else {
    opencomune_enqueue_dashboard_esperienze_categories_form_assets();
}
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-profilo-partner-page oc-esperienze-categories-admin p-6">
            <?php if ($is_form && ($is_new || $edit_term instanceof WP_Term)) : ?>
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="oc-dashboard-section__title text-2xl mb-1">
                            <?php
                            echo $is_new
                                ? esc_html__('Nuova categoria', 'opencomune')
                                : esc_html__('Modifica categoria', 'opencomune');
                            ?>
                        </h1>
                        <p class="text-gray-600 mb-0 text-sm">
                            <?php esc_html_e('Le categorie compaiono nel tab Categoria e tag del form esperienza.', 'opencomune'); ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url(opencomune_get_esperienze_categories_dashboard_url()); ?>" class="oc-dashboard-btn oc-dashboard-btn--secondary">
                        <?php esc_html_e('Torna all’elenco', 'opencomune'); ?>
                    </a>
                </div>

                <?php if ($error_message !== '') : ?>
                    <div class="error-message mb-4">
                        <i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <?php
                $term_id = $edit_term instanceof WP_Term ? (int) $edit_term->term_id : 0;
                $name = $edit_term instanceof WP_Term ? $edit_term->name : '';
                $slug = $edit_term instanceof WP_Term ? $edit_term->slug : '';
                $name_en = $term_id > 0 ? (string) get_term_meta($term_id, 'name_en', true) : '';
                $image = $term_id > 0 ? opencomune_esperienza_category_get_image_url($term_id) : '';

                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? $name));
                    $slug = sanitize_title(wp_unslash($_POST['slug'] ?? $slug));
                    $name_en = sanitize_text_field(wp_unslash($_POST['name_en'] ?? $name_en));
                    if (isset($_POST['image'])) {
                        $image = esc_url_raw(wp_unslash($_POST['image']));
                    }
                }
                ?>

                <form method="post" class="oc-profilo-partner-form max-w-2xl" action="<?php echo esc_url(opencomune_get_esperienze_categories_dashboard_url()); ?>">
                    <?php wp_nonce_field('opencomune_esperienza_category_save'); ?>
                    <input type="hidden" name="oc_category_action" value="save">
                    <input type="hidden" name="term_id" value="<?php echo (int) $term_id; ?>">

                    <div class="form-section">
                        <h2 class="section-title">
                            <?php echo $is_new ? esc_html__('Dati categoria', 'opencomune') : esc_html__('Modifica dati', 'opencomune'); ?>
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="md:col-span-2">
                                <?php
                                if (!function_exists('opencomune_planner_admin_render_image_field')) {
                                    require_once get_template_directory() . '/inc/planner-admin-media.php';
                                }
                                opencomune_planner_admin_render_image_field([
                                    'input_id' => 'category_image',
                                    'input_name' => 'image',
                                    'label' => __('Immagine categoria', 'opencomune'),
                                    'value' => $image,
                                    'help' => __('Icona o foto usata in filtri, mappe e API. Consigliato formato quadrato.', 'opencomune'),
                                ]);
                                ?>
                            </div>
                            <div>
                                <label class="form-label" for="category_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                                <input class="form-input" id="category_name" name="name" type="text" required value="<?php echo esc_attr($name); ?>">
                            </div>
                            <div>
                                <label class="form-label" for="category_slug"><?php esc_html_e('Slug', 'opencomune'); ?></label>
                                <input class="form-input" id="category_slug" name="slug" type="text" value="<?php echo esc_attr($slug); ?>">
                                <p class="text-sm text-gray-500 mt-2 mb-0"><?php esc_html_e('Lascia vuoto per generarlo dal nome.', 'opencomune'); ?></p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label" for="category_name_en"><?php esc_html_e('Nome in inglese', 'opencomune'); ?></label>
                                <input class="form-input" id="category_name_en" name="name_en" type="text" value="<?php echo esc_attr($name_en); ?>">
                                <p class="text-sm text-gray-500 mt-2 mb-0"><?php esc_html_e('Usato nelle API e nelle versioni EN del sito.', 'opencomune'); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="oc-profilo-partner-actions">
                        <a href="<?php echo esc_url(opencomune_get_esperienze_categories_dashboard_url()); ?>" class="btn-secondary"><?php esc_html_e('Annulla', 'opencomune'); ?></a>
                        <button type="submit" class="btn-primary"><?php esc_html_e('Salva categoria', 'opencomune'); ?></button>
                    </div>
                </form>
            <?php else : ?>
                <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                    <div>
                        <h1 class="oc-dashboard-section__title text-2xl mb-1">
                            <?php esc_html_e('Categorie esperienze', 'opencomune'); ?>
                        </h1>
                        <p class="text-gray-600 mb-0 text-sm">
                            <?php esc_html_e('Gestisci le categorie selezionabili nel form esperienza (tab Categoria e tag).', 'opencomune'); ?>
                        </p>
                    </div>
                    <a href="<?php echo esc_url(opencomune_esperienze_categories_form_url(['cat_edit' => 'new'])); ?>" class="oc-dashboard-create-btn">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                        <?php esc_html_e('Nuova categoria', 'opencomune'); ?>
                    </a>
                </div>

                <?php if ($success_message !== '') : ?>
                    <div class="success-message mb-4">
                        <i class="bi bi-check-circle mr-2" aria-hidden="true"></i>
                        <?php echo esc_html($success_message); ?>
                    </div>
                <?php endif; ?>
                <?php if ($error_message !== '') : ?>
                    <div class="error-message mb-4">
                        <i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>
                        <?php echo esc_html($error_message); ?>
                    </div>
                <?php endif; ?>

                <div class="oc-dashboard-table-wrap oc-dashboard-esperienze-categories-dt p-6">
                    <table id="esperienzeCategoriesTable" class="oc-dashboard-table display w-full">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Immagine', 'opencomune'); ?></th>
                                <th scope="col"><?php esc_html_e('Nome', 'opencomune'); ?></th>
                                <th scope="col"><?php esc_html_e('Slug', 'opencomune'); ?></th>
                                <th scope="col"><?php esc_html_e('Nome EN', 'opencomune'); ?></th>
                                <th scope="col"><?php esc_html_e('Esperienze', 'opencomune'); ?></th>
                                <th scope="col" class="oc-dashboard-table__actions"><?php esc_html_e('Azioni', 'opencomune'); ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
