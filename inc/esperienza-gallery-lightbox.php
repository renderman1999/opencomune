<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_esperienza_gallery_lightbox_item($attachment_id) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return null;
    }

    $full = wp_get_attachment_image_url($attachment_id, 'full');
    $large = wp_get_attachment_image_url($attachment_id, 'large');
    if (!$full) {
        return null;
    }

    $alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
    $title = get_the_title($attachment_id);

    return [
        'id' => $attachment_id,
        'full' => $full,
        'large' => $large ?: $full,
        'alt' => $alt,
        'title' => is_string($title) ? $title : '',
    ];
}

function opencomune_get_esperienza_gallery_lightbox_items(array $attachment_ids) {
    $items = [];
    foreach ($attachment_ids as $attachment_id) {
        $item = opencomune_get_esperienza_gallery_lightbox_item((int) $attachment_id);
        if ($item) {
            $items[] = $item;
        }
    }

    return $items;
}

function opencomune_enqueue_esperienza_gallery_lightbox_assets() {
    if (!is_singular('esperienze')) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $vendor_js = $theme_dir . '/assets/vendor/fslightbox/fslightbox.js';

    if (!is_readable($vendor_js)) {
        return;
    }

    wp_enqueue_style(
        'opencomune-esperienza-gallery-lightbox',
        $theme_uri . '/assets/css/esperienza-gallery-lightbox.css',
        [],
        (string) filemtime($theme_dir . '/assets/css/esperienza-gallery-lightbox.css')
    );

    wp_enqueue_script(
        'fslightbox',
        $theme_uri . '/assets/vendor/fslightbox/fslightbox.js',
        [],
        (string) filemtime($vendor_js),
        true
    );
    wp_enqueue_script(
        'opencomune-esperienza-gallery-lightbox',
        $theme_uri . '/assets/js/esperienza-gallery-lightbox.js',
        ['fslightbox'],
        (string) filemtime($theme_dir . '/assets/js/esperienza-gallery-lightbox.js'),
        true
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_esperienza_gallery_lightbox_assets', 25);
