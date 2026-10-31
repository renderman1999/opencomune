<?php
/* Template Name: Modifica Esperienza */

// Include WordPress media handling functions
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// Carica jQuery
wp_enqueue_script('jquery');

// Carica SweetAlert2
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

// Carica WordPress Media Uploader per il frontend
wp_enqueue_media();
wp_enqueue_script('wp-media-uploader');

// Carica Google Maps API
$api_key = opencomune_get_google_maps_api_key();
if (empty($api_key)) {
    error_log('Google Maps API key is not set in OpenComune settings');
}
wp_enqueue_script('google-maps', 'https://maps.googleapis.com/maps/api/js?key=' . $api_key . '&libraries=places&callback=opencomunePartnerMapsReady', array(), null, true);
wp_add_inline_script('google-maps', 'window.opencomunePartnerMapsReady = function(){ window.opencomunePartnerMapsLoaded = true; document.dispatchEvent(new Event("opencomune-partner-maps-ready")); };', 'before');

// Aggiungi ajaxurl al frontend
wp_localize_script('select2', 'select2_params', array(
    'ajaxurl' => admin_url('admin-ajax.php')
));

get_header();

if (!is_user_logged_in() || !current_user_can('partner')) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">' . esc_html__('Accesso riservato ai Partner. Effettua il login con un account autorizzato.', 'opencomune') . '</div>';
    get_footer();
    exit;
}

// Verifica che sia specificata un'esperienza da modificare
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$edit_id) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">Nessuna esperienza specificata per la modifica. <a href="' . esc_url(opencomune_get_dashboard_url()) . '" class="underline text-blue-700">Torna alla dashboard</a>.</div>';
    get_footer();
    exit;
}

// Carica i dati dell'esperienza
$post = get_post($edit_id);
if (!$post || $post->post_type !== 'esperienze' || !opencomune_user_can_edit_esperienza($edit_id)) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">Esperienza non trovata o non hai i permessi per modificarla. <a href="' . esc_url(opencomune_get_dashboard_url()) . '" class="underline text-blue-700">Torna alla dashboard</a>.</div>';
    get_footer();
    exit;
}

$descrizione_breve = get_post_meta($edit_id, 'descrizione_breve', true);
if ($descrizione_breve === '') {
    $descrizione_breve = $post->post_excerpt;
}

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tour'])) {
    error_log('=== INIZIO AGGIORNAMENTO ESPERIENZA ===');
    
    // Sanitizzazione dei dati
    $tour_title = sanitize_text_field($_POST['tour_title'] ?? '');
    $tour_excerpt = sanitize_textarea_field($_POST['tour_excerpt'] ?? '');
    $tour_description = wp_kses_post($_POST['tour_description'] ?? '');
    $tour_duration = sanitize_text_field($_POST['tour_duration'] ?? '');
    $tour_date_mode = sanitize_text_field($_POST['tour_date_mode'] ?? 'single');
    $tour_start_date = sanitize_text_field($_POST['tour_start_date'] ?? '');
    $tour_end_date = sanitize_text_field($_POST['tour_end_date'] ?? '');
    $mostra_form_prenotazione = !empty($_POST['mostra_form_prenotazione']) ? '1' : '0';
    $tour_max_participants_raw = trim((string) ($_POST['tour_max_participants'] ?? ''));
    $tour_max_participants = ($tour_max_participants_raw !== '') ? intval($tour_max_participants_raw) : '';
    $tour_difficulty = sanitize_text_field($_POST['tour_difficulty'] ?? '');
    $tour_languages = sanitize_text_field($_POST['tour_languages'] ?? '');
    $tour_meeting_point = sanitize_text_field($_POST['tour_meeting_point'] ?? '');
    $tour_whats_included = wp_kses_post($_POST['tour_whats_included'] ?? '');
    $tour_whats_not_included = wp_kses_post($_POST['tour_whats_not_included'] ?? '');
    $tour_requirements = wp_kses_post($_POST['tour_requirements'] ?? '');
    $tour_cancellation_policy = wp_kses_post($_POST['tour_cancellation_policy'] ?? '');
    $tour_highlights = wp_kses_post($_POST['tour_highlights'] ?? '');
    $tour_itinerary = wp_kses_post($_POST['tour_itinerary'] ?? '');
    $tour_meeting_time = sanitize_text_field($_POST['tour_meeting_time'] ?? '');
    $tour_meeting_date = sanitize_text_field($_POST['tour_meeting_date'] ?? '');
    $tour_latitude = floatval($_POST['tour_latitude'] ?? 0);
    $tour_longitude = floatval($_POST['tour_longitude'] ?? 0);
    $tour_address = sanitize_text_field($_POST['tour_address'] ?? '');
    $tour_destination = esc_url_raw($_POST['tour_destination'] ?? '');
    $tour_categories = array_map('sanitize_text_field', $_POST['tour_categories'] ?? []);
    $tour_new_categories = array_map('sanitize_text_field', $_POST['tour_new_categories'] ?? []);
    
    // Debug: log delle categorie ricevute
    error_log('Categorie ricevute: ' . print_r($tour_categories, true));
    
    // Validazione
    if (empty($tour_title)) {
        $error_message = 'Il titolo è obbligatorio.';
    } elseif (empty($tour_description)) {
        $error_message = 'La descrizione è obbligatoria.';
    } else {
        $date_validation = opencomune_validate_tour_date_fields($tour_date_mode, $tour_start_date, $tour_end_date);
        if (is_wp_error($date_validation)) {
            $error_message = $date_validation->get_error_message();
        }
    }

    if (empty($error_message)) {
        $tour_date_mode = $date_validation['tour_date_mode'];
        $tour_start_date = $date_validation['tour_start_date'];
        $tour_end_date = $date_validation['tour_end_date'];
        $tour_duration = opencomune_calculate_tour_duration_from_dates($tour_date_mode, $tour_start_date, $tour_end_date);

        // Aggiornamento del post
    $post_data = array(
            'ID' => $edit_id,
            'post_title' => $tour_title,
            'post_excerpt' => $tour_excerpt,
            'post_content' => $tour_description,
            'post_status' => $post->post_status, // Mantieni lo stato attuale
            'post_type' => 'esperienze',
            'post_name' => sanitize_title($tour_title) // Aggiorna lo slug automaticamente
        );
        
        $result = wp_update_post($post_data);
        
        if ($result && !is_wp_error($result)) {
            update_post_meta($edit_id, 'descrizione_breve', $tour_excerpt);
            // Aggiornamento dei meta fields
            update_post_meta($edit_id, 'tour_duration', $tour_duration);
            update_post_meta($edit_id, 'tour_date_mode', $tour_date_mode);
            update_post_meta($edit_id, 'tour_start_date', $tour_start_date);
            update_post_meta($edit_id, 'tour_end_date', $tour_end_date);
            update_post_meta($edit_id, 'mostra_form_prenotazione', $mostra_form_prenotazione);
            if ($tour_max_participants !== '') {
                update_post_meta($edit_id, 'tour_max_participants', $tour_max_participants);
            } else {
                delete_post_meta($edit_id, 'tour_max_participants');
            }
            if ($tour_difficulty !== '') {
                update_post_meta($edit_id, 'tour_difficulty', $tour_difficulty);
            } else {
                delete_post_meta($edit_id, 'tour_difficulty');
            }
            update_post_meta($edit_id, 'tour_languages', $tour_languages);
            update_post_meta($edit_id, 'tour_meeting_point', $tour_meeting_point);
            update_post_meta($edit_id, 'tour_whats_included', $tour_whats_included);
            update_post_meta($edit_id, 'tour_whats_not_included', $tour_whats_not_included);
            update_post_meta($edit_id, 'tour_requirements', $tour_requirements);
            update_post_meta($edit_id, 'tour_cancellation_policy', $tour_cancellation_policy);
            update_post_meta($edit_id, 'tour_highlights', $tour_highlights);
            update_post_meta($edit_id, 'tour_itinerary', $tour_itinerary);
            update_post_meta($edit_id, 'tour_meeting_time', $tour_meeting_time);
            update_post_meta($edit_id, 'tour_meeting_date', $tour_meeting_date);
            update_post_meta($edit_id, 'tour_latitude', $tour_latitude);
            update_post_meta($edit_id, 'tour_longitude', $tour_longitude);
            update_post_meta($edit_id, 'tour_address', $tour_address);
            if ($tour_destination !== '') {
                update_post_meta($edit_id, 'tour_destination', $tour_destination);
            } else {
                delete_post_meta($edit_id, 'tour_destination');
            }
            
            opencomune_save_esperienza_categories($edit_id, $tour_categories, $tour_new_categories);
            
            // Gestione dell'immagine principale
            $featured_image_id = intval($_POST['featured_image_id'] ?? 0);
            if ($featured_image_id > 0) {
                set_post_thumbnail($edit_id, $featured_image_id);
            } else {
                // Rimuovi immagine principale se non selezionata
                delete_post_thumbnail($edit_id);
            }
            
            // Gestione della galleria fotografica
            $gallery_ids = array();
            if (!empty($_POST['gallery_ids'])) {
                $gallery_ids = array_map('intval', explode(',', $_POST['gallery_ids']));
                $gallery_ids = array_filter($gallery_ids); // Rimuove valori vuoti
                
                update_post_meta($edit_id, 'galleria', $gallery_ids);
            } else {
                // Rimuovi galleria se vuota
                delete_post_meta($edit_id, 'galleria');
            }

            opencomune_save_esperienza_allegati($edit_id, $_POST['allegati_ids'] ?? '');
            opencomune_save_esperienza_availability_rules($edit_id, $_POST);
            opencomune_save_esperienza_translations_from_post($edit_id, $_POST);
            
            $success = true;
            $success_message = 'L\'esperienza "' . esc_js($tour_title) . '" è stata aggiornata con successo.';
            $descrizione_breve = $tour_excerpt;
            $post = get_post($edit_id);
            $dashboard_url = esc_url(opencomune_get_dashboard_url());
            
            // Imposta una variabile per indicare il successo
            $show_success_modal = true;
                } else {
            $error_message = 'Errore durante l\'aggiornamento dell\'esperienza.';
        }
    }
}

// Carica le categorie
$categorie = get_terms(array(
    'taxonomy' => 'categorie_esperienze',
    'hide_empty' => false,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Carica i dati esistenti
$existing_categories = wp_get_post_terms($edit_id, 'categorie_esperienze', array('fields' => 'slugs'));
?>

<style>
.form-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.form-section:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
}

.form-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-textarea {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 0.875rem;
    min-height: 120px;
    resize: vertical;
    transition: all 0.3s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-label {
    display: block;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.5rem;
    font-size: 0.875rem;
}

.form-required {
    color: #ef4444;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
    background: #6b7280;
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-secondary:hover {
    background: #4b5563;
    transform: translateY(-1px);
}

.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin-bottom: 1rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #e5e7eb;
}

.error-message {
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.success-message {
    background: #f0fdf4;
    border: 1px solid #bbf7d0;
    color: #16a34a;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1rem;
}

.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid #f3f4f6;
    border-top: 4px solid #667eea;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Modifica Esperienza</h1>
                    <p class="text-gray-600 mt-2">Modifica l'esperienza turistica selezionata</p>
                </div>
                <div class="flex space-x-4">
                    <a href="<?php echo opencomune_get_dashboard_url(); ?>" class="btn-secondary">
                        <i class="bi bi-arrow-left mr-2"></i>
                        Torna alla Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Messaggi di errore/successo -->
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="bi bi-exclamation-triangle mr-2"></i>
                <?php echo esc_html($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($show_success_modal) && $show_success_modal): ?>
            <script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                        title: "Esperienza Aggiornata!",
                        text: "L'esperienza è stata aggiornata con successo.",
                icon: "success",
                        confirmButtonText: "Vai alla Dashboard",
                showCancelButton: true,
                        cancelButtonText: "Continua Modifica"
            }).then((result) => {
                if (result.isConfirmed) {
                            window.location.href = "<?php echo esc_js(opencomune_get_dashboard_url()); ?>";
                }
                        // Se sceglie "Continua Modifica", il modal non si ripresenterà
            });
        });
            </script>
<?php endif; ?>

        <!-- Form -->
        <form method="POST" enctype="multipart/form-data" id="modifica-esperienza-form" class="space-y-8">
            <!-- Sezione Informazioni Base -->
            <div class="form-section p-6">
                <h2 class="section-title">
                    <i class="bi bi-info-circle mr-2"></i>
                    Informazioni Base
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    get_template_part('template-parts/esperienza-form-bilingual-base', null, [
                        'post_id' => $edit_id,
                    ]);
                    ?>

                    <div>
                        <?php
                        $form_date_mode = $_SERVER['REQUEST_METHOD'] === 'POST'
                            ? ($_POST['tour_date_mode'] ?? 'single')
                            : (get_post_meta($edit_id, 'tour_date_mode', true) ?: 'single');
                        $form_start_date = $_SERVER['REQUEST_METHOD'] === 'POST'
                            ? ($_POST['tour_start_date'] ?? '')
                            : get_post_meta($edit_id, 'tour_start_date', true);
                        $form_end_date = $_SERVER['REQUEST_METHOD'] === 'POST'
                            ? ($_POST['tour_end_date'] ?? '')
                            : get_post_meta($edit_id, 'tour_end_date', true);
                        get_template_part('template-parts/partner-esperienza', 'date-fields', [
                            'tour_date_mode' => $form_date_mode,
                            'tour_start_date' => $form_start_date,
                            'tour_end_date' => $form_end_date,
                            'mostra_form_prenotazione' => $_SERVER['REQUEST_METHOD'] === 'POST'
                                ? (!empty($_POST['mostra_form_prenotazione']) ? '1' : '0')
                                : (get_post_meta($edit_id, 'mostra_form_prenotazione', true) ?: '0'),
                        ]);
                        ?>
                    </div>

                    <div>
                        <label for="tour_duration" class="form-label">
                            Durata
                        </label>
                        <input type="text" id="tour_duration" name="tour_duration" class="form-input"
                               readonly style="background-color: #f9fafb;"
                               placeholder="Calcolata dalle date"
                               value="<?php echo esc_attr(opencomune_calculate_tour_duration_from_dates($form_date_mode, $form_start_date, $form_end_date)); ?>">
                        <p class="text-sm text-gray-500 mt-1">Calcolata automaticamente in base alle date inserite.</p>
                        </div>

                    <div>
                        <label for="tour_max_participants" class="form-label">
                            Max Partecipanti <span class="text-gray-500 font-normal">(facoltativo)</span>
                        </label>
                        <input type="number" id="tour_max_participants" name="tour_max_participants" class="form-input" 
                               min="1" step="1" placeholder="es. 20"
                               value="<?php echo esc_attr(get_post_meta($edit_id, 'tour_max_participants', true)); ?>">
                        <p class="text-sm text-gray-500 mt-1">Lascia vuoto se non c'è un limite di partecipanti</p>
                        </div>

                    <div>
                        <label for="tour_difficulty" class="form-label">
                            Difficoltà <span class="text-gray-500 font-normal">(facoltativo)</span>
                        </label>
                        <select id="tour_difficulty" name="tour_difficulty" class="form-input">
                            <option value="">Seleziona difficoltà</option>
                            <option value="facile" <?php selected(get_post_meta($edit_id, 'tour_difficulty', true), 'facile'); ?>>Facile</option>
                            <option value="medio" <?php selected(get_post_meta($edit_id, 'tour_difficulty', true), 'medio'); ?>>Medio</option>
                            <option value="difficile" <?php selected(get_post_meta($edit_id, 'tour_difficulty', true), 'difficile'); ?>>Difficile</option>
                            </select>
                        </div>
                        </div>
                    </div>

            <!-- Sezione Categorie e Immagine -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-tags mr-2"></i>
                    Categorie e Immagine
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <?php
                        get_template_part('template-parts/partner-esperienza', 'categories-fields', [
                            'selected_slugs' => $_SERVER['REQUEST_METHOD'] === 'POST'
                                ? ($_POST['tour_categories'] ?? [])
                                : $existing_categories,
                            'new_categories' => $_POST['tour_new_categories'] ?? [],
                            'categorie' => $categorie,
                        ]);
                        ?>
            </div>

                    <div>
                        <label class="form-label">
                            Immagine Principale <span class="form-required">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                            <button type="button" id="upload-featured-image" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                Seleziona Immagine
                            </button>
                            <div id="featured-image-preview" class="mt-2 <?php echo has_post_thumbnail($edit_id) ? '' : 'hidden'; ?>">
                                <img id="featured-image-preview-img" src="<?php echo has_post_thumbnail($edit_id) ? get_the_post_thumbnail_url($edit_id, 'thumbnail') : ''; ?>" alt="" class="w-24 h-24 object-cover rounded mx-auto">
                                <button type="button" id="remove-featured-image" class="text-red-600 text-sm mt-1">Rimuovi</button>
                            </div>
                            <input type="hidden" id="featured-image-id" name="featured_image_id" value="<?php echo has_post_thumbnail($edit_id) ? get_post_thumbnail_id($edit_id) : ''; ?>">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Formati supportati: JPG, PNG, GIF. Max 5MB</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <label class="form-label">
                        Galleria Fotografica
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <button type="button" id="upload-gallery-images" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">
                            Seleziona Immagini
                        </button>
                        <div id="gallery-preview" class="mt-4 grid grid-cols-4 gap-2">
                            <?php 
                            $existing_gallery = get_post_meta($edit_id, 'galleria', true);
                            if (is_array($existing_gallery) && !empty($existing_gallery)): 
                                foreach($existing_gallery as $gallery_id): 
                                    $img_url = wp_get_attachment_image_url($gallery_id, 'thumbnail');
                                    if ($img_url): ?>
                                        <img src="<?php echo esc_url($img_url); ?>" alt="" class="w-16 h-16 object-cover rounded" data-id="<?php echo $gallery_id; ?>">
                                    <?php endif;
                                endforeach;
                            endif; ?>
                        </div>
                        <input type="hidden" id="gallery-ids" name="gallery_ids" value="<?php echo is_array($existing_gallery) ? implode(',', $existing_gallery) : ''; ?>">
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Seleziona più immagini per la galleria (JPG, PNG, GIF. Max 5MB ciascuna)</p>
                    </div>
                        </div>

            <!-- Sezione Dettagli -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-list-ul mr-2"></i>
                    Dettagli Esperienza
                </h2>
                
                <?php
                get_template_part('template-parts/esperienza-form-bilingual-details', null, [
                    'post_id' => $edit_id,
                ]);
                ?>
            </div>

            <!-- Sezione Incontro -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-geo-alt mr-2"></i>
                    Punto di Incontro
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="tour_meeting_point" class="form-label">
                            Luogo di Incontro
                        </label>
                        <input type="text" id="tour_meeting_point" name="tour_meeting_point" class="form-input" 
                               placeholder="es. Piazza del Duomo" value="<?php echo esc_attr(get_post_meta($edit_id, 'tour_meeting_point', true)); ?>">
                        </div>

                    <div>
                        <label for="tour_meeting_time" class="form-label">
                            Orario di Incontro
                        </label>
                        <input type="time" id="tour_meeting_time" name="tour_meeting_time" class="form-input" 
                               value="<?php echo esc_attr(get_post_meta($edit_id, 'tour_meeting_time', true)); ?>">
                            </div>
                    
                    <div>
                        <label for="tour_meeting_date" class="form-label">
                            Data di Incontro
                        </label>
                        <input type="date" id="tour_meeting_date" name="tour_meeting_date" class="form-input" 
                               value="<?php echo esc_attr(get_post_meta($edit_id, 'tour_meeting_date', true)); ?>">
                            </div>
                    
                    <div>
                        <label for="tour_languages" class="form-label">
                            Lingue
                        </label>
                        <input type="text" id="tour_languages" name="tour_languages" class="form-input" 
                               placeholder="es. Italiano, Inglese" value="<?php echo esc_attr(get_post_meta($edit_id, 'tour_languages', true)); ?>">
                        </div>
                    </div>

                <?php
                get_template_part('template-parts/partner-esperienza', 'address-fields', [
                    'tour_address' => $_SERVER['REQUEST_METHOD'] === 'POST'
                        ? ($_POST['tour_address'] ?? '')
                        : get_post_meta($edit_id, 'tour_address', true),
                    'tour_latitude' => $_SERVER['REQUEST_METHOD'] === 'POST'
                        ? ($_POST['tour_latitude'] ?? '')
                        : get_post_meta($edit_id, 'tour_latitude', true),
                    'tour_longitude' => $_SERVER['REQUEST_METHOD'] === 'POST'
                        ? ($_POST['tour_longitude'] ?? '')
                        : get_post_meta($edit_id, 'tour_longitude', true),
                ]);
                ?>

                <?php
                get_template_part('template-parts/partner-esperienza-external-link-fields', null, [
                    'tour_destination' => $_SERVER['REQUEST_METHOD'] === 'POST'
                        ? ($_POST['tour_destination'] ?? '')
                        : get_post_meta($edit_id, 'tour_destination', true),
                ]);
                ?>
            </div>

            <?php
            get_template_part('template-parts/partner-esperienza-allegati-fields', null, [
                'post_id' => $edit_id,
            ]);
            ?>

            <?php
            get_template_part('template-parts/esperienza-form-availability-calendar', null, [
                'post_id' => $edit_id,
                'form_id' => 'modifica-esperienza-form',
            ]);
            ?>

            <!-- Sezione Politiche -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-shield-check mr-2"></i>
                    Politiche e Termini
                </h2>
                
                <?php
                get_template_part('template-parts/esperienza-form-bilingual-cancellation', null, [
                    'post_id' => $edit_id,
                ]);
                ?>
                    </div>

            <!-- Pulsanti di Azione -->
            <div class="flex justify-end space-x-4 pb-8">
                <a href="<?php echo opencomune_get_dashboard_url(); ?>" class="btn-secondary">
                    <i class="bi bi-x-circle mr-2"></i>
                    Annulla
                </a>
                <button type="submit" name="save_tour" class="btn-primary">
                    <i class="bi bi-check-circle mr-2"></i>
                    Salva Modifiche
                                            </button>
                                        </div>
        </form>
                </div>
            </div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="bg-white p-6 rounded-lg shadow-lg text-center">
        <div class="loading-spinner mx-auto mb-4"></div>
        <p class="text-gray-600">Salvataggio in corso...</p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Checkbox per categorie - nessuna inizializzazione necessaria
    
    // Gestione del form
    const form = document.getElementById('modifica-esperienza-form');
    const loadingOverlay = document.getElementById('loading-overlay');
    
    form.addEventListener('submit', function(e) {
        // Mostra loading
        loadingOverlay.style.display = 'flex';
        
        // Validazione lato client
        const requiredFields = ['tour_title', 'tour_description', 'tour_start_date'];
        let isValid = true;
        
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (!field.value.trim()) {
                field.style.borderColor = '#ef4444';
                isValid = false;
            } else {
                field.style.borderColor = '#d1d5db';
            }
        });
        
        // Validazione immagine principale
        const featuredImageId = document.getElementById('featured-image-id').value;
        if (!featuredImageId) {
            isValid = false;
            Swal.fire({
                title: 'Immagine Obbligatoria',
                text: 'Devi selezionare un\'immagine principale per l\'esperienza',
                icon: 'warning'
            });
        }
        
        if (!isValid) {
            e.preventDefault();
            loadingOverlay.style.display = 'none';
            if (featuredImageId) {
                Swal.fire({
                    title: 'Campi Obbligatori',
                    text: 'Compila tutti i campi obbligatori',
                    icon: 'warning'
                });
            }
            return;
        }

        const dateMode = document.querySelector('.tour-date-mode-radio:checked');
        const startDate = document.getElementById('tour_start_date').value;
        const endDate = document.getElementById('tour_end_date').value;
        if (dateMode && dateMode.value === 'range') {
            if (!endDate) {
                e.preventDefault();
                loadingOverlay.style.display = 'none';
                Swal.fire({
                    title: 'Data fine obbligatoria',
                    text: 'Inserisci la data di fine per il periodo selezionato.',
                    icon: 'warning'
                });
                return;
            }
            if (endDate < startDate) {
                e.preventDefault();
                loadingOverlay.style.display = 'none';
                Swal.fire({
                    title: 'Periodo non valido',
                    text: 'La data di fine deve essere uguale o successiva alla data di inizio.',
                    icon: 'warning'
                });
                return;
            }
        }
        
        // Se tutto è valido, il form viene inviato
    });
    
    // Validazione file upload (ora gestito da WordPress Media Uploader)
    
    // WordPress Media Uploader per immagine principale
    let featuredImageFrame;
    const uploadFeaturedBtn = document.getElementById('upload-featured-image');
    if (uploadFeaturedBtn && typeof wp !== 'undefined' && wp.media) {
        uploadFeaturedBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (featuredImageFrame) {
            featuredImageFrame.open();
            return;
        }
        
        featuredImageFrame = wp.media({
            title: 'Seleziona Immagine Principale',
            button: {
                text: 'Usa questa immagine'
            },
            multiple: false
        });
        
        featuredImageFrame.on('select', function() {
            const attachment = featuredImageFrame.state().get('selection').first().toJSON();
            document.getElementById('featured-image-id').value = attachment.id;
            document.getElementById('featured-image-preview-img').src = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            document.getElementById('featured-image-preview').classList.remove('hidden');
        });
        
        featuredImageFrame.open();
        });
    }
    
    // Rimuovi immagine principale
    const removeFeaturedBtn = document.getElementById('remove-featured-image');
    if (removeFeaturedBtn) {
        removeFeaturedBtn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('featured-image-id').value = '';
        document.getElementById('featured-image-preview').classList.add('hidden');
        });
    }
    
    // WordPress Media Uploader per galleria
    let galleryFrame;
    const uploadGalleryBtn = document.getElementById('upload-gallery-images');
    if (uploadGalleryBtn && typeof wp !== 'undefined' && wp.media) {
        uploadGalleryBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (galleryFrame) {
            galleryFrame.open();
            return;
        }
        
        galleryFrame = wp.media({
            title: 'Seleziona Immagini per la Galleria',
            button: {
                text: 'Aggiungi alla galleria'
            },
            multiple: true
        });
        
        galleryFrame.on('select', function() {
            const attachments = galleryFrame.state().get('selection').toJSON();
            const currentIds = document.getElementById('gallery-ids').value ? document.getElementById('gallery-ids').value.split(',') : [];
            const newIds = attachments.map(att => att.id);
            const allIds = [...new Set([...currentIds, ...newIds])]; // Rimuove duplicati
            document.getElementById('gallery-ids').value = allIds.join(',');
            
            // Mostra anteprime
            const preview = document.getElementById('gallery-preview');
            preview.innerHTML = '';
            allIds.forEach(id => {
                const attachment = attachments.find(att => att.id == id) || { sizes: { thumbnail: { url: '' } }, url: '' };
                const img = document.createElement('img');
                img.src = attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                img.className = 'w-16 h-16 object-cover rounded';
                img.alt = attachment.alt || '';
                img.dataset.id = id;
                preview.appendChild(img);
            });
        });
        
        galleryFrame.open();
        });
    }
    
    // Rimuovi immagini dalla galleria
    const galleryPreview = document.getElementById('gallery-preview');
    if (galleryPreview) {
        galleryPreview.addEventListener('click', function(e) {
        if (e.target.tagName === 'IMG') {
            const imgId = e.target.dataset.id;
            const currentIds = document.getElementById('gallery-ids').value ? document.getElementById('gallery-ids').value.split(',') : [];
            const newIds = currentIds.filter(id => id != imgId);
            document.getElementById('gallery-ids').value = newIds.join(',');
            e.target.remove();
        }
        });
    }
});
</script>

<?php get_footer(); ?>