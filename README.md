# OpenComune - Tema WordPress per Uffici Turistici Comunali

## Panoramica

OpenComune è un tema WordPress derivato da MyGuideLab, convertito per essere utilizzato da uffici turistici comunali. Il tema permette la gestione di esperienze turistiche in modo centralizzato, mantenendo le funzionalità principali del template originale ma semplificando la gestione utenti.

## Modifiche Principali

### 1. Sistema Utenti
- **Ruolo**: `editor_turistico` (sostituisce `guida`)
- **Accesso**: Dashboard frontend personalizzata (non wp-admin)
- **Redirect**: Automatico a `/dashboard-ufficio/`

### 2. Post Types
- **Tour** → **Esperienze** (`esperienze`)
- **Guide** → **Partner** (`partner`)
- **URL**: `/esperienze/slug/` e `/partner/slug/`

### 3. Template Rinominati
- `single-tour.php` → `single-esperienza.php`
- `page-mappa-tour.php` → `page-mappa-esperienze.php`
- `page-nuovo-tour.php` → `page-nuova-esperienza.php`
- `page-modifica-tour.php` → `page-modifica-esperienza.php`
- `single-guide.php` → `single-partner.php`
- `templates/dashboard-guide.php` → `templates/dashboard-ufficio.php`

### 4. Funzionalità Rimosse
- ❌ Sistema registrazione guide
- ❌ Sistema sottoscrizioni/pagamenti
- ❌ Upload documenti professionali
- ❌ Verifica account guide
- ❌ Limiti numero tour

### 5. Funzionalità Mantenute
- ✅ Sistema esperienze completo (CRUD)
- ✅ Calendario disponibilità + Google Calendar
- ✅ Sistema prenotazioni online
- ✅ Sistema recensioni
- ✅ Mappa interattiva con filtri
- ✅ Ricerca e autocomplete
- ✅ Dashboard frontend custom

### 6. Nuove Configurazioni
- **Nome Comune**: Personalizzazione branding
- **Email Ufficio**: Destinazione prenotazioni
- **Telefono/Indirizzo**: Contatti ufficio
- **Orari Apertura**: Informazioni pubbliche

## Installazione

1. **Backup**: Il tema originale MyGuideLab è stato mantenuto intatto
2. **Attivazione**: Attivare il tema OpenComune da WordPress Admin
3. **Migrazione**: Eseguire `migration-ufficio-turistico.php` (una volta)
4. **Configurazione**: Impostare i dati ufficio turistico in OpenComune Settings

## File di Migrazione

### `migration-ufficio-turistico.php`
Script per migrare il database da MyGuideLab a OpenComune:
- Rimuove tabella sottoscrizioni
- Converte post type `tour` → `esperienze`
- Converte post type `guide` → `partner`
- Aggiorna tassonomie
- Rimuove user meta collegamenti

## Struttura Database

### Tabelle Mantenute
- `wp_tour_calendari` - Calendario disponibilità
- `wp_comuni_italiani` - Database comuni per autocomplete

### Tabelle Rimosse
- `wp_sottoscrizioni` - Sistema abbonamenti

## Configurazione

### Impostazioni Tema (OpenComune Settings)
- **Google Maps API Key**: Per mappe e geocoding
- **Google Calendar**: OAuth per sincronizzazione
- **Nome Comune**: Branding personalizzato
- **Email Ufficio**: Destinazione prenotazioni
- **Contatti**: Telefono, indirizzo, orari

### Ruoli e Permessi
- **editor_turistico**: Gestione esperienze, accesso dashboard
- **partner**: Preparato per futura integrazione

## Architettura Futura

### Partner Autonomi
Il sistema è preparato per permettere ai partner di:
- Collegarsi autonomamente
- Inserire le proprie esperienze
- Gestire prenotazioni

### Campi Meta Preparati
- `organizzatore_tipo`: 'ufficio' | 'partner' | 'collaborazione'
- `partner_id`: Collegamento esperienza-partner
- `_partner_email`, `_partner_telefono`: Contatti partner

## Sicurezza

- Nonce verification su tutti i form
- Sanitizzazione input completa
- Controllo capabilities utente
- Escape output sistematico
- Upload file con whitelist

## Performance

- Cache WordPress per query pesanti
- Lazy loading immagini
- CDN per librerie esterne
- Query ottimizzate con indici DB

## Supporto

Per problemi o domande:
- Email: info@opencomune.com
- Documentazione: [Link alla documentazione]
- GitHub: [Link al repository]

## Changelog

### v1.0.0
- Conversione da MyGuideLab
- Rimozione sistema multi-guida
- Implementazione single-tenant
- Dashboard semplificata
- Configurazione ufficio turistico
