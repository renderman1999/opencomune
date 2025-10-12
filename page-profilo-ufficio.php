<?php
/* Template Name: Profilo Ufficio */

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

get_header();

if (!is_user_logged_in() || !current_user_can('editor_turistico')) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">' . esc_html__('Accesso riservato all\'Ufficio Turistico. Effettua il login con un account autorizzato.', 'opencomune') . '</div>';
    get_footer();
    exit;
}

// Gestione del salvataggio del profilo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $user_id = get_current_user_id();
    
    // Sanitizzazione dei dati
    $display_name = sanitize_text_field($_POST['display_name'] ?? '');
    $user_email = sanitize_email($_POST['user_email'] ?? '');
    $user_phone = sanitize_text_field($_POST['user_phone'] ?? '');
    $user_bio = wp_kses_post($_POST['user_bio'] ?? '');
    $profile_image_id = intval($_POST['profile_image_id'] ?? 0);
    
    // Validazione
    if (empty($display_name)) {
        $error_message = 'Il nome è obbligatorio.';
    } elseif (empty($user_email)) {
        $error_message = 'L\'email è obbligatoria.';
    } elseif (!is_email($user_email)) {
        $error_message = 'L\'email non è valida.';
    } else {
        // Aggiornamento dati utente
        $user_data = array(
            'ID' => $user_id,
            'display_name' => $display_name,
            'user_email' => $user_email,
        );
        
        $result = wp_update_user($user_data);
        
        if (!is_wp_error($result)) {
            // Aggiornamento meta fields
            update_user_meta($user_id, 'user_phone', $user_phone);
            update_user_meta($user_id, 'user_bio', $user_bio);
            
            // Gestione immagine profilo
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

// Carica dati esistenti
$user_id = get_current_user_id();
$user = get_userdata($user_id);
$user_phone = get_user_meta($user_id, 'user_phone', true);
$user_bio = get_user_meta($user_id, 'user_bio', true);
$profile_image_id = get_user_meta($user_id, 'profile_image_id', true);
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
</style>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Profilo Ufficio Turistico</h1>
                    <p class="text-gray-600 mt-2">Gestisci le informazioni del tuo ufficio turistico</p>
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
        <form method="POST" id="profilo-ufficio-form" class="space-y-8">
            <!-- Sezione Informazioni Base -->
            <div class="form-section p-6">
                <h2 class="section-title">
                    <i class="bi bi-info-circle mr-2"></i>
                    Informazioni Base
                </h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="display_name" class="form-label">
                            Nome Ufficio <span class="form-required">*</span>
                        </label>
                        <input type="text" id="display_name" name="display_name" class="form-input" 
                               value="<?php echo esc_attr($user->display_name); ?>" required>
                    </div>
                    
                    <div>
                        <label for="user_email" class="form-label">
                            Email <span class="form-required">*</span>
                        </label>
                        <input type="email" id="user_email" name="user_email" class="form-input" 
                               value="<?php echo esc_attr($user->user_email); ?>" required>
                    </div>
                    
                    <div>
                        <label for="user_phone" class="form-label">
                            Telefono
                        </label>
                        <input type="tel" id="user_phone" name="user_phone" class="form-input" 
                               value="<?php echo esc_attr($user_phone); ?>">
                    </div>
                </div>
            </div>

            <!-- Sezione Immagine Profilo -->
            <div class="form-section p-6">
                <h2 class="section-title">
                    <i class="bi bi-camera mr-2"></i>
                    Immagine Profilo
                </h2>
                
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                    <button type="button" id="upload-profile-image" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                        Seleziona Immagine
                    </button>
                    <div id="profile-image-preview" class="mt-4 <?php echo $profile_image_id ? '' : 'hidden'; ?>">
                        <img id="profile-image-preview-img" 
                             src="<?php echo $profile_image_id ? wp_get_attachment_image_url($profile_image_id, 'medium') : ''; ?>" 
                             alt="Immagine Profilo" 
                             class="w-32 h-32 object-cover rounded-full mx-auto border-4 border-white shadow-lg">
                        <button type="button" id="remove-profile-image" class="text-red-600 text-sm mt-2 block">Rimuovi</button>
                    </div>
                    <input type="hidden" id="profile-image-id" name="profile_image_id" value="<?php echo esc_attr($profile_image_id); ?>">
                </div>
                <p class="text-sm text-gray-500 mt-2">Questa immagine apparirà nella home page del sito</p>
            </div>

            <!-- Sezione Biografia -->
            <div class="form-section p-6">
                <h2 class="section-title">
                    <i class="bi bi-file-text mr-2"></i>
                    Descrizione Ufficio
                </h2>
                
                <div>
                    <label for="user_bio" class="form-label">
                        Biografia
                    </label>
                    <textarea id="user_bio" name="user_bio" class="form-textarea" 
                              placeholder="Descrivi il tuo ufficio turistico..."><?php echo esc_textarea($user_bio); ?></textarea>
                    <p class="text-sm text-gray-500 mt-1">Una breve descrizione del tuo ufficio turistico</p>
                </div>
            </div>

            <!-- Pulsanti di Azione -->
            <div class="flex justify-end space-x-4 pb-8">
                <a href="<?php echo home_url('/dashboard-ufficio/'); ?>" class="btn-secondary">
                    <i class="bi bi-x-circle mr-2"></i>
                    Annulla
                </a>
                <button type="submit" name="save_profile" class="btn-primary">
                    <i class="bi bi-check-circle mr-2"></i>
                    Salva Profilo
                </button>
            </div>
        </form>
    </div>
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
    const form = document.getElementById('profilo-ufficio-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validazione lato client
            const requiredFields = ['display_name', 'user_email'];
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

<?php get_footer(); ?>