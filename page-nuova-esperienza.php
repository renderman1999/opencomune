<?php
/* Template Name: Nuova Esperienza */

// Include WordPress media handling functions
require_once(ABSPATH . 'wp-admin/includes/image.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/media.php');

// Carica Select2
wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'));

// Carica SweetAlert2
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), null, true);

// Carica Google Maps API
$api_key = opencomune_get_google_maps_api_key();
if (empty($api_key)) {
    error_log('Google Maps API key is not set in OpenComune settings');
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

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tour'])) {
    error_log('=== INIZIO SALVATAGGIO ESPERIENZA ===');
    
    // Sanitizzazione dei dati
    $tour_title = sanitize_text_field($_POST['tour_title'] ?? '');
    $tour_description = wp_kses_post($_POST['tour_description'] ?? '');
    $tour_duration = sanitize_text_field($_POST['tour_duration'] ?? '');
    // $tour_price = floatval($_POST['tour_price'] ?? 0); // Rimosso per ente pubblico
    $tour_max_participants = intval($_POST['tour_max_participants'] ?? 0);
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
    $tour_categories = array_map('sanitize_text_field', $_POST['tour_categories'] ?? []);
    
    // Validazione
    if (empty($tour_title)) {
        $error_message = 'Il titolo è obbligatorio.';
    } elseif (empty($tour_description)) {
        $error_message = 'La descrizione è obbligatoria.';
    } elseif (empty($tour_duration)) {
        $error_message = 'La durata è obbligatoria.';
    // } elseif ($tour_price <= 0) {
    //     $error_message = 'Il prezzo deve essere maggiore di 0.';
    } else {
        // Creazione del post
        $post_data = array(
            'post_title' => $tour_title,
            'post_content' => $tour_description,
            'post_status' => 'draft',
            'post_type' => 'esperienze',
            'post_author' => get_current_user_id()
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // Salvataggio dei meta fields
            update_post_meta($post_id, 'tour_duration', $tour_duration);
            // update_post_meta($post_id, 'tour_price', $tour_price); // Rimosso per ente pubblico
            update_post_meta($post_id, 'tour_max_participants', $tour_max_participants);
            update_post_meta($post_id, 'tour_difficulty', $tour_difficulty);
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
            
            // Gestione delle categorie
            if (!empty($tour_categories)) {
                wp_set_post_terms($post_id, $tour_categories, 'categorie_esperienze');
            }
            
            // Gestione dell'immagine
            if (!empty($_FILES['tour_image']['name'])) {
                $upload = wp_handle_upload($_FILES['tour_image'], array('test_form' => false));
                if (!isset($upload['error'])) {
                    $attachment_id = wp_insert_attachment(array(
                        'post_mime_type' => $upload['type'],
                        'post_title' => sanitize_file_name($_FILES['tour_image']['name']),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    ), $upload['file'], $post_id);
                    
                    if (!is_wp_error($attachment_id)) {
                        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $upload['file']));
                        set_post_thumbnail($post_id, $attachment_id);
                    }
                }
            }
            
            $success = true;
            $success_message = 'L\'esperienza "' . esc_js($tour_title) . '" è stata creata con successo.';
            $dashboard_url = esc_url(site_url('/dashboard-ufficio/'));
            
            echo '<script>
                document.addEventListener("DOMContentLoaded", function() {
                    Swal.fire({
                        title: "Esperienza Creata!",
                        text: "' . $success_message . '",
                        icon: "success",
                        confirmButtonText: "Vai alla Dashboard",
                        showCancelButton: true,
                        cancelButtonText: "Crea Altra Esperienza"
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = "' . $dashboard_url . '";
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
                    <h1 class="text-3xl font-bold text-gray-900">Nuova Esperienza</h1>
                    <p class="text-gray-600 mt-2">Crea una nuova esperienza turistica per il tuo comune</p>
                </div>
                <div class="flex space-x-4">
                    <a href="<?php echo home_url('/dashboard-ufficio/'); ?>" class="btn-secondary">
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
                    <div class="md:col-span-2">
                        <label for="tour_title" class="form-label">
                            Titolo Esperienza <span class="form-required">*</span>
                        </label>
                        <input type="text" id="tour_title" name="tour_title" class="form-input" 
                               value="<?php echo esc_attr($_POST['tour_title'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="tour_description" class="form-label">
                            Descrizione <span class="form-required">*</span>
                        </label>
                        <textarea id="tour_description" name="tour_description" class="form-textarea" required><?php echo esc_textarea($_POST['tour_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label for="tour_duration" class="form-label">
                            Durata <span class="form-required">*</span>
                        </label>
                        <input type="text" id="tour_duration" name="tour_duration" class="form-input" 
                               placeholder="es. 2 ore, 1 giorno" value="<?php echo esc_attr($_POST['tour_duration'] ?? ''); ?>" required>
                    </div>
                    
                    <!-- Campo prezzo rimosso per ente pubblico -->
                    
                    <div>
                        <label for="tour_max_participants" class="form-label">
                            Max Partecipanti
                        </label>
                        <input type="number" id="tour_max_participants" name="tour_max_participants" class="form-input" 
                               min="1" value="<?php echo esc_attr($_POST['tour_max_participants'] ?? ''); ?>">
                    </div>
                    
                    <div>
                        <label for="tour_difficulty" class="form-label">
                            Difficoltà
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
                        <label for="tour_categories" class="form-label">
                            Categorie
                        </label>
                        <select id="tour_categories" name="tour_categories[]" class="form-input" multiple>
                            <?php foreach ($categorie as $categoria): ?>
                                <option value="<?php echo esc_attr($categoria->slug); ?>" 
                                        <?php selected(in_array($categoria->slug, $_POST['tour_categories'] ?? []), true); ?>>
                                    <?php echo esc_html($categoria->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="tour_image" class="form-label">
                            Immagine Principale
                        </label>
                        <input type="file" id="tour_image" name="tour_image" class="form-input" accept="image/*">
                        <p class="text-sm text-gray-500 mt-1">Formati supportati: JPG, PNG, GIF. Max 5MB</p>
                    </div>
                </div>
            </div>

            <!-- Sezione Dettagli -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-list-ul mr-2"></i>
                    Dettagli Esperienza
                </h2>
                
                <div class="space-y-6">
                    <div>
                        <label for="tour_highlights" class="form-label">
                            Punti Salienti
                        </label>
                        <textarea id="tour_highlights" name="tour_highlights" class="form-textarea" 
                                  placeholder="Elenca i punti salienti dell'esperienza..."><?php echo esc_textarea($_POST['tour_highlights'] ?? ''); ?></textarea>
                    </div>
                    
                    <div>
                        <label for="tour_itinerary" class="form-label">
                            Itinerario
                        </label>
                        <textarea id="tour_itinerary" name="tour_itinerary" class="form-textarea" 
                                  placeholder="Descrivi l'itinerario dettagliato..."><?php echo esc_textarea($_POST['tour_itinerary'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="tour_whats_included" class="form-label">
                                Cosa Include
                            </label>
                            <textarea id="tour_whats_included" name="tour_whats_included" class="form-textarea" 
                                      placeholder="Cosa è incluso nel prezzo..."><?php echo esc_textarea($_POST['tour_whats_included'] ?? ''); ?></textarea>
                        </div>
                        
                        <div>
                            <label for="tour_whats_not_included" class="form-label">
                                Cosa Non Include
                            </label>
                            <textarea id="tour_whats_not_included" name="tour_whats_not_included" class="form-textarea" 
                                      placeholder="Cosa non è incluso..."><?php echo esc_textarea($_POST['tour_whats_not_included'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div>
                        <label for="tour_requirements" class="form-label">
                            Requisiti
                        </label>
                        <textarea id="tour_requirements" name="tour_requirements" class="form-textarea" 
                                  placeholder="Requisiti per partecipare..."><?php echo esc_textarea($_POST['tour_requirements'] ?? ''); ?></textarea>
                    </div>
                </div>
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
                
                <div class="mt-6">
                    <label for="tour_address" class="form-label">
                        Indirizzo Completo
                    </label>
                    <input type="text" id="tour_address" name="tour_address" class="form-input" 
                           placeholder="Indirizzo completo del punto di incontro" value="<?php echo esc_attr($_POST['tour_address'] ?? ''); ?>">
                </div>
                
                <div class="mt-6">
                    <label class="form-label">Posizione sulla Mappa</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="tour_latitude" class="form-label">Latitudine</label>
                            <input type="number" id="tour_latitude" name="tour_latitude" class="form-input" 
                                   step="any" placeholder="es. 40.123456" value="<?php echo esc_attr($_POST['tour_latitude'] ?? ''); ?>">
                        </div>
                        <div>
                            <label for="tour_longitude" class="form-label">Longitudine</label>
                            <input type="number" id="tour_longitude" name="tour_longitude" class="form-input" 
                                   step="any" placeholder="es. 18.123456" value="<?php echo esc_attr($_POST['tour_longitude'] ?? ''); ?>">
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">Usa Google Maps per trovare le coordinate precise</p>
                </div>
            </div>

            <!-- Sezione Politiche -->
            <div class="form-section p-6 mt-8">
                <h2 class="section-title">
                    <i class="bi bi-shield-check mr-2"></i>
                    Politiche e Termini
                </h2>
                
                <div>
                    <label for="tour_cancellation_policy" class="form-label">
                        Politica di Cancellazione
                    </label>
                    <textarea id="tour_cancellation_policy" name="tour_cancellation_policy" class="form-textarea" 
                              placeholder="Descrivi la politica di cancellazione..."><?php echo esc_textarea($_POST['tour_cancellation_policy'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Pulsanti di Azione -->
            <div class="flex justify-end space-x-4 pb-8">
                <a href="<?php echo home_url('/dashboard-ufficio/'); ?>" class="btn-secondary">
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
    // Inizializza Select2
    $('#tour_categories').select2({
        placeholder: 'Seleziona le categorie',
        allowClear: true
    });
    
    // Gestione del form
    const form = document.getElementById('nuova-esperienza-form');
    const loadingOverlay = document.getElementById('loading-overlay');
    
    form.addEventListener('submit', function(e) {
        // Mostra loading
        loadingOverlay.style.display = 'flex';
        
        // Validazione lato client
        const requiredFields = ['tour_title', 'tour_description', 'tour_duration'];
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
        
        if (!isValid) {
            e.preventDefault();
            loadingOverlay.style.display = 'none';
            Swal.fire({
                title: 'Campi Obbligatori',
                text: 'Compila tutti i campi obbligatori',
                icon: 'warning'
            });
            return;
        }
        
        // Se tutto è valido, il form viene inviato
    });
    
    // Gestione file upload
    const fileInput = document.getElementById('tour_image');
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            // Validazione dimensione (5MB)
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    title: 'File Troppo Grande',
                    text: 'L\'immagine deve essere inferiore a 5MB',
                    icon: 'error'
                });
                fileInput.value = '';
                return;
            }
            
            // Validazione tipo
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                Swal.fire({
                    title: 'Formato Non Supportato',
                    text: 'Usa solo file JPG, PNG o GIF',
                    icon: 'error'
                });
                fileInput.value = '';
                return;
            }
        }
    });
    
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
});
</script>

<?php get_footer(); ?>