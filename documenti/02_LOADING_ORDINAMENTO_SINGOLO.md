### Piano d'Azione 02: Caricamento asincrono ordinamento singolo

Questo documento descrive la modifica alla logica di lancio dell'ordinamento postale in `IndexPage.vue`. L'obiettivo è attendere il completamento della chiamata al server e mostrare lo spinner di caricamento solo sul pulsante specifico cliccato.

---

#### 1. Analisi dello stato attuale
Attualmente, la funzione `lanciaElaborazione(id_elaborazione)` esegue una chiamata `api.post` senza attendere il risultato (`await`). Subito dopo l'invio della richiesta, viene eseguito un ciclo sui dati locali per forzare lo stato dell'elaborazione a `1` ("In elaborazione"), indipendentemente dall'esito della chiamata o dal tempo di esecuzione del server.

#### 2. Strategia di risoluzione
1.  **Identificazione del caricamento**: Introduzione di una variabile `isSortingSubmitting` che memorizzerà l'ID dell'elaborazione attualmente in fase di ordinamento.
2.  **Gestione Asincrona**: Trasformazione di `lanciaElaborazione` in una funzione `async` che utilizza `await` per la chiamata API.
3.  **Refactoring logica di stato**: Rimozione del ciclo manuale che forzava lo stato locale a `1`. Il feedback visivo sarà gestito dallo spinner sul pulsante. Al termine della chiamata, lo stato aggiornato verrà riflesso ricaricando i dati o lasciando che il server gestisca la persistenza (se l'utente aggiorna la pagina, vedrà lo stato corretto).
4.  **Feedback visivo mirato**: Il pulsante "Ordina" mostrerà lo spinner solo se `isSortingSubmitting === props.row.id_elaborazione`.

---

#### 3. Modifiche proposte

##### File: `src/pages/IndexPage.vue`

**A. Dichiarazione del nuovo stato (vicino a `isActionSubmitting`):**
```javascript
const isSortingSubmitting = ref(null)
```

**B. Modifica del Template (righe ~127-131):**
```vue
<BaseBtn
  label="Ordina"
  size="sm"
  @click="lanciaElaborazione(props.row.id_elaborazione)"
  :loading="isSortingSubmitting === props.row.id_elaborazione"
/>
```

**C. Refactoring della funzione `lanciaElaborazione` (righe ~543-562):**
```javascript
async function lanciaElaborazione(id_elaborazione) {
  isSortingSubmitting.value = id_elaborazione
  try {
    await api.post('/lancia_ordinamento.php', {
      id_elaborazione: id_elaborazione,
    })
    messaggioPositivo('Ordinamento avviato con successo')
    // Opzionale: ricarica i dati per vedere lo stato aggiornato (es. "Prenota" o "Elaborato")
    prelevaElaborazioniInCorso()
  } catch (e) {
    gestioneErrore(e, 'Impossibile lanciare ordinamento - ' + (e.response?.data?.message || e.message))
  } finally {
    isSortingSubmitting.value = null
  }
}
```

---

#### 4. Vantaggi
- **Precisione**: Lo spinner appare solo dove serve e dura esattamente quanto l'operazione sul server.
- **Affidabilità**: Se la chiamata fallisce, l'utente riceve l'errore e il pulsante torna cliccabile, senza aver forzato lo stato a "In elaborazione" erroneamente.
- **Pulizia del codice**: Rimozione di cicli manuali non necessari per la gestione dello stato UI temporaneo.
