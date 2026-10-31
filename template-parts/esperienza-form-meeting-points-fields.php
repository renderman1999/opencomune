<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$points = opencomune_get_esperienza_meeting_points($post_id);
$use_pickup_group = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['tour_meeting_points_payload'])) {
        $decoded = json_decode(wp_unslash((string) $_POST['tour_meeting_points_payload']), true);
        if (is_array($decoded)) {
            $points = opencomune_normalize_meeting_points_list($decoded);
        }
    }
    $use_pickup_group = !empty($_POST['tour_use_pickup_group']);
} elseif ($post_id > 0) {
    $use_pickup_group = opencomune_get_esperienza_use_pickup_group($post_id);
}

opencomune_enqueue_meeting_pickup_assets();
$payload_json = wp_json_encode($points);

?>

<div class="form-section p-6 oc-exp-meeting-points" id="oc-exp-meeting-points-root">
    <h2 class="oc-exp-meeting-points__title">
        <?php esc_html_e('Dove devono incontrarti i viaggiatori?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-meeting-points__lead">
        <?php esc_html_e('Ti consigliamo un luogo facile da trovare, come una fermata bus, un edificio noto o un punto di riferimento.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-meeting-points__empty<?php echo empty($points) ? '' : ' is-hidden'; ?>" id="oc-exp-meeting-points-empty">
        <?php esc_html_e('Non ci sono punti di incontro per questa esperienza.', 'opencomune'); ?>
    </div>

    <ul class="oc-exp-meeting-points__list" id="oc-exp-meeting-points-list" aria-live="polite"></ul>

    <button type="button" class="oc-exp-meeting-points__add" id="oc-exp-meeting-points-add">
        <?php esc_html_e('+ Aggiungi punto di inizio', 'opencomune'); ?>
    </button>

    <div class="oc-exp-meeting-points__group-toggle">
        <div>
            <h3><?php esc_html_e('Hai più punti di incontro?', 'opencomune'); ?></h3>
            <p><?php esc_html_e('Puoi usare i gruppi di prelievo per aggiungere rapidamente più punti di incontro.', 'opencomune'); ?></p>
        </div>
        <label class="oc-exp-switch">
            <input type="checkbox" name="tour_use_pickup_group" value="1"<?php checked($use_pickup_group); ?>>
            <span class="oc-exp-switch__track" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e('Usa un gruppo di prelievo', 'opencomune'); ?></span>
        </label>
    </div>
    <p class="text-sm text-gray-500 mt-2"><?php esc_html_e('Usa un gruppo di prelievo', 'opencomune'); ?></p>
    <p class="oc-exp-meeting-points__lead text-sm text-gray-600 mt-4">
        <?php esc_html_e('Orari di partenza e date vendibili: passi Orario di inizio e Calendario. Le lingue parlate sono nel passo Inclusione.', 'opencomune'); ?>
    </p>

    <?php
    get_template_part('template-parts/partner-esperienza-external-link-fields', null, [
        'tour_destination' => $_POST['tour_destination'] ?? ($post_id > 0 ? get_post_meta($post_id, 'tour_destination', true) : ''),
    ]);
    ?>

    <input type="hidden" name="tour_meeting_points_payload" id="tour_meeting_points_payload" value="<?php echo esc_attr($payload_json); ?>">
</div>

<div id="oc-exp-mp-modal" class="oc-exp-mp-modal" hidden role="dialog" aria-modal="true" aria-labelledby="oc-exp-mp-modal-title">
    <div class="oc-exp-mp-modal__panel">
        <div class="oc-exp-mp-modal__header">
            <h3 id="oc-exp-mp-modal-title" class="oc-exp-mp-modal__title"><?php esc_html_e('Aggiungi punto di inizio', 'opencomune'); ?></h3>
            <button type="button" class="oc-exp-mp-modal__close" data-mp-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>

        <div class="oc-exp-mp-modal__field">
            <label for="oc-exp-mp-title"><?php esc_html_e('Titolo', 'opencomune'); ?> <span class="form-required">*</span></label>
            <input type="text" id="oc-exp-mp-title" class="form-input" maxlength="160">
        </div>

        <div class="oc-exp-mp-modal__field" id="oc-exp-mp-autocomplete-wrap">
            <div class="oc-exp-mp-modal__label-row">
                <label for="oc-exp-mp-address-search"><?php esc_html_e('Indirizzo', 'opencomune'); ?> <span class="form-required">*</span></label>
                <span class="oc-exp-mp-modal__google"><?php esc_html_e('powered by Google', 'opencomune'); ?></span>
            </div>
            <input type="text" id="oc-exp-mp-address-search" class="form-input" autocomplete="off" placeholder="<?php esc_attr_e('Clicca qui o inizia a digitare per selezionare una posizione', 'opencomune'); ?>">
            <button type="button" class="oc-exp-mp-modal__manual" id="oc-exp-mp-toggle-manual"></button>
        </div>

        <div class="oc-exp-mp-modal__manual-fields is-hidden" id="oc-exp-mp-manual-fields">
            <div class="oc-exp-mp-modal__field">
                <label for="oc-exp-mp-address-manual"><?php esc_html_e('Indirizzo completo', 'opencomune'); ?></label>
                <input type="text" id="oc-exp-mp-address-manual" class="form-input">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="oc-exp-mp-lat"><?php esc_html_e('Latitudine', 'opencomune'); ?></label>
                    <input type="text" id="oc-exp-mp-lat" class="form-input">
                </div>
                <div>
                    <label for="oc-exp-mp-lng"><?php esc_html_e('Longitudine', 'opencomune'); ?></label>
                    <input type="text" id="oc-exp-mp-lng" class="form-input">
                </div>
            </div>
        </div>

        <div id="oc-exp-mp-map" class="oc-exp-mp-modal__map" aria-hidden="true"></div>

        <div class="oc-exp-mp-modal__footer">
            <button type="button" class="btn-secondary" data-mp-close><?php esc_html_e('Annulla', 'opencomune'); ?></button>
            <button type="button" class="btn-primary" id="oc-exp-mp-save"><?php esc_html_e('Salva', 'opencomune'); ?></button>
        </div>
    </div>
</div>
