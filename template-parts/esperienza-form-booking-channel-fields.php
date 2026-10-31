<?php
/**
 * Canale vendita: prenotazione diretta, link esterno, politica pagamento.
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
opencomune_enqueue_booking_channel_assets();
$settings = opencomune_get_esperienza_booking_channel_settings($post_id);
$payment_modes = opencomune_get_esperienza_booking_payment_modes();
$payment_mode_locked = opencomune_partner_booking_payment_mode_is_fixed();
$locked_payment_mode = opencomune_get_partner_fixed_booking_payment_mode();
$locked_payment_label = $payment_modes[$locked_payment_mode] ?? $locked_payment_mode;
?>

<div class="form-section p-6 oc-exp-experience-settings">
    <h3 class="text-lg font-bold mb-2"><?php esc_html_e('Prenotazione e pagamento', 'opencomune'); ?></h3>
    <p class="text-sm text-gray-600 mb-6">
        <?php esc_html_e('Configura come i visitatori possono acquistare l’esperienza dalla scheda pubblica (equivalente al canale di vendita / checkout Bókun).', 'opencomune'); ?>
    </p>

    <div class="oc-exp-settings-card mb-6">
        <label class="oc-exp-settings-toggle">
            <input type="checkbox" name="mostra_form_prenotazione" value="1" <?php checked($settings['direct_booking_enabled']); ?>>
            <span class="oc-exp-settings-toggle__label">
                <strong><?php esc_html_e('Prenotazione diretta sulla piattaforma', 'opencomune'); ?></strong>
                <span class="oc-exp-settings-toggle__hint">
                    <?php esc_html_e('Mostra partecipanti, calendario e procedura checkout (come “Prenotazione Diretta” in anteprima).', 'opencomune'); ?>
                </span>
            </span>
        </label>
    </div>

    <div class="oc-exp-settings-card mb-6">
        <label for="tour_external_booking_url" class="form-label"><?php esc_html_e('Link prenotazione esterna (opzionale)', 'opencomune'); ?></label>
        <input type="url" class="form-input" id="tour_external_booking_url" name="tour_external_booking_url"
               value="<?php echo esc_attr($settings['external_booking_url']); ?>"
               placeholder="https://…">
        <p class="text-sm text-gray-500 mt-2">
            <?php esc_html_e('Se la prenotazione diretta è disattivata, in scheda pubblica compare il pulsante verso questo URL (es. sito partner o Bókun embedded).', 'opencomune'); ?>
        </p>
    </div>

    <div class="oc-exp-settings-card">
        <span class="form-label"><?php esc_html_e('Politica di pagamento', 'opencomune'); ?></span>
        <?php if ($payment_mode_locked) : ?>
            <input type="hidden" name="tour_booking_payment_mode" value="<?php echo esc_attr($locked_payment_mode); ?>">
            <p class="oc-exp-settings-locked-value mt-2 mb-0 font-medium text-gray-900">
                <?php echo esc_html($locked_payment_label); ?>
            </p>
            <p class="text-sm text-gray-500 mt-2 mb-0">
                <?php esc_html_e('Per le esperienze dei partner il pagamento online in fase di prenotazione è obbligatorio e non può essere modificato.', 'opencomune'); ?>
            </p>
        <?php else : ?>
            <label class="screen-reader-text" for="tour_booking_payment_mode"><?php esc_html_e('Politica di pagamento', 'opencomune'); ?></label>
            <select id="tour_booking_payment_mode" name="tour_booking_payment_mode" class="form-input mt-2">
                <?php foreach ($payment_modes as $mode_key => $mode_label) : ?>
                    <option value="<?php echo esc_attr($mode_key); ?>" <?php selected($settings['payment_mode'], $mode_key); ?>>
                        <?php echo esc_html($mode_label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <p class="text-sm text-gray-500 mt-2">
            <?php esc_html_e('Con pagamento online attivo, il visitatore passa dal wizard checkout (contatto → viaggiatori → riepilogo → gateway). Le credenziali sono quelle del partner titolare dell’esperienza o, se non configurate, quelle globali del portale.', 'opencomune'); ?>
        </p>
    </div>
</div>
