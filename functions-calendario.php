<?php
// === CREAZIONE TABELLA CALENDARIO TOUR ===
function opencomune_create_calendario_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        tour_id BIGINT UNSIGNED NOT NULL,
        data DATE NOT NULL,
        ora TIME NOT NULL,
        posti_disponibili INT DEFAULT 0,
        note VARCHAR(255) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (tour_id),
        INDEX (data)
    ) $charset_collate;";
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Log della creazione della tabella
    error_log('Tabella calendario creata/aggiornata: ' . $table);
}
// Esegui solo una volta, poi commenta o rimuovi
// add_action('after_switch_theme', 'opencomune_create_calendario_table');

// === AGGIUNGI/AGGIORNA DATA ===
add_action('wp_ajax_opencomune_add_calendario', function() {
    error_log('AJAX calendario chiamato: ' . print_r($_POST, true));
    if (!is_user_logged_in()) {
        error_log('Non loggato');
        wp_send_json_error(['message' => 'Non autorizzato']);
    }
    $tour_id = intval($_POST['tour_id'] ?? 0);
    $data = sanitize_text_field($_POST['data'] ?? '');
    $ora = sanitize_text_field($_POST['ora'] ?? '');
    $posti = intval($_POST['posti'] ?? 0);
    $note = sanitize_text_field($_POST['note'] ?? '');
    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    // Controllo autore
    $post = get_post($tour_id);
    if (!$post || $post->post_type !== 'esperienze' || (int)$post->post_author !== get_current_user_id()) {
        error_log('Controllo autore fallito: post_id=' . $tour_id . ' user=' . get_current_user_id());
        wp_send_json_error(['message' => 'Non autorizzato']);
    }
    $wpdb->insert($table, [
        'tour_id' => $tour_id,
        'data' => $data,
        'ora' => $ora,
        'posti_disponibili' => $posti,
        'note' => $note,
    ]);
    error_log('Inserimento completato per tour_id=' . $tour_id . ' data=' . $data . ' ora=' . $ora);
    wp_send_json_success();
});

// === RECUPERA DATE DI UN TOUR ===
add_action('wp_ajax_opencomune_get_calendario', function() {
    $tour_id = intval($_GET['tour_id'] ?? 0);
    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, data, ora, posti_disponibili, note FROM $table WHERE tour_id = %d ORDER BY data, ora",
        $tour_id
    ));
    $events = [];
    foreach ($results as $row) {
        $events[] = [
            'id' => $row->id,
            'title' => ($row->note ? $row->note : 'Disponibile') . ($row->posti_disponibili ? ' (' . $row->posti_disponibili . ' posti)' : ''),
            'start' => $row->data . 'T' . $row->ora,
        ];
    }
    wp_send_json($events);
});

// === CANCELLA UNA DATA ===
add_action('wp_ajax_opencomune_delete_calendario', function() {
    if (!is_user_logged_in()) wp_send_json_error(['message' => 'Non autorizzato']);
    $id = intval($_POST['id'] ?? 0);
    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    // Controllo autore
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    if (!$row) wp_send_json_error(['message' => 'Non trovato']);
    $post = get_post($row->tour_id);
    if (!$post || $post->post_type !== 'esperienze' || (int)$post->post_author !== get_current_user_id()) {
        wp_send_json_error(['message' => 'Non autorizzato']);
    }
    $wpdb->delete($table, ['id' => $id]);
    wp_send_json_success();
});

add_action('wp_ajax_nopriv_opencomune_get_calendario', function() {
    $tour_id = intval($_GET['tour_id'] ?? 0);
    global $wpdb;
    $table = $wpdb->prefix . 'tour_calendari';
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT id, data, ora, posti_disponibili, note FROM $table WHERE tour_id = %d ORDER BY data, ora",
        $tour_id
    ));
    $events = [];
    foreach ($results as $row) {
        $events[] = [
            'id' => $row->id,
            'title' => ($row->note ? $row->note : 'Disponibile') . ($row->posti_disponibili ? ' (' . $row->posti_disponibili . ' posti)' : ''),
            'start' => $row->data . 'T' . $row->ora,
        ];
    }
    wp_send_json($events);
}); 