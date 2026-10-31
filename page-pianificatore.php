<?php
/* Template Name: Dashboard Pianificatore */

if (!opencomune_user_can_manage_all_esperienze()) {
    wp_safe_redirect(home_url('/dashboard-partner/'));
    exit;
}

$post_result = opencomune_planner_admin_get_post_result();

$success_message = isset($_GET['oc_msg']) ? sanitize_text_field(wp_unslash($_GET['oc_msg'])) : '';
$error_message = isset($_GET['oc_err']) ? sanitize_text_field(wp_unslash($_GET['oc_err'])) : '';
if (!empty($post_result['success'])) {
    $success_message = $post_result['success'];
}
if (!empty($post_result['error'])) {
    $error_message = $post_result['error'];
}

$view = opencomune_planner_admin_get_view();
$allowed_views = [
    'destinations',
    'poi-edit',
    'cities',
    'city-edit',
    'interests',
    'interest-edit',
    'focuses',
    'focus-edit',
    'settings',
    'requests',
    'request-edit',
];
if (!in_array($view, $allowed_views, true)) {
    $view = 'destinations';
}

$needs_map = in_array($view, ['poi-edit', 'city-edit'], true);
opencomune_enqueue_dashboard_planner_assets($needs_map);
opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-dashboard-panel oc-profilo-partner-page oc-planner-admin p-6">
            <?php
            get_template_part('template-parts/planner-admin/nav', null, ['view' => $view]);
            if ($error_message !== '') {
                echo '<div class="error-message mb-4"><i class="bi bi-exclamation-triangle mr-2" aria-hidden="true"></i>' . esc_html($error_message) . '</div>';
            }
            if ($success_message !== '') {
                echo '<div class="success-message mb-4"><i class="bi bi-check-circle mr-2" aria-hidden="true"></i>' . esc_html($success_message) . '</div>';
            }

            $part_slug = 'template-parts/planner-admin/' . $view;
            if (locate_template($part_slug . '.php')) {
                get_template_part($part_slug, null, ['view' => $view]);
            } else {
                get_template_part('template-parts/planner-admin/destinations', null, ['view' => $view]);
            }
            ?>
        </section>
    </main>
</div>

<?php opencomune_render_dashboard_shell_close(); ?>
