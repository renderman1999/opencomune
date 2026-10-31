<?php
/**
 * Card partner (stile card esperienze - home / archivio)
 *
 * @var array $args Argomenti opzionali passati da get_template_part().
 */

$partner = isset($args['partner']) ? $args['partner'] : null;
if (!$partner) {
    return;
}

$img = !empty($partner->logo_url)
    ? $partner->logo_url
    : 'https://via.placeholder.com/400x300?text=Partner';
$title = $partner->nome_attivita ?? '';
$specializzazioni_label = !empty($partner->tipologia_attivita) ? esc_html($partner->tipologia_attivita) : '';
$citta_label = !empty($partner->comune) ? esc_html(ucwords(strtolower($partner->comune))) : '';
$excerpt = !empty($partner->descrizione_breve)
    ? wp_trim_words(wp_strip_all_tags(opencomune_get_partner_public_description($partner->descrizione_breve)), 18)
    : '';
$author_id = !empty($partner->wp_user_id) ? (int) $partner->wp_user_id : 0;
$esperienze_count = $author_id ? count_user_posts($author_id, 'esperienze', true) : 0;

if ($author_id) {
    $card_url = opencomune_get_partner_profile_url($author_id);
    $link_target = '';
    $link_rel = '';
} elseif (!empty($partner->sito_web)) {
    $card_url = esc_url($partner->sito_web);
    $link_target = '_blank';
    $link_rel = 'noopener noreferrer';
} else {
    $card_url = '#';
    $link_target = '';
    $link_rel = '';
}

$card_class = isset($args['card_class'])
    ? $args['card_class']
    : 'block bg-white rounded-2xl shadow p-3 h-full flex flex-col hover:shadow-lg transition';
$wrapper_class = isset($args['wrapper_class']) ? $args['wrapper_class'] : 'h-full';

$profile_thumb = '';
if ($author_id) {
    $profile_image_id = get_user_meta($author_id, 'profile_image_id', true);
    if ($profile_image_id && is_numeric($profile_image_id)) {
        $profile_thumb = wp_get_attachment_image_url($profile_image_id, 'thumbnail');
    }
}
if (!$profile_thumb && $img) {
    $profile_thumb = $img;
}
if (!$profile_thumb) {
    $profile_thumb = 'https://www.gravatar.com/avatar/?d=mp&f=y';
}
?>

<div class="<?php echo esc_attr($wrapper_class); ?>">
    <a href="<?php echo esc_url($card_url); ?>" class="<?php echo esc_attr($card_class); ?>"<?php echo $link_target ? ' target="' . esc_attr($link_target) . '"' : ''; ?><?php echo $link_rel ? ' rel="' . esc_attr($link_rel) . '"' : ''; ?>>
        <div class="relative rounded-xl w-full bg-gray-50 overflow-hidden" style="height: 10rem; display: flex; align-items: center; justify-content: center;">
            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" style="max-width: 100%; max-height: 100%; width: auto; height: auto; object-fit: contain; padding: 12px;">
            <img src="<?php echo esc_url($profile_thumb); ?>" alt="Partner" class="absolute top-2 right-2 w-10 h-10 rounded-full border-2 border-white shadow object-cover bg-white" />
        </div>
        <div class="mt-3 flex-1 flex flex-col">
            <div class="font-semibold text-base leading-tight mb-1"><?php echo esc_html($title); ?></div>
            <div class="flex items-center gap-2 mb-2">
                <span class="text-xs text-green-800">
                    <i class="bi bi-patch-check"></i> Partner certificato
                </span>
            </div>
            <?php if ($excerpt) : ?>
            <div class="text-gray-500 text-sm mb-2"><?php echo esc_html($excerpt); ?></div>
            <?php endif; ?>

            <?php if ($specializzazioni_label) : ?>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-red-600 text-white text-xs px-2 py-1 rounded font-semibold"><?php echo $specializzazioni_label; ?></span>
            </div>
            <?php endif; ?>

            <?php if ($citta_label) : ?>
            <div class="flex items-center gap-2 mb-2">
                <span class="inline-block bg-blue-600 text-white text-xs px-2 py-1 rounded font-semibold">
                    <i class="bi bi-geo-alt-fill mr-1"></i><?php echo $citta_label; ?>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($esperienze_count > 0) : ?>
            <div class="flex items-center gap-2 mt-auto">
                <span class="text-xs text-gray-600">
                    <i class="bi bi-compass mr-1"></i><?php echo esc_html($esperienze_count); ?>
                    <?php echo $esperienze_count === 1 ? 'esperienza' : 'esperienze'; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </a>
</div>
