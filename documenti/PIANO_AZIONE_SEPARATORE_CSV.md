### Piano di Azione: Aggiunta Campo Separatore in Creazione Base Dati

#### Obiettivo
Consentire all'utente di specificare un carattere separatore durante la creazione di una base dati. Il separatore sarà preimpostato a `;` di default, ma potrà essere modificato dall'utente prima del caricamento del file o della creazione della base dati.

#### Modifiche nel Frontend (`src/pages/creazione_BaseDati.vue`)

1.  **Aggiornamento dello Stato (`formData`)**:
    *   Aggiungere la proprietà `separatore` all'oggetto `formData` con valore predefinito `;`.
    ```javascript
    const formData = ref({
      nome_base_dati: '',
      file_base_dati: null,
      campo_cap: '',
      campo_localita: '',
      campo_provincia: '',
      intestazione_si_no: null,
      separatore: ';', // Valore di default
      test: null,
    })
    ```

2.  **Interfaccia Utente (Template)**:
    *   Inserire un nuovo componente `BaseInput` nel form, appena prima del campo `file_base_dati`.
    ```html
    <BaseInput
      v-model="formData.separatore"
      label="Separatore CSV/Testo"
      :rules="[required]"
    />

    <BaseFile
      v-model="formData.file_base_dati"
      ...
    />
    ```

3.  **Logica di Invio (uploadFile)**:
    *   Includere il separatore nei dati inviati per l'analisi preliminare (multipart/form-data).
    ```javascript
    const uploadFile = async (file) => {
      if (!formData.value.file_base_dati) return
      
      fileStore.setSelectedFile(file)
      
      const uploadData = new FormData()
      uploadData.append('file_to_upload', formData.value.file_base_dati)
      uploadData.append('separatore', formData.value.separatore)
      uploadData.append('intestazione_si_no', formData.value.intestazione_si_no)
      // ... resto della funzione
    }
    ```

4.  **Logica di Creazione (creaBaseDati)**:
    *   Il separatore è parte di `formData`, quindi viene inviato automaticamente nel payload JSON.
    ```javascript
    function creaBaseDati() {
      const dati = { ...formData.value, intestazione: intestazione.value }
      // dati contiene 'separatore'
      // ...
      api.post('/crea_base_dati.php', dati)
      // ...
    }
    ```

#### Modifiche nel Backend (`php-docker-boilerplate/src/crea_base_dati.php`)

1.  **Ricezione del parametro**:
    ```php
    $jsonData = json_decode(file_get_contents('php://input'), true);
    // Il parametro sarà disponibile in $jsonData['separatore']
    ```

2.  **Salvataggio metadati**:
    *   Il valore verrà salvato automaticamente nella tabella `base_dati` se la colonna esiste, poiché viene passato l'intero array `$jsonData` alla funzione `carica_a_db`.
    *   È necessario aggiungere la colonna `separatore` alla tabella `base_dati` nel database MySQL.

#### Modifiche nel Backend (`php-docker-boilerplate/src/upload_file_per_nuova_base_dati.php`)

1.  **Utilizzo del separatore nell'analisi preliminare**:
    ```php
    // Recupero il separatore inviato tramite POST
    $separatore = $_POST['separatore'] ?? ';';
    
    // Utilizzo con la classe Csv
    $csv = new Csv($log);
    $csv->apri($path, $intestazione_si_no, separatore: $separatore);
    ```

#### Verifiche e Test
1.  Verificare che il campo "Separatore CSV/Testo" sia visibile prima del selettore file.
2.  Modificare il separatore (es. in `,`) e caricare un file CSV con quel separatore.
3.  Verificare tramite console network che il parametro `separatore` inviato corrisponda a quello inserito.
4.  Verificare che l'importazione dei dati avvenga correttamente.

#### Aggiornamento Documentazione
1.  Aggiornare `documenti\SINTESI_PROGETTO.md` per includere il campo `separatore` (modificabile) nella descrizione della pagina `creazione_BaseDati.vue`.
