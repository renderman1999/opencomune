<?php
/**
 * Card categoria esperienze (homepage).
 *
 * @var array $args
 */

$term = isset($args['term']) ? $args['term'] : null;
if (!$term instanceof WP_Term) {
    return;
}

$name = function_exists('opencomune_sanitize_taxonomy_display_name')
    ? opencomune_sanitize_taxonomy_display_name($term->name)
    : $term->name;
$url = opencomune_get_esperienza_category_archive_url($term);
if ($url === '') {
    return;
}

$image_url = function_exists('opencomune_esperienza_category_get_image_url')
    ? opencomune_esperienza_category_get_image_url((int) $term->term_id)
    : '';
?>

<article class="oc-home-category-card">
    <a href="<?php echo esc_url($url); ?>" class="oc-home-category-card__link">
        <div class="oc-home-category-card__media">
            <?php if ($image_url !== '') : ?>
                <img
                    src="<?php echo esc_url($image_url); ?>"
                    alt="<?php echo esc_attr(sprintf(__('Categoria esperienze: %s', 'opencomune'), $name)); ?>"
                    class="oc-home-category-card__img"
                    loading="lazy"
                    decoding="async"
                >
            <?php else : ?>
                <div class="oc-home-category-card__placeholder" aria-hidden="true">
                    <i class="bi bi-tag"></i>
                </div>
            <?php endif; ?>
            <div class="oc-home-category-card__overlay">
                <h3 class="oc-home-category-card__title"><?php echo esc_html($name); ?></h3>
            </div>
        </div>
    </a>
</article>
