# Piano d'Azione: Ottimizzazione e Correzione dei Popup in IndexPage.vue

## Analisi del Problema
Nel file `IndexPage.vue`, i componenti `<q-dialog>` relativi a **SQL**, **Prenotazione** e **Azioni** sono attualmente definiti all'interno dei template `v-slot:body-cell-*` della tabella `elaborazioniInCorso`.
Poiché la tabella renderizza il contenuto del template per ogni singola riga, vengono create molteplici istanze degli stessi popup. Quando la variabile di stato (es. `dialogSqlVisible`) diventa `true`, tutti i popup reagiscono simultaneamente, causandone l'apertura sovrapposta (effetto "popup doppio" o "triplo").

## Soluzione Proposta
Spostare i componenti `<q-dialog>` al di fuori della struttura della tabella e utilizzare delle variabili di supporto per memorizzare i dati della riga selezionata al momento del click.

---

## Modifiche al Codice (Proposta)

### 1. Nuove Variabili di Stato (Script)
Aggiungere variabili per memorizzare la riga attiva per ogni tipologia di azione.

```javascript
// Aggiungere nel blocco <script setup>
const selectedRowSql = ref(null)
const selectedRowPrenotazione = ref(null)
const selectedRowAzioni = ref(null)
```

### 2. Modifica dei Pulsanti nella Tabella (Template)
Modificare gli eventi `@click` per impostare la riga corrente prima di aprire il popup.

#### Cella Stato (Prenota)
```vue
<!-- Da riga 130 -->
<BaseBtn 
  label="Prenota" 
  @click="selectedRowPrenotazione = props.row; dialogPrenotazioneVisible = true" 
/>
```

#### Cella SQL
```vue
<!-- Da riga 172 -->
<BaseBtn 
  label="sql" 
  @click="selectedRowSql = props.row; dialogSqlVisible = true" 
/>
```

#### Cella Azioni
```vue
<!-- Da riga 230 -->
<BaseBtn 
  label="azioni" 
  @click="selectedRowAzioni = props.row; dialogAzioniVisible = true" 
/>
```

### 3. Spostamento dei Dialog fuori dalla Tabella (Template)
I blocchi `<q-dialog>` vanno spostati, ad esempio, subito prima della chiusura di `</q-page>`. I riferimenti a `props.row` devono essere sostituiti con le nuove variabili `selectedRow*`.

#### Nuovo blocco SQL (esterno)
```vue
<q-dialog v-model="dialogSqlVisible" @hide="selectedRowSql = null">
  <q-card style="max-width: 700px" v-if="selectedRowSql"><!--Il v-if garantisce che il contenuto della card venga renderizzato solo quando i dati della riga sono effettivamente disponibili.-->
    <q-card-section class="row items-center justify-between">
      <div class="text-h6">Query MySql</div>
      <div class="row q-gutter-sm">
        <BaseBtn
          dense
          flat
          color="primary"
          icon="content_copy"
          label="Copia"
          @click="copiaQuery(
            'SELECT * FROM `' + selectedRowSql.nome_base_dati + '_' + selectedRowSql.nome_elaborazione +
            '` e JOIN `ordinati_' + selectedRowSql.tipo_spedizione + '_' + selectedRowSql.nome_base_dati +
            '` o ON e.id=o.c1 ORDER BY progr'
          )"
        ></BaseBtn>
        <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
      </div>
    </q-card-section>
    <q-separator></q-separator>
    <q-card-section>
      <pre class="sql-code">
SELECT * FROM `{{ selectedRowSql.nome_base_dati }}_{{ selectedRowSql.nome_elaborazione }}` e
    JOIN `ordinati_{{ selectedRowSql.tipo_spedizione }}_{{ selectedRowSql.nome_base_dati }}` o
         ON e.id=o.c1
ORDER BY progr
      </pre>
    </q-card-section>
  </q-card>
</q-dialog>
```

#### Nuovo blocco Prenotazione (esterno)
```vue
<q-dialog v-model="dialogPrenotazioneVisible" @hide="selectedRowPrenotazione = null">
  <q-card flat bordered v-if="selectedRowPrenotazione"><!--Il v-if garantisce che il contenuto della card venga renderizzato solo quando i dati della riga sono effettivamente disponibili.-->
    <q-card-section class="row items-center justify-between">
      <div class="text-h6">Azioni</div>
      <div class="row q-gutter-sm">
        <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
      </div>
    </q-card-section>
    <q-separator></q-separator>
    <q-card-section>
      <BaseDatePicker
        v-model="dataPrenotazione[selectedRowPrenotazione.id_elaborazione]"
        label="Data prenotazione"
      ></BaseDatePicker>
      <BaseBtn
        label="Prenota"
        size="sm"
        @click="lanciaPrenotazione(selectedRowPrenotazione.id_elaborazione)"
      />
    </q-card-section>
  </q-card>
</q-dialog>
```

#### Nuovo blocco Azioni (esterno)
```vue
<q-dialog v-model="dialogAzioniVisible" @hide="selectedRowAzioni = null">
  <q-card flat bordered v-if="selectedRowAzioni"><!--Il v-if garantisce che il contenuto della card venga renderizzato solo quando i dati della riga sono effettivamente disponibili.-->
    <q-card-section class="row items-center justify-between">
      <div class="text-h6">Azioni</div>
      <div class="row q-gutter-sm">
        <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
      </div>
    </q-card-section>
    <q-separator></q-separator>
    <q-card-section>
      <div class="q-mb-md q-pa-sm bg-grey-2 rounded-borders row items-center">
        <div class="col text-caption text-weight-medium">
          Percorso salvataggio suggerito:<br />
          <code style="word-break: break-all">{{
            convertiPathWindows(selectedRowAzioni.folder_cliente + selectedRowAzioni.folder_z)
          }}</code>
        </div>
        <div class="col-auto">
          <BaseBtn
            flat
            round
            dense
            icon="content_copy"
            @click="copiaPercorso(selectedRowAzioni.folder_cliente + selectedRowAzioni.folder_z)"
          />
        </div>
      </div>
      <div v-for="(datiAzione, nomeAzione) in azioni[selectedRowAzioni.tipo_spedizione]" :key="nomeAzione">
        <base-date-picker
          v-if="datiAzione.parametri?.includes('d')"
          v-model="dataAzioni"
        ></base-date-picker>
        <BaseBtn
          :label="nomeAzione"
          @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione)"
        ></BaseBtn>
      </div>
    </q-card-section>
  </q-card>
</q-dialog>
```

## Vantaggi della Soluzione
1. **Correzione Errore Grafico:** Viene renderizzato un solo popup alla volta, eliminando l'effetto di sovrapposizione.
2. **Performance:** Il DOM è più leggero poiché non vengono creati centinaia di componenti `q-dialog` inutilizzati.
3. **Manutenibilità:** Il codice dei dialog è centralizzato in un unico punto anziché essere disperso nelle definizioni delle colonne della tabella.
