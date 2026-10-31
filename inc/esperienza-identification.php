<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_esperienza_identification_taxonomies() {
    return [
        'flags' => 'flag_esperienze',
        'keywords' => 'parole_chiave_esperienze',
        'product_group_labels' => 'etichette_gruppo_prodotto',
    ];
}

function opencomune_register_esperienza_identification_taxonomies() {
    $common = [
        'hierarchical' => false,
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_admin_column' => false,
        'show_in_nav_menus' => false,
        'show_tagcloud' => false,
        'show_in_rest' => true,
        'capabilities' => [
            'manage_terms' => 'manage_categories',
            'edit_terms' => 'manage_categories',
            'delete_terms' => 'manage_categories',
            'assign_terms' => 'edit_esperienze',
        ],
    ];

    register_taxonomy(
        'flag_esperienze',
        'esperienze',
        array_merge($common, [
            'label' => __('Flag esperienze', 'opencomune'),
            'labels' => [
                'name' => __('Flag esperienze', 'opencomune'),
                'singular_name' => __('Flag', 'opencomune'),
                'menu_name' => __('Flag esperienze', 'opencomune'),
                'search_items' => __('Cerca flag', 'opencomune'),
                'all_items' => __('Tutti i flag', 'opencomune'),
                'edit_item' => __('Modifica flag', 'opencomune'),
                'add_new_item' => __('Aggiungi flag', 'opencomune'),
                'new_item_name' => __('Nome nuovo flag', 'opencomune'),
            ],
            'rewrite' => false,
        ])
    );

    register_taxonomy(
        'parole_chiave_esperienze',
        'esperienze',
        array_merge($common, [
            'label' => __('Parole chiave esperienze', 'opencomune'),
            'labels' => [
                'name' => __('Parole chiave esperienze', 'opencomune'),
                'singular_name' => __('Parola chiave', 'opencomune'),
                'menu_name' => __('Parole chiave', 'opencomune'),
                'search_items' => __('Cerca parole chiave', 'opencomune'),
                'all_items' => __('Tutte le parole chiave', 'opencomune'),
                'edit_item' => __('Modifica parola chiave', 'opencomune'),
                'add_new_item' => __('Aggiungi parola chiave', 'opencomune'),
                'new_item_name' => __('Nome nuova parola chiave', 'opencomune'),
            ],
            'rewrite' => false,
        ])
    );

    register_taxonomy(
        'etichette_gruppo_prodotto',
        'esperienze',
        array_merge($common, [
            'label' => __('Etichette gruppo prodotto', 'opencomune'),
            'labels' => [
                'name' => __('Etichette gruppo prodotto', 'opencomune'),
                'singular_name' => __('Etichetta gruppo', 'opencomune'),
                'menu_name' => __('Etichette gruppo', 'opencomune'),
                'search_items' => __('Cerca etichette', 'opencomune'),
                'all_items' => __('Tutte le etichette', 'opencomune'),
                'edit_item' => __('Modifica etichetta', 'opencomune'),
                'add_new_item' => __('Aggiungi etichetta', 'opencomune'),
                'new_item_name' => __('Nome nuova etichetta', 'opencomune'),
            ],
            'rewrite' => false,
        ])
    );
}
add_action('init', 'opencomune_register_esperienza_identification_taxonomies', 9);

function opencomune_get_identification_term_partner_meta_key() {
    return 'opencomune_partner_id';
}

function opencomune_register_identification_term_partner_meta() {
    $taxonomies = array_values(opencomune_get_esperienza_identification_taxonomies());
    foreach ($taxonomies as $taxonomy) {
        if (!taxonomy_exists($taxonomy)) {
            continue;
        }
        register_term_meta($taxonomy, opencomune_get_identification_term_partner_meta_key(), [
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => false,
            'auth_callback' => static function () {
                return current_user_can('manage_categories');
            },
        ]);
    }
}
add_action('init', 'opencomune_register_identification_term_partner_meta', 20);

function opencomune_get_identification_term_owner_partner_id($term_id) {
    $term_id = (int) $term_id;
    if ($term_id <= 0) {
        return 0;
    }

    return (int) get_term_meta($term_id, opencomune_get_identification_term_partner_meta_key(), true);
}

function opencomune_get_partner_id_for_identification_user($user_id = 0) {
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    if ($user_id <= 0) {
        return 0;
    }

    $row = opencomune_get_partner_row_for_user($user_id);
    if (!$row || empty($row->id)) {
        return 0;
    }

    return (int) $row->id;
}

function opencomune_user_can_manage_global_identification_terms($user_id = 0) {
    return opencomune_user_can_manage_all_esperienze();
}

function opencomune_user_can_manage_partner_identification_terms($user_id = 0) {
    if (opencomune_user_can_manage_global_identification_terms($user_id)) {
        return true;
    }

    return opencomune_get_partner_id_for_identification_user($user_id) > 0;
}

function opencomune_user_can_assign_identification_term($term, $user_id = 0) {
    if (opencomune_user_can_manage_global_identification_terms($user_id)) {
        return true;
    }

    if (!$term instanceof WP_Term) {
        $term = get_term($term);
    }
    if (!$term || is_wp_error($term)) {
        return false;
    }

    $partner_id = opencomune_get_partner_id_for_identification_user($user_id);
    if ($partner_id <= 0) {
        return false;
    }

    $owner = opencomune_get_identification_term_owner_partner_id($term->term_id);
    if ($owner <= 0) {
        return false;
    }

    return $owner === $partner_id;
}

function opencomune_get_identification_terms_for_form($taxonomy, $post_id = 0, $user_id = 0) {
    $taxonomy = sanitize_key($taxonomy);
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $post_id = (int) $post_id;
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();

    if (opencomune_user_can_manage_global_identification_terms($user_id)) {
        $terms = get_terms([
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ]);

        return is_wp_error($terms) ? [] : $terms;
    }

    $partner_id = opencomune_get_partner_id_for_identification_user($user_id);
    if ($partner_id <= 0) {
        return [];
    }

    $partner_terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => opencomune_get_identification_term_partner_meta_key(),
                'value' => $partner_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);
    if (is_wp_error($partner_terms)) {
        $partner_terms = [];
    }

    $by_id = [];
    foreach ($partner_terms as $term) {
        $by_id[(int) $term->term_id] = $term;
    }

    if ($post_id > 0) {
        $assigned = wp_get_post_terms($post_id, $taxonomy);
        if (!is_wp_error($assigned)) {
            foreach ($assigned as $term) {
                if (opencomune_user_can_assign_identification_term($term, $user_id)
                    || opencomune_get_identification_term_owner_partner_id($term->term_id) === 0) {
                    $by_id[(int) $term->term_id] = $term;
                }
            }
        }
    }

    $merged = array_values($by_id);
    usort($merged, static function ($a, $b) {
        return strcasecmp($a->name, $b->name);
    });

    return $merged;
}

function opencomune_ensure_partner_identification_term($taxonomy, $label, $partner_id) {
    $taxonomy = sanitize_key($taxonomy);
    $partner_id = (int) $partner_id;
    $label = sanitize_text_field((string) $label);
    if ($label === '' || $partner_id <= 0 || !taxonomy_exists($taxonomy)) {
        return null;
    }

    $slug = sanitize_title($label);
    if ($slug === '') {
        return null;
    }

    $existing = get_term_by('slug', $slug, $taxonomy);
    if ($existing && !is_wp_error($existing)) {
        if (opencomune_get_identification_term_owner_partner_id($existing->term_id) === $partner_id) {
            return $existing;
        }

        return null;
    }

    $created = wp_insert_term($label, $taxonomy, ['slug' => $slug]);
    if (is_wp_error($created)) {
        if ($created->get_error_code() === 'term_exists') {
            $term_id = (int) $created->get_error_data();
            $term = get_term($term_id, $taxonomy);
            if ($term && !is_wp_error($term)
                && opencomune_get_identification_term_owner_partner_id($term->term_id) === $partner_id) {
                return $term;
            }

            return null;
        }

        return null;
    }

    $term_id = (int) ($created['term_id'] ?? 0);
    if ($term_id <= 0) {
        return null;
    }

    update_term_meta($term_id, opencomune_get_identification_term_partner_meta_key(), $partner_id);

    return get_term($term_id, $taxonomy);
}

function opencomune_ensure_global_identification_term($taxonomy, $label) {
    $taxonomy = sanitize_key($taxonomy);
    $label = sanitize_text_field((string) $label);
    if ($label === '' || !taxonomy_exists($taxonomy)) {
        return null;
    }

    $slug = sanitize_title($label);
    $existing = $slug !== '' ? get_term_by('slug', $slug, $taxonomy) : false;
    if ($existing && !is_wp_error($existing)) {
        return $existing;
    }

    $existing = get_term_by('name', $label, $taxonomy);
    if ($existing && !is_wp_error($existing)) {
        return $existing;
    }

    $created = wp_insert_term($label, $taxonomy, $slug !== '' ? ['slug' => $slug] : []);
    if (is_wp_error($created)) {
        if ($created->get_error_code() === 'term_exists') {
            return get_term((int) $created->get_error_data(), $taxonomy);
        }

        return null;
    }

    return get_term((int) $created['term_id'], $taxonomy);
}

function opencomune_resolve_identification_term_values_for_save($taxonomy, $raw_values, $user_id = 0) {
    $taxonomy = sanitize_key($taxonomy);
    $user_id = $user_id > 0 ? (int) $user_id : get_current_user_id();
    $slugs = [];
    $is_global_manager = opencomune_user_can_manage_global_identification_terms($user_id);
    $partner_id = opencomune_get_partner_id_for_identification_user($user_id);

    foreach ((array) $raw_values as $raw) {
        $raw = sanitize_text_field(wp_unslash((string) $raw));
        if ($raw === '') {
            continue;
        }

        $term = get_term_by('slug', sanitize_title($raw), $taxonomy);
        if (!$term) {
            $term = get_term_by('name', $raw, $taxonomy);
        }

        if (!$term && $is_global_manager) {
            $term = opencomune_ensure_global_identification_term($taxonomy, $raw);
        } elseif (!$term && $partner_id > 0) {
            $term = opencomune_ensure_partner_identification_term($taxonomy, $raw, $partner_id);
        }

        if (!$term || is_wp_error($term)) {
            continue;
        }

        if (!opencomune_user_can_assign_identification_term($term, $user_id)) {
            continue;
        }

        $slugs[] = $term->slug;
    }

    return array_values(array_unique($slugs));
}

/**
 * Opzioni Select2 per valori selezionati non ancora in elenco (es. dopo POST con tag nuovi).
 *
 * @param WP_Term[] $terms
 * @param string[]  $selected_values
 */
function opencomune_render_identification_select_orphan_options(array $terms, array $selected_values) {
    $known = [];
    foreach ($terms as $term) {
        if ($term instanceof WP_Term) {
            $known[$term->slug] = true;
        }
    }

    foreach ($selected_values as $value) {
        $value = sanitize_text_field((string) $value);
        if ($value === '') {
            continue;
        }
        $slug = sanitize_title($value);
        if ($slug !== '' && isset($known[$slug])) {
            continue;
        }
        if ($value !== '' && !isset($known[$value])) {
            echo '<option value="' . esc_attr($value) . '" selected>' . esc_html($value) . '</option>';
            $known[$value] = true;
        }
    }
}

function opencomune_get_esperienza_identification_product_code($post_id) {
    return (string) get_post_meta((int) $post_id, 'tour_internal_product_code', true);
}

function opencomune_get_esperienza_taxonomy_slugs($post_id, $taxonomy) {
    $terms = wp_get_post_terms((int) $post_id, $taxonomy, ['fields' => 'slugs']);
    if (is_wp_error($terms) || empty($terms)) {
        return [];
    }

    return array_values(array_filter(array_map('strval', $terms)));
}

function opencomune_save_esperienza_taxonomy_slugs($post_id, $taxonomy, $selected_slugs = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !taxonomy_exists($taxonomy)) {
        return [];
    }

    $slugs = array_values(array_unique(array_filter(array_map('sanitize_title', (array) $selected_slugs))));
    wp_set_object_terms($post_id, $slugs, $taxonomy);

    return $slugs;
}

function opencomune_get_esperienza_identification($post_id) {
    $post_id = (int) $post_id;
    $map = opencomune_get_esperienza_identification_taxonomies();

    return [
        'product_code' => opencomune_get_esperienza_identification_product_code($post_id),
        'flags' => opencomune_get_esperienza_taxonomy_slugs($post_id, $map['flags']),
        'keywords' => opencomune_get_esperienza_taxonomy_slugs($post_id, $map['keywords']),
        'product_group_labels' => opencomune_get_esperienza_taxonomy_slugs($post_id, $map['product_group_labels']),
    ];
}

function opencomune_save_esperienza_identification($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $product_code = isset($post_data['tour_internal_product_code'])
        ? sanitize_text_field(wp_unslash($post_data['tour_internal_product_code']))
        : '';
    update_post_meta($post_id, 'tour_internal_product_code', $product_code);

    $map = opencomune_get_esperienza_identification_taxonomies();
    $user_id = get_current_user_id();
    opencomune_save_esperienza_taxonomy_slugs(
        $post_id,
        $map['flags'],
        opencomune_resolve_identification_term_values_for_save(
            $map['flags'],
            $post_data['tour_esperienza_flags'] ?? [],
            $user_id
        )
    );
    opencomune_save_esperienza_taxonomy_slugs(
        $post_id,
        $map['product_group_labels'],
        opencomune_resolve_identification_term_values_for_save(
            $map['product_group_labels'],
            $post_data['tour_esperienza_group_labels'] ?? [],
            $user_id
        )
    );

    opencomune_merge_legacy_identification_keywords_into_themes($post_id);

    return opencomune_get_esperienza_identification($post_id);
}

/**
 * Le parole chiave legacy vengono unite ai temi (unica tassonomia pubblica per filtri/SEO).
 */
function opencomune_merge_legacy_identification_keywords_into_themes($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $map = opencomune_get_esperienza_identification_taxonomies();
    $keyword_slugs = opencomune_get_esperienza_taxonomy_slugs($post_id, $map['keywords']);
    if (empty($keyword_slugs)) {
        return;
    }

    $theme_slugs = opencomune_get_esperienza_theme_slugs($post_id);
    $merged = array_values(array_unique(array_merge($theme_slugs, $keyword_slugs)));
    opencomune_save_esperienza_themes($post_id, $merged);
    opencomune_save_esperienza_taxonomy_slugs($post_id, $map['keywords'], []);
}

function opencomune_format_identification_terms_for_api($post_id, $taxonomy) {
    $terms = wp_get_post_terms((int) $post_id, $taxonomy);

    return opencomune_format_esperienza_taxonomy_terms_for_api($terms);
}

function opencomune_format_experience_identification_for_api($post_id) {
    $post_id = (int) $post_id;
    $map = opencomune_get_esperienza_identification_taxonomies();

    return [
        'product_code' => opencomune_get_esperienza_identification_product_code($post_id),
        'flags' => opencomune_format_identification_terms_for_api($post_id, $map['flags']),
        'keywords' => opencomune_format_identification_terms_for_api($post_id, 'temi_esperienze'),
        'product_group_labels' => opencomune_format_identification_terms_for_api($post_id, $map['product_group_labels']),
    ];
}

function opencomune_get_esperienza_identification_taxonomy_admin_url($taxonomy) {
    return admin_url('edit-tags.php?taxonomy=' . rawurlencode($taxonomy) . '&post_type=esperienze');
}

function opencomune_enqueue_esperienza_identification_assets() {
    $theme = wp_get_theme();
    wp_enqueue_style(
        'opencomune-esperienza-identification',
        get_template_directory_uri() . '/assets/css/esperienza-identification.css',
        [],
        $theme->get('Version') ?: '1.0'
    );
}
