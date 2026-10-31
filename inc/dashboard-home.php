<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conteggi partner per la home dashboard (admin).
 *
 * @return array{active:int,pending:int}
 */
function opencomune_get_dashboard_partner_counts() {
    global $wpdb;

    $table = $wpdb->prefix . 'tour_partners';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return ['active' => 0, 'pending' => 0];
    }

    $active = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE status = 'approved'"
    );
    $pending = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$table} WHERE status = 'pending'"
    );

    return [
        'active' => $active,
        'pending' => $pending,
    ];
}

/**
 * @return array{
 *   bookings:int,
 *   passengers:int,
 *   gross_formatted:string,
 *   gross:float,
 *   top_experiences:array<int,array{title:string,count:int}>
 * }
 */
function opencomune_query_dashboard_home_performance(int $days, int $author_id = 0) {
    global $wpdb;

    $days = max(0, $days);
    $author_id = max(0, $author_id);

    $table_prenotazioni = $wpdb->prefix . 'tour_prenotazioni';
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table_prenotazioni)) !== $table_prenotazioni) {
        return opencomune_dashboard_home_performance_empty();
    }

    $where = ["p.status IN ('confermata', 'pending_payment')"];
    $params = [];

    if ($days > 0) {
        $where[] = 'p.data_prenotazione >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $params[] = $days;
    }

    if ($author_id > 0) {
        $where[] = 't.post_author = %d';
        $params[] = $author_id;
    }

    $from = "
        FROM {$table_prenotazioni} p
        LEFT JOIN {$wpdb->posts} t ON p.tour_id = t.ID AND t.post_type = 'esperienze'
    ";
    $where_sql = implode(' AND ', $where);

    $agg_sql = "SELECT COUNT(*) AS bookings, COALESCE(SUM(p.participants), 0) AS passengers {$from} WHERE {$where_sql}";
    $agg_row = $params
        ? $wpdb->get_row($wpdb->prepare($agg_sql, $params))
        : $wpdb->get_row($agg_sql);

    $top_sql = "SELECT p.tour_id, t.post_title AS title, COUNT(*) AS booking_count
        {$from}
        WHERE {$where_sql} AND p.tour_id > 0
        GROUP BY p.tour_id, t.post_title
        ORDER BY booking_count DESC
        LIMIT 3";
    $top_rows = $params
        ? $wpdb->get_results($wpdb->prepare($top_sql, $params))
        : $wpdb->get_results($top_sql);

    $gross = opencomune_query_dashboard_home_gross_total($days, $author_id);

    $top_experiences = [];
    foreach ($top_rows ?: [] as $row) {
        $title = trim((string) ($row->title ?? ''));
        if ($title === '') {
            $title = sprintf('#%d', (int) $row->tour_id);
        }
        $top_experiences[] = [
            'title' => $title,
            'count' => (int) $row->booking_count,
        ];
    }

    return [
        'bookings' => (int) ($agg_row->bookings ?? 0),
        'passengers' => (int) ($agg_row->passengers ?? 0),
        'gross' => $gross,
        'gross_formatted' => number_format_i18n($gross, 2) . ' €',
        'top_experiences' => $top_experiences,
    ];
}

function opencomune_dashboard_home_performance_empty() {
    return [
        'bookings' => 0,
        'passengers' => 0,
        'gross' => 0.0,
        'gross_formatted' => number_format_i18n(0, 2) . ' €',
        'top_experiences' => [],
    ];
}

function opencomune_query_dashboard_home_gross_total(int $days, int $author_id = 0) {
    global $wpdb;

    if (!function_exists('opencomune_tour_ordini_table_name')) {
        return 0.0;
    }

    opencomune_ensure_tour_ordini_table();
    $table = opencomune_tour_ordini_table_name();
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
        return 0.0;
    }

    $where = ["o.status = 'completed'"];
    $params = [];

    if ($days > 0) {
        $where[] = 'o.data_pagamento >= DATE_SUB(NOW(), INTERVAL %d DAY)';
        $params[] = $days;
    }

    $join = "LEFT JOIN {$wpdb->posts} t ON t.ID = o.tour_id AND t.post_type = 'esperienze'";
    if ($author_id > 0) {
        $where[] = 't.post_author = %d';
        $params[] = $author_id;
    }

    $sql = "SELECT COALESCE(SUM(o.totale), 0) FROM {$table} o {$join} WHERE " . implode(' AND ', $where);
    $value = $params
        ? $wpdb->get_var($wpdb->prepare($sql, $params))
        : $wpdb->get_var($sql);

    return round((float) $value, 2);
}

add_action('wp_ajax_opencomune_get_dashboard_home_performance', 'opencomune_get_dashboard_home_performance_callback');
function opencomune_get_dashboard_home_performance_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    $days = isset($_GET['days']) ? (int) $_GET['days'] : 14;
    if (!in_array($days, [14, 30, 90], true)) {
        $days = 14;
    }

    $author_id = 0;
    if (opencomune_user_is_partner_only()) {
        $author_id = get_current_user_id();
    }

    wp_send_json_success(opencomune_query_dashboard_home_performance($days, $author_id));
}

/**
 * Card promozionali home dashboard (filtrate per capacità utente).
 *
 * @return array<int, array<string, mixed>>
 */
function opencomune_get_dashboard_home_promo_cards() {
    $is_admin = opencomune_user_can_manage_all_esperienze();
    $dashboard_url = opencomune_get_dashboard_url();

    $esperienze_url = function_exists('opencomune_get_esperienze_dashboard_url')
        ? opencomune_get_esperienze_dashboard_url()
        : home_url('/esperienze-partner/');
    $ai_url = function_exists('opencomune_get_dashboard_partner_ai_url')
        ? opencomune_get_dashboard_partner_ai_url()
        : home_url('/profilo-partner-ai/');
    $incassi_url = function_exists('opencomune_get_incassi_dashboard_url')
        ? opencomune_get_incassi_dashboard_url()
        : home_url('/incassi/');
    $profile_url = function_exists('opencomune_get_dashboard_profile_url')
        ? opencomune_get_dashboard_profile_url()
        : home_url('/profilo-partner/');
    $bookings_url = $dashboard_url . '#section-prenotazioni';
    $new_experience_url = home_url('/nuova-esperienza/');

    $cards = [
        [
            'visual' => 'qr',
            'icon' => 'bi-qr-code-scan',
            'kicker' => __('Strumenti', 'opencomune'),
            'kicker_icon' => 'bi-lightning-charge',
            'title' => __('Codici QR', 'opencomune'),
            'text' => __('Genera QR per le esperienze pubblicate e offri un accesso rapido alla pagina di prenotazione.', 'opencomune'),
            'url' => $esperienze_url,
            'button' => __('Vai alle esperienze', 'opencomune'),
        ],
        [
            'visual' => 'bookings',
            'icon' => 'bi-calendar-check',
            'kicker' => __('Prenotazioni', 'opencomune'),
            'kicker_icon' => 'bi-inbox',
            'title' => __('Gestisci le prenotazioni', 'opencomune'),
            'text' => $is_admin
                ? __('Consulta, filtra e gestisci tutte le prenotazioni della piattaforma.', 'opencomune')
                : __('Visualizza le richieste ricevute, annulla o rimborsa quando previsto.', 'opencomune'),
            'bullets' => [
                __('Filtri per periodo e stato', 'opencomune'),
                __('Dettaglio e registro attività', 'opencomune'),
            ],
            'url' => $bookings_url,
            'button' => __('Apri prenotazioni', 'opencomune'),
        ],
        [
            'visual' => 'ai',
            'icon' => 'bi-translate',
            'accent' => true,
            'kicker' => __('Profilo', 'opencomune'),
            'kicker_icon' => 'bi-stars',
            'title' => __('Traduzioni con AI', 'opencomune'),
            'text' => __('Pubblica in inglese più velocemente con testi coerenti per titolo, descrizione e dettagli.', 'opencomune'),
            'bullets' => [
                __('Modifica manuale o assistita', 'opencomune'),
                __('Badge lingua nella lista esperienze', 'opencomune'),
            ],
            'url' => $ai_url,
            'button' => __('Configura AI', 'opencomune'),
        ],
        [
            'visual' => 'revenue',
            'icon' => 'bi-graph-up-arrow',
            'kicker' => __('Incassi', 'opencomune'),
            'kicker_icon' => 'bi-cash-stack',
            'title' => __('Monitora i ricavi', 'opencomune'),
            'text' => $is_admin
                ? __('Pagamenti, commissioni e rimborsi su tutta la piattaforma.', 'opencomune')
                : __('Consulta pagamenti completati, commissioni e andamento mensile.', 'opencomune'),
            'bullets' => [
                __('Dettaglio ordini SumUp', 'opencomune'),
                __('Filtri per esperienza e periodo', 'opencomune'),
            ],
            'url' => $incassi_url,
            'button' => __('Apri incassi', 'opencomune'),
        ],
        [
            'visual' => 'profile',
            'icon' => 'bi-person-badge',
            'kicker' => __('Account', 'opencomune'),
            'kicker_icon' => 'bi-person',
            'title' => __('Profilo partner', 'opencomune'),
            'text' => __('Aggiorna dati di contatto, immagine e informazioni visibili agli visitatori.', 'opencomune'),
            'url' => $profile_url,
            'button' => __('Modifica profilo', 'opencomune'),
        ],
        [
            'visual' => 'create',
            'icon' => 'bi-plus-lg',
            'kicker' => __('Esperienze', 'opencomune'),
            'kicker_icon' => 'bi-compass',
            'title' => __('Nuova esperienza', 'opencomune'),
            'text' => __('Avvia il wizard guidato per pubblicare un’attività sul territorio.', 'opencomune'),
            'url' => $new_experience_url,
            'button' => __('Crea esperienza', 'opencomune'),
        ],
    ];

    if ($is_admin) {
        $planner_url = function_exists('opencomune_get_planner_admin_url')
            ? opencomune_get_planner_admin_url()
            : home_url('/pianificatore/');
        $payments_url = function_exists('opencomune_get_dashboard_partner_payments_url')
            ? opencomune_get_dashboard_partner_payments_url()
            : home_url('/profilo-partner-pagamenti/');
        $operations_url = function_exists('opencomune_get_operations_dashboard_url')
            ? opencomune_get_operations_dashboard_url()
            : home_url('/operazioni/');
        $commission_url = function_exists('opencomune_get_commission_settings_url')
            ? opencomune_get_commission_settings_url()
            : home_url('/commissioni/');

        $cards[] = [
            'visual' => 'planner',
            'icon' => 'bi-signpost-2',
            'kicker' => __('Pianificatore', 'opencomune'),
            'kicker_icon' => 'bi-map',
            'title' => __('Catalogo itinerari', 'opencomune'),
            'text' => __('Gestisci destinazioni, tappe e contenuti del trip planner pubblico.', 'opencomune'),
            'bullets' => [
                __('Città e punti di interesse', 'opencomune'),
                __('Esperienze collegate al planner', 'opencomune'),
            ],
            'url' => $planner_url,
            'button' => __('Apri pianificatore', 'opencomune'),
        ];
        $cards[] = [
            'visual' => 'partners',
            'icon' => 'bi-people',
            'kicker' => __('Amministrazione', 'opencomune'),
            'kicker_icon' => 'bi-shield-check',
            'title' => __('Gestione partner', 'opencomune'),
            'text' => __('Approva operatori, modifica profili e monitora l’accesso alla dashboard.', 'opencomune'),
            'url' => home_url('/gestione-partner/'),
            'button' => __('Gestisci partner', 'opencomune'),
        ];
        $cards[] = [
            'visual' => 'ops',
            'icon' => 'bi-journal-text',
            'kicker' => __('Operazioni', 'opencomune'),
            'kicker_icon' => 'bi-activity',
            'title' => __('Registro operazioni', 'opencomune'),
            'text' => __('Traccia pagamenti, rimborsi e attività sensibili del sistema.', 'opencomune'),
            'url' => $operations_url,
            'button' => __('Apri operazioni', 'opencomune'),
        ];
        $cards[] = [
            'visual' => 'commission',
            'icon' => 'bi-percent',
            'kicker' => __('Commissioni', 'opencomune'),
            'kicker_icon' => 'bi-sliders',
            'title' => __('Impostazioni commissioni', 'opencomune'),
            'text' => __('Configura le percentuali applicate ai pagamenti online.', 'opencomune'),
            'url' => $commission_url,
            'button' => __('Apri commissioni', 'opencomune'),
        ];
        $cards[] = [
            'visual' => 'payments',
            'icon' => 'bi-credit-card',
            'kicker' => __('Pagamenti', 'opencomune'),
            'kicker_icon' => 'bi-wallet2',
            'title' => __('SumUp sul sito', 'opencomune'),
            'text' => __('Credenziali e impostazioni del gateway di pagamento centralizzato.', 'opencomune'),
            'url' => $payments_url,
            'button' => __('Configura SumUp', 'opencomune'),
        ];
        $cards[] = [
            'visual' => 'map',
            'icon' => 'bi-map',
            'kicker' => __('Mappa', 'opencomune'),
            'kicker_icon' => 'bi-geo-alt',
            'title' => __('Mappa esperienze', 'opencomune'),
            'text' => __('Visualizza tutte le esperienze geolocalizzate sulla mappa pubblica.', 'opencomune'),
            'url' => home_url('/mappa-esperienze/'),
            'button' => __('Apri mappa', 'opencomune'),
        ];
    }

    return $cards;
}

/**
 * @param array<string, mixed> $card
 */
function opencomune_render_dashboard_home_promo_card(array $card) {
    $visual = sanitize_html_class((string) ($card['visual'] ?? 'default'));
    $icon = sanitize_html_class((string) ($card['icon'] ?? 'bi-link'));
    $kicker_icon = sanitize_html_class((string) ($card['kicker_icon'] ?? 'bi-dot'));
    $accent = !empty($card['accent']);
    $visual_classes = 'oc-dashboard-promo-card__visual oc-dashboard-promo-card__visual--' . $visual;
    if ($accent) {
        $visual_classes .= ' oc-dashboard-promo-card__visual--accent';
    }
    ?>
    <article class="oc-dashboard-promo-card">
        <div class="<?php echo esc_attr($visual_classes); ?>" aria-hidden="true">
            <i class="bi <?php echo esc_attr($icon); ?>" aria-hidden="true"></i>
        </div>
        <div class="oc-dashboard-promo-card__body">
            <p class="oc-dashboard-promo-card__kicker">
                <i class="bi <?php echo esc_attr($kicker_icon); ?>" aria-hidden="true"></i>
                <?php echo esc_html((string) ($card['kicker'] ?? '')); ?>
            </p>
            <h3 class="oc-dashboard-promo-card__title"><?php echo esc_html((string) ($card['title'] ?? '')); ?></h3>
            <?php if (!empty($card['text'])) : ?>
                <p class="oc-dashboard-promo-card__text"><?php echo esc_html((string) $card['text']); ?></p>
            <?php endif; ?>
            <?php if (!empty($card['bullets']) && is_array($card['bullets'])) : ?>
                <ul class="oc-dashboard-promo-card__list">
                    <?php foreach ($card['bullets'] as $bullet) : ?>
                        <li><?php echo esc_html((string) $bullet); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if (!empty($card['url'])) : ?>
                <a href="<?php echo esc_url((string) $card['url']); ?>" class="oc-dashboard-promo-card__btn">
                    <?php echo esc_html((string) ($card['button'] ?? __('Scopri', 'opencomune'))); ?>
                </a>
            <?php endif; ?>
        </div>
    </article>
    <?php
}
