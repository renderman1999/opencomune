<?php
/* Template Name: Gestione Partner */
if (!is_user_logged_in() || !opencomune_user_can_manage_all_esperienze()) {
    wp_safe_redirect(home_url());
    exit;
}

wp_enqueue_script('jquery');
wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), '1.13.6', true);
wp_enqueue_style('datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', array(), '1.13.6');
opencomune_enqueue_dashboard_layout_assets();

opencomune_render_dashboard_shell_open();
?>

<style>
.partner-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.partner-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -3px rgba(0, 0, 0, 0.1);
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.status-pending {
    background-color: #fef3c7;
    color: #92400e;
}

.status-approved {
    background-color: #d1fae5;
    color: #065f46;
}

.status-rejected {
    background-color: #fee2e2;
    color: #991b1b;
}

.partner-actions {
    display: flex;
    flex-wrap: nowrap;
    align-items: center;
    gap: 0.35rem;
    white-space: nowrap;
}

.action-btn {
    width: 2rem;
    height: 2rem;
    padding: 0;
    border: none;
    border-radius: 0.375rem;
    font-size: 1rem;
    line-height: 1;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    cursor: pointer;
}

.action-btn:hover {
    transform: translateY(-1px);
}

.btn-view {
    background-color: #3b82f6;
    color: white;
}

.btn-edit {
    background-color: #10b981;
    color: white;
}

.btn-approve {
    background-color: #059669;
    color: white;
}

.btn-reject {
    background-color: #dc2626;
    color: white;
}

.btn-delete {
    background-color: #ef4444;
    color: white;
}

.dataTables_wrapper {
    margin-top: 1rem;
}

.dataTables_filter input {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem;
    margin-left: 0.5rem;
}

.dataTables_length select {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.25rem;
    margin: 0 0.5rem;
}

.dataTables_info {
    color: #6b7280;
    font-size: 0.875rem;
}

.dataTables_paginate .paginate_button {
    border: 1px solid #d1d5db;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    margin: 0 0.125rem;
    color: #374151;
    text-decoration: none;
    transition: all 0.2s;
}

.dataTables_paginate .paginate_button:hover {
    background-color: #f3f4f6;
    border-color: #9ca3af;
}

.dataTables_paginate .paginate_button.current {
    background-color: #3b82f6;
    color: white;
    border-color: #3b82f6;
}

.partner-bulk-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    padding: 0.75rem 1rem;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 0.5rem;
}

.partner-bulk-toolbar__count {
    font-size: 0.875rem;
    color: #4b5563;
}

.partner-bulk-select-cell {
    width: 2.5rem;
    text-align: center;
}

.partner-bulk-select-cell input[type="checkbox"] {
    width: 1rem;
    height: 1rem;
    cursor: pointer;
    accent-color: #2563eb;
}

.partner-actions-cell {
    width: 1%;
    white-space: nowrap;
}
</style>

<div class="oc-dashboard-app">
    <?php opencomune_render_dashboard_sidebar(); ?>
    <main class="oc-dashboard-main p-3">
        <div class="mb-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="oc-dashboard-section__title text-3xl mb-2">Gestione Partner</h1>
                    <p class="text-gray-600">Gestisci le richieste di registrazione partner</p>
                </div>
                <a href="<?php echo esc_url(home_url('/registrazione-partner/')); ?>" class="oc-dashboard-btn oc-dashboard-btn--primary">
                    <i class="bi bi-person-plus" aria-hidden="true"></i>
                    Nuova registrazione partner
                </a>
            </div>
        </div>

        <!-- Statistiche -->
        <div class="flex flex-wrap gap-4 mb-8">
            <div class="bg-white rounded-xl shadow-lg p-4 flex-1 min-w-[200px]">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                        <i class="bi bi-people text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Totale Partner</p>
                        <p class="text-2xl font-bold text-gray-900" id="total-partners">-</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-4 flex-1 min-w-[200px]">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                        <i class="bi bi-clock text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">In attesa verifica</p>
                        <p class="text-2xl font-bold text-gray-900" id="pending-partners">-</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-4 flex-1 min-w-[200px]">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-green-100 text-green-600">
                        <i class="bi bi-check-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Approvati</p>
                        <p class="text-2xl font-bold text-gray-900" id="approved-partners">-</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-4 flex-1 min-w-[200px]">
                <div class="flex items-center">
                    <div class="p-3 rounded-full bg-red-100 text-red-600">
                        <i class="bi bi-x-circle text-2xl"></i>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Rifiutati</p>
                        <p class="text-2xl font-bold text-gray-900" id="rejected-partners">-</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabella Partner -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Lista Partner</h2>
                <p class="text-gray-600 text-sm mt-1">Gestisci le richieste di registrazione partner</p>
            </div>
            
            <div class="p-6">
                <div class="partner-bulk-toolbar" id="partner-bulk-toolbar">
                    <button type="button" id="partner-bulk-delete" class="oc-dashboard-btn oc-dashboard-btn--delete" disabled>
                        <i class="bi bi-trash" aria-hidden="true"></i>
                        <?php esc_html_e('Elimina selezionati', 'opencomune'); ?>
                    </button>
                    <span class="partner-bulk-toolbar__count" id="partner-bulk-count">
                        <?php esc_html_e('Nessun partner selezionato', 'opencomune'); ?>
                    </span>
                </div>
                <table id="partnersTable" class="w-full">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider partner-bulk-select-cell">
                                <input type="checkbox" id="partner-select-all-page" aria-label="<?php esc_attr_e('Seleziona tutti in pagina', 'opencomune'); ?>">
                            </th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Partner</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipologia</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comune</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Referente</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Azioni</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <!-- I dati verranno caricati via AJAX -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal Dettagli Partner -->
<div id="partnerModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto z-50" style="display: none;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-2/3 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <!-- Header -->
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900" id="partnerModalTitle">Dettagli Partner</h3>
                <button onclick="closePartnerModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg text-2xl"></i>
                </button>
            </div>
            
            <!-- Contenuto -->
            <div id="partnerModalContent" class="space-y-6">
                <!-- I dettagli verranno caricati qui -->
            </div>
        </div>
    </div>
</div>

<script>
// Definisci ajax_object
var ajax_object = {
    ajax_url: '<?php echo admin_url('admin-ajax.php'); ?>',
    nonce: '<?php echo wp_create_nonce('opencomune_nonce'); ?>'
};

let partnersTable;
const selectedPartnerIds = new Set();

function updatePartnerBulkToolbar() {
    const count = selectedPartnerIds.size;
    const deleteBtn = document.getElementById('partner-bulk-delete');
    const countEl = document.getElementById('partner-bulk-count');
    if (deleteBtn) {
        deleteBtn.disabled = count === 0;
    }
    if (countEl) {
        countEl.textContent = count === 0
            ? 'Nessun partner selezionato'
            : (count === 1 ? '1 partner selezionato' : count + ' partner selezionati');
    }
}

function syncPartnerSelectAllCheckbox() {
    const selectAll = document.getElementById('partner-select-all-page');
    if (!selectAll) {
        return;
    }
    const rowChecks = jQuery('#partnersTable .partner-row-check');
    if (!rowChecks.length) {
        selectAll.checked = false;
        selectAll.indeterminate = false;
        return;
    }
    let checkedOnPage = 0;
    rowChecks.each(function () {
        if (this.checked) {
            checkedOnPage++;
        }
    });
    selectAll.checked = checkedOnPage === rowChecks.length;
    selectAll.indeterminate = checkedOnPage > 0 && checkedOnPage < rowChecks.length;
}

// Funzione per inizializzare DataTable
function initDataTable() {
    console.log('Initializing DataTable with server-side processing...');
    
    // Verifica che la tabella esista
    const table = jQuery('#partnersTable');
    if (table.length === 0) {
        console.error('Table #partnersTable not found');
        return;
    }
    
    console.log('Table found, initializing DataTable...');
    
    try {
        partnersTable = jQuery('#partnersTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: ajax_object.ajax_url,
            type: 'POST',
            data: function(d) {
                d.action = 'opencomune_get_partners';
                d._wpnonce = ajax_object.nonce;
                console.log('DataTables sending data:', d);
                return d;
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables AJAX error:', error, thrown);
                console.error('Response:', xhr.responseText);
            },
            dataSrc: function(json) {
                console.log('DataTables received data:', json);
                return json.data;
            }
        },
        paging: true,
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        searching: true,
        ordering: true,
        info: true,
        responsive: true,
        columns: [
            {
                data: 'id',
                orderable: false,
                searchable: false,
                className: 'partner-bulk-select-cell',
                render: function (data) {
                    const id = parseInt(data, 10);
                    const checked = selectedPartnerIds.has(id) ? ' checked' : '';
                    return '<input type="checkbox" class="partner-row-check" value="' + id + '" aria-label="Seleziona partner"' + checked + '>';
                }
            },
            { 
                data: 'nome_attivita',
                render: function(data, type, row) {
                    return `<div class="font-medium text-gray-900">${data}</div>`;
                }
            },
            { 
                data: 'tipologia_attivita',
                render: function(data, type, row) {
                    const tipologie = {
                        'ristorante': 'Ristorante',
                        'bb': 'B&B',
                        'agriturismo': 'Agriturismo',
                        'hotel': 'Hotel',
                        'tour_operator': 'Tour Operator',
                        'guida': 'Guida Turistica',
                        'museo': 'Museo',
                        'evento': 'Organizzatore Eventi',
                        'produttore': 'Produttore Locale',
                        'negozio': 'Negozio',
                        'altro': 'Altro'
                    };
                    return `<span class="text-sm text-gray-600">${tipologie[data] || data}</span>`;
                }
            },
            { 
                data: 'comune',
                render: function(data, type, row) {
                    return `<span class="text-sm text-gray-600">${data}</span>`;
                }
            },
            { 
                data: 'nome_referente',
                render: function(data, type, row) {
                    return `<span class="text-sm text-gray-600">${data}</span>`;
                }
            },
            {
                data: 'email_ufficiale',
                render: function(data, type, row) {
                    const email = data || row.email_login || '';
                    if (!email) {
                        return '<span class="text-sm text-gray-400">—</span>';
                    }
                    const safe = String(email)
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;');
                    return '<a href="mailto:' + encodeURIComponent(email) + '" class="text-sm text-blue-600 hover:underline">' + safe + '</a>';
                }
            },
            { 
                data: 'status',
                render: function(data, type, row) {
                    const statusConfig = {
                        'pending': { class: 'status-pending', text: 'In attesa verifica' },
                        'approved': { class: 'status-approved', text: 'Approvato' },
                        'rejected': { class: 'status-rejected', text: 'Rifiutato' }
                    };
                    const config = statusConfig[data] || { class: 'status-pending', text: data };
                    return `<span class="status-badge ${config.class}">${config.text}</span>`;
                }
            },
            { 
                data: 'data_registrazione',
                render: function(data, type, row) {
                    const date = new Date(data);
                    return `<span class="text-sm text-gray-600">${date.toLocaleDateString('it-IT')}</span>`;
                }
            },
            { 
                data: null,
                orderable: false,
                className: 'partner-actions-cell',
                render: function(data, type, row) {
                    return `
                        <div class="partner-actions">
                            <button type="button" onclick="viewPartner(${row.id})" class="action-btn btn-view" title="Visualizza" aria-label="Visualizza">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                            ${row.status === 'pending' ? `
                                <button type="button" onclick="approvePartner(${row.id})" class="action-btn btn-approve" title="Approva" aria-label="Approva">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                                </button>
                                <button type="button" onclick="rejectPartner(${row.id})" class="action-btn btn-reject" title="Rifiuta" aria-label="Rifiuta">
                                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                                </button>
                            ` : ''}
                            <button type="button" onclick="editPartner(${row.id})" class="action-btn btn-edit" title="Modifica" aria-label="Modifica">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button" onclick="deletePartner(${row.id})" class="action-btn btn-delete" title="Elimina" aria-label="Elimina">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ],
        language: {
            "processing": "Elaborazione...",
            "search": "Cerca:",
            "lengthMenu": "Mostra _MENU_ elementi",
            "info": "Visualizzati da _START_ a _END_ di _TOTAL_ elementi",
            "infoEmpty": "Visualizzati da 0 a 0 di 0 elementi",
            "infoFiltered": "(filtrati da _MAX_ elementi totali)",
            "infoPostFix": "",
            "loadingRecords": "Caricamento...",
            "zeroRecords": "Nessun elemento corrispondente trovato",
            "emptyTable": "Nessun dato disponibile nella tabella",
            "paginate": {
                "first": "Primo",
                "previous": "Precedente",
                "next": "Successivo",
                "last": "Ultimo"
            },
            "aria": {
                "sortAscending": ": attiva per ordinare la colonna in modo crescente",
                "sortDescending": ": attiva per ordinare la colonna in modo decrescente"
            }
        },
        responsive: true,
        pageLength: 10,
        order: [[7, 'desc']],
        drawCallback: function () {
            syncPartnerSelectAllCheckbox();
        }
    });

    jQuery('#partnersTable').on('change', '.partner-row-check', function () {
        const id = parseInt(this.value, 10);
        if (!id) {
            return;
        }
        if (this.checked) {
            selectedPartnerIds.add(id);
        } else {
            selectedPartnerIds.delete(id);
        }
        updatePartnerBulkToolbar();
        syncPartnerSelectAllCheckbox();
    });

    jQuery('#partner-select-all-page').on('change', function () {
        const checked = this.checked;
        jQuery('#partnersTable .partner-row-check').each(function () {
            const id = parseInt(this.value, 10);
            if (!id) {
                return;
            }
            this.checked = checked;
            if (checked) {
                selectedPartnerIds.add(id);
            } else {
                selectedPartnerIds.delete(id);
            }
        });
        updatePartnerBulkToolbar();
        syncPartnerSelectAllCheckbox();
    });

    jQuery('#partner-bulk-delete').on('click', function () {
        bulkDeletePartners();
    });

    updatePartnerBulkToolbar();
    } catch (error) {
        console.error('Error initializing DataTable:', error);
        alert('Errore nell\'inizializzazione della tabella. Ricarica la pagina.');
    }
}


// Inizializza quando tutto è pronto
jQuery(document).ready(function($) {
    console.log('jQuery ready, initializing DataTable...');
    
    // Verifica che DataTables sia disponibile
    if (typeof $.fn.DataTable !== 'undefined') {
        console.log('DataTables available, initializing...');
        initDataTable();
        loadPartnerStats();
    } else {
        console.error('DataTables not available');
        // Aspetta un po' e riprova
        setTimeout(function() {
            if (typeof $.fn.DataTable !== 'undefined') {
                console.log('DataTables now available, initializing...');
                initDataTable();
                loadPartnerStats();
            } else {
                console.error('DataTables still not available after timeout');
                alert('Errore: DataTables non caricato. Ricarica la pagina.');
            }
        }, 2000);
    }
});

// Carica statistiche partner
function loadPartnerStats() {
    fetch(ajax_object.ajax_url + '?action=opencomune_get_partners')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const partners = data.data;
                const total = partners.length;
                const pending = partners.filter(p => p.status === 'pending').length;
                const approved = partners.filter(p => p.status === 'approved').length;
                const rejected = partners.filter(p => p.status === 'rejected').length;
                
                document.getElementById('total-partners').textContent = total;
                document.getElementById('pending-partners').textContent = pending;
                document.getElementById('approved-partners').textContent = approved;
                document.getElementById('rejected-partners').textContent = rejected;
            }
        })
        .catch(error => console.error('Errore nel caricamento delle statistiche:', error));
}

// Azioni partner
function viewPartner(partnerId) {
    window.open(`<?php echo home_url('/dettaglio-partner/'); ?>?id=${partnerId}`, '_blank');
}

function editPartner(partnerId) {
    window.location.href = `<?php echo home_url('/dettaglio-partner/'); ?>?id=${partnerId}`;
}

function approvePartner(partnerId) {
    updatePartnerStatus(partnerId, 'approved');
}

function rejectPartner(partnerId) {
    updatePartnerStatus(partnerId, 'rejected');
}

function bulkDeletePartners() {
    const ids = Array.from(selectedPartnerIds);
    if (!ids.length) {
        return;
    }
    const label = ids.length === 1 ? '1 partner' : ids.length + ' partner';
    Swal.fire({
        title: 'Elimina selezionati',
        text: 'Eliminare ' + label + '? Questa azione non può essere annullata.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sì, elimina',
        cancelButtonText: 'Annulla',
        confirmButtonColor: '#dc2626'
    }).then(function (result) {
        if (!result.isConfirmed) {
            return;
        }
        const formData = new FormData();
        formData.append('action', 'opencomune_bulk_delete_partners');
        formData.append('_wpnonce', ajax_object.nonce);
        ids.forEach(function (id) {
            formData.append('ids[]', id);
        });
        fetch(ajax_object.ajax_url, {
            method: 'POST',
            body: formData
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    ids.forEach(function (id) { selectedPartnerIds.delete(id); });
                    updatePartnerBulkToolbar();
                    Swal.fire('Eliminati', data.data.message || 'Partner eliminati', 'success');
                    partnersTable.ajax.reload();
                    loadPartnerStats();
                } else {
                    Swal.fire('Errore', (data.data && data.data.message) ? data.data.message : 'Eliminazione non riuscita', 'error');
                }
            })
            .catch(function () {
                Swal.fire('Errore', 'Errore di rete durante l\'eliminazione', 'error');
            });
    });
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
                        partnersTable.ajax.reload();
                        loadPartnerStats();
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
                        partnersTable.ajax.reload();
                        loadPartnerStats();
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


// Mostra modal partner
function showPartnerModal(partner) {
    const modal = document.getElementById('partnerModal');
    const title = document.getElementById('partnerModalTitle');
    const content = document.getElementById('partnerModalContent');
    
    title.textContent = `Dettagli Partner - ${partner.nome_attivita}`;
    
    const statusConfig = {
        'pending': { class: 'status-pending', text: 'In attesa verifica' },
        'approved': { class: 'status-approved', text: 'Approvato' },
        'rejected': { class: 'status-rejected', text: 'Rifiutato' }
    };
    const status = statusConfig[partner.status] || { class: 'status-pending', text: partner.status };
    
    content.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Informazioni Attività</h4>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div><strong>Nome:</strong> ${partner.nome_attivita}</div>
                        <div><strong>Tipologia:</strong> ${partner.tipologia_attivita}</div>
                        <div><strong>P.IVA:</strong> ${partner.partita_iva}</div>
                        ${partner.numero_rea ? `<div><strong>REA:</strong> ${partner.numero_rea}</div>` : ''}
                        <div><strong>Status:</strong> <span class="status-badge ${status.class}">${status.text}</span></div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Descrizione</h4>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <p class="text-sm text-gray-700">${partner.descrizione_breve || 'Nessuna descrizione'}</p>
                    </div>
                </div>
            </div>
            
            <div class="space-y-4">
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Localizzazione</h4>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div><strong>Indirizzo:</strong> ${partner.indirizzo}</div>
                        <div><strong>CAP:</strong> ${partner.cap}</div>
                        <div><strong>Comune:</strong> ${partner.comune}</div>
                        ${partner.area_quartiere ? `<div><strong>Area:</strong> ${partner.area_quartiere}</div>` : ''}
                        ${partner.latitudine && partner.longitudine ? `
                            <div><strong>Coordinate:</strong> ${partner.latitudine}, ${partner.longitudine}</div>
                        ` : ''}
                    </div>
                </div>
                
                <div>
                    <h4 class="font-semibold text-gray-900 mb-2">Contatti</h4>
                    <div class="bg-gray-50 rounded-lg p-4 space-y-2">
                        <div><strong>Referente:</strong> ${partner.nome_referente}</div>
                        <div><strong>Ruolo:</strong> ${partner.ruolo_referente}</div>
                        <div><strong>Telefono:</strong> ${partner.telefono_attivita}</div>
                        <div><strong>Email:</strong> ${partner.email_ufficiale}</div>
                        ${partner.sito_web ? `<div><strong>Sito:</strong> <a href="${partner.sito_web}" target="_blank" class="text-blue-600 hover:underline">${partner.sito_web}</a></div>` : ''}
                        ${partner.social_media ? `<div><strong>Social:</strong> <a href="${partner.social_media}" target="_blank" class="text-blue-600 hover:underline">${partner.social_media}</a></div>` : ''}
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-6">
            <h4 class="font-semibold text-gray-900 mb-2">Note Amministrative</h4>
            <div class="bg-gray-50 rounded-lg p-4">
                <textarea id="admin-notes" class="w-full border border-gray-300 rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" rows="3" placeholder="Aggiungi note amministrative...">${partner.note_admin || ''}</textarea>
            </div>
        </div>
        
        <div class="mt-6 flex justify-end space-x-4">
            <button type="button" onclick="closePartnerModal()" class="btn-secondary">
                Chiudi
            </button>
            <button type="button" onclick="savePartnerNotes(${partner.id})" class="btn-primary">
                Salva Note
            </button>
        </div>
    `;
    
    modal.style.display = 'block';
}

// Chiudi modal partner
function closePartnerModal() {
    document.getElementById('partnerModal').style.display = 'none';
}

// Salva note partner
function savePartnerNotes(partnerId) {
    const notes = document.getElementById('admin-notes').value;
    
    fetch(ajax_object.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=opencomune_update_partner&id=${partnerId}&note_admin=${encodeURIComponent(notes)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Successo!', 'Note salvate con successo', 'success');
        } else {
            Swal.fire('Errore!', data.message || 'Errore nel salvataggio', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Errore!', 'Errore di connessione', 'error');
    });
}

// Approva partner
function approvePartner(partnerId) {
    Swal.fire({
        title: 'Conferma Approvazione',
        text: 'Sei sicuro di voler approvare questo partner?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#059669',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sì, approva',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            updatePartnerStatus(partnerId, 'approved');
        }
    });
}

// Rifiuta partner
function rejectPartner(partnerId) {
    Swal.fire({
        title: 'Conferma Rifiuto',
        text: 'Sei sicuro di voler rifiutare questo partner?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sì, rifiuta',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            updatePartnerStatus(partnerId, 'rejected');
        }
    });
}

// Aggiorna status partner
function updatePartnerStatus(partnerId, status) {
    fetch(ajax_object.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=opencomune_update_partner&id=${partnerId}&status=${status}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Aggiornato!', data.message, 'success');
            partnersTable.ajax.reload();
            loadPartnerStats();
        } else {
            Swal.fire('Errore!', data.message || 'Errore durante l\'aggiornamento', 'error');
        }
    })
    .catch(error => {
        Swal.fire('Errore!', 'Errore di connessione', 'error');
    });
}

// Modifica partner

// Elimina partner
function deletePartner(partnerId) {
    Swal.fire({
        title: 'Sei sicuro?',
        text: "Questo partner verrà eliminato definitivamente!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Sì, elimina!',
        cancelButtonText: 'Annulla'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch(ajax_object.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=opencomune_delete_partner&id=${partnerId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Eliminato!', 'Il partner è stato eliminato', 'success');
                    partnersTable.ajax.reload();
                    loadPartnerStats();
                } else {
                    Swal.fire('Errore!', data.message || 'Errore durante l\'eliminazione', 'error');
                }
            })
            .catch(error => {
                Swal.fire('Errore!', 'Errore di connessione', 'error');
            });
        }
    });
}

// Chiudi modal cliccando fuori
document.getElementById('partnerModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closePartnerModal();
    }
});
</script>

<?php opencomune_render_dashboard_shell_close(); ?>
