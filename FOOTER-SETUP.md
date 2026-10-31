# Setup Footer Personalizzato

## 🎯 **Struttura Footer**

Il footer è composto da:

### **Header con Logo**
- Logo del sito (se configurato) o icona fallback
- Nome del sito WordPress
- Descrizione del sito WordPress

### **Tre Colonne Principali**

#### **Colonna 1: CONTATTI**
- **Fonte dati**: Impostazioni OpenComune (`/admin.php?page=opencomune-settings`)
- **Campi utilizzati**:
  - `opencomune_indirizzo_ufficio`
  - `opencomune_telefono_ufficio`
  - `opencomune_email_ufficio`
  - `opencomune_orari_apertura`

#### **Colonna 2: LINK UTILI**
- **Menu WordPress**: `footer` (se configurato)
- **Fallback**: Menu predefinito con link principali
- **Gestione**: `Appearance > Menus` in WordPress Admin

#### **Colonna 3: INFORMAZIONI**
- **Widget Area**: `footer-widget`
- **Gestione**: `Appearance > Widgets` in WordPress Admin
- **Default**: Messaggio "Area widget disponibile"

### **Barra Inferiore**
- **Colore**: Blu più scuro (`bg-blue-800`)
- **Contenuto**: Link legali + copyright
- **Layout**: Responsive (centrato su mobile, distribuito su desktop)

## 🔧 **Configurazione**

### **1. Logo e Info Sito**
Vai su `WordPress Admin > Appearance > Customize > Site Identity`:
- Logo del sito (se desiderato)
- Nome del sito
- Descrizione del sito

### **2. Impostazioni Ufficio Turistico**
Vai su `WordPress Admin > OpenComune > Settings` e compila:
- Email Ufficio
- Telefono Ufficio
- Indirizzo Ufficio
- Orari Apertura

### **3. Menu Footer**
Vai su `WordPress Admin > Appearance > Menus`:
1. Crea un nuovo menu chiamato "Footer"
2. Aggiungi le pagine/link desiderati
3. Assegna il menu alla posizione "Footer"

### **4. Widget Footer**
Vai su `WordPress Admin > Appearance > Widgets`:
1. Trova "Footer Widget" nella sidebar
2. Aggiungi widget (testo, HTML, immagini, ecc.)
3. Personalizza il contenuto

## 🎨 **Personalizzazione**

### **Colori**
- **Footer principale**: `#004d99` (blu personalizzato)
- **Barra inferiore**: `#003366` (blu più scuro)
- **Testo**: `text-white`
- **Link**: `text-blue-200` con hover `text-white`

### **Layout Responsive**
- **Mobile**: Colonne in stack verticale
- **Desktop**: 3 colonne affiancate
- **Barra inferiore**: Centrata su mobile, distribuita su desktop

## 📋 **Funzionalità**

- ✅ **Dati dinamici** dalle impostazioni OpenComune
- ✅ **Menu WordPress** con fallback
- ✅ **Widget area** personalizzabile
- ✅ **Design responsive** con Tailwind CSS
- ✅ **Link legali** predefiniti
- ✅ **Copyright dinamico** con anno corrente

## 🚀 **Utilizzo**

Il footer si carica automaticamente su tutte le pagine del sito. I contenuti vengono popolati dinamicamente dalle impostazioni e dai menu/widget configurati in WordPress Admin.
