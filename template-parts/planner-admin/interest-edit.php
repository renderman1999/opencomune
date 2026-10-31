<?php
if (!defined('ABSPATH')) {
    exit;
}
$id = opencomune_planner_admin_get_record_id();
$row = $id > 0 ? opencomune_planner_get_interest_by_id($id) : null;
if ($id > 0 && !$row) {
    echo '<p class="text-gray-600">' . esc_html__('Non trovato.', 'opencomune') . '</p>';
    return;
}
$is_new = $row === null;
?>
<p class="mb-4">
    <a href="<?php echo esc_url(opencomune_planner_admin_base_url('interests')); ?>" class="text-blue-700 hover:text-blue-900 font-medium">
        <i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>
        <?php esc_html_e('Torna agli interessi', 'opencomune'); ?>
    </a>
</p>
<form method="post" class="oc-profilo-partner-form max-w-xl" action="<?php echo esc_url(opencomune_planner_admin_form_action('interest-edit', $id > 0 ? ['id' => $id] : [])); ?>">
    <?php wp_nonce_field('opencomune_planner_interest'); ?>
    <input type="hidden" name="oc_planner_action" value="save_interest">
    <input type="hidden" name="interest_id" value="<?php echo (int) $id; ?>">
    <div class="form-section">
        <h2 class="section-title"><?php echo $is_new ? esc_html__('Nuovo interesse', 'opencomune') : esc_html__('Modifica interesse', 'opencomune'); ?></h2>
        <div class="grid grid-cols-1 gap-6">
            <div>
                <label class="form-label" for="interest_name"><?php esc_html_e('Nome', 'opencomune'); ?></label>
                <input class="form-input" id="interest_name" name="name" required value="<?php echo esc_attr($row['name'] ?? ''); ?>">
            </div>
            <div>
                <label class="form-label" for="interest_slug"><?php esc_html_e('Slug', 'opencomune'); ?></label>
                <input class="form-input" id="interest_slug" name="slug" value="<?php echo esc_attr($row['slug'] ?? ''); ?>">
            </div>
            <div>
                <?php
                opencomune_planner_admin_render_image_field([
                    'input_id' => 'interest_image',
                    'input_name' => 'image',
                    'label' => __('Immagine', 'opencomune'),
                    'value' => $row['image'] ?? '',
                ]);
                ?>
            </div>
            <div>
                <label class="form-label" for="interest_sort"><?php esc_html_e('Ordine', 'opencomune'); ?></label>
                <input class="form-input max-w-[8rem]" id="interest_sort" type="number" name="sort_order" value="<?php echo esc_attr((string) ($row['sort_order'] ?? 0)); ?>">
            </div>
            <div class="flex flex-wrap gap-6">
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="active" value="1" <?php checked(!$row || (int) $row['active'] === 1); ?>>
                    <span><?php esc_html_e('Attivo nel wizard', 'opencomune'); ?></span>
                </label>
                <label class="oc-planner-admin__checkbox">
                    <input type="checkbox" name="is_food" value="1" <?php checked($row && (int) $row['is_food'] === 1); ?>>
                    <span><?php esc_html_e('Food', 'opencomune'); ?></span>
                </label>
            </div>
        </div>
    </div>
    <div class="oc-profilo-partner-actions">
        <button type="submit" class="btn-primary"><?php esc_html_e('Salva', 'opencomune'); ?></button>
    </div>
</form>
<?php if (!$is_new) : ?>
    <form method="post" class="oc-profilo-partner-form max-w-xl mt-2" action="<?php echo esc_url(opencomune_planner_admin_form_action('interest-edit', ['id' => $id])); ?>" onsubmit="return confirm('<?php echo esc_js(__('Eliminare questo interesse?', 'opencomune')); ?>');">
        <?php wp_nonce_field('opencomune_planner_interest'); ?>
        <input type="hidden" name="oc_planner_action" value="delete_interest">
        <input type="hidden" name="interest_id" value="<?php echo (int) $id; ?>">
        <div class="oc-profilo-partner-actions" style="border-top: none; padding-top: 0;">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Elimina', 'opencomune'); ?></button>
        </div>
    </form>
<?php endif; ?>
