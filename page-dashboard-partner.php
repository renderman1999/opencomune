<?php
/* Template Name: Dashboard Partner */
if (!opencomune_user_can_access_dashboard()) {
    wp_safe_redirect(home_url());
    exit;
}

$oc_dashboard_is_admin = opencomune_user_can_manage_all_esperienze();
$oc_dashboard_welcome_name = opencomune_get_partner_welcome_name();
$oc_dashboard_hero_bg = opencomune_get_dashboard_hero_background_url();
$oc_dashboard_hero_lead = $oc_dashboard_is_admin
    ? __('Ecco cosa succede oggi sulle esperienze della piattaforma.', 'opencomune')
    : __('Ecco cosa succede oggi sulle tue esperienze.', 'opencomune');
$oc_dashboard_new_experience_url = home_url('/nuova-esperienza/');

wp_enqueue_script('sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11', array(), '11.0.0', true);
wp_enqueue_style('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', array(), '1.9.4');
wp_enqueue_script('leaflet', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', array(), '1.9.4', true);
opencomune_enqueue_dashboard_partner_home_assets();

opencomune_render_dashboard_shell_open();
?>

<style>
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

#partnerMapModal .leaflet-container {
    width: 100%;
    height: 100%;
    min-height: 420px;
    border-radius: 8px;
}

#partner-map-canvas {
    height: 60vh;
    min-height: 420px;
}

.partner-map-popup .leaflet-popup-content-wrapper {
    border-radius: 8px;
}

#partnerMapEmpty {
    display: none;
}
</style>

<div class="oc-dashboard-app" id="dashboardContent">
        <?php opencomune_render_dashboard_sidebar(); ?>
        <main class="oc-dashboard-main">
        <section id="section-overview" class="oc-dashboard-section oc-dashboard-section--hero">
            <div class="oc-dashboard-hero"<?php echo $oc_dashboard_hero_bg !== '' ? ' style="--oc-dashboard-hero-bg: url(\'' . esc_url($oc_dashboard_hero_bg) . '\');"' : ''; ?>>
                <div class="oc-dashboard-hero__overlay" aria-hidden="true"></div>
                <div class="oc-dashboard-hero__content">
                    <div class="oc-dashboard-hero__head">
                        <div class="oc-dashboard-hero__intro">
                            <h1 class="oc-dashboard-hero__title">
                                <?php
                                if ($oc_dashboard_welcome_name !== '') {
                                    printf(
                                        /* translators: %s: user first name */
                                        esc_html__('Benvenuto, %s!', 'opencomune'),
                                        esc_html($oc_dashboard_welcome_name)
                                    );
                                } else {
                                    esc_html_e('Benvenuto!', 'opencomune');
                                }
                                ?>
                            </h1>
                            <p class="oc-dashboard-hero__lead"><?php echo esc_html($oc_dashboard_hero_lead); ?></p>
                        </div>
                        <div class="oc-dashboard-hero__actions">
                            <button type="button" id="openPartnerMapBtn" class="oc-dashboard-hero__btn oc-dashboard-hero__btn--ghost">
                                <i class="bi bi-map" aria-hidden="true"></i>
                                <?php esc_html_e('Mappa esperienze', 'opencomune'); ?>
                            </button>
                            <a href="<?php echo esc_url($oc_dashboard_new_experience_url); ?>" class="oc-dashboard-hero__btn oc-dashboard-hero__btn--primary">
                                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                <?php esc_html_e('Crea esperienza', 'opencomune'); ?>
                            </a>
                        </div>
                    </div>

                    <div class="oc-dashboard-hero__cards">
                    <div class="oc-dashboard-hero__card">
                        <p class="oc-dashboard-hero__card-kicker"><?php esc_html_e('Totale esperienze', 'opencomune'); ?></p>
                        <div class="oc-dashboard-hero__stats">
                            <div class="oc-dashboard-hero__stat">
                                <span class="oc-dashboard-hero__stat-value" id="totalEsperienze">-</span>
                                <span class="oc-dashboard-hero__stat-label"><?php esc_html_e('Totale', 'opencomune'); ?></span>
                            </div>
                            <div class="oc-dashboard-hero__stat">
                                <span class="oc-dashboard-hero__stat-value" id="esperienzePubblicate">-</span>
                                <span class="oc-dashboard-hero__stat-label"><?php esc_html_e('Pubblicate', 'opencomune'); ?></span>
                            </div>
                            <div class="oc-dashboard-hero__stat">
                                <span class="oc-dashboard-hero__stat-value" id="esperienzeBozza">-</span>
                                <span class="oc-dashboard-hero__stat-label"><?php esc_html_e('In bozza', 'opencomune'); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($oc_dashboard_is_admin) : ?>
                    <div class="oc-dashboard-hero__card">
                        <p class="oc-dashboard-hero__card-kicker"><?php esc_html_e('Utenti partner', 'opencomune'); ?></p>
                        <div class="oc-dashboard-hero__stats oc-dashboard-hero__stats--duo">
                            <div class="oc-dashboard-hero__stat">
                                <span class="oc-dashboard-hero__stat-value" id="partnersActive">-</span>
                                <span class="oc-dashboard-hero__stat-label"><?php esc_html_e('Partner attivi', 'opencomune'); ?></span>
                            </div>
                            <div class="oc-dashboard-hero__stat">
                                <span class="oc-dashboard-hero__stat-value" id="partnersPending">-</span>
                                <span class="oc-dashboard-hero__stat-label"><?php esc_html_e('Da autorizzare', 'opencomune'); ?></span>
                            </div>
                        </div>
                        <p class="oc-dashboard-hero__card-foot">
                            <a href="<?php echo esc_url(home_url('/gestione-partner/')); ?>" class="oc-dashboard-hero__card-link">
                                <?php esc_html_e('Gestione partner', 'opencomune'); ?>
                                <i class="bi bi-arrow-right" aria-hidden="true"></i>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="oc-dashboard-home">
                <div class="oc-dashboard-home-block oc-dashboard-home-block--performance">
                    <div class="oc-dashboard-home-block__head">
                        <div class="oc-dashboard-home-block__intro">
                            <h2 class="oc-dashboard-home-block__title"><?php esc_html_e('Le tue performance', 'opencomune'); ?></h2>
                            <p class="oc-dashboard-home-block__lead">
                                <?php echo $oc_dashboard_is_admin
                                    ? esc_html__('Prenotazioni, partecipanti e valore prenotazioni su tutta la piattaforma.', 'opencomune')
                                    : esc_html__('Prenotazioni, partecipanti e valore prenotazioni delle tue esperienze.', 'opencomune'); ?>
                            </p>
                        </div>
                        <label class="oc-dashboard-home-period">
                            <span class="screen-reader-text"><?php esc_html_e('Periodo', 'opencomune'); ?></span>
                            <select id="dashboardPerformanceDays" class="oc-dashboard-home-period__select">
                                <option value="14" selected><?php esc_html_e('Ultimi 14 giorni', 'opencomune'); ?></option>
                                <option value="30"><?php esc_html_e('Ultimi 30 giorni', 'opencomune'); ?></option>
                                <option value="90"><?php esc_html_e('Ultimi 90 giorni', 'opencomune'); ?></option>
                            </select>
                        </label>
                    </div>

                    <div class="oc-dashboard-home-metrics" aria-live="polite">
                        <article class="oc-dashboard-metric">
                            <h3 class="oc-dashboard-metric__label"><?php esc_html_e('Prenotazioni', 'opencomune'); ?></h3>
                            <p class="oc-dashboard-metric__value" id="perfBookings">-</p>
                            <div class="oc-dashboard-metric__spark" aria-hidden="true"></div>
                        </article>
                        <article class="oc-dashboard-metric">
                            <h3 class="oc-dashboard-metric__label"><?php esc_html_e('Partecipanti', 'opencomune'); ?></h3>
                            <p class="oc-dashboard-metric__value" id="perfPassengers">-</p>
                            <div class="oc-dashboard-metric__spark" aria-hidden="true"></div>
                        </article>
                        <article class="oc-dashboard-metric">
                            <h3 class="oc-dashboard-metric__label"><?php esc_html_e('Valore prenotazioni', 'opencomune'); ?></h3>
                            <p class="oc-dashboard-metric__value" id="perfGross">-</p>
                            <div class="oc-dashboard-metric__spark" aria-hidden="true"></div>
                        </article>
                        <article class="oc-dashboard-metric oc-dashboard-metric--top">
                            <h3 class="oc-dashboard-metric__label"><?php esc_html_e('Esperienze top', 'opencomune'); ?></h3>
                            <div class="oc-dashboard-metric__top" id="perfTopExperiences">
                                <p class="oc-dashboard-metric__empty"><?php esc_html_e('Caricamento…', 'opencomune'); ?></p>
                            </div>
                        </article>
                    </div>
                </div>

                <div class="oc-dashboard-home-block oc-dashboard-home-block--discover">
                    <div class="oc-dashboard-home-block__intro oc-dashboard-home-block__intro--spaced">
                        <h2 class="oc-dashboard-home-block__title"><?php esc_html_e('Scopri di più', 'opencomune'); ?></h2>
                        <p class="oc-dashboard-home-block__lead">
                            <?php esc_html_e('Strumenti e risorse per vendere di più e gestire meglio le esperienze.', 'opencomune'); ?>
                        </p>
                    </div>

                    <div class="oc-dashboard-promo-grid">
                        <?php
                        foreach (opencomune_get_dashboard_home_promo_cards() as $oc_promo_card) {
                            opencomune_render_dashboard_home_promo_card($oc_promo_card);
                        }
                        ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="section-prenotazioni" class="oc-dashboard-section oc-dashboard-panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h2 class="oc-dashboard-section__title mb-0"><?php esc_html_e('Prenotazioni', 'opencomune'); ?></h2>
                <p class="text-sm text-gray-500 mb-0 w-full sm:w-auto">
                    <?php echo $oc_dashboard_is_admin
                        ? esc_html__('Tutte le prenotazioni nel sistema.', 'opencomune')
                        : esc_html__('Prenotazioni ricevute per le tue esperienze.', 'opencomune'); ?>
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 mb-6">
                <select id="filterBookingsDays" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value="30"><?php esc_html_e('Ultimi 30 giorni', 'opencomune'); ?></option>
                    <option value="90" selected><?php esc_html_e('Ultimi 90 giorni', 'opencomune'); ?></option>
                    <option value="365"><?php esc_html_e('Ultimo anno', 'opencomune'); ?></option>
                    <option value="0"><?php esc_html_e('Tutte', 'opencomune'); ?></option>
                </select>
                <select id="filterBookingsStatus" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm">
                    <option value=""><?php esc_html_e('Tutti gli stati', 'opencomune'); ?></option>
                    <option value="confermata"><?php esc_html_e('Confermate', 'opencomune'); ?></option>
                    <option value="pending_payment"><?php esc_html_e('In attesa pagamento', 'opencomune'); ?></option>
                    <option value="pending_approval"><?php esc_html_e('In attesa approvazione', 'opencomune'); ?></option>
                    <option value="cancellata"><?php esc_html_e('Cancellate', 'opencomune'); ?></option>
                </select>
                <input type="search" id="filterBookingsGuest" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 text-sm min-w-[200px]"
                       placeholder="<?php esc_attr_e('Nome cliente', 'opencomune'); ?>" autocomplete="off">
                <button id="refreshBookings" type="button" class="btn-primary whitespace-nowrap">
                    <i class="bi bi-arrow-clockwise mr-2"></i>
                    <?php esc_html_e('Aggiorna', 'opencomune'); ?>
                </button>
            </div>

            <div class="oc-dashboard-table-wrap oc-dashboard-bookings-dt p-6">
                <table id="bookingsTable" class="oc-dashboard-table oc-dashboard-table--bookings display w-full">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e('ID', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Esperienza', 'opencomune'); ?></th>
                            <?php if ($oc_dashboard_is_admin) : ?>
                                <th scope="col"><?php esc_html_e('Partner', 'opencomune'); ?></th>
                            <?php endif; ?>
                            <th scope="col"><?php esc_html_e('Data/ora', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Cliente', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Partecipanti', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Stato', 'opencomune'); ?></th>
                            <th scope="col"><?php esc_html_e('Prenotato il', 'opencomune'); ?></th>
                            <th scope="col"><span class="screen-reader-text"><?php esc_html_e('Azioni', 'opencomune'); ?></span></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
        </main>
</div>

<!-- Modal Mappa Esperienze Partner -->
<div id="partnerMapModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 z-50 p-4 md:p-8" style="display: none;">
    <div class="relative mx-auto w-full max-w-6xl h-full max-h-[90vh] bg-white rounded-lg shadow-lg flex flex-col overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Mappa esperienze</h3>
                <p class="text-sm text-gray-500">Visualizza sulla mappa le tue esperienze.</p>
            </div>
            <button type="button" id="closePartnerMapModal" class="text-gray-400 hover:text-gray-600" aria-label="Chiudi mappa">
                <i class="bi bi-x-lg text-xl"></i>
            </button>
        </div>
        <div class="relative flex-1 p-4 md:p-5">
            <div id="partnerMapLoading" class="absolute inset-0 flex items-center justify-center bg-white bg-opacity-90 z-10">
                <div class="text-center">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-500">Caricamento mappa...</p>
                </div>
            </div>
            <div id="partnerMapEmpty" class="absolute inset-0 flex items-center justify-center bg-gray-50 z-10 rounded-lg border border-dashed border-gray-300">
                <div class="text-center px-6">
                    <i class="bi bi-geo-alt text-4xl text-gray-400"></i>
                    <p class="mt-3 text-gray-600 font-medium">Nessuna esperienza con coordinate disponibile</p>
                    <p class="mt-1 text-sm text-gray-500">Aggiungi latitudine e longitudine alle tue esperienze per visualizzarle sulla mappa.</p>
                </div>
            </div>
            <div id="partner-map-canvas"></div>
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
    loadDashboardStats();

    const openPartnerMapBtn = document.getElementById('openPartnerMapBtn');
    const closePartnerMapModalBtn = document.getElementById('closePartnerMapModal');
    const partnerMapModal = document.getElementById('partnerMapModal');

    if (openPartnerMapBtn) {
        openPartnerMapBtn.addEventListener('click', openPartnerMapModal);
    }
    if (closePartnerMapModalBtn) {
        closePartnerMapModalBtn.addEventListener('click', closePartnerMapModal);
    }
    if (partnerMapModal) {
        partnerMapModal.addEventListener('click', function(e) {
            if (e.target === partnerMapModal) {
                closePartnerMapModal();
            }
        });
    }
});

function loadDashboardStats() {
    fetch(ajax_object.ajax_url + '?action=opencomune_get_dashboard_stats')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('totalEsperienze').textContent = data.data.total_esperienze;
                document.getElementById('esperienzePubblicate').textContent = data.data.pubblicate;
                document.getElementById('esperienzeBozza').textContent = data.data.bozza;
                const partnersActive = document.getElementById('partnersActive');
                const partnersPending = document.getElementById('partnersPending');
                if (partnersActive && typeof data.data.partners_active !== 'undefined') {
                    partnersActive.textContent = data.data.partners_active;
                }
                if (partnersPending && typeof data.data.partners_pending !== 'undefined') {
                    partnersPending.textContent = data.data.partners_pending;
                }
            }
        })
        .catch(error => console.error('Errore nel caricamento delle statistiche:', error));
}

let partnerMapInstance = null;
let partnerMapMarkers = [];
let partnerMapLayerGroup = null;

function openPartnerMapModal() {
    const modal = document.getElementById('partnerMapModal');
    if (!modal) {
        return;
    }

    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    if (typeof L === 'undefined') {
        Swal.fire('Errore', 'Impossibile caricare la libreria mappa.', 'error');
        return;
    }

    if (!partnerMapInstance) {
        initPartnerMap();
    }

    setTimeout(function() {
        if (partnerMapInstance) {
            partnerMapInstance.invalidateSize();
        }
        loadPartnerMapMarkers();
    }, 150);
}

function closePartnerMapModal() {
    const modal = document.getElementById('partnerMapModal');
    if (modal) {
        modal.style.display = 'none';
    }
    document.body.style.overflow = '';
}

function initPartnerMap() {
    const canvas = document.getElementById('partner-map-canvas');
    if (!canvas || partnerMapInstance) {
        return;
    }

    partnerMapInstance = L.map('partner-map-canvas', {
        center: [41.8719, 12.5674],
        zoom: 6,
        scrollWheelZoom: true,
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19,
    }).addTo(partnerMapInstance);

    partnerMapLayerGroup = L.layerGroup().addTo(partnerMapInstance);
}

function buildPartnerMapPopup(tour) {
    const image = tour.tour_img || 'https://via.placeholder.com/60x60?text=Esperienza';
    const viewLink = tour.status === 'publish'
        ? `<a href="${tour.link}" target="_blank" rel="noopener noreferrer" style="background:#2563eb;color:#fff;padding:6px 12px;border-radius:4px;text-decoration:none;font-size:12px;">Visualizza</a>`
        : '';
    const editLink = tour.edit_url
        ? `<a href="${tour.edit_url}" style="background:#4b5563;color:#fff;padding:6px 12px;border-radius:4px;text-decoration:none;font-size:12px;">Modifica</a>`
        : '';

    return `
        <div style="min-width:240px;max-width:280px;">
            <div style="display:flex;gap:12px;margin-bottom:10px;">
                <img src="${image}" alt="" style="width:56px;height:56px;border-radius:8px;object-fit:cover;">
                <div style="flex:1;">
                    <strong style="display:block;font-size:15px;line-height:1.3;">${tour.titolo}</strong>
                    <span style="display:inline-block;margin-top:6px;font-size:11px;background:#dbeafe;color:#1e40af;padding:2px 8px;border-radius:999px;">${tour.status_label || tour.status}</span>
                </div>
            </div>
            <div style="font-size:13px;color:#4b5563;margin-bottom:8px;">${tour.citta || ''}</div>
            <div style="font-size:12px;color:#6b7280;margin-bottom:12px;">${tour.categoria || ''}</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">${viewLink}${editLink}</div>
        </div>
    `;
}

function loadPartnerMapMarkers() {
    const loading = document.getElementById('partnerMapLoading');
    const empty = document.getElementById('partnerMapEmpty');
    const canvas = document.getElementById('partner-map-canvas');

    if (loading) {
        loading.style.display = 'flex';
    }
    if (empty) {
        empty.style.display = 'none';
    }
    if (canvas) {
        canvas.style.visibility = 'hidden';
    }

    fetch(ajax_object.ajax_url + '?action=opencomune_get_partner_tours_map')
        .then(response => response.json())
        .then(data => {
            if (loading) {
                loading.style.display = 'none';
            }
            if (canvas) {
                canvas.style.visibility = 'visible';
            }

            if (!partnerMapInstance || !partnerMapLayerGroup) {
                return;
            }

            partnerMapLayerGroup.clearLayers();
            partnerMapMarkers = [];

            const tours = data.success ? (data.data || []) : [];

            if (!tours.length) {
                if (empty) {
                    empty.style.display = 'flex';
                }
                partnerMapInstance.setView([41.8719, 12.5674], 6);
                return;
            }

            tours.forEach(function(tour) {
                const marker = L.marker([parseFloat(tour.lat), parseFloat(tour.lon)], {
                    title: tour.titolo,
                });
                marker.bindPopup(buildPartnerMapPopup(tour), {
                    maxWidth: 320,
                    className: 'partner-map-popup',
                });
                marker.addTo(partnerMapLayerGroup);
                partnerMapMarkers.push(marker);
            });

            const group = L.featureGroup(partnerMapMarkers);
            partnerMapInstance.fitBounds(group.getBounds().pad(0.15));
        })
        .catch(function(error) {
            if (loading) {
                loading.style.display = 'none';
            }
            if (canvas) {
                canvas.style.visibility = 'visible';
            }
            console.error('Errore caricamento mappa partner:', error);
            Swal.fire('Errore', 'Impossibile caricare le esperienze sulla mappa.', 'error');
        });
}

</script>

<?php opencomune_render_dashboard_shell_close(); ?>
