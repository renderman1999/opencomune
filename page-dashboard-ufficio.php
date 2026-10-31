<?php
/* Template Name: Dashboard Ufficio Turistico */
if (!is_user_logged_in() || !opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

wp_safe_redirect(opencomune_get_dashboard_url());
exit;
