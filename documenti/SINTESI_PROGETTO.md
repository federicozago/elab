### Sintesi del Progetto - Sistema di Ordinamento Postale

---

### Scopo del Progetto

Il progetto offre un servizio di **ordinamento postale dei dati** secondo i parametri di Poste Italiane. Permette di ordinare i dati secondo le regole di diversi prodotti postali come:
- **Posta Massiva**
- **Raccomandata Market**
- **Target**
- Altri prodotti definiti nell'array `elaborazioni` (file `temp/elab.php`)

Il sistema consente di:
1. Importare dati da file Excel/CSV in database MySQL
2. Creare lavori con una o più elaborazioni
3. Ordinare i dati secondo le regole del prodotto postale selezionato
4. Eseguire azioni aggiuntive (generazione etichette con download diretto, distinte, prenotazioni)
5. Gestire il ciclo di vita delle elaborazioni (attive/concluse)

---

### Prodotti Postali Disponibili

I prodotti postali sono definiti nel file `temp/elab.php` nell'array `elaborazioni`:

#### Target
- **Genera etichette** (endpoint: `genera_etichette.php`)
- **Prenotazione** (endpoint: `Prenota.php`, parametri: data)

#### Massiva
- **Genera etichette** (endpoint: `genera_etichette.php`)

Ogni prodotto postale può avere azioni specifiche oltre all'ordinamento base.

---

## Architettura e Infrastruttura

Il progetto è basato su un'architettura moderna che separa il frontend dal backend, gestita tramite containerizzazione.

### Tecnologie Core
- **Frontend:** [Vue 3](https://vuejs.org/) con [Quasar Framework](https://quasar.dev/) (Vite).
- **Backend:** PHP 8.x (API REST).
- **Database:** MySQL.
- **Containerizzazione:** [Docker](https://www.docker.com/) con Docker Compose.
- **Package Management:** [Yarn](https://yarnpkg.com/) (v4+) per il frontend, [Composer](https://getcomposer.org/) per il backend.

### Configurazione Docker
Il backend e l'ambiente PHP sono isolati all'interno di container Docker definiti nella cartella `php-docker-boilerplate/`.
- **Dockerfile:** Definisce l'immagine PHP-Apache con le estensioni necessarie (pdo_mysql, zip, gd, ecc.).
- **docker-compose.yml:** Configura i servizi, i volumi per la persistenza dei dati e la comunicazione tra i container.

### Gestione Frontend (Yarn)
Il frontend utilizza Yarn come package manager. I comandi principali definiti in `package.json` sono:
- `yarn dev`: Avvia il server di sviluppo Quasar.
- `yarn build`: Genera la build di produzione.
- `yarn lint`: Esegue il controllo del codice con ESLint.

### Struttura dei File di Configurazione
Tutti i file di configurazione necessari al funzionamento dell'infrastruttura sono presenti nella root del progetto o nelle sottocartelle dedicate:
- `package.json` / `yarn.lock`: Dipendenze e script frontend.
- `quasar.config.js`: Configurazione specifica del framework Quasar.
- `php-docker-boilerplate/`: Contiene `Dockerfile`, `docker-compose.yml` e le configurazioni PHP (`php.ini`).
- `.env`: Variabili d'ambiente per la configurazione dei servizi Docker.

---

## Pages (Pagine)

### 1. **IndexPage.vue** (Pagina Principale)
**Path:** `src/pages/IndexPage.vue`

**Scopo:** Pagina principale del sistema che gestisce la creazione di nuove elaborazioni e il monitoraggio dei lavori.

**Funzionalità:**
- **Creazione elaborazione rapida:**
  - Selezione di un lavoro esistente
  - Upload file dati (Excel/CSV)
  - Indicazione folder Z (percorso di output)
  - Indicazione commessa (id_flusso)
  - Pulsante "Nuovo lavoro" per creare un lavoro da zero

- **Visualizzazione elaborazioni in corso:**
  - Tabella con lavori attivi raggruppati per nome lavoro
  - Ogni lavoro può contenere più elaborazioni (dettagli espandibili)
  - Per ogni elaborazione mostra:
    - ID elaborazione
    - Nome elaborazione
    - Tipo spedizione (prodotto postale)
    - Base dati associata
    - Folder Z
    - Stato ordinamento (pulsante "Ordina" / "In elaborazione" / "Elaborato")
    - Query SQL per visualizzare i dati ordinati
    - Stato prenotazione (pulsante "Prenota" con data / "In attesa" / "Prenotato")
    - Pulsante "Azioni" per eseguire azioni specifiche (es. etichette con download diretto e suggerimento cartella di salvataggio)
    - Pulsante "Chiudi Elab" per chiudere l'elaborazione

- **Visualizzazione elaborazioni concluse:**
  - Tabella con lavori chiusi
  - Possibilità di riaprire elaborazioni concluse
  - **Riesporta:** Pulsante per riesportare il database in formato ZIP (richiama `chiudi_elaborazione.php`)
  - **ELIMINA DATI:** Pulsante per eliminare i record dell'elaborazione sia dalla tabella dati che dalla tabella ordinati (richiama `elimina_elaborazione.php`)

- **Azioni disponibili:**
  - `lanciaElaborazione()`: Avvia l'ordinamento dei dati
  - `lanciaPrenotazione()`: Prenota la spedizione con data
  - `lanciaAzione()`: Esegue azioni specifiche (etichette, distinte, chiusura lavoro con download ZIP, ecc.)
  - `chiudiElaborazione()`: Chiude un'elaborazione (non più visibile in "in corso")
  - `riapriElaborazione()`: Riapre un'elaborazione chiusa
  - `riesporta()`: Riesporta un'elaborazione conclusa in formato ZIP
  - `confermaElimina()`: Elimina i dati relativi a un'elaborazione specifica
  - `copiaQuery()`: Copia la query SQL negli appunti

---

### 2. **creazione_lavoro.vue** (Creazione/Modifica Lavoro)
**Path:** `src/pages/creazione_lavoro.vue`

**Scopo:** Gestisce la creazione di nuovi lavori o la modifica di lavori esistenti. Un lavoro è caratterizzato da una base dati MySQL e una o più elaborazioni.

**Funzionalità:**
- **Selezione/Creazione base dati:**
  - Selezione da basi dati esistenti
  - Pulsante "Nuova Base Dati" per crearne una nuova

- **Configurazione lavoro:**
  - Nome lavoro
  - Una o più elaborazioni (sotto-elaborazioni)

- **Per ogni elaborazione:**
  - **Nome elaborazione:** Identifica la sotto-elaborazione
  - **Tipo spedizione:** Selezione del prodotto postale (target, massiva, ecc.)
  - **Configurazione:** Selezione di una configurazione esistente o creazione di una nuova
  - **WHERE SQL:** Filtro per selezionare parte dei dati dalla base dati
    - Se ci sono più elaborazioni, viene creata una vista MySQL per ogni elaborazione
    - Il campo WHERE permette di specificare quali record selezionare
    - Mostra suggerimenti con i campi disponibili dall'intestazione della base dati
  - **Pulsanti:** Elimina elaborazione / Aggiungi elaborazione

- **Modalità:**
  - **Creazione:** Crea un nuovo lavoro
  - **Modifica:** Modifica un lavoro esistente (selezionabile da dropdown)

- **Validazioni:**
  - Controllo che i nomi delle elaborazioni non siano duplicati
  - Controllo che le viste non esistano già
  - Validazione SQL sicura per il campo WHERE

- **Flusso:**
  - Dopo la creazione, reindirizza a IndexPage con il lavoro appena creato preselezionato
  - Integrazione con creazione_BaseDati (può tornare da lì con dati precompilati)

---

### 3. **creazione_BaseDati.vue** (Creazione Base Dati)
**Path:** `src/pages/creazione_BaseDati.vue`

**Scopo:** Gestisce la creazione di nuove basi dati MySQL importando file Excel o CSV.

**Funzionalità:**
- **Visualizzazione basi dati esistenti:**
  - Dropdown con tutte le basi dati create
  - Possibilità di visualizzare i dettagli di una base dati esistente

- **Creazione nuova base dati:**
  - **Nome base dati:** Nome univoco per la nuova base dati
  - **Intestazione presente:** Toggle per indicare se il file ha intestazione
  - **Upload file:** Caricamento file Excel/CSV
    - Upload immediato per analizzare l'intestazione
    - Mostra i campi disponibili dopo l'upload
  - **Mappatura campi obbligatori:**
    - Campo CAP
    - Campo Località
    - Campo Provincia
    - (Necessari per l'ordinamento postale)

- **Flusso:**
  - Upload del file → Analisi intestazione → Mappatura campi → Creazione base dati MySQL
  - Se chiamata da creazione_lavoro, ritorna automaticamente con i dati della base dati creata

---

### 4. **creazione_configurazione.vue** (Creazione Configurazione)
**Path:** `src/pages/creazione_configurazione.vue`

**Scopo:** Pagina dedicata alla creazione di configurazioni per i diversi prodotti postali.

**Funzionalità:**
- Creazione di configurazioni specifiche per ogni tipo di spedizione
- Definizione dei parametri di ordinamento
- Salvataggio delle configurazioni per riutilizzo futuro

**Nota:** Questa pagina definisce le regole di ordinamento specifiche per ogni prodotto postale.

---

### 5. **ErrorNotFound.vue** (Pagina Errore 404)
**Path:** `src/pages/ErrorNotFound.vue`

**Scopo:** Pagina di errore mostrata quando l'utente accede a un percorso non esistente.

---

## Components (Componenti)

### Componenti Principali

#### 1. **creazione_configurazione.vue**
**Path:** `src/components/creazione_configurazione.vue`

**Scopo:** Componente form per la creazione/modifica di configurazioni di ordinamento.

**Funzionalità:**
- Form riutilizzabile per creare configurazioni
- Utilizzato sia nella pagina dedicata che nel dialog di creazione_lavoro
- Gestisce i parametri specifici per ogni tipo di spedizione
- Emette evento `saved` quando la configurazione viene salvata

---

#### 2. **NewConfigTarget.vue**
**Path:** `src/components/NewConfigTarget.vue`

**Scopo:** Componente specifico per la configurazione del prodotto postale "Target".

**Funzionalità:**
- Form specializzato per le configurazioni Target
- Gestisce parametri specifici del prodotto Target
- Integrato nel sistema di creazione configurazioni

---

#### 3. **EssentialLink.vue**
**Path:** `src/components/EssentialLink.vue`

**Scopo:** Componente per i link di navigazione nel menu/sidebar.

**Funzionalità:**
- Rendering dei link di navigazione
- Gestione icone e titoli
- Utilizzato nel layout dell'applicazione

---

### Componenti Forms (Riutilizzabili)

Tutti i componenti form sono nella cartella `src/components/forms/` e forniscono elementi UI standardizzati con validazione integrata.

#### 1. **BaseForm.vue**
**Scopo:** Wrapper per form con gestione submit e validazione.
- Gestisce l'invio del form
- Validazione automatica dei campi
- Pulsante submit personalizzabile (prop `labelInvia`)

#### 2. **BaseInput.vue**
**Scopo:** Campo input di testo con validazione.
- Input testuale standard
- Supporto regole di validazione
- Suggerimenti automatici (autocomplete)
- Tooltip per informazioni aggiuntive

#### 3. **BaseSelect.vue**
**Scopo:** Dropdown/Select con validazione.
- Selezione da lista di opzioni
- Formato opzioni: `{ value, label }`
- Supporto validazione
- Utilizzato per selezione lavori, basi dati, configurazioni, tipi spedizione

#### 4. **BaseFile.vue**
**Scopo:** Input per upload file.
- Selezione file Excel/CSV
- Supporto validazione
- Indicatore di caricamento
- Gestione upload con FormData

#### 5. **BaseBtn.vue**
**Scopo:** Pulsante standardizzato.
- Pulsante riutilizzabile con stili consistenti
- Supporto icone, colori, dimensioni
- Gestione eventi click

#### 6. **BaseDatePicker.vue**
**Scopo:** Selettore data.
- Selezione date per prenotazioni
- Utilizzato per indicare la data di spedizione
- Integrato con le azioni che richiedono parametro data

#### 7. **BaseToggle.vue**
**Scopo:** Switch on/off.
- Toggle booleano
- Utilizzato per "Intestazione presente" in creazione base dati

#### 8. **BaseRadio.vue**
**Scopo:** Radio button per selezione singola.
- Selezione esclusiva tra opzioni
- Validazione integrata

---

## Flusso di Lavoro Tipico

### Scenario 1: Creazione Nuovo Lavoro Completo

1. **IndexPage** → Click "Nuovo lavoro"
2. **creazione_lavoro** → Click "Nuova Base Dati"
3. **creazione_BaseDati** → Upload file Excel/CSV → Mappa campi → Crea base dati
4. **creazione_lavoro** (ritorno automatico) → Compila nome lavoro
5. **creazione_lavoro** → Seleziona tipo spedizione → Click "Nuova configurazione"
6. **Dialog configurazione** → Compila parametri → Salva
7. **creazione_lavoro** → Indica WHERE per filtrare dati → Salva lavoro
8. **IndexPage** (ritorno automatico) → Lavoro preselezionato

### Scenario 2: Elaborazione Rapida con Lavoro Esistente

1. **IndexPage** → Seleziona lavoro esistente
2. **IndexPage** → Upload nuovo file dati
3. **IndexPage** → Indica folder Z e commessa
4. **IndexPage** → Click "Elabora"
5. **IndexPage** → Nella tabella, click "Ordina" per avviare ordinamento
6. **IndexPage** → Quando elaborato, click "Azioni" per generare etichette/distinte
7. **IndexPage** → Eventualmente "Prenota" con data spedizione
8. **IndexPage** → Click "Chiudi" quando completato

### Scenario 3: Lavoro Multi-Elaborazione

1. **creazione_lavoro** → Crea prima elaborazione con WHERE specifico
2. **creazione_lavoro** → Click "Aggiungi" per aggiungere seconda elaborazione
3. **creazione_lavoro** → Configura seconda elaborazione con WHERE diverso
4. **creazione_lavoro** → Salva (vengono create viste MySQL separate)
5. **IndexPage** → Il lavoro appare con dettagli espandibili
6. **IndexPage** → Ogni elaborazione può essere ordinata e gestita indipendentemente

---

## Concetti Chiave

### Base Dati
- Tabella MySQL contenente i dati importati da Excel/CSV
- Contiene tutti i record del file originale
- Campi obbligatori: CAP, Località, Provincia
- Riutilizzabile per più lavori

### Lavoro
- Contenitore logico per una o più elaborazioni
- Associato a una base dati specifica
- Ha un nome identificativo
- Può essere chiuso/riaperto

### Elaborazione
- Sotto-insieme di un lavoro
- Seleziona parte dei dati tramite clausola WHERE
- Associata a un tipo spedizione (prodotto postale)
- Associata a una configurazione di ordinamento
- Se ci sono più elaborazioni nello stesso lavoro, viene creata una vista MySQL

### Configurazione
- Definisce le regole di ordinamento per un prodotto postale
- Riutilizzabile per più elaborazioni
- Specifica per ogni tipo spedizione

### Tipo Spedizione (Prodotto Postale)
- Definisce il prodotto di Poste Italiane (target, massiva, ecc.)
- Ogni tipo ha azioni specifiche disponibili
- Definito in `temp/elab.php` → `elaborazioni`

- **Note Tecniche:** 
  - I PDF generati vengono salvati temporaneamente in `php-docker-boilerplate/src/temp_pdf/` e cancellati dopo il download.
  - La UI del dialog azioni mostra il percorso suggerito (folder_cliente + folder_z) con possibilità di copia rapida.
  - Il riconoscimento delle azioni PDF avviene tramite la proprietà `output: "pdf"` nell'array delle azioni.

### Stati Elaborazione
- **Ordinamento:** 0 = Da ordinare, 1 = In elaborazione, 2+ = Elaborato
- **Prenotazione:** 2 = Da prenotare, 3 = In attesa OK Poste, 4 = Prenotato
- **Lavoro:** Attivo / Concluso (chiuso)

---

## Struttura Dati

### Query SQL Risultante
Dopo l'ordinamento, i dati possono essere estratti con una query che unisce:
- Tabella base dati originale (o vista se multi-elaborazione)
- Tabella ordinati generata dal sistema

Esempio:
```sql
SELECT * FROM `nome_base_dati_nome_elaborazione` e
    JOIN `ordinati_tipo_spedizione_nome_base_dati` o
         ON e.id=o.c1
ORDER BY progr
```

Questa query è visualizzabile tramite il pulsante "SQL" in IndexPage.

---

## Note Tecniche

- **Framework:** Vue 3 con Quasar Framework
- **Backend:** PHP (file .php nella cartella server)
- **Database:** MySQL
- **Upload:** Gestito tramite FormData e multipart/form-data
- **Store:** Utilizza Pinia (fileStore per gestire file tra pagine)
- **Routing:** Vue Router per navigazione tra pagine
- **Validazione:** Composable `rules.js` con regole riutilizzabili
- **Gestione errori:** Composable `cassettaAttrezzi.ts` con `gestioneErrore()` e `messaggioPositivo()`

---

## Endpoint PHP (Backend API)

**Path:** `php-docker-boilerplate/src/`

Tutti gli endpoint PHP seguono un pattern comune:
- Gestione CORS per permettere chiamate dal frontend
- Utilizzo della libreria CASSETTA_ATTREZZI per operazioni comuni
- Validazione input e gestione errori con try-catch
- Risposta JSON con struttura `{ success: boolean, message?: string, data?: any }`

### Endpoint di Prelievo Dati (GET/Lettura)

#### **preleva_lavori.php**
**Scopo:** Restituisce la lista di tutti i lavori disponibili.
- **Output:** Array di lavori con `id` (value) e `nome_lavoro` (label)
- **Utilizzo:** Popola il dropdown di selezione lavoro in IndexPage e creazione_lavoro

#### **preleva_lavoro.php**
**Scopo:** Restituisce i dettagli completi di un lavoro specifico con tutte le sue elaborazioni.
- **Input:** `id_lavoro`
- **Output:** Dati del lavoro (nome, base dati, elaborazioni con configurazioni)
- **Utilizzo:** Caricamento dati per modifica lavoro esistente

#### **preleva_elaborazioni_in_corso.php**
**Scopo:** Restituisce tutte le elaborazioni attive raggruppate per lavoro.
- **Output:** Array di lavori con dettagli elaborazioni (stato ordinamento, prenotazione, folder Z, ecc.)
- **Utilizzo:** Popola la tabella "Elaborazioni in corso" in IndexPage

#### **preleva_elaborazioni_concluse.php**
**Scopo:** Restituisce tutte le elaborazioni chiuse/concluse.
- **Output:** Array di elaborazioni concluse
- **Utilizzo:** Popola la tabella "Elaborazioni concluse" in IndexPage

#### **preleva_basi_dati.php**
**Scopo:** Restituisce la lista di tutte le basi dati create.
- **Output:** Array di basi dati con `id`, `nome_base_dati`, `intestazione`
- **Utilizzo:** Popola dropdown selezione base dati in creazione_lavoro e creazione_BaseDati

#### **preleva_base_dati.php**
**Scopo:** Restituisce i dettagli di una base dati specifica.
- **Input:** `id_base_dati`
- **Output:** Dettagli base dati (nome, intestazione, campi mappati)
- **Utilizzo:** Visualizzazione dettagli base dati esistente

#### **preleva_configurazioni.php**
**Scopo:** Restituisce le configurazioni disponibili per un tipo di spedizione.
- **Input:** `tipo_spedizione`
- **Output:** Array di configurazioni per il tipo spedizione specificato
- **Utilizzo:** Popola dropdown configurazioni in creazione_lavoro

#### **preleva_configurazione.php**
**Scopo:** Restituisce i dettagli di una configurazione specifica.
- **Input:** `id_configurazione`, `tipo_spedizione`
- **Output:** Dettagli completi della configurazione
- **Utilizzo:** Caricamento dati per modifica configurazione

#### **preleva_tipi_spedizione.php**
**Scopo:** Restituisce i tipi di spedizione disponibili (prodotti postali).
- **Output:** Array di tipi spedizione (target, massiva, ecc.)
- **Utilizzo:** Popola dropdown tipo spedizione in creazione_lavoro

#### **preleva_azioni_elaborazioni.php**
**Scopo:** Restituisce le azioni disponibili per ogni tipo di spedizione.
- **Output:** Array associativo `tipo_spedizione => azioni` dall'array `elaborazioni` definito nel file `temp/elab.php`
- **Utilizzo:** Popola il dialog "Azioni" in IndexPage

#### **preleva_viste.php**
**Scopo:** Restituisce la lista delle viste MySQL esistenti.
- **Output:** Array di nomi viste
- **Utilizzo:** Validazione per evitare duplicati in creazione multi-elaborazione

---

### Endpoint di Creazione Dati (POST/Scrittura)

#### **crea_base_dati.php**
**Scopo:** Crea una nuova base dati MySQL importando i dati da file Excel/CSV.
- **Input:** `nome_base_dati`, `file_base_dati` (nome file già caricato), `intestazione_si_no`, `campo_cap`, `campo_localita`, `campo_provincia`, `intestazione`
- **Processo:**
  - Legge il file temporaneo caricato precedentemente
  - Crea tabella MySQL con struttura dinamica basata sull'intestazione
  - Importa tutti i record dal file
  - Salva metadati nella tabella `base_dati`
- **Output:** `id_base_dati`, `nome_base_dati`, `intestazione`
- **Libreria utilizzata:** `Csv`, `Excel`, `Gestione_db`, `Array_php`

#### **crea_lavoro.php**
**Scopo:** Crea un nuovo lavoro con una o più elaborazioni.
- **Input:** `id_base_dati`, `nome_lavoro`, `elaborazioni[]` (array con nome, tipo_spedizione, id_configurazione, where)
- **Processo:**
  - Inserisce record nella tabella `lavori`
  - Per ogni elaborazione: inserisce record in `elaborazioni_lavoro`
  - Crea viste MySQL per ogni elaborazione: `CREATE VIEW nome_lavoro_nome_elaborazione AS SELECT * FROM base_dati WHERE ...`
  - Validazione SQL sicura per clausole WHERE
  - Utilizza transazioni DB (blocca_db/sblocca_db)
- **Output:** `id_lavoro`
- **Libreria utilizzata:** `Gestione_db` con gestione transazioni

#### **crea_elaborazione.php**
**Scopo:** Crea nuove elaborazioni per un lavoro esistente importando un nuovo file dati.
- **Input:** `file_to_upload` (FormData), `folder_z`, `id_flusso` (commessa), `id_lavoro`
- **Processo:**
  - Upload e salvataggio file temporaneo
  - Verifica univocità commessa (id_flusso)
  - Legge file Excel/CSV con intestazione della base dati
  - Importa dati nella tabella base dati con campi aggiuntivi (id_flusso, folder_z, lavoro)
  - Per ogni elaborazione del lavoro: crea record in tabella `elaborazioni` e aggiorna i dati con `id_elaborazione` e `nome_elaborazione` secondo la clausola WHERE
  - Verifica che tutte le WHERE coprano tutti i record
  - Elimina file temporaneo
  - Utilizza transazioni DB
- **Output:** `id_flusso`
- **Libreria utilizzata:** `Csv`, `Excel`, `Gestione_db`, `Array_php`

#### **crea_configurazione.php**
**Scopo:** Crea una nuova configurazione di ordinamento per un tipo di spedizione.
- **Input:** Parametri specifici del tipo spedizione, `tipo_spedizione`
- **Processo:** Inserisce configurazione nella tabella del tipo spedizione (es. tabella `target`, `massiva`)
- **Output:** `id_configurazione`, `nome_configurazione`

#### **upload_file_per_nuova_base_dati.php**
**Scopo:** Upload preliminare del file per analizzare l'intestazione prima di creare la base dati.
- **Input:** `file_to_upload` (FormData), `intestazione_si_no`
- **Processo:**
  - Salva file temporaneamente
  - Legge la prima riga (se intestazione presente) o genera nomi colonne (Colonna 1, 2, ...)
  - Mantiene il file sul server per uso successivo in crea_base_dati
- **Output:** `intestazione` (array di nomi campi)
- **Utilizzo:** Permette di mappare i campi obbligatori (CAP, Località, Provincia) in creazione_BaseDati
- **Libreria utilizzata:** `Csv`, `Excel`

---

### Endpoint di Aggiornamento Dati (POST/Update)

#### **aggiorna_lavoro.php**
**Scopo:** Aggiorna un lavoro esistente e le sue elaborazioni.
- **Input:** `id_lavoro`, `id_base_dati`, `nome_lavoro`, `elaborazioni[]`
- **Processo:** Simile a crea_lavoro ma aggiorna record esistenti e ricrea le viste
- **Libreria utilizzata:** `Gestione_db`

#### **aggiorna_configurazione.php**
**Scopo:** Aggiorna una configurazione esistente.
- **Input:** `id_configurazione`, parametri configurazione, `tipo_spedizione`
- **Processo:** Aggiorna record nella tabella del tipo spedizione

#### **chiudi_elaborazione.php**
**Scopo:** Esporta i dati dell'elaborazione (anagrafica, ordinati e light) in CSV filtrati per `nome_elaborazione` e `id_flusso`, li archivia in un file ZIP (usando `ZipStream` per compatibilità con l'ambiente Docker) e segna l'elaborazione come conclusa.
- **Input:** `id_elaborazione`
- **Processo:**
  - Genera file CSV temporanei dai dati del database (tabelle anagrafica, ordinati ed eventualmente light) filtrando per `nome_elaborazione` e `id_flusso`.
  - Archivia i CSV in un file .zip tramite la libreria `ZipStream`.
  - Invia il file .zip al frontend per il download tramite streaming HTTP.
  - Elimina i file CSV temporanei dal server dopo l'archiviazione.
  - Aggiorna lo stato dell'elaborazione a conclusa (stato 255).
- **Libreria utilizzata:** `Csv`, `ZipStream`, `Gestione_db`

#### **riapri_elaborazione.php**
**Scopo:** Riapre un'elaborazione precedentemente chiusa.
- **Input:** `id_elaborazione`
- **Processo:** Aggiorna campo stato/chiuso nella tabella `elaborazioni`

---

### Endpoint di Eliminazione Dati (POST/Delete)

#### **elimina_base_dati.php**
**Scopo:** Elimina una base dati e tutti i dati associati.
- **Input:** `id_base_dati`
- **Processo:**
  - Elimina tabella MySQL della base dati
  - Elimina record dalla tabella `base_dati`
  - Verifica che non ci siano lavori attivi che la utilizzano

#### **elimina_elaborazione.php**
**Scopo:** Elimina i record relativi a un'elaborazione specifica identificata da ID e ID Flusso.
- **Input:** `id_elaborazione`
- **Processo:**
  - Recupera i dettagli dell'elaborazione per ottenere `id_flusso` e nomi tabelle.
  - Elimina i record dalla tabella dati originale filtrando per `id_elaborazione` e `id_flusso`.
  - Elimina i record dalla tabella `ordinati` corrispondente filtrando per `nome_elaborazione` e `id_flusso`.
  - Elimina eventuali record dalla tabella `ordinati_light`.
  - Utilizza transazioni DB per garantire l'integrità dei dati.
- **Libreria utilizzata:** `Gestione_db`

---

### Endpoint di Elaborazione/Azioni (POST/Processi)

#### **lancia_ordinamento.php**
**Scopo:** Avvia il processo di ordinamento postale per un'elaborazione.
- **Input:** `id_elaborazione`
- **Processo:**
  - Preleva dati elaborazione e configurazione dal database
  - Crea istanza dinamica della classe di elaborazione: `Elaborazione_postale_{tipo_spedizione}`
  - Imposta parametri (elaborazione + configurazione)
  - Aggiorna stato elaborazione a 1 (in elaborazione)
  - Esegue ordinamento: `$elab->ordina(nome_vista)`
  - Crea tabella `ordinati_{tipo_spedizione}_{nome_base_dati}` con i dati ordinati
  - Aggiorna stato elaborazione a 2 (elaborato)
- **Libreria utilizzata:** `Gestione_db`, `Elaborazione_postale_{tipo}` (da vendor/elaborazione_postale)

#### **prenotazione.php**
**Scopo:** Gestisce la prenotazione della spedizione con Poste Italiane.
- **Input:** `id_elaborazione`, `data_prenotazione`
- **Processo:**
  - Preleva dati elaborazione
  - Genera file distinta per Poste Italiane
  - Carica file su FTP Poste (configurazione da `temp/elab.php`)
  - Aggiorna stato prenotazione
- **Libreria utilizzata:** `Gestione_db`, `Ftp`, classi elaborazione postale

#### **genera_etichette.php**
**Scopo:** Genera etichette PDF univoche per la spedizione e le invia al browser per il download.
- **Input:** `id_elaborazione`, `data_spedizione` (opzionale)
- **Processo:**
  - Preleva dati ordinati e configurazioni
  - Genera un nome file univoco con `uniqid()`
  - Crea il PDF nella cartella temporanea `temp_pdf/`
  - Invia il PDF come download tramite header HTTP
  - Elimina il file temporaneo immediatamente dopo l'invio
- **Libreria utilizzata:** `Pdf_php`, `Gestione_db`

---

## Libreria CASSETTA_ATTREZZI

**Path:** `php-docker-boilerplate/src/vendor/cassetta_attrezzi_php/`

La libreria CASSETTA_ATTREZZI è una libreria PHP custom sviluppata per ridurre il codice ripetitivo nelle operazioni comuni. Fornisce classi riutilizzabili per gestione database, file, array, date, comunicazioni e altro.

### Classi Principali

#### **Gestione Database**

##### `Gestione_db.php`
**Scopo:** Gestione completa delle operazioni database MySQL.
- **Funzionalità:**
  - Connessione automatica al database usando variabili globali
  - `preleva_da_db($query, $params)`: Esegue SELECT e restituisce array associativo
  - `preleva_da_db_un_singolo_valore($query, $params)`: Restituisce un singolo valore
  - `carica_a_db($dati, $tabella)`: INSERT di array associativi (singolo o multiplo)
  - `esegui_query($query, $params)`: Esegue query generica (UPDATE, DELETE, CREATE, ecc.)
  - `verifica_presenza_record($query, $params)`: Verifica esistenza record
  - `get_ultimo_id_inserito()`: Restituisce last insert ID
  - `blocca_db()` / `sblocca_db($rollback)`: Gestione transazioni con lock
  - Prepared statements automatici per sicurezza SQL injection
  - Logging automatico errori tramite `Segnalazioni_e_log`

#### **Gestione File**

##### `Excel.php`
**Scopo:** Lettura e scrittura file Excel (.xlsx, .xls).
- **Funzionalità:**
  - `apri($path, $intestazione)`: Apre file Excel
  - `converti_in_array($mappatura_campi, $ignora_intestazione)`: Converte in array PHP
  - `leggi_riga()`: Legge riga per riga
  - Supporto fogli multipli
  - Gestione formati data Excel
- **Libreria sottostante:** PhpSpreadsheet

##### `Csv.php`
**Scopo:** Lettura e scrittura file CSV.
- **Funzionalità:**
  - `apri($path, $intestazione)`: Apre file CSV
  - `converti_in_array($mappatura_campi, $ignora_intestazione)`: Converte in array PHP
  - `leggi_riga()`: Legge riga per riga
  - Rilevamento automatico delimitatore (`,`, `;`, `\t`)
  - Gestione encoding (UTF-8, ISO-8859-1)

##### `Pdf_php.php`
**Scopo:** Generazione e manipolazione PDF.
- **Funzionalità:**
  - Creazione PDF da zero
  - Aggiunta testo, immagini, tabelle
  - Merge di PDF esistenti
  - Estrazione testo da PDF
- **Libreria sottostante:** TCPDF

##### `Esporta_excel.php`
**Scopo:** Esportazione dati in formato Excel.
- **Funzionalità:**
  - Esporta array PHP in file Excel
  - Formattazione celle (colori, bordi, font)
  - Formule Excel

##### `Txt.php`
**Scopo:** Lettura e scrittura file di testo.

##### `File_indice.php`
**Scopo:** Gestione file indice (formato specifico per elaborazioni postali).

##### `Read_idx.php`
**Scopo:** Lettura file .idx (formato indice Poste Italiane).

#### **Gestione Array**

##### `Array_php.php`
**Scopo:** Operazioni avanzate su array multidimensionali.
- **Funzionalità:**
  - `array_add_column($array, $nome_colonna, $valore)`: Aggiunge colonna ad array bidimensionale
  - `array_delete_columns($array, $colonne)`: Elimina colonne
  - `array_rename_columns($array, $mappatura)`: Rinomina colonne
  - `array_prefix($array, $prefisso)`: Aggiunge prefisso a tutte le chiavi
  - `array_bidimensional_filter_by_key_value($array, $chiave, $valore)`: Filtra array
  - `array_search_multidimensional($array, $chiave, $valore)`: Cerca in array multidimensionale
  - `array_merge_bidimensionale($array1, $array2)`: Merge di array bidimensionali
  - `array_multidimensional_to_single($array)`: Appiattisce array
  - `array_implode_multidimensional($array, $separatore)`: Implode ricorsivo

#### **Comunicazioni**

##### `Ftp.php`
**Scopo:** Gestione connessioni FTP/SFTP.
- **Funzionalità:**
  - Connessione FTP/SFTP
  - Upload/download file
  - Navigazione directory
  - Eliminazione file remoti
  - Supporto autenticazione con chiave SSH
- **Utilizzo:** Upload distinte a Poste Italiane, download esiti

##### `Invia_mail.php`
**Scopo:** Invio email.
- **Funzionalità:**
  - Invio email con allegati
  - Supporto HTML
  - SMTP autenticato
- **Libreria sottostante:** PHPMailer

##### `Invia_sms.php`
**Scopo:** Invio SMS tramite gateway.

#### **Logging e Segnalazioni**

##### `Segnalazioni_e_log.php`
**Scopo:** Sistema centralizzato di logging e segnalazione errori.
- **Funzionalità:**
  - Logging su file
  - Invio email automatico per errori critici
  - Livelli di log (info, warning, error, fatal)
  - Integrazione con tutte le altre classi
  - Opzione `blocca_il_programma_per_qualsiasi_errore`: se true, lancia eccezione per ogni errore

#### **Configurazione**

##### `Variabili_globali_import.php`
**Scopo:** Caricamento variabili di configurazione globali.
- **Funzionalità:**
  - `get_variabili_globali($id_flusso)`: Carica configurazione da file `temp/elab.php` o da repository centrale
  - Restituisce array con configurazioni DB, FTP, email, ecc.
  - Permette configurazioni diverse per ambiente (dev/prod)

#### **Utilità Date**

##### Funzioni per gestione date:
- `data_add.php`: Aggiunge giorni/mesi/anni a una data
- `data_converti_formato.php`: Converte formati data (IT/US/ISO)
- `data_festivo.php`: Verifica se una data è festivo
- `differenza_date.php`: Calcola differenza tra date
- `validateDate.php`: Valida formato data

#### **Altre Utilità**

##### `Commessa.php` / `Commessa_pdf.php`
**Scopo:** Gestione commesse e generazione report PDF.

##### `Genera_codice_univoco.php`
**Scopo:** Generazione codici univoci/UUID.

##### `Ocr.php`
**Scopo:** Riconoscimento testo da immagini (OCR).

##### `Cmd.php`
**Scopo:** Esecuzione comandi shell in modo sicuro.

##### `Import_file.php`
**Scopo:** Import generico file con rilevamento automatico formato.

##### Funzioni di manipolazione stringhe:
- `estrai_testo.php`: Estrazione testo con regex
- `strpos_array.php`: strpos con array di needle
- `strreplace_ricorsivo.php`: str_replace ricorsivo su array
- `sostituisci_variabili.php`: Template engine semplice
- `sanitize_path.php`: Sanitizzazione percorsi file
- `interpreta_blocchi_indirizzi.php`: Parsing indirizzi postali

##### Altre utilità:
- `converti_array_in_tabella_html.php`: Genera tabella HTML da array
- `implode_associative_array.php`: Implode per array associativi
- `getDirectorySize.php`: Calcola dimensione directory
- `gestione_parametri.php`: Gestione parametri configurazione
- `fatal_error_handler.php`: Handler errori fatali PHP

---

### Pattern di Utilizzo Comune negli Endpoint

```php
// 1. Autoload e namespace
namespace indi\Classes;
require 'vendor/autoload.php';

// 2. CORS headers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    // 3. Inizializzazione libreria
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);
    
    // 4. Lettura input
    $jsonData = json_decode(file_get_contents('php://input'), true);
    // oppure per file upload: $_FILES, $_POST
    
    // 5. Validazione input
    if (!isset($jsonData["campo_richiesto"]))
        throw new \Exception('Mancano dati in input');
    
    // 6. Operazioni database/file
    $risultato = $db->preleva_da_db("SELECT ...", [$param]);
    
    // 7. Risposta successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $risultato
    ]);
    
} catch (\Exception $e) {
    // 8. Gestione errori
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

---

## File di Configurazione Globale

**File:** `temp/elab.php`

**Nota importante:** Il file `temp/elab.php` presente nel progetto è **solo un file di esempio**. Le variabili globali reali sono memorizzate su un altro server e vengono caricate dinamicamente durante l'esecuzione.

### Scopo delle Variabili Globali

Le variabili globali servono per fornire alcuni settaggi al progetto utili allo svolgimento delle varie operazioni. Questo sistema permette di modificare le configurazioni (database, FTP, email, ecc.) senza dover entrare nel codice del progetto: è sufficiente modificare le variabili globali sul server centralizzato.

### Contenuto delle Variabili Globali

Le variabili globali contengono:
- Array `elaborazioni`: Definisce prodotti postali e relative azioni (es. `output: "pdf"`)
- Configurazioni database (host, porta, credenziali)
- Configurazioni FTP Poste Italiane (credenziali, percorsi)
- Configurazioni email per segnalazioni (SMTP, destinatari)

L'array `elaborazioni` è la fonte di verità per i prodotti postali disponibili e le loro azioni.
