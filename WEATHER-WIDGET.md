# Widget Meteo OpenWeather

## 🌤️ **Widget Meteo Personalizzato**

Widget WordPress per mostrare le condizioni meteo utilizzando l'API di OpenWeather.

## 🎯 **Funzionalità**

### **Dati Meteo**
- **Temperatura** in gradi Celsius
- **Descrizione** delle condizioni (es. "Nuvoloso", "Soleggiato")
- **Icona meteo** dinamica da OpenWeather
- **Umidità** in percentuale
- **Velocità vento** in km/h
- **Nome città** personalizzabile

### **Caratteristiche Tecniche**
- **Cache intelligente**: Dati aggiornati ogni 30 minuti
- **API gratuita**: Utilizza OpenWeather API (gratuita)
- **Design responsive**: Adattato al tema OpenComune
- **Gestione errori**: Fallback elegante in caso di problemi
- **Configurazione semplice**: Tramite WordPress Admin

## 🔧 **Configurazione**

### **1. Chiave API OpenWeather**
1. Vai su `WordPress Admin > OpenComune > Settings`
2. Inserisci la chiave API OpenWeather nel campo dedicato
3. Salva le impostazioni

### **2. Aggiungi Widget al Footer**
1. Vai su `WordPress Admin > Appearance > Widgets`
2. Trascina "Meteo OpenComune" nell'area "Footer Widget"
3. Configura titolo e città
4. Salva

### **3. Personalizzazione Widget**
- **Titolo**: Nome del widget (es. "Meteo")
- **ID Città**: ID OpenWeather della città (default: "3175860" = Gravina in Puglia)

## 🎨 **Design**

### **Stile Widget**
- **Sfondo**: Gradiente blu (`from-blue-500 to-blue-600`)
- **Testo**: Bianco con trasparenze
- **Layout**: Card con informazioni organizzate
- **Icone**: Bootstrap Icons per umidità e vento
- **Responsive**: Adattato per mobile e desktop

### **Struttura Visuale**
```
┌─────────────────────────────────┐
│ [Città]              [Icona] °C │
│ [Descrizione]                   │
│                                 │
│ Umidità: XX%    Vento: XX km/h  │
│                                 │
│     Dati aggiornati ogni 30m    │
└─────────────────────────────────┘
```

## 🚀 **Utilizzo**

### **Posizionamento**
Il widget è progettato per essere utilizzato nell'area "Footer Widget" del footer del sito.

### **Configurazione Città**
- **Default**: "3175860" (Gravina in Puglia)
- **Personalizzabile**: Qualsiasi ID città OpenWeather
- **Formato**: ID numerico (es. "3175860" per Gravina, "3169070" per Roma)

## 🔑 **API OpenWeather**

### **Come Ottenere la Chiave**
1. Vai su [OpenWeatherMap.org](https://openweathermap.org/api)
2. Registrati gratuitamente
3. Ottieni la chiave API
4. Inseriscila nelle impostazioni del tema

### **Limiti API Gratuita**
- **1000 chiamate/giorno**
- **Cache 30 minuti** per ottimizzare le chiamate
- **Dati sufficienti** per uso normale

## 🛠️ **Sviluppo**

### **File Widget**
- **Percorso**: `/widgets/weather-widget.php`
- **Classe**: `OpenComune_Weather_Widget`
- **Registrazione**: Automatica in `functions.php`

### **Cache System**
- **Transient**: `weather_[hash]`
- **Durata**: 30 minuti
- **Chiave**: Basata su città + API key

### **Gestione Errori**
- **API non disponibile**: Messaggio di errore
- **Chiave mancante**: Istruzioni per configurazione
- **Città non trovata**: Fallback a città predefinita

## 📱 **Responsive Design**

- **Mobile**: Layout compatto
- **Desktop**: Layout espanso
- **Icone**: Scalabili e chiare
- **Testo**: Leggibile su tutti i dispositivi

## 🔄 **Aggiornamenti**

Il widget si aggiorna automaticamente ogni 30 minuti per:
- **Ridurre chiamate API**
- **Mantenere prestazioni**
- **Aggiornare dati meteo**

**Il widget meteo è ora pronto per essere utilizzato nel footer!** 🌤️
