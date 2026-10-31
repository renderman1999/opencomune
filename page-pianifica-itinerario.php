<?php
/**
 * Template Name: Pianifica itinerario
 */

if (!opencomune_planner_is_enabled()) {
    get_header();
    echo '<main class="container mx-auto px-4 py-12"><p>' . esc_html__('Il pianificatore non è disponibile al momento.', 'opencomune') . '</p></main>';
    get_footer();
    return;
}

$oc_planner_step = isset($_GET['step']) ? sanitize_key(wp_unslash($_GET['step'])) : 'landing';
$oc_planner_city = isset($_GET['city']) ? sanitize_title(wp_unslash($_GET['city'])) : '';
$show_wizard = ($oc_planner_step === 'wizard' || $oc_planner_step === 'configure');
$show_origin = ($oc_planner_step === 'origin');

get_header();

get_template_part(
    'template-parts/planner-flow-header',
    null,
    [
        'visible' => true,
    ]
);

$oc_planner_hero_bg = function_exists('opencomune_get_home_hero_image_url') ? opencomune_get_home_hero_image_url() : '';
$oc_planner_hero_style = $oc_planner_hero_bg !== '' ? '--oc-planner-hero-bg: url(' . esc_url($oc_planner_hero_bg) . ');' : '';
?>

<main class="oc-planner oc-planner--wizard flex-1 w-full">
    <section
        id="oc-planner-landing"
        class="oc-planner-landing-scene<?php echo ($show_wizard || $show_origin) ? ' is-hidden' : ''; ?>"
        style="<?php echo esc_attr($oc_planner_hero_style); ?>"
        aria-labelledby="oc-planner-landing-title"
    >
        <div class="oc-planner-landing-scene__bg" aria-hidden="true"></div>
        <div class="oc-planner-landing-scene__overlay" aria-hidden="true"></div>
        <div class="oc-planner-landing-scene__inner">
            <div class="oc-planner-landing oc-planner-panel oc-planner-landing__card">
                <h1 id="oc-planner-landing-title" class="oc-planner-landing__title">
                    <?php esc_html_e('Pianifica la tua esperienza', 'opencomune'); ?>
                </h1>
                <div class="oc-planner-landing__row">
                    <div class="oc-planner-landing__field oc-planner-landing__combobox">
                        <label class="screen-reader-text" for="oc-planner-landing-city">
                            <?php esc_html_e('Dove vuoi andare?', 'opencomune'); ?>
                        </label>
                        <input
                            type="text"
                            id="oc-planner-landing-city"
                            class="oc-planner-landing__input"
                            placeholder="<?php esc_attr_e('Dove vuoi andare?', 'opencomune'); ?>"
                            autocomplete="off"
                            role="combobox"
                            aria-autocomplete="list"
                            aria-expanded="false"
                            aria-controls="oc-planner-landing-city-suggestions"
                        >
                        <ul
                            id="oc-planner-landing-city-suggestions"
                            class="oc-planner-landing__suggestions"
                            role="listbox"
                            hidden
                        ></ul>
                    </div>
                    <button type="button" class="oc-planner-landing__cta" id="oc-planner-landing-start">
                        <?php esc_html_e('Crea la tua esperienza personalizzata', 'opencomune'); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <div class="oc-planner-layout container mx-auto px-4 py-8">
        <div
            id="oc-planner-wizard"
            class="<?php echo $show_wizard || $show_origin ? '' : 'is-hidden'; ?>"
            data-initial-city="<?php echo esc_attr($oc_planner_city); ?>"
            data-initial-step="<?php echo esc_attr($show_origin ? 'origin' : ($show_wizard ? 'wizard' : 'landing')); ?>"
        >
            <div class="oc-planner-body">
                <aside class="oc-planner-sidebar">
                    <section class="oc-planner-panel oc-planner-panel--dates" aria-labelledby="oc-planner-dates-heading">
                        <h2 id="oc-planner-dates-heading" class="oc-planner-panel__title">
                            <?php esc_html_e('Date del viaggio', 'opencomune'); ?>
                        </h2>
                        <div id="oc-planner-calendar" class="oc-planner-calendar" aria-live="polite"></div>
                        <div class="oc-planner-date-range">
                            <div class="oc-planner-date-range__item">
                                <span class="oc-planner-date-range__label"><?php esc_html_e('Inizio', 'opencomune'); ?></span>
                                <span class="oc-planner-date-range__value" id="oc-planner-range-start">—</span>
                            </div>
                            <div class="oc-planner-date-range__item">
                                <span class="oc-planner-date-range__label"><?php esc_html_e('Fine', 'opencomune'); ?></span>
                                <span class="oc-planner-date-range__value" id="oc-planner-range-end">—</span>
                            </div>
                        </div>
                        <input type="hidden" id="oc-planner-start-date" name="oc_planner_start_date">
                        <input type="hidden" id="oc-planner-end-date" name="oc_planner_end_date">

                        <div class="oc-planner-field oc-planner-field--time">
                            <label class="oc-planner-field__label" for="oc-planner-start-time">
                                <?php esc_html_e('A che ora vuoi partire?', 'opencomune'); ?>
                            </label>
                            <input
                                type="time"
                                id="oc-planner-start-time"
                                class="oc-planner-field__input"
                                value="10:00"
                                step="300"
                            >
                        </div>

                        <div class="oc-planner-field oc-planner-field--start">
                            <label class="oc-planner-field__label" for="oc-planner-start-input">
                                <?php esc_html_e('Da dove vorresti iniziare il tuo percorso?', 'opencomune'); ?>
                            </label>
                            <input
                                type="text"
                                id="oc-planner-start-input"
                                class="oc-planner-field__input"
                                autocomplete="off"
                                placeholder="<?php esc_attr_e('Cerca un indirizzo o un luogo', 'opencomune'); ?>"
                            >
                        </div>
                    </section>
                </aside>

                <div class="oc-planner-main">
                    <section class="oc-planner-panel" aria-labelledby="oc-planner-focus-heading">
                        <h2 id="oc-planner-focus-heading" class="oc-planner-panel__title">
                            <?php esc_html_e('Focus del viaggio', 'opencomune'); ?>
                        </h2>
                        <div id="oc-planner-travelers" class="oc-planner-focus-grid" role="list"></div>
                    </section>

                    <section class="oc-planner-panel" aria-labelledby="oc-planner-interests-heading">
                        <h2 id="oc-planner-interests-heading" class="oc-planner-panel__title">
                            <?php esc_html_e('Categorie', 'opencomune'); ?>
                        </h2>
                        <p class="oc-planner-panel__subtitle oc-planner-panel__subtitle--tight">
                            <?php esc_html_e('Seleziona almeno 2 categorie di esperienze', 'opencomune'); ?>
                        </p>
                        <div id="oc-planner-categories" class="oc-planner-interests-grid" role="list"></div>
                    </section>

                    <div class="oc-planner-actions">
                        <button type="button" class="oc-planner-submit" id="oc-planner-continue">
                            <?php esc_html_e('Continua', 'opencomune'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <section
        id="oc-planner-origin"
        class="oc-planner-origin-scene<?php echo $show_origin ? '' : ' is-hidden'; ?>"
        style="<?php echo esc_attr($oc_planner_hero_style); ?>"
        aria-labelledby="oc-planner-origin-heading"
    >
        <div class="oc-planner-origin-scene__bg" aria-hidden="true"></div>
        <div class="oc-planner-origin-scene__overlay" aria-hidden="true"></div>
        <div class="oc-planner-origin-scene__inner">
            <div class="oc-planner-panel oc-planner-origin__card">
                <h2 id="oc-planner-origin-heading" class="oc-planner-panel__title oc-planner-origin__title">
                    <?php esc_html_e('Da dove venite in visita?', 'opencomune'); ?>
                </h2>
                <div id="oc-planner-origin-grid" class="oc-planner-origin-grid" role="radiogroup" aria-labelledby="oc-planner-origin-heading"></div>
                <div class="oc-planner-actions oc-planner-origin__actions">
                    <button type="button" class="oc-planner-submit" id="oc-planner-generate">
                        <?php esc_html_e('Genera itinerario', 'opencomune'); ?>
                    </button>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
