<?php
/**
 * HTML biglietto prenotazione (PDF / documento).
 *
 * @var array $ticket Contesto da opencomune_build_booking_ticket_context().
 */

if (!defined('ABSPATH')) {
    exit;
}

$ticket = isset($ticket) && is_array($ticket) ? $ticket : [];

function oc_ticket_esc($value) {
    return esc_html((string) $value);
}

function oc_ticket_html($value) {
    return wp_kses_post((string) $value);
}

$qr_url = isset($ticket['qr_url']) ? (string) $ticket['qr_url'] : '';
$customer_phone = isset($ticket['customer_phone']) ? trim((string) $ticket['customer_phone']) : '';
$sample_note = isset($ticket['sample_note']) ? trim((string) $ticket['sample_note']) : '';
$ticket_message = isset($ticket['ticket_message']) ? (string) $ticket['ticket_message'] : '';
$cancellation = isset($ticket['cancellation_policy']) ? (string) $ticket['cancellation_policy'] : '';
$supplier_vat = isset($ticket['supplier_vat']) ? trim((string) $ticket['supplier_vat']) : '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title><?php echo oc_ticket_esc($ticket['experience_title'] ?? __('Biglietto', 'opencomune')); ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; margin: 24px; }
        .header { display: table; width: 100%; margin-bottom: 18px; }
        .header-col { display: table-cell; vertical-align: top; }
        .qr { width: 100px; height: 100px; }
        .qr-code { font-size: 10px; text-align: center; margin-top: 4px; }
        .badge { display: inline-block; background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 999px; font-weight: 600; font-size: 10px; }
        .customer { margin: 4px 0; }
        .supplier-name { font-weight: 700; font-size: 13px; margin: 0 0 6px; }
        .supplier-line { margin: 2px 0; font-size: 10px; }
        .section { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; margin-bottom: 14px; }
        .section-head { font-weight: 700; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 8px; }
        .product-title { font-weight: 700; font-size: 13px; margin: 0 0 4px; }
        .muted { color: #6b7280; font-size: 10px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .invoice-table th, .invoice-table td { border-bottom: 1px solid #e5e7eb; padding: 6px 4px; text-align: left; vertical-align: top; }
        .invoice-table th { font-size: 9px; color: #6b7280; }
        .total { text-align: right; font-size: 13px; margin-top: 10px; }
        .footer h4 { margin: 0 0 4px; font-size: 11px; }
        .footer p { margin: 0 0 10px; font-size: 10px; line-height: 1.45; }
        .rich-content ul, .rich-content ol { margin: 0 0 8px 18px; padding: 0; }
        .rich-content li { margin: 0 0 4px; }
        .rich-content p { margin: 0 0 8px; }
        .rich-content h2, .rich-content h3 { margin: 10px 0 6px; font-size: 11px; }
        .rich-content strong { font-weight: 700; }
        .sample-note { font-size: 9px; color: #6b7280; font-style: italic; margin-top: 8px; }
        .cancellation-policy-page { page-break-before: always; margin-top: 0; }
        .cancellation-policy-page .rich-content { font-size: 10px; line-height: 1.45; }
    </style>
</head>
<body>
    <div class="ticket-page">
    <div class="header">
        <div class="header-col" style="width: 110px;">
            <?php if ($qr_url !== '') : ?>
                <img class="qr" src="<?php echo esc_url($qr_url); ?>" alt="">
            <?php endif; ?>
            <p class="qr-code"><?php echo oc_ticket_esc($ticket['ticket_code'] ?? ''); ?></p>
        </div>
        <div class="header-col" style="padding-left: 12px;">
            <p><?php esc_html_e('Biglietto:', 'opencomune'); ?> <strong><?php echo oc_ticket_esc($ticket['ticket_code'] ?? ''); ?></strong></p>
            <p><?php echo oc_ticket_esc($ticket['booking_ref'] ?? ''); ?></p>
            <p><span class="badge"><?php echo oc_ticket_esc($ticket['payment_status'] ?? ''); ?></span></p>
            <p class="customer"><?php echo oc_ticket_esc($ticket['customer_name'] ?? ''); ?></p>
            <p class="customer"><?php echo oc_ticket_esc($ticket['customer_email'] ?? ''); ?></p>
            <?php if ($customer_phone !== '') : ?>
                <p class="customer"><?php echo oc_ticket_esc($customer_phone); ?></p>
            <?php endif; ?>
        </div>
        <div class="header-col" style="text-align: right; padding-left: 12px;">
            <p style="font-weight: 700; margin: 0;"><?php echo oc_ticket_esc(get_bloginfo('name')); ?></p>
            <p class="supplier-name"><?php echo oc_ticket_esc($ticket['supplier_name'] ?? ''); ?></p>
            <?php if ($supplier_vat !== '') : ?>
                <p class="supplier-line"><?php echo oc_ticket_esc($supplier_vat); ?></p>
            <?php endif; ?>
            <?php if (!empty($ticket['supplier_phone'])) : ?>
                <p class="supplier-line"><?php echo oc_ticket_esc($ticket['supplier_phone']); ?></p>
            <?php endif; ?>
            <?php if (!empty($ticket['supplier_email'])) : ?>
                <p class="supplier-line"><?php echo oc_ticket_esc($ticket['supplier_email']); ?></p>
            <?php endif; ?>
            <?php if (!empty($ticket['supplier_web'])) : ?>
                <p class="supplier-line"><?php echo oc_ticket_esc($ticket['supplier_web']); ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <div class="section-head"><?php esc_html_e('Prodotto', 'opencomune'); ?></div>
        <p class="product-title"><?php echo oc_ticket_esc($ticket['experience_title'] ?? ''); ?></p>
        <p><?php echo oc_ticket_esc($ticket['rate_label'] ?? ''); ?></p>
        <p><?php echo oc_ticket_esc($ticket['start_time'] ?? ''); ?></p>
        <?php if (trim(wp_strip_all_tags($ticket_message)) !== '') : ?>
            <div><?php echo oc_ticket_html($ticket_message); ?></div>
        <?php endif; ?>
        <p style="margin-top: 8px;"><strong><?php echo oc_ticket_esc($ticket['quantity_summary'] ?? ''); ?></strong></p>
    </div>

    <div class="section">
        <div class="section-head"><?php esc_html_e('Fattura', 'opencomune'); ?></div>
        <table class="invoice-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Prodotto', 'opencomune'); ?></th>
                    <th><?php esc_html_e('Quantità', 'opencomune'); ?></th>
                    <th><?php esc_html_e('Prezzo unitario', 'opencomune'); ?></th>
                    <th><?php esc_html_e('Subtotale', 'opencomune'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <p style="margin: 0;"><?php echo oc_ticket_esc($ticket['invoice_line_title'] ?? ''); ?></p>
                        <p class="muted"><?php echo oc_ticket_esc($ticket['invoice_line_datetime'] ?? ''); ?></p>
                        <p><?php echo oc_ticket_esc($ticket['invoice_line_category'] ?? ''); ?></p>
                    </td>
                    <td><?php echo oc_ticket_esc($ticket['invoice_qty'] ?? ''); ?></td>
                    <td><?php echo oc_ticket_esc($ticket['invoice_unit'] ?? ''); ?></td>
                    <td><?php echo oc_ticket_esc($ticket['invoice_subtotal'] ?? ''); ?></td>
                </tr>
            </tbody>
        </table>
        <p class="total"><?php esc_html_e('Totale:', 'opencomune'); ?> <strong><?php echo oc_ticket_esc($ticket['invoice_total'] ?? ''); ?></strong></p>
        <?php if ($sample_note !== '') : ?>
            <p class="sample-note"><?php echo oc_ticket_esc($sample_note); ?></p>
        <?php endif; ?>
    </div>

    <div class="footer">
        <h4><?php esc_html_e('Orario di avvio', 'opencomune'); ?></h4>
        <p><?php echo oc_ticket_esc($ticket['operating_hours'] ?? ''); ?></p>
    </div>
    </div>

    <?php if (trim(wp_strip_all_tags($cancellation)) !== '') : ?>
        <div class="cancellation-policy-page">
            <div class="rich-content"><?php echo oc_ticket_html($cancellation); ?></div>
        </div>
    <?php endif; ?>
</body>
</html>
