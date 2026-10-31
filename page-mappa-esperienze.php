<?php
/**
 * Template Name: Mappa Esperienze
 */

get_header();
?>

<div class="oc-public-map">
    <div class="oc-public-map__mobile-bar">
        <div class="oc-public-map__pill-group">
            <button type="button" id="btn-tutto-mobile" class="oc-public-map__pill is-active"><?php esc_html_e('Tutto', 'opencomune'); ?></button>
            <button type="button" id="btn-vicino-mobile" class="oc-public-map__pill"><?php esc_html_e('Vicino a me', 'opencomune'); ?></button>
            <span id="vicino-loader-mobile" class="oc-public-map__loader is-hidden" aria-hidden="true">
                <span class="oc-public-map__loader-spin"></span>
            </span>
        </div>
        <button type="button" id="mobile-filter-toggle" class="oc-public-map__icon-btn" aria-label="<?php esc_attr_e('Filtri', 'opencomune'); ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i>
        </button>
    </div>

    <div id="mobile-filter-panel" class="oc-public-map__mobile-panel">
        <div class="oc-public-map__panel-head">
            <h2 class="oc-public-map__panel-title"><?php esc_html_e('Filtri', 'opencomune'); ?></h2>
            <button type="button" id="mobile-close-filters" class="oc-public-map__close-btn" aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        </div>
        <div class="oc-public-map__field">
            <label class="oc-public-map__label" for="filter-categoria-mobile"><?php esc_html_e('Categoria esperienza', 'opencomune'); ?></label>
            <select id="filter-categoria-mobile" class="oc-public-map__select">
                <option value=""><?php esc_html_e('Tutte', 'opencomune'); ?></option>
            </select>
        </div>
        <div class="oc-public-map__actions">
            <button type="button" id="mobile-apply-filters" class="oc-public-map__btn oc-public-map__btn--primary"><?php esc_html_e('Applica', 'opencomune'); ?></button>
            <button type="button" id="reset-filtri-mobile" class="oc-public-map__btn oc-public-map__btn--secondary"><?php esc_html_e('Reset', 'opencomune'); ?></button>
        </div>
    </div>

    <div id="tour-map-container" class="oc-public-map__shell">
        <div id="two-finger-hint" class="oc-public-map__hint" role="status">
            <span class="oc-public-map__hint-mobile"><?php esc_html_e('Usa due dita per muovere la mappa', 'opencomune'); ?></span>
            <span class="oc-public-map__hint-desktop"><?php esc_html_e('Usa Ctrl+scroll per zoomare la mappa', 'opencomune'); ?></span>
        </div>

        <button type="button" id="desktop-filter-open" class="oc-public-map__filters-open" aria-label="<?php esc_attr_e('Apri filtri', 'opencomune'); ?>">
            <i class="bi bi-funnel" aria-hidden="true"></i>
            <span><?php esc_html_e('Filtri', 'opencomune'); ?></span>
        </button>

        <div id="map-filters-column" class="oc-public-map__filters-column">
            <aside id="tour-map-sidebar" class="oc-public-map__sidebar">
                <p class="oc-public-map__sidebar-payoff">
                    <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                    <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
                </p>
                <div class="oc-public-map__sidebar-head">
                    <h2 class="oc-public-map__sidebar-title"><?php esc_html_e('Filtra le esperienze', 'opencomune'); ?></h2>
                    <button type="button" id="desktop-filter-close" class="oc-public-map__sidebar-close" aria-label="<?php esc_attr_e('Chiudi filtri', 'opencomune'); ?>">
                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="oc-public-map__pill-group oc-public-map__field" id="vicino-tutto-group-desktop">
                    <button type="button" id="btn-tutto-desktop" class="oc-public-map__pill is-active"><?php esc_html_e('Tutto', 'opencomune'); ?></button>
                    <button type="button" id="btn-vicino-desktop" class="oc-public-map__pill"><?php esc_html_e('Vicino a me', 'opencomune'); ?></button>
                    <span id="vicino-loader-desktop" class="oc-public-map__loader is-hidden" aria-hidden="true">
                        <span class="oc-public-map__loader-spin"></span>
                    </span>
                </div>
                <div class="oc-public-map__field">
                    <label class="oc-public-map__label" for="filter-categoria-desktop"><?php esc_html_e('Tipologia esperienza', 'opencomune'); ?></label>
                    <select id="filter-categoria-desktop" class="oc-public-map__select">
                        <option value=""><?php esc_html_e('Tutte', 'opencomune'); ?></option>
                    </select>
                </div>
                <button type="button" id="reset-filtri-desktop" class="oc-public-map__btn oc-public-map__btn--secondary"><?php esc_html_e('Reset filtri', 'opencomune'); ?></button>
            </aside>
        </div>

        <div id="tour-map" class="oc-public-map__map" aria-label="<?php esc_attr_e('Mappa delle esperienze', 'opencomune'); ?>"></div>
    </div>
</div>

<div id="tour-modal" class="oc-public-map__modal is-hidden" aria-hidden="true">
    <div class="oc-public-map__modal-panel" role="dialog" aria-modal="true" aria-labelledby="tour-modal-title">
        <button type="button" id="close-modal" class="oc-public-map__modal-close" aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>">&times;</button>
        <div id="modal-content"></div>
    </div>
</div>

<?php get_footer(); ?>
