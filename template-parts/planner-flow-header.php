<?php
/**
 * Header compatto durante il wizard pianificatore (dopo la landing).
 */

if (!defined('ABSPATH')) {
    exit;
}

$visible = !empty($args['visible']);
$home_url = home_url('/');
$exit_url = apply_filters('opencomune_planner_exit_url', $home_url);
?>

<header
    id="oc-planner-app-header"
    class="oc-planner-app-header<?php echo $visible ? '' : ' is-hidden'; ?>"
    aria-hidden="<?php echo $visible ? 'false' : 'true'; ?>"
>
    <div class="oc-planner-app-header__inner">
        <a href="<?php echo esc_url($home_url); ?>" class="oc-planner-app-header__logo" rel="home">
            <?php
            $logo_id = (int) get_theme_mod('custom_logo');
            if ($logo_id > 0) {
                echo wp_get_attachment_image(
                    $logo_id,
                    'medium',
                    false,
                    [
                        'class' => 'oc-planner-app-header__logo-image',
                        'alt' => get_bloginfo('name'),
                    ]
                );
            } else {
                echo '<span class="oc-planner-app-header__logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
            }
            ?>
        </a>
        <p class="oc-planner-app-header__title">
            <?php esc_html_e('Pianifica la tua esperienza', 'opencomune'); ?>
        </p>
        <div class="oc-planner-app-header__actions">
            <button type="button" class="oc-planner-app-header__back" id="oc-planner-header-back" hidden>
                <?php esc_html_e('Modifica destinazione', 'opencomune'); ?>
            </button>
            <a class="oc-planner-app-header__exit" href="<?php echo esc_url($exit_url); ?>">
                <?php esc_html_e('Esci', 'opencomune'); ?>
            </a>
        </div>
    </div>
</header>
