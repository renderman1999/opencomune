<?php

function opencomune_partner_get_tipologie() {
    return [
        'ristorante' => 'Ristorante',
        'bb' => 'B&B',
        'agriturismo' => 'Agriturismo',
        'hotel' => 'Hotel',
        'tour_operator' => 'Tour Operator',
        'guida' => 'Guida Turistica',
        'museo' => 'Museo',
        'evento' => 'Organizzatore Eventi',
        'ente_territoriale' => 'Ente territoriale',
        'produttore' => 'Produttore Locale',
        'negozio' => 'Negozio',
        'altro' => 'Altro',
    ];
}

function opencomune_partner_get_status_labels() {
    return [
        'pending' => 'In attesa verifica',
        'approved' => 'Attivo',
        'rejected' => 'Rifiutato',
    ];
}

function opencomune_get_partner_cpt_post_id($tour_partner_id) {
    $posts = get_posts([
        'post_type' => 'partner',
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => 'tour_partner_id',
        'meta_value' => (int) $tour_partner_id,
        'suppress_filters' => true,
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function opencomune_map_partner_status_to_post_status($status) {
    switch ($status) {
        case 'approved':
            return 'publish';
        case 'rejected':
            return 'draft';
        default:
            return 'pending';
    }
}

function opencomune_map_post_status_to_partner_status($post_status) {
    switch ($post_status) {
        case 'publish':
            return 'approved';
        case 'draft':
        case 'trash':
            return 'rejected';
        default:
            return 'pending';
    }
}

function opencomune_get_partner_admin_user_id() {
    $admins = get_users([
        'role' => 'administrator',
        'number' => 1,
        'fields' => 'ID',
    ]);

    return $admins ? (int) $admins[0] : 1;
}

function opencomune_run_with_admin_user($callback) {
    $previous_user_id = get_current_user_id();
    wp_set_current_user(opencomune_get_partner_admin_user_id());

    try {
        return $callback();
    } finally {
        wp_set_current_user($previous_user_id);
    }
}

function opencomune_get_tour_partner_row($partner_id) {
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}tour_partners WHERE id = %d",
        (int) $partner_id
    ));
}

function opencomune_partner_update_post_meta_from_row($post_id, $partner) {
    $meta_map = [
        'tour_partner_id' => (int) $partner->id,
        'partner_status' => $partner->status,
        'partner_tipologia_attivita' => $partner->tipologia_attivita,
        'partner_partita_iva' => $partner->partita_iva,
        'partner_numero_rea' => $partner->numero_rea,
        'partner_comune' => $partner->comune,
        'partner_indirizzo' => $partner->indirizzo,
        'partner_cap' => $partner->cap,
        'partner_area_quartiere' => $partner->area_quartiere,
        'partner_latitudine' => $partner->latitudine,
        'partner_longitudine' => $partner->longitudine,
        'partner_nome_referente' => $partner->nome_referente,
        'partner_ruolo_referente' => $partner->ruolo_referente,
        'partner_telefono' => $partner->telefono_attivita,
        'partner_email_login' => $partner->email_login,
        'partner_email_ufficiale' => $partner->email_ufficiale,
        'partner_sito_web' => $partner->sito_web,
        'partner_social_media' => $partner->social_media,
        'partner_logo_url' => $partner->logo_url,
        'partner_note_admin' => $partner->note_admin,
        'partner_data_registrazione' => $partner->data_registrazione,
        'partner_data_approvazione' => $partner->data_approvazione,
        'partner_wp_user_id' => (int) $partner->wp_user_id,
    ];

    foreach ($meta_map as $key => $value) {
        update_post_meta($post_id, $key, $value ?? '');
    }
}

function opencomune_sync_partner_cpt($partner_id) {
    if (get_transient('opencomune_partner_syncing_from_post')) {
        return 0;
    }

    $partner = opencomune_get_tour_partner_row($partner_id);
    if (!$partner) {
        return 0;
    }

    return opencomune_run_with_admin_user(function() use ($partner) {
        $post_id = opencomune_get_partner_cpt_post_id($partner->id);
        $post_status = opencomune_map_partner_status_to_post_status($partner->status);

        $post_data = [
            'post_title' => $partner->nome_attivita,
            'post_content' => $partner->descrizione_estesa ?: '',
            'post_excerpt' => $partner->descrizione_breve ?: '',
            'post_type' => 'partner',
            'post_status' => $post_status,
            'post_author' => $partner->wp_user_id ? (int) $partner->wp_user_id : opencomune_get_partner_admin_user_id(),
        ];

        if ($post_id) {
            $post_data['ID'] = $post_id;
            $result = wp_update_post($post_data, true);
        } else {
            $result = wp_insert_post($post_data, true);
        }

        if (is_wp_error($result)) {
            error_log('OPENCOMUNE PARTNER CPT: sync failed for partner ID ' . $partner->id . ' - ' . $result->get_error_message());
            return 0;
        }

        $post_id = (int) $result;
        opencomune_partner_update_post_meta_from_row($post_id, $partner);
        opencomune_partner_apply_logo_assets(
            $post_id,
            $partner->logo_url ?? '',
            0,
            (int) ($partner->wp_user_id ?? 0)
        );

        return $post_id;
    });
}

function opencomune_sync_partner_post_to_table($post_id) {
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'partner') {
        return false;
    }

    $partner_id = (int) get_post_meta($post_id, 'tour_partner_id', true);
    if (!$partner_id) {
        return false;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tour_partners';
    $existing = opencomune_get_tour_partner_row($partner_id);
    if (!$existing) {
        return false;
    }

    set_transient('opencomune_partner_syncing_from_post', 1, 30);

    $post = get_post($post_id);
    $status = sanitize_text_field($_POST['partner_status'] ?? get_post_meta($post_id, 'partner_status', true));
    if (!array_key_exists($status, opencomune_partner_get_status_labels())) {
        $status = opencomune_map_post_status_to_partner_status($post->post_status);
    }

    $update_data = [
        'nome_attivita' => sanitize_text_field($post->post_title),
        'descrizione_breve' => sanitize_textarea_field($post->post_excerpt),
        'descrizione_estesa' => wp_kses_post($post->post_content),
        'tipologia_attivita' => sanitize_text_field($_POST['partner_tipologia_attivita'] ?? get_post_meta($post_id, 'partner_tipologia_attivita', true)),
        'partita_iva' => sanitize_text_field($_POST['partner_partita_iva'] ?? get_post_meta($post_id, 'partner_partita_iva', true)),
        'numero_rea' => sanitize_text_field($_POST['partner_numero_rea'] ?? get_post_meta($post_id, 'partner_numero_rea', true)),
        'indirizzo' => sanitize_text_field($_POST['partner_indirizzo'] ?? get_post_meta($post_id, 'partner_indirizzo', true)),
        'cap' => sanitize_text_field($_POST['partner_cap'] ?? get_post_meta($post_id, 'partner_cap', true)),
        'comune' => sanitize_text_field($_POST['partner_comune'] ?? get_post_meta($post_id, 'partner_comune', true)),
        'area_quartiere' => sanitize_text_field($_POST['partner_area_quartiere'] ?? get_post_meta($post_id, 'partner_area_quartiere', true)),
        'latitudine' => opencomune_partner_sanitize_coordinate($_POST['partner_latitudine'] ?? get_post_meta($post_id, 'partner_latitudine', true)),
        'longitudine' => opencomune_partner_sanitize_coordinate($_POST['partner_longitudine'] ?? get_post_meta($post_id, 'partner_longitudine', true)),
        'nome_referente' => sanitize_text_field($_POST['partner_nome_referente'] ?? get_post_meta($post_id, 'partner_nome_referente', true)),
        'ruolo_referente' => sanitize_text_field($_POST['partner_ruolo_referente'] ?? get_post_meta($post_id, 'partner_ruolo_referente', true)),
        'telefono_attivita' => sanitize_text_field($_POST['partner_telefono'] ?? get_post_meta($post_id, 'partner_telefono', true)),
        'email_ufficiale' => sanitize_email($_POST['partner_email_ufficiale'] ?? get_post_meta($post_id, 'partner_email_ufficiale', true)),
        'email_login' => sanitize_email($_POST['partner_email_login'] ?? get_post_meta($post_id, 'partner_email_login', true)),
        'sito_web' => esc_url_raw($_POST['partner_sito_web'] ?? get_post_meta($post_id, 'partner_sito_web', true)),
        'social_media' => esc_url_raw($_POST['partner_social_media'] ?? get_post_meta($post_id, 'partner_social_media', true)),
        'logo_url' => esc_url_raw($_POST['partner_logo_url'] ?? get_post_meta($post_id, 'partner_logo_url', true)),
        'note_admin' => sanitize_textarea_field($_POST['partner_note_admin'] ?? get_post_meta($post_id, 'partner_note_admin', true)),
        'status' => $status,
    ];

    if ($status === 'approved' && $existing->status !== 'approved') {
        $update_data['data_approvazione'] = current_time('mysql');
    } elseif ($status !== 'approved') {
        $update_data['data_approvazione'] = null;
    }

    $result = $wpdb->update($table, $update_data, ['id' => $partner_id]);
    if ($result === false) {
        delete_transient('opencomune_partner_syncing_from_post');
        return false;
    }

    $updated_partner = opencomune_get_tour_partner_row($partner_id);
    opencomune_partner_update_post_meta_from_row($post_id, $updated_partner);

    $logo_attachment_id = isset($_POST['partner_logo_attachment_id'])
        ? (int) $_POST['partner_logo_attachment_id']
        : (int) get_post_meta($post_id, 'partner_logo_attachment_id', true);
    opencomune_partner_apply_logo_assets(
        $post_id,
        $update_data['logo_url'],
        $logo_attachment_id,
        (int) ($existing->wp_user_id ?? 0)
    );

    if (!empty($existing->wp_user_id)) {
        $user_id = (int) $existing->wp_user_id;
        wp_update_user([
            'ID' => $user_id,
            'display_name' => $update_data['nome_attivita'],
            'first_name' => $update_data['nome_attivita'],
            'user_email' => $update_data['email_ufficiale'],
        ]);

        if ($status === 'approved') {
            update_user_meta($user_id, 'partner_email_verified', true);
            update_user_meta($user_id, 'partner_approved', true);
        } else {
            update_user_meta($user_id, 'partner_approved', false);
            if ($status === 'pending') {
                update_user_meta($user_id, 'partner_email_verified', false);
            }
        }
    }

    $new_post_status = opencomune_map_partner_status_to_post_status($status);
    if ($post->post_status !== $new_post_status) {
        $wpdb->update(
            $wpdb->posts,
            ['post_status' => $new_post_status],
            ['ID' => $post_id],
            ['%s'],
            ['%d']
        );
        clean_post_cache($post_id);
    }

    delete_transient('opencomune_partner_syncing_from_post');

    set_transient('opencomune_partner_saved_notice', 1, 30);

    return true;
}

function opencomune_partner_sanitize_coordinate($value) {
    if ($value === '' || $value === null) {
        return null;
    }

    return is_numeric($value) ? (float) $value : null;
}

function opencomune_get_partner_row_for_user($user_id) {
    $user_id = (int) $user_id;
    if ($user_id <= 0) {
        return null;
    }

    $stub = opencomune_get_partner_by_user_id($user_id);
    if (!$stub) {
        return null;
    }

    return opencomune_get_tour_partner_row($stub->id);
}

/**
 * Campi modificabili dal partner loggato (scheda pubblica / author).
 *
 * @return string[]
 */
function opencomune_partner_self_service_field_keys() {
    return [
        'nome_attivita',
        'tipologia_attivita',
        'partita_iva',
        'numero_rea',
        'descrizione_breve',
        'descrizione_estesa',
        'indirizzo',
        'cap',
        'comune',
        'area_quartiere',
        'latitudine',
        'longitudine',
        'nome_referente',
        'ruolo_referente',
        'telefono_attivita',
        'email_ufficiale',
        'email_login',
        'sito_web',
        'social_media',
        'logo_url',
    ];
}

/**
 * Aggiorna email e username WordPress (login) del partner.
 *
 * @return true|WP_Error
 */
function opencomune_partner_apply_login_email_to_wp_user($user_id, $email_login) {
    $user_id = (int) $user_id;
    $email_login = sanitize_email($email_login);
    if ($user_id <= 0 || $email_login === '' || !is_email($email_login)) {
        return new WP_Error('invalid_email', __('Email di accesso non valida.', 'opencomune'));
    }

    $user = get_userdata($user_id);
    if (!$user) {
        return new WP_Error('no_user', __('Utente non trovato.', 'opencomune'));
    }

    $existing_id = email_exists($email_login);
    if ($existing_id && (int) $existing_id !== $user_id) {
        return new WP_Error('email_taken', __('Questa email è già usata da un altro account.', 'opencomune'));
    }

    $login_taken = username_exists($email_login);
    if ($login_taken && (int) $login_taken !== $user_id) {
        return new WP_Error('email_taken', __('Questa email è già usata come username.', 'opencomune'));
    }

    if (strcasecmp($user->user_email, $email_login) === 0 && strcasecmp($user->user_login, $email_login) === 0) {
        return true;
    }

    global $wpdb;
    $result = $wpdb->update(
        $wpdb->users,
        [
            'user_email' => $email_login,
            'user_login' => $email_login,
        ],
        ['ID' => $user_id],
        ['%s', '%s'],
        ['%d']
    );

    if ($result === false) {
        return new WP_Error('db_error', __('Impossibile aggiornare l\'email di accesso.', 'opencomune'));
    }

    clean_user_cache($user_id);

    return true;
}

/**
 * @return true|WP_Error
 */
function opencomune_save_partner_self_service_profile($user_id, array $input) {
    $user_id = (int) $user_id;
    $current = get_current_user_id();
    if ($user_id <= 0 || ($current !== $user_id && !opencomune_user_can_manage_all_esperienze())) {
        return new WP_Error('forbidden', __('Accesso negato.', 'opencomune'));
    }

    $partner = opencomune_get_partner_row_for_user($user_id);
    if (!$partner) {
        return new WP_Error('no_partner', __('Profilo attività non trovato.', 'opencomune'));
    }

    $nome_attivita = sanitize_text_field($input['nome_attivita'] ?? $input['display_name'] ?? '');
    if ($nome_attivita === '') {
        return new WP_Error('required_name', __('Il nome attività è obbligatorio.', 'opencomune'));
    }

    $partita_iva = sanitize_text_field($input['partita_iva'] ?? '');
    if ($partita_iva === '') {
        return new WP_Error('required_vat', __('La partita IVA è obbligatoria.', 'opencomune'));
    }

    $email_ufficiale = sanitize_email($input['email_ufficiale'] ?? '');
    if ($email_ufficiale !== '' && !is_email($email_ufficiale)) {
        return new WP_Error('invalid_email', __('L\'email ufficiale non è valida.', 'opencomune'));
    }

    $tipologie = opencomune_partner_get_tipologie();
    $tipologia = sanitize_text_field($input['tipologia_attivita'] ?? '');
    if ($tipologia !== '' && !isset($tipologie[$tipologia])) {
        $tipologia = (string) $partner->tipologia_attivita;
    }

    $logo_url = esc_url_raw($input['logo_url'] ?? '');
    if ($logo_url === '') {
        $logo_url = (string) ($partner->logo_url ?? '');
    }
    $profile_image_id = (int) ($input['profile_image_id'] ?? 0);
    if ($profile_image_id > 0) {
        $attachment_url = wp_get_attachment_image_url($profile_image_id, 'full');
        if ($attachment_url) {
            $logo_url = $attachment_url;
        }
    } elseif ($profile_image_id === 0 && !empty($input['clear_profile_image'])) {
        $logo_url = '';
    }

    $email_login = sanitize_email($input['user_email'] ?? $input['email_login'] ?? '');
    if ($email_login === '' || !is_email($email_login)) {
        return new WP_Error('invalid_login_email', __('L\'email di accesso non è valida.', 'opencomune'));
    }

    global $wpdb;
    $table = $wpdb->prefix . 'tour_partners';
    $email_taken = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$table} WHERE email_login = %s AND id != %d LIMIT 1",
        $email_login,
        (int) $partner->id
    ));
    if ($email_taken) {
        return new WP_Error('email_taken', __('Questa email di accesso è già usata da un altro partner.', 'opencomune'));
    }

    $wp_email_result = opencomune_partner_apply_login_email_to_wp_user($user_id, $email_login);
    if (is_wp_error($wp_email_result)) {
        return $wp_email_result;
    }

    $update_data = [
        'nome_attivita' => $nome_attivita,
        'tipologia_attivita' => $tipologia,
        'partita_iva' => $partita_iva,
        'numero_rea' => sanitize_text_field($input['numero_rea'] ?? ''),
        'descrizione_breve' => sanitize_textarea_field($input['descrizione_breve'] ?? $input['user_bio'] ?? ''),
        'descrizione_estesa' => wp_kses_post($input['descrizione_estesa'] ?? ''),
        'indirizzo' => sanitize_text_field($input['indirizzo'] ?? ''),
        'cap' => sanitize_text_field($input['cap'] ?? ''),
        'comune' => sanitize_text_field($input['comune'] ?? ''),
        'area_quartiere' => sanitize_text_field($input['area_quartiere'] ?? ''),
        'latitudine' => opencomune_partner_sanitize_coordinate($input['latitudine'] ?? null),
        'longitudine' => opencomune_partner_sanitize_coordinate($input['longitudine'] ?? null),
        'nome_referente' => sanitize_text_field($input['nome_referente'] ?? ''),
        'ruolo_referente' => sanitize_text_field($input['ruolo_referente'] ?? ''),
        'telefono_attivita' => sanitize_text_field($input['telefono_attivita'] ?? $input['user_phone'] ?? ''),
        'email_ufficiale' => $email_ufficiale !== '' ? $email_ufficiale : $email_login,
        'email_login' => $email_login,
        'sito_web' => esc_url_raw($input['sito_web'] ?? ''),
        'social_media' => sanitize_text_field($input['social_media'] ?? ''),
        'logo_url' => esc_url_raw($logo_url),
    ];

    $updated = $wpdb->update($table, $update_data, ['id' => (int) $partner->id]);
    if ($updated === false) {
        return new WP_Error('db_error', __('Errore durante il salvataggio del profilo attività.', 'opencomune'));
    }

    opencomune_sync_partner_cpt((int) $partner->id);

    $user_update = [
        'ID' => $user_id,
        'display_name' => $nome_attivita,
        'first_name' => $nome_attivita,
        'last_name' => '',
    ];
    if (!empty($input['user_url'])) {
        $user_update['user_url'] = esc_url_raw($input['user_url']);
    }
    $wp_user_result = wp_update_user($user_update);
    if (is_wp_error($wp_user_result)) {
        return $wp_user_result;
    }

    update_user_meta($user_id, 'user_phone', $update_data['telefono_attivita']);
    update_user_meta($user_id, 'user_bio', $update_data['descrizione_breve']);
    if ($profile_image_id > 0) {
        update_user_meta($user_id, 'profile_image_id', $profile_image_id);
    } elseif (!empty($input['clear_profile_image'])) {
        delete_user_meta($user_id, 'profile_image_id');
    }

    return true;
}

function opencomune_partner_resolve_logo_attachment_id($logo_url, $attachment_id = 0) {
    $attachment_id = (int) $attachment_id;
    if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
        return $attachment_id;
    }

    $logo_url = esc_url_raw((string) $logo_url);
    if ($logo_url === '') {
        return 0;
    }

    return (int) attachment_url_to_postid($logo_url);
}

function opencomune_partner_apply_logo_assets($post_id, $logo_url, $attachment_id = 0, $user_id = 0) {
    $post_id = (int) $post_id;
    $user_id = (int) $user_id;
    $logo_url = esc_url_raw((string) $logo_url);
    $attachment_id = opencomune_partner_resolve_logo_attachment_id($logo_url, $attachment_id);

    update_post_meta($post_id, 'partner_logo_url', $logo_url);
    if ($attachment_id > 0) {
        update_post_meta($post_id, 'partner_logo_attachment_id', $attachment_id);
    } else {
        delete_post_meta($post_id, 'partner_logo_attachment_id');
    }

    if ($attachment_id > 0) {
        set_post_thumbnail($post_id, $attachment_id);
    } elseif ($logo_url === '') {
        delete_post_thumbnail($post_id);
    }

    if ($user_id > 0) {
        if ($attachment_id > 0) {
            update_user_meta($user_id, 'profile_image_id', $attachment_id);
        } elseif ($logo_url === '') {
            delete_user_meta($user_id, 'profile_image_id');
        }
    }
}

function opencomune_partner_render_field($field_id, $label, $type = 'text', $args = []) {
    $post_id = $args['post_id'] ?? 0;
    $value = $args['value'] ?? get_post_meta($post_id, $field_id, true);
    $required = !empty($args['required']) ? ' required' : '';
    $readonly = !empty($args['readonly']) ? ' readonly' : '';
    $description = $args['description'] ?? '';

    echo '<tr>';
    echo '<th scope="row"><label for="' . esc_attr($field_id) . '">' . esc_html($label) . '</label></th>';
    echo '<td>';

    if ($type === 'select') {
        echo '<select name="' . esc_attr($field_id) . '" id="' . esc_attr($field_id) . '" class="regular-text"' . $required . $readonly . '>';
        foreach (($args['options'] ?? []) as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($value, $option_value, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select>';
    } elseif ($type === 'textarea') {
        echo '<textarea name="' . esc_attr($field_id) . '" id="' . esc_attr($field_id) . '" class="large-text" rows="' . esc_attr((string) ($args['rows'] ?? 3)) . '"' . $required . $readonly . '>' . esc_textarea($value) . '</textarea>';
    } else {
        $input_type = in_array($type, ['text', 'email', 'url', 'number'], true) ? $type : 'text';
        echo '<input type="' . esc_attr($input_type) . '" name="' . esc_attr($field_id) . '" id="' . esc_attr($field_id) . '" value="' . esc_attr($value) . '" class="regular-text"' . $required . $readonly . '>';
    }

    if ($description !== '') {
        echo '<p class="description">' . esc_html($description) . '</p>';
    }

    echo '</td></tr>';
}

function opencomune_partner_register_meta_boxes() {
    if (!current_user_can('manage_options')) {
        return;
    }

    add_meta_box(
        'opencomune_partner_status',
        'Stato e amministrazione',
        'opencomune_partner_render_status_meta_box',
        'partner',
        'side',
        'high'
    );

    add_meta_box(
        'opencomune_partner_activity',
        'Dati attivita',
        'opencomune_partner_render_activity_meta_box',
        'partner',
        'normal',
        'default'
    );

    add_meta_box(
        'opencomune_partner_contacts',
        'Referente e contatti',
        'opencomune_partner_render_contacts_meta_box',
        'partner',
        'normal',
        'default'
    );

    add_meta_box(
        'opencomune_partner_location',
        'Localizzazione',
        'opencomune_partner_render_location_meta_box',
        'partner',
        'normal',
        'default'
    );

    add_meta_box(
        'opencomune_partner_web',
        'Web, social e logo',
        'opencomune_partner_render_web_meta_box',
        'partner',
        'normal',
        'default'
    );

    remove_meta_box('postimagediv', 'partner', 'side');
}
add_action('add_meta_boxes_partner', 'opencomune_partner_register_meta_boxes');

function opencomune_partner_render_meta_box_table_start($post) {
    echo '<table class="form-table"><tbody>';
    opencomune_partner_render_field('tour_partner_id_display', 'ID Partner (database)', 'text', [
        'post_id' => $post->ID,
        'value' => get_post_meta($post->ID, 'tour_partner_id', true),
        'readonly' => true,
    ]);
}

function opencomune_partner_render_save_nonce() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'partner') {
        return;
    }

    wp_nonce_field('opencomune_partner_meta', 'opencomune_partner_meta_nonce');
}
add_action('edit_form_after_title', 'opencomune_partner_render_save_nonce');

function opencomune_partner_render_meta_box_table_end() {
    echo '</tbody></table>';
}

function opencomune_partner_render_status_meta_box($post) {
    opencomune_partner_render_meta_box_table_start($post);
    opencomune_partner_render_field('partner_status', 'Stato', 'select', [
        'post_id' => $post->ID,
        'options' => opencomune_partner_get_status_labels(),
    ]);
    opencomune_partner_render_field('partner_data_registrazione', 'Data registrazione', 'text', [
        'post_id' => $post->ID,
        'readonly' => true,
    ]);
    opencomune_partner_render_field('partner_data_approvazione', 'Data approvazione', 'text', [
        'post_id' => $post->ID,
        'readonly' => true,
    ]);
    opencomune_partner_render_field('partner_wp_user_id', 'ID utente WordPress', 'text', [
        'post_id' => $post->ID,
        'readonly' => true,
    ]);
    opencomune_partner_render_field('partner_note_admin', 'Note amministrative', 'textarea', [
        'post_id' => $post->ID,
        'rows' => 4,
    ]);
    opencomune_partner_render_meta_box_table_end();
    echo '<p class="description">Titolo = nome attivita. Excerpt = descrizione breve. Contenuto = descrizione estesa.</p>';
}

function opencomune_partner_render_activity_meta_box($post) {
    opencomune_partner_render_meta_box_table_start($post);
    opencomune_partner_render_field('partner_tipologia_attivita', 'Tipologia attivita', 'select', [
        'post_id' => $post->ID,
        'options' => opencomune_partner_get_tipologie(),
        'required' => true,
    ]);
    opencomune_partner_render_field('partner_partita_iva', 'Partita IVA / Codice Fiscale', 'text', [
        'post_id' => $post->ID,
        'required' => true,
    ]);
    opencomune_partner_render_field('partner_numero_rea', 'Numero REA', 'text', [
        'post_id' => $post->ID,
    ]);
    opencomune_partner_render_meta_box_table_end();
}

function opencomune_partner_render_contacts_meta_box($post) {
    opencomune_partner_render_meta_box_table_start($post);
    opencomune_partner_render_field('partner_nome_referente', 'Nome referente', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_ruolo_referente', 'Ruolo referente', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_telefono', 'Telefono attivita', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_email_ufficiale', 'Email ufficiale', 'email', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_email_login', 'Email login', 'email', [
        'post_id' => $post->ID,
        'required' => true,
        'description' => 'Usata per accedere alla piattaforma.',
    ]);
    opencomune_partner_render_meta_box_table_end();
}

function opencomune_partner_render_location_meta_box($post) {
    opencomune_partner_render_meta_box_table_start($post);
    opencomune_partner_render_field('partner_indirizzo', 'Indirizzo', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_cap', 'CAP', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_comune', 'Comune', 'text', ['post_id' => $post->ID, 'required' => true]);
    opencomune_partner_render_field('partner_area_quartiere', 'Area / Quartiere', 'text', ['post_id' => $post->ID]);
    opencomune_partner_render_field('partner_latitudine', 'Latitudine', 'number', ['post_id' => $post->ID]);
    opencomune_partner_render_field('partner_longitudine', 'Longitudine', 'number', ['post_id' => $post->ID]);
    opencomune_partner_render_meta_box_table_end();
}

function opencomune_partner_render_web_meta_box($post) {
    $logo_url = get_post_meta($post->ID, 'partner_logo_url', true);
    $logo_attachment_id = (int) get_post_meta($post->ID, 'partner_logo_attachment_id', true);
    if ($logo_attachment_id <= 0 && $logo_url !== '') {
        $logo_attachment_id = opencomune_partner_resolve_logo_attachment_id($logo_url);
    }

    opencomune_partner_render_meta_box_table_start($post);
    opencomune_partner_render_field('partner_sito_web', 'Sito web', 'url', ['post_id' => $post->ID]);
    opencomune_partner_render_field('partner_social_media', 'Social media', 'url', ['post_id' => $post->ID]);
    echo '<tr><th scope="row"><label for="partner_logo_url">Logo</label></th><td>';
    echo '<input type="url" name="partner_logo_url" id="partner_logo_url" value="' . esc_attr($logo_url) . '" class="regular-text" placeholder="https://...">';
    echo '<input type="hidden" name="partner_logo_attachment_id" id="partner_logo_attachment_id" value="' . esc_attr((string) $logo_attachment_id) . '">';
    echo ' <button type="button" class="button opencomune-partner-logo-upload">Seleziona dalla libreria</button>';
    echo ' <button type="button" class="button opencomune-partner-logo-remove">Rimuovi logo</button>';
    echo '<p class="description">Il logo viene salvato nel database partner e sincronizzato anche sul profilo utente collegato.</p>';
    echo '<div id="partner_logo_preview_wrap" style="margin-top:10px;' . ($logo_url ? '' : ' display:none;') . '">';
    echo '<img id="partner_logo_preview" src="' . ($logo_url ? esc_url($logo_url) : '') . '" alt="" style="max-width:160px;max-height:120px;height:auto;border-radius:4px;border:1px solid #ccd0d4;padding:4px;background:#fff;">';
    echo '</div>';
    echo '</td></tr>';
    opencomune_partner_render_meta_box_table_end();
}

function opencomune_partner_save_meta_boxes($post_id, $post, $update = null) {
    static $is_running = false;

    if ($is_running || get_transient('opencomune_partner_syncing_from_post')) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!is_object($post)) {
        $post = get_post($post_id);
    }

    if (!$post || $post->post_type !== 'partner' || !current_user_can('manage_options')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['opencomune_partner_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_partner_meta_nonce'])), 'opencomune_partner_meta')) {
        return;
    }

    $is_running = true;

    $meta_sanitizers = [
        'partner_status' => 'sanitize_text_field',
        'partner_tipologia_attivita' => 'sanitize_text_field',
        'partner_partita_iva' => 'sanitize_text_field',
        'partner_numero_rea' => 'sanitize_text_field',
        'partner_indirizzo' => 'sanitize_text_field',
        'partner_cap' => 'sanitize_text_field',
        'partner_comune' => 'sanitize_text_field',
        'partner_area_quartiere' => 'sanitize_text_field',
        'partner_latitudine' => 'opencomune_partner_sanitize_coordinate',
        'partner_longitudine' => 'opencomune_partner_sanitize_coordinate',
        'partner_nome_referente' => 'sanitize_text_field',
        'partner_ruolo_referente' => 'sanitize_text_field',
        'partner_telefono' => 'sanitize_text_field',
        'partner_email_ufficiale' => 'sanitize_email',
        'partner_email_login' => 'sanitize_email',
        'partner_sito_web' => 'esc_url_raw',
        'partner_social_media' => 'esc_url_raw',
        'partner_note_admin' => 'sanitize_textarea_field',
    ];

    foreach ($meta_sanitizers as $field => $sanitizer) {
        if (isset($_POST[$field])) {
            $value = call_user_func($sanitizer, wp_unslash($_POST[$field]));
            update_post_meta($post_id, $field, $value);
        }
    }

    if (array_key_exists('partner_logo_url', $_POST)) {
        $logo_url = esc_url_raw(wp_unslash($_POST['partner_logo_url']));
        $logo_attachment_id = isset($_POST['partner_logo_attachment_id'])
            ? (int) $_POST['partner_logo_attachment_id']
            : 0;
        $partner_user_id = (int) get_post_meta($post_id, 'partner_wp_user_id', true);
        opencomune_partner_apply_logo_assets($post_id, $logo_url, $logo_attachment_id, $partner_user_id);
    }

    opencomune_sync_partner_post_to_table($post_id);

    $is_running = false;
}
add_action('save_post_partner', 'opencomune_partner_save_meta_boxes', 99, 3);

function opencomune_partner_admin_enqueue_scripts($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== 'partner') {
        return;
    }

    wp_enqueue_media();
    wp_add_inline_script('jquery', "
        jQuery(function($) {
            function opencomunePartnerUpdateLogoPreview(url) {
                var \$wrap = $('#partner_logo_preview_wrap');
                var \$img = $('#partner_logo_preview');
                if (!url) {
                    \$wrap.hide();
                    \$img.attr('src', '');
                    return;
                }
                var cacheBust = url.indexOf('?') === -1 ? '?' : '&';
                \$img.attr('src', url + cacheBust + 'v=' + Date.now());
                \$wrap.show();
            }

            $('#partner_logo_url').on('change input', function() {
                opencomunePartnerUpdateLogoPreview($(this).val());
            });

            $('.opencomune-partner-logo-upload').on('click', function(e) {
                e.preventDefault();
                var frame = wp.media({
                    title: 'Seleziona logo partner',
                    button: { text: 'Usa questo logo' },
                    library: { type: 'image' },
                    multiple: false
                });
                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    var logoUrl = attachment.url || '';
                    $('#partner_logo_url').val(logoUrl).trigger('change');
                    $('#partner_logo_attachment_id').val(attachment.id || '');
                    opencomunePartnerUpdateLogoPreview(logoUrl);
                });
                frame.open();
            });

            $('.opencomune-partner-logo-remove').on('click', function(e) {
                e.preventDefault();
                $('#partner_logo_url').val('').trigger('change');
                $('#partner_logo_attachment_id').val('');
                opencomunePartnerUpdateLogoPreview('');
            });
        });
    ");
}
add_action('admin_enqueue_scripts', 'opencomune_partner_admin_enqueue_scripts');

function opencomune_sync_all_partners_to_cpt() {
    global $wpdb;

    $partner_ids = $wpdb->get_col("SELECT id FROM {$wpdb->prefix}tour_partners ORDER BY id ASC");
    foreach ($partner_ids as $partner_id) {
        opencomune_sync_partner_cpt((int) $partner_id);
    }
}

function opencomune_delete_partner_cpt($partner_id) {
    $post_id = opencomune_get_partner_cpt_post_id($partner_id);
    if (!$post_id) {
        return;
    }

    opencomune_run_with_admin_user(function() use ($post_id) {
        wp_delete_post($post_id, true);
    });
}

function opencomune_partner_admin_columns($columns) {
    $new_columns = [];

    foreach ($columns as $key => $label) {
        $new_columns[$key] = $label;

        if ($key === 'title') {
            $new_columns['partner_tipologia'] = 'Tipologia';
            $new_columns['partner_comune'] = 'Comune';
            $new_columns['partner_email'] = 'Email';
            $new_columns['partner_status'] = 'Stato';
            $new_columns['partner_registered'] = 'Registrato il';
        }
    }

    return $new_columns;
}
add_filter('manage_partner_posts_columns', 'opencomune_partner_admin_columns');

function opencomune_partner_admin_column_content($column, $post_id) {
    switch ($column) {
        case 'partner_tipologia':
            echo esc_html(get_post_meta($post_id, 'partner_tipologia_attivita', true));
            break;
        case 'partner_comune':
            echo esc_html(get_post_meta($post_id, 'partner_comune', true));
            break;
        case 'partner_email':
            $email = get_post_meta($post_id, 'partner_email_login', true);
            if ($email) {
                echo '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>';
            }
            break;
        case 'partner_status':
            $status = get_post_meta($post_id, 'partner_status', true);
            $labels = opencomune_partner_get_status_labels();
            echo esc_html($labels[$status] ?? $status);
            break;
        case 'partner_registered':
            $date = get_post_meta($post_id, 'partner_data_registrazione', true);
            echo $date ? esc_html(date_i18n('d/m/Y H:i', strtotime($date))) : '-';
            break;
    }
}
add_action('manage_partner_posts_custom_column', 'opencomune_partner_admin_column_content', 10, 2);

function opencomune_partner_admin_row_actions($actions, $post) {
    if ($post->post_type !== 'partner') {
        return $actions;
    }

    $partner_id = (int) get_post_meta($post->ID, 'tour_partner_id', true);
    if ($partner_id) {
        $actions['opencomune_partner_detail'] = sprintf(
            '<a href="%s">Dettaglio frontend</a>',
            esc_url(add_query_arg('id', $partner_id, home_url('/dettaglio-partner/')))
        );
    }

    return $actions;
}
add_filter('post_row_actions', 'opencomune_partner_admin_row_actions', 10, 2);

function opencomune_partner_admin_sync_on_load() {
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->id !== 'edit-partner') {
        return;
    }

    opencomune_sync_all_partners_to_cpt();
}
add_action('current_screen', 'opencomune_partner_admin_sync_on_load');

function opencomune_partner_admin_notice() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || !in_array($screen->id, ['edit-partner', 'partner'], true)) {
        return;
    }

    if ($screen->id === 'edit-partner') {
        echo '<div class="notice notice-info"><p>I partner vengono registrati dal frontend e sincronizzati automaticamente in questo elenco.</p></div>';
        return;
    }

    if (get_transient('opencomune_partner_saved_notice')) {
        delete_transient('opencomune_partner_saved_notice');
        echo '<div class="notice notice-success is-dismissible"><p>Partner aggiornato e sincronizzato con successo.</p></div>';
    }

    echo '<div class="notice notice-info"><p>Modifica i campi del partner e salva: le modifiche vengono sincronizzate nel database partner e sull\'account utente collegato.</p></div>';
}
add_action('admin_notices', 'opencomune_partner_admin_notice');
