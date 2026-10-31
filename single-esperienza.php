<?php
get_header();
if (have_posts()) : while (have_posts()) : the_post();
    $id = get_the_ID();
    $img = opencomune_get_esperienza_thumbnail_url($id, 'full');
    $titolo = get_the_title();
    $durata = get_post_meta($id, 'tour_duration', true);
    $difficolta = get_post_meta($id, 'tour_difficulty', true);
    
    $desc_breve = opencomune_get_esperienza_descrizione_breve($id);
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
    
    $prezzo = opencomune_get_esperienza_base_price($id);
    $pricing_summary = opencomune_format_esperienza_price_api_payload($id);
    $include = get_post_meta($id, 'tour_whats_included', true);
    $non_include = get_post_meta($id, 'tour_whats_not_included', true);
    $itinerario = get_post_meta($id, 'tour_itinerary', true);
    $itinerary_steps = function_exists('opencomune_get_esperienza_itinerary_steps')
        ? opencomune_get_esperienza_itinerary_steps($id)
        : [];
    $has_itinerary_tab = !empty($itinerary_steps) || opencomune_has_field_value($itinerario);
    $note = get_post_meta($id, 'tour_requirements', true);
    $cancellation_policy = get_post_meta($id, 'tour_cancellation_policy', true);
    $tipo_esperienza = get_post_meta($id, 'tipo_esperienza', true);
    $scadenza_prenotazioni = get_post_meta($id, 'scadenza_prenotazioni', true);
    $indirizzo_ritrovo = get_post_meta($id, 'tour_meeting_point', true);
    $tour_destination = get_post_meta($id, 'tour_destination', true);
    $lat = get_post_meta($id, 'tour_latitude', true);
    $lon = get_post_meta($id, 'tour_longitude', true);
    $difficulty_badge = function_exists('opencomune_get_tour_difficulty_badge')
        ? opencomune_get_tour_difficulty_badge($difficolta)
        : null;
    $difficolta_label = $difficulty_badge ? $difficulty_badge['label'] : '';
    $lingue_arr = is_array($lingue) ? $lingue : (is_string($lingue) ? explode(',', $lingue) : []);
    $lingue_arr = array_values(array_filter(array_map('trim', $lingue_arr)));
    $durata_label = opencomune_format_esperienza_duration_label($durata);
    $allegati_items = opencomune_get_esperienza_allegati_items($id);
    $has_ulteriori_info = opencomune_has_field_value($tipo_esperienza)
        || opencomune_has_field_value($scadenza_prenotazioni)
        || opencomune_has_field_value($durata_label)
        || opencomune_has_field_value($difficolta_label)
        || !empty($categorie_arr)
        || !empty($lingue_arr)
        || !empty($allegati_items);
    $has_ritrovo_info = opencomune_has_field_value($indirizzo_ritrovo) || ($lat && $lon) || opencomune_has_field_value($tour_destination);
    // --- Organizzato da Ufficio Turistico ---
    $ufficio_nome = get_option('opencomune_nome_comune', 'Ufficio Turistico');
    $ufficio_email = get_option('opencomune_email_ufficio', '');
    $ufficio_telefono = get_option('opencomune_telefono_ufficio', '');
    
    // Determina se l'esperienza è di un partner (a pagamento)
    $author_id = get_post_field('post_author', $id);
    $author = get_userdata($author_id);
    $is_partner_experience = $author && in_array('partner', $author->roles);
    $tour_date_mode = get_post_meta($id, 'tour_date_mode', true) ?: 'single';
    $tour_start_date = get_post_meta($id, 'tour_start_date', true);
    $tour_end_date = get_post_meta($id, 'tour_end_date', true);
    $mostra_form_prenotazione = get_post_meta($id, 'mostra_form_prenotazione', true);
    if ($mostra_form_prenotazione === '' || $mostra_form_prenotazione === false) {
        $mostra_form_prenotazione = '1';
    }
    $show_booking_form = ($mostra_form_prenotazione === '1');
    $booking_channel = opencomune_get_esperienza_booking_channel_settings($id);
    $pricing_categories_public = opencomune_format_pricing_categories_for_api($id);
    $booking_capacity_payload = opencomune_get_esperienza_capacity_payload($id);
    $booking_global_max_participants = $booking_capacity_payload['max_participants'];
    $booking_requires_approval = function_exists('opencomune_experience_requires_booking_approval')
        && opencomune_experience_requires_booking_approval($id);
    $partner_booking_url = $tour_destination ?: ($author ? $author->user_url : '');
    $experience_partner = $is_partner_experience ? opencomune_get_partner_by_user_id($author_id) : null;
    $partner_display_name = $experience_partner && !empty($experience_partner->nome_attivita)
        ? $experience_partner->nome_attivita
        : ($author ? $author->display_name : '');
    $partner_avatar_url = opencomune_get_partner_avatar_url($experience_partner, $author_id);
    $partner_profile_url = $author_id ? opencomune_get_partner_profile_url($author_id) : '';
    $organizer_name = $partner_display_name !== ''
        ? $partner_display_name
        : ($author ? $author->display_name : '');
    $organizer_avatar_url = $partner_avatar_url
        ? $partner_avatar_url
        : ($author_id ? get_avatar_url($author_id, ['size' => 192]) : '');
    $share_urls = opencomune_get_esperienza_share_urls($id);
?>

<main class="max-w-7xl mx-auto p-4">
    <!-- HERO -->
    <div class="rounded-xl overflow-hidden shadow mb-8">
        <?php if ($img): ?>
            <div class="relative h-72 md:h-96 w-full bg-gray-200">
                <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($titolo); ?>" class="object-cover w-full h-full" loading="lazy">
                <button id="scroll-gallery-btn" class="absolute top-4 right-4 bg-white/80 px-4 py-2 rounded shadow text-blue-700 font-semibold text-sm"><?php _e('Visualizza foto', 'opencomune'); ?></button>
            </div>
        <?php else : ?>
            <?php
            opencomune_render_esperienza_cover($id, [
                'size' => 'full',
                'img_class' => 'object-cover w-full h-full',
                'placeholder_class' => 'esperienza-cover-placeholder is-hero h-72 md:h-96 w-full',
            ]);
            ?>
        <?php endif; ?>
        <div class="bg-white p-6 md:p-8 flex flex-col md:flex-row md:items-center gap-4">
            <div class="flex-1">
                <h1 class="text-3xl font-bold mb-2"><?php echo esc_html($titolo); ?></h1>
                <div class="flex flex-wrap items-center gap-3 text-gray-600 mb-2">
                    <?php if ($durata_label): ?><span><svg class="inline w-5 h-5 mr-1 text-blue-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> <?php echo esc_html($durata_label); ?></span><?php endif; ?>
                    <?php
                    if ($difficulty_badge && function_exists('opencomune_render_tour_difficulty_badge')) {
                        echo opencomune_render_tour_difficulty_badge($difficolta);
                    }
                    ?>
                </div>
                <?php if ($desc_breve): ?>
                <div class="text-lg text-gray-700 mb-2"><?php echo esc_html($desc_breve); ?></div>
                <?php endif; ?>
            </div>
            <?php
            if (is_user_logged_in() && opencomune_user_can_edit_esperienza($id)) {
                $edit_url = opencomune_get_esperienza_wizard_url($id);
                echo '<a href="' . esc_url($edit_url) . '" class="p-3 ml-auto inline-flex items-center justify-center bg-yellow-400 hover:bg-yellow-500 text-yellow-900 font-bold w-11 h-11 rounded shadow transition" aria-label="' . esc_attr__('Modifica esperienza', 'opencomune') . '" title="' . esc_attr__('Modifica esperienza', 'opencomune') . '">';
                echo '<i class="bi bi-pencil-square text-lg" aria-hidden="true"></i>';
                echo '</a>';
            }
            ?>
        </div>
    </div>
    <!-- GALLERIA MASONRY -->
    <?php 
    $gallery_ids = get_post_meta($id, 'galleria', true);
    $gallery_lightbox_items = is_array($gallery_ids)
        ? opencomune_get_esperienza_gallery_lightbox_items($gallery_ids)
        : [];
    $gallery_fslightbox_id = 'esperienza-gallery-' . (int) $id;
    if (!empty($gallery_lightbox_items)): ?>
    <div id="tour-gallery" class="mb-10">
        <div class="flex justify-between items-center mb-2 mx-auto">
            <h2 class="text-xl font-bold"><?php _e('Galleria', 'opencomune'); ?></h2>
            <a href="#" id="view-photos-btn" class="hidden md:inline-block bg-white border border-blue-600 text-blue-700 px-4 py-2 rounded font-semibold text-sm shadow hover:bg-blue-50"><?php _e('Visualizza foto', 'opencomune'); ?></a>
        </div>
        <!-- Swiper mobile -->
        <div class="md:hidden overflow-hidden">
            <div class="swiper-container relative">
                <div class="swiper-wrapper">
                    <?php foreach ($gallery_lightbox_items as $gallery_item) : ?>
                        <div class="swiper-slide flex justify-center items-center">
                            <a
                                class="oc-esperienza-gallery-item"
                                href="<?php echo esc_url($gallery_item['full']); ?>"
                                data-fslightbox="<?php echo esc_attr($gallery_fslightbox_id); ?>"
                                data-caption="<?php echo esc_attr($titolo); ?>"
                            >
                                <img
                                    src="<?php echo esc_url($gallery_item['large']); ?>"
                                    alt="<?php echo esc_attr($gallery_item['alt'] !== '' ? $gallery_item['alt'] : $titolo); ?>"
                                    class="object-contain rounded-lg max-h-72 w-auto max-w-full mx-auto"
                                    loading="lazy"
                                >
                            </a>
                        </div>
                    <?php endforeach; ?>
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
            <?php foreach ($gallery_lightbox_items as $gallery_item) : ?>
                <div class="overflow-hidden rounded-lg">
                    <a
                        class="oc-esperienza-gallery-item"
                        href="<?php echo esc_url($gallery_item['full']); ?>"
                        data-fslightbox="<?php echo esc_attr($gallery_fslightbox_id); ?>"
                        data-caption="<?php echo esc_attr($titolo); ?>"
                    >
                        <img
                            src="<?php echo esc_url($gallery_item['large']); ?>"
                            alt="<?php echo esc_attr($gallery_item['alt'] !== '' ? $gallery_item['alt'] : $titolo); ?>"
                            class="w-full h-auto object-cover"
                            loading="lazy"
                        >
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <!-- MAIN + SIDEBAR -->
    <div class="flex flex-col md:flex-row gap-8">
        <!-- MAIN CONTENT -->
        <div class="flex-1 min-w-0">
            <!-- Tabs -->
            <div class="border-b mb-4 flex gap-4 flex-wrap">
                <button class="tab-btn border-b-2 border-blue-600 text-blue-700 px-4 py-2 font-semibold" data-tab="descrizione"><?php _e('Descrizione', 'opencomune'); ?></button>
                <?php if ($has_itinerary_tab) : ?>
                <button class="tab-btn border-b-2 border-transparent text-gray-600 px-4 py-2 font-semibold" data-tab="itinerario"><?php _e('Itinerario', 'opencomune'); ?></button>
                <?php endif; ?>
                <?php if ($has_ritrovo_info) : ?>
                <button class="tab-btn border-b-2 border-transparent text-gray-600 px-4 py-2 font-semibold" data-tab="ritrovo"><?php _e('Altre informazioni', 'opencomune'); ?></button>
                <?php endif; ?>
            </div>
            <div id="tab-descrizione" class="tab-pane">
                <div class="oc-esperienza-rich-content mb-4"><?php echo opencomune_render_esperienza_post_content($id); ?></div>
                <?php if ($include): ?>
                <div class="mb-4">
                    <b><?php _e('Cosa include:', 'opencomune'); ?></b>
                    <div class="oc-esperienza-rich-content mt-1"><?php echo opencomune_filter_esperienza_rich_text($include, $id); ?></div>
                </div>
                <?php endif; ?>
                <?php if ($non_include): ?>
                <div class="mb-4">
                    <b><?php _e('Cosa non è incluso:', 'opencomune'); ?></b>
                    <div class="oc-esperienza-rich-content mt-1"><?php echo opencomune_filter_esperienza_rich_text($non_include, $id); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($has_itinerary_tab) : ?>
            <div id="tab-itinerario" class="tab-pane hidden">
                <?php if (!empty($itinerary_steps)) : ?>
                    <?php
                    get_template_part('template-parts/esperienza', 'itinerary-steps', [
                        'post_id' => $id,
                        'steps' => $itinerary_steps,
                    ]);
                    ?>
                <?php elseif ($itinerario) : ?>
                <div class="oc-esperienza-rich-content">
                    <?php echo opencomune_filter_esperienza_rich_text($itinerario, $id); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div id="tab-ritrovo" class="tab-pane hidden">
                <?php if ($indirizzo_ritrovo || ($lat && $lon) || opencomune_has_field_value($tour_destination)): ?>
                <div class="mb-4">
                    <h3 class="font-bold text-lg mb-3"><?php _e('Punto di ritrovo', 'opencomune'); ?></h3>
                    <?php if ($indirizzo_ritrovo): ?>
                    <div class="mb-4">
                        <b><?php _e('Indirizzo:', 'opencomune'); ?></b>
                        <span class="text-gray-700"><?php echo esc_html($indirizzo_ritrovo); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (opencomune_has_field_value($tour_destination)): ?>
                    <div class="mb-4">
                        <b><?php _e('Link esterno', 'opencomune'); ?>:</b>
                        <a href="<?php echo esc_url($tour_destination); ?>" class="text-blue-700 hover:text-blue-900 break-all" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($tour_destination); ?>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($lat && $lon): ?>
                    <div class="mb-4">
                        <div id="map-ritrovo" class="w-full h-64 rounded-lg border border-gray-300" style="min-height: 200px;"></div>
                    </div>
                    <?php elseif ($indirizzo_ritrovo): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
                        <p class="text-yellow-800 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            <?php _e('Le coordinate del punto di ritrovo non sono state configurate. Contatta l\'ufficio turistico per maggiori informazioni.', 'opencomune'); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (opencomune_has_field_value($note)): ?>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php _e('Si prega di notare', 'opencomune'); ?></h3>
                <div class="oc-esperienza-rich-content"><?php echo opencomune_filter_esperienza_rich_text($note, $id); ?></div>
            </div>
            <?php endif; ?>
            <?php if (opencomune_has_field_value($cancellation_policy)): ?>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php esc_html_e('Politica sulle disdette', 'opencomune'); ?></h3>
                <button
                    type="button"
                    id="open-cancellation-policy-modal"
                    class="text-blue-700 font-medium hover:text-blue-900 underline underline-offset-2 text-left"
                    aria-haspopup="dialog"
                    aria-controls="cancellation-policy-modal"
                >
                    <?php esc_html_e('Leggi la nostra policy sulla cancellazione', 'opencomune'); ?>
                </button>
            </div>
            <div
                id="cancellation-policy-modal"
                class="oc-cancellation-policy-modal hidden"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cancellation-policy-modal-title"
                aria-hidden="true"
            >
                <div class="oc-cancellation-policy-modal__panel" role="document">
                    <header class="oc-cancellation-policy-modal__header">
                        <h3 id="cancellation-policy-modal-title" class="oc-cancellation-policy-modal__title">
                            <?php esc_html_e('Politica sulle disdette', 'opencomune'); ?>
                        </h3>
                        <button
                            type="button"
                            class="oc-cancellation-policy-modal__close"
                            data-close-cancellation-policy-modal
                            aria-label="<?php esc_attr_e('Chiudi', 'opencomune'); ?>"
                        >&times;</button>
                    </header>
                    <div class="oc-cancellation-policy-modal__body oc-esperienza-rich-content">
                        <?php echo opencomune_filter_esperienza_rich_text($cancellation_policy, $id); ?>
                    </div>
                    <footer class="oc-cancellation-policy-modal__footer">
                        <button type="button" class="oc-cancellation-policy-modal__footer-btn" data-close-cancellation-policy-modal>
                            <?php esc_html_e('Chiudi', 'opencomune'); ?>
                        </button>
                    </footer>
                </div>
            </div>
            <style>
            .oc-cancellation-policy-modal {
                position: fixed;
                inset: 0;
                z-index: 100200;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
                background: rgba(0, 0, 0, 0.4);
                box-sizing: border-box;
            }
            .oc-cancellation-policy-modal.hidden {
                display: none !important;
            }
            .oc-cancellation-policy-modal__panel {
                display: flex;
                flex-direction: column;
                width: 100%;
                max-width: 42rem;
                height: 100%;
                max-height: 100%;
                background: #fff;
                border-radius: 0.5rem;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
                overflow: hidden;
            }
            .oc-cancellation-policy-modal__header {
                flex-shrink: 0;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1rem 1.25rem;
                border-bottom: 1px solid #e5e7eb;
                background: #fff;
            }
            .oc-cancellation-policy-modal__title {
                margin: 0;
                font-size: 1.125rem;
                font-weight: 700;
                color: #111827;
                line-height: 1.35;
            }
            .oc-cancellation-policy-modal__close {
                flex-shrink: 0;
                border: none;
                background: transparent;
                color: #6b7280;
                font-size: 1.75rem;
                line-height: 1;
                padding: 0.25rem;
                cursor: pointer;
            }
            .oc-cancellation-policy-modal__close:hover {
                color: #dc2626;
            }
            .oc-cancellation-policy-modal__body {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                padding: 1.25rem 1.5rem;
                color: #374151;
            }
            .oc-cancellation-policy-modal__footer {
                flex-shrink: 0;
                display: flex;
                justify-content: flex-end;
                padding: 1rem 1.25rem;
                border-top: 1px solid #e5e7eb;
                background: #f9fafb;
            }
            .oc-cancellation-policy-modal__footer-btn {
                border: none;
                border-radius: 0.375rem;
                padding: 0.625rem 1.25rem;
                font-size: 0.9375rem;
                font-weight: 600;
                color: #fff;
                background: #2563eb;
                cursor: pointer;
            }
            .oc-cancellation-policy-modal__footer-btn:hover {
                background: #1d4ed8;
            }
            @supports (height: 100dvh) {
                .oc-cancellation-policy-modal {
                    height: 100dvh;
                }
                .oc-cancellation-policy-modal__panel {
                    max-height: calc(100dvh - 2rem);
                }
            }
            </style>
            <script>
            (function () {
                const modal = document.getElementById('cancellation-policy-modal');
                const openBtn = document.getElementById('open-cancellation-policy-modal');
                const closeButtons = modal.querySelectorAll('[data-close-cancellation-policy-modal]');
                if (!modal || !openBtn) {
                    return;
                }

                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }

                function openCancellationPolicyModal() {
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.style.overflow = 'hidden';
                }

                function closeCancellationPolicyModal() {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.style.overflow = '';
                }

                openBtn.addEventListener('click', openCancellationPolicyModal);
                closeButtons.forEach(function (button) {
                    button.addEventListener('click', closeCancellationPolicyModal);
                });
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        closeCancellationPolicyModal();
                    }
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeCancellationPolicyModal();
                    }
                });
            })();
            </script>
            <?php endif; ?>
            <?php if ($has_ulteriori_info): ?>
            <div class="mt-8">
                <h3 class="font-bold text-lg mb-2"><?php _e('Ulteriori informazioni', 'opencomune'); ?></h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700">
                    <?php if (opencomune_has_field_value($tipo_esperienza)): ?>
                    <div><b><?php _e('Tipo di esperienza', 'opencomune'); ?>:</b> <?php echo esc_html($tipo_esperienza); ?></div>
                    <?php endif; ?>
                    <?php if (opencomune_has_field_value($scadenza_prenotazioni)): ?>
                    <div><b><?php _e('Prenotare in anticipo', 'opencomune'); ?>:</b> <?php echo esc_html($scadenza_prenotazioni); ?></div>
                    <?php endif; ?>
                    <?php if ($durata_label): ?>
                    <div><b><?php _e('Durata', 'opencomune'); ?>:</b> <?php echo esc_html($durata_label); ?></div>
                    <?php endif; ?>
                    <?php if ($difficolta_label): ?>
                    <div class="flex flex-wrap items-center gap-2"><b><?php _e('Difficoltà', 'opencomune'); ?>:</b> <?php echo opencomune_render_tour_difficulty_badge($difficolta); ?></div>
                    <?php endif; ?>
                    <?php if (!empty($categorie_arr)): ?>
                    <div><b><?php _e('Categorie', 'opencomune'); ?>:</b>
                        <?php foreach ($categorie_arr as $c): ?>
                            <span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 mr-1 text-xs"><?php echo esc_html($c); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($lingue_arr)): ?>
                    <div><b><?php _e('Lingue disponibili', 'opencomune'); ?>:</b>
                        <?php foreach ($lingue_arr as $l): ?>
                            <span class="inline-block bg-blue-100 text-blue-800 rounded px-2 py-1 mr-1 text-xs"><?php echo esc_html($l); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($allegati_items)): ?>
                    <div class="md:col-span-2">
                        <b><?php _e('Allegati', 'opencomune'); ?>:</b>
                        <ul class="mt-2 space-y-2">
                            <?php foreach ($allegati_items as $allegato): ?>
                            <li>
                                <a href="<?php echo esc_url($allegato['url']); ?>" class="inline-flex items-center gap-2 text-blue-700 hover:text-blue-900" target="_blank" rel="noopener noreferrer" download>
                                    <i class="bi bi-download"></i>
                                    <?php echo esc_html($allegato['title']); ?>
                                </a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <!-- SIDEBAR -->
        <aside class="w-full md:w-96 flex-shrink-0 md:sticky md:top-8 h-fit bg-white rounded-xl shadow p-6">
            <?php if ($show_booking_form) : ?>
            <h3 class="font-bold text-lg mb-2"><?php _e('Partecipanti', 'opencomune'); ?></h3>
            <?php if (!empty($pricing_summary['label'])) : ?>
            <p class="text-sm text-gray-600 mb-4"><?php echo esc_html($pricing_summary['label']); ?></p>
            <?php endif; ?>
            <div id="participant-categories" class="space-y-3 mb-6">
                <?php if (!empty($pricing_categories_public)) : ?>
                    <?php foreach ($pricing_categories_public as $cat) : ?>
                        <div class="flex items-center justify-between gap-3 participant-category-row" data-category-id="<?php echo esc_attr($cat['id']); ?>" data-unit-price="<?php echo esc_attr((string) $cat['price']); ?>" data-is-default="<?php echo !empty($cat['is_default']) ? '1' : '0'; ?>">
                            <div class="min-w-0">
                                <span class="block font-medium text-gray-900"><?php echo esc_html($cat['label']); ?></span>
                                <span class="text-sm text-gray-500">
                                    <?php
                                    $cat_price = (float) $cat['price'];
                                    echo $cat_price > 0
                                        ? esc_html('€ ' . number_format($cat_price, 2, ',', '.'))
                                        : esc_html__('Gratuito', 'opencomune');
                                    ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" class="participant-minus px-2 py-1 bg-gray-200 rounded" aria-label="<?php esc_attr_e('Meno', 'opencomune'); ?>">-</button>
                                <span class="participant-count w-6 text-center" data-category-count="<?php echo esc_attr($cat['id']); ?>"><?php echo !empty($cat['is_default']) ? '1' : '0'; ?></span>
                                <button type="button" class="participant-plus px-2 py-1 bg-gray-200 rounded" aria-label="<?php esc_attr_e('Più', 'opencomune'); ?>">+</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="flex items-center gap-2">
                        <button id="adulti-minus" type="button" class="px-2 py-1 bg-gray-200 rounded">-</button>
                        <span id="adulti-count">1</span>
                        <button id="adulti-plus" type="button" class="px-2 py-1 bg-gray-200 rounded">+</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-sm font-semibold text-gray-900 mb-4" id="booking-total-wrap">
                <?php _e('Totale stimato', 'opencomune'); ?>:
                <span id="booking-total-estimate">—</span>
            </div>
            <?php endif; ?>
            <?php if ($show_booking_form): ?>
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
                
                <!-- Pulsante Prenota ora -->
                <div id="prenota-section" class="hidden">
                    <button type="button" id="prenota-ora-btn" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                        <i class="bi bi-calendar-check mr-2"></i><?php echo $booking_requires_approval ? esc_html__('Invia richiesta', 'opencomune') : esc_html__('Continua', 'opencomune'); ?>
                    </button>
                </div>
            </div>
            <?php else: ?>
            <div class="mb-6">
                <?php if ($tour_start_date): ?>
                <div class="bg-gray-50 border border-gray-200 rounded p-4 mb-4">
                    <h4 class="font-semibold text-gray-900 mb-2">
                        <?php
                        echo ($tour_date_mode === 'range' && $tour_end_date)
                            ? esc_html__('Date dell\'esperienza', 'opencomune')
                            : esc_html__('Data dell\'esperienza', 'opencomune');
                        ?>
                    </h4>
                    <p class="text-sm text-gray-700">
                        <?php if ($tour_date_mode === 'range' && $tour_end_date): ?>
                            <?php echo esc_html(date_i18n('j F Y', strtotime($tour_start_date))); ?>
                            &mdash;
                            <?php echo esc_html(date_i18n('j F Y', strtotime($tour_end_date))); ?>
                        <?php else: ?>
                            <?php echo esc_html(date_i18n('j F Y', strtotime($tour_start_date))); ?>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php if ($is_partner_experience && $partner_booking_url): ?>
                <a href="<?php echo esc_url($partner_booking_url); ?>" target="_blank" rel="noopener noreferrer" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center block">
                    <i class="bi bi-external-link mr-2"></i><?php _e('Prenota sul Sito Partner', 'opencomune'); ?>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <!-- Sezione riepilogo/prenotazione -->
            <?php if ($show_booking_form): ?>
            <div class="bg-blue-50 border border-blue-200 rounded p-4">
                  <?php if ($organizer_name !== '') : ?>
                    <?php if ($partner_profile_url) : ?>
                    <a href="<?php echo esc_url($partner_profile_url); ?>" class="esperienza-organizer-card">
                    <?php else : ?>
                    <div class="esperienza-organizer-card">
                    <?php endif; ?>
                        <div class="esperienza-organizer-card__avatar" aria-hidden="true">
                            <?php if ($organizer_avatar_url) : ?>
                                <img src="<?php echo esc_url($organizer_avatar_url); ?>" alt="<?php echo esc_attr($organizer_name); ?>">
                            <?php else : ?>
                                <span class="esperienza-organizer-card__avatar-fallback"><i class="bi bi-person"></i></span>
                            <?php endif; ?>
                        </div>
                        <div class="esperienza-organizer-card__content">
                            <span class="esperienza-organizer-card__label"><?php _e('Organizzato da', 'opencomune'); ?></span>
                            <span class="esperienza-organizer-card__name"><?php echo esc_html($organizer_name); ?></span>
                        </div>
                    <?php if ($partner_profile_url) : ?>
                    </a>
                    <?php else : ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php elseif ($is_partner_experience): ?>
            <h3 class="font-bold text-lg mb-4"><?php _e('Esperienza Partner', 'opencomune'); ?></h3>
            <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                <?php if ($partner_profile_url): ?>
                <a href="<?php echo esc_url($partner_profile_url); ?>" class="esperienza-partner-badge">
                <?php else: ?>
                <div class="esperienza-partner-badge">
                <?php endif; ?>
                    <div class="esperienza-partner-badge-logo">
                        <?php if ($partner_avatar_url): ?>
                        <img src="<?php echo esc_url($partner_avatar_url); ?>" alt="<?php echo esc_attr($partner_display_name); ?>">
                        <?php else: ?>
                        <span class="esperienza-partner-badge-logo-fallback"><i class="bi bi-building"></i></span>
                        <?php endif; ?>
                    </div>
                    <div class="esperienza-partner-badge-content">
                        <span class="esperienza-partner-badge-label">
                            <i class="bi bi-patch-check"></i>
                            <?php _e('Partner certificato', 'opencomune'); ?>
                        </span>
                        <span class="esperienza-partner-badge-name"><?php echo esc_html($partner_display_name); ?></span>
                    </div>
                <?php if ($partner_profile_url): ?>
                </a>
                <?php else: ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <h3 class="font-bold text-lg mb-3"><?php _e('Condividi', 'opencomune'); ?></h3>
                <?php
                get_template_part('template-parts/share', 'buttons', [
                    'urls' => $share_urls,
                    'group_label' => __('Condividi questa esperienza', 'opencomune'),
                ]);
                ?>
            </div>
            <?php if (is_user_logged_in()) : ?>
            <button id="open-review-modal" class="w-full mt-4 bg-blue-600 text-white py-2 rounded font-semibold hover:bg-blue-700 transition">Lascia una recensione</button>
            <?php endif; ?>
        </aside>
    </div>

    <!-- Modal Prenotazione -->
    <div id="bookingModal" class="fixed inset-0 bg-black bg-opacity-60 overflow-y-auto h-full w-full z-50" style="display: none;">
        <div class="relative top-10 mx-auto p-0 w-11/12 md:w-2/3 lg:w-1/2 max-w-2xl" style="padding: 10px;">
            <!-- Card principale con design moderno -->
            <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                <!-- Header con gradiente -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                          
                            <div>
                                <h3 class="text-xl font-bold text-black">Conferma Presenza</h3>
                                <p class="text-blue-100 text-sm">Completa i tuoi dati per prenotare</p>
                            </div>
                        </div>
                        <button id="close-booking-modal-x" class="text-white hover:text-blue-200 transition-colors p-2 rounded-full hover:bg-white hover:bg-opacity-20">
                            <i class="bi bi-x-lg text-xl"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Contenuto -->
                <div class="p-6">
                    <!-- Riepilogo prenotazione -->
                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-xl p-4 mb-6">
                        <div class="flex items-center mb-3">
                   
                            <h4 class="font-semibold text-gray-800">Dettagli della richiesta</h4>
                        </div>
                        <div id="booking-summary" class="text-sm text-gray-700">
                            <!-- I dettagli verranno inseriti qui via JavaScript -->
                        </div>
                    </div>
                    
                    <!-- Form dati utente -->
                    <form id="bookingForm" class="space-y-6">
                        <!-- Nome e Cognome -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-person mr-1"></i>Nome *
                                </label>
                                <input type="text" id="booking-nome" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" placeholder="Il tuo nome" required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="bi bi-person mr-1"></i>Cognome *
                                </label>
                                <input type="text" id="booking-cognome" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" placeholder="Il tuo cognome" required>
                            </div>
                        </div>
                        
                        <!-- Email -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-envelope mr-1"></i>Email *
                            </label>
                            <input type="email" id="booking-email" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" placeholder="tua.email@esempio.com" required>
                        </div>
                        
                        <!-- Telefono con prefisso -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-telephone mr-1"></i>Telefono
                            </label>
                            <div class="flex space-x-2">
                                <?php
                                opencomune_render_phone_prefix_select([
                                    'id' => 'booking-prefisso',
                                    'name' => '',
                                    'class' => 'px-3 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all bg-white',
                                    'selected' => opencomune_get_default_phone_prefix(),
                                ]);
                                ?>
                                <input type="tel" id="booking-telefono" class="flex-1 px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all" placeholder="Numero di telefono">
                            </div>
                        </div>
                        
                        <!-- Note -->
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="bi bi-chat-text mr-1"></i>Note (opzionale)
                            </label>
                            <textarea id="booking-note" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none" placeholder="Eventuali richieste speciali..."></textarea>
                        </div>
                        
                        <!-- Privacy e consenso -->
                        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4">
                            <div class="flex items-start space-x-3">
                                <input type="checkbox" id="booking-privacy" class="mt-1 w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500" required>
                                <label for="booking-privacy" class="text-sm text-gray-700">
                                    <strong>&nbsp;Consenso al trattamento dei dati personali *</strong><br>
                                    Acconsento al trattamento dei miei dati personali per la gestione della prenotazione e l'invio di comunicazioni relative all'esperienza. 
                                    <a href="#" class="text-blue-600 hover:text-blue-800 underline">Leggi l'informativa privacy</a>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Campi nascosti per i dati della prenotazione -->
                        <input type="hidden" id="booking-tour-id" value="<?php echo $id; ?>">
                        <input type="hidden" id="booking-event-id">
                        <input type="hidden" id="booking-data">
                        <input type="hidden" id="booking-ora">
                        <input type="hidden" id="booking-participants">
                        
                        <!-- Pulsanti -->
                        <div class="flex gap-0 pt-4">
                            <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 transition-all transform hover:scale-105 shadow-lg">
                                <i class="bi bi-check-circle mr-2"></i>Conferma Prenotazione
                            </button>
                            <button type="button" id="close-booking-modal" class="px-6 py-3 text-gray-600 bg-gray-100 hover:bg-gray-200 font-semibold transition-all">
                                <i class="bi bi-x-circle mr-2"></i>Annulla
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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

.esperienza-organizer-card {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  margin-top: 0.25rem;
  padding: 0.75rem;
  background: #fff;
  border: 1px solid #bfdbfe;
  border-radius: 0.75rem;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

a.esperienza-organizer-card:hover {
  border-color: #93c5fd;
  box-shadow: 0 2px 8px rgba(37, 99, 235, 0.12);
}

.esperienza-organizer-card__avatar {
  width: 3.5rem;
  height: 3.5rem;
  flex-shrink: 0;
  border-radius: 50%;
  border: 2px solid #dbeafe;
  background: #f8fafc;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}

.esperienza-organizer-card__avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.esperienza-organizer-card__avatar-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #64748b;
  font-size: 1.35rem;
}

.esperienza-organizer-card__content {
  display: flex;
  flex-direction: column;
  gap: 0.125rem;
  min-width: 0;
}

.esperienza-organizer-card__label {
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.02em;
  color: #1d4ed8;
}

.esperienza-organizer-card__name {
  font-size: 1rem;
  font-weight: 600;
  color: #0f172a;
  line-height: 1.35;
  word-break: break-word;
}

.esperienza-partner-badge {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.75rem;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 0.75rem;
  text-decoration: none;
  color: inherit;
  transition: box-shadow 0.2s ease, border-color 0.2s ease;
}

a.esperienza-partner-badge:hover {
  border-color: #d1d5db;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.esperienza-partner-badge-logo {
  width: 4rem;
  height: 4rem;
  flex-shrink: 0;
  border-radius: 0.5rem;
  border: 1px solid #e5e7eb;
  background: #f9fafb;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.esperienza-partner-badge-logo img {
  max-width: 100%;
  max-height: 100%;
  width: auto;
  height: auto;
  object-fit: contain;
  padding: 0.375rem;
}

.esperienza-partner-badge-logo-fallback {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
  height: 100%;
  color: #9ca3af;
  font-size: 1.5rem;
}

.esperienza-partner-badge-content {
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
  min-width: 0;
}

.esperienza-partner-badge-label {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: #166534;
}

.esperienza-partner-badge-name {
  font-size: 1rem;
  font-weight: 600;
  color: #111827;
  line-height: 1.3;
  word-break: break-word;
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
    // Rimuovi le classi attive da tutti i bottoni
    tabBtns.forEach(b => {
        b.classList.remove('border-blue-600', 'text-blue-700');
        b.classList.add('border-transparent', 'text-gray-600');
    });
    
    // Aggiungi le classi attive al bottone cliccato
    this.classList.remove('border-transparent', 'text-gray-600');
    this.classList.add('border-blue-600', 'text-blue-700');
    
    // Nascondi tutti i tab content
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    
    // Mostra il tab content selezionato
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
    const prezzoBase = <?php echo (float) $prezzo; ?>;
    const participantRows = document.querySelectorAll('.participant-category-row');
    const bookingTotalEl = document.getElementById('booking-total-estimate');
    const BOOKING_PARTICIPANT_FALLBACK_MAX = 99;
    const bookingGlobalMax = <?php echo $booking_global_max_participants !== null ? (int) $booking_global_max_participants : 'null'; ?>;
    let maxTotalParticipants = bookingGlobalMax !== null ? bookingGlobalMax : BOOKING_PARTICIPANT_FALLBACK_MAX;
    const participantRefreshers = [];
    let legacyParticipantRefresh = null;

    window.opencomuneBookingMaxParticipants = maxTotalParticipants;

    function formatEuro(amount) {
        if (amount <= 0) {
            return '€ 0,00';
        }
        return '€ ' + amount.toFixed(2).replace('.', ',');
    }

    function getParticipantPayload() {
        const payload = [];
        let totalPax = 0;
        participantRows.forEach(function (row) {
            const categoryId = row.getAttribute('data-category-id');
            const countEl = row.querySelector('.participant-count');
            const qty = parseInt(countEl ? countEl.textContent : '0', 10) || 0;
            if (qty > 0) {
                payload.push({ category_id: categoryId, quantity: qty });
                totalPax += qty;
            }
        });
        return { payload, totalPax };
    }

    function getTotalParticipantCount() {
        if (participantRows.length) {
            return getParticipantPayload().totalPax;
        }
        const adultiCount = document.getElementById('adulti-count');
        return parseInt(adultiCount ? adultiCount.textContent : '1', 10) || 0;
    }

    function refreshAllParticipantControls() {
        participantRefreshers.forEach(function (refresh) {
            refresh();
        });
        if (typeof legacyParticipantRefresh === 'function') {
            legacyParticipantRefresh();
        }
    }

    function clampParticipantsToMax() {
        if (maxTotalParticipants <= 0) {
            if (participantRows.length) {
                participantRows.forEach(function (row) {
                    const countEl = row.querySelector('.participant-count');
                    if (countEl) {
                        countEl.textContent = '0';
                    }
                });
            } else {
                const adultiCount = document.getElementById('adulti-count');
                if (adultiCount) {
                    adultiCount.textContent = '0';
                }
            }
            refreshAllParticipantControls();
            return;
        }

        let guard = 0;
        while (getTotalParticipantCount() > maxTotalParticipants && guard < 500) {
            guard += 1;
            let reduced = false;

            if (participantRows.length) {
                participantRows.forEach(function (row) {
                    if (reduced) {
                        return;
                    }
                    const isDefault = row.getAttribute('data-is-default') === '1';
                    const countEl = row.querySelector('.participant-count');
                    const qty = parseInt(countEl ? countEl.textContent : '0', 10) || 0;
                    const minQty = isDefault ? 1 : 0;
                    if (qty > minQty) {
                        countEl.textContent = String(qty - 1);
                        reduced = true;
                    }
                });
            } else {
                const adultiCount = document.getElementById('adulti-count');
                const qty = parseInt(adultiCount ? adultiCount.textContent : '1', 10) || 1;
                if (qty > 1) {
                    adultiCount.textContent = String(qty - 1);
                    reduced = true;
                }
            }

            if (!reduced) {
                break;
            }
        }

        refreshAllParticipantControls();
    }

    window.opencomuneApplyBookingSlotCapacity = function (availableSpots) {
        if (availableSpots === null || availableSpots === undefined || availableSpots === '') {
            maxTotalParticipants = bookingGlobalMax !== null ? bookingGlobalMax : BOOKING_PARTICIPANT_FALLBACK_MAX;
        } else {
            maxTotalParticipants = Math.max(0, parseInt(availableSpots, 10) || 0);
        }
        window.opencomuneBookingMaxParticipants = maxTotalParticipants;
        clampParticipantsToMax();
    };

    function canAddParticipant() {
        return getTotalParticipantCount() < maxTotalParticipants;
    }

    function updateBookingTotal() {
        let total = 0;
        participantRows.forEach(function (row) {
            const unit = parseFloat(row.getAttribute('data-unit-price') || '0') || 0;
            const countEl = row.querySelector('.participant-count');
            const qty = parseInt(countEl ? countEl.textContent : '0', 10) || 0;
            total += unit * qty;
        });
        if (bookingTotalEl) {
            bookingTotalEl.textContent = formatEuro(total);
        }
    }

    function bindParticipantRow(row) {
        const countEl = row.querySelector('.participant-count');
        const minusBtn = row.querySelector('.participant-minus');
        const plusBtn = row.querySelector('.participant-plus');
        const isDefault = row.getAttribute('data-is-default') === '1';

        function refreshButtons() {
            const qty = parseInt(countEl.textContent, 10) || 0;
            const minQty = isDefault ? 1 : 0;
            if (minusBtn) {
                minusBtn.disabled = qty <= minQty;
            }
            if (plusBtn) {
                plusBtn.disabled = !canAddParticipant();
            }
            updateBookingTotal();
        }

        participantRefreshers.push(refreshButtons);

        if (minusBtn) {
            minusBtn.addEventListener('click', function () {
                const qty = parseInt(countEl.textContent, 10) || 0;
                const minQty = isDefault ? 1 : 0;
                if (qty > minQty) {
                    countEl.textContent = String(qty - 1);
                    refreshAllParticipantControls();
                }
            });
        }
        if (plusBtn) {
            plusBtn.addEventListener('click', function () {
                const qty = parseInt(countEl.textContent, 10) || 0;
                if (canAddParticipant()) {
                    countEl.textContent = String(qty + 1);
                    refreshAllParticipantControls();
                }
            });
        }
        refreshButtons();
    }

    if (participantRows.length) {
        participantRows.forEach(bindParticipantRow);
    } else {
        let adulti = 1;
        const minAdulti = 1;
        const adultiCount = document.getElementById('adulti-count');
        const adultiMinus = document.getElementById('adulti-minus');
        const adultiPlus = document.getElementById('adulti-plus');
        function aggiornaTotaleLegacy() {
            if (adultiCount) {
                adultiCount.textContent = adulti;
            }
            if (bookingTotalEl) {
                bookingTotalEl.textContent = formatEuro(adulti * prezzoBase);
            }
            if (adultiMinus) {
                adultiMinus.disabled = adulti <= minAdulti;
            }
            if (adultiPlus) {
                adultiPlus.disabled = !canAddParticipant();
            }
        }
        legacyParticipantRefresh = aggiornaTotaleLegacy;
        if (adultiMinus) {
            adultiMinus.addEventListener('click', function () {
                if (adulti > minAdulti) {
                    adulti--;
                    aggiornaTotaleLegacy();
                }
            });
        }
        if (adultiPlus) {
            adultiPlus.addEventListener('click', function () {
                if (canAddParticipant()) {
                    adulti++;
                    aggiornaTotaleLegacy();
                }
            });
        }
        aggiornaTotaleLegacy();
    }

    window.opencomuneGetBookingParticipantsPayload = function () {
        if (participantRows.length) {
            const data = getParticipantPayload();
            if (data.totalPax <= 0) {
                return null;
            }
            if (data.totalPax > window.opencomuneBookingMaxParticipants) {
                return null;
            }
            return data.payload;
        }
        const adultiCount = document.getElementById('adulti-count');
        const participants = parseInt(adultiCount ? adultiCount.textContent : '1', 10) || 1;
        if (participants > window.opencomuneBookingMaxParticipants) {
            return null;
        }
        const categoryId = defaultPricingCategoryId;
        return categoryId
            ? [{ category_id: categoryId, quantity: participants }]
            : [{ category_id: '', quantity: participants }];
    };
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
    window.opencomuneTourId = tourId;
    window.opencomuneBookingRequiresApproval = <?php echo $booking_requires_approval ? 'true' : 'false'; ?>;
    var showBookingForm = <?php echo $show_booking_form ? 'true' : 'false'; ?>;
    var checkoutSessionsUrl = <?php echo wp_json_encode(rest_url('opencomune/v1/checkout/sessions')); ?>;
    window.opencomuneCheckoutSessionsUrl = checkoutSessionsUrl;
    var pricingCategories = <?php echo wp_json_encode($pricing_categories_public); ?>;
    var defaultPricingCategoryId = '';
    pricingCategories.forEach(function (cat) {
        if (cat.is_default) {
            defaultPricingCategoryId = cat.id;
        }
    });
    if (!defaultPricingCategoryId && pricingCategories.length) {
        defaultPricingCategoryId = pricingCategories[0].id;
    }
    var calendarioEvents = [];
    
    <?php if ($show_booking_form): ?>
    // Carica gli eventi del calendario
    var calFrom = new Date().toISOString().slice(0, 10);
    var calToDate = new Date();
    calToDate.setMonth(calToDate.getMonth() + 4);
    var calTo = calToDate.toISOString().slice(0, 10);
    fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=opencomune_get_calendario&tour_id=' + tourId + '&from=' + calFrom + '&to=' + calTo)
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
                    if (typeof window.opencomuneApplyBookingSlotCapacity === 'function') {
                        window.opencomuneApplyBookingSlotCapacity(null);
                    }
                    window.selectedBookingData = null;
                    document.querySelectorAll('#lista-orari button').forEach(function (btn) {
                        btn.classList.remove('bg-blue-600', 'text-white', 'orario-selezionato');
                        btn.classList.add('border-gray-200', 'hover:bg-blue-50');
                    });
                    const prenotaSection = document.getElementById('prenota-section');
                    if (prenotaSection) {
                        prenotaSection.classList.add('hidden');
                    }
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
                var orario = evento.start.split('T')[1].substring(0, 5);
                var ruleMax = typeof evento.max_participants === 'number'
                    ? parseInt(evento.max_participants, 10)
                    : parseInt(evento.max_participants, 10);
                if (isNaN(ruleMax)) {
                    ruleMax = 0;
                }
                var availableSpots = typeof evento.available_spots === 'number'
                    ? evento.available_spots
                    : parseInt(evento.available_spots, 10);
                if (isNaN(availableSpots)) {
                    availableSpots = 0;
                }
                var capacityLimit = availableSpots;
                var isBookable = evento.bookable !== false;
                var requiresApproval = evento.requires_approval === true || window.opencomuneBookingRequiresApproval === true;
                if (ruleMax > 0) {
                    isBookable = isBookable && availableSpots > 0;
                } else {
                    capacityLimit = bookingGlobalMax !== null ? bookingGlobalMax : BOOKING_PARTICIPANT_FALLBACK_MAX;
                }
                var availabilityBadge = '';
                if (!isBookable) {
                    availabilityBadge = '<span class="oc-slot-badge oc-slot-badge--soldout">Esaurito</span>';
                } else if (requiresApproval) {
                    availabilityBadge = '<span class="oc-slot-badge oc-slot-badge--request">Su richiesta</span>';
                } else if (ruleMax > 0 && availableSpots > 0) {
                    availabilityBadge = '<span class="oc-slot-badge oc-slot-badge--available">' + availableSpots + ' posti disponibili</span>';
                } else {
                    availabilityBadge = '<span class="oc-slot-badge oc-slot-badge--available">Disponibile</span>';
                }

                var orarioBtn = document.createElement('button');
                orarioBtn.type = 'button';
                orarioBtn.className = 'oc-slot-time-btn w-full text-left p-3 border border-gray-200 rounded hover:bg-blue-50 hover:border-blue-300 transition';
                orarioBtn.disabled = !isBookable;
                if (!isBookable) {
                    orarioBtn.classList.add('opacity-50', 'cursor-not-allowed');
                }
                orarioBtn.innerHTML = '<div class="flex justify-between items-center gap-3"><span class="font-semibold">' + orario + '</span>' + availabilityBadge + '</div>';
                orarioBtn.dataset.availableSpots = String(capacityLimit);
                orarioBtn.onclick = function() {
                    if (!isBookable) {
                        return;
                    }
                    // Rimuovi selezione precedente
                    document.querySelectorAll('#lista-orari button').forEach(function(btn) {
                        btn.classList.remove('bg-blue-600', 'text-white', 'orario-selezionato');
                        btn.classList.add('border-gray-200', 'hover:bg-blue-50');
                    });
                    
                    // Seleziona questo orario
                    this.classList.remove('border-gray-200', 'hover:bg-blue-50');
                    this.classList.add('bg-blue-600', 'text-white', 'orario-selezionato');
                    
                    var isoDate = data || (evento.start ? evento.start.split('T')[0] : '');
                    this.dataset.eventId = String(evento.id);
                    this.dataset.time = orario;
                    this.dataset.date = isoDate;

                    window.selectedBookingData = {
                        data: isoDate,
                        orario: orario,
                        eventId: evento.id,
                        availableSpots: capacityLimit
                    };

                    if (typeof window.opencomuneApplyBookingSlotCapacity === 'function') {
                        window.opencomuneApplyBookingSlotCapacity(capacityLimit);
                    }

                    if (showBookingForm) {
                        const prenotaSection = document.getElementById('prenota-section');
                        if (prenotaSection) {
                            if (capacityLimit > 0) {
                                prenotaSection.classList.remove('hidden');
                            } else {
                                prenotaSection.classList.add('hidden');
                            }
                        }
                    }
                };
                listaOrari.appendChild(orarioBtn);
            });
        } else {
            listaOrari.innerHTML = '<p class="text-gray-500 text-center py-4">Nessun orario disponibile per questo giorno</p>';
        }
    }
    <?php endif; ?>
    
    
            
           
    
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
    
 
    
 
   
    
   
    
 
    
    document.getElementById('close-prenota-modal').addEventListener('click', closeBookingModal);
    document.getElementById('cancel-prenota').addEventListener('click', closeBookingModal);
    
    // Chiudi modal cliccando fuori
    document.getElementById('prenota-modal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeBookingModal();
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
                closeBookingModal();
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

 
<style>
    .step-indicator {
        width: 20px;
        height: 20px;
        
    }
</style>

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
    // Inizializza mappa del punto di ritrovo
    <?php if ($lat && $lon): ?>
    function initMapRitrovo() {
        var lat = <?php echo floatval($lat); ?>;
        var lng = <?php echo floatval($lon); ?>;
        
        if (isNaN(lat) || isNaN(lng)) {
            console.error('Coordinate non valide per la mappa del punto di ritrovo');
            return;
        }
        
        var mapElement = document.getElementById('map-ritrovo');
        if (!mapElement) return;
        
        // Inizializza la mappa Leaflet
        var map = L.map('map-ritrovo').setView([lat, lng], 15);
        
        // Aggiungi il layer OpenStreetMap
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);
        
        // Aggiungi marker classico per il punto di ritrovo
        var marker = L.marker([lat, lng]).addTo(map);
        
        // Aggiungi popup al marker
        marker.bindPopup('<div style="padding: 10px;"><strong>Punto di ritrovo</strong><br><?php echo esc_js($indirizzo_ritrovo); ?></div>');
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
                            // Inizializza la mappa OpenStreetMap
                            initMapRitrovo();
                            mapInitialized = true;
                        }, 100);
                    }
                }
            });
        });
        
        observer.observe(tabRitrovo, { attributes: true });
    });
    <?php endif; ?>
    
    // === GESTIONE PRENOTAZIONE ===
    // Gestione click pulsante prenota (solo per esperienze non-partner)
    const prenotaBtn = document.getElementById('prenota-ora-btn');
    if (prenotaBtn) {
        prenotaBtn.addEventListener('click', function() {
            openBookingModal();
        });
    }
    
    // Gestione form prenotazione
    const bookingForm = document.getElementById('bookingForm');
    if (bookingForm) {
        bookingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBooking();
        });
    }
    
    // Gestione chiusura modal prenotazione
    const closeBookingModalBtn = document.getElementById('close-booking-modal');
    if (closeBookingModalBtn) {
        closeBookingModalBtn.addEventListener('click', closeBookingModal);
    }
    
    // Gestione click su X del modal
    const closeBookingModalX = document.getElementById('close-booking-modal-x');
    if (closeBookingModalX) {
        closeBookingModalX.addEventListener('click', closeBookingModal);
    }
    
    // Gestione click fuori dal modal per chiuderlo
    const bookingModal = document.getElementById('bookingModal');
    if (bookingModal) {
        bookingModal.addEventListener('click', function(e) {
            if (e.target === bookingModal) {
                closeBookingModal();
            }
        });
    }
 

// === FUNZIONI PRENOTAZIONE ===
let selectedEventData = null;

function openBookingModal() {
    const selectedTime = document.querySelector('.orario-selezionato');
    if (!selectedTime) {
        alert('Seleziona prima un orario disponibile');
        return;
    }

    const bookingData = window.selectedBookingData || {};
    const isoDate = bookingData.data || selectedTime.dataset.date || '';
    const eventIdRaw = selectedTime.dataset.eventId || bookingData.eventId;
    const eventId = parseInt(eventIdRaw, 10);
    const time = selectedTime.dataset.time || bookingData.orario || '';
    if (!isoDate || !eventIdRaw || eventId <= 0 || !time) {
        alert('Dati prenotazione incompleti. Seleziona di nuovo data e orario.');
        return;
    }

    const participantsPayload = typeof window.opencomuneGetBookingParticipantsPayload === 'function'
        ? window.opencomuneGetBookingParticipantsPayload()
        : null;
    if (!participantsPayload || !participantsPayload.length) {
        const maxPax = typeof window.opencomuneBookingMaxParticipants === 'number'
            ? window.opencomuneBookingMaxParticipants
            : null;
        if (maxPax !== null && maxPax <= 0) {
            alert('Non ci sono posti disponibili per questo orario.');
        } else if (maxPax !== null) {
            alert('Il numero di partecipanti supera i posti disponibili (' + maxPax + ').');
        } else {
            alert('Seleziona almeno un partecipante.');
        }
        return;
    }

    const prenotaBtn = document.getElementById('prenota-ora-btn');
    if (prenotaBtn) {
        prenotaBtn.disabled = true;
        prenotaBtn.textContent = 'Apertura checkout…';
    }

    const sessionsUrl = window.opencomuneCheckoutSessionsUrl || '';
    const experienceId = window.opencomuneTourId || 0;
    if (!sessionsUrl || !experienceId) {
        alert('Configurazione checkout non disponibile. Ricarica la pagina.');
        return;
    }

    fetch(sessionsUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            experience_id: experienceId,
            event_id: eventId,
            date: isoDate,
            time: time,
            participants: participantsPayload,
        }),
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data && data.checkout_url) {
                window.location.href = data.checkout_url;
                return;
            }
            const message = (data && data.message) ? data.message : 'Impossibile avviare il checkout.';
            alert(message);
        })
        .catch(function () {
            alert('Errore di rete durante l\'avvio del checkout.');
        })
        .finally(function () {
            if (prenotaBtn) {
                prenotaBtn.disabled = false;
                prenotaBtn.innerHTML = '<i class="bi bi-calendar-check mr-2"></i><?php echo esc_js($booking_requires_approval ? __('Invia richiesta', 'opencomune') : __('Continua', 'opencomune')); ?>';
            }
        });
}

function closeBookingModal() {
    document.getElementById('bookingModal').style.display = 'none';
    selectedEventData = null;
}

function submitBooking() {
    // Verifica che la privacy sia accettata
    if (!document.getElementById('booking-privacy').checked) {
        alert('Devi accettare il trattamento dei dati personali per procedere.');
        return;
    }
    
    // Combina prefisso e numero di telefono
    const prefisso = document.getElementById('booking-prefisso').value;
    const numero = document.getElementById('booking-telefono').value;
    const telefonoCompleto = numero ? `${prefisso} ${numero}` : '';
    
    const formData = {
        action: 'opencomune_add_prenotazione',
        tour_id: document.getElementById('booking-tour-id').value,
        event_id: document.getElementById('booking-event-id').value,
        nome: document.getElementById('booking-nome').value,
        cognome: document.getElementById('booking-cognome').value,
        email: document.getElementById('booking-email').value,
        telefono: telefonoCompleto,
        note: document.getElementById('booking-note').value,
        participants: document.getElementById('booking-participants').value,
        _wpnonce: '<?php echo wp_create_nonce('opencomune_nonce'); ?>'
    };
    
    // Mostra loading
    const submitBtn = document.querySelector('#bookingForm button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split mr-2"></i>Prenotando...';
    submitBtn.disabled = true;
    
    // Invia la prenotazione
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: Object.keys(formData).map(key => `${key}=${encodeURIComponent(formData[key])}`).join('&')
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('Prenotazione confermata con successo! Riceverai una email di conferma.');
            closeBookingModal();
            // Reset form
            document.getElementById('bookingForm').reset();
        } else {
            alert('Errore: ' + (result.message || 'Errore durante la prenotazione'));
        }
    })
    .catch(error => {
        alert('Errore di connessione. Riprova più tardi.');
        console.error('Errore prenotazione:', error);
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}
</script>

<?php endwhile; endif;
get_footer(); 