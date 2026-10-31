<?php
/**
 * Impostazioni biglietto per i viaggiatori.
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
opencomune_enqueue_esperienza_tickets_assets($post_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'ticket_per_participant' => !empty($_POST['tour_ticket_per_participant']),
        'override_barcode_format' => !empty($_POST['tour_ticket_override_barcode']),
        'ticket_message' => isset($_POST['tour_ticket_message'])
            ? wp_kses_post(wp_unslash($_POST['tour_ticket_message']))
            : '',
    ];
} else {
    $settings = opencomune_get_esperienza_ticket_settings($post_id);
}

$experience_title = $post_id > 0 ? get_the_title($post_id) : '';
if ($experience_title === '' && isset($_POST['tour_title'])) {
    $experience_title = sanitize_text_field(wp_unslash($_POST['tour_title']));
}
?>

<div class="form-section p-6 oc-exp-tickets" data-oc-ticket-preview-title="<?php echo esc_attr($experience_title); ?>">
    <h2 class="oc-exp-tickets__title">
        <?php esc_html_e('Personalizza i biglietti che ricevono i viaggiatori', 'opencomune'); ?>
    </h2>
    <p class="oc-exp-tickets__intro">
        <?php esc_html_e('Puoi modificare le impostazioni del biglietto e aggiungere informazioni importanti.', 'opencomune'); ?>
    </p>

    <div class="oc-exp-tickets__block">
        <label class="oc-exp-tickets__toggle-row">
            <span><?php esc_html_e('Biglietto per partecipante', 'opencomune'); ?></span>
            <span class="oc-exp-switch">
                <input type="checkbox" name="tour_ticket_per_participant" value="1"<?php checked($settings['ticket_per_participant']); ?>>
                <span class="oc-exp-switch__track" aria-hidden="true"></span>
            </span>
        </label>
    </div>

    <div class="oc-exp-tickets__block oc-exp-tickets__editor-block">
        <label for="tour_ticket_message" class="oc-exp-tickets__subtitle">
            <?php esc_html_e('Messaggio da mostrare sul biglietto', 'opencomune'); ?>
        </label>
        <p class="oc-exp-tickets__hint">
            <?php esc_html_e('Questo messaggio sarà visibile su tutti i biglietti di questa esperienza.', 'opencomune'); ?>
        </p>
        <?php
        wp_editor(
            $settings['ticket_message'],
            'tour_ticket_message',
            [
                'textarea_name' => 'tour_ticket_message',
                'media_buttons' => true,
                'teeny' => false,
                'textarea_rows' => 10,
                'quicktags' => true,
                'editor_class' => 'oc-exp-tickets__editor',
                'tinymce' => [
                    'toolbar1' => 'formatselect,fontsizeselect,bold,italic,underline,bullist,numlist,alignleft,aligncenter,alignright,link,unlink,wp_adv',
                    'fontsize_formats' => '12px 14px 16px 18px 24px',
                    'default_link_target' => '_blank',
                ],
            ]
        );
        ?>
    </div>

    <div class="oc-exp-tickets__block oc-exp-tickets__toggle-block">
        <label class="oc-exp-tickets__toggle-row">
            <span><?php esc_html_e('Sostituisci il formato del codice a barre', 'opencomune'); ?></span>
            <span class="oc-exp-switch">
                <input type="checkbox" name="tour_ticket_override_barcode" value="1"<?php checked($settings['override_barcode_format']); ?>>
                <span class="oc-exp-switch__track" aria-hidden="true"></span>
            </span>
        </label>
    </div>

    <div class="oc-exp-tickets__actions">
        <button type="button" class="oc-exp-tickets__preview-btn" id="oc-exp-ticket-preview-open">
            <?php esc_html_e('Anteprima biglietto', 'opencomune'); ?>
        </button>
    </div>
</div>

<div class="oc-exp-tickets-modal" id="oc-exp-ticket-preview-modal" hidden aria-hidden="true">
    <div class="oc-exp-tickets-modal__backdrop" data-oc-ticket-preview-close tabindex="-1"></div>
    <div class="oc-exp-tickets-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="oc-exp-ticket-preview-heading">
        <header class="oc-exp-tickets-modal__header">
            <h3 id="oc-exp-ticket-preview-heading"><?php esc_html_e('Anteprima biglietto', 'opencomune'); ?></h3>
            <button type="button" class="oc-exp-tickets-modal__close" data-oc-ticket-preview-close aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </header>
        <div class="oc-exp-tickets-modal__body" id="oc-exp-ticket-preview-body">
            <?php get_template_part('template-parts/esperienza-form-ticket-preview'); ?>
        </div>
    </div>
</div>
