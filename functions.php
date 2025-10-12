<?php
// Increase memory limit
ini_set('memory_limit', '256M');

// Enqueue Tailwind CSS
function opencomune_enqueue_styles() {
    wp_enqueue_style('opencomune-tailwind', get_stylesheet_uri(), [], filemtime(get_template_directory() . '/style.css'));
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_styles');

// Enqueue Google Fonts
function opencomune_enqueue_fonts() {
    wp_enqueue_style(
        'opencomune-fonts',
        'https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:ital,wght@0,400;0,700;1,400;1,700&display=swap',
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
                'assign_terms' => 'edit_posts',
            ],
        ]
    );
}
add_action('init', 'opencomune_register_categorie_esperienze_taxonomy');

// Custom Post Type: Esperienze
function opencomune_register_cpt_esperienze() {
    register_post_type('esperienze', [
        'label' => 'Esperienze',
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'esperienze'],
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'author','comments'],
        'show_in_rest' => true,
        'taxonomies' => ['post_tag', 'categorie_esperienze'],
    ]);
}
add_action('init', 'opencomune_register_cpt_esperienze');

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
        'label' => 'Partner',
        'public' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'partner'],
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'opencomune_register_cpt_partner');

// Ruoli personalizzati
function opencomune_add_custom_roles() {
    add_role('editor_turistico', 'Editor Turistico', [
        'read' => true,
        'edit_posts' => true,
        'upload_files' => true,
        'edit_tours' => true,
        'publish_tours' => true,
        'edit_esperienze' => true,
        'publish_esperienze' => true,
    ]);
  
}
add_action('init', 'opencomune_add_custom_roles');

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
    echo '<style>body { font-family: "Atkinson Hyperlegible", sans-serif !important; }</style>';
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
add_action('init', 'opencomune_register_specializzazioni_taxonomy');

// Redirect editor turistico alla dashboard dopo login
add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->roles) && in_array('editor_turistico', $user->roles)) {
        return site_url('/dashboard-ufficio/');
    }
    return $redirect_to;
}, 10, 3);

// Nascondi barra admin per editor turistico
add_action('after_setup_theme', function() {
    if (current_user_can('editor_turistico')) {
        show_admin_bar(false);
    }
});

// Impedisci accesso a wp-admin per editor turistico
add_action('admin_init', function() {
    if (current_user_can('editor_turistico') && !defined('DOING_AJAX')) {
        wp_redirect(site_url('/dashboard-ufficio/'));
        exit;
    }
});

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
    if (!is_user_logged_in() || !current_user_can('guida')) {
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
    $calendario_sync = !empty($_POST['calendario_sync']);
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
    update_post_meta($post_id, 'calendario_sync', $calendario_sync);
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
    if (!is_user_logged_in() || !current_user_can('guida')) {
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
    if (!is_user_logged_in() || !current_user_can('guida')) {
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
    if (!is_user_logged_in() || !current_user_can('guida')) {
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
        'footer' => __('Footer')
    ));
}
add_action('after_setup_theme', 'registra_menu_personalizzati');

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

// Menu di fallback per enti/comuni
function opencomune_fallback_menu() {
    echo '<ul class="flex space-x-4 font-light text-xl">';
    echo '<li><a href="' . home_url('/') . '" class="hover:text-blue-600 transition-colors">Home</a></li>';
    echo '<li><a href="' . home_url('/esperienze/') . '" class="hover:text-blue-600 transition-colors">Esperienze</a></li>';
    echo '<li><a href="' . home_url('/mappa-esperienze/') . '" class="hover:text-blue-600 transition-colors">Mappa</a></li>';
    echo '<li><a href="' . home_url('/partner/') . '" class="hover:text-blue-600 transition-colors">Partner</a></li>';
    echo '<li><a href="' . home_url('/info-turistiche/') . '" class="hover:text-blue-600 transition-colors">Info</a></li>';
    echo '<li><a href="' . home_url('/contatti/') . '" class="hover:text-blue-600 transition-colors">Contatti</a></li>';
    echo '</ul>';
}

// Menu mobile di fallback per enti/comuni
function opencomune_mobile_fallback_menu() {
    echo '<ul class="flex flex-col items-center gap-8 text-2xl">';
    echo '<li><a href="' . home_url('/') . '" class="hover:text-blue-600 transition-colors">Home</a></li>';
    echo '<li><a href="' . home_url('/esperienze/') . '" class="hover:text-blue-600 transition-colors">Esperienze</a></li>';
    echo '<li><a href="' . home_url('/mappa-esperienze/') . '" class="hover:text-blue-600 transition-colors">Mappa</a></li>';
    echo '<li><a href="' . home_url('/partner/') . '" class="hover:text-blue-600 transition-colors">Partner</a></li>';
    echo '<li><a href="' . home_url('/info-turistiche/') . '" class="hover:text-blue-600 transition-colors">Info</a></li>';
    echo '<li><a href="' . home_url('/contatti/') . '" class="hover:text-blue-600 transition-colors">Contatti</a></li>';
    echo '</ul>';
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
    
    $data = array_map(function($cat) {
        return [
            'id' => $cat->term_id,
            'name' => $cat->name,
            'slug' => $cat->slug,
            'count' => $cat->count
        ];
    }, $categorie);
    
    wp_send_json_success($data);
}

// === AJAX: Restituisce tutti i tour pubblicati con lat/lon per la mappa ===
add_action('wp_ajax_opencomune_get_all_tours', 'opencomune_get_all_tours_callback');
add_action('wp_ajax_nopriv_opencomune_get_all_tours', 'opencomune_get_all_tours_callback');
function opencomune_get_all_tours_callback() {
    $args = [
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    ];
    $query = new WP_Query($args);
    $tours = [];
    foreach ($query->posts as $post) {
        $id = $post->ID;
        $gps = get_post_meta($id, 'gps', true);
        if (!$gps || strpos($gps, ',') === false) continue;
        list($lat, $lon) = array_map('trim', explode(',', $gps));
        
        // Get tour's first image (featured image)
        $tour_img = '';
        if (has_post_thumbnail($id)) {
            $tour_img = get_the_post_thumbnail_url($id, 'medium');
        } else {
            // If no featured image, try to get first image from gallery
            $gallery_ids = get_post_meta($id, 'galleria', true);
            if (is_array($gallery_ids) && !empty($gallery_ids)) {
                $tour_img = wp_get_attachment_image_url($gallery_ids[0], 'medium');
            }
        }
        
        $tags = wp_get_post_terms($id, 'post_tag', ['fields' => 'names']);
        $tours[] = [
            'id' => $id,
            'titolo' => get_the_title($id),
            'desc_breve' => get_the_excerpt($id),
            'categoria' => implode(', ', $tags), // per retrocompatibilità
            'categorie_array' => $tags, // array di tag
            'lingue' => get_post_meta($id, 'lingue', true),
            'citta' => get_post_meta($id, 'citta', true),
            'lat' => $lat,
            'lon' => $lon,
            'link' => get_permalink($id),
            'tour_img' => $tour_img,
        ];
    }
    wp_send_json_success($tours);
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
    // Save settings if form is submitted
    if (isset($_POST['opencomune_save_settings'])) {
        if (check_admin_referer('opencomune_settings_nonce')) {
            update_option('opencomune_google_maps_api_key', sanitize_text_field($_POST['google_maps_api_key']));
            update_option('opencomune_debug_mode', isset($_POST['debug_mode']) ? '1' : '0');
            update_option('opencomune_google_calendar_client_id', sanitize_text_field($_POST['google_calendar_client_id']));
            update_option('opencomune_google_calendar_client_secret', sanitize_text_field($_POST['google_calendar_client_secret']));
            update_option('opencomune_google_calendar_default', sanitize_text_field($_POST['google_calendar_default']));
            
            // Nuovi campi per ufficio turistico
            update_option('opencomune_nome_comune', sanitize_text_field($_POST['nome_comune']));
            update_option('opencomune_email_ufficio', sanitize_email($_POST['email_ufficio']));
            update_option('opencomune_telefono_ufficio', sanitize_text_field($_POST['telefono_ufficio']));
            update_option('opencomune_indirizzo_ufficio', sanitize_textarea_field($_POST['indirizzo_ufficio']));
            update_option('opencomune_orari_apertura', sanitize_textarea_field($_POST['orari_apertura']));
            
            // Campi swiper homepage
            update_option('opencomune_swiper_slide_1_title', sanitize_text_field($_POST['swiper_slide_1_title']));
            update_option('opencomune_swiper_slide_1_text', sanitize_textarea_field($_POST['swiper_slide_1_text']));
            update_option('opencomune_swiper_slide_1_icon', sanitize_text_field($_POST['swiper_slide_1_icon']));
            update_option('opencomune_swiper_slide_2_title', sanitize_text_field($_POST['swiper_slide_2_title']));
            update_option('opencomune_swiper_slide_2_text', sanitize_textarea_field($_POST['swiper_slide_2_text']));
            update_option('opencomune_swiper_slide_2_icon', sanitize_text_field($_POST['swiper_slide_2_icon']));
            update_option('opencomune_swiper_slide_3_title', sanitize_text_field($_POST['swiper_slide_3_title']));
            update_option('opencomune_swiper_slide_3_text', sanitize_textarea_field($_POST['swiper_slide_3_text']));
            update_option('opencomune_swiper_slide_3_icon', sanitize_text_field($_POST['swiper_slide_3_icon']));
            
            echo '<div class="notice notice-success"><p>Impostazioni salvate con successo!</p></div>';
        }
    }
    
    // Show connection status messages
    if (isset($_GET['calendar_connected']) && $_GET['calendar_connected'] == '1') {
        echo '<div class="notice notice-success"><p>✅ Google Calendar connesso con successo!</p></div>';
    }
    
    if (isset($_GET['calendar_error']) && $_GET['calendar_error'] == '1') {
        echo '<div class="notice notice-error"><p>❌ Errore nella connessione con Google Calendar. Riprova più tardi.</p></div>';
    }

    // Get current settings
    $api_key = get_option('opencomune_google_maps_api_key', '');
    $debug_mode = get_option('opencomune_debug_mode', '0');
    $nome_comune = get_option('opencomune_nome_comune', '');
    $email_ufficio = get_option('opencomune_email_ufficio', '');
    $telefono_ufficio = get_option('opencomune_telefono_ufficio', '');
    $indirizzo_ufficio = get_option('opencomune_indirizzo_ufficio', '');
    $orari_apertura = get_option('opencomune_orari_apertura', '');
    
    // Swiper homepage
    $swiper_slide_1_title = get_option('opencomune_swiper_slide_1_title', 'Vivi esperienze autentiche');
    $swiper_slide_1_text = get_option('opencomune_swiper_slide_1_text', 'Partecipa a tour, eventi e attività uniche, guidate da esperti locali.');
    $swiper_slide_1_icon = get_option('opencomune_swiper_slide_1_icon', 'bi-stars');
    $swiper_slide_2_title = get_option('opencomune_swiper_slide_2_title', 'Scopri e prenota facilmente');
    $swiper_slide_2_text = get_option('opencomune_swiper_slide_2_text', 'Trova l\'esperienza perfetta per te e prenota in pochi click, anche da mobile.');
    $swiper_slide_2_icon = get_option('opencomune_swiper_slide_2_icon', 'bi-search');
    $swiper_slide_3_title = get_option('opencomune_swiper_slide_3_title', 'Diventa protagonista');
    $swiper_slide_3_text = get_option('opencomune_swiper_slide_3_text', 'Crea e condividi le tue esperienze, entra nella community di Explorando.');
    $swiper_slide_3_icon = get_option('opencomune_swiper_slide_3_icon', 'bi-people');
    ?>
    <div class="wrap">
        <h1>OpenComune - Impostazioni Tema</h1>
        <form method="post" action="">
            <?php wp_nonce_field('opencomune_settings_nonce'); ?>
            <table class="form-table">
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
                <tr>
                    <th scope="row" colspan="2">
                        <h3>Google Calendar Integration</h3>
                    </th>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="google_calendar_client_id">Google Calendar Client ID</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="google_calendar_client_id" 
                               name="google_calendar_client_id" 
                               value="<?php echo esc_attr(get_option('opencomune_google_calendar_client_id', '')); ?>" 
                               class="regular-text">
                        <p class="description">
                            Enter your Google Calendar Client ID for calendar integration.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="google_calendar_client_secret">Google Calendar Client Secret</label>
                    </th>
                    <td>
                        <input type="password" 
                               id="google_calendar_client_secret" 
                               name="google_calendar_client_secret" 
                               value="<?php echo esc_attr(get_option('opencomune_google_calendar_client_secret', '')); ?>" 
                               class="regular-text">
                        <p class="description">
                            Enter your Google Calendar Client Secret for calendar integration.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>Connessione Account</label>
                    </th>
                    <td>
                        <?php 
                        $current_user_id = get_current_user_id();
                        $is_connected = get_user_meta($current_user_id, 'google_calendar_connected', true);
                        ?>
                        <?php if ($is_connected): ?>
                            <button type="button" id="disconnect-google-calendar-admin" class="button button-secondary">
                                <span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>
                                Disconnetti Google Calendar
                            </button>
                            <p class="description" style="color: green;">
                                ✅ Account Google Calendar connesso
                            </p>
                        <?php else: ?>
                            <a href="<?php echo opencomune_get_google_calendar_auth_url($current_user_id); ?>" class="button button-primary">
                                <span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>
                                Connetti Google Calendar
                            </a>
                            <p class="description" style="color: orange;">
                                ⚠️ Account Google Calendar non connesso. Connetti il tuo account per utilizzare le funzionalità del calendario.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="google_calendar_default">Calendario di Default</label>
                    </th>
                    <td>
                        <select id="google_calendar_default" name="google_calendar_default" class="regular-text">
                            <option value="primary" <?php selected(get_option('opencomune_google_calendar_default', 'primary'), 'primary'); ?>>Calendario Principale</option>
                        </select>
                        <button type="button" id="load-calendars" class="button button-secondary" style="margin-left: 10px;">
                            Carica Calendari
                        </button>
                        <p class="description">
                            Seleziona il calendario di default per i tour. Clicca "Carica Calendari" per aggiornare la lista.
                        </p>
                        <div id="calendars-loading" style="margin-top: 10px; display: none;">
                            <span class="spinner is-active"></span> Caricamento calendari...
                        </div>
                        <div id="calendars-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label>Database Table</label>
                    </th>
                    <td>
                        <button type="button" id="create-calendar-table" class="button button-secondary">
                            Create Calendar Table
                        </button>
                        <p class="description">
                            Create the database table for storing calendar events. Click this if you see database errors.
                        </p>
                        <div id="calendar-table-result" style="margin-top: 10px;"></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row" colspan="2">
                        <h3>Informazioni Ufficio Turistico</h3>
                    </th>
                </tr>
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
                        <p class="description">
                            Nome del comune (es. "Comune di Lecce")
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="email_ufficio">Email Ufficio Turistico</label>
                    </th>
                    <td>
                        <input type="email" 
                               id="email_ufficio" 
                               name="email_ufficio" 
                               value="<?php echo esc_attr($email_ufficio); ?>" 
                               class="regular-text">
                        <p class="description">
                            Email per ricevere prenotazioni e contatti
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="telefono_ufficio">Telefono Ufficio</label>
                    </th>
                    <td>
                        <input type="tel" 
                               id="telefono_ufficio" 
                               name="telefono_ufficio" 
                               value="<?php echo esc_attr($telefono_ufficio); ?>" 
                               class="regular-text">
                        <p class="description">
                            Numero di telefono dell'ufficio turistico
                        </p>
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
                        <p class="description">
                            Indirizzo completo dell'ufficio turistico
                        </p>
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
                        <p class="description">
                            Orari di apertura dell'ufficio turistico
                        </p>
                    </td>
                </tr>
                
                <!-- Sezione Swiper Homepage -->
                <tr>
                    <th scope="row" colspan="2">
                        <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-top: 1px solid #ddd;">Homepage Swiper - Configurazione Slide</h3>
                    </th>
                </tr>
                
                <!-- Slide 1 -->
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
                                  rows="2" 
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
                        <p class="description">Nome dell'icona Bootstrap Icons (es: bi-stars, bi-search, bi-people)</p>
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
                                  rows="2" 
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
                        <p class="description">Nome dell'icona Bootstrap Icons (es: bi-stars, bi-search, bi-people)</p>
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
                                  rows="2" 
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
                        <p class="description">Nome dell'icona Bootstrap Icons (es: bi-stars, bi-search, bi-people)</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" 
                       name="opencomune_save_settings" 
                       class="button-primary" 
                       value="Salva Impostazioni">
            </p>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        $('#create-calendar-table').on('click', function() {
            var button = $(this);
            var resultDiv = $('#calendar-table-result');
            
            button.prop('disabled', true).text('Creating...');
            resultDiv.html('');
            
            $.post(ajaxurl, {
                action: 'opencomune_create_calendar_table'
            }, function(response) {
                if (response.success) {
                    resultDiv.html('<div class="notice notice-success"><p>' + response.data + '</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>Error: ' + (response.data || 'Unknown error') + '</p></div>');
                }
            }).fail(function() {
                resultDiv.html('<div class="notice notice-error"><p>Network error occurred</p></div>');
            }).always(function() {
                button.prop('disabled', false).text('Create Calendar Table');
            });
        });
        
        // Load Google Calendars in settings
        $('#load-calendars').on('click', function() {
            var button = $(this);
            var loadingDiv = $('#calendars-loading');
            var resultDiv = $('#calendars-result');
            var select = $('#google_calendar_default');
            
            button.prop('disabled', true);
            loadingDiv.show();
            resultDiv.html('');
            
            $.post(ajaxurl, {
                action: 'opencomune_get_google_calendars'
            }, function(response) {
                if (response.success && response.data) {
                    // Save current selection
                    var currentValue = select.val();
                    
                    // Clear and add primary calendar
                    select.empty();
                    select.append('<option value="primary">Calendario Principale</option>');
                    
                    // Add other calendars
                    response.data.forEach(function(calendar) {
                        if (calendar.accessRole === 'owner' || calendar.accessRole === 'writer') {
                            var selected = (calendar.id === currentValue) ? 'selected' : '';
                            select.append('<option value="' + calendar.id + '" ' + selected + '>' + calendar.summary + '</option>');
                        }
                    });
                    
                    // Restore selection if still valid
                    if (select.find('option[value="' + currentValue + '"]').length > 0) {
                        select.val(currentValue);
                    }
                    
                    resultDiv.html('<div class="notice notice-success"><p>Calendari caricati con successo! Trovati ' + response.data.length + ' calendari.</p></div>');
                } else {
                    resultDiv.html('<div class="notice notice-error"><p>Errore: ' + (response.data || 'Errore sconosciuto') + '</p></div>');
                }
            }).fail(function(xhr, status, error) {
                resultDiv.html('<div class="notice notice-error"><p>Errore di rete: ' + error + '</p></div>');
            }).always(function() {
                button.prop('disabled', false);
                loadingDiv.hide();
            });
        });
        
        // Disconnect Google Calendar in admin settings
        $('#disconnect-google-calendar-admin').on('click', function() {
            if (confirm('Sei sicuro di voler disconnettere Google Calendar? Tutti i token verranno rimossi.')) {
                var button = $(this);
                button.prop('disabled', true).text('Disconnessione...');
                
                $.post(ajaxurl, {
                    action: 'opencomune_disconnect_google_calendar'
                }, function(response) {
                    if (response.success) {
                        location.reload(); // Reload page to show connect button
                    } else {
                        alert('Errore durante la disconnessione: ' + (response.data || 'Errore sconosciuto'));
                        button.prop('disabled', false).html('<span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>Disconnetti Google Calendar');
                    }
                }).fail(function() {
                    alert('Errore di rete durante la disconnessione');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-calendar-alt" style="margin-right: 5px;"></span>Disconnetti Google Calendar');
                });
            }
        });
    });
    </script>
    <?php
}

// Function to get Google Maps API key
function opencomune_get_google_maps_api_key() {
    return get_option('opencomune_google_maps_api_key', '');
}

// Function to get Google Calendar Client ID
function opencomune_get_google_calendar_client_id() {
    return get_option('opencomune_google_calendar_client_id', '');
}

// Function to get Google Calendar Client Secret
function opencomune_get_google_calendar_client_secret() {
    return get_option('opencomune_google_calendar_client_secret', '');
}

// Function to check if Google Calendar integration is enabled
function opencomune_is_google_calendar_enabled() {
    $client_id = opencomune_get_google_calendar_client_id();
    $client_secret = opencomune_get_google_calendar_client_secret();
    return !empty($client_id) && !empty($client_secret);
}

// Function to get default Google Calendar
function opencomune_get_google_calendar_default() {
    return get_option('opencomune_google_calendar_default', 'primary');
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

add_action('wp_ajax_opencomune_search_tour_autocomplete', 'opencomune_search_tour_autocomplete');
add_action('wp_ajax_nopriv_opencomune_search_tour_autocomplete', 'opencomune_search_tour_autocomplete');
function opencomune_search_tour_autocomplete() {
    $q = isset($_GET['q']) ? sanitize_text_field($_GET['q']) : '';
    $results = [];
    if ($q) {
        $args = [
            'post_type' => ['esperienze'],
            'posts_per_page' => 20, // recupera più risultati per filtrare dopo
            'post_status' => 'publish',
            's' => $q,
            'fields' => 'ids'
        ];
        $query = new WP_Query($args);
        foreach ($query->posts as $post_id) {
            $post = get_post($post_id);
            $citta = get_post_meta($post_id, 'citta', true);
            $match_title = stripos($post->post_title, $q) !== false;
            $match_citta = stripos($citta, $q) !== false;
            if ($match_title || $match_citta) {
                $img = get_the_post_thumbnail_url($post_id, 'thumbnail') ?: 'https://via.placeholder.com/80x80?text=Tour';
                $results[] = [
                    'title' => get_the_title($post_id),
                    'type' => 'Tour',
                    'location' => $citta,
                    'link' => get_permalink($post_id),
                    'img' => $img
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

// Aggiungi un filtro per ottimizzare le query
add_filter('posts_where', function($where) {
    global $wpdb;
    if (is_admin() && isset($_GET['post_type']) && $_GET['post_type'] === 'esperienze') {
        $where .= $wpdb->prepare(" AND {$wpdb->posts}.post_author = %d", get_current_user_id());
    }
    return $where;
});

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
    
    // Verifica che l'evento esista e sia disponibile
    global $wpdb;
    $table_calendario = $wpdb->prefix . 'tour_calendari';
    $evento = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_calendario WHERE id = %d AND tour_id = %d",
        $event_id, $tour_id
    ));
    
    if (!$evento) {
        wp_send_json_error('Evento non trovato');
        return;
    }
    
    // Verifica disponibilità posti
    if ($evento->posti_disponibili > 0) {
        // Aggiorna i posti disponibili
        $wpdb->update(
            $table_calendario,
            ['posti_disponibili' => $evento->posti_disponibili - 1],
            ['id' => $event_id]
        );
    }
    
    // Salva la prenotazione
    $table_prenotazioni = $wpdb->prefix . 'tour_prenotazioni';
    
    // Crea la tabella se non esiste
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_prenotazioni (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_id mediumint(9) NOT NULL,
        event_id mediumint(9) NOT NULL,
        nome varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        telefono varchar(20),
        note text,
        metodo_pagamento varchar(20) DEFAULT 'guida',
        data_prenotazione datetime DEFAULT CURRENT_TIMESTAMP,
        status varchar(20) DEFAULT 'confermata',
        PRIMARY KEY (id),
        INDEX (tour_id),
        INDEX (event_id),
        INDEX (email)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    $result = $wpdb->insert($table_prenotazioni, [
        'tour_id' => $tour_id,
        'event_id' => $event_id,
        'nome' => $nome . ' ' . $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'metodo_pagamento' => $metodo_pagamento,
        'status' => 'confermata'
    ]);
    
    if ($result === false) {
        wp_send_json_error('Errore nel salvataggio della prenotazione');
        return;
    }
    
    $prenotazione_id = $wpdb->insert_id;
    
    // Invia email di conferma
    $to = $email;
    $subject = 'Conferma prenotazione - ' . $tour->post_title;
    $message = "Gentile $nome $cognome,\n\n";
    $message .= "La tua prenotazione per il tour \"" . $tour->post_title . "\" è stata confermata.\n\n";
    $message .= "Dettagli:\n";
    $message .= "- Data: " . date('d/m/Y', strtotime($data)) . "\n";
    $message .= "- Orario: $orario\n";
    $message .= "- Numero prenotazione: #$prenotazione_id\n";
    $metodo_text = ($metodo_pagamento === 'online') ? 'Online (già pagato)' : 'Alla guida il giorno del tour';
    $message .= "- Metodo di pagamento: $metodo_text\n\n";
    $message .= "Grazie per aver scelto i nostri servizi!\n\n";
    $message .= "Cordiali saluti,\nIl team di MyGuideLab";
    
    $headers = ['Content-Type: text/plain; charset=UTF-8'];
    
    wp_mail($to, $subject, $message, $headers);
    
    // Invia email di notifica alla guida
    $guida_email = get_the_author_meta('user_email', $tour->post_author);
    if ($guida_email) {
        $subject_guida = 'Nuova prenotazione - ' . $tour->post_title;
        $message_guida = "Hai ricevuto una nuova prenotazione per il tour \"" . $tour->post_title . "\".\n\n";
        $message_guida .= "Dettagli cliente:\n";
        $message_guida .= "- Nome: $nome $cognome\n";
        $message_guida .= "- Email: $email\n";
        $message_guida .= "- Telefono: $telefono\n";
        $message_guida .= "- Data: " . date('d/m/Y', strtotime($data)) . "\n";
        $message_guida .= "- Orario: $orario\n";
        $message_guida .= "- Numero prenotazione: #$prenotazione_id\n";
        $metodo_text = ($metodo_pagamento === 'online') ? 'Online (già pagato)' : 'Alla guida il giorno del tour';
        $message_guida .= "- Metodo di pagamento: $metodo_text\n";
        if (!empty($note)) {
            $message_guida .= "- Note: $note\n";
        }
        
        wp_mail($guida_email, $subject_guida, $message_guida, $headers);
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
    $telefono = $prefisso . ' ' . $numero_telefono;
    $note = sanitize_textarea_field($_POST['note'] ?? '');
    $gateway = sanitize_text_field($_POST['gateway'] ?? '');
    
    // Validazione
    if (empty($tour_id) || empty($data) || empty($orario) || empty($nome) || empty($cognome) || empty($email) || empty($numero_telefono) || empty($gateway)) {
        wp_send_json_error('Dati mancanti');
        return;
    }
    
    // Calcola il totale
    $prezzo_tour = intval(get_post_meta($tour_id, 'prezzo', true));
    $partecipanti = 1; // Per ora fisso a 1, puoi modificare per gestire più partecipanti
    $totale = $prezzo_tour * $partecipanti;
    
    // Genera ID ordine univoco
    $order_id = 'TOUR_' . $tour_id . '_' . time() . '_' . rand(1000, 9999);
    
    // Salva l'ordine in attesa di pagamento
    global $wpdb;
    $table_ordini = $wpdb->prefix . 'tour_ordini';
    
    // Crea tabella ordini se non esiste
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_ordini (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id varchar(50) NOT NULL,
        tour_id mediumint(9) NOT NULL,
        event_id mediumint(9) NOT NULL,
        nome varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        telefono varchar(20),
        note text,
        totale decimal(10,2) NOT NULL,
        gateway varchar(20) NOT NULL,
        status varchar(20) DEFAULT 'pending',
        data_creazione datetime DEFAULT CURRENT_TIMESTAMP,
        data_pagamento datetime NULL,
        PRIMARY KEY (id),
        UNIQUE KEY (order_id),
        INDEX (tour_id),
        INDEX (email),
        INDEX (status)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Inserisci l'ordine
    $result = $wpdb->insert($table_ordini, [
        'order_id' => $order_id,
        'tour_id' => $tour_id,
        'event_id' => $event_id,
        'nome' => $nome . ' ' . $cognome,
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'totale' => $totale,
        'gateway' => $gateway,
        'status' => 'pending'
    ]);
    
    if (!$result) {
        wp_send_json_error('Errore nel salvataggio dell\'ordine');
        return;
    }
    
    $ordine_id = $wpdb->insert_id;
    
    // Gestisci il gateway specifico
    if ($gateway === 'nexi') {
        $redirect_url = opencomune_processa_pagamento_nexi($order_id, $totale, $nome . ' ' . $cognome, $email);
    } elseif ($gateway === 'paypal') {
        $redirect_url = opencomune_processa_pagamento_paypal($order_id, $totale, $nome . ' ' . $cognome, $email);
    } else {
        wp_send_json_error('Gateway di pagamento non supportato');
        return;
    }
    
    if ($redirect_url) {
        wp_send_json_success([
            'redirect_url' => $redirect_url,
            'order_id' => $order_id
        ]);
    } else {
        wp_send_json_error('Errore nella configurazione del gateway di pagamento');
    }
}

// Gestione pagamento Nexi
function opencomune_processa_pagamento_nexi($order_id, $totale, $nome, $email) {
    // Configurazione Nexi (da personalizzare con le tue credenziali)
    $nexi_config = [
        'merchant_id' => get_option('nexi_merchant_id', ''),
        'api_key' => get_option('nexi_api_key', ''),
        'environment' => get_option('nexi_environment', 'test'), // 'test' o 'live'
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
function opencomune_processa_pagamento_paypal($order_id, $totale, $nome, $email) {
    // Configurazione PayPal (da personalizzare con le tue credenziali)
    $paypal_config = [
        'client_id' => get_option('paypal_client_id', ''),
        'client_secret' => get_option('paypal_client_secret', ''),
        'environment' => get_option('paypal_environment', 'sandbox'), // 'sandbox' o 'live'
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

// Gestione ritorno pagamento confermato
function opencomune_pagamento_confermato_page() {
    if (isset($_GET['order_id']) || isset($_GET['txn_id'])) {
        $order_id = sanitize_text_field($_GET['order_id'] ?? $_GET['txn_id'] ?? '');
        
        if (!empty($order_id)) {
            // Aggiorna lo status dell'ordine
            global $wpdb;
            $table_ordini = $wpdb->prefix . 'tour_ordini';
            
            $wpdb->update(
                $table_ordini,
                [
                    'status' => 'completed',
                    'data_pagamento' => current_time('mysql')
                ],
                ['order_id' => $order_id],
                ['%s', '%s'],
                ['%s']
            );
            
            // Invia email di conferma
            $ordine = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_ordini WHERE order_id = %s", $order_id));
            if ($ordine) {
                opencomune_invia_email_conferma_pagamento($ordine);
            }
        }
    }
    
    // Mostra pagina di conferma
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pagamento Confermato - MyGuideLab</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center">
            <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
                <div class="mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Pagamento Confermato!</h1>
                    <p class="text-gray-600">La tua prenotazione è stata confermata con successo.</p>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-sm text-gray-600">Riceverai una email di conferma con tutti i dettagli della prenotazione.</p>
                </div>
                
                <a href="<?php echo home_url(); ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                    Torna alla Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Gestione ritorno pagamento annullato
function opencomune_pagamento_annullato_page() {
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Pagamento Annullato - MyGuideLab</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-50">
        <div class="min-h-screen flex items-center justify-center">
            <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 text-center">
                <div class="mb-6">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-900 mb-2">Pagamento Annullato</h1>
                    <p class="text-gray-600">Il pagamento è stato annullato. La tua prenotazione non è stata confermata.</p>
                </div>
                
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <p class="text-sm text-gray-600">Puoi riprovare la prenotazione quando vuoi.</p>
                </div>
                
                <a href="<?php echo home_url(); ?>" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                    Torna alla Home
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Hook per le pagine di ritorno
add_action('init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'pagamento-confermato') {
        opencomune_pagamento_confermato_page();
    }
    if (isset($_GET['page']) && $_GET['page'] === 'pagamento-annullato') {
        opencomune_pagamento_annullato_page();
    }
});

// Email di conferma pagamento
function opencomune_invia_email_conferma_pagamento($ordine) {
    $tour_title = get_the_title($ordine->tour_id);
    $subject = 'Prenotazione confermata - ' . $tour_title;
    
    $message = "Gentile " . $ordine->nome . ",\n\n";
    $message .= "La tua prenotazione è stata confermata con successo!\n\n";
    $message .= "Dettagli prenotazione:\n";
    $message .= "- Tour: " . $tour_title . "\n";
    $message .= "- ID Ordine: " . $ordine->order_id . "\n";
    $message .= "- Totale pagato: €" . $ordine->totale . "\n";
    $message .= "- Data pagamento: " . date('d/m/Y H:i', strtotime($ordine->data_pagamento)) . "\n\n";
    $message .= "Grazie per aver scelto i nostri servizi!\n\n";
    $message .= "Cordiali saluti,\nMyGuideLab";
    
    wp_mail($ordine->email, $subject, $message);
}

// Aggiungi menu di amministrazione per le impostazioni
function opencomune_add_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=esperienze',
        'Impostazioni Pagamenti',
        'Impostazioni Pagamenti',
        'manage_options',
        'opencomune-settings',
        'opencomune_settings_page'
    );
}
add_action('admin_menu', 'opencomune_add_admin_menu');

// Pagina delle impostazioni
function opencomune_settings_page() {
    // Salva le impostazioni
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['opencomune_settings_nonce'], 'opencomune_settings')) {
        // Nexi Pay
        update_option('nexi_merchant_id', sanitize_text_field($_POST['nexi_merchant_id']));
        update_option('nexi_api_key', sanitize_text_field($_POST['nexi_api_key']));
        update_option('nexi_environment', sanitize_text_field($_POST['nexi_environment']));
        
        // PayPal
        update_option('paypal_client_id', sanitize_text_field($_POST['paypal_client_id']));
        update_option('paypal_client_secret', sanitize_text_field($_POST['paypal_client_secret']));
        update_option('paypal_environment', sanitize_text_field($_POST['paypal_environment']));
        
        echo '<div class="notice notice-success"><p>Impostazioni salvate con successo!</p></div>';
    }
    
    // Recupera i valori attuali
    $nexi_merchant_id = get_option('nexi_merchant_id', '');
    $nexi_api_key = get_option('nexi_api_key', '');
    $nexi_environment = get_option('nexi_environment', 'test');
    $paypal_client_id = get_option('paypal_client_id', '');
    $paypal_client_secret = get_option('paypal_client_secret', '');
    $paypal_environment = get_option('paypal_environment', 'sandbox');
    
    ?>
    <div class="wrap">
        <h1>Gateway Pagamenti</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('opencomune_settings', 'opencomune_settings_nonce'); ?>
            
            <div class="card" style="max-width: 800px;">
                <h2 class="title">Nexi Pay</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="nexi_merchant_id">Merchant ID</label>
                        </th>
                        <td>
                            <input type="text" id="nexi_merchant_id" name="nexi_merchant_id" 
                                   value="<?php echo esc_attr($nexi_merchant_id); ?>" class="regular-text" />
                            <p class="description">Il tuo Merchant ID fornito da Nexi</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nexi_api_key">API Key</label>
                        </th>
                        <td>
                            <input type="password" id="nexi_api_key" name="nexi_api_key" 
                                   value="<?php echo esc_attr($nexi_api_key); ?>" class="regular-text" />
                            <p class="description">La tua API Key segreta di Nexi</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nexi_environment">Ambiente</label>
                        </th>
                        <td>
                            <select id="nexi_environment" name="nexi_environment">
                                <option value="test" <?php selected($nexi_environment, 'test'); ?>>Test</option>
                                <option value="live" <?php selected($nexi_environment, 'live'); ?>>Produzione</option>
                            </select>
                            <p class="description">Usa 'Test' per i test, 'Produzione' per i pagamenti reali</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2 class="title">PayPal</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="paypal_client_id">Client ID</label>
                        </th>
                        <td>
                            <input type="text" id="paypal_client_id" name="paypal_client_id" 
                                   value="<?php echo esc_attr($paypal_client_id); ?>" class="regular-text" />
                            <p class="description">Il tuo Client ID di PayPal</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="paypal_client_secret">Client Secret</label>
                        </th>
                        <td>
                            <input type="password" id="paypal_client_secret" name="paypal_client_secret" 
                                   value="<?php echo esc_attr($paypal_client_secret); ?>" class="regular-text" />
                            <p class="description">Il tuo Client Secret di PayPal</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="paypal_environment">Ambiente</label>
                        </th>
                        <td>
                            <select id="paypal_environment" name="paypal_environment">
                                <option value="sandbox" <?php selected($paypal_environment, 'sandbox'); ?>>Sandbox (Test)</option>
                                <option value="live" <?php selected($paypal_environment, 'live'); ?>>Produzione</option>
                            </select>
                            <p class="description">Usa 'Sandbox' per i test, 'Produzione' per i pagamenti reali</p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2 class="title">Informazioni</h2>
                <div style="padding: 15px;">
                    <h3>Come ottenere le credenziali:</h3>
                    
                    <h4>Nexi Pay:</h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Registrati su <a href="https://www.nexi.it" target="_blank">nexi.it</a></li>
                        <li>Accedi al tuo account merchant</li>
                        <li>Vai alla sezione "API e Integrazioni"</li>
                        <li>Genera le tue credenziali API</li>
                    </ul>
                    
                    <h4>PayPal:</h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li>Registrati su <a href="https://developer.paypal.com" target="_blank">developer.paypal.com</a></li>
                        <li>Crea un'applicazione</li>
                        <li>Ottieni Client ID e Client Secret</li>
                        <li>Configura gli URL di ritorno</li>
                    </ul>
                    
                    <h4>URL di ritorno da configurare:</h4>
                    <ul style="list-style: disc; margin-left: 20px;">
                        <li><strong>Successo:</strong> <?php echo home_url('/pagamento-confermato/'); ?></li>
                        <li><strong>Annullamento:</strong> <?php echo home_url('/pagamento-annullato/'); ?></li>
                        <li><strong>Webhook:</strong> <?php echo home_url('/wp-admin/admin-ajax.php?action=nexi_webhook'); ?></li>
                    </ul>
                </div>
            </div>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Salva Impostazioni">
            </p>
        </form>
    </div>
    
    <style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .card .title {
        margin-top: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid #eee;
    }
    </style>
    <?php
}

// ============================================================================
// GOOGLE CALENDAR INTEGRATION FUNCTIONS
// ============================================================================

// Function to generate Google Calendar authorization URL
function opencomune_get_google_calendar_auth_url($user_id) {
    if (!opencomune_is_google_calendar_enabled()) {
        return false;
    }
    
    $client_id = opencomune_get_google_calendar_client_id();
    $redirect_uri = home_url('/wp-admin/admin-ajax.php?action=opencomune_google_calendar_callback');
    
    // Alternative redirect URI if needed
    // $redirect_uri = home_url('/google-calendar-callback/');
    
    // Debug: log the redirect URI
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Redirect URI: ' . $redirect_uri);
    }
    
    $scope = 'https://www.googleapis.com/auth/calendar';
    
    $params = array(
        'client_id' => $client_id,
        'redirect_uri' => $redirect_uri,
        'scope' => $scope,
        'response_type' => 'code',
        'access_type' => 'online' // Changed to online for testing
    );
    
    $auth_url = 'https://accounts.google.com/o/oauth2/auth?' . http_build_query($params);
    
    // Debug: log the complete auth URL
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Auth URL: ' . $auth_url);
    }
    
    return $auth_url;
}

// Function to exchange authorization code for access token
function opencomune_exchange_google_calendar_code($code) {
    if (!opencomune_is_google_calendar_enabled()) {
        return false;
    }
    
    $client_id = opencomune_get_google_calendar_client_id();
    $client_secret = opencomune_get_google_calendar_client_secret();
    $redirect_uri = home_url('/wp-admin/admin-ajax.php?action=opencomune_google_calendar_callback');
    
    $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'body' => array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirect_uri
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['access_token'])) {
        return $data;
    }
    
    return false;
}

// Function to refresh Google Calendar access token
function opencomune_refresh_google_calendar_token($user_id) {
    $refresh_token = get_user_meta($user_id, 'google_calendar_refresh_token', true);
    if (empty($refresh_token)) {
        return false;
    }
    
    $client_id = opencomune_get_google_calendar_client_id();
    $client_secret = opencomune_get_google_calendar_client_secret();
    
    $response = wp_remote_post('https://oauth2.googleapis.com/token', array(
        'body' => array(
            'client_id' => $client_id,
            'client_secret' => $client_secret,
            'refresh_token' => $refresh_token,
            'grant_type' => 'refresh_token'
        )
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['access_token'])) {
        update_user_meta($user_id, 'google_calendar_access_token', $data['access_token']);
        update_user_meta($user_id, 'google_calendar_token_expires', time() + $data['expires_in']);
        return $data['access_token'];
    }
    
    return false;
}

// Function to get valid Google Calendar access token
function opencomune_get_google_calendar_access_token($user_id) {
    $access_token = get_user_meta($user_id, 'google_calendar_access_token', true);
    $expires = get_user_meta($user_id, 'google_calendar_token_expires', true);
    
    if (empty($access_token)) {
        return false;
    }
    
    // Check if token is expired or will expire in the next 5 minutes
    if ($expires && $expires <= (time() + 300)) {
        $access_token = opencomune_refresh_google_calendar_token($user_id);
    }
    
    return $access_token;
}

// Function to create Google Calendar event
function opencomune_create_google_calendar_event($user_id, $tour_id, $date, $time, $posti, $note = '', $durata = null, $calendar_id = 'primary') {
    $access_token = opencomune_get_google_calendar_access_token($user_id);
    if (!$access_token) {
        return false;
    }
    
    $tour = get_post($tour_id);
    $tour_title = $tour->post_title;
    $citta = get_post_meta($tour_id, 'citta', true);
    $prezzo = get_post_meta($tour_id, 'prezzo', true);
    
    // Parse time
    $time_parts = explode(':', $time);
    $hour = intval($time_parts[0]);
    $minute = intval($time_parts[1]);
    
    // Create datetime objects with Italian timezone
    $timezone = new DateTimeZone('Europe/Rome');
    $start_datetime = new DateTime($date . ' ' . $time, $timezone);
    $end_datetime = clone $start_datetime;
    $end_datetime->add(new DateInterval('PT2H')); // Default 2 hours duration
    
    // Debug: log the datetime information
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Event - Date: ' . $date . ', Time: ' . $time);
        error_log('Google Calendar Event - Start DateTime: ' . $start_datetime->format('Y-m-d H:i:s T'));
        error_log('Google Calendar Event - End DateTime: ' . $end_datetime->format('Y-m-d H:i:s T'));
    }
    
    // Get tour duration from parameter, meta, or use default 2 hours
    if ($durata !== null && $durata > 0) {
        $tour_duration = $durata;
    } else {
        $tour_duration = get_post_meta($tour_id, 'durata', true);
        if (empty($tour_duration)) {
            $tour_duration = 2; // Default 2 hours
        }
    }
    
    // Calculate end time based on tour duration
    $end_datetime = clone $start_datetime;
    $end_datetime->add(new DateInterval('PT' . $tour_duration . 'H'));
    
    $event_data = array(
        'summary' => $tour_title,
        'description' => "Tour: $tour_title\nCittà: $citta\nPrezzo: €$prezzo\nDurata: {$tour_duration}h\nPosti disponibili: $posti\nNote: $note",
        'start' => array(
            'dateTime' => $start_datetime->format('c'),
            'timeZone' => 'Europe/Rome'
        ),
        'end' => array(
            'dateTime' => $end_datetime->format('c'),
            'timeZone' => 'Europe/Rome'
        ),
        'location' => $citta,
        'transparency' => 'opaque',
        'visibility' => 'public'
    );
    
    $response = wp_remote_post('https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token,
            'Content-Type' => 'application/json'
        ),
        'body' => json_encode($event_data)
    ));
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (isset($data['id'])) {
        return $data['id'];
    }
    
    return false;
}

// Function to get user's Google Calendars
function opencomune_get_google_calendars($user_id) {
    $access_token = opencomune_get_google_calendar_access_token($user_id);
    if (!$access_token) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar Debug: No access token for user ' . $user_id);
        }
        return false;
    }
    
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Debug: Getting calendars with token: ' . substr($access_token, 0, 20) . '...');
    }
    
    $response = wp_remote_get('https://www.googleapis.com/calendar/v3/users/me/calendarList', array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar Debug: WP Error: ' . $response->get_error_message());
        }
        return false;
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Debug: Response code: ' . $response_code);
        error_log('Google Calendar Debug: Response body: ' . $body);
    }
    
    if ($response_code !== 200) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar Debug: Non-200 response code: ' . $response_code);
        }
        return false;
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar Debug: JSON decode error: ' . json_last_error_msg());
        }
        return false;
    }
    
    if (isset($data['items'])) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar Debug: Found ' . count($data['items']) . ' calendars');
        }
        return $data['items'];
    }
    
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Debug: No items found in response');
    }
    
    return false;
}

// Function to delete Google Calendar event
function opencomune_delete_google_calendar_event($user_id, $event_id, $calendar_id = 'primary') {
    $access_token = opencomune_get_google_calendar_access_token($user_id);
    if (!$access_token) {
        return false;
    }
    
    $response = wp_remote_request('https://www.googleapis.com/calendar/v3/calendars/' . urlencode($calendar_id) . '/events/' . $event_id, array(
        'method' => 'DELETE',
        'headers' => array(
            'Authorization' => 'Bearer ' . $access_token
        )
    ));
    
    return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 204;
}

// AJAX handler for Google Calendar authorization callback
add_action('wp_ajax_opencomune_google_calendar_callback', 'opencomune_google_calendar_callback_handler');
add_action('wp_ajax_nopriv_opencomune_google_calendar_callback', 'opencomune_google_calendar_callback_handler');
function opencomune_google_calendar_callback_handler() {
    // Debug: log the callback
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar Callback - GET params: ' . print_r($_GET, true));
        error_log('Google Calendar Callback - Current URL: ' . home_url('/wp-admin/admin-ajax.php?action=opencomune_google_calendar_callback'));
    }
    
    if (isset($_GET['code'])) {
        $code = sanitize_text_field($_GET['code']);
        $state = isset($_GET['state']) ? json_decode(base64_decode($_GET['state']), true) : array();
        $user_id = isset($state['user_id']) ? intval($state['user_id']) : get_current_user_id();
        
        $token_data = opencomune_exchange_google_calendar_code($code);
        
        if ($token_data) {
            update_user_meta($user_id, 'google_calendar_access_token', $token_data['access_token']);
            update_user_meta($user_id, 'google_calendar_refresh_token', $token_data['refresh_token']);
            update_user_meta($user_id, 'google_calendar_token_expires', time() + $token_data['expires_in']);
            update_user_meta($user_id, 'google_calendar_connected', '1');
            
            // Redirect to admin settings if user is admin, otherwise to dashboard
            if (current_user_can('manage_options')) {
                wp_redirect(admin_url('admin.php?page=opencomune-settings&calendar_connected=1'));
            } else {
                wp_redirect(home_url('/dashboard-guida/?calendar_connected=1'));
            }
            exit;
        } else {
            if (current_user_can('manage_options')) {
                wp_redirect(admin_url('admin.php?page=opencomune-settings&calendar_error=1'));
            } else {
                wp_redirect(home_url('/dashboard-guida/?calendar_error=1'));
            }
            exit;
        }
    }
    
    wp_redirect(home_url('/dashboard-guida/'));
    exit;
}

// AJAX handler for syncing calendar events to Google Calendar
add_action('wp_ajax_opencomune_sync_to_google_calendar', 'opencomune_sync_to_google_calendar_handler');
function opencomune_sync_to_google_calendar_handler() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $user_id = get_current_user_id();
    $tour_id = intval($_POST['tour_id']);
    $date = sanitize_text_field($_POST['date']);
    $time = sanitize_text_field($_POST['time']);
    $posti = intval($_POST['posti']);
    $durata = floatval($_POST['durata']);
    $note = sanitize_text_field($_POST['note']);
    $calendar_id = sanitize_text_field($_POST['calendar_id']);
    
    // Use default calendar if none specified
    if (empty($calendar_id)) {
        $calendar_id = opencomune_get_google_calendar_default();
    }
    
    // Check if user owns the tour
    $tour = get_post($tour_id);
    if (!$tour || $tour->post_author != $user_id) {
        wp_send_json_error('Tour not found or access denied');
        return;
    }
    
    // Check if Google Calendar is connected
    $is_connected = get_user_meta($user_id, 'google_calendar_connected', true);
    if (!$is_connected) {
        wp_send_json_error('Google Calendar not connected');
        return;
    }
    
    // Create Google Calendar event
    $event_id = opencomune_create_google_calendar_event($user_id, $tour_id, $date, $time, $posti, $note, $durata, $calendar_id);
    
    if ($event_id) {
        // Store the Google Calendar event ID in the database
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'tour_calendar',
            array(
                'tour_id' => $tour_id,
                'data' => $date,
                'ora' => $time,
                'posti' => $posti,
                'note' => $note,
                'google_calendar_event_id' => $event_id
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s')
        );
        
        wp_send_json_success(array(
            'message' => 'Event synced to Google Calendar successfully',
            'event_id' => $event_id
        ));
    } else {
        wp_send_json_error('Failed to create Google Calendar event');
    }
}

// Function to create tour calendar table
function opencomune_create_tour_calendar_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'tour_calendar';
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        return true;
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        tour_id bigint(20) NOT NULL,
        data date NOT NULL,
        ora time NOT NULL,
        posti int(11) DEFAULT 0,
        note text,
        google_calendar_event_id varchar(255),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY tour_id (tour_id),
        KEY data (data),
        KEY google_calendar_event_id (google_calendar_event_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
}

// Hook to create table on theme activation
add_action('after_switch_theme', 'opencomune_create_tour_calendar_table');

// Also create table on init if it doesn't exist (for existing installations)
add_action('init', function() {
    if (!opencomune_create_tour_calendar_table()) {
        error_log('Failed to create tour_calendar table');
    }
});

// AJAX handler for disconnecting Google Calendar
add_action('wp_ajax_opencomune_disconnect_google_calendar', 'opencomune_disconnect_google_calendar_handler');
function opencomune_disconnect_google_calendar_handler() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Delete user meta
    delete_user_meta($user_id, 'google_calendar_access_token');
    delete_user_meta($user_id, 'google_calendar_refresh_token');
    delete_user_meta($user_id, 'google_calendar_token_expires');
    delete_user_meta($user_id, 'google_calendar_connected');
    
    wp_send_json_success('Google Calendar disconnected successfully');
}

// AJAX handler for getting Google Calendars
add_action('wp_ajax_opencomune_get_google_calendars', 'opencomune_get_google_calendars_handler');
function opencomune_get_google_calendars_handler() {
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $user_id = get_current_user_id();
    
    if (opencomune_is_debug_mode()) {
        error_log('Google Calendar AJAX Debug: User ID: ' . $user_id);
        error_log('Google Calendar AJAX Debug: Is connected: ' . get_user_meta($user_id, 'google_calendar_connected', true));
    }
    
    // Check if Google Calendar is connected
    $is_connected = get_user_meta($user_id, 'google_calendar_connected', true);
    if (!$is_connected) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar AJAX Debug: User not connected to Google Calendar');
        }
        wp_send_json_error('Google Calendar not connected. Please connect your account first.');
        return;
    }
    
    $calendars = opencomune_get_google_calendars($user_id);
    
    if ($calendars !== false) {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar AJAX Debug: Successfully got calendars');
        }
        wp_send_json_success($calendars);
    } else {
        if (opencomune_is_debug_mode()) {
            error_log('Google Calendar AJAX Debug: Failed to get calendars');
        }
        wp_send_json_error('Failed to get calendars. Check debug logs for details.');
    }
}

// AJAX handler for creating tour calendar table
add_action('wp_ajax_opencomune_create_calendar_table', 'opencomune_create_calendar_table_handler');
function opencomune_create_calendar_table_handler() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    $result = opencomune_create_tour_calendar_table();
    
    if ($result) {
        wp_send_json_success('Tour calendar table created successfully');
    } else {
        wp_send_json_error('Failed to create tour calendar table');
    }
}