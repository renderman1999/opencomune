# Risoluzione Errore 401 OpenWeather API

## 🚨 **Errore 401 - Chiave API Non Valida**

L'errore 401 indica un problema con la chiave API OpenWeather. Ecco come risolverlo:

## 🔍 **Possibili Cause**

### **1. Chiave API Non Attivata**
- **Problema**: Chiave appena creata, non ancora attivata
- **Soluzione**: Attendi 2-3 ore dopo la registrazione
- **Verifica**: Controlla il tuo account OpenWeather

### **2. Chiave API Errata**
- **Problema**: Chiave copiata male o sbagliata
- **Soluzione**: Copia di nuovo la chiave dal tuo account
- **Verifica**: Controlla caratteri mancanti o extra

### **3. Chiave API Scaduta**
- **Problema**: Chiave scaduta o disattivata
- **Soluzione**: Genera una nuova chiave API
- **Verifica**: Controlla lo stato nel tuo account

### **4. Account Non Attivo**
- **Problema**: Account OpenWeather non attivato
- **Soluzione**: Attiva l'account tramite email
- **Verifica**: Controlla la tua email per link di attivazione

## 🛠️ **Passaggi per Risolvere**

### **Step 1: Verifica Account OpenWeather**
1. Vai su [OpenWeatherMap.org](https://openweathermap.org/api)
2. Fai login nel tuo account
3. Vai su [API Keys](https://home.openweathermap.org/api_keys)
4. Verifica che la chiave sia **attiva** e **valida**

### **Step 2: Copia Chiave Corretta**
1. Copia la chiave API dal tuo account
2. Assicurati di copiare tutti i caratteri
3. Non includere spazi extra

### **Step 3: Inserisci in WordPress**
1. Vai su `WordPress Admin > OpenComune > Settings`
2. Incolla la chiave nel campo "OpenWeather API Key"
3. Salva le impostazioni

### **Step 4: Test con Widget Debug**
1. Vai su `Appearance > Widgets`
2. Trascina "Debug Meteo OpenComune" in "Footer Widget"
3. Verifica la risposta dell'API

## 🔑 **Come Ottenere Chiave API**

### **Registrazione Gratuita**
1. Vai su [OpenWeatherMap.org](https://openweathermap.org/api)
2. Clicca "Sign Up" per registrarti
3. Compila il form di registrazione
4. Verifica la tua email
5. Attiva l'account

### **Generazione Chiave**
1. Fai login nel tuo account
2. Vai su [API Keys](https://home.openweathermap.org/api_keys)
3. Clicca "Generate" per creare una nuova chiave
4. Copia la chiave generata

## ⏰ **Tempi di Attivazione**

- **Chiave nuova**: 2-3 ore per l'attivazione
- **Account nuovo**: Fino a 24 ore per l'attivazione completa
- **Verifica email**: Controlla la cartella spam

## 🧪 **Test della Chiave**

### **Test Manuale**
```bash
curl "https://api.openweathermap.org/data/2.5/weather?id=3175860&appid=TU_CHIAVE_API&units=metric&lang=it"
```

### **Test con Widget Debug**
1. Usa il widget "Debug Meteo OpenComune"
2. Verifica la risposta dell'API
3. Controlla il codice di errore

## ✅ **Verifica Funzionamento**

**Risposta corretta:**
```json
{
  "cod": 200,
  "main": {
    "temp": 20.5,
    "humidity": 65
  },
  "weather": [
    {
      "description": "nuvoloso",
      "icon": "04d"
    }
  ],
  "name": "Gravina in Puglia"
}
```

**Errore 401:**
```json
{
  "cod": 401,
  "message": "Invalid API key"
}
```

## 🆘 **Se il Problema Persiste**

1. **Contatta OpenWeather**: [Support](https://openweathermap.org/help)
2. **Verifica limiti**: Controlla se hai superato i limiti gratuiti
3. **Prova altra chiave**: Genera una nuova chiave API
4. **Controlla account**: Verifica che l'account sia attivo

## 📞 **Supporto**

- **OpenWeather Support**: [help.openweathermap.org](https://help.openweathermap.org)
- **Documentazione API**: [openweathermap.org/api](https://openweathermap.org/api)
- **Account Management**: [home.openweathermap.org](https://home.openweathermap.org)

**Segui questi passaggi per risolvere l'errore 401!** 🔧
