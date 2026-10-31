<?php
$config = (isset($args) && is_array($args)) ? $args : [];
$title = $config['title'] ?? '';
$subtitle = $config['subtitle'] ?? '';
$items = isset($config['items']) && is_array($config['items']) ? $config['items'] : [];
$actions = isset($config['actions']) && is_array($config['actions']) ? $config['actions'] : [];
$profile_url = $config['profile_url'] ?? '';
$profile_email = $config['profile_email'] ?? '';
$current_nav = $config['current_nav'] ?? '';
$current_user = wp_get_current_user();
$profile_name = ($current_user instanceof WP_User && $current_user->exists() && $current_user->display_name !== '')
    ? $current_user->display_name
    : $profile_email;
$logout_url = wp_logout_url(home_url('/'));
?>
<aside class="oc-dashboard-sidebar" aria-label="<?php esc_attr_e('Menu dashboard', 'opencomune'); ?>">
    <div class="oc-dashboard-sidebar__brand">
        <p class="oc-dashboard-sidebar__title"><?php echo esc_html($title); ?></p>
        <?php if ($subtitle !== '') : ?>
            <p class="oc-dashboard-sidebar__subtitle"><?php echo esc_html($subtitle); ?></p>
        <?php endif; ?>
    </div>

    <nav class="oc-dashboard-sidebar__nav">
        <?php foreach ($items as $item) :
            $item_id = $item['id'] ?? '';
            $children = isset($item['children']) && is_array($item['children']) ? $item['children'] : [];
            $child_active = false;
            foreach ($children as $child) {
                if (($child['id'] ?? '') === $current_nav) {
                    $child_active = true;
                    break;
                }
            }
            $parent_active = ($current_nav !== '' && $item_id === $current_nav) || $child_active;
            ?>
            <a href="<?php echo esc_url($item['href']); ?>" class="oc-dashboard-sidebar__link<?php echo $parent_active ? ' is-active' : ''; ?>" data-dashboard-nav="<?php echo esc_attr($item_id); ?>">
                <i class="bi <?php echo esc_attr($item['icon']); ?>" aria-hidden="true"></i>
                <span class="oc-dashboard-sidebar__link-text"><?php echo esc_html($item['label']); ?></span>
                <?php
                if (($item_id === 'prenotazioni') && !empty($item['badges']) && is_array($item['badges'])) {
                    get_template_part('template-parts/dashboard-sidebar', 'booking-badges', ['badges' => $item['badges']]);
                }
                ?>
            </a>
            <?php if (!empty($children)) : ?>
                <div class="oc-dashboard-sidebar__subnav" aria-label="<?php echo esc_attr(sprintf(__('Sottomenu %s', 'opencomune'), $item['label'] ?? '')); ?>">
                    <?php foreach ($children as $child) :
                        $child_id = $child['id'] ?? '';
                        $child_is_active = $current_nav !== '' && $child_id === $current_nav;
                        ?>
                        <a href="<?php echo esc_url($child['href']); ?>" class="oc-dashboard-sidebar__sublink<?php echo $child_is_active ? ' is-active' : ''; ?>" data-dashboard-nav="<?php echo esc_attr($child_id); ?>">
                            <span><?php echo esc_html($child['label']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($actions)) : ?>
        <div class="oc-dashboard-sidebar__actions">
            <?php foreach ($actions as $action) : ?>
                <a href="<?php echo esc_url($action['href']); ?>"
                   class="oc-dashboard-sidebar__action<?php echo !empty($action['primary']) ? ' is-primary' : ''; ?>">
                    <?php if (!empty($action['icon'])) : ?>
                        <i class="bi <?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></i>
                    <?php endif; ?>
                    <span><?php echo esc_html($action['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($profile_email !== '' && $profile_url !== '') : ?>
        <div class="oc-dashboard-sidebar__profile">
            <a href="<?php echo esc_url($profile_url); ?>" class="oc-dashboard-sidebar__profile-link" title="<?php esc_attr_e('Modifica profilo', 'opencomune'); ?>">
                <i class="bi bi-person-circle" aria-hidden="true"></i>
                <span class="oc-dashboard-sidebar__profile-text">
                    <span class="oc-dashboard-sidebar__profile-name"><?php echo esc_html($profile_name); ?></span>
                    <?php if ($profile_name !== $profile_email) : ?>
                        <span class="oc-dashboard-sidebar__profile-email"><?php echo esc_html($profile_email); ?></span>
                    <?php endif; ?>
                </span>
                <i class="bi bi-pencil-square oc-dashboard-sidebar__profile-edit" aria-hidden="true"></i>
            </a>
            <a href="<?php echo esc_url($logout_url); ?>" class="oc-dashboard-sidebar__logout">
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
                <?php esc_html_e('Esci', 'opencomune'); ?>
            </a>
        </div>
    <?php endif; ?>
</aside>
