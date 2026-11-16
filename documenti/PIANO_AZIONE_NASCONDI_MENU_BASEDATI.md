### Piano d'Azione: Nascondere Menu Basi Dati in Creazione Base Dati

#### Obiettivo
Nascondere il menu a tendina delle basi dati esistenti nella pagina `creazione_BaseDati.vue` quando l'accesso avviene tramite il pulsante "Nuova Base Dati" dalla pagina `creazione_lavoro.vue`. Il menu deve rimanere visibile se l'accesso avviene tramite il menu laterale.

#### Analisi
La pagina `creazione_lavoro.vue` invia già un parametro nella query string quando reindirizza a `creazione_BaseDati.vue`:
```javascript
// src/pages/creazione_lavoro.vue
function nuovaBaseDati() {
  router.push({ path: '/creazione_BaseDati/', query: { creazioneLavoroInCorso: true } })
}
```

La pagina `creazione_BaseDati.vue` recupera già questo parametro:
```javascript
// src/pages/creazione_BaseDati.vue
const creazioneLavoroInCorso = route.query?.creazioneLavoroInCorso == 'true' ? true : false
```

#### Modifiche Proposte

Andrò a modificare il file `src/pages/creazione_BaseDati.vue` aggiungendo una condizione `v-if` al componente `BaseSelect` iniziale.

**File:** `src/pages/creazione_BaseDati.vue`

**Blocco da modificare (righe 4-10):**

*Codice attuale:*
```vue
      <!-- menu di selezione base dati esistente -->
      <BaseSelect
        v-model="idBaseDati"
        :options="basiDati"
        label="Basi dati create"
        @update:model-value="baseDatiCambiata"
      />
```

*Codice modificato:*
```vue
      <!-- menu di selezione base dati esistente -->
      <BaseSelect
        v-if="!creazioneLavoroInCorso"
        v-model="idBaseDati"
        :options="basiDati"
        label="Basi dati create"
        @update:model-value="baseDatiCambiata"
      />
```

#### Verifica
1. Accedendo a "Basi dati" dal menu laterale, `route.query.creazioneLavoroInCorso` sarà `undefined`, quindi `creazioneLavoroInCorso` sarà `false` e il menu sarà visibile.
2. Cliccando su "Nuova Base Dati" da `creazione_lavoro.vue`, `creazioneLavoroInCorso` sarà `true` e il menu verrà nascosto correttamente.
