<?php

function opencomune_dashboard_shell_page_slugs() {
    return ['dashboard-partner', 'esperienze-partner', 'categorie-esperienze', 'gestione-partner', 'dettaglio-partner', 'nuova-esperienza', 'profilo-partner', 'profilo-partner-pagamenti', 'profilo-partner-ai', 'prenotazione-partner', 'commissioni', 'incassi', 'operazioni', 'dashboard-pianificatore', 'pianificatore'];
}

function opencomune_is_dashboard_app_page() {
    return is_page(opencomune_dashboard_shell_page_slugs());
}

add_action('template_redirect', function () {
    if (is_page(['nuova-esperienza-partner', 'modifica-esperienza-partner'])) {
        $target = home_url('/nuova-esperienza/');
        if (is_page('modifica-esperienza-partner') && isset($_GET['id'])) {
            $target = add_query_arg('id', (int) $_GET['id'], $target);
        }
        wp_safe_redirect($target);
        exit;
    }

    if (is_page('dashboard-ufficio')) {
        if (!opencomune_user_can_access_dashboard()) {
            wp_safe_redirect(home_url());
            exit;
        }
        wp_safe_redirect(opencomune_get_dashboard_url());
        exit;
    }

    if (!opencomune_is_dashboard_app_page()) {
        return;
    }

    if (is_page('gestione-partner') || is_page('dettaglio-partner')) {
        if (!opencomune_user_can_manage_all_esperienze()) {
            wp_safe_redirect(home_url());
            exit;
        }
    } elseif (is_page('categorie-esperienze')) {
        if (!opencomune_esperienze_categories_can_manage()) {
            wp_safe_redirect(home_url('/esperienze-partner/'));
            exit;
        }
    } elseif (is_page('profilo-partner-pagamenti') || is_page('commissioni') || is_page('operazioni') || is_page('dashboard-pianificatore') || is_page('pianificatore')) {
        if (!opencomune_user_can_manage_all_esperienze()) {
            wp_safe_redirect(home_url('/profilo-partner/'));
            exit;
        }
    } elseif (is_page('incassi')) {
        if (!opencomune_user_can_access_dashboard()) {
            wp_safe_redirect(home_url());
            exit;
        }
    } elseif (is_page(['nuova-esperienza', 'profilo-partner', 'profilo-partner-ai', 'prenotazione-partner', 'esperienze-partner'])) {
        if (!opencomune_user_can_access_dashboard()) {
            wp_safe_redirect(home_url());
            exit;
        }
    } elseif (!opencomune_user_can_access_dashboard()) {
        wp_safe_redirect(home_url());
        exit;
    }

    show_admin_bar(false);
    nocache_headers();
});

function opencomune_get_dashboard_current_nav_id() {
    if (is_page('gestione-partner') || is_page('dettaglio-partner')) {
        return 'gestione-partner';
    }
    if (is_page('profilo-partner-ai')) {
        return 'profilo-ai';
    }
    if (is_page('profilo-partner-pagamenti')) {
        return 'profilo-pagamenti';
    }
    if (is_page('profilo-partner')) {
        return 'profilo';
    }
    if (is_page('prenotazione-partner')) {
        return 'prenotazioni';
    }
    if (is_page('commissioni')) {
        return 'commissioni';
    }
    if (is_page('incassi')) {
        return 'incassi';
    }
    if (is_page('operazioni')) {
        return 'operazioni';
    }
    if (is_page('dashboard-pianificatore') || is_page('pianificatore')) {
        if (function_exists('opencomune_planner_admin_get_view')) {
            $planner_view = opencomune_planner_admin_get_view();
            if ($planner_view === 'requests' || $planner_view === 'request-edit') {
                return 'pianificatore-richieste';
            }
        }
        return 'pianificatore';
    }
    if (is_page('esperienze-partner')) {
        return 'esperienze';
    }
    if (is_page('categorie-esperienze')) {
        return 'esperienze-categorie';
    }

    return '';
}

function opencomune_get_dashboard_partner_payments_url() {
    return home_url('/profilo-partner-pagamenti/');
}

function opencomune_get_dashboard_profile_url() {
    return home_url('/profilo-partner/');
}

function opencomune_render_dashboard_shell_open() {
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="profile" href="https://gmpg.org/xfn/11">
        <?php wp_head(); ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    </head>
    <body <?php body_class('oc-dashboard-app-body'); ?>>
    <?php wp_body_open(); ?>
    <?php
}

function opencomune_render_dashboard_shell_close() {
    wp_footer();
    echo '</body></html>';
}

function opencomune_enqueue_dashboard_layout_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    wp_enqueue_style(
        'opencomune-dashboard-app',
        $theme_uri . '/assets/css/dashboard-app.css',
        [],
        file_exists($theme_dir . '/assets/css/dashboard-app.css') ? (string) filemtime($theme_dir . '/assets/css/dashboard-app.css') : '1.0'
    );

    if (!opencomune_user_can_access_dashboard()) {
        return;
    }

    wp_enqueue_script(
        'opencomune-dashboard-sidebar-booking-badges',
        $theme_uri . '/assets/js/dashboard-sidebar-booking-badges.js',
        [],
        file_exists($theme_dir . '/assets/js/dashboard-sidebar-booking-badges.js')
            ? (string) filemtime($theme_dir . '/assets/js/dashboard-sidebar-booking-badges.js')
            : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-sidebar-booking-badges', 'opencomuneDashboardSidebarBadges', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_dashboard_bookings'),
        'pollIntervalMs' => 60000,
        'i18n' => [
            'badgePendingApproval' => __('In attesa approvazione', 'opencomune'),
            'badgePendingPayment' => __('In attesa pagamento', 'opencomune'),
            'badgeNewConfirmed' => __('Nuove prenotazioni confermate', 'opencomune'),
        ],
    ]);
}

function opencomune_enqueue_dashboard_sweetalert() {
    wp_enqueue_script(
        'sweetalert2',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11',
        [],
        '11.0.0',
        true
    );
}

function opencomune_enqueue_dashboard_esperienze_table_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    wp_enqueue_editor();

    wp_enqueue_script(
        'qrcode',
        'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js',
        [],
        '1.5.3',
        true
    );

    wp_enqueue_script(
        'opencomune-dashboard-esperienze-table',
        $theme_uri . '/assets/js/dashboard-esperienze-table.js',
        ['qrcode', 'editor'],
        file_exists($theme_dir . '/assets/js/dashboard-esperienze-table.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-esperienze-table.js') : '1.0',
        true
    );

    opencomune_enqueue_dashboard_sweetalert();

    wp_localize_script('opencomune-dashboard-esperienze-table', 'opencomuneDashboardTable', [
        'showAuthor' => opencomune_user_can_manage_all_esperienze(),
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'translationsNonce' => wp_create_nonce('opencomune_dashboard_translations'),
        'i18n' => [
            'noExperiences' => __('Nessuna esperienza trovata', 'opencomune'),
            'noTranslation' => __('—', 'opencomune'),
            'translations' => __('Traduzioni', 'opencomune'),
            'translationsTitle' => __('Traduzioni inglese', 'opencomune'),
            'translationsHint' => __('Compila i campi in inglese. Il testo italiano e mostrato solo come riferimento.', 'opencomune'),
            'translationsSave' => __('Salva traduzioni', 'opencomune'),
            'translationsSaved' => __('Traduzioni salvate', 'opencomune'),
            'translationsLoadError' => __('Impossibile caricare le traduzioni', 'opencomune'),
            'translationsSaveError' => __('Errore durante il salvataggio', 'opencomune'),
            'aiTranslationSwitch' => __('Usa AI per la traduzione', 'opencomune'),
            'aiAutoTranslate' => __('Traduci automaticamente', 'opencomune'),
            'aiTranslating' => __('Traduzione in corso...', 'opencomune'),
            'aiTranslateError' => __('Impossibile tradurre questo campo.', 'opencomune'),
            'aiTranslateEmpty' => __('Compila prima il testo italiano dell\'esperienza.', 'opencomune'),
            'itBadge' => __('IT', 'opencomune'),
            'enBadge' => __('EN', 'opencomune'),
            'qrTitle' => __('QR Code esperienza', 'opencomune'),
            'qrHint' => __('Scansiona per aprire la pagina pubblica', 'opencomune'),
            'close' => __('Chiudi', 'opencomune'),
            'copyLink' => __('Copia link', 'opencomune'),
            'copied' => __('Link copiato', 'opencomune'),
            'setDraft' => __('Bozza', 'opencomune'),
            'setDraftTitle' => __('Metti in bozza (non visibile sul sito)', 'opencomune'),
            'setPublish' => __('Pubblica', 'opencomune'),
            'setPublishTitle' => __('Pubblica sul sito', 'opencomune'),
            'viewPublicTitle' => __('Apri pagina pubblica', 'opencomune'),
        ],
    ]);
}

function opencomune_enqueue_dashboard_bookings_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    opencomune_enqueue_dashboard_sweetalert();

    wp_enqueue_script('jquery');
    wp_enqueue_style(
        'datatables',
        'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
        [],
        '1.13.6'
    );
    wp_enqueue_script(
        'datatables',
        'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
        ['jquery'],
        '1.13.6',
        true
    );

    wp_enqueue_script(
        'opencomune-booking-cancel-modal',
        $theme_uri . '/assets/js/booking-cancel-modal.js',
        ['sweetalert2'],
        file_exists($theme_dir . '/assets/js/booking-cancel-modal.js') ? (string) filemtime($theme_dir . '/assets/js/booking-cancel-modal.js') : '1.0',
        true
    );

    wp_enqueue_script(
        'opencomune-booking-refund-modal',
        $theme_uri . '/assets/js/booking-refund-modal.js',
        ['sweetalert2'],
        file_exists($theme_dir . '/assets/js/booking-refund-modal.js') ? (string) filemtime($theme_dir . '/assets/js/booking-refund-modal.js') : '1.0',
        true
    );

    $bookings_config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_dashboard_bookings'),
        'showPartner' => opencomune_user_can_manage_all_esperienze(),
        'esperienzeUrl' => function_exists('opencomune_get_esperienze_dashboard_url')
            ? opencomune_get_esperienze_dashboard_url()
            : '',
        'i18n' => [
            'noBookings' => __('Nessuna prenotazione trovata', 'opencomune'),
            'loadError' => __('Impossibile caricare le prenotazioni', 'opencomune'),
            'colExperience' => __('Esperienza', 'opencomune'),
            'colBookingId' => __('ID', 'opencomune'),
            'colPartner' => __('Partner', 'opencomune'),
            'paginationPrev' => __('Precedente', 'opencomune'),
            'paginationNext' => __('Successiva', 'opencomune'),
            'paginationSummary' => __('Pagina %1$s di %2$s (%3$s prenotazioni)', 'opencomune'),
            'colSlot' => __('Data/ora', 'opencomune'),
            'colGuest' => __('Cliente', 'opencomune'),
            'colParticipants' => __('Partecipanti', 'opencomune'),
            'colStatus' => __('Stato', 'opencomune'),
            'colBookedAt' => __('Prenotato il', 'opencomune'),
            'cancelTitle' => __('Annulla prenotazione', 'opencomune'),
            'cancelConfirmBtn' => __('Conferma annullamento', 'opencomune'),
            'cancelled' => __('Prenotazione annullata', 'opencomune'),
            'cancelError' => __('Errore durante l\'annullamento', 'opencomune'),
            'cancelling' => __('Annullamento in corso...', 'opencomune'),
            'close' => __('Chiudi', 'opencomune'),
            'openDetail' => __('Dettaglio prenotazione', 'opencomune'),
            'colBooking' => __('Prenotazione', 'opencomune'),
            'cancelStatusHint' => __('La prenotazione passerà allo stato: Cancellata. I posti verranno ripristinati quando possibile.', 'opencomune'),
            'reasonLabel' => __('Motivazione annullamento', 'opencomune'),
            'reasonPlaceholder' => __('Descrivi il motivo (obbligatorio, salvato nel registro attività)', 'opencomune'),
            'reasonRequired' => __('Inserisci una motivazione di almeno 5 caratteri.', 'opencomune'),
            'badgePendingApproval' => __('In attesa approvazione', 'opencomune'),
            'badgePendingPayment' => __('In attesa pagamento', 'opencomune'),
            'badgeNewConfirmed' => __('Nuove prenotazioni confermate', 'opencomune'),
            'refundTitle' => __('Rimborsa pagamento', 'opencomune'),
            'refundedLabel' => __('Pagamento rimborsato', 'opencomune'),
        ],
    ];

    wp_enqueue_script(
        'opencomune-dashboard-bookings-datatable',
        $theme_uri . '/assets/js/dashboard-bookings-datatable.js',
        ['jquery', 'datatables'],
        file_exists($theme_dir . '/assets/js/dashboard-bookings-datatable.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-bookings-datatable.js') : '1.0',
        true
    );

    wp_localize_script('opencomune-dashboard-bookings-datatable', 'opencomuneDashboardBookings', $bookings_config);

    wp_enqueue_script(
        'opencomune-dashboard-bookings',
        $theme_uri . '/assets/js/dashboard-bookings.js',
        ['opencomune-booking-cancel-modal', 'opencomune-booking-refund-modal', 'opencomune-dashboard-bookings-datatable'],
        file_exists($theme_dir . '/assets/js/dashboard-bookings.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-bookings.js') : '1.0',
        true
    );

    wp_localize_script('opencomune-booking-refund-modal', 'opencomuneBookingRefund', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_dashboard_bookings'),
        'i18n' => [
            'refundTitle' => __('Rimborsa pagamento SumUp', 'opencomune'),
            'refundConfirmBtn' => __('Conferma rimborso', 'opencomune'),
            'refunded' => __('Rimborso inviato', 'opencomune'),
            'refundError' => __('Errore durante il rimborso', 'opencomune'),
            'refunding' => __('Rimborso in corso...', 'opencomune'),
            'close' => __('Annulla', 'opencomune'),
            'colBooking' => __('Prenotazione', 'opencomune'),
            'colExperience' => __('Esperienza', 'opencomune'),
            'colGuest' => __('Cliente', 'opencomune'),
            'colAmount' => __('Importo da rimborsare', 'opencomune'),
            'refundHint' => __('L\'importo verrà restituito al cliente tramite SumUp. L\'operazione non può essere annullata.', 'opencomune'),
            'refundConfirmPlain' => __('Confermi il rimborso completo del pagamento?', 'opencomune'),
        ],
    ]);

    wp_localize_script('opencomune-booking-cancel-modal', 'opencomuneBookingCancel', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_dashboard_bookings'),
        'i18n' => [
            'cancelTitle' => __('Annulla prenotazione', 'opencomune'),
            'cancelConfirmBtn' => __('Conferma annullamento', 'opencomune'),
            'cancelled' => __('Prenotazione annullata', 'opencomune'),
            'cancelError' => __('Errore durante l\'annullamento', 'opencomune'),
            'cancelling' => __('Annullamento in corso...', 'opencomune'),
            'close' => __('Chiudi', 'opencomune'),
            'colExperience' => __('Esperienza', 'opencomune'),
            'colSlot' => __('Data/ora', 'opencomune'),
            'colGuest' => __('Cliente', 'opencomune'),
            'colParticipants' => __('Partecipanti', 'opencomune'),
            'colStatus' => __('Stato attuale', 'opencomune'),
            'colBooking' => __('Prenotazione', 'opencomune'),
            'cancelStatusHint' => __('La prenotazione passerà allo stato: Cancellata. I posti verranno ripristinati quando possibile.', 'opencomune'),
            'reasonLabel' => __('Motivazione annullamento', 'opencomune'),
            'reasonPlaceholder' => __('Descrivi il motivo (obbligatorio, salvato nel registro attività)', 'opencomune'),
            'reasonRequired' => __('Inserisci una motivazione di almeno 5 caratteri.', 'opencomune'),
        ],
    ]);
}

function opencomune_get_dashboard_hero_background_url() {
    $url = trim((string) get_option('opencomune_swiper_slide_1_image', ''));
    if ($url !== '') {
        return $url;
    }
    return '';
}

function opencomune_enqueue_dashboard_partner_home_assets() {
    opencomune_enqueue_dashboard_layout_assets();
    opencomune_enqueue_dashboard_bookings_assets();

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    wp_enqueue_script(
        'opencomune-dashboard-partner-home',
        $theme_uri . '/assets/js/dashboard-partner-home.js',
        [],
        file_exists($theme_dir . '/assets/js/dashboard-partner-home.js') ? (string) filemtime($theme_dir . '/assets/js/dashboard-partner-home.js') : '1.0',
        true
    );

    add_action('wp_head', 'opencomune_dashboard_partner_head_view_script', 0);
}

/**
 * Imposta subito la vista Prenotazioni (evita flash dell'hero in home).
 */
function opencomune_dashboard_partner_head_view_script() {
    if (!is_page_template('page-dashboard-partner.php')) {
        return;
    }
    echo "<script>if(window.location.hash==='#section-prenotazioni'){document.documentElement.classList.add('oc-dashboard-view-prenotazioni');}</script>\n";
}

function opencomune_enqueue_dashboard_assets() {
    opencomune_enqueue_dashboard_layout_assets();
    opencomune_enqueue_dashboard_esperienze_table_assets();
    opencomune_enqueue_dashboard_bookings_assets();
}

function opencomune_get_dashboard_sidebar_config() {
    $user = wp_get_current_user();
    $profile_email = $user instanceof WP_User && $user->exists() ? $user->user_email : '';
    $is_admin = opencomune_user_can_manage_all_esperienze();

    $new_experience_url = home_url('/nuova-esperienza/');

    $actions = [
        [
            'label' => __('Nuova esperienza', 'opencomune'),
            'href' => $new_experience_url,
            'icon' => 'bi-plus-circle',
            'primary' => true,
        ],
    ];

    if ($is_admin) {
        $actions[] = [
            'label' => __('Mappa esperienze', 'opencomune'),
            'href' => home_url('/mappa-esperienze/'),
            'icon' => 'bi-map',
        ];
    }

    $dashboard_url = opencomune_get_dashboard_url();
    $items = [
        ['id' => 'overview', 'label' => __('Home', 'opencomune'), 'icon' => 'bi-house-door', 'href' => $dashboard_url . '#section-overview'],
        array_merge(
            [
                'id' => 'esperienze',
                'label' => __('Esperienze', 'opencomune'),
                'icon' => 'bi-compass',
                'href' => opencomune_get_esperienze_dashboard_url(),
            ],
            $is_admin ? [
                'children' => [
                    [
                        'id' => 'esperienze-categorie',
                        'label' => __('Categorie', 'opencomune'),
                        'href' => opencomune_get_esperienze_categories_dashboard_url(),
                    ],
                ],
            ] : []
        ),
        ['id' => 'prenotazioni', 'label' => __('Prenotazioni', 'opencomune'), 'icon' => 'bi-calendar-check', 'href' => $dashboard_url . '#section-prenotazioni', 'badges' => opencomune_get_dashboard_booking_sidebar_badge_counts()],
        [
            'id' => 'profilo',
            'label' => __('Profilo', 'opencomune'),
            'icon' => 'bi-person',
            'href' => home_url('/profilo-partner/'),
            'children' => array_values(array_filter([
                $is_admin ? [
                    'id' => 'profilo-pagamenti',
                    'label' => __('Pagamenti SumUp (sito)', 'opencomune'),
                    'href' => opencomune_get_dashboard_partner_payments_url(),
                ] : null,
                [
                    'id' => 'profilo-ai',
                    'label' => __('Traduzione automatica (AI)', 'opencomune'),
                    'href' => opencomune_get_dashboard_partner_ai_url(),
                ],
            ])),
        ],
    ];

    if ($is_admin) {
        array_splice($items, 3, 0, [[
            'id' => 'pianificatore',
            'label' => __('Pianificatore', 'opencomune'),
            'icon' => 'bi-signpost-2',
            'href' => opencomune_get_planner_admin_url(),
            'children' => [
                [
                    'id' => 'pianificatore-richieste',
                    'label' => __('Richieste', 'opencomune'),
                    'href' => opencomune_planner_admin_base_url('requests'),
                ],
            ],
        ]]);
    }

    $items[] = [
        'id' => 'incassi',
        'label' => __('Incassi', 'opencomune'),
        'icon' => 'bi-cash-stack',
        'href' => opencomune_get_incassi_dashboard_url(),
    ];

    if ($is_admin) {
        $items[] = [
            'id' => 'operazioni',
            'label' => __('Operazioni', 'opencomune'),
            'icon' => 'bi-journal-text',
            'href' => opencomune_get_operations_dashboard_url(),
        ];
        $items[] = [
            'id' => 'commissioni',
            'label' => __('Commissioni', 'opencomune'),
            'icon' => 'bi-percent',
            'href' => opencomune_get_commission_settings_url(),
        ];
        $items[] = [
            'id' => 'gestione-partner',
            'label' => __('Gestione partner', 'opencomune'),
            'icon' => 'bi-people',
            'href' => home_url('/gestione-partner/'),
        ];
    }

    return [
        'title' => __('Dashboard', 'opencomune'),
        'subtitle' => $is_admin
            ? __('Tutte le esperienze del sistema', 'opencomune')
            : __('Gestisci le tue esperienze', 'opencomune'),
        'items' => $items,
        'actions' => $actions,
        'profile_url' => opencomune_get_dashboard_profile_url(),
        'profile_email' => $profile_email,
        'current_nav' => opencomune_get_dashboard_current_nav_id(),
    ];
}

function opencomune_get_dashboard_esperienza_translations($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('opencomune_get_esperienza_translation_availability')) {
        return [];
    }

    $availability = opencomune_get_esperienza_translation_availability($post_id);
    $translations = [];

    $translations[] = [
        'lang' => 'it',
        'title' => get_the_title($post_id),
    ];

    if (!empty($availability['en'])) {
        $en_title = function_exists('opencomune_get_esperienza_english_field_value')
            ? opencomune_get_esperienza_english_field_value($post_id, 'title')
            : '';
        $translations[] = [
            'lang' => 'en',
            'title' => $en_title,
        ];
    }

    return $translations;
}

function opencomune_render_dashboard_sidebar() {
    $config = opencomune_get_dashboard_sidebar_config();
    get_template_part('template-parts/dashboard', 'sidebar', $config);
}
