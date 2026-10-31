<?php
if (!defined('ABSPATH')) {
    exit;
}
$id = opencomune_planner_admin_get_record_id();
$row = $id > 0 ? opencomune_planner_get_focus_by_id($id) : null;
if ($id > 0 && !$row) {
    echo '<p class="text-gray-600">' . esc_html__('Non trovato.', 'opencomune') . '</p>';
    return;
}
$is_new = $row === null;
?>
<p class="mb-4">
    <a href="<?php echo esc_url(opencomune_planner_admin_base_url('focuses')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
        <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
        <?php esc_html_e('Torna ai tipi viaggio', 'opencomune'); ?>
    </a>
</p>
<form method="post" class="oc-profilo-partner-form max-w-xl" action="<?php echo esc_url(opencomune_planner_admin_form_action('focus-edit', $id > 0 ? ['id' => $id] : [])); ?>">
    <?php wp_nonce_field('opencomune_planner_focus'); ?>
    <input type="hidden" name="oc_planner_action" value="save_focus">
    <input type="hidden" name="focus_id" value="<?php echo (int) $id; ?>">
    <div class="form-section">
        <h2 class="section-title"><?php echo $is_new ? esc_html__('Nuovo tipo viaggio', 'opencomune') : esc_html__('Modifica tipo viaggio', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="form-label" for="focus_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                <input class="form-input" id="focus_name" name="name" required value="<?php echo esc_attr($row['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="focus_slug"><?php esc_html_e('Slug', 'opencomune'); ?></label>
                <input class="form-input" id="focus_slug" name="slug" value="<?php echo esc_attr($row['slug'] ?? ''); ?>">
            </div>
            <div>
                <?php
                opencomune_planner_admin_render_image_field([
                    'input_id' => 'focus_image',
                    'input_name' => 'image',
                    'label' => __('Immagine', 'opencomune'),
                    'value' => $row['image'] ?? '',
                ]);
                ?>
            </div>
            <div>
                <label class="form-label" for="focus_sort"><?php esc_html_e('Ordine', 'opencomune'); ?></label>
                <input class="form-input max-w-[8rem]" id="focus_sort" type="number" name="sort_order" value="<?php echo esc_attr((string) ($row['sort_order'] ?? 0)); ?>">
            </div>
            <div>
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="active" value="1" <?php checked(!$row || (int) $row['active'] === 1); ?>>
                    <span><?php esc_html_e('Attivo nel wizard', 'opencomune'); ?></span>
                </label>
            </div>
        </div>
    </div>
    <div class="oc-profilo-partner-actions">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva', 'opencomune'); ?></button>
    </div>
</form>
<?php if (!$is_new) : ?>
    <form method="post" class="oc-profilo-partner-form max-w-xl mt-2" action="<?php echo esc_url(opencomune_planner_admin_form_action('focus-edit', ['id' => $id])); ?>" onsubmit="return confirm('<?php echo esc_js(__('Eliminare questo tipo?', 'opencomune')); ?>');">
        <?php wp_nonce_field('opencomune_planner_focus'); ?>
        <input type="hidden" name="oc_planner_action" value="delete_focus">
        <input type="hidden" name="focus_id" value="<?php echo (int) $id; ?>">
        <div class="oc-profilo-partner-actions" style="border-top: none; padding-top: 0;">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Elimina', 'opencomune'); ?></button>
        </div>
    </form>
<?php endif; ?>
