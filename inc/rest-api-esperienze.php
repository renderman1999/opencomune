<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_rest_api_permission_check(WP_REST_Request $request) {
    if (!opencomune_is_rest_api_key_required()) {
        return true;
    }

    $configured_key = opencomune_get_rest_api_key();
    if ($configured_key === '') {
        return true;
    }

    $provided = $request->get_header('X-OpenComune-API-Key');
    if ($provided === null || $provided === '') {
        $provided = $request->get_param('api_key');
    }

    return is_string($provided) && hash_equals($configured_key, $provided);
}

function opencomune_get_esperienza_meta_value($post_id, $primary_key, $legacy_key = '') {
    $value = get_post_meta($post_id, $primary_key, true);
    if (($value === '' || $value === null) && $legacy_key !== '') {
        $value = get_post_meta($post_id, $legacy_key, true);
    }

    return $value;
}

function opencomune_get_esperienza_proposal_type($author_id) {
    $author_id = (int) $author_id;
    if ($author_id <= 0) {
        return '';
    }

    if (user_can($author_id, 'partner')) {
        return 'partner_territoriale';
    }

    return 'partner_territoriale';
}

function opencomune_format_esperienza_api($post_id, $options = []) {
    if (is_bool($options)) {
        $options = ['include_content' => $options];
    }

    $options = wp_parse_args($options, [
        'include_content' => true,
        'lang' => 'it',
        'include_translations' => false,
        'availability_from' => '',
        'availability_to' => '',
    ]);

    $lang = opencomune_normalize_api_language($options['lang']);
    $include_content = (bool) $options['include_content'];
    $include_translations = (bool) $options['include_translations'];

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze') {
        return null;
    }

    $localized = opencomune_get_esperienza_translation_bundle($post_id, $lang);

    $author_id = (int) $post->post_author;
    $author = get_userdata($author_id);
    $proposal_type = opencomune_get_esperienza_proposal_type($author_id);
    $partner = $proposal_type === 'partner_territoriale'
        ? opencomune_get_partner_by_user_id($author_id)
        : null;

    $terms = wp_get_post_terms($post_id, 'categorie_esperienze');
    $categorie = [];
    if (!is_wp_error($terms)) {
        foreach ($terms as $term) {
            $category = [
                'id' => (int) $term->term_id,
                'name' => opencomune_get_term_localized_name($term, $lang),
                'slug' => $term->slug,
            ];

            if ($include_translations) {
                $category['translations'] = opencomune_get_term_translation_payload($term);
            }

            $categorie[] = $category;
        }
    }

    $theme_terms = wp_get_post_terms($post_id, 'temi_esperienze');
    $temi = [];
    if (!is_wp_error($theme_terms)) {
        foreach ($theme_terms as $term) {
            $temi[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }
    }

    $inclusion_terms = wp_get_post_terms($post_id, 'inclusioni_esperienze');
    $inclusioni = [];
    if (!is_wp_error($inclusion_terms)) {
        foreach ($inclusion_terms as $term) {
            $inclusioni[] = [
                'id' => (int) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
            ];
        }
    }

    $lang_options = opencomune_get_esperienza_language_options($post_id);

    $esclusioni = [];
    foreach (opencomune_get_esperienza_exclusion_terms($post_id) as $term) {
        $esclusioni[] = [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
        ];
    }

    $gallery_ids = get_post_meta($post_id, 'galleria', true);
    $gallery = [];
    if (is_array($gallery_ids)) {
        foreach ($gallery_ids as $attachment_id) {
            $attachment_id = (int) $attachment_id;
            $url = wp_get_attachment_image_url($attachment_id, 'large');
            if (!$url) {
                continue;
            }
            $gallery[] = [
                'id' => $attachment_id,
                'url' => $url,
                'thumbnail' => wp_get_attachment_image_url($attachment_id, 'medium') ?: $url,
                'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            ];
        }
    }

    $allegati = [];
    foreach (opencomune_get_esperienza_allegati_items($post_id) as $allegato) {
        $allegati[] = [
            'id' => (int) $allegato['id'],
            'title' => $allegato['title'],
            'filename' => $allegato['filename'],
            'url' => $allegato['url'],
        ];
    }

    $featured_id = (int) get_post_thumbnail_id($post_id);
    $featured = null;
    if ($featured_id) {
        $featured = [
            'id' => $featured_id,
            'url' => get_the_post_thumbnail_url($post_id, 'full'),
            'thumbnail' => get_the_post_thumbnail_url($post_id, 'medium'),
            'alt' => get_post_meta($featured_id, '_wp_attachment_image_alt', true),
        ];
    }

    $languages = opencomune_get_esperienza_meta_value($post_id, 'tour_languages', 'lingue');
    if (is_array($languages)) {
        $languages = implode(', ', array_filter(array_map('trim', $languages)));
    }

    $mostra_form = get_post_meta($post_id, 'mostra_form_prenotazione', true);
    if ($mostra_form === '' || $mostra_form === false) {
        $mostra_form = '1';
    }

    $avg_rating = null;
    $reviews_count = 0;
    if (function_exists('opencomune_get_average_rating')) {
        $avg_rating = opencomune_get_average_rating($post_id);
        $reviews_count = (int) get_comments([
            'post_id' => $post_id,
            'status' => 'approve',
            'count' => true,
        ]);
    }

    $date_mode = get_post_meta($post_id, 'tour_date_mode', true) ?: 'single';
    $start_date = get_post_meta($post_id, 'tour_start_date', true);
    $end_date = get_post_meta($post_id, 'tour_end_date', true);
    $dates_label = opencomune_format_esperienza_dates_label_for_lang($post_id, $lang);

    $destination = get_post_meta($post_id, 'tour_destination', true);
    $partner_booking_url = $destination ?: ($author ? $author->user_url : '');
    $duration_payload = opencomune_get_tour_duration_api_payload($post_id);
    $know_before_booking = opencomune_format_esperienza_taxonomy_terms_for_api(
        wp_get_post_terms($post_id, 'info_prima_prenotazione')
    );
    $age_limit = opencomune_get_esperienza_age_limit($post_id);
    $operating_hours = opencomune_get_esperienza_operating_hours($post_id);
    $capacity = opencomune_get_esperienza_capacity_payload($post_id);
    $start_times = opencomune_format_start_times_for_api($post_id);
    $pricing_categories = opencomune_format_pricing_categories_for_api($post_id);
    $pricing = opencomune_format_esperienza_price_api_payload($post_id);
    $meeting_pickup = opencomune_format_meeting_pickup_for_api($post_id);
    $experience_settings = opencomune_format_experience_settings_for_api($post_id);
    $ticket_settings = opencomune_format_ticket_settings_for_api($post_id);
    $experience_identification = opencomune_format_experience_identification_for_api($post_id);

    $data = [
        'id' => (int) $post_id,
        'locale' => $lang,
        'title' => $localized['title'],
        'slug' => $post->post_name,
        'status' => $post->post_status,
        'permalink' => get_permalink($post_id),
        'created_at' => get_post_time('c', true, $post),
        'updated_at' => get_post_time('c', true, $post, true),
        'descrizione_breve' => $localized['descrizione_breve'],
        'excerpt' => $localized['descrizione_breve'],
        'translation_available' => opencomune_get_esperienza_translation_availability($post_id),
        'proposal_type' => $proposal_type,
        'author' => [
            'id' => $author_id,
            'name' => $author ? $author->display_name : '',
            'email' => $author ? $author->user_email : '',
            'url' => $author ? $author->user_url : '',
        ],
        'partner' => $partner ? [
            'id' => (int) $partner->id,
            'nome_attivita' => $partner->nome_attivita,
            'tipologia_attivita' => $partner->tipologia_attivita ?? '',
            'comune' => $partner->comune ?? '',
            'logo_url' => $partner->logo_url ?? '',
            'sito_web' => $partner->sito_web ?? '',
        ] : null,
        'categorie' => $categorie,
        'categoria' => !empty($categorie) ? $categorie[0] : null,
        'temi' => $temi,
        'inclusioni' => $inclusioni,
        'esclusioni' => $esclusioni,
        'language_options' => [
            'live_guide' => $lang_options['live_guide'],
            'audio_guide' => $lang_options['audio_guide'],
            'reading_material' => $lang_options['reading_material'],
        ],
        'before_start' => [
            'know_before_booking' => $know_before_booking,
            'other_notes' => $localized['requirements'],
            'physical_difficulty' => opencomune_get_esperienza_meta_value($post_id, 'tour_difficulty', 'difficolta'),
            'age_limit' => [
                'enabled' => $age_limit['enabled'],
                'minimum_age' => $age_limit['minimum_age'],
            ],
        ],
        'tags' => $temi,
        'media' => [
            'featured' => $featured,
            'gallery' => $gallery,
            'allegati' => $allegati,
        ],
        'location' => [
            'address' => opencomune_get_esperienza_address($post_id),
            'meeting_point' => opencomune_get_esperienza_meeting_point($post_id),
            'city' => opencomune_get_esperienza_meta_value($post_id, 'citta', ''),
            'latitude' => (float) get_post_meta($post_id, 'tour_latitude', true),
            'longitude' => (float) get_post_meta($post_id, 'tour_longitude', true),
            'external_link' => $destination,
        ],
        'scheduling' => [
            'date_mode' => $date_mode,
            'booking_schedule' => opencomune_get_esperienza_booking_schedule_type($post_id),
            'start_date' => $start_date,
            'end_date' => $end_date,
            'dates_label' => $dates_label,
            'meeting_date' => get_post_meta($post_id, 'tour_meeting_date', true),
            'meeting_time' => get_post_meta($post_id, 'tour_meeting_time', true),
            'duration' => $duration_payload['label'],
            'duration_parts' => $duration_payload,
            'start_times' => $start_times,
        ],
        'start_times' => $start_times,
        'pricing_categories' => $pricing_categories,
        'pricing' => $pricing,
        'meeting_pickup' => $meeting_pickup,
        'experience_settings' => $experience_settings,
        'ticket_settings' => $ticket_settings,
        'experience_identification' => $experience_identification,
        'booking_schedule' => opencomune_get_esperienza_booking_schedule_type($post_id),
        'operating_hours' => $operating_hours,
        'capacity' => $capacity,
        'details' => [
            'difficulty' => opencomune_get_esperienza_meta_value($post_id, 'tour_difficulty', 'difficolta'),
            'languages' => $languages,
            'max_participants' => $capacity['max_participants'] !== null ? (string) $capacity['max_participants'] : opencomune_get_esperienza_meta_value($post_id, 'tour_max_participants', 'max_partecipanti'),
            'min_participants' => get_post_meta($post_id, 'min_partecipanti', true),
            'price' => (string) $pricing['amount'],
            'price_amount' => $pricing['amount'],
            'is_free' => $pricing['is_free'],
            'price_label' => $pricing['label'],
            'currency' => $pricing['currency'],
            'price_old' => get_post_meta($post_id, 'prezzo_old', true),
            'tipo_esperienza' => opencomune_get_experience_type_label($experience_settings['experience_type']),
            'scadenza_prenotazioni' => get_post_meta($post_id, 'scadenza_prenotazioni', true),
            'highlights' => $localized['highlights'],
            'itinerary' => $localized['itinerary'],
            'included' => $localized['included'],
            'not_included' => $localized['not_included'],
            'requirements' => $localized['requirements'],
            'cancellation_policy' => $localized['cancellation_policy'],
            'accessibility' => get_post_meta($post_id, 'accessibilita', true),
            'badge' => get_post_meta($post_id, 'badge', true),
            'what_to_bring' => opencomune_get_esperienza_what_to_bring($post_id),
        ],
        'what_to_bring' => opencomune_get_esperienza_what_to_bring($post_id),
        'booking' => array_merge(
            opencomune_format_booking_channel_for_api($post_id),
            [
                'show_form' => $mostra_form === '1',
                'external_url' => $partner_booking_url,
                'checkout_url_pattern' => home_url('/checkout-esperienza/?session={token}'),
            ]
        ),
        'reviews' => [
            'average' => $avg_rating,
            'count' => $reviews_count,
        ],
        'stats' => [
            'views' => (int) get_post_meta($post_id, 'views_count', true),
            'likes' => (int) get_post_meta($post_id, 'likes_count', true),
        ],
        'availability' => [
            'has_future' => opencomune_availability_has_future($post_id),
            'rules_count' => count(opencomune_get_esperienza_availability_rules($post_id)),
        ],
    ];

    $availability_from = sanitize_text_field((string) $options['availability_from']);
    $availability_to = sanitize_text_field((string) $options['availability_to']);
    if ($availability_from !== '' || $availability_to !== '') {
        if ($availability_from === '') {
            $availability_from = gmdate('Y-m-01');
        }
        if ($availability_to === '') {
            $availability_to = gmdate('Y-m-t', strtotime($availability_from));
        }
        $data['availability'] = opencomune_get_availability_api_payload($post_id, $availability_from, $availability_to);
    }

    if ($include_content) {
        if ($lang === 'en' && opencomune_get_esperienza_english_field_value($post_id, 'content') !== '') {
            $content_raw = opencomune_get_esperienza_english_field_value($post_id, 'content');
        } else {
            $content_raw = $post->post_content;
        }

        $data['content'] = apply_filters('the_content', $content_raw);
        $data['content_raw'] = $content_raw;
    }

    if ($include_translations) {
        $data['translations'] = opencomune_get_esperienza_translations_payload($post_id);
    }

    return $data;
}

function opencomune_rest_parse_boolean_param($value) {
    return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
}

function opencomune_rest_get_api_format_options(WP_REST_Request $request, $include_content = true) {
    return [
        'include_content' => $include_content,
        'lang' => opencomune_normalize_api_language((string) $request->get_param('lang')),
        'include_translations' => opencomune_rest_parse_boolean_param($request->get_param('include_translations')),
        'availability_from' => sanitize_text_field((string) $request->get_param('availability_from')),
        'availability_to' => sanitize_text_field((string) $request->get_param('availability_to')),
    ];
}

function opencomune_rest_get_esperienze(WP_REST_Request $request) {
    $page = max(1, (int) $request->get_param('page'));
    $per_page = min(100, max(1, (int) $request->get_param('per_page') ?: 20));
    $search = sanitize_text_field((string) $request->get_param('search'));
    $categoria = sanitize_text_field((string) $request->get_param('categoria'));
    $author_type = sanitize_text_field((string) $request->get_param('author_type'));
    $date_from = sanitize_text_field((string) $request->get_param('date_from'));
    $date_to = sanitize_text_field((string) $request->get_param('date_to'));

    $args = [
        'post_type' => 'esperienze',
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC',
    ];

    if ($search !== '') {
        $args['s'] = $search;
    }

    if ($categoria !== '') {
        $args['tax_query'] = [[
            'taxonomy' => 'categorie_esperienze',
            'field' => 'slug',
            'terms' => $categoria,
        ]];
    }

    if ($author_type === 'ente') {
        $args['author__in'] = wp_list_pluck(get_users(['role' => 'administrator', 'fields' => ['ID']]), 'ID');
    } elseif ($author_type === 'partner') {
        $args['author__in'] = wp_list_pluck(get_users(['role' => 'partner', 'fields' => ['ID']]), 'ID');
    }

    if ($date_from !== '' || $date_to !== '') {
        $args['meta_query'] = ['relation' => 'AND'];
        if ($date_from !== '') {
            $args['meta_query'][] = [
                'key' => 'tour_start_date',
                'value' => $date_from,
                'compare' => '>=',
                'type' => 'DATE',
            ];
        }
        if ($date_to !== '') {
            $args['meta_query'][] = [
                'key' => 'tour_start_date',
                'value' => $date_to,
                'compare' => '<=',
                'type' => 'DATE',
            ];
        }
    }

    $query = new WP_Query($args);
    $items = [];

    $format_options = opencomune_rest_get_api_format_options($request, false);

    foreach ($query->posts as $post) {
        $formatted = opencomune_format_esperienza_api($post->ID, $format_options);
        if ($formatted) {
            $items[] = $formatted;
        }
    }

    $response = rest_ensure_response([
        'items' => $items,
        'pagination' => [
            'page' => $page,
            'per_page' => $per_page,
            'total' => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
        ],
    ]);

    $response->header('X-WP-Total', (int) $query->found_posts);
    $response->header('X-WP-TotalPages', (int) $query->max_num_pages);

    return $response;
}

function opencomune_rest_get_esperienza(WP_REST_Request $request) {
    $post_id = (int) $request['id'];
    $post = get_post($post_id);

    if (!$post || $post->post_type !== 'esperienze' || $post->post_status !== 'publish') {
        return new WP_Error('not_found', __('Esperienza non trovata.', 'opencomune'), ['status' => 404]);
    }

    $data = opencomune_format_esperienza_api($post_id, opencomune_rest_get_api_format_options($request, true));
    return rest_ensure_response($data);
}

function opencomune_rest_get_categorie_esperienze(WP_REST_Request $request) {
    $lang = opencomune_normalize_api_language((string) $request->get_param('lang'));
    $include_translations = opencomune_rest_parse_boolean_param($request->get_param('include_translations'));

    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return $terms;
    }

    $items = array_map(static function ($term) use ($lang, $include_translations) {
        $image = opencomune_esperienza_category_get_image_url((int) $term->term_id);
        $item = [
            'id' => (int) $term->term_id,
            'name' => opencomune_get_term_localized_name($term, $lang),
            'slug' => $term->slug,
            'count' => (int) $term->count,
            'locale' => $lang,
            'image' => $image,
        ];

        if ($include_translations) {
            $item['translations'] = opencomune_get_term_translation_payload($term);
        }

        return $item;
    }, $terms);

    return rest_ensure_response(['items' => $items]);
}

function opencomune_rest_get_temi_esperienze(WP_REST_Request $request) {
    return opencomune_rest_get_taxonomy_terms_list_response('temi_esperienze');
}

function opencomune_rest_get_inclusioni_esperienze(WP_REST_Request $request) {
    return opencomune_rest_get_taxonomy_terms_list_response('inclusioni_esperienze');
}

function opencomune_rest_get_info_prima_prenotazione(WP_REST_Request $request) {
    return opencomune_rest_get_taxonomy_terms_list_response('info_prima_prenotazione');
}

function opencomune_rest_get_taxonomy_terms_list_response($taxonomy) {
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return $terms;
    }

    $items = array_map(static function ($term) {
        return [
            'id' => (int) $term->term_id,
            'name' => $term->name,
            'slug' => $term->slug,
            'count' => (int) $term->count,
        ];
    }, $terms);

    return rest_ensure_response(['items' => $items]);
}

function opencomune_register_esperienze_rest_routes() {
    register_rest_route('opencomune/v1', '/esperienze', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_esperienze',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'page' => ['default' => 1, 'sanitize_callback' => 'absint'],
            'per_page' => ['default' => 20, 'sanitize_callback' => 'absint'],
            'search' => ['sanitize_callback' => 'sanitize_text_field'],
            'categoria' => ['sanitize_callback' => 'sanitize_text_field'],
            'author_type' => ['sanitize_callback' => 'sanitize_text_field'],
            'date_from' => ['sanitize_callback' => 'sanitize_text_field'],
            'date_to' => ['sanitize_callback' => 'sanitize_text_field'],
            'lang' => ['sanitize_callback' => 'sanitize_text_field'],
            'include_translations' => ['sanitize_callback' => 'rest_sanitize_boolean'],
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('opencomune/v1', '/esperienze/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_esperienza',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'id' => ['validate_callback' => static function ($value) {
                return is_numeric($value) && (int) $value > 0;
            }],
            'lang' => ['sanitize_callback' => 'sanitize_text_field'],
            'include_translations' => ['sanitize_callback' => 'rest_sanitize_boolean'],
            'availability_from' => ['sanitize_callback' => 'sanitize_text_field'],
            'availability_to' => ['sanitize_callback' => 'sanitize_text_field'],
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('opencomune/v1', '/categorie-esperienze', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_categorie_esperienze',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'lang' => ['sanitize_callback' => 'sanitize_text_field'],
            'include_translations' => ['sanitize_callback' => 'rest_sanitize_boolean'],
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('opencomune/v1', '/temi-esperienze', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_temi_esperienze',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('opencomune/v1', '/inclusioni-esperienze', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_inclusioni_esperienze',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);

    register_rest_route('opencomune/v1', '/info-prima-prenotazione', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'opencomune_rest_get_info_prima_prenotazione',
        'permission_callback' => 'opencomune_rest_api_permission_check',
        'args' => [
            'api_key' => ['sanitize_callback' => 'sanitize_text_field'],
        ],
    ]);
}
add_action('rest_api_init', 'opencomune_register_esperienze_rest_routes');

add_filter('rest_pre_serve_request', static function ($served, $result, $request, $server) {
    if (strpos($request->get_route(), '/opencomune/v1/') === 0) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, OPTIONS');
        header('Access-Control-Allow-Headers: X-OpenComune-API-Key, Content-Type');
    }

    return $served;
}, 10, 4);

function opencomune_get_openapi_spec() {
    $site_name = get_bloginfo('name');
    $base_url = rest_url('opencomune/v1');

    return [
        'openapi' => '3.0.3',
        'info' => [
            'title' => 'API Marketing Territoriale',
            'description' => 'API pubblica per integrare le esperienze del territorio su portali e canali esterni.

**Localizzazione (v1.2+)**
- `lang=it` (default) o `lang=en` seleziona la lingua dei testi restituiti.
- `include_translations=1` aggiunge l\'oggetto `translations` con entrambe le lingue.
- Se una traduzione EN non esiste, i campi testuali fanno fallback sull\'italiano.
- `translation_available` indica per ogni lingua se esiste contenuto tradotto.
- I campi `location.address` e `location.meeting_point` restano sempre in italiano.

**Prenotazione e checkout (v1.3.0)**
- Ogni esperienza espone `booking` con canale di vendita (`direct_booking_enabled`, `payment_mode`, pagamento online) e `checkout_url_pattern`.
- Flusso tipo carrello/checkout: `POST /checkout/sessions` → `PATCH` contatto/viaggiatori → `POST .../confirm` → pagamento (se richiesto).
- Gli endpoint `/checkout/*` sono pubblici (non richiedono API Key) e accettano `POST`/`PATCH`.',
            'version' => '1.3.0',
            'contact' => [
                'name' => get_option('opencomune_nome_comune', $site_name),
                'email' => get_option('opencomune_email_ufficio', ''),
            ],
        ],
        'servers' => [
            ['url' => $base_url, 'description' => 'API base'],
        ],
        'tags' => [
            ['name' => 'Esperienze', 'description' => 'Proposte ed esperienze del territorio'],
            ['name' => 'Categorie', 'description' => 'Tassonomia categorie esperienze'],
            ['name' => 'Localizzazione', 'description' => 'Parametri lang e include_translations per contenuti IT/EN'],
            ['name' => 'Checkout', 'description' => 'Sessioni di prenotazione e checkout'],
        ],
        'components' => [
            'securitySchemes' => [
                'ApiKeyHeader' => [
                    'type' => 'apiKey',
                    'in' => 'header',
                    'name' => 'X-OpenComune-API-Key',
                    'description' => 'Richiesta solo se configurata una API Key nelle impostazioni tema.',
                ],
                'ApiKeyQuery' => [
                    'type' => 'apiKey',
                    'in' => 'query',
                    'name' => 'api_key',
                    'description' => 'Alternativa all\'header X-OpenComune-API-Key.',
                ],
            ],
            'schemas' => [
                'Categoria' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'example' => 12],
                        'name' => ['type' => 'string', 'example' => 'Arte e Musei', 'description' => 'Nome localizzato in base al parametro lang'],
                        'slug' => ['type' => 'string', 'example' => 'arte-e-musei'],
                        'count' => ['type' => 'integer', 'example' => 5],
                        'locale' => ['type' => 'string', 'enum' => ['it', 'en'], 'example' => 'it', 'description' => 'Lingua applicata al campo name'],
                        'translations' => ['$ref' => '#/components/schemas/TranslationMap', 'description' => 'Presente solo con include_translations=1'],
                    ],
                ],
                'TranslationMap' => [
                    'type' => 'object',
                    'properties' => [
                        'it' => ['type' => 'string'],
                        'en' => ['type' => 'string'],
                    ],
                ],
                'TranslationAvailability' => [
                    'type' => 'object',
                    'properties' => [
                        'it' => ['type' => 'boolean', 'example' => true],
                        'en' => ['type' => 'boolean', 'example' => true],
                    ],
                ],
                'EsperienzaTranslations' => [
                    'type' => 'object',
                    'properties' => [
                        'it' => ['$ref' => '#/components/schemas/EsperienzaTranslationBundle'],
                        'en' => ['$ref' => '#/components/schemas/EsperienzaTranslationBundle'],
                    ],
                ],
                'EsperienzaTranslationBundle' => [
                    'type' => 'object',
                    'description' => 'Bundle testuale di un\'esperienza in una singola lingua',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'descrizione_breve' => ['type' => 'string'],
                        'content' => ['type' => 'string', 'description' => 'Descrizione completa (HTML consentito)'],
                        'highlights' => ['type' => 'string'],
                        'itinerary' => ['type' => 'string'],
                        'included' => ['type' => 'string'],
                        'not_included' => ['type' => 'string'],
                        'requirements' => ['type' => 'string'],
                        'cancellation_policy' => ['type' => 'string'],
                    ],
                ],
                'EsperienzaLocation' => [
                    'type' => 'object',
                    'description' => 'Dati geografici sempre restituiti in italiano, indipendentemente da lang',
                    'properties' => [
                        'address' => ['type' => 'string', 'example' => 'Via Roma 1, Matera'],
                        'meeting_point' => ['type' => 'string', 'example' => 'Piazza del Duomo'],
                        'city' => ['type' => 'string'],
                        'latitude' => ['type' => 'number', 'format' => 'float'],
                        'longitude' => ['type' => 'number', 'format' => 'float'],
                        'external_link' => ['type' => 'string', 'format' => 'uri'],
                    ],
                ],
                'EsperienzaScheduling' => [
                    'type' => 'object',
                    'properties' => [
                        'date_mode' => ['type' => 'string', 'enum' => ['single', 'range'], 'description' => 'Periodo di validita esperienza (singola data o intervallo)'],
                        'booking_schedule' => [
                            'type' => 'string',
                            'enum' => ['datetime', 'date_only', 'pass'],
                            'description' => 'Modalita prenotazione: data+ora, solo data, pass senza data',
                        ],
                        'start_date' => ['type' => 'string', 'format' => 'date'],
                        'end_date' => ['type' => 'string', 'format' => 'date'],
                        'dates_label' => ['type' => 'string', 'description' => 'Etichetta date localizzata (IT/EN)'],
                        'meeting_date' => ['type' => 'string', 'format' => 'date'],
                        'meeting_time' => ['type' => 'string'],
                        'duration' => ['type' => 'string', 'description' => 'Etichetta leggibile della durata (compatibilita)'],
                        'duration_parts' => [
                            'type' => 'object',
                            'properties' => [
                                'days' => ['type' => 'integer'],
                                'hours' => ['type' => 'integer'],
                                'minutes' => ['type' => 'integer'],
                                'total_minutes' => ['type' => 'integer'],
                                'label' => ['type' => 'string'],
                            ],
                        ],
                        'start_times' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StartTime']],
                    ],
                ],
                'TassonomiaTerm' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                    ],
                ],
                'LanguageOptions' => [
                    'type' => 'object',
                    'properties' => [
                        'live_guide' => ['type' => 'boolean'],
                        'audio_guide' => ['type' => 'boolean'],
                        'reading_material' => ['type' => 'boolean'],
                    ],
                ],
                'AgeLimit' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'minimum_age' => ['type' => 'integer'],
                    ],
                ],
                'BeforeStart' => [
                    'type' => 'object',
                    'properties' => [
                        'know_before_booking' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TassonomiaTerm']],
                        'other_notes' => ['type' => 'string', 'description' => 'Altre info (localizzato con lang, alias di details.requirements)'],
                        'physical_difficulty' => ['type' => 'string', 'description' => 'facile, medio, difficile'],
                        'age_limit' => ['$ref' => '#/components/schemas/AgeLimit'],
                    ],
                ],
                'OperatingHoursDaySlot' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'open' => ['type' => 'string', 'example' => '09:00'],
                        'close' => ['type' => 'string', 'example' => '18:00'],
                    ],
                ],
                'OperatingHoursSeasonal' => [
                    'type' => 'object',
                    'properties' => [
                        'label' => ['type' => 'string'],
                        'start_date' => ['type' => 'string', 'format' => 'date'],
                        'end_date' => ['type' => 'string', 'format' => 'date'],
                        'days' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/OperatingHoursDaySlot']],
                    ],
                ],
                'OperatingHours' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'default' => ['type' => 'object', 'additionalProperties' => ['$ref' => '#/components/schemas/OperatingHoursDaySlot']],
                        'seasonal' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/OperatingHoursSeasonal']],
                    ],
                ],
                'Capacity' => [
                    'type' => 'object',
                    'properties' => [
                        'mode' => ['type' => 'string', 'enum' => ['unlimited', 'limited', 'on_request']],
                        'max_participants' => ['type' => 'integer', 'nullable' => true, 'description' => 'Valorizzato solo se mode=limited'],
                    ],
                ],
                'StartTime' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'time' => ['type' => 'string', 'example' => '14:00'],
                        'duration_hours' => ['type' => 'integer'],
                        'duration_minutes' => ['type' => 'integer'],
                        'duration_label' => ['type' => 'string'],
                        'internal_label' => ['type' => 'string'],
                        'external_label' => ['type' => 'string'],
                        'linked_rates' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'linked_resource_types' => ['type' => 'array', 'items' => ['type' => 'object']],
                    ],
                ],
                'AvailabilityRule' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'rule_type' => ['type' => 'string', 'enum' => ['weekly', 'yearly_months', 'date_range', 'single_date']],
                        'effective_from' => ['type' => 'string', 'format' => 'date'],
                        'effective_until' => ['type' => 'string', 'format' => 'date'],
                        'weekdays' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'months' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'date_from' => ['type' => 'string', 'format' => 'date'],
                        'date_to' => ['type' => 'string', 'format' => 'date'],
                        'single_date' => ['type' => 'string', 'format' => 'date'],
                        'min_participants' => ['type' => 'integer'],
                        'max_participants' => ['type' => 'integer'],
                        'all_start_times' => ['type' => 'boolean'],
                        'start_time_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'internal_label' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                    ],
                ],
                'AvailabilityOccurrence' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'rule_id' => ['type' => 'string'],
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'time' => ['type' => 'string'],
                        'min_participants' => ['type' => 'integer'],
                        'max_participants' => ['type' => 'integer'],
                        'booked_participants' => ['type' => 'integer'],
                        'available_spots' => ['type' => 'integer', 'nullable' => true],
                    ],
                ],
                'AvailabilityPayload' => [
                    'type' => 'object',
                    'properties' => [
                        'has_future' => ['type' => 'boolean'],
                        'rules_count' => ['type' => 'integer'],
                        'from' => ['type' => 'string', 'format' => 'date'],
                        'to' => ['type' => 'string', 'format' => 'date'],
                        'rules' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AvailabilityRule']],
                        'occurrences' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/AvailabilityOccurrence']],
                    ],
                ],
                'ExperienceSettings' => [
                    'type' => 'object',
                    'properties' => [
                        'experience_type' => [
                            'type' => 'string',
                            'enum' => ['tour_activity', 'attraction', 'event', 'transportation'],
                        ],
                        'experience_type_label' => ['type' => 'string'],
                        'private_experience' => ['type' => 'boolean'],
                        'customized_bookings' => ['type' => 'boolean'],
                    ],
                ],
                'BookingChannel' => [
                    'type' => 'object',
                    'description' => 'Canale di vendita / politica pagamento configurata sul prodotto',
                    'properties' => [
                        'direct_booking_enabled' => ['type' => 'boolean', 'description' => 'Prenotazione diretta sulla piattaforma'],
                        'external_booking_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'Link esterno se la prenotazione diretta e disattivata'],
                        'payment_mode' => [
                            'type' => 'string',
                            'enum' => ['on_site', 'online_optional', 'online_required'],
                            'description' => 'on_site = in loco; online_optional/required = checkout con gateway',
                        ],
                        'online_payment_available' => ['type' => 'boolean'],
                        'online_payment_required' => ['type' => 'boolean'],
                        'is_free' => ['type' => 'boolean', 'description' => 'true se il prezzo base e 0 (nessun pagamento online)'],
                    ],
                ],
                'ExperiencePricing' => [
                    'type' => 'object',
                    'properties' => [
                        'amount' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'description' => 'Prezzo minimo tra le categorie'],
                        'amount_max' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'description' => 'Prezzo massimo tra le categorie'],
                        'currency' => ['type' => 'string', 'example' => 'EUR'],
                        'is_free' => ['type' => 'boolean'],
                        'label' => ['type' => 'string', 'example' => '€ 54,00 o da € 10,00 a € 22,00'],
                    ],
                ],
                'Booking' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/BookingChannel'],
                        [
                            'type' => 'object',
                            'properties' => [
                                'show_form' => ['type' => 'boolean', 'description' => 'Alias legacy di direct_booking_enabled'],
                                'external_url' => ['type' => 'string', 'format' => 'uri', 'description' => 'Alias legacy di external_booking_url'],
                                'checkout_url_pattern' => [
                                    'type' => 'string',
                                    'example' => 'https://example.com/checkout-esperienza/?session={token}',
                                    'description' => 'URL frontend; sostituire {token} con session_token',
                                ],
                            ],
                        ],
                    ],
                ],
                'PricingCategory' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'public_id' => ['type' => 'integer'],
                        'label' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'min_age' => ['type' => 'integer', 'nullable' => true],
                        'max_age' => ['type' => 'integer', 'nullable' => true],
                        'is_default' => ['type' => 'boolean'],
                        'sort_order' => ['type' => 'integer'],
                        'price' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'description' => 'Prezzo unitario EUR per questa categoria'],
                    ],
                ],
                'CheckoutParticipantInput' => [
                    'type' => 'object',
                    'properties' => [
                        'category_id' => ['type' => 'string', 'description' => 'ID categoria prezzo (pricing_categories.id)'],
                        'quantity' => ['type' => 'integer', 'minimum' => 1],
                    ],
                ],
                'CheckoutSessionCreateRequest' => [
                    'type' => 'object',
                    'required' => ['experience_id', 'event_id', 'date', 'time'],
                    'properties' => [
                        'experience_id' => ['type' => 'integer'],
                        'event_id' => ['type' => 'integer', 'description' => 'ID slot calendario legacy (tabella tour_calendari)'],
                        'date' => ['type' => 'string', 'format' => 'date', 'example' => '2026-11-15'],
                        'time' => ['type' => 'string', 'example' => '10:00'],
                        'participants' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/CheckoutParticipantInput'],
                        ],
                    ],
                ],
                'CheckoutSessionCreateResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'session_token' => ['type' => 'string'],
                        'checkout_url' => ['type' => 'string', 'format' => 'uri'],
                        'payload' => ['$ref' => '#/components/schemas/CheckoutSessionPayload'],
                    ],
                ],
                'CheckoutLineTotal' => [
                    'type' => 'object',
                    'properties' => [
                        'category_id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'quantity' => ['type' => 'integer'],
                        'unit_price' => ['type' => 'number', 'format' => 'float'],
                        'line_total' => ['type' => 'number', 'format' => 'float'],
                    ],
                ],
                'CheckoutTotals' => [
                    'type' => 'object',
                    'properties' => [
                        'currency' => ['type' => 'string', 'example' => 'EUR'],
                        'lines' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CheckoutLineTotal']],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'participants' => ['type' => 'integer'],
                    ],
                ],
                'CheckoutSessionPayload' => [
                    'type' => 'object',
                    'properties' => [
                        'step' => ['type' => 'string', 'enum' => ['contact', 'travelers', 'review', 'payment']],
                        'experience_id' => ['type' => 'integer'],
                        'event_id' => ['type' => 'integer'],
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'time' => ['type' => 'string'],
                        'participants' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/CheckoutParticipantInput']],
                        'totals' => ['$ref' => '#/components/schemas/CheckoutTotals'],
                        'contact' => ['type' => 'object'],
                        'travelers' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'payment_mode' => ['type' => 'string'],
                        'consents' => ['type' => 'object'],
                        'booking_id' => ['type' => 'integer', 'nullable' => true],
                    ],
                ],
                'CheckoutSession' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'session_token' => ['type' => 'string'],
                        'experience_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['open', 'awaiting_payment', 'completed']],
                        'booking_id' => ['type' => 'integer', 'nullable' => true],
                        'expires_at' => ['type' => 'string', 'format' => 'date-time'],
                        'payload' => ['$ref' => '#/components/schemas/CheckoutSessionPayload'],
                        'experience' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'title' => ['type' => 'string'],
                                'permalink' => ['type' => 'string', 'format' => 'uri'],
                                'booking_channel' => ['$ref' => '#/components/schemas/BookingChannel'],
                                'pricing_categories' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PricingCategory']],
                                'pricing' => ['$ref' => '#/components/schemas/ExperiencePricing'],
                            ],
                        ],
                    ],
                ],
                'CheckoutSessionPatchRequest' => [
                    'type' => 'object',
                    'properties' => [
                        'step' => ['type' => 'string', 'enum' => ['contact', 'travelers', 'review', 'payment']],
                        'contact' => [
                            'type' => 'object',
                            'properties' => [
                                'first_name' => ['type' => 'string'],
                                'last_name' => ['type' => 'string'],
                                'email' => ['type' => 'string', 'format' => 'email'],
                                'phone' => ['type' => 'string'],
                                'notes' => ['type' => 'string'],
                            ],
                        ],
                        'travelers' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'consents' => ['type' => 'object'],
                    ],
                ],
                'CheckoutConfirmResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'booking_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'example' => 'confermata', 'description' => 'confermata, pending_payment, pending_approval o cancellata'],
                        'requires_payment' => ['type' => 'boolean'],
                        'total' => ['type' => 'number', 'format' => 'float'],
                        'payment' => [
                            'type' => 'object',
                            'nullable' => true,
                            'properties' => [
                                'gateway' => ['type' => 'string', 'enum' => ['paypal', 'nexi', 'sumup']],
                                'source' => ['type' => 'string', 'enum' => ['site', 'partner']],
                            ],
                        ],
                    ],
                ],
                'TicketSettings' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_per_participant' => ['type' => 'boolean'],
                        'ticket_message' => ['type' => 'string', 'description' => 'HTML consentito'],
                        'override_barcode_format' => ['type' => 'boolean'],
                    ],
                ],
                'EsperienzaTaxonomyTerm' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                    ],
                ],
                'ExperienceIdentification' => [
                    'type' => 'object',
                    'properties' => [
                        'product_code' => ['type' => 'string'],
                        'flags' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/EsperienzaTaxonomyTerm'],
                        ],
                        'keywords' => [
                            'type' => 'array',
                            'description' => 'Alias dei temi esperienza (temi_esperienze) per compatibilita API',
                            'items' => ['$ref' => '#/components/schemas/EsperienzaTaxonomyTerm'],
                        ],
                        'product_group_labels' => [
                            'type' => 'array',
                            'items' => ['$ref' => '#/components/schemas/EsperienzaTaxonomyTerm'],
                        ],
                    ],
                ],
                'EsperienzaDetails' => [
                    'type' => 'object',
                    'description' => 'Campi highlights, itinerary, included, not_included, requirements e cancellation_policy sono localizzati con lang',
                    'properties' => [
                        'difficulty' => ['type' => 'string'],
                        'languages' => ['type' => 'string', 'description' => 'Lingue parlate durante l\'esperienza (meta, non localizzato)'],
                        'max_participants' => ['type' => 'string'],
                        'min_participants' => ['type' => 'string'],
                        'price' => ['type' => 'string', 'description' => 'Importo numerico come stringa (es. "54" o "0")'],
                        'price_amount' => ['type' => 'number', 'format' => 'float'],
                        'is_free' => ['type' => 'boolean'],
                        'price_label' => ['type' => 'string', 'example' => '€ 54,00 o Gratuito'],
                        'currency' => ['type' => 'string', 'example' => 'EUR'],
                        'price_old' => ['type' => 'string'],
                        'tipo_esperienza' => ['type' => 'string'],
                        'scadenza_prenotazioni' => ['type' => 'string'],
                        'highlights' => ['type' => 'string'],
                        'itinerary' => ['type' => 'string'],
                        'included' => ['type' => 'string'],
                        'not_included' => ['type' => 'string'],
                        'requirements' => ['type' => 'string'],
                        'cancellation_policy' => ['type' => 'string'],
                        'accessibility' => ['type' => 'string'],
                        'badge' => ['type' => 'string'],
                        'what_to_bring' => ['type' => 'string', 'description' => 'Cosa portare (HTML)'],
                    ],
                ],
                'MediaAsset' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'url' => ['type' => 'string', 'format' => 'uri'],
                        'thumbnail' => ['type' => 'string', 'format' => 'uri'],
                        'alt' => ['type' => 'string'],
                    ],
                ],
                'Allegato' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'filename' => ['type' => 'string'],
                        'url' => ['type' => 'string', 'format' => 'uri'],
                    ],
                ],
                'Author' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'url' => ['type' => 'string', 'format' => 'uri'],
                    ],
                ],
                'Partner' => [
                    'type' => 'object',
                    'nullable' => true,
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'nome_attivita' => ['type' => 'string'],
                        'tipologia_attivita' => ['type' => 'string'],
                        'comune' => ['type' => 'string'],
                        'logo_url' => ['type' => 'string', 'format' => 'uri'],
                        'sito_web' => ['type' => 'string', 'format' => 'uri'],
                    ],
                ],
                'EsperienzaListItem' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'locale' => ['type' => 'string', 'enum' => ['it', 'en'], 'example' => 'it', 'description' => 'Lingua effettivamente applicata ai campi testuali'],
                        'title' => ['type' => 'string', 'description' => 'Titolo localizzato'],
                        'slug' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'example' => 'publish'],
                        'permalink' => ['type' => 'string', 'format' => 'uri'],
                        'created_at' => ['type' => 'string', 'format' => 'date-time'],
                        'updated_at' => ['type' => 'string', 'format' => 'date-time'],
                        'descrizione_breve' => ['type' => 'string', 'description' => 'Descrizione breve localizzata'],
                        'excerpt' => ['type' => 'string', 'description' => 'Alias di descrizione_breve'],
                        'translation_available' => ['$ref' => '#/components/schemas/TranslationAvailability'],
                        'translations' => ['$ref' => '#/components/schemas/EsperienzaTranslations', 'description' => 'Presente solo con include_translations=1'],
                        'proposal_type' => ['type' => 'string', 'enum' => ['partner_territoriale', 'ente_territoriale', 'altro', ''], 'description' => 'Attualmente le esperienze sono partner_territoriale'],
                        'pricing_categories' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/PricingCategory']],
                        'pricing' => ['$ref' => '#/components/schemas/ExperiencePricing'],
                        'author' => ['$ref' => '#/components/schemas/Author'],
                        'partner' => ['$ref' => '#/components/schemas/Partner'],
                        'categorie' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Categoria']],
                        'temi' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TassonomiaTerm']],
                        'inclusioni' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TassonomiaTerm']],
                        'esclusioni' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/TassonomiaTerm'], 'description' => 'Voci da inclusioni_esperienze marcate come non incluse'],
                        'language_options' => ['$ref' => '#/components/schemas/LanguageOptions'],
                        'before_start' => ['$ref' => '#/components/schemas/BeforeStart'],
                        'what_to_bring' => ['type' => 'string', 'description' => 'Alias di details.what_to_bring'],
                        'booking_schedule' => [
                            'type' => 'string',
                            'enum' => ['datetime', 'date_only', 'pass'],
                            'description' => 'Alias di scheduling.booking_schedule',
                        ],
                        'operating_hours' => ['$ref' => '#/components/schemas/OperatingHours'],
                        'capacity' => ['$ref' => '#/components/schemas/Capacity'],
                        'start_times' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/StartTime']],
                        'availability' => ['$ref' => '#/components/schemas/AvailabilityPayload'],
                        'experience_settings' => ['$ref' => '#/components/schemas/ExperienceSettings'],
                        'ticket_settings' => ['$ref' => '#/components/schemas/TicketSettings'],
                        'experience_identification' => ['$ref' => '#/components/schemas/ExperienceIdentification'],
                        'media' => ['type' => 'object'],
                        'location' => ['$ref' => '#/components/schemas/EsperienzaLocation'],
                        'scheduling' => ['$ref' => '#/components/schemas/EsperienzaScheduling'],
                        'details' => ['$ref' => '#/components/schemas/EsperienzaDetails'],
                        'booking' => ['$ref' => '#/components/schemas/Booking'],
                        'reviews' => ['type' => 'object'],
                        'stats' => ['type' => 'object'],
                    ],
                ],
                'EsperienzaDetail' => [
                    'allOf' => [
                        ['$ref' => '#/components/schemas/EsperienzaListItem'],
                        [
                            'type' => 'object',
                            'properties' => [
                                'content' => ['type' => 'string', 'description' => 'Descrizione completa HTML'],
                                'content_raw' => ['type' => 'string', 'description' => 'Descrizione completa raw'],
                            ],
                        ],
                    ],
                ],
                'Pagination' => [
                    'type' => 'object',
                    'properties' => [
                        'page' => ['type' => 'integer'],
                        'per_page' => ['type' => 'integer'],
                        'total' => ['type' => 'integer'],
                        'total_pages' => ['type' => 'integer'],
                    ],
                ],
                'EsperienzeListResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/EsperienzaListItem']],
                        'pagination' => ['$ref' => '#/components/schemas/Pagination'],
                    ],
                ],
                'CategorieListResponse' => [
                    'type' => 'object',
                    'properties' => [
                        'items' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/Categoria']],
                    ],
                ],
                'Error' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'data' => ['type' => 'object'],
                    ],
                ],
            ],
        ],
        'paths' => [
            '/esperienze' => [
                'get' => [
                    'tags' => ['Esperienze', 'Localizzazione'],
                    'summary' => 'Elenco esperienze pubblicate',
                    'description' => 'Restituisce le esperienze con tutti i campi disponibili (senza content HTML nella lista). Usa `lang` per i testi localizzati e `include_translations=1` per ottenere il bundle IT+EN.',
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1, 'minimum' => 1]],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100]],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Ricerca testuale'],
                        ['name' => 'categoria', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Slug categoria'],
                        ['name' => 'author_type', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['ente', 'partner']], 'description' => 'ente = legacy amministratori; partner = autori con ruolo partner'],
                        ['name' => 'date_from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'date_to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'lang', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['it', 'en'], 'default' => 'it'], 'description' => 'Lingua dei testi restituiti. Valori accettati: it, en, en-US, en-GB (normalizzati a en). Fallback su italiano se EN assente.', 'example' => 'en'],
                        ['name' => 'include_translations', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false], 'description' => 'Se true, aggiunge translations.it e translations.en con tutti i campi testuali traducibili', 'example' => true],
                        ['name' => 'api_key', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'security' => [['ApiKeyHeader' => []], ['ApiKeyQuery' => []], []],
                    'responses' => [
                        '200' => [
                            'description' => 'Lista esperienze',
                            'headers' => [
                                'X-WP-Total' => ['schema' => ['type' => 'integer']],
                                'X-WP-TotalPages' => ['schema' => ['type' => 'integer']],
                            ],
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/EsperienzeListResponse'],
                                    'examples' => [
                                        'italiano' => [
                                            'summary' => 'Risposta in italiano (default)',
                                            'value' => [
                                                'items' => [[
                                                    'id' => 123,
                                                    'locale' => 'it',
                                                    'title' => 'Tour storico del centro',
                                                    'translation_available' => ['it' => true, 'en' => true],
                                                ]],
                                                'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 1, 'total_pages' => 1],
                                            ],
                                        ],
                                        'inglese' => [
                                            'summary' => 'Risposta con lang=en',
                                            'value' => [
                                                'items' => [[
                                                    'id' => 123,
                                                    'locale' => 'en',
                                                    'title' => 'Historic city tour',
                                                    'translation_available' => ['it' => true, 'en' => true],
                                                ]],
                                                'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 1, 'total_pages' => 1],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '401' => ['description' => 'API Key mancante o non valida', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            '/esperienze/{id}' => [
                'get' => [
                    'tags' => ['Esperienze', 'Localizzazione'],
                    'summary' => 'Dettaglio esperienza',
                    'description' => 'Dettaglio completo con content HTML. I campi location.address e location.meeting_point restano sempre in italiano.',
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                        ['name' => 'lang', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['it', 'en'], 'default' => 'it'], 'description' => 'Lingua dei testi restituiti (fallback IT)', 'example' => 'en'],
                        ['name' => 'include_translations', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false], 'description' => 'Include oggetto translations con bundle IT ed EN', 'example' => true],
                        ['name' => 'availability_from', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date'], 'description' => 'Se impostato con availability_to, espande le occorrenze nel payload availability'],
                        ['name' => 'availability_to', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date']],
                        ['name' => 'api_key', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'security' => [['ApiKeyHeader' => []], ['ApiKeyQuery' => []], []],
                    'responses' => [
                        '200' => ['description' => 'Dettaglio completo', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/EsperienzaDetail']]]],
                        '404' => ['description' => 'Esperienza non trovata', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '401' => ['description' => 'API Key mancante o non valida', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            '/categorie-esperienze' => [
                'get' => [
                    'tags' => ['Categorie', 'Localizzazione'],
                    'summary' => 'Elenco categorie esperienze',
                    'description' => 'Restituisce le categorie con name localizzato tramite lang. Con include_translations=1 ogni categoria include translations.it e translations.en.',
                    'parameters' => [
                        ['name' => 'lang', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['it', 'en'], 'default' => 'it'], 'description' => 'Lingua del campo name (meta name_en per EN)', 'example' => 'en'],
                        ['name' => 'include_translations', 'in' => 'query', 'schema' => ['type' => 'boolean', 'default' => false], 'description' => 'Include translations.it/en per ogni categoria', 'example' => true],
                        ['name' => 'api_key', 'in' => 'query', 'schema' => ['type' => 'string']],
                    ],
                    'security' => [['ApiKeyHeader' => []], ['ApiKeyQuery' => []], []],
                    'responses' => [
                        '200' => ['description' => 'Lista categorie', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CategorieListResponse']]]],
                        '401' => ['description' => 'API Key mancante o non valida', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            '/checkout/sessions' => [
                'post' => [
                    'tags' => ['Checkout'],
                    'summary' => 'Crea sessione checkout',
                    'description' => 'Avvia una sessione di prenotazione (equivalente shopping cart). Non richiede API Key. Scadenza tipica: 1 ora.',
                    'security' => [],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CheckoutSessionCreateRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Sessione creata',
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CheckoutSessionCreateResponse']]],
                        ],
                        '400' => ['description' => 'Dati non validi o prenotazione disabilitata', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            '/checkout/sessions/{token}' => [
                'get' => [
                    'tags' => ['Checkout'],
                    'summary' => 'Leggi sessione checkout',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'token', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Sessione attiva', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CheckoutSession']]]],
                        '404' => ['description' => 'Sessione scaduta o inesistente', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'patch' => [
                    'tags' => ['Checkout'],
                    'summary' => 'Aggiorna sessione checkout',
                    'description' => 'Salva contatto principale, viaggiatori e step del wizard.',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'token', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/CheckoutSessionPatchRequest'],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Sessione aggiornata', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CheckoutSession']]]],
                        '400' => ['description' => 'Sessione chiusa o non valida', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            '/checkout/sessions/{token}/confirm' => [
                'post' => [
                    'tags' => ['Checkout'],
                    'summary' => 'Conferma prenotazione',
                    'description' => 'Crea il record in tour_prenotazioni. Se payment_mode=online_required, status pending_payment e requires_payment=true.',
                    'security' => [],
                    'parameters' => [
                        ['name' => 'token', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Prenotazione registrata', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/CheckoutConfirmResponse']]]],
                        '400' => ['description' => 'Contatto mancante o slot non disponibile', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
        ],
    ];
}

function opencomune_register_swagger_rewrite_rules() {
    add_rewrite_rule('^api/swagger/openapi\.json/?$', 'index.php?opencomune_swagger_spec=1', 'top');
    add_rewrite_rule('^api/swagger/?$', 'index.php?opencomune_swagger_ui=1', 'top');
}
add_action('init', 'opencomune_register_swagger_rewrite_rules');

function opencomune_swagger_query_vars($vars) {
    $vars[] = 'opencomune_swagger_ui';
    $vars[] = 'opencomune_swagger_spec';
    return $vars;
}
add_filter('query_vars', 'opencomune_swagger_query_vars');

function opencomune_maybe_flush_swagger_rewrite_rules() {
    if (get_option('opencomune_swagger_rewrite_version') !== '1') {
        flush_rewrite_rules(false);
        update_option('opencomune_swagger_rewrite_version', '1');
    }
}
add_action('init', 'opencomune_maybe_flush_swagger_rewrite_rules', 99);

function opencomune_serve_swagger_documentation() {
    $spec_url = home_url('/api/swagger/openapi.json');

    if (get_query_var('opencomune_swagger_spec')) {
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo wp_json_encode(opencomune_get_openapi_spec(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    if (!get_query_var('opencomune_swagger_ui')) {
        return;
    }

    nocache_headers();
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html(get_bloginfo('name')); ?> - API Swagger (v1.3.0)</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
    <style>
        html { box-sizing: border-box; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin: 0; background: #fafafa; }
    </style>
</head>
<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
<script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
<script>
window.onload = function() {
    SwaggerUIBundle({
        url: <?php echo wp_json_encode($spec_url); ?>,
        dom_id: '#swagger-ui',
        deepLinking: true,
        presets: [SwaggerUIBundle.presets.apis, SwaggerUIStandalonePreset],
        layout: 'StandaloneLayout',
        validatorUrl: null
    });
};
</script>
</body>
</html>
    <?php
    exit;
}
add_action('template_redirect', 'opencomune_serve_swagger_documentation', 0);

function opencomune_register_api_documentation_admin_page() {
    add_submenu_page(
        'opencomune-settings',
        'Documentazione API',
        'Documentazione',
        'manage_options',
        'opencomune-api-documentation',
        'opencomune_render_api_documentation_admin_page'
    );
}
add_action('admin_menu', 'opencomune_register_api_documentation_admin_page');

function opencomune_api_documentation_admin_menu_swagger_script() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $swagger_url = home_url('/api/swagger');
    ?>
    <script>
    (function () {
        var swaggerUrl = <?php echo wp_json_encode($swagger_url); ?>;
        document.querySelectorAll('#adminmenu a[href*="page=opencomune-api-documentation"]').forEach(function (link) {
            link.addEventListener('click', function (event) {
                event.preventDefault();
                window.open(swaggerUrl, '_blank', 'noopener,noreferrer');
            });
        });
    })();
    </script>
    <?php
}
add_action('admin_footer', 'opencomune_api_documentation_admin_menu_swagger_script');

function opencomune_render_api_documentation_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $swagger_url = home_url('/api/swagger');
    $settings_url = admin_url('admin.php?page=opencomune-settings&tab=api');
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Documentazione API', 'opencomune'); ?></h1>
        <p><?php esc_html_e('Swagger UI si apre in una nuova scheda del browser.', 'opencomune'); ?></p>
        <p>
            <a class="button button-primary" href="<?php echo esc_url($swagger_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Apri Swagger UI', 'opencomune'); ?>
            </a>
            <a class="button" href="<?php echo esc_url($settings_url); ?>"><?php esc_html_e('Torna alle impostazioni API', 'opencomune'); ?></a>
        </p>
    </div>
    <script>
        window.open(<?php echo wp_json_encode($swagger_url); ?>, '_blank', 'noopener,noreferrer');
    </script>
    <?php
}
