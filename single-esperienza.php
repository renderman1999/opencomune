<?php
get_header();
if (have_posts()) : while (have_posts()) : the_post();
    $id = get_the_ID();
    $img = get_the_post_thumbnail_url($id, 'full');
    $titolo = get_the_title();
    $durata = get_post_meta($id, 'tour_duration', true);
    $difficolta = get_post_meta($id, 'tour_difficulty', true);
    
    $desc_breve = get_the_excerpt();
    $desc_completa = get_the_content();
    $citta = get_post_meta($id, 'citta', true);
    $lingue = get_post_meta($id, 'tour_languages', true);
    // Recupera le categorie dalla tassonomia personalizzata
    $categorie_arr = wp_get_post_terms($id, 'categorie_esperienze', ['fields' => 'names']);
    
    // Fallback: se non ci sono categorie nella tassonomia, prova con i tag
    if (empty($categorie_arr)) {
        $categorie_arr = wp_get_post_terms($id, 'post_tag', ['fields' => 'names']);
    }
    
    // Fallback: se non ci sono tag, prova dal meta field
    if (empty($categorie_arr)) {
        $categoria = get_post_meta($id, 'categoria', true);
        $categorie_arr = is_array($categoria) ? $categoria : [];
    }
    
    $prezzo = get_post_meta($id, 'tour_price', true);
    $include = get_post_meta($id, 'tour_whats_included', true);
    $non_include = get_post_meta($id, 'tour_whats_not_included', true);
    $itinerario = get_post_meta($id, 'tour_itinerary', true);
    $note = get_post_meta($id, 'tour_requirements', true);
    $indirizzo_ritrovo = get_post_meta($id, 'tour_meeting_point', true);
$lat = get_post_meta($id, 'tour_latitude', true);
$lon = get_post_meta($id, 'tour_longitude', true);
    $difficolta_label = $difficolta ? ucfirst($difficolta) : '';
    $lingue_arr = is_array($lingue) ? $lingue : (is_string($lingue) ? explode(',', $lingue) : []);
    // --- Organizzato da Ufficio Turistico ---
    $ufficio_nome = get_option('opencomune_nome_comune', 'Ufficio Turistico');
    $ufficio_email = get_option('opencomune_email_ufficio', '');
    $ufficio_telefono = get_option('opencomune_telefono_ufficio', '');
?>

<main class="max-w-7xl mx-auto p-4">
    <!-- HERO -->
    <div class="rounded-xl overflow-hidden shadow mb-8">
        <?php if ($img): ?>
            <div class="relative h-72 md:h-96 w-full bg-gray-200">
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($titolo); ?>" class="object-cover w-full h-full" loading="lazy">
                <button id="scroll-gallery-btn" class="absolute top-4 right-4 bg-white/80 px-4 py-2 rounded shadow text-blue-700 font-semibold text-sm"><?php _e('Visualizza foto', 'opencomune'); ?></button>
            </div>
        <?php endif; ?>
        <div class="bg-white p-6 md:p-8 flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex-1">
                <h1 class="text-3xl font-bold mb-2"><?php echo esc_html($titolo); ?></h1>
                <div class="flex items-center gap-4 text-gray-600 mb-2">
                    <?php if ($durata): ?><span><svg class="inline w-5 h-5 mr-1 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <?php echo esc_html($durata); ?></span><?php endif; ?>
                    <?php if ($difficolta_label): ?><span><svg class="inline w-5 h-5 mr-1 text-green-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20l9-5-9-5-9 5 9 5z"/><path d="M12 12V4"/></svg> <?php echo esc_html($difficolta_label); ?></span><?php endif; ?>
                </div>
                <div class="text-lg text-gray-700 mb-2"><?php echo esc_html($desc_breve); ?></div>
            </div>
            <?php
            if (is_user_logged_in() && current_user_can('editor_turistico') && get_current_user_id() === (int) get_post_field('post_author', $id)) {
                $edit_url = site_url('/modifica-esperienza/?id=' . $id);
                echo '<a href="' . esc_url($edit_url) . '" class="ml-auto bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-bold px-5 py-2 rounded shadow transition">' . esc_html__('Modifica esperienza', 'opencomune') . '</a>';
            }
            ?>
        </div>
    </div>
    <!-- GALLERIA MASONRY -->
    <?php 
    $gallery_ids = get_post_meta($id, 'galleria', true);
    if (is_array($gallery_ids) && count($gallery_ids) > 0): ?>
    <div id="tour-gallery" class="mb-10">
        <div class="flex justify-between items-center mb-2 mx-auto">
            <h2 class="text-xl font-bold"><?php _e('Galleria foto', 'opencomune'); ?></h2>
            <a href="#" id="view-photos-btn" class="hidden md:inline-block bg-white border border-blue-600 text-blue-700 px-4 py-2 rounded font-semibold text-sm shadow hover:bg-blue-50"><?php _e('Visualizza foto', 'opencomune'); ?></a>
        </div>
        <!-- Swiper mobile -->
        <div class="md:hidden overflow-hidden">
            <div class="swiper-container relative">
                <div class="swiper-wrapper">
                    <?php foreach($gallery_ids as $gid): 
                        $img_url = wp_get_attachment_image_url($gid, 'large');
                        if ($img_url): ?>
                        <div class="swiper-slide flex justify-center items-center">
                            <img src="<?php echo esc_url($img_url); ?>" alt="" class="object-contain rounded-lg max-h-72 w-auto max-w-full mx-auto" loading="lazy">
                        </div>
                    <?php endif; endforeach; ?>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        <!-- Masonry desktop -->
        <div id="gallery-skeleton" class="relative mx-auto hidden md:block">
            <?php for($r=0;$r<3;$r++): ?>
            <div class="flex gap-2 mb-2">
                <?php for($c=0;$c<3;$c++): ?>
                <div class="flex-1 overflow-hidden rounded-lg bg-gray-200 animate-pulse" style="height:180px;"></div>
                <?php endfor; ?>
            </div>
            <?php endfor; ?>
        </div>
        <div id="macy-gallery" class="macy-gallery mx-auto grid gap-2 opacity-0 transition-opacity duration-500 hidden md:grid">
            <?php foreach($gallery_ids as $gid): 
                $img_url = wp_get_attachment_image_url($gid, 'large');
                if ($img_url): ?>
                <div class="overflow-hidden rounded-lg"><img src="<?php echo esc_url($img_url); ?>" alt="" class="w-full h-auto object-cover" loading="lazy"></div>
            <?php endif; endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <!-- MAIN + SIDEBAR -->
    <div class="flex flex-col md:flex-row gap-8">
        <!-- MAIN CONTENT -->
        <div class="flex-1 min-w-0">
            <!-- Tabs -->
            <div class="border-b mb-4 flex gap-4">
                <button class="tab-btn border-b-2 border-blue-600 text-blue-700 px-4 py-2 font-semibold" data-tab="descrizione"><?php _e('Descrizione', 'opencomune'); ?></button>
                <button class="tab-btn border-b-2 border-transparent text-gray-600 px-4 py-2 font-semibold" data-tab="ritrovo"><?php _e('Punti di ritrovo', 'opencomune'); ?></button>
            </div>
            <div id="tab-descrizione" class="tab-pane">
                <div class="prose max-w-none mb-4"><?php echo wpautop($desc_completa); ?></div>
                <?php if ($include): ?><div class="mb-2"><b><?php _e('Cosa include:', 'opencomune'); ?></b> <?php echo esc_html($include); ?></div><?php endif; ?>
                <?php if ($non_include): ?><div class="mb-2"><b><?php _e('Cosa non è incluso:', 'opencomune'); ?></b> <?php echo esc_html($non_include); ?></div><?php endif; ?>
                <?php if ($itinerario): ?><div class="mb-2"><b><?php _e('Itinerario:', 'opencomune'); ?></b> <?php echo esc_html($itinerario); ?></div><?php endif; ?>
                <?php if ($note): ?><div class="mb-2"><b><?php _e('Note importanti:', 'opencomune'); ?></b> <?php echo esc_html($note); ?></div><?php endif; ?>
            </div>
            <div id="tab-ritrovo" class="tab-pane hidden">
                <div class="mb-4">
                    <h3 class="font-bold text-lg mb-3"><?php _e('Punto di ritrovo', 'opencomune'); ?></h3>
                    <div class="mb-4">
                        <b><?php _e('Indirizzo:', 'opencomune'); ?></b> 
                        <span class="text-gray-700"><?php echo esc_html($indirizzo_ritrovo); ?></span>
                    </div>
                    
                    <?php if ($lat && $lon): ?>
                    <!-- Mappa Google Maps -->
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2"><?php _e('Posizione sulla mappa', 'opencomune'); ?></h4>
                        <div id="map-ritrovo" class="w-full h-64 rounded-lg border border-gray-300" style="min-height: 200px;"></div>
                    </div>
                    <?php else: ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <p class="text-yellow-800 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            <?php _e('Le coordinate del punto di ritrovo non sono state configurate. Contatta l\'ufficio turistico per maggiori informazioni.', 'opencomune'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Informazioni aggiuntive -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-semibold text-blue-800 mb-2"><?php _e('Come raggiungere il punto di ritrovo', 'opencomune'); ?></h4>
                        <ul class="text-blue-700 text-sm space-y-1">
                            <li>• <?php _e('Presentati 10 minuti prima dell\'orario di inizio', 'opencomune'); ?></li>
                            <li>• <?php _e('Cerca il referente dell\'ufficio turistico', 'opencomune'); ?></li>
                            <li>• <?php _e('In caso di difficoltà, contatta l\'ufficio turistico', 'opencomune'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php _e('Si prega di notare', 'opencomune'); ?></h3>
                <ul class="list-disc ml-6 text-gray-700">
                    <li><?php _e('Trasporti pubblici nelle vicinanze', 'opencomune'); ?></li>
                    <li><?php _e('Come raggiungere il punto di incontro', 'opencomune'); ?></li>
                </ul>
            </div>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php _e('Politica sulle disdette', 'opencomune'); ?></h3>
                <ul class="list-disc ml-6 text-gray-700">
                    <li><?php _e('Le prenotazioni non sono rimborsabili. Tutte le vendite sono definitive.', 'opencomune'); ?></li>
                </ul>
            </div>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php _e('Ulteriori informazioni', 'opencomune'); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">
                    <div><b><?php _e('Tipo di esperienza', 'opencomune'); ?>:</b> <?php _e('Tour giornaliero / attività', 'opencomune'); ?></div>
                    <div><b><?php _e('Prenotare in anticipo', 'opencomune'); ?>:</b> <?php _e('Scadenza', 'opencomune'); ?>: 1 ora</div>
                    <div><b><?php _e('Durata', 'opencomune'); ?>:</b> <?php echo esc_html($durata); ?></div>
                    <div><b><?php _e('Difficoltà', 'opencomune'); ?>:</b> <?php echo esc_html($difficolta_label); ?></div>
                    <div><b><?php _e('Categorie', 'opencomune'); ?>:</b> 
                        <?php 
                        if (!empty($categorie_arr)) {
                            foreach($categorie_arr as $c) {
                                echo '<span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 mr-1 text-xs">'.esc_html($c).'</span>';
                            }
                        } else {
                            echo '<span class="text-gray-500">Nessuna categoria</span>';
                        }
                        ?>
                    </div>
                    <div><b><?php _e('Lingue disponibili', 'opencomune'); ?>:</b> <?php foreach($lingue_arr as $l) echo '<span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 mr-1 text-xs">'.esc_html(trim($l)).'</span>'; ?></div>
                </div>
            </div>
        </div>
        <!-- SIDEBAR -->
        <aside class="w-full md:w-96 flex-shrink-0 md:sticky md:top-8 h-fit bg-white rounded-xl shadow p-6">
            <h3 class="font-bold text-lg mb-4"><?php _e('Partecipanti', 'opencomune'); ?></h3>
            <div class="flex items-center gap-4 mb-6">
                <div>
                    <span class="block text-gray-600 text-sm"><?php _e('Adulti', 'opencomune'); ?></span>
                    <div class="flex items-center gap-2 mt-1">
                        <button id="adulti-minus" class="px-2 py-1 bg-gray-200 rounded">-</button>
                        <span id="adulti-count">1</span>
                        <button id="adulti-plus" class="px-2 py-1 bg-gray-200 rounded">+</button>
                    </div>
                </div>
                <div>
                    <span class="block text-gray-600 text-sm"><?php _e('Bambini', 'opencomune'); ?></span>
                    <div class="flex items-center gap-2 mt-1">
                        <button class="px-2 py-1 bg-gray-200 rounded" disabled>-</button>
                        <span id="bambini-count">0</span>
                        <button class="px-2 py-1 bg-gray-200 rounded" disabled>+</button>
                    </div>
                </div>
            </div>
            <h3 class="font-bold text-lg mb-4"><?php _e('Scegli una data', 'opencomune'); ?></h3>
            <div class="mb-6">
                <div id="tour-datepicker" class=" "></div>
            </div>
            
            <!-- Sezione orari disponibili -->
            <div id="orari-disponibili" class="mb-6 hidden">
                <h4 class="font-bold text-lg mb-3">Orari disponibili per <span id="data-selezionata"></span></h4>
                <div id="lista-orari" class="space-y-2 mb-4">
                    <!-- Gli orari verranno caricati qui dinamicamente -->
                </div>
                
                <!-- Pulsante Prenota ora (nascosto inizialmente) -->
                <div id="prenota-section" class="hidden">
                    <button id="prenota-ora-btn" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                        REQUEST
                    </button>
                </div>
            </div>
            <h3 class="font-bold text-lg mb-4"><?php _e('Riepilogo Ordine', 'opencomune'); ?></h3>
            <div class="bg-gray-50 rounded p-4">
                <div class="font-semibold mb-2" id="riepilogo-titolo"><?php echo esc_html($titolo); ?></div>
                <div class="text-sm text-gray-600 mb-2" id="riepilogo-adulti"><?php _e('Adulti', 'opencomune'); ?>: <span id="riepilogo-adulti-count">1</span></div>
                <div class="text-sm text-gray-600 mb-2"><?php _e('Totale', 'opencomune'); ?>: <span class="font-bold text-2xl text-blue-700" id="riepilogo-totale">€<?php echo esc_html($prezzo); ?></span></div>
            </div>
            <?php if (is_user_logged_in()) : ?>
            <button id="open-review-modal" class="w-full mt-4 bg-blue-600 text-white py-2 rounded font-semibold hover:bg-blue-700 transition">Lascia una recensione</button>
            <?php endif; ?>
        </aside>
    </div>
</main>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/macy@2"></script>
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/it.js"></script>
<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr(opencomune_get_google_maps_api_key()); ?>"></script>
<style>
.swiper-pagination-bullet {
  background: #fff !important;
  opacity: 0.3;
}
.swiper-pagination-bullet-active {
  opacity: 1;
}

/* Stili per il modal di prenotazione */
#prenota-modal {
  overflow-y: auto;
  overflow-x: hidden;
}

#prenota-modal .bg-white {
  overflow: hidden;
}

.swiper-container-tour-modal {
  overflow: hidden !important;
}

.swiper-container-tour-modal .swiper-wrapper {
  overflow: visible;
}

.swiper-container-tour-modal .swiper-slide {
  overflow: hidden;
}

/* Nascondi barra di scorrimento per webkit browsers */
#prenota-modal::-webkit-scrollbar {
  width: 0px;
  background: transparent;
}

#prenota-modal .bg-white::-webkit-scrollbar {
  width: 0px;
  background: transparent;
}
</style>
<script>
// Tabs switching
const tabBtns = document.querySelectorAll('.tab-btn');
tabBtns.forEach(btn => btn.addEventListener('click', function() {
    tabBtns.forEach(b => b.classList.remove('border-blue-600', 'text-blue-700'));
    this.classList.add('border-blue-600', 'text-blue-700');
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    document.getElementById('tab-' + this.dataset.tab).classList.remove('hidden');
}));
// Macy.js masonry gallery + skeleton
if (document.getElementById('macy-gallery')) {
    const macyGallery = document.getElementById('macy-gallery');
    const skeleton = document.getElementById('gallery-skeleton');
    const imgs = macyGallery.querySelectorAll('img');
    let loaded = 0;
    function showGallery() {
        if (skeleton) skeleton.style.display = 'none';
        macyGallery.style.opacity = 1;
        new Macy({
            container: '#macy-gallery',
            trueOrder: false,
            waitForImages: true,
            margin: 8,
            columns: 3,
            breakAt: {
                900: 3
            }
        });
    }
    if (imgs.length === 0) {
        showGallery();
    } else {
        imgs.forEach(img => {
            if (img.complete) {
                loaded++;
                if (loaded === imgs.length) showGallery();
            } else {
                img.addEventListener('load', function() {
                    loaded++;
                    if (loaded === imgs.length) showGallery();
                });
                img.addEventListener('error', function() {
                    loaded++;
                    if (loaded === imgs.length) showGallery();
                });
            }
        });
    }
}
// Scroll to gallery on button click
const scrollBtn = document.getElementById('scroll-gallery-btn');
if (scrollBtn && document.getElementById('tour-gallery')) {
    scrollBtn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('tour-gallery').scrollIntoView({behavior: 'smooth'});
    });
}
const viewPhotosBtn = document.getElementById('view-photos-btn');
if (viewPhotosBtn && document.getElementById('tour-gallery')) {
    viewPhotosBtn.addEventListener('click', function(e) {
        e.preventDefault();
        document.getElementById('tour-gallery').scrollIntoView({behavior: 'smooth'});
    });
}
// --- LOGICA PARTECIPANTI E TOTALE ---
document.addEventListener('DOMContentLoaded', function() {
    const prezzoBase = <?php echo (float)$prezzo; ?>;
    let adulti = 1;
    const minAdulti = 1;
    const maxAdulti = 99;
    const adultiCount = document.getElementById('adulti-count');
    const adultiMinus = document.getElementById('adulti-minus');
    const adultiPlus = document.getElementById('adulti-plus');
    const riepilogoAdulti = document.getElementById('riepilogo-adulti-count');
    const riepilogoTotale = document.getElementById('riepilogo-totale');
    function aggiornaTotale() {
        adultiCount.textContent = adulti;
        riepilogoAdulti.textContent = adulti;
        riepilogoTotale.textContent = '€' + (adulti * prezzoBase);
        adultiMinus.disabled = adulti <= minAdulti;
        adultiPlus.disabled = adulti >= maxAdulti;
    }
    adultiMinus.addEventListener('click', function() {
        if (adulti > minAdulti) {
            adulti--;
            aggiornaTotale();
        }
    });
    adultiPlus.addEventListener('click', function() {
        if (adulti < maxAdulti) {
            adulti++;
            aggiornaTotale();
        }
    });
    aggiornaTotale();
    // Swiper mobile
    if (window.innerWidth < 768 && document.querySelector('.swiper-container')) {
        new Swiper('.swiper-container', {
            slidesPerView: 1,
            spaceBetween: 16,
            centeredSlides: true,
            pagination: {
                el: '.swiper-pagination',
                clickable: true
            },
            autoplay: {
                delay: 3000,
                disableOnInteraction: false
            },
            loop: true
        });
    }
    // Macy desktop
    if (window.innerWidth >= 768 && document.getElementById('macy-gallery')) {
        const macyGallery = document.getElementById('macy-gallery');
        const skeleton = document.getElementById('gallery-skeleton');
        const imgs = macyGallery.querySelectorAll('img');
        let loaded = 0;
        function showGallery() {
            if (skeleton) skeleton.style.display = 'none';
            macyGallery.style.opacity = 1;
            new Macy({
                container: '#macy-gallery',
                trueOrder: false,
                waitForImages: true,
                margin: 8,
                columns: 3,
                breakAt: {
                    900: 3
                }
            });
        }
        if (imgs.length === 0) {
            showGallery();
        } else {
            imgs.forEach(img => {
                if (img.complete) {
                    loaded++;
                    if (loaded === imgs.length) showGallery();
                } else {
                    img.addEventListener('load', function() {
                        loaded++;
                        if (loaded === imgs.length) showGallery();
                    });
                    img.addEventListener('error', function() {
                        loaded++;
                        if (loaded === imgs.length) showGallery();
                    });
                }
            });
        }
    }
    var tourId = <?php echo get_the_ID(); ?>;
    var calendarioEvents = [];
    
    // Carica gli eventi del calendario
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=opencomune_get_calendario&tour_id=' + tourId)
        .then(res => res.json())
        .then(events => {
            calendarioEvents = events;
            var dates = events.map(ev => ev.start.split('T')[0]);
            var fp = flatpickr("#tour-datepicker", {
                inline: true,
                enable: dates,
                locale: 'it',
                showMonths: 1,
                disableMobile: true,
                minDate: 'today',
                onChange: function(selectedDates, dateStr) {
                    if (selectedDates.length > 0) {
                        showOrariDisponibili(dateStr);
                    }
                }
            });
        });
        
    function showOrariDisponibili(data) {
        // Formatta la data per la visualizzazione
        var dataObj = new Date(data);
        var dataFormattata = dataObj.toLocaleDateString('it-IT', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // Mostra la sezione orari
        document.getElementById('data-selezionata').textContent = dataFormattata;
        document.getElementById('orari-disponibili').classList.remove('hidden');
        
        // Filtra gli eventi per la data selezionata
        var eventiData = calendarioEvents.filter(function(evento) {
            return evento.start.startsWith(data);
        });
        
        var listaOrari = document.getElementById('lista-orari');
        listaOrari.innerHTML = '';
        
        if (eventiData.length > 0) {
            eventiData.forEach(function(evento) {
                var orario = evento.start.split('T')[1];
                var posti = evento.title.match(/\((\d+) posti\)/);
                var postiText = posti ? ' - ' + posti[1] + ' posti disponibili' : '';
                var note = evento.title.replace(/\((\d+) posti\)/, '').replace('Disponibile', '').trim();
                
                var orarioBtn = document.createElement('button');
                orarioBtn.className = 'w-full text-left p-3 border border-gray-200 rounded hover:bg-blue-50 hover:border-blue-300 transition';
                orarioBtn.innerHTML = '<div class="flex justify-between items-center"><span class="font-semibold">' + orario + '</span><span class="text-sm text-white-600">' + postiText + '</span></div>' + (note ? '<div class="text-sm text-gray-500 mt-1">' + note + '</div>' : '');
                orarioBtn.onclick = function() {
                    // Rimuovi selezione precedente
                    document.querySelectorAll('#lista-orari button').forEach(function(btn) {
                        btn.classList.remove('bg-blue-600', 'text-white');
                        btn.classList.add('border-gray-200', 'hover:bg-blue-50');
                    });
                    
                    // Seleziona questo orario
                    this.classList.remove('border-gray-200', 'hover:bg-blue-50');
                    this.classList.add('bg-blue-600', 'text-white');
                    
                    // Aggiorna il riepilogo
                    updateRiepilogo(data, orario, note);
                    
                    // Mostra il pulsante "Prenota ora"
                    document.getElementById('prenota-section').classList.remove('hidden');
                    
                    // Salva i dati per la prenotazione
                    window.selectedBookingData = {
                        data: data,
                        orario: orario,
                        note: note,
                        eventId: evento.id
                    };
                };
                listaOrari.appendChild(orarioBtn);
            });
        } else {
            listaOrari.innerHTML = '<p class="text-gray-500 text-center py-4">Nessun orario disponibile per questo giorno</p>';
        }
    }
    
    function updateRiepilogo(data, orario, note) {
        // Aggiorna il riepilogo con data e orario selezionati
        var riepilogoData = document.getElementById('riepilogo-data');
        if (!riepilogoData) {
            // Crea l'elemento se non esiste
            var riepilogoContainer = document.querySelector('.bg-gray-50.rounded.p-4');
            var riepilogoTotale = document.querySelector('#riepilogo-totale').parentElement;
            
            var dataElement = document.createElement('div');
            dataElement.className = 'text-sm text-gray-600 mb-2';
            dataElement.id = 'riepilogo-data';
            riepilogoContainer.insertBefore(dataElement, riepilogoTotale);
        }
        
        var dataFormattata = new Date(data).toLocaleDateString('it-IT');
        var noteText = note ? ' - ' + note : '';
        document.getElementById('riepilogo-data').innerHTML = 'Data: <span class="font-semibold">' + dataFormattata + ' alle ' + orario + noteText + '</span>';
    }
    
    // Gestione modal prenotazione multi-step
    document.getElementById('prenota-ora-btn').addEventListener('click', function() {
        if (window.selectedBookingData) {
            // Mostra il modal con loader
            document.getElementById('prenota-modal').classList.remove('hidden');
            document.getElementById('prenota-loader').classList.remove('hidden');
            
            // Simula caricamento (puoi rimuovere questo timeout in produzione)
            setTimeout(function() {
                // Nascondi il loader
                document.getElementById('prenota-loader').classList.add('hidden');
                
                // Popola i campi nascosti del modal
                document.getElementById('prenota-data').value = window.selectedBookingData.data;
                document.getElementById('prenota-orario').value = window.selectedBookingData.orario;
                document.getElementById('prenota-event-id').value = window.selectedBookingData.eventId;
                
                // Popola i dettagli della prenotazione
                var dataFormattata = new Date(window.selectedBookingData.data).toLocaleDateString('it-IT');
                var noteText = window.selectedBookingData.note ? ' - ' + window.selectedBookingData.note : '';
                var partecipanti = parseInt(document.getElementById('adulti-count').textContent) || 1;
                var prezzoEsperienza = <?php echo intval(get_post_meta(get_the_ID(), 'tour_price', true)); ?>;
                var prezzoTotale = prezzoEsperienza * partecipanti;
                
                document.getElementById('prenota-dettagli-data').innerHTML = '<strong>Data:</strong> ' + dataFormattata + ' alle ' + window.selectedBookingData.orario + noteText + '<br><strong>Partecipanti:</strong> ' + partecipanti + ' adulto' + (partecipanti > 1 ? 'i' : '') + ' - <strong>Totale:</strong> €' + prezzoTotale;
                
                // Inizializza Swiper per la galleria del modal
                if (document.querySelector('.swiper-container-tour-modal')) {
                    new Swiper('.swiper-container-tour-modal', {
                        slidesPerView: 1,
                        spaceBetween: 10,
                        navigation: {
                            nextEl: '.swiper-button-next',
                            prevEl: '.swiper-button-prev',
                        },
                        breakpoints: {
                            768: { slidesPerView: 3 }
                        }
                    });
                }
            }, 800); // 800ms di caricamento
        }
    });
    
    // Funzione per cambiare step
    function changeStep(currentStep, nextStep) {
        // Nascondi step corrente
        document.getElementById('step-' + currentStep).classList.add('hidden');
        
        // Mostra step successivo
        document.getElementById('step-' + nextStep).classList.remove('hidden');
        
        // Aggiorna indicatori
        document.querySelectorAll('.step-indicator').forEach(function(indicator) {
            indicator.classList.remove('active', 'bg-blue-600', 'text-white');
            indicator.classList.add('bg-gray-300', 'text-gray-600');
        });
        
        document.querySelector('.step-indicator[data-step="' + nextStep + '"]').classList.add('active', 'bg-blue-600', 'text-white');
        document.querySelector('.step-indicator[data-step="' + nextStep + '"]').classList.remove('bg-gray-300', 'text-gray-600');
    }
    
    // Gestione navigazione step
    document.getElementById('next-step-1').addEventListener('click', function() {
        changeStep(1, 2);
    });
    
    document.getElementById('next-step-2').addEventListener('click', function() {
        // Validazione step 2
        var nome = document.getElementById('prenota-nome').value.trim();
        var cognome = document.getElementById('prenota-cognome').value.trim();
        var prefisso = document.getElementById('prenota-prefisso').value;
        var numeroTelefono = document.getElementById('prenota-telefono').value.trim();
        var telefono = prefisso + ' ' + numeroTelefono;
        var email = document.getElementById('prenota-email').value.trim();
        
        if (!nome || !cognome || !numeroTelefono || !email) {
            alert('Compila tutti i campi obbligatori');
            return;
        }
        
        // Validazione numero di telefono
        var telefonoRegex = /^[0-9\s\-\(\)]{8,15}$/;
        if (!telefonoRegex.test(numeroTelefono)) {
            alert('Inserisci un numero di telefono valido (8-15 cifre)');
            return;
        }
        
        // Recupera il prezzo dell'esperienza e il numero di partecipanti
        var prezzoEsperienza = <?php echo intval(get_post_meta(get_the_ID(), 'tour_price', true)); ?>;
        var partecipanti = parseInt(document.getElementById('adulti-count').textContent) || 1;
        var prezzoTotale = prezzoEsperienza * partecipanti;
        
        // Popola riepilogo finale
        var dataFormattata = new Date(window.selectedBookingData.data).toLocaleDateString('it-IT');
        var noteText = window.selectedBookingData.note ? ' - ' + window.selectedBookingData.note : '';
        var riepilogoHTML = '<div class="space-y-2">';
        riepilogoHTML += '<div><strong>Esperienza:</strong> <?php echo esc_html(get_the_title()); ?></div>';
        riepilogoHTML += '<div><strong>Data:</strong> ' + dataFormattata + ' alle ' + window.selectedBookingData.orario + noteText + '</div>';
        riepilogoHTML += '<div><strong>Nome:</strong> ' + nome + ' ' + cognome + '</div>';
        riepilogoHTML += '<div><strong>Telefono:</strong> ' + telefono + '</div>';
        riepilogoHTML += '<div><strong>Email:</strong> ' + email + '</div>';
        riepilogoHTML += '<div><strong>Partecipanti:</strong> ' + partecipanti + ' adulto' + (partecipanti > 1 ? 'i' : '') + '</div>';
        if (document.getElementById('prenota-note').value.trim()) {
            riepilogoHTML += '<div><strong>Note:</strong> ' + document.getElementById('prenota-note').value.trim() + '</div>';
        }
        riepilogoHTML += '<div class="mt-3 pt-3 border-t"><strong>Totale:</strong> €' + prezzoTotale + '</div>';
        riepilogoHTML += '</div>';
        
        document.getElementById('riepilogo-finale').innerHTML = riepilogoHTML;
        
        changeStep(2, 3);
        
        // Gestione selezione metodo di pagamento
        document.querySelectorAll('input[name="metodo_pagamento"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                updateRiepilogoPagamento();
            });
        });
        
        // Inizializza il riepilogo pagamento
        updateRiepilogoPagamento();
    });
    
    function updateRiepilogoPagamento() {
        var metodoSelezionato = document.querySelector('input[name="metodo_pagamento"]:checked').value;
        var riepilogoContainer = document.getElementById('riepilogo-finale');
        var riepilogoHTML = riepilogoContainer.innerHTML;
        
        // Rimuovi eventuali note di pagamento precedenti
        riepilogoHTML = riepilogoHTML.replace(/<div[^>]*class="[^"]*mt-3 pt-3 border-t[^"]*"[^>]*>.*?<strong>Pagamento:<\/strong>.*?<\/div>/g, '');
        
        // Aggiungi nota di pagamento
        var notaPagamento = '';
        if (metodoSelezionato === 'guida') {
            notaPagamento = '<div class="mt-3 pt-3 border-t bg-blue-50 border border-blue-200 text-blue-700 rounded p-3"><strong>Pagamento:</strong> All\'ufficio turistico il giorno dell\'esperienza</div>';
        } else if (metodoSelezionato === 'online') {
            notaPagamento = '<div class="mt-3 pt-3 border-t bg-green-50 border border-green-200 text-green-700 rounded p-3"><strong>Pagamento:</strong> Online (verrai reindirizzato al gateway di pagamento)</div>';
        }
        
        riepilogoContainer.innerHTML = riepilogoHTML + notaPagamento;
    }
    
    document.getElementById('prev-step-2').addEventListener('click', function() {
        changeStep(2, 1);
    });
    
    document.getElementById('prev-step-3').addEventListener('click', function() {
        changeStep(3, 2);
    });
    
    document.getElementById('next-step-3').addEventListener('click', function() {
        var metodoSelezionato = document.querySelector('input[name="metodo_pagamento"]:checked').value;
        
        if (metodoSelezionato === 'online') {
            // Copia il riepilogo nel step 4
            var riepilogoFinale = document.getElementById('riepilogo-finale').innerHTML;
            document.getElementById('riepilogo-pagamento').innerHTML = riepilogoFinale;
            changeStep(3, 4);
        } else {
            // Pagamento alla guida - invia direttamente
            document.getElementById('prenota-form').dispatchEvent(new Event('submit'));
        }
    });
    
    document.getElementById('prev-step-4').addEventListener('click', function() {
        changeStep(4, 3);
    });
    
    // Gestione pulsante Paga Ora
    document.getElementById('paga-ora-btn').addEventListener('click', function() {
        var gatewaySelezionato = document.querySelector('input[name="gateway_pagamento"]:checked').value;
        
        // Disabilita il pulsante durante l'elaborazione
        this.disabled = true;
        this.textContent = 'Elaborazione...';
        
        // Prepara i dati per il pagamento
        var formData = new FormData(document.getElementById('prenota-form'));
        formData.append('action', 'opencomune_processa_pagamento');
        formData.append('gateway', gatewaySelezionato);
        formData.append('nonce', '<?php echo wp_create_nonce('processa_pagamento_nonce'); ?>');
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.data.redirect_url) {
                    // Reindirizza al gateway di pagamento
                    window.location.href = data.data.redirect_url;
                } else {
                    alert('Pagamento elaborato con successo!');
                    closeModal();
                }
            } else {
                alert('Errore durante l\'elaborazione del pagamento: ' + (data.data || 'Errore sconosciuto'));
                // Riabilita il pulsante
                document.getElementById('paga-ora-btn').disabled = false;
                document.getElementById('paga-ora-btn').textContent = 'Paga Ora';
            }
        })
        .catch(error => {
            alert('Errore di connessione. Riprova.');
            console.error('Error:', error);
            // Riabilita il pulsante
            document.getElementById('paga-ora-btn').disabled = false;
            document.getElementById('paga-ora-btn').textContent = 'Paga Ora';
        });
    });
    
    // Chiudi modal e reset
    function closeModal() {
        document.getElementById('prenota-modal').classList.add('hidden');
        document.getElementById('prenota-loader').classList.add('hidden');
        
        // Reset allo step 1
        document.querySelectorAll('.step-content').forEach(function(step) {
            step.classList.add('hidden');
        });
        document.getElementById('step-1').classList.remove('hidden');
        
        // Reset indicatori
        document.querySelectorAll('.step-indicator').forEach(function(indicator) {
            indicator.classList.remove('active', 'bg-blue-600', 'text-white');
            indicator.classList.add('bg-gray-300', 'text-gray-600');
        });
        document.querySelector('.step-indicator[data-step="1"]').classList.add('active', 'bg-blue-600', 'text-white');
        document.querySelector('.step-indicator[data-step="1"]').classList.remove('bg-gray-300', 'text-gray-600');
        
        // Reset riepilogo pagamento
        if (document.getElementById('riepilogo-pagamento')) {
            document.getElementById('riepilogo-pagamento').innerHTML = '';
        }
        
        // Reset form
        document.getElementById('prenota-form').reset();
    }
    
    document.getElementById('close-prenota-modal').addEventListener('click', closeModal);
    document.getElementById('cancel-prenota').addEventListener('click', closeModal);
    
    // Chiudi modal cliccando fuori
    document.getElementById('prenota-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
    
    // Gestione form prenotazione
    document.getElementById('prenota-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'opencomune_prenota_tour');
        formData.append('nonce', '<?php echo wp_create_nonce('prenota_tour_nonce'); ?>');
        
        // Disabilita il pulsante durante l'invio
        var submitBtn = this.querySelector('button[type="submit"]');
        var originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Prenotazione in corso...';
        
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Prenotazione confermata! Riceverai una email di conferma.');
                closeModal();
                // Reset selezione orario
                document.querySelectorAll('#lista-orari button').forEach(function(btn) {
                    btn.classList.remove('bg-blue-600', 'text-white');
                    btn.classList.add('border-gray-200', 'hover:bg-blue-50');
                });
                document.getElementById('prenota-section').classList.add('hidden');
                if (document.getElementById('riepilogo-data')) {
                    document.getElementById('riepilogo-data').remove();
                }
            } else {
                alert('Errore durante la prenotazione: ' + (data.data || 'Errore sconosciuto'));
            }
        })
        .catch(error => {
            alert('Errore di connessione. Riprova.');
            console.error('Error:', error);
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
    // Swiper recensioni
    if (document.querySelector('.mySwiperReview')) {
        const swiper = new Swiper('.mySwiperReview', {
            slidesPerView: 1,
            spaceBetween: 24,
            loop: true,
            autoplay: {
                delay: 3500,
                disableOnInteraction: false,
            },
            breakpoints: {
                640: { slidesPerView: 1 },
                900: { slidesPerView: 2 },
                1200: { slidesPerView: 2 }
            }
        });

        // Funzione per attivare i toggle su tutte le slide (anche clonate)
        function attachToggleReview() {
            document.querySelectorAll('.toggle-review').forEach(function(btn) {
                if (!btn.dataset.toggleBound) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const card = btn.closest('.review-content');
                        card.querySelector('.short-text').classList.toggle('hidden');
                        card.querySelector('.full-text').classList.toggle('hidden');
                        btn.textContent = btn.textContent === 'Leggi di più' ? 'Mostra meno' : 'Leggi di più';
                    });
                    btn.dataset.toggleBound = '1';
                }
            });
        }

        // Attacca subito e ogni volta che lo swiper cambia slide (per le clonate)
        attachToggleReview();
        swiper.on('slideChangeTransitionEnd', attachToggleReview);
    }
});
</script>
<?php if (is_user_logged_in()) : ?>
  <div id="review-modal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg max-w-md w-full mx-4 p-6 relative">
      <button id="close-review-modal" class="absolute top-2 right-2 text-gray-500 hover:text-red-600 text-2xl">&times;</button>
      <?php
        require_once get_template_directory() . '/review-system.php';
        opencomune_review_form(get_the_ID());
      ?>
    </div>
  </div>
  <script>
    document.getElementById('open-review-modal').onclick = function() {
      document.getElementById('review-modal').classList.remove('hidden');
    };
    document.getElementById('close-review-modal').onclick = function() {
      document.getElementById('review-modal').classList.add('hidden');
    };
    document.getElementById('review-modal').onclick = function(e) {
      if (e.target === this) this.classList.add('hidden');
    };
  </script>
<?php endif; ?>
<?php
require_once get_template_directory() . '/review-system.php';
opencomune_show_reviews_swiper(get_the_ID());
?>

<!-- Modal Prenotazione Multi-Step -->
    <div id="prenota-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <!-- Loader -->
        <div id="prenota-loader" class="absolute inset-0 bg-white bg-opacity-90 flex items-center justify-center z-10">
            <div class="text-center">
                <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mb-4"></div>
                <p class="text-gray-600 font-medium">Caricamento...</p>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold">Prenota Esperienza</h3>
                <button id="close-prenota-modal" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            
            <!-- Progress Bar -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-sm font-bold step-indicator active" data-step="1">1</div>
                        <div class="ml-2 text-sm font-medium">Dettagli Esperienza</div>
                    </div>
                    <div class="flex-1 h-1 bg-gray-200 mx-4"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-bold step-indicator" data-step="2">2</div>
                        <div class="ml-2 text-sm font-medium">Fatturazione</div>
                    </div>
                    <div class="flex-1 h-1 bg-gray-200 mx-4"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-bold step-indicator" data-step="3">3</div>
                        <div class="ml-2 text-sm font-medium">Riepilogo</div>
                    </div>
                    <div class="flex-1 h-1 bg-gray-200 mx-4"></div>
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-gray-300 text-gray-600 flex items-center justify-center text-sm font-bold step-indicator" data-step="4">4</div>
                        <div class="ml-2 text-sm font-medium">Pagamento</div>
                    </div>
                </div>
            </div>
            
            <form id="prenota-form">
                <input type="hidden" id="prenota-tour-id" value="<?php echo get_the_ID(); ?>">
                <input type="hidden" id="prenota-data" name="data">
                <input type="hidden" id="prenota-orario" name="orario">
                <input type="hidden" id="prenota-event-id" name="event_id">
                
                <!-- STEP 1: Dettagli Esperienza -->
                <div id="step-1" class="step-content">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold mb-4">Dettagli dell'esperienza</h4>
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <div class="flex items-start gap-4">
                                <?php if (has_post_thumbnail()): ?>
                                <div class="flex-shrink-0">
                                    <img src="<?php echo get_the_post_thumbnail_url(get_the_ID(), 'medium'); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="w-20 h-20 object-cover rounded">
                                </div>
                                <?php endif; ?>
                                <div class="flex-1">
                                    <h5 class="font-semibold text-lg"><?php echo esc_html(get_the_title()); ?></h5>
                                    <div id="prenota-dettagli-data" class="text-gray-600 mt-1"></div>
                                    <div class="text-blue-600 font-semibold mt-2">
                                        €<?php echo esc_html(get_post_meta(get_the_ID(), 'tour_price', true)); ?> 
                                        <span class="text-sm text-gray-500">per persona</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Galleria Swiper -->
                        <?php 
                        $gallery_ids = get_post_meta(get_the_ID(), 'galleria', true);
                        if (is_array($gallery_ids) && count($gallery_ids) > 0): ?>
                        <div class="mb-4">
                            <h5 class="font-semibold mb-2">Galleria foto</h5>
                            <div class="swiper-container-tour-modal relative overflow-hidden">
                                <div class="swiper-wrapper">
                                    <?php foreach($gallery_ids as $gid): 
                                        $img_url = wp_get_attachment_image_url($gid, 'medium');
                                        if ($img_url): ?>
                                        <div class="swiper-slide">
                                            <img src="<?php echo esc_url($img_url); ?>" alt="" class="w-full h-32 object-cover rounded">
                                        </div>
                                    <?php endif; endforeach; ?>
                                </div>
                                <div class="swiper-button-prev"></div>
                                <div class="swiper-button-next"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="cancel-prenota" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded transition">
                            Annulla
                        </button>
                        <button type="button" id="next-step-1" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded transition">
                            Avanti
                        </button>
                    </div>
                </div>
                
                <!-- STEP 2: Fatturazione -->
                <div id="step-2" class="step-content hidden">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold mb-4">Dettagli di fatturazione</h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="prenota-nome" class="block text-sm font-medium text-gray-700 mb-2">Nome *</label>
                                <input type="text" id="prenota-nome" name="nome" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div>
                                <label for="prenota-cognome" class="block text-sm font-medium text-gray-700 mb-2">Cognome *</label>
                                <input type="text" id="prenota-cognome" name="cognome" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="prenota-telefono" class="block text-sm font-medium text-gray-700 mb-2">Telefono *</label>
                            <div class="flex gap-2">
                                <select id="prenota-prefisso" name="prefisso" class="border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" style="width: 100px;">
                                    <option value="+39" selected>🇮🇹 +39</option>
                                    <option value="+33">🇫🇷 +33</option>
                                    <option value="+49">🇩🇪 +49</option>
                                    <option value="+44">🇬🇧 +44</option>
                                    <option value="+34">🇪🇸 +34</option>
                                    <option value="+1">🇺🇸 +1</option>
                                    <option value="+86">🇨🇳 +86</option>
                                    <option value="+81">🇯🇵 +81</option>
                                    <option value="+91">🇮🇳 +91</option>
                                    <option value="+55">🇧🇷 +55</option>
                                    <option value="+7">🇷🇺 +7</option>
                                    <option value="+61">🇦🇺 +61</option>
                                    <option value="+27">🇿🇦 +27</option>
                                    <option value="+52">🇲🇽 +52</option>
                                    <option value="+971">🇦🇪 +971</option>
                                    <option value="+966">🇸🇦 +966</option>
                                    <option value="+20">🇪🇬 +20</option>
                                    <option value="+234">🇳🇬 +234</option>
                                    <option value="+254">🇰🇪 +254</option>
                                    <option value="+233">🇬🇭 +233</option>
                                </select>
                                <input type="tel" id="prenota-telefono" name="telefono" placeholder="Numero di telefono" required class="flex-1 border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" pattern="[0-9\s\-\(\)]+" minlength="8" maxlength="15">
                            </div>
                            <div class="text-xs text-gray-500 mt-1">Inserisci solo numeri, spazi, trattini o parentesi</div>
                        </div>
                        
                        <div class="mt-4">
                            <label for="prenota-email" class="block text-sm font-medium text-gray-700 mb-2">Indirizzo Email *</label>
                            <input type="email" id="prenota-email" name="email" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mt-4">
                            <label for="prenota-note" class="block text-sm font-medium text-gray-700 mb-2">Note aggiuntive</label>
                            <textarea id="prenota-note" name="note" rows="3" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Richieste speciali, allergie, ecc."></textarea>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="prev-step-2" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded transition">
                            Indietro
                        </button>
                        <button type="button" id="next-step-2" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded transition">
                            Avanti
                        </button>
                    </div>
                </div>
                
                <!-- STEP 3: Pagamento -->
                <div id="step-3" class="step-content hidden">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold mb-4">Riepilogo e pagamento</h4>
                        
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <h5 class="font-semibold mb-2">Riepilogo prenotazione</h5>
                            <div id="riepilogo-finale" class="text-sm text-gray-600"></div>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-4 mb-4">
                            <h5 class="font-semibold mb-2">Metodo di pagamento</h5>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 mb-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="metodo_pagamento" value="guida" class="mr-3 text-blue-600" checked>
                                    <div class="mb-3">
                                        <div class="font-medium">Pagamento all'ufficio turistico</div>
                                        <div class="text-sm text-gray-600">Paga direttamente all'ufficio turistico il giorno dell'esperienza</div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 mb-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="metodo_pagamento" value="online" class="mr-3 text-blue-600">
                                    <div class="mb-3">
                                        <div class="font-medium">Paga ora online</div>
                                        <div class="text-sm text-gray-600">Paga subito con carta di credito o PayPal</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="prev-step-3" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded transition">
                            Indietro
                        </button>
                        <button type="button" id="next-step-3" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded transition">
                            Conferma Prenotazione
                        </button>
                    </div>
                </div>
                
                <!-- STEP 4: Pagamento Online -->
                <div id="step-4" class="step-content hidden">
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold mb-4">Pagamento Online</h4>
                        
                        <div class="bg-gray-50 rounded-lg p-4 mb-4">
                            <h5 class="font-semibold mb-2">Riepilogo prenotazione</h5>
                            <div id="riepilogo-pagamento" class="text-sm text-gray-600"></div>
                        </div>
                        
                        <div class="bg-blue-50 rounded-lg p-4 mb-4">
                            <h5 class="font-semibold mb-2">Scegli il metodo di pagamento</h5>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="gateway_pagamento" value="nexi" class="mr-3 text-blue-600" checked>
                                    <div class="flex items-center">
                                        <img src="https://www.nexi.it/sites/default/files/2019-11/logo-nexi-pay.png" alt="Nexi Pay" class="h-8 mr-3">
                                        <div>
                                            <div class="font-medium">Nexi Pay</div>
                                            <div class="text-sm text-gray-600">Carta di credito/debito, Bonifico, Satispay</div>
                                        </div>
                                    </div>
                                </label>
                                
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition">
                                    <input type="radio" name="gateway_pagamento" value="paypal" class="mr-3 text-blue-600">
                                    <div class="flex items-center">
                                        <img src="https://www.paypalobjects.com/webstatic/mktg/logo/pp_cc_mark_37x23.jpg" alt="PayPal" class="h-8 mr-3">
                                        <div>
                                            <div class="font-medium">PayPal</div>
                                            <div class="text-sm text-gray-600">Conto PayPal, Carta di credito</div>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between">
                        <button type="button" id="prev-step-4" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-semibold py-2 px-6 rounded transition">
                            Indietro
                        </button>
                        <button type="button" id="paga-ora-btn" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded transition">
                            Paga Ora
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<style>
    .step-indicator {
        width: 20px;
        height: 20px;
        
    }
</style>

<script>
    // Inizializza mappa del punto di ritrovo
    <?php if ($lat && $lon): ?>
    function loadGoogleMapsIfNeeded(callback) {
        if (typeof google !== 'undefined' && google.maps) {
            callback();
        } else {
            // Carica Google Maps dinamicamente
            var script = document.createElement('script');
            script.src = 'https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr(opencomune_get_google_maps_api_key()); ?>&callback=' + callback.name;
            document.head.appendChild(script);
        }
    }
    
    function initMapRitrovo() {
        var lat = <?php echo floatval($lat); ?>;
        var lng = <?php echo floatval($lon); ?>;
        
        if (isNaN(lat) || isNaN(lng)) {
            console.error('Coordinate non valide per la mappa del punto di ritrovo');
            return;
        }
        
        var mapElement = document.getElementById('map-ritrovo');
        if (!mapElement) return;
        
        var map = new google.maps.Map(mapElement, {
            center: { lat: lat, lng: lng },
            zoom: 15,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: false,
            styles: [
                {
                    featureType: 'poi',
                    elementType: 'labels',
                    stylers: [{ visibility: 'off' }]
                }
            ]
        });
        
        // Aggiungi marker per il punto di ritrovo
        var marker = new google.maps.Marker({
            position: { lat: lat, lng: lng },
            map: map,
            title: '<?php echo esc_js($indirizzo_ritrovo); ?>',
            icon: {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e53e3e" width="32" height="32"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>'),
                scaledSize: new google.maps.Size(32, 32),
                anchor: new google.maps.Point(16, 32)
            }
        });
        
        // Aggiungi info window
        var infoWindow = new google.maps.InfoWindow({
            content: '<div style="padding: 10px;"><strong>Punto di ritrovo</strong><br><?php echo esc_js($indirizzo_ritrovo); ?></div>'
        });
        
        marker.addListener('click', function() {
            infoWindow.open(map, marker);
        });
    }
    
    // Inizializza la mappa quando il tab viene mostrato
    document.addEventListener('DOMContentLoaded', function() {
        var tabRitrovo = document.getElementById('tab-ritrovo');
        var mapInitialized = false;
        
        // Observer per monitorare quando il tab diventa visibile
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                    if (!tabRitrovo.classList.contains('hidden') && !mapInitialized) {
                        setTimeout(function() {
                            // Carica Google Maps se necessario e inizializza la mappa
                            loadGoogleMapsIfNeeded(function() {
                                initMapRitrovo();
                                mapInitialized = true;
                            });
                        }, 100);
                    }
                }
            });
        });
        
        observer.observe(tabRitrovo, { attributes: true });
    });
    <?php endif; ?>
</script>

<?php endwhile; endif;
get_footer(); 