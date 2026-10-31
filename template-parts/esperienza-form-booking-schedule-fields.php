<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$options = opencomune_get_booking_schedule_type_definitions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = opencomune_sanitize_booking_schedule_type($_POST['tour_booking_schedule'] ?? 'datetime');
} else {
    $selected = $post_id > 0
        ? opencomune_get_esperienza_booking_schedule_type($post_id)
        : 'datetime';
}
?>

<div class="oc-exp-booking-schedule">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Come è programmata la tua esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-booking-schedule__lead">
        <?php esc_html_e('L’esperienza è a ore, a giornata o è flessibile (pass)?', 'opencomune'); ?>
    </p>

    <input type="hidden" name="tour_booking_schedule" id="tour_booking_schedule" value="<?php echo esc_attr($selected); ?>">

    <ul class="oc-exp-booking-schedule__list" role="list">
        <?php foreach ($options as $value => $option) : ?>
            <li>
                <div
                    class="oc-exp-booking-schedule__card<?php echo $selected === $value ? ' is-selected' : ''; ?>"
                    data-schedule-option="<?php echo esc_attr($value); ?>"
                    role="button"
                    tabindex="0"
                    aria-pressed="<?php echo $selected === $value ? 'true' : 'false'; ?>"
                >
                    <div class="oc-exp-booking-schedule__card-text">
                        <span class="oc-exp-booking-schedule__card-title"><?php echo esc_html($option['label']); ?></span>
                        <p class="oc-exp-booking-schedule__card-desc"><?php echo esc_html($option['description']); ?></p>
                    </div>
                    <label class="oc-exp-switch oc-exp-booking-schedule__switch" aria-hidden="true">
                        <input
                            type="checkbox"
                            class="oc-exp-booking-schedule__switch-input"
                            data-schedule-value="<?php echo esc_attr($value); ?>"
                            <?php checked($selected, $value); ?>
                        >
                        <span class="oc-exp-switch__track"></span>
                    </label>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hidden = document.getElementById('tour_booking_schedule');
    const cards = document.querySelectorAll('.oc-exp-booking-schedule__card');
    const switches = document.querySelectorAll('.oc-exp-booking-schedule__switch-input');

    if (!hidden || !cards.length) {
        return;
    }

    function selectSchedule(value) {
        const safeValue = String(value || 'datetime');
        hidden.value = safeValue;

        cards.forEach(function (card) {
            const isActive = card.getAttribute('data-schedule-option') === safeValue;
            card.classList.toggle('is-selected', isActive);
            card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });

        switches.forEach(function (input) {
            input.checked = input.getAttribute('data-schedule-value') === safeValue;
        });
    }

    switches.forEach(function (input) {
        input.addEventListener('change', function () {
            if (input.checked) {
                selectSchedule(input.getAttribute('data-schedule-value'));
                return;
            }
            selectSchedule(hidden.value || 'datetime');
        });
    });

    cards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('.oc-exp-booking-schedule__switch')) {
                return;
            }
            selectSchedule(card.getAttribute('data-schedule-option'));
        });

        card.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return;
            }
            event.preventDefault();
            selectSchedule(card.getAttribute('data-schedule-option'));
        });
    });
});
</script>
