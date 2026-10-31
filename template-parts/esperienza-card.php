<?php
/**
 * Card esperienza (stile home - La nostra proposta culturale)
 *
 * @var array $args Argomenti opzionali passati da get_template_part().
 */

$post_id = get_the_ID();
$prezzo = get_post_meta($post_id, 'prezzo', true);
$prezzo_old = get_post_meta($post_id, 'prezzo_old', true);
$rating = get_post_meta($post_id, 'rating', true);
$badge = get_post_meta($post_id, 'badge', true);

if (!function_exists('opencomune_get_average_rating')) {
    require_once get_template_directory() . '/review-system.php';
}
$avg_rating = opencomune_get_average_rating($post_id);

$card_class = isset($args['card_class'])
    ? $args['card_class']
    : 'block bg-white rounded-2xl shadow p-3 h-full flex flex-col hover:shadow-lg transition';
$wrapper_class = isset($args['wrapper_class']) ? $args['wrapper_class'] : 'h-full';
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <a href="<?php the_permalink(); ?>" class="<?php echo esc_attr($card_class); ?>">
        <div class="relative">
            <?php
            opencomune_render_esperienza_cover($post_id, [
                'alt' => get_the_title($post_id),
            ]);
            ?>
            <?php
            $author_id = get_the_author_meta('ID');
            $ufficio_image_id = '';
            $ufficio_thumb = '';

            if ($author_id) {
                $ufficio_image_id = get_user_meta($author_id, 'profile_image_id', true);
                if ($ufficio_image_id && is_numeric($ufficio_image_id)) {
                    $ufficio_thumb = wp_get_attachment_image_url($ufficio_image_id, 'thumbnail');
                }
            }

            if (!$ufficio_thumb) {
                $ufficio_thumb = 'https://www.gravatar.com/avatar/?d=mp&f=y';
            }
            ?>
            <img src="<?php echo esc_url($ufficio_thumb); ?>" alt="Ufficio Turistico" class="absolute top-2 right-2 w-10 h-10 rounded-full border-2 border-white shadow object-cover bg-white" />
        </div>
        <div class="mt-3 flex-1 flex flex-col">
            <div class="font-semibold text-base leading-tight mb-1"><?php the_title(); ?></div>
            <div class="flex items-center gap-2 mb-2">
                <?php
                $author_id = get_the_author_meta('ID');
                $is_ente_author = opencomune_user_is_ente_author($author_id);
                $is_partner = user_can($author_id, 'partner');

                if ($is_ente_author) {
                    $editor_name = get_the_author_meta('display_name', $author_id);
                    echo '<span class="text-xs text-green-800"><i class="bi bi-patch-check"></i> ' . esc_html($editor_name) . '</span>';
                } elseif ($is_partner) {
                    $partner_name = get_the_author_meta('display_name', $author_id);
                    echo '<span class="text-xs text-green-800"><i class="bi bi-patch-check"></i> Proposta da ' . esc_html($partner_name) . '</span>';
                } else {
                    echo '<span class="text-xs text-green-800"><i class="bi bi-patch-check"></i> Certificato da ' . esc_html(get_bloginfo('name')) . '</span>';
                }
                ?>
            </div>
            <div class="text-gray-500 text-sm mb-2"><?php echo wp_trim_words(get_the_excerpt(), 18); ?></div>

            <?php if ($badge) : ?>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-red-600 text-white text-xs px-2 py-1 rounded font-semibold"><?php echo esc_html($badge); ?></span>
            </div>
            <?php endif; ?>

            <?php opencomune_render_esperienza_info_badges($post_id); ?>

            <div class="flex items-center gap-2 mt-auto">
                <?php if ($rating) : ?>
                <span class="ml-auto flex items-center gap-1 text-yellow-500 font-bold text-base">
                    <i class="bi bi-star-fill"></i> <?php echo esc_html($rating); ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="flex items-center gap-1 mt-2">
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    echo '<span class="text-yellow-400 text-lg">' . ($i <= round($avg_rating) ? '★' : '☆') . '</span>';
                }
                ?>
                <?php if ($avg_rating) : ?>
                    <span class="text-gray-600 text-sm ml-1"><?php echo esc_html($avg_rating); ?>/5</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
</div>
