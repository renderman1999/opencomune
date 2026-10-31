<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_pricing_category_public_id($internal_id) {
    $num = abs(crc32((string) $internal_id)) % 900000;
    return 100000 + $num;
}

function opencomune_normalize_pricing_categories_list($raw) {
    if (!is_array($raw)) {
        return [];
    }

    $items = [];
    $has_default = false;

    foreach ($raw as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $label = sanitize_text_field($item['label'] ?? '');
        if ($label === '') {
            continue;
        }

        $id = sanitize_text_field($item['id'] ?? '');
        if ($id === '') {
            $id = 'pc_' . uniqid('', true);
        }

        $is_default = !empty($item['is_default']);
        if ($is_default) {
            $has_default = true;
        }

        $min_age = isset($item['min_age']) && $item['min_age'] !== '' ? max(0, (int) $item['min_age']) : null;
        $max_age = isset($item['max_age']) && $item['max_age'] !== '' ? max(0, (int) $item['max_age']) : null;

        $public_id = (int) ($item['public_id'] ?? 0);
        if ($public_id <= 0) {
            $public_id = opencomune_pricing_category_public_id($id);
        }

        $price = opencomune_sanitize_esperienza_base_price($item['price'] ?? 0);
        if ($price === null) {
            $price = 0.0;
        }

        $items[] = [
            'id' => $id,
            'public_id' => $public_id,
            'label' => $label,
            'description' => sanitize_textarea_field($item['description'] ?? ''),
            'price' => $price,
            'min_age' => $min_age,
            'max_age' => $max_age,
            'is_default' => $is_default,
            'sort_order' => isset($item['sort_order']) ? (int) $item['sort_order'] : $index,
        ];
    }

    usort($items, static function ($a, $b) {
        return $a['sort_order'] <=> $b['sort_order'];
    });

    if (!empty($items) && !$has_default) {
        $items[0]['is_default'] = true;
    }

    $default_set = false;
    foreach ($items as &$row) {
        if ($row['is_default']) {
            if ($default_set) {
                $row['is_default'] = false;
            } else {
                $default_set = true;
            }
        }
    }
    unset($row);

    if (!empty($items) && !$default_set) {
        $items[0]['is_default'] = true;
    }

    return $items;
}

function opencomune_get_default_pricing_categories() {
    return opencomune_normalize_pricing_categories_list([
        [
            'id' => 'pc_default_adults',
            'label' => __('Adulti', 'opencomune'),
            'description' => '',
            'min_age' => 18,
            'max_age' => null,
            'is_default' => true,
            'sort_order' => 0,
            'price' => 0,
        ],
        [
            'id' => 'pc_default_children',
            'label' => __('Bambini', 'opencomune'),
            'description' => '',
            'min_age' => null,
            'max_age' => 17,
            'is_default' => false,
            'sort_order' => 1,
            'price' => 0,
        ],
    ]);
}

function opencomune_apply_legacy_base_price_to_categories(array $items, $post_id) {
    if (empty($items)) {
        return $items;
    }

    $has_positive = false;
    foreach ($items as $item) {
        if ((float) ($item['price'] ?? 0) > 0) {
            $has_positive = true;
            break;
        }
    }
    if ($has_positive) {
        return $items;
    }

    $raw = get_post_meta((int) $post_id, 'tour_price', true);
    if ($raw === '' || $raw === false) {
        $raw = get_post_meta((int) $post_id, 'prezzo', true);
    }
    $base = opencomune_sanitize_esperienza_base_price($raw);
    if ($base === null || $base <= 0) {
        return $items;
    }

    foreach ($items as &$item) {
        if (!empty($item['is_default'])) {
            $item['price'] = $base;
            break;
        }
    }
    unset($item);

    return $items;
}

function opencomune_get_pricing_category_unit_price(array $category_row, $experience_id = 0) {
    $price = opencomune_sanitize_esperienza_base_price($category_row['price'] ?? 0);

    return $price !== null ? $price : 0.0;
}

function opencomune_collect_pricing_category_amounts($post_id) {
    $amounts = [];
    foreach (opencomune_get_esperienza_pricing_categories($post_id) as $row) {
        $amounts[] = opencomune_get_pricing_category_unit_price($row, $post_id);
    }

    return $amounts;
}

function opencomune_sync_esperienza_base_price_from_categories($post_id, array $items) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0.0;
    }

    $min = null;
    foreach ($items as $item) {
        $price = opencomune_get_pricing_category_unit_price($item, 0);
        if ($min === null || $price < $min) {
            $min = $price;
        }
    }

    $amount = $min !== null ? $min : 0.0;

    return opencomune_save_esperienza_base_price($post_id, $amount);
}

function opencomune_get_esperienza_pricing_categories($post_id) {
    $post_id = (int) $post_id;
    $stored = get_post_meta($post_id, 'tour_pricing_categories', true);
    $items = opencomune_normalize_pricing_categories_list(is_array($stored) ? $stored : []);

    return opencomune_apply_legacy_base_price_to_categories($items, $post_id);
}

function opencomune_should_repopulate_esperienza_form_from_post() {
    return !empty($GLOBALS['opencomune_esperienza_repopulate_post_fields']);
}

/**
 * Categorie per il form: salvate, POST corrente (solo se validazione fallita), oppure default.
 */
function opencomune_resolve_pricing_categories_for_form($post_id) {
    $post_id = (int) $post_id;

    if (
        opencomune_should_repopulate_esperienza_form_from_post()
        && $_SERVER['REQUEST_METHOD'] === 'POST'
        && array_key_exists('tour_pricing_categories_payload', $_POST)
    ) {
        $raw = wp_unslash((string) ($_POST['tour_pricing_categories_payload'] ?? ''));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return opencomune_get_esperienza_pricing_categories($post_id);
        }

        return opencomune_normalize_pricing_categories_list($decoded);
    }

    $saved = opencomune_get_esperienza_pricing_categories($post_id);
    if (!empty($saved)) {
        return $saved;
    }

    return opencomune_get_default_pricing_categories();
}

function opencomune_save_esperienza_pricing_categories($post_id, $post_data) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $payload = $post_data['tour_pricing_categories_payload'] ?? '';
    $decoded = [];
    if (is_string($payload) && $payload !== '') {
        $decoded = json_decode(wp_unslash($payload), true);
    }
    if (!is_array($decoded)) {
        return opencomune_get_esperienza_pricing_categories($post_id);
    }

    $items = opencomune_normalize_pricing_categories_list($decoded);
    if (empty($items)) {
        return opencomune_get_esperienza_pricing_categories($post_id);
    }

    update_post_meta($post_id, 'tour_pricing_categories', $items);
    opencomune_sync_esperienza_base_price_from_categories($post_id, $items);

    return $items;
}

function opencomune_pricing_category_collects_traveler_name(array $category_row) {
    $max_age = $category_row['max_age'] ?? null;
    if ($max_age !== null && (int) $max_age < 18) {
        return false;
    }

    $min_age = $category_row['min_age'] ?? null;
    if ($min_age !== null && (int) $min_age >= 18) {
        return true;
    }

    $label = strtolower((string) ($category_row['label'] ?? ''));
    if (preg_match('/bambin|child|infant|ragazz|minor/i', $label)) {
        return false;
    }

    return true;
}

function opencomune_pricing_category_collects_traveler_name_by_id($post_id, $category_id) {
    $category_id = sanitize_text_field((string) $category_id);
    if ($category_id === '') {
        return true;
    }

    foreach (opencomune_get_esperienza_pricing_categories($post_id) as $row) {
        if (($row['id'] ?? '') === $category_id) {
            return opencomune_pricing_category_collects_traveler_name($row);
        }
    }

    return true;
}

function opencomune_format_pricing_categories_for_api($post_id) {
    $items = [];
    foreach (opencomune_get_esperienza_pricing_categories($post_id) as $row) {
        $items[] = [
            'id' => $row['id'],
            'public_id' => (int) $row['public_id'],
            'label' => $row['label'],
            'description' => $row['description'],
            'min_age' => $row['min_age'],
            'max_age' => $row['max_age'],
            'is_default' => (bool) $row['is_default'],
            'collects_traveler_name' => opencomune_pricing_category_collects_traveler_name($row),
            'sort_order' => (int) $row['sort_order'],
            'price' => opencomune_get_pricing_category_unit_price($row, $post_id),
        ];
    }

    return $items;
}

function opencomune_enqueue_pricing_categories_editor() {
    $theme = wp_get_theme();
    $ver = $theme->get('Version') ?: '1.0';

    wp_enqueue_style(
        'opencomune-pricing-categories',
        get_template_directory_uri() . '/assets/css/pricing-categories.css',
        [],
        $ver
    );

    wp_enqueue_script(
        'opencomune-pricing-categories',
        get_template_directory_uri() . '/assets/js/pricing-categories.js',
        [],
        $ver,
        true
    );

    wp_add_inline_script(
        'opencomune-pricing-categories',
        'window.opencomunePricingCategoriesStrings = ' . wp_json_encode([
            'createTitle' => __('Crea categoria prezzo', 'opencomune'),
            'editTitle' => __('Modifica categoria prezzo', 'opencomune'),
            'confirmDelete' => __('Rimuovere questa categoria prezzo?', 'opencomune'),
            'default' => __('Predefinita', 'opencomune'),
            'setDefault' => __('Imposta come predefinita', 'opencomune'),
            'empty' => __('Nessuna categoria prezzo selezionata.', 'opencomune'),
            'free' => __('Gratuito', 'opencomune'),
            'priceRequired' => __('Inserisci il prezzo per questa categoria (0 se gratuita).', 'opencomune'),
        ]) . ';',
        'before'
    );
}

/**
 * Prezzo base per persona (meta tour_price / prezzo legacy).
 */
function opencomune_sanitize_esperienza_base_price($raw) {
    if ($raw === '' || $raw === null) {
        return null;
    }

    if (is_string($raw)) {
        $raw = str_replace(',', '.', trim($raw));
    }

    if (!is_numeric($raw)) {
        return null;
    }

    $value = round(max(0, (float) $raw), 2);

    return $value;
}

function opencomune_get_esperienza_base_price($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0.0;
    }

    $amounts = opencomune_collect_pricing_category_amounts($post_id);
    if (!empty($amounts)) {
        return min($amounts);
    }

    $raw = opencomune_get_esperienza_meta_value($post_id, 'tour_price', 'prezzo');
    if ($raw === '' || $raw === false || $raw === null) {
        return 0.0;
    }

    $price = opencomune_sanitize_esperienza_base_price($raw);

    return $price !== null ? $price : 0.0;
}

function opencomune_esperienza_is_free($post_id) {
    $post_id = (int) $post_id;
    $amounts = opencomune_collect_pricing_category_amounts($post_id);
    if (!empty($amounts)) {
        return max($amounts) <= 0;
    }

    $raw = opencomune_get_esperienza_meta_value($post_id, 'tour_price', 'prezzo');
    $price = opencomune_sanitize_esperienza_base_price($raw);

    return $price === null || $price <= 0;
}

function opencomune_get_esperienza_price_form_value($post_id) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && array_key_exists('tour_price', $_POST)) {
        return wp_unslash((string) $_POST['tour_price']);
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $raw = get_post_meta($post_id, 'tour_price', true);
    if ($raw === '' || $raw === false) {
        $raw = get_post_meta($post_id, 'prezzo', true);
    }
    if ($raw === '' || $raw === false || $raw === null) {
        return '';
    }

    $price = opencomune_sanitize_esperienza_base_price($raw);

    return $price !== null ? (string) $price : '';
}

function opencomune_save_esperienza_base_price($post_id, $price) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0.0;
    }

    $price = opencomune_sanitize_esperienza_base_price($price);
    if ($price === null) {
        $price = 0.0;
    }

    update_post_meta($post_id, 'tour_price', (string) $price);
    update_post_meta($post_id, 'prezzo', (string) $price);

    return $price;
}

function opencomune_format_esperienza_price_display($amount, $with_currency = false) {
    $amount = opencomune_sanitize_esperienza_base_price($amount);
    if ($amount === null) {
        $amount = 0.0;
    }

    if ($amount <= 0) {
        return __('Gratuito', 'opencomune');
    }

    $formatted = number_format($amount, 2, ',', '.');

    if ($with_currency) {
        return '€ ' . $formatted;
    }

    return $formatted;
}

/**
 * Etichetta prezzo per card esperienza (home, archivio, mappa).
 */
function opencomune_get_esperienza_card_price_label($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $amounts = opencomune_collect_pricing_category_amounts($post_id);
    if (empty($amounts)) {
        $amounts = [opencomune_get_esperienza_base_price($post_id)];
    }

    $paid_amounts = array_values(array_filter($amounts, static function ($amount) {
        return (float) $amount > 0.001;
    }));

    if ($paid_amounts === []) {
        return __('Gratuito', 'opencomune');
    }

    $min = min($paid_amounts);
    $max = max($paid_amounts);
    if (abs($max - $min) >= 0.001) {
        return sprintf(
            __('Da %1$s a %2$s', 'opencomune'),
            opencomune_format_esperienza_price_display($min, true),
            opencomune_format_esperienza_price_display($max, true)
        );
    }

    return sprintf(
        __('Da %s', 'opencomune'),
        opencomune_format_esperienza_price_display($min, true)
    );
}

function opencomune_format_esperienza_price_api_payload($post_id) {
    $post_id = (int) $post_id;
    $amounts = opencomune_collect_pricing_category_amounts($post_id);
    if (empty($amounts)) {
        $amount = opencomune_get_esperienza_base_price($post_id);
        $amounts = [$amount];
    }

    $min = min($amounts);
    $max = max($amounts);
    $is_free = $max <= 0;

    if ($is_free) {
        $label = __('Gratuito', 'opencomune');
    } elseif (abs($max - $min) < 0.001) {
        $label = opencomune_format_esperienza_price_display($min, true);
    } else {
        $label = sprintf(
            __('da %s a %s', 'opencomune'),
            opencomune_format_esperienza_price_display($min, true),
            opencomune_format_esperienza_price_display($max, true)
        );
    }

    return [
        'amount' => $min,
        'amount_max' => $max,
        'currency' => 'EUR',
        'is_free' => $is_free,
        'label' => $label,
    ];
}
