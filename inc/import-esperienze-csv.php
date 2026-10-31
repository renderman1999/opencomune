<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_register_import_esperienze_admin_page() {
    add_submenu_page(
        'opencomune-settings',
        'Importa Esperienze CSV',
        'Importa',
        'manage_options',
        'opencomune-import-esperienze',
        'opencomune_render_import_esperienze_admin_page'
    );
}
add_action('admin_menu', 'opencomune_register_import_esperienze_admin_page');

function opencomune_is_import_esperienze_admin_page() {
    $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    return $page === 'opencomune-import-esperienze';
}

function opencomune_register_import_esperienze_admin_assets() {
    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $css_path = $theme_dir . '/assets/css/admin-import-esperienze.css';
    $js_path = $theme_dir . '/assets/js/admin-import-esperienze.js';

    wp_enqueue_style(
        'opencomune-import-esperienze',
        $theme_uri . '/assets/css/admin-import-esperienze.css',
        [],
        file_exists($css_path) ? (string) filemtime($css_path) : '1.0.0'
    );

    wp_enqueue_script(
        'opencomune-import-esperienze',
        $theme_uri . '/assets/js/admin-import-esperienze.js',
        ['jquery'],
        file_exists($js_path) ? (string) filemtime($js_path) : '1.0.0',
        true
    );

    wp_localize_script('opencomune-import-esperienze', 'opencomuneImportCsv', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_import_csv_ajax'),
    ]);
}

function opencomune_enqueue_import_esperienze_admin_assets($hook) {
    if ($hook === 'opencomune-settings_page_opencomune-import-esperienze' || opencomune_is_import_esperienze_admin_page()) {
        opencomune_register_import_esperienze_admin_assets();
    }
}
add_action('admin_enqueue_scripts', 'opencomune_enqueue_import_esperienze_admin_assets');
add_action('admin_print_scripts-opencomune-settings_page_opencomune-import-esperienze', 'opencomune_register_import_esperienze_admin_assets');

function opencomune_print_import_esperienze_admin_footer_script() {
    if (!opencomune_is_import_esperienze_admin_page()) {
        return;
    }

    $config = [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('opencomune_import_csv_ajax'),
    ];
    ?>
    <script>
    window.opencomuneImportCsv = window.opencomuneImportCsv || <?php echo wp_json_encode($config); ?>;
    </script>
    <?php

    if (wp_script_is('opencomune-import-esperienze', 'done') || wp_script_is('opencomune-import-esperienze', 'enqueued')) {
        return;
    }

    $js_path = get_template_directory() . '/assets/js/admin-import-esperienze.js';
    if (!is_readable($js_path)) {
        return;
    }

    echo '<script>' . file_get_contents($js_path) . '</script>';
}
add_action('admin_footer', 'opencomune_print_import_esperienze_admin_footer_script');

function opencomune_get_import_esperienze_csv_headers() {
    return [
        'nome_evento',
        'nome_organizzazione',
        'data',
        'orari',
        'info_e_prenotazioni',
        'citta',
        'luogo',
        'tipologia',
        'latitudine',
        'longitudine',
        'precisione_coordinate',
        'descrizione_evento',
        'pagina_fonte',
    ];
}

function opencomune_import_should_update_existing($value = null) {
    if ($value === null) {
        $value = $_POST['import_update_existing'] ?? '1';
    }

    return (string) $value !== '0';
}

function opencomune_import_should_publish_after_import($value = null) {
    if ($value === null) {
        $value = $_POST['import_publish_after_import'] ?? '0';
    }

    return (string) $value === '1';
}

function opencomune_import_should_fetch_google_cover($value = null) {
    if ($value === null) {
        $value = $_POST['import_google_places_cover'] ?? '0';
    }

    return (string) $value === '1';
}

function opencomune_import_resolve_post_status($publish_after_import) {
    return opencomune_import_should_publish_after_import($publish_after_import) ? 'publish' : 'draft';
}

function opencomune_build_import_places_search_query($data) {
    $place = trim((string) ($data['place'] ?? ''));
    $city = trim((string) ($data['city'] ?? ''));

    if ($place !== '' && $city !== '' && stripos($place, $city) === false) {
        return $place . ', ' . $city . ', Italia';
    }

    if ($place !== '') {
        return $place . ', Italia';
    }

    if ($city !== '') {
        return $city . ', Italia';
    }

    return '';
}

function opencomune_google_places_api_request($endpoint, $args) {
    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key === '') {
        return new WP_Error('no_api_key', 'Google Maps API key non configurata.');
    }

    $args['key'] = $api_key;
    $url = 'https://maps.googleapis.com/maps/api/place/' . ltrim($endpoint, '/') . '?' . http_build_query($args);
    $response = wp_remote_get($url, [
        'timeout' => 20,
        'redirection' => 5,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);

    if ($status_code >= 400 || !is_array($body)) {
        return new WP_Error('google_places_http', 'Risposta Google Places non valida.');
    }

    if (($body['status'] ?? '') !== 'OK' && ($body['status'] ?? '') !== 'ZERO_RESULTS') {
        $message = $body['error_message'] ?? ($body['status'] ?? 'Errore Google Places');
        return new WP_Error('google_places_api', $message);
    }

    return $body;
}

function opencomune_google_places_get_photo_reference($data) {
    $query = opencomune_build_import_places_search_query($data);
    $latitude = (float) ($data['latitude'] ?? 0);
    $longitude = (float) ($data['longitude'] ?? 0);

    if ($query === '' && ($latitude === 0.0 || $longitude === 0.0)) {
        return new WP_Error('no_address', 'Indirizzo non disponibile per la ricerca Google Places.');
    }

    if ($query !== '') {
        $find_args = [
            'input' => $query,
            'inputtype' => 'textquery',
            'fields' => 'photos,place_id,name',
        ];

        if ($latitude !== 0.0 && $longitude !== 0.0) {
            $find_args['locationbias'] = sprintf('circle:1000@%s,%s', $latitude, $longitude);
        }

        $find = opencomune_google_places_api_request('findplacefromtext/json', $find_args);
        if (!is_wp_error($find) && !empty($find['candidates'][0]['photos'][0]['photo_reference'])) {
            return $find['candidates'][0]['photos'][0]['photo_reference'];
        }

        $text_search_args = ['query' => $query];
        if ($latitude !== 0.0 && $longitude !== 0.0) {
            $text_search_args['location'] = $latitude . ',' . $longitude;
            $text_search_args['radius'] = 1500;
        }

        $text_search = opencomune_google_places_api_request('textsearch/json', $text_search_args);
        if (!is_wp_error($text_search) && !empty($text_search['results'][0]['photos'][0]['photo_reference'])) {
            return $text_search['results'][0]['photos'][0]['photo_reference'];
        }
    }

    if ($latitude !== 0.0 && $longitude !== 0.0) {
        $nearby_args = [
            'location' => $latitude . ',' . $longitude,
            'radius' => 500,
            'keyword' => trim((string) ($data['place'] ?? $data['title'] ?? '')),
        ];

        $nearby = opencomune_google_places_api_request('nearbysearch/json', $nearby_args);
        if (!is_wp_error($nearby) && !empty($nearby['results'][0]['photos'][0]['photo_reference'])) {
            return $nearby['results'][0]['photos'][0]['photo_reference'];
        }
    }

    return new WP_Error('no_photo', 'Nessuna foto trovata su Google Places per questo indirizzo.');
}

function opencomune_google_places_get_photo_download_url($photo_reference) {
    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key === '' || $photo_reference === '') {
        return new WP_Error('no_photo_reference', 'Riferimento foto Google Places mancante.');
    }

    return add_query_arg([
        'maxwidth' => 1600,
        'photo_reference' => $photo_reference,
        'key' => $api_key,
    ], 'https://maps.googleapis.com/maps/api/place/photo');
}

function opencomune_import_attachment_from_url($image_url, $post_id, $title) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $tmp_file = download_url($image_url, 30);
    if (is_wp_error($tmp_file)) {
        return $tmp_file;
    }

    $filename = sanitize_file_name(sanitize_title($title) . '-google-places.jpg');
    $file_array = [
        'name' => $filename,
        'tmp_name' => $tmp_file,
    ];

    $attachment_id = media_handle_sideload($file_array, $post_id, $title);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp_file);
        return $attachment_id;
    }

    if (!opencomune_is_valid_image_attachment($attachment_id)) {
        wp_delete_attachment($attachment_id, true);
        return new WP_Error('invalid_image', 'Il file scaricato non e un\'immagine valida.');
    }

    update_post_meta($attachment_id, '_wp_attachment_image_alt', $title);

    return (int) $attachment_id;
}

function opencomune_get_import_cover_action($data, $import_google_cover, $duplicate_id = 0) {
    if (!$import_google_cover) {
        return [
            'action' => 'disabled',
            'message' => '',
        ];
    }

    if (opencomune_get_google_maps_api_key() === '') {
        return [
            'action' => 'no_api_key',
            'message' => 'API Google Maps non configurata.',
        ];
    }

    if ($duplicate_id > 0 && opencomune_esperienza_has_valid_thumbnail($duplicate_id)) {
        return [
            'action' => 'has_cover',
            'message' => 'Copertina gia presente.',
        ];
    }

    if (opencomune_build_import_places_search_query($data) === ''
        && ((float) ($data['latitude'] ?? 0) === 0.0 || (float) ($data['longitude'] ?? 0) === 0.0)) {
        return [
            'action' => 'no_address',
            'message' => 'Indirizzo insufficiente per Google Places.',
        ];
    }

    return [
        'action' => 'will_fetch',
        'message' => 'Copertina da Google Places se disponibile.',
    ];
}

function opencomune_set_import_cover_from_google_places($post_id, $data) {
    if (opencomune_esperienza_has_valid_thumbnail($post_id)) {
        return [
            'status' => 'skipped',
            'message' => 'Copertina gia presente.',
        ];
    }

    $existing_thumbnail_id = (int) get_post_thumbnail_id($post_id);
    if ($existing_thumbnail_id > 0 && !opencomune_esperienza_has_valid_thumbnail($post_id)) {
        delete_post_thumbnail($post_id);
        wp_delete_attachment($existing_thumbnail_id, true);
    }

    $photo_reference = opencomune_google_places_get_photo_reference($data);
    if (is_wp_error($photo_reference)) {
        return [
            'status' => 'error',
            'message' => $photo_reference->get_error_message(),
        ];
    }

    $photo_url = opencomune_google_places_get_photo_download_url($photo_reference);
    if (is_wp_error($photo_url)) {
        return [
            'status' => 'error',
            'message' => $photo_url->get_error_message(),
        ];
    }

    $attachment_id = opencomune_import_attachment_from_url($photo_url, $post_id, $data['title']);
    if (is_wp_error($attachment_id)) {
        return [
            'status' => 'error',
            'message' => $attachment_id->get_error_message(),
        ];
    }

    set_post_thumbnail($post_id, $attachment_id);
    update_post_meta($post_id, '_cover_source', 'google_places');

    return [
        'status' => 'success',
        'message' => 'Copertina importata da Google Places.',
        'attachment_id' => $attachment_id,
    ];
}

function opencomune_normalize_import_csv_header($header) {
    $header = strtolower(trim((string) $header));
    $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
    $header = preg_replace('/[^a-z0-9_]+/', '_', $header);
    return trim($header, '_');
}

function opencomune_ensure_tour_partners_table() {
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

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function opencomune_find_partner_by_name($name) {
    global $wpdb;

    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    opencomune_ensure_tour_partners_table();

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}tour_partners
         WHERE LOWER(TRIM(nome_attivita)) = LOWER(%s)
         LIMIT 1",
        $name
    ));
}

function opencomune_generate_import_partner_email($organization) {
    $host = parse_url(home_url(), PHP_URL_HOST);
    if (!$host) {
        $host = 'opencomune.local';
    }

    $base = 'import.partner.' . substr(md5(strtolower(trim($organization))), 0, 12);
    $email = $base . '@' . $host;
    $suffix = 1;

    while (email_exists($email)) {
        $email = $base . '.' . $suffix . '@' . $host;
        $suffix++;
    }

    return $email;
}

function opencomune_create_partner_from_import($organization, $city = '', $latitude = 0.0, $longitude = 0.0) {
    global $wpdb;

    $organization = trim((string) $organization);
    if ($organization === '') {
        return new WP_Error('missing_partner', 'Nome organizzazione mancante.');
    }

    opencomune_ensure_tour_partners_table();

    $table = $wpdb->prefix . 'tour_partners';
    $email = opencomune_generate_import_partner_email($organization);
    $password = wp_generate_password(24, true, true);
    $slug = substr(sanitize_title($organization), 0, 24);
    if ($slug === '') {
        $slug = 'partner-import';
    }

    $inserted = $wpdb->insert($table, [
        'nome_attivita' => $organization,
        'tipologia_attivita' => 'evento',
        'partita_iva' => 'IMPORT-' . strtoupper(substr(md5($organization), 0, 10)),
        'descrizione_breve' => '',
        'indirizzo' => $city !== '' ? $city : 'Da completare',
        'cap' => '00000',
        'comune' => $city !== '' ? $city : 'Da completare',
        'latitudine' => $latitude,
        'longitudine' => $longitude,
        'nome_referente' => $organization,
        'ruolo_referente' => 'Import CSV',
        'telefono_attivita' => 'N/D',
        'email_ufficiale' => $email,
        'email_login' => $email,
        'password_hash' => wp_hash_password($password),
        'termini_condizioni' => 1,
        'privacy_gdpr' => 1,
        'pubblicazione_dati' => 1,
        'dichiarazione_verita' => 1,
        'status' => 'approved',
        'data_approvazione' => current_time('mysql'),
        'note_admin' => 'Creato da import CSV il ' . current_time('mysql'),
    ]);

    if ($inserted === false) {
        return new WP_Error('partner_insert', 'Impossibile creare il partner nel database.');
    }

    $partner_id = (int) $wpdb->insert_id;
    $username = sanitize_user($email, true);
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        $wpdb->delete($table, ['id' => $partner_id]);
        return $user_id;
    }

    wp_update_user([
        'ID' => $user_id,
        'display_name' => $organization,
        'first_name' => $organization,
        'last_name' => '',
    ]);

    $user = new WP_User($user_id);
    $user->set_role('partner');

    update_user_meta($user_id, 'partner_id', $partner_id);
    update_user_meta($user_id, 'partner_email_verified', true);
    update_user_meta($user_id, 'partner_approved', true);
    update_user_meta($user_id, '_import_source', 'csv_batch');

    $wpdb->update(
        $table,
        ['wp_user_id' => $user_id],
        ['id' => $partner_id]
    );

    opencomune_sync_partner_cpt($partner_id);

    return opencomune_get_tour_partner_row($partner_id);
}

function opencomune_resolve_import_partner($organization, $city = '', $latitude = 0.0, $longitude = 0.0, $dry_run = false) {
    $organization = trim((string) $organization);
    if ($organization === '') {
        return [
            'partner_id' => 0,
            'partner_user_id' => 0,
            'partner_name' => '',
            'partner_action' => 'none',
        ];
    }

    $existing = opencomune_find_partner_by_name($organization);
    if ($existing) {
        return [
            'partner_id' => (int) $existing->id,
            'partner_user_id' => (int) $existing->wp_user_id,
            'partner_name' => $existing->nome_attivita,
            'partner_action' => 'existing',
        ];
    }

    if ($dry_run) {
        return [
            'partner_id' => 0,
            'partner_user_id' => 0,
            'partner_name' => $organization,
            'partner_action' => 'will_create',
        ];
    }

    $created = opencomune_create_partner_from_import($organization, $city, $latitude, $longitude);
    if (is_wp_error($created)) {
        return $created;
    }

    return [
        'partner_id' => (int) $created->id,
        'partner_user_id' => (int) $created->wp_user_id,
        'partner_name' => $created->nome_attivita,
        'partner_action' => 'created',
    ];
}

function opencomune_parse_import_date_field($raw_date) {
    $raw_date = trim((string) $raw_date);
    if ($raw_date === '') {
        return [
            'start' => '',
            'end' => '',
            'mode' => 'single',
            'duration' => '',
            'label' => '',
            'error' => '',
        ];
    }

    $normalized = preg_replace('/\s+/u', ' ', $raw_date);
    $normalized = preg_replace('/^dal\s+/iu', '', $normalized);

    $start_raw = $normalized;
    $end_raw = '';

    if (preg_match('/^(.+?)\s*-\s*(.+)$/u', $normalized, $matches)) {
        $start_raw = trim($matches[1]);
        $end_raw = trim($matches[2]);
    }

    $start = opencomune_parse_import_italian_date($start_raw);
    $end = $end_raw !== '' ? opencomune_parse_import_italian_date($end_raw) : '';

    if ($start === '') {
        return [
            'start' => '',
            'end' => '',
            'mode' => 'single',
            'duration' => '',
            'label' => $raw_date,
            'error' => 'Data non valida: ' . $raw_date,
        ];
    }

    if ($end !== '' && $end >= $start) {
        $days = (int) floor((strtotime($end) - strtotime($start)) / DAY_IN_SECONDS) + 1;
        return [
            'start' => $start,
            'end' => $end,
            'mode' => 'range',
            'duration' => $days === 1 ? '1 giorno' : $days . ' giorni',
            'label' => $start . ' -> ' . $end,
            'error' => '',
        ];
    }

    return [
        'start' => $start,
        'end' => '',
        'mode' => 'single',
        'duration' => '1 giorno',
        'label' => $start,
        'error' => '',
    ];
}

function opencomune_parse_import_italian_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
        $day = (int) $matches[1];
        $month = (int) $matches[2];
        $year = (int) $matches[3];
        if (checkdate($month, $day, $year)) {
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        return gmdate('Y-m-d', $timestamp);
    }

    return '';
}

function opencomune_parse_import_tipologia_categories($tipologia) {
    $tipologia = trim((string) $tipologia);
    if ($tipologia === '') {
        return [];
    }

    $parts = preg_split('/\s*\/\s*/u', $tipologia);
    $categories = [];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part !== '') {
            $categories[] = $part;
        }
    }

    return $categories;
}

function opencomune_find_import_esperienza_duplicate($title, $start_date) {
    global $wpdb;

    $title = trim((string) $title);
    if ($title === '') {
        return 0;
    }

    $post_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT ID FROM {$wpdb->posts}
         WHERE post_type = 'esperienze'
           AND post_title = %s
           AND post_status IN ('publish', 'draft', 'pending', 'private')",
        $title
    ));

    if ($post_ids === []) {
        return 0;
    }

    if ($start_date === '') {
        return (int) $post_ids[0];
    }

    foreach ($post_ids as $post_id) {
        $existing_start = get_post_meta((int) $post_id, 'tour_start_date', true);
        if ($existing_start === $start_date) {
            return (int) $post_id;
        }
    }

    return 0;
}

function opencomune_map_import_csv_row($row, $line_number) {
    $headers = opencomune_get_import_esperienze_csv_headers();
    $mapped = array_fill_keys($headers, '');

    foreach ($headers as $index => $header) {
        $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : '';
    }

    $title = sanitize_text_field($mapped['nome_evento']);
    if ($title === '') {
        return new WP_Error('missing_title', 'Riga ' . $line_number . ': nome_evento mancante.');
    }

    $schedule = opencomune_parse_import_date_field($mapped['data']);
    if ($schedule['error'] !== '') {
        return new WP_Error('invalid_date', 'Riga ' . $line_number . ': ' . $schedule['error']);
    }

    $description = wp_kses_post($mapped['descrizione_evento']);
    if ($description === '') {
        $description = $title;
    }

    $latitude = $mapped['latitudine'] !== '' ? floatval(str_replace(',', '.', $mapped['latitudine'])) : 0.0;
    $longitude = $mapped['longitudine'] !== '' ? floatval(str_replace(',', '.', $mapped['longitudine'])) : 0.0;

    return [
        'line' => $line_number,
        'title' => $title,
        'description' => $description,
        'excerpt' => wp_trim_words(wp_strip_all_tags($description), 30, '...'),
        'organization' => sanitize_text_field($mapped['nome_organizzazione']),
        'schedule' => $schedule,
        'orari' => sanitize_text_field($mapped['orari']),
        'booking_info' => sanitize_textarea_field($mapped['info_e_prenotazioni']),
        'city' => sanitize_text_field($mapped['citta']),
        'place' => sanitize_text_field($mapped['luogo']),
        'tipologia' => sanitize_text_field($mapped['tipologia']),
        'categories' => opencomune_parse_import_tipologia_categories($mapped['tipologia']),
        'latitude' => $latitude,
        'longitude' => $longitude,
        'coordinate_precision' => sanitize_text_field($mapped['precisione_coordinate']),
        'source_page' => sanitize_text_field($mapped['pagina_fonte']),
    ];
}

function opencomune_get_import_experience_action($data, $update_existing) {
    $duplicate_id = opencomune_find_import_esperienza_duplicate($data['title'], $data['schedule']['start']);
    if ($duplicate_id <= 0) {
        return 'create';
    }

    return $update_existing ? 'update' : 'skip';
}

function opencomune_build_import_preview_item($data, $options) {
    $update_existing = opencomune_import_should_update_existing($options['update_existing'] ?? '1');
    $import_google_cover = opencomune_import_should_fetch_google_cover($options['google_places_cover'] ?? '0');

    if (is_wp_error($data)) {
        return [
            'line' => 0,
            'title' => '',
            'organization' => '',
            'partner_action' => 'none',
            'partner_name' => '',
            'date_label' => '',
            'city' => '',
            'tipologia' => '',
            'experience_action' => 'error',
            'message' => $data->get_error_message(),
        ];
    }

    $partner = opencomune_resolve_import_partner(
        $data['organization'],
        $data['city'],
        $data['latitude'],
        $data['longitude'],
        true
    );

    if (is_wp_error($partner)) {
        return [
            'line' => $data['line'],
            'title' => $data['title'],
            'organization' => $data['organization'],
            'partner_action' => 'error',
            'partner_name' => $data['organization'],
            'date_label' => $data['schedule']['label'],
            'city' => $data['city'],
            'tipologia' => $data['tipologia'],
            'experience_action' => 'error',
            'message' => $partner->get_error_message(),
        ];
    }

    $experience_action = opencomune_get_import_experience_action($data, $update_existing);
    $duplicate_id = 0;
    if ($experience_action === 'update' || $experience_action === 'skip') {
        $duplicate_id = opencomune_find_import_esperienza_duplicate($data['title'], $data['schedule']['start']);
    }

    $cover = opencomune_get_import_cover_action($data, $import_google_cover, $duplicate_id);
    $message = $cover['message'];

    if ($experience_action === 'skip') {
        $message = trim($message . ' Esperienza gia presente: verra saltata.');
    }

    if ($experience_action === 'update') {
        $message = trim($message . ' Esperienza duplicata: verra sovrascritta.');
    }

    return [
        'line' => $data['line'],
        'title' => $data['title'],
        'organization' => $data['organization'],
        'partner_action' => $partner['partner_action'],
        'partner_name' => $partner['partner_name'],
        'date_label' => $data['schedule']['label'],
        'city' => $data['city'],
        'tipologia' => $data['tipologia'],
        'experience_action' => $experience_action,
        'cover_action' => $cover['action'],
        'message' => $message,
    ];
}

function opencomune_build_import_preview_summary($items) {
    $summary = [
        'valid' => 0,
        'errors' => 0,
        'partners_existing' => 0,
        'partners_to_create' => 0,
        'experiences_create' => 0,
        'experiences_update' => 0,
        'experiences_skip' => 0,
        'covers_to_fetch' => 0,
    ];

    foreach ($items as $item) {
        if ($item['experience_action'] === 'error') {
            $summary['errors']++;
            continue;
        }

        $summary['valid']++;

        if ($item['partner_action'] === 'existing') {
            $summary['partners_existing']++;
        } elseif ($item['partner_action'] === 'will_create') {
            $summary['partners_to_create']++;
        }

        if ($item['experience_action'] === 'create') {
            $summary['experiences_create']++;
        } elseif ($item['experience_action'] === 'update') {
            $summary['experiences_update']++;
        } elseif ($item['experience_action'] === 'skip') {
            $summary['experiences_skip']++;
        }

        if (($item['cover_action'] ?? '') === 'will_fetch') {
            $summary['covers_to_fetch']++;
        }
    }

    return $summary;
}

function opencomune_save_imported_esperienza($data, $options) {
    $post_status = opencomune_import_resolve_post_status($options['publish_after_import'] ?? '0');
    $update_existing = opencomune_import_should_update_existing($options['update_existing'] ?? '1');
    $import_google_cover = opencomune_import_should_fetch_google_cover($options['google_places_cover'] ?? '0');
    $duplicate_id = opencomune_find_import_esperienza_duplicate($data['title'], $data['schedule']['start']);
    $is_update = $duplicate_id > 0 && $update_existing;

    if ($duplicate_id > 0 && !$update_existing) {
        return [
            'status' => 'skipped',
            'post_id' => $duplicate_id,
            'message' => 'Esperienza gia presente (titolo + data).',
            'partner_action' => 'none',
        ];
    }

    $partner = opencomune_resolve_import_partner(
        $data['organization'],
        $data['city'],
        $data['latitude'],
        $data['longitude'],
        false
    );

    if (is_wp_error($partner)) {
        return [
            'status' => 'error',
            'post_id' => 0,
            'message' => $partner->get_error_message(),
            'partner_action' => 'error',
        ];
    }

    $author_id = $partner['partner_user_id'] > 0
        ? $partner['partner_user_id']
        : (get_current_user_id() ?: opencomune_get_partner_admin_user_id());

    $postarr = [
        'post_title' => $data['title'],
        'post_content' => $data['description'],
        'post_excerpt' => $data['excerpt'],
        'post_type' => 'esperienze',
        'post_status' => $post_status,
        'post_name' => sanitize_title($data['title']),
        'post_author' => $author_id,
    ];

    if ($is_update) {
        $postarr['ID'] = $duplicate_id;
        $post_id = wp_update_post($postarr, true);
    } else {
        $post_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($post_id)) {
        return [
            'status' => 'error',
            'post_id' => 0,
            'message' => $post_id->get_error_message(),
            'partner_action' => $partner['partner_action'],
        ];
    }

    update_post_meta($post_id, 'descrizione_breve', $data['excerpt']);
    update_post_meta($post_id, 'tour_date_mode', $data['schedule']['mode']);
    update_post_meta($post_id, 'tour_start_date', $data['schedule']['start']);
    update_post_meta($post_id, 'tour_end_date', $data['schedule']['end']);
    update_post_meta($post_id, 'tour_duration', $data['schedule']['duration'] ?: '1 giorno');
    update_post_meta($post_id, 'tour_meeting_time', $data['orari']);
    update_post_meta($post_id, 'tour_requirements', $data['booking_info']);
    update_post_meta($post_id, 'citta', $data['city']);
    update_post_meta($post_id, 'tour_meeting_point', $data['place']);
    update_post_meta($post_id, 'tour_address', $data['place']);
    update_post_meta($post_id, 'tour_latitude', $data['latitude']);
    update_post_meta($post_id, 'tour_longitude', $data['longitude']);
    update_post_meta($post_id, 'nome_organizzazione', $data['organization']);
    update_post_meta($post_id, 'precisione_coordinate', $data['coordinate_precision']);
    update_post_meta($post_id, 'pagina_fonte', $data['source_page']);
    update_post_meta($post_id, 'import_tipologia', $data['tipologia']);
    update_post_meta($post_id, '_import_source', 'csv_batch');
    update_post_meta($post_id, '_imported_at', current_time('mysql'));

    if ($partner['partner_id'] > 0) {
        update_post_meta($post_id, 'partner_id', $partner['partner_id']);
        update_post_meta($post_id, 'organizzatore_tipo', 'partner');
    }

    if ($data['categories'] !== []) {
        opencomune_save_esperienza_categories($post_id, [], $data['categories']);
    }

    $cover_result = null;
    if ($import_google_cover) {
        usleep(250000);
        $cover_result = opencomune_set_import_cover_from_google_places($post_id, $data);
    }

    $message = $is_update ? 'Esperienza sovrascritta.' : 'Esperienza creata.';
    if ($cover_result && ($cover_result['status'] ?? '') === 'success') {
        $message .= ' ' . $cover_result['message'];
    } elseif ($cover_result && ($cover_result['status'] ?? '') === 'error') {
        $message .= ' Copertina non importata: ' . $cover_result['message'];
    }

    return [
        'status' => $is_update ? 'updated' : 'created',
        'post_id' => (int) $post_id,
        'message' => $message,
        'partner_action' => $partner['partner_action'],
        'cover_status' => $cover_result['status'] ?? 'disabled',
    ];
}

function opencomune_read_import_csv_rows($file_path) {
    $handle = fopen($file_path, 'r');
    if ($handle === false) {
        return new WP_Error('csv_open', 'Impossibile aprire il file CSV.');
    }

    $header_row = fgetcsv($handle, 0, ',', '"', '\\');
    if ($header_row === false) {
        fclose($handle);
        return new WP_Error('csv_empty', 'Il file CSV e vuoto.');
    }

    $expected = opencomune_get_import_esperienze_csv_headers();
    $normalized_header = array_map('opencomune_normalize_import_csv_header', $header_row);

    foreach ($expected as $index => $column) {
        if (!isset($normalized_header[$index]) || $normalized_header[$index] !== $column) {
            fclose($handle);
            return new WP_Error(
                'csv_header',
                'Intestazione CSV non valida. Attesa: ' . implode(', ', $expected)
            );
        }
    }

    $rows = [];
    $line_number = 1;
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        $line_number++;
        if ($row === [null] || $row === [] || implode('', $row) === '') {
            continue;
        }
        $rows[] = [
            'line' => $line_number,
            'values' => $row,
        ];
    }

    fclose($handle);

    if ($rows === []) {
        return new WP_Error('csv_no_rows', 'Il file CSV non contiene righe dati.');
    }

    return $rows;
}

function opencomune_parse_import_csv_file($file) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return new WP_Error('upload_missing', 'Nessun file caricato.');
    }

    $filename = isset($file['name']) ? (string) $file['name'] : '';
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        return new WP_Error('upload_type', 'Carica un file con estensione .csv');
    }

    $rows = opencomune_read_import_csv_rows($file['tmp_name']);
    if (is_wp_error($rows)) {
        return $rows;
    }

    $mapped_rows = [];
    foreach ($rows as $entry) {
        $mapped_rows[] = opencomune_map_import_csv_row($entry['values'], $entry['line']);
    }

    return $mapped_rows;
}

function opencomune_build_import_preview_from_rows($mapped_rows, $options) {
    $items = [];
    foreach ($mapped_rows as $mapped) {
        $items[] = opencomune_build_import_preview_item($mapped, $options);
    }

    return [
        'items' => $items,
        'summary' => opencomune_build_import_preview_summary($items),
    ];
}

function opencomune_store_import_preview_payload($mapped_rows, $options) {
    $token = wp_generate_password(32, false, false);
    $valid_rows = [];

    foreach ($mapped_rows as $mapped) {
        if (!is_wp_error($mapped)) {
            $valid_rows[] = $mapped;
        }
    }

    $payload = [
        'rows' => $valid_rows,
        'options' => $options,
        'user_id' => get_current_user_id(),
        'created_at' => time(),
    ];

    set_transient('opencomune_import_' . $token, $payload, HOUR_IN_SECONDS);

    return $token;
}

function opencomune_get_import_preview_payload($token) {
    $token = sanitize_text_field((string) $token);
    if ($token === '') {
        return new WP_Error('missing_token', 'Token anteprima mancante.');
    }

    $payload = get_transient('opencomune_import_' . $token);
    if (!is_array($payload)) {
        return new WP_Error('expired_token', 'Anteprima scaduta. Ricarica il file CSV.');
    }

    if ((int) ($payload['user_id'] ?? 0) !== get_current_user_id()) {
        return new WP_Error('invalid_token', 'Token anteprima non valido.');
    }

    return $payload;
}

function opencomune_get_import_options_from_request() {
    return [
        'publish_after_import' => opencomune_import_should_publish_after_import($_POST['import_publish_after_import'] ?? '0') ? '1' : '0',
        'update_existing' => opencomune_import_should_update_existing($_POST['import_update_existing'] ?? '1') ? '1' : '0',
        'google_places_cover' => opencomune_import_should_fetch_google_cover($_POST['import_google_places_cover'] ?? '0') ? '1' : '0',
    ];
}

function opencomune_process_import_rows($mapped_rows, $options) {
    $results = [
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'partners_created' => 0,
        'covers_imported' => 0,
        'items' => [],
    ];

    foreach ($mapped_rows as $mapped) {
        if (is_wp_error($mapped)) {
            $results['errors']++;
            $results['items'][] = [
                'line' => 0,
                'title' => '',
                'organization' => '',
                'status' => 'error',
                'message' => $mapped->get_error_message(),
                'post_id' => 0,
                'edit_url' => '',
            ];
            continue;
        }

        $save = opencomune_save_imported_esperienza($mapped, $options);
        $results['items'][] = [
            'line' => $mapped['line'],
            'title' => $mapped['title'],
            'organization' => $mapped['organization'],
            'status' => $save['status'],
            'message' => $save['message'],
            'post_id' => (int) $save['post_id'],
            'edit_url' => !empty($save['post_id']) ? get_edit_post_link((int) $save['post_id'], 'raw') : '',
        ];

        if ($save['status'] === 'created') {
            $results['created']++;
        } elseif ($save['status'] === 'updated') {
            $results['updated']++;
        } elseif ($save['status'] === 'skipped') {
            $results['skipped']++;
        } else {
            $results['errors']++;
        }

        if (($save['partner_action'] ?? '') === 'created') {
            $results['partners_created']++;
        }

        if (($save['cover_status'] ?? '') === 'success') {
            $results['covers_imported']++;
        }
    }

    return $results;
}

function opencomune_import_csv_preview_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti.'], 403);
    }

    check_ajax_referer('opencomune_import_csv_ajax', 'nonce');

    $options = opencomune_get_import_options_from_request();

    if (empty($_FILES['import_csv_file']['name'])) {
        wp_send_json_error(['message' => 'Seleziona un file CSV da importare.']);
    }

    $mapped_rows = opencomune_parse_import_csv_file($_FILES['import_csv_file']);
    if (is_wp_error($mapped_rows)) {
        wp_send_json_error(['message' => $mapped_rows->get_error_message()]);
    }

    $preview = opencomune_build_import_preview_from_rows($mapped_rows, $options);
    $token = opencomune_store_import_preview_payload($mapped_rows, $options);

    wp_send_json_success([
        'token' => $token,
        'items' => $preview['items'],
        'summary' => $preview['summary'],
    ]);
}
add_action('wp_ajax_opencomune_import_csv_preview', 'opencomune_import_csv_preview_ajax');

function opencomune_import_csv_confirm_ajax() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Permessi insufficienti.'], 403);
    }

    check_ajax_referer('opencomune_import_csv_ajax', 'nonce');

    $token = sanitize_text_field(wp_unslash($_POST['token'] ?? ''));
    $payload = opencomune_get_import_preview_payload($token);
    if (is_wp_error($payload)) {
        wp_send_json_error(['message' => $payload->get_error_message()]);
    }

    $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
    $results = opencomune_process_import_rows($payload['rows'] ?? [], $options);
    delete_transient('opencomune_import_' . $token);

    wp_send_json_success([
        'message' => sprintf(
            'Import completato: %d create, %d aggiornate, %d saltate, %d errori, %d partner creati, %d copertine da Google Places.',
            (int) $results['created'],
            (int) $results['updated'],
            (int) $results['skipped'],
            (int) $results['errors'],
            (int) $results['partners_created'],
            (int) $results['covers_imported']
        ),
        'results' => $results,
    ]);
}
add_action('wp_ajax_opencomune_import_csv_confirm', 'opencomune_import_csv_confirm_ajax');

function opencomune_render_import_esperienze_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $headers = opencomune_get_import_esperienze_csv_headers();
    ?>
    <div class="wrap">
        <h1>Importa esperienze da CSV</h1>
        <p class="description">
            Carica un file CSV, controlla l'anteprima nel modal e conferma l'importazione.
            L'organizzazione viene collegata al Partner: se non esiste, viene creato automaticamente.
        </p>

        <div class="opencomune-import-notices"></div>

        <div class="card" style="max-width: 960px; padding: 16px 20px; margin-top: 16px;">
            <h2>Formato CSV atteso</h2>
            <p>La prima riga deve contenere esattamente queste colonne, in questo ordine:</p>
            <code><?php echo esc_html(implode(',', $headers)); ?></code>

            <table class="widefat striped" style="margin-top: 16px;">
                <thead>
                    <tr>
                        <th>Colonna CSV</th>
                        <th>Campo esperienza</th>
                        <th>Note</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>nome_evento</td><td>Titolo</td><td>Obbligatorio</td></tr>
                    <tr><td>nome_organizzazione</td><td>Partner collegato</td><td>Se assente nel sistema, viene creato in stato approvato</td></tr>
                    <tr><td>data</td><td>Date esperienza</td><td>Formati: <code>20/08/2026</code>, <code>20/08/2026 - 03/09/2026</code>, <code>Dal 21/08/2026</code></td></tr>
                    <tr><td>orari</td><td><code>tour_meeting_time</code></td><td>Testo libero</td></tr>
                    <tr><td>info_e_prenotazioni</td><td><code>tour_requirements</code></td><td>Contatti e modalita di prenotazione</td></tr>
                    <tr><td>citta</td><td><code>citta</code> e comune partner</td><td>Comune</td></tr>
                    <tr><td>luogo</td><td><code>tour_meeting_point</code>, <code>tour_address</code></td><td>Indirizzo o area</td></tr>
                    <tr><td>tipologia</td><td><code>categorie_esperienze</code></td><td>Valori separati da <code>/</code></td></tr>
                    <tr><td>latitudine / longitudine</td><td>Coordinate mappa</td><td>Decimale</td></tr>
                    <tr><td>precisione_coordinate</td><td>Meta dedicata</td><td>es. bassa, media, alta</td></tr>
                    <tr><td>descrizione_evento</td><td>Contenuto</td><td>Descrizione completa</td></tr>
                    <tr><td>pagina_fonte</td><td>Meta <code>pagina_fonte</code></td><td>Riferimento brochure</td></tr>
                </tbody>
            </table>
        </div>

        <div id="opencomune-import-form" class="card" style="max-width: 960px; padding: 16px 20px; margin-top: 16px;">
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="import_csv_file">File CSV</label></th>
                    <td>
                        <input type="file" id="import_csv_file" name="import_csv_file" accept=".csv,text/csv">
                        <p class="description">Encoding consigliato: UTF-8. Separatore: virgola.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Pubblicazione</th>
                    <td>
                        <label>
                            <input type="checkbox" id="import_publish_after_import" name="import_publish_after_import" value="1">
                            Pubblica le esperienze dopo l'importazione
                        </label>
                        <p class="description">Se disattivato, le esperienze vengono salvate come bozza.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Copertina</th>
                    <td>
                        <label>
                            <input type="checkbox" id="import_google_places_cover" name="import_google_places_cover" value="1">
                            Importa copertina da Google Places se mancante
                        </label>
                        <p class="description">
                            Usa luogo e citta dell'esperienza per cercare una foto su Google Places.
                            Richiede la Google Maps API Key configurata in OpenComune con Places API e Place Photos abilitate.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Duplicati</th>
                    <td>
                        <label>
                            <input type="checkbox" id="import_update_existing" name="import_update_existing" value="1" checked>
                            Sovrascrivi esperienze esistenti con stesso titolo e data inizio
                        </label>
                        <p class="description">Attivo di default: gli eventi gia presenti vengono aggiornati con i dati del CSV.</p>
                    </td>
                </tr>
            </table>

            <p>
                <button type="button" class="button button-primary" id="opencomune-import-preview-btn">
                    Anteprima importazione
                </button>
            </p>
        </div>

        <div id="opencomune-import-loader" class="opencomune-import-loader" aria-hidden="true" aria-live="polite">
            <div class="opencomune-import-loader__panel">
                <span class="opencomune-import-loader__spinner" aria-hidden="true"></span>
                <p class="opencomune-import-loader__message">Elaborazione in corso...</p>
                <p class="opencomune-import-loader__hint">Non chiudere la pagina finche l'operazione non e completata.</p>
            </div>
        </div>

        <div id="opencomune-import-results-wrap" style="display:none; max-width: 1200px; margin-top: 20px;">
            <h2>Risultato importazione</h2>
            <table id="opencomune-import-results-table" class="widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:70px;">Riga</th>
                        <th>Titolo</th>
                        <th>Organizzazione</th>
                        <th style="width:110px;">Esito</th>
                        <th>Dettaglio</th>
                        <th style="width:110px;">ID</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <div id="opencomune-import-preview-modal" class="opencomune-import-modal" aria-hidden="true">
        <div class="opencomune-import-modal__backdrop" data-opencomune-import-close></div>
        <div class="opencomune-import-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="opencomune-import-preview-title">
            <div class="opencomune-import-modal__header">
                <h2 id="opencomune-import-preview-title" style="margin:0;">Anteprima importazione CSV</h2>
            </div>
            <div class="opencomune-import-modal__body">
                <p id="opencomune-import-preview-summary" class="opencomune-import-modal__summary"></p>
                <table id="opencomune-import-preview-table" class="widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width:50px;">Riga</th>
                            <th>Evento</th>
                            <th>Organizzazione</th>
                            <th style="width:130px;">Partner</th>
                            <th style="width:150px;">Data</th>
                            <th style="width:100px;">Citta</th>
                            <th style="width:150px;">Tipologia</th>
                            <th style="width:120px;">Esperienza</th>
                            <th>Note</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="opencomune-import-modal__footer">
                <button type="button" class="button" data-opencomune-import-close>Annulla</button>
                <button type="button" class="button button-primary" id="opencomune-import-confirm-btn">
                    Conferma e importa
                </button>
            </div>
        </div>
    </div>
    <?php
}
