<?php

if (!defined('ABSPATH')) {
    exit;
}

$badges = isset($args['badges']) && is_array($args['badges']) ? $args['badges'] : [];
$pending_approval = max(0, (int) ($badges['pending_approval'] ?? 0));
$pending_payment = max(0, (int) ($badges['pending_payment'] ?? 0));
$confermata = max(0, (int) ($badges['confermata'] ?? 0));

if ($pending_approval === 0 && $pending_payment === 0 && $confermata === 0) {
    return;
}
?>
<span class="oc-dashboard-sidebar__badges" data-booking-nav-badges>
    <?php if ($pending_approval > 0) : ?>
        <span class="oc-booking-status oc-booking-status--pending-approval oc-dashboard-sidebar__badge" title="<?php esc_attr_e('In attesa approvazione', 'opencomune'); ?>">
            <?php echo (int) $pending_approval; ?>
        </span>
    <?php endif; ?>
    <?php if ($pending_payment > 0) : ?>
        <span class="oc-booking-status oc-booking-status--pending oc-dashboard-sidebar__badge" title="<?php esc_attr_e('In attesa pagamento', 'opencomune'); ?>">
            <?php echo (int) $pending_payment; ?>
        </span>
    <?php endif; ?>
    <?php if ($confermata > 0) : ?>
        <span class="oc-booking-status oc-booking-status--confirmed oc-dashboard-sidebar__badge" title="<?php esc_attr_e('Nuove prenotazioni confermate', 'opencomune'); ?>">
            <?php echo (int) $confermata; ?>
        </span>
    <?php endif; ?>
</span>
