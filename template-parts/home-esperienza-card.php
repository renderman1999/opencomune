<?php
/**
 * Card esperienza in homepage (stile catalogo tour).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
if ($post_id <= 0) {
    return;
}

$in_slider = !empty($args['in_slider']);
$card_class = 'oc-home-tour-card' . ($in_slider ? ' swiper-slide' : '');

$meta = opencomune_get_home_esperienza_card_meta($post_id);
$permalink = get_permalink($post_id);
$title = get_the_title($post_id);
$excerpt = get_the_excerpt($post_id);
if (trim($excerpt) === '') {
    $excerpt = wp_trim_words(get_post_field('post_content', $post_id), 22);
}
?>
<article class="<?php echo esc_attr($card_class); ?>">
    <a href="<?php echo esc_url($permalink); ?>" class="oc-home-tour-card__link">
        <div class="oc-home-tour-card__media">
            <?php
            opencomune_render_esperienza_cover($post_id, [
                'size' => 'large',
                'img_class' => 'oc-home-tour-card__img',
                'placeholder_class' => 'oc-home-tour-card__placeholder',
            ]);
            ?>
        </div>
        <ul class="oc-home-tour-card__meta" aria-label="<?php esc_attr_e('Dettagli esperienza', 'opencomune'); ?>">
            <?php if ($meta['max_label'] !== '') : ?>
                <li><i class="bi bi-people" aria-hidden="true"></i> <?php echo esc_html($meta['max_label']); ?></li>
            <?php endif; ?>
            <?php if ($meta['duration_label'] !== '') : ?>
                <li><i class="bi bi-clock" aria-hidden="true"></i> <?php echo esc_html($meta['duration_label']); ?></li>
            <?php endif; ?>
            <?php if ($meta['city'] !== '') : ?>
                <li><i class="bi bi-geo-alt" aria-hidden="true"></i> <?php echo esc_html($meta['city']); ?></li>
            <?php endif; ?>
            <?php if ($meta['price_label'] !== '') : ?>
                <li class="oc-home-tour-card__price"><?php echo esc_html($meta['price_label']); ?></li>
            <?php endif; ?>
        </ul>
        <h3 class="oc-home-tour-card__title"><?php echo esc_html($title); ?></h3>
        <?php if (trim(wp_strip_all_tags($excerpt)) !== '') : ?>
            <p class="oc-home-tour-card__excerpt"><?php echo esc_html(wp_trim_words($excerpt, 20)); ?></p>
        <?php endif; ?>
        <span class="oc-home-tour-card__cta"><?php esc_html_e('Prenota ora', 'opencomune'); ?></span>
    </a>
</article>
