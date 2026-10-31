<?php

if (!defined('ABSPATH')) {
    exit;
}

function opencomune_checkout_sessions_table_name() {
    global $wpdb;

    return $wpdb->prefix . 'tour_checkout_sessions';
}

function opencomune_ensure_checkout_sessions_table() {
    global $wpdb;

    $table = opencomune_checkout_sessions_table_name();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_token varchar(64) NOT NULL,
        experience_id bigint(20) unsigned NOT NULL,
        payload longtext NOT NULL,
        status varchar(32) NOT NULL DEFAULT 'open',
        booking_id bigint(20) unsigned NULL,
        expires_at datetime NOT NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY session_token (session_token),
        KEY experience_id (experience_id),
        KEY status (status)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

function opencomune_get_checkout_session_url($token) {
    return home_url('/checkout-esperienza/?session=' . rawurlencode($token));
}

function opencomune_calculate_checkout_line_items($experience_id, array $participants_by_category) {
    $experience_id = (int) $experience_id;
    $fallback_unit_price = opencomune_get_esperienza_base_price($experience_id);

    $categories = opencomune_get_esperienza_pricing_categories($experience_id);
    $by_id = [];
    foreach ($categories as $cat) {
        $by_id[$cat['id']] = $cat;
    }

    $lines = [];
    $total = 0.0;
    $total_pax = 0;

    foreach ($participants_by_category as $row) {
        if (!is_array($row)) {
            continue;
        }
        $cat_id = sanitize_text_field($row['category_id'] ?? '');
        $qty = max(0, (int) ($row['quantity'] ?? 0));
        if ($qty <= 0) {
            continue;
        }

        $label = isset($by_id[$cat_id]) ? $by_id[$cat_id]['label'] : __('Partecipante', 'opencomune');
        if (isset($by_id[$cat_id])) {
            $unit_price = opencomune_get_pricing_category_unit_price($by_id[$cat_id], $experience_id);
        } else {
            $unit_price = $fallback_unit_price;
        }
        $line_total = round($unit_price * $qty, 2);
        $lines[] = [
            'category_id' => $cat_id,
            'label' => $label,
            'quantity' => $qty,
            'unit_price' => $unit_price,
            'line_total' => $line_total,
        ];
        $total += $line_total;
        $total_pax += $qty;
    }

    if ($total_pax === 0) {
        $default_cat = null;
        foreach ($categories as $cat) {
            if (!empty($cat['is_default'])) {
                $default_cat = $cat;
                break;
            }
        }
        if (!$default_cat && !empty($categories)) {
            $default_cat = $categories[0];
        }

        $total_pax = 1;
        $unit_price = $default_cat
            ? opencomune_get_pricing_category_unit_price($default_cat, $experience_id)
            : $fallback_unit_price;
        $lines[] = [
            'category_id' => $default_cat ? $default_cat['id'] : '',
            'label' => $default_cat ? $default_cat['label'] : __('Adulto', 'opencomune'),
            'quantity' => 1,
            'unit_price' => $unit_price,
            'line_total' => $unit_price,
        ];
        $total = $unit_price;
    }

    return [
        'currency' => 'EUR',
        'lines' => $lines,
        'total' => round($total, 2),
        'participants' => $total_pax,
    ];
}

function opencomune_create_checkout_session(array $args) {
    opencomune_ensure_checkout_sessions_table();

    $experience_id = (int) ($args['experience_id'] ?? 0);
    $event_id = (int) ($args['event_id'] ?? 0);
    $date = sanitize_text_field($args['date'] ?? '');
    $time = sanitize_text_field($args['time'] ?? '');
    $participants = is_array($args['participants'] ?? null) ? $args['participants'] : [];

    if ($experience_id <= 0 || $event_id <= 0 || $date === '' || $time === '') {
        return new WP_Error('invalid_session', __('Dati prenotazione incompleti.', 'opencomune'));
    }

    $channel = opencomune_get_esperienza_booking_channel_settings($experience_id);
    if (!$channel['direct_booking_enabled']) {
        return new WP_Error('booking_disabled', __('Prenotazione diretta non attiva per questa esperienza.', 'opencomune'));
    }

    $totals = opencomune_calculate_checkout_line_items($experience_id, $participants);
    $token = wp_generate_password(32, false, false);

    $payload = [
        'step' => 'contact',
        'experience_id' => $experience_id,
        'event_id' => $event_id,
        'date' => $date,
        'time' => $time,
        'participants' => $participants,
        'totals' => $totals,
        'contact' => [],
        'travelers' => [],
        'payment_mode' => $channel['payment_mode'],
        'consents' => [],
    ];

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $expires = gmdate('Y-m-d H:i:s', time() + HOUR_IN_SECONDS);

    $inserted = $wpdb->insert(
        $table,
        [
            'session_token' => $token,
            'experience_id' => $experience_id,
            'payload' => wp_json_encode($payload),
            'status' => 'open',
            'expires_at' => $expires,
        ],
        ['%s', '%d', '%s', '%s', '%s']
    );

    if (!$inserted) {
        return new WP_Error('db_error', __('Impossibile avviare il checkout.', 'opencomune'));
    }

    return [
        'session_token' => $token,
        'checkout_url' => opencomune_get_checkout_session_url($token),
        'payload' => $payload,
    ];
}

function opencomune_get_checkout_session($token) {
    opencomune_ensure_checkout_sessions_table();
    global $wpdb;

    $token = sanitize_text_field($token);
    if ($token === '') {
        return null;
    }

    $table = opencomune_checkout_sessions_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
        $token
    ));

    if (!$row) {
        return null;
    }

    if ($row->status === 'cancelled' || $row->status === 'completed') {
        return null;
    }

    if (strtotime($row->expires_at) < time()) {
        return null;
    }

    $payload = json_decode($row->payload, true);
    if (!is_array($payload)) {
        $payload = [];
    }

    return [
        'id' => (int) $row->id,
        'session_token' => $row->session_token,
        'experience_id' => (int) $row->experience_id,
        'status' => $row->status,
        'booking_id' => $row->booking_id ? (int) $row->booking_id : null,
        'expires_at' => $row->expires_at,
        'payload' => $payload,
    ];
}

function opencomune_update_checkout_session($token, array $patch) {
    $session = opencomune_get_checkout_session($token);
    if (!$session || $session['status'] !== 'open') {
        return new WP_Error('session_closed', __('Sessione checkout non valida.', 'opencomune'));
    }

    $payload = $session['payload'];
    $allowed = ['step', 'contact', 'travelers', 'consents'];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $patch)) {
            $payload[$key] = $patch[$key];
        }
    }

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $wpdb->update(
        $table,
        ['payload' => wp_json_encode($payload)],
        ['session_token' => sanitize_text_field($token)],
        ['%s'],
        ['%s']
    );

    $session['payload'] = $payload;

    return $session;
}

function opencomune_cancel_checkout_session($token) {
    opencomune_ensure_checkout_sessions_table();
    global $wpdb;

    $token = sanitize_text_field($token);
    if ($token === '') {
        return new WP_Error('not_found', __('Sessione non trovata.', 'opencomune'), ['status' => 404]);
    }

    $table = opencomune_checkout_sessions_table_name();
    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table} WHERE session_token = %s LIMIT 1",
        $token
    ));

    if (!$row) {
        return new WP_Error('not_found', __('Sessione non trovata.', 'opencomune'), ['status' => 404]);
    }

    $experience_id = (int) $row->experience_id;
    $post = get_post($experience_id);
    $redirect_url = $post ? get_permalink($post) : home_url('/');

    if ($row->status === 'open') {
        $wpdb->update(
            $table,
            ['status' => 'cancelled'],
            ['session_token' => $token],
            ['%s'],
            ['%s']
        );
    }

    return [
        'cancelled' => true,
        'experience_id' => $experience_id,
        'redirect_url' => $redirect_url,
    ];
}

function opencomune_finalize_checkout_session_booking($token) {
    $session = opencomune_get_checkout_session($token);
    if (!$session) {
        return new WP_Error('session_closed', __('Sessione checkout non valida o scaduta.', 'opencomune'), ['status' => 404]);
    }

    if (!in_array($session['status'], ['open', 'awaiting_payment'], true)) {
        return new WP_Error('session_closed', __('Sessione checkout non valida.', 'opencomune'), ['status' => 400]);
    }

    $payload = $session['payload'];
    $experience_id = (int) $session['experience_id'];

    if ($session['status'] === 'open' && opencomune_experience_requires_booking_approval($experience_id)) {
        return opencomune_finalize_checkout_session_booking_request($token);
    }

    $checkout_total = (float) ($payload['totals']['total'] ?? 0);
    $requires_online = opencomune_experience_requires_online_payment($experience_id, $checkout_total);

    if ($session['status'] === 'awaiting_payment') {
        $booking_id = (int) ($session['booking_id'] ?? 0);
        if ($booking_id <= 0 && !empty($payload['booking_id'])) {
            $booking_id = (int) $payload['booking_id'];
        }
        if ($booking_id <= 0) {
            return new WP_Error('session_closed', __('Sessione checkout non valida.', 'opencomune'), ['status' => 400]);
        }

        return [
            'booking_id' => $booking_id,
            'status' => $requires_online ? 'pending_payment' : 'confermata',
            'requires_payment' => $requires_online,
            'total' => $checkout_total,
            'already_confirmed' => true,
        ];
    }

    $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
    $event_id = (int) ($payload['event_id'] ?? 0);
    $participants = (int) ($payload['totals']['participants'] ?? 1);

    $nome = sanitize_text_field($contact['first_name'] ?? '');
    $cognome = sanitize_text_field($contact['last_name'] ?? '');
    $email = sanitize_email($contact['email'] ?? '');
    $telefono = sanitize_text_field($contact['phone'] ?? '');
    if ($telefono === '') {
        $phone_local = sanitize_text_field($contact['phone_local'] ?? '');
        if ($phone_local !== '') {
            $phone_prefix = sanitize_text_field($contact['phone_prefix'] ?? '+39');
            $telefono = trim($phone_prefix . ' ' . $phone_local);
        }
    }
    $note = sanitize_textarea_field($contact['notes'] ?? '');

    if ($nome === '' || $cognome === '' || $email === '' || $telefono === '') {
        return new WP_Error('contact_required', __('Compila i dati del contatto principale, incluso il telefono.', 'opencomune'), ['status' => 400]);
    }

    $status = $requires_online ? 'pending_payment' : 'confermata';
    $metodo = $requires_online ? 'online' : ($checkout_total <= 0 ? 'gratuito' : 'guida');

    $slot = opencomune_resolve_booking_event($experience_id, $event_id, $participants);
    if (is_wp_error($slot)) {
        return $slot;
    }

    $booking_id = opencomune_insert_tour_prenotazione_row([
        'tour_id' => $experience_id,
        'event_id' => $event_id,
        'booking_date' => $slot['booking_date'],
        'booking_time' => $slot['booking_time'],
        'rule_id' => $slot['rule_id'],
        'nome' => trim($nome . ' ' . $cognome),
        'email' => $email,
        'telefono' => $telefono,
        'note' => $note,
        'participants' => $participants,
        'metodo_pagamento' => $metodo,
        'status' => $status,
    ]);

    if (!$booking_id) {
        return new WP_Error('booking_failed', __('Errore nel salvataggio della prenotazione.', 'opencomune'), ['status' => 500]);
    }

    if (!$requires_online) {
        opencomune_after_booking_decrement_legacy($experience_id, $event_id, $participants, $slot);
    }

    global $wpdb;
    $table = opencomune_checkout_sessions_table_name();
    $wpdb->update(
        $table,
        [
            'booking_id' => $booking_id,
            'status' => $requires_online ? 'awaiting_payment' : 'completed',
            'payload' => wp_json_encode(array_merge($payload, ['booking_id' => $booking_id])),
        ],
        ['session_token' => sanitize_text_field($token)],
        ['%d', '%s', '%s'],
        ['%s']
    );

    return [
        'booking_id' => $booking_id,
        'status' => $status,
        'requires_payment' => $requires_online,
        'total' => (float) ($payload['totals']['total'] ?? 0),
    ];
}

function opencomune_register_checkout_rest_routes() {
    register_rest_route('opencomune/v1', '/checkout/sessions', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'opencomune_rest_create_checkout_session',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('opencomune/v1', '/checkout/sessions/(?P<token>[a-zA-Z0-9]+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'opencomune_rest_get_checkout_session',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'opencomune_rest_patch_checkout_session',
            'permission_callback' => '__return_true',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'opencomune_rest_cancel_checkout_session',
            'permission_callback' => '__return_true',
        ],
    ]);

    register_rest_route('opencomune/v1', '/checkout/sessions/(?P<token>[a-zA-Z0-9]+)/confirm', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'opencomune_rest_confirm_checkout_session',
        'permission_callback' => '__return_true',
    ]);
}
add_action('rest_api_init', 'opencomune_register_checkout_rest_routes');

function opencomune_rest_create_checkout_session(WP_REST_Request $request) {
    $body = $request->get_json_params();
    if (!is_array($body)) {
        $body = [];
    }

    $result = opencomune_create_checkout_session([
        'experience_id' => (int) ($body['experience_id'] ?? 0),
        'event_id' => (int) ($body['event_id'] ?? 0),
        'date' => $body['date'] ?? '',
        'time' => $body['time'] ?? '',
        'participants' => $body['participants'] ?? [],
    ]);

    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

function opencomune_rest_get_checkout_session(WP_REST_Request $request) {
    $session = opencomune_get_checkout_session($request['token']);
    if (!$session) {
        return new WP_Error('not_found', __('Sessione non trovata.', 'opencomune'), ['status' => 404]);
    }

    $post = get_post($session['experience_id']);
    $session['experience'] = [
        'id' => $session['experience_id'],
        'title' => $post ? $post->post_title : '',
        'permalink' => $post ? get_permalink($post) : '',
        'booking_channel' => opencomune_format_booking_channel_for_api($session['experience_id']),
        'pricing_categories' => opencomune_format_pricing_categories_for_api($session['experience_id']),
        'pricing' => opencomune_format_esperienza_price_api_payload($session['experience_id']),
    ];

    return rest_ensure_response($session);
}

function opencomune_rest_cancel_checkout_session(WP_REST_Request $request) {
    $result = opencomune_cancel_checkout_session($request['token']);
    if (is_wp_error($result)) {
        return $result;
    }

    return rest_ensure_response($result);
}

function opencomune_rest_patch_checkout_session(WP_REST_Request $request) {
    $body = $request->get_json_params();
    if (!is_array($body)) {
        $body = [];
    }

    $session = opencomune_update_checkout_session($request['token'], $body);
    if (is_wp_error($session)) {
        return $session;
    }

    return rest_ensure_response($session);
}

function opencomune_rest_confirm_checkout_session(WP_REST_Request $request) {
    $result = opencomune_finalize_checkout_session_booking($request['token']);
    if (is_wp_error($result)) {
        return $result;
    }

    if (!empty($result['requires_payment'])) {
        $session = opencomune_get_checkout_session($request['token']);
        $gateway_config = opencomune_resolve_payment_gateway_config_for_experience($session['experience_id']);
        $result['payment'] = [
            'gateway' => $gateway_config['gateway'],
            'source' => $gateway_config['source'],
        ];
    }

    return rest_ensure_response($result);
}

add_filter('rest_pre_serve_request', static function ($served, $result, $request, $server) {
    if (strpos($request->get_route(), '/opencomune/v1/checkout/') === 0) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-OpenComune-API-Key');
    }

    return $served;
}, 11, 4);
