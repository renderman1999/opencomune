# OpenComune – Piattaforma WordPress per il marketing territoriale

OpenComune è un tema WordPress pensato come **piattaforma di pubblicazione e valorizzazione del territorio**: catalogo esperienze, prenotazioni, mappe, contenuti multilingua e strumenti per enti, destination manager, uffici turistici e **partner territoriali** (guide, associazioni, operatori locali).

Non è solo un gestionale per uffici comunali: unisce **vetrina pubblica**, **area riservata partner** e **pannello di coordinamento** per chi promuove il territorio su canali propri e integrazioni API.

## Funzionalità principali

### Pubblicazione e catalogo
- CPT **Esperienze** (`esperienze`) e profili **Partner** (`partner`)
- URL pubbliche: `/esperienze/slug/` e archivi partner/autore
- API REST `opencomune/v1` per integrazioni esterne (portali, app, DMS)
- Traduzioni IT/EN e strumenti di localizzazione contenuti

### Prenotazioni e pagamenti
- Calendario disponibilità, eventi e slot
- Checkout online, richieste on-request, email transazionali HTML
- Dashboard prenotazioni per partner e staff

### Scoperta del territorio
- Home, mappe esperienze, ricerca e autocomplete
- **Pianificatore itinerari** (wizard pubblico + gestione catalogo POI/città in dashboard)
- Recensioni e elementi di social proof

### Area riservata (dashboard frontend)
- Accesso da pagine dedicate (non wp-admin classico per i ruoli operativi)
- Wizard creazione/modifica esperienze, incassi, commissioni, operazioni di piattaforma
- Protezione form con Cloudflare Turnstile (ove configurato)

## Ruoli utente (sintesi)

| Ruolo | Uso tipico |
|--------|------------|
| **Amministratore / staff piattaforma** | Configurazione globale, partner, categorie, pianificatore, incassi |
| **Partner territoriale** | Pubblicazione e gestione delle proprie esperienze e prenotazioni |
| **editor_turistico** | Ruolo legacy/coordinamento contenuti (in evoluzione verso modello partner/ufficio) |

I permessi effettivi dipendono dalle capability WordPress assegnate al ruolo; vedi `docs/documentazione.md` per architettura e moduli.

## Documentazione tecnica

Guida backend/frontend, API, template e indice funzioni:

- [`docs/documentazione.md`](docs/documentazione.md)

## Stack e requisiti

- WordPress recente, PHP 8+
- SMTP configurabile per email di sistema
- Chiavi opzionali: Google Maps, Cloudflare Turnstile, gateway pagamento (es. SumUp), API REST marketing

## Contatti e demo

- Email: renderman1999@gmail.com
- Repository: https://github.com/renderman1999/opencomune/
- Demo: https://betadev.it/explora/
