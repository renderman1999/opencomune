<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

$categorie = isset($args['categorie']) ? $args['categorie'] : get_terms([
    'taxonomy' => 'categorie_esperienze',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
]);

$temi = isset($args['temi']) ? $args['temi'] : get_terms([
    'taxonomy' => 'temi_esperienze',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
]);

if (is_wp_error($categorie)) {
    $categorie = [];
}
if (is_wp_error($temi)) {
    $temi = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_categories = isset($_POST['tour_categories']) ? array_map('sanitize_text_field', (array) $_POST['tour_categories']) : [];
    $selected_themes = isset($_POST['tour_themes']) ? array_map('sanitize_text_field', (array) $_POST['tour_themes']) : [];
} else {
    $selected_categories = $post_id > 0 ? opencomune_get_esperienza_category_slugs($post_id) : [];
    $selected_themes = $post_id > 0 ? opencomune_get_esperienza_theme_slugs($post_id) : [];
}
?>

<div class="oc-exp-category-tags">
    <h2 class="oc-exp-category-tags__title">
        <?php esc_html_e('Scegli categorie e temi che descrivono la tua esperienza', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-category-tags__lead">
        <?php esc_html_e('Aiuta i visitatori a trovare quello che cercano: tipo di attività e argomenti collegati.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-category-tags__field">
        <label for="tour_categories" class="form-label">
            <?php esc_html_e('Categorie', 'opencomune'); ?>
            <span class="form-required" aria-hidden="true">*</span>
        </label>
        <p class="oc-exp-category-tags__hint">
            <?php esc_html_e('Puoi selezionare più categorie. Cerca e aggiungi come tag.', 'opencomune'); ?>
        </p>
        <select id="tour_categories" name="tour_categories[]" class="oc-exp-select2" multiple data-placeholder="<?php esc_attr_e('Seleziona...', 'opencomune'); ?>">
            <?php foreach ($categorie as $categoria) : ?>
                <option value="<?php echo esc_attr($categoria->slug); ?>" <?php selected(in_array($categoria->slug, $selected_categories, true)); ?>>
                    <?php echo esc_html($categoria->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($categorie)) : ?>
            <p class="text-sm text-gray-500 mt-2">
                <?php esc_html_e('Nessuna categoria disponibile: creale dalla dashboard in Esperienze → Categorie.', 'opencomune'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="oc-exp-category-tags__field">
        <label for="tour_themes" class="form-label"><?php esc_html_e('Temi', 'opencomune'); ?></label>
        <p class="oc-exp-category-tags__hint">
            <?php esc_html_e('Seleziona i temi applicabili a questa esperienza. Sono usati anche per filtri, SEO e API (in sostituzione delle vecchie parole chiave interne).', 'opencomune'); ?>
        </p>
        <select id="tour_themes" name="tour_themes[]" class="oc-exp-select2" multiple data-placeholder="<?php esc_attr_e('Seleziona...', 'opencomune'); ?>">
            <?php foreach ($temi as $tema) : ?>
                <option value="<?php echo esc_attr($tema->slug); ?>" <?php selected(in_array($tema->slug, $selected_themes, true)); ?>>
                    <?php echo esc_html($tema->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($temi)) : ?>
            <p class="text-sm text-gray-500 mt-2">
                <?php esc_html_e('Nessun tema disponibile: creali da Esperienze → Temi esperienze in wp-admin.', 'opencomune'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
        return;
    }

    jQuery('.oc-exp-category-tags .oc-exp-select2').each(function () {
        const $select = jQuery(this);
        if ($select.data('select2')) {
            return;
        }

        $select.select2({
            width: '100%',
            placeholder: $select.data('placeholder') || 'Seleziona...',
            allowClear: true,
            closeOnSelect: false
        });

        $select.on('change', function () {
            if (typeof window.opencomuneRefreshExperienceWizardStatus === 'function') {
                window.opencomuneRefreshExperienceWizardStatus();
            }
        });
    });
});
</script>
