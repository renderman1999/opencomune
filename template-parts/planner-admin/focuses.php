<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="oc-planner-admin__toolbar oc-planner-admin__toolbar--sub">
    <p class="text-sm text-gray-600 mb-0"><?php esc_html_e('Tipo di viaggio nel wizard. Se vuoto, si usano i valori predefiniti.', 'opencomune'); ?></p>
    <a href="<?php echo esc_url(opencomune_planner_admin_base_url('focus-edit')); ?>" class="btn-primary"><?php esc_html_e('Nuovo tipo', 'opencomune'); ?></a>
</div>
<div class="oc-dashboard-table-wrap oc-dashboard-planner-dt p-6">
    <table class="oc-dashboard-table display w-full" data-planner-entity="focuses">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Nome', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Slug', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Ordine', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Stato', 'opencomune'); ?></th>
                <th scope="col" class="oc-dashboard-table__actions"><?php esc_html_e('Azioni', 'opencomune'); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
