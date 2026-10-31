<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$options = opencomune_get_capacity_mode_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = opencomune_sanitize_capacity_mode($_POST['tour_capacity_mode'] ?? 'limited');
    $max_participants = isset($_POST['tour_max_participants']) ? (int) $_POST['tour_max_participants'] : 0;
} else {
    $selected = $post_id > 0 ? opencomune_get_esperienza_capacity_mode($post_id) : 'limited';
    $max_participants = $post_id > 0 ? (int) get_post_meta($post_id, 'tour_max_participants', true) : 0;
}
?>

<div class="oc-exp-capacity">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Qual è la capacità della tua esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-capacity__lead">
        <?php esc_html_e('Hai un numero massimo di viaggiatori per partenza o approvi ogni prenotazione manualmente? Questo valore è il tetto globale: nel Calendario puoi impostare limiti diversi per singola regola.', 'opencomune'); ?>
    </p>

    <ul class="oc-exp-capacity__list" role="radiogroup" aria-label="<?php esc_attr_e('Modalità capacità', 'opencomune'); ?>">
        <?php foreach ($options as $value => $option) : ?>
            <li>
                <label class="oc-exp-capacity__card<?php echo $selected === $value ? ' is-selected' : ''; ?>">
                    <input
                        type="radio"
                        name="tour_capacity_mode"
                        class="oc-exp-capacity__radio"
                        value="<?php echo esc_attr($value); ?>"
                        <?php checked($selected, $value); ?>
                    >
                    <span class="oc-exp-capacity__card-text">
                        <span class="oc-exp-capacity__card-title"><?php echo esc_html($option['label']); ?></span>
                        <span class="oc-exp-capacity__card-desc"><?php echo esc_html($option['description']); ?></span>
                    </span>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>

    <div id="oc-exp-capacity-limited-fields" class="oc-exp-capacity__limited<?php echo $selected === 'limited' ? '' : ' is-hidden'; ?>">
        <label for="tour_max_participants" class="form-label">
            <?php esc_html_e('Numero massimo di partecipanti', 'opencomune'); ?>
        </label>
        <input
            type="number"
            id="tour_max_participants"
            name="tour_max_participants"
            class="form-input oc-exp-capacity__max-input"
            min="1"
            step="1"
            value="<?php echo $max_participants > 0 ? esc_attr($max_participants) : ''; ?>"
            placeholder="<?php esc_attr_e('es. 20', 'opencomune'); ?>"
        >
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('.oc-exp-capacity');
    if (!root) {
        return;
    }

    const limitedFields = document.getElementById('oc-exp-capacity-limited-fields');
    const radios = root.querySelectorAll('.oc-exp-capacity__radio');
    const cards = root.querySelectorAll('.oc-exp-capacity__card');

    function syncCapacityUi() {
        let selected = 'limited';
        radios.forEach(function (radio) {
            if (radio.checked) {
                selected = radio.value;
            }
        });

        cards.forEach(function (card) {
            const radio = card.querySelector('.oc-exp-capacity__radio');
            card.classList.toggle('is-selected', radio && radio.checked);
        });

        if (limitedFields) {
            limitedFields.classList.toggle('is-hidden', selected !== 'limited');
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', syncCapacityUi);
    });

    syncCapacityUi();
});
</script>
