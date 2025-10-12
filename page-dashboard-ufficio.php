<?php
/* Template Name: Dashboard Ufficio Turistico */
if (!is_user_logged_in() || !current_user_can('editor_turistico')) {
    wp_redirect(home_url());
    exit;
}
get_header();

// Aggiungi SweetAlert
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
?>

<!-- Aggiungi CSS per il loader -->
<style>
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

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.dashboard-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    text-align: center;
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 0.875rem;
    opacity: 0.9;
}

.action-btn {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s ease;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.experience-card {
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    transition: all 0.3s ease;
}

.experience-card:hover {
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-published {
    background-color: #d1fae5;
    color: #065f46;
}

.status-draft {
    background-color: #fef3c7;
    color: #92400e;
}

.status-pending {
    background-color: #dbeafe;
    color: #1e40af;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.experience-card {
    transition: all 0.3s ease;
}

.experience-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 25px -3px rgba(0, 0, 0, 0.1);
}
</style>

<div class="page-loader" id="pageLoader">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">Caricamento Dashboard Ufficio Turistico...</div>
                                </div>
                            </div>
                            
<div class="min-h-screen bg-gray-50 py-8" id="dashboardContent" style="display: none;">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header Dashboard -->
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Dashboard Ufficio Turistico</h1>
                    <p class="text-gray-600 mt-2">Gestisci le esperienze turistiche del tuo comune</p>
                                </div>
                <div class="flex space-x-4">
                    <a href="<?php echo home_url('/nuova-esperienza/'); ?>" class="action-btn">
                        <i class="bi bi-plus-circle mr-2"></i>
                        Nuova Esperienza
                    </a>
                    <a href="<?php echo home_url('/mappa-esperienze/'); ?>" class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50">
                        <i class="bi bi-map mr-2"></i>
                        Visualizza Mappa
                    </a>
        </div>
    </div>
        </div>

        <!-- Statistiche -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="stat-card">
                <div class="stat-number" id="totalEsperienze">-</div>
                <div class="stat-label">Totale Esperienze</div>
                            </div>
            <div class="stat-card">
                <div class="stat-number" id="esperienzePubblicate">-</div>
                <div class="stat-label">Pubblicate</div>
                        </div>
            <div class="stat-card">
                <div class="stat-number" id="esperienzeBozza">-</div>
                <div class="stat-label">In Bozza</div>
                        </div>
            <div class="stat-card">
                <div class="stat-number" id="prenotazioniOggi">-</div>
                <div class="stat-label">Prenotazioni Oggi</div>
            </div>
                            </div>

        <!-- Filtri e Ricerca -->
        <div class="dashboard-card p-6 mb-8">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="relative">
                        <input type="text" id="searchEsperienze" placeholder="Cerca esperienze..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <i class="bi bi-search absolute left-3 top-3 text-gray-400"></i>
                    </div>
                    <select id="filterStatus" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Tutti gli stati</option>
                        <option value="publish">Pubblicate</option>
                        <option value="draft">Bozza</option>
                        <option value="pending">In revisione</option>
                    </select>
                    <select id="filterCategoria" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">Tutte le categorie</option>
                    </select>
                </div>
                <div class="flex items-center space-x-2">
                    <button id="refreshData" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600">
                        <i class="bi bi-arrow-clockwise mr-2"></i>
                        Aggiorna
                    </button>
                </div>
            </div>
            </div>

        <!-- Sezione Prenotazioni -->
        <div class="dashboard-card p-6 mb-8">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Prenotazioni Recenti</h2>
                <div class="flex items-center space-x-2">
                    <button id="refreshPrenotazioni" class="bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 text-sm">
                        <i class="bi bi-arrow-clockwise mr-2"></i>
                        Aggiorna
                    </button>
                </div>
            </div>

            <!-- Loading State Prenotazioni -->
            <div id="prenotazioniLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <p class="mt-2 text-gray-500">Caricamento prenotazioni...</p>
            </div>

            <!-- Lista Prenotazioni -->
            <div id="prenotazioniList" class="space-y-4">
                <!-- Le prenotazioni verranno caricate qui via AJAX -->
            </div>
        </div>

        <!-- Lista Esperienze -->
        <div class="dashboard-card p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-900">Le tue Esperienze</h2>
                <div class="flex items-center space-x-2">
                    <span class="text-sm text-gray-500">Ordina per:</span>
                    <select id="sortBy" class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                        <option value="date_desc">Data (più recenti)</option>
                        <option value="date_asc">Data (più vecchi)</option>
                        <option value="title_asc">Titolo (A-Z)</option>
                        <option value="title_desc">Titolo (Z-A)</option>
                    </select>
                </div>
            </div>

            <!-- Loading State -->
            <div id="esperienzeLoading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <p class="mt-2 text-gray-500">Caricamento esperienze...</p>
            </div>

            <!-- Lista Esperienze -->
            <div id="esperienzeList" class="space-y-4">
                <!-- Le esperienze verranno caricate qui via AJAX -->
            </div>

            <!-- Paginazione -->
            <div id="pagination" class="mt-6 flex justify-center">
                <!-- La paginazione verrà generata dinamicamente -->
        </div>
        </div>
    </div>
</div>

<script>
// Definisci ajax_object direttamente
var ajax_object = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('opencomune_nonce'); ?>'
};

document.addEventListener('DOMContentLoaded', function() {
    // Nascondi loader dopo 1 secondo
    setTimeout(function() {
        document.getElementById('pageLoader').classList.add('fade-out');
        setTimeout(function() {
            document.getElementById('pageLoader').style.display = 'none';
            document.getElementById('dashboardContent').style.display = 'block';
        }, 500);
    }, 1000);

    // Carica dati iniziali
    loadDashboardData();
    loadCategorie();
    loadPrenotazioni();
    
    // Event listeners
    document.getElementById('searchEsperienze').addEventListener('input', debounce(filterEsperienze, 300));
    document.getElementById('filterStatus').addEventListener('change', filterEsperienze);
    document.getElementById('filterCategoria').addEventListener('change', filterEsperienze);
    document.getElementById('sortBy').addEventListener('change', filterEsperienze);
    document.getElementById('refreshData').addEventListener('click', loadDashboardData);
    document.getElementById('refreshPrenotazioni').addEventListener('click', loadPrenotazioni);
});

// Carica dati dashboard
function loadDashboardData() {
    fetch(ajax_object.ajax_url + '?action=opencomune_get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalEsperienze').textContent = data.data.total_esperienze;
                document.getElementById('esperienzePubblicate').textContent = data.data.pubblicate;
                document.getElementById('esperienzeBozza').textContent = data.data.bozza;
                document.getElementById('prenotazioniOggi').textContent = data.data.prenotazioni_oggi;
            }
        })
        .catch(error => console.error('Errore nel caricamento delle statistiche:', error));
    
    loadEsperienze();
}

// Carica categorie
function loadCategorie() {
    fetch(ajax_object.ajax_url + '?action=opencomune_get_categorie_esperienze')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const select = document.getElementById('filterCategoria');
                select.innerHTML = '<option value="">Tutte le categorie</option>';
                data.data.forEach(categoria => {
                    select.innerHTML += `<option value="${categoria.slug}">${categoria.name}</option>`;
                });
            }
        })
        .catch(error => console.error('Errore nel caricamento delle categorie:', error));
}

// Carica prenotazioni
function loadPrenotazioni() {
    document.getElementById('prenotazioniLoading').style.display = 'block';
    document.getElementById('prenotazioniList').innerHTML = '';
    
    fetch(ajax_object.ajax_url + '?action=opencomune_get_prenotazioni')
        .then(response => response.json())
        .then(data => {
            document.getElementById('prenotazioniLoading').style.display = 'none';
            
            if (data.success) {
                renderPrenotazioni(data.data);
            } else {
                document.getElementById('prenotazioniList').innerHTML = 
                    '<div class="text-center py-8 text-gray-500">Nessuna prenotazione trovata</div>';
            }
        })
        .catch(error => {
            document.getElementById('prenotazioniLoading').style.display = 'none';
            console.error('Errore nel caricamento delle prenotazioni:', error);
        });
}

// Carica esperienze
function loadEsperienze(page = 1) {
    const search = document.getElementById('searchEsperienze').value;
    const status = document.getElementById('filterStatus').value;
    const categoria = document.getElementById('filterCategoria').value;
    const sort = document.getElementById('sortBy').value;
    
    document.getElementById('esperienzeLoading').style.display = 'block';
    document.getElementById('esperienzeList').innerHTML = '';
    
    const params = new URLSearchParams({
        action: 'opencomune_get_esperienze_dashboard',
        search: search,
        status: status,
        categoria: categoria,
        sort: sort,
        page: page
    });
    
    fetch(ajax_object.ajax_url + '?' + params.toString())
        .then(response => response.json())
        .then(data => {
            document.getElementById('esperienzeLoading').style.display = 'none';
            
            if (data.success) {
                renderEsperienze(data.data.esperienze);
                renderPagination(data.data.pagination);
            } else {
                document.getElementById('esperienzeList').innerHTML = 
                    '<div class="text-center py-8 text-gray-500">Nessuna esperienza trovata</div>';
            }
        })
        .catch(error => {
            document.getElementById('esperienzeLoading').style.display = 'none';
            console.error('Errore nel caricamento delle esperienze:', error);
        });
}

// Renderizza prenotazioni
function renderPrenotazioni(prenotazioni) {
    const container = document.getElementById('prenotazioniList');
    
    if (prenotazioni.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">Nessuna prenotazione trovata</div>';
        return;
    }
    
    container.innerHTML = prenotazioni.map(prenotazione => `
        <div class="experience-card">
            <div class="flex items-start space-x-4">
                <!-- Icona prenotazione -->
                <div class="flex-shrink-0">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="bi bi-calendar-check text-blue-600 text-xl"></i>
                    </div>
                </div>
                
                <!-- Contenuto prenotazione -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <h3 class="text-lg font-semibold text-gray-900">${prenotazione.esperienza_title}</h3>
                                <span class="status-badge ${prenotazione.status === 'confirmed' ? 'status-published' : 'status-draft'}">
                                    ${prenotazione.status === 'confirmed' ? 'Confermata' : 'In Attesa'}
                                </span>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Partecipanti:</strong> ${prenotazione.participants}</p>
                                    <p class="text-sm text-gray-600"><strong>Data:</strong> ${prenotazione.date}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600"><strong>Nome:</strong> ${prenotazione.name}</p>
                                    <p class="text-sm text-gray-600"><strong>Email:</strong> ${prenotazione.email}</p>
                                </div>
                            </div>
                            ${prenotazione.notes ? `<p class="text-sm text-gray-600"><strong>Note:</strong> ${prenotazione.notes}</p>` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Azioni prenotazione -->
                <div class="flex flex-col space-y-2 ml-4">
                    <div class="flex space-x-2">
                        <button onclick="togglePrenotazioneStatus(${prenotazione.id}, '${prenotazione.status}')" 
                                class="px-3 py-1 rounded text-xs font-medium transition-colors ${prenotazione.status === 'confirmed' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200'}">
                            <i class="bi bi-${prenotazione.status === 'confirmed' ? 'x-circle' : 'check-circle'} mr-1"></i>
                            ${prenotazione.status === 'confirmed' ? 'Annulla' : 'Conferma'}
                        </button>
                    </div>
                    
                    <div class="flex space-x-2">
                        <button onclick="viewPrenotazioneDetails(${prenotazione.id})" 
                                class="bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 transition-colors">
                            <i class="bi bi-eye mr-1"></i>Dettagli
                        </button>
                        <button onclick="deletePrenotazione(${prenotazione.id})" 
                                class="bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition-colors">
                            <i class="bi bi-trash mr-1"></i>Elimina
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Renderizza esperienze
function renderEsperienze(esperienze) {
    const container = document.getElementById('esperienzeList');
    
    if (esperienze.length === 0) {
        container.innerHTML = '<div class="text-center py-8 text-gray-500">Nessuna esperienza trovata</div>';
        return;
    }
    
    container.innerHTML = esperienze.map(esperienza => `
        <div class="experience-card">
            <div class="flex items-start space-x-4">
                <!-- Immagine di copertina -->
                <div class="flex-shrink-0">
                    <div class="w-24 h-24 bg-gray-200 rounded-lg overflow-hidden">
                        ${esperienza.thumbnail ? 
                            `<img src="${esperienza.thumbnail}" alt="${esperienza.title}" class="w-full h-full object-cover">` :
                            `<div class="w-full h-full flex items-center justify-center text-gray-400">
                                <i class="bi bi-image text-2xl"></i>
                            </div>`
                        }
                    </div>
                </div>
                
                <!-- Contenuto principale -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center space-x-3 mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 truncate">${esperienza.title}</h3>
                                <span class="status-badge status-${esperienza.status}">
                                    ${getStatusLabel(esperienza.status)}
                                </span>
                            </div>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2">${esperienza.excerpt || 'Nessuna descrizione disponibile'}</p>
                            <div class="flex items-center space-x-4 text-sm text-gray-500">
                                <span><i class="bi bi-calendar mr-1"></i>${esperienza.date}</span>
                                <span><i class="bi bi-clock mr-1"></i>${esperienza.duration || 'N/A'}</span>
                                <span><i class="bi bi-people mr-1"></i>${esperienza.max_participants || 'N/A'} max</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Azioni -->
                <div class="flex flex-col space-y-2 ml-4">
                    <!-- Controllo stato -->
                    <div class="flex space-x-2">
                        <button onclick="toggleEsperienzaStatus(${esperienza.id}, '${esperienza.status}')" 
                                class="px-3 py-1 rounded text-xs font-medium transition-colors ${esperienza.status === 'publish' ? 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200' : 'bg-green-100 text-green-800 hover:bg-green-200'}">
                            <i class="bi bi-${esperienza.status === 'publish' ? 'eye-slash' : 'eye'} mr-1"></i>
                            ${esperienza.status === 'publish' ? 'Nascondi' : 'Pubblica'}
                        </button>
                    </div>
                    
                    <!-- Azioni principali -->
                    <div class="flex space-x-2">
                        <a href="${esperienza.edit_url}" class="bg-blue-500 text-white px-3 py-1 rounded text-xs hover:bg-blue-600 transition-colors" title="${esperienza.edit_url}">
                            <i class="bi bi-pencil mr-1"></i>Modifica
                        </a>
                        <a href="${esperienza.view_url}" class="bg-gray-500 text-white px-3 py-1 rounded text-xs hover:bg-gray-600 transition-colors" target="_blank">
                            <i class="bi bi-eye mr-1"></i>Visualizza
                        </a>
                    </div>
                    
                    <!-- Azione eliminazione -->
                    <button onclick="deleteEsperienza(${esperienza.id})" 
                            class="bg-red-500 text-white px-3 py-1 rounded text-xs hover:bg-red-600 transition-colors">
                        <i class="bi bi-trash mr-1"></i>Elimina
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

// Renderizza paginazione
function renderPagination(pagination) {
    const container = document.getElementById('pagination');
    
    if (!pagination || pagination.total_pages <= 1) {
        container.innerHTML = '';
        return;
    }
    
    let html = '<nav class="flex items-center space-x-2">';
    
    // Pagina precedente
    if (pagination.current_page > 1) {
        html += `<button onclick="loadEsperienze(${pagination.current_page - 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Precedente</button>`;
    }
    
    // Numeri pagina
    for (let i = 1; i <= pagination.total_pages; i++) {
        if (i === pagination.current_page) {
            html += `<span class="px-3 py-2 bg-blue-500 text-white rounded-lg">${i}</span>`;
        } else {
            html += `<button onclick="loadEsperienze(${i})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">${i}</button>`;
        }
    }
    
    // Pagina successiva
    if (pagination.current_page < pagination.total_pages) {
        html += `<button onclick="loadEsperienze(${pagination.current_page + 1})" class="px-3 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Successiva</button>`;
    }
    
    html += '</nav>';
    container.innerHTML = html;
}

// Filtra esperienze
function filterEsperienze() {
    loadEsperienze(1);
}

// Cambia stato esperienza
function toggleEsperienzaStatus(id, currentStatus) {
    const newStatus = currentStatus === 'publish' ? 'draft' : 'publish';
    const actionText = newStatus === 'publish' ? 'pubblicare' : 'nascondere';
    
    Swal.fire({
        title: 'Conferma Azione',
        text: `Sei sicuro di voler ${actionText} questa esperienza?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: newStatus === 'publish' ? '#10b981' : '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sì, ${actionText}`,
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(ajax_object.ajax_url + '?action=opencomune_toggle_esperienza_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Aggiornato!', data.message, 'success');
                    loadDashboardData(); // Ricarica i dati
                } else {
                    Swal.fire('Errore!', data.message || 'Errore durante l\'aggiornamento.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Errore!', 'Errore di connessione.', 'error');
            });
        }
    });
}

// Gestione prenotazioni
function togglePrenotazioneStatus(id, currentStatus) {
    const newStatus = currentStatus === 'confirmed' ? 'pending' : 'confirmed';
    const actionText = newStatus === 'confirmed' ? 'confermare' : 'annullare';
    
    Swal.fire({
        title: 'Conferma Azione',
        text: `Sei sicuro di voler ${actionText} questa prenotazione?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: newStatus === 'confirmed' ? '#10b981' : '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: `Sì, ${actionText}`,
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(ajax_object.ajax_url + '?action=opencomune_toggle_prenotazione_status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}&status=${newStatus}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Aggiornato!', data.message, 'success');
                    loadPrenotazioni(); // Ricarica le prenotazioni
                } else {
                    Swal.fire('Errore!', data.message || 'Errore durante l\'aggiornamento.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Errore!', 'Errore di connessione.', 'error');
            });
        }
    });
}

function viewPrenotazioneDetails(id) {
    // Implementa la visualizzazione dei dettagli della prenotazione
    Swal.fire({
        title: 'Dettagli Prenotazione',
        text: 'Funzionalità in sviluppo...',
        icon: 'info'
    });
}

function deletePrenotazione(id) {
    Swal.fire({
        title: 'Sei sicuro?',
        text: "Questa prenotazione verrà eliminata definitivamente!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sì, elimina!',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(ajax_object.ajax_url + '?action=opencomune_delete_prenotazione', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `id=${id}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Eliminata!', 'La prenotazione è stata eliminata.', 'success');
                    loadPrenotazioni();
                } else {
                    Swal.fire('Errore!', data.message || 'Errore durante l\'eliminazione.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Errore!', 'Errore di connessione.', 'error');
            });
        }
    });
}

// Elimina esperienza
function deleteEsperienza(id) {
    Swal.fire({
        title: 'Sei sicuro?',
        text: "Questa azione non può essere annullata!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sì, elimina!',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(ajax_object.ajax_url + '?action=opencomune_delete_esperienza&id=' + id, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Eliminata!', 'L\'esperienza è stata eliminata.', 'success');
                    loadDashboardData();
      } else {
                    Swal.fire('Errore!', data.message || 'Errore durante l\'eliminazione.', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Errore!', 'Errore di connessione.', 'error');
            });
        }
      });
    }

// Utility functions
function getStatusLabel(status) {
    const labels = {
        'publish': 'Pubblicata',
        'draft': 'Bozza',
        'pending': 'In revisione'
    };
    return labels[status] || status;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

<?php get_footer(); ?> 