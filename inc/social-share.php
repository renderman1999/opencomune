<?php

function opencomune_get_share_text($text) {
    return wp_strip_all_tags(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'));
}

function opencomune_get_esperienza_share_data($post_id) {
    $permalink = get_permalink($post_id);
    $title = opencomune_get_share_text(get_the_title($post_id));

    $image = '';
    $image_width = 0;
    $image_height = 0;
    $thumbnail_id = get_post_thumbnail_id($post_id);

    if ($thumbnail_id && opencomune_esperienza_has_valid_thumbnail($post_id)) {
        foreach (['large', 'full'] as $size) {
            $src = wp_get_attachment_image_src($thumbnail_id, $size);
            if (!empty($src[0])) {
                $image = $src[0];
                $image_width = (int) ($src[1] ?? 0);
                $image_height = (int) ($src[2] ?? 0);
                break;
            }
        }
    }

    $description = opencomune_get_esperienza_descrizione_breve($post_id);
    if ($description === '') {
        $description = wp_trim_words(
            opencomune_get_share_text(get_post_field('post_content', $post_id)),
            30,
            '...'
        );
    } else {
        $description = opencomune_get_share_text($description);
    }

    return [
        'permalink' => $permalink,
        'title' => $title,
        'description' => $description,
        'image' => $image,
        'image_width' => $image_width,
        'image_height' => $image_height,
    ];
}

function opencomune_enqueue_share_buttons_styles() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $path = $theme_dir . '/assets/css/opencomune-share.css';

    wp_enqueue_style(
        'opencomune-share-buttons',
        $theme_uri . '/assets/css/opencomune-share.css',
        [],
        file_exists($path) ? (string) filemtime($path) : '1.0'
    );
}

function opencomune_maybe_enqueue_share_buttons_styles() {
    if (!is_singular('esperienze') && !is_author()) {
        return;
    }

    opencomune_enqueue_share_buttons_styles();
}
add_action('wp_enqueue_scripts', 'opencomune_maybe_enqueue_share_buttons_styles');

function opencomune_get_partner_share_urls($author_id, $title = '') {
    $author_id = (int) $author_id;
    $permalink = opencomune_get_partner_profile_url($author_id);

    if ($title === '') {
        $partner = opencomune_get_partner_by_user_id($author_id);
        if ($partner && !empty($partner->nome_attivita)) {
            $title = opencomune_get_share_text($partner->nome_attivita);
        } else {
            $user = get_userdata($author_id);
            $title = $user && $user->exists()
                ? opencomune_get_share_text($user->display_name)
                : '';
        }
    } else {
        $title = opencomune_get_share_text($title);
    }

    $share_text = $title !== '' ? $title . ' - ' . $permalink : $permalink;
    $email_body = sprintf(
        __("Scopri le esperienze di questo partner:\n\n%s", 'opencomune'),
        $permalink
    );

    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($permalink),
        'whatsapp' => 'https://wa.me/?text=' . rawurlencode($share_text),
        'email' => 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($email_body),
    ];
}

function opencomune_get_esperienza_share_urls($post_id) {
    $data = opencomune_get_esperienza_share_data($post_id);
    $share_text = $data['title'] . ' - ' . $data['permalink'];
    $email_body = sprintf(
        __("Scopri questa esperienza:\n\n%s", 'opencomune'),
        $data['permalink']
    );

    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($data['permalink']),
        'whatsapp' => 'https://wa.me/?text=' . rawurlencode($share_text),
        'email' => 'mailto:?subject=' . rawurlencode($data['title']) . '&body=' . rawurlencode($email_body),
    ];
}

function opencomune_output_esperienza_social_meta_tags() {
    if (!is_singular('esperienze')) {
        return;
    }

    $post_id = get_queried_object_id();
    if (!$post_id) {
        return;
    }

    $data = opencomune_get_esperienza_share_data($post_id);
    $tags = [
        'og:type' => 'article',
        'og:title' => $data['title'],
        'og:url' => $data['permalink'],
        'og:site_name' => get_bloginfo('name'),
        'og:locale' => 'it_IT',
        'twitter:card' => $data['image'] !== '' ? 'summary_large_image' : 'summary',
        'twitter:title' => $data['title'],
    ];

    if ($data['description'] !== '') {
        $tags['og:description'] = $data['description'];
        $tags['twitter:description'] = $data['description'];
    }

    if ($data['image'] !== '') {
        $tags['og:image'] = $data['image'];
        $tags['twitter:image'] = $data['image'];

        if ($data['image_width'] > 0) {
            $tags['og:image:width'] = (string) $data['image_width'];
        }
        if ($data['image_height'] > 0) {
            $tags['og:image:height'] = (string) $data['image_height'];
        }
    }

    foreach ($tags as $property => $content) {
        if ($content === '') {
            continue;
        }

        $attr = strpos($property, 'twitter:') === 0 ? 'name' : 'property';
        printf(
            '<meta %1$s="%2$s" content="%3$s">' . "\n",
            esc_attr($attr),
            esc_attr($property),
            esc_attr($content)
        );
    }
}
add_action('wp_head', 'opencomune_output_esperienza_social_meta_tags', 5);
