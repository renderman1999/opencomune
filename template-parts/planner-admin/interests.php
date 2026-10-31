<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="oc-planner-admin__toolbar oc-planner-admin__toolbar--sub">
    <p class="text-sm text-gray-600 mb-0"><?php esc_html_e('Chip interessi nel wizard pubblico.', 'opencomune'); ?></p>
    <div class="flex flex-wrap gap-2">
        <form method="post" action="<?php echo esc_url(opencomune_planner_admin_form_action('interests')); ?>">
            <?php wp_nonce_field('opencomune_planner_import_interests'); ?>
            <input type="hidden" name="oc_planner_action" value="import_interests">
            <button type="submit" class="btn-secondary"><?php esc_html_e('Importa da categorie', 'opencomune'); ?></button>
        </form>
        <a href="<?php echo esc_url(opencomune_planner_admin_base_url('interest-edit')); ?>" class="btn-primary"><?php esc_html_e('Nuovo interesse', 'opencomune'); ?></a>
    </div>
</div>
<div class="oc-dashboard-table-wrap oc-dashboard-planner-dt p-6">
    <table class="oc-dashboard-table display w-full" data-planner-entity="interests">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Interesse', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Slug', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Ordine', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Stato', 'opencomune'); ?></th>
                <th scope="col" class="oc-dashboard-table__actions"><?php esc_html_e('Azioni', 'opencomune'); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
