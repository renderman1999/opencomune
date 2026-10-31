<?php
/**
 * Template pagina 404
 *
 * @package OpenComune
 */

get_header();

$esperienze_url = home_url('/esperienze/');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<style>
.error404-swiper .swiper-wrapper {
    align-items: stretch;
}
.error404-swiper .swiper-slide {
    display: flex;
    height: auto;
    box-sizing: border-box;
}
.error404-swiper .swiper-slide .esperienza-card-wrapper {
    display: flex;
    flex-direction: column;
    width: 100%;
    height: 100%;
}
.error404-swiper .swiper-slide .esperienza-card-link {
    flex: 1;
    display: flex;
    flex-direction: column;
}
</style>

<main class="container mx-auto px-4 py-20 md:py-28">
    <div class="max-w-3xl mx-auto text-center mb-16 md:mb-20">
        <h1 class="text-7xl md:text-8xl font-bold mb-4 mt-12 md:mt-20" style="color: #004d99;">404</h1>
        <h2 class="text-3xl md:text-4xl font-bold text-gray-800 mb-4">Pagina non trovata</h2>
        <p class="text-gray-600 text-lg mb-8">
            La pagina che stai cercando non esiste, è stata spostata o il link non è più valido.
        </p>
        <a href="<?php echo esc_url(home_url('/')); ?>" class="inline-flex items-center gap-2 px-6 py-3 text-white font-semibold rounded-lg shadow hover:opacity-90 transition" style="background-color: #004d99;">
            <i class="bi bi-house-door"></i>
            Torna alla home
        </a>
    </div>

    <?php
    $featured = new WP_Query([
        'post_type'      => 'esperienze',
        'posts_per_page' => 8,
        'post_status'    => 'publish',
        'orderby'        => 'rand',
    ]);

    if ($featured->have_posts()) :
    ?>
    <div class="max-w-6xl mx-auto">
        <h2 class="text-2xl font-bold mb-4">Dal territorio</h2>
        <div class="swiper error404-swiper">
            <div class="swiper-wrapper">
                <?php
                while ($featured->have_posts()) :
                    $featured->the_post();
                    ?>
                    <div class="swiper-slide">
                        <?php
                        get_template_part('template-parts/esperienza', 'card', [
                            'wrapper_class' => 'esperienza-card-wrapper h-full w-full',
                            'card_class'    => 'esperienza-card-link block bg-white rounded-2xl shadow p-3 w-full h-full flex flex-col hover:shadow-lg transition no-underline',
                        ]);
                        ?>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
            <div class="swiper-pagination mt-4"></div>
        </div>
        <div class="text-center mt-10 mb-4">
            <a href="<?php echo esc_url($esperienze_url); ?>" class="inline-flex items-center gap-2 text-blue-600 font-semibold hover:underline">
                Vedi tutte le esperienze
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    new Swiper('.error404-swiper', {
        slidesPerView: 1.2,
        spaceBetween: 10,
        breakpoints: {
            640: { slidesPerView: 2.2 },
            1024: { slidesPerView: 3.2 },
            1280: { slidesPerView: 4 },
        },
        pagination: { el: '.error404-swiper .swiper-pagination', clickable: true },
        freeMode: true,
    });
});
</script>

<?php get_footer(); ?>
