<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Nome tassonomia per UI (decodifica entità HTML eventualmente salvate nel termine).
 */
function opencomune_sanitize_taxonomy_display_name($name) {
    $name = (string) $name;
    if ($name === '') {
        return '';
    }

    $decoded = html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = wp_strip_all_tags($decoded);

    return trim(preg_replace('/\s+/u', ' ', $decoded));
}

/**
 * @return array<string, mixed>
 */
function opencomune_esperienze_dashboard_item_from_post($post_id) {
    $post_id = (int) $post_id;
    $categorie_terms = wp_get_post_terms($post_id, 'categorie_esperienze', ['fields' => 'names']);
    $categorie_names = is_wp_error($categorie_terms) ? [] : $categorie_terms;
    $categorie_names = array_values(array_filter(array_map('opencomune_sanitize_taxonomy_display_name', $categorie_names)));

    $author_id = (int) get_post_field('post_author', $post_id);
    $item = [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'excerpt' => get_post_field('post_excerpt', $post_id),
        'status' => get_post_status($post_id),
        'date' => get_the_date('d/m/Y', $post_id),
        'edit_url' => opencomune_get_esperienza_wizard_url($post_id),
        'view_url' => get_permalink($post_id),
        'translations' => opencomune_get_dashboard_esperienza_translations($post_id),
        'views' => get_post_meta($post_id, 'views_count', true) ?: 0,
        'likes' => get_post_meta($post_id, 'likes_count', true) ?: 0,
        'thumbnail' => get_the_post_thumbnail_url($post_id, 'thumbnail'),
        'duration' => get_post_meta($post_id, 'tour_duration', true),
        'max_participants' => get_post_meta($post_id, 'tour_max_participants', true),
        'categories' => $categorie_names,
        'categories_label' => $categorie_names !== [] ? implode(', ', $categorie_names) : '',
    ];

    if (opencomune_user_can_manage_all_esperienze()) {
        $item['author_id'] = $author_id;
        $item['author_name'] = opencomune_get_esperienza_author_label($author_id);
    }

    return $item;
}

/**
 * @param array<string, mixed> $filters
 * @return array<string, mixed>
 */
function opencomune_esperienze_dashboard_build_query_args(array $filters) {
    $search = isset($filters['search']) ? sanitize_text_field((string) $filters['search']) : '';
    $status = isset($filters['status']) ? sanitize_text_field((string) $filters['status']) : '';
    $categoria = isset($filters['categoria']) ? sanitize_text_field((string) $filters['categoria']) : '';
    $sort = isset($filters['sort']) ? sanitize_text_field((string) $filters['sort']) : 'date_desc';

    $args = [
        'post_type' => 'esperienze',
        'post_status' => $status !== '' ? [$status] : ['publish', 'draft', 'pending'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'ignore_sticky_posts' => true,
    ];

    if (!opencomune_user_can_manage_all_esperienze()) {
        $args['author'] = get_current_user_id();
    }

    if ($search !== '') {
        $args['s'] = $search;
    }

    if ($categoria !== '') {
        $args['tax_query'] = [
            [
                'taxonomy' => 'categorie_esperienze',
                'field' => 'slug',
                'terms' => $categoria,
            ],
        ];
    }

    switch ($sort) {
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'title_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
        default:
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
    }

    return $args;
}

/**
 * @param array<string, mixed> $filters
 * @return array{total: int, filtered: int, rows: array<int, array<string, mixed>>}
 */
function opencomune_esperienze_dashboard_fetch_page(array $filters, $offset, $length) {
    $offset = max(0, (int) $offset);
    $length = max(1, min(100, (int) $length));

    $base_filters = $filters;
    unset($base_filters['search']);
    $total_args = opencomune_esperienze_dashboard_build_query_args($base_filters);
    $total_query = new WP_Query($total_args);
    $records_total = (int) $total_query->found_posts;
    wp_reset_postdata();

    $page_args = opencomune_esperienze_dashboard_build_query_args($filters);
    $count_args = $page_args;
    $count_args['posts_per_page'] = 1;
    $count_args['fields'] = 'ids';
    $filtered_query = new WP_Query($count_args);
    $records_filtered = (int) $filtered_query->found_posts;
    wp_reset_postdata();

    $page_args['posts_per_page'] = $length;
    $page_args['offset'] = $offset;
    $page_args['fields'] = 'ids';

    $page_query = new WP_Query($page_args);

    $rows = [];
    foreach ($page_query->posts as $post_id) {
        $rows[] = opencomune_esperienze_dashboard_item_from_post((int) $post_id);
    }
    wp_reset_postdata();

    return [
        'total' => $records_total,
        'filtered' => $records_filtered,
        'rows' => $rows,
    ];
}

function opencomune_esperienze_dashboard_datatables_response() {
    $draw = (int) ($_POST['draw'] ?? 0);
    $start = (int) ($_POST['start'] ?? 0);
    $length = (int) ($_POST['length'] ?? 10);
    $search_value = sanitize_text_field($_POST['search']['value'] ?? '');
    $filter_status = sanitize_text_field($_POST['filter_status'] ?? '');
    $filter_categoria = sanitize_text_field($_POST['filter_categoria'] ?? '');

    $order_column = (int) ($_POST['order'][0]['column'] ?? 2);
    $order_dir = sanitize_text_field($_POST['order'][0]['dir'] ?? 'desc');
    $show_author = opencomune_user_can_manage_all_esperienze();

    $sort = 'date_desc';
    if ($order_column === 0) {
        $sort = $order_dir === 'asc' ? 'date_asc' : 'date_desc';
    } elseif ($order_column === 2 || ($show_author && $order_column === 4)) {
        $sort = $order_dir === 'asc' ? 'title_asc' : 'title_desc';
    } elseif ($order_column === 1) {
        $sort = $order_dir === 'asc' ? 'date_asc' : 'date_desc';
    }

    $filters = [
        'search' => $search_value,
        'status' => $filter_status,
        'categoria' => $filter_categoria,
        'sort' => $sort,
    ];

    $result = opencomune_esperienze_dashboard_fetch_page($filters, $start, $length);

    wp_send_json([
        'draw' => $draw,
        'recordsTotal' => $result['total'],
        'recordsFiltered' => $result['filtered'],
        'data' => $result['rows'],
    ]);
}

function opencomune_esperienze_dashboard_legacy_response() {
    $search = sanitize_text_field($_GET['search'] ?? '');
    $status = sanitize_text_field($_GET['status'] ?? '');
    $categoria = sanitize_text_field($_GET['categoria'] ?? '');
    $sort = sanitize_text_field($_GET['sort'] ?? 'date_desc');
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = 10;

    $filters = [
        'search' => $search,
        'status' => $status,
        'categoria' => $categoria,
        'sort' => $sort,
    ];

    $result = opencomune_esperienze_dashboard_fetch_page($filters, ($page - 1) * $per_page, $per_page);
    $total_pages = $per_page > 0 ? (int) ceil($result['filtered'] / $per_page) : 1;

    wp_send_json_success([
        'esperienze' => $result['rows'],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, $total_pages),
            'total_posts' => $result['filtered'],
        ],
    ]);
}
