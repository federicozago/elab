### Piano d'Azione 01: Correzione caricamento singolo pulsante azioni

Questo documento descrive la modifica necessaria per evitare che tutti i pulsanti delle azioni in `IndexPage.vue` mostrino contemporaneamente lo spinner di caricamento. La soluzione prevede l'identificazione specifica del pulsante cliccato.

---

#### 1. Analisi del problema
Attualmente, la variabile `isActionSubmitting` è di tipo `Boolean`. Poiché tutti i pulsanti nel ciclo `v-for` sono collegati alla stessa variabile tramite `:loading="isActionSubmitting"`, l'attivazione della variabile a `true` influisce su tutti i componenti contemporaneamente.

#### 2. Strategia di risoluzione
Trasformeremo `isActionSubmitting` da un booleano a una stringa (o null). 
- Quando un'azione viene cliccata, `isActionSubmitting` memorizzerà il nome dell'azione (es. "Genera etichette").
- Ogni pulsante mostrerà lo spinner solo se `isActionSubmitting === nomeAzione`.
- Al termine dell'operazione, la variabile tornerà a `null`.

---

#### 3. Modifiche proposte

##### File: `src/pages/IndexPage.vue`

**A. Modifica dichiarazione stato (riga ~360):**
```javascript
// Da:
const isActionSubmitting = ref(false)

// A:
const isActionSubmitting = ref(null)
```

**B. Modifica Template (righe ~328-332):**
```vue
<BaseBtn
  :label="nomeAzione"
  @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione, nomeAzione)"
  :loading="isActionSubmitting === nomeAzione"
></BaseBtn>
```
*Nota: Passiamo `nomeAzione` come terzo parametro alla funzione `lanciaAzione`.*

**C. Modifica Funzione `lanciaAzione` (riga ~635):**
```javascript
async function lanciaAzione(datiAzione, id_elaborazione, nomeAzione = null) {
  if (datiAzione.parametri?.includes('d')) {
    if (dataAzioni.value === null) {
      gestioneErrore(null, 'Data non inserita')
      return
    }
  }

  // Imposto il nome dell'azione corrente per attivare il loading specifico
  isActionSubmitting.value = nomeAzione || true 
  
  var dati = {
    data: dataAzioni.value,
    id_elaborazione: id_elaborazione,
  }

  // ... (restante logica della funzione) ...

  try {
    // ... (chiamata API e gestione download) ...
  } catch (e) {
    // ... (gestione errore) ...
  } finally {
    // Reset dello stato
    isActionSubmitting.value = null
  }
}
```

**D. Aggiornamento chiamate interne a `lanciaAzione` (riga ~613):**
Per le chiamate programmatiche (come in `riesporta`), possiamo passare il nome dell'azione o lasciare che usi il default.
```javascript
function riesporta(row) {
  const datiAzione = {
    endpoint: 'chiudi_elaborazione.php',
    output: 'zip'
  }
  lanciaAzione(datiAzione, row.id_elaborazione, 'Riesporta')
}
```

---

#### 4. Vantaggi
- **Feedback preciso**: L'utente vede esattamente quale operazione sta attendendo.
- **Interfaccia pulita**: Gli altri pulsanti rimangono visivamente disponibili (anche se disabilitati nativamente da Quasar durante il loading se necessario, o comunque non mostrano animazioni fuorvianti).
- **Consistenza**: Si mantiene la logica `async/await` e `try/finally` già implementata.
