<?php get_header(); ?>
<!-- Swiper CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css"/>
<script src="https://cdnjs.cloudflare.com/ajax/libs/lazysizes/5.3.2/lazysizes.min.js" async=""></script>
<style>
.lazyload:not(.ls-is-cached):not(.lazyloaded) {
  filter: blur(8px);
  transition: filter 0.4s;
}
.lazyloaded {
  filter: blur(0);
}
</style>

<!-- HERO SWIPER FULLSCREEN -->
<div class="relative w-full h-screen">
  <div class="swiper hero-swiper h-full">
    <div class="swiper-wrapper">
      <!-- Slide 1 -->
      <div class="swiper-slide relative w-full h-full">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="https://betadev.it/explora/wp-content/uploads/2025/06/lecce_home.jpg" class="object-cover w-full h-full lazyload" alt="Esperienza 1" />
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 flex flex-col justify-center items-center text-center pl-0 md:items-start md:text-left md:pl-40 z-10">
          <h2 class="text-white text-5xl font-bold mb-4">Scopri luoghi veri, vivi emozioni autentiche</h2>
          <div class="text-white text-lg mb-2">Dimentica i soliti itinerari. Con Explorando entri nel cuore del territorio: esperienze locali, sapori genuini, storie da ricordare.

</div>
          <a href="#" class="inline-block mt-4 px-6 py-3 bg-white text-blue-700 font-semibold rounded shadow hover:bg-blue-100">Scopri di più</a>
        </div>
      </div>
      <!-- Slide 2 -->
      <div class="swiper-slide relative w-full h-full">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="https://betadev.it/explora/wp-content/uploads/2025/06/polignano.jpg" class="object-cover w-full h-full lazyload" alt="Esperienza 2" />
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 flex flex-col justify-center items-center text-center pl-0 md:items-start md:text-left md:pl-40 z-10">
          <h2 class="text-white text-5xl font-bold mb-4">Eventi, tour e attività… a due passi da te</h2>
          <div class="text-white text-lg mb-2">Che sia una gita in natura, un workshop artigianale o una serata sotto le stelle, Explorando ti porta dove succede la magia.</div>
          <a href="#" class="inline-block mt-4 px-6 py-3 bg-white text-blue-700 font-semibold rounded shadow hover:bg-blue-100">Scopri di più</a>
        </div>
      </div>
      <!-- Slide 3 -->
      <div class="swiper-slide relative w-full h-full">
        <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="https://betadev.it/explora/wp-content/uploads/2025/06/vendemmia_puglia.jpeg" class="object-cover w-full h-full lazyload" alt="Esperienza 3" />
        <div class="absolute inset-0 bg-black/40"></div>
        <div class="absolute inset-0 flex flex-col justify-center items-center text-center pl-0 md:items-start md:text-left md:pl-40 z-10">
          <h2 class="text-white text-5xl font-bold mb-4">Esperienze da condividere, ricordi da custodire</h2>
          <div class="text-white text-lg mb-2">Scopri proposte per coppie, famiglie e gruppi. Condividi momenti unici e crea connessioni autentiche, una tappa alla volta.</div>
          <a href="#" class="inline-block mt-4 px-6 py-3 bg-white text-blue-700 font-semibold rounded shadow hover:bg-blue-100">Scopri di più</a>
        </div>
      </div>
    </div>
    <!-- Swiper navigation -->
    <div class="swiper-pagination"></div>
  </div>
</div>
<!-- Swiper JS -->
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  new Swiper('.hero-swiper', {
    loop: true,
    effect: 'fade',
    autoplay: { delay: 3000 },
    pagination: { el: '.swiper-pagination', clickable: true }
  });
});
</script>
<main class="container mx-auto px-4 py-8">

    <!-- Logo e azioni -->
    <div class="flex flex-col items-center mb-8">
 
        <div class="text-center  text-gray-700 mb-6">
            <h1 class="text-4xl font-bold"><?php echo bloginfo('name'); ?>, la piattaforma per scoprire, creare<br>
            e condividere esperienze culturali</h1>
        </div>
    </div>

    <!-- Ricerca -->
    <form id="tour-search-form" class="w-full max-w-2xl mx-auto mb-8 relative" autocomplete="off">
        <div class="flex flex-col md:flex-row bg-white rounded-xl shadow px-4 py-3 items-center space-y-2 md:space-y-0 md:space-x-2">
            <input type="text" id="tour-search-input" placeholder="Cerca esperienze, città..." class="flex-1 border-none focus:ring-0 text-1xl bg-transparent py-4 px-4" autocomplete="off" />
            <button type="submit" class="bg-blue-600 text-white px-8 py-3 rounded font-light text-lg hover:bg-blue-700" style="    width: 100px;">Cerca</button>
        </div>
        <div id="tour-search-suggestions" class="absolute left-0 right-0 bg-white border rounded shadow z-50 mt-1 hidden"></div>
    </form>
    <div id="tour-search-results" class="max-w-2xl mx-auto mb-10"></div>

    <!-- BOX FUNZIONI (Swiper responsive) -->
    <div class="mb-10">
      <div class="swiper box-swiper">
        <div class="swiper-wrapper">
          <div class="swiper-slide">
            <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
              <i class="bi bi-stars mb-2 text-3xl text-blue-600"></i>
              <div class="font-bold mb-1">Vivi esperienze autentiche</div>
              <div class="text-gray-500 text-sm">Partecipa a tour, eventi e attività uniche, guidate da esperti locali.</div>
            </div>
          </div>
          <div class="swiper-slide">
            <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
              <i class="bi bi-search mb-2 text-3xl text-blue-600"></i>
              <div class="font-bold mb-1">Scopri e prenota facilmente</div>
              <div class="text-gray-500 text-sm">Trova l'esperienza perfetta per te e prenota in pochi click, anche da mobile.</div>
            </div>
          </div>
          <div class="swiper-slide">
            <div class="bg-white rounded-xl shadow p-6 flex flex-col items-center text-center">
              <i class="bi bi-people mb-2 text-3xl text-blue-600"></i>
              <div class="font-bold mb-1">Diventa protagonista</div>
              <div class="text-gray-500 text-sm">Crea e condividi le tue esperienze, entra nella community di Explorando.</div>
            </div>
          </div>
        </div>
        <div class="swiper-pagination mt-2"></div>
      </div>
      <script>
      document.addEventListener('DOMContentLoaded', function() {
        new Swiper('.box-swiper', {
          slidesPerView: 1.1,
          spaceBetween: 16,
          pagination: { el: '.box-swiper .swiper-pagination', clickable: true },
          breakpoints: {
            768: { slidesPerView: 3, spaceBetween: 24 }
          }
        });
      });
      </script>
    </div>

    <!-- SWIPER TOUR CARDS DINAMICO -->
    <div class="container mx-auto px-4 py-8">
      <h2 class="text-2xl font-bold mb-4">Esperienze consigliate</h2>
      <div class="swiper tour-swiper">
        <div class="swiper-wrapper">
          <?php
          $tour_query = new WP_Query([
            'post_type' => 'tour',
            'posts_per_page' => 8,
            'post_status' => 'publish',
          ]);
          if ($tour_query->have_posts()):
            while ($tour_query->have_posts()): $tour_query->the_post();
              $post_id = get_the_ID();
              $img = get_the_post_thumbnail_url($post_id, 'large') ?: 'https://via.placeholder.com/400x300?text=Tour';
              $prezzo = get_post_meta($post_id, 'prezzo', true);
              $prezzo_old = get_post_meta($post_id, 'prezzo_old', true);
              $rating = get_post_meta($post_id, 'rating', true);
              $badge = get_post_meta($post_id, 'badge', true);
              require_once get_template_directory() . '/review-system.php';
              $avg_rating = opencomune_get_average_rating($post_id);
          ?>
          <div class="swiper-slide">
            <a href="<?php the_permalink(); ?>" class="block bg-white rounded-2xl shadow p-3 max-w-xs mx-auto flex flex-col hover:shadow-lg transition">
              <div class="relative">
                <img src="<?php echo esc_url($img); ?>" class="rounded-xl w-full h-40 object-cover" alt="<?php the_title_attribute(); ?>">
                <?php
                  $author_id = get_post_field('post_author', $post_id);
                  $guida_post_id = get_user_meta($author_id, 'guida_post_id', true);
                  $guida_thumb = $guida_post_id ? get_the_post_thumbnail_url($guida_post_id, 'thumbnail') : '';
                  if (!$guida_thumb) {
                    $guida_thumb = 'https://www.gravatar.com/avatar/?d=mp&f=y';
                  }
                ?>
                <img src="<?php echo esc_url($guida_thumb); ?>" alt="Guida" class="absolute top-2 right-2 w-10 h-10 rounded-full border-2 border-white shadow object-cover bg-white" />
              </div>
              <div class="mt-3 flex-1 flex flex-col">
                <div class="font-semibold text-base leading-tight mb-1"><?php the_title(); ?></div>
                <div class="flex items-center gap-2 mb-2">
                  <span class="text-xs text-green-800"><i class="bi bi-patch-check"></i> Certificato da <?php echo get_bloginfo('name'); ?></span>
                </div>
                <div class="text-gray-500 text-sm mb-2"><?php echo wp_trim_words(get_the_excerpt(), 18); ?></div>
               
                <?php if ($badge): ?>
                <div class="flex items-center gap-2 mb-2">
                  <span class="inline-block bg-red-600 text-white text-xs px-2 py-1 rounded font-semibold"><?php echo esc_html($badge); ?></span>
                </div>
                <?php endif; ?>
                
                <!-- City Badge -->
                <?php 
                $citta = get_post_meta($post_id, 'citta', true);
                if ($citta): ?>
                <div class="flex items-center gap-2 mb-2">
                  <span class="inline-block bg-blue-600 text-white text-xs px-2 py-1 rounded font-semibold">
                    <i class="bi bi-geo-alt-fill mr-1"></i><?php echo esc_html(stripslashes($citta)); ?>
                  </span>
                </div>
                <?php endif; ?>

                <div class="flex items-center gap-2 mt-auto">
                  <?php if ($rating): ?>
                  <span class="ml-auto flex items-center gap-1 text-yellow-500 font-bold text-base">
                    <i class="bi bi-star-fill"></i> <?php echo esc_html($rating); ?>
                  </span>
                  <?php endif; ?>
                </div>
                <!-- Stelle recensioni -->
                <div class="flex items-center gap-1 mt-2">
                  <?php
                  for ($i = 1; $i <= 5; $i++) {
                      echo '<span class="text-yellow-400 text-lg">'.($i <= round($avg_rating) ? '★' : '☆').'</span>';
                  }
                  ?>
                  <?php if ($avg_rating): ?>
                      <span class="text-gray-600 text-sm ml-1"><?php echo esc_html($avg_rating); ?>/5</span>
                  <?php endif; ?>
            
                </div>
                
              </div>
            </a>
          </div>
          <?php endwhile; wp_reset_postdata(); endif; ?>
        </div>
        <div class="swiper-pagination mt-4"></div>
      </div>
    </div>
</main>
<script>
document.addEventListener('DOMContentLoaded', function() {
  new Swiper('.tour-swiper', {
    slidesPerView: 1.2,
    spaceBetween: 10,
    breakpoints: {
      640: { slidesPerView: 2.2 },
      1024: { slidesPerView: 3.2 },
      1280: { slidesPerView: 5 },
    },
    pagination: { el: '.tour-swiper .swiper-pagination', clickable: true },
    freeMode: true,
  });
});
</script>
<script>
jQuery(document).ready(function($) {
    let timer;
    $('#tour-search-input').on('input', function() {
        clearTimeout(timer);
        const query = $(this).val();
        if (query.length < 2) {
            $('#tour-search-suggestions').hide();
            return;
        }
        timer = setTimeout(function() {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                method: 'GET',
                data: {
                    action: 'opencomune_search_tour_autocomplete',
                    q: query
                },
                success: function(res) {
                    let html = '';
                    if (res.success && res.data.length) {
                        res.data.forEach(item => {
                            html += `<div class="px-4 py-2 hover:bg-blue-50 cursor-pointer" data-link="${item.link}"><b>${item.title}</b><br><span class="text-xs text-gray-500">${item.type} - ${item.location}</span></div>`;
                        });
                        html += `<div class="px-4 py-2 text-blue-700 cursor-pointer" id="see-all-results">Vedi tutti i risultati per "<b>${query}</b>"</div>`;
                        $('#tour-search-suggestions').html(html).show();
                    } else {
                        $('#tour-search-suggestions').html('<div class="px-4 py-2 text-gray-500">Nessun risultato</div>').show();
                    }
                }
            });
        }, 300);
    });

    // Click suggestion
    $('#tour-search-suggestions').on('click', 'div[data-link]', function() {
        window.location.href = $(this).data('link');
    });

    // Mostra tutti i risultati sotto il form
    $('#tour-search-suggestions').on('click', '#see-all-results', function() {
        const query = $('#tour-search-input').val();
        searchAndShowResults(query);
        $('#tour-search-suggestions').hide();
    });

    // Submit form: mostra risultati sotto il form
    $('#tour-search-form').on('submit', function(e) {
        e.preventDefault();
        const query = $('#tour-search-input').val();
        searchAndShowResults(query);
        $('#tour-search-suggestions').hide();
    });

    function searchAndShowResults(query) {
        $('#tour-search-results').html('<div class="text-center py-8 text-gray-500">Caricamento risultati...</div>');
        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            method: 'GET',
            data: {
                action: 'opencomune_search_tour_autocomplete',
                q: query
            },
            success: function(res) {
                let html = '';
                if (res.success && res.data.length) {
                    html += '<div class="bg-white rounded-xl shadow p-4 mt-4">';
                    res.data.forEach(item => {
                        html += `
                        <a href="${item.link}" class="flex items-center gap-4 border-b last:border-0 py-3 hover:bg-blue-50 transition">
                            <img src="${item.img}" alt="${item.title}" class="w-20 h-20 object-cover rounded-lg flex-shrink-0" />
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-lg">${item.title}</div>
                                <div class="text-sm text-gray-500">${item.type} - ${item.location}</div>
                            </div>
                        </a>`;
                    });
                    html += '</div>';
                } else {
                    html = '<div class="text-center py-8 text-gray-500">Nessun risultato trovato.</div>';
                }
                $('#tour-search-results').html(html);
            }
        });
    }

    // Chiudi suggerimenti se clicchi fuori
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tour-search-form').length) {
            $('#tour-search-suggestions').hide();
        }
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('.homeReviewSwiper')) {
        const swiper = new Swiper('.homeReviewSwiper', {
            slidesPerView: 1,
            spaceBetween: 24,
            loop: true,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            breakpoints: {
                640: { slidesPerView: 1 },
                900: { slidesPerView: 2 },
                1200: { slidesPerView: 4 }
            }
        });

        function attachToggleReview() {
            document.querySelectorAll('.homeReviewSwiper .toggle-review').forEach(function(btn) {
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
        attachToggleReview();
        swiper.on('slideChangeTransitionEnd', attachToggleReview);
    }
});
</script>
<?php
require_once get_template_directory() . '/review-system.php';
opencomune_show_home_reviews_swiper(8);
?>

<!-- MAPPA ESPERIENZE -->
<div class="container mx-auto px-4 py-8">
  <div class="relative w-full" style="height: 600px;">
    <!-- Pulsante centrato -->
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
      <a href="<?php echo home_url('/mappa-tour/'); ?>" style="pointer-events:auto;z-index:20;">
        <button class="bg-blue-700 bg-opacity-90 text-white text-xl font-bold px-8 py-4 rounded-full shadow-lg border-4 border-white hover:bg-blue-800 transition" style="background: #fbbf24;padding: 20px;color: black;font-weight: 500;">
          Vedi tutte le Esperienze
        </button>
      </a>
    </div>
    <!-- Mappa -->
    <div id="tours-map" class="w-full h-full rounded-2xl" style="filter: grayscale(1) opacity(0.7);"></div>
  </div>
</div>
<script>
// Recupera i marker dei tour dal backend
const toursMarkers = [
<?php
$tour_query = new WP_Query([
  'post_type' => 'tour',
  'post_status' => 'publish',
  'posts_per_page' => -1,
]);
if ($tour_query->have_posts()):
  while ($tour_query->have_posts()): $tour_query->the_post();
    $gps = get_post_meta(get_the_ID(), 'gps', true);
    $img = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
    if ($gps && $img) {
      $coords = explode(',', $gps);
      if (count($coords) == 2) {
        $lat = trim($coords[0]);
        $lng = trim($coords[1]);
        $title = addslashes(get_the_title());
        $img_url = esc_url($img);
        echo "  {lat: $lat, lng: $lng, title: '$title', img: '$img_url'},\n";
      }
    }
  endwhile;
  wp_reset_postdata();
endif;
?>
];

function createCircleMarker() {
  // Cerchio pieno giallo oro #fbbf24
  const svg = `
    <svg xmlns='http://www.w3.org/2000/svg' width='24' height='24'>
      <circle cx='12' cy='12' r='10' fill='#fbbf24' stroke='#fff' stroke-width='2'/>
    </svg>
  `;
  return {
    url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
    scaledSize: new google.maps.Size(24, 24),
    anchor: new google.maps.Point(12, 12)
  };
}

function initToursMap() {
  if (!window.google || !window.google.maps) return;
  const map = new google.maps.Map(document.getElementById('tours-map'), {
    center: { lat: 41.8719, lng: 12.5674 }, // Centro Italia
    zoom: 6,
    disableDefaultUI: true,
    scrollwheel: false,
    disableDoubleClickZoom: true,
    gestureHandling: 'none',
    styles: [
      { featureType: 'poi', stylers: [{ visibility: 'off' }] },
      { featureType: 'transit', stylers: [{ visibility: 'off' }] }
    ]
  });
  toursMarkers.forEach(marker => {
    new google.maps.Marker({
      position: { lat: parseFloat(marker.lat), lng: parseFloat(marker.lng) },
      map,
      title: marker.title,
      icon: createCircleMarker()
    });
  });
}

// Inizializza la mappa quando Google Maps è pronto
if (typeof google !== 'undefined' && google.maps && google.maps.Map) {
  initToursMap();
} else {
  window.initToursMap = initToursMap;
  if (!window.toursMapScriptInjected) {
    const script = document.createElement('script');
    script.src = 'https://maps.googleapis.com/maps/api/js?key=<?php echo esc_js(opencomune_get_google_maps_api_key()); ?>&callback=initToursMap&libraries=places';
    script.async = true;
    document.head.appendChild(script);
    window.toursMapScriptInjected = true;
  }
}
</script>

<!-- NEWSLETTER SECTION -->
<div class="container mx-auto px-4 py-8 l overflow-hidden shadow-lg flex flex-col md:flex-row bg-white border border-yellow-300">
  <!-- Colonna sinistra: testo -->
  <div class="md:w-1/2 flex flex-col justify-center items-start p-8 bg-[#fbbf24] bg-opacity-90">
    <h2 class="text-3xl md:text-4xl font-extrabold mb-4 text-gray-900">Iscriviti alla newsletter</h2>
    <p class="text-lg text-gray-800 mb-2">Ricevi offerte esclusive, novità e ispirazioni di viaggio direttamente nella tua casella email.</p>
    <ul class="text-gray-700 text-base space-y-1 mt-2">
      <li><i class="bi bi-stars text-blue-700 mr-2"></i>Esperienze uniche e autentiche</li>
      <li><i class="bi bi-gift text-blue-700 mr-2"></i>Sconti riservati agli iscritti</li>
      <li><i class="bi bi-calendar-event text-blue-700 mr-2"></i>Eventi e novità in anteprima</li>
    </ul>
  </div>
  <!-- Colonna destra: form -->
  <div class="md:w-1/2 flex flex-col justify-center items-center p-8 bg-blue-700 bg-opacity-90">
    <form action="#" method="post" class="w-full max-w-sm">
      <label for="newsletter-email" class="block text-white text-lg font-semibold mb-2">La tua email</label>
      <input type="email" id="newsletter-email" name="newsletter-email" required placeholder="Inserisci la tua email" class="w-full px-4 py-3 rounded-lg border-2 border-blue-200 focus:border-yellow-400 focus:ring-2 focus:ring-yellow-300 outline-none text-gray-900 mb-4">
      <button type="submit" class="w-full bg-[#fbbf24] hover:bg-yellow-400 text-blue-900 font-bold py-3 rounded-lg text-lg transition">Iscriviti ora</button>
      <p class="text-xs text-white mt-3 opacity-80">Nessuno spam. Potrai disiscriverti in ogni momento.</p>
    </form>
  </div>
</div>

<?php get_footer(); ?>
