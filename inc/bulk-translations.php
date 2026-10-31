<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_get_categoria_english_name_map() {
    return [
        'Animazione urbana' => 'Urban entertainment',
        'Arte' => 'Art',
        'Arte contemporanea' => 'Contemporary art',
        'Arte e Musei' => 'Art and museums',
        'Circo' => 'Circus',
        'Cultura' => 'Culture',
        'Danza' => 'Dance',
        'Enogastronomia' => 'Food and wine tourism',
        'Esperienza' => 'Experience',
        'Eventi Speciali' => 'Special events',
        'Expo' => 'Expo',
        'Food' => 'Food',
        'Food & Wine' => 'Food & Wine',
        'Fotografia' => 'Photography',
        'Inclusione' => 'Inclusion',
        'Installazione luminosa' => 'Light installation',
        'Intrattenimento' => 'Entertainment',
        'Istituzionale' => 'Institutional',
        'Lirica' => 'Opera',
        'Moda' => 'Fashion',
        'Museo' => 'Museum',
        'Musica' => 'Music',
        'Natura e Paesaggi' => 'Nature and landscapes',
        'Parata' => 'Parade',
        'Rievocazione storica' => 'Historical reenactment',
        'Sport' => 'Sport',
        'Sport inclusivo' => 'Inclusive sport',
        'Storia e Archeologia' => 'History and archaeology',
        'Teatro' => 'Theatre',
        'Tour' => 'Tour',
        'Tradizione' => 'Tradition',
        'Tradizioni Locali' => 'Local traditions',
        'Vintage' => 'Vintage',
    ];
}

function opencomune_normalize_taxonomy_label($label) {
    return html_entity_decode(wp_strip_all_tags((string) $label), ENT_QUOTES, 'UTF-8');
}

function opencomune_seed_categoria_translations($overwrite = false) {
    $map = opencomune_get_categoria_english_name_map();
    $terms = get_terms([
        'taxonomy' => 'categorie_esperienze',
        'hide_empty' => false,
    ]);

    if (is_wp_error($terms)) {
        return [
            'updated' => 0,
            'skipped' => 0,
            'errors' => [$terms->get_error_message()],
        ];
    }

    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($terms as $term) {
        $label = opencomune_normalize_taxonomy_label($term->name);
        $english = $map[$label] ?? '';

        if ($english === '') {
            $errors[] = sprintf('Missing EN map for category: %s', $label);
            continue;
        }

        $existing = get_term_meta($term->term_id, 'name_en', true);
        if ($existing !== '' && !$overwrite) {
            $skipped++;
            continue;
        }

        update_term_meta($term->term_id, 'name_en', $english);
        $updated++;
    }

    return compact('updated', 'skipped', 'errors');
}

function opencomune_auto_translate_it_to_en($text) {
    $text = trim(html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES, 'UTF-8'));
    if ($text === '') {
        return '';
    }

    $chunks = [];
    $max_chunk = 3500;
    if (strlen($text) <= $max_chunk) {
        $chunks[] = $text;
    } else {
        $parts = preg_split('/(\n+|(?<=[.!?])\s+)/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $buffer = '';
        foreach ($parts as $part) {
            if (strlen($buffer . $part) > $max_chunk && $buffer !== '') {
                $chunks[] = $buffer;
                $buffer = $part;
            } else {
                $buffer .= $part;
            }
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }
    }

    $translated = [];
    foreach ($chunks as $chunk) {
        $url = add_query_arg([
            'client' => 'gtx',
            'sl' => 'it',
            'tl' => 'en',
            'dt' => 't',
            'q' => $chunk,
        ], 'https://translate.googleapis.com/translate_a/single');

        $response = wp_remote_get($url, [
            'timeout' => 20,
            'headers' => [
                'User-Agent' => 'OpenComuneTranslationBot/1.0',
            ],
        ]);

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);
        if (!is_array($json) || empty($json[0])) {
            return '';
        }

        $segment = '';
        foreach ($json[0] as $line) {
            if (isset($line[0])) {
                $segment .= $line[0];
            }
        }
        $translated[] = $segment;
        usleep(150000);
    }

    return trim(implode(' ', array_filter($translated)));
}

function opencomune_seed_esperienza_translations($post_id, $overwrite = false) {
    $post_id = (int) $post_id;
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'esperienze') {
        return ['updated' => 0, 'skipped' => 0, 'errors' => ['Invalid experience ID']];
    }

    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach (opencomune_get_esperienza_translation_field_defs() as $field_key => $def) {
        $existing = opencomune_get_esperienza_english_field_value($post_id, $field_key);
        if ($existing !== '' && !$overwrite) {
            $skipped++;
            continue;
        }

        $source = opencomune_get_esperienza_italian_field_value($post_id, $field_key);
        if ($source === '') {
            $skipped++;
            continue;
        }

        $english = opencomune_auto_translate_it_to_en($source);
        if ($english === '') {
            $errors[] = sprintf('Translation failed for field %s on post %d', $field_key, $post_id);
            continue;
        }

        update_post_meta($post_id, $def['meta'], $english);
        $updated++;
    }

    return compact('updated', 'skipped', 'errors');
}

function opencomune_run_bulk_translation_migration($args = []) {
    $args = wp_parse_args($args, [
        'categories' => true,
        'esperienze' => true,
        'overwrite' => false,
        'limit' => 0,
        'offset' => 0,
    ]);

    $summary = [
        'categories' => null,
        'esperienze' => [],
        'totals' => [
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'processed' => 0,
        ],
    ];

    if ($args['categories']) {
        $summary['categories'] = opencomune_seed_categoria_translations((bool) $args['overwrite']);
        $summary['totals']['updated'] += (int) $summary['categories']['updated'];
        $summary['totals']['skipped'] += (int) $summary['categories']['skipped'];
        $summary['totals']['errors'] += count($summary['categories']['errors']);
    }

    if ($args['esperienze']) {
        $query_args = [
            'post_type' => 'esperienze',
            'post_status' => 'publish',
            'posts_per_page' => $args['limit'] > 0 ? (int) $args['limit'] : -1,
            'offset' => max(0, (int) $args['offset']),
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
        ];

        $post_ids = get_posts($query_args);
        foreach ($post_ids as $post_id) {
            $result = opencomune_seed_esperienza_translations($post_id, (bool) $args['overwrite']);
            $summary['esperienze'][$post_id] = $result;
            $summary['totals']['updated'] += (int) $result['updated'];
            $summary['totals']['skipped'] += (int) $result['skipped'];
            $summary['totals']['errors'] += count($result['errors']);
            $summary['totals']['processed']++;
        }
    }

    return $summary;
}

function opencomune_register_bulk_translations_admin_page() {
    add_submenu_page(
        'opencomune-settings',
        'Migrazione traduzioni EN',
        'Traduzioni EN',
        'manage_options',
        'opencomune-bulk-translations',
        'opencomune_render_bulk_translations_admin_page'
    );
}
add_action('admin_menu', 'opencomune_register_bulk_translations_admin_page');

function opencomune_render_bulk_translations_admin_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = null;
    if (isset($_POST['opencomune_run_bulk_translations']) && check_admin_referer('opencomune_bulk_translations')) {
        $summary = opencomune_run_bulk_translation_migration([
            'categories' => !empty($_POST['seed_categories']),
            'esperienze' => !empty($_POST['seed_esperienze']),
            'overwrite' => !empty($_POST['overwrite_existing']),
            'limit' => isset($_POST['batch_limit']) ? (int) $_POST['batch_limit'] : 0,
            'offset' => isset($_POST['batch_offset']) ? (int) $_POST['batch_offset'] : 0,
        ]);

        $notice = [
            'type' => $summary['totals']['errors'] > 0 ? 'warning' : 'success',
            'message' => sprintf(
                'Migrazione completata. Aggiornati: %d, saltati: %d, errori: %d, esperienze processate: %d.',
                $summary['totals']['updated'],
                $summary['totals']['skipped'],
                $summary['totals']['errors'],
                $summary['totals']['processed']
            ),
        ];
    }

    $base_url = rest_url('opencomune/v1/esperienze');
    $published_count = (int) wp_count_posts('esperienze')->publish;
    ?>
    <div class="wrap">
        <h1>Migrazione traduzioni EN</h1>
        <p class="description">
            Popola automaticamente le traduzioni inglesi mancanti per categorie ed esperienze pubblicate.
            Le categorie usano una mappa predefinita; le esperienze vengono tradotte dal contenuto italiano.
        </p>

        <?php if ($notice) : ?>
            <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
                <p><?php echo esc_html($notice['message']); ?></p>
            </div>
        <?php endif; ?>

        <div class="card" style="max-width: 920px; padding: 16px 20px; margin-top: 16px;">
            <h2>Integrazione API consigliata</h2>
            <ul style="list-style: disc; padding-left: 20px;">
                <li>Contenuto localizzato: <code><?php echo esc_html($base_url); ?>?lang=en</code></li>
                <li>App multilingua: <code><?php echo esc_html($base_url); ?>?include_translations=1</code></li>
                <li>Dettaglio esperienza: <code><?php echo esc_html(trailingslashit($base_url)); ?>123?lang=en&amp;include_translations=1</code></li>
            </ul>
        </div>

        <form method="post" class="card" style="max-width: 920px; padding: 16px 20px; margin-top: 16px;">
            <?php wp_nonce_field('opencomune_bulk_translations'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">Ambito</th>
                    <td>
                        <label><input type="checkbox" name="seed_categories" value="1" checked> Categorie (<code>name_en</code>)</label><br>
                        <label><input type="checkbox" name="seed_esperienze" value="1" checked> Esperienze pubblicate (<?php echo esc_html((string) $published_count); ?>)</label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="batch_limit">Batch limit</label></th>
                    <td>
                        <input type="number" min="0" step="1" id="batch_limit" name="batch_limit" value="0" class="small-text">
                        <p class="description">0 = tutte. Usa batch (es. 10) per evitare timeout in caso di molte esperienze.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="batch_offset">Batch offset</label></th>
                    <td>
                        <input type="number" min="0" step="1" id="batch_offset" name="batch_offset" value="0" class="small-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">Sovrascrittura</th>
                    <td>
                        <label><input type="checkbox" name="overwrite_existing" value="1"> Sovrascrivi traduzioni EN gia presenti</label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" name="opencomune_run_bulk_translations" class="button button-primary">Avvia migrazione</button>
            </p>
        </form>
    </div>
    <?php
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('opencomune seed-translations', function ($args, $assoc_args) {
        $summary = opencomune_run_bulk_translation_migration([
            'categories' => !isset($assoc_args['skip-categories']),
            'esperienze' => !isset($assoc_args['skip-esperienze']),
            'overwrite' => isset($assoc_args['overwrite']),
            'limit' => isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0,
            'offset' => isset($assoc_args['offset']) ? (int) $assoc_args['offset'] : 0,
        ]);

        WP_CLI::success(wp_json_encode($summary['totals']));
        if (!empty($summary['categories']['errors'])) {
            foreach ($summary['categories']['errors'] as $error) {
                WP_CLI::warning($error);
            }
        }
    });
}
