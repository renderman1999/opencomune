<?php
if (!defined('ABSPATH')) {
    exit;
}
$view = isset($args['view']) ? (string) $args['view'] : 'destinations';
$tabs = [
    'destinations' => __('Destinazioni', 'opencomune'),
    'cities' => __('Città', 'opencomune'),
    'interests' => __('Interessi', 'opencomune'),
    'focuses' => __('Tipo viaggio', 'opencomune'),
    'requests' => __('Richieste', 'opencomune'),
    'settings' => __('Impostazioni', 'opencomune'),
];
$active_group = $view;
if ($view === 'poi-edit') {
    $active_group = 'destinations';
} elseif ($view === 'city-edit') {
    $active_group = 'cities';
} elseif ($view === 'interest-edit') {
    $active_group = 'interests';
} elseif ($view === 'focus-edit') {
    $active_group = 'focuses';
} elseif ($view === 'request-edit') {
    $active_group = 'requests';
}
?>
<div class="mb-8">
    <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Pianificatore', 'opencomune'); ?></h1>
    <p class="text-gray-600 mb-0">
        <?php esc_html_e('Catalogo destinazioni, città e preferenze per il wizard pubblico.', 'opencomune'); ?>
    </p>
</div>

<div class="oc-planner-admin__toolbar">
    <nav class="oc-planner-admin__tabs" aria-label="<?php esc_attr_e('Sezioni pianificatore', 'opencomune'); ?>">
        <?php foreach ($tabs as $id => $label) : ?>
            <a href="<?php echo esc_url(opencomune_planner_admin_base_url($id)); ?>"
               class="oc-planner-admin__tab<?php echo $active_group === $id ? ' is-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($view === 'destinations') : ?>
        <a href="<?php echo esc_url(opencomune_planner_admin_base_url('poi-edit')); ?>" class="btn-primary oc-planner-admin__toolbar-action">
            <?php esc_html_e('Nuova destinazione', 'opencomune'); ?>
        </a>
    <?php endif; ?>
</div>
