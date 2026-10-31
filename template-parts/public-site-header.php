<?php
/**
 * Header frontend pubblico (contatti | logo | menu).
 */

if (!defined('ABSPATH')) {
    exit;
}

$telefono = trim((string) get_option('opencomune_telefono_ufficio', ''));
$email = trim((string) get_option('opencomune_email_ufficio', ''));
$whatsapp_digits = preg_replace('/\D+/', '', $telefono);
$whatsapp_url = $whatsapp_digits !== '' ? 'https://wa.me/' . $whatsapp_digits : '';
$facebook_url = trim((string) get_option('opencomune_social_facebook', ''));
$instagram_url = trim((string) get_option('opencomune_social_instagram', ''));
$has_header_contact = $telefono !== '' || $email !== '';
$has_header_social = $facebook_url !== '' || $instagram_url !== '';
$show_header_top = $has_header_contact || $has_header_social;
$overlay_telefono = $telefono !== '' ? $telefono : '+39 335 878 3067';
$overlay_email = $email !== '' ? $email : 'pmt@wewelcome.it';
$overlay_whatsapp_digits = preg_replace('/\D+/', '', $overlay_telefono);
$overlay_whatsapp_url = $overlay_whatsapp_digits !== '' ? 'https://wa.me/' . $overlay_whatsapp_digits : '';

ob_start();
?>
<a href="<?php echo esc_url(home_url('/')); ?>" class="site-logo" rel="home">
    <?php
    $logo_id = (int) get_theme_mod('custom_logo');
    if ($logo_id > 0) {
        echo wp_get_attachment_image(
            $logo_id,
            'medium',
            false,
            [
                'class' => 'site-logo__image',
                'alt' => get_bloginfo('name'),
            ]
        );
    } else {
        echo '<span class="site-logo__text">' . esc_html(get_bloginfo('name')) . '</span>';
    }
    ?>
</a>
<?php
$opencomune_public_site_logo = ob_get_clean();
?>

<header id="site-header" class="site-header site-header--public oc-public-header">
    <?php if ($show_header_top) : ?>
        <div class="site-header__top">
            <div class="site-header__top-inner">
                <?php if ($telefono !== '') : ?>
                    <a class="site-header__top-link" href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $telefono)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Telefono: %s', 'opencomune'), $telefono)); ?>">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <?php if ($email !== '') : ?>
                    <a class="site-header__top-link" href="<?php echo esc_url('mailto:' . $email); ?>" aria-label="<?php echo esc_attr(sprintf(__('Email: %s', 'opencomune'), $email)); ?>">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <?php if ($whatsapp_url !== '') : ?>
                    <a class="site-header__top-link" href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('WhatsApp', 'opencomune'); ?>">
                        <i class="bi bi-whatsapp" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <?php if ($facebook_url !== '') : ?>
                    <a class="site-header__top-link" href="<?php echo esc_url($facebook_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Facebook', 'opencomune'); ?>">
                        <i class="bi bi-facebook" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
                <?php if ($instagram_url !== '') : ?>
                    <a class="site-header__top-link" href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Instagram', 'opencomune'); ?>">
                        <i class="bi bi-instagram" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="site-header__inner">
        <div class="site-header__brand">
            <?php echo $opencomune_public_site_logo; ?>
            <p class="site-header__payoff">
                <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
            </p>
        </div>

        <div class="site-header__right">
            <nav class="site-nav site-nav--desktop" id="site-nav-desktop" aria-label="<?php esc_attr_e('Menu principale', 'opencomune'); ?>">
                <?php opencomune_render_header_nav_menu('site-nav__list'); ?>
            </nav>

            <div class="site-header__actions">
                <?php if (is_user_logged_in() && opencomune_user_can_access_dashboard()) : ?>
                    <?php
                    $user = wp_get_current_user();
                    $initials = '';
                    if ($user->first_name && $user->last_name) {
                        $initials = strtoupper(mb_substr($user->first_name, 0, 1) . mb_substr($user->last_name, 0, 1));
                    } else {
                        $initials = strtoupper(mb_substr($user->display_name, 0, 2));
                    }
                    ?>
                    <div class="site-header__user">
                        <button id="user-menu-btn" type="button" class="site-header__user-btn" aria-expanded="false" aria-haspopup="true" aria-controls="user-menu-dropdown">
                            <?php echo esc_html($initials); ?>
                        </button>
                        <div id="user-menu-dropdown" class="site-header__user-menu is-hidden" role="menu">
                            <a href="<?php echo esc_url(opencomune_get_dashboard_url()); ?>" role="menuitem"><?php esc_html_e('Dashboard', 'opencomune'); ?></a>
                            <a href="<?php echo esc_url(opencomune_get_dashboard_profile_url()); ?>" role="menuitem"><?php esc_html_e('Profilo', 'opencomune'); ?></a>
                            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" role="menuitem"><?php esc_html_e('Esci', 'opencomune'); ?></a>
                        </div>
                    </div>
                <?php endif; ?>

                <button type="button" class="site-nav__toggle" id="site-nav-toggle" aria-label="<?php esc_attr_e('Apri menu', 'opencomune'); ?>" aria-expanded="false" aria-controls="site-nav-overlay">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</header>

<div class="site-nav__overlay" id="site-nav-overlay" aria-hidden="true">
    <button type="button" class="site-nav__close" id="site-nav-close" aria-label="<?php esc_attr_e('Chiudi menu', 'opencomune'); ?>">&times;</button>
    <div class="site-nav__overlay-inner">
        <div class="site-nav__overlay-brand">
            <div class="site-nav__overlay-brand-row">
                <?php echo $opencomune_public_site_logo; ?>
                <p class="site-nav__overlay-payoff">
                    <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                    <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
                </p>
            </div>
        </div>

        <div class="site-nav__overlay-contact" aria-label="<?php esc_attr_e('Contatti', 'opencomune'); ?>">
            <a class="site-nav__overlay-contact-link" href="<?php echo esc_url('tel:' . preg_replace('/\s+/', '', $overlay_telefono)); ?>" aria-label="<?php echo esc_attr(sprintf(__('Telefono: %s', 'opencomune'), $overlay_telefono)); ?>">
                <i class="bi bi-telephone" aria-hidden="true"></i>
            </a>
            <a class="site-nav__overlay-contact-link" href="<?php echo esc_url('mailto:' . $overlay_email); ?>" aria-label="<?php echo esc_attr(sprintf(__('Email: %s', 'opencomune'), $overlay_email)); ?>">
                <i class="bi bi-envelope" aria-hidden="true"></i>
            </a>
            <?php if ($overlay_whatsapp_url !== '') : ?>
                <a class="site-nav__overlay-contact-link" href="<?php echo esc_url($overlay_whatsapp_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('WhatsApp', 'opencomune'); ?>">
                    <i class="bi bi-whatsapp" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if ($facebook_url !== '') : ?>
                <a class="site-nav__overlay-contact-link" href="<?php echo esc_url($facebook_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Facebook', 'opencomune'); ?>">
                    <i class="bi bi-facebook" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
            <?php if ($instagram_url !== '') : ?>
                <a class="site-nav__overlay-contact-link" href="<?php echo esc_url($instagram_url); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('Instagram', 'opencomune'); ?>">
                    <i class="bi bi-instagram" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>

        <nav class="site-nav site-nav--overlay" aria-label="<?php esc_attr_e('Menu principale', 'opencomune'); ?>">
            <?php opencomune_render_header_nav_menu('site-nav__overlay-list'); ?>
        </nav>

        <?php if (is_user_logged_in() && opencomune_user_can_access_dashboard()) : ?>
            <div class="site-nav__overlay-account">
                <a href="<?php echo esc_url(opencomune_get_dashboard_url()); ?>"><?php esc_html_e('Dashboard', 'opencomune'); ?></a>
                <a href="<?php echo esc_url(opencomune_get_dashboard_profile_url()); ?>"><?php esc_html_e('Profilo', 'opencomune'); ?></a>
                <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>"><?php esc_html_e('Esci', 'opencomune'); ?></a>
            </div>
        <?php endif; ?>
    </div>
</div>
