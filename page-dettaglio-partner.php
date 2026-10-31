<?php
/*
Template Name: Dettaglio Partner
*/

if (!is_user_logged_in() || !opencomune_user_can_access_ufficio_dashboard()) {
    wp_redirect(home_url());
    exit;
}

// Enqueue script necessari
wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', [], '11.0.0', true);
wp_enqueue_style('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css', [], '11.0.0');

// Ottieni ID partner dalla URL
$partner_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$partner_id) {
    wp_safe_redirect(home_url('/gestione-partner/'));
    exit;
}

// Carica dati partner
global $wpdb;
$table_partners = $wpdb->prefix . 'tour_partners';
$partner = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_partners WHERE id = %d", $partner_id));

if (!$partner) {
    wp_safe_redirect(home_url('/gestione-partner/'));
    exit;
}

opencomune_enqueue_dashboard_layout_assets();

opencomune_render_dashboard_shell_open();
?>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main p-3">
        <div class="mb-6">
            <a href="<?php echo esc_url(home_url('/gestione-partner/')); ?>" class="oc-dashboard-btn oc-dashboard-btn--secondary">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <?php esc_html_e('Torna a gestione partner', 'opencomune'); ?>
            </a>
        </div>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Dettaglio Partner</h1>
                <p class="text-gray-600 mt-2">Gestisci le informazioni del partner</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php 
                    echo $partner->status === 'approved' ? 'bg-green-100 text-green-800' : 
                         ($partner->status === 'rejected' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800');
                ?>">
                    <?php 
                    switch($partner->status) {
                        case 'pending': echo 'In Attesa'; break;
                        case 'approved': echo 'Approvato'; break;
                        case 'rejected': echo 'Rifiutato'; break;
                        default: echo 'Sconosciuto';
                    }
                    ?>
                </span>
                <a href="<?php echo esc_url(home_url('/gestione-partner/')); ?>" class="oc-dashboard-btn oc-dashboard-btn--secondary">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i>
                    <?php esc_html_e('Torna alla lista', 'opencomune'); ?>
                </a>
            </div>
        </div>

        <!-- Informazioni Partner -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Informazioni Generali</h3>
                <hr class="mb-3">
                <!-- Logo Partner -->
                <?php if (!empty($partner->logo_url)): ?>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><strong>Logo</strong></label>
                        <div class="flex items-center">
                            <img src="<?php echo esc_url($partner->logo_url); ?>" 
                                 alt="Logo <?php echo esc_attr($partner->nome_attivita); ?>" 
                                 class="w-20 h-20 object-cover rounded-lg border-2 border-gray-200 shadow-sm">
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="space-y-2">
                    <p><strong>Nome Attività:</strong> <?php echo esc_html($partner->nome_attivita); ?></p>
                    <p><strong>Tipologia:</strong> <?php echo esc_html($partner->tipologia_attivita); ?></p>
                    <p><strong>Partita IVA:</strong> <?php echo esc_html($partner->partita_iva); ?></p>
                    <?php if ($partner->numero_rea): ?>
                        <p><strong>Numero REA:</strong> <?php echo esc_html($partner->numero_rea); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Contatti</h3>
                <hr class="mb-3">
                <div class="space-y-2">
                    <p><strong>Referente:</strong> <?php echo esc_html($partner->nome_referente); ?></p>
                    <p><strong>Ruolo:</strong> <?php echo esc_html($partner->ruolo_referente); ?></p>
                    <p><strong>Telefono:</strong> <?php echo esc_html($partner->telefono_attivita); ?></p>
                    <p><strong>Email:</strong> <?php echo esc_html($partner->email_ufficiale); ?></p>
                    <?php if ($partner->sito_web): ?>
                        <p><strong>Sito Web:</strong> <a href="<?php echo esc_url($partner->sito_web); ?>" target="_blank" class="text-blue-600 hover:underline"><?php echo esc_html($partner->sito_web); ?></a></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Localizzazione</h3>
                <hr class="mb-3">
                <div class="space-y-2">
                    <p><strong>Indirizzo:</strong> <?php echo esc_html($partner->indirizzo); ?></p>
                    <p><strong>CAP:</strong> <?php echo esc_html($partner->cap); ?></p>
                    <p><strong>Comune:</strong> <?php echo esc_html($partner->comune); ?></p>
                    <?php if ($partner->area_quartiere): ?>
                        <p><strong>Area/Quartiere:</strong> <?php echo esc_html($partner->area_quartiere); ?></p>
                    <?php endif; ?>
                    <?php if ($partner->latitudine && $partner->longitudine): ?>
                        <p><strong>Coordinate:</strong> <?php echo esc_html($partner->latitudine); ?>, <?php echo esc_html($partner->longitudine); ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-gray-50 rounded-lg p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Credenziali</h3>
                <hr class="mb-3">
                <div class="space-y-2">
                    <p><strong>Email Login:</strong> <?php echo esc_html($partner->email_login); ?></p>
                    <p><strong>Data Registrazione:</strong> <?php echo date('d/m/Y H:i', strtotime($partner->data_registrazione)); ?></p>
                    <?php if ($partner->data_approvazione): ?>
                        <p><strong>Data Approvazione:</strong> <?php echo date('d/m/Y H:i', strtotime($partner->data_approvazione)); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($partner->descrizione_breve || $partner->descrizione_estesa || !empty($partner->logo_url)): ?>
        <div class="mt-6 bg-gray-50 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Descrizioni</h3>
            <hr class="mb-3">
          
            
            <?php if ($partner->descrizione_breve): ?>
                <div class="mb-3">
                    <strong>Descrizione Breve:</strong>
                    <p class="text-gray-700 mt-1"><?php echo esc_html($partner->descrizione_breve); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($partner->descrizione_estesa): ?>
                <div>
                    <strong>Descrizione Estesa:</strong>
                    <p class="text-gray-700 mt-1"><?php echo esc_html($partner->descrizione_estesa); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($partner->note_admin): ?>
        <div class="mt-6 bg-yellow-50 rounded-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-3">Note Admin</h3>
            <p class="text-gray-700"><?php echo esc_html($partner->note_admin); ?></p>
        </div>
        <?php endif; ?>

        <!-- Pulsanti Azione -->
        <div class="mt-8 flex flex-wrap gap-4">
            <button onclick="approvePartner(<?php echo $partner->id; ?>)" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-600 transition <?php echo $partner->status === 'approved' ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $partner->status === 'approved' ? 'disabled' : ''; ?>>
                <i class="bi bi-check mr-2"></i>Approva Partner
            </button>
            
            <button onclick="rejectPartner(<?php echo $partner->id; ?>)" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-red-600 transition <?php echo $partner->status === 'rejected' ? 'opacity-50 cursor-not-allowed' : ''; ?>" <?php echo $partner->status === 'rejected' ? 'disabled' : ''; ?>>
                <i class="bi bi-x mr-2"></i>Annulla Approvazione
            </button>
            
            <button onclick="deletePartner(<?php echo $partner->id; ?>)" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition">
                <i class="bi bi-trash mr-2"></i>Elimina Partner
            </button>
        </div>
    </div>
    </main>
</div>

<script>
// Variabili globali
const ajax_object = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('opencomune_nonce'); ?>'
};

// Funzioni per le azioni
function approvePartner(partnerId) {
    updatePartnerStatus(partnerId, 'approved');
}

function rejectPartner(partnerId) {
    updatePartnerStatus(partnerId, 'rejected');
}

function deletePartner(partnerId) {
    Swal.fire({
        title: 'Conferma Eliminazione',
        text: 'Sei sicuro di voler eliminare questo partner? Questa azione non può essere annullata.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla',
        confirmButtonColor: '#ef4444'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'opencomune_delete_partner');
            formData.append('_wpnonce', ajax_object.nonce);
            formData.append('id', partnerId);
            
            fetch(ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Eliminato!',
                        text: 'Partner eliminato con successo',
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        window.location.href = '<?php echo home_url('/gestione-partner/'); ?>';
                    });
                } else {
                    Swal.fire({
                        title: 'Errore!',
                        text: data.message || 'Errore durante l\'eliminazione',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Errore!',
                    text: 'Errore di connessione',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }
    });
}

function updatePartnerStatus(partnerId, status) {
    const statusText = status === 'approved' ? 'approvare' : 'rifiutare';
    
    Swal.fire({
        title: 'Conferma',
        text: `Sei sicuro di voler ${statusText} questo partner?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sì, conferma',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('action', 'opencomune_update_partner');
            formData.append('_wpnonce', ajax_object.nonce);
            formData.append('id', partnerId);
            formData.append('status', status);
            
            fetch(ajax_object.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Successo!',
                        text: `Partner ${statusText} con successo`,
                        icon: 'success',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Errore!',
                        text: data.message || 'Errore durante l\'aggiornamento',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Errore!',
                    text: 'Errore di connessione',
                    icon: 'error',
                    confirmButtonText: 'OK'
                });
            });
        }
    });
}
</script>

<?php opencomune_render_dashboard_shell_close(); ?>