<?php
/**
 * Catalogo planner: città, interessi, focus, destinazioni POI.
 */

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_planner_plain_label($text) {
    $text = wp_specialchars_decode((string) $text, ENT_QUOTES);

    return wp_strip_all_tags($text);
}

function opencomune_planner_city_table() {
    global $wpdb;
    return $wpdb->prefix . 'oc_planner_city';
}

function opencomune_planner_interest_table() {
    global $wpdb;
    return $wpdb->prefix . 'oc_planner_interest';
}

function opencomune_planner_focus_table() {
    global $wpdb;
    return $wpdb->prefix . 'oc_planner_focus';
}

function opencomune_planner_poi_table() {
    global $wpdb;
    return $wpdb->prefix . 'oc_planner_poi';
}

function opencomune_planner_catalog_maybe_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $city = opencomune_planner_city_table();
    dbDelta("CREATE TABLE {$city} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(64) NOT NULL,
        name varchar(255) NOT NULL,
        image varchar(1000) DEFAULT NULL,
        lat double NOT NULL DEFAULT 0,
        lng double NOT NULL DEFAULT 0,
        address varchar(500) NOT NULL DEFAULT '',
        sort_order int NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY active_sort (active, sort_order)
    ) {$charset};");

    $interest = opencomune_planner_interest_table();
    dbDelta("CREATE TABLE {$interest} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(64) NOT NULL,
        name varchar(255) NOT NULL,
        image varchar(1000) DEFAULT NULL,
        is_food tinyint(1) NOT NULL DEFAULT 0,
        sort_order int NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY active_sort (active, sort_order)
    ) {$charset};");

    $focus = opencomune_planner_focus_table();
    dbDelta("CREATE TABLE {$focus} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        slug varchar(64) NOT NULL,
        name varchar(255) NOT NULL,
        image varchar(1000) DEFAULT NULL,
        sort_order int NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY active_sort (active, sort_order)
    ) {$charset};");

    $poi = opencomune_planner_poi_table();
    dbDelta("CREATE TABLE {$poi} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(255) NOT NULL,
        description longtext DEFAULT NULL,
        city varchar(64) NOT NULL DEFAULT '',
        lat double NOT NULL,
        lng double NOT NULL,
        address varchar(500) DEFAULT NULL,
        duration_min int NOT NULL DEFAULT 60,
        cover_image varchar(1000) DEFAULT NULL,
        categories text NOT NULL,
        popularity int NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug),
        KEY active_city (active, city)
    ) {$charset};");
}
add_action('after_switch_theme', 'opencomune_planner_catalog_maybe_create_tables');
add_action('init', 'opencomune_planner_catalog_maybe_create_tables', 4);

function opencomune_planner_catalog_slugify($input) {
    return sanitize_title((string) $input);
}

function opencomune_planner_get_cities($active_only = false) {
    global $wpdb;
    $table = opencomune_planner_city_table();
    $sql = "SELECT * FROM {$table}";
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function opencomune_planner_get_city_by_id($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_city_table() . ' WHERE id = %d', $id),
        ARRAY_A
    );
}

function opencomune_planner_count_poi_by_city_slug($city_slug) {
    global $wpdb;
    return (int) $wpdb->get_var(
        $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . opencomune_planner_poi_table() . ' WHERE city = %s',
            $city_slug
        )
    );
}

function opencomune_planner_get_interests($active_only = false) {
    global $wpdb;
    $table = opencomune_planner_interest_table();
    $sql = "SELECT * FROM {$table}";
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function opencomune_planner_get_interest_by_id($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_interest_table() . ' WHERE id = %d', $id),
        ARRAY_A
    );
}

function opencomune_planner_get_focuses($active_only = false) {
    global $wpdb;
    $table = opencomune_planner_focus_table();
    $sql = "SELECT * FROM {$table}";
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function opencomune_planner_get_focus_by_id($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_focus_table() . ' WHERE id = %d', $id),
        ARRAY_A
    );
}

function opencomune_planner_get_pois($active_only = false) {
    global $wpdb;
    $table = opencomune_planner_poi_table();
    $sql = "SELECT * FROM {$table}";
    if ($active_only) {
        $sql .= ' WHERE active = 1';
    }
    $sql .= ' ORDER BY city ASC, active DESC, popularity DESC, name ASC';
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function opencomune_planner_get_poi_by_id($id) {
    global $wpdb;
    $id = (int) $id;
    if ($id <= 0) {
        return null;
    }
    return $wpdb->get_row(
        $wpdb->prepare('SELECT * FROM ' . opencomune_planner_poi_table() . ' WHERE id = %d', $id),
        ARRAY_A
    );
}

function opencomune_planner_city_name_map() {
    $map = [];
    foreach (opencomune_planner_get_cities(false) as $row) {
        $map[$row['slug']] = $row['name'];
    }
    return $map;
}

function opencomune_planner_interest_name_map() {
    $map = opencomune_planner_esperienze_category_slug_name_map();
    if ($map !== []) {
        return $map;
    }

    foreach (opencomune_planner_get_interests(false) as $row) {
        $map[$row['slug']] = $row['name'];
    }

    return $map;
}

/**
 * Slug => nome categorie esperienze (tassonomia categorie_esperienze).
 *
 * @return array<string, string>
 */
function opencomune_planner_esperienze_category_slug_name_map() {
    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms) || $terms === []) {
        return [];
    }

    $map = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }
        $name = (string) $term->name;
        if (function_exists('opencomune_sanitize_taxonomy_display_name')) {
            $name = opencomune_sanitize_taxonomy_display_name($name);
        } else {
            $name = opencomune_planner_plain_label($name);
        }
        $map[(string) $term->slug] = $name;
    }

    return $map;
}

/**
 * @return list<array{slug: string, name: string}>
 */
function opencomune_planner_esperienze_category_choices_for_admin() {
    $choices = [];
    foreach (opencomune_planner_esperienze_category_slug_name_map() as $slug => $name) {
        $choices[] = [
            'slug' => $slug,
            'name' => $name,
        ];
    }

    return $choices;
}

/**
 * @return list<array{id: int, title: string, lat: float, lng: float, duration_min: int, city_slug: string, category_slugs: string[], permalink: string, cover: string, popularity: int, source: string}>
 */
function opencomune_planner_load_poi_candidates($city_slug = '', $category_slugs = []) {
    $category_slugs = array_map('strval', $category_slugs);
    $items = [];
    foreach (opencomune_planner_get_pois(true) as $row) {
        if ($city_slug !== '' && (string) $row['city'] !== $city_slug) {
            continue;
        }
        $cats = array_filter(array_map('trim', explode(',', (string) $row['categories'])));
        if ($category_slugs !== []) {
            $match = false;
            foreach ($cats as $slug) {
                if (in_array($slug, $category_slugs, true)) {
                    $match = true;
                    break;
                }
            }
            if (!$match) {
                continue;
            }
        }
        $items[] = [
            'id' => (int) $row['id'],
            'title' => (string) $row['name'],
            'lat' => (float) $row['lat'],
            'lng' => (float) $row['lng'],
            'duration_min' => max(15, min(240, (int) $row['duration_min'])),
            'city_slug' => (string) $row['city'],
            'category_slugs' => $cats,
            'permalink' => '',
            'cover' => (string) ($row['cover_image'] ?? ''),
            'popularity' => (int) $row['popularity'],
            'source' => 'poi',
            'address' => sanitize_text_field((string) ($row['address'] ?? '')),
        ];
    }
    return $items;
}

function opencomune_planner_load_all_candidates($city_slug = '', $category_slugs = []) {
    $esperienze = opencomune_planner_load_esperienze_candidates($city_slug, $category_slugs);
    foreach ($esperienze as &$row) {
        $row['source'] = 'esperienza';
    }
    unset($row);
    $pois = opencomune_planner_load_poi_candidates($city_slug, $category_slugs);
    return array_merge($esperienze, $pois);
}

function opencomune_planner_import_interests_from_taxonomy() {
    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
    ]);
    if (is_wp_error($terms) || $terms === []) {
        return 0;
    }

    global $wpdb;
    $table = opencomune_planner_interest_table();
    $imported = 0;
    $order = 0;
    foreach ($terms as $term) {
        $exists = (int) $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$table} WHERE slug = %s", $term->slug)
        );
        if ($exists > 0) {
            continue;
        }
        $wpdb->insert(
            $table,
            [
                'slug' => $term->slug,
                'name' => opencomune_planner_plain_label($term->name),
                'sort_order' => $order,
                'active' => 1,
            ],
            ['%s', '%s', '%d', '%d']
        );
        $imported++;
        $order++;
    }
    return $imported;
}

function opencomune_planner_config_cities_payload() {
    $rows = opencomune_planner_get_cities(true);
    if ($rows !== []) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['slug'],
                'name' => opencomune_planner_plain_label($row['name']),
                'image' => $row['image'] ?: null,
                'center' => [
                    'lat' => (float) $row['lat'],
                    'lng' => (float) $row['lng'],
                    'address' => (string) $row['address'],
                ],
            ];
        }
        return $out;
    }

    $cities_map = [];
    $candidates = opencomune_planner_load_esperienze_candidates();
    foreach ($candidates as $item) {
        if ($item['city_slug'] === '') {
            continue;
        }
        if (!isset($cities_map[$item['city_slug']])) {
            $cities_map[$item['city_slug']] = [
                'id' => $item['city_slug'],
                'name' => ucwords(str_replace('-', ' ', $item['city_slug'])),
                'image' => null,
                'center' => [
                    'lat' => $item['lat'],
                    'lng' => $item['lng'],
                    'address' => '',
                ],
            ];
        }
    }
    return array_values($cities_map);
}

function opencomune_planner_config_categories_payload() {
    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms) || $terms === []) {
        return [];
    }

    $categories = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $image = '';
        if (function_exists('opencomune_esperienza_category_get_image_url')) {
            $image = opencomune_esperienza_category_get_image_url((int) $term->term_id);
        } else {
            $meta = get_term_meta((int) $term->term_id, 'image', true);
            $image = is_string($meta) && $meta !== '' ? esc_url($meta) : '';
        }

        $categories[] = [
            'id' => $term->slug,
            'name' => opencomune_planner_plain_label($term->name),
            'image' => $image !== '' ? $image : null,
        ];
    }

    return $categories;
}

function opencomune_planner_config_traveler_types_payload() {
    $rows = opencomune_planner_get_focuses(true);
    if ($rows !== []) {
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => $row['slug'],
                'name' => opencomune_planner_plain_label($row['name']),
                'image' => $row['image'] ?: null,
            ];
        }
        return $out;
    }
    return opencomune_planner_traveler_types();
}
