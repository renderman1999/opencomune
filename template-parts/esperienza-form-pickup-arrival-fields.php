<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$current = 'meet_on_location';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tour_pickup_mode'])) {
    $current = opencomune_normalize_pickup_mode($_POST['tour_pickup_mode']);
} elseif ($post_id > 0) {
    $current = opencomune_get_esperienza_pickup_mode($post_id);
}

$definitions = opencomune_get_pickup_mode_definitions();
?>

<div class="form-section p-6 oc-exp-pickup-arrival">
    <h2 class="oc-exp-pickup-arrival__title">
        <?php esc_html_e('I viaggiatori possono essere prelevati per l\'esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-pickup-arrival__lead">
        <?php esc_html_e('Oppure devono incontrarti sul posto?', 'opencomune'); ?>
    </p>

    <ul class="oc-exp-pickup-arrival__list" role="radiogroup" aria-label="<?php esc_attr_e('Modalità di arrivo', 'opencomune'); ?>">
        <?php foreach ($definitions as $mode => $def) : ?>
            <li>
                <label class="oc-exp-pickup-arrival__card<?php echo $current === $mode ? ' is-selected' : ''; ?>">
                    <span class="oc-exp-pickup-arrival__card-title">
                        <input type="radio" name="tour_pickup_mode" value="<?php echo esc_attr($mode); ?>"<?php checked($current, $mode); ?>>
                        <?php echo esc_html($def['label']); ?>
                    </span>
                    <p class="oc-exp-pickup-arrival__card-desc"><?php echo esc_html($def['description']); ?></p>
                </label>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function syncPickupCards() {
        document.querySelectorAll('.oc-exp-pickup-arrival__card').forEach(function (card) {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', radio && radio.checked);
        });
    }
    document.querySelectorAll('.oc-exp-pickup-arrival input[name="tour_pickup_mode"]').forEach(function (input) {
        input.addEventListener('change', syncPickupCards);
    });
    syncPickupCards();
});
</script>
