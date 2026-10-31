<?php
/**
 * Template Name: Itinerario
 */

$token = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';

get_header();

if (function_exists('opencomune_planner_use_flow_chrome') && opencomune_planner_use_flow_chrome()) {
    get_template_part(
        'template-parts/planner-flow-header',
        null,
        [
            'visible' => true,
        ]
    );
}
?>

<main class="oc-planner oc-planner--view oc-planner--itinerary-full flex-1 w-full">
    <div
        id="oc-planner-itinerary"
        class="oc-planner-itinerary-root oc-planner-itinerary-root--loading"
        data-token="<?php echo esc_attr($token); ?>"
    >
        <div class="oc-planner-itinerary-loader" role="status" aria-live="polite">
            <div class="oc-planner-itinerary-loader__logo">
                <?php
                $logo_id = (int) get_theme_mod('custom_logo');
                if ($logo_id > 0) {
                    echo wp_get_attachment_image(
                        $logo_id,
                        'medium',
                        false,
                        [
                            'class' => 'oc-planner-itinerary-loader__logo-image',
                            'alt' => get_bloginfo('name'),
                        ]
                    );
                } else {
                    echo '<span class="oc-planner-itinerary-loader__logo-text">' . esc_html(get_bloginfo('name')) . '</span>';
                }
                ?>
            </div>
            <span class="oc-planner-itinerary-loader__spin" aria-hidden="true"></span>
            <span class="screen-reader-text"><?php esc_html_e('Caricamento itinerario...', 'opencomune'); ?></span>
        </div>
    </div>
</main>

<?php
get_footer();
