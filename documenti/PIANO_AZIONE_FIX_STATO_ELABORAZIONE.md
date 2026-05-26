### Piano d'Azione - Fix Aggiornamento Stato Elaborazione

---

## Problema Identificato

Quando si clicca sul pulsante "ORDINA" nella tabella "Elaborazioni in corso" di `IndexPage.vue`, lo stato dell'elaborazione non viene aggiornato visivamente nella tabella, anche se la chiamata all'endpoint `lancia_ordinamento.php` viene eseguita correttamente.

### Comportamento Atteso
- Clic su "ORDINA" → stato passa da 0 a 1
- La tabella dovrebbe mostrare "In elaborazione" (riga 128)

### Comportamento Attuale
- Clic su "ORDINA" → chiamata API eseguita
- La riga 488 non aggiorna correttamente lo stato
- La tabella continua a mostrare il pulsante "ORDINA"

---

## Analisi Tecnica del Bug

### Struttura Dati `elaborazioniInCorso`

L'array `elaborazioniInCorso` ha una struttura **gerarchica/raggruppata**:

```javascript
elaborazioniInCorso = [
  {
    nome_lavoro: "suzuki",
    dettagli: [
      {
        id_elaborazione: 10,
        stato: 0
      },
      {
        id_elaborazione: 11,
        stato: 0
      }
    ]
  },
  {
    nome_lavoro: "toyota",
    dettagli: [
      {
        id_elaborazione: 12,
        stato: 2
      }
    ]
  }
]
```

### Codice Problematico (righe 486-488)

```javascript
elaborazioniInCorso.value.filter(
  (elaborazione) => elaborazione.id === id_elaborazione,
)[0].stato = 1
```

**Problemi:**
1. **Struttura errata**: Il codice cerca `elaborazione.id` ma la struttura ha `elaborazione.dettagli[]` con `id_elaborazione`
2. **Livello sbagliato**: Filtra il primo livello (lavori) invece del secondo livello (elaborazioni nei dettagli)
3. **Campo errato**: Cerca `id` invece di `id_elaborazione`
4. **Rischio errore**: Se il filtro non trova nulla, `[0]` causa un errore "Cannot read property 'stato' of undefined"

---

## Soluzione

### Codice Corretto

Sostituire le righe 486-488 con:

```javascript
// Trova il lavoro che contiene l'elaborazione
for (const lavoro of elaborazioniInCorso.value) {
  // Cerca l'elaborazione nei dettagli del lavoro
  const elaborazione = lavoro.dettagli.find(
    (elab) => elab.id_elaborazione === id_elaborazione
  )
  
  // Se trovata, aggiorna lo stato e interrompi il ciclo
  if (elaborazione) {
    elaborazione.stato = 1
    break
  }
}
```

### Alternativa con forEach (più concisa)

```javascript
elaborazioniInCorso.value.forEach((lavoro) => {
  const elaborazione = lavoro.dettagli.find(
    (elab) => elab.id_elaborazione === id_elaborazione
  )
  if (elaborazione) {
    elaborazione.stato = 1
  }
})
```

### Alternativa con flatMap (funzionale)

```javascript
const elaborazione = elaborazioniInCorso.value
  .flatMap((lavoro) => lavoro.dettagli)
  .find((elab) => elab.id_elaborazione === id_elaborazione)

if (elaborazione) {
  elaborazione.stato = 1
}
```

---

## Implementazione Consigliata

### Passo 1: Backup
Creare una copia di backup di `IndexPage.vue` prima di modificare.

### Passo 2: Modifica del Codice
Aprire `C:\wamp64\progr\elab\src\pages\IndexPage.vue` e modificare la funzione `lanciaElaborazione`:

**PRIMA (righe 477-489):**
```javascript
function lanciaElaborazione(id_elaborazione) {
  api
    .post('/lancia_ordinamento.php', {
      id_elaborazione: id_elaborazione,
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile lanciare ordinamento - ' + e.response.data.message)
    })
  //setto stato a 1
  elaborazioniInCorso.value.filter(
    (elaborazione) => elaborazione.id === id_elaborazione,
  )[0].stato = 1
}
```

**DOPO:**
```javascript
function lanciaElaborazione(id_elaborazione) {
  api
    .post('/lancia_ordinamento.php', {
      id_elaborazione: id_elaborazione,
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile lanciare ordinamento - ' + e.response.data.message)
    })
  
  // Aggiorna lo stato dell'elaborazione nella struttura gerarchica
  for (const lavoro of elaborazioniInCorso.value) {
    const elaborazione = lavoro.dettagli.find(
      (elab) => elab.id_elaborazione === id_elaborazione
    )
    if (elaborazione) {
      elaborazione.stato = 1
      break
    }
  }
}
```

### Passo 3: Test
1. Avviare l'applicazione
2. Importare dati per creare un'elaborazione
3. Verificare che nella tabella "Elaborazioni in corso" compaia il pulsante "ORDINA"
4. Cliccare su "ORDINA"
5. Verificare che:
   - La chiamata API venga eseguita correttamente
   - Il pulsante "ORDINA" scompaia
   - Compaia il testo "In elaborazione"
6. Attendere il completamento dell'ordinamento sul server
7. Ricaricare la pagina e verificare che lo stato sia "Elaborato"

---

## Considerazioni Aggiuntive

### Gestione Asincrona
Il codice attuale non attende la risposta del server prima di aggiornare lo stato. Questo è corretto secondo le specifiche ("essendo che l'ordinamento può richiedere anche diversi minuti faccio in modo che il client non attenda risposta dal server").

Tuttavia, se la chiamata API fallisce, lo stato viene comunque impostato a 1. Per una gestione più robusta, si potrebbe:

```javascript
function lanciaElaborazione(id_elaborazione) {
  // Prima aggiorna l'UI
  for (const lavoro of elaborazioniInCorso.value) {
    const elaborazione = lavoro.dettagli.find(
      (elab) => elab.id_elaborazione === id_elaborazione
    )
    if (elaborazione) {
      elaborazione.stato = 1
      break
    }
  }
  
  // Poi lancia la chiamata API
  api
    .post('/lancia_ordinamento.php', {
      id_elaborazione: id_elaborazione,
    })
    .catch((e) => {
      // In caso di errore, ripristina lo stato a 0
      for (const lavoro of elaborazioniInCorso.value) {
        const elaborazione = lavoro.dettagli.find(
          (elab) => elab.id_elaborazione === id_elaborazione
        )
        if (elaborazione) {
          elaborazione.stato = 0
          break
        }
      }
      gestioneErrore(e, 'Impossibile lanciare ordinamento - ' + e.response.data.message)
    })
}
```

### Reattività Vue
Vue 3 con Composition API gestisce automaticamente la reattività degli oggetti nested, quindi la modifica di `elaborazione.stato` dovrebbe aggiornare immediatamente la vista.

### Verifica Endpoint Backend
Assicurarsi che `lancia_ordinamento.php` aggiorni correttamente lo stato nel database:
- Imposta `stato = 1` all'inizio dell'elaborazione
- Imposta `stato = 2` (o superiore) al completamento

---

## Riepilogo Modifiche

**File da modificare:** `C:\wamp64\progr\elab\src\pages\IndexPage.vue`

**Righe da modificare:** 486-488

**Tipo di modifica:** Correzione logica di accesso alla struttura dati

**Impatto:** Basso - modifica isolata in una singola funzione

**Test richiesti:** Funzionali - verificare il ciclo completo di ordinamento

---

## Checklist Pre-Implementazione

- [ ] Backup del file `IndexPage.vue`
- [ ] Verifica che l'endpoint `lancia_ordinamento.php` funzioni correttamente
- [ ] Verifica che `preleva_elaborazioni_in_corso.php` restituisca la struttura corretta
- [ ] Ambiente di test disponibile

## Checklist Post-Implementazione

- [ ] Codice modificato secondo il piano
- [ ] Test manuale eseguito con successo
- [ ] Nessun errore in console del browser
- [ ] Stato visivo aggiornato correttamente
- [ ] Comportamento conforme alle specifiche
- [ ] Commit delle modifiche con messaggio descrittivo

---

## Note Finali

Questo fix risolve il problema di aggiornamento dello stato nell'interfaccia utente. Il bug era causato da un accesso errato alla struttura dati gerarchica di `elaborazioniInCorso`. La soluzione proposta naviga correttamente la struttura a due livelli (lavori → dettagli) e utilizza il campo corretto (`id_elaborazione` invece di `id`).