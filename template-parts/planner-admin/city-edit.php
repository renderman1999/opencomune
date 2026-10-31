<?php
if (!defined('ABSPATH')) {
    exit;
}
$id = opencomune_planner_admin_get_record_id();
$city = $id > 0 ? opencomune_planner_get_city_by_id($id) : null;
if ($city === null) {
    $draft = opencomune_planner_admin_city_draft_from_request();
    if ($draft !== null) {
        $city = $draft;
        if ($id <= 0 && (int) ($draft['id'] ?? 0) > 0) {
            $id = (int) $draft['id'];
        }
    }
}
if ($id > 0 && $city === null) {
    echo '<p class="text-gray-600">' . esc_html__('Città non trovata.', 'opencomune') . '</p>';
    return;
}
$is_new = $id <= 0 && ($city === null || (int) ($city['id'] ?? 0) <= 0);
$default_lat = 41.9028;
$default_lng = 12.4964;
$maps_key = opencomune_get_google_maps_api_key();
?>
<form method="post" class="oc-profilo-partner-form oc-planner-poi-edit-form" action="<?php echo esc_url(opencomune_planner_admin_form_action('city-edit', $id > 0 ? ['id' => $id] : [])); ?>">
    <?php wp_nonce_field('opencomune_planner_city'); ?>
    <input type="hidden" name="oc_planner_action" value="save_city">
    <input type="hidden" name="city_id" value="<?php echo (int) $id; ?>">

    <?php if ($maps_key === '') : ?>
        <div class="error-message mb-4">
            <?php esc_html_e('Chiave Google Maps non configurata: imposta latitudine e longitudine manualmente o configura la chiave nelle impostazioni del sito.', 'opencomune'); ?>
        </div>
    <?php endif; ?>

    <div class="oc-planner-poi-edit__row">
    <div class="form-section">
        <h2 class="section-title"><?php echo $is_new ? esc_html__('Nuova città', 'opencomune') : esc_html__('Dati città', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="form-label" for="city_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                <input class="form-input" id="city_name" name="name" required value="<?php echo esc_attr($city['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="city_slug"><?php esc_html_e('Slug', 'opencomune'); ?></label>
                <input class="form-input" id="city_slug" name="slug" value="<?php echo esc_attr($city['slug'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="city_sort"><?php esc_html_e('Ordine', 'opencomune'); ?></label>
                <input class="form-input" id="city_sort" type="number" name="sort_order" value="<?php echo esc_attr((string) ($city['sort_order'] ?? 0)); ?>">
            </div>
            <div class="flex items-end">
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="active" value="1" <?php checked(!$city || (int) $city['active'] === 1); ?>>
                    <span><?php esc_html_e('Attiva nel wizard', 'opencomune'); ?></span>
                </label>
            </div>
            <div class="md:col-span-2">
                <?php
                opencomune_planner_admin_render_image_field([
                    'input_id' => 'city_image',
                    'input_name' => 'image',
                    'label' => __('Immagine', 'opencomune'),
                    'value' => $city['image'] ?? '',
                ]);
                ?>
            </div>
        </div>
    </div>

    <div class="form-section">
        <h2 class="section-title"><?php esc_html_e('Centro mappa', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="form-label" for="city_address"><?php esc_html_e('Indirizzo', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-address" id="city_address" name="address" required value="<?php echo esc_attr($city['address'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="city_lat"><?php esc_html_e('Latitudine', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-lat" id="city_lat" name="lat" type="number" step="any" required value="<?php echo esc_attr((string) ($city['lat'] ?? ($is_new ? $default_lat : ''))); ?>">
            </div>
            <div>
                <label class="form-label" for="city_lng"><?php esc_html_e('Longitudine', 'opencomune'); ?></label>
                <input class="form-input oc-planner-admin-lng" id="city_lng" name="lng" type="number" step="any" required value="<?php echo esc_attr((string) ($city['lng'] ?? ($is_new ? $default_lng : ''))); ?>">
            </div>
        </div>
        <div id="oc-planner-admin-map" class="oc-planner-admin-map" aria-hidden="true"></div>
    </div>
    </div>

    <div class="oc-profilo-partner-actions">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva', 'opencomune'); ?></button>
    </div>
</form>
<?php if (!$is_new) : ?>
    <form method="post" class="oc-profilo-partner-form oc-planner-poi-edit-form mt-2" action="<?php echo esc_url(opencomune_planner_admin_form_action('city-edit', ['id' => $id])); ?>" onsubmit="return confirm('<?php echo esc_js(__('Eliminare questa città?', 'opencomune')); ?>');">
        <?php wp_nonce_field('opencomune_planner_city'); ?>
        <input type="hidden" name="oc_planner_action" value="delete_city">
        <input type="hidden" name="city_id" value="<?php echo (int) $id; ?>">
        <div class="oc-profilo-partner-actions" style="border-top: none; padding-top: 0;">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Elimina città', 'opencomune'); ?></button>
        </div>
    </form>
<?php endif; ?>
