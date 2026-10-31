<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tour_duration_days'])) {
    $duration_parts = opencomune_tour_duration_parts_from_post($_POST);
} else {
    $duration_parts = opencomune_get_tour_duration_parts($post_id);
}
?>

<div class="oc-exp-duration-fields">
    <h2 class="oc-exp-duration-fields__title"><?php esc_html_e('Qual è la durata totale della tua esperienza?', 'opencomune'); ?></h2>
    <p class="oc-exp-duration-fields__lead">
        <?php esc_html_e('Indica ai visitatori quanto dura l\'esperienza così possono organizzare il tempo.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-duration-fields__grid">
        <div class="oc-exp-duration-fields__item">
            <input
                type="number"
                id="tour_duration_days"
                name="tour_duration_days"
                class="form-input oc-exp-duration-fields__input"
                min="0"
                step="1"
                inputmode="numeric"
                value="<?php echo esc_attr($duration_parts['days']); ?>"
            >
            <label for="tour_duration_days" class="oc-exp-duration-fields__label"><?php esc_html_e('Giorni', 'opencomune'); ?></label>
        </div>
        <div class="oc-exp-duration-fields__item">
            <input
                type="number"
                id="tour_duration_hours"
                name="tour_duration_hours"
                class="form-input oc-exp-duration-fields__input"
                min="0"
                step="1"
                inputmode="numeric"
                value="<?php echo esc_attr($duration_parts['hours']); ?>"
            >
            <label for="tour_duration_hours" class="oc-exp-duration-fields__label"><?php esc_html_e('Ore', 'opencomune'); ?></label>
        </div>
        <div class="oc-exp-duration-fields__item">
            <input
                type="number"
                id="tour_duration_minutes"
                name="tour_duration_minutes"
                class="form-input oc-exp-duration-fields__input"
                min="0"
                max="59"
                step="1"
                inputmode="numeric"
                value="<?php echo esc_attr($duration_parts['minutes']); ?>"
            >
            <label for="tour_duration_minutes" class="oc-exp-duration-fields__label"><?php esc_html_e('Minuti', 'opencomune'); ?></label>
        </div>
    </div>
</div>
