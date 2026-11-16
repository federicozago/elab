---
sessionId: session-260714-114043-e05w
---

# Nota sul file richiesto

### Nota sul file richiesto

Come agente di **planning**, posso creare/modificare **esclusivamente** il file di piano gestito dal sistema, cioe' questo stesso file: `.junie/plans/lista-eventi-notifiche.md`. Non ho la possibilita' di creare altri file fisici nel progetto (ne' `.junie/piano_intervento.md`, ne' `.junie/piano_intervento_lista_eventi.md`, ne' alcun file di codice PHP/JS/Vue): qualsiasi creazione di file al di fuori di questo percorso e' vietata dalle regole con cui opero in questa fase.

Questo file **e' gia'** il piano d'azione dettagliato e modificabile a mano richiesto (obiettivo, file coinvolti, analisi, passaggi operativi con codice proposto, rischi, test, domande aperte). E' salvato in `.junie/plans/` — la cartella standard usata dal sistema di planning — e non nel percorso `.junie/piano_intervento.md` indicato nella richiesta, perche' quel percorso non e' tra quelli che sono autorizzato a scrivere.

Se necessario, una volta avviata la fase di implementazione, potra' essere valutato se copiare/duplicare questo contenuto anche in un file con il nome esatto richiesto — ma questa operazione non e' consentita durante la sola fase di pianificazione.

# Obiettivo

### Obiettivo

Aggiungere, nella toolbar principale (`src/layouts/MainLayout.vue`, accanto alla scritta `Quasar v{{ $q.version }}`), un pulsante che apra un elenco di **tutti i messaggi mostrati all'utente durante la sessione corrente** tramite le funzioni `messaggioPositivo` e `gestioneErrore` esposte dal composable `useCassettaAttrezzi` (`src/composables/cassettaAttrezzi.ts`).

Requisiti chiave (dal task):
- Storico **solo in memoria per la sessione corrente**: al refresh della pagina la lista si azzera (nessuna persistenza in `localStorage`/`sessionStorage`).
- Ogni evento nella lista deve avere **sfondo verde** se generato da `messaggioPositivo`, **sfondo rosso** se generato da `gestioneErrore`.
- La lista deve poter essere **chiusa** dopo la consultazione (pulsante di chiusura).
- Nessuna modifica applicata in questa fase: questo documento è solo un piano, con il codice proposto mostrato per revisione.

Requisiti confermati dall'utente (decisioni prese):
- **Limite massimo di 50 eventi** conservati in memoria: superata questa soglia, gli eventi piu' vecchi vengono scartati automaticamente.
- **Nessun pulsante "svuota cronologia"**: la cronologia resta disponibile finche' non si ricarica la pagina, si consulta e si chiude soltanto.
- **Ordine cronologico inverso**: l'evento **piu' recente** deve apparire **in cima** alla lista.
- **Badge con conteggio** sul pulsante della toolbar: confermato.
- **`q-dialog` centrale** per mostrare la lista: confermato.
- **Orario visibile** per ogni evento (oltre al messaggio): confermato.

# File coinvolti

### File coinvolti

 File | Tipo di modifica |
---|---|
 `src/stores/notificationHistoryStore.js` | **Nuovo** — store Pinia che mantiene la cronologia degli eventi/notifiche della sessione |
 `src/composables/cassettaAttrezzi.ts` | **Modifica** — `gestioneErrore` e `messaggioPositivo` registrano l'evento nello store |
 `src/layouts/MainLayout.vue` | **Modifica** — nuovo pulsante in toolbar + dialog/lista per mostrare/chiudere lo storico |

Nessun file PHP o backend viene toccato: la funzionalità è interamente client-side (Vue/Quasar/Pinia).

# Analisi del problema

### Analisi del problema

**Stato attuale**
- `useCassettaAttrezzi()` (in `src/composables/cassettaAttrezzi.ts`) è una *composable function* che, ad ogni chiamata (es. `const { gestioneErrore, messaggioPositivo } = useCassettaAttrezzi()` in `IndexPage.vue`, `creazione_lavoro.vue`, `creazione_BaseDati.vue`, `creazione_configurazione.vue`), crea un nuovo `useQuasar()` e nuove funzioni locali. Non esiste alcuno stato condiviso tra i vari punti in cui viene chiamata: ogni componente ha la propria istanza delle funzioni, quindi **non c'è oggi un posto centrale dove salvare la cronologia dei messaggi**.
- `gestioneErrore(error, msg)` mostra una `$q.notify` di tipo `negative` con timeout gestito manualmente (pausa al passaggio del mouse) e loggga l'errore in console.
- `messaggioPositivo(msg)` mostra una `$q.notify` di tipo `positive` con timeout fisso di 2500ms.
- `MainLayout.vue` ha già un `q-toolbar` con un pulsante menu (`toggleLeftDrawer`) e il testo `Quasar v{{ $q.version }}`: è il punto naturale dove aggiungere il nuovo pulsante, seguendo lo stile esistente (icone Material `q-btn flat dense round icon=...`).

**Pattern esistente da riusare**
- Il progetto usa già Pinia con la *setup syntax* per gli store condivisi, vedi `src/stores/fileStore.js` (`defineStore('fileStore', () => { const selectedFile = ref(null); ... return {...} })`). Questo è il pattern giusto per creare uno **stato condiviso in memoria, scoped alla sessione del browser** (si azzera automaticamente al reload, perché non è collegato ad alcun plugin di persistenza — nel progetto non è installato `pinia-plugin-persistedstate` né equivalenti).

**Conseguenza progettuale**
- Per soddisfare il requisito "stessa lista visibile ovunque, valida per tutta la sessione, azzerata al reload", la cronologia **non può vivere dentro il composable** (che è ricreato ad ogni chiamata) ma deve vivere in un **Pinia store dedicato**, letto sia da `cassettaAttrezzi.ts` (per scrivere i nuovi eventi) sia da `MainLayout.vue` (per leggerli e mostrarli).
- Per mostrare **l'evento piu' recente in cima**, il nuovo evento va inserito in **testa** all'array (`unshift`) invece che in coda (`push`).
- Per rispettare il **limite di 50 eventi**, dopo ogni inserimento l'array va troncato a 50 elementi (`events.value.length > 50 -> events.value.splice(50)`), mantenendo cosi' solo i 50 eventi piu' recenti.

# Passaggi operativi

### Passaggi operativi (con codice proposto)

#### 1. Nuovo store Pinia — `src/stores/notificationHistoryStore.js`

```js
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

// Numero massimo di eventi conservati in memoria per la sessione corrente.
const MAX_EVENTS = 50

export const useNotificationHistoryStore = defineStore('notificationHistory', () => {
  // Elenco eventi della sessione corrente. Si azzera automaticamente al refresh
  // della pagina perche' non e' persistito su storage.
  // Il piu' recente e' sempre in testa (index 0).
  const events = ref([])

  function addEvent(type, message) {
    events.value.unshift({
      id: Date.now() + '-' + Math.random().toString(36).slice(2),
      type, // 'positive' | 'negative'
      message,
      timestamp: new Date(),
    })

    // Manteniamo solo i MAX_EVENTS piu' recenti (i piu' vecchi vengono scartati).
    if (events.value.length > MAX_EVENTS) {
      events.value.splice(MAX_EVENTS)
    }
  }

  function clearEvents() {
    events.value = []
  }

  const eventCount = computed(() => events.value.length)

  return {
    events,
    eventCount,
    addEvent,
    clearEvents,
  }
})
```

#### 2. Aggiornare `src/composables/cassettaAttrezzi.ts`

Aggiungere l'import dello store e registrare l'evento nei due punti in cui oggi viene chiamato `$q.notify`.

```ts
import { useQuasar } from 'quasar'
import { useNotificationHistoryStore } from 'src/stores/notificationHistoryStore'

export function useCassettaAttrezzi() {
  const $q = useQuasar()
  const notificationHistory = useNotificationHistoryStore()

  const gestioneErrore = (error: any, msg: string) => {
    // ...codice esistente invariato...

    dismiss = $q.notify({
      type: 'negative',
      message: msg,
      position: 'top',
      timeout: 0,
      actions: [{ icon: 'close', color: 'white', round: true }],
      attrs: {
        onmouseenter: stopTimeout,
        onmouseleave: startTimeout,
      },
    })

    notificationHistory.addEvent('negative', msg)

    startTimeout()
    // ...resto invariato...
  }

  const messaggioPositivo = (msg: string) => {
    $q.notify({
      type: 'positive',
      message: msg,
      timeout: 2500,
      actions: [{ icon: 'close', color: 'white', round: true }],
    })
    notificationHistory.addEvent('positive', msg)
  }

  // richiediConferma invariato

  return {
    gestioneErrore,
    messaggioPositivo,
    richiediConferma,
  }
}
```

#### 3. Aggiornare `src/layouts/MainLayout.vue`

**Template** — nuovo pulsante nella toolbar (con badge del numero di eventi) e un `q-dialog` con la lista colorata:

```vue
<q-header elevated>
  <q-toolbar>
    <q-btn flat dense round icon="menu" aria-label="Menu" @click="toggleLeftDrawer" />

    <q-toolbar-title> Elab </q-toolbar-title>

    <q-btn flat dense round icon="notifications" aria-label="Cronologia notifiche" @click="showEventsDialog = true">
      <q-badge v-if="eventCount > 0" color="red" floating>{{ eventCount }}</q-badge>
    </q-btn>

    <div>Quasar v{{ $q.version }}</div>
  </q-toolbar>
</q-header>

<q-dialog v-model="showEventsDialog">
  <q-card style="min-width: 400px; max-width: 90vw">
    <q-card-section class="row items-center q-pb-none">
      <div class="text-h6">Cronologia notifiche (sessione corrente)</div>
      <q-space />
      <q-btn icon="close" flat round dense v-close-popup />
    </q-card-section>

    <q-card-section style="max-height: 60vh" class="scroll">
      <q-list separator v-if="events.length > 0">
        <q-item
          v-for="event in events"
          :key="event.id"
          :class="event.type === 'positive' ? 'bg-green-2' : 'bg-red-2'"
        >
          <q-item-section>
            <q-item-label>{{ event.message }}</q-item-label>
            <q-item-label caption>{{ formatTimestamp(event.timestamp) }}</q-item-label>
          </q-item-section>
        </q-item>
      </q-list>
      <div v-else class="text-grey text-center q-pa-md">Nessuna notifica in questa sessione.</div>
    </q-card-section>
  </q-card>
</q-dialog>
```

**Script** — import dello store, stato del dialog e helper di formattazione data:

```js
import { ref } from 'vue'
import { storeToRefs } from 'pinia'
import EssentialLink from 'components/EssentialLink.vue'
import { useNotificationHistoryStore } from 'src/stores/notificationHistoryStore'

const notificationHistory = useNotificationHistoryStore()
const { events, eventCount } = storeToRefs(notificationHistory)

const showEventsDialog = ref(false)

function formatTimestamp(date) {
  return new Date(date).toLocaleTimeString()
}

// ...linksList, leftDrawerOpen, toggleLeftDrawer invariati...
```

> Nota: l'icona `notifications` e i colori `bg-green-2` / `bg-red-2` sono classi Quasar/Material di default gia' disponibili nel progetto (nessuna nuova dipendenza).
> Nota: `clearEvents` resta esposta dallo store per eventuali usi futuri, ma **non** viene collegata a nessun pulsante nell'interfaccia (nessuna azione di "svuota cronologia" richiesta): la cronologia si azzera solo al reload della pagina.

# Rischi

### Rischi

- **Perdita degli eventi piu' vecchi oltre il limite di 50**: e' un comportamento voluto (richiesto dall'utente), non un bug; va solo assicurato che il troncamento avvenga in modo coerente con l'inserimento in testa (`unshift` + `splice(MAX_EVENTS)`), altrimenti si rischia di scartare eventi recenti invece che quelli vecchi.
- **Reattivita' tra store e composable**: `useCassettaAttrezzi()` viene istanziato piu' volte in componenti diversi; usare il Pinia store garantisce che tutte le istanze scrivano/leggano lo stesso stato condiviso, ma va verificato che l'ordine di inizializzazione di Pinia (plugin registrato in `src/boot`) permetta di chiamare `useNotificationHistoryStore()` sia dentro il composable sia dentro `MainLayout.vue` senza errori di "store non ancora installato".
- **Doppia registrazione evento**: bisogna assicurarsi che `notificationHistory.addEvent(...)` venga chiamato una sola volta per notifica (non ad ogni retry/hover del timeout in `gestioneErrore`).
- **Messaggi molto lunghi o con markup**: se `msg` contiene HTML o testo molto lungo, la card della lista potrebbe necessitare di `wrap`/`ellipsis` (dettaglio di stile, non bloccante).

# Test da eseguire

### Test da eseguire (manuali, dopo l'implementazione)

1. Chiamare un flusso che invoca `messaggioPositivo(...)` (es. salvataggio riuscito in `creazione_lavoro.vue` o `creazione_BaseDati.vue`) e verificare che l'evento compaia nella lista con **sfondo verde**.
2. Provocare un errore che invoca `gestioneErrore(...)` (es. chiamata API fallita) e verificare che l'evento compaia nella lista con **sfondo rosso**.
3. Verificare che il badge numerico sul pulsante della toolbar rifletta correttamente il numero di eventi accumulati.
4. Verificare che la lista mostri gli eventi generati da **pagine diverse** (es. un messaggio da `IndexPage.vue` e uno da `creazione_configurazione.vue`), confermando che lo stato e' effettivamente condiviso via Pinia store.
5. Aprire la lista, chiuderla con il pulsante di chiusura (`v-close-popup`) e verificare che gli eventi restino invariati (la chiusura non deve azzerare la cronologia).
6. Ricaricare la pagina (F5) e verificare che la lista risulti vuota (azzeramento allo refresh, nessuna persistenza).
7. Verificare che il layout del pulsante e del dialog sia coerente con lo stile Quasar esistente (dark/light mode se applicabile, responsive su mobile).
8. Generare piu' notifiche in sequenza e verificare che la **piu' recente compaia in cima** alla lista (ordine cronologico inverso).
9. Generare piu' di 50 eventi in una stessa sessione e verificare che la lista si stabilizzi a **50 elementi**, scartando i piu' vecchi.

# Domande aperte

### Domande aperte

Tutte le domande aperte della versione precedente del piano sono state risolte dalle indicazioni dell'utente (vedi sezione Obiettivo, "Requisiti confermati dall'utente"). Nessuna domanda aperta rimanente.

# Delivery Steps

### ✓ Step 1: Creare lo store Pinia per la cronologia notifiche
Al termine di questa fase esiste un nuovo store Pinia che conserva in memoria gli eventi di notifica della sessione corrente, limitati a 50 elementi con il piu' recente in testa.
- Creare il file `src/stores/notificationHistoryStore.js` seguendo il pattern setup-syntax di `src/stores/fileStore.js`.
- Definire lo stato `events` (array reattivo di `{id, type, message, timestamp}`).
- Implementare l'azione `addEvent(type, message)` che inserisce il nuovo evento **in testa** all'array (`unshift`) con id univoco e timestamp.
- Dopo l'inserimento, troncare l'array a **50 elementi** (`MAX_EVENTS = 50`), scartando gli eventi piu' vecchi in eccesso.
- Esporre il getter calcolato `eventCount` e l'azione `clearEvents` (mantenuta nello store per completezza, ma non collegata a nessun pulsante nell'UI).
- Non collegare nessun plugin di persistenza, cosi' lo stato si azzera automaticamente al reload della pagina.

### ✓ Step 2: Collegare gestioneErrore e messaggioPositivo allo store
Al termine di questa fase ogni chiamata a `gestioneErrore` o `messaggioPositivo` registra automaticamente l'evento nella cronologia condivisa.
- Modificare `src/composables/cassettaAttrezzi.ts` importando `useNotificationHistoryStore`.
- Istanziare lo store dentro `useCassettaAttrezzi()`.
- In `gestioneErrore`, dopo la creazione della notify, invocare `notificationHistory.addEvent('negative', msg)`.
- In `messaggioPositivo`, dopo la chiamata a `$q.notify`, invocare `notificationHistory.addEvent('positive', msg)`.
- Verificare che l'evento venga registrato una sola volta per invocazione (nessuna duplicazione dovuta al meccanismo di timeout/hover di `gestioneErrore`).

### ✓ Step 3: Aggiungere pulsante e lista eventi in MainLayout.vue
Al termine di questa fase la toolbar principale mostra un pulsante con badge numerico che apre un elenco chiudibile di tutte le notifiche della sessione, colorato per tipo, con l'evento piu' recente in cima e l'orario visibile per ciascun evento.
- Modificare `src/layouts/MainLayout.vue` aggiungendo un `q-btn` con icona (es. `notifications`) nella `q-toolbar`, con `q-badge` che mostra `eventCount`.
- Aggiungere lo stato locale `showEventsDialog` (ref booleano) per controllare l'apertura del pannello.
- Aggiungere un `q-dialog`/`q-card` con `q-list` che itera su `events` (l'ordine e' gia' dal piu' recente al piu' vecchio grazie allo store), applicando la classe `bg-green-2` per gli eventi `positive` e `bg-red-2` per gli eventi `negative`.
- Mostrare per ciascun evento sia il messaggio sia l'**orario** (`formatTimestamp` con `toLocaleTimeString()`).
- Aggiungere un pulsante di chiusura (`q-btn icon="close" v-close-popup`) nel dialog che chiude la lista senza azzerare la cronologia (nessun pulsante "svuota cronologia" nell'UI).
- Importare `useNotificationHistoryStore` e leggere `events`/`eventCount` tramite `storeToRefs`.