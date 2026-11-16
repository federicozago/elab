# PIANO 09 - INVIO TIPO FILE ALL'ENDPOINT CREA BASE DATI

Il presente piano descrive le modifiche da apportare alla funzione `creaBaseDati` nella pagina `creazione_BaseDati.vue` per includere l'informazione sul tipo di file (CSV/Testo o Excel) nella richiesta inviata al server.

## 1. Analisi dello stato attuale

L'oggetto `formData` contiene già la proprietà `tipo_file`, inizializzata a `'CSV/Testo'` e aggiornata tramite il componente `BaseRadio`.
Nella funzione `creaBaseDati`, i dati vengono preparati come segue:

```javascript
async function creaBaseDati() {
  //se bisogna importare i dati
  isSubmitting.value = true
  const dati = { ...formData.value, intestazione: intestazione.value }
  dati.file_base_dati = dati.file_base_dati.name
  // ...
}
```

Poiché `dati` è una copia di `formData.value`, la proprietà `tipo_file` è già inclusa. Tuttavia, per chiarezza e per assicurarsi che il server riceva esattamente ciò che si aspetta, è opportuno verificare o esplicitare l'invio.

## 2. Modifiche proposte

Non sono necessarie modifiche strutturali pesanti in quanto `tipo_file` è già parte di `formData`. La funzione `creaBaseDati` invia già l'intero oggetto `dati` che include tutti i campi di `formData`.

Se si desidera mappare `tipo_file` in modo specifico o assicurarsi che venga inviato, il codice rimane sostanzialmente lo stesso, ma confermiamo che la proprietà `tipo_file` verrà trasmessa.

### Codice modificato (nello script setup)

La funzione `creaBaseDati` non necessita di cambiamenti radicali perché `...formData.value` include già `tipo_file`.

```javascript
async function creaBaseDati() {
  // se bisogna importare i dati
  isSubmitting.value = true
  
  // 'tipo_file' è già presente in formData.value grazie al v-model del BaseRadio
  const dati = { ...formData.value, intestazione: intestazione.value }
  
  // Assicuriamoci di inviare solo il nome del file e non l'oggetto File intero
  if (dati.file_base_dati && dati.file_base_dati.name) {
    dati.file_base_dati = dati.file_base_dati.name
  }

  try {
    // L'oggetto 'dati' ora contiene:
    // nome_base_dati, campo_cap, campo_localita, campo_provincia, 
    // intestazione_si_no, separatore, tipo_file, intestazione, ecc.
    const response = await api.post('/crea_base_dati.php', dati)
    
    messaggioPositivo('Base dati creata con successo')
    
    if (creazioneLavoroInCorso) {
      router.replace({
        path: '/creazione_lavoro/',
        query: {
          id_base_dati: response.data.id_base_dati,
          nome_base_dati: response.data.nome_base_dati,
          intestazione: response.data.intestazione,
        },
      })
    }
  } catch (e) {
    gestioneErrore(e, 'Impossibile creare la base dati - ' + (e.response?.data?.message || 'Errore di rete'))
    return false
  } finally {
    isSubmitting.value = false
  }
}
```

## 3. Conclusione

La modifica è già implicitamente supportata dalla struttura attuale del form, ma questo piano conferma che l'informazione `tipo_file` (che può valere `'CSV/Testo'` o `'Excel'`) viene correttamente inclusa nell'oggetto `dati` inviato a `/crea_base_dati.php`.
