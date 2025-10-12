<?php get_header(); ?>
<main class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6"><?php the_archive_title(); ?></h1>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article class="mb-8">
            <h2 class="text-xl font-semibold"><a href="<?php the_permalink(); ?>" class="text-blue-600 hover:underline"><?php the_title(); ?></a></h2>
            <div class="text-gray-700"><?php the_excerpt(); ?></div>
        </article>
    <?php endwhile; endif; ?>
</main>
<?php get_footer(); ?> 