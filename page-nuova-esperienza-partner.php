<?php
/* Template Name: Nuova Esperienza Partner */

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

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tour'])) {
    error_log('=== INIZIO SALVATAGGIO ESPERIENZA ===');
    
    // Sanitizzazione dei dati
    $tour_title = sanitize_text_field($_POST['tour_title'] ?? '');
    $tour_excerpt = sanitize_textarea_field($_POST['tour_excerpt'] ?? '');
    $tour_description = wp_kses_post($_POST['tour_description'] ?? '');
    $tour_duration = sanitize_text_field($_POST['tour_duration'] ?? '');
    $tour_date_mode = sanitize_text_field($_POST['tour_date_mode'] ?? 'single');
    $tour_start_date = sanitize_text_field($_POST['tour_start_date'] ?? '');
    $tour_end_date = sanitize_text_field($_POST['tour_end_date'] ?? '');
    $mostra_form_prenotazione = !empty($_POST['mostra_form_prenotazione']) ? '1' : '0';
    $tour_price = floatval($_POST['tour_price'] ?? 0); // Aggiunto per partner
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
    $tour_destination = esc_url_raw($_POST['tour_destination'] ?? ''); // URL del sito partner
    $tour_categories = array_map('sanitize_text_field', $_POST['tour_categories'] ?? []);
    $tour_new_categories = array_map('sanitize_text_field', $_POST['tour_new_categories'] ?? []);
    
    // Debug: log delle categorie ricevute
    error_log('Categorie ricevute (nuova esperienza): ' . print_r($tour_categories, true));
    
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

        // Creazione del post
    $post_data = array(
            'post_title' => $tour_title,
            'post_excerpt' => $tour_excerpt,
            'post_content' => $tour_description,
            'post_status' => 'draft',
            'post_type' => 'esperienze',
            'post_author' => get_current_user_id(),
            'post_name' => sanitize_title($tour_title) // Genera lo slug dal titolo
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'descrizione_breve', $tour_excerpt);
            // Salvataggio dei meta fields
            update_post_meta($post_id, 'tour_duration', $tour_duration);
            update_post_meta($post_id, 'tour_date_mode', $tour_date_mode);
            update_post_meta($post_id, 'tour_start_date', $tour_start_date);
            update_post_meta($post_id, 'tour_end_date', $tour_end_date);
            update_post_meta($post_id, 'mostra_form_prenotazione', $mostra_form_prenotazione);
            update_post_meta($post_id, 'tour_price', $tour_price); // Aggiunto per partner
            if ($tour_max_participants !== '') {
                update_post_meta($post_id, 'tour_max_participants', $tour_max_participants);
            } else {
                delete_post_meta($post_id, 'tour_max_participants');
            }
            if ($tour_difficulty !== '') {
                update_post_meta($post_id, 'tour_difficulty', $tour_difficulty);
            } else {
                delete_post_meta($post_id, 'tour_difficulty');
            }
            update_post_meta($post_id, 'tour_languages', $tour_languages);
            update_post_meta($post_id, 'tour_meeting_point', $tour_meeting_point);
            update_post_meta($post_id, 'tour_whats_included', $tour_whats_included);
            update_post_meta($post_id, 'tour_whats_not_included', $tour_whats_not_included);
            update_post_meta($post_id, 'tour_requirements', $tour_requirements);
            update_post_meta($post_id, 'tour_cancellation_policy', $tour_cancellation_policy);
            update_post_meta($post_id, 'tour_highlights', $tour_highlights);
            update_post_meta($post_id, 'tour_itinerary', $tour_itinerary);
            update_post_meta($post_id, 'tour_meeting_time', $tour_meeting_time);
            update_post_meta($post_id, 'tour_meeting_date', $tour_meeting_date);
            update_post_meta($post_id, 'tour_latitude', $tour_latitude);
            update_post_meta($post_id, 'tour_longitude', $tour_longitude);
            update_post_meta($post_id, 'tour_address', $tour_address);
            if ($tour_destination !== '') {
                update_post_meta($post_id, 'tour_destination', $tour_destination);
            } else {
                delete_post_meta($post_id, 'tour_destination');
            }
            
            opencomune_save_esperienza_categories($post_id, $tour_categories, $tour_new_categories);
            
            // Gestione dell'immagine principale
            $featured_image_id = intval($_POST['featured_image_id'] ?? 0);
            if ($featured_image_id > 0) {
                set_post_thumbnail($post_id, $featured_image_id);
            }
            
            // Gestione della galleria fotografica
            $gallery_ids = array();
            if (!empty($_POST['gallery_ids'])) {
                $gallery_ids = array_map('intval', explode(',', $_POST['gallery_ids']));
                $gallery_ids = array_filter($gallery_ids); // Rimuove valori vuoti
                
                if (!empty($gallery_ids)) {
                    update_post_meta($post_id, 'galleria', $gallery_ids);
                }
            }

            opencomune_save_esperienza_allegati($post_id, $_POST['allegati_ids'] ?? '');
            opencomune_save_esperienza_translations_from_post($post_id, $_POST);
            
            $success = true;
            $success_message = 'L\'esperienza "' . $tour_title . '" è stata creata con successo.';
            $dashboard_url = esc_url(opencomune_get_dashboard_url());
            
    echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                        title: "Esperienza Creata!",
                text: ' . json_encode($success_message) . ',
                icon: "success",
                        confirmButtonText: "Vai alla Dashboard",
                showCancelButton: true,
                        cancelButtonText: "Crea Altra Esperienza"
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = ' . json_encode($dashboard_url) . ';
                } else {
                            window.location.reload();
                }
            });
        });
    </script>';
        } else {
            $error_message = 'Errore durante la creazione dell\'esperienza.';
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
                    <h1 class="text-3xl font-bold text-gray-900">Nuova Esperienza Partner</h1>
                    <p class="text-gray-600 mt-2">Crea una nuova esperienza turistica per il tuo business</p>
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

        <?php if (isset($success_message)): ?>
            <div class="success-message">
                <i class="bi bi-check-circle mr-2"></i>
                <?php echo esc_html($success_message); ?>
                        </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" enctype="multipart/form-data" id="nuova-esperienza-form" class="space-y-8">
            <!-- Sezione Informazioni Base -->
            <div class="form-section p-6">
                <h2 class="section-title">
                    <i class="bi bi-info-circle mr-2"></i>
                    Informazioni Base
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    get_template_part('template-parts/esperienza-form-bilingual-base', null, [
                        'post_id' => 0,
                    ]);
                    ?>

                    <div>
                        <?php
                        $form_date_mode = $_POST['tour_date_mode'] ?? 'single';
                        $form_start_date = $_POST['tour_start_date'] ?? '';
                        $form_end_date = $_POST['tour_end_date'] ?? '';
                        get_template_part('template-parts/partner-esperienza', 'date-fields', [
                            'tour_date_mode' => $form_date_mode,
                            'tour_start_date' => $form_start_date,
                            'tour_end_date' => $form_end_date,
                            'mostra_form_prenotazione' => !empty($_POST['mostra_form_prenotazione']) ? '1' : '0',
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
                        <label for="tour_price" class="form-label">
                            Prezzo (€)
                        </label>
                        <input type="number" id="tour_price" name="tour_price" class="form-input" 
                               step="0.01" min="0" placeholder="es. 25.00" value="<?php echo esc_attr($_POST['tour_price'] ?? ''); ?>">
                        <p class="text-sm text-gray-500 mt-1">Lascia vuoto per esperienza gratuita</p>
                    </div>
                    
                    <div>
                        <label for="tour_max_participants" class="form-label">
                            Max Partecipanti <span class="text-gray-500 font-normal">(facoltativo)</span>
                        </label>
                        <input type="number" id="tour_max_participants" name="tour_max_participants" class="form-input" 
                               min="1" step="1" placeholder="es. 20"
                               value="<?php echo esc_attr($_POST['tour_max_participants'] ?? ''); ?>">
                        <p class="text-sm text-gray-500 mt-1">Lascia vuoto se non c'è un limite di partecipanti</p>
                    </div>

                    <div>
                        <label for="tour_difficulty" class="form-label">
                            Difficoltà <span class="text-gray-500 font-normal">(facoltativo)</span>
                        </label>
                        <select id="tour_difficulty" name="tour_difficulty" class="form-input">
                            <option value="">Seleziona difficoltà</option>
                            <option value="facile" <?php selected($_POST['tour_difficulty'] ?? '', 'facile'); ?>>Facile</option>
                            <option value="medio" <?php selected($_POST['tour_difficulty'] ?? '', 'medio'); ?>>Medio</option>
                            <option value="difficile" <?php selected($_POST['tour_difficulty'] ?? '', 'difficile'); ?>>Difficile</option>
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
                            'selected_slugs' => $_POST['tour_categories'] ?? [],
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
                            <div id="featured-image-preview" class="mt-2 hidden">
                                <img id="featured-image-preview-img" src="" alt="" class="w-24 h-24 object-cover rounded mx-auto">
                                <button type="button" id="remove-featured-image" class="text-red-600 text-sm mt-1">Rimuovi</button>
                            </div>
                            <input type="hidden" id="featured-image-id" name="featured_image_id" value="">
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
                        <div id="gallery-preview" class="mt-4 grid grid-cols-4 gap-2 hidden">
                            <!-- Le anteprime delle immagini verranno inserite qui -->
                            </div>
                        <input type="hidden" id="gallery-ids" name="gallery_ids" value="">
                            </div>
                    <p class="text-sm text-gray-500 mt-1">Seleziona più immagini per la galleria (JPG, PNG, GIF. Max 5MB ciascuna)</p>
                            </div>
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
                    'post_id' => 0,
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
                               placeholder="es. Piazza del Duomo" value="<?php echo esc_attr($_POST['tour_meeting_point'] ?? ''); ?>">
                        </div>

                    <div>
                        <label for="tour_meeting_time" class="form-label">
                            Orario di Incontro
                        </label>
                        <input type="time" id="tour_meeting_time" name="tour_meeting_time" class="form-input" 
                               value="<?php echo esc_attr($_POST['tour_meeting_time'] ?? ''); ?>">
                            </div>
                    
                    <div>
                        <label for="tour_meeting_date" class="form-label">
                            Data di Incontro
                        </label>
                        <input type="date" id="tour_meeting_date" name="tour_meeting_date" class="form-input" 
                               value="<?php echo esc_attr($_POST['tour_meeting_date'] ?? ''); ?>">
                    </div>

                    <div>
                        <label for="tour_languages" class="form-label">
                            Lingue
                        </label>
                        <input type="text" id="tour_languages" name="tour_languages" class="form-input" 
                               placeholder="es. Italiano, Inglese" value="<?php echo esc_attr($_POST['tour_languages'] ?? ''); ?>">
                        </div>
                    </div>

                <?php
                get_template_part('template-parts/partner-esperienza', 'address-fields', [
                    'tour_address' => $_POST['tour_address'] ?? '',
                    'tour_latitude' => $_POST['tour_latitude'] ?? '',
                    'tour_longitude' => $_POST['tour_longitude'] ?? '',
                ]);
                ?>

                <div class="mt-6">
                    <?php
                    get_template_part('template-parts/partner-esperienza-external-link-fields', null, [
                        'tour_destination' => $_POST['tour_destination'] ?? '',
                    ]);
                    ?>
                </div>
            </div>

            <?php
            get_template_part('template-parts/partner-esperienza-allegati-fields', null, [
                'post_id' => 0,
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
                    'post_id' => 0,
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
                    Salva Esperienza
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
    const form = document.getElementById('nuova-esperienza-form');
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
    
    // Auto-save draft (opzionale)
    let autoSaveTimeout;
    const autoSaveFields = ['tour_title', 'tour_description', 'tour_duration'];
    
    autoSaveFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        field.addEventListener('input', function() {
            clearTimeout(autoSaveTimeout);
            autoSaveTimeout = setTimeout(() => {
                // Qui potresti implementare un auto-save
                console.log('Auto-save triggered for:', fieldName);
            }, 2000);
        });
    });
    
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
            const galleryIds = attachments.map(att => att.id);
            document.getElementById('gallery-ids').value = galleryIds.join(',');
            
            // Mostra anteprime
            const preview = document.getElementById('gallery-preview');
            preview.innerHTML = '';
            attachments.forEach(att => {
                const img = document.createElement('img');
                img.src = att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                img.className = 'w-16 h-16 object-cover rounded';
                img.alt = att.alt;
                preview.appendChild(img);
            });
            preview.classList.remove('hidden');
        });
        
        galleryFrame.open();
        });
    }
});
</script>

<?php get_footer(); ?>