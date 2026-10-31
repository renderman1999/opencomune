<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="oc-dashboard-table-wrap oc-dashboard-planner-dt p-6">
    <table class="oc-dashboard-table display w-full" data-planner-entity="requests">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e('Data', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Viaggio', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Partenza', 'opencomune'); ?></th>
                <th scope="col"><?php esc_html_e('Contatto', 'opencomune'); ?></th>
                <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Azioni', 'opencomune'); ?></span></th>
            </tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
