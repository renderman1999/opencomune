<?php
$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$steps = opencomune_get_esperienza_itinerary_steps($post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['tour_itinerary_steps_payload'])) {
    $decoded = json_decode(wp_unslash((string) $_POST['tour_itinerary_steps_payload']), true);
    if (is_array($decoded)) {
        $steps = opencomune_normalize_esperienza_itinerary_steps_list($decoded);
    }
}

opencomune_enqueue_meeting_pickup_assets();
opencomune_enqueue_esperienza_itinerary_steps_assets();

$payload_json = wp_json_encode($steps);
?>

<div class="oc-exp-start-times oc-exp-itinerary-steps" id="oc-exp-itinerary-steps-root">
    <h2 class="oc-exp-inclusion__title">
        <?php esc_html_e('Itinerario passo per passo', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-inclusion__hint oc-exp-itinerary-steps__lead">
        <?php esc_html_e('Aggiungi le tappe dell’esperienza in ordine: titolo, descrizione, punto sulla mappa e foto per ogni step.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-start-times__table-wrap">
        <table class="oc-exp-start-times__table" aria-describedby="oc-exp-itinerary-steps-empty-msg">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    <th scope="col"><?php esc_html_e('Titolo', 'opencomune'); ?></th>
                    <th scope="col"><?php esc_html_e('Indirizzo', 'opencomune'); ?></th>
                    <th scope="col"><?php esc_html_e('Foto', 'opencomune'); ?></th>
                    <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Azioni', 'opencomune'); ?></span></th>
                </tr>
            </thead>
            <tbody id="oc-exp-itinerary-steps-tbody"></tbody>
        </table>
        <p id="oc-exp-itinerary-steps-empty-msg" class="oc-exp-start-times__empty<?php echo empty($steps) ? '' : ' is-hidden'; ?>">
            <?php esc_html_e('Non ci sono tappe nell\'itinerario.', 'opencomune'); ?>
        </p>
    </div>

    <button type="button" id="oc-exp-itinerary-steps-add" class="oc-exp-start-times__add-btn">
        <?php esc_html_e('+ Aggiungi itinerario', 'opencomune'); ?>
    </button>

    <input type="hidden" name="tour_itinerary_steps_payload" id="tour_itinerary_steps_payload" value="<?php echo esc_attr($payload_json); ?>">
</div>

<div id="oc-exp-itinerary-steps-modal" class="oc-exp-start-times-modal oc-exp-itinerary-steps-modal" hidden aria-hidden="true">
    <div class="oc-exp-start-times-modal__backdrop" data-it-step-close></div>
    <div class="oc-exp-start-times-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-exp-itinerary-steps-modal-title">
        <div class="oc-exp-start-times-modal__header">
            <h3 id="oc-exp-itinerary-steps-modal-title" class="oc-exp-start-times-modal__title">
                <?php esc_html_e('Aggiungi tappa', 'opencomune'); ?>
            </h3>
            <button type="button" class="oc-exp-start-times-modal__close" data-it-step-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>
        <div class="oc-exp-start-times-modal__body">
            <div class="oc-exp-mp-modal__field">
                <label for="oc-exp-it-step-title"><?php esc_html_e('Titolo', 'opencomune'); ?> <span class="form-required">*</span></label>
                <input type="text" id="oc-exp-it-step-title" class="form-input" maxlength="160">
            </div>

            <div class="oc-exp-mp-modal__field oc-exp-itinerary-steps-modal__description">
                <label for="oc_exp_it_step_description"><?php esc_html_e('Descrizione', 'opencomune'); ?></label>
                <textarea
                    id="oc_exp_it_step_description"
                    class="large-text oc-exp-itinerary-step__editor"
                    rows="10"
                    aria-describedby="oc-exp-it-step-description-hint"
                ></textarea>
                <p id="oc-exp-it-step-description-hint" class="oc-exp-inclusion__hint">
                    <?php esc_html_e('Puoi usare elenchi, link e formattazione di base.', 'opencomune'); ?>
                </p>
            </div>

            <div class="oc-exp-mp-modal__field" id="oc-exp-it-step-autocomplete-wrap">
                <div class="oc-exp-mp-modal__label-row">
                    <label for="oc-exp-it-step-address-search"><?php esc_html_e('Indirizzo', 'opencomune'); ?></label>
                    <span class="oc-exp-mp-modal__google"><?php esc_html_e('powered by Google', 'opencomune'); ?></span>
                </div>
                <input type="text" id="oc-exp-it-step-address-search" class="form-input" autocomplete="off" placeholder="<?php esc_attr_e('Cerca un indirizzo o un luogo…', 'opencomune'); ?>">
                <button type="button" class="oc-exp-mp-modal__manual" id="oc-exp-it-step-toggle-manual"></button>
            </div>

            <div class="oc-exp-mp-modal__manual-fields is-hidden" id="oc-exp-it-step-manual-fields">
                <div class="oc-exp-mp-modal__field">
                    <label for="oc-exp-it-step-address-manual"><?php esc_html_e('Indirizzo completo', 'opencomune'); ?></label>
                    <input type="text" id="oc-exp-it-step-address-manual" class="form-input">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label for="oc-exp-it-step-lat"><?php esc_html_e('Latitudine', 'opencomune'); ?></label>
                        <input type="text" id="oc-exp-it-step-lat" class="form-input">
                    </div>
                    <div>
                        <label for="oc-exp-it-step-lng"><?php esc_html_e('Longitudine', 'opencomune'); ?></label>
                        <input type="text" id="oc-exp-it-step-lng" class="form-input">
                    </div>
                </div>
            </div>

            <div id="oc-exp-it-step-map" class="oc-exp-itinerary-steps-modal__map" aria-hidden="true"></div>

            <div class="oc-exp-mp-modal__field oc-exp-itinerary-steps-modal__gallery">
                <label><?php esc_html_e('Galleria fotografica', 'opencomune'); ?></label>
                <button type="button" class="btn-secondary oc-exp-itinerary-steps-modal__gallery-add" id="oc-exp-it-step-gallery-add">
                    <?php esc_html_e('Aggiungi immagini', 'opencomune'); ?>
                </button>
                <div id="oc-exp-it-step-gallery-preview" class="oc-exp-gallery-preview oc-exp-itinerary-steps-modal__gallery-preview"></div>
            </div>
        </div>
        <div class="oc-exp-start-times-modal__footer oc-exp-mp-modal__footer">
            <button type="button" class="btn-secondary" data-it-step-close><?php esc_html_e('Annulla', 'opencomune'); ?></button>
            <button type="button" id="oc-exp-it-step-save" class="btn-primary"><?php esc_html_e('Salva tappa', 'opencomune'); ?></button>
        </div>
    </div>
</div>
