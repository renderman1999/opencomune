<?php
/* Template Name: Gestione Partner Test */
if (!is_user_logged_in() || !opencomune_user_can_access_ufficio_dashboard()) {
    wp_redirect(home_url());
    exit;
}
get_header(); ?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900">Gestione Partner - Test</h1>
        <p class="text-gray-600 mt-2">Se vedi questa pagina, il template funziona!</p>
    </div>
</div>

<?php get_footer(); ?>
