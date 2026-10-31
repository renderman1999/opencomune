<?php
// Increase memory limit
ini_set('memory_limit', '256M');

function opencomune_configure_smtp($phpmailer) {
    if (!defined('MAIL_HOST') || !MAIL_HOST) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host = MAIL_HOST;
    $phpmailer->SMTPAuth = true;
    $phpmailer->Username = MAIL_USERNAME;
    $phpmailer->Password = MAIL_PASSWORD;
    $phpmailer->SMTPSecure = defined('MAIL_SECURE') ? MAIL_SECURE : 'ssl';
    $phpmailer->Port = defined('MAIL_PORT') ? (int) MAIL_PORT : 465;
    $phpmailer->From = defined('MAIL_FROM') ? MAIL_FROM : get_option('admin_email');
    $phpmailer->FromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : get_bloginfo('name');
}
add_action('phpmailer_init', 'opencomune_configure_smtp');

function opencomune_mail_from($email) {
    return defined('MAIL_FROM') && MAIL_FROM ? MAIL_FROM : $email;
}
add_filter('wp_mail_from', 'opencomune_mail_from');

function opencomune_mail_from_name($name) {
    if (defined('MAIL_FROM_NAME') && MAIL_FROM_NAME) {
        return MAIL_FROM_NAME;
    }
    return $name ?: get_bloginfo('name');
}
add_filter('wp_mail_from_name', 'opencomune_mail_from_name');

function opencomune_get_smtp_config_summary() {
    return [
        'enabled' => defined('MAIL_HOST') && MAIL_HOST,
        'host' => defined('MAIL_HOST') ? MAIL_HOST : '',
        'port' => defined('MAIL_PORT') ? (int) MAIL_PORT : 465,
        'secure' => defined('MAIL_SECURE') ? MAIL_SECURE : 'ssl',
        'username' => defined('MAIL_USERNAME') ? MAIL_USERNAME : '',
        'from' => defined('MAIL_FROM') ? MAIL_FROM : get_option('admin_email'),
        'from_name' => defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : get_bloginfo('name'),
    ];
}

function opencomune_send_admin_test_email($to) {
    $smtp = opencomune_get_smtp_config_summary();
    $subject = 'Test email - ' . get_bloginfo('name');
    $message = "Questa e' un'email di test inviata da " . get_bloginfo('name') . ".\n\n";
    $message .= "Data/ora: " . current_time('d/m/Y H:i:s') . "\n";
    $message .= "Server SMTP: " . ($smtp['host'] ?: 'non configurato') . "\n";
    $message .= "Porta: " . $smtp['port'] . " (" . strtoupper($smtp['secure']) . ")\n";
    $message .= "Mittente: " . $smtp['from'] . "\n";
    $message .= "Nome mittente: " . $smtp['from_name'] . "\n\n";
    $message .= "Se ricevi questo messaggio, la configurazione email funziona correttamente.\n";

    $error_message = '';
    $capture_failure = function($wp_error) use (&$error_message) {
        $error_message = $wp_error->get_error_message();
    };

    add_action('wp_mail_failed', $capture_failure);
    $sent = wp_mail($to, $subject, $message);
    remove_action('wp_mail_failed', $capture_failure);

    global $phpmailer;
    if (!$sent && $error_message === '' && isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
        $error_message = $phpmailer->ErrorInfo;
    }

    return [
        'success' => $sent,
        'error' => $error_message,
    ];
}

// Enqueue Tailwind CSS
function opencomune_enqueue_styles() {
    wp_enqueue_style('opencomune-tailwind', get_stylesheet_uri(), [], filemtime(get_template_directory() . '/style.css'));
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_styles');

function opencomune_is_public_frontend_request() {
    if (is_admin()) {
        return false;
    }
    if (function_exists('opencomune_is_dashboard_app_page') && opencomune_is_dashboard_app_page()) {
        return false;
    }

    return true;
}

function opencomune_enqueue_public_theme_styles() {
    if (!opencomune_is_public_frontend_request()) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $public_css = $theme_dir . '/assets/css/public-theme.css';
    if (!is_readable($public_css)) {
        return;
    }

    wp_enqueue_style(
        'opencomune-public-theme',
        $theme_uri . '/assets/css/public-theme.css',
        ['opencomune-tailwind', 'opencomune-fonts'],
        (string) filemtime($public_css)
    );

    $needs_home_card_styles = is_front_page()
        || is_post_type_archive('esperienze')
        || is_author()
        || is_page_template('page-mappa-esperienze.php');
    if ($needs_home_card_styles) {
        $front_css = $theme_dir . '/assets/css/front-page.css';
        if (is_readable($front_css)) {
            wp_enqueue_style(
                'opencomune-front-page',
                $theme_uri . '/assets/css/front-page.css',
                ['opencomune-public-theme'],
                (string) filemtime($front_css)
            );
        }
    }

    $header_js = $theme_dir . '/assets/js/public-header.js';
    if (is_readable($header_js)) {
        wp_enqueue_script(
            'opencomune-public-header',
            $theme_uri . '/assets/js/public-header.js',
            [],
            (string) filemtime($header_js),
            true
        );
    }
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_public_theme_styles', 25);

function opencomune_public_site_body_class($classes) {
    if (!opencomune_is_public_frontend_request()) {
        return $classes;
    }

    $classes[] = 'oc-public-site';
    $classes[] = 'site-body';

    return $classes;
}
add_filter('body_class', 'opencomune_public_site_body_class');

function opencomune_enqueue_esperienza_rich_content_styles() {
    if (!is_singular('esperienze')) {
        return;
    }

    $path = get_template_directory() . '/assets/css/esperienza-rich-content.css';
    if (!is_readable($path)) {
        return;
    }

    wp_enqueue_style(
        'opencomune-esperienza-rich-content',
        get_template_directory_uri() . '/assets/css/esperienza-rich-content.css',
        ['opencomune-tailwind'],
        (string) filemtime($path)
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_esperienza_rich_content_styles', 20);

function opencomune_enqueue_partner_registration_assets() {
    if (!is_page_template('page-registrazione-partner.php')) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    $reg_css = $theme_dir . '/assets/css/partner-registration.css';
    if (is_readable($reg_css)) {
        wp_enqueue_style(
            'opencomune-partner-registration',
            $theme_uri . '/assets/css/partner-registration.css',
            ['opencomune-public-theme'],
            (string) filemtime($reg_css)
        );
    }

    wp_enqueue_script(
        'sweetalert2',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11',
        [],
        '11',
        true
    );

    if (function_exists('opencomune_enqueue_turnstile_script')) {
        opencomune_enqueue_turnstile_script();
    }

    $wizard_js = $theme_dir . '/assets/js/partner-registration-wizard.js';
    wp_enqueue_script(
        'opencomune-partner-registration-wizard',
        $theme_uri . '/assets/js/partner-registration-wizard.js',
        ['sweetalert2'],
        is_readable($wizard_js) ? (string) filemtime($wizard_js) : '1.0.0',
        true
    );

    wp_localize_script(
        'opencomune-partner-registration-wizard',
        'opencomunePartnerRegistration',
        [
            'turnstileRequired' => function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured(),
            'turnstileSiteKey' => function_exists('opencomune_get_turnstile_site_key') ? opencomune_get_turnstile_site_key() : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('opencomune_nonce'),
            'loginUrl' => home_url('/login/?primo_accesso=1'),
            'confirmColor' => '#ffad02',
        ]
    );

    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key === '') {
        return;
    }

    wp_enqueue_style(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        [],
        '1.9.4'
    );
    wp_enqueue_script(
        'leaflet',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [],
        '1.9.4',
        true
    );

    wp_enqueue_script(
        'google-maps-partner-registration',
        'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places&callback=opencomunePartnerMapsReady',
        [],
        null,
        true
    );
    wp_add_inline_script(
        'google-maps-partner-registration',
        'window.opencomunePartnerMapsReady = function(){ window.opencomunePartnerMapsLoaded = true; document.dispatchEvent(new Event("opencomune-partner-maps-ready")); };',
        'before'
    );

    $address_js = $theme_dir . '/assets/js/partner-profile-address.js';
    wp_enqueue_script(
        'opencomune-partner-profile-address',
        $theme_uri . '/assets/js/partner-profile-address.js',
        ['google-maps-partner-registration'],
        is_readable($address_js) ? (string) filemtime($address_js) : '1.0.0',
        true
    );

    $map_js = $theme_dir . '/assets/js/partner-registration-map.js';
    wp_enqueue_script(
        'opencomune-partner-registration-map',
        $theme_uri . '/assets/js/partner-registration-map.js',
        ['leaflet', 'opencomune-partner-profile-address'],
        is_readable($map_js) ? (string) filemtime($map_js) : '1.0.0',
        true
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_partner_registration_assets', 25);

function opencomune_enqueue_login_page_assets() {
    if (!is_page('login')) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();

    $login_css = $theme_dir . '/assets/css/public-login.css';
    if (is_readable($login_css)) {
        wp_enqueue_style(
            'opencomune-public-login',
            $theme_uri . '/assets/css/public-login.css',
            ['opencomune-public-theme'],
            (string) filemtime($login_css)
        );
    }

    if (function_exists('opencomune_enqueue_turnstile_script')) {
        opencomune_enqueue_turnstile_script();
    }

    $login_js = $theme_dir . '/assets/js/public-login.js';
    wp_enqueue_script(
        'opencomune-public-login',
        $theme_uri . '/assets/js/public-login.js',
        [],
        is_readable($login_js) ? (string) filemtime($login_js) : '1.0.0',
        true
    );

    wp_localize_script(
        'opencomune-public-login',
        'opencomunePublicLogin',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'verifyNonce' => wp_create_nonce('opencomune_partner_verify'),
            'dashboardUrl' => function_exists('opencomune_get_dashboard_url') ? opencomune_get_dashboard_url() : home_url('/'),
            'turnstileRequired' => function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured(),
            'turnstileSiteKey' => function_exists('opencomune_get_turnstile_site_key') ? opencomune_get_turnstile_site_key() : '',
            'turnstileMessage' => __('Completa la verifica anti-spam prima di accedere.', 'opencomune'),
            'loggingInLabel' => __('Accesso in corso...', 'opencomune'),
            'verifyingLabel' => __('Verifica in corso...', 'opencomune'),
            'connectionError' => __('Errore di connessione. Riprova più tardi.', 'opencomune'),
        ]
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_login_page_assets', 30);

function opencomune_enqueue_map_page_assets() {
    if (!is_page_template('page-mappa-esperienze.php')) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $api_key = opencomune_get_google_maps_api_key();

    $map_css = $theme_dir . '/assets/css/public-map.css';
    if (is_readable($map_css)) {
        wp_enqueue_style(
            'opencomune-public-map',
            $theme_uri . '/assets/css/public-map.css',
            ['opencomune-public-theme', 'opencomune-front-page'],
            (string) filemtime($map_css)
        );
    }

    $map_js = $theme_dir . '/assets/js/public-map.js';
    $map_script_deps = [];

    if ($api_key !== '') {
        wp_register_script('opencomune-public-map-bootstrap', false, [], null, true);
        wp_enqueue_script('opencomune-public-map-bootstrap');
        wp_add_inline_script(
            'opencomune-public-map-bootstrap',
            'window.opencomunePublicMapReady=function(){window.opencomunePublicMapMapsLoaded=true;document.dispatchEvent(new Event("opencomune-public-map-ready"));};'
        );
        wp_enqueue_script(
            'google-maps-public-map',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&loading=async&callback=opencomunePublicMapReady',
            ['opencomune-public-map-bootstrap'],
            null,
            true
        );
        $map_script_deps[] = 'google-maps-public-map';
    }

    wp_enqueue_script(
        'opencomune-public-map',
        $theme_uri . '/assets/js/public-map.js',
        $map_script_deps,
        is_readable($map_js) ? (string) filemtime($map_js) : '1.0.0',
        true
    );

    wp_localize_script(
        'opencomune-public-map',
        'opencomunePublicMap',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'hintDesktop' => __('Usa Ctrl+scroll per zoomare la mappa', 'opencomune'),
            'mapsReady' => $api_key !== '',
            'mapsKeyMissing' => __(
                'Impossibile caricare la mappa: configura la Google Maps API key nelle impostazioni del sito.',
                'opencomune'
            ),
        ]
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_map_page_assets', 26);

/**
 * Marker per la mappa in homepage (esperienze geolocalizzate).
 *
 * @return list<array{lat: float, lng: float, title: string}>
 */
function opencomune_get_home_map_markers() {
    $query = new WP_Query([
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    $markers = [];
    foreach ($query->posts as $post_id) {
        $post_id = (int) $post_id;
        $lat = get_post_meta($post_id, 'tour_latitude', true);
        $lng = get_post_meta($post_id, 'tour_longitude', true);
        if (!$lat || !$lng) {
            continue;
        }

        $markers[] = [
            'lat' => (float) $lat,
            'lng' => (float) $lng,
            'title' => html_entity_decode(wp_strip_all_tags(get_the_title($post_id)), ENT_QUOTES, 'UTF-8'),
        ];
    }

    wp_reset_postdata();

    return $markers;
}

function opencomune_enqueue_home_map_assets() {
    if (!is_front_page()) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $api_key = opencomune_get_google_maps_api_key();
    $map_script_deps = [];

    if ($api_key !== '') {
        wp_register_script('opencomune-home-map-bootstrap', false, [], null, true);
        wp_enqueue_script('opencomune-home-map-bootstrap');
        wp_add_inline_script(
            'opencomune-home-map-bootstrap',
            'window.opencomuneHomeMapReady=function(){window.opencomuneHomeMapMapsLoaded=true;document.dispatchEvent(new Event("opencomune-home-map-ready"));};'
        );
        wp_enqueue_script(
            'google-maps-home-map',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&loading=async&callback=opencomuneHomeMapReady',
            ['opencomune-home-map-bootstrap'],
            null,
            true
        );
        $map_script_deps[] = 'google-maps-home-map';
    }

    $home_map_js = $theme_dir . '/assets/js/home-map.js';
    wp_enqueue_script(
        'opencomune-home-map',
        $theme_uri . '/assets/js/home-map.js',
        $map_script_deps,
        is_readable($home_map_js) ? (string) filemtime($home_map_js) : '1.0.0',
        true
    );

    wp_localize_script(
        'opencomune-home-map',
        'opencomuneHomeMap',
        [
            'mapsReady' => $api_key !== '',
            'center' => ['lat' => 41.8719, 'lng' => 12.5674],
            'zoom' => 6,
            'markers' => opencomune_get_home_map_markers(),
        ]
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_home_map_assets', 27);

// Enqueue Google Fonts
function opencomune_enqueue_fonts() {
    wp_enqueue_style(
        'opencomune-fonts',
        'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&family=Titillium+Web:ital,wght@0,200;0,300;0,400;0,600;0,700;0,900;1,200;1,300;1,400;1,600;1,700&display=swap',
        false,
        null
    );
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_fonts');

// Enqueue Select2
function opencomune_enqueue_select2() {
    wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
    wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery'], null, true);
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_select2');

// Enqueue Bootstrap for admin/dashboard pages
function opencomune_enqueue_bootstrap_admin() {
    // Check if we're on a dashboard or admin page
    if (is_page_template('templates/dashboard-guide.php') || 
        is_page('nuovo-tour') || 
        is_page('modifica-tour') || 
        is_page('profilo-guida') || 
        is_page('registrazione-guida') ||
        is_admin()) {
        
        // Bootstrap CSS
        wp_enqueue_style('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
        
        // Bootstrap Icons
        wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css');
        
        // Bootstrap JS
        wp_enqueue_script('bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', ['jquery'], null, true);
    }
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_bootstrap_admin');

// Enqueue admin scripts per media uploader
function opencomune_admin_scripts($hook) {
    // Solo nella pagina delle impostazioni
    if ($hook != 'toplevel_page_opencomune-settings') {
        return;
    }
    
    wp_enqueue_media();
    wp_enqueue_script('jquery');
}
add_action('admin_enqueue_scripts', 'opencomune_admin_scripts');

// Tassonomia per le categorie delle esperienze
function opencomune_register_categorie_esperienze_taxonomy() {
    register_taxonomy(
        'categorie_esperienze',
        'esperienze',
        [
            'label' => 'Categorie Esperienze',
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'categoria-tour'],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_esperienze',
            ],
        ]
    );
}
add_action('init', 'opencomune_register_categorie_esperienze_taxonomy');

function opencomune_register_temi_esperienze_taxonomy() {
    register_taxonomy(
        'temi_esperienze',
        'esperienze',
        [
            'label' => 'Temi esperienze',
            'labels' => [
                'name' => 'Temi esperienze',
                'singular_name' => 'Tema esperienza',
                'search_items' => 'Cerca temi',
                'all_items' => 'Tutti i temi',
                'edit_item' => 'Modifica tema',
                'update_item' => 'Aggiorna tema',
                'add_new_item' => 'Aggiungi nuovo tema',
                'new_item_name' => 'Nome nuovo tema',
                'menu_name' => 'Temi esperienze',
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'tema-esperienza'],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_esperienze',
            ],
        ]
    );
}
add_action('init', 'opencomune_register_temi_esperienze_taxonomy');

function opencomune_register_inclusioni_esperienze_taxonomy() {
    register_taxonomy(
        'inclusioni_esperienze',
        'esperienze',
        [
            'label' => 'Inclusioni esperienze',
            'labels' => [
                'name' => 'Inclusioni esperienze',
                'singular_name' => 'Inclusione',
                'search_items' => 'Cerca inclusioni',
                'all_items' => 'Tutte le inclusioni',
                'edit_item' => 'Modifica inclusione',
                'update_item' => 'Aggiorna inclusione',
                'add_new_item' => 'Aggiungi nuova inclusione',
                'new_item_name' => 'Nome nuova inclusione',
                'menu_name' => 'Inclusioni',
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'inclusione-esperienza'],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_esperienze',
            ],
        ]
    );
}
add_action('init', 'opencomune_register_inclusioni_esperienze_taxonomy');

function opencomune_register_info_prima_prenotazione_taxonomy() {
    register_taxonomy(
        'info_prima_prenotazione',
        'esperienze',
        [
            'label' => 'Info prima di prenotare',
            'labels' => [
                'name' => 'Info prima di prenotare',
                'singular_name' => 'Info prenotazione',
                'search_items' => 'Cerca info',
                'all_items' => 'Tutte le info',
                'edit_item' => 'Modifica info',
                'update_item' => 'Aggiorna info',
                'add_new_item' => 'Aggiungi nuova info',
                'new_item_name' => 'Nome nuova info',
                'menu_name' => 'Prima di prenotare',
            ],
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => false,
            'show_in_nav_menus' => false,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'info-prima-prenotazione'],
            'capabilities' => [
                'manage_terms' => 'manage_categories',
                'edit_terms' => 'manage_categories',
                'delete_terms' => 'manage_categories',
                'assign_terms' => 'edit_esperienze',
            ],
        ]
    );
}
add_action('init', 'opencomune_register_info_prima_prenotazione_taxonomy');

// Custom Post Type: Esperienze
function opencomune_register_cpt_esperienze() {
    register_post_type('esperienze', [
        'labels' => [
            'name' => 'Esperienze',
            'singular_name' => 'Esperienza',
            'menu_name' => 'Esperienze',
            'all_items' => 'Tutte le esperienze',
            'add_new' => 'Aggiungi esperienza',
            'add_new_item' => 'Aggiungi nuova esperienza',
            'edit_item' => 'Modifica esperienza',
            'view_item' => 'Visualizza esperienza',
            'search_items' => 'Cerca esperienze',
            'not_found' => 'Nessuna esperienza trovata',
            'not_found_in_trash' => 'Nessuna esperienza nel cestino',
        ],
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'esperienze'],
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author', 'comments'],
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-location-alt',
        'show_in_rest' => true,
        'taxonomies' => ['post_tag', 'categorie_esperienze', 'temi_esperienze', 'inclusioni_esperienze', 'info_prima_prenotazione'],
        'capability_type' => 'esperienza',
        'map_meta_cap' => true,
        'capabilities' => [
            'edit_post' => 'edit_esperienza',
            'read_post' => 'read_esperienza',
            'delete_post' => 'delete_esperienza',
            'edit_posts' => 'edit_esperienze',
            'edit_others_posts' => 'edit_others_esperienze',
            'publish_posts' => 'publish_esperienze',
            'read_private_posts' => 'read_private_esperienze',
            'delete_posts' => 'delete_esperienze',
            'delete_private_posts' => 'delete_private_esperienze',
            'delete_published_posts' => 'delete_published_esperienze',
            'delete_others_posts' => 'delete_others_esperienze',
            'edit_private_posts' => 'edit_private_esperienze',
            'edit_published_posts' => 'edit_published_esperienze',
        ],
    ]);
}
add_action('init', 'opencomune_register_cpt_esperienze');

function opencomune_grant_admin_esperienze_caps() {
    $admin = get_role('administrator');
    if (!$admin) {
        return;
    }

    $caps = [
        'edit_esperienza',
        'read_esperienza',
        'delete_esperienza',
        'edit_esperienze',
        'edit_others_esperienze',
        'publish_esperienze',
        'read_private_esperienze',
        'delete_esperienze',
        'delete_private_esperienze',
        'delete_published_esperienze',
        'delete_others_esperienze',
        'edit_private_esperienze',
        'edit_published_esperienze',
    ];

    foreach ($caps as $cap) {
        $admin->add_cap($cap);
    }
}
add_action('admin_init', 'opencomune_grant_admin_esperienze_caps');

// Funzione per migrare le categorie dai tag alla tassonomia personalizzata
function opencomune_migrate_tour_categories() {
    // Controlla se la migrazione è già stata fatta
    if (get_option('tour_categories_migrated')) {
        return;
    }
    
    // Recupera tutti i tour
    $tours = get_posts([
        'post_type' => 'esperienze',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);
    
    foreach ($tours as $tour) {
        // Recupera i tag associati al tour
        $tags = wp_get_post_terms($tour->ID, 'post_tag', ['fields' => 'names']);
        
        if (!empty($tags)) {
            // Per ogni tag, crea o recupera il termine nella tassonomia categorie_tour
            foreach ($tags as $tag_name) {
                $term = term_exists($tag_name, 'categorie_tour');
                if (!$term) {
                    $term = wp_insert_term($tag_name, 'categorie_tour');
                }
                
                if (!is_wp_error($term)) {
                    // Associa il termine al tour
                    wp_set_object_terms($tour->ID, $term['term_id'], 'categorie_tour', true);
                }
            }
        }
    }
    
    // Marca la migrazione come completata
    update_option('tour_categories_migrated', true);
}

// Esegui la migrazione quando si attiva il tema
add_action('after_switch_theme', 'opencomune_migrate_tour_categories');

// Aggiungi voce di menu per la migrazione manuale
function opencomune_add_migration_menu() {
    add_submenu_page(
        'edit.php?post_type=esperienze',
        'Migra Categorie',
        'Migra Categorie',
        'manage_options',
        'migrate-tour-categories',
        'opencomune_migration_page'
    );
}
add_action('admin_menu', 'opencomune_add_migration_menu');

function opencomune_migration_page() {
    if (isset($_POST['migrate_categories']) && wp_verify_nonce($_POST['_wpnonce'], 'migrate_categories')) {
        opencomune_migrate_tour_categories();
        echo '<div class="notice notice-success"><p>Categorie migrate con successo!</p></div>';
    }
    
    $migrated = get_option('tour_categories_migrated');
    ?>
    <div class="wrap">
        <h1>Migrazione Categorie Tour</h1>
        <?php if ($migrated): ?>
            <div class="notice notice-info">
                <p>La migrazione delle categorie è già stata completata.</p>
            </div>
        <?php else: ?>
            <p>Questa operazione migrerà tutte le categorie dei tour dai tag alla nuova tassonomia personalizzata.</p>
            <form method="post">
                <?php wp_nonce_field('migrate_categories'); ?>
                <input type="submit" name="migrate_categories" class="button button-primary" value="Esegui Migrazione">
            </form>
        <?php endif; ?>
    </div>
    <?php
}

// Custom Post Type: Partner
function opencomune_register_cpt_partner() {
    register_post_type('partner', [
        'labels' => [
            'name' => 'Partner',
            'singular_name' => 'Partner',
            'menu_name' => 'Partner',
            'all_items' => 'Tutti i partner',
            'add_new' => 'Aggiungi partner',
            'add_new_item' => 'Aggiungi partner',
            'edit_item' => 'Modifica partner',
            'view_item' => 'Visualizza partner',
            'search_items' => 'Cerca partner',
            'not_found' => 'Nessun partner trovato',
            'not_found_in_trash' => 'Nessun partner nel cestino',
        ],
        'public' => true,
        'has_archive' => 'partner',
        'rewrite' => ['slug' => 'scheda-partner', 'with_front' => false],
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_icon' => 'dashicons-groups',
        'menu_position' => 26,
        'capabilities' => [
            'edit_post' => 'manage_options',
            'read_post' => 'manage_options',
            'delete_post' => 'manage_options',
            'edit_posts' => 'manage_options',
            'edit_others_posts' => 'manage_options',
            'publish_posts' => 'manage_options',
            'read_private_posts' => 'manage_options',
            'create_posts' => 'do_not_allow',
            'delete_posts' => 'manage_options',
            'delete_private_posts' => 'manage_options',
            'delete_published_posts' => 'manage_options',
            'delete_others_posts' => 'manage_options',
            'edit_private_posts' => 'manage_options',
            'edit_published_posts' => 'manage_options',
        ],
        'map_meta_cap' => false,
    ]);
}
add_action('init', 'opencomune_register_cpt_partner');

function opencomune_setup_partner_author_permalinks() {
    global $wp_rewrite;

    $wp_rewrite->author_base = 'partner';
    $wp_rewrite->author_structure = '/partner/%author%';

    add_rewrite_rule('^author/([^/]+)/page/?([0-9]{1,})/?$', 'index.php?author_name=$matches[1]&paged=$matches[2]', 'top');
    add_rewrite_rule('^author/([^/]+)/?$', 'index.php?author_name=$matches[1]', 'top');
}
add_action('init', 'opencomune_setup_partner_author_permalinks', 1);

function opencomune_get_partner_profile_url($user_id, $author_nicename = '') {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return '';
    }

    return get_author_posts_url($user_id, $author_nicename);
}

function opencomune_redirect_legacy_author_urls() {
    if (is_admin() || !is_author()) {
        return;
    }

    $request_uri = wp_unslash($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($request_uri, '/author/') === false) {
        return;
    }

    $author = get_queried_object();
    if (!$author instanceof WP_User) {
        return;
    }

    $redirect_url = opencomune_get_partner_profile_url($author->ID, $author->user_nicename);
    $paged = max(1, (int) get_query_var('paged'));
    if ($paged > 1) {
        $redirect_url = trailingslashit($redirect_url) . 'page/' . $paged . '/';
    }

    wp_safe_redirect($redirect_url, 301);
    exit;
}
add_action('template_redirect', 'opencomune_redirect_legacy_author_urls');

function opencomune_maybe_flush_rewrite_rules() {
    $version = '20260815-partner-author-base';
    if (get_option('opencomune_rewrite_version') === $version) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('opencomune_rewrite_version', $version);
}
add_action('init', 'opencomune_maybe_flush_rewrite_rules', 99);

function opencomune_disable_partner_block_editor($use_block_editor, $post_type) {
    if ($post_type === 'partner') {
        return false;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'opencomune_disable_partner_block_editor', 10, 2);

function opencomune_restrict_partner_admin_access() {
    if (!is_admin() || current_user_can('manage_options')) {
        return;
    }

    global $pagenow;
    $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '';
    if ($post_type === '') {
        $post_type = isset($_GET['post']) ? get_post_type((int) $_GET['post']) : '';
    }

    if ($post_type === 'partner' && in_array($pagenow, ['edit.php', 'post.php', 'post-new.php'], true)) {
        wp_die(esc_html__('Non hai il permesso di accedere a questa pagina.', 'opencomune'));
    }
}
add_action('admin_init', 'opencomune_restrict_partner_admin_access');

function opencomune_ensure_partner_role() {
    if (get_role('partner')) {
        return;
    }

    add_role('partner', 'Partner', [
        'read' => true,
        'level_0' => true,
    ]);
}

function opencomune_migrate_legacy_roles_to_partner() {
    opencomune_ensure_partner_role();

    if (get_option('opencomune_roles_migrated_to_partner_v1')) {
        return;
    }

    $legacy_roles = ['guida', 'operatore', 'editor_turistico'];
    foreach ($legacy_roles as $legacy_role) {
        $user_ids = get_users([
            'role' => $legacy_role,
            'fields' => 'ID',
        ]);

        foreach ($user_ids as $user_id) {
            $user = get_userdata((int) $user_id);
            if ($user instanceof WP_User) {
                $user->set_role('partner');
            }
        }

        if (get_role($legacy_role)) {
            remove_role($legacy_role);
        }
    }

    update_option('opencomune_roles_migrated_to_partner_v1', time(), false);
}
add_action('init', 'opencomune_migrate_legacy_roles_to_partner', 5);

function opencomune_user_can_access_ufficio_dashboard() {
    return current_user_can('manage_options');
}

function opencomune_user_can_access_dashboard() {
    if (!is_user_logged_in()) {
        return false;
    }
    if (opencomune_user_can_manage_all_esperienze()) {
        return true;
    }
    return current_user_can('partner');
}

function opencomune_get_dashboard_url() {
    return home_url('/dashboard-partner/');
}

function opencomune_user_can_manage_all_esperienze() {
    return current_user_can('manage_options');
}

function opencomune_user_is_partner_only() {
    return current_user_can('partner') && !current_user_can('manage_options');
}

/**
 * @deprecated Solo partner: non usare per distinguere tipi di esperienza.
 */
function opencomune_user_is_ente_author($user_id) {
    return false;
}

function opencomune_get_esperienza_wizard_url($post_id = 0) {
    $post_id = (int) $post_id;
    if ($post_id > 0) {
        return home_url('/nuova-esperienza/?id=' . $post_id);
    }

    return home_url('/nuova-esperienza/');
}

function opencomune_user_can_edit_esperienza($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze') {
        return false;
    }

    if (opencomune_user_can_manage_all_esperienze()) {
        return true;
    }

    if (current_user_can('partner')) {
        return (int) $post->post_author === get_current_user_id();
    }

    return false;
}

function opencomune_get_esperienza_author_label($author_id) {
    $author_id = (int) $author_id;
    if ($author_id <= 0) {
        return '';
    }

    $author = get_userdata($author_id);
    if (!$author) {
        return '';
    }

    if ($author->first_name || $author->last_name) {
        return trim($author->first_name . ' ' . $author->last_name);
    }

    return $author->display_name;
}

// Metabox per dati aggiuntivi guida
function opencomune_add_guide_metaboxes() {
    add_meta_box(
        'guide_extra_info',
        'Dati Guida',
        'opencomune_render_guide_metabox',
        'guide',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'opencomune_add_guide_metaboxes');

function opencomune_render_guide_metabox($post) {
    $lingue = get_post_meta($post->ID, '_lingue', true);
    $citta = get_post_meta($post->ID, '_citta', true);
    $specializzazioni = get_post_meta($post->ID, '_specializzazioni', true);
    ?>
    <label>Lingue parlate:</label>
    <input type="text" name="lingue" value="<?php echo esc_attr($lingue); ?>" class="widefat" />
    <label>Città operative:</label>
    <input type="text" name="citta" value="<?php echo esc_attr($citta); ?>" class="widefat" />
    <label>Specializzazioni:</label>
    <input type="text" name="specializzazioni" value="<?php echo esc_attr($specializzazioni); ?>" class="widefat" />
    <?php
}

function opencomune_save_guide_metabox($post_id) {
    if (array_key_exists('lingue', $_POST)) {
        update_post_meta($post_id, '_lingue', sanitize_text_field($_POST['lingue']));
    }
    if (array_key_exists('citta', $_POST)) {
        update_post_meta($post_id, '_citta', sanitize_text_field($_POST['citta']));
    }
    if (array_key_exists('specializzazioni', $_POST)) {
        update_post_meta($post_id, '_specializzazioni', sanitize_text_field($_POST['specializzazioni']));
    }
}
add_action('save_post_guide', 'opencomune_save_guide_metabox');

// Menu
register_nav_menus([
    'primary' => 'Menu principale',
]);

// Font-family globale inline
function opencomune_global_font_family() {
    echo '<style>body {   font-family: "Titillium Web", sans-serif; !important}</style>';
}
add_action('wp_head', 'opencomune_global_font_family');

function opencomune_register_specializzazioni_taxonomy() {
    register_taxonomy(
        'specializzazioni',
        'guide',
        array(
            'label' => 'Specializzazioni',
            'rewrite' => array('slug' => 'specializzazione'),
            'hierarchical' => false, // come i tag
            'show_in_rest' => true,
        )
    );
}
//add_action('init', 'opencomune_register_specializzazioni_taxonomy');

// NOTA: Rimuoviamo il filter login_redirect per evitare conflitti
// La gestione del redirect è ora centralizzata in opencomune_handle_custom_login

// Supporto per funzionalità moderne del tema
add_action('after_setup_theme', function() {
    // Title tag gestito da WordPress
    add_theme_support('title-tag');
    // Immagini in evidenza
    add_theme_support('post-thumbnails');
    // HTML5 markup
    add_theme_support('html5', [
        'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script',
    ]);
    // Feed automatici
    add_theme_support('automatic-feed-links');
    // Custom logo
    add_theme_support('custom-logo', [
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
    ]);
    // Custom background
    add_theme_support('custom-background');
    // Custom header
    add_theme_support('custom-header', [
        'width'         => 2000,
        'height'        => 1200,
        'flex-height'   => true,
        'flex-width'    => true,
        'uploads'       => true,
    ]);
});

// AJAX handler per creazione tour dal frontend
add_action('wp_ajax_opencomune_crea_tour', 'opencomune_crea_tour_callback');
function opencomune_crea_tour_callback() {
    if (!is_user_logged_in() || !current_user_can('partner')) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $user_id = get_current_user_id();
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;
    $is_update = false;
    if ($edit_id) {
        $post = get_post($edit_id);
        if ($post && $post->post_type === 'esperienze' && (int)$post->post_author === $user_id) {
            $is_update = true;
        } else {
            wp_send_json_error(['message' => __('Non autorizzato a modificare questo tour.', 'opencomune')]);
        }
    }
    // Dati base
    $titolo = sanitize_text_field($_POST['titolo'] ?? '');
    $categoria = sanitize_text_field($_POST['categoria'] ?? '');
    $citta = sanitize_text_field($_POST['citta'] ?? '');
    $durata = sanitize_text_field($_POST['durata'] ?? '');
    $lingue = isset($_POST['lingue']) ? array_map('sanitize_text_field', (array)$_POST['lingue']) : [];
    $prezzo = floatval($_POST['prezzo'] ?? 0);
    // Step 2
    $desc_breve = sanitize_text_field($_POST['descrizione_breve'] ?? '');
    $desc_completa = wp_kses_post($_POST['descrizione_completa'] ?? '');
    $include = sanitize_text_field($_POST['include'] ?? '');
    $non_include = sanitize_text_field($_POST['non_include'] ?? '');
    $itinerario = sanitize_text_field($_POST['itinerario'] ?? '');
    $note = sanitize_text_field($_POST['note'] ?? '');
    // Step 3
    $indirizzo_ritrovo = sanitize_text_field($_POST['indirizzo_ritrovo'] ?? '');
    $gps = sanitize_text_field($_POST['gps'] ?? '');
    $indicazioni_ritrovo = sanitize_text_field($_POST['indicazioni_ritrovo'] ?? '');
    $difficolta = sanitize_text_field($_POST['difficolta'] ?? '');
    $accessibilita = sanitize_text_field($_POST['accessibilita'] ?? '');
    $min_partecipanti = intval($_POST['min_partecipanti'] ?? 2);
    $max_partecipanti = intval($_POST['max_partecipanti'] ?? 15);
    $prezzo_privato = floatval($_POST['prezzo_privato'] ?? 0);
    $cosa_portare = sanitize_text_field($_POST['cosa_portare'] ?? '');
    // Step 4
    $giorni = isset($_POST['giorni']) ? (array)$_POST['giorni'] : [];
    $orari = [];
    foreach ($giorni as $g) {
        $orari[$g] = sanitize_text_field($_POST['orari_'.$g] ?? '');
    }
    $data_inizio = sanitize_text_field($_POST['data_inizio'] ?? '');
    $data_fine = sanitize_text_field($_POST['data_fine'] ?? '');
    $eccezioni = sanitize_text_field($_POST['eccezioni'] ?? '');
    $scadenza_prenotazioni = sanitize_text_field($_POST['scadenza_prenotazioni'] ?? '');
    $cancellazione = sanitize_text_field($_POST['cancellazione'] ?? '');
    $rimborso = sanitize_text_field($_POST['rimborso'] ?? '');
    $blocco_slot = !empty($_POST['blocco_slot']);
    $lista_attesa = !empty($_POST['lista_attesa']);
    $evento_specifico = !empty($_POST['evento_specifico']);
    $data_evento = sanitize_text_field($_POST['data_evento'] ?? '');
    $ora_evento = sanitize_text_field($_POST['ora_evento'] ?? '');
    // Crea o aggiorna il post tour
    if ($is_update) {
        $post_id = wp_update_post([
            'ID' => $edit_id,
            'post_title' => $titolo,
            'post_content' => $desc_completa,
            'post_excerpt' => $desc_breve,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);
    } else {
        $post_id = wp_insert_post([
            'post_type' => 'esperienze',
            'post_title' => $titolo,
            'post_content' => $desc_completa,
            'post_excerpt' => $desc_breve,
            'post_status' => 'publish',
            'post_author' => $user_id,
        ]);
    }
    if (!$post_id) {
        wp_send_json_error(['message' => __('Errore nella creazione/modifica del tour.', 'opencomune')]);
    }
    update_post_meta($post_id, 'descrizione_breve', $desc_breve);
    // Salva i tag come tag WordPress
    $categorie = isset($_POST['categoria']) ? (array) $_POST['categoria'] : [];
    if (!empty($categorie)) {
        wp_set_post_terms($post_id, $categorie, 'post_tag');
    }
    // Salva custom fields
    update_post_meta($post_id, 'citta', $citta);
    update_post_meta($post_id, 'durata', $durata);
    update_post_meta($post_id, 'lingue', $lingue);
    update_post_meta($post_id, 'prezzo', $prezzo);
    update_post_meta($post_id, 'include', $include);
    update_post_meta($post_id, 'non_include', $non_include);
    update_post_meta($post_id, 'itinerario', $itinerario);
    update_post_meta($post_id, 'note', $note);
    update_post_meta($post_id, 'indirizzo_ritrovo', $indirizzo_ritrovo);
    update_post_meta($post_id, 'gps', $gps);
    update_post_meta($post_id, 'indicazioni_ritrovo', $indicazioni_ritrovo);
    update_post_meta($post_id, 'difficolta', $difficolta);
    update_post_meta($post_id, 'accessibilita', $accessibilita);
    update_post_meta($post_id, 'min_partecipanti', $min_partecipanti);
    update_post_meta($post_id, 'max_partecipanti', $max_partecipanti);
    update_post_meta($post_id, 'prezzo_privato', $prezzo_privato);
    update_post_meta($post_id, 'cosa_portare', $cosa_portare);
    update_post_meta($post_id, 'giorni', $giorni);
    update_post_meta($post_id, 'orari', $orari);
    update_post_meta($post_id, 'data_inizio', $data_inizio);
    update_post_meta($post_id, 'data_fine', $data_fine);
    update_post_meta($post_id, 'eccezioni', $eccezioni);
    update_post_meta($post_id, 'scadenza_prenotazioni', $scadenza_prenotazioni);
    update_post_meta($post_id, 'cancellazione', $cancellazione);
    update_post_meta($post_id, 'rimborso', $rimborso);
    update_post_meta($post_id, 'blocco_slot', $blocco_slot);
    update_post_meta($post_id, 'lista_attesa', $lista_attesa);
    update_post_meta($post_id, 'evento_specifico', $evento_specifico);
    update_post_meta($post_id, 'data_evento', $data_evento);
    update_post_meta($post_id, 'ora_evento', $ora_evento);
    // File upload (foto principale, galleria, video, foto ritrovo)
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    // Foto principale
    if (!empty($_FILES['foto_principale']['tmp_name'])) {
        $attach_id = media_handle_upload('foto_principale', $post_id);
        if (is_wp_error($attach_id)) {
            wp_send_json_error(['message' => __('Errore upload foto principale.', 'opencomune')]);
        }
        set_post_thumbnail($post_id, $attach_id);
    }
    // Galleria immagini
    $gallery_ids = [];
    if ($is_update) {
        // Mantieni solo le immagini che l'utente non ha rimosso
        $keep_ids = isset($_POST['galleria_keep_ids']) ? array_map('intval', (array)$_POST['galleria_keep_ids']) : [];
        if (!empty($keep_ids)) {
            $gallery_ids = $keep_ids;
        }
    }
    if (!empty($_FILES['galleria']['name'][0])) {
        foreach ($_FILES['galleria']['name'] as $i => $name) {
            if (!$_FILES['galleria']['tmp_name'][$i]) continue;
            $file = [
                'name'     => $_FILES['galleria']['name'][$i],
                'type'     => $_FILES['galleria']['type'][$i],
                'tmp_name' => $_FILES['galleria']['tmp_name'][$i],
                'error'    => $_FILES['galleria']['error'][$i],
                'size'     => $_FILES['galleria']['size'][$i]
            ];
            $_FILES['galleria_single'] = $file;
            $attach_id = media_handle_upload('galleria_single', $post_id);
            if (!is_wp_error($attach_id)) {
                $gallery_ids[] = $attach_id;
            }
        }
    }
    update_post_meta($post_id, 'galleria', $gallery_ids);
    // Video presentazione
    if (!empty($_FILES['video']['tmp_name'])) {
        $video_id = media_handle_upload('video', $post_id);
        if (!is_wp_error($video_id)) {
            update_post_meta($post_id, 'video_presentazione', $video_id);
        }
    }
    // Foto punto ritrovo
    if (!empty($_FILES['foto_ritrovo']['tmp_name'])) {
        $ritrovo_id = media_handle_upload('foto_ritrovo', $post_id);
        if (!is_wp_error($ritrovo_id)) {
            update_post_meta($post_id, 'foto_ritrovo', $ritrovo_id);
        }
    }
    wp_send_json_success(['message' => $is_update ? __('Tour aggiornato con successo!', 'opencomune') : __('Tour creato con successo!', 'opencomune'), 'redirect' => site_url('/dashboard-guida/')]);
}

// AJAX: restituisce la thumbnail del post (foto principale)
add_action('wp_ajax_opencomune_get_post_thumbnail', function() {
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error();
    $thumb = get_the_post_thumbnail_url($post_id, 'large');
    wp_send_json_success(['thumbnail' => $thumb]);
});

// AJAX: restituisce le immagini della galleria
add_action('wp_ajax_opencomune_get_gallery_images', function() {
    $post_id = intval($_POST['post_id'] ?? 0);
    if (!$post_id) wp_send_json_error();
    $gallery_ids = get_post_meta($post_id, 'galleria', true);
    $images = [];
    if (is_array($gallery_ids)) {
        foreach ($gallery_ids as $gid) {
            $img = wp_get_attachment_image_url($gid, 'medium');
            if ($img) $images[] = ['id' => $gid, 'url' => $img];
        }
    }
    wp_send_json_success(['images' => $images]);
});

add_action('wp_ajax_opencomune_search_comuni', function() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    $results = [];
    if ($term && strlen($term) > 1) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT Descrizione FROM wpmyguide_comuni WHERE (DataFineVal IS NULL OR DataFineVal = '') AND Descrizione LIKE %s ORDER BY Descrizione ASC LIMIT 30", $like));
        foreach ($rows as $row) {
            $results[] = ['id' => $row->Descrizione, 'text' => $row->Descrizione];
        }
    }
    wp_send_json(['results' => $results]);
});
add_action('wp_ajax_nopriv_opencomune_search_comuni', function() {
    global $wpdb;
    $term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
    $results = [];
    if ($term && strlen($term) > 1) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $rows = $wpdb->get_results($wpdb->prepare("SELECT Descrizione FROM wpmyguide_comuni WHERE (DataFineVal IS NULL OR DataFineVal = '') AND Descrizione LIKE %s ORDER BY Descrizione ASC LIMIT 30", $like));
        foreach ($rows as $row) {
            $results[] = ['id' => $row->Descrizione, 'text' => $row->Descrizione];
        }
    }
    wp_send_json(['results' => $results]);
});

// === AJAX: Elimina tour ===
add_action('wp_ajax_opencomune_elimina_tour', function() {
    if (!is_user_logged_in() || !current_user_can('partner')) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze' || (int)$post->post_author !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $deleted = wp_delete_post($post_id, true);
    if ($deleted) {
        wp_send_json_success();
    } else {
        wp_send_json_error(['message' => __('Errore durante l\'eliminazione.', 'opencomune')]);
    }
});

// === AJAX: Sospendi tour (bozza) ===
add_action('wp_ajax_opencomune_sospendi_tour', function() {
    if (!is_user_logged_in() || !current_user_can('partner')) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze' || (int)$post->post_author !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $res = wp_update_post([
        'ID' => $post_id,
        'post_status' => 'draft',
    ], true);
    if (is_wp_error($res)) {
        wp_send_json_error(['message' => __('Errore durante la sospensione.', 'opencomune')]);
    } else {
        wp_send_json_success();
    }
});

// === AJAX: Pubblica tour ===
add_action('wp_ajax_opencomune_pubblica_tour', function() {
    if (!is_user_logged_in() || !current_user_can('partner')) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $post_id = intval($_POST['post_id'] ?? 0);
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze' || (int)$post->post_author !== get_current_user_id()) {
        wp_send_json_error(['message' => __('Non autorizzato.', 'opencomune')]);
    }
    $res = wp_update_post([
        'ID' => $post_id,
        'post_status' => 'publish',
    ], true);
    if (is_wp_error($res)) {
        wp_send_json_error(['message' => __('Errore durante la pubblicazione.', 'opencomune')]);
    } else {
        wp_send_json_success();
    }
});

// === SOTTOSCRIZIONI: Funzione centrale ===
function opencomune_handle_subscription($user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    
    // Check cache first
    $cache_key = 'opencomune_sub_' . $user_id;
    $cached = wp_cache_get($cache_key);
    if ($cached !== false) {
        return $cached;
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'sottoscrizioni';
    
    $sub = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} 
        WHERE user_id = %d 
        AND status = 'COMPLETED'
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY created_at DESC LIMIT 1",
        $user_id
    ));
    
    $result = [
        'active' => false,
        'expires_at' => null,
        'storico' => []
    ];
    
    if ($sub) {
        $result['active'] = true;
        $result['expires_at'] = $sub->expires_at;
        
        // Get payment history
        $result['storico'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE user_id = %d 
            ORDER BY created_at DESC",
            $user_id
        ));
    }
    
    // Cache for 1 hour
    wp_cache_set($cache_key, $result, '', HOUR_IN_SECONDS);
    
    return $result;
}

// === SOTTOSCRIZIONI: Endpoint AJAX per registrare pagamento PayPal ===
add_action('wp_ajax_opencomune_registra_pagamento', 'opencomune_registra_pagamento_callback');
function opencomune_registra_pagamento_callback() {
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Non autorizzato']);
    }
    $user_id = get_current_user_id();
    $order_id = sanitize_text_field($_POST['orderID'] ?? '');
    if (!$order_id) {
        wp_send_json_error(['message' => 'Order ID mancante']);
    }
    // Verifica ordine con PayPal SDK
    require_once __DIR__ . '/vendor/autoload.php';
    $clientId = 'sb'; // Sostituisci con il tuo clientId sandbox
    $clientSecret = 'sb'; // Sostituisci con il tuo clientSecret sandbox
    $environment = new PayPalCheckoutSdk\Core\SandboxEnvironment($clientId, $clientSecret);
    $client = new PayPalCheckoutSdk\Core\PayPalHttpClient($environment);
    $request = new PayPalCheckoutSdk\Orders\OrdersGetRequest($order_id);
    try {
        $response = $client->execute($request);
        $result = $response->result;
        if ($result->status !== 'COMPLETED') {
            wp_send_json_error(['message' => 'Pagamento non completato']);
        }
        $amount = $result->purchase_units[0]->amount->value;
        $currency = $result->purchase_units[0]->amount->currency_code;
        $txn_id = $result->id;
        $now = current_time('mysql');
        $expires = date('Y-m-d H:i:s', strtotime('+1 year'));
        global $wpdb;
        $table = $wpdb->prefix . 'sottoscrizioni';
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'paypal_txn_id' => $txn_id,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'COMPLETED',
            'created_at' => $now,
            'expires_at' => $expires,
            'raw_response' => wp_json_encode($result),
            'abbonamento_tipo' => 'base',
        ]);
        wp_send_json_success(['message' => 'Pagamento registrato', 'expires_at' => $expires]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Errore PayPal: ' . $e->getMessage()]);
    }
} 

function registra_menu_personalizzati() {
    register_nav_menus(array(
        'header' => __('Header'),
        'footer' => __('Footer'),
        'footer_1' => __('Footer Servizi', 'opencomune'),
    ));
}
add_action('after_setup_theme', 'registra_menu_personalizzati');

function opencomune_get_default_footer_nav_links() {
    $links = opencomune_get_default_header_nav_links();
    $links[] = [
        'label' => __('Registrazione partner', 'opencomune'),
        'url' => home_url('/registrazione-partner/'),
    ];
    $links[] = [
        'label' => __('Accedi ai servizi', 'opencomune'),
        'url' => home_url('/login/'),
    ];

    return $links;
}

function opencomune_footer_nav_fallback($args) {
    $menu_class = 'oc-public-footer__nav-list';
    if (is_array($args) && !empty($args['menu_class'])) {
        $menu_class = $args['menu_class'];
    }

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach (opencomune_get_default_footer_nav_links() as $link) {
        echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
    }
    echo '</ul>';
}

function opencomune_render_footer_nav_menu() {
    wp_nav_menu([
        'theme_location' => 'footer',
        'container' => false,
        'menu_class' => 'oc-public-footer__nav-list',
        'fallback_cb' => 'opencomune_footer_nav_fallback',
    ]);
}

function opencomune_get_default_footer_servizi_links() {
    $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
    if ($privacy_url === '') {
        $privacy_url = home_url('/privacy-policy/');
    }

    return [
        [
            'label' => __('Registrazione partner', 'opencomune'),
            'url' => home_url('/registrazione-partner/'),
        ],
        [
            'label' => __('Accedi ai servizi', 'opencomune'),
            'url' => home_url('/login/'),
        ],
        [
            'label' => __('Privacy policy', 'opencomune'),
            'url' => $privacy_url,
        ],
        [
            'label' => __('Termini e condizioni', 'opencomune'),
            'url' => home_url('/termini-e-condizioni/'),
        ],
        [
            'label' => __('Cookie policy', 'opencomune'),
            'url' => home_url('/cookie-policy/'),
        ],
    ];
}

function opencomune_footer_servizi_nav_fallback($args) {
    $menu_class = 'oc-public-footer__nav-list';
    if (is_array($args) && !empty($args['menu_class'])) {
        $menu_class = $args['menu_class'];
    }

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach (opencomune_get_default_footer_servizi_links() as $link) {
        echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
    }
    echo '</ul>';
}

function opencomune_render_footer_servizi_nav_menu() {
    wp_nav_menu([
        'theme_location' => 'footer_1',
        'container' => false,
        'menu_class' => 'oc-public-footer__nav-list',
        'fallback_cb' => 'opencomune_footer_servizi_nav_fallback',
    ]);
}


require_once get_template_directory() . '/functions-calendario.php';

// Helper function per ottenere informazioni ufficio turistico
function opencomune_get_ufficio_info() {
    return [
        'nome_comune' => get_option('opencomune_nome_comune', ''),
        'email' => get_option('opencomune_email_ufficio', ''),
        'telefono' => get_option('opencomune_telefono_ufficio', ''),
        'indirizzo' => get_option('opencomune_indirizzo_ufficio', ''),
        'orari' => get_option('opencomune_orari_apertura', ''),
    ];
}

// Menu di fallback header (desktop e mobile: stesse voci, classe da wp_nav_menu)
function opencomune_get_default_header_nav_links() {
    return [
        ['label' => __('Home', 'opencomune'), 'url' => home_url('/')],
        ['label' => __('Esperienze', 'opencomune'), 'url' => home_url('/esperienze/')],
        ['label' => __('Mappa', 'opencomune'), 'url' => home_url('/mappa/')],
        ['label' => __('Planner', 'opencomune'), 'url' => home_url('/planner/')],
        ['label' => __('Contatti', 'opencomune'), 'url' => home_url('/contatti/')],
    ];
}

function opencomune_header_nav_fallback($args) {
    $menu_class = 'site-nav__list';
    if (is_array($args) && !empty($args['menu_class'])) {
        $menu_class = $args['menu_class'];
    }

    echo '<ul class="' . esc_attr($menu_class) . '">';
    foreach (opencomune_get_default_header_nav_links() as $link) {
        echo '<li><a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a></li>';
    }
    echo '</ul>';
}

function opencomune_render_header_nav_menu($menu_class) {
    wp_nav_menu([
        'theme_location' => 'header',
        'container' => false,
        'menu_class' => $menu_class,
        'fallback_cb' => 'opencomune_header_nav_fallback',
    ]);
}

// Menu di fallback per enti/comuni (compatibilità)
function opencomune_fallback_menu() {
    opencomune_header_nav_fallback(['menu_class' => 'site-nav__list']);
}

function opencomune_mobile_fallback_menu() {
    opencomune_header_nav_fallback(['menu_class' => 'site-nav__overlay-list']);
}

// === AJAX: Restituisce le categorie delle esperienze ===
add_action('wp_ajax_opencomune_get_categorie_esperienze', 'opencomune_get_categorie_esperienze_callback');
add_action('wp_ajax_nopriv_opencomune_get_categorie_esperienze', 'opencomune_get_categorie_esperienze_callback');
function opencomune_get_categorie_esperienze_callback() {
    $categorie = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ]);
    
    if (is_wp_error($categorie)) {
        wp_send_json_error(['message' => 'Errore nel caricamento delle categorie']);
    }
    
    $data = array_map(function ($cat) {
        $term_id = (int) $cat->term_id;
        $image = opencomune_esperienza_category_get_image_url($term_id);

        return [
            'id' => $term_id,
            'name' => opencomune_sanitize_taxonomy_display_name($cat->name),
            'slug' => $cat->slug,
            'count' => $cat->count,
            'image' => $image,
        ];
    }, $categorie);
    
    wp_send_json_success($data);
}

// === AJAX: Statistiche Dashboard ===
add_action('wp_ajax_opencomune_get_dashboard_stats', 'opencomune_get_dashboard_stats_callback');
function opencomune_get_dashboard_stats_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
    }
    
    if (opencomune_user_is_partner_only()) {
        $user_id = get_current_user_id();
        
        // Query per contare le esperienze dell'utente corrente
        $args = [
            'post_type' => 'esperienze',
            'post_status' => ['publish', 'draft', 'pending'],
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids'
        ];
        
        $user_esperienze = get_posts($args);
        $pubblicate = 0;
        $bozza = 0;
        $pending = 0;
        
        foreach ($user_esperienze as $post_id) {
            $status = get_post_status($post_id);
            switch ($status) {
                case 'publish':
                    $pubblicate++;
                    break;
                case 'draft':
                    $bozza++;
                    break;
                case 'pending':
                    $pending++;
                    break;
            }
        }
    } else {
        $total_esperienze = wp_count_posts('esperienze');
        $pubblicate = $total_esperienze->publish;
        $bozza = $total_esperienze->draft;
        $pending = $total_esperienze->pending;
    }
    
    // Prenotazioni di oggi (placeholder - da implementare con sistema prenotazioni)
    $prenotazioni_oggi = 0;

    $payload = [
        'total_esperienze' => $pubblicate + $bozza + $pending,
        'pubblicate' => $pubblicate,
        'bozza' => $bozza,
        'pending' => $pending,
        'prenotazioni_oggi' => $prenotazioni_oggi,
    ];

    if (opencomune_user_can_manage_all_esperienze() && function_exists('opencomune_get_dashboard_partner_counts')) {
        $partner_counts = opencomune_get_dashboard_partner_counts();
        $payload['partners_active'] = $partner_counts['active'];
        $payload['partners_pending'] = $partner_counts['pending'];
    }

    wp_send_json_success($payload);
}

// === AJAX: Lista Esperienze Dashboard ===
add_action('wp_ajax_opencomune_get_esperienze_dashboard', 'opencomune_get_esperienze_dashboard_callback');
function opencomune_get_esperienze_dashboard_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
    }

    if (isset($_POST['draw'])) {
        opencomune_esperienze_dashboard_datatables_response();
        return;
    }

    opencomune_esperienze_dashboard_legacy_response();
}

add_action('wp_ajax_opencomune_get_esperienza_translations_form', 'opencomune_get_esperienza_translations_form_callback');
function opencomune_get_esperienza_translations_form_callback() {
    check_ajax_referer('opencomune_dashboard_translations', 'nonce');

    $post_id = (int) ($_GET['post_id'] ?? 0);
    if (!opencomune_user_can_edit_esperienza($post_id)) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    ob_start();
    opencomune_render_esperienza_english_translation_fields($post_id);
    $html = ob_get_clean();

    wp_send_json_success([
        'html' => $html,
        'title' => get_the_title($post_id),
        'has_en' => opencomune_esperienza_has_english_translation($post_id),
        'ai_configured' => opencomune_partner_ai_is_configured(get_current_user_id()),
    ]);
}

add_action('wp_ajax_opencomune_save_esperienza_translations_dashboard', 'opencomune_save_esperienza_translations_dashboard_callback');
function opencomune_save_esperienza_translations_dashboard_callback() {
    check_ajax_referer('opencomune_dashboard_translations', 'nonce');

    $post_id = (int) ($_POST['post_id'] ?? 0);
    if (!opencomune_user_can_edit_esperienza($post_id)) {
        wp_send_json_error(['message' => __('Accesso negato', 'opencomune')]);
    }

    opencomune_save_esperienza_translations_from_post($post_id, $_POST);

    wp_send_json_success([
        'has_en' => opencomune_esperienza_has_english_translation($post_id),
        'message' => __('Traduzioni salvate.', 'opencomune'),
    ]);
}

// === AJAX: Cambia Stato Esperienza ===
add_action('wp_ajax_opencomune_toggle_esperienza_status', 'opencomune_toggle_esperienza_status_callback');
function opencomune_toggle_esperienza_status_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
    }
    
    $id = intval($_POST['id'] ?? 0);
    $status = sanitize_text_field($_POST['status'] ?? '');
    
    if (!$id) {
        wp_send_json_error(['message' => 'ID esperienza non valido']);
    }
    
    if (!in_array($status, ['publish', 'draft'])) {
        wp_send_json_error(['message' => 'Stato non valido']);
    }
    
    if (!opencomune_user_can_edit_esperienza($id)) {
        wp_send_json_error(['message' => 'Non hai i permessi per modificare questa esperienza']);
    }
    
    $result = wp_update_post([
        'ID' => $id,
        'post_status' => $status
    ]);
    
    if ($result && !is_wp_error($result)) {
        $status_text = $status === 'publish' ? 'pubblicata' : 'nascosta';
        wp_send_json_success(['message' => "Esperienza {$status_text} con successo"]);
    } else {
        wp_send_json_error(['message' => 'Errore durante l\'aggiornamento']);
    }
}

// === AJAX: Add Prenotazione ===
add_action('wp_ajax_opencomune_add_prenotazione', 'opencomune_add_prenotazione_callback');
add_action('wp_ajax_nopriv_opencomune_add_prenotazione', 'opencomune_add_prenotazione_callback');

// === AJAX: Partner Management ===
add_action('wp_ajax_opencomune_submit_partner_registration', 'opencomune_submit_partner_registration_callback');
add_action('wp_ajax_nopriv_opencomune_submit_partner_registration', 'opencomune_submit_partner_registration_callback');

add_action('wp_ajax_opencomune_get_partners', 'opencomune_get_partners_callback');
add_action('wp_ajax_opencomune_get_partner_details', 'opencomune_get_partner_details_callback');
add_action('wp_ajax_opencomune_update_partner', 'opencomune_update_partner_callback');
add_action('wp_ajax_opencomune_delete_partner', 'opencomune_delete_partner_callback');
add_action('wp_ajax_opencomune_bulk_delete_partners', 'opencomune_bulk_delete_partners_callback');
add_action('wp_ajax_opencomune_verify_partner_code', 'opencomune_verify_partner_code_callback');
add_action('wp_ajax_nopriv_opencomune_verify_partner_code', 'opencomune_verify_partner_code_callback');

function opencomune_generate_partner_confirmation_code() {
    return str_pad((string) wp_rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

function opencomune_is_partner_email_verified($user_id) {
    if (get_user_meta($user_id, 'partner_email_verified', true)) {
        return true;
    }
    if (get_user_meta($user_id, 'partner_approved', true)) {
        return true;
    }
    return false;
}

function opencomune_activate_partner_account($user_id) {
    update_user_meta($user_id, 'partner_email_verified', true);
    update_user_meta($user_id, 'partner_approved', true);
    delete_user_meta($user_id, 'partner_confirmation_code');

    $partner_id = (int) get_user_meta($user_id, 'partner_id', true);
    if ($partner_id) {
        global $wpdb;
        $table_partners = $wpdb->prefix . 'tour_partners';
        $wpdb->update(
            $table_partners,
            [
                'status' => 'approved',
                'data_approvazione' => current_time('mysql'),
            ],
            ['id' => $partner_id]
        );
        opencomune_sync_partner_cpt($partner_id);
    }
}

function opencomune_send_partner_confirmation_email($email, $nome_referente, $nome_attivita, $code) {
    if (!function_exists('opencomune_send_html_email')) {
        return false;
    }

    $login_url = home_url('/login/?primo_accesso=1');
    $subject = sprintf(__('Codice di conferma account partner - %s', 'opencomune'), get_bloginfo('name'));

    $body = opencomune_html_email_paragraph(
        sprintf(
            /* translators: %s: contact name */
            __('Gentile %s,', 'opencomune'),
            esc_html($nome_referente)
        )
    );
    $body .= opencomune_html_email_paragraph(
        sprintf(
            __('Grazie per esserti registrato come partner sulla %s.', 'opencomune'),
            esc_html(get_bloginfo('name'))
        )
    );
    $body .= opencomune_html_email_paragraph(
        sprintf(
            __('Per attivare l\'account dell\'attività «%s», al primo accesso inserisci il codice qui sotto insieme all\'email e alla password che hai ricevuto nell\'altra email.', 'opencomune'),
            esc_html($nome_attivita)
        )
    );
    $body .= opencomune_html_email_details_table([
        ['label' => __('Codice di conferma (6 cifre)', 'opencomune'), 'value' => $code],
    ]);
    $body .= opencomune_html_email_paragraph(
        __('Vai al login, clicca su «Primo accesso?» e completa la verifica con email, password e codice.', 'opencomune')
    );

    return opencomune_send_html_email($email, $subject, $body, [
        'title' => __('Codice primo accesso partner', 'opencomune'),
        'preheader' => __('Usa questo codice al primo accesso.', 'opencomune'),
        'cta_url' => $login_url,
        'cta_label' => __('Accedi ora', 'opencomune'),
    ]);
}

function opencomune_send_partner_registration_credentials_email($email, $nome_referente, $nome_attivita, $password) {
    if (!function_exists('opencomune_send_html_email')) {
        return false;
    }

    $login_url = home_url('/login/');
    $subject = sprintf(__('Credenziali di accesso partner - %s', 'opencomune'), get_bloginfo('name'));

    $body = opencomune_html_email_paragraph(
        sprintf(
            __('Gentile %s,', 'opencomune'),
            esc_html($nome_referente)
        )
    );
    $body .= opencomune_html_email_paragraph(
        sprintf(
            __('La richiesta di registrazione per «%s» è stata ricevuta. Ecco le credenziali generate automaticamente per accedere al portale partner.', 'opencomune'),
            esc_html($nome_attivita)
        )
    );
    $body .= opencomune_html_email_details_table([
        ['label' => __('Email di accesso', 'opencomune'), 'value' => $email],
        ['label' => __('Password', 'opencomune'), 'value' => $password],
    ]);
    $body .= opencomune_html_email_paragraph(
        __('Riceverai un\'altra email con il codice numerico da usare al primo accesso. Conserva entrambe le email in un luogo sicuro.', 'opencomune')
    );

    return opencomune_send_html_email($email, $subject, $body, [
        'title' => __('Credenziali partner', 'opencomune'),
        'preheader' => __('Password generata per il tuo account partner.', 'opencomune'),
        'cta_url' => $login_url,
        'cta_label' => __('Vai al login', 'opencomune'),
    ]);
}

/**
 * Destinatari admin per notifiche registrazione partner.
 *
 * @return string[]
 */
function opencomune_get_partner_registration_admin_recipients() {
    if (function_exists('opencomune_get_contatti_admin_recipients')) {
        $recipients = opencomune_get_contatti_admin_recipients();
        if ($recipients !== []) {
            return $recipients;
        }
    }

    $admin_email = get_option('admin_email');
    if ($admin_email && is_email($admin_email)) {
        return [$admin_email];
    }

    return [];
}

/**
 * @param array<string, mixed> $data
 */
function opencomune_send_partner_registration_admin_email($partner_id, array $data) {
    if (!function_exists('opencomune_send_html_email')) {
        return false;
    }

    $partner_id = (int) $partner_id;
    if ($partner_id <= 0) {
        return false;
    }

    $recipients = opencomune_get_partner_registration_admin_recipients();
    if ($recipients === []) {
        return false;
    }

    $tipologie = function_exists('opencomune_partner_get_tipologie')
        ? opencomune_partner_get_tipologie()
        : [];
    $tipologia_key = sanitize_key((string) ($data['tipologia_attivita'] ?? ''));
    $tipologia_label = $tipologie[$tipologia_key] ?? ($data['tipologia_attivita'] ?? '');

    $nome_attivita = sanitize_text_field((string) ($data['nome_attivita'] ?? ''));
    $subject = sprintf(
        '[%s] %s',
        get_bloginfo('name'),
        sprintf(__('Nuova registrazione partner: %s', 'opencomune'), $nome_attivita)
    );

    $detail_url = add_query_arg('id', $partner_id, home_url('/dettaglio-partner/'));
    $indirizzo_line = trim(
        sanitize_text_field((string) ($data['indirizzo'] ?? ''))
        . ', '
        . sanitize_text_field((string) ($data['cap'] ?? ''))
        . ' '
        . sanitize_text_field((string) ($data['comune'] ?? ''))
    );

    $rows = [
        ['label' => __('ID richiesta', 'opencomune'), 'value' => (string) $partner_id],
        ['label' => __('Attività', 'opencomune'), 'value' => $nome_attivita],
        ['label' => __('Tipologia', 'opencomune'), 'value' => $tipologia_label],
        ['label' => __('Partita IVA / CF', 'opencomune'), 'value' => sanitize_text_field((string) ($data['partita_iva'] ?? ''))],
    ];

    $numero_rea = sanitize_text_field((string) ($data['numero_rea'] ?? ''));
    if ($numero_rea !== '') {
        $rows[] = ['label' => __('REA', 'opencomune'), 'value' => $numero_rea];
    }

    $rows[] = ['label' => __('Referente', 'opencomune'), 'value' => sanitize_text_field((string) ($data['nome_referente'] ?? ''))];
    $rows[] = ['label' => __('Ruolo', 'opencomune'), 'value' => sanitize_text_field((string) ($data['ruolo_referente'] ?? ''))];
    $rows[] = ['label' => __('Telefono', 'opencomune'), 'value' => sanitize_text_field((string) ($data['telefono_attivita'] ?? ''))];
    $rows[] = ['label' => __('Email', 'opencomune'), 'value' => sanitize_email((string) ($data['email_ufficiale'] ?? ''))];
    $rows[] = ['label' => __('Indirizzo', 'opencomune'), 'value' => $indirizzo_line];

    $area = sanitize_text_field((string) ($data['area_quartiere'] ?? ''));
    if ($area !== '') {
        $rows[] = ['label' => __('Area / quartiere', 'opencomune'), 'value' => $area];
    }

    $descrizione = sanitize_textarea_field((string) ($data['descrizione_breve'] ?? ''));
    if ($descrizione !== '') {
        $rows[] = ['label' => __('Descrizione breve', 'opencomune'), 'value' => $descrizione];
    }

    $rows[] = [
        'label' => __('Data richiesta', 'opencomune'),
        'value' => wp_date('d/m/Y H:i'),
    ];

    $body = opencomune_html_email_paragraph(
        __('È arrivata una nuova richiesta di registrazione partner. Lo stato è «In attesa verifica»: puoi approvarla o rifiutarla dalla gestione partner.', 'opencomune')
    );
    $body .= opencomune_html_email_details_table($rows);

    $email_args = [
        'title' => __('Nuova registrazione partner', 'opencomune'),
        'preheader' => $nome_attivita,
        'cta_url' => $detail_url,
        'cta_label' => __('Apri dettaglio partner', 'opencomune'),
        'reply_to' => sanitize_email((string) ($data['email_ufficiale'] ?? '')),
    ];

    $sent_any = false;
    foreach ($recipients as $recipient) {
        if (!is_email($recipient)) {
            continue;
        }
        if (opencomune_send_html_email($recipient, $subject, $body, $email_args)) {
            $sent_any = true;
        }
    }

    return $sent_any;
}

function opencomune_geocode_italian_address_line($address_line) {
    $address_line = trim((string) $address_line);
    if ($address_line === '') {
        return null;
    }

    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key === '') {
        return null;
    }

    $url = add_query_arg(
        [
            'address' => $address_line,
            'key' => $api_key,
            'components' => 'country:IT',
        ],
        'https://maps.googleapis.com/maps/api/geocode/json'
    );

    $response = wp_remote_get($url, ['timeout' => 15]);
    if (is_wp_error($response)) {
        return null;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($body['results'][0]['geometry']['location'])) {
        return null;
    }

    $location = $body['results'][0]['geometry']['location'];

    return [
        'lat' => (float) $location['lat'],
        'lng' => (float) $location['lng'],
    ];
}

function opencomune_verify_partner_first_access($login, $password, $code) {
    $user = wp_authenticate($login, $password);

    if (is_wp_error($user)) {
        return new WP_Error('invalid_credentials', 'Credenziali non valide.');
    }

    if (!in_array('partner', $user->roles, true)) {
        return new WP_Error('not_partner', 'Questo account non e\' un partner.');
    }

    if (opencomune_is_partner_email_verified($user->ID)) {
        return new WP_Error('already_verified', 'Account gia\' attivato. Puoi accedere normalmente.');
    }

    $stored_code = get_user_meta($user->ID, 'partner_confirmation_code', true);
    if (!$stored_code || !hash_equals((string) $stored_code, sanitize_text_field($code))) {
        return new WP_Error('invalid_code', 'Codice di conferma non valido.');
    }

    opencomune_activate_partner_account($user->ID);

    return $user;
}

function opencomune_verify_partner_code_callback() {
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'opencomune_partner_verify')) {
        wp_send_json_error(['message' => 'Sessione non valida. Ricarica la pagina e riprova.']);
        return;
    }

    if (function_exists('opencomune_verify_turnstile_response')) {
        $turnstile_token = sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'] ?? ''));
        $turnstile_check = opencomune_verify_turnstile_response($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            wp_send_json_error(['message' => $turnstile_check->get_error_message()]);
            return;
        }
    }

    $login = sanitize_text_field($_POST['log'] ?? '');
    $password = $_POST['pwd'] ?? '';
    $code = sanitize_text_field($_POST['confirmation_code'] ?? '');

    if ($login === '' || $password === '' || $code === '') {
        wp_send_json_error(['message' => 'Compila tutti i campi obbligatori.']);
        return;
    }

    $result = opencomune_verify_partner_first_access($login, $password, $code);

    if (is_wp_error($result)) {
        wp_send_json_error(['message' => $result->get_error_message()]);
        return;
    }

    wp_set_current_user($result->ID);
    wp_set_auth_cookie($result->ID, false);

    wp_send_json_success([
        'message' => 'Account attivato con successo.',
        'redirect' => opencomune_get_dashboard_url(),
    ]);
}

// === Hook per controllare se il partner ha verificato l'email ===
function opencomune_get_partner_portal_page_slugs() {
    return [
        'dashboard-partner',
        'nuova-esperienza-partner',
        'modifica-esperienza-partner',
        'profilo-partner',
        'profilo-partner-pagamenti',
        'profilo-partner-ai',
        'prenotazione-partner',
    ];
}

function opencomune_is_partner_dashboard_page() {
    if (!is_user_logged_in()
        || !current_user_can('partner')
        || current_user_can('manage_options')) {
        return false;
    }

    return is_page(opencomune_get_partner_portal_page_slugs());
}

function opencomune_get_partner_welcome_name($user = null) {
    if (!$user instanceof WP_User) {
        $user = wp_get_current_user();
    }

    if (!$user || !$user->exists()) {
        return '';
    }

    $name = trim($user->first_name);
    if ($name === '') {
        $name = trim($user->display_name);
    }

    return $name;
}

add_action('wp_login', 'opencomune_check_partner_email_verification', 10, 2);
function opencomune_check_partner_email_verification($user_login, $user) {
    if (in_array('partner', $user->roles, true) && !opencomune_is_partner_email_verified($user->ID)) {
        wp_logout();
        wp_redirect(home_url('/login/?partner_verify=1'));
        exit;
    }
}

// === Hook aggiuntivo per controllare l'accesso al dashboard ===
add_action('admin_init', 'opencomune_check_partner_dashboard_access');
function opencomune_check_partner_dashboard_access() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('partner', $user->roles)) {
            if (!opencomune_is_partner_email_verified($user->ID)) {
                wp_logout();
                wp_redirect(home_url('/login/?partner_verify=1'));
                exit;
            } else {
                // Se il partner è approvato, reindirizza alla dashboard partner solo se sta cercando di accedere al dashboard admin
                if (is_admin() && !wp_doing_ajax()) {
                    wp_redirect(opencomune_get_dashboard_url());
                    exit;
                }
            }
        }
    }
}

// === Hook per controllare l'accesso alle pagine frontend ===
add_action('template_redirect', 'opencomune_check_partner_frontend_access');
function opencomune_check_partner_frontend_access() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('partner', $user->roles)) {
            if (!opencomune_is_partner_email_verified($user->ID)) {
                wp_logout();
                wp_redirect(home_url('/login/?partner_verify=1'));
                exit;
            }
        }
    }
}

// === Hook per nascondere la barra admin per i partner ===
add_action('admin_init', 'opencomune_hide_admin_bar_for_partners');
function opencomune_hide_admin_bar_for_partners() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('partner', $user->roles)) {
            // Nasconde la barra admin per i partner
            show_admin_bar(false);
        }
    }
}

// === Hook per nascondere la barra admin nel frontend per i partner ===
add_action('wp_loaded', 'opencomune_hide_admin_bar_frontend_for_partners');
function opencomune_hide_admin_bar_frontend_for_partners() {
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        if (in_array('partner', $user->roles)) {
            // Nasconde la barra admin nel frontend per i partner
            show_admin_bar(false);
        }
    }
}

// === Hook per aggiungere permessi di upload ai partner ===
add_action('init', 'opencomune_add_partner_upload_capabilities');
function opencomune_add_partner_upload_capabilities() {
    $role = get_role('partner');
    if ($role) {
        // Aggiungi permessi per upload di media
        $role->add_cap('upload_files');
        $role->add_cap('edit_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('publish_posts');
        $role->add_cap('delete_posts');
        $role->add_cap('delete_published_posts');
        $role->add_cap('edit_others_posts');
        $role->add_cap('delete_others_posts');
        $role->add_cap('read_private_posts');
        $role->add_cap('edit_private_posts');
        $role->add_cap('delete_private_posts');
        
        // Aggiungi permessi per il custom post type 'esperienze'
        $role->add_cap('edit_esperienze');
        $role->add_cap('edit_published_esperienze');
        $role->add_cap('publish_esperienze');
        $role->add_cap('delete_esperienze');
        $role->add_cap('delete_published_esperienze');
        $role->add_cap('edit_others_esperienze');
        //$role->add_cap('delete_others_esperienze');
        $role->add_cap('read_private_esperienze');
        $role->add_cap('edit_private_esperienze');
        $role->add_cap('delete_private_esperienze');
        
        // Aggiungi permessi per le tassonomie
        //$role->add_cap('manage_categories');
        //$role->add_cap('edit_categories');
        //$role->add_cap('delete_categories');
        $role->add_cap('assign_categories');
    }
}

// === Hook per permettere ai partner di accedere alla libreria media ===
add_action('wp_ajax_query-attachments', 'opencomune_allow_partner_media_access', 1);
function opencomune_allow_partner_media_access() {
    if (is_user_logged_in() && current_user_can('partner')) {
        // Permetti ai partner di accedere alla libreria media
        return;
    }
}

// === Hook per permettere ai partner di caricare file ===
add_action('wp_ajax_upload-attachment', 'opencomune_allow_partner_upload', 1);
function opencomune_allow_partner_upload() {
    if (is_user_logged_in() && current_user_can('partner')) {
        // Permetti ai partner di caricare file
        return;
    }
}

// === Hook per permettere ai partner di accedere ai media ===
add_filter('ajax_query_attachments_args', 'opencomune_partner_media_query_args');
function opencomune_partner_media_query_args($query) {
    if (is_user_logged_in() && current_user_can('partner')) {
        // I partner possono vedere solo i loro file
        $query['author'] = get_current_user_id();
    }
    return $query;
}

// === Hook per limitare l'accesso alle esperienze per i partner ===
add_action('pre_get_posts', 'opencomune_limit_partner_esperienze');
function opencomune_limit_partner_esperienze($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if (!is_user_logged_in() || !current_user_can('partner') || current_user_can('manage_options')) {
        return;
    }

    $post_type = $query->get('post_type');
    if ($post_type === 'esperienze' || (is_array($post_type) && in_array('esperienze', $post_type, true))) {
        $query->set('author', get_current_user_id());
    }
}

// === Hook per limitare l'accesso ai media per i partner ===
add_action('pre_get_posts', 'opencomune_limit_partner_media');
function opencomune_limit_partner_media($query) {
    if (is_user_logged_in() && opencomune_user_is_partner_only()) {
        // I partner possono vedere solo i loro media
        if ($query->is_main_query() && is_admin() && !is_page() && $query->get('post_type') == 'attachment') {
            $query->set('author', get_current_user_id());
        }
    }
}

// === Hook per limitare l'accesso alle esperienze nel frontend ===
add_action('pre_get_posts', 'opencomune_limit_partner_esperienze_frontend');
function opencomune_limit_partner_esperienze_frontend($query) {
    if (is_user_logged_in() && opencomune_user_is_partner_only()) {
        // I partner possono vedere solo le loro esperienze nel frontend (esclusi archivi autore pubblici).
        if ($query->is_main_query() && !is_admin() && !is_page() && !$query->is_author()) {
            $query->set('author', get_current_user_id());
        }
    }
}

add_action('pre_get_posts', 'opencomune_author_archive_esperienze');
function opencomune_author_archive_esperienze($query) {
    if (is_admin() || !$query->is_main_query() || !$query->is_author()) {
        return;
    }

    $query->set('post_type', 'esperienze');
    $query->set('post_status', 'publish');
    $query->set('orderby', 'date');
    $query->set('order', 'DESC');
}

// === Hook per limitare l'accesso alle esperienze specifiche ===
add_action('wp_ajax_opencomune_get_esperienze_dashboard', 'opencomune_limit_partner_esperienze_ajax');
function opencomune_limit_partner_esperienze_ajax() {
    if (is_user_logged_in() && opencomune_user_is_partner_only()) {
        // I partner possono vedere solo le loro esperienze nelle chiamate AJAX
        add_filter('posts_where', 'opencomune_partner_esperienze_where');
    }
}

// === Filtro per limitare le esperienze nelle query AJAX ===
function opencomune_partner_esperienze_where($where) {
    if (is_user_logged_in() && opencomune_user_is_partner_only()) {
        global $wpdb;
        $user_id = get_current_user_id();
        $where .= " AND {$wpdb->posts}.post_author = {$user_id}";
    }
    return $where;
}


// === Hook per permettere ai partner di accedere alla libreria media nel frontend ===
add_action('wp_ajax_nopriv_query-attachments', 'opencomune_allow_partner_media_access_frontend');
function opencomune_allow_partner_media_access_frontend() {
    if (is_user_logged_in() && current_user_can('partner')) {
        // Permetti ai partner di accedere alla libreria media nel frontend
        return;
    }
}

// === Hook per permettere ai partner di caricare file nel frontend ===
add_action('wp_ajax_nopriv_upload-attachment', 'opencomune_allow_partner_upload_frontend');
function opencomune_allow_partner_upload_frontend() {
    if (is_user_logged_in() && current_user_can('partner')) {
        // Permetti ai partner di caricare file nel frontend
        return;
    }
}

// === AJAX: Submit Partner Registration ===
function opencomune_submit_partner_registration_callback() {
    // Debug logging
    error_log('OPENCOMUNE PARTNER REGISTRATION: Handler called');
    error_log('OPENCOMUNE PARTNER REGISTRATION: POST data: ' . print_r($_POST, true));
    
    // Verifica nonce per sicurezza
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'opencomune_nonce')) {
        error_log('OPENCOMUNE PARTNER REGISTRATION: Nonce verification failed');
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }

    if (function_exists('opencomune_verify_turnstile_response')) {
        $turnstile_token = sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'] ?? ''));
        $turnstile_check = opencomune_verify_turnstile_response($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            wp_send_json_error(['message' => $turnstile_check->get_error_message()]);
            return;
        }
    }
    
    // Crea tabella se non esiste
    global $wpdb;
    $table_partners = $wpdb->prefix . 'tour_partners';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_partners (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        nome_attivita varchar(255) NOT NULL,
        tipologia_attivita varchar(100) NOT NULL,
        partita_iva varchar(50) NOT NULL,
        numero_rea varchar(50),
        descrizione_breve text,
        descrizione_estesa text,
        logo_url varchar(500),
        indirizzo varchar(255) NOT NULL,
        cap varchar(10) NOT NULL,
        comune varchar(100) NOT NULL,
        area_quartiere varchar(100),
        latitudine decimal(10,8),
        longitudine decimal(11,8),
        nome_referente varchar(255) NOT NULL,
        ruolo_referente varchar(100) NOT NULL,
        telefono_attivita varchar(50) NOT NULL,
        email_ufficiale varchar(255) NOT NULL,
        sito_web varchar(500),
        social_media varchar(500),
        email_login varchar(255) NOT NULL,
        password_hash varchar(255) NOT NULL,
        termini_condizioni tinyint(1) DEFAULT 0,
        privacy_gdpr tinyint(1) DEFAULT 0,
        pubblicazione_dati tinyint(1) DEFAULT 0,
        dichiarazione_verita tinyint(1) DEFAULT 0,
        status varchar(20) DEFAULT 'pending',
        data_registrazione datetime DEFAULT CURRENT_TIMESTAMP,
        data_approvazione datetime NULL,
        note_admin text,
        wp_user_id bigint(20) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY email_login (email_login),
        KEY status (status),
        KEY comune (comune)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Validazione dati
    $required_fields = [
        'nome_attivita', 'tipologia_attivita', 'partita_iva', 'descrizione_breve',
        'indirizzo', 'cap', 'comune', 'nome_referente', 'ruolo_referente',
        'telefono_attivita', 'email_ufficiale',
        'termini_condizioni', 'privacy_gdpr', 'pubblicazione_dati', 'dichiarazione_verita',
    ];

    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            wp_send_json_error(['message' => "Campo obbligatorio mancante: $field"]);
            return;
        }
    }

    $email_login = sanitize_email(wp_unslash($_POST['email_ufficiale'] ?? ''));
    if ($email_login === '' || !is_email($email_login)) {
        wp_send_json_error(['message' => 'Email ufficiale non valida']);
        return;
    }

    if (email_exists($email_login) || username_exists($email_login)) {
        wp_send_json_error(['message' => 'Email già registrata']);
        return;
    }

    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_partners WHERE email_login = %s OR email_ufficiale = %s",
        $email_login,
        $email_login
    ));

    if ($existing) {
        wp_send_json_error(['message' => 'Email già registrata']);
        return;
    }

    $password = wp_generate_password(16, true, true);

    $latitudine = isset($_POST['latitudine']) ? (float) $_POST['latitudine'] : 0.0;
    $longitudine = isset($_POST['longitudine']) ? (float) $_POST['longitudine'] : 0.0;
    if ($latitudine === 0.0 && $longitudine === 0.0) {
        $geo_query = trim(
            sanitize_text_field(wp_unslash($_POST['indirizzo'] ?? ''))
            . ', '
            . sanitize_text_field(wp_unslash($_POST['cap'] ?? ''))
            . ' '
            . sanitize_text_field(wp_unslash($_POST['comune'] ?? ''))
            . ', Italia'
        );
        $geo = opencomune_geocode_italian_address_line($geo_query);
        if ($geo) {
            $latitudine = (float) $geo['lat'];
            $longitudine = (float) $geo['lng'];
        }
    }
    
    // Gestione upload logo
    $logo_url = '';
    $logo_attachment_id = null;
    if (!empty($_FILES['logo_file']['name'])) {
        $upload = wp_handle_upload($_FILES['logo_file'], ['test_form' => false]);
        if ($upload && !isset($upload['error'])) {
            $logo_url = $upload['url'];
            
            // Crea attachment per l'immagine
            $attachment = [
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name($_FILES['logo_file']['name']),
                'post_content' => '',
                'post_status' => 'inherit'
            ];
            
            $logo_attachment_id = wp_insert_attachment($attachment, $upload['file']);
            if ($logo_attachment_id) {
                // Genera metadati per l'immagine
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($logo_attachment_id, $upload['file']);
                wp_update_attachment_metadata($logo_attachment_id, $attach_data);
            }
        }
    }
    
    // Inserisci dati nel database
    $result = $wpdb->insert($table_partners, [
        'nome_attivita' => sanitize_text_field($_POST['nome_attivita']),
        'tipologia_attivita' => sanitize_text_field($_POST['tipologia_attivita']),
        'partita_iva' => sanitize_text_field($_POST['partita_iva']),
        'numero_rea' => sanitize_text_field($_POST['numero_rea']),
        'descrizione_breve' => sanitize_textarea_field($_POST['descrizione_breve']),
        'descrizione_estesa' => sanitize_textarea_field(wp_unslash($_POST['descrizione_estesa'] ?? '')),
        'logo_url' => $logo_url,
        'indirizzo' => sanitize_text_field(wp_unslash($_POST['indirizzo'] ?? '')),
        'cap' => sanitize_text_field(wp_unslash($_POST['cap'] ?? '')),
        'comune' => sanitize_text_field(wp_unslash($_POST['comune'] ?? '')),
        'area_quartiere' => sanitize_text_field(wp_unslash($_POST['area_quartiere'] ?? '')),
        'latitudine' => $latitudine,
        'longitudine' => $longitudine,
        'nome_referente' => sanitize_text_field(wp_unslash($_POST['nome_referente'] ?? '')),
        'ruolo_referente' => sanitize_text_field(wp_unslash($_POST['ruolo_referente'] ?? '')),
        'telefono_attivita' => sanitize_text_field(wp_unslash($_POST['telefono_attivita'] ?? '')),
        'email_ufficiale' => $email_login,
        'sito_web' => '',
        'social_media' => '',
        'email_login' => $email_login,
        'password_hash' => wp_hash_password($password),
        'termini_condizioni' => intval($_POST['termini_condizioni']),
        'privacy_gdpr' => intval($_POST['privacy_gdpr']),
        'pubblicazione_dati' => intval($_POST['pubblicazione_dati']),
        'dichiarazione_verita' => intval($_POST['dichiarazione_verita']),
        'status' => 'pending'
    ]);
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Errore nel salvataggio dei dati']);
        return;
    }
    
    $partner_id = $wpdb->insert_id;

    opencomune_send_partner_registration_admin_email($partner_id, [
        'nome_attivita' => sanitize_text_field(wp_unslash($_POST['nome_attivita'] ?? '')),
        'tipologia_attivita' => sanitize_text_field(wp_unslash($_POST['tipologia_attivita'] ?? '')),
        'partita_iva' => sanitize_text_field(wp_unslash($_POST['partita_iva'] ?? '')),
        'numero_rea' => sanitize_text_field(wp_unslash($_POST['numero_rea'] ?? '')),
        'descrizione_breve' => sanitize_textarea_field(wp_unslash($_POST['descrizione_breve'] ?? '')),
        'indirizzo' => sanitize_text_field(wp_unslash($_POST['indirizzo'] ?? '')),
        'cap' => sanitize_text_field(wp_unslash($_POST['cap'] ?? '')),
        'comune' => sanitize_text_field(wp_unslash($_POST['comune'] ?? '')),
        'area_quartiere' => sanitize_text_field(wp_unslash($_POST['area_quartiere'] ?? '')),
        'nome_referente' => sanitize_text_field(wp_unslash($_POST['nome_referente'] ?? '')),
        'ruolo_referente' => sanitize_text_field(wp_unslash($_POST['ruolo_referente'] ?? '')),
        'telefono_attivita' => sanitize_text_field(wp_unslash($_POST['telefono_attivita'] ?? '')),
        'email_ufficiale' => $email_login,
    ]);
    
    // Crea utente WordPress con ruolo partner (disabilitato)
    $user_id = wp_create_user(
        $email_login,
        $password,
        $email_login
    );
    
    // Aggiorna il nome pubblico dell'utente con il nome dell'attività
    if (!is_wp_error($user_id)) {
        wp_update_user([
            'ID' => $user_id,
            'display_name' => sanitize_text_field($_POST['nome_attivita']),
            'first_name' => sanitize_text_field($_POST['nome_attivita']),
            'last_name' => ''
        ]);
    }
    
    if (!is_wp_error($user_id)) {
        // Assegna ruolo partner
        $user = new WP_User($user_id);
        $user->set_role('partner');
        
        $confirmation_code = opencomune_generate_partner_confirmation_code();
        update_user_meta($user_id, 'partner_confirmation_code', $confirmation_code);
        update_user_meta($user_id, 'partner_email_verified', false);
        update_user_meta($user_id, 'partner_approved', false);
        update_user_meta($user_id, 'partner_id', $partner_id);
        
        // Debug: verifica che l'utente sia in attesa di conferma email
        $partner_verified = get_user_meta($user_id, 'partner_email_verified', true);
        error_log("OPENCOMUNE PARTNER: User ID $user_id created with partner_email_verified = " . ($partner_verified ? 'true' : 'false'));
        
        // Assegna l'immagine del logo come immagine profilo se presente
        if ($logo_attachment_id) {
            update_user_meta($user_id, 'profile_image_id', $logo_attachment_id);
            error_log("OPENCOMUNE PARTNER: Set profile image ID $logo_attachment_id for user ID $user_id");
        }
        
        // Aggiorna il record partner con l'ID utente WordPress
        $wpdb->update(
            $table_partners,
            ['wp_user_id' => $user_id],
            ['id' => $partner_id]
        );
        
        error_log("OPENCOMUNE PARTNER: Created WordPress user ID $user_id for partner ID $partner_id");

        opencomune_send_partner_registration_credentials_email(
            $email_login,
            sanitize_text_field(wp_unslash($_POST['nome_referente'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['nome_attivita'] ?? '')),
            $password
        );

        opencomune_send_partner_confirmation_email(
            $email_login,
            sanitize_text_field(wp_unslash($_POST['nome_referente'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['nome_attivita'] ?? '')),
            $confirmation_code
        );

        opencomune_sync_partner_cpt($partner_id);
    } else {
        error_log("OPENCOMUNE PARTNER: Failed to create WordPress user: " . $user_id->get_error_message());
        wp_send_json_error(['message' => 'Errore nella creazione dell\'account. Riprova.']);
        return;
    }
    
    wp_send_json_success([
        'message' => 'Registrazione completata. Controlla la tua email: troverai la password e il codice per il primo accesso.',
    ]);
}

// Validazione campi data esperienza (form partner)
function opencomune_validate_tour_date_fields($tour_date_mode, $tour_start_date, $tour_end_date) {
    $tour_date_mode = in_array($tour_date_mode, ['single', 'range'], true) ? $tour_date_mode : 'single';

    if (empty($tour_start_date)) {
        return new WP_Error('missing_start_date', 'La data di inizio è obbligatoria.');
    }

    if ($tour_date_mode === 'range') {
        if (empty($tour_end_date)) {
            return new WP_Error('missing_end_date', 'La data di fine è obbligatoria quando selezioni un periodo.');
        }
        if ($tour_end_date < $tour_start_date) {
            return new WP_Error('invalid_date_range', 'La data di fine deve essere uguale o successiva alla data di inizio.');
        }
    } else {
        $tour_end_date = '';
    }

    return [
        'tour_date_mode' => $tour_date_mode,
        'tour_start_date' => $tour_start_date,
        'tour_end_date' => $tour_end_date,
    ];
}

function opencomune_calculate_tour_duration_from_dates($tour_date_mode, $tour_start_date, $tour_end_date) {
    if (empty($tour_start_date)) {
        return '';
    }

    if ($tour_date_mode === 'range' && !empty($tour_end_date)) {
        $start = DateTime::createFromFormat('Y-m-d', $tour_start_date);
        $end = DateTime::createFromFormat('Y-m-d', $tour_end_date);

        if (!$start || !$end || $end < $start) {
            return '';
        }

        $days = (int) $start->diff($end)->days + 1;

        return $days === 1 ? '1 giorno' : $days . ' giorni';
    }

    return '1 giorno';
}

function opencomune_tour_duration_parts_from_post($post_data = null) {
    if ($post_data === null) {
        $post_data = $_POST;
    }

    return [
        'days' => max(0, (int) ($post_data['tour_duration_days'] ?? 0)),
        'hours' => max(0, (int) ($post_data['tour_duration_hours'] ?? 0)),
        'minutes' => max(0, min(59, (int) ($post_data['tour_duration_minutes'] ?? 0))),
    ];
}

function opencomune_get_tour_duration_parts($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return ['days' => 0, 'hours' => 0, 'minutes' => 0];
    }

    return [
        'days' => max(0, (int) get_post_meta($post_id, 'tour_duration_days', true)),
        'hours' => max(0, (int) get_post_meta($post_id, 'tour_duration_hours', true)),
        'minutes' => max(0, min(59, (int) get_post_meta($post_id, 'tour_duration_minutes', true))),
    ];
}

function opencomune_tour_duration_parts_total_minutes($parts) {
    $days = max(0, (int) ($parts['days'] ?? 0));
    $hours = max(0, (int) ($parts['hours'] ?? 0));
    $minutes = max(0, (int) ($parts['minutes'] ?? 0));

    return ($days * 24 * 60) + ($hours * 60) + $minutes;
}

function opencomune_format_tour_duration_label($parts, $lang = 'it') {
    $days = max(0, (int) ($parts['days'] ?? 0));
    $hours = max(0, (int) ($parts['hours'] ?? 0));
    $minutes = max(0, (int) ($parts['minutes'] ?? 0));
    $chunks = [];

    if ($lang === 'en') {
        if ($days > 0) {
            $chunks[] = $days . ' day' . ($days === 1 ? '' : 's');
        }
        if ($hours > 0) {
            $chunks[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
        }
        if ($minutes > 0) {
            $chunks[] = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
        }

        return implode(', ', $chunks);
    }

    if ($days > 0) {
        $chunks[] = $days . ' giorn' . ($days === 1 ? 'o' : 'i');
    }
    if ($hours > 0) {
        $chunks[] = $hours . ' or' . ($hours === 1 ? 'a' : 'e');
    }
    if ($minutes > 0) {
        $chunks[] = $minutes . ' minut' . ($minutes === 1 ? 'o' : 'i');
    }

    return implode(', ', $chunks);
}

function opencomune_save_tour_duration_parts($post_id, $parts) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $parts = [
        'days' => max(0, (int) ($parts['days'] ?? 0)),
        'hours' => max(0, (int) ($parts['hours'] ?? 0)),
        'minutes' => max(0, min(59, (int) ($parts['minutes'] ?? 0))),
    ];

    update_post_meta($post_id, 'tour_duration_days', $parts['days']);
    update_post_meta($post_id, 'tour_duration_hours', $parts['hours']);
    update_post_meta($post_id, 'tour_duration_minutes', $parts['minutes']);

    $label = opencomune_format_tour_duration_label($parts, 'it');
    if ($label !== '') {
        update_post_meta($post_id, 'tour_duration', $label);
    }
}

function opencomune_get_tour_duration_api_payload($post_id) {
    $parts = opencomune_get_tour_duration_parts($post_id);
    $legacy = opencomune_get_esperienza_meta_value($post_id, 'tour_duration', 'durata');
    $label = opencomune_format_tour_duration_label($parts, 'it');

    return [
        'days' => $parts['days'],
        'hours' => $parts['hours'],
        'minutes' => $parts['minutes'],
        'total_minutes' => opencomune_tour_duration_parts_total_minutes($parts),
        'label' => $label !== '' ? $label : (string) $legacy,
    ];
}

function opencomune_format_esperienza_dates_label($post_id) {
    $schedule = opencomune_get_esperienza_schedule_dates($post_id);
    $start = $schedule['start'];
    $end = $schedule['end'];
    $mode = $schedule['mode'];

    if ($start === '') {
        return '';
    }

    if ($mode === 'range' && $end !== '' && $end !== $start) {
        return date_i18n('j M Y', strtotime($start)) . ' - ' . date_i18n('j M Y', strtotime($end));
    }

    return date_i18n('j M Y', strtotime($start));
}

function opencomune_get_esperienza_luogo_label($post_id) {
    $luogo_keys = ['tour_meeting_point', 'indirizzo_ritrovo', 'tour_address'];
    foreach ($luogo_keys as $key) {
        $value = get_post_meta($post_id, $key, true);
        if (opencomune_has_field_value($value)) {
            return trim(wp_strip_all_tags((string) $value));
        }
    }

    return '';
}

function opencomune_get_esperienza_citta_label($post_id) {
    $citta = get_post_meta($post_id, 'citta', true);
    if (!opencomune_has_field_value($citta)) {
        return '';
    }

    return ucwords(strtolower(stripslashes(trim((string) $citta))));
}

function opencomune_render_esperienza_info_badges($post_id) {
    $post_id = (int) $post_id;
    $badges = [];

    $dates_label = opencomune_format_esperienza_dates_label($post_id);
    if ($dates_label !== '') {
        $badges[] = [
            'icon' => 'bi-calendar-event',
            'label' => $dates_label,
        ];
    }

    $luogo = opencomune_get_esperienza_luogo_label($post_id);
    if ($luogo !== '') {
        $badges[] = [
            'icon' => 'bi-pin-map',
            'label' => wp_trim_words($luogo, 10, '...'),
        ];
    }

    $citta = opencomune_get_esperienza_citta_label($post_id);
    if ($citta !== '') {
        $badges[] = [
            'icon' => 'bi-geo-alt-fill',
            'label' => $citta,
        ];
    }

    if ($badges === []) {
        return;
    }

    echo '<div class="flex flex-wrap items-center gap-2 mb-2">';
    foreach ($badges as $badge) {
        echo '<span class="inline-block bg-blue-600 text-white text-xs px-2 py-1 rounded font-semibold">';
        echo '<i class="bi ' . esc_attr($badge['icon']) . ' mr-1" aria-hidden="true"></i>';
        echo esc_html($badge['label']);
        echo '</span>';
    }
    echo '</div>';
}

function opencomune_has_field_value($value) {
    if (is_array($value)) {
        foreach ($value as $item) {
            if (opencomune_has_field_value($item)) {
                return true;
            }
        }
        return false;
    }

    return trim(wp_strip_all_tags((string) $value)) !== '';
}

/**
 * HTML da editor (post_content o meta): filtri WordPress come in pagina articolo.
 *
 * @param string $html    Contenuto grezzo dal DB.
 * @param int    $post_id Opzionale: contesto post per shortcode/embed in the_content.
 */
function opencomune_filter_esperienza_rich_text($html, $post_id = 0) {
    $html = is_string($html) ? $html : '';
    if (trim($html) === '') {
        return '';
    }

    $post_id = (int) $post_id;
    if ($post_id > 0) {
        global $post;
        $previous = $post;
        $post = get_post($post_id);
        if ($post instanceof WP_Post) {
            setup_postdata($post);
            $html = apply_filters('the_content', $html);
            wp_reset_postdata();
            $post = $previous instanceof WP_Post ? $previous : null;

            return $html;
        }
    }

    return apply_filters('the_content', $html);
}

/**
 * Descrizione completa (post_content) per scheda pubblica.
 */
function opencomune_render_esperienza_post_content($post_id = 0) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }
    if ($post_id <= 0) {
        return '';
    }

    $raw = get_post_field('post_content', $post_id);
    if (!is_string($raw) || trim($raw) === '') {
        return '';
    }

    return opencomune_filter_esperienza_rich_text($raw, $post_id);
}

function opencomune_format_esperienza_duration_label($duration) {
    $duration = trim((string) $duration);
    if ($duration === '') {
        return '';
    }

    if (preg_match('/[a-zA-Zàèéìòù]/u', $duration)) {
        return $duration;
    }

    return $duration . 'h';
}

function opencomune_normalize_tour_difficulty_slug($difficulty) {
    $slug = sanitize_key((string) $difficulty);
    if ($slug === '') {
        return '';
    }

    $aliases = [
        'easy' => 'facile',
        'medium' => 'medio',
        'hard' => 'difficile',
        'difficult' => 'difficile',
    ];

    return $aliases[$slug] ?? $slug;
}

/**
 * @return array{slug:string,label:string,class:string}|null
 */
function opencomune_get_tour_difficulty_badge($difficulty) {
    $slug = opencomune_normalize_tour_difficulty_slug($difficulty);
    if ($slug === '') {
        return null;
    }

    $labels = [
        'facile' => __('Facile', 'opencomune'),
        'medio' => __('Medio', 'opencomune'),
        'difficile' => __('Difficile', 'opencomune'),
    ];

    if (!isset($labels[$slug])) {
        return [
            'slug' => $slug,
            'label' => ucfirst(str_replace('-', ' ', $slug)),
            'class' => 'oc-exp-difficulty-badge--default',
        ];
    }

    return [
        'slug' => $slug,
        'label' => $labels[$slug],
        'class' => 'oc-exp-difficulty-badge--' . $slug,
    ];
}

function opencomune_render_tour_difficulty_badge($difficulty) {
    $badge = opencomune_get_tour_difficulty_badge($difficulty);
    if (!$badge) {
        return '';
    }

    return sprintf(
        '<span class="oc-exp-difficulty-badge %1$s">%2$s</span>',
        esc_attr($badge['class']),
        esc_html($badge['label'])
    );
}

function opencomune_is_valid_image_attachment($attachment_id) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id <= 0) {
        return false;
    }

    $file_path = get_attached_file($attachment_id);
    if (!$file_path || !is_readable($file_path) || (int) filesize($file_path) <= 0) {
        return false;
    }

    $filetype = wp_check_filetype($file_path);
    if (empty($filetype['type']) || strpos($filetype['type'], 'image/') !== 0) {
        return false;
    }

    return true;
}

function opencomune_esperienza_has_valid_thumbnail($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    $thumbnail_id = (int) get_post_thumbnail_id($post_id);
    if (!opencomune_is_valid_image_attachment($thumbnail_id)) {
        return false;
    }

    return (bool) get_the_post_thumbnail_url($post_id, 'thumbnail');
}

function opencomune_get_esperienza_thumbnail_url($post_id, $size = 'large') {
    if (!opencomune_esperienza_has_valid_thumbnail($post_id)) {
        return '';
    }

    return get_the_post_thumbnail_url($post_id, $size) ?: '';
}

function opencomune_print_esperienza_cover_placeholder_styles() {
    static $styles_printed = false;
    if ($styles_printed) {
        return;
    }

    $styles_printed = true;
    ?>
    <style>
    .esperienza-cover-placeholder {
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f3f4f6;
        color: #9ca3af;
    }
    .esperienza-cover-placeholder i {
        font-size: 2.5rem;
        line-height: 1;
    }
    .esperienza-cover-placeholder.is-hero i {
        font-size: 4rem;
    }
    </style>
    <?php
}

function opencomune_render_esperienza_cover($post_id, $args = []) {
    $defaults = [
        'size' => 'large',
        'img_class' => 'rounded-xl w-full h-40 object-cover',
        'placeholder_class' => 'esperienza-cover-placeholder rounded-xl w-full h-40',
        'icon_class' => 'bi bi-images',
        'alt' => get_the_title($post_id),
    ];
    $args = wp_parse_args($args, $defaults);

    $url = opencomune_get_esperienza_thumbnail_url($post_id, $args['size']);
    if ($url !== '') {
        printf(
            '<img src="%s" class="%s" alt="%s" loading="lazy">',
            esc_url($url),
            esc_attr($args['img_class']),
            esc_attr($args['alt'])
        );
        return;
    }

    opencomune_print_esperienza_cover_placeholder_styles();
    printf(
        '<div class="%s" aria-hidden="true"><i class="%s" aria-hidden="true"></i></div>',
        esc_attr($args['placeholder_class']),
        esc_attr($args['icon_class'])
    );
}

function opencomune_get_esperienza_allegati_ids($post_id) {
    $ids = get_post_meta($post_id, 'esperienza_allegati', true);
    if (!is_array($ids)) {
        if (is_string($ids) && $ids !== '') {
            $ids = array_map('intval', explode(',', $ids));
        } else {
            $ids = [];
        }
    }

    return array_values(array_filter(array_map('intval', $ids)));
}

function opencomune_save_esperienza_allegati($post_id, $raw_ids) {
    if (is_string($raw_ids)) {
        $ids = array_filter(array_map('intval', explode(',', $raw_ids)));
    } elseif (is_array($raw_ids)) {
        $ids = array_filter(array_map('intval', $raw_ids));
    } else {
        $ids = [];
    }

    $ids = array_values(array_unique($ids));
    if (empty($ids)) {
        delete_post_meta($post_id, 'esperienza_allegati');
        return;
    }

    update_post_meta($post_id, 'esperienza_allegati', $ids);
}

function opencomune_get_esperienza_allegati_items($post_id) {
    $items = [];

    foreach (opencomune_get_esperienza_allegati_ids($post_id) as $attachment_id) {
        $url = wp_get_attachment_url($attachment_id);
        if (!$url) {
            continue;
        }

        $filename = basename(get_attached_file($attachment_id) ?: $url);
        $items[] = [
            'id' => $attachment_id,
            'title' => get_the_title($attachment_id) ?: $filename,
            'url' => $url,
            'filename' => $filename,
        ];
    }

    return $items;
}

function opencomune_get_esperienza_descrizione_breve($post_id) {
    $desc = get_post_meta($post_id, 'descrizione_breve', true);
    if (is_string($desc) && $desc !== '') {
        return $desc;
    }

    return get_post_field('post_excerpt', $post_id);
}

function opencomune_get_esperienze_week_filter_days($days = 7) {
    $today = current_time('Y-m-d');
    $items = [];

    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime($today . " +{$i} days"));
        $label = $i === 0
            ? __('Oggi', 'opencomune')
            : date_i18n('D j M', strtotime($date));

        $items[] = [
            'value' => $date,
            'label' => $label,
        ];
    }

    return $items;
}

function opencomune_get_esperienze_upcoming_meta_query($today = null) {
    if ($today === null) {
        $today = current_time('Y-m-d');
    }

    return [
        'relation' => 'OR',
        [
            'relation' => 'AND',
            [
                'key' => 'tour_start_date',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE',
            ],
            [
                'relation' => 'OR',
                [
                    'key' => 'tour_date_mode',
                    'value' => 'single',
                    'compare' => '=',
                ],
                [
                    'key' => 'tour_date_mode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ],
        [
            'relation' => 'AND',
            [
                'key' => 'tour_date_mode',
                'value' => 'range',
                'compare' => '=',
            ],
            [
                'key' => 'tour_end_date',
                'value' => $today,
                'compare' => '>=',
                'type' => 'DATE',
            ],
        ],
    ];
}

function opencomune_get_esperienza_schedule_dates($post_id) {
    $post_id = (int) $post_id;

    if (function_exists('opencomune_get_esperienza_availability_date_bounds')) {
        $bounds = opencomune_get_esperienza_availability_date_bounds($post_id);
        if ($bounds !== null) {
            return [
                'start' => $bounds['start'],
                'end' => $bounds['end'],
                'mode' => $bounds['mode'],
            ];
        }
    }

    $start = get_post_meta($post_id, 'tour_start_date', true);
    $end = get_post_meta($post_id, 'tour_end_date', true);
    $mode = get_post_meta($post_id, 'tour_date_mode', true);

    if ($start === '' || $start === null) {
        $start = get_post_meta($post_id, 'data_inizio', true);
    }

    return [
        'start' => is_string($start) ? trim($start) : '',
        'end' => is_string($end) ? trim($end) : '',
        'mode' => $mode ?: 'single',
    ];
}

/**
 * Allinea meta legacy dopo salvataggio wizard (orari/date non più editabili a mano).
 */
function opencomune_finalize_esperienza_form_meta($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    if (function_exists('opencomune_sync_legacy_schedule_dates_from_availability')) {
        opencomune_sync_legacy_schedule_dates_from_availability($post_id);
    }

    delete_post_meta($post_id, 'tour_meeting_date');

    $start_times = opencomune_get_esperienza_start_times($post_id);
    if (!empty($start_times[0]['time'])) {
        update_post_meta($post_id, 'tour_meeting_time', $start_times[0]['time']);
    } else {
        delete_post_meta($post_id, 'tour_meeting_time');
    }
}

function opencomune_is_esperienza_upcoming($post_id, $today = null) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    if ($today === null) {
        $today = current_time('Y-m-d');
    }

    $schedule = opencomune_get_esperienza_schedule_dates($post_id);
    $start = $schedule['start'];
    $end = $schedule['end'];
    $mode = $schedule['mode'];

    if ($start === '') {
        return false;
    }

    if ($mode === 'range' && $end !== '') {
        return $end >= $today;
    }

    return $start >= $today;
}

function opencomune_get_upcoming_esperienze_by_authors($author_ids, $limit = 8) {
    $author_ids = array_values(array_filter(array_map('intval', (array) $author_ids)));
    if ($author_ids === []) {
        return [];
    }

    $limit = max(1, (int) $limit);
    $query = new WP_Query([
        'post_type' => 'esperienze',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'author__in' => $author_ids,
        'no_found_rows' => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $posts = [];
    foreach ($query->posts as $post) {
        if (!opencomune_is_esperienza_upcoming($post->ID)) {
            continue;
        }
        $posts[] = $post;
        if (count($posts) >= $limit) {
            break;
        }
    }
    wp_reset_postdata();

    return $posts;
}

function opencomune_get_home_esperienze_for_visitors($limit = 12) {
    $limit = max(1, (int) $limit);
    $query = new WP_Query([
        'post_type' => 'esperienze',
        'posts_per_page' => 50,
        'post_status' => 'publish',
        'no_found_rows' => true,
        'update_post_meta_cache' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $posts = [];
    foreach ($query->posts as $post) {
        if (function_exists('opencomune_is_esperienza_upcoming') && !opencomune_is_esperienza_upcoming($post->ID)) {
            continue;
        }
        $posts[] = $post;
        if (count($posts) >= $limit) {
            break;
        }
    }

    if ($posts === [] && $query->posts !== []) {
        $posts = array_slice($query->posts, 0, $limit);
    }

    wp_reset_postdata();

    return $posts;
}

/**
 * @return array{max_label: string, duration_label: string, city: string, price_label: string}
 */
function opencomune_get_home_esperienza_card_meta($post_id) {
    $post_id = (int) $post_id;
    $capacity = opencomune_get_esperienza_capacity_payload($post_id);
    $max_label = '';
    if (!empty($capacity['max_participants'])) {
        $max_label = sprintf(
            /* translators: %d: maximum participants */
            __('Max %d', 'opencomune'),
            (int) $capacity['max_participants']
        );
    }

    $parts = opencomune_get_tour_duration_parts($post_id);
    $duration_label = opencomune_format_tour_duration_label($parts, 'it');
    if ($duration_label === '') {
        $legacy = trim((string) get_post_meta($post_id, 'tour_duration', true));
        if ($legacy !== '') {
            $duration_label = $legacy;
        }
    }

    $city = opencomune_get_esperienza_citta_label($post_id);
    $price_label = '';
    if (function_exists('opencomune_get_esperienza_card_price_label')) {
        $price_label = opencomune_get_esperienza_card_price_label($post_id);
    } else {
        $prezzo = get_post_meta($post_id, 'prezzo', true);
        if ($prezzo !== '' && is_numeric($prezzo) && (float) $prezzo > 0) {
            $price_label = sprintf(__('Da %s', 'opencomune'), '€ ' . number_format_i18n((float) $prezzo, 0));
        }
    }

    return [
        'max_label' => $max_label,
        'duration_label' => $duration_label,
        'city' => $city,
        'price_label' => $price_label,
    ];
}

function opencomune_render_homepage_esperienze_empty_alert() {
    ?>
    <div class="oc-home-empty">
        <p class="oc-home-empty__title"><?php esc_html_e('Nessuna esperienza disponibile al momento', 'opencomune'); ?></p>
        <p class="oc-home-empty__text"><?php esc_html_e('Torna a trovarci presto: stiamo aggiornando il calendario.', 'opencomune'); ?></p>
        <a class="oc-home-btn oc-home-btn--outline" href="<?php echo esc_url(home_url('/mappa/')); ?>"><?php esc_html_e('Apri la mappa', 'opencomune'); ?></a>
    </div>
    <?php
}

function opencomune_get_esperienze_date_meta_query($filter_date) {
    return [
        'relation' => 'OR',
        [
            'relation' => 'AND',
            [
                'key' => 'tour_start_date',
                'value' => $filter_date,
                'compare' => '=',
                'type' => 'DATE',
            ],
            [
                'relation' => 'OR',
                [
                    'key' => 'tour_date_mode',
                    'value' => 'single',
                    'compare' => '=',
                ],
                [
                    'key' => 'tour_date_mode',
                    'compare' => 'NOT EXISTS',
                ],
            ],
        ],
        [
            'relation' => 'AND',
            [
                'key' => 'tour_date_mode',
                'value' => 'range',
                'compare' => '=',
            ],
            [
                'key' => 'tour_start_date',
                'value' => $filter_date,
                'compare' => '<=',
                'type' => 'DATE',
            ],
            [
                'key' => 'tour_end_date',
                'value' => $filter_date,
                'compare' => '>=',
                'type' => 'DATE',
            ],
        ],
    ];
}

function opencomune_is_esperienze_archive_main_query($query) {
    if (is_admin() || !$query->is_main_query()) {
        return false;
    }

    if ($query->is_author()) {
        return false;
    }

    // Le pagine di dettaglio hanno post_type=esperienze ma non devono usare il filtro archivio.
    if ($query->is_singular() || $query->is_single() || $query->get('name') || $query->get('p')) {
        return false;
    }

    if ($query->is_post_type_archive('esperienze')) {
        return true;
    }

    $post_type = $query->get('post_type');
    if ($post_type === 'esperienze') {
        return true;
    }

    return is_array($post_type) && in_array('esperienze', $post_type, true);
}

function opencomune_get_esperienze_archive_filter_category() {
    $slug = isset($_GET['categoria']) ? sanitize_title(wp_unslash($_GET['categoria'])) : '';
    if ($slug === '' || $slug === 'tutte') {
        return '';
    }

    $term = get_term_by('slug', $slug, 'categorie_esperienze');

    return $term instanceof WP_Term ? $slug : '';
}

function opencomune_get_esperienza_category_archive_url($term) {
    if (!$term instanceof WP_Term) {
        return '';
    }

    $archive_url = get_post_type_archive_link('esperienze');
    if (!$archive_url) {
        $link = get_term_link($term);
        return is_wp_error($link) ? '' : $link;
    }

    return add_query_arg('categoria', $term->slug, $archive_url);
}

/**
 * Categorie con almeno un'esperienza pubblicata e prossima in archivio.
 *
 * @return WP_Term[]
 */
function opencomune_get_esperienze_archive_filter_categories() {
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $upcoming_ids = opencomune_get_esperienze_archive_filtered_ids('');
    $term_ids = [];
    foreach ($upcoming_ids as $post_id) {
        $terms = wp_get_post_terms((int) $post_id, 'categorie_esperienze', ['fields' => 'ids']);
        if (is_wp_error($terms)) {
            continue;
        }
        foreach ($terms as $term_id) {
            $term_ids[(int) $term_id] = true;
        }
    }

    if ($term_ids === []) {
        $cached = [];

        return $cached;
    }

    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'include' => array_keys($term_ids),
        'orderby' => 'name',
        'order' => 'ASC',
        'hide_empty' => false,
    ]);

    $cached = is_wp_error($terms) ? [] : $terms;

    return $cached;
}

function opencomune_esperienza_matches_archive_category_filter($post_id, $category_slug = '') {
    if ($category_slug === '') {
        return true;
    }

    $slugs = wp_get_post_terms((int) $post_id, 'categorie_esperienze', ['fields' => 'slugs']);
    if (is_wp_error($slugs)) {
        return false;
    }

    return in_array($category_slug, $slugs, true);
}

function opencomune_esperienza_matches_archive_date_filter($post_id, $filter_date = '') {
    if (!opencomune_is_esperienza_upcoming($post_id)) {
        return false;
    }

    if ($filter_date === '') {
        return true;
    }

    $schedule = opencomune_get_esperienza_schedule_dates($post_id);
    $start = $schedule['start'];
    $end = $schedule['end'];
    $mode = $schedule['mode'];

    if ($start === '') {
        return false;
    }

    if ($mode === 'range' && $end !== '') {
        return $start <= $filter_date && $end >= $filter_date;
    }

    return $start === $filter_date;
}

function opencomune_get_esperienze_archive_filtered_ids($category_slug = '') {
    static $cache = [];

    $cache_key = $category_slug === '' ? 'all' : $category_slug;
    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $query = new WP_Query([
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
        'orderby' => 'date',
        'order' => 'DESC',
    ]);

    $filtered_ids = [];
    foreach ($query->posts as $post_id) {
        $post_id = (int) $post_id;
        if (!opencomune_is_esperienza_upcoming($post_id)) {
            continue;
        }
        if (!opencomune_esperienza_matches_archive_category_filter($post_id, $category_slug)) {
            continue;
        }
        $filtered_ids[] = $post_id;
    }

    usort($filtered_ids, static function ($post_id_a, $post_id_b) {
        $schedule_a = opencomune_get_esperienza_schedule_dates($post_id_a);
        $schedule_b = opencomune_get_esperienza_schedule_dates($post_id_b);
        $date_cmp = strcmp($schedule_a['start'], $schedule_b['start']);
        if ($date_cmp !== 0) {
            return $date_cmp;
        }

        return get_post_time('U', true, $post_id_b) <=> get_post_time('U', true, $post_id_a);
    });

    wp_reset_postdata();
    $cache[$cache_key] = $filtered_ids;

    return $filtered_ids;
}

add_filter('posts_pre_query', 'opencomune_esperienze_archive_posts_pre_query', 10, 2);
function opencomune_esperienze_archive_posts_pre_query($posts, $query) {
    if (!opencomune_is_esperienze_archive_main_query($query)) {
        return $posts;
    }

    $filter_category = opencomune_get_esperienze_archive_filter_category();
    $filtered_ids = opencomune_get_esperienze_archive_filtered_ids($filter_category);
    $per_page = (int) $query->get('posts_per_page');
    if ($per_page <= 0) {
        $per_page = (int) get_option('posts_per_page');
    }
    if ($per_page <= 0) {
        $per_page = 10;
    }

    $paged = max(1, (int) $query->get('paged'));
    $total = count($filtered_ids);
    $offset = ($paged - 1) * $per_page;
    $page_ids = array_slice($filtered_ids, $offset, $per_page);

    $query->found_posts = $total;
    $query->max_num_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

    if ($page_ids === []) {
        return [];
    }

    return array_map('get_post', $page_ids);
}

function opencomune_render_pagination($args = []) {
    global $wp_query;

    $defaults = [
        'total' => (int) $wp_query->max_num_pages,
        'current' => max(1, (int) get_query_var('paged', get_query_var('page', 1))),
        'mid_size' => 2,
        'prev_text' => __('Precedente', 'opencomune'),
        'next_text' => __('Successiva', 'opencomune'),
        'add_args' => [],
    ];
    $args = wp_parse_args($args, $defaults);

    if ($args['total'] <= 1) {
        return;
    }

    get_template_part('template-parts/pagination', null, $args);
}

function opencomune_save_esperienza_themes($post_id, $selected_slugs = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $selected_slugs))));
    wp_set_object_terms($post_id, $slugs, 'temi_esperienze');

    return $slugs;
}

function opencomune_get_esperienza_category_slug($post_id) {
    $slugs = opencomune_get_esperienza_category_slugs($post_id);

    return $slugs[0] ?? '';
}

function opencomune_get_esperienza_category_slugs($post_id) {
    $terms = wp_get_post_terms((int) $post_id, 'categorie_esperienze', ['fields' => 'slugs']);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $terms)));
}

function opencomune_get_esperienza_theme_slugs($post_id) {
    $terms = wp_get_post_terms((int) $post_id, 'temi_esperienze', ['fields' => 'slugs']);
    if (is_wp_error($terms)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $terms)));
}

function opencomune_save_esperienza_inclusions($post_id, $selected_slugs = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $selected_slugs))));
    wp_set_object_terms($post_id, $slugs, 'inclusioni_esperienze');

    return $slugs;
}

function opencomune_get_esperienza_inclusion_slugs($post_id) {
    $terms = wp_get_post_terms((int) $post_id, 'inclusioni_esperienze', ['fields' => 'slugs']);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $terms)));
}

/**
 * Esclusioni: stessi termini della tassonomia inclusioni_esperienze, salvati in meta (separati dalle inclusioni assegnate al post).
 */
function opencomune_save_esperienza_exclusions($post_id, $selected_slugs = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $selected_slugs))));
    $valid = [];
    foreach ($slugs as $slug) {
        $term = get_term_by('slug', $slug, 'inclusioni_esperienze');
        if ($term && !is_wp_error($term)) {
            $valid[] = $slug;
        }
    }

    update_post_meta($post_id, 'tour_exclusion_slugs', $valid);

    return $valid;
}

function opencomune_get_esperienza_exclusion_slugs($post_id) {
    $slugs = get_post_meta((int) $post_id, 'tour_exclusion_slugs', true);
    if (!is_array($slugs)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $slugs)));
}

function opencomune_get_esperienza_exclusion_terms($post_id) {
    $items = [];
    foreach (opencomune_get_esperienza_exclusion_slugs($post_id) as $slug) {
        $term = get_term_by('slug', $slug, 'inclusioni_esperienze');
        if ($term && !is_wp_error($term)) {
            $items[] = $term;
        }
    }

    return $items;
}

function opencomune_save_esperienza_before_booking_info($post_id, $selected_slugs = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $selected_slugs))));
    wp_set_object_terms($post_id, $slugs, 'info_prima_prenotazione');

    return $slugs;
}

function opencomune_get_esperienza_before_booking_slugs($post_id) {
    $terms = wp_get_post_terms((int) $post_id, 'info_prima_prenotazione', ['fields' => 'slugs']);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $terms)));
}

function opencomune_save_esperienza_age_limit($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $enabled = !empty($post_data['tour_age_limit_enabled']);
    update_post_meta($post_id, 'tour_age_limit_enabled', $enabled ? '1' : '0');

    $min_age = 0;
    if ($enabled) {
        $min_age = max(0, (int) ($post_data['tour_age_limit_min'] ?? 0));
    }
    update_post_meta($post_id, 'tour_age_limit_min', $min_age);
}

function opencomune_get_esperienza_age_limit($post_id) {
    $enabled = get_post_meta((int) $post_id, 'tour_age_limit_enabled', true) === '1';
    $min_age = (int) get_post_meta((int) $post_id, 'tour_age_limit_min', true);

    return [
        'enabled' => $enabled,
        'minimum_age' => $enabled ? max(0, $min_age) : 0,
    ];
}

function opencomune_get_operating_hours_weekdays() {
    return [
        'monday' => __('Lunedì', 'opencomune'),
        'tuesday' => __('Martedì', 'opencomune'),
        'wednesday' => __('Mercoledì', 'opencomune'),
        'thursday' => __('Giovedì', 'opencomune'),
        'friday' => __('Venerdì', 'opencomune'),
        'saturday' => __('Sabato', 'opencomune'),
        'sunday' => __('Domenica', 'opencomune'),
    ];
}

function opencomune_get_default_operating_hours_day_slots() {
    $days = [];
    foreach (array_keys(opencomune_get_operating_hours_weekdays()) as $key) {
        $days[$key] = [
            'enabled' => false,
            'open' => '',
            'close' => '',
        ];
    }

    return $days;
}

function opencomune_sanitize_operating_hours_time($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{2}:\d{2}$/', $value)) {
        return $value;
    }

    return '';
}

function opencomune_normalize_operating_hours_day_slots($raw) {
    $normalized = opencomune_get_default_operating_hours_day_slots();
    if (!is_array($raw)) {
        return $normalized;
    }

    foreach ($normalized as $key => $_default) {
        if (!isset($raw[$key]) || !is_array($raw[$key])) {
            continue;
        }

        $normalized[$key]['enabled'] = !empty($raw[$key]['enabled']);
        $normalized[$key]['open'] = opencomune_sanitize_operating_hours_time($raw[$key]['open'] ?? '');
        $normalized[$key]['close'] = opencomune_sanitize_operating_hours_time($raw[$key]['close'] ?? '');
    }

    return $normalized;
}

function opencomune_normalize_operating_hours($raw) {
    if (!is_array($raw)) {
        $raw = [];
    }

    $seasonal = [];
    if (!empty($raw['seasonal']) && is_array($raw['seasonal'])) {
        foreach ($raw['seasonal'] as $block) {
            if (!is_array($block)) {
                continue;
            }

            $seasonal[] = [
                'label' => sanitize_text_field($block['label'] ?? ''),
                'start_date' => sanitize_text_field($block['start_date'] ?? ''),
                'end_date' => sanitize_text_field($block['end_date'] ?? ''),
                'days' => opencomune_normalize_operating_hours_day_slots($block['days'] ?? []),
            ];
        }
    }

    return [
        'default' => opencomune_normalize_operating_hours_day_slots($raw['default'] ?? []),
        'seasonal' => $seasonal,
    ];
}

function opencomune_get_esperienza_operating_hours($post_id) {
    $post_id = (int) $post_id;
    $stored = get_post_meta($post_id, 'tour_operating_hours', true);
    $hours = opencomune_normalize_operating_hours(is_array($stored) ? $stored : []);

    return [
        'enabled' => get_post_meta($post_id, 'tour_operating_hours_enabled', true) === '1',
        'default' => $hours['default'],
        'seasonal' => $hours['seasonal'],
    ];
}

function opencomune_render_operating_hours_days_list(array $days) {
    $weekdays = opencomune_get_operating_hours_weekdays();
    echo '<ul class="oc-exp-oh-days" role="list">';
    foreach ($weekdays as $key => $label) {
        $day = isset($days[$key]) && is_array($days[$key]) ? $days[$key] : [];
        $enabled = !empty($day['enabled']);
        $open = isset($day['open']) ? (string) $day['open'] : '';
        $close = isset($day['close']) ? (string) $day['close'] : '';
        ?>
        <li class="oc-exp-oh-day<?php echo $enabled ? ' is-active' : ''; ?>" data-day="<?php echo esc_attr($key); ?>">
            <label class="oc-exp-oh-day__label">
                <input type="checkbox" class="oc-exp-oh-day-enabled" <?php checked($enabled); ?>>
                <span><?php echo esc_html($label); ?></span>
            </label>
            <div class="oc-exp-oh-day__times">
                <input type="time" class="form-input oc-exp-oh-day-open" value="<?php echo esc_attr($open); ?>">
                <span class="oc-exp-oh-day__sep" aria-hidden="true">–</span>
                <input type="time" class="form-input oc-exp-oh-day-close" value="<?php echo esc_attr($close); ?>">
            </div>
        </li>
        <?php
    }
    echo '</ul>';
}

function opencomune_save_esperienza_operating_hours($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $enabled = !empty($post_data['tour_operating_hours_enabled']);
    update_post_meta($post_id, 'tour_operating_hours_enabled', $enabled ? '1' : '0');

    $payload = $post_data['tour_operating_hours_payload'] ?? '';
    $decoded = [];
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode(wp_unslash($payload), true);
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    update_post_meta($post_id, 'tour_operating_hours', opencomune_normalize_operating_hours($decoded));
}

function opencomune_normalize_start_times_list($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }

        $time = opencomune_sanitize_operating_hours_time($item['time'] ?? '');
        if ($time === '') {
            continue;
        }

        $id = sanitize_text_field($item['id'] ?? '');
        if ($id === '') {
            $id = 'st_' . uniqid('', true);
        }

        $external = sanitize_text_field($item['external_label'] ?? '');
        if (function_exists('mb_substr')) {
            $external = mb_substr($external, 0, 24);
        } else {
            $external = substr($external, 0, 24);
        }

        $items[] = [
            'id' => $id,
            'time' => $time,
            'duration_hours' => max(0, (int) ($item['duration_hours'] ?? 0)),
            'duration_minutes' => max(0, min(59, (int) ($item['duration_minutes'] ?? 0))),
            'internal_label' => sanitize_text_field($item['internal_label'] ?? ''),
            'external_label' => $external,
            'linked_rates' => [],
            'linked_resource_types' => [],
        ];
    }

    return $items;
}

function opencomune_get_esperienza_start_times($post_id) {
    $stored = get_post_meta((int) $post_id, 'tour_start_times', true);

    return opencomune_normalize_start_times_list(is_array($stored) ? $stored : []);
}

function opencomune_save_esperienza_start_times($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $payload = $post_data['tour_start_times_payload'] ?? '';
    $decoded = [];
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode(wp_unslash($payload), true);
    }
    if (!is_array($decoded)) {
        $decoded = [];
    }

    $items = opencomune_normalize_start_times_list($decoded);
    update_post_meta($post_id, 'tour_start_times', $items);

    return $items;
}

function opencomune_format_start_time_duration_label($hours, $minutes) {
    $hours = max(0, (int) $hours);
    $minutes = max(0, min(59, (int) $minutes));
    $parts = [];

    if ($hours > 0) {
        $parts[] = $hours === 1
            ? __('1 ora', 'opencomune')
            : sprintf(__('%d ore', 'opencomune'), $hours);
    }
    if ($minutes > 0) {
        $parts[] = $minutes === 1
            ? __('1 minuto', 'opencomune')
            : sprintf(__('%d minuti', 'opencomune'), $minutes);
    }

    if (empty($parts)) {
        return __('0 minuti', 'opencomune');
    }

    return implode(' ', $parts);
}

function opencomune_format_start_times_for_api($post_id) {
    $items = [];
    foreach (opencomune_get_esperienza_start_times($post_id) as $row) {
        $items[] = [
            'id' => $row['id'],
            'time' => $row['time'],
            'duration_hours' => (int) $row['duration_hours'],
            'duration_minutes' => (int) $row['duration_minutes'],
            'duration_label' => opencomune_format_start_time_duration_label($row['duration_hours'], $row['duration_minutes']),
            'internal_label' => $row['internal_label'],
            'external_label' => $row['external_label'],
            'linked_rates' => $row['linked_rates'],
            'linked_resource_types' => $row['linked_resource_types'],
        ];
    }

    return $items;
}

function opencomune_get_capacity_mode_definitions() {
    return [
        'unlimited' => [
            'label' => __('Vendita libera (illimitata)', 'opencomune'),
            'description' => __('Nessun limite: conferma tutte le prenotazioni possibili.', 'opencomune'),
        ],
        'limited' => [
            'label' => __('Numero limitato', 'opencomune'),
            'description' => __('Capacità limitata: le prenotazioni si confermano finché ci sono posti.', 'opencomune'),
        ],
        'on_request' => [
            'label' => __('Su richiesta (sconsigliato)', 'opencomune'),
            'description' => __('Le prenotazioni non si confermano subito: il cliente attende la tua approvazione o rifiuto.', 'opencomune'),
        ],
    ];
}

function opencomune_sanitize_capacity_mode($value) {
    $allowed = array_keys(opencomune_get_capacity_mode_definitions());
    $value = sanitize_key((string) $value);

    return in_array($value, $allowed, true) ? $value : 'limited';
}

function opencomune_get_esperienza_capacity_mode($post_id) {
    $post_id = (int) $post_id;
    $value = get_post_meta($post_id, 'tour_capacity_mode', true);
    if ($value !== '') {
        return opencomune_sanitize_capacity_mode($value);
    }

    $max = (int) get_post_meta($post_id, 'tour_max_participants', true);

    return $max > 0 ? 'limited' : 'unlimited';
}

function opencomune_get_esperienza_capacity_payload($post_id) {
    $post_id = (int) $post_id;
    $mode = opencomune_get_esperienza_capacity_mode($post_id);
    $max = (int) get_post_meta($post_id, 'tour_max_participants', true);
    if ($max <= 0) {
        $max = (int) get_post_meta($post_id, 'max_partecipanti', true);
    }

    return [
        'mode' => $mode,
        'max_participants' => $mode === 'limited' && $max > 0 ? $max : null,
    ];
}

function opencomune_save_esperienza_capacity($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $mode = opencomune_sanitize_capacity_mode($post_data['tour_capacity_mode'] ?? 'limited');
    update_post_meta($post_id, 'tour_capacity_mode', $mode);

    $max_raw = trim((string) ($post_data['tour_max_participants'] ?? ''));
    $max = $max_raw !== '' ? max(0, (int) $max_raw) : 0;

    if ($mode === 'limited' && $max > 0) {
        update_post_meta($post_id, 'tour_max_participants', $max);
    } else {
        delete_post_meta($post_id, 'tour_max_participants');
    }
}

function opencomune_get_booking_schedule_type_definitions() {
    return [
        'datetime' => [
            'label' => __('Data e ora', 'opencomune'),
            'description' => __('I clienti selezionano data e ora al momento della prenotazione.', 'opencomune'),
        ],
        'date_only' => [
            'label' => __('Solo data, senza orario', 'opencomune'),
            'description' => __('I clienti scelgono la data, senza indicare un orario nella giornata.', 'opencomune'),
        ],
        'pass' => [
            'label' => __('Pass', 'opencomune'),
            'description' => __('Non si seleziona una data: è un pass o un titolo non legato a un giorno specifico.', 'opencomune'),
        ],
    ];
}

function opencomune_sanitize_booking_schedule_type($value) {
    $allowed = array_keys(opencomune_get_booking_schedule_type_definitions());
    $value = sanitize_key((string) $value);

    return in_array($value, $allowed, true) ? $value : 'datetime';
}

function opencomune_get_esperienza_booking_schedule_type($post_id) {
    $value = get_post_meta((int) $post_id, 'tour_booking_schedule', true);

    return opencomune_sanitize_booking_schedule_type($value !== '' ? $value : 'datetime');
}

function opencomune_save_esperienza_booking_schedule_type($post_id, $value) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 'datetime';
    }

    $type = opencomune_sanitize_booking_schedule_type($value);
    update_post_meta($post_id, 'tour_booking_schedule', $type);

    return $type;
}

function opencomune_get_esperienza_what_to_bring($post_id) {
    $post_id = (int) $post_id;
    $value = get_post_meta($post_id, 'tour_what_to_bring', true);
    if ($value === '' || $value === null) {
        $value = get_post_meta($post_id, 'cosa_portare', true);
    }

    return (string) $value;
}

function opencomune_format_esperienza_taxonomy_terms_for_api($terms) {
    $items = [];
    if (is_wp_error($terms) || empty($terms)) {
        return $items;
    }

    foreach ($terms as $term) {
        $items[] = [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ];
    }

    return $items;
}

function opencomune_save_esperienza_language_options($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $flags = [
        'tour_lang_live_guide' => !empty($post_data['tour_lang_live_guide']),
        'tour_lang_audio_guide' => !empty($post_data['tour_lang_audio_guide']),
        'tour_lang_reading_material' => !empty($post_data['tour_lang_reading_material']),
    ];

    foreach ($flags as $meta_key => $enabled) {
        update_post_meta($post_id, $meta_key, $enabled ? '1' : '0');
    }
}

function opencomune_get_esperienza_language_options($post_id) {
    return [
        'live_guide' => get_post_meta((int) $post_id, 'tour_lang_live_guide', true) === '1',
        'audio_guide' => get_post_meta((int) $post_id, 'tour_lang_audio_guide', true) === '1',
        'reading_material' => get_post_meta((int) $post_id, 'tour_lang_reading_material', true) === '1',
    ];
}

function opencomune_save_esperienza_categories($post_id, $selected_slugs = [], $new_category_names = []) {
    $slugs = array_filter(array_map('sanitize_title', (array) $selected_slugs));

    foreach ((array) $new_category_names as $name) {
        $name = sanitize_text_field(trim($name));
        if ($name === '') {
            continue;
        }

        $existing = get_term_by('name', $name, 'categorie_esperienze');
        if (!$existing) {
            $existing = get_term_by('slug', sanitize_title($name), 'categorie_esperienze');
        }

        if ($existing && !is_wp_error($existing)) {
            $slugs[] = $existing->slug;
            continue;
        }

        $result = wp_insert_term($name, 'categorie_esperienze');
        if (!is_wp_error($result)) {
            $term = get_term($result['term_id'], 'categorie_esperienze');
            if ($term && !is_wp_error($term)) {
                $slugs[] = $term->slug;
            }
        }
    }

    $slugs = array_values(array_unique(array_filter($slugs)));
    wp_set_object_terms($post_id, $slugs, 'categorie_esperienze');

    return $slugs;
}

function opencomune_get_partner_by_user_id($user_id) {
    global $wpdb;

    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }

    $table = $wpdb->prefix . 'tour_partners';

    return $wpdb->get_row($wpdb->prepare(
        "SELECT id, nome_attivita, tipologia_attivita, comune, descrizione_breve, descrizione_estesa,
                logo_url, sito_web, telefono_attivita, email_ufficiale, indirizzo, cap, wp_user_id, status
         FROM {$table}
         WHERE wp_user_id = %d
         LIMIT 1",
        $user_id
    ));
}

function opencomune_get_partner_public_description($descrizione_breve) {
    $descrizione_breve = trim((string) $descrizione_breve);
    if ($descrizione_breve === 'Partner creato automaticamente da import CSV.') {
        return '';
    }

    return $descrizione_breve;
}

function opencomune_get_partner_avatar_url($partner, $user_id = 0) {
    if ($partner && !empty($partner->logo_url)) {
        return $partner->logo_url;
    }

    $user_id = (int) $user_id;
    if ($user_id > 0) {
        $profile_image_id = get_user_meta($user_id, 'profile_image_id', true);
        if ($profile_image_id && is_numeric($profile_image_id)) {
            $url = wp_get_attachment_image_url((int) $profile_image_id, 'thumbnail');
            if ($url) {
                return $url;
            }
        }

        return get_avatar_url($user_id, ['size' => 96]);
    }

    return '';
}

// Partner approvati dalla tabella tour_partners (archivio pubblico)
function opencomune_get_public_partners($args = []) {
    global $wpdb;

    $defaults = [
        'status' => 'approved',
        'posts_per_page' => 15,
        'paged' => 1,
        'orderby' => 'nome_attivita',
        'order' => 'ASC',
    ];
    $args = wp_parse_args($args, $defaults);

    $allowed_orderby = ['nome_attivita', 'comune', 'data_registrazione', 'data_approvazione'];
    $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'nome_attivita';
    $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';
    $paged = max(1, (int) $args['paged']);
    $per_page = max(1, (int) $args['posts_per_page']);
    $offset = ($paged - 1) * $per_page;

    $table = $wpdb->prefix . 'tour_partners';

    $total = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE status = %s",
        $args['status']
    ));

    $partners = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nome_attivita, tipologia_attivita, comune, descrizione_breve, logo_url, sito_web, wp_user_id, status
         FROM $table
         WHERE status = %s
         ORDER BY $orderby $order
         LIMIT %d OFFSET %d",
        $args['status'],
        $per_page,
        $offset
    ));

    return [
        'partners' => $partners ?: [],
        'total' => $total,
        'pages' => (int) ceil($total / $per_page),
        'paged' => $paged,
    ];
}

// === AJAX: Get Partners ===
function opencomune_get_partners_callback() {
    error_log('OPENCOMUNE GET PARTNERS: Handler called');
    error_log('OPENCOMUNE GET PARTNERS: Current user: ' . get_current_user_id());
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        error_log('OPENCOMUNE GET PARTNERS: Access denied');
        wp_send_json_error(['message' => 'Accesso negato']);
        return;
    }
    
    global $wpdb;
    $table_partners = $wpdb->prefix . 'tour_partners';
    
    // Verifica se è una richiesta DataTables server-side
    if (isset($_POST['draw'])) {
        error_log('OPENCOMUNE GET PARTNERS: DataTables server-side request');
        error_log('OPENCOMUNE GET PARTNERS: POST data: ' . print_r($_POST, true));
        
        // Parametri DataTables
        $draw = intval($_POST['draw']);
        $start = intval($_POST['start']);
        $length = intval($_POST['length']);
        $search_value = sanitize_text_field($_POST['search']['value'] ?? '');
        $order_column = intval($_POST['order'][0]['column'] ?? 0);
        $order_dir = sanitize_text_field($_POST['order'][0]['dir'] ?? 'desc');
        
        // Mappa colonne per ordinamento (allineata alla tabella gestione-partner)
        $columns = [
            'id',
            'nome_attivita',
            'tipologia_attivita',
            'comune',
            'nome_referente',
            'email_ufficiale',
            'status',
            'data_registrazione',
        ];
        $order_by = $columns[$order_column] ?? 'data_registrazione';
        if ($order_by === 'id') {
            $order_by = 'data_registrazione';
        }
        
        // Query base per WHERE
        $where_conditions = array();
        $where_params = array();
        
        if (!empty($search_value)) {
            $where_conditions[] = '(nome_attivita LIKE %s OR tipologia_attivita LIKE %s OR comune LIKE %s OR status LIKE %s OR nome_referente LIKE %s OR email_ufficiale LIKE %s OR email_login LIKE %s)';
            $search_param = '%' . $wpdb->esc_like($search_value) . '%';
            $where_params = array_fill(0, 7, $search_param);
        }
        
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }
        
        // Query per contare i record totali
        $total_records = $wpdb->get_var("SELECT COUNT(*) FROM $table_partners");
        
        // Query per contare i record filtrati
        if (!empty($where_conditions)) {
            $filtered_query = "SELECT COUNT(*) FROM $table_partners $where_clause";
            $filtered_records = $wpdb->get_var($wpdb->prepare($filtered_query, $where_params));
        } else {
            $filtered_records = $total_records;
        }
        
        // Query per i dati con paginazione
        $data_query = "
            SELECT id, nome_attivita, tipologia_attivita, comune, status, 
                   data_registrazione, nome_referente, email_ufficiale, email_login
            FROM $table_partners 
            $where_clause
            ORDER BY $order_by $order_dir
            LIMIT %d OFFSET %d
        ";
        
        $query_params = array_merge($where_params, array($length, $start));
        $partners = $wpdb->get_results($wpdb->prepare($data_query, $query_params));
        
        error_log('OPENCOMUNE GET PARTNERS: Total records: ' . $total_records);
        error_log('OPENCOMUNE GET PARTNERS: Filtered records: ' . $filtered_records);
        error_log('OPENCOMUNE GET PARTNERS: Found ' . count($partners) . ' partners for this page');
        error_log('OPENCOMUNE GET PARTNERS: Partners data: ' . print_r($partners, true));
        
        // Risposta DataTables
        $response = array(
            'draw' => $draw,
            'recordsTotal' => $total_records,
            'recordsFiltered' => $filtered_records,
            'data' => $partners
        );
        
        error_log('OPENCOMUNE GET PARTNERS: Response: ' . print_r($response, true));
        wp_send_json($response);
    } else {
        // Richiesta normale (per statistiche)
        error_log('OPENCOMUNE GET PARTNERS: Normal request');
        
        $partners = $wpdb->get_results("
            SELECT id, nome_attivita, tipologia_attivita, comune, status, 
                   data_registrazione, nome_referente, email_ufficiale, email_login
            FROM $table_partners 
            ORDER BY data_registrazione DESC
        ");
        
        error_log('OPENCOMUNE GET PARTNERS: Found ' . count($partners) . ' partners');
        
        wp_send_json_success($partners);
    }
}

// === AJAX: Get Partner Details ===
function opencomune_get_partner_details_callback() {
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
        return;
    }
    
    $partner_id = intval($_GET['id'] ?? 0);
    if (!$partner_id) {
        wp_send_json_error(['message' => 'ID partner mancante']);
        return;
    }
    
    global $wpdb;
    $table_partners = $wpdb->prefix . 'tour_partners';
    
    $partner = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_partners WHERE id = %d",
        $partner_id
    ));
    
    if (!$partner) {
        wp_send_json_error(['message' => 'Partner non trovato']);
        return;
    }
    
    // Rimuovi password hash dalla risposta
    unset($partner->password_hash);
    
    wp_send_json_success($partner);
}

// === AJAX: Update Partner ===
function opencomune_update_partner_callback() {
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
        return;
    }
    
    $partner_id = intval($_POST['id'] ?? 0);
    if (!$partner_id) {
        wp_send_json_error(['message' => 'ID partner mancante']);
        return;
    }
    
    global $wpdb;
    $table_partners = $wpdb->prefix . 'tour_partners';
    
    // Verifica che il partner esista
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table_partners WHERE id = %d",
        $partner_id
    ));
    
    if (!$existing) {
        wp_send_json_error(['message' => 'Partner non trovato']);
        return;
    }
    
    // Prepara dati per aggiornamento
    $update_data = [];
    $allowed_fields = [
        'nome_attivita', 'tipologia_attivita', 'partita_iva', 'numero_rea',
        'descrizione_breve', 'descrizione_estesa', 'indirizzo', 'cap', 'comune',
        'area_quartiere', 'latitudine', 'longitudine', 'nome_referente',
        'ruolo_referente', 'telefono_attivita', 'email_ufficiale', 'sito_web',
        'social_media', 'status', 'note_admin'
    ];
    
    foreach ($allowed_fields as $field) {
        if (isset($_POST[$field])) {
            if ($field === 'latitudine' || $field === 'longitudine') {
                $update_data[$field] = floatval($_POST[$field]);
            } else {
                $update_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
    }
    
    if (isset($_POST['status']) && $_POST['status'] === 'approved') {
        $update_data['data_approvazione'] = current_time('mysql');
    }
    
    $result = $wpdb->update(
        $table_partners,
        $update_data,
        ['id' => $partner_id]
    );
    
    if ($result === false) {
        wp_send_json_error(['message' => 'Errore durante l\'aggiornamento']);
        return;
    }
    
    // Gestisci utente WordPress se lo status è cambiato o se il nome attività è cambiato
    $wp_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_user_id FROM $table_partners WHERE id = %d",
        $partner_id
    ));
    
    if ($wp_user_id) {
        $user = get_user_by('id', $wp_user_id);
        if ($user) {
            // Aggiorna il nome pubblico se il nome attività è cambiato
            if (isset($_POST['nome_attivita'])) {
                wp_update_user([
                    'ID' => $wp_user_id,
                    'display_name' => sanitize_text_field($_POST['nome_attivita']),
                    'first_name' => sanitize_text_field($_POST['nome_attivita']),
                    'last_name' => ''
                ]);
                error_log("OPENCOMUNE PARTNER: Updated display name for user ID $wp_user_id to: " . sanitize_text_field($_POST['nome_attivita']));
            }
            
            // Gestisci status se cambiato
            if (isset($_POST['status'])) {
                if ($_POST['status'] === 'approved') {
                    opencomune_activate_partner_account($wp_user_id);
                    error_log("OPENCOMUNE PARTNER: Enabled WordPress user ID $wp_user_id for partner ID $partner_id");
                } else {
                    update_user_meta($wp_user_id, 'partner_approved', false);
                    update_user_meta($wp_user_id, 'partner_email_verified', false);
                    error_log("OPENCOMUNE PARTNER: Disabled WordPress user ID $wp_user_id for partner ID $partner_id");
                }
            }
        }
    }

    opencomune_sync_partner_cpt($partner_id);
    
    wp_send_json_success(['message' => 'Partner aggiornato con successo']);
}

/**
 * @return true|WP_Error
 */
function opencomune_delete_partner_record($partner_id) {
    $partner_id = (int) $partner_id;
    if ($partner_id <= 0) {
        return new WP_Error('invalid_id', __('ID partner non valido.', 'opencomune'));
    }

    global $wpdb;
    $table_partners = $wpdb->prefix . 'tour_partners';

    $wp_user_id = $wpdb->get_var($wpdb->prepare(
        "SELECT wp_user_id FROM $table_partners WHERE id = %d",
        $partner_id
    ));

    $result = $wpdb->delete($table_partners, ['id' => $partner_id]);

    if ($result === false) {
        return new WP_Error('db_error', __('Errore durante l\'eliminazione', 'opencomune'));
    }

    opencomune_delete_partner_cpt($partner_id);

    if ($wp_user_id) {
        $user = get_user_by('id', $wp_user_id);
        if ($user) {
            $profile_image_id = get_user_meta($wp_user_id, 'profile_image_id', true);
            if ($profile_image_id) {
                wp_delete_attachment($profile_image_id, true);
            }
            wp_delete_user($wp_user_id);
        }
    }

    return true;
}

// === AJAX: Delete Partner ===
function opencomune_delete_partner_callback() {
    if (!opencomune_user_can_access_ufficio_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
        return;
    }

    $partner_id = intval($_POST['id'] ?? 0);
    if (!$partner_id) {
        wp_send_json_error(['message' => 'ID partner mancante']);
        return;
    }

    $deleted = opencomune_delete_partner_record($partner_id);
    if (is_wp_error($deleted)) {
        wp_send_json_error(['message' => $deleted->get_error_message()]);
        return;
    }

    wp_send_json_success(['message' => 'Partner eliminato con successo']);
}

function opencomune_bulk_delete_partners_callback() {
    if (!opencomune_user_can_manage_all_esperienze()) {
        wp_send_json_error(['message' => 'Accesso negato']);
        return;
    }

    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'opencomune_nonce')) {
        wp_send_json_error(['message' => 'Nonce non valido']);
        return;
    }

    $ids = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

    if ($ids === []) {
        wp_send_json_error(['message' => 'Nessun partner selezionato']);
        return;
    }

    $deleted = 0;
    $failed = 0;
    foreach ($ids as $partner_id) {
        $result = opencomune_delete_partner_record($partner_id);
        if (is_wp_error($result)) {
            $failed++;
        } else {
            $deleted++;
        }
    }

    wp_send_json_success([
        'message' => sprintf(
            /* translators: 1: deleted count, 2: failed count */
            __('Eliminati %1$d partner. Non eliminati: %2$d.', 'opencomune'),
            $deleted,
            $failed
        ),
        'deleted' => $deleted,
        'failed' => $failed,
    ]);
}

function opencomune_add_prenotazione_callback() {
    // Verifica nonce per sicurezza
    if (!wp_verify_nonce($_POST['_wpnonce'] ?? '', 'opencomune_nonce')) {
        wp_send_json_error(['message' => 'Nonce verification failed']);
        return;
    }
    
    $tour_id = intval($_POST['tour_id'] ?? 0);
    $event_id = intval($_POST['event_id'] ?? 0);
    $nome = sanitize_text_field($_POST['nome'] ?? '');
    $cognome = sanitize_text_field($_POST['cognome'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $participants = intval($_POST['participants'] ?? 1);
    
    // Validazione
    if (empty($nome) || empty($cognome) || empty($email) || !$tour_id || !$event_id) {
        wp_send_json_error(['message' => 'Dati mancanti o non validi']);
        return;
    }
    
    $slot = opencomune_resolve_booking_event($tour_id, $event_id, $participants);
    if (is_wp_error($slot)) {
        wp_send_json_error(['message' => $slot->get_error_message()]);
        return;
    }

    $prenotazione_id = opencomune_insert_tour_prenotazione_row([
        'tour_id' => $tour_id,
        'event_id' => $event_id,
        'booking_date' => $slot['booking_date'],
        'booking_time' => $slot['booking_time'],
        'rule_id' => $slot['rule_id'],
        'nome' => $nome . ' ' . $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'participants' => $participants,
        'status' => 'confermata',
    ]);

    if (!$prenotazione_id) {
        wp_send_json_error(['message' => 'Errore nel salvataggio della prenotazione']);
        return;
    }

    opencomune_after_booking_decrement_legacy($tour_id, $event_id, $participants, $slot);

    $tour = get_post($tour_id);
    $subject = 'Conferma prenotazione - ' . $tour->post_title;
    $message = "Gentile $nome $cognome,\n\n";
    $message .= "La tua prenotazione per l'esperienza \"" . $tour->post_title . "\" è stata confermata.\n\n";
    $message .= "Dettagli:\n";
    $message .= "- Data: " . $slot['email_date'] . "\n";
    $message .= "- Ora: " . $slot['email_time'] . "\n";
    $message .= "- Partecipanti: $participants\n\n";
    $message .= "Grazie per aver scelto le nostre esperienze!\n\n";
    $message .= "Cordiali saluti,\nL'Ufficio Turistico";
    
    wp_mail($email, $subject, $message);
    
    wp_send_json_success(['message' => 'Prenotazione confermata con successo']);
}

// === AJAX: Elimina Esperienza ===
add_action('wp_ajax_opencomune_delete_esperienza', 'opencomune_delete_esperienza_callback');
function opencomune_delete_esperienza_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
    }
    
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        wp_send_json_error(['message' => 'ID esperienza non valido']);
    }

    if (!opencomune_user_can_edit_esperienza($id)) {
        wp_send_json_error(['message' => 'Non hai i permessi per eliminare questa esperienza']);
    }
    
    $result = wp_delete_post($id, true);
    
    if ($result) {
        wp_send_json_success(['message' => 'Esperienza eliminata con successo']);
    } else {
        wp_send_json_error(['message' => 'Errore durante l\'eliminazione']);
    }
}

// === AJAX: Restituisce tutti i tour pubblicati con lat/lon per la mappa ===
add_action('wp_ajax_opencomune_get_all_tours', 'opencomune_get_all_tours_callback');
add_action('wp_ajax_nopriv_opencomune_get_all_tours', 'opencomune_get_all_tours_callback');
function opencomune_get_all_tours_callback() {
    $args = [
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
    ];
    $query = new WP_Query($args);
    $tours = [];
    foreach ($query->posts as $post) {
        if (!opencomune_is_esperienza_upcoming($post->ID)) {
            continue;
        }

        $item = opencomune_build_tour_map_item($post->ID);
        if ($item) {
            unset($item['edit_url'], $item['status'], $item['status_label']);
            $tours[] = $item;
        }
    }
    wp_reset_postdata();
    wp_send_json_success($tours);
}

add_action('wp_ajax_opencomune_get_partner_tours_map', 'opencomune_get_partner_tours_map_callback');
function opencomune_get_partner_tours_map_callback() {
    if (!opencomune_user_can_access_dashboard()) {
        wp_send_json_error(['message' => 'Accesso negato']);
    }

    $args = [
        'post_type' => 'esperienze',
        'post_status' => ['publish', 'draft', 'pending'],
        'posts_per_page' => -1,
    ];

    if (!opencomune_user_can_manage_all_esperienze()) {
        $args['author'] = get_current_user_id();
    }

    $query = new WP_Query($args);
    $tours = [];

    foreach ($query->posts as $post) {
        $item = opencomune_build_tour_map_item($post->ID);
        if ($item) {
            $tours[] = $item;
        }
    }

    wp_reset_postdata();
    wp_send_json_success($tours);
}

function opencomune_decode_map_display_text($value, $strip_tags = false) {
    if (!is_string($value) || $value === '') {
        return '';
    }

    $decoded = wp_specialchars_decode($value, ENT_QUOTES);

    return $strip_tags ? wp_strip_all_tags($decoded) : $decoded;
}

/**
 * Markup card esperienza (stile home) per popup mappa pubblica.
 */
function opencomune_get_esperienza_map_popup_card_html($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    ob_start();
    echo '<div class="oc-home oc-map-popup-card">';
    get_template_part('template-parts/home', 'esperienza-card', [
        'post_id' => $post_id,
    ]);
    echo '</div>';

    return (string) ob_get_clean();
}

function opencomune_build_tour_map_item($post_id) {
    $post_id = (int) $post_id;
    $lat = get_post_meta($post_id, 'tour_latitude', true);
    $lon = get_post_meta($post_id, 'tour_longitude', true);

    if (!$lat || !$lon) {
        return null;
    }

    $tour_img = '';
    if (opencomune_esperienza_has_valid_thumbnail($post_id)) {
        $tour_img = get_the_post_thumbnail_url($post_id, 'medium');
    } else {
        $gallery_ids = get_post_meta($post_id, 'galleria', true);
        if (is_array($gallery_ids) && !empty($gallery_ids)) {
            $tour_img = wp_get_attachment_image_url($gallery_ids[0], 'medium');
        }
    }

    $categorie = wp_get_post_terms($post_id, 'categorie_esperienze', ['fields' => 'names']);
    $categorie_slugs = wp_get_post_terms($post_id, 'categorie_esperienze', ['fields' => 'slugs']);
    if (is_wp_error($categorie)) {
        $categorie = [];
    }
    if (is_wp_error($categorie_slugs)) {
        $categorie_slugs = [];
    }

    $author_id = (int) get_post_field('post_author', $post_id);
    $proposal_type = 'partner_territoriale';

    $status = get_post_status($post_id);
    $status_labels = [
        'publish' => 'Pubblicata',
        'draft' => 'Bozza',
        'pending' => 'In revisione',
    ];

    $schedule = opencomune_get_esperienza_schedule_dates($post_id);

    return [
        'id' => $post_id,
        'titolo' => opencomune_decode_map_display_text(get_the_title($post_id)),
        'desc_breve' => opencomune_decode_map_display_text(opencomune_get_esperienza_descrizione_breve($post_id), true),
        'categoria' => opencomune_decode_map_display_text(implode(', ', $categorie)),
        'categorie_array' => array_map('opencomune_decode_map_display_text', $categorie),
        'categorie_slugs' => $categorie_slugs,
        'lingue' => opencomune_decode_map_display_text(get_post_meta($post_id, 'tour_languages', true)),
        'citta' => opencomune_decode_map_display_text(get_post_meta($post_id, 'tour_address', true)),
        'lat' => $lat,
        'lon' => $lon,
        'link' => get_permalink($post_id),
        'edit_url' => opencomune_get_esperienza_wizard_url($post_id),
        'tour_img' => $tour_img,
        'tour_start_date' => $schedule['start'],
        'tour_end_date' => $schedule['end'],
        'tour_date_mode' => $schedule['mode'],
        'dates_label' => opencomune_format_esperienza_dates_label($post_id),
        'proposal_type' => $proposal_type,
        'status' => $status,
        'status_label' => $status_labels[$status] ?? $status,
        'popup_html' => opencomune_get_esperienza_map_popup_card_html($post_id),
    ];
}

// Theme Settings Page
function opencomune_theme_settings_page() {
    add_menu_page(
        'OpenComune Settings',
        'OpenComune',
        'manage_options',
        'opencomune-settings',
        'opencomune_theme_settings_page_content',
        'dashicons-admin-generic',
        30
    );
}
add_action('admin_menu', 'opencomune_theme_settings_page');

// Settings Page Content
function opencomune_theme_settings_page_content() {
    $settings_tabs = [
        'generale' => 'Generale',
        'contatti' => 'Ufficio turistico',
        'api' => 'API REST',
        'homepage' => 'Homepage',
        'email' => 'Email',
    ];

    $active_tab = 'generale';
    if (isset($_POST['opencomune_settings_tab'])) {
        $candidate = sanitize_key(wp_unslash($_POST['opencomune_settings_tab']));
        if (isset($settings_tabs[$candidate])) {
            $active_tab = $candidate;
        }
    } elseif (isset($_GET['tab'])) {
        $candidate = sanitize_key(wp_unslash($_GET['tab']));
        if (isset($settings_tabs[$candidate])) {
            $active_tab = $candidate;
        }
    }

    $email_test_notice = null;

    if (isset($_POST['opencomune_send_test_email'])) {
        if (isset($_POST['opencomune_test_email_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_test_email_nonce'])), 'opencomune_test_email')) {
            $test_email_to = sanitize_email(wp_unslash($_POST['test_email_to'] ?? ''));

            if (!is_email($test_email_to)) {
                $email_test_notice = [
                    'type' => 'error',
                    'message' => 'Inserisci un indirizzo email valido per il test.',
                ];
            } else {
                $test_result = opencomune_send_admin_test_email($test_email_to);

                if ($test_result['success']) {
                    $email_test_notice = [
                        'type' => 'success',
                        'message' => 'Email di test inviata con successo a ' . $test_email_to . '.',
                    ];
                } else {
                    $error_detail = $test_result['error'] !== '' ? ' Dettaglio: ' . $test_result['error'] : '';
                    $email_test_notice = [
                        'type' => 'error',
                        'message' => 'Invio email di test fallito.' . $error_detail,
                    ];
                }
            }
        } else {
            $email_test_notice = [
                'type' => 'error',
                'message' => 'Sessione scaduta. Ricarica la pagina e riprova l\'invio email di test.',
            ];
        }
    }

    $settings_notice = null;

    // Save settings if form is submitted
    if (isset($_POST['opencomune_save_settings'])) {
        if (isset($_POST['_wpnonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'opencomune_settings')) {
            update_option('opencomune_google_maps_api_key', sanitize_text_field($_POST['google_maps_api_key']));
            $tour_limit_post = isset($_POST['tour_limit']) ? max(1, (int) $_POST['tour_limit']) : 3;
            update_option('opencomune_tour_limit', $tour_limit_post);
            update_option('opencomune_debug_mode', isset($_POST['debug_mode']) ? '1' : '0');
            update_option('opencomune_openweather_api_key', sanitize_text_field($_POST['openweather_api_key']));
            update_option('opencomune_openweather_api_key_created', time());
            update_option('opencomune_turnstile_enabled', isset($_POST['turnstile_enabled']) ? '1' : '0');
            update_option('opencomune_turnstile_site_key', sanitize_text_field($_POST['turnstile_site_key'] ?? ''));
            $turnstile_secret_post = sanitize_text_field($_POST['turnstile_secret_key'] ?? '');
            if ($turnstile_secret_post !== '') {
                update_option('opencomune_turnstile_secret_key', $turnstile_secret_post);
            }
            $api_key_enabled = isset($_POST['api_key_enabled']) ? '1' : '0';
            update_option('opencomune_api_key_enabled', $api_key_enabled);
            if ($api_key_enabled === '1') {
                update_option('opencomune_api_key', sanitize_text_field($_POST['api_key'] ?? ''));
            }

            update_option('opencomune_nome_comune', sanitize_text_field($_POST['nome_comune'] ?? ''));
            update_option('opencomune_email_ufficio', sanitize_email($_POST['email_ufficio'] ?? ''));
            update_option('opencomune_telefono_ufficio', sanitize_text_field($_POST['telefono_ufficio'] ?? ''));
            update_option('opencomune_indirizzo_ufficio', sanitize_textarea_field($_POST['indirizzo_ufficio'] ?? ''));
            update_option('opencomune_orari_apertura', sanitize_textarea_field($_POST['orari_apertura'] ?? ''));
            update_option('opencomune_social_facebook', esc_url_raw($_POST['social_facebook'] ?? ''));
            update_option('opencomune_social_instagram', esc_url_raw($_POST['social_instagram'] ?? ''));
            
            // Campi swiper unificati homepage
            update_option('opencomune_swiper_slide_1_title', sanitize_text_field($_POST['swiper_slide_1_title'] ?? ''));
            update_option('opencomune_swiper_slide_1_text', sanitize_textarea_field($_POST['swiper_slide_1_text'] ?? ''));
            update_option('opencomune_swiper_slide_1_icon', sanitize_text_field($_POST['swiper_slide_1_icon'] ?? ''));
            update_option('opencomune_swiper_slide_1_image', esc_url_raw($_POST['swiper_slide_1_image'] ?? ''));
            update_option('opencomune_swiper_slide_1_link', esc_url_raw($_POST['swiper_slide_1_link'] ?? ''));
            update_option('opencomune_swiper_slide_2_title', sanitize_text_field($_POST['swiper_slide_2_title'] ?? ''));
            update_option('opencomune_swiper_slide_2_text', sanitize_textarea_field($_POST['swiper_slide_2_text'] ?? ''));
            update_option('opencomune_swiper_slide_2_icon', sanitize_text_field($_POST['swiper_slide_2_icon'] ?? ''));
            update_option('opencomune_swiper_slide_2_image', esc_url_raw($_POST['swiper_slide_2_image'] ?? ''));
            update_option('opencomune_swiper_slide_2_link', esc_url_raw($_POST['swiper_slide_2_link'] ?? ''));
            update_option('opencomune_swiper_slide_3_title', sanitize_text_field($_POST['swiper_slide_3_title'] ?? ''));
            update_option('opencomune_swiper_slide_3_text', sanitize_textarea_field($_POST['swiper_slide_3_text'] ?? ''));
            update_option('opencomune_swiper_slide_3_icon', sanitize_text_field($_POST['swiper_slide_3_icon'] ?? ''));
            update_option('opencomune_swiper_slide_3_image', esc_url_raw($_POST['swiper_slide_3_image'] ?? ''));
            update_option('opencomune_swiper_slide_3_link', esc_url_raw($_POST['swiper_slide_3_link'] ?? ''));
            
            $settings_notice = [
                'type' => 'success',
                'message' => 'Impostazioni salvate con successo!',
            ];
        } else {
            $settings_notice = [
                'type' => 'error',
                'message' => 'Sessione scaduta. Ricarica la pagina e riprova.',
            ];
        }
    }
    
    // Get current settings
    $api_key = get_option('opencomune_google_maps_api_key', '');
    $debug_mode = get_option('opencomune_debug_mode', '0');
    $rest_api_key = get_option('opencomune_api_key', '');
    $rest_api_key_enabled = opencomune_is_rest_api_key_enabled();
    $nome_comune = get_option('opencomune_nome_comune', '');
    $email_ufficio = get_option('opencomune_email_ufficio', '');
    $telefono_ufficio = get_option('opencomune_telefono_ufficio', '');
    $indirizzo_ufficio = get_option('opencomune_indirizzo_ufficio', '');
    $orari_apertura = get_option('opencomune_orari_apertura', '');
    $social_facebook = get_option('opencomune_social_facebook', '');
    $social_instagram = get_option('opencomune_social_instagram', '');
    $tour_limit = opencomune_get_tour_limit();
    $turnstile_enabled = get_option('opencomune_turnstile_enabled', '0');
    $turnstile_site_key = get_option('opencomune_turnstile_site_key', '');
    $turnstile_secret_key = get_option('opencomune_turnstile_secret_key', '');

    $settings_page_url = admin_url('admin.php?page=opencomune-settings');
    
    // Swiper unificato homepage
    $swiper_slide_1_title = get_option('opencomune_swiper_slide_1_title', 'Prenota esperienze locali');
    $swiper_slide_1_text = get_option('opencomune_swiper_slide_1_text', 'Scegli data e partecipanti dalla scheda. Il pagamento si conclude online.');
    $swiper_slide_1_icon = get_option('opencomune_swiper_slide_1_icon', 'bi-stars');
    $swiper_slide_1_image = get_option('opencomune_swiper_slide_1_image', 'https://betadev.it/explora/wp-content/uploads/2025/06/lecce_home.jpg');
    $swiper_slide_1_link = get_option('opencomune_swiper_slide_1_link', home_url('/mappa/'));
    $swiper_slide_2_title = get_option('opencomune_swiper_slide_2_title', 'Un catalogo per la destinazione');
    $swiper_slide_2_text = get_option('opencomune_swiper_slide_2_text', 'Schede con mappa, date e testi aggiornati. Li riusi su sito comunale, portali e campagne.');
    $swiper_slide_2_icon = get_option('opencomune_swiper_slide_2_icon', 'bi-search');
    $swiper_slide_2_image = get_option('opencomune_swiper_slide_2_image', 'https://betadev.it/explora/wp-content/uploads/2025/06/polignano.jpg');
    $swiper_slide_2_link = get_option('opencomune_swiper_slide_2_link', home_url('/mappa/'));
    $swiper_slide_3_title = get_option('opencomune_swiper_slide_3_title', 'Per guide e operatori locali');
    $swiper_slide_3_text = get_option('opencomune_swiper_slide_3_text', 'Pubblichi l\'esperienza, imposti calendario e prezzi. Le prenotazioni arrivano in dashboard.');
    $swiper_slide_3_icon = get_option('opencomune_swiper_slide_3_icon', 'bi-people');
    $swiper_slide_3_image = get_option('opencomune_swiper_slide_3_image', 'https://betadev.it/explora/wp-content/uploads/2025/06/vendemmia_puglia.jpeg');
    $swiper_slide_3_link = get_option('opencomune_swiper_slide_3_link', home_url('/registrazione-partner/'));
    $smtp_config = opencomune_get_smtp_config_summary();
    $test_email_to = isset($_POST['test_email_to']) ? sanitize_email(wp_unslash($_POST['test_email_to'])) : get_option('admin_email');
    if (!is_email($test_email_to)) {
        $test_email_to = get_option('admin_email');
    }
    ?>
    <div class="wrap opencomune-settings-wrap">
        <h1>OpenComune - Impostazioni Tema</h1>

        <?php if ($email_test_notice) : ?>
        <div class="notice notice-<?php echo $email_test_notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($email_test_notice['message']); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($settings_notice) : ?>
        <div class="notice notice-<?php echo $settings_notice['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
            <p><?php echo esc_html($settings_notice['message']); ?></p>
        </div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper wp-clearfix" aria-label="Sezioni impostazioni">
            <?php foreach ($settings_tabs as $tab_slug => $tab_label) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_slug, $settings_page_url)); ?>"
                   class="nav-tab<?php echo $active_tab === $tab_slug ? ' nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?php echo esc_url(add_query_arg('tab', $active_tab, $settings_page_url)); ?>">
            <?php wp_nonce_field('opencomune_settings'); ?>
            <input type="hidden" name="opencomune_settings_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div class="opencomune-settings-tab-panel"<?php echo $active_tab !== 'generale' ? ' hidden' : ''; ?> data-tab="generale">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="google_maps_api_key">Google Maps API Key</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="google_maps_api_key" 
                               name="google_maps_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text">
                        <p class="description">
                            Enter your Google Maps API key. This key will be used across the site for maps functionality.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="openweather_api_key">OpenWeather API Key</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="openweather_api_key" 
                               name="openweather_api_key" 
                               value="<?php echo esc_attr(get_option('opencomune_openweather_api_key', '')); ?>" 
                               class="regular-text">
                        <p class="description">
                            Inserisci la tua chiave API di OpenWeather per abilitare il widget meteo. <a href="https://openweathermap.org/api" target="_blank">Ottieni la chiave gratuita</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="tour_limit">Tour Limit</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="tour_limit" 
                               name="tour_limit" 
                               value="<?php echo esc_attr($tour_limit); ?>" 
                               class="regular-text"
                               min="1">
                        <p class="description">
                            Maximum number of tours that can be created without a subscription.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="debug_mode">Debug Mode</label>
                    </th>
                    <td>
                        <input type="checkbox" 
                               id="debug_mode" 
                               name="debug_mode" 
                               value="1" 
                               <?php checked($debug_mode, '1'); ?>>
                        <p class="description">
                            Enable debug mode for development and testing purposes.
                        </p>
                    </td>
                </tr>
            </table>

            <h2 class="title"><?php esc_html_e('Cloudflare Turnstile', 'opencomune'); ?></h2>
            <p class="description">
                <?php esc_html_e('Protezione anti-spam per moduli pubblici (registrazione partner, contatti, ecc.). Crea le chiavi nel dashboard Cloudflare Turnstile.', 'opencomune'); ?>
                <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Apri Cloudflare Turnstile', 'opencomune'); ?></a>
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Abilita Turnstile', 'opencomune'); ?></th>
                    <td>
                        <label for="turnstile_enabled">
                            <input type="checkbox"
                                   id="turnstile_enabled"
                                   name="turnstile_enabled"
                                   value="1"
                                   <?php checked($turnstile_enabled, '1'); ?>>
                            <?php esc_html_e('Richiedi verifica Turnstile sui form protetti', 'opencomune'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="turnstile_site_key"><?php esc_html_e('Site Key (pubblica)', 'opencomune'); ?></label>
                    </th>
                    <td>
                        <input type="text"
                               id="turnstile_site_key"
                               name="turnstile_site_key"
                               value="<?php echo esc_attr($turnstile_site_key); ?>"
                               class="regular-text"
                               autocomplete="off">
                        <p class="description"><?php esc_html_e('Chiave del widget mostrata nel frontend.', 'opencomune'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="turnstile_secret_key"><?php esc_html_e('Secret Key', 'opencomune'); ?></label>
                    </th>
                    <td>
                        <input type="password"
                               id="turnstile_secret_key"
                               name="turnstile_secret_key"
                               value=""
                               class="regular-text"
                               autocomplete="new-password"
                               placeholder="<?php echo $turnstile_secret_key !== '' ? esc_attr__('Lascia vuoto per mantenere la chiave attuale', 'opencomune') : ''; ?>">
                        <?php if ($turnstile_secret_key !== '') : ?>
                            <p class="description"><?php esc_html_e('Secret key salvata. Inserisci un nuovo valore solo per sostituirla.', 'opencomune'); ?></p>
                        <?php else : ?>
                            <p class="description"><?php esc_html_e('Chiave segreta per la verifica lato server.', 'opencomune'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit"
                       name="opencomune_save_settings"
                       class="button button-primary"
                       value="Salva Impostazioni">
            </p>
            </div>

            <div class="opencomune-settings-tab-panel"<?php echo $active_tab !== 'contatti' ? ' hidden' : ''; ?> data-tab="contatti">
            <p class="description" style="margin: 1em 0;">Dati mostrati nel footer del sito, sezione CONTATTI.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="nome_comune">Nome Comune</label>
                    </th>
                    <td>
                        <input type="text"
                               id="nome_comune"
                               name="nome_comune"
                               value="<?php echo esc_attr($nome_comune); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_ufficio">Email Ufficio</label>
                    </th>
                    <td>
                        <input type="email"
                               id="email_ufficio"
                               name="email_ufficio"
                               value="<?php echo esc_attr($email_ufficio); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="telefono_ufficio">Telefono Ufficio</label>
                    </th>
                    <td>
                        <input type="text"
                               id="telefono_ufficio"
                               name="telefono_ufficio"
                               value="<?php echo esc_attr($telefono_ufficio); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="indirizzo_ufficio">Indirizzo Ufficio</label>
                    </th>
                    <td>
                        <textarea id="indirizzo_ufficio"
                                  name="indirizzo_ufficio"
                                  rows="3"
                                  class="large-text"><?php echo esc_textarea($indirizzo_ufficio); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="orari_apertura">Orari Apertura</label>
                    </th>
                    <td>
                        <textarea id="orari_apertura"
                                  name="orari_apertura"
                                  rows="3"
                                  class="large-text"><?php echo esc_textarea($orari_apertura); ?></textarea>
                        <p class="description">Esempio: da Lunedì a Sabato 8.00 - 14.00; 15:00 - 17:00</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="social_facebook">Facebook</label>
                    </th>
                    <td>
                        <input type="url"
                               id="social_facebook"
                               name="social_facebook"
                               value="<?php echo esc_attr($social_facebook); ?>"
                               class="regular-text"
                               placeholder="https://www.facebook.com/...">
                        <p class="description">Icona nella barra arancione in cima al sito pubblico.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="social_instagram">Instagram</label>
                    </th>
                    <td>
                        <input type="url"
                               id="social_instagram"
                               name="social_instagram"
                               value="<?php echo esc_attr($social_instagram); ?>"
                               class="regular-text"
                               placeholder="https://www.instagram.com/...">
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit"
                       name="opencomune_save_settings"
                       class="button button-primary"
                       value="Salva Impostazioni">
            </p>
            </div>

            <div class="opencomune-settings-tab-panel"<?php echo $active_tab !== 'api' ? ' hidden' : ''; ?> data-tab="api">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="api_key_enabled">API Key (opzionale)</label>
                    </th>
                    <td>
                        <label for="api_key_enabled" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                            <input type="checkbox"
                                   id="api_key_enabled"
                                   name="api_key_enabled"
                                   value="1"
                                   <?php checked($rest_api_key_enabled, true); ?>>
                            Usa API Key
                        </label>

                        <div id="opencomune-api-key-fields"<?php echo $rest_api_key_enabled ? '' : ' style="display:none;"'; ?>>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 8px;">
                                <input type="text"
                                       id="api_key"
                                       name="api_key"
                                       value="<?php echo esc_attr($rest_api_key); ?>"
                                       class="regular-text"
                                       autocomplete="off">
                                <button type="button"
                                        id="opencomune-generate-api-key"
                                        class="button button-secondary">
                                    Genera API Key
                                </button>
                            </div>
                            <p class="description">
                                Se abilitata, le API REST richiedono l'header <code>X-OpenComune-API-Key</code> oppure il parametro <code>api_key</code>.
                                Endpoint base: <code><?php echo esc_html(rest_url('opencomune/v1/esperienze')); ?></code><br>
                                Swagger UI: <a href="<?php echo esc_url(home_url('/api/swagger')); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html(home_url('/api/swagger')); ?></a><br>
                                Esempi localizzazione (v1.2.0):
                                <code><?php echo esc_html(rest_url('opencomune/v1/esperienze')); ?>?lang=en</code>,
                                <code><?php echo esc_html(rest_url('opencomune/v1/esperienze/123')); ?>?lang=en&amp;include_translations=1</code>,
                                <code><?php echo esc_html(rest_url('opencomune/v1/categorie-esperienze')); ?>?lang=en</code>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit"
                       name="opencomune_save_settings"
                       class="button button-primary"
                       value="Salva Impostazioni">
            </p>
            </div>

            <div class="opencomune-settings-tab-panel"<?php echo $active_tab !== 'homepage' ? ' hidden' : ''; ?> data-tab="homepage">
            <p class="description" style="margin: 1em 0;">Configurazione unica per entrambi gli swiper: Hero (fullscreen) e Box (sotto la ricerca).</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_1_title">Slide 1 - Titolo</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_1_title" 
                               name="swiper_slide_1_title" 
                               value="<?php echo esc_attr($swiper_slide_1_title); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_1_text">Slide 1 - Testo</label>
                    </th>
                    <td>
                        <textarea id="swiper_slide_1_text" 
                                  name="swiper_slide_1_text" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($swiper_slide_1_text); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_1_icon">Slide 1 - Icona (Bootstrap Icons)</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_1_icon" 
                               name="swiper_slide_1_icon" 
                               value="<?php echo esc_attr($swiper_slide_1_icon); ?>" 
                               class="regular-text"
                               placeholder="bi-stars">
                        <p class="description">Nome dell'icona Bootstrap Icons per il box swiper (es: bi-stars, bi-search, bi-people)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_1_image">Slide 1 - Immagine Hero</label>
                    </th>
                    <td>
                        <div class="swiper-image-upload">
                            <input type="hidden" 
                                   id="swiper_slide_1_image" 
                                   name="swiper_slide_1_image" 
                                   value="<?php echo esc_attr($swiper_slide_1_image); ?>" 
                                   class="regular-text">
                            <div class="image-preview" style="margin-bottom: 10px;">
                                <?php if ($swiper_slide_1_image): ?>
                                    <img src="<?php echo esc_url($swiper_slide_1_image); ?>" style="max-width: 200px; height: 100px; object-fit: cover; border-radius: 4px;" />
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button upload-image-button" data-target="swiper_slide_1_image">Seleziona Immagine</button>
                            <button type="button" class="button remove-image-button" data-target="swiper_slide_1_image" style="margin-left: 10px; <?php echo $swiper_slide_1_image ? '' : 'display: none;'; ?>">Rimuovi</button>
                        </div>
                        <p class="description">Immagine di sfondo per lo swiper hero fullscreen</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_1_link">Slide 1 - Link Bottone</label>
                    </th>
                    <td>
                        <input type="url" 
                               id="swiper_slide_1_link" 
                               name="swiper_slide_1_link" 
                               value="<?php echo esc_attr($swiper_slide_1_link); ?>" 
                               class="regular-text"
                               placeholder="#">
                        <p class="description">URL del link per il bottone "Scopri di più"</p>
                    </td>
                </tr>
                
                <!-- Slide 2 -->
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_2_title">Slide 2 - Titolo</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_2_title" 
                               name="swiper_slide_2_title" 
                               value="<?php echo esc_attr($swiper_slide_2_title); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_2_text">Slide 2 - Testo</label>
                    </th>
                    <td>
                        <textarea id="swiper_slide_2_text" 
                                  name="swiper_slide_2_text" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($swiper_slide_2_text); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_2_icon">Slide 2 - Icona (Bootstrap Icons)</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_2_icon" 
                               name="swiper_slide_2_icon" 
                               value="<?php echo esc_attr($swiper_slide_2_icon); ?>" 
                               class="regular-text"
                               placeholder="bi-search">
                        <p class="description">Nome dell'icona Bootstrap Icons per il box swiper (es: bi-stars, bi-search, bi-people)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_2_image">Slide 2 - Immagine Hero</label>
                    </th>
                    <td>
                        <div class="swiper-image-upload">
                            <input type="hidden" 
                                   id="swiper_slide_2_image" 
                                   name="swiper_slide_2_image" 
                                   value="<?php echo esc_attr($swiper_slide_2_image); ?>" 
                                   class="regular-text">
                            <div class="image-preview" style="margin-bottom: 10px;">
                                <?php if ($swiper_slide_2_image): ?>
                                    <img src="<?php echo esc_url($swiper_slide_2_image); ?>" style="max-width: 200px; height: 100px; object-fit: cover; border-radius: 4px;" />
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button upload-image-button" data-target="swiper_slide_2_image">Seleziona Immagine</button>
                            <button type="button" class="button remove-image-button" data-target="swiper_slide_2_image" style="margin-left: 10px; <?php echo $swiper_slide_2_image ? '' : 'display: none;'; ?>">Rimuovi</button>
                        </div>
                        <p class="description">Immagine di sfondo per lo swiper hero fullscreen</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_2_link">Slide 2 - Link Bottone</label>
                    </th>
                    <td>
                        <input type="url" 
                               id="swiper_slide_2_link" 
                               name="swiper_slide_2_link" 
                               value="<?php echo esc_attr($swiper_slide_2_link); ?>" 
                               class="regular-text"
                               placeholder="#">
                        <p class="description">URL del link per il bottone "Scopri di più"</p>
                    </td>
                </tr>
                
                <!-- Slide 3 -->
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_3_title">Slide 3 - Titolo</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_3_title" 
                               name="swiper_slide_3_title" 
                               value="<?php echo esc_attr($swiper_slide_3_title); ?>" 
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_3_text">Slide 3 - Testo</label>
                    </th>
                    <td>
                        <textarea id="swiper_slide_3_text" 
                                  name="swiper_slide_3_text" 
                                  rows="3" 
                                  class="large-text"><?php echo esc_textarea($swiper_slide_3_text); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_3_icon">Slide 3 - Icona (Bootstrap Icons)</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="swiper_slide_3_icon" 
                               name="swiper_slide_3_icon" 
                               value="<?php echo esc_attr($swiper_slide_3_icon); ?>" 
                               class="regular-text"
                               placeholder="bi-people">
                        <p class="description">Nome dell'icona Bootstrap Icons per il box swiper (es: bi-stars, bi-search, bi-people)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_3_image">Slide 3 - Immagine Hero</label>
                    </th>
                    <td>
                        <div class="swiper-image-upload">
                            <input type="hidden" 
                                   id="swiper_slide_3_image" 
                                   name="swiper_slide_3_image" 
                                   value="<?php echo esc_attr($swiper_slide_3_image); ?>" 
                                   class="regular-text">
                            <div class="image-preview" style="margin-bottom: 10px;">
                                <?php if ($swiper_slide_3_image): ?>
                                    <img src="<?php echo esc_url($swiper_slide_3_image); ?>" style="max-width: 200px; height: 100px; object-fit: cover; border-radius: 4px;" />
                                <?php endif; ?>
                            </div>
                            <button type="button" class="button upload-image-button" data-target="swiper_slide_3_image">Seleziona Immagine</button>
                            <button type="button" class="button remove-image-button" data-target="swiper_slide_3_image" style="margin-left: 10px; <?php echo $swiper_slide_3_image ? '' : 'display: none;'; ?>">Rimuovi</button>
                        </div>
                        <p class="description">Immagine di sfondo per lo swiper hero fullscreen</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="swiper_slide_3_link">Slide 3 - Link Bottone</label>
                    </th>
                    <td>
                        <input type="url" 
                               id="swiper_slide_3_link" 
                               name="swiper_slide_3_link" 
                               value="<?php echo esc_attr($swiper_slide_3_link); ?>" 
                               class="regular-text"
                               placeholder="#">
                        <p class="description">URL del link per il bottone "Scopri di più"</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit"
                       name="opencomune_save_settings"
                       class="button button-primary"
                       value="Salva Impostazioni">
            </p>
            </div>

            <div class="opencomune-settings-tab-panel"<?php echo $active_tab !== 'email' ? ' hidden' : ''; ?> data-tab="email">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Configurazione attuale</th>
                    <td>
                        <?php if ($smtp_config['enabled']) : ?>
                            <p style="margin: 0 0 8px;"><strong>Stato:</strong> SMTP attivo</p>
                            <ul style="margin: 0; list-style: disc; padding-left: 20px;">
                                <li><strong>Server:</strong> <?php echo esc_html($smtp_config['host']); ?></li>
                                <li><strong>Porta:</strong> <?php echo esc_html((string) $smtp_config['port']); ?> (<?php echo esc_html(strtoupper($smtp_config['secure'])); ?>)</li>
                                <li><strong>Account:</strong> <?php echo esc_html($smtp_config['username']); ?></li>
                                <li><strong>Mittente:</strong> <?php echo esc_html($smtp_config['from']); ?></li>
                                <li><strong>Nome mittente:</strong> <?php echo esc_html($smtp_config['from_name']); ?></li>
                            </ul>
                            <p class="description">Le credenziali SMTP sono configurate in <code>wp-config.php</code>.</p>
                        <?php else : ?>
                            <p style="color: #b32d2e; margin: 0;"><strong>SMTP non configurato.</strong> Definisci le costanti MAIL_* in <code>wp-config.php</code>.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
            </div>
        </form>

        <?php if ($active_tab === 'email') : ?>
        <form method="post" action="<?php echo esc_url(add_query_arg('tab', 'email', $settings_page_url)); ?>" style="max-width: 800px; margin-top: 10px;">
            <?php wp_nonce_field('opencomune_test_email', 'opencomune_test_email_nonce'); ?>
            <input type="hidden" name="opencomune_settings_tab" value="email">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="test_email_to">Invia email di test a</label>
                    </th>
                    <td>
                        <input type="email"
                               id="test_email_to"
                               name="test_email_to"
                               value="<?php echo esc_attr($test_email_to); ?>"
                               class="regular-text">
                        <p class="description">Viene inviata un'email di prova usando la configurazione SMTP del sito (registrazioni partner, prenotazioni, ecc.).</p>
                        <p style="margin-top: 12px;">
                            <input type="submit"
                                   name="opencomune_send_test_email"
                                   class="button button-secondary"
                                   value="Invia email di test">
                        </p>
                    </td>
                </tr>
            </table>
        </form>
        <?php endif; ?>
    </div>

    <style>
        .opencomune-settings-wrap .nav-tab-wrapper {
            margin-bottom: 0;
        }
        .opencomune-settings-wrap .opencomune-settings-tab-panel {
            margin-top: 1em;
            max-width: 960px;
        }
        .opencomune-settings-wrap .opencomune-settings-tab-panel[hidden] {
            display: none !important;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        function toggleApiKeyFields() {
            const enabled = $('#api_key_enabled').is(':checked');
            $('#opencomune-api-key-fields').toggle(enabled);
        }

        $('#api_key_enabled').on('change', toggleApiKeyFields);

        $('#opencomune-generate-api-key').on('click', function(e) {
            e.preventDefault();

            const bytes = new Uint8Array(24);
            window.crypto.getRandomValues(bytes);
            const generatedKey = Array.from(bytes, function(byte) {
                return byte.toString(16).padStart(2, '0');
            }).join('');

            $('#api_key').val(generatedKey);
            $('#api_key_enabled').prop('checked', true);
            toggleApiKeyFields();
        });

        // Media uploader per le immagini swiper
        $('.upload-image-button').click(function(e) {
            e.preventDefault();
            
            var button = $(this);
            var targetInput = button.data('target');
            var previewContainer = button.siblings('.image-preview');
            var removeButton = button.siblings('.remove-image-button');
            
            // Crea il media uploader
            var mediaUploader = wp.media({
                title: 'Seleziona Immagine',
                button: {
                    text: 'Usa questa immagine'
                },
                multiple: false
            });
            
            // Quando viene selezionata un'immagine
            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                
                // Aggiorna il campo hidden
                $('#' + targetInput).val(attachment.url);
                
                // Aggiorna l'anteprima
                previewContainer.html('<img src="' + attachment.url + '" style="max-width: 200px; height: 100px; object-fit: cover; border-radius: 4px;" />');
                
                // Mostra il pulsante rimuovi
                removeButton.show();
            });
            
            // Apri il media uploader
            mediaUploader.open();
        });
        
        // Rimuovi immagine
        $('.remove-image-button').click(function(e) {
            e.preventDefault();
            
            var button = $(this);
            var targetInput = button.data('target');
            var previewContainer = button.siblings('.image-preview');
            
            // Pulisci il campo
            $('#' + targetInput).val('');
            
            // Pulisci l'anteprima
            previewContainer.empty();
            
            // Nascondi il pulsante rimuovi
            button.hide();
        });
    });
    </script>
    <?php
}

// Function to get Google Maps API key
function opencomune_get_google_maps_api_key() {
    return get_option('opencomune_google_maps_api_key', '');
}

function opencomune_is_rest_api_key_enabled() {
    $enabled = get_option('opencomune_api_key_enabled', null);
    if ($enabled === null) {
        return get_option('opencomune_api_key', '') !== '';
    }

    return $enabled === '1';
}

function opencomune_is_rest_api_key_required() {
    if (!opencomune_is_rest_api_key_enabled()) {
        return false;
    }

    return get_option('opencomune_api_key', '') !== '';
}

function opencomune_get_rest_api_key() {
    return get_option('opencomune_api_key', '');
}

// Function to get tour limit
function opencomune_get_tour_limit() {
    // Default limit for non-subscribed users
    $default_limit = 3;
    
    // Get limit from options
    $limit = get_option('opencomune_tour_limit', $default_limit);
    
    // Ensure limit is at least 1
    return max(1, intval($limit));
}

function opencomune_check_tour_limit($user_id = null) {
    if (!$user_id) {
        $user_id = get_current_user_id();
    }
    
    // Check subscription first
    $sub = opencomune_handle_subscription($user_id);
    if ($sub['active']) {
        return true; // No limit for subscribed users
    }
    
    // Count user's tours
    global $wpdb;
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'esperienze' 
        AND post_author = %d 
        AND post_status IN ('publish', 'draft')",
        $user_id
    ));
    
    return $count < opencomune_get_tour_limit();
}

// Function to check if debug mode is enabled
function opencomune_is_debug_mode() {
    return get_option('opencomune_debug_mode', '0') === '1';
}

/**
 * Whether a published esperienza matches the home search string (title, city, short or full description).
 */
function opencomune_esperienza_matches_home_search($post_id, $query, $post = null) {
    $query = trim((string) $query);
    if ($query === '') {
        return false;
    }
    if (!$post instanceof WP_Post) {
        $post = get_post($post_id);
    }
    if (!$post || $post->post_type !== 'esperienze') {
        return false;
    }

    if (stripos($post->post_title, $query) !== false) {
        return true;
    }

    $citta = get_post_meta($post_id, 'citta', true);
    if (is_string($citta) && $citta !== '' && stripos($citta, $query) !== false) {
        return true;
    }

    $breve = function_exists('opencomune_get_esperienza_descrizione_breve')
        ? opencomune_get_esperienza_descrizione_breve($post_id)
        : $post->post_excerpt;
    if (is_string($breve) && $breve !== '' && stripos($breve, $query) !== false) {
        return true;
    }

    $content_plain = wp_strip_all_tags($post->post_content);
    if ($content_plain !== '' && stripos($content_plain, $query) !== false) {
        return true;
    }

    return false;
}

add_action('wp_ajax_opencomune_search_tour_autocomplete', 'opencomune_search_tour_autocomplete');
add_action('wp_ajax_nopriv_opencomune_search_tour_autocomplete', 'opencomune_search_tour_autocomplete');
function opencomune_search_tour_autocomplete() {
    $q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
    $results = [];
    if ($q !== '') {
        $args = [
            'post_type' => ['esperienze'],
            'posts_per_page' => 20,
            'post_status' => 'publish',
            's' => $q,
            'fields' => 'ids',
        ];
        $query = new WP_Query($args);
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            if (!opencomune_esperienza_matches_home_search($post_id, $q, $post)) {
                continue;
            }
            if ($post) {
                $citta = get_post_meta($post_id, 'citta', true);
                $img = get_the_post_thumbnail_url($post_id, 'thumbnail');
                if ($img === false || $img === '') {
                    $img = function_exists('opencomune_get_esperienza_thumbnail_url')
                        ? opencomune_get_esperienza_thumbnail_url($post_id, 'thumbnail')
                        : '';
                }
                $meta = opencomune_get_home_esperienza_card_meta($post_id);
                $results[] = [
                    'title' => get_the_title($post_id),
                    'type' => 'Tour',
                    'location' => $citta,
                    'link' => get_permalink($post_id),
                    'img' => $img !== '' ? $img : '',
                    'price' => $meta['price_label'],
                ];
                if (count($results) >= 7) break;
            }
        }
    }
    wp_send_json_success($results);
}

add_action('admin_menu', function() {
    add_submenu_page(
        'edit.php?post_type=esperienze',
        'Gestisci Commenti',
        'Gestisci Commenti',
        'manage_options',
        'tour-comments',
        'opencomune_tour_comments_page'
    );
});

function opencomune_tour_comments_page() {
    ?>
    <div class="wrap">
        <h1>Gestisci Commenti Tour</h1>
        <?php
        $comments = get_comments(['post_type' => 'esperienze']);
        if ($comments): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Autore</th>
                        <th>Commento</th>
                        <th>Stato</th>
                        <th>Azioni</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comments as $comment): ?>
                        <tr>
                            <td><?php echo esc_html($comment->comment_author); ?></td>
                            <td><?php echo esc_html($comment->comment_content); ?></td>
                            <td><?php echo esc_html($comment->comment_approved); ?></td>
                            <td>
                                <a href="<?php echo admin_url('comment.php?action=editcomment&c=' . $comment->comment_ID); ?>">Modifica</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>Nessun commento trovato.</p>
        <?php endif; ?>
    </div>
    <?php
}

function enable_comments_for_tours($post_ID) {
    $post_type = get_post_type($post_ID);
    if ($post_type == 'esperienze') {
        if (!get_post_meta($post_ID, '_comment_status_set', true)) {
            // Use direct database update instead of wp_update_post to avoid infinite loop
            global $wpdb;
            $wpdb->update(
                $wpdb->posts,
                array('comment_status' => 'open'),
                array('ID' => $post_ID),
                array('%s'),
                array('%d')
            );
            update_post_meta($post_ID, '_comment_status_set', true);
        }
    }
}
add_action('save_post', 'enable_comments_for_tours');

add_action('wp_ajax_opencomune_submit_review', 'opencomune_submit_review_handler');
function opencomune_submit_review_handler() {
    error_log('AJAX review POST: ' . print_r($_POST, true));
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Devi essere loggato per lasciare una recensione']);
    }
    if (!isset($_POST['review_post_id'], $_POST['review_rating'], $_POST['review_comment'], $_POST['opencomune_review_nonce_field']) ||
        !wp_verify_nonce($_POST['opencomune_review_nonce_field'], 'opencomune_review_nonce')) {
        wp_send_json_error(['message' => 'Errore di sicurezza']);
    }
    $commentdata = [
        'comment_post_ID' => intval($_POST['review_post_id']),
        'comment_content' => sanitize_textarea_field($_POST['review_comment']),
        'user_id'         => get_current_user_id(),
        'comment_author'  => wp_get_current_user()->display_name,
        'comment_author_email' => wp_get_current_user()->user_email,
        'comment_approved' => 0,
    ];
    $comment_id = wp_insert_comment($commentdata);
    if ($comment_id) {
        add_comment_meta($comment_id, 'review_rating', intval($_POST['review_rating']));
        wp_send_json_success([
            'message' => 'Recensione inviata con successo!',
            'redirect' => get_permalink(intval($_POST['review_post_id'])) . '?review=ok#reviews-list'
        ]);
    } else {
        wp_send_json_error(['message' => 'Errore durante l\'inserimento del commento']);
    }
}

// Aumenta il timeout per le operazioni AJAX
add_filter('http_request_timeout', function($timeout) {
    return 120; // 2 minuti
});

// Ottimizza il salvataggio dei tour
function opencomune_save_tour($post_id, $post_data) {
    // Disabilita temporaneamente alcuni hook per migliorare le performance
    remove_action('save_post', 'opencomune_save_tour_meta');
    
    // Aggiorna i meta in batch
    $meta_updates = [];
    foreach ($post_data as $key => $value) {
        if (strpos($key, 'meta_') === 0) {
            $meta_key = substr($key, 5);
            $meta_updates[$meta_key] = $value;
        }
    }
    
    // Esegui un unico update per tutti i meta
    if (!empty($meta_updates)) {
        foreach ($meta_updates as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }
    
    // Riattiva l'hook
    add_action('save_post', 'opencomune_save_tour_meta');
    
    return true;
}

// Ottimizza la query per il conteggio dei tour
function opencomune_get_tour_count($user_id) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} 
        WHERE post_type = 'esperienze' 
        AND post_author = %d 
        AND post_status IN ('publish', 'draft')",
        $user_id
    ));
}

// Ottimizza il salvataggio delle immagini
function opencomune_handle_tour_images($post_id, $images) {
    if (empty($images)) return;
    
    // Imposta un timeout più lungo per l'elaborazione delle immagini
    set_time_limit(120);
    
    // Elabora le immagini in batch
    $image_ids = [];
    foreach ($images as $image) {
        if (isset($image['id'])) {
            $image_ids[] = $image['id'];
        }
    }
    
    // Aggiorna la galleria in un'unica operazione
    if (!empty($image_ids)) {
        update_post_meta($post_id, 'galleria', $image_ids);
    }
}

// Ottimizza il caricamento delle immagini
add_filter('wp_handle_upload_prefilter', function($file) {
    // Limita la dimensione massima delle immagini
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        $file['error'] = 'L\'immagine è troppo grande. Dimensione massima: 2MB';
    }
    return $file;
});

// Funzioni AJAX per Select2
function search_categorie_callback() {
    if (!check_ajax_referer('search_categorie_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    global $wpdb;
    $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    
    // Cerca solo nella tassonomia personalizzata categorie_tour
    // Restituisce i nomi invece degli ID per compatibilità con Select2
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT t.name as id, t.name as text 
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
         WHERE tt.taxonomy = 'categorie_tour'
         AND t.name LIKE %s
         ORDER BY t.name ASC
         LIMIT 10",
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    wp_send_json($results);
}
add_action('wp_ajax_search_categorie', 'search_categorie_callback');
add_action('wp_ajax_nopriv_search_categorie', 'search_categorie_callback');

// Funzione per creare automaticamente nuove categorie
function create_categoria_callback() {
    if (!check_ajax_referer('create_categoria_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $categoria_name = isset($_POST['categoria']) ? sanitize_text_field($_POST['categoria']) : '';
    
    if (empty($categoria_name)) {
        wp_send_json_error('Nome categoria non specificato');
        return;
    }
    
    // Verifica se la categoria esiste già
    $existing_term = get_term_by('name', $categoria_name, 'categorie_tour');
    if ($existing_term) {
        wp_send_json_success([
            'id' => $categoria_name,
            'text' => $categoria_name,
            'message' => 'Categoria già esistente'
        ]);
        return;
    }
    
    // Crea la nuova categoria
    $result = wp_insert_term($categoria_name, 'categorie_tour');
    
    if (is_wp_error($result)) {
        wp_send_json_error('Errore nella creazione della categoria: ' . $result->get_error_message());
        return;
    }
    
    wp_send_json_success([
        'id' => $categoria_name,
        'text' => $categoria_name,
        'message' => 'Categoria creata con successo'
    ]);
}
add_action('wp_ajax_create_categoria', 'create_categoria_callback');
add_action('wp_ajax_nopriv_create_categoria', 'create_categoria_callback');

function search_lingue_callback() {
    if (!check_ajax_referer('search_lingue_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    global $wpdb;
    $search = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT t.term_id as id, t.name as text 
         FROM {$wpdb->terms} t
         INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
         WHERE tt.taxonomy = 'lingua'
         AND t.name LIKE %s
         LIMIT 10",
        '%' . $wpdb->esc_like($search) . '%'
    ));
    
    wp_send_json($results);
}
add_action('wp_ajax_search_lingue', 'search_lingue_callback');
add_action('wp_ajax_nopriv_search_lingue', 'search_lingue_callback');

// Funzione per ottenere l'ID della città dal database
function get_citta_id_callback() {
    if (!check_ajax_referer('get_citta_id_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    global $wpdb;
    $citta = isset($_GET['citta']) ? sanitize_text_field($_GET['citta']) : '';
    
    if (empty($citta)) {
        wp_send_json_error('Città non specificata');
        return;
    }
    
    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT id 
         FROM {$wpdb->prefix}myguide_comuni 
         WHERE nome = %s
         LIMIT 1",
        $citta
    ));
    
    if ($result) {
        wp_send_json_success($result->id);
    } else {
        wp_send_json_error('Città non trovata');
    }
}
add_action('wp_ajax_get_citta_id', 'get_citta_id_callback');
add_action('wp_ajax_nopriv_get_citta_id', 'get_citta_id_callback');

// Funzione per gestire le prenotazioni dei tour
function opencomune_prenota_tour_callback() {
    if (!check_ajax_referer('prenota_tour_nonce', 'nonce', false)) {
        wp_send_json_error('Invalid nonce');
        return;
    }
    
    $tour_id = intval($_POST['tour_id'] ?? 0);
    $data = sanitize_text_field($_POST['data'] ?? '');
    $orario = sanitize_text_field($_POST['orario'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $nome = sanitize_text_field($_POST['nome'] ?? '');
    $cognome = sanitize_text_field($_POST['cognome'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $prefisso = sanitize_text_field($_POST['prefisso'] ?? '+39');
    $numero_telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $telefono = $prefisso . ' ' . $numero_telefono;
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $metodo_pagamento = sanitize_text_field($_POST['metodo_pagamento'] ?? 'guida');
    
    // Validazione
    if (empty($tour_id) || empty($data) || empty($orario) || empty($nome) || empty($cognome) || empty($email) || empty($numero_telefono)) {
        wp_send_json_error('Dati mancanti');
        return;
    }
    
    // Validazione numero di telefono
    if (!preg_match('/^[0-9\s\-\(\)]{8,15}$/', $numero_telefono)) {
        wp_send_json_error('Numero di telefono non valido');
        return;
    }
    
    // Verifica che il tour esista
    $tour = get_post($tour_id);
    if (!$tour || $tour->post_type !== 'esperienze') {
        wp_send_json_error('Tour non trovato');
        return;
    }
    
    $slot = opencomune_resolve_booking_event($tour_id, $event_id, 1);
    if (is_wp_error($slot)) {
        wp_send_json_error($slot->get_error_message());
        return;
    }

    $prenotazione_id = opencomune_insert_tour_prenotazione_row([
        'tour_id' => $tour_id,
        'event_id' => $event_id,
        'booking_date' => $slot['booking_date'],
        'booking_time' => $slot['booking_time'],
        'rule_id' => $slot['rule_id'],
        'nome' => $nome . ' ' . $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'metodo_pagamento' => $metodo_pagamento,
        'participants' => 1,
        'status' => 'confermata',
    ]);

    if (!$prenotazione_id) {
        wp_send_json_error('Errore nel salvataggio della prenotazione');
        return;
    }

    opencomune_after_booking_decrement_legacy($tour_id, $event_id, 1, $slot);
    
    $metodo_text = ($metodo_pagamento === 'online') ? __('Online (già pagato)', 'opencomune') : __('Alla guida il giorno del tour', 'opencomune');
    $subject = sprintf(__('Conferma prenotazione - %s', 'opencomune'), $tour->post_title);
    $client_body = opencomune_build_prenotazione_client_email_html($nome, $cognome, $tour->post_title, $data, $orario, $prenotazione_id, $metodo_text);
    if (function_exists('opencomune_build_booking_calendar_event')) {
        $calendar_event = opencomune_build_booking_calendar_event((int) $prenotazione_id, null);
        if (is_array($calendar_event)) {
            $client_body .= opencomune_html_email_calendar_block($calendar_event);
        }
    }
    $client_body .= opencomune_html_email_paragraph(__('In allegato trovi il biglietto in PDF e l’invito calendario (.ics).', 'opencomune'));
    $ticket_attachments = function_exists('opencomune_get_booking_confirmation_email_attachments')
        ? opencomune_get_booking_confirmation_email_attachments((int) $prenotazione_id, null)
        : [];
    opencomune_send_html_email($email, $subject, $client_body, [
        'title' => __('Prenotazione confermata', 'opencomune'),
        'preheader' => __('I dettagli della tua prenotazione.', 'opencomune'),
        'cta_url' => get_permalink($tour_id),
        'cta_label' => __('Vedi esperienza', 'opencomune'),
        'attachments' => $ticket_attachments,
    ]);

    $guida_email = get_the_author_meta('user_email', $tour->post_author);
    if ($guida_email) {
        $subject_guida = sprintf(__('Nuova prenotazione - %s', 'opencomune'), $tour->post_title);
        $guida_body = opencomune_build_prenotazione_guida_email_html($nome, $cognome, $tour->post_title, $data, $orario, $prenotazione_id, $email, $telefono, $metodo_text, $note);
        opencomune_send_html_email($guida_email, $subject_guida, $guida_body, [
            'title' => __('Nuova prenotazione', 'opencomune'),
        ]);
    }
    
    wp_send_json_success([
        'message' => 'Prenotazione confermata',
        'prenotazione_id' => $prenotazione_id
    ]);
}
add_action('wp_ajax_opencomune_prenota_tour', 'opencomune_prenota_tour_callback');
add_action('wp_ajax_nopriv_opencomune_prenota_tour', 'opencomune_prenota_tour_callback');

// Gestione pagamento online
function opencomune_processa_pagamento_callback() {
    // Verifica nonce
    if (!wp_verify_nonce($_POST['nonce'], 'processa_pagamento_nonce')) {
        wp_send_json_error('Errore di sicurezza');
        return;
    }
    
    // Recupera i dati
    $tour_id = intval($_POST['tour_id'] ?? 0);
    $data = sanitize_text_field($_POST['data'] ?? '');
    $orario = sanitize_text_field($_POST['orario'] ?? '');
    $event_id = intval($_POST['event_id'] ?? 0);
    $nome = sanitize_text_field($_POST['nome'] ?? '');
    $cognome = sanitize_text_field($_POST['cognome'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $prefisso = sanitize_text_field($_POST['prefisso'] ?? '+39');
    $numero_telefono = sanitize_text_field($_POST['telefono'] ?? '');
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $gateway = opencomune_get_global_payment_gateway_slug();
    $checkout_token = sanitize_text_field($_POST['checkout_token'] ?? '');
    $booking_id = (int) ($_POST['booking_id'] ?? 0);
    $checkout_total = isset($_POST['checkout_total']) ? (float) $_POST['checkout_total'] : null;

    $checkout_payload = null;
    if ($checkout_token !== '' && function_exists('opencomune_get_checkout_session')) {
        $checkout_session = opencomune_get_checkout_session($checkout_token);
        if ($checkout_session) {
            $checkout_payload = is_array($checkout_session['payload'] ?? null) ? $checkout_session['payload'] : [];
            if ($tour_id <= 0) {
                $tour_id = (int) $checkout_session['experience_id'];
            }
            if ($booking_id <= 0 && !empty($checkout_session['booking_id'])) {
                $booking_id = (int) $checkout_session['booking_id'];
            }
            if ($data === '') {
                $data = sanitize_text_field($checkout_payload['date'] ?? '');
            }
            if ($orario === '') {
                $orario = sanitize_text_field($checkout_payload['time'] ?? '');
            }
            if ($event_id <= 0) {
                $event_id = (int) ($checkout_payload['event_id'] ?? 0);
            }
            $contact = is_array($checkout_payload['contact'] ?? null) ? $checkout_payload['contact'] : [];
            if ($nome === '') {
                $nome = sanitize_text_field($contact['first_name'] ?? '');
            }
            if ($cognome === '') {
                $cognome = sanitize_text_field($contact['last_name'] ?? '');
            }
            if ($email === '') {
                $email = sanitize_email($contact['email'] ?? '');
            }
            if ($numero_telefono === '') {
                $numero_telefono = sanitize_text_field($contact['phone_local'] ?? '');
            }
            if ($numero_telefono === '' && !empty($contact['phone'])) {
                $numero_telefono = trim(preg_replace('/^\+\d+\s*/', '', (string) $contact['phone']));
            }
            if (!empty($contact['phone_prefix'])) {
                $prefisso = sanitize_text_field($contact['phone_prefix']);
            }
            if ($note === '' && !empty($contact['notes'])) {
                $note = sanitize_textarea_field($contact['notes']);
            }
            if ($checkout_total === null && isset($checkout_payload['totals']['total'])) {
                $checkout_total = (float) $checkout_payload['totals']['total'];
            }
            if ($booking_id <= 0 && !empty($checkout_payload['booking_id'])) {
                $booking_id = (int) $checkout_payload['booking_id'];
            }
        }
    }

    $telefono = trim($numero_telefono) !== '' ? trim($prefisso . ' ' . $numero_telefono) : '';

    if (!function_exists('opencomune_site_online_payments_available') || !opencomune_site_online_payments_available()) {
        wp_send_json_error(['message' => __('Pagamenti online non attivi o SumUp non configurato dall\'amministratore.', 'opencomune')]);
        return;
    }

    if (empty($tour_id) || empty($data) || empty($orario) || empty($nome) || empty($cognome) || empty($email) || $telefono === '') {
        wp_send_json_error('Dati mancanti');
        return;
    }

    $participant_rows = [];
    $participants_json = isset($_POST['participants_json']) ? wp_unslash($_POST['participants_json']) : '';
    if ($participants_json !== '') {
        $decoded = json_decode($participants_json, true);
        if (is_array($decoded)) {
            $participant_rows = $decoded;
        }
    }
    if ($participant_rows === [] && is_array($checkout_payload)) {
        $lines = $checkout_payload['totals']['lines'] ?? [];
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (!is_array($line)) {
                    continue;
                }
                $qty = (int) ($line['quantity'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }
                $participant_rows[] = [
                    'category_id' => sanitize_text_field($line['category_id'] ?? ''),
                    'quantity' => $qty,
                ];
            }
        }
        if ($participant_rows === [] && is_array($checkout_payload['participants'] ?? null)) {
            $participant_rows = $checkout_payload['participants'];
        }
    }

    if ($checkout_total !== null && $checkout_total >= 0) {
        $totale = round($checkout_total, 2);
    } else {
        $partecipanti = max(1, (int) ($_POST['participants'] ?? 1));
        if ($participant_rows !== []) {
            $totals = opencomune_calculate_checkout_line_items($tour_id, $participant_rows);
        } else {
            $totals = opencomune_calculate_checkout_line_items($tour_id, [
                ['category_id' => '', 'quantity' => $partecipanti],
            ]);
        }
        $totale = (float) $totals['total'];
    }
    
    // Genera ID ordine univoco
    $order_id = 'TOUR_' . $tour_id . '_' . time() . '_' . rand(1000, 9999);
    
    opencomune_ensure_tour_ordini_table();
    global $wpdb;
    $table_ordini = opencomune_tour_ordini_table_name();

    $result = $wpdb->insert($table_ordini, [
        'order_id' => $order_id,
        'tour_id' => $tour_id,
        'event_id' => $event_id,
        'booking_id' => $booking_id > 0 ? $booking_id : null,
        'checkout_token' => $checkout_token !== '' ? $checkout_token : null,
        'nome' => $nome . ' ' . $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'totale' => $totale,
        'gateway' => $gateway,
        'status' => 'pending',
    ]);
    
    if (!$result) {
        wp_send_json_error('Errore nel salvataggio dell\'ordine');
        return;
    }
    
    $ordine_id = $wpdb->insert_id;
    
    $sumup_result = opencomune_processa_pagamento_sumup($order_id, $totale, $nome . ' ' . $cognome, $email, $tour_id, [
        'booking_id' => $booking_id,
        'checkout_token' => $checkout_token,
        'customer_name' => trim($nome . ' ' . $cognome),
    ]);
    if (is_wp_error($sumup_result)) {
        wp_send_json_error(['message' => $sumup_result->get_error_message()]);
        return;
    }
    $redirect_url = $sumup_result;
    
    if ($redirect_url) {
        wp_send_json_success([
            'redirect_url' => $redirect_url,
            'order_id' => $order_id
        ]);
    } else {
        wp_send_json_error(['message' => __('Errore nella configurazione del gateway di pagamento.', 'opencomune')]);
    }
}

// Gestione pagamento Nexi
function opencomune_processa_pagamento_nexi($order_id, $totale, $nome, $email, $tour_id = 0) {
    $resolved = $tour_id > 0 ? opencomune_resolve_payment_gateway_config_for_experience($tour_id, 'nexi') : null;
    $creds = $resolved ? ($resolved['credentials'] ?? []) : [];
    $nexi_config = [
        'merchant_id' => $creds['merchant_id'] ?? get_option('nexi_merchant_id', ''),
        'api_key' => $creds['api_key'] ?? get_option('nexi_api_key', ''),
        'environment' => $creds['environment'] ?? get_option('nexi_environment', 'test'),
        'return_url' => home_url('/pagamento-confermato/'),
        'cancel_url' => home_url('/pagamento-annullato/'),
        'notify_url' => home_url('/wp-admin/admin-ajax.php?action=nexi_webhook')
    ];
    
    // Se non hai ancora configurato Nexi, usa un URL di test
    if (empty($nexi_config['merchant_id'])) {
        return 'https://test.nexi.it/payment/checkout?order_id=' . $order_id . '&amount=' . $totale;
    }
    
    // Qui implementeresti la logica reale per Nexi
    // Per ora restituiamo un URL di esempio
    $base_url = $nexi_config['environment'] === 'live' ? 'https://pay.nexi.it' : 'https://test.nexi.it';
    
    return $base_url . '/payment/checkout?' . http_build_query([
        'merchantId' => $nexi_config['merchant_id'],
        'orderId' => $order_id,
        'amount' => $totale * 100, // Nexi richiede l'importo in centesimi
        'currency' => 'EUR',
        'description' => 'Prenotazione Tour - ' . $order_id,
        'returnUrl' => $nexi_config['return_url'],
        'cancelUrl' => $nexi_config['cancel_url'],
        'notifyUrl' => $nexi_config['notify_url']
    ]);
}

// Gestione pagamento PayPal
function opencomune_processa_pagamento_paypal($order_id, $totale, $nome, $email, $tour_id = 0) {
    $resolved = $tour_id > 0 ? opencomune_resolve_payment_gateway_config_for_experience($tour_id, 'paypal') : null;
    $creds = $resolved ? ($resolved['credentials'] ?? []) : [];
    $paypal_config = [
        'client_id' => $creds['client_id'] ?? get_option('paypal_client_id', ''),
        'client_secret' => $creds['client_secret'] ?? get_option('paypal_client_secret', ''),
        'environment' => $creds['environment'] ?? get_option('paypal_environment', 'sandbox'),
        'return_url' => home_url('/pagamento-confermato/'),
        'cancel_url' => home_url('/pagamento-annullato/'),
        'notify_url' => home_url('/wp-admin/admin-ajax.php?action=paypal_webhook')
    ];
    
    // Se non hai ancora configurato PayPal, usa un URL di test
    if (empty($paypal_config['client_id'])) {
        return 'https://www.sandbox.paypal.com/cgi-bin/webscr?cmd=_xclick&business=test@example.com&item_name=Tour&amount=' . $totale . '&currency_code=EUR&return=' . urlencode($paypal_config['return_url']) . '&cancel_return=' . urlencode($paypal_config['cancel_url']) . '&custom=' . $order_id;
    }
    
    // Qui implementeresti la logica reale per PayPal
    // Per ora restituiamo un URL di esempio
    $base_url = $paypal_config['environment'] === 'live' ? 'https://www.paypal.com' : 'https://www.sandbox.paypal.com';
    
    return $base_url . '/cgi-bin/webscr?' . http_build_query([
        'cmd' => '_xclick',
        'business' => $paypal_config['client_id'],
        'item_name' => 'Prenotazione Tour - ' . $order_id,
        'amount' => $totale,
        'currency_code' => 'EUR',
        'return' => $paypal_config['return_url'],
        'cancel_return' => $paypal_config['cancel_url'],
        'notify_url' => $paypal_config['notify_url'],
        'custom' => $order_id
    ]);
}

function opencomune_get_sumup_config() {
    return [
        'api_key' => get_option('sumup_api_key', ''),
        'merchant_code' => get_option('sumup_merchant_code', ''),
        'environment' => get_option('sumup_environment', 'test'),
        'return_url' => home_url('/pagamento-confermato/'),
        'cancel_url' => home_url('/pagamento-annullato/'),
    ];
}

function opencomune_processa_pagamento_sumup($order_id, $totale, $nome, $email, $tour_id = 0, array $context = []) {
    $config = opencomune_get_sumup_config();
    if ($tour_id > 0) {
        $resolved = opencomune_resolve_payment_gateway_config_for_experience($tour_id, 'sumup');
        $creds = $resolved['credentials'] ?? [];
        if (!empty($creds['api_key'])) {
            $config['api_key'] = $creds['api_key'];
        }
        if (!empty($creds['merchant_code'])) {
            $config['merchant_code'] = $creds['merchant_code'];
        }
    }

    if (empty($config['api_key']) || empty($config['merchant_code'])) {
        return new WP_Error('sumup_config', __('Credenziali SumUp mancanti (API Key o Merchant Code).', 'opencomune'));
    }

    if (function_exists('opencomune_validate_sumup_api_key')) {
        $key_check = opencomune_validate_sumup_api_key($config['api_key']);
        if (is_wp_error($key_check)) {
            return $key_check;
        }
    }

    $checkout_reference = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $order_id);
    if ($checkout_reference === '') {
        $checkout_reference = 'oc_' . wp_generate_password(16, false, false);
    }
    if (strlen($checkout_reference) > 64) {
        $checkout_reference = substr($checkout_reference, 0, 64);
    }

    $return_args = [
        'order_id' => $order_id,
        'gateway' => 'sumup',
    ];
    $checkout_token = sanitize_text_field((string) ($context['checkout_token'] ?? ''));
    if ($checkout_token !== '') {
        $return_args['checkout_token'] = $checkout_token;
    }
    $redirect_url = add_query_arg($return_args, $config['return_url']);

    $amount = round((float) $totale, 2);
    if ($amount <= 0) {
        return new WP_Error('sumup_amount', __('Importo non valido per il pagamento SumUp.', 'opencomune'));
    }

    $response = wp_remote_post(
        'https://api.sumup.com/v0.1/checkouts',
        [
            'headers' => [
                'Authorization' => 'Bearer ' . $config['api_key'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/problem+json, application/json',
            ],
            'body' => wp_json_encode([
                'checkout_reference' => $checkout_reference,
                'amount' => $amount,
                'currency' => 'EUR',
                'merchant_code' => $config['merchant_code'],
                'description' => function_exists('opencomune_build_sumup_checkout_description')
                    ? opencomune_build_sumup_checkout_description((int) $tour_id, array_merge($context, ['customer_name' => $nome]))
                    : sprintf(__('Prenotazione (%s)', 'opencomune'), $nome),
                'redirect_url' => $redirect_url,
                'hosted_checkout' => [
                    'enabled' => true,
                ],
            ]),
            'timeout' => 30,
        ]
    );

    if (is_wp_error($response)) {
        return new WP_Error('sumup_http', $response->get_error_message());
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $raw_body = wp_remote_retrieve_body($response);
    $body = json_decode($raw_body, true);

    if ($status_code < 200 || $status_code >= 300 || !is_array($body)) {
        $detail = '';
        if (is_array($body)) {
            $detail = (string) ($body['detail'] ?? $body['message'] ?? $body['error_message'] ?? '');
        }
        if ($detail === '' && $raw_body !== '') {
            $detail = wp_strip_all_tags(substr($raw_body, 0, 200));
        }
        if ($status_code === 401) {
            return new WP_Error(
                'sumup_unauthorized',
                __('SumUp ha rifiutato la API Key. Usa la chiave segreta (sup_sk_) del merchant, non la chiave pubblica (sup_pk_).', 'opencomune')
            );
        }
        if ($status_code === 403) {
            return new WP_Error(
                'sumup_forbidden',
                __('SumUp non consente i pagamenti con questo account o merchant code.', 'opencomune')
            );
        }

        return new WP_Error(
            'sumup_api',
            $detail !== ''
                ? sprintf(__('SumUp: %s', 'opencomune'), $detail)
                : __('Impossibile creare il checkout SumUp.', 'opencomune')
        );
    }

    if (!empty($body['id']) && function_exists('opencomune_store_sumup_checkout_id_for_order')) {
        opencomune_store_sumup_checkout_id_for_order($order_id, $body['id']);
    }

    if (!empty($body['hosted_checkout_url'])) {
        return $body['hosted_checkout_url'];
    }

    if (!empty($body['id'])) {
        return 'https://checkout.sumup.com/pay/' . rawurlencode($body['id']);
    }

    return new WP_Error('sumup_response', __('Risposta SumUp incompleta: URL di pagamento non ricevuto.', 'opencomune'));
}

// Handler per debug log
add_action('wp_ajax_debug_log', 'opencomune_debug_log_callback');
add_action('wp_ajax_nopriv_debug_log', 'opencomune_debug_log_callback');

function opencomune_debug_log_callback() {
    if (!wp_verify_nonce($_POST['nonce'], 'debug_log_nonce')) {
        wp_die('Errore di sicurezza');
    }
    
    $message = sanitize_text_field($_POST['message']);
    error_log('[DEBUG JS] ' . $message);
    wp_die('OK');
}

add_action('wp_ajax_opencomune_processa_pagamento', 'opencomune_processa_pagamento_callback');
add_action('wp_ajax_nopriv_opencomune_processa_pagamento', 'opencomune_processa_pagamento_callback');

// Email di conferma pagamento
// Voce menu wp-admin per aprire la dashboard frontend esperienze.
function opencomune_add_admin_frontend_dashboard_menu() {
    $hook = add_menu_page(
        'Dashboard admin',
        'Dashboard admin',
        'manage_options',
        'opencomune-admin-dashboard',
        'opencomune_render_admin_frontend_dashboard_page',
        'dashicons-welcome-view-site',
        3
    );

    if ($hook) {
        add_action('load-' . $hook, 'opencomune_redirect_admin_frontend_dashboard');
    }
}
add_action('admin_menu', 'opencomune_add_admin_frontend_dashboard_menu');

function opencomune_redirect_admin_frontend_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Non autorizzato.', 'opencomune'));
    }

    wp_safe_redirect(opencomune_get_dashboard_url());
    exit;
}

function opencomune_render_admin_frontend_dashboard_page() {
    $dashboard_url = opencomune_get_dashboard_url();
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Dashboard admin', 'opencomune'); ?></h1>
        <p><?php esc_html_e('Reindirizzamento alla dashboard esperienze...', 'opencomune'); ?></p>
        <p><a href="<?php echo esc_url($dashboard_url); ?>"><?php esc_html_e('Apri la dashboard', 'opencomune'); ?></a></p>
    </div>
    <?php
}

// Aggiungi menu di amministrazione per le impostazioni
function opencomune_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=esperienze',
        'Pagamenti SumUp',
        'Pagamenti SumUp',
        'manage_options',
        'opencomune-payment-settings',
        'opencomune_settings_page'
    );
}
add_action('admin_menu', 'opencomune_add_admin_menu');

// Pagina delle impostazioni
function opencomune_settings_page() {
    $payment_tabs = [
        'sumup' => 'SumUp',
        'info' => 'Informazioni',
    ];

    $active_tab = 'sumup';
    if (isset($_POST['opencomune_payment_settings_tab'])) {
        $candidate = sanitize_key(wp_unslash($_POST['opencomune_payment_settings_tab']));
        if (isset($payment_tabs[$candidate])) {
            $active_tab = $candidate;
        }
    } elseif (isset($_GET['tab'])) {
        $candidate = sanitize_key(wp_unslash($_GET['tab']));
        if (isset($payment_tabs[$candidate])) {
            $active_tab = $candidate;
        }
    }

    $settings_page_url = admin_url('edit.php?post_type=esperienze&page=opencomune-payment-settings');
    $settings_saved = false;
    $settings_error = '';

    if (isset($_POST['submit']) && isset($_POST['opencomune_payment_settings_nonce']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_payment_settings_nonce'])), 'opencomune_payment_settings')) {
        $save_result = opencomune_save_site_sumup_payment_settings(wp_unslash($_POST));
        if (is_wp_error($save_result)) {
            $settings_error = $save_result->get_error_message();
        } else {
            $settings_saved = true;
        }
    }

    $form_settings = opencomune_get_site_sumup_settings_for_form();
    $site_payments_enabled = !empty($form_settings['payments_enabled']);
    $sumup_api_key = get_option('sumup_api_key', '');
    $sumup_merchant_code = $form_settings['sumup_merchant_code'];
    $sumup_environment = $form_settings['sumup_environment'];

    ?>
    <div class="wrap opencomune-payment-settings-wrap">
        <h1><?php esc_html_e('Pagamenti online (SumUp)', 'opencomune'); ?></h1>
        <p class="description"><?php esc_html_e('Configurazione globale per tutte le esperienze. I partner non possono modificare il gateway.', 'opencomune'); ?></p>

        <?php if ($settings_saved) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Impostazioni salvate con successo.', 'opencomune'); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($settings_error !== '') : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($settings_error); ?></p>
        </div>
        <?php endif; ?>

        <nav class="nav-tab-wrapper wp-clearfix" aria-label="Sezioni gateway pagamenti">
            <?php foreach ($payment_tabs as $tab_slug => $tab_label) : ?>
                <a href="<?php echo esc_url(add_query_arg('tab', $tab_slug, $settings_page_url)); ?>"
                   class="nav-tab<?php echo $active_tab === $tab_slug ? ' nav-tab-active' : ''; ?>">
                    <?php echo esc_html($tab_label); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <form method="post" action="<?php echo esc_url(add_query_arg('tab', $active_tab, $settings_page_url)); ?>">
            <?php wp_nonce_field('opencomune_payment_settings', 'opencomune_payment_settings_nonce'); ?>
            <input type="hidden" name="opencomune_payment_settings_tab" value="<?php echo esc_attr($active_tab); ?>">

            <div class="opencomune-payment-tab-panel"<?php echo $active_tab !== 'sumup' ? ' hidden' : ''; ?> data-tab="sumup">
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php esc_html_e('Pagamenti online', 'opencomune'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="opencomune_site_payments_enabled" value="1" <?php checked($site_payments_enabled); ?>>
                                <?php esc_html_e('Abilita checkout SumUp per tutte le esperienze con pagamento online', 'opencomune'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sumup_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="password" id="sumup_api_key" name="sumup_api_key"
                                   value="<?php echo esc_attr($sumup_api_key); ?>" class="regular-text" autocomplete="off" />
                            <p class="description">
                                Chiave segreta <code>sup_sk_…</code> (non la chiave pubblica <code>sup_pk_</code>) da SumUp → Impostazioni → Per sviluppatori → Toolkit → API Keys.
                                <a href="https://developer.sumup.com/tools/authorization/api-keys/" target="_blank" rel="noopener noreferrer">Documentazione</a>.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sumup_merchant_code">Merchant Code</label>
                        </th>
                        <td>
                            <input type="text" id="sumup_merchant_code" name="sumup_merchant_code"
                                   value="<?php echo esc_attr($sumup_merchant_code); ?>" class="regular-text" />
                            <p class="description">Codice merchant SumUp associato al conto (es. ME...).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="sumup_environment">Ambiente</label>
                        </th>
                        <td>
                            <select id="sumup_environment" name="sumup_environment">
                                <option value="test" <?php selected($sumup_environment, 'test'); ?>>Test</option>
                                <option value="live" <?php selected($sumup_environment, 'live'); ?>>Produzione</option>
                            </select>
                            <p class="description">Usa credenziali di test o live in base all'account SumUp configurato.</p>
                        </td>
                    </tr>
                </table>
                <?php if ($active_tab === 'sumup') : ?>
                <p class="submit">
                    <input type="submit" name="submit" class="button button-primary" value="Salva Impostazioni">
                </p>
                <?php endif; ?>
            </div>

        </form>

        <div class="opencomune-payment-tab-panel"<?php echo $active_tab !== 'info' ? ' hidden' : ''; ?> data-tab="info">
            <div class="card opencomune-payment-info-card">
                <h2 class="title"><?php esc_html_e('SumUp — credenziali e ritorno', 'opencomune'); ?></h2>

                <ul>
                    <li><?php esc_html_e('Accedi a SumUp e genera una API Key segreta (sup_sk_…).', 'opencomune'); ?>
                        <a href="https://developer.sumup.com/tools/authorization/api-keys/" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Documentazione', 'opencomune'); ?></a>
                    </li>
                    <li><?php esc_html_e('Recupera il Merchant Code dal pannello SumUp (es. ME…).', 'opencomune'); ?></li>
                    <li><?php esc_html_e('Il checkout usa hosted checkout SumUp con redirect al pagamento.', 'opencomune'); ?></li>
                </ul>

                <h3><?php esc_html_e('URL di ritorno', 'opencomune'); ?></h3>
                <ul>
                    <li><strong><?php esc_html_e('Successo', 'opencomune'); ?>:</strong> <?php echo esc_html(home_url('/pagamento-confermato/')); ?></li>
                    <li><strong><?php esc_html_e('Annullamento', 'opencomune'); ?>:</strong> <?php echo esc_html(home_url('/pagamento-annullato/')); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <style>
        .opencomune-payment-settings-wrap .nav-tab-wrapper {
            margin-bottom: 0;
        }
        .opencomune-payment-settings-wrap .opencomune-payment-tab-panel {
            margin-top: 1em;
            max-width: 800px;
        }
        .opencomune-payment-settings-wrap .opencomune-payment-tab-panel[hidden] {
            display: none !important;
        }
        .opencomune-payment-info-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        .opencomune-payment-info-card .title {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .opencomune-payment-info-card ul {
            list-style: disc;
            margin: 0 0 1em 1.5em;
        }
    </style>
    <?php
}


// ========================================
// CUSTOM LOGIN PAGE REDIRECTS
// ========================================

/**
 * Redirect wp-login.php to custom login page
 */
function opencomune_redirect_login_page() {
    $page_viewed = basename($_SERVER['REQUEST_URI']);
    
    if ($page_viewed == "wp-login.php" && $_SERVER['REQUEST_METHOD'] == 'GET') {
        // Se è un logout, reindirizza alla homepage
        if (isset($_GET['loggedout']) && $_GET['loggedout'] == 'true') {
            wp_redirect(home_url());
            exit;
        }
        // Altrimenti reindirizza alla custom login page
        wp_redirect(home_url('/login/'));
        exit;
    }
}
add_action('init', 'opencomune_redirect_login_page');

/**
 * Redirect wp-admin to custom login page for non-logged users
 */
function opencomune_redirect_admin() {
    // Solo se non stiamo processando un login
    if (!is_user_logged_in() && !wp_doing_ajax() && !wp_doing_cron() && !isset($_POST['wp-submit'])) {
        if (is_admin() || (isset($_GET['action']) && $_GET['action'] == 'logout')) {
            wp_redirect(home_url('/login/'));
            exit;
        }
    }
}
add_action('init', 'opencomune_redirect_admin');

// GESTIONE LOGIN RIMOSSA - Ora gestita direttamente in page-login.php


/**
 * Add custom login page template
 */
function opencomune_custom_login_template($template) {
    if (is_page('login')) {
        $custom_template = get_template_directory() . '/page-login.php';
        if (file_exists($custom_template)) {
            return $custom_template;
        }
    }
    return $template;
}
add_filter('page_template', 'opencomune_custom_login_template');

// Aggiungi sottomenu per visualizzare tutte le esperienze nell'admin
function opencomune_add_esperienze_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=esperienze',
        'Tutte le Esperienze',
        'Tutte le Esperienze',
        'manage_options',
        'all-esperienze',
        'opencomune_all_esperienze_page'
    );
}
add_action('admin_menu', 'opencomune_add_esperienze_admin_menu');

// Pagina per visualizzare tutte le esperienze
function opencomune_all_esperienze_page() {
    ?>
    <div class="wrap">
        <h1>Tutte le Esperienze</h1>
        
        <?php
        // Query per ottenere tutte le esperienze
        $args = array(
            'post_type' => 'esperienze',
            'post_status' => array('publish', 'draft', 'pending', 'private'),
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $esperienze = get_posts($args);
        
        if ($esperienze) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead>';
            echo '<tr>';
            echo '<th>Titolo</th>';
            echo '<th>Autore</th>';
            echo '<th>Stato</th>';
            echo '<th>Data</th>';
            echo '<th>Azioni</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            foreach ($esperienze as $esperienza) {
                $author = get_userdata($esperienza->post_author);
                $status_labels = array(
                    'publish' => 'Pubblicata',
                    'draft' => 'Bozza',
                    'pending' => 'In revisione',
                    'private' => 'Privata'
                );
                $status_label = isset($status_labels[$esperienza->post_status]) ? $status_labels[$esperienza->post_status] : $esperienza->post_status;
                
                echo '<tr>';
                echo '<td><strong>' . esc_html($esperienza->post_title) . '</strong></td>';
                echo '<td>' . esc_html($author->display_name) . '</td>';
                echo '<td><span class="status-' . esc_attr($esperienza->post_status) . '">' . esc_html($status_label) . '</span></td>';
                echo '<td>' . esc_html(date('d/m/Y H:i', strtotime($esperienza->post_date))) . '</td>';
                echo '<td>';
                echo '<a href="' . get_edit_post_link($esperienza->ID) . '" class="button button-small">Modifica</a> ';
                echo '<a href="' . get_permalink($esperienza->ID) . '" class="button button-small" target="_blank">Visualizza</a>';
                echo '</td>';
                echo '</tr>';
            }
            
            echo '</tbody>';
            echo '</table>';
        } else {
            echo '<p>Nessuna esperienza trovata.</p>';
        }
        ?>
    </div>
    
    <style>
    .status-publish { color: #00a32a; font-weight: bold; }
    .status-draft { color: #dba617; font-weight: bold; }
    .status-pending { color: #d63638; font-weight: bold; }
    .status-private { color: #8c8f94; font-weight: bold; }
    </style>
    <?php
}

// FUNZIONE RIMOSSA - Duplicata con opencomune_redirect_login_page()

require_once get_template_directory() . '/inc/partner-admin-cpt.php';
require_once get_template_directory() . '/inc/import-esperienze-csv.php';
require_once get_template_directory() . '/inc/translations.php';
require_once get_template_directory() . '/inc/planner-catalog.php';
require_once get_template_directory() . '/inc/planner.php';
require_once get_template_directory() . '/inc/planner-requests-admin.php';
require_once get_template_directory() . '/inc/planner-itinerary-edit.php';
require_once get_template_directory() . '/inc/planner-routes.php';
require_once get_template_directory() . '/inc/planner-email.php';
require_once get_template_directory() . '/inc/dashboard-planner.php';
require_once get_template_directory() . '/inc/planner-admin.php';
require_once get_template_directory() . '/inc/planner-catalog-admin.php';
require_once get_template_directory() . '/inc/dashboard.php';
require_once get_template_directory() . '/inc/dashboard-esperienze.php';
require_once get_template_directory() . '/inc/dashboard-esperienze-categories.php';
require_once get_template_directory() . '/inc/partner-bookings.php';
require_once get_template_directory() . '/inc/dashboard-home.php';
require_once get_template_directory() . '/inc/bulk-translations.php';
require_once get_template_directory() . '/inc/rest-api-esperienze.php';
require_once get_template_directory() . '/inc/availability.php';
require_once get_template_directory() . '/inc/esperienza-form-steps.php';
require_once get_template_directory() . '/inc/esperienza-form-summary.php';
require_once get_template_directory() . '/inc/pricing-categories.php';
require_once get_template_directory() . '/inc/meeting-pickup.php';
require_once get_template_directory() . '/inc/esperienza-itinerary-steps.php';
require_once get_template_directory() . '/inc/esperienza-settings.php';
require_once get_template_directory() . '/inc/esperienza-tickets.php';
require_once get_template_directory() . '/inc/esperienza-identification.php';
require_once get_template_directory() . '/inc/esperienza-gallery-lightbox.php';
require_once get_template_directory() . '/inc/esperienza-booking-channel.php';
require_once get_template_directory() . '/inc/partner-payment-settings.php';
require_once get_template_directory() . '/inc/partner-ai-settings.php';
require_once get_template_directory() . '/inc/ai-translate.php';
require_once get_template_directory() . '/inc/booking-checkout.php';
require_once get_template_directory() . '/inc/booking-on-request.php';
require_once get_template_directory() . '/inc/payment-orders.php';
require_once get_template_directory() . '/inc/payment-commissions.php';
require_once get_template_directory() . '/inc/payment-revenue.php';
require_once get_template_directory() . '/inc/platform-operations-log.php';
require_once get_template_directory() . '/inc/dashboard-operations.php';
require_once get_template_directory() . '/inc/booking-calendar-ics.php';
require_once get_template_directory() . '/inc/email-template.php';
require_once get_template_directory() . '/inc/phone-prefix.php';
require_once get_template_directory() . '/inc/turnstile.php';
require_once get_template_directory() . '/inc/contact-form.php';
require_once get_template_directory() . '/inc/social-share.php';
require_once get_template_directory() . '/inc/home-seo.php';
require_once get_template_directory() . '/inc/social-proof-badge.php';