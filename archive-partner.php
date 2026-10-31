<?php
get_header();

$paged = max(1, get_query_var('paged') ? (int) get_query_var('paged') : (int) get_query_var('page'));
$result = opencomune_get_public_partners([
    'paged' => $paged,
    'posts_per_page' => 15,
]);
$partners = $result['partners'];
?>

<style>
.esperienze-archive-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 10px;
}
@media (min-width: 640px) {
    .esperienze-archive-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (min-width: 1024px) {
    .esperienze-archive-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (min-width: 1280px) {
    .esperienze-archive-grid {
        grid-template-columns: repeat(5, minmax(0, 1fr));
    }
}
</style>

<main class="container mx-auto px-4 py-8 flex-1 w-full">
    <h1 class="text-3xl font-bold mb-6">Partner</h1>

    <?php if (!empty($partners)) : ?>
        <div class="esperienze-archive-grid">
            <?php foreach ($partners as $partner) : ?>
                <?php get_template_part('template-parts/partner', 'card', ['partner' => $partner]); ?>
            <?php endforeach; ?>
        </div>

        <?php if ($result['pages'] > 1) : ?>
        <div class="mt-10">
            <?php
            opencomune_render_pagination([
                'total' => (int) $result['pages'],
                'current' => $paged,
            ]);
            ?>
        </div>
        <?php endif; ?>
    <?php else : ?>
        <p class="text-gray-600">Nessun partner trovato.</p>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
