<?php
/**
 * Badge social proof: rotazione esperienze pubblicate nel frontend pubblico.
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @return list<array{id: int, title: string, url: string, image: string}>
 */
function opencomune_get_social_proof_esperienze_items($limit = 24) {
    if (!function_exists('opencomune_get_home_esperienze_for_visitors')) {
        return [];
    }

    $posts = opencomune_get_home_esperienze_for_visitors((int) $limit);
    if ($posts === []) {
        return [];
    }

    $items = [];
    foreach ($posts as $post) {
        $post_id = (int) $post->ID;
        $image = '';
        if (function_exists('opencomune_get_esperienza_thumbnail_url')) {
            $image = (string) opencomune_get_esperienza_thumbnail_url($post_id, 'medium');
        }
        if ($image === '' && has_post_thumbnail($post_id)) {
            $image = (string) get_the_post_thumbnail_url($post_id, 'medium');
        }

        $items[] = [
            'id' => $post_id,
            'title' => get_the_title($post_id),
            'url' => get_permalink($post_id),
            'image' => $image,
        ];
    }

    return $items;
}

function opencomune_should_show_social_proof_badge() {
    if (is_admin() || wp_doing_ajax() || wp_is_json_request()) {
        return false;
    }

    if (function_exists('opencomune_is_dashboard_app_page') && opencomune_is_dashboard_app_page()) {
        return false;
    }

    if (function_exists('opencomune_is_partner_dashboard_page') && opencomune_is_partner_dashboard_page()) {
        return false;
    }

    return opencomune_get_social_proof_esperienze_items(1) !== [];
}

function opencomune_enqueue_social_proof_badge() {
    if (!opencomune_should_show_social_proof_badge()) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    $css = $theme_dir . '/assets/css/social-proof-badge.css';
    if (is_readable($css)) {
        $style_deps = [];
        if (wp_style_is('opencomune-public-theme', 'registered') || wp_style_is('opencomune-public-theme', 'enqueued')) {
            $style_deps[] = 'opencomune-public-theme';
        }
        wp_enqueue_style(
            'opencomune-social-proof-badge',
            $theme_uri . '/assets/css/social-proof-badge.css',
            $style_deps,
            (string) filemtime($css)
        );
    }

    $js = $theme_dir . '/assets/js/social-proof-badge.js';
    if (!is_readable($js)) {
        return;
    }

    wp_enqueue_script(
        'opencomune-social-proof-badge',
        $theme_uri . '/assets/js/social-proof-badge.js',
        [],
        (string) filemtime($js),
        true
    );

    wp_localize_script('opencomune-social-proof-badge', 'opencomuneSocialProof', [
        'items' => opencomune_get_social_proof_esperienze_items(24),
        'intervalMs' => 10000,
        'visibleMs' => 6500,
        'initialDelayMs' => 4000,
        'labelTemplate' => __('%d persone stanno osservando:', 'opencomune'),
        'placeholderLetter' => __('E', 'opencomune'),
    ]);
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_social_proof_badge', 30);

function opencomune_render_social_proof_badge() {
    if (!opencomune_should_show_social_proof_badge()) {
        return;
    }
    ?>
    <div id="oc-social-proof" class="oc-social-proof" hidden>
        <a href="#" class="oc-social-proof__link" id="oc-social-proof-link">
            <span class="oc-social-proof__thumb-wrap">
                <img class="oc-social-proof__thumb" id="oc-social-proof-img" src="" alt="" width="56" height="56" loading="lazy" hidden>
                <span class="oc-social-proof__thumb-fallback" id="oc-social-proof-thumb-fallback" hidden aria-hidden="true"></span>
            </span>
            <span class="oc-social-proof__text">
                <span class="oc-social-proof__label" id="oc-social-proof-label"></span>
                <span class="oc-social-proof__title" id="oc-social-proof-title"></span>
            </span>
        </a>
        <button type="button" class="oc-social-proof__close" id="oc-social-proof-close" aria-label="<?php esc_attr_e('Chiudi notifica', 'opencomune'); ?>">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php
}
add_action('wp_footer', 'opencomune_render_social_proof_badge', 5);
