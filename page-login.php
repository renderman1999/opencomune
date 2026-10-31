<?php
$login_error = '';
$verify_error = '';
$show_first_access = isset($_GET['primo_accesso']) || isset($_GET['partner_verify']);

if (isset($_POST['log']) && isset($_POST['pwd']) && empty($_POST['confirmation_code'])) {
    if (function_exists('opencomune_verify_turnstile_response')) {
        $turnstile_token = sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'] ?? ''));
        $turnstile_check = opencomune_verify_turnstile_response($turnstile_token);
        if (is_wp_error($turnstile_check)) {
            $login_error = $turnstile_check->get_error_message();
        }
    }

    if ($login_error === '') {
        $user = wp_authenticate($_POST['log'], $_POST['pwd']);

        if (!is_wp_error($user)) {
            if (in_array('partner', $user->roles, true) && !opencomune_is_partner_email_verified($user->ID)) {
                $login_error = 'Account non ancora attivato. Clicca su "Primo accesso? Clicca qui" e inserisci il codice ricevuto via email.';
                $show_first_access = true;
            } else {
                wp_set_current_user($user->ID);
                wp_set_auth_cookie($user->ID, isset($_POST['rememberme']));

                if (in_array('administrator', $user->roles)) {
                    wp_redirect(opencomune_get_dashboard_url());
                } elseif (in_array('editor', $user->roles)) {
                    wp_redirect(admin_url());
                } elseif (in_array('partner', $user->roles)) {
                    wp_redirect(opencomune_get_dashboard_url());
                } else {
                    wp_redirect(home_url());
                }
                exit;
            }
        } else {
            $login_error = 'Credenziali non valide. Riprova.';
        }
    }
}

if (is_user_logged_in()) {
    $user = wp_get_current_user();
    if (in_array('administrator', $user->roles)) {
        wp_redirect(opencomune_get_dashboard_url());
    } elseif (in_array('editor', $user->roles)) {
        wp_redirect(admin_url());
    } elseif (in_array('partner', $user->roles)) {
        if (opencomune_is_partner_email_verified($user->ID)) {
            wp_redirect(opencomune_get_dashboard_url());
        } else {
            wp_logout();
            $show_first_access = true;
        }
    } else {
        wp_redirect(home_url());
    }
    exit;
}

get_header();

$login_hero_url = function_exists('opencomune_get_home_hero_image_url') ? opencomune_get_home_hero_image_url() : '';
$login_hero_style = $login_hero_url !== '' ? 'background-image:url(' . esc_url($login_hero_url) . ');' : '';
$site_name = get_bloginfo('name');

if (isset($_GET['login']) && $_GET['login'] === 'failed') {
    $login_error = 'Credenziali non valide. Riprova.';
}
?>

<div class="oc-public-login">
    <aside class="oc-public-login__hero" style="<?php echo esc_attr($login_hero_style); ?>">
        <div class="oc-public-login__hero-overlay" aria-hidden="true"></div>
        <div class="oc-public-login__hero-inner">
         
            <h1 class="oc-public-login__hero-title"><?php echo esc_html($site_name); ?></h1>
            <p class="oc-public-login__hero-lead">
                <?php esc_html_e('Valorizza proposte, eventi ed esperienze locali. Entra nell\'ecosistema digitale del territorio.', 'opencomune'); ?>
            </p>
            <ul class="oc-public-login__hero-list">
                <li>
                    <i class="bi bi-stars" aria-hidden="true"></i>
                    <?php esc_html_e('Proposte autentiche del territorio', 'opencomune'); ?>
                </li>
                <li>
                    <i class="bi bi-people" aria-hidden="true"></i>
                    <?php esc_html_e('Rete di enti e operatori locali', 'opencomune'); ?>
                </li>
                <li>
                    <i class="bi bi-geo-alt" aria-hidden="true"></i>
                    <?php esc_html_e('Marketing territoriale integrato', 'opencomune'); ?>
                </li>
            </ul>
        </div>
    </aside>

    <main class="oc-public-login__main">
        <div class="oc-public-login__card-wrap">
            <div class="oc-public-login__card">
                <p class="oc-public-login__mobile-payoff">
                    <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                    <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
                </p>

                <div id="login-panel" class="oc-public-login__panel<?php echo $show_first_access ? ' is-hidden' : ''; ?>">
                    <header class="oc-public-login__head">
                        <h2 class="oc-public-login__title"><?php esc_html_e('Accedi', 'opencomune'); ?></h2>
                        <p class="oc-public-login__subtitle"><?php esc_html_e('Usa le credenziali del tuo account partner o staff.', 'opencomune'); ?></p>
                    </header>

                    <?php if ($login_error !== '') : ?>
                        <div class="oc-public-login__alert oc-public-login__alert--error" role="alert">
                            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                            <span><?php echo esc_html($login_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form name="loginform" id="loginform" action="<?php echo esc_url(home_url('/login/')); ?>" method="post" class="oc-public-login__form">
                        <?php
                        $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : (isset($_GET['redirect']) ? $_GET['redirect'] : '');
                        if ($redirect_to) :
                            ?>
                            <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
                        <?php endif; ?>

                        <div class="oc-public-login__field">
                            <label class="oc-public-login__label" for="user_login"><?php esc_html_e('Username o email', 'opencomune'); ?></label>
                            <input type="text" name="log" id="user_login" class="oc-public-login__input" required autocomplete="username">
                        </div>

                        <div class="oc-public-login__field">
                            <label class="oc-public-login__label" for="user_pass"><?php esc_html_e('Password', 'opencomune'); ?></label>
                            <input type="password" name="pwd" id="user_pass" class="oc-public-login__input" required autocomplete="current-password">
                        </div>

                        <div class="oc-public-login__row">
                            <label class="oc-public-login__remember" for="rememberme">
                                <input type="checkbox" name="rememberme" id="rememberme" value="forever">
                                <?php esc_html_e('Ricordami', 'opencomune'); ?>
                            </label>
                            <a class="oc-public-login__link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                                <?php esc_html_e('Password dimenticata?', 'opencomune'); ?>
                            </a>
                        </div>

                        <?php
                        if (function_exists('opencomune_render_turnstile_widget')) {
                            echo opencomune_render_turnstile_widget('opencomune-login-turnstile oc-public-login__turnstile');
                        }
                        ?>

                        <button type="submit" name="wp-submit" class="oc-public-login__btn oc-public-login__btn--primary">
                            <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                            <?php esc_html_e('Accedi', 'opencomune'); ?>
                        </button>
                    </form>

                    <div class="oc-public-login__switch">
                        <button type="button" id="show-first-access-btn" class="oc-public-login__btn oc-public-login__btn--ghost">
                            <?php esc_html_e('Primo accesso? Attiva l\'account', 'opencomune'); ?>
                        </button>
                    </div>
                </div>

                <div id="first-access-panel" class="oc-public-login__panel<?php echo $show_first_access ? '' : ' is-hidden'; ?>">
                    <header class="oc-public-login__head">
                        <h2 class="oc-public-login__title"><?php esc_html_e('Primo accesso', 'opencomune'); ?></h2>
                        <p class="oc-public-login__subtitle">
                            <?php esc_html_e('Inserisci email, password e codice a 6 cifre ricevuto dopo la registrazione.', 'opencomune'); ?>
                        </p>
                    </header>

                    <?php if (isset($_GET['partner_verify']) && $_GET['partner_verify'] === '1') : ?>
                        <div class="oc-public-login__alert oc-public-login__alert--warn" role="status">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                            <span>
                                <strong><?php esc_html_e('Attivazione richiesta', 'opencomune'); ?></strong>
                                <?php esc_html_e('Inserisci il codice di conferma inviato via email.', 'opencomune'); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div id="verify-error" class="oc-public-login__alert oc-public-login__alert--error is-hidden" role="alert"></div>
                    <div id="verify-success" class="oc-public-login__alert oc-public-login__alert--success is-hidden" role="status"></div>

                    <form id="first-access-form" class="oc-public-login__form">
                        <div class="oc-public-login__field">
                            <label class="oc-public-login__label" for="first_access_login"><?php esc_html_e('Email di accesso', 'opencomune'); ?></label>
                            <input type="text" name="log" id="first_access_login" class="oc-public-login__input" required autocomplete="username">
                        </div>

                        <div class="oc-public-login__field">
                            <label class="oc-public-login__label" for="first_access_pass"><?php esc_html_e('Password', 'opencomune'); ?></label>
                            <input type="password" name="pwd" id="first_access_pass" class="oc-public-login__input" required autocomplete="current-password">
                        </div>

                        <div class="oc-public-login__field">
                            <label class="oc-public-login__label" for="confirmation_code"><?php esc_html_e('Codice di conferma', 'opencomune'); ?></label>
                            <input type="text" name="confirmation_code" id="confirmation_code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric"
                                   class="oc-public-login__input oc-public-login__input--code" placeholder="000000" required>
                        </div>

                        <?php if (function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured()) : ?>
                            <div id="first-access-turnstile-host" class="oc-public-login__turnstile" aria-live="polite"></div>
                        <?php endif; ?>

                        <button type="submit" id="first-access-submit" class="oc-public-login__btn oc-public-login__btn--primary">
                            <i class="bi bi-check-circle" aria-hidden="true"></i>
                            <?php esc_html_e('Attiva account e accedi', 'opencomune'); ?>
                        </button>
                    </form>

                    <div class="oc-public-login__switch">
                        <button type="button" id="show-login-btn" class="oc-public-login__btn oc-public-login__btn--ghost">
                            <?php esc_html_e('Account già attivo? Accedi', 'opencomune'); ?>
                        </button>
                    </div>
                </div>

                <footer class="oc-public-login__footer">
                    <a class="oc-public-login__link" href="<?php echo esc_url(home_url('/')); ?>">
                        <?php esc_html_e('Torna alla home', 'opencomune'); ?>
                    </a>
                </footer>
            </div>
        </div>
    </main>
</div>

<?php get_footer(); ?>
