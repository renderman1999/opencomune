<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

$info_items = isset($args['info_items']) ? $args['info_items'] : get_terms([
    'taxonomy' => 'info_prima_prenotazione',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC',
]);

if (is_wp_error($info_items)) {
    $info_items = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_info = isset($_POST['tour_before_booking_info'])
        ? array_map('sanitize_text_field', (array) $_POST['tour_before_booking_info'])
        : [];
    $other_notes = isset($_POST['tour_requirements'])
        ? wp_kses_post(wp_unslash($_POST['tour_requirements']))
        : '';
    $difficulty = sanitize_text_field($_POST['tour_difficulty'] ?? '');
    $age_limit_enabled = !empty($_POST['tour_age_limit_enabled']);
    $age_limit_min = $age_limit_enabled ? max(0, (int) ($_POST['tour_age_limit_min'] ?? 0)) : 0;
} else {
    $selected_info = $post_id > 0 ? opencomune_get_esperienza_before_booking_slugs($post_id) : [];
    $other_notes = $post_id > 0
        ? opencomune_get_esperienza_form_it_field_value($post_id, 'tour_requirements')
        : '';
    $difficulty = $post_id > 0
        ? (string) get_post_meta($post_id, 'tour_difficulty', true)
        : '';
    $age_limit = $post_id > 0
        ? opencomune_get_esperienza_age_limit($post_id)
        : ['enabled' => false, 'minimum_age' => 0];
    $age_limit_enabled = $age_limit['enabled'];
    $age_limit_min = $age_limit['minimum_age'];
}
?>

<div class="oc-exp-inclusion oc-exp-before-start">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Informazioni importanti prima di prenotare', 'opencomune'); ?>
    </h2>

    <div class="oc-exp-inclusion__field">
        <label for="tour_before_booking_info" class="form-label">
            <?php esc_html_e('Cosa dovrebbero sapere i viaggiatori prima di prenotare?', 'opencomune'); ?>
        </label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Indica restrizioni di salute, requisiti fisici o accessibilità (es. percorso accessibile in sedia a rotelle, seggiolini per neonati).', 'opencomune'); ?>
        </p>
        <select id="tour_before_booking_info" name="tour_before_booking_info[]" class="oc-exp-select2" multiple data-placeholder="<?php esc_attr_e('Seleziona...', 'opencomune'); ?>">
            <?php foreach ($info_items as $item) : ?>
                <option value="<?php echo esc_attr($item->slug); ?>" <?php selected(in_array($item->slug, $selected_info, true)); ?>>
                    <?php echo esc_html($item->name); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($info_items)) : ?>
            <p class="text-sm text-gray-500 mt-2">
                <?php esc_html_e('Nessuna voce disponibile: creale da Esperienze → Prima di prenotare in wp-admin.', 'opencomune'); ?>
            </p>
        <?php endif; ?>
    </div>

    <div class="oc-exp-inclusion__field">
        <label for="tour_requirements" class="form-label"><?php esc_html_e('Altre cose da sapere', 'opencomune'); ?></label>
        <p class="oc-exp-inclusion__hint">
            <?php esc_html_e('Per altri dettagli puoi usare il campo di testo qui sotto.', 'opencomune'); ?>
        </p>
        <?php
        wp_editor(
            $other_notes,
            'tour_requirements',
            [
                'textarea_name' => 'tour_requirements',
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
        <label for="tour_difficulty" class="form-label"><?php esc_html_e('Livello di difficoltà fisica', 'opencomune'); ?></label>
        <select id="tour_difficulty" name="tour_difficulty" class="form-input">
            <option value=""><?php esc_html_e('Seleziona difficoltà', 'opencomune'); ?></option>
            <option value="facile" <?php selected($difficulty, 'facile'); ?>><?php esc_html_e('Facile', 'opencomune'); ?></option>
            <option value="medio" <?php selected($difficulty, 'medio'); ?>><?php esc_html_e('Medio', 'opencomune'); ?></option>
            <option value="difficile" <?php selected($difficulty, 'difficile'); ?>><?php esc_html_e('Difficile', 'opencomune'); ?></option>
        </select>
    </div>

    <div class="oc-exp-inclusion__field">
        <h3 class="oc-exp-inclusion__subtitle"><?php esc_html_e('Limite di età', 'opencomune'); ?></h3>
        <ul class="oc-exp-inclusion__toggles" role="list">
            <li class="oc-exp-inclusion__toggle-row">
                <span class="oc-exp-inclusion__toggle-label"><?php esc_html_e('Imposta il limite di età per questa esperienza', 'opencomune'); ?></span>
                <label class="oc-exp-switch">
                    <input type="checkbox" id="tour_age_limit_enabled" name="tour_age_limit_enabled" value="1" <?php checked($age_limit_enabled); ?>>
                    <span class="oc-exp-switch__track" aria-hidden="true"></span>
                </label>
            </li>
        </ul>
        <div id="tour_age_limit_fields" class="oc-exp-before-start__age-fields<?php echo $age_limit_enabled ? '' : ' is-hidden'; ?>">
            <label for="tour_age_limit_min" class="form-label"><?php esc_html_e('Età minima (anni)', 'opencomune'); ?></label>
            <input
                type="number"
                id="tour_age_limit_min"
                name="tour_age_limit_min"
                class="form-input oc-exp-before-start__age-input"
                min="0"
                max="120"
                value="<?php echo esc_attr($age_limit_min); ?>"
            >
        </div>
    </div>
</div>

<style>
.oc-exp-before-start__age-fields.is-hidden {
    display: none;
}
.oc-exp-before-start__age-fields {
    margin-top: 1rem;
}
.oc-exp-before-start__age-input {
    max-width: 12rem;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery !== 'undefined' && jQuery.fn.select2) {
        jQuery('#tour_before_booking_info').each(function () {
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
    }

    const ageToggle = document.getElementById('tour_age_limit_enabled');
    const ageFields = document.getElementById('tour_age_limit_fields');
    if (ageToggle && ageFields) {
        ageToggle.addEventListener('change', function () {
            ageFields.classList.toggle('is-hidden', !ageToggle.checked);
        });
    }
});
</script>
