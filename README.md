# MyDieta - WebApp Standalone

Applicazione web standalone e autonoma creata specificamente per la gestione integrata della salute, dieta, attività sportiva, pressione arteriosa e terapia farmacologica.

Ideata per funzionare localmente sia con il server PHP incorporato (`php -S`), sia all'interno di ambienti applicativi embedded come **phpdesktop-chrome**.

---

## 🗂️ Struttura del Progetto

```
d:\git\agravity/
├── api/
│   ├── db.php            # Connessione PDO SQLite, PRAGMA WAL, schema iniziale e Logger
│   ├── diet.php          # Endpoint Dieta, alimenti (grammi, note) e storico peso con orario
│   ├── sport.php         # Endpoint Attività Sportive (data, ora inizio/fine, attività, note)
│   ├── plan.php          # Endpoint Piano Alimentare Settimanale (5 pasti per 7 giorni)
│   ├── pressure.php      # Endpoint Pressione Arteriosa (Sistolica, Diastolica, Pulsazioni)
│   ├── medications.php   # Endpoint Registro Farmaci e spunte assunzioni giornaliere
│   ├── report.php        # Endpoint aggregazione dati clinici per il Report
│   └── seed.php          # Inizializzazione dati demo di benvenuto
├── data/
│   └── salute_dieta.sqlite  # Database SQLite (creato automaticamente)
├── log/
│   └── app_YYYY-MM-DD.log  # Log dettagliati di ogni operazione di backend
├── css/
│   └── style.css         # Stile moderno Vanilla CSS3 + regole @media print per referto medico
├── js/
│   └── app.js            # Frontend Vanilla JS Single Page App con Fetch API
├── index.html            # Interfaccia grafica completa a schede
└── README.md             # Documentazione d'uso
```

---

## 🌟 Funzionalità Implementate

### 1. Controllo Dieta e Peso
- **Diario Alimentare**: Possibilità di aggiungere cibi categorizzati per pasto (*Prima Colazione*, *Seconda Colazione*, *Pranzo*, *Merenda*, *Cena*, *Altro*) con campi dedicati per **grammi** e **note lunghe**. Nel riepilogo ogni alimento mostra `gr: <valore>` e `note: <testo>`.
- **Storico Peso Corporeo**: Registrazione giornaliera del peso in kg con orario di misurazione (precompilato con l'ora attuale statica), note e calcolo automatico della variazione rispetto alla pesata precedente.

### 2. Sport & Attività Fisica
- **Tracciamento Sessioni Sportive**: Registrazione di data, ora inizio, ora fine, attività svolta e note.
- **Registro Storico**: Visualizzazione tabellare di tutte le sessioni con possibilità di cancellazione rapida.

### 3. Piano Alimentare Settimanale
- Griglia settimanale completa da **Lunedì a Domenica**.
- Dettaglio per tutti i 5 pasti richiesti:
  - *Prima Colazione (elenco alimenti)*
  - *Seconda Colazione (elenco alimenti)*
  - *Pranzo (elenco alimenti)*
  - *Merenda*
  - *Cena (elenco alimenti)*
- Celle modificabili direttamente (*contenteditable*) con salvataggio immediato al cambio di focus o pulsante "Salva Tutto".

### 4. Monitoraggio Pressione Arteriosa (PA)
- Registrazione di: Data e ora, **Sistolica (Massima)**, **Diastolica (Minima)**, **Pulsazioni (bpm)** e note.
- Badge diagnostico automatico secondo linee guida cliniche (*Ottimale*, *Normale*, *Normale-Alta*, *Ipertensione Gr.1*, *Ipertensione Gr.2*).
- Calcolo in tempo reale delle medie cliniche di periodo.

### 5. Registro Farmaci & Terapia
- Gestione anagrafica della terapia: Nome farmaco, dosaggio, orario di assunzione e note d'uso.
- Modale per inserire o modificare i farmaci.
- **Checklist di somministrazione giornaliera**: Caselle di spunta con orario e stato d'assunzione per ogni giornata selezionabile.

### 5. Stampa Report / Esportazione PDF (@media print)
- Pulsante dedicato **"🖨️ Stampa Report Medico"** nell'header e nella scheda anteprima.
- Genera una stampa pulita tramite `@media print`, nascondendo la UI di navigazione e strutturando un dossier elegante pronto da stampare o salvare come PDF per il medico curante o nutrizionista.

---

## 🚀 Avvio Locale

### Con PHP integrato:
```powershell
php -S 127.0.0.1:8080 -t "d:\git\agravity"
```
Apri il browser su `http://127.0.0.1:8080`.

### Con phpdesktop-chrome:
Copia la cartella del progetto dentro la cartella `www/` di phpdesktop-chrome oppure punta la direttiva `web_server.document_root` del file `settings.json` di phpdesktop verso `d:\git\agravity`.
