<?php
get_header();

require_once get_template_directory() . '/review-system.php';

$selected_category = opencomune_get_esperienze_archive_filter_category();
$filter_categories = opencomune_get_esperienze_archive_filter_categories();
$archive_url = get_post_type_archive_link('esperienze');
?>

<style>
.oc-esperienze-archive {
    --oc-home-accent: var(--brand-primary, #ffad02);
    --oc-home-accent-dark: var(--brand-primary-hover, #ff8c00);
}
.esperienze-archive-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
    align-items: stretch;
}
.esperienze-archive-grid .oc-home-tour-card {
    height: 100%;
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
.esperienze-archive-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-bottom: 1.5rem;
}
.esperienze-filter-badge {
    display: inline-block;
    padding: 0.45rem 0.85rem;
    border-radius: 9999px;
    font-size: 0.875rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #d1d5db;
    color: #374151;
    background: #fff;
    transition: all 0.2s ease;
}
.esperienze-filter-badge:hover {
    border-color: #2563eb;
    color: #2563eb;
}
.esperienze-filter-badge.is-active {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}
.esperienze-empty-notice {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-radius: 0.5rem;
    border: 1px solid #bfdbfe;
    background-color: #eff6ff;
    color: #1e40af;
    font-size: 0.9375rem;
    line-height: 1.5;
}
.esperienze-empty-notice i {
    font-size: 1.125rem;
    margin-top: 0.1rem;
    flex-shrink: 0;
}
</style>

<main class="container mx-auto px-4 py-8 flex-1 w-full oc-esperienze-archive">
    <h1 class="text-3xl font-bold mb-4"><?php post_type_archive_title(); ?></h1>

    <?php if ($filter_categories !== []) : ?>
        <div class="esperienze-archive-filters">
            <a href="<?php echo esc_url($archive_url); ?>"
               class="esperienze-filter-badge<?php echo $selected_category === '' ? ' is-active' : ''; ?>">
                <?php esc_html_e('Tutte', 'opencomune'); ?>
            </a>
            <?php foreach ($filter_categories as $term) : ?>
                <?php if (!$term instanceof WP_Term) {
                    continue;
                } ?>
                <a href="<?php echo esc_url(add_query_arg('categoria', $term->slug, $archive_url)); ?>"
                   class="esperienze-filter-badge<?php echo $selected_category === $term->slug ? ' is-active' : ''; ?>">
                    <?php echo esc_html($term->name); ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (have_posts()) : ?>
        <div class="esperienze-archive-grid">
            <?php
            while (have_posts()) :
                the_post();
                get_template_part('template-parts/home', 'esperienza-card', ['post_id' => get_the_ID()]);
            endwhile;
            ?>
        </div>

        <div class="mt-10">
            <?php
            opencomune_render_pagination([
                'add_args' => $selected_category !== '' ? ['categoria' => $selected_category] : [],
            ]);
            ?>
        </div>
    <?php else : ?>
        <div class="esperienze-empty-notice" role="alert">
            <i class="bi bi-info-circle"></i>
            <span>
                <?php if ($selected_category !== '') : ?>
                    <?php esc_html_e('Nessuna esperienza trovata per la categoria selezionata.', 'opencomune'); ?>
                <?php else : ?>
                    <?php esc_html_e('Nessuna esperienza trovata.', 'opencomune'); ?>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
