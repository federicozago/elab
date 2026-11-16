# PIANO 10 - VISUALIZZAZIONE INTESTAZIONE SELEZIONE BASE DATI

Il presente piano descrive le modifiche necessarie per far sì che, quando viene selezionata una base dati esistente dalla lista, la relativa intestazione venga visualizzata correttamente nell'interfaccia utente.

## 1. Analisi del Problema
Attualmente, nella funzione `baseDatiCambiata`, i dati ricevuti dal backend tramite `/preleva_base_dati.php` vengono usati per popolare l'oggetto `formData`. Tuttavia, la variabile reattiva `intestazione`, che controlla la visualizzazione dei badge e popola le opzioni delle `BaseSelect` (CAP, Località, Provincia), non viene valorizzata. Questo impedisce all'utente di vedere quali campi sono disponibili per la base dati selezionata.

## 2. Modifiche Proposte

### 2.1 Aggiornamento funzione `baseDatiCambiata`
Bisogna modificare la funzione `baseDatiCambiata` in `creazione_BaseDati.vue` (intorno alla riga 160) per assegnare l'array delle intestazioni alla variabile `intestazione.value`.

```javascript
function baseDatiCambiata(idBaseDati) {
  api
    .post('/preleva_base_dati.php', {
      id_base_dati: idBaseDati,
    })
    .then((response) => {
      // VALORIZZAZIONE INTESTAZIONE (Aggiunta per Piano 10)
      if (response.data.base_dati.intestazione) {
        intestazione.value = response.data.base_dati.intestazione
      } else {
        intestazione.value = []
      }

      // vado a valorizzare i dati del form (esistente)
      Object.keys(response.data.base_dati).forEach((key) => {
        if (key === 'intestazione_si_no') {
          if (response.data.base_dati[key] === 1) {
            formData.value[key] = true
          } else {
            formData.value[key] = false
          }
        } else if (key === 'file_base_dati') {
          formData.value[key] = [response.data.base_dati[key]]
        } else if (key in formData.value) {
          formData.value[key] = response.data.base_dati[key]
        }
      })
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati - ' + e.response.data.message)
    })
}
```

## 3. Risultato Atteso
Dopo questa modifica:
1. Selezionando una base dati, comparirà il box grigio con i badge dei nomi dei campi (se presenti).
2. Le `BaseSelect` relative a CAP, Località e Provincia verranno popolate con i nomi dei campi corretti, permettendo di vedere (e modificare) le mappature salvate.
3. Se la base dati selezionata non ha intestazione, l'array verrà svuotato per evitare di mostrare dati obsoleti di selezioni precedenti.
