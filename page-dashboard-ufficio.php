<?php
/* Template Name: Dashboard Ufficio Turistico */
if (!is_user_logged_in() || !current_user_can('editor_turistico')) {
    wp_redirect(home_url());
    exit;
}
get_header();
// Aggiungi SweetAlert
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);

// Aggiungi CSS per il loader
echo '<style>
.page-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    transition: opacity 0.5s ease-out;
}

.page-loader.fade-out {
    opacity: 0;
    pointer-events: none;
}

.loader-content {
    text-align: center;
    color: white;
}

.loader-spinner {
    width: 60px;
    height: 60px;
    border: 4px solid rgba(255, 255, 255, 0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

.loader-text {
    font-size: 18px;
    font-weight: 500;
    margin-bottom: 10px;
}

.loader-subtext {
    font-size: 14px;
    opacity: 0.8;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Nascondi il contenuto principale durante il caricamento */
.dashboard-content {
    opacity: 0;
    transition: opacity 0.5s ease-in;
}

.dashboard-content.loaded {
    opacity: 1;
}
</style>';

// Aggiungi il loader HTML
echo '<div id="page-loader" class="page-loader">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">Caricamento Dashboard</div>
        <div class="loader-subtext">Preparazione delle tue esperienze...</div>
    </div>
</div>';
$current_user = wp_get_current_user();
$args = [
    'post_type' => 'esperienze',
    'author' => $current_user->ID,
    'post_status' => ['publish', 'pending', 'draft'],
    'posts_per_page' => 10,
];
$tour_query = new WP_Query($args);

$guida_post_id = get_user_meta($current_user->ID, 'guida_post_id', true);

// Gestione salvataggio form
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

// Messaggi di feedback per Google Calendar
if (isset($_GET['calendar_connected']) && $_GET['calendar_connected'] == '1') {
    echo '<div class="bg-green-100 text-green-800 rounded p-4 mb-6">';
    echo '<i class="bi bi-calendar-check me-2"></i>Google Calendar connesso con successo! Ora puoi sincronizzare i tuoi tour.';
    echo '</div>';
}

if (isset($_GET['calendar_error']) && $_GET['calendar_error'] == '1') {
    echo '<div class="bg-red-100 text-red-800 rounded p-4 mb-6">';
    echo '<i class="bi bi-calendar-x me-2"></i>Errore nella connessione con Google Calendar. Riprova più tardi.';
    echo '</div>';
}

// Recupera le città dalla tabella custom (come in page-registrazione-guida.php)
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
<div class="container mx-auto px-2 py-8 w-full dashboard-content">
    <!-- SEZIONE 1: I tuoi tour (full-width) -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">I tuoi tour</h2>
            <div class="d-flex gap-2">
                <?php if (opencomune_is_google_calendar_enabled()): ?>
                    <?php 
                    $is_connected = get_user_meta($current_user->ID, 'google_calendar_connected', true);
                    if ($is_connected): ?>
                        <button class="btn btn-outline-success btn-sm" id="disconnect-google-calendar">
                            <i class="bi bi-calendar-check me-1"></i>Google Calendar Connesso
                        </button>
                    <?php else: ?>
                        <a href="<?php echo opencomune_get_google_calendar_auth_url($current_user->ID); ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-calendar-plus me-1"></i>Connetti Google Calendar
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="<?php echo site_url('/nuovo-tour'); ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>Crea nuovo tour
                </a>
            </div>
        </div>
        <div class="card-body">
        
        <?php
        if ($tour_query->have_posts()) :
            echo '<div class="row">';
            while ($tour_query->have_posts()) : $tour_query->the_post();
                $post_id = get_the_ID();
                $status = get_post_status();
                $thumb = get_the_post_thumbnail_url($post_id, 'medium');
                $prezzo = get_post_meta($post_id, 'prezzo', true);
                $citta = get_post_meta($post_id, 'citta', true);
                ?>
                <div class="col-md-3 mb-3">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1 me-2">
                                    <h6 class="card-title mb-1">
                                        <a href="<?php the_permalink(); ?>" class="text-decoration-none"><?php the_title(); ?></a>
                                    </h6>
                                    <?php if ($citta): ?>
                                        <p class="text-muted small mb-1">
                                            <i class="bi bi-geo-alt-fill me-1"></i><?php echo esc_html($citta); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if ($prezzo): ?>
                                        <p class="text-success fw-bold small">€<?php echo esc_html($prezzo); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($status === 'publish'): ?>
                                        <span class="badge bg-success">Pubblicato</span>
                                    <?php elseif ($status === 'draft'): ?>
                                        <span class="badge bg-warning text-dark">Bozza</span>
                                    <?php elseif ($status === 'pending'): ?>
                                        <span class="badge bg-primary">In revisione</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <?php if ($thumb): ?>
                                <img src="<?php echo esc_url($thumb); ?>" alt="<?php the_title_attribute(); ?>" class="img-fluid rounded mb-2" style="height: 80px; object-fit: cover; width: 100%;" />
                            <?php else: ?>
                                <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height: 80px;">
                                    <i class="bi bi-image text-muted"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?php echo site_url('/modifica-tour/?id=' . $post_id); ?>" class="btn btn-outline-secondary btn-sm" title="Modifica tour">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <?php if ($status === 'publish'): ?>
                                        <button class="btn btn-warning btn-sm sospendi-tour" data-id="<?php echo $post_id; ?>">Sospendi</button>
                                    <?php elseif ($status === 'draft'): ?>
                                        <button class="btn btn-success btn-sm pubblica-tour" data-id="<?php echo $post_id; ?>">Pubblica</button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-primary btn-sm calendario-tour" data-id="<?php echo $post_id; ?>" title="Gestisci calendario">
                                        <i class="bi bi-calendar"></i>
                                    </button>
                                </div>
                                <button class="btn btn-outline-danger btn-sm elimina-tour" data-id="<?php echo $post_id; ?>" title="Elimina">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            endwhile;
            echo '</div>';
            wp_reset_postdata();
        else :
            echo '<div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-6 rounded" role="alert">';
            echo '<div class="flex items-center">';
            echo '<svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 mr-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">';
            echo '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z" />';
            echo '</svg>';
            echo '<div>';
            echo '<p class="font-bold">Nessun tour inserito</p>';
            echo '<p class="text-sm">Inizia creando il tuo primo tour per mostrare le tue esperienze ai viaggiatori.</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        endif;
        ?>
        </div>
    </div>

    <!-- SEZIONE 2: Prossimi appuntamenti (full-width) -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h4 mb-0">Prossimi appuntamenti</h2>
            <span class="text-muted small">Prossimi 7 giorni</span>
        </div>
        <div class="card-body">
            <div class="row">
            <!-- Card appuntamento esempio -->
            <div class="col-md-4 mb-3">
                <div class="card border-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h6 class="card-title">Tour del Centro Storico</h6>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-calendar-event me-1"></i>Lunedì 15 Gennaio 2024
                                </p>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-clock me-1"></i>10:00 - 12:00
                                </p>
                                <p class="text-muted small">
                                    <i class="bi bi-people me-1"></i>8 partecipanti confermati
                                </p>
                            </div>
                            <span class="badge bg-success">Confermato</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-success fw-bold">€240 incassato</span>
                            <button class="btn btn-outline-primary btn-sm">Dettagli</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card border-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h6 class="card-title">Visita ai Musei Vaticani</h6>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-calendar-event me-1"></i>Martedì 16 Gennaio 2024
                                </p>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-clock me-1"></i>14:00 - 16:30
                                </p>
                                <p class="text-muted small">
                                    <i class="bi bi-people me-1"></i>5 partecipanti confermati
                                </p>
                            </div>
                            <span class="badge bg-warning text-dark">In attesa</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-warning fw-bold">€150 in attesa</span>
                            <button class="btn btn-warning btn-sm">Conferma</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 mb-3">
                <div class="card border-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <h6 class="card-title">Tour Gastronomico</h6>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-calendar-event me-1"></i>Giovedì 18 Gennaio 2024
                                </p>
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-clock me-1"></i>19:00 - 21:30
                                </p>
                                <p class="text-muted small">
                                    <i class="bi bi-people me-1"></i>12 partecipanti confermati
                                </p>
                            </div>
                            <span class="badge bg-primary">Completo</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-primary fw-bold">€360 incassato</span>
                            <button class="btn btn-outline-secondary btn-sm">Gestisci</button>
                        </div>
                    </div>
                </div>
            </div>
            </div>

        <!-- Statistiche rapide -->
        <div class="row mt-4">
            <div class="col-md-3 mb-3">
                <div class="card border-success text-center">
                    <div class="card-body">
                        <div class="h2 text-success mb-1">12</div>
                        <div class="text-muted small">Appuntamenti questa settimana</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-primary text-center">
                    <div class="card-body">
                        <div class="h2 text-primary mb-1">€1,250</div>
                        <div class="text-muted small">Incasso previsto</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-warning text-center">
                    <div class="card-body">
                        <div class="h2 text-warning mb-1">3</div>
                        <div class="text-muted small">In attesa di conferma</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card border-info text-center">
                    <div class="card-body">
                        <div class="h2 text-info mb-1">25</div>
                        <div class="text-muted small">Partecipanti totali</div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>

<!-- Modal calendario -->
<div id="modal-calendario" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-40 hidden">
  <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-2xl relative">
    <button id="close-modal-calendario" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
    <h3 class="text-xl font-bold mb-4">Gestisci date tour</h3>
    <div id="dashboard-calendar"></div>
    <div id="calendario-feedback" class="mt-3 text-sm"></div>
  </div>
</div>
<!-- Modal orario per aggiunta fascia oraria -->
<div id="orario-form" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black bg-opacity-40 hidden">
  <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md relative">
    <button id="close-orario-form" class="absolute top-2 right-2 text-gray-400 hover:text-gray-700 text-2xl">&times;</button>
    <h4 class="text-lg font-bold mb-2">Aggiungi orario</h4>
    <div id="selected-dates-display" class="mb-3 p-2 bg-blue-50 rounded text-sm"></div>
    <form id="form-orario">
      <div class="mb-2">
        <label class="block text-sm mb-1">Ora</label>
        <input type="text" name="ora" id="orario-ora" class="border rounded px-3 py-2 w-full" required>
      </div>
      <div class="mb-2">
        <label class="block text-sm mb-1">Posti disponibili</label>
        <input type="number" name="posti" id="orario-posti" class="border rounded px-3 py-2 w-full" min="0">
      </div>
      <div class="mb-2">
        <label class="block text-sm mb-1">Durata (ore)</label>
        <input type="number" name="durata" id="orario-durata" class="border rounded px-3 py-2 w-full" min="0.5" max="12" step="0.5" value="2">
      </div>
      <div class="mb-2">
        <label class="block text-sm mb-1">Note</label>
        <input type="text" name="note" id="orario-note" class="border rounded px-3 py-2 w-full">
      </div>
      <?php if (opencomune_is_google_calendar_enabled() && get_user_meta($current_user->ID, 'google_calendar_connected', true)): ?>
        <div class="mb-3">
          <label class="flex items-center">
            <input type="checkbox" id="sync-google-calendar" class="mr-2" checked>
            <span class="text-sm">Sincronizza con Google Calendar (<?php echo esc_html(opencomune_get_google_calendar_default() === 'primary' ? 'Calendario Principale' : 'Calendario Personalizzato'); ?>)</span>
          </label>
        </div>
      <?php endif; ?>
      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Aggiungi per tutte le date</button>
    </form>
  </div>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
</script>
<script>
jQuery(document).ready(function($) {
    $('#citta-operative').select2({
        tags: true,
        width: '100%',
        placeholder: 'Seleziona o aggiungi città operative',
        dropdownAutoWidth: true,
        ajax: {
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    action: 'opencomune_search_comuni',
                    term: params.term
                };
            },
            processResults: function(data) {
                return data;
            },
            cache: true
        },
        minimumInputLength: 2
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
    // Elimina tour
    $(document).on('click', '.elimina-tour', function() {
        var btn = $(this);
        var postId = btn.data('id');
        var tourTitle = btn.closest('li').find('.tour-title').text() || 'questo tour';
        
        Swal.fire({
            title: 'Elimina tour',
            text: 'Sei sicuro di voler eliminare "' + tourTitle + '"? Questa azione non può essere annullata.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sì, elimina!',
            cancelButtonText: 'Annulla',
            showLoaderOnConfirm: true,
            preConfirm: function() {
                return $.post(ajaxurl, { 
                    action: 'opencomune_elimina_tour', 
                    post_id: postId 
                }).then(function(resp) {
                    if (!resp.success) {
                        throw new Error(resp.data && resp.data.message ? resp.data.message : 'Errore eliminazione.');
                    }
                    return resp;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Eliminato!',
                    text: 'Il tour è stato eliminato con successo.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Refresh della pagina
                });
            }
        }).catch((error) => {
            Swal.fire({
                title: 'Errore!',
                text: error.message,
                icon: 'error'
            });
        });
    });
    // Sospendi tour
    $(document).on('click', '.sospendi-tour', function() {
        var btn = $(this);
        var postId = btn.data('id');
        var tourTitle = btn.closest('li').find('.tour-title').text() || 'questo tour';
        
        Swal.fire({
            title: 'Sospendi tour',
            text: 'Sei sicuro di voler sospendere "' + tourTitle + '"?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ffc107',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sì, sospendi',
            cancelButtonText: 'Annulla',
            showLoaderOnConfirm: true,
            preConfirm: function() {
                return $.post(ajaxurl, { 
                    action: 'opencomune_sospendi_tour', 
                    post_id: postId 
                }).then(function(resp) {
                    if (!resp.success) {
                        throw new Error(resp.data && resp.data.message ? resp.data.message : 'Errore sospensione.');
                    }
                    return resp;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Sospeso!',
                    text: 'Il tour è stato sospeso con successo.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Refresh della pagina
                });
            }
        }).catch((error) => {
            Swal.fire({
                title: 'Errore!',
                text: error.message,
                icon: 'error'
            });
        });
    });
    
    // Pubblica tour
    $(document).on('click', '.pubblica-tour', function() {
        var btn = $(this);
        var postId = btn.data('id');
        var tourTitle = btn.closest('li').find('.tour-title').text() || 'questo tour';
        
        Swal.fire({
            title: 'Pubblica tour',
            text: 'Sei sicuro di voler pubblicare "' + tourTitle + '"?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sì, pubblica',
            cancelButtonText: 'Annulla',
            showLoaderOnConfirm: true,
            preConfirm: function() {
                return $.post(ajaxurl, { 
                    action: 'opencomune_pubblica_tour', 
                    post_id: postId 
                }).then(function(resp) {
                    if (!resp.success) {
                        throw new Error(resp.data && resp.data.message ? resp.data.message : 'Errore pubblicazione.');
                    }
                    return resp;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Pubblicato!',
                    text: 'Il tour è stato pubblicato con successo.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Refresh della pagina
                });
            }
        }).catch((error) => {
            Swal.fire({
                title: 'Errore!',
                text: error.message,
                icon: 'error'
            });
        });
    });
    
    // Gestione disconnessione Google Calendar
    $(document).on('click', '#disconnect-google-calendar', function() {
        Swal.fire({
            title: 'Disconnetti Google Calendar',
            text: 'Sei sicuro di voler disconnettere il tuo account Google Calendar?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sì, disconnetti',
            cancelButtonText: 'Annulla',
            showLoaderOnConfirm: true,
            preConfirm: function() {
                return $.post(ajaxurl, { 
                    action: 'opencomune_disconnect_google_calendar'
                }).then(function(resp) {
                    if (!resp.success) {
                        throw new Error(resp.data && resp.data.message ? resp.data.message : 'Errore disconnessione.');
                    }
                    return resp;
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Disconnesso!',
                    text: 'Google Calendar è stato disconnesso con successo.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload(); // Refresh della pagina
                });
            }
        }).catch((error) => {
            Swal.fire({
                title: 'Errore!',
                text: error.message,
                icon: 'error'
            });
        });
    });
});
</script>
<script>
// Tab switching logic
const tabBtns = [
    document.getElementById('tab-specifiche-btn'),
    document.getElementById('tab-anagrafici-btn'),
    document.getElementById('tab-immagine-btn')
].filter(Boolean);
const tabPanes = [
    document.getElementById('tab-specifiche'),
    document.getElementById('tab-anagrafici'),
    document.getElementById('tab-immagine')
].filter(Boolean);
if (tabBtns.length && tabPanes.length) {
    tabBtns.forEach((btn, idx) => {
        btn.addEventListener('click', function() {
            tabBtns.forEach((b, i) => {
                b.classList.toggle('border-blue-600', i === idx);
                tabPanes[i].classList.toggle('hidden', i !== idx);
            });
        });
    });
}
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
<script>
jQuery(function($){
  flatpickr('#orario-ora', { 
    enableTime: true, 
    noCalendar: true, 
    dateFormat: 'H:i',
    time_24hr: true,
    minuteIncrement: 15
  });
  var calendar;
  var currentTourId = null;
  var selectedDates = [];
  $(document).on('click', '.calendario-tour', function() {
    currentTourId = $(this).data('id');
    $('#modal-calendario').removeClass('hidden');
    $('#calendario-feedback').text('');
    setTimeout(function() {
      if (!calendar) {
        calendar = new FullCalendar.Calendar(document.getElementById('dashboard-calendar'), {
          initialView: 'dayGridMonth',
          locale: 'it',
          selectable: true,
          height: 500,
          select: function(info) {
            selectedDates = [];
            // Converti le date selezionate in array
            var currentDate = new Date(info.start);
            var endDate = new Date(info.end);
            
            while (currentDate < endDate) {
              selectedDates.push(currentDate.toISOString().split('T')[0]);
              currentDate.setDate(currentDate.getDate() + 1);
            }
            
            // Mostra le date selezionate
            if (selectedDates.length > 0) {
              $('#selected-dates-display').html('<strong>Date selezionate:</strong> ' + selectedDates.join(', '));
              $('#orario-form').removeClass('hidden');
            }
          },
          dateClick: function(info) {
            // Per click singolo, seleziona solo quella data
            selectedDates = [info.dateStr];
            $('#selected-dates-display').html('<strong>Data selezionata:</strong> ' + info.dateStr);
            $('#orario-form').removeClass('hidden');
          },
          events: function(fetchInfo, successCallback, failureCallback) {
            $.get(ajaxurl, {
              action: 'opencomune_get_calendario',
              tour_id: currentTourId
            }, function(events) {
              successCallback(events);
            });
          },
          eventContent: function(arg) {
            var ora = arg.event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            return { html: '<span class="inline-block bg-blue-600 text-white px-2 py-1 rounded mr-2">'+ora+'</span>' +
              '<button class="delete-event text-red-600 ml-1" data-id="'+arg.event.id+'">&times;</button>' };
          }
        });
        calendar.render();
      } else {
        calendar.refetchEvents();
      }
    }, 100);
  });
  $('#close-modal-calendario').on('click', function() {
    $('#modal-calendario').addClass('hidden');
  });
  $('#close-orario-form').on('click', function() {
    $('#orario-form').addClass('hidden');
    $('#selected-dates-display').html('');
    selectedDates = [];
  });
  $('#form-orario').on('submit', function(e){
    e.preventDefault();
    
    if (selectedDates.length === 0) {
      $('#calendario-feedback').text('Seleziona almeno una data').removeClass('text-green-600').addClass('text-red-600');
      return;
    }
    
    // Mostra loader
    var submitBtn = $(this).find('button[type="submit"]');
    var originalText = submitBtn.text();
    submitBtn.text('Aggiungendo...').prop('disabled', true);
    
    // Invia le richieste per tutte le date selezionate
    var promises = selectedDates.map(function(data) {
      return $.post(ajaxurl, {
        action: 'opencomune_add_calendario',
        tour_id: currentTourId,
        data: data,
        ora: $('#orario-ora').val(),
        posti: $('#orario-posti').val(),
        note: $('#orario-note').val()
      });
    });
    
    // Se Google Calendar è abilitato e l'utente vuole sincronizzare
    var syncToGoogle = $('#sync-google-calendar').is(':checked');
    if (syncToGoogle) {
      var googleCalendarPromises = selectedDates.map(function(data) {
        return $.post(ajaxurl, {
          action: 'opencomune_sync_to_google_calendar',
          tour_id: currentTourId,
          date: data,
          time: $('#orario-ora').val(),
          posti: $('#orario-posti').val(),
          durata: $('#orario-durata').val(),
          note: $('#orario-note').val()
          // calendar_id will use the default from settings
        });
      });
      
      // Aggiungi le promesse di Google Calendar
      promises = promises.concat(googleCalendarPromises);
    }
    
    Promise.all(promises).then(function(responses) {
      var successCount = 0;
      var errorCount = 0;
      
      responses.forEach(function(resp) {
        if (resp.success) {
          successCount++;
        } else {
          errorCount++;
        }
      });
      
      if (successCount > 0) {
        calendar.refetchEvents();
        $('#orario-form').addClass('hidden');
        $('#form-orario')[0].reset();
        $('#selected-dates-display').html('');
        selectedDates = [];
        
        if (errorCount > 0) {
          $('#calendario-feedback').text(successCount + ' date aggiunte, ' + errorCount + ' errori').removeClass('text-green-600').addClass('text-yellow-600');
        } else {
          $('#calendario-feedback').text(successCount + ' date aggiunte con successo!').removeClass('text-red-600').addClass('text-green-600');
        }
      } else {
        $('#calendario-feedback').text('Errore nell\'aggiunta delle date').removeClass('text-green-600').addClass('text-red-600');
      }
    }).catch(function() {
      $('#calendario-feedback').text('Errore di connessione').removeClass('text-green-600').addClass('text-red-600');
    }).finally(function() {
      submitBtn.text(originalText).prop('disabled', false);
    });
  });
  // Elimina evento (delegato)
  $(document).on('click', '.delete-event', function(e){
    e.preventDefault();
    var id = $(this).data('id');
    if (confirm('Vuoi eliminare questa fascia oraria?')) {
      $.post(ajaxurl, {
        action: 'opencomune_delete_calendario',
        id: id
      }, function(resp) {
        if (resp.success) {
          calendar.refetchEvents();
          $('#calendario-feedback').text('Data eliminata!').removeClass('text-red-600').addClass('text-green-600');
        } else {
          $('#calendario-feedback').text(resp.data && resp.data.message ? resp.data.message : 'Errore').removeClass('text-green-600').addClass('text-red-600');
        }
      });
    }
  });
});

// Gestione loader pagina
document.addEventListener('DOMContentLoaded', function() {
    const pageLoader = document.getElementById('page-loader');
    const dashboardContent = document.querySelector('.dashboard-content');
    
    // Simula un tempo di caricamento minimo per una migliore UX
    setTimeout(function() {
        // Nascondi il loader con fade out
        if (pageLoader) {
            pageLoader.classList.add('fade-out');
        }
        
        // Mostra il contenuto con fade in
        if (dashboardContent) {
            dashboardContent.classList.add('loaded');
        }
        
        // Rimuovi completamente il loader dal DOM dopo l'animazione
        setTimeout(function() {
            if (pageLoader) {
                pageLoader.remove();
            }
        }, 500);
    }, 800); // Tempo minimo di visualizzazione del loader
});
</script>
<?php get_footer(); ?> 