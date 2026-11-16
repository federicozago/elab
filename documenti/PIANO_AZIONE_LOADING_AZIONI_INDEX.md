### Piano d'Azione: Gestione Loading sui Pulsanti Azioni in IndexPage.vue

Questo documento descrive la strategia per aggiungere un feedback di caricamento ai pulsanti delle azioni specifiche (es. Genera etichette, Prenota, ecc.) che appaiono nel dialog delle azioni di `IndexPage.vue`. Questi pulsanti non sono all'interno di un `BaseForm`, quindi la gestione del caricamento deve essere implementata direttamente sui componenti `BaseBtn`.

---

#### 1. Obiettivo
Fornire un feedback visivo (spinner) quando l'utente clicca su una delle azioni dinamiche nella tabella delle elaborazioni in corso, evitando click multipli e migliorando la UX.

---

#### 2. Strategia Tecnica
*   **Nuova Variabile di Stato**: Introduzione di `isActionSubmitting` (ref booleano) per gestire lo stato di caricamento globale delle azioni nel dialog.
*   **Refactoring `lanciaAzione`**: Trasformazione della funzione in `async` per permettere l'uso di `await` con le chiamate `api.post` e garantire il corretto reset dello stato tramite il blocco `finally`.
*   **Aggiornamento Template**: Collegamento della prop `:loading` dei componenti `BaseBtn` alla nuova variabile di stato.

---

#### 3. Codice da Modificare

##### I. `src/pages/IndexPage.vue` - Dichiarazione variabile
Aggiunta della variabile reattiva per tracciare il caricamento delle azioni.

```javascript
// Intorno alla riga 358, vicino a isSubmitting esistente
const isSubmitting = ref(false)
const isActionSubmitting = ref(false) // Nuova variabile
```

##### II. `src/pages/IndexPage.vue` - Template (Pulsanti dinamici)
Collegamento della prop `:loading` ai pulsanti generati dal ciclo `v-for`.

```vue
<!-- Righe 328-331 originali -->
<BaseBtn
  :label="nomeAzione"
  @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione)"
  :loading="isActionSubmitting"
></BaseBtn>
```

##### III. `src/pages/IndexPage.vue` - Funzione `lanciaAzione`
Refactoring per gestire lo stato asincrono.

```javascript
/**
 * lancia un azione qualsiasi fra quelle presenti in var globali...
 */
async function lanciaAzione(datiAzione, id_elaborazione) {
  if (datiAzione.parametri?.includes('d')) {
    if (dataAzioni.value === null) {
      gestioneErrore(null, 'Data non inserita')
      return
    }
  }

  isActionSubmitting.value = true // Attiva caricamento

  var dati = {
    data: dataAzioni.value,
    id_elaborazione: id_elaborazione,
  }

  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const config = (isPdf || isZip) ? { responseType: 'blob' } : {}

  try {
    const response = await api.post('/' + datiAzione.endpoint, dati, config)
    
    if (isPdf || isZip) {
      const url = window.URL.createObjectURL(new Blob([response.data]))
      const link = document.createElement('a')
      link.href = url

      let filename = isZip ? 'esportazione.zip' : 'etichette.pdf'
      const contentDisposition = response.headers['content-disposition'] || response.headers['Content-Disposition']

      if (contentDisposition) {
        const fileNameMatch = contentDisposition.match(/filename=(.+)/)
        if (fileNameMatch && fileNameMatch.length === 2) 
          filename = fileNameMatch[1].replace(/['"]/g, '')
      }

      link.setAttribute('download', filename)
      document.body.appendChild(link)
      link.click()
      link.remove()
      window.URL.revokeObjectURL(url)
    }

    if (datiAzione.messaggio_successo) {
      messaggioPositivo(datiAzione.messaggio_successo)
    }

    // Se l'azione prevede la chiusura o l'aggiornamento, refresha le tabelle
    if (datiAzione.endpoint === 'chiudi_elaborazione.php') {
      prelevaElaborazioniInCorso()
      prelevaElaborazioniConcluse()
      dialogAzioniVisible.value = false
    }
  } catch (e) {
    gestioneErrore(e, 'Impossibile completare l\'azione')
  } finally {
    isActionSubmitting.value = false // Spegne caricamento sempre
  }
}
```

---

#### 4. Vantaggi della Soluzione
1.  **Coerenza**: Segue lo stesso pattern già implementato per i `BaseForm`.
2.  **Sicurezza**: Impedisce che l'utente clicchi più volte sullo stesso pulsante (o su altri del dialog) mentre è in corso il download di un PDF o la generazione di un archivio ZIP, operazioni che possono richiedere diversi secondi.
3.  **Chiarezza**: Mostra chiaramente che il sistema sta lavorando, anche se non c'è un form visibile.
