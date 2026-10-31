<?php
get_header();

$oc_hero_bg = opencomune_get_home_hero_image_url();
$oc_home_map_url = home_url('/mappa/');
$oc_home_tours = opencomune_get_home_esperienze_for_visitors(12);
$oc_home_place = opencomune_get_home_seo_place_label();
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js" async=""></script>

<div class="oc-home">
    <section class="oc-home-hero">
        <img
            class="oc-home-hero__bg"
            src="<?php echo esc_url($oc_hero_bg); ?>"
            alt=""
            width="1920"
            height="1080"
            fetchpriority="high"
            decoding="async"
            aria-hidden="true"
        >
        <div class="oc-home-hero__inner">
            <p class="oc-home-hero__brand"><?php echo esc_html(get_bloginfo('name')); ?></p>
            <h1 class="oc-home-hero__title">
                <?php
                if ($oc_home_place !== '') {
                    printf(
                        esc_html__('Vivi il territorio, un’esperienza alla volta', 'opencomune'),
                        esc_html($oc_home_place)
                    );
                } else {
                    esc_html_e('Vivi il territorio, un’esperienza alla volta', 'opencomune');
                }
                ?>
            </h1>
            <p class="oc-home-hero__lead">
                <?php
                if ($oc_home_place !== '') {
                    printf(
                        esc_html__('Scopri luoghi, attività ed eventi e trova le esperienze perfette per il tuo viaggio', 'opencomune'),
                        esc_html($oc_home_place)
                    );
                } else {
                    esc_html_e('Scopri luoghi, attività ed eventi e trova le esperienze perfette per il tuo viaggio', 'opencomune');
                }
                ?>
            </p>

            <div class="oc-home-search-panel">
                <form id="tour-search-form" class="oc-home-search" role="search" autocomplete="off">
                    <label class="oc-home-search__label" for="tour-search-input">
                        <?php esc_html_e('Cerca esperienze', 'opencomune'); ?>
                    </label>
                    <div class="oc-home-search__row">
                        <input
                            type="search"
                            id="tour-search-input"
                            class="oc-home-search__input"
                            placeholder="<?php esc_attr_e('Cosa vorresti fare?', 'opencomune'); ?>"
                            autocomplete="off"
                        />
                        <button type="submit" class="oc-home-search__btn"><?php esc_html_e('Cerca', 'opencomune'); ?></button>
                    </div>
                    <div id="tour-search-suggestions" class="oc-home-search__suggestions hidden"></div>
                </form>
                <div id="tour-search-results" class="oc-home-search-results" aria-live="polite"></div>
            </div>
        </div>

        <div class="oc-home-hero__tours">
            <?php if (!empty($oc_home_tours)) : ?>
                <div class="swiper oc-home-tours-swiper">
                    <div class="swiper-wrapper">
                        <?php
                        foreach ($oc_home_tours as $post) {
                            get_template_part('template-parts/home', 'esperienza-card', [
                                'post_id' => $post->ID,
                                'in_slider' => true,
                            ]);
                        }
                        ?>
                    </div>
                    <div class="swiper-pagination"></div>
                </div>
                <div class="oc-home-hero__actions">
                    <a class="oc-home-btn oc-home-btn--light" href="<?php echo esc_url($oc_home_map_url); ?>">
                        <?php esc_html_e('Tutte le esperienze', 'opencomune'); ?>
                    </a>
                </div>
            <?php else : ?>
                <?php opencomune_render_homepage_esperienze_empty_alert(); ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<section class="oc-home-section oc-home-section--muted oc-home-section--full" aria-labelledby="oc-home-why-title">
    <div class="oc-home-section__inner">
        <h2 id="oc-home-why-title" class="oc-home-section__title"><?php esc_html_e('Perché prenotare qui', 'opencomune'); ?></h2>
        <p class="oc-home-section__lead"><?php esc_html_e('Confronta le esperienze, leggi le recensioni degli altri viaggiatori e prenota online in modo semplice e sicuro, tutto in un unico posto.', 'opencomune'); ?></p>
        <div class="swiper oc-home-trust-swiper" aria-label="<?php esc_attr_e('Motivi per prenotare', 'opencomune'); ?>">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="oc-home-trust__item">
                        <h3><?php esc_html_e('Tutto il territorio, in una unica piattaforma', 'opencomune'); ?></h3>
                        <p><?php esc_html_e('Dalle visite culturali alle attività all\'aria aperta, dalle tradizioni locali agli eventi: trova le esperienze che rendono speciale la tua destinazione e scegli come viverla.', 'opencomune'); ?></p>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="oc-home-trust__item">
                        <h3><?php esc_html_e('Scegli l\'esperienza giusta per te', 'opencomune'); ?></h3>
                        <p><?php esc_html_e('Consulta le informazioni di ogni attività, scopri dove si svolge, quanto dura e chi la organizza. Confronta le proposte e trova quella più adatta ai tuoi desideri.', 'opencomune'); ?></p>
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="oc-home-trust__item">
                        <h3><?php esc_html_e('Prenota in pochi semplici passaggi', 'opencomune'); ?></h3>
                        <p><?php esc_html_e('Verifica date e disponibilità, indica i partecipanti e completa la prenotazione online con pagamento sicuro. Organizza le tue attività comodamente, senza telefonate.', 'opencomune'); ?></p>
                    </div>
                </div>
            </div>
            <div class="swiper-pagination oc-home-trust-swiper__pagination"></div>
        </div>
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.oc-home-tours-swiper')) {
        new Swiper('.oc-home-tours-swiper', {
            slidesPerView: 1.08,
            spaceBetween: 16,
            pagination: { el: '.oc-home-tours-swiper .swiper-pagination', clickable: true },
            breakpoints: {
                640: { slidesPerView: 1.6, spaceBetween: 20 },
                900: { slidesPerView: 2.4, spaceBetween: 24 },
                1200: { slidesPerView: 3.2, spaceBetween: 28 },
            },
        });
    }

    if (document.querySelector('.oc-home-trust-swiper')) {
        new Swiper('.oc-home-trust-swiper', {
            slidesPerView: 1.08,
            spaceBetween: 16,
            pagination: {
                el: '.oc-home-trust-swiper__pagination',
                clickable: true,
            },
            breakpoints: {
                768: {
                    slidesPerView: 3,
                    spaceBetween: 20,
                    allowTouchMove: false,
                },
            },
        });
    }
});
</script>

<script>
jQuery(document).ready(function($) {
    let timer;
    const $suggestions = $('#tour-search-suggestions');

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderSearchResultItem(item) {
        const title = escapeHtml(item.title);
        const price = item.price ? escapeHtml(item.price) : '<?php echo esc_js(__('Prezzo su richiesta', 'opencomune')); ?>';
        const img = item.img ? escapeHtml(item.img) : '';
        const link = escapeHtml(item.link);
        let thumbHtml = '<span class="oc-home-search-results__thumb oc-home-search-results__thumb--empty" aria-hidden="true"></span>';
        if (img) {
            thumbHtml = '<span class="oc-home-search-results__thumb"><img src="' + img + '" alt="" loading="lazy" decoding="async" width="52" height="52"></span>';
        }
        return (
            '<a href="' + link + '" class="oc-home-search-results__item">' +
            thumbHtml +
            '<span class="oc-home-search-results__body">' +
            '<span class="oc-home-search-results__title">' + title + '</span>' +
            '<span class="oc-home-search-results__price">' + price + '</span>' +
            '</span></a>'
        );
    }

    function renderSearchResultsList(items, options) {
        const opts = options || {};
        let html = '<div class="oc-home-search-results__list">';
        items.forEach(function(item) {
            html += renderSearchResultItem(item);
        });
        if (opts.showSeeAll && opts.query) {
            html += '<button type="button" class="oc-home-search-results__see-all" id="see-all-results">' +
                '<?php echo esc_js(__('Vedi tutti i risultati per', 'opencomune')); ?> &quot;<strong>' + escapeHtml(opts.query) + '</strong>&quot;' +
                '</button>';
        }
        html += '</div>';
        return html;
    }

    $('#tour-search-input').on('input', function() {
        clearTimeout(timer);
        const query = $(this).val();
        if (query.length < 2) {
            $suggestions.addClass('hidden').hide();
            return;
        }
        timer = setTimeout(function() {
            $.ajax({
                url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                method: 'GET',
                data: {
                    action: 'opencomune_search_tour_autocomplete',
                    q: query
                },
                success: function(res) {
                    if (res.success && res.data.length) {
                        $suggestions.html(renderSearchResultsList(res.data, {
                            showSeeAll: true,
                            query: query,
                        })).removeClass('hidden').show();
                    } else {
                        $suggestions.html('<div class="oc-home-search-results__empty"><?php echo esc_js(__('Nessun risultato', 'opencomune')); ?></div>').removeClass('hidden').show();
                    }
                }
            });
        }, 300);
    });

    $suggestions.on('click', '#see-all-results', function(e) {
        e.preventDefault();
        searchAndShowResults($('#tour-search-input').val());
        $suggestions.addClass('hidden').hide();
    });

    $('#tour-search-form').on('submit', function(e) {
        e.preventDefault();
        searchAndShowResults($('#tour-search-input').val());
        $suggestions.addClass('hidden').hide();
    });

    function searchAndShowResults(query) {
        const $results = $('#tour-search-results');
        $results.html('<div class="oc-home-search-results__list oc-home-search-results__list--loading"><div class="oc-home-search-results__empty"><?php echo esc_js(__('Caricamento risultati...', 'opencomune')); ?></div></div>');
        $.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            method: 'GET',
            data: {
                action: 'opencomune_search_tour_autocomplete',
                q: query
            },
            success: function(res) {
                if (res.success && res.data.length) {
                    $results.html(renderSearchResultsList(res.data));
                } else {
                    $results.html('<div class="oc-home-search-results__list"><div class="oc-home-search-results__empty"><?php echo esc_js(__('Nessun risultato trovato.', 'opencomune')); ?></div></div>');
                }
            }
        });
    }

    $(document).on('click', function(e) {
        if (!$(e.target).closest('.oc-home-search-panel').length) {
            $suggestions.addClass('hidden').hide();
        }
    });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (!document.querySelector('.homeReviewSwiper')) {
        return;
    }
    const swiper = new Swiper('.homeReviewSwiper', {
        slidesPerView: 1,
        spaceBetween: 24,
        loop: true,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        breakpoints: {
            640: { slidesPerView: 1 },
            900: { slidesPerView: 2 },
            1200: { slidesPerView: 3 },
        },
    });

    function attachToggleReview() {
        document.querySelectorAll('.homeReviewSwiper .toggle-review').forEach(function(btn) {
            if (!btn.dataset.toggleBound) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const card = btn.closest('.review-content');
                    card.querySelector('.short-text').classList.toggle('hidden');
                    card.querySelector('.full-text').classList.toggle('hidden');
                    btn.textContent = btn.textContent === '<?php echo esc_js(__('Leggi di più', 'opencomune')); ?>' ? '<?php echo esc_js(__('Mostra meno', 'opencomune')); ?>' : '<?php echo esc_js(__('Leggi di più', 'opencomune')); ?>';
                });
                btn.dataset.toggleBound = '1';
            }
        });
    }
    attachToggleReview();
    swiper.on('slideChangeTransitionEnd', attachToggleReview);
});
</script>

<?php
require_once get_template_directory() . '/review-system.php';
opencomune_show_home_reviews_swiper(8);
?>

<?php
$oc_home_categories = function_exists('opencomune_get_esperienze_archive_filter_categories')
    ? opencomune_get_esperienze_archive_filter_categories()
    : [];
if ($oc_home_categories !== []) :
    $oc_esperienze_archive_url = get_post_type_archive_link('esperienze');
    ?>
<section class="oc-home-section oc-home-section--categories" aria-labelledby="oc-home-categories-title">
    <div class="oc-home-container">
        <h2 id="oc-home-categories-title" class="oc-home-section__title">
            <?php esc_html_e('Esplora per categoria', 'opencomune'); ?>
        </h2>
        <p class="oc-home-section__lead">
            <?php esc_html_e('Scegli il tipo di attività che ti interessa e scopri tutte le esperienze disponibili.', 'opencomune'); ?>
        </p>
        <div class="oc-home-categories">
            <?php
            foreach ($oc_home_categories as $oc_category_term) {
                get_template_part('template-parts/home', 'category-card', [
                    'term' => $oc_category_term,
                ]);
            }
            ?>
        </div>
        <?php if ($oc_esperienze_archive_url) : ?>
            <p class="oc-home-categories__more">
                <a class="oc-home-btn oc-home-btn--outline" href="<?php echo esc_url($oc_esperienze_archive_url); ?>">
                    <?php esc_html_e('Tutte le esperienze', 'opencomune'); ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<section class="oc-home-section oc-home-section--map" aria-labelledby="oc-home-map-title">
    <div class="oc-home-container">
        <h2 id="oc-home-map-title" class="oc-home-section__title"><?php esc_html_e('Esplora le esperienze sulla mappa', 'opencomune'); ?></h2>
        <p class="oc-home-section__lead"><?php esc_html_e('Scopri cosa fare nella tua destinazione, trova le attività vicino a te e prenota quella perfetta per il tuo viaggio.', 'opencomune'); ?></p>
        <div class="oc-home-map-wrap">
            <div class="oc-home-map-cta">
                <a class="oc-home-btn oc-home-btn--light" href="<?php echo esc_url($oc_home_map_url); ?>">
                    <?php esc_html_e('Scopri di più', 'opencomune'); ?>
                </a>
            </div>
            <div id="tours-map"></div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
