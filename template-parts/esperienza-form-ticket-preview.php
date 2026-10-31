<?php
/**
 * Documento anteprima biglietto (voucher prenotazione).
 */
?>

<div class="oc-ticket-doc" id="oc-ticket-doc">
    <header class="oc-ticket-doc__header">
        <div class="oc-ticket-doc__qr-col">
            <img class="oc-ticket-doc__qr" data-oc-field="qr" src="" alt="" width="120" height="120">
            <p class="oc-ticket-doc__qr-code" data-oc-field="ticket_code"></p>
        </div>
        <div class="oc-ticket-doc__ticket-meta">
            <p class="oc-ticket-doc__ticket-line">
                <?php esc_html_e('Biglietto:', 'opencomune'); ?>
                <strong data-oc-field="ticket_code_inline"></strong>
            </p>
            <p class="oc-ticket-doc__ref" data-oc-field="booking_ref"></p>
            <p class="oc-ticket-doc__badge" data-oc-field="payment_status"></p>
            <p class="oc-ticket-doc__customer" data-oc-field="customer_name"></p>
            <p class="oc-ticket-doc__customer" data-oc-field="customer_email"></p>
            <p class="oc-ticket-doc__customer">
                <a href="#" data-oc-field="customer_phone_link" data-oc-href="customer_phone"></a>
            </p>
        </div>
        <div class="oc-ticket-doc__brand-col">
            <div class="oc-ticket-doc__platform">
                <?php if (has_custom_logo()) : ?>
                    <?php the_custom_logo(); ?>
                <?php else : ?>
                    <span class="oc-ticket-doc__platform-name"><?php echo esc_html(get_bloginfo('name')); ?></span>
                <?php endif; ?>
                <p class="oc-ticket-doc__platform-tagline"><?php echo esc_html(get_bloginfo('description')); ?></p>
            </div>
            <div class="oc-ticket-doc__supplier">
                <p class="oc-ticket-doc__supplier-name" data-oc-field="supplier_name"></p>
                <p class="oc-ticket-doc__supplier-line" data-oc-field="supplier_vat" hidden></p>
                <p class="oc-ticket-doc__supplier-line">
                    <a href="#" data-oc-field="supplier_phone_link" data-oc-href="supplier_phone"></a>
                </p>
                <p class="oc-ticket-doc__supplier-line">
                    <a href="#" data-oc-field="supplier_email_link" data-oc-href="supplier_email"></a>
                </p>
                <p class="oc-ticket-doc__supplier-line">
                    <a href="#" data-oc-field="supplier_web_link" data-oc-href="supplier_web" target="_blank" rel="noopener noreferrer"></a>
                </p>
            </div>
        </div>
    </header>

    <section class="oc-ticket-doc__section">
        <div class="oc-ticket-doc__section-head">
            <span><?php esc_html_e('Prodotto', 'opencomune'); ?></span>
            <span><?php esc_html_e('Quantità', 'opencomune'); ?></span>
        </div>
        <div class="oc-ticket-doc__product-row">
            <div class="oc-ticket-doc__product-main">
                <p class="oc-ticket-doc__product-title" data-oc-field="experience_title"></p>
                <p class="oc-ticket-doc__product-rate" data-oc-field="rate_label"></p>
                <p class="oc-ticket-doc__product-start" data-oc-field="start_time"></p>
                <div class="oc-ticket-doc__product-message oc-esperienza-rich-content" data-oc-field="ticket_message"></div>
            </div>
            <div class="oc-ticket-doc__product-qty" data-oc-field="quantity_summary"></div>
        </div>
    </section>

    <section class="oc-ticket-doc__section oc-ticket-doc__invoice">
        <h3 class="oc-ticket-doc__invoice-title"><?php esc_html_e('Fattura', 'opencomune'); ?></h3>
        <div class="oc-ticket-doc__invoice-head">
            <span><?php esc_html_e('Prodotto', 'opencomune'); ?></span>
            <span><?php esc_html_e('Quantità', 'opencomune'); ?></span>
            <span><?php esc_html_e('Prezzo unitario', 'opencomune'); ?></span>
            <span><?php esc_html_e('Subtotale', 'opencomune'); ?></span>
        </div>
        <div class="oc-ticket-doc__invoice-row">
            <div class="oc-ticket-doc__invoice-product">
                <p data-oc-field="invoice_line_title"></p>
                <p class="oc-ticket-doc__muted" data-oc-field="invoice_line_datetime"></p>
                <p data-oc-field="invoice_line_category"></p>
            </div>
            <span data-oc-field="invoice_qty"></span>
            <span data-oc-field="invoice_unit"></span>
            <span data-oc-field="invoice_subtotal"></span>
        </div>
        <div class="oc-ticket-doc__invoice-total">
            <span><?php esc_html_e('Totale:', 'opencomune'); ?></span>
            <strong data-oc-field="invoice_total"></strong>
        </div>
        <p class="oc-ticket-doc__sample-note" data-oc-field="sample_note"></p>
    </section>

    <footer class="oc-ticket-doc__footer">
        <div class="oc-ticket-doc__footer-block oc-ticket-doc__footer-block--cancellation">
            <div class="oc-ticket-doc__footer-text oc-esperienza-rich-content" data-oc-field="cancellation_policy"></div>
        </div>
        <div class="oc-ticket-doc__footer-block">
            <h4><?php esc_html_e('Orario di avvio', 'opencomune'); ?></h4>
            <p class="oc-ticket-doc__footer-text" data-oc-field="operating_hours"></p>
        </div>
    </footer>
</div>
