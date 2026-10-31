<?php
/**
 * Identificazione interna esperienza (codice prodotto + tassonomie).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
opencomune_enqueue_esperienza_identification_assets();

$tax_map = opencomune_get_esperienza_identification_taxonomies();

$flags_terms = opencomune_get_identification_terms_for_form($tax_map['flags'], $post_id);
$group_labels_terms = opencomune_get_identification_terms_for_form($tax_map['product_group_labels'], $post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_code = isset($_POST['tour_internal_product_code'])
        ? sanitize_text_field(wp_unslash($_POST['tour_internal_product_code']))
        : '';
    $selected_flags = isset($_POST['tour_esperienza_flags'])
        ? array_map('sanitize_text_field', (array) $_POST['tour_esperienza_flags'])
        : [];
    $selected_group_labels = isset($_POST['tour_esperienza_group_labels'])
        ? array_map('sanitize_text_field', (array) $_POST['tour_esperienza_group_labels'])
        : [];
} else {
    $identification = opencomune_get_esperienza_identification($post_id);
    $product_code = $identification['product_code'];
    $selected_flags = $identification['flags'];
    $selected_group_labels = $identification['product_group_labels'];
}

$can_manage_terms = opencomune_user_can_manage_global_identification_terms();
$can_manage_partner_terms = opencomune_user_can_manage_partner_identification_terms();
$allow_create_terms = $can_manage_terms || $can_manage_partner_terms;
$group_labels_admin_url = opencomune_get_esperienza_identification_taxonomy_admin_url($tax_map['product_group_labels']);
?>

<div class="form-section p-6 oc-exp-category-tags oc-exp-identification">
    <h2 class="oc-exp-category-tags__title">
        <?php esc_html_e('Devi organizzare le esperienze internamente?', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-category-tags__lead">
        <?php esc_html_e('Puoi impostare identificatori personali, come il codice prodotto interno o dati recuperabili tramite API.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-category-tags__field">
        <label for="tour_internal_product_code" class="form-label"><?php esc_html_e('Codice prodotto', 'opencomune'); ?></label>
        <p class="oc-exp-category-tags__hint">
            <?php esc_html_e('(Facoltativo) Codice SKU interno. L’ID numerico dell’esperienza in piattaforma resta quello mostrato in sidebar.', 'opencomune'); ?>
        </p>
        <input
            type="text"
            class="form-input oc-exp-identification__input"
            id="tour_internal_product_code"
            name="tour_internal_product_code"
            value="<?php echo esc_attr($product_code); ?>"
            placeholder="<?php esc_attr_e('Inserisci codice prodotto…', 'opencomune'); ?>"
            maxlength="120"
            autocomplete="off"
        >
    </div>

    <div class="oc-exp-category-tags__field">
        <label for="tour_esperienza_flags" class="form-label"><?php esc_html_e('Flag', 'opencomune'); ?></label>
        <select id="tour_esperienza_flags" name="tour_esperienza_flags[]" class="oc-exp-identification__select" multiple data-placeholder="<?php esc_attr_e('Seleziona…', 'opencomune'); ?>" data-allow-tags="<?php echo $allow_create_terms ? '1' : '0'; ?>">
            <?php foreach ($flags_terms as $term) : ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(in_array($term->slug, $selected_flags, true)); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
            <?php opencomune_render_identification_select_orphan_options($flags_terms, $selected_flags); ?>
        </select>
        <?php if ($allow_create_terms && empty($flags_terms)) : ?>
            <p class="oc-exp-identification__empty-hint">
                <?php
                if ($can_manage_terms) {
                    printf(
                        /* translators: %s: admin URL */
                        wp_kses_post(__('Nessun flag: scrivi un nome nel campo per crearne uno oppure <a href="%s">gestiscili in amministrazione</a>.', 'opencomune')),
                        esc_url(opencomune_get_esperienza_identification_taxonomy_admin_url($tax_map['flags']))
                    );
                } else {
                    esc_html_e('Nessun flag ancora: digita un nome nel campo e premi Invio per crearne uno (resta associato al tuo account partner).', 'opencomune');
                }
                ?>
            </p>
        <?php elseif (!$allow_create_terms && empty($flags_terms)) : ?>
            <p class="oc-exp-identification__empty-hint"><?php esc_html_e('Nessun flag disponibile.', 'opencomune'); ?></p>
        <?php elseif ($allow_create_terms && !empty($flags_terms)) : ?>
            <p class="oc-exp-identification__empty-hint"><?php esc_html_e('Puoi aggiungere nuovi flag digitando un nome e premendo Invio.', 'opencomune'); ?></p>
        <?php endif; ?>
    </div>

    <div class="oc-exp-category-tags__field">
        <label for="tour_esperienza_group_labels" class="form-label"><?php esc_html_e('Etichette gruppo prodotto', 'opencomune'); ?></label>
        <p class="oc-exp-category-tags__hint">
            <?php esc_html_e('Quando questa attività viene prenotata, le etichette di gruppo selezionate vengono applicate automaticamente alla prenotazione.', 'opencomune'); ?>
        </p>
        <select id="tour_esperienza_group_labels" name="tour_esperienza_group_labels[]" class="oc-exp-identification__select" multiple data-placeholder="<?php esc_attr_e('Seleziona…', 'opencomune'); ?>" data-allow-tags="<?php echo $allow_create_terms ? '1' : '0'; ?>">
            <?php foreach ($group_labels_terms as $term) : ?>
                <option value="<?php echo esc_attr($term->slug); ?>" <?php selected(in_array($term->slug, $selected_group_labels, true)); ?>>
                    <?php echo esc_html($term->name); ?>
                </option>
            <?php endforeach; ?>
            <?php opencomune_render_identification_select_orphan_options($group_labels_terms, $selected_group_labels); ?>
        </select>
        <?php if ($allow_create_terms && empty($group_labels_terms)) : ?>
            <p class="oc-exp-identification__empty-hint">
                <?php
                if ($can_manage_terms) {
                    printf(
                        wp_kses_post(__('Nessuna etichetta: scrivi un nome nel campo per crearne una oppure <a href="%s">gestiscile in amministrazione</a>.', 'opencomune')),
                        esc_url($group_labels_admin_url)
                    );
                } else {
                    esc_html_e('Nessuna etichetta ancora: digita un nome nel campo e premi Invio per crearne una (resta associata al tuo account partner).', 'opencomune');
                }
                ?>
            </p>
        <?php elseif (!$allow_create_terms && empty($group_labels_terms)) : ?>
            <p class="oc-exp-identification__empty-hint"><?php esc_html_e('Nessuna etichetta di gruppo disponibile.', 'opencomune'); ?></p>
        <?php elseif ($allow_create_terms && !empty($group_labels_terms)) : ?>
            <p class="oc-exp-identification__empty-hint"><?php esc_html_e('Puoi aggiungere nuove etichette digitando un nome e premendo Invio.', 'opencomune'); ?></p>
        <?php endif; ?>
    </div>

    <?php if ($can_manage_terms) : ?>
        <div class="oc-exp-identification__notice" role="note">
            <?php
            printf(
                wp_kses_post(
                    __('Per gestire le etichette applicate alle prenotazioni, vai su <a href="%s">Etichette gruppo prodotto</a>.', 'opencomune')
                ),
                esc_url($group_labels_admin_url)
            );
            ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof jQuery === 'undefined' || !jQuery.fn.select2) {
        return;
    }

    jQuery('.oc-exp-identification .oc-exp-identification__select').each(function () {
        const $select = jQuery(this);
        if ($select.data('select2')) {
            $select.select2('destroy');
        }

        const allowTags = $select.attr('data-allow-tags') === '1';
        const config = {
            width: '100%',
            placeholder: $select.attr('data-placeholder') || 'Seleziona…',
            allowClear: true,
            closeOnSelect: false,
        };

        if (allowTags) {
            config.tags = true;
            config.tokenSeparators = [','];
            config.createTag = function (params) {
                const term = jQuery.trim(params.term);
                if (term === '') {
                    return null;
                }

                return {
                    id: term,
                    text: term,
                    newTag: true,
                };
            };
        }

        $select.select2(config);
    });
});
</script>
