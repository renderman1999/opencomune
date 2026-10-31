<?php
/* Template Name: Nuova Esperienza */

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
wp_enqueue_editor();

// Carica Google Maps API
$api_key = opencomune_get_google_maps_api_key();
if (empty($api_key)) {
    error_log('Google Maps API key is not set in OpenComune settings');
}
wp_enqueue_script(
    'google-maps',
    'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places&callback=opencomuneNuovaEsperienzaMapsReady',
    [],
    null,
    true
);
wp_add_inline_script(
    'google-maps',
    'window.opencomuneNuovaEsperienzaMapsReady = function () { window.opencomuneNuovaEsperienzaMapsLoaded = true; document.dispatchEvent(new Event("opencomune-nuova-esperienza-maps-ready")); };',
    'before'
);

opencomune_enqueue_esperienza_location_autocomplete_assets();

// Aggiungi ajaxurl al frontend
wp_localize_script('select2', 'select2_params', array(
    'ajaxurl' => admin_url('admin-ajax.php')
));

if (!is_user_logged_in() || !opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$form_errors = [];
$GLOBALS['opencomune_esperienza_repopulate_post_fields'] = false;

$experience_id = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['experience_id'])) {
    $experience_id = (int) $_POST['experience_id'];
} elseif (isset($_GET['id'])) {
    $experience_id = (int) $_GET['id'];
}
$esperienza_form_post_id = 0;
if ($experience_id > 0) {
    $experience_post = get_post($experience_id);
    if (
        $experience_post
        && $experience_post->post_type === 'esperienze'
        && opencomune_user_can_edit_esperienza($experience_id)
    ) {
        $esperienza_form_post_id = $experience_id;
    } else {
        $experience_id = 0;
    }
}

// Gestione del salvataggio del form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tour'])) {
    $GLOBALS['opencomune_esperienza_repopulate_post_fields'] = true;
    error_log('=== INIZIO SALVATAGGIO ESPERIENZA ===');
    
    // Sanitizzazione dei dati
    $tour_title = sanitize_text_field($_POST['tour_title'] ?? '');
    $tour_excerpt = sanitize_textarea_field($_POST['tour_excerpt'] ?? '');
    $tour_description = wp_kses_post($_POST['tour_description'] ?? '');
    $duration_parts = opencomune_tour_duration_parts_from_post($_POST);
    $tour_difficulty = sanitize_text_field($_POST['tour_difficulty'] ?? '');
    $tour_languages = sanitize_text_field($_POST['tour_languages'] ?? '');
    $tour_whats_included = wp_kses_post($_POST['tour_whats_included'] ?? '');
    $tour_whats_not_included = wp_kses_post($_POST['tour_whats_not_included'] ?? '');
    $tour_requirements = wp_kses_post($_POST['tour_requirements'] ?? '');
    $tour_cancellation_policy = wp_kses_post($_POST['tour_cancellation_policy'] ?? '');
    $tour_highlights = wp_kses_post($_POST['tour_highlights'] ?? '');
    $tour_itinerary = wp_kses_post($_POST['tour_itinerary'] ?? '');
    $tour_latitude = floatval($_POST['tour_latitude'] ?? 0);
    $tour_longitude = floatval($_POST['tour_longitude'] ?? 0);
    $citta = sanitize_text_field($_POST['citta'] ?? '');
    $tour_address = sanitize_text_field($_POST['tour_address'] ?? '');
    $tour_destination = esc_url_raw($_POST['tour_destination'] ?? '');
    $tour_categories = array_map('sanitize_text_field', $_POST['tour_categories'] ?? []);
    $tour_themes = array_map('sanitize_text_field', $_POST['tour_themes'] ?? []);
    $tour_inclusions = array_map('sanitize_text_field', $_POST['tour_inclusions'] ?? []);
    $tour_exclusions = array_map('sanitize_text_field', $_POST['tour_exclusions'] ?? []);
    $tour_before_booking_info = array_map('sanitize_text_field', $_POST['tour_before_booking_info'] ?? []);
    $tour_what_to_bring = wp_kses_post($_POST['tour_what_to_bring'] ?? '');
    
    $updating_id_on_save = (int) ($_POST['experience_id'] ?? 0);
    $featured_image_id = (int) ($_POST['featured_image_id'] ?? 0);
    if ($featured_image_id <= 0 && $updating_id_on_save > 0 && opencomune_user_can_edit_esperienza($updating_id_on_save)) {
        $existing_thumb = (int) get_post_thumbnail_id($updating_id_on_save);
        if ($existing_thumb > 0) {
            $featured_image_id = $existing_thumb;
        }
    }

    if ($tour_title === '') {
        $form_errors[] = __('Il titolo è obbligatorio.', 'opencomune');
    }
    if (trim(wp_strip_all_tags($tour_description)) === '') {
        $form_errors[] = __('La descrizione completa è obbligatoria.', 'opencomune');
    }
    if (opencomune_tour_duration_parts_total_minutes($duration_parts) <= 0) {
        $form_errors[] = __('La durata è obbligatoria: indica almeno un valore tra giorni, ore e minuti.', 'opencomune');
    }
    if ($featured_image_id <= 0) {
        $form_errors[] = __('Seleziona un\'immagine principale nel tab Media.', 'opencomune');
    }
    $tour_categories = array_values(array_filter($tour_categories));
    if ($tour_categories === []) {
        $form_errors[] = __('Seleziona almeno una categoria nello step Categoria e tag.', 'opencomune');
    }
    $pricing_payload_raw = isset($_POST['tour_pricing_categories_payload'])
        ? wp_unslash((string) $_POST['tour_pricing_categories_payload'])
        : '';
    $pricing_preview = [];
    if ($pricing_payload_raw === '') {
        $form_errors[] = __('Configura almeno una categoria prezzo nel tab Prezzo (es. Adulti 22 €, Bambini 10 €).', 'opencomune');
    } else {
        $pricing_decoded = json_decode($pricing_payload_raw, true);
        $pricing_preview = opencomune_normalize_pricing_categories_list(is_array($pricing_decoded) ? $pricing_decoded : []);
        if (empty($pricing_preview)) {
            $form_errors[] = __('Aggiungi almeno una categoria prezzo con nome e importo.', 'opencomune');
        }
    }

    if (empty($form_errors)) {
        $updating_id = (int) ($_POST['experience_id'] ?? 0);
        $is_update = $updating_id > 0 && opencomune_user_can_edit_esperienza($updating_id);
        $existing_post = $is_update ? get_post($updating_id) : null;

        if ($is_update && $existing_post && $existing_post->post_type === 'esperienze') {
            $post_data = [
                'ID' => $updating_id,
                'post_title' => $tour_title,
                'post_excerpt' => $tour_excerpt,
                'post_content' => $tour_description,
                'post_status' => $existing_post->post_status,
                'post_type' => 'esperienze',
                'post_name' => sanitize_title($tour_title),
            ];
            $post_id = wp_update_post($post_data);
        } else {
            $post_data = [
                'post_title' => $tour_title,
                'post_excerpt' => $tour_excerpt,
                'post_content' => $tour_description,
                'post_status' => 'draft',
                'post_type' => 'esperienze',
                'post_author' => get_current_user_id(),
                'post_name' => sanitize_title($tour_title),
            ];
            $post_id = wp_insert_post($post_data);
            $is_update = false;
        }

        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'descrizione_breve', $tour_excerpt);
            // Salvataggio dei meta fields
            opencomune_save_tour_duration_parts($post_id, $duration_parts);
            opencomune_save_esperienza_capacity($post_id, $_POST);
            update_post_meta($post_id, 'tour_difficulty', $tour_difficulty);
            update_post_meta($post_id, 'tour_languages', $tour_languages);
            update_post_meta($post_id, 'tour_whats_included', $tour_whats_included);
            update_post_meta($post_id, 'tour_whats_not_included', $tour_whats_not_included);
            update_post_meta($post_id, 'tour_requirements', $tour_requirements);
            update_post_meta($post_id, 'tour_cancellation_policy', $tour_cancellation_policy);
            update_post_meta($post_id, 'tour_highlights', $tour_highlights);
            update_post_meta($post_id, 'tour_itinerary', $tour_itinerary);
            update_post_meta($post_id, 'tour_latitude', $tour_latitude);
            update_post_meta($post_id, 'tour_longitude', $tour_longitude);
            update_post_meta($post_id, 'tour_address', $tour_address);
            if ($citta !== '') {
                update_post_meta($post_id, 'citta', $citta);
            }
            if ($tour_destination !== '') {
                update_post_meta($post_id, 'tour_destination', $tour_destination);
            }
            
            opencomune_save_esperienza_categories($post_id, $tour_categories);
            opencomune_save_esperienza_themes($post_id, $tour_themes);
            opencomune_save_esperienza_inclusions($post_id, $tour_inclusions);
            opencomune_save_esperienza_exclusions($post_id, $tour_exclusions);
            opencomune_save_esperienza_before_booking_info($post_id, $tour_before_booking_info);
            opencomune_save_esperienza_age_limit($post_id, $_POST);
            opencomune_save_esperienza_language_options($post_id, $_POST);
            opencomune_save_esperienza_booking_schedule_type($post_id, $_POST['tour_booking_schedule'] ?? 'datetime');
            opencomune_save_esperienza_operating_hours($post_id, $_POST);
            opencomune_save_esperienza_start_times($post_id, $_POST);
            opencomune_save_esperienza_pricing_categories($post_id, $_POST);
            opencomune_save_esperienza_meeting_pickup($post_id, $_POST);
            opencomune_save_esperienza_itinerary_steps($post_id, $_POST);
            opencomune_save_esperienza_experience_settings($post_id, $_POST);
            opencomune_save_esperienza_booking_channel_settings($post_id, $_POST);
            opencomune_save_esperienza_ticket_settings($post_id, $_POST);
            opencomune_save_esperienza_identification($post_id, $_POST);
            update_post_meta($post_id, 'tour_what_to_bring', $tour_what_to_bring);
            
            if ($featured_image_id > 0) {
                set_post_thumbnail($post_id, $featured_image_id);
            } elseif ($is_update) {
                delete_post_thumbnail($post_id);
            }

            // Gestione della galleria fotografica
            if (array_key_exists('gallery_ids', $_POST)) {
                $gallery_raw = sanitize_text_field(wp_unslash((string) $_POST['gallery_ids']));
                if ($gallery_raw === '') {
                    delete_post_meta($post_id, 'galleria');
                } else {
                    $gallery_ids = array_values(array_filter(array_map('intval', explode(',', $gallery_raw))));
                    if (!empty($gallery_ids)) {
                        update_post_meta($post_id, 'galleria', $gallery_ids);
                    }
                }
            }

            opencomune_save_esperienza_allegati($post_id, $_POST['allegati_ids'] ?? '');
            opencomune_save_esperienza_availability_rules($post_id, $_POST);
            opencomune_merge_legacy_identification_keywords_into_themes($post_id);
            opencomune_finalize_esperienza_form_meta($post_id);

            $experience_id = (int) $post_id;
            $esperienza_form_post_id = $experience_id;

            if (!empty($_POST['redirect_to_dashboard'])) {
                wp_safe_redirect(opencomune_get_dashboard_url());
                exit;
            }

            $success = true;
            $GLOBALS['opencomune_esperienza_repopulate_post_fields'] = false;
            $dashboard_url = esc_url(opencomune_get_dashboard_url());
            $calendar_url = esc_url(add_query_arg(['id' => $post_id, 'exp_tab' => 'availability'], home_url('/nuova-esperienza/')));

            if ($is_update) {
                $success_message = sprintf(
                    __('L\'esperienza "%s" è stata aggiornata con successo.', 'opencomune'),
                    $tour_title
                );
                echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                title: ' . json_encode(__('Esperienza aggiornata', 'opencomune')) . ',
                text: ' . json_encode($success_message) . ',
                icon: "success",
                confirmButtonText: ' . json_encode(__('Continua modifica', 'opencomune')) . '
            });
        });
    </script>';
            } else {
                $success_message = sprintf(
                    __('L\'esperienza "%s" è stata creata con successo.', 'opencomune'),
                    $tour_title
                );
                echo '<script>
        document.addEventListener("DOMContentLoaded", function() {
            Swal.fire({
                title: ' . json_encode(__('Esperienza creata', 'opencomune')) . ',
                text: ' . json_encode($success_message) . ',
                icon: "success",
                showDenyButton: true,
                confirmButtonText: ' . json_encode(__('Configura calendario', 'opencomune')) . ',
                denyButtonText: ' . json_encode(__('Vai alla Dashboard', 'opencomune')) . ',
                showCancelButton: true,
                cancelButtonText: ' . json_encode(__('Crea altra esperienza', 'opencomune')) . '
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = ' . json_encode($calendar_url) . ';
                } else if (result.isDenied) {
                    window.location.href = ' . json_encode($dashboard_url) . ';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    window.location.href = ' . json_encode(home_url('/nuova-esperienza/')) . ';
                }
            });
        });
    </script>';
            }
        } else {
            $form_errors[] = __('Errore durante la creazione dell\'esperienza. Riprova.', 'opencomune');
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

opencomune_enqueue_dashboard_layout_assets();
opencomune_enqueue_select2();

$wizard_title = '';
$wizard_preview_url = '';
$wizard_featured_image_id = 0;
$wizard_featured_image_url = '';
$wizard_gallery_ids = [];
$wizard_initial_step = $esperienza_form_post_id > 0 ? 'summary' : 'base';

if ($esperienza_form_post_id > 0) {
    $wizard_title = get_the_title($esperienza_form_post_id);
    $wizard_preview_url = get_permalink($esperienza_form_post_id);
    $wizard_featured_image_id = (int) get_post_thumbnail_id($esperienza_form_post_id);
    if ($wizard_featured_image_id > 0) {
        $wizard_featured_image_url = (string) get_the_post_thumbnail_url($esperienza_form_post_id, 'thumbnail');
    }
    $wizard_gallery_meta = get_post_meta($esperienza_form_post_id, 'galleria', true);
    $wizard_gallery_ids = is_array($wizard_gallery_meta) ? array_map('intval', $wizard_gallery_meta) : [];
    $wizard_gallery_ids = array_values(array_filter($wizard_gallery_ids));
}

opencomune_enqueue_esperienza_wizard_assets([
    'experienceId' => $esperienza_form_post_id,
    'defaultTitle' => __('Nuova esperienza', 'opencomune'),
    'strings' => [
        'finish' => __('Fine e torna alla dashboard', 'opencomune'),
    ],
]);

opencomune_render_dashboard_shell_open();
?>

<style>
.form-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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

.oc-exp-form-tabs__nav {
    display: flex;
    flex-wrap: wrap;
    gap: 0.35rem;
    margin-bottom: 1.25rem;
    padding: 0.5rem;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08);
}

.oc-exp-form-tabs__nav button {
    border: none;
    background: transparent;
    padding: 0.5rem 0.75rem;
    border-radius: 8px;
    font-size: 0.8125rem;
    font-weight: 600;
    color: #4b5563;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    transition: background 0.15s ease, color 0.15s ease;
}

.oc-exp-form-tabs__nav button:hover {
    background: #f3f4f6;
}

.oc-exp-form-tabs__nav button.is-active {
    background: #2563eb;
    color: #fff;
}

.oc-exp-form-tabs__panel {
    display: none;
}

.oc-exp-form-tabs__panel.is-active {
    display: block;
}

.oc-exp-wizard .oc-exp-form-tabs__panel .form-section {
    width: 100%;
    max-width: none;
    margin-top: 0 !important;
}

.oc-exp-base-fields {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
    max-width: none;
}

.oc-exp-base-fields .opencomune-italian-only-fields,
.oc-exp-base-fields .opencomune-bilingual-field {
    width: 100%;
    max-width: none;
}

.oc-exp-base-fields .form-input,
.oc-exp-base-fields .form-textarea {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
}

.oc-exp-base-fields .wp-editor-wrap {
    width: 100%;
    max-width: none;
    box-sizing: border-box;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
    overflow: hidden;
}

.oc-exp-base-fields #wp-tour_description-wrap .wp-editor-container {
    min-height: 480px;
}

.oc-exp-base-fields__stack {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
    width: 100%;
}

.oc-exp-base-fields__hint {
    margin: 0;
    padding-top: 0.25rem;
    border-top: 1px solid #e5e7eb;
}

.oc-exp-form-tabs__panel .form-section + .form-section {
    margin-top: 1rem !important;
}

.oc-exp-duration-fields__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.oc-exp-duration-fields__lead {
    color: #6b7280;
    font-size: 0.9375rem;
    margin: 0 0 1.5rem;
    max-width: 42rem;
}

.oc-exp-duration-fields__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
    width: 100%;
    max-width: none;
}

.oc-exp-duration-fields__item {
    text-align: center;
}

.oc-exp-duration-fields__input {
    text-align: center;
    font-size: 1.125rem;
    font-weight: 600;
}

.oc-exp-duration-fields__label {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    font-style: italic;
    color: #6b7280;
}

.oc-exp-category-tags__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.oc-exp-category-tags__lead {
    color: #6b7280;
    font-size: 0.9375rem;
    margin: 0 0 1.5rem;
    max-width: 42rem;
}

.oc-exp-category-tags__field + .oc-exp-category-tags__field {
    margin-top: 1.5rem;
}

.oc-exp-category-tags__hint {
    margin: 0 0 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.oc-exp-category-tags__select {
    appearance: auto;
    background-color: #fff;
}

.oc-exp-category-tags .select2-container {
    width: 100% !important;
}

.oc-exp-category-tags .select2-container--default .select2-selection--multiple,
.oc-exp-inclusion .select2-container--default .select2-selection--multiple,
.oc-exp-exclusion .select2-container--default .select2-selection--multiple,
.oc-exp-before-start .select2-container--default .select2-selection--multiple {
    min-height: 46px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    padding: 4px 8px;
}

.oc-exp-inclusion__title {
    font-size: 1.25rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
}

.oc-exp-inclusion__subtitle {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 1rem;
}

.oc-exp-inclusion__field + .oc-exp-inclusion__field {
    margin-top: 1.75rem;
}

.oc-exp-inclusion__hint {
    margin: 0 0 0.5rem;
    font-size: 0.875rem;
    color: #6b7280;
}

.oc-exp-inclusion .select2-container,
.oc-exp-exclusion .select2-container,
.oc-exp-before-start .select2-container {
    width: 100% !important;
}

.oc-exp-exclusion__lead {
    margin-bottom: 1.25rem;
}

.oc-exp-inclusion__toggles {
    list-style: none;
    margin: 0;
    padding: 0;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
}

.oc-exp-inclusion__toggle-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}

.oc-exp-inclusion__toggle-row:last-child {
    border-bottom: 0;
}

.oc-exp-inclusion__toggle-label {
    font-size: 0.9375rem;
    color: #111827;
}

.oc-exp-switch {
    position: relative;
    display: inline-flex;
    flex-shrink: 0;
    cursor: pointer;
}

.oc-exp-switch input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

.oc-exp-switch__track {
    display: block;
    width: 44px;
    height: 26px;
    background: #d1d5db;
    border-radius: 9999px;
    transition: background 0.2s ease;
}

.oc-exp-switch__track::after {
    content: '';
    position: absolute;
    top: 3px;
    left: 3px;
    width: 20px;
    height: 20px;
    background: #fff;
    border-radius: 50%;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s ease;
}

.oc-exp-switch input:checked + .oc-exp-switch__track {
    background: #2563eb;
}

.oc-exp-switch input:checked + .oc-exp-switch__track::after {
    transform: translateX(18px);
}

.oc-exp-switch input:focus-visible + .oc-exp-switch__track {
    outline: 2px solid #2563eb;
    outline-offset: 2px;
}

.oc-exp-booking-schedule__lead {
    margin-bottom: 1.25rem;
}

.oc-exp-booking-schedule__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.oc-exp-booking-schedule__card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.oc-exp-booking-schedule__card.is-selected {
    border-color: #111827;
    box-shadow: 0 0 0 1px #111827;
}

.oc-exp-booking-schedule__card-title {
    display: block;
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.35rem;
}

.oc-exp-booking-schedule__card-desc {
    margin: 0;
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.45;
}

.oc-exp-booking-schedule__switch {
    pointer-events: auto;
}

.oc-exp-operating-hours__lead {
    margin-bottom: 1.25rem;
}

.oc-exp-operating-hours__enable-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 0 1.25rem;
    border-bottom: 1px solid #e5e7eb;
    margin-bottom: 1.25rem;
}

.oc-exp-operating-hours__enable-title {
    display: block;
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.oc-exp-oh-config.is-hidden {
    display: none;
}

.oc-exp-oh-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    margin-bottom: 1rem;
    overflow: hidden;
}

.oc-exp-oh-card__title {
    font-size: 1rem;
    font-weight: 600;
    margin: 0 0 0.5rem;
}

.oc-exp-oh-card > .oc-exp-inclusion__hint,
.oc-exp-oh-card > .oc-exp-oh-card__title {
    padding: 1rem 1.25rem 0;
}

.oc-exp-oh-card__body {
    padding: 0.75rem 1.25rem 1.25rem;
}

.oc-exp-oh-days {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.oc-exp-oh-day {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.oc-exp-oh-day__label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    color: #111827;
    cursor: pointer;
}

.oc-exp-oh-day__times {
    display: none;
    align-items: center;
    gap: 0.5rem;
}

.oc-exp-oh-day.is-active .oc-exp-oh-day__times {
    display: flex;
}

.oc-exp-oh-day__times .form-input {
    width: auto;
    min-width: 7rem;
}

.oc-exp-oh-day__sep {
    color: #6b7280;
}

.oc-exp-oh-seasonal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1rem 1.25rem 0;
}

.oc-exp-oh-seasonal__meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
    gap: 1rem;
    padding: 1rem 1.25rem 0;
}

.oc-exp-oh-add-seasonal {
    margin-top: 0.5rem;
}

.oc-exp-capacity__lead {
    margin-bottom: 1.25rem;
}

.oc-exp-capacity__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.oc-exp-capacity__card {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 1.25rem 1.5rem;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.oc-exp-capacity__card.is-selected {
    border-color: #111827;
    box-shadow: 0 0 0 1px #111827;
}

.oc-exp-capacity__radio {
    flex-shrink: 0;
    width: 1.125rem;
    height: 1.125rem;
    margin-top: 0.15rem;
    accent-color: #111827;
}

.oc-exp-capacity__card-text {
    display: flex;
    flex-direction: column;
    gap: 0.35rem;
}

.oc-exp-capacity__card-title {
    font-size: 1rem;
    font-weight: 600;
    color: #111827;
}

.oc-exp-capacity__card-desc {
    font-size: 0.875rem;
    color: #6b7280;
    line-height: 1.45;
}

.oc-exp-capacity__limited {
    margin-top: 1.5rem;
    max-width: 16rem;
}

.oc-exp-capacity__limited.is-hidden {
    display: none;
}

.oc-exp-start-times__lead {
    margin-bottom: 1.25rem;
}

.oc-exp-start-times__table-wrap {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    margin-bottom: 1rem;
}

.oc-exp-start-times__table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.oc-exp-start-times__table th,
.oc-exp-start-times__table td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

.oc-exp-start-times__table th {
    background: #f9fafb;
    font-weight: 600;
    color: #374151;
}

.oc-exp-start-times__th-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    margin-left: 0.25rem;
    background: #111827;
    color: #fff;
    font-size: 0.65rem;
    border-radius: 2px;
    vertical-align: middle;
}

.oc-exp-start-times__empty {
    margin: 0;
    padding: 1.25rem;
    text-align: center;
    color: #6b7280;
    background: #f9fafb;
}

.oc-exp-start-times__empty.is-hidden {
    display: none;
}

.oc-exp-start-times__add-btn {
    display: block;
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #111827;
    border-radius: 9999px;
    background: #fff;
    font-weight: 500;
    cursor: pointer;
}

.oc-exp-start-times__add-btn:hover {
    background: #f9fafb;
}

.oc-exp-start-times-modal {
    position: fixed;
    inset: 0;
    z-index: 100060;
    display: grid;
    place-items: center;
    padding: 1rem;
    box-sizing: border-box;
}

.oc-exp-start-times-modal[hidden] {
    display: none !important;
}

.oc-exp-start-times-modal__backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.45);
    grid-area: 1 / 1;
}

.oc-exp-start-times-modal__dialog {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 32rem;
    max-height: min(90vh, calc(100dvh - 2rem));
    overflow: auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    grid-area: 1 / 1;
    margin: auto;
}

.oc-exp-start-times-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid #e5e7eb;
}

.oc-exp-start-times-modal__title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
}

.oc-exp-start-times-modal__close {
    border: 0;
    background: transparent;
    font-size: 1.5rem;
    line-height: 1;
    cursor: pointer;
    color: #6b7280;
}

.oc-exp-start-times-modal__body {
    padding: 1.25rem 1.5rem;
}

.oc-exp-start-times-modal__time-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    margin-bottom: 1.25rem;
}

.oc-exp-start-times-modal__clock {
    font-size: 1.125rem;
    color: #6b7280;
}

.oc-exp-start-times-modal__time-sep {
    font-size: 1.25rem;
    font-weight: 700;
    color: #374151;
    line-height: 1;
    padding: 0 0.15rem;
}

.oc-exp-start-times-modal__time-row .form-input {
    flex: 1;
    min-width: 0;
}

.oc-exp-start-times-modal__field {
    margin-bottom: 1.25rem;
}

.oc-exp-start-times-modal__duration {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.oc-exp-start-times-modal__unit {
    display: block;
    font-size: 0.8125rem;
    font-style: italic;
    color: #6b7280;
    margin-top: 0.25rem;
}

.oc-exp-start-times-modal__counter {
    display: block;
    font-size: 0.75rem;
    color: #6b7280;
    margin-top: 0.25rem;
}

.oc-exp-start-times-modal__advanced summary {
    cursor: pointer;
    font-weight: 500;
    color: #111827;
}

.oc-exp-start-times-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
    padding: 1rem 1.5rem 1.25rem;
    border-top: 1px solid #e5e7eb;
}

body.oc-exp-start-times-modal-open {
    overflow: hidden;
}

.oc-exp-category-tags .select2-container--default.select2-container--focus .select2-selection--multiple {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.oc-exp-category-tags .select2-container--default .select2-selection--multiple .select2-selection__choice {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1e40af;
    border-radius: 9999px;
    padding: 2px 8px;
    font-size: 0.8125rem;
}

.oc-exp-location-fields__title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1f2937;
    margin: 0 0 0.5rem;
}

.oc-exp-location-fields__lead {
    color: #6b7280;
    font-size: 0.9375rem;
    margin: 0 0 1.5rem;
    max-width: 42rem;
}

.oc-exp-location-fields__label-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    margin-bottom: 0.5rem;
}

.oc-exp-location-fields__google {
    font-size: 0.75rem;
    color: #9ca3af;
}

.oc-exp-location-fields__input-wrap {
    position: relative;
}

.oc-exp-location-fields__input {
    padding-right: 2.75rem;
}

.oc-exp-location-fields__clear {
    position: absolute;
    right: 0.5rem;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #6b7280;
    cursor: pointer;
    padding: 0.25rem;
    line-height: 1;
}

.oc-exp-location-fields__status {
    margin: 0.5rem 0 0;
    font-size: 0.875rem;
    color: #059669;
}

.oc-exp-location-fields__status.is-error {
    color: #dc2626;
}

.pac-container {
    z-index: 100100 !important;
}

.oc-exp-form-footer {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 1rem;
    margin-top: 2rem;
    padding-bottom: 2rem;
}
</style>

<div class="oc-exp-wizard">
    <?php
    get_template_part('template-parts/esperienza-form-wizard-sidebar', null, [
        'post_id' => $esperienza_form_post_id,
        'title' => $wizard_title,
        'preview_url' => $wizard_preview_url,
    ]);
    ?>
    <div class="oc-exp-wizard__main">
        <?php if (isset($success_message)) : ?>
            <div class="success-message" style="margin:1rem 2rem 0;">
                <i class="bi bi-check-circle mr-2"></i>
                <?php echo esc_html($success_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" id="nuova-esperienza-form" class="oc-exp-wizard__form">
            <?php if ($esperienza_form_post_id > 0) : ?>
                <input type="hidden" name="experience_id" value="<?php echo (int) $esperienza_form_post_id; ?>">
            <?php endif; ?>
            <input type="hidden" name="redirect_to_dashboard" id="ocExpWizardRedirectDashboard" value="0">

            <div class="oc-exp-wizard__content">
                <h2 class="oc-exp-wizard__step-heading" id="ocExpWizardStepHeading"<?php echo $wizard_initial_step === 'summary' ? ' hidden' : ''; ?>>
                    <?php
                    echo $wizard_initial_step === 'summary'
                        ? esc_html__('Riepilogo', 'opencomune')
                        : esc_html__('Informazioni principali', 'opencomune');
                    ?>
                </h2>

            <div class="oc-exp-form-tabs" id="nuovaEsperienzaTabs">
                <div class="oc-exp-form-tabs__nav" role="tablist" aria-label="<?php esc_attr_e('Sezioni esperienza', 'opencomune'); ?>" hidden>
                    <button type="button" class="is-active" role="tab" id="tab-base" aria-selected="true" aria-controls="panel-base" data-exp-tab="base">
                        <i class="bi bi-info-circle" aria-hidden="true"></i>
                        <?php esc_html_e('Informazioni', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-duration" aria-selected="false" aria-controls="panel-duration" data-exp-tab="duration">
                        <i class="bi bi-clock" aria-hidden="true"></i>
                        <?php esc_html_e('Durata', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-booking-schedule" aria-selected="false" aria-controls="panel-booking-schedule" data-exp-tab="booking-schedule">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <?php esc_html_e('Orario/Data/Pass', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-operating-hours" aria-selected="false" aria-controls="panel-operating-hours" data-exp-tab="operating-hours">
                        <i class="bi bi-calendar-week" aria-hidden="true"></i>
                        <?php esc_html_e('Orario di avvio', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-start-times" aria-selected="false" aria-controls="panel-start-times" data-exp-tab="start-times">
                        <i class="bi bi-alarm" aria-hidden="true"></i>
                        <?php esc_html_e('Orario di inizio', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-capacity" aria-selected="false" aria-controls="panel-capacity" data-exp-tab="capacity">
                        <i class="bi bi-people" aria-hidden="true"></i>
                        <?php esc_html_e('Capacità', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-availability" aria-selected="false" aria-controls="panel-availability" data-exp-tab="availability">
                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                        <?php esc_html_e('Calendario disponibilità', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-category-tags" aria-selected="false" aria-controls="panel-category-tags" data-exp-tab="category-tags">
                        <i class="bi bi-tags" aria-hidden="true"></i>
                        <?php esc_html_e('Categoria e tag', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-inclusion" aria-selected="false" aria-controls="panel-inclusion" data-exp-tab="inclusion">
                        <i class="bi bi-check2-square" aria-hidden="true"></i>
                        <?php esc_html_e('Inclusione', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-exclusion" aria-selected="false" aria-controls="panel-exclusion" data-exp-tab="exclusion">
                        <i class="bi bi-x-square" aria-hidden="true"></i>
                        <?php esc_html_e('Esclusione', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-before-start" aria-selected="false" aria-controls="panel-before-start" data-exp-tab="before-start">
                        <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                        <?php esc_html_e('Prima di iniziare', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-what-to-bring" aria-selected="false" aria-controls="panel-what-to-bring" data-exp-tab="what-to-bring">
                        <i class="bi bi-bag" aria-hidden="true"></i>
                        <?php esc_html_e('Cosa portare', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-location" aria-selected="false" aria-controls="panel-location" data-exp-tab="location">
                        <i class="bi bi-geo" aria-hidden="true"></i>
                        <?php esc_html_e('Location', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-media" aria-selected="false" aria-controls="panel-media" data-exp-tab="media">
                        <i class="bi bi-images" aria-hidden="true"></i>
                        <?php esc_html_e('Media', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-details" aria-selected="false" aria-controls="panel-details" data-exp-tab="details">
                        <i class="bi bi-list-ul" aria-hidden="true"></i>
                        <?php esc_html_e('Dettagli', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-allegati" aria-selected="false" aria-controls="panel-allegati" data-exp-tab="allegati">
                        <i class="bi bi-paperclip" aria-hidden="true"></i>
                        <?php esc_html_e('Allegati', 'opencomune'); ?>
                    </button>
                    <button type="button" role="tab" id="tab-policies" aria-selected="false" aria-controls="panel-policies" data-exp-tab="policies">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <?php esc_html_e('Politiche', 'opencomune'); ?>
                    </button>
                </div>

                <div class="oc-exp-form-tabs__panels">
            <?php if ($esperienza_form_post_id > 0) : ?>
            <div class="oc-exp-form-tabs__panel<?php echo $wizard_initial_step === 'summary' ? ' is-active' : ''; ?>" id="panel-summary" role="tabpanel" aria-labelledby="tab-summary" data-exp-panel="summary"<?php echo $wizard_initial_step !== 'summary' ? ' hidden' : ''; ?>>
                <?php
                get_template_part('template-parts/esperienza-form-summary', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            <?php endif; ?>
            <div class="oc-exp-form-tabs__panel<?php echo $wizard_initial_step === 'base' ? ' is-active' : ''; ?>" id="panel-base" role="tabpanel" aria-labelledby="tab-base" data-exp-panel="base"<?php echo $wizard_initial_step !== 'base' ? ' hidden' : ''; ?>>
            <div class="form-section p-6">
                <div class="oc-exp-base-fields">
                    <?php
                    get_template_part('template-parts/esperienza-form-bilingual-base', null, [
                        'post_id' => $esperienza_form_post_id,
                        'italian_only' => true,
                    ]);
                    ?>
                 
                </div>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-duration" role="tabpanel" aria-labelledby="tab-duration" data-exp-panel="duration" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-duration-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-booking-schedule" role="tabpanel" aria-labelledby="tab-booking-schedule" data-exp-panel="booking-schedule" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-booking-schedule-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-operating-hours" role="tabpanel" aria-labelledby="tab-operating-hours" data-exp-panel="operating-hours" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-operating-hours-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-start-times" role="tabpanel" aria-labelledby="tab-start-times" data-exp-panel="start-times" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-start-times-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-capacity" role="tabpanel" aria-labelledby="tab-capacity" data-exp-panel="capacity" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-capacity-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-availability" role="tabpanel" aria-labelledby="tab-availability" data-exp-panel="availability" hidden>
            <?php if ($esperienza_form_post_id > 0) : ?>
                <?php
                get_template_part('template-parts/esperienza-form-availability-calendar', null, [
                    'post_id' => $esperienza_form_post_id,
                    'form_id' => 'nuova-esperienza-form',
                ]);
                ?>
            <?php else : ?>
                <div class="form-section p-6">
                    <h2 class="section-title">
                        <i class="bi bi-calendar3 mr-2"></i>
                        <?php esc_html_e('Calendario disponibilità', 'opencomune'); ?>
                    </h2>
                    <p class="text-sm text-gray-600">
                        <?php esc_html_e('Salva prima l\'esperienza (bozza). Dopo la creazione potrai aprire questo tab per definire le regole di disponibilità, oppure usare il pulsante "Configura calendario" nel messaggio di conferma.', 'opencomune'); ?>
                    </p>
                </div>
            <?php endif; ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-pricing" role="tabpanel" aria-labelledby="tab-pricing" data-exp-panel="pricing" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-pricing-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-category-tags" role="tabpanel" aria-labelledby="tab-category-tags" data-exp-panel="category-tags" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-category-tags-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                    'categorie' => $categorie,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-inclusion" role="tabpanel" aria-labelledby="tab-inclusion" data-exp-panel="inclusion" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-inclusion-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-exclusion" role="tabpanel" aria-labelledby="tab-exclusion" data-exp-panel="exclusion" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-exclusion-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-before-start" role="tabpanel" aria-labelledby="tab-before-start" data-exp-panel="before-start" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-before-start-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-what-to-bring" role="tabpanel" aria-labelledby="tab-what-to-bring" data-exp-panel="what-to-bring" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-what-to-bring-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-location" role="tabpanel" aria-labelledby="tab-location" data-exp-panel="location" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-location-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-media" role="tabpanel" aria-labelledby="tab-media" data-exp-panel="media" hidden>
            <div class="form-section p-6">
                <div class="grid grid-cols-1 gap-6">
                    <div>
                        <label class="form-label">
                            Immagine Principale <span class="form-required">*</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                            <button type="button" id="upload-featured-image" class="btn-primary">
                                Seleziona Immagine
                            </button>
                            <div id="featured-image-preview" class="mt-2<?php echo $wizard_featured_image_id > 0 ? '' : ' hidden'; ?>">
                                <img id="featured-image-preview-img" src="<?php echo esc_url($wizard_featured_image_url); ?>" alt="" class="w-24 h-24 object-cover rounded mx-auto">
                                <button type="button" id="remove-featured-image" class="text-red-600 text-sm mt-1">Rimuovi</button>
                            </div>
                            <input type="hidden" id="featured-image-id" name="featured_image_id" value="<?php echo $wizard_featured_image_id > 0 ? (int) $wizard_featured_image_id : ''; ?>">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Formati supportati: JPG, PNG, GIF. Max 5MB</p>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="form-label">
                        Galleria Fotografica
                    </label>
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <button type="button" id="upload-gallery-images" class="btn-secondary">
                            Seleziona Immagini
                        </button>
                        <div id="gallery-preview" class="oc-exp-gallery-preview mt-4<?php echo empty($wizard_gallery_ids) ? ' hidden' : ''; ?>">
                            <?php foreach ($wizard_gallery_ids as $gallery_id) :
                                opencomune_esperienza_wizard_gallery_preview_item($gallery_id);
                            endforeach; ?>
                        </div>
                        <input type="hidden" id="gallery-ids" name="gallery_ids" value="<?php echo esc_attr(implode(',', $wizard_gallery_ids)); ?>">
                    </div>
                    <p class="text-sm text-gray-500 mt-1">Seleziona più immagini per la galleria (JPG, PNG, GIF. Max 5MB ciascuna)</p>
                </div>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-details" role="tabpanel" aria-labelledby="tab-details" data-exp-panel="details" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-bilingual-details', null, [
                    'post_id' => $esperienza_form_post_id,
                    'italian_only' => true,
                    'skip_included' => true,
                    'skip_not_included' => true,
                    'skip_requirements' => true,
                ]);
                ?>
            </div>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-experience-settings" role="tabpanel" aria-labelledby="tab-experience-settings" data-exp-panel="experience-settings" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-experience-settings-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-booking-channel" role="tabpanel" aria-labelledby="tab-booking-channel" data-exp-panel="booking-channel" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-booking-channel-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-tickets" role="tabpanel" aria-labelledby="tab-tickets" data-exp-panel="tickets" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-tickets-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-experience-identification" role="tabpanel" aria-labelledby="tab-experience-identification" data-exp-panel="experience-identification" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-identification-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-pickup-arrival" role="tabpanel" aria-labelledby="tab-pickup-arrival" data-exp-panel="pickup-arrival" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-pickup-arrival-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-meeting-points" role="tabpanel" aria-labelledby="tab-meeting-points" data-exp-panel="meeting-points" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-meeting-points-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-itinerary" role="tabpanel" aria-labelledby="tab-itinerary" data-exp-panel="itinerary" hidden>
                <?php
                get_template_part('template-parts/esperienza-form-itinerary-steps-fields', null, [
                    'post_id' => $esperienza_form_post_id,
                ]);
                ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-allegati" role="tabpanel" aria-labelledby="tab-allegati" data-exp-panel="allegati" hidden>
            <?php
            get_template_part('template-parts/partner-esperienza-allegati-fields', null, [
                'post_id' => $esperienza_form_post_id,
            ]);
            ?>
            </div>

            <div class="oc-exp-form-tabs__panel" id="panel-policies" role="tabpanel" aria-labelledby="tab-policies" data-exp-panel="policies" hidden>
            <div class="form-section p-6">
                <?php
                get_template_part('template-parts/esperienza-form-bilingual-cancellation', null, [
                    'post_id' => $esperienza_form_post_id,
                    'italian_only' => true,
                ]);
                ?>
            </div>
            </div>

                </div>
            </div>
            </div>

            <div class="oc-exp-wizard__footer">
                <div class="oc-exp-wizard__footer-nav">
                    <button type="button" class="btn-secondary" id="ocExpWizardPrev">
                        <i class="bi bi-arrow-left mr-1"></i>
                        <?php esc_html_e('Indietro', 'opencomune'); ?>
                    </button>
                    <button type="button" class="btn-primary" id="ocExpWizardNext">
                        <?php esc_html_e('Avanti', 'opencomune'); ?>
                        <i class="bi bi-arrow-right ml-1"></i>
                    </button>
                </div>
                <div class="oc-exp-wizard__footer-actions">
                    <a href="<?php echo esc_url(opencomune_get_dashboard_url()); ?>" class="btn-secondary">
                        <?php esc_html_e('Esci', 'opencomune'); ?>
                    </a>
                    <button type="submit" name="save_tour" class="btn-secondary">
                        <i class="bi bi-save mr-1"></i>
                        <?php esc_html_e('Salva', 'opencomune'); ?>
                    </button>
                    <button type="submit" name="save_tour" class="btn-primary" onclick="document.getElementById('ocExpWizardRedirectDashboard').value='1';">
                        <i class="bi bi-check-circle mr-1"></i>
                        <?php esc_html_e('Salva e torna alla dashboard', 'opencomune'); ?>
                    </button>
                </div>
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
    function switchExperienceTab(tabId) {
        if (typeof window.opencomuneActivateExperienceStep === 'function') {
            window.opencomuneActivateExperienceStep(tabId);
        }
    }

    const form = document.getElementById('nuova-esperienza-form');
    const loadingOverlay = document.getElementById('loading-overlay');
    const serverFormErrors = <?php echo wp_json_encode(array_values($form_errors)); ?>;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function showFormErrorsModal(messages) {
        const items = (messages || []).filter(Boolean);
        if (!items.length || typeof Swal === 'undefined') {
            return;
        }

        const listHtml = '<ul style="text-align:left;margin:0.5rem 0 0;padding-left:1.25rem;">'
            + items.map(function (message) {
                return '<li style="margin:0.25rem 0;">' + escapeHtml(message) + '</li>';
            }).join('')
            + '</ul>';

        Swal.fire({
            title: 'Impossibile salvare',
            html: '<p style="margin:0;">Correggi i seguenti punti:</p>' + listHtml,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }

    if (!form) {
        return;
    }

    if (serverFormErrors.length) {
        showFormErrorsModal(serverFormErrors);
    }
    
    form.addEventListener('submit', function(e) {
        if (typeof window.opencomuneSyncPricingCategoriesPayload === 'function') {
            window.opencomuneSyncPricingCategoriesPayload();
        }
        if (typeof window.tinymce !== 'undefined') {
            window.tinymce.triggerSave();
        }

        const validationErrors = [];
        let focusTab = '';

        const titleField = document.getElementById('tour_title');
        if (!titleField || !titleField.value.trim()) {
            validationErrors.push('Il titolo è obbligatorio.');
            if (titleField) {
                titleField.style.borderColor = '#ef4444';
            }
            focusTab = focusTab || 'base';
        } else if (titleField) {
            titleField.style.borderColor = '#d1d5db';
        }

        function getRichTextPlain(fieldId) {
            if (typeof window.tinymce !== 'undefined') {
                const editor = window.tinymce.get(fieldId);
                if (editor && !editor.isHidden()) {
                    return (editor.getContent({ format: 'text' }) || '').trim();
                }
            }
            const field = document.getElementById(fieldId);
            return field ? String(field.value || '').trim() : '';
        }

        const descriptionPlain = getRichTextPlain('tour_description');
        const descriptionField = document.getElementById('tour_description');
        if (!descriptionPlain) {
            validationErrors.push('La descrizione completa è obbligatoria.');
            focusTab = focusTab || 'base';
        } else if (descriptionField) {
            descriptionField.style.borderColor = '#d1d5db';
        }

        const durationDays = parseInt(document.getElementById('tour_duration_days')?.value || '0', 10) || 0;
        const durationHours = parseInt(document.getElementById('tour_duration_hours')?.value || '0', 10) || 0;
        const durationMinutes = parseInt(document.getElementById('tour_duration_minutes')?.value || '0', 10) || 0;
        const durationTotalMinutes = (durationDays * 24 * 60) + (durationHours * 60) + durationMinutes;
        if (durationTotalMinutes <= 0) {
            validationErrors.push('La durata è obbligatoria: indica almeno un valore tra giorni, ore e minuti.');
            focusTab = focusTab || 'duration';
        }

        const featuredImageId = document.getElementById('featured-image-id')?.value || '';
        if (!featuredImageId) {
            validationErrors.push('Seleziona un\'immagine principale nel tab Media.');
            focusTab = focusTab || 'media';
        }

        function getMultiSelectValueCount(selectId) {
            const el = document.getElementById(selectId);
            if (!el) {
                return 0;
            }
            return Array.from(el.selectedOptions).filter(function (opt) {
                return String(opt.value || '').trim() !== '';
            }).length;
        }

        if (getMultiSelectValueCount('tour_categories') <= 0) {
            validationErrors.push('Seleziona almeno una categoria nello step Categoria e tag.');
            focusTab = focusTab || 'category-tags';
        }

        const firstInvalid = form.querySelector(':invalid');
        if (firstInvalid) {
            const panel = firstInvalid.closest('[data-exp-panel]');
            if (panel && !focusTab) {
                focusTab = panel.getAttribute('data-exp-panel') || '';
            }
        }

        if (validationErrors.length) {
            e.preventDefault();
            loadingOverlay.style.display = 'none';
            showFormErrorsModal(validationErrors);
            if (typeof window.opencomuneRefreshExperienceWizardStatus === 'function') {
                window.opencomuneRefreshExperienceWizardStatus();
            }
            if (switchExperienceTab && focusTab) {
                switchExperienceTab(focusTab);
            }
            return;
        }

        loadingOverlay.style.display = 'flex';
    });
    
    // Validazione file upload (ora gestito da WordPress Media Uploader)
    
    // Auto-save draft (opzionale)
    let autoSaveTimeout;
    const autoSaveFields = ['tour_title', 'tour_description', 'tour_duration_days', 'tour_duration_hours', 'tour_duration_minutes'];
    
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
            if (typeof window.opencomuneRefreshExperienceWizardStatus === 'function') {
                window.opencomuneRefreshExperienceWizardStatus();
            }
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
        if (typeof window.opencomuneRefreshExperienceWizardStatus === 'function') {
            window.opencomuneRefreshExperienceWizardStatus();
        }
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
            const currentIds = document.getElementById('gallery-ids').value
                ? document.getElementById('gallery-ids').value.split(',').filter(Boolean)
                : [];
            const newIds = attachments.map(function (att) { return String(att.id); });
            const allIds = Array.from(new Set(currentIds.concat(newIds)));
            document.getElementById('gallery-ids').value = allIds.join(',');

            const preview = document.getElementById('gallery-preview');
            const thumbMap = {};
            preview.querySelectorAll('.oc-exp-gallery-preview__item').forEach(function (item) {
                const id = item.dataset.id;
                const img = item.querySelector('.oc-exp-gallery-preview__img');
                if (id && img) {
                    thumbMap[id] = img.src;
                }
            });
            attachments.forEach(function (att) {
                thumbMap[String(att.id)] = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
            });
            opencomuneRenderWizardGalleryPreview(allIds, thumbMap);
        });
        
        galleryFrame.open();
        });
    }

    function opencomuneBuildWizardGalleryPreviewItem(id, thumbUrl) {
        const item = document.createElement('div');
        item.className = 'oc-exp-gallery-preview__item';
        item.dataset.id = String(id);
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'oc-exp-gallery-preview__remove';
        removeBtn.setAttribute('aria-label', 'Rimuovi immagine');
        removeBtn.innerHTML = '<i class="bi bi-trash3" aria-hidden="true"></i>';
        const img = document.createElement('img');
        img.src = thumbUrl || '';
        img.alt = '';
        img.className = 'oc-exp-gallery-preview__img';
        item.appendChild(removeBtn);
        item.appendChild(img);
        return item;
    }

    function opencomuneRenderWizardGalleryPreview(allIds, thumbMap) {
        const preview = document.getElementById('gallery-preview');
        if (!preview) {
            return;
        }
        preview.innerHTML = '';
        allIds.forEach(function (id) {
            preview.appendChild(opencomuneBuildWizardGalleryPreviewItem(id, thumbMap[id] || ''));
        });
        if (allIds.length) {
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
    }

    const galleryPreviewEl = document.getElementById('gallery-preview');
    if (galleryPreviewEl) {
        galleryPreviewEl.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.oc-exp-gallery-preview__remove');
            if (!removeBtn) {
                return;
            }
            e.preventDefault();
            const item = removeBtn.closest('.oc-exp-gallery-preview__item');
            if (!item || !item.dataset.id) {
                return;
            }
            const removeId = item.dataset.id;
            const currentIds = document.getElementById('gallery-ids').value
                ? document.getElementById('gallery-ids').value.split(',').filter(Boolean)
                : [];
            const nextIds = currentIds.filter(function (id) { return String(id) !== String(removeId); });
            document.getElementById('gallery-ids').value = nextIds.join(',');
            item.remove();
            if (!nextIds.length) {
                galleryPreviewEl.classList.add('hidden');
            }
        });
    }

    if (typeof window.opencomuneRefreshExperienceWizardStatus === 'function') {
        window.opencomuneRefreshExperienceWizardStatus();
    }
});
</script>

<?php opencomune_render_dashboard_shell_close(); ?>