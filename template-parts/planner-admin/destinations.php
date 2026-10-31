<?php
if (!defined('ABSPATH')) {
    exit;
}
$data = opencomune_planner_get_admin_view_data();
?>
<p class="text-sm text-gray-600 mb-4">
    <?php esc_html_e('Destinazioni manuali (POI) e esperienze pubblicate con GPS entrano nel calcolo dell’itinerario.', 'opencomune'); ?>
</p>

<div class="oc-dashboard-table-wrap oc-dashboard-planner-dt p-6 mb-8">
    <table class="oc-dashboard-table display w-full" data-planner-entity="pois">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Destinazione', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Città', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Interessi', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Durata', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Stato', 'opencomune'); ?></th>
                <th scope="col" class="oc-dashboard-table__actions"><?php esc_html_e('Azioni', 'opencomune'); ?></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>

<div class="oc-booking-detail__card max-w-2xl">
    <h2 class="oc-booking-detail__card-title"><?php esc_html_e('Esperienze pubblicate', 'opencomune'); ?></h2>
    <p class="text-gray-600 mb-3">
        <?php
        printf(
            esc_html__('%1$d con GPS su %2$d pubblicate.', 'opencomune'),
            (int) $data['with_geo'],
            (int) $data['published']
        );
        ?>
    </p>
    <p class="mb-0">
        <a href="<?php echo esc_url(opencomune_get_esperienze_dashboard_url()); ?>" class="text-blue-700 hover:text-blue-900 font-medium"><?php esc_html_e('Gestisci esperienze', 'opencomune'); ?></a>
        <span class="text-gray-400 mx-2">·</span>
        <a href="<?php echo esc_url($data['wizard_url']); ?>" class="text-blue-700 hover:text-blue-900 font-medium" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apri wizard pubblico', 'opencomune'); ?></a>
    </p>
</div>
