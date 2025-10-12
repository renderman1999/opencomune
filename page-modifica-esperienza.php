<?php
/* Template Name: Modifica Esperienza */

// Include WordPress media handling functions
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// Carica Select2
wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

// CSS personalizzato per correggere i problemi di layout
wp_add_inline_style('select2', '
    .select2-container {
        width: 100% !important;
    }
    .select2-container--default .select2-selection--multiple {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        min-height: 38px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        background-color: #0d6efd;
        color: white;
        border: none;
        border-radius: 0.25rem;
        padding: 2px 8px;
        margin: 2px;
    }
    .select2-container--default .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }
    .form-control-sm {
        height: calc(1.5em + 0.5rem + 2px);
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        border-radius: 0.2rem;
    }
    .gap-2 {
        gap: 0.5rem !important;
    }
    .overflow-auto {
        overflow: auto !important;
    }
    .position-relative {
        position: relative !important;
    }
    .position-absolute {
        position: absolute !important;
    }
    .top-0 {
        top: 0 !important;
    }
    .end-0 {
        right: 0 !important;
    }
    .img-thumbnail {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
');

// Carica SweetAlert2
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

// Carica Google Maps API
$api_key = opencomune_get_google_maps_api_key();
if (empty($api_key)) {
    error_log('Google Maps API key is not set in Exxlpora settings');
}
wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places', array(), null, true);

// Aggiungi ajaxurl al frontend
wp_localize_script('select2', 'select2_params', array(
    'ajaxurl' => admin_url('admin-ajax.php')
));

get_header();
if (!is_user_logged_in() || !current_user_can('editor_turistico')) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">' . esc_html__('Accesso riservato all\'Ufficio Turistico. Effettua il login con un account autorizzato.', 'opencomune') . '</div>';
    get_footer();
    exit;
}
// Debug per verificare se il form viene inviato
error_log('=== DEBUG FORM SUBMISSION ===');
error_log('REQUEST_METHOD: ' . $_SERVER['REQUEST_METHOD']);
error_log('POST save_tour: ' . (isset($_POST['save_tour']) ? 'PRESENTE' : 'NON PRESENTE'));
error_log('POST count: ' . count($_POST));
error_log('POST data completo: ' . print_r($_POST, true));
if (isset($_POST['save_tour'])) {
    error_log('save_tour value: ' . $_POST['save_tour']);
}

// Il form verrà gestito direttamente dal JavaScript

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tour'])) {
    error_log('=== INIZIO SALVATAGGIO TOUR ===');
    error_log('Metodo: ' . $_SERVER['REQUEST_METHOD']);
    error_log('save_tour presente: ' . (isset($_POST['save_tour']) ? 'SI' : 'NO'));
    error_log('Numero campi POST: ' . count($_POST));
    // Aumenta il timeout e la memoria disponibile
    set_time_limit(300); // 5 minuti
    ini_set('memory_limit', '512M');
    
    error_log('=== Inizio processo di salvataggio ===');
    $current_user_id = get_current_user_id();
    
    // Verifica nonce
    error_log('Nonce ricevuto: ' . (isset($_POST['tour_nonce']) ? $_POST['tour_nonce'] : 'NON PRESENTE'));
    error_log('Debug nonce: ' . (isset($_POST['debug_nonce']) ? $_POST['debug_nonce'] : 'NON PRESENTE'));
    error_log('Nonce valido: ' . (wp_verify_nonce($_POST['tour_nonce'] ?? '', 'save_tour_nonce') ? 'SI' : 'NO'));
    
    if (!isset($_POST['tour_nonce']) || !wp_verify_nonce($_POST['tour_nonce'], 'save_tour_nonce')) {
        error_log('Errore di sicurezza: nonce non valido');
        error_log('Nonce atteso: ' . wp_create_nonce('save_tour_nonce'));
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Chiudi il loader se è aperto
                Swal.close();
                
                // Mostra il messaggio di errore
                Swal.fire({
                    title: "Errore di sicurezza",
                    text: "Si è verificato un errore di sicurezza. Ricarica la pagina e riprova.",
                    icon: "error",
                    confirmButtonText: "OK",
                    confirmButtonColor: "#dc3545"
                });
            });
        </script>';
        exit;
    }
    
    error_log('Preparazione dati post');
    // Prepara i dati del post
    $post_data = array(
        'post_title'    => sanitize_text_field($_POST['titolo']),
        'post_content'  => wp_kses_post($_POST['descrizione_completa']),
        'post_excerpt'  => sanitize_text_field($_POST['descrizione_breve']),
        'post_status'   => 'publish',
        'post_type'     => 'tour',
        'post_author'   => $current_user_id
    );
    
    error_log('Salvataggio post principale');
    // Se stiamo modificando un tour esistente
    if (isset($_POST['post_id']) && !empty($_POST['post_id'])) {
        $post_data['ID'] = intval($_POST['post_id']);
        error_log('Aggiornamento tour esistente con ID: ' . $post_data['ID']);
        $post_id = wp_update_post($post_data);
        error_log('Risultato aggiornamento: ' . ($post_id ? $post_id : 'ERRORE'));
    } else {
        error_log('Creazione nuovo tour');
        $post_id = wp_insert_post($post_data);
        error_log('Risultato creazione: ' . ($post_id ? $post_id : 'ERRORE'));
    }
    
    if (!is_wp_error($post_id)) {
        error_log('Post salvato con ID: ' . $post_id);
        error_log('Titolo salvato: ' . $post_data['post_title']);
        
        // Debug log for address fields
        error_log('Address fields from POST:');
        error_log('indirizzo_ritrovo: ' . (isset($_POST['indirizzo_ritrovo']) ? $_POST['indirizzo_ritrovo'] : 'not set'));
        error_log('indirizzo_ritrovo_place_id: ' . (isset($_POST['indirizzo_ritrovo_place_id']) ? $_POST['indirizzo_ritrovo_place_id'] : 'not set'));
        error_log('coordinate_ritrovo: ' . (isset($_POST['coordinate_ritrovo']) ? $_POST['coordinate_ritrovo'] : 'not set'));
        error_log('gps: ' . (isset($_POST['gps']) ? $_POST['gps'] : 'not set'));
        
        // Debug essenziale per tutti i tour
        error_log('Post ID: ' . (isset($_POST['post_id']) ? $_POST['post_id'] : 'NUOVO TOUR'));
        error_log('Titolo: ' . (isset($_POST['titolo']) ? $_POST['titolo'] : 'NON PRESENTE'));
        
        // Debug coordinate conversion
        if (isset($_POST['coordinate_ritrovo']) && !empty($_POST['coordinate_ritrovo'])) {
            error_log('=== DEBUG COORDINATE CONVERSION ===');
            error_log('coordinate_ritrovo POST: ' . $_POST['coordinate_ritrovo']);
            $coordinate_array = json_decode($_POST['coordinate_ritrovo'], true);
            error_log('coordinate_array: ' . print_r($coordinate_array, true));
            if ($coordinate_array && isset($coordinate_array['lat']) && isset($coordinate_array['lng'])) {
                $gps_value = $coordinate_array['lat'] . ',' . $coordinate_array['lng'];
                error_log('gps value to save: ' . $gps_value);
            }
        }
        
        // Handle categories
        if (isset($_POST['categoria']) && is_array($_POST['categoria'])) {
            $categories = array_map('sanitize_text_field', $_POST['categoria']);
            
            // Filtra categorie vuote e rimuovi duplicati
            $categories = array_filter($categories, function($cat) {
                return !empty(trim($cat));
            });
            $categories = array_unique($categories);
            
            // Salva solo nella tassonomia personalizzata
            wp_set_object_terms($post_id, $categories, 'categorie_tour');
            
            error_log('Categorie salvate nella tassonomia: ' . implode(', ', $categories));
        }
        
        error_log('Inizio salvataggio meta');
        // Prepara tutti i meta in un array
        $meta_updates = array();
        
        // Handle address fields explicitly
        if (isset($_POST['indirizzo_ritrovo'])) {
            $meta_updates['indirizzo_ritrovo'] = sanitize_text_field($_POST['indirizzo_ritrovo']);
            error_log('Saving indirizzo_ritrovo: ' . $meta_updates['indirizzo_ritrovo']);
        }
        if (isset($_POST['indirizzo_ritrovo_place_id'])) {
            $meta_updates['indirizzo_ritrovo_place_id'] = sanitize_text_field($_POST['indirizzo_ritrovo_place_id']);
            error_log('Saving indirizzo_ritrovo_place_id: ' . $meta_updates['indirizzo_ritrovo_place_id']);
        }
        
        if (isset($_POST['coordinate_ritrovo']) && !empty($_POST['coordinate_ritrovo'])) {
            $coordinate_data = sanitize_text_field($_POST['coordinate_ritrovo']);
            // Converti le coordinate JSON in formato lat,lon per il campo gps
            $coordinate_array = json_decode($coordinate_data, true);
            if ($coordinate_array && isset($coordinate_array['lat']) && isset($coordinate_array['lng'])) {
                $meta_updates['gps'] = $coordinate_array['lat'] . ',' . $coordinate_array['lng'];
                error_log('Saving gps coordinates from coordinate_ritrovo: ' . $meta_updates['gps']);
            }
        }
        if (isset($_POST['gps'])) {
            $meta_updates['gps'] = sanitize_text_field($_POST['gps']);
            error_log('Saving gps: ' . $meta_updates['gps']);
        }
        
        // Add other meta fields
        $meta_fields = array(
            'citta' => 'citta',
            'citta_place_id' => 'citta_place_id',
            'durata' => 'durata',
            'lingue' => 'lingue',
            'prezzo' => 'prezzo',
            'include' => 'include',
            'non_include' => 'non_include',
            'itinerario' => 'itinerario',
            'note' => 'note',
            'difficolta' => 'difficolta',
            'accessibilita' => 'accessibilita',
            'min_partecipanti' => 'min_partecipanti',
            'max_partecipanti' => 'max_partecipanti',
            'prezzo_privato' => 'prezzo_privato',
            'cosa_portare' => 'cosa_portare',
            'giorni' => 'giorni',
            'data_inizio' => 'data_inizio',
            'data_fine' => 'data_fine',
            'eccezioni' => 'eccezioni',
            'scadenza_prenotazioni' => 'scadenza_prenotazioni',
            'cancellazione' => 'cancellazione',
            'rimborso' => 'rimborso',
            'evento_specifico' => 'evento_specifico',
            'data_evento' => 'data_evento',
            'ora_evento' => 'ora_evento'
        );
        
        // Prepara i meta in batch
        foreach ($meta_fields as $field => $meta_key) {
            if (isset($_POST[$field])) {
                error_log("Preparazione meta: {$meta_key}");
                if (is_array($_POST[$field])) {
                    $meta_updates[$meta_key] = array_map(function($v) {
                        return sanitize_text_field(stripslashes($v));
                    }, $_POST[$field]);
                } else {
                    $meta_updates[$meta_key] = sanitize_text_field(stripslashes($_POST[$field]));
                }
            }
        }
        
        // Handle orari separately since they come from individual fields
        $orari = array();
        foreach (['lunedi', 'martedi', 'mercoledi', 'giovedi', 'venerdi', 'sabato', 'domenica'] as $giorno) {
            if (isset($_POST['orari_' . $giorno])) {
                $orari[$giorno] = sanitize_text_field($_POST['orari_' . $giorno]);
            }
        }
        if (!empty($orari)) {
            $meta_updates['orari'] = $orari;
        }
        
        // Esegui un'unica query per aggiornare tutti i meta
        if (!empty($meta_updates)) {
            error_log('Esecuzione batch update dei meta');
            global $wpdb;
            
            // Get existing gallery images before deleting meta
            $existing_gallery = get_post_meta($post_id, 'galleria', true);
            $gallery_keep = isset($_POST['gallery_keep']) ? json_decode(stripslashes($_POST['gallery_keep']), true) : array();
            // Get existing thumbnail before deleting meta
            $existing_thumbnail_id = get_post_meta($post_id, '_thumbnail_id', true);
            
            // First, delete existing meta for this post (but preserve thumbnail if not being replaced)
            error_log('Eliminazione meta esistenti per post ID: ' . $post_id);
            if (empty($_FILES['foto_principale']['name'])) {
                // If no new photo is being uploaded, preserve the existing thumbnail
                $deleted_count = $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key != %s",
                    $post_id,
                    '_thumbnail_id'
                ));
                error_log('Meta eliminati (thumbnail preservato): ' . $deleted_count);
            } else {
                // If new photo is being uploaded, delete all meta including thumbnail
                $deleted_count = $wpdb->delete($wpdb->postmeta, array('post_id' => $post_id));
                error_log('Meta eliminati (incluso thumbnail): ' . $deleted_count);
            }
            
            // Then insert all new meta values
            error_log('Inserimento nuovi meta. Numero meta da inserire: ' . count($meta_updates));
            $values = array();
            $placeholders = array();
            
            foreach ($meta_updates as $meta_key => $meta_value) {
                if (is_array($meta_value)) {
                    $meta_value = maybe_serialize($meta_value);
                    error_log("Meta array serializzato: $meta_key = " . $meta_value);
                }
                $values[] = $post_id;
                $values[] = $meta_key;
                $values[] = $meta_value;
                $placeholders[] = "(%d, %s, %s)";
            }
            
            // Esegui la query in batch
            $query = $wpdb->prepare(
                "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . 
                implode(', ', $placeholders),
                $values
            );
            
            error_log('Esecuzione query batch meta: ' . substr($query, 0, 200) . '...');
            $result = $wpdb->query($query);
            error_log('Risultato query batch meta: ' . ($result !== false ? $result . ' righe inserite' : 'ERRORE: ' . $wpdb->last_error));
            error_log('Batch update meta completato');
            
            // Debug: verifica che i meta siano stati salvati
            if (isset($_POST['post_id']) && $_POST['post_id'] == 205) {
                error_log('=== VERIFICA META SALVATI TOUR 205 ===');
                error_log('indirizzo_ritrovo salvato: ' . get_post_meta($post_id, 'indirizzo_ritrovo', true));
                error_log('coordinate_ritrovo salvato: ' . get_post_meta($post_id, 'coordinate_ritrovo', true));
                error_log('gps salvato: ' . get_post_meta($post_id, 'gps', true));
                error_log('citta salvato: ' . get_post_meta($post_id, 'citta', true));
                error_log('prezzo salvato: ' . get_post_meta($post_id, 'prezzo', true));
            }
            // Thumbnail is already preserved if no new photo is uploaded
        }
        
        error_log('Inizio gestione immagini');
        
        // Assicurati che la directory uploads esista
        $upload_dir = wp_upload_dir();
        $year_month_dir = $upload_dir['basedir'] . '/' . date('Y/m');
        if (!file_exists($year_month_dir)) {
            wp_mkdir_p($year_month_dir);
            error_log('Creata directory: ' . $year_month_dir);
        }
        
        // Gestione delle immagini
        if (!empty($_FILES['foto_principale']['name'])) {
            error_log('Elaborazione foto principale');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            
            // Aumenta il timeout per l'upload delle immagini
            set_time_limit(120);
            
            // Verifica il tipo di file
            $file_type = wp_check_filetype($_FILES['foto_principale']['name']);
            if (!$file_type['type']) {
                error_log('Tipo file non valido per foto principale: ' . $_FILES['foto_principale']['name']);
            } else {
                // Imposta il nome del file
                $_FILES['foto_principale']['name'] = sanitize_file_name($_FILES['foto_principale']['name']);
                
                // Gestisci l'upload
                error_log('Upload foto principale in corso');
                $attachment_id = media_handle_upload('foto_principale', $post_id);
                if (!is_wp_error($attachment_id)) {
                    set_post_thumbnail($post_id, $attachment_id);
                    error_log('Foto principale salvata con ID: ' . $attachment_id);
                } else {
                    error_log('Errore upload foto principale: ' . $attachment_id->get_error_message());
                    // Non bloccare il salvataggio del tour per un errore di immagine
                    error_log('Continua il salvataggio del tour senza la foto principale');
                }
            }
        }
        
        // Gestione della galleria
        $gallery_ids = array();
        
        // Add kept images to gallery
        if (!empty($gallery_keep)) {
            error_log('Adding kept images to gallery: ' . implode(', ', $gallery_keep));
            $gallery_ids = array_merge($gallery_ids, $gallery_keep);
        }
        
        // Handle new gallery uploads
        if (!empty($_FILES['galleria']['name'][0])) {
            error_log('Inizio elaborazione nuove immagini galleria');
            $max_size = 2 * 1024 * 1024; // 2MB
            
            foreach ($_FILES['galleria']['name'] as $key => $value) {
                error_log("Elaborazione immagine galleria {$key}");
                if ($_FILES['galleria']['error'][$key] === 0) {
                    // Verifica dimensione
                    if ($_FILES['galleria']['size'][$key] > $max_size) {
                        error_log('Immagine troppo grande: ' . $_FILES['galleria']['name'][$key]);
                        continue;
                    }
                    
                    // Verifica tipo di file
                    $file_type = wp_check_filetype($_FILES['galleria']['name'][$key]);
                    if (!$file_type['type']) {
                        error_log('Tipo file non valido per immagine galleria: ' . $_FILES['galleria']['name'][$key]);
                        continue;
                    }
                    
                    // Prepara il file
                    $file = array(
                        'name'     => sanitize_file_name($_FILES['galleria']['name'][$key]),
                        'type'     => $_FILES['galleria']['type'][$key],
                        'tmp_name' => $_FILES['galleria']['tmp_name'][$key],
                        'error'    => $_FILES['galleria']['error'][$key],
                        'size'     => $_FILES['galleria']['size'][$key]
                    );
                    
                    // Gestisci l'upload
                    error_log('Upload immagine galleria in corso');
                    $attachment_id = media_handle_sideload($file, $post_id);
                    if (!is_wp_error($attachment_id)) {
                        $gallery_ids[] = $attachment_id;
                        error_log('Immagine galleria salvata con ID: ' . $attachment_id);
                    } else {
                        error_log('Errore upload immagine galleria: ' . $attachment_id->get_error_message());
                    }
                }
            }
        }
        
        // Save final gallery
        if (!empty($gallery_ids)) {
            error_log('Saving final gallery with ' . count($gallery_ids) . ' images');
            update_post_meta($post_id, 'galleria', $gallery_ids);
        }
        
        error_log('=== Fine processo di salvataggio ===');
        $redirect_url = get_permalink($post_id);
        error_log('Redirect URL: ' . $redirect_url);
        error_log('Post ID per redirect: ' . $post_id);
        
        // Set success flag for SweetAlert confirmation
        $success = true;
        $tour_title = $post_data['post_title'];
        $tour_id = $post_id;
        error_log('Success flag impostato: ' . ($success ? 'true' : 'false'));
        error_log('Tour salvato: ' . $tour_title . ' (ID: ' . $tour_id . ')');
    } else {
        error_log('Errore nel salvataggio del post: ' . $post_id->get_error_message());
        $success = false;
        $error_message = $post_id->get_error_message();
        echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                // Chiudi il loader se è aperto
                Swal.close();
                
                // Mostra il messaggio di errore
                Swal.fire({
                    title: "Errore nel salvataggio",
                    text: "' . esc_js($error_message) . '",
                    icon: "error",
                    confirmButtonText: "OK",
                    confirmButtonColor: "#dc3545"
                });
            });
        </script>';
    }
}
// --- VERIFICA TOUR DA MODIFICARE ---
$current_user_id = get_current_user_id();
require_once get_template_directory() . '/functions.php';

// Add this at the very beginning of the file, right after the template name
if (isset($success) && $success) {
    error_log('Tour salvato con successo, mostrando SweetAlert');
    $success_message = 'Il tour "' . esc_js($tour_title) . '" è stato aggiornato con successo.';
    $dashboard_url = esc_url(site_url('/dashboard-ufficio/'));
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            // Chiudi il loader se è aperto
            Swal.close();
            
            // Mostra il messaggio di successo
            Swal.fire({
                title: "Tour aggiornato!",
                text: "' . $success_message . '",
                icon: "success",
                showCancelButton: true,
                confirmButtonText: "Torna alla Dashboard",
                cancelButtonText: "Rimani qui",
                confirmButtonColor: "#28a745",
                cancelButtonColor: "#6c757d"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "' . $dashboard_url . '";
                }
            });
        });
    </script>';
}

// Verifica che sia specificato un tour da modificare
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$edit_id) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">Nessuna esperienza specificata per la modifica. <a href="' . esc_url(site_url('/dashboard-ufficio/')) . '" class="underline text-blue-700">Torna alla dashboard</a>.</div>';
    get_footer();
    exit;
}
global $wpdb;
$comuni = $wpdb->get_results("SELECT Descrizione FROM wpmyguide_comuni WHERE DataFineVal IS NULL OR DataFineVal = '' ORDER BY Descrizione ASC");
$tour_edit_data = null;
if ($edit_id && is_user_logged_in() && current_user_can('editor_turistico')) {
    $post = get_post($edit_id);
    if ($post && $post->post_type === 'esperienze' && (int)$post->post_author === get_current_user_id()) {
        // Recupera le categorie dalla tassonomia personalizzata
        $categorie_tags = wp_get_post_terms($edit_id, 'categorie_esperienze', ['fields' => 'names']);
        
        // Debug: log delle categorie recuperate
        error_log('Categorie recuperate per tour ' . $edit_id . ': ' . print_r($categorie_tags, true));
        
        // Debug per tour 205
        if ($edit_id == 205) {
            error_log('=== DEBUG TOUR 205 ===');
            error_log('coordinate_ritrovo raw: ' . get_post_meta($edit_id, 'coordinate_ritrovo', true));
            error_log('indirizzo_ritrovo: ' . get_post_meta($edit_id, 'indirizzo_ritrovo', true));
            error_log('gps: ' . get_post_meta($edit_id, 'gps', true));
        }
        
        $tour_edit_data = [
            'ID' => $edit_id,
            'titolo' => $post->post_title,
            'desc_breve' => $post->post_excerpt,
            'desc_completa' => $post->post_content,
            'categoria' => $categorie_tags,
            'citta' => get_post_meta($edit_id, 'citta', true),
            'citta_place_id' => get_post_meta($edit_id, 'citta_place_id', true),
            'durata' => get_post_meta($edit_id, 'durata', true),
            'lingue' => get_post_meta($edit_id, 'lingue', true),
            'prezzo' => get_post_meta($edit_id, 'prezzo', true),
            'include' => get_post_meta($edit_id, 'include', true),
            'non_include' => get_post_meta($edit_id, 'non_include', true),
            'itinerario' => get_post_meta($edit_id, 'itinerario', true),
            'note' => get_post_meta($edit_id, 'note', true),
            'indirizzo_ritrovo' => get_post_meta($edit_id, 'indirizzo_ritrovo', true),
            'indirizzo_ritrovo_place_id' => get_post_meta($edit_id, 'indirizzo_ritrovo_place_id', true),
            'coordinate_ritrovo' => '', // Non più usato, convertito in gps
            'gps' => get_post_meta($edit_id, 'gps', true),
            'indicazioni_ritrovo' => get_post_meta($edit_id, 'indicazioni_ritrovo', true),
            'difficolta' => get_post_meta($edit_id, 'difficolta', true),
            'accessibilita' => get_post_meta($edit_id, 'accessibilita', true),
            'min_partecipanti' => get_post_meta($edit_id, 'min_partecipanti', true),
            'max_partecipanti' => get_post_meta($edit_id, 'max_partecipanti', true),
            'prezzo_privato' => get_post_meta($edit_id, 'prezzo_privato', true),
            'cosa_portare' => get_post_meta($edit_id, 'cosa_portare', true),
            'giorni' => get_post_meta($edit_id, 'giorni', true),
            'orari' => get_post_meta($edit_id, 'orari', true),
            'data_inizio' => get_post_meta($edit_id, 'data_inizio', true),
            'data_fine' => get_post_meta($edit_id, 'data_fine', true),
            'eccezioni' => get_post_meta($edit_id, 'eccezioni', true),
            'scadenza_prenotazioni' => get_post_meta($edit_id, 'scadenza_prenotazioni', true),
            'cancellazione' => get_post_meta($edit_id, 'cancellazione', true),
            'rimborso' => get_post_meta($edit_id, 'rimborso', true),
            'calendario_sync' => get_post_meta($edit_id, 'calendario_sync', true),
            'blocco_slot' => get_post_meta($edit_id, 'blocco_slot', true),
            'lista_attesa' => get_post_meta($edit_id, 'lista_attesa', true),
            'evento_specifico' => get_post_meta($edit_id, 'evento_specifico', true),
            'data_evento' => get_post_meta($edit_id, 'data_evento', true),
            'ora_evento' => get_post_meta($edit_id, 'ora_evento', true),
            'galleria' => get_post_meta($edit_id, 'galleria', true),
            'video_presentazione' => get_post_meta($edit_id, 'video_presentazione', true),
            'foto_ritrovo' => get_post_meta($edit_id, 'foto_ritrovo', true),
        ];
    }
    global $wpdb;
 }
?>
<script>
let galleryKeepIds = [];
window.tourEditData = <?php echo json_encode($tour_edit_data); ?>;

// Debug per tour 205
<?php if ($edit_id == 205): ?>
console.log('=== DEBUG TOUR 205 FRONTEND ===');
console.log('tourEditData:', window.tourEditData);
console.log('coordinate_ritrovo:', window.tourEditData.coordinate_ritrovo);
console.log('indirizzo_ritrovo:', window.tourEditData.indirizzo_ritrovo);
console.log('gps:', window.tourEditData.gps);
<?php endif; ?>

// Popola le coordinate dal campo gps se disponibili
if (window.tourEditData && window.tourEditData.gps) {
    var gpsParts = window.tourEditData.gps.split(',');
    if (gpsParts.length === 2) {
        var lat = parseFloat(gpsParts[0].trim());
        var lng = parseFloat(gpsParts[1].trim());
        if (!isNaN(lat) && !isNaN(lng)) {
            var coordinateElement = document.getElementById('coordinate_ritrovo');
            if (coordinateElement) {
                coordinateElement.value = JSON.stringify({
                    lat: lat,
                    lng: lng
                });
            }
        }
    }
}
</script>
 

<div class="min-vh-100 bg-light py-4">
    <div class="container">
      

        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <strong><?php _e('Modifica tour:', 'opencomune'); ?></strong> <?php echo esc_html($tour_edit_data['titolo']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>

        <form method="post" enctype="multipart/form-data" id="tour-form" action="<?php echo esc_url($_SERVER['REQUEST_URI']); ?>">
            <?php 
            $nonce = wp_create_nonce('save_tour_nonce');
            error_log('Nonce generato: ' . $nonce);
            wp_nonce_field('save_tour_nonce', 'tour_nonce'); 
            ?>
            <input type="hidden" name="debug_nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="post_id" value="<?php echo esc_attr($edit_id); ?>">

            <!-- Dati obbligatori -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php _e('Dati obbligatori', 'opencomune'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="titolo" class="form-label fw-bold"><?php _e('Titolo evento', 'opencomune'); ?> *</label>
                            <input type="text" id="titolo" name="titolo" value="<?php echo esc_attr($tour_edit_data['titolo'] ?? ''); ?>" required class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="categoria" class="form-label fw-bold"><?php _e('Categoria principale', 'opencomune'); ?> *</label>
                            <select id="categoria" name="categoria[]" multiple required class="form-control">
                                <?php 
                                // Recupera tutte le categorie tour disponibili
                                $all_categorie = get_terms([
                                    'taxonomy' => 'categorie_tour',
                                    'hide_empty' => false,
                                ]);
                                
                                foreach ($all_categorie as $categoria): 
                                    $selected = isset($tour_edit_data['categoria']) && in_array($categoria->name, $tour_edit_data['categoria']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo esc_attr($categoria->name); ?>" <?php echo $selected; ?>><?php echo esc_html($categoria->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="citta" class="form-label fw-bold"><?php _e('Città/Località *', 'opencomune'); ?></label>
                            <input type="text" 
                                   id="citta" 
                                   name="citta" 
                                   class="form-control" 
                                   required
                                   value="<?php echo esc_attr($tour_edit_data['citta'] ?? ''); ?>"
                                   placeholder="<?php _e('Inizia a digitare il nome della città...', 'opencomune'); ?>">
                            <input type="hidden" id="citta_place_id" name="citta_place_id" value="<?php echo esc_attr($tour_edit_data['citta_place_id'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="durata" class="form-label fw-bold"><?php _e('Durata stimata', 'opencomune'); ?> *</label>
                            <input type="text" id="durata" name="durata" value="<?php echo esc_attr($tour_edit_data['durata'] ?? ''); ?>" required class="form-control" placeholder="1h 30m, 2h, mezza giornata...">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="lingue" class="form-label fw-bold"><?php _e('Lingue disponibili', 'opencomune'); ?> *</label>
                            <select id="lingue" name="lingue[]" multiple required class="form-control">
                                <?php
                                $lingue_predefinite = ['Italiano','English','Français','Deutsch','Español','Português','Русский','中文','日本語','العربية'];
                                foreach ($lingue_predefinite as $lingua) {
                                    $selected = '';
                                    if (isset($tour_edit_data['lingue'])) {
                                        $lingue_array = is_array($tour_edit_data['lingue']) ? $tour_edit_data['lingue'] : explode(',', $tour_edit_data['lingue']);
                                        if (in_array($lingua, $lingue_array)) {
                                            $selected = 'selected';
                                        }
                                    }
                                    echo '<option value="' . esc_attr($lingua) . '" ' . $selected . '>' . esc_html($lingua) . '</option>';
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="prezzo" class="form-label fw-bold"><?php _e('Prezzo per persona (€)', 'opencomune'); ?> *</label>
                            <input type="number" id="prezzo" name="prezzo" value="<?php echo esc_attr($tour_edit_data['prezzo'] ?? ''); ?>" min="10" max="500" step="1" required class="form-control">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contenuti descrittivi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php _e('Contenuti descrittivi', 'opencomune'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="descrizione_breve" class="form-label fw-bold"><?php _e('Descrizione breve', 'opencomune'); ?> *</label>
                        <input type="text" id="descrizione_breve" name="descrizione_breve" value="<?php echo esc_attr($tour_edit_data['desc_breve'] ?? ''); ?>" maxlength="160" required class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="descrizione_completa" class="form-label fw-bold"><?php _e('Descrizione completa', 'opencomune'); ?> *</label>
                        <textarea id="descrizione_completa" name="descrizione_completa" rows="6" required class="form-control"><?php echo esc_textarea($tour_edit_data['desc_completa'] ?? ''); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="include" class="form-label fw-bold"><?php _e('Cosa include il tour', 'opencomune'); ?></label>
                            <textarea id="include" name="include" rows="3" class="form-control"><?php echo esc_textarea($tour_edit_data['include'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="non_include" class="form-label fw-bold"><?php _e('Cosa non è incluso', 'opencomune'); ?></label>
                            <textarea id="non_include" name="non_include" rows="3" class="form-control"><?php echo esc_textarea($tour_edit_data['non_include'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="itinerario" class="form-label fw-bold"><?php _e('Itinerario dettagliato', 'opencomune'); ?></label>
                        <textarea id="itinerario" name="itinerario" rows="4" class="form-control"><?php echo esc_textarea($tour_edit_data['itinerario'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="note" class="form-label fw-bold"><?php _e('Note importanti', 'opencomune'); ?></label>
                        <textarea id="note" name="note" rows="3" class="form-control"><?php echo esc_textarea($tour_edit_data['note'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Informazioni pratiche -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php _e('Informazioni pratiche', 'opencomune'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="indirizzo_ritrovo" class="form-label fw-bold"><?php _e('Indirizzo completo punto di ritrovo *', 'opencomune'); ?></label>
                        <input type="text" 
                               id="indirizzo_ritrovo" 
                               name="indirizzo_ritrovo" 
                               class="form-control" 
                               required
                               value="<?php echo esc_attr($tour_edit_data['indirizzo_ritrovo'] ?? ''); ?>"
                               placeholder="<?php _e('Inizia a digitare l\'indirizzo...', 'opencomune'); ?>">
                        <input type="hidden" id="indirizzo_ritrovo_place_id" name="indirizzo_ritrovo_place_id" value="<?php echo esc_attr($tour_edit_data['indirizzo_ritrovo_place_id'] ?? ''); ?>">
                        <input type="hidden" id="coordinate_ritrovo" name="coordinate_ritrovo" value="<?php echo esc_attr($tour_edit_data['coordinate_ritrovo'] ?? ''); ?>">
                        <input type="hidden" id="gps" name="gps" value="<?php echo esc_attr($tour_edit_data['gps'] ?? ''); ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><?php _e('Difficoltà fisica', 'opencomune'); ?> *</label>
                            <div class="form-check">
                                <input type="radio" name="difficolta" value="facile" id="difficolta_facile" class="form-check-input" <?php checked($tour_edit_data['difficolta'] ?? '', 'facile'); ?> required>
                                <label for="difficolta_facile" class="form-check-label"><?php _e('Facile', 'opencomune'); ?></label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="difficolta" value="moderata" id="difficolta_moderata" class="form-check-input" <?php checked($tour_edit_data['difficolta'] ?? '', 'moderata'); ?>>
                                <label for="difficolta_moderata" class="form-check-label"><?php _e('Moderata', 'opencomune'); ?></label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="difficolta" value="impegnativa" id="difficolta_impegnativa" class="form-check-input" <?php checked($tour_edit_data['difficolta'] ?? '', 'impegnativa'); ?>>
                                <label for="difficolta_impegnativa" class="form-check-label"><?php _e('Impegnativa', 'opencomune'); ?></label>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold"><?php _e('Accessibilità', 'opencomune'); ?> *</label>
                            <div class="form-check">
                                <input type="radio" name="accessibilita" value="carrozzine" id="accessibilita_carrozzine" class="form-check-input" <?php checked($tour_edit_data['accessibilita'] ?? '', 'carrozzine'); ?> required>
                                <label for="accessibilita_carrozzine" class="form-check-label"><?php _e('Accessibile carrozzine', 'opencomune'); ?></label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="accessibilita" value="ridotta" id="accessibilita_ridotta" class="form-check-input" <?php checked($tour_edit_data['accessibilita'] ?? '', 'ridotta'); ?>>
                                <label for="accessibilita_ridotta" class="form-check-label"><?php _e('Accessibile mobilità ridotta', 'opencomune'); ?></label>
                            </div>
                            <div class="form-check">
                                <input type="radio" name="accessibilita" value="no" id="accessibilita_no" class="form-check-input" <?php checked($tour_edit_data['accessibilita'] ?? '', 'no'); ?>>
                                <label for="accessibilita_no" class="form-check-label"><?php _e('Non accessibile', 'opencomune'); ?></label>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="min_partecipanti" class="form-label fw-bold"><?php _e('Numero minimo partecipanti', 'opencomune'); ?> *</label>
                            <input type="number" id="min_partecipanti" name="min_partecipanti" value="<?php echo esc_attr($tour_edit_data['min_partecipanti'] ?? '2'); ?>" min="1" max="100" required class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="max_partecipanti" class="form-label fw-bold"><?php _e('Numero massimo partecipanti', 'opencomune'); ?> *</label>
                            <input type="number" id="max_partecipanti" name="max_partecipanti" value="<?php echo esc_attr($tour_edit_data['max_partecipanti'] ?? '15'); ?>" min="1" max="100" required class="form-control">
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="prezzo_privato" class="form-label fw-bold"><?php _e('Prezzo gruppo privato (€)', 'opencomune'); ?></label>
                            <input type="number" id="prezzo_privato" name="prezzo_privato" value="<?php echo esc_attr($tour_edit_data['prezzo_privato'] ?? ''); ?>" min="10" max="5000" step="1" class="form-control">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="cosa_portare" class="form-label fw-bold"><?php _e('Cosa portare/indossare', 'opencomune'); ?></label>
                        <textarea id="cosa_portare" name="cosa_portare" rows="3" class="form-control"><?php echo esc_textarea($tour_edit_data['cosa_portare'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>



            <!-- Media -->
            <div class="card mb-4">
                <div class="card-header">
                    <h2 class="h4 mb-0"><?php _e('Media', 'opencomune'); ?></h2>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="foto_principale" class="form-label fw-bold"><?php _e('Foto principale', 'opencomune'); ?> *</label>
                        <?php if ($edit_id && has_post_thumbnail($edit_id)): ?>
                            <div class="mb-2">
                                <?php echo get_the_post_thumbnail($edit_id, 'medium'); ?>
                            </div>
                        <?php endif; ?>
                        <input type="file" id="foto_principale" name="foto_principale" accept="image/*" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label for="galleria" class="form-label fw-bold"><?php _e('Galleria immagini', 'opencomune'); ?> (max 8)</label>
                        <?php if ($edit_id): 
                            $gallery = get_post_meta($edit_id, 'galleria', true);
                            if (!empty($gallery)): ?>
                                <div class="d-flex gap-2 mb-3 overflow-auto">
                                    <?php foreach ($gallery as $image_id): ?>
                                        <div class="position-relative">
                                            <?php echo wp_get_attachment_image($image_id, 'thumbnail', false, array('class' => 'img-thumbnail', 'style' => 'width: 100px; height: 100px; object-fit: cover;')); ?>
                                            <button type="button" 
                                                    class="btn btn-danger btn-sm position-absolute top-0 end-0"
                                                    style="width: 24px; height: 24px; border-radius: 50%; padding: 0; font-size: 12px;"
                                                    onclick="removeGalleryImage(<?php echo $image_id; ?>)">
                                                ×
                                            </button>
                                            <input type="hidden" name="gallery_keep[]" value="<?php echo $image_id; ?>">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif;
                        endif; ?>
                        <input type="file" id="galleria" name="galleria[]" accept="image/*" multiple class="form-control">
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" name="save_tour" class="btn btn-primary btn-lg">
                    <?php _e('Aggiorna tour', 'opencomune'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Funzione per loggare nel debug.log
function debugLog(message) {
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'debug_log',
            message: message,
            nonce: '<?php echo wp_create_nonce("debug_log_nonce"); ?>'
        },
        success: function(response) {
            // Debug log inviato con successo
        },
        error: function() {
            // Errore nell'invio del debug log
        }
    });
}

debugLog('Script tour form caricato');
jQuery(document).ready(function($) {
    debugLog('jQuery ready, inizializzazione form tour');
    try {
    // Il loader verrà mostrato solo dopo la conferma, non automaticamente
    // Verifica che il form esista
    if ($('#tour-form').length === 0) {
        debugLog('ERRORE: Form tour-form non trovato!');
        return;
    }
    debugLog('Form tour-form trovato, aggiungendo event listener');
    
    // Gestione submit del form con SweetAlert
    $('#tour-form').on('submit', function(e) {
        debugLog('Form submit intercettato');
        console.log('Form submit event triggered');
        e.preventDefault();
        e.stopPropagation();
        
        debugLog('Inizio validazione campi obbligatori');
        
        // Verifica che tutti i campi obbligatori siano compilati
        var requiredFields = $(this).find('[required]');
        debugLog('Campi obbligatori trovati: ' + requiredFields.length);
        var isValid = true;
        var missingFields = [];
        
        requiredFields.each(function() {
            var $field = $(this);
            var fieldValue = $field.val();
            var isEmpty = false;
            
            // Controllo specifico per select multipli
            if ($field.is('select[multiple]')) {
                isEmpty = !fieldValue || fieldValue.length === 0;
            } else {
                isEmpty = !fieldValue || fieldValue.trim() === '';
            }
            
            if (isEmpty) {
                isValid = false;
                var fieldName = $field.attr('name');
                var fieldLabel = $field.closest('.mb-3').find('label').text() || 
                                $field.closest('.col-md-6').find('label').text() || 
                                fieldName;
                var cleanLabel = fieldLabel.replace('*', '').trim();
                missingFields.push(cleanLabel);
                debugLog('Campo mancante: ' + fieldName + ' Label: ' + cleanLabel);
            } else {
                debugLog('Campo valido: ' + $field.attr('name'));
            }
        });
        
        debugLog('Validazione completata. Valido: ' + isValid + ' Campi mancanti: ' + missingFields.length);
        
        if (!isValid) {
            debugLog('Mostrando alert per campi mancanti');
            var missingFieldsText = missingFields.slice(0, 3).join(', ');
            if (missingFields.length > 3) {
                missingFieldsText += ' e altri ' + (missingFields.length - 3) + ' campi';
            }
            
            // Evidenzia i campi mancanti
            requiredFields.each(function() {
                var $field = $(this);
                var fieldValue = $field.val();
                var isEmpty = false;
                
                if ($field.is('select[multiple]')) {
                    isEmpty = !fieldValue || fieldValue.length === 0;
                } else {
                    isEmpty = !fieldValue || fieldValue.trim() === '';
                }
                
                if (isEmpty) {
                    $field.addClass('border-danger');
                    $field.closest('.mb-3, .col-md-6').find('label').addClass('text-danger');
                }
            });
            
            Swal.fire({
                title: 'Campi obbligatori mancanti',
                html: '<div class="text-left">' +
                      '<p class="mb-3">I seguenti campi sono obbligatori:</p>' +
                      '<ul class="text-left mb-3">' +
                      missingFields.map(function(field) {
                          return '<li><strong>' + field + '</strong></li>';
                      }).join('') +
                      '</ul>' +
                      '<p class="text-sm text-muted">Compila tutti i campi obbligatori prima di salvare.</p>' +
                      '</div>',
                icon: 'warning',
                confirmButtonText: 'OK, ho capito',
                confirmButtonColor: '#ffc107',
                width: '500px'
            }).then(() => {
                // Scroll al primo campo mancante
                var firstMissingField = requiredFields.filter(function() {
                    var $field = $(this);
                    var fieldValue = $field.val();
                    if ($field.is('select[multiple]')) {
                        return !fieldValue || fieldValue.length === 0;
                    } else {
                        return !fieldValue || fieldValue.trim() === '';
                    }
                }).first();
                
                if (firstMissingField.length) {
                    $('html, body').animate({
                        scrollTop: firstMissingField.offset().top - 100
                    }, 500);
                    firstMissingField.focus();
                }
            });
            return false;
        }
        
        debugLog('Tutti i campi sono validi, mostrando conferma salvataggio');
        
        // Mostra conferma prima del salvataggio
        Swal.fire({
            title: 'Conferma salvataggio',
            text: 'Sei sicuro di voler salvare questo tour?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sì, salva',
            cancelButtonText: 'Annulla',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostra loader prima di inviare il form
                Swal.fire({
                    title: 'Salvataggio in corso...',
                    text: 'Attendi mentre salvo il tour...',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    timer: 30000, // 30 secondi di timeout
                    timerProgressBar: true,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Aggiungi il campo save_tour al form e invialo
                var saveTourInput = $('<input>').attr({
                    type: 'hidden',
                    name: 'save_tour',
                    value: '1'
                });
                $('#tour-form').append(saveTourInput);
                debugLog('Campo save_tour aggiunto al form');
                console.log('Form data dopo aggiunta save_tour:', $('#tour-form').serialize());
                
                // Invia il form dopo un breve delay per permettere al loader di apparire
                setTimeout(() => {
                    debugLog('Invio form tour');
                    console.log('Form data prima dell\'invio:', $('#tour-form').serialize());
                    console.log('Form action:', $('#tour-form').attr('action'));
                    console.log('Form method:', $('#tour-form').attr('method'));
                    $('#tour-form')[0].submit();
                }, 100);
            }
        });
    });
    // Debug per verificare che select2_params sia definito
    console.log('select2_params:', select2_params);



    // Inizializzazione Select2 per le categorie
    $('#categoria').select2({
        placeholder: '<?php _e('Seleziona le categorie', 'opencomune'); ?>',
        allowClear: true,
        width: '100%',
        tags: true,
        tokenSeparators: [',', ' '],
        ajax: {
            url: select2_params.ajaxurl,
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    action: 'search_categorie',
                    nonce: '<?php echo wp_create_nonce('search_categorie_nonce'); ?>'
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        createTag: function(params) {
            var term = $.trim(params.term);
            if (term === '') {
                return null;
            }
            
            // Controlla se il termine esiste già nelle opzioni
            var existingOptions = $('#categoria option').map(function() {
                return $(this).val().toLowerCase();
            }).get();
            
            if (existingOptions.indexOf(term.toLowerCase()) !== -1) {
                return null;
            }
            
            // Crea la nuova categoria sul server
            $.ajax({
                url: select2_params.ajaxurl,
                type: 'POST',
                data: {
                    action: 'create_categoria',
                    categoria: term,
                    nonce: '<?php echo wp_create_nonce('create_categoria_nonce'); ?>'
                },
                async: false,
                success: function(response) {
                    if (response.success) {
                        console.log('Categoria creata:', response.data.message);
                    } else {
                        console.error('Errore creazione categoria:', response.data);
                    }
                },
                error: function() {
                    console.error('Errore nella richiesta AJAX per creare categoria');
                }
            });
            
            return {
                id: term,
                text: term,
                newTag: true
            };
        }
    });

    // Pre-select existing categories
    <?php if (isset($tour_edit_data['categoria']) && is_array($tour_edit_data['categoria'])): ?>
    var existingCategories = <?php echo json_encode(array_map(function($name) {
        return ['id' => $name, 'text' => $name];
    }, $tour_edit_data['categoria'])); ?>;
    
    console.log('Categorie esistenti:', existingCategories);
    
    // Aggiungi le categorie esistenti come opzioni
    existingCategories.forEach(function(cat) {
        var option = new Option(cat.text, cat.id, true, true);
        $('#categoria').append(option);
    });
    
    // Trigger change per aggiornare Select2
    $('#categoria').trigger('change');
    <?php endif; ?>

    // Inizializzazione Select2 per le lingue
    $('#lingue').select2({
        placeholder: '<?php _e('Seleziona le lingue', 'opencomune'); ?>',
        allowClear: true,
        width: '100%',
        tags: true,
        tokenSeparators: [',', ' '],
        minimumInputLength: 1
    });

    // Pre-select existing languages
    <?php if (isset($tour_edit_data['lingue']) && !empty($tour_edit_data['lingue'])): ?>
    var existingLanguages = <?php echo json_encode(is_array($tour_edit_data['lingue']) ? $tour_edit_data['lingue'] : explode(',', $tour_edit_data['lingue'])); ?>;
    
    console.log('Lingue esistenti:', existingLanguages);
    
    // Aggiungi le lingue esistenti come opzioni
    existingLanguages.forEach(function(lang) {
        var option = new Option(lang, lang, true, true);
        $('#lingue').append(option);
    });
    
    // Trigger change per aggiornare Select2
    $('#lingue').trigger('change');
    <?php endif; ?>

    // Initialize Google Maps Places Autocomplete
    function initializeGoogleMaps() {
        console.log('Initializing Google Maps...');
        
        // Initialize city autocomplete
        const cittaInput = document.getElementById('citta');
        if (!cittaInput) {
            console.error('City input element not found');
            return;
        }
        
        try {
            const cittaAutocomplete = new google.maps.places.Autocomplete(cittaInput, {
                types: ['(cities)'],
                fields: ['formatted_address', 'place_id', 'geometry'],
                componentRestrictions: { country: 'it' }
            });

            cittaAutocomplete.addListener('place_changed', function() {
                const place = cittaAutocomplete.getPlace();
                console.log('City place selected:', place);
                if (place.place_id) {
                    var cittaPlaceIdElement = document.getElementById('citta_place_id');
                    if (cittaPlaceIdElement) {
                        cittaPlaceIdElement.value = place.place_id;
                    }
                    cittaInput.value = place.formatted_address;
                }
            });
        } catch (error) {
            console.error('Error initializing city autocomplete:', error);
        }

        // Initialize meeting point address autocomplete
        const indirizzoInput = document.getElementById('indirizzo_ritrovo');
        if (!indirizzoInput) {
            console.error('Address input element not found');
            return;
        }

        try {
            const indirizzoAutocomplete = new google.maps.places.Autocomplete(indirizzoInput, {
                types: ['address'],
                fields: ['formatted_address', 'place_id', 'geometry'],
                componentRestrictions: { country: 'it' }
            });

            indirizzoAutocomplete.addListener('place_changed', function() {
                const place = indirizzoAutocomplete.getPlace();
                console.log('Address place selected:', place);
                if (place.place_id) {
                    var indirizzoPlaceIdElement = document.getElementById('indirizzo_ritrovo_place_id');
                    if (indirizzoPlaceIdElement) {
                        indirizzoPlaceIdElement.value = place.place_id;
                    }
                    document.getElementById('indirizzo_ritrovo').value = place.formatted_address;
                
                    // Salva le coordinate
                    if (place.geometry && place.geometry.location) {
                        var lat = place.geometry.location.lat();
                        var lng = place.geometry.location.lng();
                        var coordinateElement = document.getElementById('coordinate_ritrovo');
                        if (coordinateElement) {
                            coordinateElement.value = JSON.stringify({
                                lat: lat,
                                lng: lng
                            });
                        }
                    }
                    
                    // Update GPS coordinates
                    if (place.geometry && place.geometry.location) {
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        var gpsElement = document.getElementById('gps');
                        if (gpsElement) {
                            gpsElement.value = `${lat},${lng}`;
                        }
                    }
                }
            });
        } catch (error) {
            console.error('Error initializing address autocomplete:', error);
        }
    }

    // Check if Google Maps is loaded
    if (typeof google !== 'undefined' && google.maps && google.maps.places) {
        console.log('Google Maps API is loaded');
        initializeGoogleMaps();
    } else {
        console.error('Google Maps API not loaded');
        // Try to initialize after a short delay
        setTimeout(function() {
            if (typeof google !== 'undefined' && google.maps && google.maps.places) {
                console.log('Google Maps API loaded after delay');
                initializeGoogleMaps();
            } else {
                console.error('Google Maps API still not loaded after delay');
            }
        }, 1000);
    }

    // Gestione del form submit
    $('#tour-form').on('submit', function(e) {
        // Mostra il loading overlay
        $('#loading-overlay').removeClass('d-none');
        
        // Log per debug
        console.log('Form submission started');
        
        // Aggiungi un listener per il completamento della richiesta
        $(window).on('beforeunload', function() {
            console.log('Page is about to unload');
        });
    });

    // Add this to your existing JavaScript
    function removeGalleryImage(imageId) {
        if (confirm('Sei sicuro di voler rimuovere questa immagine?')) {
            const container = document.querySelector(`[data-image-id="${imageId}"]`);
            if (container) {
                container.remove();
            }
        }
    }

    // Modify your form submission to handle gallery images
    $('#tour-form').on('submit', function(e) {
        // Show loading overlay
        $('#loading-overlay').removeClass('d-none');
        
        // Get all gallery images to keep
        const galleryKeep = [];
        $('input[name="gallery_keep[]"]').each(function() {
            galleryKeep.push($(this).val());
        });
        
        // Add gallery keep data to form
        if (galleryKeep.length > 0) {
            $('<input>').attr({
                type: 'hidden',
                name: 'gallery_keep',
                value: JSON.stringify(galleryKeep)
            }).appendTo('#tour-form');
        }
    });
        // Rimuovi le classi di errore quando l'utente inizia a compilare
        $('[required]').on('input change', function() {
            var $field = $(this);
            var fieldValue = $field.val();
            var hasValue = false;
            
            if ($field.is('select[multiple]')) {
                hasValue = fieldValue && fieldValue.length > 0;
            } else {
                hasValue = fieldValue && fieldValue.trim() !== '';
            }
            
            if (hasValue) {
                $field.removeClass('border-danger');
                $field.closest('.mb-3, .col-md-6').find('label').removeClass('text-danger');
            }
        });
    } catch (error) {
        console.error('Error in tour form JavaScript:', error);
    }
});
</script>

<?php get_footer(); ?>
