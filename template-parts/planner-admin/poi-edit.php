<?php
if (!defined('ABSPATH')) {
    exit;
}
$id = opencomune_planner_admin_get_record_id();
$poi = $id > 0 ? opencomune_planner_get_poi_by_id($id) : null;
if ($id > 0 && !$poi) {
    echo '<p class="text-gray-600">' . esc_html__('Destinazione non trovata.', 'opencomune') . '</p>';
    return;
}
$cities = opencomune_planner_get_cities(false);
$category_choices = opencomune_planner_esperienze_category_choices_for_admin();
$categories_dashboard_url = function_exists('opencomune_get_esperienze_categories_dashboard_url')
    ? opencomune_get_esperienze_categories_dashboard_url()
    : home_url('/categorie-esperienze/');
$selected = $poi ? array_filter(array_map('trim', explode(',', (string) $poi['categories']))) : [];
$is_new = $poi === null;
?>
<p class="mb-4">
    <a href="<?php echo esc_url(opencomune_planner_admin_base_url('destinations')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
        <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
        <?php esc_html_e('Torna alle destinazioni', 'opencomune'); ?>
    </a>
</p>

<form method="post" class="oc-profilo-partner-form oc-planner-poi-edit-form" action="<?php echo esc_url(opencomune_planner_admin_form_action('poi-edit', $id > 0 ? ['id' => $id] : [])); ?>">
    <?php wp_nonce_field('opencomune_planner_poi'); ?>
    <input type="hidden" name="oc_planner_action" value="save_poi">
    <input type="hidden" name="poi_id" value="<?php echo (int) $id; ?>">

    <div class="oc-planner-poi-edit__row">
    <div class="form-section">
        <h2 class="section-title"><?php echo $is_new ? esc_html__('Nuova destinazione', 'opencomune') : esc_html__('Dati destinazione', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="form-label" for="poi_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                <input class="form-input" id="poi_name" name="name" required value="<?php echo esc_attr($poi['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="poi_slug"><?php esc_html_e('Slug', 'opencomune'); ?></label>
                <input class="form-input" id="poi_slug" name="slug" value="<?php echo esc_attr($poi['slug'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="poi_city"><?php esc_html_e('Città', 'opencomune'); ?></label>
                <select class="form-input" id="poi_city" name="city" required>
                    <?php if ($cities === []) : ?>
                        <option value=""><?php esc_html_e('Crea prima una città', 'opencomune'); ?></option>
                    <?php else : ?>
                        <?php foreach ($cities as $city) : ?>
                            <option value="<?php echo esc_attr($city['slug']); ?>" <?php selected($poi['city'] ?? '', $city['slug']); ?>><?php echo esc_html($city['name']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div>
                <label class="form-label" for="poi_duration"><?php esc_html_e('Durata (minuti)', 'opencomune'); ?></label>
                <input class="form-input" type="number" min="15" step="15" id="poi_duration" name="duration_min" value="<?php echo esc_attr((string) ($poi['duration_min'] ?? 60)); ?>">
            </div>
            <div class="md:col-span-2">
                <label class="form-label" for="poi_description"><?php esc_html_e('Descrizione', 'opencomune'); ?></label>
                <textarea class="form-input" id="poi_description" name="description" rows="3"><?php echo esc_textarea($poi['description'] ?? ''); ?></textarea>
            </div>
            <div>
                <label class="form-label" for="poi_pop"><?php esc_html_e('Popolarità', 'opencomune'); ?></label>
                <input class="form-input" type="number" id="poi_pop" name="popularity" value="<?php echo esc_attr((string) ($poi['popularity'] ?? 0)); ?>">
            </div>
            <div class="flex items-end">
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="active" value="1" <?php checked(!$poi || (int) $poi['active'] === 1); ?>>
                    <span><?php esc_html_e('Attiva nel pianificatore', 'opencomune'); ?></span>
                </label>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h2 class="section-title"><?php esc_html_e('Posizione', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="form-label" for="poi_address"><?php esc_html_e('Indirizzo', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-address" id="poi_address" name="address" value="<?php echo esc_attr($poi['address'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="poi_lat"><?php esc_html_e('Latitudine', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-lat" type="number" step="any" id="poi_lat" name="lat" required value="<?php echo esc_attr((string) ($poi['lat'] ?? '')); ?>">
            </div>
            <div>
                <label class="form-label" for="poi_lng"><?php esc_html_e('Longitudine', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-lng" type="number" step="any" id="poi_lng" name="lng" required value="<?php echo esc_attr((string) ($poi['lng'] ?? '')); ?>">
            </div>
        </div>
        <div id="oc-planner-admin-map" class="oc-planner-admin-map" aria-hidden="true"></div>
    </div>
    </div>

    <div class="form-section">
        <h2 class="section-title"><?php esc_html_e('Media e categorie', 'opencomune'); ?></h2>
        <div class="mb-6">
            <?php
            opencomune_planner_admin_render_image_field([
                'input_id' => 'poi_cover',
                'input_name' => 'cover_image',
                'attachment_input_name' => 'cover_image_attachment_id',
                'label' => __('Immagine di copertina', 'opencomune'),
                'value' => $poi['cover_image'] ?? '',
                'help' => __('Usata nel wizard e nelle schede destinazione. Seleziona dalla libreria media o carica un nuovo file.', 'opencomune'),
            ]);
            ?>
        </div>
        <p class="form-label mb-2"><?php esc_html_e('Categorie esperienze', 'opencomune'); ?></p>
        <?php if ($category_choices === []) : ?>
            <p class="text-gray-600 text-sm mb-0">
                <?php esc_html_e('Nessuna categoria disponibile. Creane almeno una in', 'opencomune'); ?>
                <a href="<?php echo esc_url($categories_dashboard_url); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
                    <?php esc_html_e('Categorie esperienze', 'opencomune'); ?>
                </a>.
            </p>
        <?php else : ?>
            <p class="text-sm text-gray-500 mb-2">
                <?php esc_html_e('Stesse categorie gestite in', 'opencomune'); ?>
                <a href="<?php echo esc_url($categories_dashboard_url); ?>" class="text-blue-700 hover:text-blue-900">
                    <?php esc_html_e('Categorie esperienze', 'opencomune'); ?>
                </a>.
            </p>
            <div class="oc-planner-admin__checks">
                <?php foreach ($category_choices as $choice) :
                    $slug = $choice['slug'] ?? '';
                    ?>
                    <label class="oc-planner-admin__checkbox">
                        <input type="checkbox" name="categories[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $selected, true)); ?>>
                        <span><?php echo esc_html($choice['name'] ?? $slug); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="oc-profilo-partner-actions">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva', 'opencomune'); ?></button>
    </div>
</form>

<?php if (!$is_new) : ?>
    <form method="post" class="oc-profilo-partner-form oc-planner-poi-edit-form mt-4" action="<?php echo esc_url(opencomune_planner_admin_form_action('poi-edit', ['id' => $id])); ?>" onsubmit="return confirm('<?php echo esc_js(__('Eliminare questa destinazione?', 'opencomune')); ?>');">
        <?php wp_nonce_field('opencomune_planner_poi'); ?>
        <input type="hidden" name="oc_planner_action" value="delete_poi">
        <input type="hidden" name="poi_id" value="<?php echo (int) $id; ?>">
        <div class="oc-profilo-partner-actions" style="border-top: none; padding-top: 0;">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Elimina destinazione', 'opencomune'); ?></button>
        </div>
    </form>
<?php endif; ?>
