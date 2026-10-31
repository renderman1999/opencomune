<?php
/**
 * Impostazioni esperienza (tipo, privata, prenotazioni personalizzate).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
opencomune_enqueue_experience_settings_assets();
$settings = opencomune_get_esperienza_experience_settings($post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['tour_experience_type'])) {
        $settings['experience_type'] = opencomune_normalize_experience_type($_POST['tour_experience_type']);
    }
    $settings['private_experience'] = !empty($_POST['tour_private_experience']);
    $settings['customized_bookings'] = !empty($_POST['tour_customized_bookings']);
}

$definitions = opencomune_get_experience_type_definitions();
$current_type = $settings['experience_type'];
?>

<div class="form-section p-6 oc-exp-experience-settings">
    <h2 class="oc-exp-experience-settings__title">
        <?php esc_html_e('Personalizza ulteriormente il prodotto…', 'opencomune'); ?>
    </h2>

    <div class="oc-exp-experience-settings__block">
        <h3 class="oc-exp-experience-settings__subtitle">
            <?php esc_html_e('Tipo di esperienza', 'opencomune'); ?>
            <span class="form-required" aria-hidden="true">*</span>
        </h3>

        <ul class="oc-exp-pickup-arrival__list" role="radiogroup" aria-label="<?php esc_attr_e('Tipo di esperienza', 'opencomune'); ?>">
            <?php foreach ($definitions as $type_key => $def) : ?>
                <li>
                    <label class="oc-exp-pickup-arrival__card<?php echo $current_type === $type_key ? ' is-selected' : ''; ?>">
                        <span class="oc-exp-pickup-arrival__card-title">
                            <input type="radio" name="tour_experience_type" value="<?php echo esc_attr($type_key); ?>"<?php checked($current_type, $type_key); ?> required>
                            <?php echo esc_html($def['label']); ?>
                        </span>
                        <p class="oc-exp-pickup-arrival__card-desc"><?php echo esc_html($def['description']); ?></p>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="oc-exp-experience-settings__block oc-exp-experience-settings__toggle-block">
        <h3 class="oc-exp-experience-settings__subtitle"><?php esc_html_e('Esperienza privata', 'opencomune'); ?></h3>
        <p class="oc-exp-experience-settings__hint">
            <?php esc_html_e('Le esperienze private accolgono un solo gruppo per prenotazione e non sono condivise con altri. Non chiudono automaticamente la partenza alla ricezione di una prenotazione.', 'opencomune'); ?>
        </p>
        <label class="oc-exp-experience-settings__toggle-row">
            <span><?php esc_html_e('Mostra come esperienza privata', 'opencomune'); ?></span>
            <span class="oc-exp-switch">
                <input type="checkbox" name="tour_private_experience" value="1"<?php checked($settings['private_experience']); ?>>
                <span class="oc-exp-switch__track" aria-hidden="true"></span>
            </span>
        </label>
    </div>

    <div class="oc-exp-experience-settings__block oc-exp-experience-settings__toggle-block">
        <h3 class="oc-exp-experience-settings__subtitle"><?php esc_html_e('Prenotazioni personalizzate', 'opencomune'); ?></h3>
        <label class="oc-exp-experience-settings__toggle-row">
            <span><?php esc_html_e('Consenti prenotazioni con orari di inizio e prezzi personalizzati (non incidono sulla disponibilità)', 'opencomune'); ?></span>
            <span class="oc-exp-switch">
                <input type="checkbox" name="tour_customized_bookings" value="1"<?php checked($settings['customized_bookings']); ?>>
                <span class="oc-exp-switch__track" aria-hidden="true"></span>
            </span>
        </label>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function syncTypeCards() {
        document.querySelectorAll('.oc-exp-experience-settings .oc-exp-pickup-arrival__card').forEach(function (card) {
            const radio = card.querySelector('input[type="radio"]');
            card.classList.toggle('is-selected', radio && radio.checked);
        });
        document.dispatchEvent(new CustomEvent('opencomune-experience-settings-changed'));
    }
    document.querySelectorAll('.oc-exp-experience-settings input[name="tour_experience_type"]').forEach(function (input) {
        input.addEventListener('change', syncTypeCards);
    });
    document.querySelectorAll('.oc-exp-experience-settings input[name="tour_private_experience"], .oc-exp-experience-settings input[name="tour_customized_bookings"]').forEach(function (input) {
        input.addEventListener('change', function () {
            document.dispatchEvent(new CustomEvent('opencomune-experience-settings-changed'));
        });
    });
    syncTypeCards();
});
</script>
