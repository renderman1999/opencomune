<?php get_header(); ?>
<main class="container mx-auto px-4 py-8">
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <article>
            <h1 class="text-3xl font-bold mb-6"><?php the_title(); ?></h1>
            <div class="prose"><?php the_content(); ?></div>
        </article>
    <?php endwhile; endif; ?>
</main>
<?php get_footer(); ?> 