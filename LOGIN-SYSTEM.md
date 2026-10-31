# Sistema di Login Personalizzato

## 🎯 Funzionalità Implementate

### **Redirect Automatici**
- `wp-login.php` → `/login/` (pagina personalizzata)
- `wp-admin` (utenti non autenticati) → `/login/`
- Gestione corretta dei parametri `redirect_to`

### **Gestione Login**
- Form di login personalizzato con design coerente
- Gestione errori di autenticazione
- Redirect intelligente basato sui ruoli utente
- Supporto per "Ricordami"

### **Sicurezza**
- Disabilitazione accesso diretto a `wp-login.php`
- Sanitizzazione input
- Gestione sicura dei redirect

## 🔧 Come Funziona

### **1. Redirect da wp-login.php**
```php
// Quando un utente visita wp-login.php
wp-login.php → /login/

// Quando un utente fa logout
wp-login.php?loggedout=true → homepage
```

### **2. Redirect da wp-admin**
```php
// Utenti non autenticati che tentano di accedere a wp-admin
wp-admin → /login/
```

### **3. Gestione Form**
- Il form invia i dati a `/login/`
- La funzione `opencomune_handle_custom_login()` processa l'autenticazione
- **IMPORTANTE**: Controllo `is_page('login')` per evitare conflitti
- Redirect automatico dopo login riuscito

### **4. Redirect Post-Login**
- **Amministratori/Editori**: → `wp-admin`
- **Editor Turistico**: → `/dashboard-ufficio/`
- **Partner/Subscriber**: → `home_url()`
- **Redirect personalizzato**: Se presente `redirect_to`

## 📁 File Coinvolti

1. **`page-login.php`** - Template della pagina di login
2. **`functions.php`** - Funzioni di redirect e gestione login
3. **Pagina WordPress "Login"** - ID: 316

## 🚀 URL di Accesso

- **Login personalizzato**: `yoursite.com/login/`
- **Redirect automatico**: `yoursite.com/wp-login.php` → `yoursite.com/login/`
- **Admin redirect**: `yoursite.com/wp-admin/` → `yoursite.com/login/`

## ⚙️ Personalizzazioni Possibili

### **Modificare i Redirect**
```php
// In functions.php, funzione opencomune_login_redirect()
if (in_array('administrator', $user->roles)) {
    return admin_url(); // Cambia qui il redirect per admin
} else {
    return home_url(); // Cambia qui il redirect per altri utenti
}
```

### **Modificare il Design**
- Edita `page-login.php`
- Mantieni la struttura del form per il funzionamento

### **Aggiungere Logica Personalizzata**
```php
// Aggiungi nella funzione opencomune_handle_custom_login()
// Logica personalizzata dopo login riuscito
```

## 🔍 Test del Sistema

1. **Test Redirect wp-login.php**:
   - Visita `yoursite.com/wp-login.php`
   - Dovrebbe reindirizzare a `yoursite.com/login/`

2. **Test Login**:
   - Compila il form su `/login/`
   - Verifica il redirect dopo login

3. **Test wp-admin**:
   - Disconnettiti e visita `yoursite.com/wp-admin/`
   - Dovrebbe reindirizzare a `/login/`

## 🛠️ Risoluzione Problemi

### **Se wp-login.php non reindirizza**
- Verifica che le funzioni siano attive in `functions.php`
- Controlla che non ci siano conflitti con altri plugin

### **Se il form non funziona**
- Verifica che la pagina "Login" esista (ID: 316)
- Controlla che il template `page-login.php` sia presente

### **Se i redirect non funzionano**
- Verifica i permalink di WordPress
- Controlla che non ci siano cache attive

### **Se dopo il login rimani sulla pagina di login (LOOP)**
- **CAUSA**: Conflitto tra funzioni di redirect
- **SOLUZIONE**: 
  1. Verifica che `opencomune_handle_custom_login()` abbia il controllo `is_page('login')`
  2. Assicurati che `opencomune_redirect_admin()` non interferisca con il processo di login
  3. Controlla che non ci siano plugin che interferiscano con il login
- **TEST**: Usa il file `test-login.php` per diagnosticare il problema
