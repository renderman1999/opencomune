<?php
/*
Template Name: Dettaglio Partner Test
*/

if (!is_user_logged_in() || !opencomune_user_can_access_ufficio_dashboard()) {
    wp_redirect(home_url());
    exit;
}

get_header();
?>

<div class="container mx-auto px-4 py-8">
    <h1>Test Dettaglio Partner</h1>
    <p>Template funzionante!</p>
    <p>Partner ID: <?php echo isset($_GET['id']) ? intval($_GET['id']) : 'Nessun ID'; ?></p>
</div>

<?php get_footer(); ?>
