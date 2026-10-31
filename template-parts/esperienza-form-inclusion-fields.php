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
    $selected_inclusions = isset($_POST['tour_inclusions'])
        ? array_map('sanitize_text_field', (array) $_POST['tour_inclusions'])
        : [];
    $other_inclusions = isset($_POST['tour_whats_included'])
        ? wp_kses_post(wp_unslash($_POST['tour_whats_included']))
        : '';
    $lang_options = [
        'live_guide' => !empty($_POST['tour_lang_live_guide']),
        'audio_guide' => !empty($_POST['tour_lang_audio_guide']),
        'reading_material' => !empty($_POST['tour_lang_reading_material']),
    ];
    $spoken_languages = isset($_POST['tour_languages'])
        ? sanitize_text_field(wp_unslash($_POST['tour_languages']))
        : '';
} else {
    $selected_inclusions = $post_id > 0 ? opencomune_get_esperienza_inclusion_slugs($post_id) : [];
    $other_inclusions = $post_id > 0
        ? opencomune_get_esperienza_form_it_field_value($post_id, 'tour_whats_included')
        : '';
    $lang_options = $post_id > 0
        ? opencomune_get_esperienza_language_options($post_id)
        : ['live_guide' => false, 'audio_guide' => false, 'reading_material' => false];
    $spoken_languages = $post_id > 0 ? (string) get_post_meta($post_id, 'tour_languages', true) : '';
}
?>

<div class="oc-exp-inclusion">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Cosa è incluso nella tua esperienza?', 'opencomune'); ?>
    </h2>

    <div class="oc-exp-inclusion__field">
        <label for="tour_inclusions" class="form-label"><?php esc_html_e('Inclusioni', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Usa le inclusioni per evidenziare costi, attrezzatura o altri elementi già compresi.', 'opencomune'); ?>
        </p>
        <select id="tour_inclusions" name="tour_inclusions[]" class="oc-exp-select2" multiple data-placeholder="<?php esc_attr_e('Seleziona...', 'opencomune'); ?>">
            <?php foreach ($inclusioni as $inclusione) : ?>
                <option value="<?php echo esc_attr($inclusione->slug); ?>" <?php selected(in_array($inclusione->slug, $selected_inclusions, true)); ?>>
                    <?php echo esc_html($inclusione->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($inclusioni)) : ?>
            <p class="text-sm text-gray-500 mt-2">
                <?php esc_html_e('Nessuna inclusione disponibile: creale da Esperienze → Inclusioni in wp-admin.', 'opencomune'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="oc-exp-inclusion__field">
        <label for="tour_whats_included" class="form-label"><?php esc_html_e('Altre inclusioni', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Se serve aggiungere altri dettagli su cosa è incluso, usa il campo di testo qui sotto.', 'opencomune'); ?>
        </p>
        <?php
        wp_editor(
            $other_inclusions,
            'tour_whats_included',
            [
                'textarea_name' => 'tour_whats_included',
                'media_buttons' => true,
                'teeny' => false,
                'textarea_rows' => 8,
                'quicktags' => true,
                'editor_class' => 'oc-exp-inclusion__editor',
            ]
        );
        ?>
    </div>

    <div class="oc-exp-inclusion__field">
        <h3 class="oc-exp-inclusion__subtitle"><?php esc_html_e('Lingue', 'opencomune'); ?></h3>
        <label for="tour_languages" class="form-label"><?php esc_html_e('Lingue parlate', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Elenco delle lingue in cui è offerta l’esperienza (es. Italiano, Inglese).', 'opencomune'); ?>
        </p>
        <input
            type="text"
            id="tour_languages"
            name="tour_languages"
            class="form-input"
            placeholder="<?php esc_attr_e('es. Italiano, Inglese', 'opencomune'); ?>"
            value="<?php echo esc_attr($spoken_languages); ?>"
        >
        <ul class="oc-exp-inclusion__toggles mt-4" role="list">
            <li class="oc-exp-inclusion__toggle-row">
                <span class="oc-exp-inclusion__toggle-label"><?php esc_html_e("L'esperienza prevede una guida dal vivo", 'opencomune'); ?></span>
                <label class="oc-exp-switch">
                    <input type="checkbox" name="tour_lang_live_guide" value="1" <?php checked($lang_options['live_guide']); ?>>
                    <span class="oc-exp-switch__track" aria-hidden="true"></span>
                </label>
            </li>
            <li class="oc-exp-inclusion__toggle-row">
                <span class="oc-exp-inclusion__toggle-label"><?php esc_html_e('Offriamo audioguida / cuffie', 'opencomune'); ?></span>
                <label class="oc-exp-switch">
                    <input type="checkbox" name="tour_lang_audio_guide" value="1" <?php checked($lang_options['audio_guide']); ?>>
                    <span class="oc-exp-switch__track" aria-hidden="true"></span>
                </label>
            </li>
            <li class="oc-exp-inclusion__toggle-row">
                <span class="oc-exp-inclusion__toggle-label"><?php esc_html_e('È disponibile materiale di lettura', 'opencomune'); ?></span>
                <label class="oc-exp-switch">
                    <input type="checkbox" name="tour_lang_reading_material" value="1" <?php checked($lang_options['reading_material']); ?>>
                    <span class="oc-exp-switch__track" aria-hidden="true"></span>
                </label>
            </li>
        </ul>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
        return;
    }

    jQuery('#tour_inclusions.oc-exp-select2, .oc-exp-inclusion .oc-exp-select2').each(function () {
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
