<?php
/**
 * Template Name: Pagamento Annullato
 */

$payment_result = opencomune_handle_payment_return('cancel');

get_header();
?>

<main class="oc-payment-result">
    <div class="oc-payment-result__card">
        <div class="oc-payment-result__icon oc-payment-result__icon--error" aria-hidden="true">
            <i class="bi bi-x-lg"></i>
        </div>
        <h1 class="oc-payment-result__title"><?php esc_html_e('Pagamento annullato', 'opencomune'); ?></h1>
        <p class="oc-payment-result__message"><?php echo esc_html($payment_result['message']); ?></p>
        <div class="oc-payment-result__actions">
            <?php if (!empty($payment_result['checkout_url'])) : ?>
                <a href="<?php echo esc_url($payment_result['checkout_url']); ?>" class="oc-payment-result__btn oc-payment-result__btn--primary">
                    <?php esc_html_e('Riprendi il checkout', 'opencomune'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url($payment_result['tour_permalink']); ?>" class="oc-payment-result__btn<?php echo empty($payment_result['checkout_url']) ? ' oc-payment-result__btn--primary' : ' oc-payment-result__btn--secondary'; ?>">
                <?php esc_html_e('Torna all\'esperienza', 'opencomune'); ?>
            </a>
        </div>
    </div>
</main>

<?php
get_footer();
