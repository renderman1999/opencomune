<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

$inclusioni = isset($args['inclusioni']) ? $args['inclusioni'] : get_terms([
    'taxonomy' => 'inclusioni_esperienze',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
]);

if (is_wp_error($inclusioni)) {
    $inclusioni = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_exclusions = isset($_POST['tour_exclusions'])
        ? array_map('sanitize_text_field', (array) $_POST['tour_exclusions'])
        : [];
    $other_exclusions = isset($_POST['tour_whats_not_included'])
        ? wp_kses_post(wp_unslash($_POST['tour_whats_not_included']))
        : '';
} else {
    $selected_exclusions = $post_id > 0 ? opencomune_get_esperienza_exclusion_slugs($post_id) : [];
    $other_exclusions = $post_id > 0
        ? opencomune_get_esperienza_form_it_field_value($post_id, 'tour_whats_not_included')
        : '';
}
?>

<div class="oc-exp-inclusion oc-exp-exclusion">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Cosa NON è incluso nella tua esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-exclusion__lead">
        <?php esc_html_e('C’è qualcosa che i viaggiatori potrebbero aver bisogno ma non è compreso nell’offerta? Esempi: cibo, attrezzatura o costi aggiuntivi.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-inclusion__field">
        <label for="tour_exclusions" class="form-label"><?php esc_html_e('Esclusioni', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('C’è qualcosa non incluso ma acquistabile il giorno dell’esperienza? Seleziona dalle voci create in Inclusioni.', 'opencomune'); ?>
        </p>
        <select id="tour_exclusions" name="tour_exclusions[]" class="oc-exp-select2" multiple data-placeholder="<?php esc_attr_e('Seleziona...', 'opencomune'); ?>">
            <?php foreach ($inclusioni as $voce) : ?>
                <option value="<?php echo esc_attr($voce->slug); ?>" <?php selected(in_array($voce->slug, $selected_exclusions, true)); ?>>
                    <?php echo esc_html($voce->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($inclusioni)) : ?>
            <p class="text-sm text-gray-500 mt-2">
                <?php esc_html_e('Nessuna voce disponibile: creale da Esperienze → Inclusioni in wp-admin.', 'opencomune'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="oc-exp-inclusion__field">
        <label for="tour_whats_not_included" class="form-label"><?php esc_html_e('Altre esclusioni', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Se serve aggiungere altri dettagli su cosa è escluso, usa il campo di testo qui sotto.', 'opencomune'); ?>
        </p>
        <?php
        wp_editor(
            $other_exclusions,
            'tour_whats_not_included',
            [
                'textarea_name' => 'tour_whats_not_included',
                'media_buttons' => true,
                'teeny' => false,
                'textarea_rows' => 8,
                'quicktags' => true,
                'editor_class' => 'oc-exp-inclusion__editor',
            ]
        );
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
        return;
    }

    jQuery('#tour_exclusions').each(function () {
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
    });
});
</script>
