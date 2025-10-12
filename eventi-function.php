<?php

add_shortcode('inserisci_evento_guida', 'mgl_form_inserimento_evento_guida');
function mgl_form_inserimento_evento_guida() {
    if (!is_user_logged_in() || !(current_user_can('guida') || current_user_can('guida_turistica'))) {
        return '<div class="bg-yellow-100 text-yellow-800 p-4 rounded">Devi essere loggato come guida per inserire un evento.</div>';
    }

    $user_id = get_current_user_id();
    $guida_post_id = get_user_meta($user_id, 'guida_post_id', true);

    // Gestione invio form
    $success = false;
    $errors = [];
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['titolo_evento'])) {
        $titolo = sanitize_text_field($_POST['titolo_evento']);
        $descrizione = sanitize_textarea_field($_POST['descrizione_evento']);
        $citta = sanitize_text_field($_POST['citta_evento']);
        $categoria = sanitize_text_field($_POST['categoria_evento']);
        $prezzo = floatval($_POST['prezzo_evento']);
        $lingue = array_map('sanitize_text_field', $_POST['lingue_disponibili'] ?? []);
        $data_evento = sanitize_text_field($_POST['data_evento']);
        $durata = sanitize_text_field($_POST['durata_evento']);

        // Validazione base
        if (empty($titolo) || empty($descrizione) || empty($citta) || empty($categoria) || empty($prezzo) || empty($data_evento)) {
            $errors[] = 'Tutti i campi obbligatori devono essere compilati.';
        }

        // Upload foto principale
        $foto_url = '';
        if (!empty($_FILES['foto_principale']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/media.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $uploaded = media_handle_upload('foto_principale', 0);
            if (!is_wp_error($uploaded)) {
                $foto_url = wp_get_attachment_url($uploaded);
            }
        }

        if (empty($errors)) {
            // Crea il post evento
            $evento_id = wp_insert_post([
                'post_type' => 'esperienze',
                'post_title' => $titolo,
                'post_content' => $descrizione,
                'post_status' => 'pending',
                'post_author' => $user_id,
            ]);
            
            if ($evento_id) {
                // Prepara tutti i meta in un array
                $meta_updates = array(
                    'citta_evento' => $citta,
                    'categoria_evento' => $categoria,
                    'prezzo_evento' => $prezzo,
                    'lingue_disponibili' => $lingue,
                    'data_evento' => $data_evento,
                    'durata_evento' => $durata,
                    'foto_principale' => $foto_url,
                    'guida_post_id' => $guida_post_id
                );
                
                // Esegui un'unica query per aggiornare tutti i meta
                global $wpdb;
                $values = array();
                $placeholders = array();
                
                foreach ($meta_updates as $meta_key => $meta_value) {
                    if (is_array($meta_value)) {
                        $meta_value = maybe_serialize($meta_value);
                    }
                    $values[] = $evento_id;
                    $values[] = $meta_key;
                    $values[] = $meta_value;
                    $placeholders[] = "(%d, %s, %s)";
                }
                
                // Esegui la query in batch
                $query = $wpdb->prepare(
                    "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . 
                    implode(', ', $placeholders),
                    $values
                );
                
                $wpdb->query($query);

                // Setta la tassonomia città e categoria
                wp_set_object_terms($evento_id, $citta, 'citta_evento');
                wp_set_object_terms($evento_id, $categoria, 'categoria_evento');

                $success = true;
            } else {
                $errors[] = 'Errore nella creazione dell\'evento.';
            }
        }
    }

    ob_start();
    if ($success) {
        echo '<div class="bg-green-100 text-green-800 p-4 rounded mb-4">Evento inserito correttamente! Sarà pubblicato dopo la revisione.</div>';
    }
    if (!empty($errors)) {
        echo '<div class="bg-red-100 text-red-800 p-4 rounded mb-4">';
        foreach ($errors as $err) echo '<div>' . esc_html($err) . '</div>';
        echo '</div>';
    }
    ?>
    <form method="post" enctype="multipart/form-data" class="bg-white rounded-xl shadow p-6 max-w-xl mx-auto">
        <div class="mb-4">
            <label class="block font-medium mb-1">Titolo Evento *</label>
            <input type="text" name="titolo_evento" required class="w-full border rounded px-3 py-2" maxlength="80">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Descrizione *</label>
            <textarea name="descrizione_evento" required class="w-full border rounded px-3 py-2"></textarea>
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Città *</label>
            <input type="text" name="citta_evento" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Categoria *</label>
            <input type="text" name="categoria_evento" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Prezzo per persona (€) *</label>
            <input type="number" name="prezzo_evento" min="10" max="500" step="1" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Lingue disponibili *</label>
            <div class="flex flex-wrap gap-2">
                <label><input type="checkbox" name="lingue_disponibili[]" value="italiano"> Italiano</label>
                <label><input type="checkbox" name="lingue_disponibili[]" value="english"> English</label>
                <label><input type="checkbox" name="lingue_disponibili[]" value="francais"> Français</label>
                <label><input type="checkbox" name="lingue_disponibili[]" value="deutsch"> Deutsch</label>
                <label><input type="checkbox" name="lingue_disponibili[]" value="espanol"> Español</label>
            </div>
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Data evento *</label>
            <input type="date" name="data_evento" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Durata *</label>
            <input type="text" name="durata_evento" required class="w-full border rounded px-3 py-2" placeholder="Es: 2h, 3h, mezza giornata">
        </div>
        <div class="mb-4">
            <label class="block font-medium mb-1">Foto principale *</label>
            <input type="file" name="foto_principale" accept="image/*" required class="w-full border rounded px-3 py-2">
        </div>
        <div class="text-center">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded font-semibold hover:bg-blue-700">Inserisci Evento</button>
        </div>
    </form>
    <?php
    return ob_get_clean();
}
