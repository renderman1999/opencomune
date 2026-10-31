<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $citta = sanitize_text_field($_POST['citta'] ?? '');
    $tour_address = sanitize_text_field($_POST['tour_address'] ?? '');
    $tour_latitude = sanitize_text_field($_POST['tour_latitude'] ?? '');
    $tour_longitude = sanitize_text_field($_POST['tour_longitude'] ?? '');
} else {
    $citta = $post_id > 0 ? (string) get_post_meta($post_id, 'citta', true) : '';
    $tour_address = $post_id > 0 ? (string) get_post_meta($post_id, 'tour_address', true) : '';
    $tour_latitude = $post_id > 0 ? (string) get_post_meta($post_id, 'tour_latitude', true) : '';
    $tour_longitude = $post_id > 0 ? (string) get_post_meta($post_id, 'tour_longitude', true) : '';
}

$display_location = $tour_address !== '' ? $tour_address : $citta;

opencomune_enqueue_esperienza_location_autocomplete_assets();
?>

<div class="oc-exp-location-fields">
    <h2 class="oc-exp-location-fields__title">
        <?php esc_html_e('Dove si svolge la tua esperienza?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-location-fields__lead">
        <?php esc_html_e('Area generale dell’esperienza (comune o località) per filtri e ricerca. L’indirizzo preciso di ritrovo va nel passo Punto di incontro.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-location-fields__control">
        <div class="oc-exp-location-fields__label-row">
            <label for="tour_location_autocomplete" class="form-label mb-0">
                <?php esc_html_e('Località', 'opencomune'); ?>
            </label>
            <span class="oc-exp-location-fields__google"><?php esc_html_e('powered by Google', 'opencomune'); ?></span>
        </div>

        <div class="oc-exp-location-fields__input-wrap">
            <input
                type="text"
                id="tour_location_autocomplete"
                class="form-input oc-exp-location-fields__input"
                autocomplete="off"
                placeholder="<?php esc_attr_e('Clicca qui o inizia a digitare per selezionare una località', 'opencomune'); ?>"
                value="<?php echo esc_attr($display_location); ?>"
            >
            <button type="button" class="oc-exp-location-fields__clear" id="tourLocationClearBtn" aria-label="<?php esc_attr_e('Cancella località', 'opencomune'); ?>">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>

        <p id="tour-location-status" class="oc-exp-location-fields__status" hidden></p>
    </div>

    <input type="hidden" id="citta" name="citta" value="<?php echo esc_attr($citta); ?>">
    <input type="hidden" id="tour_address" name="tour_address" value="<?php echo esc_attr($tour_address); ?>">
    <input type="hidden" id="tour_latitude" name="tour_latitude" value="<?php echo esc_attr($tour_latitude); ?>">
    <input type="hidden" id="tour_longitude" name="tour_longitude" value="<?php echo esc_attr($tour_longitude); ?>">
</div>
