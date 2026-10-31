<?php
/**
 * Itinerario passo-passo (meta tour_itinerary_steps).
 *
 * @var array $args
 */

$post_id = isset($args['post_id']) ? (int) $args['post_id'] : 0;
$steps = isset($args['steps']) && is_array($args['steps']) ? $args['steps'] : [];

if ($post_id <= 0) {
    $post_id = get_the_ID();
}

if ($steps === [] && function_exists('opencomune_get_esperienza_itinerary_steps')) {
    $steps = opencomune_get_esperienza_itinerary_steps($post_id);
}

if ($steps === []) {
    return;
}

opencomune_enqueue_esperienza_itinerary_public_map();
?>

<section class="oc-esperienza-itinerary mb-4" aria-labelledby="oc-esperienza-itinerary-heading">
    <h2 id="oc-esperienza-itinerary-heading" class="oc-esperienza-itinerary__title">
        <?php esc_html_e('Itinerario', 'opencomune'); ?>
    </h2>
    <ol class="oc-esperienza-itinerary__list">
        <?php
        foreach ($steps as $index => $step) :
            if (!is_array($step)) {
                continue;
            }
            $title = (string) ($step['title'] ?? '');
            if ($title === '') {
                continue;
            }
            $description = (string) ($step['description'] ?? '');
            $address = (string) ($step['address'] ?? '');
            $lat = (float) ($step['latitude'] ?? 0);
            $lng = (float) ($step['longitude'] ?? 0);
            $has_map = $address !== '' || $lat !== 0.0 || $lng !== 0.0;
            $gallery_ids = isset($step['gallery_ids']) && is_array($step['gallery_ids']) ? $step['gallery_ids'] : [];
            $gallery_items = function_exists('opencomune_get_esperienza_gallery_lightbox_items')
                ? opencomune_get_esperienza_gallery_lightbox_items($gallery_ids)
                : [];
            $step_key = sanitize_html_class((string) ($step['id'] ?? 'step-' . $index));
            $map_id = 'oc-esperienza-itinerary-map-' . (int) $post_id . '-' . $step_key;
            $lightbox_group = 'esperienza-itinerary-' . (int) $post_id . '-' . $step_key;
            ?>
            <li class="oc-esperienza-itinerary__step">
                <div class="oc-esperienza-itinerary__step-head">
                    <span class="oc-esperienza-itinerary__badge" aria-hidden="true"><?php echo (int) $index + 1; ?></span>
                    <h3 class="oc-esperienza-itinerary__step-title"><?php echo esc_html($title); ?></h3>
                </div>
                <?php if ($has_map) : ?>
                    <p class="oc-esperienza-itinerary__map-action">
                        <button
                            type="button"
                            class="oc-esperienza-itinerary__map-toggle"
                            data-itinerary-map-toggle
                            data-map-id="<?php echo esc_attr($map_id); ?>"
                            data-lat="<?php echo esc_attr((string) $lat); ?>"
                            data-lng="<?php echo esc_attr((string) $lng); ?>"
                            data-address="<?php echo esc_attr($address); ?>"
                            data-step-title="<?php echo esc_attr($title); ?>"
                            aria-expanded="false"
                            aria-controls="<?php echo esc_attr($map_id . '-panel'); ?>"
                        >
                            <?php esc_html_e('Visualizza su mappa', 'opencomune'); ?>
                        </button>
                    </p>
                    <div
                        id="<?php echo esc_attr($map_id . '-panel'); ?>"
                        class="oc-esperienza-itinerary__map-panel"
                        hidden
                    >
                        <div id="<?php echo esc_attr($map_id); ?>" class="oc-esperienza-itinerary__map" aria-label="<?php esc_attr_e('Mappa della tappa', 'opencomune'); ?>"></div>
                    </div>
                <?php endif; ?>
                <?php if (trim(wp_strip_all_tags($description)) !== '') : ?>
                    <div class="oc-esperienza-rich-content oc-esperienza-itinerary__description">
                        <?php echo opencomune_filter_esperienza_rich_text($description, $post_id); ?>
                    </div>
                <?php endif; ?>
                <?php if ($gallery_items !== []) : ?>
                    <ul class="oc-esperienza-itinerary__gallery">
                        <?php foreach ($gallery_items as $gallery_item) : ?>
                            <li>
                                <a
                                    class="oc-esperienza-gallery-item oc-esperienza-itinerary__gallery-link"
                                    href="<?php echo esc_url($gallery_item['full']); ?>"
                                    data-fslightbox="<?php echo esc_attr($lightbox_group); ?>"
                                    data-caption="<?php echo esc_attr($title); ?>"
                                >
                                    <img
                                        src="<?php echo esc_url($gallery_item['large']); ?>"
                                        alt="<?php echo esc_attr($gallery_item['alt'] !== '' ? $gallery_item['alt'] : $title); ?>"
                                        loading="lazy"
                                        class="oc-esperienza-itinerary__gallery-img"
                                    >
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
