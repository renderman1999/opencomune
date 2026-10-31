<?php
/**
 * Record di test "test record esempio" per validare il wizard.
 *
 * Esecuzione: wp eval-file wp-content/themes/opencomune/inc/seed-esperienza-esempio.php
 */

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_seed_get_or_create_term_slug($taxonomy, $name) {
    $term = get_term_by('name', $name, $taxonomy);
    if ($term && !is_wp_error($term)) {
        return $term->slug;
    }

    $created = wp_insert_term($name, $taxonomy);
    if (is_wp_error($created)) {
        return '';
    }

    $term = get_term($created['term_id'], $taxonomy);

    return $term && !is_wp_error($term) ? $term->slug : '';
}

function opencomune_seed_esperienza_esempio_record($author_id = 0) {
    $author_id = (int) $author_id;
    if ($author_id <= 0) {
        $author_id = (int) get_current_user_id();
    }
    if ($author_id <= 0) {
        $author_id = 1;
    }

    $title = 'test record esempio';
    $existing = get_posts([
        'post_type' => 'esperienze',
        'post_status' => 'any',
        'title' => $title,
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);

    $post_id = !empty($existing) ? (int) $existing[0] : 0;

    $postarr = [
        'post_title' => $title,
        'post_excerpt' => 'Descrizione breve di esempio per testare anteprime e API.',
        'post_content' => '<p>Descrizione completa di <strong>test record esempio</strong>: contenuto ricco per verificare salvataggio, API e scheda pubblica.</p><ul><li>Punto uno</li><li>Punto due</li></ul>',
        'post_status' => 'draft',
        'post_type' => 'esperienze',
        'post_author' => $author_id,
    ];

    if ($post_id > 0) {
        $postarr['ID'] = $post_id;
        wp_update_post($postarr);
    } else {
        $post_id = (int) wp_insert_post($postarr);
    }

    if ($post_id <= 0 || is_wp_error($post_id)) {
        return new WP_Error('seed_failed', 'Impossibile creare o aggiornare l\'esperienza di esempio.');
    }

    $thumb_id = (int) get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'DESC',
    ])[0] ?? 0;
    if ($thumb_id > 0) {
        set_post_thumbnail($post_id, $thumb_id);
    }

    update_post_meta($post_id, 'descrizione_breve', $postarr['post_excerpt']);
    update_post_meta($post_id, 'tour_highlights', '<p>Highlight di test</p>');
    update_post_meta($post_id, 'tour_itinerary', '<p>Itinerario di esempio passo per passo.</p>');
    update_post_meta($post_id, 'tour_whats_included', '<p>Include guida e materiale.</p>');
    update_post_meta($post_id, 'tour_whats_not_included', '<p>Non include trasporto.</p>');
    update_post_meta($post_id, 'tour_requirements', '<p>Scarpe comode consigliate.</p>');
    update_post_meta($post_id, 'tour_cancellation_policy', '<p>Prenotazioni non rimborsabili (test).</p>');
    update_post_meta($post_id, 'tour_what_to_bring', '<p>Borraccia e documento.</p>');
    update_post_meta($post_id, 'tour_difficulty', 'medium');
    update_post_meta($post_id, 'tour_languages', 'Italiano, Inglese');
    update_post_meta($post_id, 'tour_destination', 'https://example.com/prenota-test');
    update_post_meta($post_id, 'citta', 'Milano');
    update_post_meta($post_id, 'tour_address', 'Via Example 1, Milano');
    update_post_meta($post_id, 'tour_latitude', '45.464211');
    update_post_meta($post_id, 'tour_longitude', '9.191383');

    $cat_slugs = wp_list_pluck(
        get_terms(['taxonomy' => 'categorie_esperienze', 'hide_empty' => false, 'number' => 2]),
        'slug'
    );
    $theme_slugs = wp_list_pluck(
        get_terms(['taxonomy' => 'temi_esperienze', 'hide_empty' => false, 'number' => 2]),
        'slug'
    );
    if (empty($theme_slugs)) {
        $slug = opencomune_seed_get_or_create_term_slug('temi_esperienze', 'Test tema');
        if ($slug !== '') {
            $theme_slugs[] = $slug;
        }
    }

    $inclusion_slugs = wp_list_pluck(
        get_terms(['taxonomy' => 'inclusioni_esperienze', 'hide_empty' => false, 'number' => 1]),
        'slug'
    );
    $before_slugs = wp_list_pluck(
        get_terms(['taxonomy' => 'info_prima_prenotazione', 'hide_empty' => false, 'number' => 1]),
        'slug'
    );

    $flag_slug = opencomune_seed_get_or_create_term_slug('flag_esperienze', 'Test flag');
    $group_slug = opencomune_seed_get_or_create_term_slug('etichette_gruppo_prodotto', 'Test etichetta gruppo');

    opencomune_save_esperienza_categories($post_id, $cat_slugs);
    opencomune_save_esperienza_themes($post_id, $theme_slugs);
    opencomune_save_esperienza_inclusions($post_id, $inclusion_slugs);
    opencomune_save_esperienza_exclusions($post_id, $inclusion_slugs);
    opencomune_save_esperienza_before_booking_info($post_id, $before_slugs);

    opencomune_save_tour_duration_parts($post_id, [
        'days' => 0,
        'hours' => 2,
        'minutes' => 30,
    ]);

    $start_time_id = 'st_seed_esempio';
    $start_times = [
        [
            'id' => $start_time_id,
            'time' => '10:00',
            'duration_hours' => 2,
            'duration_minutes' => 30,
            'internal_label' => 'Partenza test',
            'external_label' => 'Mattina',
        ],
    ];

    $default_days = opencomune_get_default_operating_hours_day_slots();
    foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday'] as $day) {
        $default_days[$day] = [
            'enabled' => true,
            'open' => '09:00',
            'close' => '18:00',
        ];
    }

    $availability_rules = [
        [
            'id' => 'av_seed_esempio',
            'rule_type' => 'weekly',
            'weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
            'effective_from' => '',
            'effective_until' => '',
            'months' => [],
            'date_from' => '',
            'date_to' => '',
            'single_date' => '',
            'min_participants' => 1,
            'max_participants' => 12,
            'all_start_times' => true,
            'start_time_ids' => [],
            'internal_label' => 'Regola test',
            'color' => '#059669',
        ],
    ];

    $meeting_points = [
        [
            'id' => 'mp_seed_esempio',
            'title' => 'Punto incontro test',
            'address' => 'Piazza del Duomo, Milano, MI, Italia',
            'latitude' => 45.464097,
            'longitude' => 9.191926,
            'sort_order' => 0,
        ],
    ];

    $pricing = opencomune_get_default_pricing_categories();

    $post_data = [
        'tour_capacity_mode' => 'limited',
        'tour_max_participants' => '12',
        'tour_lang_live_guide' => '1',
        'tour_lang_audio_guide' => '1',
        'tour_age_limit_enabled' => '1',
        'tour_age_limit_min' => '6',
        'tour_booking_schedule' => 'datetime',
        'tour_operating_hours_enabled' => '1',
        'tour_operating_hours_payload' => wp_json_encode([
            'default' => $default_days,
            'seasonal' => [],
        ]),
        'tour_start_times_payload' => wp_json_encode($start_times),
        'tour_pricing_categories_payload' => wp_json_encode($pricing),
        'tour_pickup_mode' => 'meet_or_pickup',
        'tour_use_pickup_group' => '1',
        'tour_meeting_points_payload' => wp_json_encode($meeting_points),
        'tour_experience_type' => 'tour_activity',
        'tour_private_experience' => '1',
        'tour_customized_bookings' => '0',
        'tour_ticket_per_participant' => '1',
        'tour_ticket_message' => '<p>Messaggio biglietto di test.</p>',
        'tour_ticket_override_barcode' => '0',
        'tour_internal_product_code' => 'TEST-SKU-001',
        'tour_esperienza_flags' => $flag_slug !== '' ? [$flag_slug] : [],
        'tour_esperienza_group_labels' => $group_slug !== '' ? [$group_slug] : [],
        'tour_availability_rules_payload' => wp_json_encode($availability_rules),
    ];

    opencomune_save_esperienza_capacity($post_id, $post_data);
    opencomune_save_esperienza_language_options($post_id, $post_data);
    opencomune_save_esperienza_age_limit($post_id, $post_data);
    opencomune_save_esperienza_booking_schedule_type($post_id, $post_data['tour_booking_schedule']);
    opencomune_save_esperienza_operating_hours($post_id, $post_data);
    opencomune_save_esperienza_start_times($post_id, $post_data);
    opencomune_save_esperienza_pricing_categories($post_id, $post_data);
    opencomune_save_esperienza_meeting_pickup($post_id, $post_data);
    opencomune_save_esperienza_experience_settings($post_id, $post_data);
    opencomune_save_esperienza_booking_channel_settings($post_id, [
        'mostra_form_prenotazione' => '1',
        'tour_booking_payment_mode' => 'online_optional',
        'tour_external_booking_url' => '',
    ]);
    update_post_meta($post_id, 'tour_price', '54');
    opencomune_save_esperienza_ticket_settings($post_id, $post_data);
    opencomune_save_esperienza_identification($post_id, $post_data);
    opencomune_save_esperienza_availability_rules($post_id, $post_data);
    opencomune_merge_legacy_identification_keywords_into_themes($post_id);
    opencomune_finalize_esperienza_form_meta($post_id);

    return [
        'post_id' => $post_id,
        'edit_url' => home_url('/nuova-esperienza/?id=' . $post_id),
        'api_url' => rest_url('opencomune/v1/esperienze/' . $post_id),
        'permalink' => get_permalink($post_id),
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    $result = opencomune_seed_esperienza_esempio_record(1);
    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    }
    WP_CLI::success('Esperienza di test creata/aggiornata (ID ' . $result['post_id'] . ').');
    WP_CLI::log('Modifica: ' . $result['edit_url']);
    WP_CLI::log('API: ' . $result['api_url']);
}
