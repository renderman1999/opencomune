<?php
$tpl_args = (isset($args) && is_array($args)) ? $args : [];
$share_urls = isset($tpl_args['urls']) && is_array($tpl_args['urls']) ? $tpl_args['urls'] : [];
$group_label = isset($tpl_args['group_label']) ? (string) $tpl_args['group_label'] : __('Condividi', 'opencomune');

$facebook = $share_urls['facebook'] ?? '';
$whatsapp = $share_urls['whatsapp'] ?? '';
$email = $share_urls['email'] ?? '';

if ($facebook === '' && $whatsapp === '' && $email === '') {
    return;
}
?>
<div class="esperienza-share-buttons" role="group" aria-label="<?php echo esc_attr($group_label); ?>">
    <?php if ($facebook !== '') : ?>
        <a href="<?php echo esc_url($facebook); ?>" target="_blank" rel="noopener noreferrer" class="esperienza-share-btn esperienza-share-btn-facebook" aria-label="<?php esc_attr_e('Condividi su Facebook', 'opencomune'); ?>">
            <i class="bi bi-facebook" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
    <?php if ($whatsapp !== '') : ?>
        <a href="<?php echo esc_url($whatsapp); ?>" target="_blank" rel="noopener noreferrer" class="esperienza-share-btn esperienza-share-btn-whatsapp" aria-label="<?php esc_attr_e('Condividi su WhatsApp', 'opencomune'); ?>">
            <i class="bi bi-whatsapp" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
    <?php if ($email !== '') : ?>
        <a href="<?php echo esc_url($email); ?>" class="esperienza-share-btn esperienza-share-btn-email" aria-label="<?php esc_attr_e('Condividi via email', 'opencomune'); ?>">
            <i class="bi bi-envelope" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</div>
