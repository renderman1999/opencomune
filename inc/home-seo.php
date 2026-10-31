<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_home_seo_place_label() {
    $nome = trim((string) get_option('opencomune_nome_comune', ''));

    return $nome !== '' ? $nome : '';
}

function opencomune_get_public_esperienze_cta_label() {
    $place_label = opencomune_get_home_seo_place_label();
    if ($place_label !== '') {
        return sprintf(
            /* translators: %s: territory or place name */
            __('Esplora le esperienze su %s', 'opencomune'),
            $place_label
        );
    }

    return __('Esplora le esperienze', 'opencomune');
}

function opencomune_get_home_hero_image_url() {
    $default = 'https://betadev.it/explora/wp-content/uploads/2025/06/lecce_home.jpg';
    $url = trim((string) get_option('opencomune_swiper_slide_1_image', $default));

    return $url !== '' ? esc_url($url) : esc_url($default);
}

/**
 * @return array{
 *   place: string,
 *   title: string,
 *   description: string,
 *   image: string,
 *   url: string,
 *   site_name: string
 * }
 */
function opencomune_get_home_seo_context() {
    static $context = null;
    if ($context !== null) {
        return $context;
    }

    $place = opencomune_get_home_seo_place_label();
    $site_name = get_bloginfo('name');
    $url = home_url('/');

    if ($place !== '') {
        $title = sprintf(
            /* translators: %1$s: destination or municipality name, %2$s: site name */
            __('Esperienze a %1$s | %2$s', 'opencomune'),
            $place,
            $site_name
        );
        $description = sprintf(
            /* translators: %s: destination or municipality name */
            __('Prenota tour, attività ed eventi a %s: confronta le proposte, leggi le recensioni e prenota online in modo sicuro.', 'opencomune'),
            $place
        );
    } else {
        $title = sprintf(
            /* translators: %s: site name */
            __('Esperienze nel territorio | %s', 'opencomune'),
            $site_name
        );
        $description = __(
            'Prenota tour, attività ed eventi nel territorio: schede aggiornate, recensioni e pagamento sicuro. Esplora per categoria o sulla mappa.',
            'opencomune'
        );
    }

    $context = [
        'place' => $place,
        'title' => $title,
        'description' => $description,
        'image' => opencomune_get_home_hero_image_url(),
        'url' => $url,
        'site_name' => $site_name,
    ];

    return $context;
}

function opencomune_filter_home_document_title($title) {
    if (!is_front_page()) {
        return $title;
    }

    $ctx = opencomune_get_home_seo_context();

    return $ctx['title'];
}
add_filter('pre_get_document_title', 'opencomune_filter_home_document_title', 20);

function opencomune_output_home_seo_head_tags() {
    if (!is_front_page()) {
        return;
    }

    $ctx = opencomune_get_home_seo_context();

    printf(
        '<meta name="description" content="%s">' . "\n",
        esc_attr($ctx['description'])
    );

    $tags = [
        'og:type' => 'website',
        'og:title' => $ctx['title'],
        'og:description' => $ctx['description'],
        'og:url' => $ctx['url'],
        'og:site_name' => $ctx['site_name'],
        'og:locale' => 'it_IT',
        'twitter:card' => 'summary_large_image',
        'twitter:title' => $ctx['title'],
        'twitter:description' => $ctx['description'],
    ];

    if ($ctx['image'] !== '') {
        $tags['og:image'] = $ctx['image'];
        $tags['twitter:image'] = $ctx['image'];
    }

    foreach ($tags as $property => $content) {
        if ($content === '') {
            continue;
        }

        $attr = strpos($property, 'twitter:') === 0 ? 'name' : 'property';
        printf(
            '<meta %1$s="%2$s" content="%3$s">' . "\n",
            esc_attr($attr),
            esc_attr($property),
            esc_attr($content)
        );
    }

    if ($ctx['image'] !== '') {
        printf(
            '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n",
            esc_url($ctx['image'])
        );
    }
}
add_action('wp_head', 'opencomune_output_home_seo_head_tags', 4);

function opencomune_get_home_seo_logo_url() {
    $logo_id = (int) get_theme_mod('custom_logo');
    if ($logo_id > 0) {
        $src = wp_get_attachment_image_url($logo_id, 'full');
        if (is_string($src) && $src !== '') {
            return $src;
        }
    }

    $icon = get_site_icon_url(512);

    return is_string($icon) ? $icon : '';
}

function opencomune_build_home_json_ld_graph() {
    $ctx = opencomune_get_home_seo_context();
    $graph = [];

    $organization = [
        '@type' => 'Organization',
        '@id' => trailingslashit($ctx['url']) . '#organization',
        'name' => $ctx['site_name'],
        'url' => $ctx['url'],
    ];

    $logo = opencomune_get_home_seo_logo_url();
    if ($logo !== '') {
        $organization['logo'] = [
            '@type' => 'ImageObject',
            'url' => $logo,
        ];
    }

    $graph[] = $organization;

    $website = [
        '@type' => 'WebSite',
        '@id' => trailingslashit($ctx['url']) . '#website',
        'url' => $ctx['url'],
        'name' => $ctx['site_name'],
        'description' => $ctx['description'],
        'publisher' => ['@id' => $organization['@id']],
        'inLanguage' => 'it-IT',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => home_url('/?s={search_term_string}'),
            ],
            'query-input' => 'required name=search_term_string',
        ],
    ];

    $graph[] = $website;

    if (function_exists('opencomune_get_home_esperienze_for_visitors')) {
        $posts = opencomune_get_home_esperienze_for_visitors(12);
        if ($posts !== []) {
            $items = [];
            $position = 1;
            foreach ($posts as $post) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'url' => get_permalink($post),
                    'name' => wp_strip_all_tags(get_the_title($post)),
                ];
                $position++;
            }

            $graph[] = [
                '@type' => 'ItemList',
                '@id' => trailingslashit($ctx['url']) . '#featured-experiences',
                'name' => __('Esperienze in evidenza', 'opencomune'),
                'itemListElement' => $items,
            ];
        }
    }

    if (function_exists('opencomune_get_esperienze_archive_filter_categories')) {
        $terms = opencomune_get_esperienze_archive_filter_categories();
        if ($terms !== []) {
            $items = [];
            $position = 1;
            foreach ($terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }
                $name = function_exists('opencomune_sanitize_taxonomy_display_name')
                    ? opencomune_sanitize_taxonomy_display_name($term->name)
                    : $term->name;
                $url = opencomune_get_esperienza_category_archive_url($term);
                if ($url === '') {
                    continue;
                }
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'url' => $url,
                    'name' => $name,
                ];
                $position++;
            }

            if ($items !== []) {
                $graph[] = [
                    '@type' => 'ItemList',
                    '@id' => trailingslashit($ctx['url']) . '#experience-categories',
                    'name' => __('Categorie esperienze', 'opencomune'),
                    'itemListElement' => $items,
                ];
            }
        }
    }

    return [
        '@context' => 'https://schema.org',
        '@graph' => $graph,
    ];
}

function opencomune_output_home_json_ld() {
    if (!is_front_page()) {
        return;
    }

    $graph = opencomune_build_home_json_ld_graph();
    if (empty($graph['@graph'])) {
        return;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'opencomune_output_home_json_ld', 6);
