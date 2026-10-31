<?php

add_shortcode('form_contatti', 'opencomune_form_contatti_shortcode');

function opencomune_contatti_page_has_form() {
    if (!is_singular()) {
        return is_page('contatti');
    }

    $post = get_post();
    if (!$post instanceof WP_Post) {
        return false;
    }

    if (has_shortcode($post->post_content, 'form_contatti')) {
        return true;
    }

    return is_page('contatti');
}

function opencomune_enqueue_contatti_form_assets() {
    if (!function_exists('opencomune_is_public_frontend_request') || !opencomune_is_public_frontend_request()) {
        return;
    }

    if (!opencomune_contatti_page_has_form()) {
        return;
    }

    $theme_dir = get_template_directory();
    $theme_uri = get_template_directory_uri();
    $contatti_css = $theme_dir . '/assets/css/public-contatti.css';
    if (is_readable($contatti_css)) {
        wp_enqueue_style(
            'opencomune-public-contatti',
            $theme_uri . '/assets/css/public-contatti.css',
            ['opencomune-public-theme'],
            (string) filemtime($contatti_css)
        );
    }

    if (function_exists('opencomune_enqueue_turnstile_script')) {
        opencomune_enqueue_turnstile_script();
    }
}
add_action('wp_enqueue_scripts', 'opencomune_enqueue_contatti_form_assets', 30);

function opencomune_get_contatti_admin_recipients() {
    $ufficio = opencomune_get_ufficio_info();
    $recipients = [];

    if (!empty($ufficio['email']) && is_email($ufficio['email'])) {
        $recipients[] = $ufficio['email'];
    }

    $admin_email = get_option('admin_email');
    if ($admin_email && is_email($admin_email)) {
        $recipients[] = $admin_email;
    }

    return array_values(array_unique($recipients));
}

function opencomune_build_contatti_admin_email($values, $page_url = '') {
    $site_name = get_bloginfo('name');
    $subject = sprintf('[%s] Nuova richiesta di contatto', $site_name);

    $message = "Gentile team,\n\n";
    $message .= "e' stata inviata una nuova richiesta di contatto dal sito " . $site_name . ".\n\n";
    $message .= "Dati del richiedente:\n";
    $message .= '- Nome: ' . $values['nome'] . "\n";
    $message .= '- Cognome: ' . $values['cognome'] . "\n";
    $message .= '- Telefono: ' . $values['telefono'] . "\n";
    $message .= '- Email: ' . $values['email'] . "\n\n";
    $message .= "Richiesta:\n" . $values['richiesta'] . "\n\n";
    $message .= 'Data invio: ' . current_time('d/m/Y H:i:s') . "\n";

    if ($page_url !== '') {
        $message .= 'Pagina: ' . $page_url . "\n";
    }

    $message .= "\nCordiali saluti,\n";
    $message .= 'Sistema automatico - ' . $site_name;

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $values['nome'] . ' ' . $values['cognome'] . ' <' . $values['email'] . '>',
    ];

    return [
        'subject' => $subject,
        'message' => $message,
        'headers' => $headers,
    ];
}

function opencomune_build_contatti_client_email($values) {
    $site_name = get_bloginfo('name');
    $ufficio = opencomune_get_ufficio_info();
    $subject = sprintf('Abbiamo ricevuto la tua richiesta - %s', $site_name);

    $message = 'Gentile ' . $values['nome'] . ' ' . $values['cognome'] . ",\n\n";
    $message .= 'grazie per averci contattato tramite il sito ' . $site_name . ".\n";
    $message .= "Abbiamo ricevuto la tua richiesta e la prenderemo in carico al piu presto.\n\n";
    $message .= "Riepilogo dei dati inviati:\n";
    $message .= '- Telefono: ' . $values['telefono'] . "\n";
    $message .= '- Email: ' . $values['email'] . "\n\n";
    $message .= "Richiesta:\n" . $values['richiesta'] . "\n\n";

    if (!empty($ufficio['telefono']) || !empty($ufficio['email'])) {
        $message .= "Per urgenze puoi contattarci:\n";
        if (!empty($ufficio['telefono'])) {
            $message .= '- Telefono: ' . $ufficio['telefono'] . "\n";
        }
        if (!empty($ufficio['email'])) {
            $message .= '- Email: ' . $ufficio['email'] . "\n";
        }
        $message .= "\n";
    }

    $message .= "Cordiali saluti,\n" . $site_name;

    return [
        'subject' => $subject,
        'message' => $message,
        'headers' => ['Content-Type: text/plain; charset=UTF-8'],
    ];
}

function opencomune_send_contatti_emails($values, $page_url = '') {
    $recipients = opencomune_get_contatti_admin_recipients();
    if (empty($recipients)) {
        return false;
    }

    $admin_email = opencomune_build_contatti_admin_email($values, $page_url);
    $admin_sent = false;

    foreach ($recipients as $recipient) {
        if (wp_mail($recipient, $admin_email['subject'], $admin_email['message'], $admin_email['headers'])) {
            $admin_sent = true;
        }
    }

    if (!$admin_sent) {
        return false;
    }

    $client_email = opencomune_build_contatti_client_email($values);
    wp_mail($values['email'], $client_email['subject'], $client_email['message'], $client_email['headers']);

    return true;
}

function opencomune_form_contatti_shortcode($atts) {
    $atts = shortcode_atts([
        'titolo' => 'Scrivici',
        'descrizione' => 'Compila il form per richiedere informazioni. Ti ricontatteremo al piu presto.',
    ], $atts, 'form_contatti');

    $success = false;
    $errors = [];
    $values = [
        'nome' => '',
        'cognome' => '',
        'telefono' => '',
        'email' => '',
        'richiesta' => '',
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['opencomune_contatti_submit'])) {
        if (!isset($_POST['opencomune_contatti_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['opencomune_contatti_nonce'])), 'opencomune_contatti_form')) {
            $errors[] = 'Sessione scaduta. Ricarica la pagina e riprova.';
        } elseif (!empty($_POST['opencomune_contatti_website'])) {
            $success = true;
        } else {
            $values['nome'] = sanitize_text_field(wp_unslash($_POST['contatti_nome'] ?? ''));
            $values['cognome'] = sanitize_text_field(wp_unslash($_POST['contatti_cognome'] ?? ''));
            $values['telefono'] = sanitize_text_field(wp_unslash($_POST['contatti_telefono'] ?? ''));
            $values['email'] = sanitize_email(wp_unslash($_POST['contatti_email'] ?? ''));
            $values['richiesta'] = sanitize_textarea_field(wp_unslash($_POST['contatti_richiesta'] ?? ''));

            if ($values['nome'] === '') {
                $errors[] = 'Il nome e obbligatorio.';
            }
            if ($values['cognome'] === '') {
                $errors[] = 'Il cognome e obbligatorio.';
            }
            if ($values['telefono'] === '') {
                $errors[] = 'Il telefono e obbligatorio.';
            }
            if ($values['email'] === '' || !is_email($values['email'])) {
                $errors[] = 'Inserisci un indirizzo email valido.';
            }
            if ($values['richiesta'] === '') {
                $errors[] = 'La richiesta e obbligatoria.';
            }

            if (empty($errors) && function_exists('opencomune_verify_turnstile_response')) {
                $turnstile_token = sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'] ?? ''));
                $turnstile_check = opencomune_verify_turnstile_response($turnstile_token);
                if (is_wp_error($turnstile_check)) {
                    $errors[] = $turnstile_check->get_error_message();
                }
            }

            if (empty($errors)) {
                $page_url = !empty($_POST['contatti_page_url'])
                    ? esc_url_raw(wp_unslash($_POST['contatti_page_url']))
                    : '';

                if (opencomune_send_contatti_emails($values, $page_url)) {
                    $success = true;
                    $values = [
                        'nome' => '',
                        'cognome' => '',
                        'telefono' => '',
                        'email' => '',
                        'richiesta' => '',
                    ];
                } else {
                    $errors[] = 'Si e verificato un errore durante l\'invio. Riprova piu tardi.';
                }
            }
        }
    }

    ob_start();
    ?>
    <div class="oc-public-contatti__shortcode">
        <div class="oc-public-contatti__card">
            <?php if ($atts['titolo'] !== '') : ?>
                <h2 class="oc-public-contatti__form-title"><?php echo esc_html($atts['titolo']); ?></h2>
            <?php endif; ?>

            <?php if ($atts['descrizione'] !== '') : ?>
                <p class="oc-public-contatti__lead"><?php echo esc_html($atts['descrizione']); ?></p>
            <?php endif; ?>

            <?php if ($success) : ?>
                <div class="oc-public-contatti__alert oc-public-contatti__alert--success" role="status">
                    <?php esc_html_e('Grazie! La tua richiesta e stata inviata correttamente. Ti abbiamo inviato una email di conferma e ti ricontatteremo al piu presto.', 'opencomune'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors)) : ?>
                <div class="oc-public-contatti__alert oc-public-contatti__alert--error" role="alert">
                    <ul>
                        <?php foreach ($errors as $error) : ?>
                            <li><?php echo esc_html($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!$success) : ?>
                <form method="post" action="<?php echo esc_url(get_permalink()); ?>#opencomune-contatti-form" id="opencomune-contatti-form" class="oc-public-contatti__fields">
                    <?php wp_nonce_field('opencomune_contatti_form', 'opencomune_contatti_nonce'); ?>
                    <input type="hidden" name="contatti_page_url" value="<?php echo esc_url(get_permalink()); ?>">

                    <div class="oc-public-contatti__hp" aria-hidden="true">
                        <label for="opencomune_contatti_website"><?php esc_html_e('Sito web', 'opencomune'); ?></label>
                        <input type="text" name="opencomune_contatti_website" id="opencomune_contatti_website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="oc-public-contatti__grid oc-public-contatti__grid--2">
                        <div>
                            <label for="contatti_nome" class="oc-public-contatti__label oc-public-contatti__label--required">
                                <?php esc_html_e('Nome', 'opencomune'); ?>
                            </label>
                            <input
                                type="text"
                                name="contatti_nome"
                                id="contatti_nome"
                                value="<?php echo esc_attr($values['nome']); ?>"
                                required
                                class="oc-public-contatti__input"
                                placeholder="<?php esc_attr_e('Il tuo nome', 'opencomune'); ?>"
                            >
                        </div>

                        <div>
                            <label for="contatti_cognome" class="oc-public-contatti__label oc-public-contatti__label--required">
                                <?php esc_html_e('Cognome', 'opencomune'); ?>
                            </label>
                            <input
                                type="text"
                                name="contatti_cognome"
                                id="contatti_cognome"
                                value="<?php echo esc_attr($values['cognome']); ?>"
                                required
                                class="oc-public-contatti__input"
                                placeholder="<?php esc_attr_e('Il tuo cognome', 'opencomune'); ?>"
                            >
                        </div>
                    </div>

                    <div>
                        <label for="contatti_telefono" class="oc-public-contatti__label oc-public-contatti__label--required">
                            <?php esc_html_e('Telefono', 'opencomune'); ?>
                        </label>
                        <input
                            type="tel"
                            name="contatti_telefono"
                            id="contatti_telefono"
                            value="<?php echo esc_attr($values['telefono']); ?>"
                            required
                            class="oc-public-contatti__input"
                            placeholder="+39 333 1234567"
                        >
                    </div>

                    <div>
                        <label for="contatti_email" class="oc-public-contatti__label oc-public-contatti__label--required">
                            <?php esc_html_e('Email', 'opencomune'); ?>
                        </label>
                        <input
                            type="email"
                            name="contatti_email"
                            id="contatti_email"
                            value="<?php echo esc_attr($values['email']); ?>"
                            required
                            class="oc-public-contatti__input"
                            placeholder="nome@esempio.it"
                        >
                    </div>

                    <div>
                        <label for="contatti_richiesta" class="oc-public-contatti__label oc-public-contatti__label--required">
                            <?php esc_html_e('Richiesta', 'opencomune'); ?>
                        </label>
                        <textarea
                            name="contatti_richiesta"
                            id="contatti_richiesta"
                            required
                            rows="5"
                            class="oc-public-contatti__textarea"
                            placeholder="<?php esc_attr_e('Descrivi la tua richiesta...', 'opencomune'); ?>"
                        ><?php echo esc_textarea($values['richiesta']); ?></textarea>
                    </div>

                    <?php
                    if (function_exists('opencomune_render_turnstile_widget')) {
                        echo opencomune_render_turnstile_widget('oc-public-contatti__turnstile');
                    }
                    ?>

                    <button type="submit" name="opencomune_contatti_submit" value="1" class="oc-public-contatti__submit">
                        <?php esc_html_e('Invia richiesta', 'opencomune'); ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
