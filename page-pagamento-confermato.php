<?php
/**
 * Template Name: Pagamento Confermato
 */

$payment_result = opencomune_handle_payment_return('success');

get_header();

$icon_class = 'oc-payment-result__icon--error';
if ($payment_result['state'] === 'success') {
    $icon_class = 'oc-payment-result__icon--success';
    $icon = 'bi-check-lg';
    $heading = __('Pagamento confermato', 'opencomune');
} elseif ($payment_result['state'] === 'pending') {
    $icon_class = 'oc-payment-result__icon--pending';
    $icon = 'bi-hourglass-split';
    $heading = __('Pagamento in elaborazione', 'opencomune');
} else {
    $icon = 'bi-exclamation-triangle';
    $heading = __('Pagamento non verificato', 'opencomune');
}
?>

<main class="oc-payment-result">
    <div class="oc-payment-result__card">
        <div class="oc-payment-result__icon <?php echo esc_attr($icon_class); ?>" aria-hidden="true">
            <i class="bi <?php echo esc_attr($icon); ?>"></i>
        </div>
        <h1 class="oc-payment-result__title"><?php echo esc_html($heading); ?></h1>
        <p class="oc-payment-result__message"><?php echo esc_html($payment_result['message']); ?></p>

        <?php if (!empty($payment_result['order'])) : ?>
            <div class="oc-payment-result__details">
                <p><strong><?php esc_html_e('Ordine', 'opencomune'); ?>:</strong> <?php echo esc_html($payment_result['order']->order_id); ?></p>
                <?php if ($payment_result['booking_id'] > 0) : ?>
                    <p><strong><?php esc_html_e('Prenotazione', 'opencomune'); ?>:</strong> #<?php echo (int) $payment_result['booking_id']; ?></p>
                <?php endif; ?>
                <p><strong><?php esc_html_e('Importo', 'opencomune'); ?>:</strong> <?php echo esc_html(number_format((float) $payment_result['order']->totale, 2, ',', '.')); ?> &euro;</p>
            </div>
        <?php endif; ?>

        <div class="oc-payment-result__actions">
            <?php if (!empty($payment_result['checkout_url'])) : ?>
                <a href="<?php echo esc_url($payment_result['checkout_url']); ?>" class="oc-payment-result__btn oc-payment-result__btn--primary">
                    <?php esc_html_e('Riprendi il checkout', 'opencomune'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url($payment_result['tour_permalink']); ?>" class="oc-payment-result__btn<?php echo empty($payment_result['checkout_url']) ? ' oc-payment-result__btn--primary' : ' oc-payment-result__btn--secondary'; ?>">
                <?php esc_html_e('Torna all\'esperienza', 'opencomune'); ?>
            </a>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="oc-payment-result__btn oc-payment-result__btn--secondary">
                <?php esc_html_e('Home', 'opencomune'); ?>
            </a>
        </div>
    </div>
</main>

<?php
get_footer();
