<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_planner_sanitize_image_from_request($url_field = 'image', $attachment_field = 'image_attachment_id') {
    $url_field = sanitize_key((string) $url_field);
    $attachment_field = sanitize_key((string) $attachment_field);

    $attachment_id = isset($_POST[$attachment_field]) ? absint($_POST[$attachment_field]) : 0;
    if ($attachment_id > 0) {
        $url = wp_get_attachment_url($attachment_id);
        if (is_string($url) && $url !== '') {
            return esc_url_raw($url);
        }
    }

    $manual = esc_url_raw(wp_unslash($_POST[$url_field] ?? ''));

    return $manual !== '' ? $manual : '';
}

function opencomune_planner_image_attachment_id_from_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return 0;
    }

    $id = attachment_url_to_postid($url);

    return $id > 0 ? $id : 0;
}

/**
 * @param array<string, mixed> $args
 */
function opencomune_planner_admin_render_image_field(array $args) {
    $args = wp_parse_args($args, [
        'input_id' => 'planner_image',
        'input_name' => 'image',
        'attachment_input_name' => 'image_attachment_id',
        'label' => __('Immagine', 'opencomune'),
        'value' => '',
        'attachment_id' => 0,
        'help' => __('Usata nel wizard pubblico. Consigliato formato orizzontale.', 'opencomune'),
    ]);

    $input_id = sanitize_html_class((string) $args['input_id']);
    $input_name = sanitize_key((string) $args['input_name']);
    $attachment_input_name = sanitize_key((string) $args['attachment_input_name']);
    $image_url = esc_url((string) $args['value']);
    $attachment_id = (int) $args['attachment_id'];
    if ($attachment_id <= 0 && $image_url !== '') {
        $attachment_id = opencomune_planner_image_attachment_id_from_url($image_url);
    }

    $preview_url = $image_url;
    if ($attachment_id > 0) {
        $thumb = wp_get_attachment_image_url($attachment_id, 'medium');
        if (is_string($thumb) && $thumb !== '') {
            $preview_url = $thumb;
        }
    }

    $has_preview = $preview_url !== '';
    ?>
    <div class="oc-planner-media-field" data-planner-media-field>
        <label class="form-label" for="<?php echo esc_attr($input_id); ?>"><?php echo esc_html((string) $args['label']); ?></label>
        <div class="oc-planner-media-field__box">
            <div class="oc-planner-media-field__actions">
                <button type="button" class="btn-primary oc-planner-media-field__select" data-planner-media-select>
                    <?php esc_html_e('Seleziona da libreria', 'opencomune'); ?>
                </button>
                <button type="button" class="btn-secondary oc-planner-media-field__remove<?php echo $has_preview ? '' : ' is-hidden'; ?>" data-planner-media-remove>
                    <?php esc_html_e('Rimuovi', 'opencomune'); ?>
                </button>
            </div>
            <div class="oc-planner-media-field__preview<?php echo $has_preview ? '' : ' is-hidden'; ?>" data-planner-media-preview-wrap>
                <img src="<?php echo esc_url($preview_url); ?>" alt="" class="oc-planner-media-field__preview-img" data-planner-media-preview-img loading="lazy">
            </div>
            <input type="hidden" name="<?php echo esc_attr($input_name); ?>" id="<?php echo esc_attr($input_id); ?>" value="<?php echo esc_attr($image_url); ?>" data-planner-media-url>
            <input type="hidden" name="<?php echo esc_attr($attachment_input_name); ?>" value="<?php echo esc_attr((string) $attachment_id); ?>" data-planner-media-attachment-id>
            <p class="text-sm text-gray-500 mt-2 mb-0"><?php echo esc_html((string) $args['help']); ?></p>
        </div>
    </div>
    <?php
}

function opencomune_enqueue_planner_admin_media_assets() {
    wp_enqueue_media();

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $js = $theme_dir . '/assets/js/dashboard-planner-media.js';

    wp_enqueue_script(
        'opencomune-dashboard-planner-media',
        $theme_uri . '/assets/js/dashboard-planner-media.js',
        ['jquery'],
        file_exists($js) ? (string) filemtime($js) : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-planner-media', 'opencomunePlannerMedia', [
        'i18n' => [
            'title' => __('Seleziona immagine', 'opencomune'),
            'button' => __('Usa questa immagine', 'opencomune'),
        ],
    ]);
}
