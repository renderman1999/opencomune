<?php
/**
 * Sincronizza privacy e termini come blocchi Gutenberg (eseguire con: wp eval-file ...).
 *
 * @package opencomune
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * @param array<string, mixed> $attrs
 */
function opencomune_legal_block_attrs(array $attrs): string {
    $json = wp_json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return $json !== false ? $json : '{}';
}

function opencomune_legal_paragraph(string $html, string $className = ''): string {
    $attrs_json = $className !== '' ? opencomune_legal_block_attrs(['className' => $className]) : '{}';
    $class_attr = $className !== '' ? ' class="' . esc_attr($className) . '"' : '';
    return "<!-- wp:paragraph {$attrs_json} -->\n"
        . "<p{$class_attr}>{$html}</p>\n"
        . "<!-- /wp:paragraph -->\n\n";
}

function opencomune_legal_heading(int $level, string $anchor, string $text): string {
    $tag = 'h' . $level;
    return "<!-- wp:heading " . opencomune_legal_block_attrs([
        'level' => $level,
        'anchor' => $anchor,
    ]) . " -->\n"
        . '<' . $tag . ' class="wp-block-heading" id="' . esc_attr($anchor) . '">' . $text . '</' . $tag . ">\n"
        . "<!-- /wp:heading -->\n\n";
}

/**
 * @param list<string> $items HTML per ogni voce.
 */
function opencomune_legal_list(array $items, bool $ordered = false): string {
    $tag = $ordered ? 'ol' : 'ul';
    $attrs_json = $ordered ? opencomune_legal_block_attrs(['ordered' => true]) : '{}';
    $inner = '';
    foreach ($items as $item) {
        $inner .= "<!-- wp:list-item -->\n<li>{$item}</li>\n<!-- /wp:list-item -->\n";
    }
    return "<!-- wp:list {$attrs_json} -->\n"
        . "<{$tag}>{$inner}</{$tag}>\n"
        . "<!-- /wp:list -->\n\n";
}

function opencomune_legal_group_open(string $className): string {
    return "<!-- wp:group " . opencomune_legal_block_attrs([
        'className' => $className,
        'layout' => ['type' => 'constrained'],
    ]) . " -->\n<div class=\"wp-block-group {$className}\">\n\n";
}

function opencomune_legal_group_close(): string {
    return "</div>\n<!-- /wp:group -->\n";
}

function opencomune_legal_table(string $table_html): string {
    return "<!-- wp:table -->\n<figure class=\"wp-block-table\">{$table_html}</figure>\n<!-- /wp:table -->\n\n";
}

function opencomune_build_privacy_policy_blocks(): string {
    $c = opencomune_legal_group_open('oc-legal-doc');

    $c .= opencomune_legal_paragraph('<strong>Ultimo aggiornamento:</strong> 29 ottobre 2026', 'oc-legal-doc__meta');
    $c .= opencomune_legal_paragraph(
        'La presente informativa descrive come vengono trattati i dati personali degli utenti che visitano o utilizzano il sito web <strong>Piattaforma per il Marketing Territoriale</strong> (di seguito, la «<strong>Piattaforma</strong>»), in conformità al Regolamento (UE) 2016/679 («<strong>GDPR</strong>») e al D.lgs. 196/2003, come modificato dal D.lgs. 101/2018.'
    );
    $c .= opencomune_legal_paragraph(
        'Le informazioni sono rese in forma chiara e accessibile (art. 12 GDPR) e contengono tutti gli elementi richiesti quando i dati sono raccolti direttamente dall\'interessato (art. 13 GDPR).'
    );

    $c .= opencomune_legal_group_open('oc-legal-doc__toc');
    $c .= opencomune_legal_paragraph('<strong>Indice</strong>');
    $c .= opencomune_legal_list([
        '<a href="#titolare">Titolare del trattamento</a>',
        '<a href="#ambito">Ambito di applicazione</a>',
        '<a href="#dati">Categorie di dati trattati</a>',
        '<a href="#finalita">Finalità, basi giuridiche e conferimento</a>',
        '<a href="#modalita">Modalità di trattamento e sicurezza</a>',
        '<a href="#conservazione">Periodo di conservazione</a>',
        '<a href="#destinatari">Destinatari e responsabili del trattamento</a>',
        '<a href="#trasferimenti">Trasferimenti verso paesi extra-UE</a>',
        '<a href="#automazione">Decisioni automatizzate e profilazione</a>',
        '<a href="#diritti">Diritti dell\'interessato</a>',
        '<a href="#reclamo">Reclamo all\'Autorità di controllo</a>',
        '<a href="#cookie">Cookie e tecnologie simili</a>',
        '<a href="#modifiche">Modifiche</a>',
    ], true);
    $c .= opencomune_legal_group_close();

    $c .= opencomune_legal_heading(2, 'titolare', '1. Titolare del trattamento');
    $c .= opencomune_legal_paragraph('Il Titolare del trattamento è:');
    $c .= opencomune_legal_paragraph(
        '<strong>Wewelcome – Travel Global, Act Local</strong><br>'
        . 'Via del Tratturello Tarantino, 5 – 74123 Taranto (TA), Italia<br>'
        . 'Email: <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a><br>'
        . 'Telefono: come indicato nella sezione Contatti del sito'
    );
    $c .= opencomune_legal_paragraph(
        'Il Titolare non ha nominato un Responsabile della Protezione dei Dati (DPO) ai sensi dell\'art. 37 GDPR. Per ogni richiesta relativa alla privacy è possibile contattare il Titolare all\'indirizzo email sopra indicato, oggetto «Privacy».'
    );

    $c .= opencomune_legal_heading(2, 'ambito', '2. Ambito di applicazione');
    $c .= opencomune_legal_paragraph('La presente informativa si applica ai trattamenti effettuati tramite la Piattaforma, inclusi a titolo esemplificativo:');
    $c .= opencomune_legal_list([
        'navigazione delle pagine pubbliche (home, catalogo esperienze, mappa, schede prodotto);',
        'utilizzo del form contatti e del planner di itinerari;',
        'registrazione e gestione account partner o utenti abilitati;',
        'accesso all\'area riservata (login);',
        'prenotazione e pagamento di esperienze turistiche, ove disponibili;',
        'pubblicazione e gestione di contenuti relativi al territorio da parte dei partner.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Le schede delle singole esperienze possono contenere dati di contatto o immagini forniti da <strong>partner/organizzatori</strong>, che agiscono in qualità di autonomi titolari per i contenuti da loro pubblicati. Per il trattamento effettuato direttamente dal partner (ad esempio gestione della prenotazione sul luogo dell\'esperienza) si rimanda alle informative rese dal partner stesso.'
    );

    $c .= opencomune_legal_heading(2, 'dati', '3. Categorie di dati trattati');
    $c .= opencomune_legal_paragraph('In relazione alle finalità descritte al punto 4, la Piattaforma può trattare le seguenti categorie di dati:');
    $c .= opencomune_legal_list([
        '<strong>Dati identificativi e di contatto:</strong> nome, cognome, ragione sociale, email, telefono, indirizzo, dati di fatturazione ove richiesti.',
        '<strong>Dati relativi all\'account:</strong> credenziali di accesso (password conservata in forma crittografata), ruolo utente, preferenze di profilo partner.',
        '<strong>Dati contrattuali e di prenotazione:</strong> esperienze selezionate, date, numero partecipanti, importi, stato pagamento, codici prenotazione.',
        '<strong>Dati di pagamento:</strong> identificativi transazione e stato ordine tramite provider di pagamento (PayPal). I dati della carta di pagamento non transitano sui server del Titolare se gestiti direttamente dal provider.',
        '<strong>Contenuti forniti dall\'utente:</strong> messaggi inviati via form contatti, richieste al planner, descrizioni e materiali caricati dai partner (testi, immagini, posizioni geografiche).',
        '<strong>Dati di navigazione e tecnici:</strong> indirizzo IP, data e ora di accesso, tipo di browser e dispositivo, pagine visitate, cookie e identificativi tecnici necessari al funzionamento del sito.',
        '<strong>Dati di localizzazione approssimativa:</strong> coordinate o indirizzi inseriti per mappe, punti di incontro o geocodifica (anche tramite servizi cartografici di terze parti).',
    ]);
    $c .= opencomune_legal_paragraph(
        'Salvo diversa indicazione nel form di raccolta, non è richiesto il conferimento di categorie particolari di dati (art. 9 GDPR). Si prega di non inserire tali dati nei campi liberi (messaggi, note) se non strettamente necessari.'
    );

    $c .= opencomune_legal_heading(2, 'finalita', '4. Finalità, basi giuridiche e conferimento');
    $c .= opencomune_legal_paragraph('Di seguito le principali finalità di trattamento, la base giuridica e le conseguenze del mancato conferimento.');
    $c .= opencomune_legal_table(
        '<table><thead><tr>'
        . '<th scope="col">Finalità</th><th scope="col">Base giuridica (art. 6 GDPR)</th><th scope="col">Conferimento</th>'
        . '</tr></thead><tbody>'
        . '<tr><td>Erogazione del sito, sicurezza informatica, prevenzione abusi (inclusa verifica anti-spam Cloudflare Turnstile ove attiva)</td>'
        . '<td>Legittimo interesse del Titolare (art. 6, par. 1, lett. f) e, ove applicabile, obbligo di legge</td>'
        . '<td>Necessario per la navigazione sicura; il rifiuto può impedire l\'invio di alcuni form</td></tr>'
        . '<tr><td>Gestione richieste inviate tramite form contatti</td>'
        . '<td>Misura precontrattuale o legittimo interesse (art. 6, par. 1, lett. b) o lett. f)</td>'
        . '<td>Necessario per ricevere risposta alla richiesta</td></tr>'
        . '<tr><td>Registrazione partner, creazione e gestione account, adempimenti contrattuali</td>'
        . '<td>Esecuzione di un contratto o misure precontrattuali (art. 6, par. 1, lett. b); consenso ove richiesto per specifiche dichiarazioni</td>'
        . '<td>Necessario per registrarsi e operare come partner</td></tr>'
        . '<tr><td>Accesso area riservata (login) e gestione sessione</td>'
        . '<td>Esecuzione del contratto (art. 6, par. 1, lett. b)</td>'
        . '<td>Necessario per l\'accesso ai servizi riservati</td></tr>'
        . '<tr><td>Prenotazione esperienze e pagamento</td>'
        . '<td>Esecuzione del contratto (art. 6, par. 1, lett. b); obblighi legali (contabilità, fiscale)</td>'
        . '<td>Necessario per completare la prenotazione</td></tr>'
        . '<tr><td>Invio di comunicazioni di servizio</td>'
        . '<td>Esecuzione del contratto (art. 6, par. 1, lett. b)</td>'
        . '<td>Il mancato conferimento dell\'email impedisce le comunicazioni operative</td></tr>'
        . '<tr><td>Planner itinerari</td>'
        . '<td>Misura precontrattuale o esecuzione del contratto (art. 6, par. 1, lett. b); consenso ove previsto</td>'
        . '<td>Facoltativo per la sola consultazione; necessario per ricevere l\'itinerario via email</td></tr>'
        . '<tr><td>Pubblicazione di esperienze e contenuti territoriali</td>'
        . '<td>Esecuzione del contratto con il partner (art. 6, par. 1, lett. b); consenso ove richiesto</td>'
        . '<td>Necessario per la visibilità delle esperienze concordate</td></tr>'
        . '<tr><td>Adempimenti di legge, difesa in giudizio, gestione reclami</td>'
        . '<td>Obbligo di legge (art. 6, par. 1, lett. c); legittimo interesse (art. 6, par. 1, lett. f)</td>'
        . '<td>Il conferimento può essere imposto dalla legge</td></tr>'
        . '</tbody></table>'
    );
    $c .= opencomune_legal_paragraph(
        '<strong>Legittimo interesse (art. 6, par. 1, lett. f):</strong> per i trattamenti basati su legittimo interesse (sicurezza, prevenzione frodi, miglioramento tecnico del servizio), l\'interessato può opporsi per motivi connessi alla sua situazione particolare (vedi sezione Diritti).'
    );

    $c .= opencomune_legal_heading(2, 'modalita', '5. Modalità di trattamento e sicurezza');
    $c .= opencomune_legal_paragraph(
        'I dati sono trattati con strumenti elettronici e, in casi limitati, cartacei, con logiche correlate alle finalità indicate e mediante misure tecniche e organizzative adeguate (art. 32 GDPR), tra cui controllo degli accessi, backup, aggiornamenti di sicurezza e minimizzazione dei dati.'
    );

    $c .= opencomune_legal_heading(2, 'conservazione', '6. Periodo di conservazione');
    $c .= opencomune_legal_paragraph(
        'I dati sono conservati per il tempo necessario al perseguimento delle finalità per cui sono stati raccolti e, successivamente, per il periodo previsto dalla legge:'
    );
    $c .= opencomune_legal_list([
        '<strong>Richieste form contatti:</strong> fino a 36 mesi dall\'ultima interazione, salvo contenzioso.',
        '<strong>Account partner e utenti:</strong> per la durata del rapporto contrattuale e fino a 10 anni per adempimenti fiscali/contabili ove applicabili.',
        '<strong>Dati di prenotazione e pagamento:</strong> per la durata del servizio e per i termini di legge (in genere 10 anni per documenti contabili).',
        '<strong>Log tecnici e sicurezza:</strong> in genere fino a 12 mesi, salvo necessità di accertamento di reati.',
        '<strong>Cookie tecnici:</strong> secondo quanto indicato nella sezione Cookie.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Scaduti i termini, i dati sono cancellati, anonimizzati o resi inaccessibili, salvo ulteriore conservazione per obblighi di legge o tutela in sede giudiziaria.'
    );

    $c .= opencomune_legal_heading(2, 'destinatari', '7. Destinatari e responsabili del trattamento');
    $c .= opencomune_legal_paragraph(
        'I dati possono essere comunicati a soggetti che trattano per conto del Titolare (Responsabili del trattamento, art. 28 GDPR) o che operano come autonomi titolari, tra cui:'
    );
    $c .= opencomune_legal_list([
        'fornitore di hosting e infrastruttura del sito;',
        'provider di posta elettronica e servizi di messaggistica transazionale;',
        '<strong>PayPal</strong> (o altro gateway di pagamento configurato) per l\'elaborazione dei pagamenti;',
        '<strong>Cloudflare Turnstile</strong> (Cloudflare, Inc.) per la verifica anti-bot sui form, ove attivata;',
        '<strong>Google LLC</strong> (Google Maps Platform) per mappe, geocodifica e servizi collegati, ove utilizzati;',
        'consulenti legali, fiscali o tecnici, nei limiti degli incarichi ricevuti;',
        'autorità pubbliche, ove previsto dalla legge.',
    ]);
    $c .= opencomune_legal_paragraph('L\'elenco aggiornato dei Responsabili può essere richiesto al Titolare ai contatti indicati sopra.');

    $c .= opencomune_legal_heading(2, 'trasferimenti', '8. Trasferimenti verso paesi extra-UE');
    $c .= opencomune_legal_paragraph(
        'Alcuni fornitori (ad esempio Cloudflare, Google, PayPal) possono trattare dati anche negli Stati Uniti o in altri Paesi extra-UE. In tali casi il Titolare adotta garanzie appropriate ai sensi degli artt. 44 e ss. GDPR (decisioni di adeguatezza, Standard Contractual Clauses, misure supplementari ove richieste). Copia delle garanzie o informazioni su come ottenerle è disponibile su richiesta al Titolare.'
    );

    $c .= opencomune_legal_heading(2, 'automazione', '9. Decisioni automatizzate e profilazione');
    $c .= opencomune_legal_paragraph(
        'La Piattaforma non adotta decisioni basate unicamente su trattamenti automatizzati, compresa la profilazione, che producano effetti giuridici sull\'interessato o incidano in modo analogo significativamente sulla sua persona (art. 22 GDPR). Strumenti di verifica automatica (anti-spam) incidono solo sulla possibilità di inviare un form, senza effetti pregiudizievoli oltre al blocco dell\'invio.'
    );

    $c .= opencomune_legal_heading(2, 'diritti', '10. Diritti dell\'interessato');
    $c .= opencomune_legal_paragraph('In qualità di interessato, hai diritto di:');
    $c .= opencomune_legal_list([
        '<strong>accesso</strong> (art. 15) – ottenere conferma del trattamento e copia dei dati;',
        '<strong>rettifica</strong> (art. 16) – correggere dati inesatti;',
        '<strong>cancellazione</strong> (art. 17) – nei casi previsti dalla legge («diritto all\'oblio»);',
        '<strong>limitazione</strong> (art. 18) – limitare il trattamento in determinate ipotesi;',
        '<strong>portabilità</strong> (art. 20) – ricevere i dati in formato strutturato, per i trattamenti basati su consenso o contratto e automatizzati;',
        '<strong>opposizione</strong> (art. 21) – opporsi ai trattamenti basati su legittimo interesse, compresa la marketing diretta ove applicabile;',
        '<strong>revoca del consenso</strong> (art. 7, par. 3) – in qualsiasi momento, senza pregiudicare la liceità del trattamento basato sul consenso prestato prima della revoca.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Per esercitare i diritti scrivi a <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a> indicando l\'oggetto «Esercizio diritti privacy». Il Titolare risponde senza ingiustificato ritardo e, in ogni caso, entro <strong>un mese</strong> dal ricevimento della richiesta (art. 12 GDPR), prorogabile di ulteriori due mesi in casi di complessità, previa comunicazione. L\'esercizio dei diritti è, di regola, gratuito.'
    );
    $c .= opencomune_legal_paragraph(
        'Il Titolare può chiedere informazioni aggiuntive per verificare l\'identità del richiedente (art. 12, par. 6 GDPR).'
    );

    $c .= opencomune_legal_heading(2, 'reclamo', '11. Reclamo all\'Autorità di controllo');
    $c .= opencomune_legal_paragraph('L\'interessato ha diritto di proporre reclamo al Garante per la protezione dei dati personali:');
    $c .= opencomune_legal_paragraph(
        'Garante per la protezione dei dati personali<br>'
        . 'Piazza Venezia, 11 – 00187 Roma<br>'
        . 'Sito: <a href="https://www.garanteprivacy.it" rel="noopener noreferrer">www.garanteprivacy.it</a>'
    );

    $c .= opencomune_legal_heading(2, 'cookie', '12. Cookie e tecnologie simili');
    $c .= opencomune_legal_paragraph('Il sito utilizza cookie e tecnologie analoghe per garantire il corretto funzionamento e la sicurezza.');
    $c .= opencomune_legal_list([
        '<strong>Cookie tecnici / necessari:</strong> indispensabili per login, sessione, preferenze di sicurezza e funzionamento della Piattaforma. Non richiedono consenso.',
        '<strong>Cookie di terze parti:</strong> mappe (Google), verifica Turnstile (Cloudflare) o embed possono impostare cookie o trattare identificativi tecnici. Ove non strettamente necessari, il relativo utilizzo è subordinato alle scelte rese tramite eventuale banner cookie o alle impostazioni del browser.',
    ]);
    $c .= opencomune_legal_paragraph(
        'È possibile gestire o disabilitare i cookie dalle impostazioni del browser; la disabilitazione dei cookie tecnici può compromettere alcune funzionalità (accesso, prenotazione, invio form).'
    );
    $c .= opencomune_legal_paragraph(
        'Per maggiori dettagli su singoli cookie e durate, contattare il Titolare o consultare la <a href="' . esc_url(home_url('/cookie-policy/')) . '">Cookie policy</a>.'
    );

    $c .= opencomune_legal_heading(2, 'modifiche', '13. Modifiche');
    $c .= opencomune_legal_paragraph(
        'Il Titolare può aggiornare la presente informativa per adeguarla a modifiche normative o del servizio. La versione aggiornata è pubblicata su questa pagina con indicazione della data di ultimo aggiornamento. Si invita a consultarla periodicamente.'
    );
    $c .= opencomune_legal_paragraph(
        '<em>Documento redatto secondo principi di trasparenza GDPR (artt. 12–13). Non sostituisce una consulenza legale personalizzata; il Titolare è invitato a verificarne l\'adeguatezza con il proprio consulente privacy in relazione ai trattamenti effettivamente svolti.</em>'
    );

    $c .= opencomune_legal_group_close();
    return $c;
}

function opencomune_build_terms_conditions_blocks(): string {
    $c = opencomune_legal_group_open('oc-legal-doc');

    $c .= opencomune_legal_paragraph('<strong>Ultimo aggiornamento:</strong> 29 ottobre 2026', 'oc-legal-doc__meta');
    $c .= opencomune_legal_paragraph(
        'I presenti <strong>Termini e condizioni</strong> (di seguito, i «<strong>Termini</strong>») regolano l\'accesso e l\'utilizzo del sito web e dei servizi digitali denominati <strong>Piattaforma per il Marketing Territoriale</strong> (di seguito, la «<strong>Piattaforma</strong>»), gestiti da <strong>Wewelcome – Travel Global, Act Local</strong> (di seguito, il «<strong>Fornitore</strong>» o «<strong>noi</strong>»).'
    );
    $c .= opencomune_legal_paragraph(
        'L\'uso della Piattaforma implica la conoscenza e l\'accettazione integrale dei Termini. Per il trattamento dei dati personali si applica l\'<a href="/privacy-policy/">Informativa sulla privacy</a>.'
    );

    $c .= opencomune_legal_group_open('oc-legal-doc__toc');
    $c .= opencomune_legal_paragraph('<strong>Indice</strong>');
    $c .= opencomune_legal_list([
        '<a href="#definizioni">Definizioni</a>',
        '<a href="#fornitore">Fornitore del servizio</a>',
        '<a href="#oggetto">Oggetto della Piattaforma</a>',
        '<a href="#accesso">Accesso, idoneità e account</a>',
        '<a href="#visitatori">Utilizzo da parte dei visitatori</a>',
        '<a href="#partner">Registrazione e obblighi dei partner</a>',
        '<a href="#esperienze">Esperienze, prenotazioni e pagamenti</a>',
        '<a href="#commissioni">Commissioni e corrispettivi</a>',
        '<a href="#contenuti">Contenuti caricati dagli utenti</a>',
        '<a href="#proprieta">Proprietà intellettuale</a>',
        '<a href="#responsabilita">Limitazione di responsabilità</a>',
        '<a href="#consumatori">Diritti dei consumatori</a>',
        '<a href="#cancellazioni">Cancellazioni e rimborsi</a>',
        '<a href="#sospensione">Sospensione e cessazione</a>',
        '<a href="#modifiche-termini">Modifiche ai Termini</a>',
        '<a href="#legge">Legge applicabile e foro competente</a>',
        '<a href="#contatti">Contatti</a>',
    ], true);
    $c .= opencomune_legal_group_close();

    $c .= opencomune_legal_heading(2, 'definizioni', '1. Definizioni');
    $c .= opencomune_legal_list([
        '<strong>Utente:</strong> qualsiasi persona che accede alla Piattaforma, con o senza registrazione.',
        '<strong>Partner:</strong> soggetto (impresa, professionista o ente) registrato per pubblicare e gestire esperienze o servizi sul territorio.',
        '<strong>Esperienza:</strong> proposta turistica, culturale o ricreativa pubblicata sulla Piattaforma dal Partner o dall\'ufficio competente.',
        '<strong>Prenotazione:</strong> richiesta di partecipazione a un\'Esperienza effettuata tramite gli strumenti di booking della Piattaforma, ove abilitati.',
        '<strong>Area riservata:</strong> sezioni accessibili previo login (dashboard partner, strumenti di gestione, ecc.).',
    ]);

    $c .= opencomune_legal_heading(2, 'fornitore', '2. Fornitore del servizio');
    $c .= opencomune_legal_paragraph('Il Fornitore della Piattaforma è:');
    $c .= opencomune_legal_paragraph(
        '<strong>Wewelcome – Travel Global, Act Local</strong><br>'
        . 'Via del Tratturello Tarantino, 5 – 74123 Taranto (TA), Italia<br>'
        . 'Email: <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a><br>'
        . 'Telefono: come indicato nella pagina <a href="/contatti/">Contatti</a>'
    );

    $c .= opencomune_legal_heading(2, 'oggetto', '3. Oggetto della Piattaforma');
    $c .= opencomune_legal_paragraph(
        'La Piattaforma ha finalità di <strong>marketing territoriale</strong> e di valorizzazione del territorio: consente la consultazione di esperienze, mappe, strumenti di pianificazione itinerari, moduli di contatto e, ove configurati, la prenotazione online e la gestione da parte dei Partner.'
    );
    $c .= opencomune_legal_paragraph(
        'Il Fornitore mette a disposizione un\'infrastruttura tecnologica. Salvo diversa indicazione esplicita, <strong>l\'erogazione dell\'Esperienza sul campo</strong> è di competenza del Partner o dell\'organizzatore indicato in scheda, che agisce come contraente verso l\'Utente finale per quanto attiene al servizio turistico offerto.'
    );

    $c .= opencomune_legal_heading(2, 'accesso', '4. Accesso, idoneità e account');
    $c .= opencomune_legal_paragraph(
        'L\'accesso alla Piattaforma è consentito a Utenti maggiorenni o minori con supervisione di un genitore o tutore. L\'Utente garantisce che i dati forniti in fase di registrazione o prenotazione sono veritieri, completi e aggiornati.'
    );
    $c .= opencomune_legal_paragraph(
        'Credenziali di accesso (username e password) sono personali e non cedibili. L\'Utente è responsabile di ogni attività svolta tramite il proprio account e deve informare tempestivamente il Fornitore in caso di uso non autorizzato.'
    );
    $c .= opencomune_legal_paragraph(
        'Il Fornitore può rifiutare, sospendere o chiudere un account in caso di violazione dei Termini, di richieste delle autorità o di rischio per la sicurezza del servizio.'
    );

    $c .= opencomune_legal_heading(2, 'visitatori', '5. Utilizzo da parte dei visitatori');
    $c .= opencomune_legal_paragraph(
        'I visitatori possono navigare contenuti pubblici, utilizzare ricerca, mappa, planner (ove attivo), inviare richieste tramite form contatti e, ove disponibile, procedere alla prenotazione.'
    );
    $c .= opencomune_legal_paragraph('È vietato:');
    $c .= opencomune_legal_list([
        'utilizzare la Piattaforma in violazione di legge, regolamenti o diritti di terzi;',
        'tentare accessi non autorizzati, interferire con il funzionamento del sito o introdurre malware;',
        'estrarre sistematicamente dati o contenuti (scraping) senza consenso scritto del Fornitore;',
        'utilizzare identità false o ingannevoli nei form o nelle prenotazioni.',
    ]);

    $c .= opencomune_legal_heading(2, 'partner', '6. Registrazione e obblighi dei partner');
    $c .= opencomune_legal_paragraph('La registrazione Partner avviene tramite il modulo dedicato. Il Partner dichiara di:');
    $c .= opencomune_legal_list([
        'essere legittimato a svolgere l\'attività indicata e a pubblicare i contenuti proposti;',
        'fornire dati fiscali e di contatto corretti (partita IVA/codice fiscale, sede, recapiti);',
        'accettare i presenti Termini, l\'Informativa sulla privacy e, ove richiesto, specifiche condizioni di adesione al portale;',
        'autorizzare, ove previsto dal modulo di registrazione, la pubblicazione dei dati dichiarati per la visibilità sul territorio.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Il Partner è l\'unico responsabile dei contenuti delle schede (testi, prezzi, disponibilità, immagini, policy di cancellazione, requisiti di partecipazione) e si impegna a mantenerli aggiornati. Il Fornitore può moderare, richiedere correzioni o rimuovere contenuti manifestamente illeciti, fuorvianti o in contrasto con i Termini, senza obbligo di controllo preventivo su ogni pubblicazione.'
    );
    $c .= opencomune_legal_paragraph(
        'L\'approvazione dell\'account Partner, se prevista, non costituisce certificazione della qualità legale o commerciale dell\'offerta.'
    );

    $c .= opencomune_legal_heading(2, 'esperienze', '7. Esperienze, prenotazioni e pagamenti');
    $c .= opencomune_legal_paragraph(
        'Le condizioni economiche, durata, luogo, lingua, inclusioni/esclusioni e vincoli di partecipazione di ciascuna Esperienza sono indicati nella relativa scheda. La Prenotazione, quando disponibile online, costituisce proposta o ordine secondo il flusso indicato al checkout (conferma, email di riepilogo, biglietto o voucher ove previsto).'
    );
    $c .= opencomune_legal_paragraph(
        'I pagamenti online possono essere gestiti tramite provider esterni (es. <strong>PayPal</strong> o altri gateway configurati). Il Fornitore non memorizza i dati completi degli strumenti di pagamento dell\'Utente ove il pagamento avviene direttamente sul circuito del provider. L\'Utente accetta anche i termini del provider di pagamento per le operazioni effettuate tramite di esso.'
    );
    $c .= opencomune_legal_paragraph(
        'Eventuali errori manifesti di prezzo o disponibilità possono essere corretti prima della conferma definitiva; in caso di pagamento già effettuato per un errore evidente, sarà proposto rimborso o alternativa equivalente.'
    );

    $c .= opencomune_legal_heading(2, 'commissioni', '8. Commissioni e corrispettivi');
    $c .= opencomune_legal_paragraph(
        'Per le Prenotazioni pagate online, il Fornitore può trattenere una <strong>commissione</strong> sul transato, calcolata secondo la percentuale e le regole comunicate al Partner in area riservata o in documentazione dedicata. Importo di commissione e netto accreditabile al Partner sono registrati al momento della conferma del pagamento e restano quelli applicabili a quell\'ordine, salvo diversi accordi scritti.'
    );
    $c .= opencomune_legal_paragraph(
        'Restano a carico del Partner imposte, oneri fiscali e adempimenti connessi alla propria attività, salvo obblighi di legge diversamente attribuiti.'
    );

    $c .= opencomune_legal_heading(2, 'contenuti', '9. Contenuti caricati dagli utenti');
    $c .= opencomune_legal_paragraph(
        'Testi, immagini, recensioni o altri materiali inviati dall\'Utente o dal Partner non devono violare diritti di terzi, né contenere materiale diffamatorio, discriminatorio, violento o contrario alla legge.'
    );
    $c .= opencomune_legal_paragraph(
        'Inviando contenuti alla Piattaforma, l\'Utente/Partner concede al Fornitore una licenza non esclusiva, gratuita, limitata alla durata della pubblicazione, per hostare, riprodurre, adattare formati e promuovere i contenuti nell\'ambito del servizio di marketing territoriale.'
    );

    $c .= opencomune_legal_heading(2, 'proprieta', '10. Proprietà intellettuale');
    $c .= opencomune_legal_paragraph(
        'Marchi, loghi, layout, software e contenuti editoriali della Piattaforma (salvo contenuti dei Partner) sono di titolarità del Fornitore o dei rispettivi licenzianti. È vietata la riproduzione sistematica senza autorizzazione scritta.'
    );
    $c .= opencomune_legal_paragraph(
        'I contenuti del Partner restano di titolarità del Partner, fermo quanto indicato alla sezione precedente per la licenza d\'uso concessa al Fornitore.'
    );

    $c .= opencomune_legal_heading(2, 'responsabilita', '11. Limitazione di responsabilità');
    $c .= opencomune_legal_paragraph(
        'La Piattaforma è fornita «così com\'è», nei limiti consentiti dalla legge applicabile. Il Fornitore adotta misure ragionevoli per garantirne disponibilità e sicurezza, senza tuttavia garantire assenza assoluta di interruzioni o errori.'
    );
    $c .= opencomune_legal_paragraph('Il Fornitore <strong>non è responsabile</strong>, nei limiti di legge:');
    $c .= opencomune_legal_list([
        'dell\'effettiva esecuzione dell\'Esperienza da parte del Partner (qualità, sicurezza, puntualità, conformità alle descrizioni), salvo obblighi inderogabili;',
        'di danni derivanti da uso improprio della Piattaforma da parte dell\'Utente;',
        'di contenuti e link di siti terzi raggiungibili dalla Piattaforma;',
        'di malfunzionamenti imputabili a reti, dispositivi dell\'Utente o servizi di terze parti (mappe, pagamenti, email).',
    ]);
    $c .= opencomune_legal_paragraph(
        'Resta salva la responsabilità del Fornitore per dolo o colpa grave e per i casi in cui la legge non consente limitazioni.'
    );

    $c .= opencomune_legal_heading(2, 'consumatori', '12. Diritti dei consumatori');
    $c .= opencomune_legal_paragraph(
        'Se l\'Utente agisce in qualità di <strong>consumatore</strong> (persona fisica che agisce per scopi estranei all\'attività imprenditoriale o professionale), si applicano le disposizioni inderogabili del Codice del consumo (D.lgs. 206/2005) e norme correlate.'
    );
    $c .= opencomune_legal_paragraph(
        'Per i contratti a distanza relativi a servizi turistici o esperienze, possono applicarsi regole specifiche in materia di recesso, informazioni precontrattuali e rimedi. Le condizioni particolari (incluso diritto di recesso o esclusioni previste dalla legge per servizi con data determinata) sono indicate nella scheda dell\'Esperienza e/o al checkout. In caso di conflitto tra scheda/checkout e i presenti Termini, prevalgono le informazioni rese al momento della Prenotazione per quanto riguarda quell\'ordine.'
    );

    $c .= opencomune_legal_heading(2, 'cancellazioni', '13. Cancellazioni e rimborsi');
    $c .= opencomune_legal_paragraph(
        'Ogni Esperienza può prevedere una <strong>politica di cancellazione</strong> propria, visibile in scheda o nel flusso di prenotazione. L\'Utente è tenuto a leggerla prima di confermare.'
    );
    $c .= opencomune_legal_paragraph(
        'Richieste di annullamento, rimborso o modifica devono essere inoltrate secondo le modalità indicate nella conferma di Prenotazione (area personale, contatti del Partner o del Fornitore). I tempi di accredito dipendono anche dal provider di pagamento.'
    );
    $c .= opencomune_legal_paragraph(
        'Il Partner e, ove competente, il Fornitore gestiscono le cancellazioni nel rispetto della policy applicabile e della normativa vigente.'
    );

    $c .= opencomune_legal_heading(2, 'sospensione', '14. Sospensione e cessazione');
    $c .= opencomune_legal_paragraph(
        'Il Fornitore può sospendere o terminare l\'accesso alla Piattaforma, in tutto o in parte, con preavviso ove ragionevolmente possibile, in caso di violazione dei Termini, obblighi di legge, ordini dell\'autorità o esigenze tecniche.'
    );
    $c .= opencomune_legal_paragraph(
        'Il Partner può richiedere la chiusura dell\'account; restano efficaci obblighi sorti prima della chiusura (pagamenti, prenotazioni in corso, responsabilità per contenuti già pubblicati).'
    );

    $c .= opencomune_legal_heading(2, 'modifiche-termini', '15. Modifiche ai Termini');
    $c .= opencomune_legal_paragraph(
        'Il Fornitore può aggiornare i Termini per adeguamenti normativi o evoluzione del servizio. La versione aggiornata è pubblicata su questa pagina con data di ultimo aggiornamento. Per i Partner registrati, modifiche sostanziali possono essere comunicate via email o avviso in area riservata. L\'uso continuato della Piattaforma dopo la pubblicazione vale come accettazione, salvo diversa previsione inderogabile di legge.'
    );

    $c .= opencomune_legal_heading(2, 'legge', '16. Legge applicabile e foro competente');
    $c .= opencomune_legal_paragraph('I Termini sono regolati dalla <strong>legge italiana</strong>.');
    $c .= opencomune_legal_paragraph(
        'Per ogni controversia relativa all\'interpretazione o esecuzione dei Termini, se l\'Utente agisce in qualità di consumatore, è competente il foro del luogo di residenza o domicilio del consumatore, ove previsto dalla legge. Negli altri casi, foro esclusivo di <strong>Taranto</strong>, salvo foro inderogabile.'
    );
    $c .= opencomune_legal_paragraph(
        'Il consumatore residente nell\'Unione europea può ricorrere alla piattaforma ODR della Commissione europea per la risoluzione extragiudiziale delle controversie: <a href="https://ec.europa.eu/consumers/odr" rel="noopener noreferrer">https://ec.europa.eu/consumers/odr</a>.'
    );

    $c .= opencomune_legal_heading(2, 'contatti', '17. Contatti');
    $c .= opencomune_legal_paragraph('Per informazioni sui Termini, sulle Prenotazioni o sul rapporto Partner:');
    $c .= opencomune_legal_paragraph(
        'Email: <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a><br>'
        . 'Pagina: <a href="/contatti/">Contatti</a>'
    );
    $c .= opencomune_legal_paragraph(
        '<em>I presenti Termini non costituiscono consulenza legale. Il Fornitore è invitato a farli revisionare dal proprio consulente in relazione al modello di business effettivo e agli accordi con enti territoriali o partner.</em>'
    );

    $c .= opencomune_legal_group_close();
    return $c;
}

function opencomune_build_cookie_policy_blocks(): string {
    $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : home_url('/privacy-policy/');
    if (!is_string($privacy_url) || $privacy_url === '') {
        $privacy_url = home_url('/privacy-policy/');
    }

    $c = opencomune_legal_group_open('oc-legal-doc');

    $c .= opencomune_legal_paragraph('<strong>Ultimo aggiornamento:</strong> 29 ottobre 2026', 'oc-legal-doc__meta');
    $c .= opencomune_legal_paragraph(
        'La presente <strong>Cookie policy</strong> spiega cosa sono i cookie e le tecnologie simili, quali ne utilizziamo sul sito <strong>Piattaforma per il Marketing Territoriale</strong> (di seguito, la «<strong>Piattaforma</strong>»), per quali finalità e come puoi gestire le tue preferenze.'
    );
    $c .= opencomune_legal_paragraph(
        'Il trattamento dei dati personali connesso all\'uso dei cookie è descritto anche nell\'<a href="' . esc_url($privacy_url) . '">Informativa sulla privacy</a>. Per i Termini d\'uso del sito consulta la pagina <a href="' . esc_url(home_url('/termini-e-condizioni/')) . '">Termini e condizioni</a>.'
    );

    $c .= opencomune_legal_group_open('oc-legal-doc__toc');
    $c .= opencomune_legal_paragraph('<strong>Indice</strong>');
    $c .= opencomune_legal_list([
        '<a href="#titolare">Titolare</a>',
        '<a href="#cosa-sono">Cosa sono cookie e tecnologie simili</a>',
        '<a href="#tipologie">Tipologie utilizzate</a>',
        '<a href="#elenco">Elenco dei cookie</a>',
        '<a href="#terze-parti">Cookie di terze parti</a>',
        '<a href="#consenso">Consenso e gestione preferenze</a>',
        '<a href="#browser">Impostazioni del browser</a>',
        '<a href="#diritti">Diritti e contatti</a>',
        '<a href="#modifiche">Modifiche</a>',
    ], true);
    $c .= opencomune_legal_group_close();

    $c .= opencomune_legal_heading(2, 'titolare', '1. Titolare');
    $c .= opencomune_legal_paragraph('Il Titolare del trattamento tramite cookie è:');
    $c .= opencomune_legal_paragraph(
        '<strong>Wewelcome – Travel Global, Act Local</strong><br>'
        . 'Via del Tratturello Tarantino, 5 – 74123 Taranto (TA), Italia<br>'
        . 'Email: <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a>'
    );

    $c .= opencomune_legal_heading(2, 'cosa-sono', '2. Cosa sono cookie e tecnologie simili');
    $c .= opencomune_legal_paragraph(
        'I <strong>cookie</strong> sono piccoli file di testo che i siti visitati inviano al terminale dell\'utente, dove vengono memorizzati per essere ritrasmessi alla visita successiva. Possono essere <strong>di sessione</strong> (eliminati alla chiusura del browser) o <strong>persistenti</strong> (con scadenza definita).'
    );
    $c .= opencomune_legal_paragraph(
        'Analoghe tecnologie includono identificatori in <strong>localStorage</strong> o <strong>sessionStorage</strong>, pixel di tracciamento e SDK incorporati. Nella presente policy il termine «cookie» include, ove rilevante, anche tali strumenti.'
    );

    $c .= opencomune_legal_heading(2, 'tipologie', '3. Tipologie utilizzate');
    $c .= opencomune_legal_paragraph('Classifichiamo i cookie secondo le finalità prevalenti (linee guida ePrivacy / GDPR):');
    $c .= opencomune_legal_list([
        '<strong>Strettamente necessari / tecnici:</strong> indispensabili per erogare il servizio richiesto (login, sicurezza, carrello o prenotazione, bilanciamento). Non richiedono consenso ai sensi della normativa applicabile.',
        '<strong>Funzionali:</strong> ricordano preferenze o abilitano funzioni aggiuntive (es. lingua). Richiedono consenso ove non strettamente necessari.',
        '<strong>Statistici / analytics:</strong> misurano in forma aggregata l\'uso del sito. Richiedono consenso preventivo se non anonimizzati in modo conforme.',
        '<strong>Marketing / profilazione:</strong> tracciano l\'utente per pubblicità personalizzata. Richiedono consenso preventivo.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Alla data dell\'ultimo aggiornamento la Piattaforma <strong>non utilizza cookie di profilazione o marketing</strong> di propria iniziativa. Eventuali strumenti analytics o marketing saranno attivati solo previo consenso e con aggiornamento di questa policy.'
    );

    $c .= opencomune_legal_heading(2, 'elenco', '4. Elenco dei cookie');
    $c .= opencomune_legal_paragraph(
        'Di seguito i cookie e storage rilevanti rilevati o prevedibili in relazione al funzionamento della Piattaforma. Nomi e durate possono variare leggermente in base agli aggiornamenti del software.'
    );
    $c .= opencomune_legal_table(
        '<table><thead><tr>'
        . '<th scope="col">Nome / tipo</th><th scope="col">Categoria</th><th scope="col">Finalità</th><th scope="col">Durata indicativa</th>'
        . '</tr></thead><tbody>'
        . '<tr><td><code>wordpress_test_cookie</code></td><td>Necessario</td><td>Verifica che il browser accetti i cookie</td><td>Sessione</td></tr>'
        . '<tr><td><code>wordpress_logged_in_*</code>, <code>wordpress_sec_*</code></td><td>Necessario</td><td>Autenticazione area riservata (login partner/ufficio)</td><td>Sessione / 14 giorni (se «ricordami»)</td></tr>'
        . '<tr><td><code>wp-settings-*</code>, <code>wp-settings-time-*</code></td><td>Funzionale / necessario</td><td>Preferenze interfaccia amministrazione o utente loggato</td><td>Fino a 1 anno</td></tr>'
        . '<tr><td>Cookie di sessione PHP / WordPress</td><td>Necessario</td><td>Mantenimento sessione e sicurezza form (nonce)</td><td>Sessione</td></tr>'
        . '<tr><td>Cookie Cloudflare Turnstile (es. <code>__cf_bm</code>, <code>cf_clearance</code>)</td><td>Necessario / sicurezza</td><td>Verifica anti-bot su form (contatti, login, registrazione) ove attiva</td><td>Da pochi minuti a 30 giorni (fornitore)</td></tr>'
        . '<tr><td>Cookie Google Maps (es. <code>NID</code>, <code>1P_JAR</code>)</td><td>Funzionale / terze parti</td><td>Mappe, geocodifica, autocomplete indirizzi ove caricati</td><td>Secondo policy Google</td></tr>'
        . '<tr><td>Cookie PayPal</td><td>Necessario / terze parti</td><td>Elaborazione pagamento al checkout ove si usa PayPal</td><td>Secondo policy PayPal</td></tr>'
        . '<tr><td>Leaflet / tile map (cache locale)</td><td>Funzionale</td><td>Visualizzazione mappa esperienze (dati tecnici in cache browser)</td><td>Sessione / cache browser</td></tr>'
        . '</tbody></table>'
    );

    $c .= opencomune_legal_heading(2, 'terze-parti', '5. Cookie di terze parti');
    $c .= opencomune_legal_paragraph(
        'Alcune funzioni incorporano risorse di terze parti che possono impostare cookie autonomamente (Titolari distinti):'
    );
    $c .= opencomune_legal_list([
        '<strong>Cloudflare, Inc.</strong> – Turnstile e protezione da abusi: <a href="https://www.cloudflare.com/privacypolicy/" rel="noopener noreferrer">Privacy Cloudflare</a>.',
        '<strong>Google LLC</strong> – Google Maps Platform: <a href="https://policies.google.com/privacy" rel="noopener noreferrer">Privacy Google</a>.',
        '<strong>PayPal</strong> – pagamenti: <a href="https://www.paypal.com/webapps/mpp/ua/privacy-full" rel="noopener noreferrer">Privacy PayPal</a>.',
    ]);
    $c .= opencomune_legal_paragraph(
        'Per questi trattamenti si applicano anche le informative dei rispettivi fornitori. Ove i cookie non siano strettamente necessari, il caricamento avviene in modo subordinato al consenso o all\'interazione dell\'utente (es. apertura mappa, avvio pagamento, invio form con verifica Turnstile).'
    );

    $c .= opencomune_legal_heading(2, 'consenso', '6. Consenso e gestione preferenze');
    $c .= opencomune_legal_paragraph(
        'In conformità alla direttiva ePrivacy e al GDPR, i cookie non necessari richiedono <strong>consenso libero, specifico, informato e revocabile</strong> (v. sentenza CJEU Planet49, C-673/17). I cookie tecnici possono essere utilizzati senza consenso.'
    );
    $c .= opencomune_legal_paragraph(
        'Se sulla Piattaforma è presente un banner o un pannello preferenze cookie, le scelte espresse tramite quel strumento prevalgono sulle impostazioni generiche del browser per i cookie non necessari.'
    );
    $c .= opencomune_legal_paragraph(
        'Puoi revocare o modificare il consenso in qualsiasi momento riaprendo le preferenze cookie (se disponibili) o contattando il Titolare.'
    );

    $c .= opencomune_legal_heading(2, 'browser', '7. Impostazioni del browser');
    $c .= opencomune_legal_paragraph(
        'Puoi limitare o bloccare i cookie dalle impostazioni del browser (Chrome, Firefox, Safari, Edge, ecc.). La disabilitazione dei cookie <strong>tecnici</strong> può impedire login, prenotazioni, invio form o visualizzazione mappe.'
    );
    $c .= opencomune_legal_paragraph(
        'Guide utili: impostazioni «Privacy e sicurezza» / «Cookie e dati dei siti» del tuo browser; per dispositivi mobili, consulta la documentazione del sistema operativo.'
    );

    $c .= opencomune_legal_heading(2, 'diritti', '8. Diritti e contatti');
    $c .= opencomune_legal_paragraph(
        'Per informazioni sui cookie o per esercitare i diritti previsti dal GDPR (accesso, cancellazione, opposizione, ecc.) scrivi a <a href="mailto:pmt@wewelcome.it">pmt@wewelcome.it</a> con oggetto «Cookie» o consulta l\'<a href="' . esc_url($privacy_url) . '">Informativa sulla privacy</a>.'
    );
    $c .= opencomune_legal_paragraph(
        'Hai diritto di proporre reclamo al Garante per la protezione dei dati personali (<a href="https://www.garanteprivacy.it" rel="noopener noreferrer">www.garanteprivacy.it</a>).'
    );

    $c .= opencomune_legal_heading(2, 'modifiche', '9. Modifiche');
    $c .= opencomune_legal_paragraph(
        'Il Titolare può aggiornare la presente Cookie policy per riflettere nuovi cookie, fornitori o obblighi normativi. La data di ultimo aggiornamento è indicata in alto. Si consiglia di consultare periodicamente questa pagina.'
    );
    $c .= opencomune_legal_paragraph(
        '<em>Elenco cookie basato sulle funzionalità attualmente previste dalla Piattaforma; un audit periodico (es. trimestrale) è consigliato per allineare testo e consenze. Non costituisce consulenza legale.</em>'
    );

    $c .= opencomune_legal_group_close();
    return $c;
}

function opencomune_sync_cookie_policy_page(int $post_id = 659) {
    return wp_update_post([
        'ID' => $post_id,
        'post_content' => opencomune_build_cookie_policy_blocks(),
        'post_status' => 'publish',
    ], true);
}

function opencomune_sync_legal_pages_to_blocks(): array {
    $privacy_id = (int) get_option('wp_page_for_privacy_policy');
    if ($privacy_id <= 0) {
        $privacy_id = 3;
    }

    $terms_id = 645;

    $privacy_content = opencomune_build_privacy_policy_blocks();
    $terms_content = opencomune_build_terms_conditions_blocks();

    $privacy_result = wp_update_post([
        'ID' => $privacy_id,
        'post_content' => $privacy_content,
    ], true);

    $terms_result = wp_update_post([
        'ID' => $terms_id,
        'post_content' => $terms_content,
    ], true);

    $cookie_id = 659;
    $cookie_result = opencomune_sync_cookie_policy_page($cookie_id);

    return [
        'privacy_id' => $privacy_id,
        'privacy_result' => $privacy_result,
        'terms_id' => $terms_id,
        'terms_result' => $terms_result,
        'cookie_id' => $cookie_id,
        'cookie_result' => $cookie_result,
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    if (getenv('OPENCOMUNE_SYNC_COOKIE_ONLY') === '1') {
        $cookie_result = opencomune_sync_cookie_policy_page(659);
        if (is_wp_error($cookie_result)) {
            WP_CLI::error($cookie_result->get_error_message());
        }
        WP_CLI::success('Cookie policy page 659 synced as blocks.');
        return;
    }

    $results = opencomune_sync_legal_pages_to_blocks();
    WP_CLI::success(
        'Legal pages synced as blocks. Privacy ID ' . $results['privacy_id']
        . ', Terms ID ' . $results['terms_id']
        . ', Cookie ID ' . $results['cookie_id']
    );
}
