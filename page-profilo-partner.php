<?php
/* Template Name: Profilo Partner */

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

if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

// Gestione del salvataggio del profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $user_id = get_current_user_id();
    $partner_row = opencomune_get_partner_row_for_user($user_id);

    if ($partner_row) {
        if (!isset($_POST['nome_attivita']) && isset($_POST['display_name'])) {
            $_POST['nome_attivita'] = $_POST['display_name'];
        }
        if (!isset($_POST['descrizione_breve']) && isset($_POST['user_bio'])) {
            $_POST['descrizione_breve'] = $_POST['user_bio'];
        }
        if (!isset($_POST['telefono_attivita']) && isset($_POST['user_phone'])) {
            $_POST['telefono_attivita'] = $_POST['user_phone'];
        }

        $saved = opencomune_save_partner_self_service_profile($user_id, $_POST);
        if (is_wp_error($saved)) {
            $error_message = $saved->get_error_message();
        } else {
            $success = true;
            $success_message = __('Scheda attività e profilo aggiornati con successo.', 'opencomune');
        }
    } else {
        $display_name = sanitize_text_field($_POST['display_name'] ?? '');
        $user_email = sanitize_email($_POST['user_email'] ?? '');
        $user_phone = sanitize_text_field($_POST['user_phone'] ?? '');
        $user_bio = wp_kses_post($_POST['user_bio'] ?? '');
        $profile_image_id = intval($_POST['profile_image_id'] ?? 0);

        if ($display_name === '') {
            $error_message = 'Il nome è obbligatorio.';
        } elseif ($user_email === '') {
            $error_message = 'L\'email è obbligatoria.';
        } elseif (!is_email($user_email)) {
            $error_message = 'L\'email non è valida.';
        } else {
            $result = wp_update_user([
                'ID' => $user_id,
                'display_name' => $display_name,
                'user_email' => $user_email,
            ]);

            if (!is_wp_error($result)) {
                update_user_meta($user_id, 'user_phone', $user_phone);
                update_user_meta($user_id, 'user_bio', $user_bio);
                if ($profile_image_id > 0) {
                    update_user_meta($user_id, 'profile_image_id', $profile_image_id);
                } else {
                    delete_user_meta($user_id, 'profile_image_id');
                }
                $success = true;
                $success_message = 'Profilo aggiornato con successo.';
            } else {
                $error_message = 'Errore durante l\'aggiornamento del profilo.';
            }
        }
    }
}

// Carica dati esistenti
$user_id = get_current_user_id();
$user = get_userdata($user_id);
$partner_row = opencomune_get_partner_row_for_user($user_id);
$partner_tipologie = opencomune_partner_get_tipologie();
$public_profile_url = opencomune_get_partner_profile_url($user_id);
$user_phone = get_user_meta($user_id, 'user_phone', true);
$user_bio = get_user_meta($user_id, 'user_bio', true);
$profile_image_id = get_user_meta($user_id, 'profile_image_id', true);
$profile_image_id = ($profile_image_id && is_numeric($profile_image_id)) ? intval($profile_image_id) : 0;

if ($partner_row) {
    $user_phone = $partner_row->telefono_attivita ?: $user_phone;
    $user_bio = $partner_row->descrizione_breve ?: $user_bio;
}

opencomune_enqueue_dashboard_layout_assets();

if ($partner_row) {
    $api_key = opencomune_get_google_maps_api_key();
    if ($api_key !== '') {
        $theme_dir = get_template_directory();
        $theme_uri = get_template_directory_uri();
        $address_js = $theme_dir . '/assets/js/partner-profile-address.js';
        wp_enqueue_script(
            'opencomune-partner-profile-address',
            $theme_uri . '/assets/js/partner-profile-address.js',
            [],
            file_exists($address_js) ? (string) filemtime($address_js) : '1.0',
            true
        );
        wp_enqueue_script(
            'google-maps-partner-profile',
            'https://maps.googleapis.com/maps/api/js?key=' . rawurlencode($api_key) . '&libraries=places&callback=opencomunePartnerMapsReady',
            [],
            null,
            true
        );
        wp_add_inline_script(
            'google-maps-partner-profile',
            'window.opencomunePartnerMapsReady = function(){ window.opencomunePartnerMapsLoaded = true; document.dispatchEvent(new Event("opencomune-partner-maps-ready")); };',
            'before'
        );
    }
}

opencomune_render_dashboard_shell_open();
?>

<style>
.pac-container {
    z-index: 10000 !important;
}
</style>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main">
        <section class="oc-dashboard-section oc-profilo-partner-page p-6">
        <div class="mb-8">
            <h1 class="oc-dashboard-section__title text-2xl sm:text-3xl mb-2"><?php esc_html_e('Profilo partner', 'opencomune'); ?></h1>
            <p class="text-gray-600"><?php esc_html_e('Gestisci le informazioni della tua scheda attività pubblica.', 'opencomune'); ?></p>
            <?php if ($partner_row && $public_profile_url) : ?>
                <p class="mt-2">
                    <a href="<?php echo esc_url($public_profile_url); ?>" class="text-blue-700 hover:text-blue-900 font-medium" target="_blank" rel="noopener noreferrer">
                        <?php esc_html_e('Anteprima scheda pubblica', 'opencomune'); ?>
                        <i class="bi bi-box-arrow-up-right ml-1" aria-hidden="true"></i>
                    </a>
                </p>
            <?php endif; ?>
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
        <form method="POST" id="profilo-partner-form" class="oc-profilo-partner-form">
            <!-- Sezione Informazioni Base -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-info-circle mr-2"></i>
                    <?php echo $partner_row ? esc_html__('Scheda attività (pubblica)', 'opencomune') : esc_html__('Informazioni Base', 'opencomune'); ?>
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="display_name" class="form-label">
                            <?php esc_html_e('Nome attività', 'opencomune'); ?> <span class="form-required">*</span>
                        </label>
                        <input type="text" id="display_name" name="display_name" class="form-input"
                               value="<?php echo esc_attr($partner_row ? $partner_row->nome_attivita : $user->display_name); ?>" required>
                        <?php if ($partner_row) : ?>
                            <input type="hidden" name="nome_attivita" value="<?php echo esc_attr($partner_row->nome_attivita); ?>" id="nome_attivita_hidden">
                        <?php endif; ?>
                    </div>

                    <?php if ($partner_row) : ?>
                    <div>
                        <label for="tipologia_attivita" class="form-label"><?php esc_html_e('Tipologia', 'opencomune'); ?></label>
                        <select id="tipologia_attivita" name="tipologia_attivita" class="form-input">
                            <?php foreach ($partner_tipologie as $key => $label) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($partner_row->tipologia_attivita, $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="partita_iva" class="form-label"><?php esc_html_e('Partita IVA', 'opencomune'); ?> <span class="form-required">*</span></label>
                        <input type="text" id="partita_iva" name="partita_iva" class="form-input" required
                               value="<?php echo esc_attr($partner_row->partita_iva); ?>">
                    </div>
                    <div>
                        <label for="numero_rea" class="form-label"><?php esc_html_e('Numero REA', 'opencomune'); ?></label>
                        <input type="text" id="numero_rea" name="numero_rea" class="form-input"
                               value="<?php echo esc_attr($partner_row->numero_rea); ?>">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="user_email" class="form-label">
                            <?php echo $partner_row ? esc_html__('Email accesso account', 'opencomune') : esc_html__('Email', 'opencomune'); ?>
                            <span class="form-required">*</span>
                        </label>
                        <input type="email" id="user_email" name="user_email" class="form-input"
                               value="<?php echo esc_attr($partner_row && !empty($partner_row->email_login) ? $partner_row->email_login : $user->user_email); ?>" required
                               autocomplete="email">
                        <?php if ($partner_row) : ?>
                            <p class="text-sm text-gray-500 mt-1"><?php esc_html_e('Usata per accedere al portale: aggiorna qui se cambi indirizzo email.', 'opencomune'); ?></p>
                        <?php endif; ?>
                    </div>

                    <?php if ($partner_row) : ?>
                    <div>
                        <label for="email_ufficiale" class="form-label"><?php esc_html_e('Email ufficiale (scheda pubblica)', 'opencomune'); ?></label>
                        <input type="email" id="email_ufficiale" name="email_ufficiale" class="form-input"
                               value="<?php echo esc_attr($partner_row->email_ufficiale); ?>">
                    </div>
                    <?php endif; ?>

                    <div>
                        <label for="user_phone" class="form-label"><?php esc_html_e('Telefono attività', 'opencomune'); ?></label>
                        <input type="tel" id="user_phone" name="user_phone" class="form-input"
                               value="<?php echo esc_attr($user_phone); ?>">
                        <?php if ($partner_row) : ?>
                            <input type="hidden" name="telefono_attivita" id="telefono_attivita_hidden" value="<?php echo esc_attr($user_phone); ?>">
                        <?php endif; ?>
                    </div>

                    <?php if ($partner_row) : ?>
                    <div>
                        <label for="sito_web" class="form-label"><?php esc_html_e('Sito web', 'opencomune'); ?></label>
                        <input type="url" id="sito_web" name="sito_web" class="form-input" placeholder="https://"
                               value="<?php echo esc_attr($partner_row->sito_web); ?>">
                    </div>
                    <div>
                        <label for="social_media" class="form-label"><?php esc_html_e('Social / link aggiuntivo', 'opencomune'); ?></label>
                        <input type="url" id="social_media" name="social_media" class="form-input" placeholder="https://"
                               value="<?php echo esc_attr($partner_row->social_media); ?>">
                    </div>
                    <div>
                        <label for="nome_referente" class="form-label"><?php esc_html_e('Referente', 'opencomune'); ?></label>
                        <input type="text" id="nome_referente" name="nome_referente" class="form-input"
                               value="<?php echo esc_attr($partner_row->nome_referente); ?>">
                    </div>
                    <div>
                        <label for="ruolo_referente" class="form-label"><?php esc_html_e('Ruolo referente', 'opencomune'); ?></label>
                        <input type="text" id="ruolo_referente" name="ruolo_referente" class="form-input"
                               value="<?php echo esc_attr($partner_row->ruolo_referente); ?>">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($partner_row) : ?>
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-geo-alt mr-2"></i>
                    <?php esc_html_e('Sede e localizzazione', 'opencomune'); ?>
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label for="indirizzo" class="form-label"><?php esc_html_e('Indirizzo', 'opencomune'); ?></label>
                        <input type="text" id="indirizzo" name="indirizzo" class="form-input" autocomplete="off"
                               placeholder="<?php esc_attr_e('Inizia a digitare l\'indirizzo...', 'opencomune'); ?>"
                               value="<?php echo esc_attr($partner_row->indirizzo); ?>">
                        <p class="text-sm text-gray-500 mt-1"><?php esc_html_e('Seleziona un suggerimento da Google Maps: CAP, comune, area e coordinate verranno compilati automaticamente.', 'opencomune'); ?></p>
                        <p id="partner-indirizzo-geocode-status" class="text-sm mt-2" style="display:none;" aria-live="polite"></p>
                    </div>
                    <div>
                        <label for="cap" class="form-label"><?php esc_html_e('CAP', 'opencomune'); ?></label>
                        <input type="text" id="cap" name="cap" class="form-input" value="<?php echo esc_attr($partner_row->cap); ?>">
                    </div>
                    <div>
                        <label for="comune" class="form-label"><?php esc_html_e('Comune', 'opencomune'); ?></label>
                        <input type="text" id="comune" name="comune" class="form-input" value="<?php echo esc_attr($partner_row->comune); ?>">
                    </div>
                    <div>
                        <label for="area_quartiere" class="form-label"><?php esc_html_e('Area / quartiere', 'opencomune'); ?></label>
                        <input type="text" id="area_quartiere" name="area_quartiere" class="form-input" value="<?php echo esc_attr($partner_row->area_quartiere); ?>">
                    </div>
                    <div>
                        <label for="latitudine" class="form-label"><?php esc_html_e('Latitudine', 'opencomune'); ?></label>
                        <input type="text" id="latitudine" name="latitudine" class="form-input" inputmode="decimal"
                               value="<?php echo esc_attr($partner_row->latitudine); ?>">
                    </div>
                    <div>
                        <label for="longitudine" class="form-label"><?php esc_html_e('Longitudine', 'opencomune'); ?></label>
                        <input type="text" id="longitudine" name="longitudine" class="form-input" inputmode="decimal"
                               value="<?php echo esc_attr($partner_row->longitudine); ?>">
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Sezione Immagine Profilo -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-camera mr-2"></i>
                    Immagine Profilo
                </h2>
                
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                    <button type="button" id="upload-profile-image" class="btn-primary">
                        Seleziona Immagine
                    </button>
                    <div id="profile-image-preview" class="mt-4 <?php echo $profile_image_id > 0 ? '' : 'hidden'; ?>">
                        <img id="profile-image-preview-img" 
                             src="<?php echo $profile_image_id > 0 ? wp_get_attachment_image_url($profile_image_id, 'medium') : ''; ?>" 
                             alt="Immagine Profilo" 
                             class="w-32 h-32 object-cover rounded-full mx-auto border-4 border-white shadow-lg">
                        <button type="button" id="remove-profile-image" class="text-red-600 text-sm mt-2 block">Rimuovi</button>
                    </div>
                    <input type="hidden" id="profile-image-id" name="profile_image_id" value="<?php echo esc_attr($profile_image_id); ?>">
                </div>
                <?php if ($partner_row) : ?>
                <div class="mt-4">
                    <label for="logo_url" class="form-label"><?php esc_html_e('URL logo (alternativa al caricamento)', 'opencomune'); ?></label>
                    <input type="url" id="logo_url" name="logo_url" class="form-input" placeholder="https://"
                           value="<?php echo esc_attr($partner_row->logo_url); ?>">
                </div>
                <?php endif; ?>
                <p class="text-sm text-gray-500 mt-2"><?php esc_html_e('Logo mostrato sulla scheda pubblica del partner e nelle esperienze.', 'opencomune'); ?></p>
            </div>

            <!-- Sezione Biografia -->
            <div class="form-section">
                <h2 class="section-title">
                    <i class="bi bi-file-text mr-2"></i>
                    <?php esc_html_e('Descrizione attività', 'opencomune'); ?>
                </h2>

                <div class="space-y-4">
                    <div>
                        <label for="user_bio" class="form-label"><?php esc_html_e('Descrizione breve', 'opencomune'); ?></label>
                        <textarea id="user_bio" name="user_bio" class="form-textarea"
                                  placeholder="<?php esc_attr_e('Descrivi la tua attività in breve...', 'opencomune'); ?>"><?php echo esc_textarea($user_bio); ?></textarea>
                        <?php if ($partner_row) : ?>
                            <input type="hidden" name="descrizione_breve" id="descrizione_breve_hidden" value="">
                        <?php endif; ?>
                    </div>
                    <?php if ($partner_row) : ?>
                    <div>
                        <label for="descrizione_estesa" class="form-label"><?php esc_html_e('Descrizione estesa', 'opencomune'); ?></label>
                        <textarea id="descrizione_estesa" name="descrizione_estesa" class="form-textarea" rows="6"
                                  placeholder="<?php esc_attr_e('Testo completo visibile sulla scheda pubblica...', 'opencomune'); ?>"><?php echo esc_textarea($partner_row->descrizione_estesa); ?></textarea>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pulsanti di Azione -->
            <div class="oc-profilo-partner-actions">
                <a href="<?php echo opencomune_get_dashboard_url(); ?>" class="btn-secondary">
                    <i class="bi bi-x-circle mr-2"></i>
                    Annulla
                </a>
                <button type="submit" name="save_profile" class="btn-primary">
                    <i class="bi bi-check-circle mr-2"></i>
                    Salva Profilo
                </button>
            </div>
        </form>
        </section>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // WordPress Media Uploader per immagine profilo
    let profileImageFrame;
    const uploadProfileBtn = document.getElementById('upload-profile-image');
    if (uploadProfileBtn && typeof wp !== 'undefined' && wp.media) {
        uploadProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            if (profileImageFrame) {
                profileImageFrame.open();
                return;
            }
            
            profileImageFrame = wp.media({
                title: 'Seleziona Immagine Profilo',
                button: {
                    text: 'Usa questa immagine'
                },
                multiple: false
            });
            
            profileImageFrame.on('select', function() {
                const attachment = profileImageFrame.state().get('selection').first().toJSON();
                document.getElementById('profile-image-id').value = attachment.id;
                document.getElementById('profile-image-preview-img').src = attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url;
                document.getElementById('profile-image-preview').classList.remove('hidden');
            });
            
            profileImageFrame.open();
        });
    }
    
    // Rimuovi immagine profilo
    const removeProfileBtn = document.getElementById('remove-profile-image');
    if (removeProfileBtn) {
        removeProfileBtn.addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('profile-image-id').value = '';
            document.getElementById('profile-image-preview').classList.add('hidden');
        });
    }
    
    // Gestione del form
    const displayNameInput = document.getElementById('display_name');
    const nomeHidden = document.getElementById('nome_attivita_hidden');
    const bioInput = document.getElementById('user_bio');
    const descBreveHidden = document.getElementById('descrizione_breve_hidden');
    const phoneInput = document.getElementById('user_phone');
    const phoneHidden = document.getElementById('telefono_attivita_hidden');
    function syncPartnerHiddenFields() {
        if (nomeHidden && displayNameInput) {
            nomeHidden.value = displayNameInput.value;
        }
        if (descBreveHidden && bioInput) {
            descBreveHidden.value = bioInput.value;
        }
        if (phoneHidden && phoneInput) {
            phoneHidden.value = phoneInput.value;
        }
    }

    if (displayNameInput) {
        displayNameInput.addEventListener('input', syncPartnerHiddenFields);
    }
    if (bioInput) {
        bioInput.addEventListener('input', syncPartnerHiddenFields);
    }
    if (phoneInput) {
        phoneInput.addEventListener('input', syncPartnerHiddenFields);
    }
    syncPartnerHiddenFields();

    const form = document.getElementById('profilo-partner-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            syncPartnerHiddenFields();
            // Validazione lato client
            const requiredFields = ['display_name', 'user_email'];
            if (document.getElementById('partita_iva')) {
                requiredFields.push('partita_iva');
            }
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
                Swal.fire({
                    title: 'Campi Obbligatori',
                    text: 'Compila tutti i campi obbligatori',
                    icon: 'warning'
                });
                return;
            }
        });
    }
});
</script>

<?php opencomune_render_dashboard_shell_close(); ?>
