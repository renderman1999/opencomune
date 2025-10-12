<?php
/* Template Name: Profilo Ufficio */
get_header();
if (!is_user_logged_in() || !current_user_can('editor_turistico')) {
    echo '<div class="container mx-auto p-8 text-center text-red-600 font-bold">Accesso riservato agli editor turistici.</div>';
    get_footer();
    exit;
}
$current_user = wp_get_current_user();
$guida_post_id = get_user_meta($current_user->ID, 'guida_post_id', true);
// Gestione salvataggio form (come in dashboard)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guida_profile'])) {
    $lingue = isset($_POST['lingue']) ? array_map('sanitize_text_field', $_POST['lingue']) : [];
    $citta = isset($_POST['citta']) ? array_map('sanitize_text_field', $_POST['citta']) : [];
    $specializzazioni = isset($_POST['specializzazioni']) ? array_map('sanitize_text_field', $_POST['specializzazioni']) : [];
    update_post_meta($guida_post_id, '_lingue', $lingue);
    update_post_meta($guida_post_id, '_citta', $citta);
    update_post_meta($guida_post_id, '_specializzazioni', $specializzazioni);
    // Patentino guida turistica
    if (!empty($_FILES['patentino']['name'])) {
        $filetype = wp_check_filetype($_FILES['patentino']['name']);
        $allowed = ['pdf', 'jpg', 'jpeg'];
        if (in_array(strtolower($filetype['ext']), $allowed)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $uploaded = media_handle_upload('patentino', 0);
            if (!is_wp_error($uploaded)) {
                $patentino_url = wp_get_attachment_url($uploaded);
                update_post_meta($guida_post_id, '_patentino', $patentino_url);
                echo '<div class="bg-green-100 text-green-800 p-2 rounded mb-4">Patentino caricato!</div>';
            } else {
                $error_message = $uploaded->get_error_message();
                echo '<div class="bg-red-100 text-red-800 p-2 rounded mb-4">Errore upload patentino: ' . esc_html($error_message) . '</div>';
            }
        } else {
            echo '<div class="bg-red-100 text-red-800 p-2 rounded mb-4">Formato patentino non valido. Solo PDF/JPG/JPEG.</div>';
        }
    }
    // Altri certificati (può essere multiplo)
    if (!empty($_FILES['certificati']['name'][0])) {
        $cert_urls = [];
        foreach ($_FILES['certificati']['name'] as $idx => $name) {
            if (!empty($name)) {
                $filetype = wp_check_filetype($name);
                $allowed = ['pdf', 'jpg', 'jpeg'];
                if (in_array(strtolower($filetype['ext']), $allowed)) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $_FILES_single = [
                        'name' => $_FILES['certificati']['name'][$idx],
                        'type' => $_FILES['certificati']['type'][$idx],
                        'tmp_name' => $_FILES['certificati']['tmp_name'][$idx],
                        'error' => $_FILES['certificati']['error'][$idx],
                        'size' => $_FILES['certificati']['size'][$idx],
                    ];
                    $file_id = media_handle_sideload($_FILES_single, 0);
                    if (!is_wp_error($file_id)) {
                        $cert_urls[] = wp_get_attachment_url($file_id);
                    }
                }
            }
        }
        if (!empty($cert_urls)) {
            update_post_meta($guida_post_id, '_certificati', $cert_urls);
            echo '<div class="bg-green-100 text-green-800 p-2 rounded mb-4">Certificati caricati!</div>';
        }
    }
    echo '<div class="bg-green-100 text-green-800 p-2 rounded mb-4">Profilo aggiornato!</div>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guida_anagrafici'])) {
    $nome = sanitize_text_field($_POST['nome'] ?? '');
    $cognome = sanitize_text_field($_POST['cognome'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $bio = sanitize_textarea_field($_POST['bio'] ?? '');
    $telefono = sanitize_text_field($_POST['telefono'] ?? '');
    wp_update_user([
        'ID' => $current_user->ID,
        'first_name' => $nome,
        'last_name' => $cognome,
        'user_email' => $email,
    ]);
    wp_update_post([
        'ID' => $guida_post_id,
        'post_content' => $bio,
    ]);
    update_post_meta($guida_post_id, '_telefono', $telefono);
    echo '<div class="bg-green-100 text-green-800 p-2 rounded mb-4">Dati anagrafici aggiornati!</div>';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_guida_immagine'])) {
    // Immagine profilo
    if (!empty($_FILES['file']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $uploaded = media_handle_upload('file', 0);
        if (!is_wp_error($uploaded)) {
            set_post_thumbnail($guida_post_id, $uploaded);
            echo '<div class="bg-green-100 text-green-800 p-2 rounded mb-4">Immagine profilo aggiornata!</div>';
        } else {
            $error_message = $uploaded->get_error_message();
            echo '<div class="bg-red-100 text-red-800 p-2 rounded mb-4">Errore upload immagine: ' . esc_html($error_message) . '</div>';
        }
    }
}
// Recupera i dati attuali
$lingue_array = get_post_meta($guida_post_id, '_lingue', true);
if (!is_array($lingue_array)) {
    $lingue_array = array_filter(array_map('trim', explode(',', $lingue_array)));
}
$citta_array = get_post_meta($guida_post_id, '_citta', true);
if (!is_array($citta_array)) {
    $citta_array = array_filter(array_map('trim', explode(',', $citta_array)));
}
$specializzazioni_array = get_post_meta($guida_post_id, '_specializzazioni', true);
if (!is_array($specializzazioni_array)) {
    $specializzazioni_array = array_filter(array_map('trim', explode(',', $specializzazioni_array)));
}
$patentino = get_post_meta($guida_post_id, '_patentino', true);
$certificati = get_post_meta($guida_post_id, '_certificati', true);
$foto_profilo = get_the_post_thumbnail_url($guida_post_id, 'medium');
$bio = get_post_field('post_content', $guida_post_id);
$missing = [];
if (empty($patentino)) $missing[] = 'Patentino guida turistica';
if (empty($certificati)) $missing[] = 'Altri certificati';
if (empty($foto_profilo)) $missing[] = 'Foto profilo';
if (empty($bio)) $missing[] = 'Bio';
if (!empty($missing)) {
    echo '<div class="bg-yellow-100 text-yellow-800 rounded p-4 mb-6">';
    echo 'Per completare il tuo profilo, inserisci: <strong>' . implode(', ', $missing) . '</strong> nella sezione Profilo Guida.';
    echo '</div>';
}
global $wpdb;
$comuni = $wpdb->get_results("SELECT Descrizione FROM wpmyguide_comuni WHERE DataFineVal IS NULL OR DataFineVal = '' ORDER BY Descrizione ASC");
$specializzazioni_list = [
    'Arte e Musei', 'Archeologia', 'Storia locale', 'Architettura', 'Natura e Paesaggi',
    'Enogastronomia', 'Tradizioni popolari', 'Letteratura', 'Fotografia', 'Religione e spiritualità',
    'Tour per famiglie', 'Tour per bambini', 'Accessibilità', 'Tour in bicicletta', 'Trekking/Escursionismo',
    'Birdwatching', 'Tour in barca', 'Shopping e moda', 'Street Art', 'Tour esperienziali',
    'Tour musicali', 'Tour cinematografici', 'Tour scientifici', 'Tour LGBTQ+', 'Tour personalizzati'
];
?>
<div class="container mx-auto max-w-2xl px-2 py-8 w-full">
    <div class="bg-white rounded-xl shadow p-6">
        <h2 class="text-xl font-bold mb-4">Profilo Guida</h2>
        <div class="mb-6 flex border-b">
            <button id="tab-specifiche-btn" class="px-4 py-2 font-semibold border-b-2 border-blue-600 focus:outline-none">Specifiche</button>
            <button id="tab-anagrafici-btn" class="px-4 py-2 font-semibold border-b-2 border-transparent focus:outline-none">Anagrafica</button>
            <button id="tab-immagine-btn" class="px-4 py-2 font-semibold border-b-2 border-transparent focus:outline-none">Immagine</button>
        </div>
        <div id="tab-specifiche" class="tab-pane">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block font-medium mb-1">Lingue parlate</label>
                    <select name="lingue[]" id="lingue-parlate" multiple="multiple" class="w-full border rounded px-3 py-2">
                        <?php
                        $lingue_predefinite = ['Italiano','English','Français','Deutsch','Español','Português','Русский','中文','日本語','العربية'];
                        foreach ($lingue_predefinite as $lingua) {
                            echo '<option value="' . esc_attr($lingua) . '" ' . (in_array($lingua, $lingue_array) ? 'selected' : '') . '>' . esc_html($lingua) . '</option>';
                        }
                        // Opzioni custom/tag
                        foreach ($lingue_array as $val) {
                            if (!in_array($val, $lingue_predefinite) && $val !== '') {
                                echo '<option value="' . esc_attr($val) . '" selected>' . esc_html($val) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Città operative</label>
                    <select name="citta[]" id="citta-operative" multiple="multiple" class="w-full border rounded px-3 py-2">
                        <?php
                        if (!empty($citta_array)) {
                            foreach ($citta_array as $val) {
                                if ($val !== '') {
                                    echo '<option value="' . esc_attr($val) . '" selected>' . esc_html($val) . '</option>';
                                }
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Specializzazioni</label>
                    <select name="specializzazioni[]" id="specializzazioni-tematiche" multiple="multiple" class="w-full border rounded px-3 py-2">
                        <?php foreach ($specializzazioni_list as $spec): ?>
                            <option value="<?php echo esc_attr($spec); ?>" <?php echo in_array($spec, $specializzazioni_array) ? 'selected' : ''; ?>><?php echo esc_html($spec); ?></option>
                        <?php endforeach; ?>
                        <?php
                        // Opzioni custom inserite come tag
                        foreach ($specializzazioni_array as $val) {
                            if (!in_array($val, $specializzazioni_list) && $val !== '') {
                                echo '<option value="' . esc_attr($val) . '" selected>' . esc_html($val) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Patentino guida turistica (PDF/JPG/JPEG)</label>
                    <?php if ($patentino): ?>
                        <a href="<?php echo esc_url($patentino); ?>" target="_blank" class="text-blue-600 underline">Visualizza patentino</a>
                    <?php endif; ?>
                    <input type="file" name="patentino" accept="application/pdf,image/jpeg,image/jpg" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Altri certificati (PDF/JPG/JPEG, multipli)</label>
                    <?php if (!empty($certificati) && is_array($certificati)): ?>
                        <div class="mb-2">
                            <?php foreach ($certificati as $cert): ?>
                                <a href="<?php echo esc_url($cert); ?>" target="_blank" class="text-blue-600 underline mr-2">Certificato</a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="certificati[]" accept="application/pdf,image/jpeg,image/jpg" multiple class="w-full border rounded px-3 py-2" />
                </div>
                <div class="text-center">
                    <button type="submit" name="update_guida_profile" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Salva modifiche</button>
                </div>
            </form>
        </div>
        <div id="tab-anagrafici" class="tab-pane hidden">
            <form method="post">
                <div class="mb-4">
                    <label class="block font-medium mb-1">Nome</label>
                    <input type="text" name="nome" value="<?php echo esc_attr($current_user->first_name); ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Cognome</label>
                    <input type="text" name="cognome" value="<?php echo esc_attr($current_user->last_name); ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Email</label>
                    <input type="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Numero di telefono</label>
                    <input type="text" name="telefono" value="<?php echo esc_attr(get_post_meta($guida_post_id, '_telefono', true)); ?>" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="mb-4">
                    <label class="block font-medium mb-1">Bio</label>
                    <textarea name="bio" class="w-full border rounded px-3 py-2"><?php echo esc_textarea($bio); ?></textarea>
                </div>
                <div class="text-center">
                    <button type="submit" name="update_guida_anagrafici" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Salva dati anagrafici</button>
                </div>
            </form>
        </div>
        <div id="tab-immagine" class="tab-pane hidden">
            <form method="post" enctype="multipart/form-data">
                <div class="mb-4">
                    <label class="block font-medium mb-1">Immagine profilo</label>
                    <?php if ($foto_profilo): ?>
                        <img id="profile-preview" src="<?php echo esc_url($foto_profilo); ?>" alt="Foto profilo" class="w-32 h-32 rounded-full object-cover mb-2" />
                    <?php else: ?>
                        <img id="profile-preview" src="https://via.placeholder.com/128x128?text=Foto" class="w-32 h-32 rounded-full object-cover mb-2" />
                    <?php endif; ?>
                    <input type="file" name="file" id="file-input" accept="image/*" class="w-full border rounded px-3 py-2" />
                </div>
                <div class="text-center">
                    <button type="submit" name="update_guida_immagine" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Salva immagine</button>
                </div>
            </form>
        </div>
    </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
jQuery(document).ready(function($) {
    $('#citta-operative').select2({
        tags: true,
        width: '100%',
        placeholder: 'Seleziona o aggiungi città operative',
        dropdownAutoWidth: true
    });
    $('#lingue-parlate').select2({
        tags: true,
        width: '100%',
        placeholder: 'Seleziona o aggiungi lingue parlate',
        dropdownAutoWidth: true
    });
    $('#specializzazioni-tematiche').select2({
        tags: true,
        width: '100%',
        placeholder: 'Seleziona o aggiungi specializzazioni',
        dropdownAutoWidth: true
    });
});
</script>
<script>
// Tab switching logic
const tabBtns = [
    document.getElementById('tab-specifiche-btn'),
    document.getElementById('tab-anagrafici-btn'),
    document.getElementById('tab-immagine-btn')
];
const tabPanes = [
    document.getElementById('tab-specifiche'),
    document.getElementById('tab-anagrafici'),
    document.getElementById('tab-immagine')
];
tabBtns.forEach((btn, idx) => {
    btn.addEventListener('click', function() {
        tabBtns.forEach((b, i) => {
            b.classList.toggle('border-blue-600', i === idx);
            tabPanes[i].classList.toggle('hidden', i !== idx);
        });
    });
});
// Preview immagine profilo
const fileInput = document.getElementById('file-input');
if (fileInput) {
    fileInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(ev) {
                document.getElementById('profile-preview').src = ev.target.result;
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>
<?php get_footer(); ?> 