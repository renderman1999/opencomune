<?php
/**
 * Categorie prezzo (tariffe per tipologia di partecipante).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$categories = opencomune_resolve_pricing_categories_for_form($post_id);

opencomune_enqueue_pricing_categories_editor();
$payload_json = wp_json_encode($categories);
?>

<div class="form-section p-6 oc-exp-pricing" id="oc-exp-pricing-root">
    <h2 class="oc-exp-pricing__title">
        <?php esc_html_e('Prezzi per categoria partecipanti', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-pricing__lead">
        <?php esc_html_e('Definisci un prezzo per ogni tipologia (es. Adulti 22 €, Bambini 10 €). Usa 0 € per una categoria gratuita. L’esperienza è gratuita solo se tutte le categorie sono a 0 €.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-pricing__list-head">
        <h3 class="oc-exp-pricing__list-title">
            <?php esc_html_e('Categorie e prezzi', 'opencomune'); ?>
        </h3>
        <button type="button" class="oc-exp-pricing__create-btn" id="oc-exp-pricing-create">
            <?php esc_html_e('Crea categoria prezzo', 'opencomune'); ?>
        </button>
    </div>

    <ul class="oc-exp-pricing__list" id="oc-exp-pricing-list" aria-live="polite"></ul>
    <p class="oc-exp-pricing__empty" id="oc-exp-pricing-empty"<?php echo empty($categories) ? '' : ' hidden'; ?>>
        <?php esc_html_e('Nessuna categoria prezzo selezionata.', 'opencomune'); ?>
    </p>

    <textarea name="tour_pricing_categories_payload" id="tour_pricing_categories_payload" class="screen-reader-text" aria-hidden="true" rows="1"><?php echo esc_textarea($payload_json); ?></textarea>
</div>

<div id="oc-exp-pricing-modal" class="oc-exp-pricing-modal" hidden role="dialog" aria-modal="true" aria-labelledby="oc-exp-pricing-modal-title">
    <div class="oc-exp-pricing-modal__panel">
        <div class="oc-exp-pricing-modal__header">
            <h3 id="oc-exp-pricing-modal-title" class="oc-exp-pricing-modal__title"></h3>
            <button type="button" class="oc-exp-pricing-modal__close" data-pricing-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>
        <div class="oc-exp-pricing-modal__field">
            <label for="oc-exp-pricing-label"><?php esc_html_e('Nome categoria', 'opencomune'); ?></label>
            <input type="text" id="oc-exp-pricing-label" class="form-input" maxlength="120" placeholder="<?php esc_attr_e('es. Adulti (18+)', 'opencomune'); ?>">
        </div>
        <div class="oc-exp-pricing-modal__field">
            <label for="oc-exp-pricing-price"><?php esc_html_e('Prezzo (€)', 'opencomune'); ?></label>
            <input type="number" id="oc-exp-pricing-price" class="form-input" min="0" step="0.01" inputmode="decimal" placeholder="<?php esc_attr_e('es. 22.00', 'opencomune'); ?>">
            <p class="oc-exp-inclusion__hint"><?php esc_html_e('0 = gratuito per questa categoria.', 'opencomune'); ?></p>
        </div>
        <div class="oc-exp-pricing-modal__field oc-exp-pricing-modal__ages">
            <div>
                <label for="oc-exp-pricing-min-age"><?php esc_html_e('Età minima', 'opencomune'); ?></label>
                <input type="number" min="0" id="oc-exp-pricing-min-age" class="form-input" placeholder="—">
            </div>
            <div>
                <label for="oc-exp-pricing-max-age"><?php esc_html_e('Età massima', 'opencomune'); ?></label>
                <input type="number" min="0" id="oc-exp-pricing-max-age" class="form-input" placeholder="—">
            </div>
        </div>
        <div class="oc-exp-pricing-modal__field">
            <label for="oc-exp-pricing-description"><?php esc_html_e('Descrizione (opzionale)', 'opencomune'); ?></label>
            <textarea id="oc-exp-pricing-description" class="form-textarea" rows="3"></textarea>
        </div>
        <div class="oc-exp-pricing-modal__footer">
            <button type="button" class="btn-secondary" data-pricing-close><?php esc_html_e('Annulla', 'opencomune'); ?></button>
            <button type="button" class="btn-primary" id="oc-exp-pricing-save"><?php esc_html_e('Salva', 'opencomune'); ?></button>
        </div>
    </div>
</div>
