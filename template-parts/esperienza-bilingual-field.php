<?php
/**
 * Campo bilingue IT/EN affiancato.
 *
 * @var array $args
 */

$args = wp_parse_args($args ?? [], [
    'section' => 'base',
    'en_key' => '',
    'it_name' => '',
    'it_id' => '',
    'it_label' => '',
    'it_value' => '',
    'en_value' => '',
    'it_required' => false,
    'rows' => 4,
    'type' => 'textarea',
    'help' => '',
    'placeholder_it' => '',
    'class' => '',
    'italian_only' => false,
    'english_only' => false,
    'ai_translate_actions' => false,
    'editor_height' => 0,
]);

if (!empty($args['italian_only']) && !empty($args['english_only'])) {
    $args['italian_only'] = false;
}

$defs = opencomune_get_esperienza_translation_field_defs();
$en_def = $defs[$args['en_key']] ?? [];
$en_name = opencomune_get_esperienza_translation_input_name($args['en_key']);
$en_label = $en_def['label'] ?? 'English';
$en_placeholder = $en_def['placeholder'] ?? '';
$is_html_editor = $args['type'] === 'html' || ($en_def['type'] ?? '') === 'html';
$is_textarea = !$is_html_editor && ($args['type'] === 'textarea' || ($args['english_only'] && ($en_def['type'] ?? '') === 'html'));
$en_rows = (int) ($en_def['rows'] ?? $args['rows']);

$it_wp_editor_settings = [
    'textarea_name' => $args['it_name'],
    'media_buttons' => true,
    'teeny' => false,
    'textarea_rows' => (int) $args['rows'],
    'quicktags' => true,
    'editor_class' => 'oc-exp-rich-editor',
];
if ((int) $args['editor_height'] > 0) {
    $it_wp_editor_settings['tinymce'] = [
        'height' => (int) $args['editor_height'],
        'wp_autoresize_on' => false,
    ];
}
?>

<div class="opencomune-bilingual-field <?php echo esc_attr($args['class']); ?>" data-section="<?php echo esc_attr($args['section']); ?>"<?php echo $args['en_key'] !== '' ? ' data-translation-field="' . esc_attr($args['en_key']) . '"' : ''; ?>>
    <?php if (!empty($args['english_only'])) : ?>
        <div class="mb-2">
            <div class="opencomune-bilingual-col-label opencomune-bilingual-col-label-en"><?php echo esc_html($en_label); ?></div>
            <?php if ($args['it_label'] !== '' && !$is_html_editor) : ?>
                <p class="text-sm text-gray-500 mt-1">
                    <?php esc_html_e('Italiano:', 'opencomune'); ?>
                    <span class="oc-dashboard-trans-it-ref"><?php echo esc_html(wp_strip_all_tags((string) $args['it_value'])); ?></span>
                </p>
            <?php endif; ?>
        </div>
        <?php if ($is_html_editor) : ?>
            <?php if ($args['it_label'] !== '') : ?>
                <p class="text-sm font-medium text-gray-700 mb-2"><?php esc_html_e('Italiano (anteprima)', 'opencomune'); ?></p>
                <div class="oc-dashboard-trans-it-html">
                    <?php echo wp_kses_post((string) $args['it_value']); ?>
                </div>
            <?php endif; ?>
            <p class="text-sm font-medium text-gray-700 mt-4 mb-2"><?php esc_html_e('Inglese', 'opencomune'); ?></p>
            <div class="oc-trans-html-editor-wrap">
                <textarea
                    id="<?php echo esc_attr($en_name); ?>"
                    name="<?php echo esc_attr($en_name); ?>"
                    class="oc-dashboard-trans-html-editor widefat"
                    rows="<?php echo (int) max(8, $en_rows); ?>"
                ><?php echo esc_textarea((string) $args['en_value']); ?></textarea>
            </div>
        <?php elseif ($is_textarea) : ?>
            <textarea
                id="<?php echo esc_attr($en_name); ?>"
                name="<?php echo esc_attr($en_name); ?>"
                class="form-textarea"
                rows="<?php echo $en_rows; ?>"
                placeholder="<?php echo esc_attr($en_placeholder); ?>"
            ><?php echo esc_textarea($args['en_value']); ?></textarea>
        <?php else : ?>
            <input
                type="text"
                id="<?php echo esc_attr($en_name); ?>"
                name="<?php echo esc_attr($en_name); ?>"
                class="form-input"
                value="<?php echo esc_attr($args['en_value']); ?>"
                placeholder="<?php echo esc_attr($en_placeholder); ?>"
            >
        <?php endif; ?>
        <?php if (!empty($args['ai_translate_actions']) && $args['en_key'] !== '') : ?>
            <div class="oc-ai-field-actions">
                <button type="button" class="btn-secondary oc-ai-auto-translate-btn" data-field-key="<?php echo esc_attr($args['en_key']); ?>">
                    <?php esc_html_e('Traduci automaticamente', 'opencomune'); ?>
                </button>
            </div>
        <?php endif; ?>
    <?php elseif (!empty($args['italian_only'])) : ?>
        <div class="mb-2">
            <label for="<?php echo esc_attr($args['it_id']); ?>" class="form-label mb-0">
                <?php echo esc_html($args['it_label']); ?>
                <?php if ($args['it_required']) : ?>
                    <span class="form-required">*</span>
                <?php endif; ?>
            </label>
            <?php if ($args['help'] !== '') : ?>
                <p class="text-sm text-gray-500 mt-1"><?php echo esc_html($args['help']); ?></p>
            <?php endif; ?>
        </div>
        <?php if ($is_html_editor) : ?>
            <?php
            wp_editor(
                $args['it_value'],
                $args['it_id'],
                $it_wp_editor_settings
            );
            ?>
        <?php elseif ($is_textarea) : ?>
            <textarea
                id="<?php echo esc_attr($args['it_id']); ?>"
                name="<?php echo esc_attr($args['it_name']); ?>"
                class="form-textarea"
                rows="<?php echo (int) $args['rows']; ?>"
                placeholder="<?php echo esc_attr($args['placeholder_it']); ?>"
                <?php echo $args['it_required'] ? 'required' : ''; ?>
            ><?php echo esc_textarea($args['it_value']); ?></textarea>
        <?php else : ?>
            <input
                type="text"
                id="<?php echo esc_attr($args['it_id']); ?>"
                name="<?php echo esc_attr($args['it_name']); ?>"
                class="form-input"
                value="<?php echo esc_attr($args['it_value']); ?>"
                placeholder="<?php echo esc_attr($args['placeholder_it']); ?>"
                <?php echo $args['it_required'] ? 'required' : ''; ?>
            >
        <?php endif; ?>
    <?php else : ?>
        <div class="mb-2">
            <label for="<?php echo esc_attr($args['it_id']); ?>" class="form-label mb-0">
                <?php echo esc_html($args['it_label']); ?>
                <?php if ($args['it_required']) : ?>
                    <span class="form-required">*</span>
                <?php endif; ?>
            </label>
            <?php if ($args['help'] !== '') : ?>
                <p class="text-sm text-gray-500 mt-1"><?php echo esc_html($args['help']); ?></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="opencomune-bilingual-col opencomune-bilingual-col-it">
                <div class="opencomune-bilingual-col-label">Italiano</div>
                <?php if ($is_html_editor) : ?>
                    <?php
                    wp_editor(
                        $args['it_value'],
                        $args['it_id'],
                        $it_wp_editor_settings
                    );
                    ?>
                <?php elseif ($args['type'] === 'textarea') : ?>
                    <textarea
                        id="<?php echo esc_attr($args['it_id']); ?>"
                        name="<?php echo esc_attr($args['it_name']); ?>"
                        class="form-textarea"
                        rows="<?php echo (int) $args['rows']; ?>"
                        placeholder="<?php echo esc_attr($args['placeholder_it']); ?>"
                        <?php echo $args['it_required'] ? 'required' : ''; ?>
                    ><?php echo esc_textarea($args['it_value']); ?></textarea>
                <?php else : ?>
                    <input
                        type="text"
                        id="<?php echo esc_attr($args['it_id']); ?>"
                        name="<?php echo esc_attr($args['it_name']); ?>"
                        class="form-input"
                        value="<?php echo esc_attr($args['it_value']); ?>"
                        placeholder="<?php echo esc_attr($args['placeholder_it']); ?>"
                        <?php echo $args['it_required'] ? 'required' : ''; ?>
                    >
                <?php endif; ?>
            </div>

            <div class="opencomune-bilingual-col opencomune-bilingual-col-en">
                <div class="opencomune-bilingual-col-label opencomune-bilingual-col-label-en"><?php echo esc_html($en_label); ?></div>
                <?php if ($args['type'] === 'textarea') : ?>
                    <textarea
                        id="<?php echo esc_attr($en_name); ?>"
                        name="<?php echo esc_attr($en_name); ?>"
                        class="form-textarea"
                        rows="<?php echo (int) $args['rows']; ?>"
                        placeholder="<?php echo esc_attr($en_placeholder); ?>"
                    ><?php echo esc_textarea($args['en_value']); ?></textarea>
                <?php else : ?>
                    <input
                        type="text"
                        id="<?php echo esc_attr($en_name); ?>"
                        name="<?php echo esc_attr($en_name); ?>"
                        class="form-input"
                        value="<?php echo esc_attr($args['en_value']); ?>"
                        placeholder="<?php echo esc_attr($en_placeholder); ?>"
                    >
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
