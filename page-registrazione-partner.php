<?php
/**
 * Template Name: Registrazione Partner
 */

get_header();

$privacy_url = get_privacy_policy_url();
if (!is_string($privacy_url) || $privacy_url === '') {
    $privacy_url = home_url('/privacy-policy/');
}
$terms_url = home_url('/termini-e-condizioni/');

$reg_steps = [
    1 => ['label' => __('Dati partner', 'opencomune')],
    2 => ['label' => __('Localizzazione', 'opencomune')],
    3 => ['label' => __('Contatti', 'opencomune')],
    4 => ['label' => __('Accesso', 'opencomune')],
    5 => ['label' => __('Consensi', 'opencomune')],
];
?>

<div class="oc-partner-reg">
    <div class="oc-partner-reg__layout">
        <aside class="oc-partner-reg__aside" aria-label="<?php esc_attr_e('Avanzamento registrazione', 'opencomune'); ?>">
            <p class="oc-partner-reg__payoff">
                <?php esc_html_e('Piattaforma per', 'opencomune'); ?><br>
                <?php esc_html_e('il Marketing territoriale', 'opencomune'); ?>
            </p>
            <ol class="oc-partner-reg__steps">
                <?php foreach ($reg_steps as $num => $step) : ?>
                    <li class="oc-partner-reg__step<?php echo $num === 1 ? ' is-active' : ''; ?>" data-step="<?php echo (int) $num; ?>">
                        <span class="oc-partner-reg__step-marker"><?php echo (int) $num; ?></span>
                        <span class="oc-partner-reg__step-label"><?php echo esc_html($step['label']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <p class="oc-partner-reg__progress" id="partner-reg-progress">
                <?php echo esc_html('1 / 5 — ' . $reg_steps[1]['label']); ?>
            </p>
        </aside>

        <div class="oc-partner-reg__main">
            <header class="oc-partner-reg__main-header">
                <h1 class="oc-partner-reg__title"><?php esc_html_e('Registrazione partner', 'opencomune'); ?></h1>
                <p class="oc-partner-reg__lead">
                    <?php esc_html_e('Compila i dati della tua attività per entrare nel portale di marketing territoriale. Ci vogliono circa 2 minuti.', 'opencomune'); ?>
                </p>
            </header>

            <form id="partnerRegistrationForm" novalidate>
                <div class="oc-partner-reg__section" id="section-1">
                    <div class="oc-partner-reg__section-head">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <h2 class="oc-partner-reg__section-title"><?php esc_html_e('Dati del partner / attività', 'opencomune'); ?></h2>
                    </div>
                    <div class="oc-partner-reg__section-body">
                        <div class="oc-partner-reg__grid oc-partner-reg__grid--2">
                            <div class="oc-partner-reg__field oc-partner-reg__field--full">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="nome_attivita">
                                    <?php esc_html_e('Nome dell\'attività / ragione sociale', 'opencomune'); ?>
                                </label>
                                <input class="oc-partner-reg__input" type="text" id="nome_attivita" name="nome_attivita" required
                                       placeholder="<?php esc_attr_e('Es. Ristorante da Mario', 'opencomune'); ?>">
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="tipologia_attivita">
                                    <?php esc_html_e('Tipologia attività', 'opencomune'); ?>
                                </label>
                                <select class="oc-partner-reg__select" id="tipologia_attivita" name="tipologia_attivita" required>
                                    <option value=""><?php esc_html_e('Seleziona tipologia', 'opencomune'); ?></option>
                                    <?php foreach (opencomune_partner_get_tipologie() as $value => $label) : ?>
                                        <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="partita_iva">
                                    <?php esc_html_e('Partita IVA / codice fiscale', 'opencomune'); ?>
                                </label>
                                <input class="oc-partner-reg__input" type="text" id="partita_iva" name="partita_iva" required
                                       placeholder="IT12345678901">
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label" for="numero_rea">
                                    <?php esc_html_e('Numero REA / camera di commercio', 'opencomune'); ?>
                                </label>
                                <input class="oc-partner-reg__input" type="text" id="numero_rea" name="numero_rea"
                                       placeholder="REA-123456">
                            </div>
                            <div class="oc-partner-reg__field oc-partner-reg__field--full">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="descrizione_breve">
                                    <?php esc_html_e('Descrizione breve (max 200 caratteri)', 'opencomune'); ?>
                                </label>
                                <textarea class="oc-partner-reg__textarea" id="descrizione_breve" name="descrizione_breve" required maxlength="200" rows="3"
                                          placeholder="<?php esc_attr_e('Breve descrizione per elenchi pubblici...', 'opencomune'); ?>"></textarea>
                                <div class="oc-partner-reg__char-count"><span id="char-count-1">0</span>/200</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="oc-partner-reg__section" id="section-2" hidden>
                    <div class="oc-partner-reg__section-head">
                        <i class="bi bi-geo-alt" aria-hidden="true"></i>
                        <h2 class="oc-partner-reg__section-title"><?php esc_html_e('Dati di localizzazione', 'opencomune'); ?></h2>
                    </div>
                    <div class="oc-partner-reg__section-body">
                        <div class="oc-partner-reg__grid oc-partner-reg__grid--2">
                            <div class="oc-partner-reg__field oc-partner-reg__field--full">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="indirizzo">
                                    <?php esc_html_e('Indirizzo completo', 'opencomune'); ?>
                                </label>
                                <input class="oc-partner-reg__input" type="text" id="indirizzo" name="indirizzo" required autocomplete="off"
                                       data-use-full-address="1"
                                       placeholder="<?php esc_attr_e('Cerca via, numero civico, città, CAP...', 'opencomune'); ?>">
                                <p class="oc-partner-reg__hint">
                                    <?php esc_html_e('Seleziona un suggerimento per compilare automaticamente CAP e comune.', 'opencomune'); ?>
                                </p>
                                <p id="partner-indirizzo-geocode-status" class="oc-partner-reg__hint" style="display:none;" aria-live="polite"></p>
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="cap"><?php esc_html_e('CAP', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="text" id="cap" name="cap" required pattern="[0-9]{5}" readonly
                                       placeholder="<?php esc_attr_e('Compilato automaticamente', 'opencomune'); ?>">
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="comune"><?php esc_html_e('Comune', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="text" id="comune" name="comune" required readonly
                                       placeholder="<?php esc_attr_e('Compilato automaticamente', 'opencomune'); ?>">
                            </div>
                            <input type="hidden" id="latitudine" name="latitudine" value="">
                            <input type="hidden" id="longitudine" name="longitudine" value="">
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label" for="area_quartiere"><?php esc_html_e('Area o quartiere', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="text" id="area_quartiere" name="area_quartiere"
                                       placeholder="<?php esc_attr_e('Es. Centro storico', 'opencomune'); ?>">
                            </div>
                            <div class="oc-partner-reg__field oc-partner-reg__field--full">
                                <span class="oc-partner-reg__label"><?php esc_html_e('Mappa', 'opencomune'); ?></span>
                                <div class="oc-partner-reg__map" id="map"></div>
                                <p class="oc-partner-reg__hint">
                                    <?php esc_html_e('Il marker segue l\'indirizzo selezionato.', 'opencomune'); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="oc-partner-reg__section" id="section-3" hidden>
                    <div class="oc-partner-reg__section-head">
                        <i class="bi bi-telephone" aria-hidden="true"></i>
                        <h2 class="oc-partner-reg__section-title"><?php esc_html_e('Contatti', 'opencomune'); ?></h2>
                    </div>
                    <div class="oc-partner-reg__section-body">
                        <div class="oc-partner-reg__grid oc-partner-reg__grid--2">
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="nome_referente"><?php esc_html_e('Nome referente', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="text" id="nome_referente" name="nome_referente" required>
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="ruolo_referente"><?php esc_html_e('Ruolo / posizione', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="text" id="ruolo_referente" name="ruolo_referente" required>
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="telefono_attivita"><?php esc_html_e('Telefono attività', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="tel" id="telefono_attivita" name="telefono_attivita" required placeholder="+39 080 123 4567">
                            </div>
                            <div class="oc-partner-reg__field">
                                <label class="oc-partner-reg__label oc-partner-reg__label--required" for="email_ufficiale"><?php esc_html_e('Email ufficiale', 'opencomune'); ?></label>
                                <input class="oc-partner-reg__input" type="email" id="email_ufficiale" name="email_ufficiale" required placeholder="info@attivita.com">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="oc-partner-reg__section" id="section-4" hidden>
                    <div class="oc-partner-reg__section-head">
                        <i class="bi bi-key" aria-hidden="true"></i>
                        <h2 class="oc-partner-reg__section-title"><?php esc_html_e('Accesso al portale', 'opencomune'); ?></h2>
                    </div>
                    <div class="oc-partner-reg__section-body">
                        <div class="oc-partner-reg__notice">
                            <p>
                                <?php esc_html_e('Email di accesso:', 'opencomune'); ?>
                                <?php esc_html_e('sarà la stessa email ufficiale del passo Contatti', 'opencomune'); ?>
                                (<span id="access-email-preview" class="oc-partner-reg__mono">—</span>).
                            </p>
                            <p><?php esc_html_e('Riceverai via email una password sicura e un codice a 6 cifre per il primo accesso.', 'opencomune'); ?></p>
                            <p><?php esc_html_e('Controlla anche la cartella spam. Potrai aggiornare sito, social e logo dal profilo partner.', 'opencomune'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="oc-partner-reg__section" id="section-5" hidden>
                    <div class="oc-partner-reg__section-head">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <h2 class="oc-partner-reg__section-title"><?php esc_html_e('Consensi e dichiarazioni', 'opencomune'); ?></h2>
                    </div>
                    <div class="oc-partner-reg__section-body">
                        <div class="oc-partner-reg__consent-list">
                            <div class="oc-partner-reg__consent">
                                <input type="checkbox" id="termini_condizioni" name="termini_condizioni" required>
                                <label for="termini_condizioni">
                                    <?php esc_html_e('Accetto i termini e condizioni di adesione al portale.', 'opencomune'); ?>
                                    <a href="<?php echo esc_url($terms_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Leggi i termini', 'opencomune'); ?></a>
                                </label>
                            </div>
                            <div class="oc-partner-reg__consent">
                                <input type="checkbox" id="privacy_gdpr" name="privacy_gdpr" required>
                                <label for="privacy_gdpr">
                                    <?php esc_html_e('Autorizzo il trattamento dei dati personali (GDPR).', 'opencomune'); ?>
                                    <a href="<?php echo esc_url($privacy_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Leggi l\'informativa', 'opencomune'); ?></a>
                                </label>
                            </div>
                            <div class="oc-partner-reg__consent">
                                <input type="checkbox" id="pubblicazione_dati" name="pubblicazione_dati" required>
                                <label for="pubblicazione_dati">
                                    <?php esc_html_e('Acconsento alla pubblicazione di dati e immagini sul portale per la promozione delle attività.', 'opencomune'); ?>
                                </label>
                            </div>
                            <div class="oc-partner-reg__consent">
                                <input type="checkbox" id="dichiarazione_verita" name="dichiarazione_verita" required>
                                <label for="dichiarazione_verita">
                                    <?php esc_html_e('Dichiaro che le informazioni inserite sono veritiere e complete.', 'opencomune'); ?>
                                </label>
                            </div>
                        </div>

                        <?php if (function_exists('opencomune_is_turnstile_configured') && opencomune_is_turnstile_configured()) : ?>
                            <div class="oc-partner-reg__turnstile">
                                <p class="oc-partner-reg__hint"><?php esc_html_e('Verifica anti-spam prima dell\'invio:', 'opencomune'); ?></p>
                                <div id="partner-registration-turnstile-host" class="opencomune-partner-registration-turnstile" aria-live="polite"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="oc-partner-reg__nav is-first-step" id="partner-reg-nav">
                    <button type="button" id="prev-btn" class="oc-partner-reg__btn oc-partner-reg__btn--secondary" hidden>
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <?php esc_html_e('Indietro', 'opencomune'); ?>
                    </button>
                    <div class="oc-partner-reg__nav-actions">
                        <button type="button" id="next-btn" class="oc-partner-reg__btn oc-partner-reg__btn--primary">
                            <?php esc_html_e('Successivo', 'opencomune'); ?>
                            <i class="bi bi-arrow-right" aria-hidden="true"></i>
                        </button>
                        <button type="submit" id="submit-btn" class="oc-partner-reg__btn oc-partner-reg__btn--primary" hidden>
                            <?php esc_html_e('Invia richiesta', 'opencomune'); ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php get_footer(); ?>
