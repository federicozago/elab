# Piano di intervento — Raggruppamento pulsanti "Azioni" in sezioni

> Stato: **APPLICATO (parte Frontend)** — la modifica al template Vue (Passo 2) è stata implementata in `src/pages/IndexPage.vue`. La modifica al backend (Passo 1) **non è stata applicata**: come spiegato nella nuova sezione "Esito applicazione" più sotto, il file reale che definisce l'array `azioni` non è presente in questo repository.
>
> Aggiornamento: le domande aperte poste nella versione precedente del piano sono state risolte dall'utente (vedi sezione "Domande aperte" in fondo al file). Le sezioni del piano sono state aggiornate di conseguenza. Successivamente, su richiesta esplicita ("applica il piano"), è stata implementata la parte Frontend.

## Obiettivo

Modificare il div delle azioni presente in `src/pages/IndexPage.vue` (attualmente a partire dalla riga **321**) affinché, invece di elencare tutti i pulsanti delle azioni in un'unica lista "piatta", li raggruppi in **sezioni logiche** (es. "sezione 1", "sezione 2", "sezione 3"), ciascuna introdotta da un separatore visivo con il titolo della sezione, e con i relativi pulsanti azione disposti sotto di essa.

Questo richiede che la struttura dati `azioni` (attualmente un oggetto `{ tipo_spedizione: { nome_azione: { endpoint, output, ... } } }`) venga trasformata in un oggetto a **due livelli**:

```
azioni[tipo_spedizione][nome_sezione][nome_azione] = { endpoint, output, ... }
```

## File coinvolti

- `src/pages/IndexPage.vue`
  - Template: div azioni, righe **321-334** (blocco `v-for` da modificare). **✓ Modificato.**
  - Script: variabile reattiva `azioni` (dichiarata come `const azioni = ref({})`), popolata in `onMounted` tramite la chiamata `api.post('/preleva_azioni_elaborazioni.php')`. Nessuna modifica necessaria (confermato durante l'applicazione).
- `php-docker-boilerplate/src/preleva_azioni_elaborazioni.php`
  - È il file PHP che **restituisce** l'array `azioni` (`echo json_encode([... 'azioni' => $vg["elaborazioni"] ...])`), ma **non lo costruisce**: si limita a leggere `$vg["elaborazioni"]`, dove `$vg` proviene da `new Variabili_globali_import(); $vg->get_variabili_globali("elab")`.
  - **Esito verifica in fase di applicazione**: la classe `Variabili_globali_import` è caricata tramite `require 'vendor/autoload.php'` (namespace `indi\Classes`), ma **nessuna cartella `vendor` è presente in questo repository** e la classe non è definita in nessun altro file del progetto. Il file/config reale che contiene l'array delle azioni (con le voci "Genera etichette", "Rilancia ordinamento", ecc.) **vive quindi fuori da questo repository** (verosimilmente sul percorso di rete citato nell'issue originale, `\\172.21.15.244\docker\h2h\easyapp\gea\app\ftp_input\`, oppure in un package Composer privato non vendorizzato qui).
  - **Di conseguenza il Passo 1 (backend) non è stato e non può essere applicato da questa sessione**: non esiste alcun file editabile nel repository che contenga la struttura reale da trasformare in sezioni. Vedi sezione "Esito applicazione" per i dettagli e le azioni consigliate all'utente.
- **Nota**: è stato individuato nel repository un file `temp/elab.php`, che contiene una struttura simile a `"elaborazioni" => [...]` (con chiavi `"target"` e `"massiva"`), verosimilmente una copia locale/di lavoro del file di configurazione reale (`variabili_globali.php`, non presente nel repo). **Non è stato modificato** perché non corrisponde esattamente alla struttura descritta nell'issue (tipo_spedizione `"market"` con l'elenco completo delle 8 azioni) e non è chiaro se sia il file effettivamente caricato in produzione o solo una bozza di lavoro dell'utente; è citato qui solo a titolo informativo.
- **Confermato dall'utente**: l'array `azioni` viene letto **solo** in questo blocco di codice (`preleva_azioni_elaborazioni.php` → `IndexPage.vue`). Non esistono altri script PHP o componenti Vue da aggiornare in parallelo (all'interno del repository).

## Analisi del problema

1. Attualmente l'array PHP restituito da `preleva_azioni_elaborazioni.php` ha la forma:
   ```php
   "market" => [
       "Genera etichette" => ["endpoint" => "genera_etichette.php", "output" => "pdf"],
       "Aggiungi ordinamento bancali diretti" => ["endpoint" => "aggiungi_ordinamento_bancali_diretti.php"],
       // ...
   ]
   ```
   e Vue lo consuma con un singolo `v-for` a un livello:
   ```html
   <div
     v-for="(datiAzione, nomeAzione) in azioni[selectedRowAzioni.tipo_spedizione]"
     :key="nomeAzione"
   >
     <base-date-picker v-if="datiAzione.parametri?.includes('d')" v-model="dataAzioni"></base-date-picker>
     <BaseBtn
       :label="nomeAzione"
       @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione, nomeAzione)"
       :loading="isActionSubmitting === nomeAzione"
     ></BaseBtn>
   </div>
   ```

2. La nuova struttura richiesta introduce un livello intermedio "sezione":
   ```php
   "market" => [
       "sezione 1" => [
           "Genera etichette" => ["endpoint" => "genera_etichette.php", "output" => "pdf"],
       ],
       "sezione 2" => [
           "Aggiungi ordinamento bancali diretti" => ["endpoint" => "aggiungi_ordinamento_bancali_diretti.php"],
           "Genera etichetta bancale" => ["endpoint" => "genera_etichette_bancale.php", "output" => "pdf"],
           "Genera report bancali" => ["endpoint" => "genera_report_bancali.php", "output" => "xlsx"],
       ],
       "sezione 3" => [
           "Rilancia ordinamento" => ["endpoint" => "lancia_ordinamento.php"],
           "Elimina dati ordinati" => ["endpoint" => "elimina_dati_ordinati.php"],
           "Chiudi elaborazione senza salvare dati" => ["endpoint" => "chiudi_elaborazione_senza_salvare_dati.php"],
           "Chiudi elaborazione" => ["endpoint" => "chiudi_elaborazione.php", "output" => "zip"],
       ],
   ]
   ```

3. Lato Vue occorre quindi un **doppio `v-for`**: il primo itera sulle sezioni (`nomeSezione`), il secondo sulle azioni contenute in ciascuna sezione (`nomeAzione`). Tra una sezione e l'altra va inserito un separatore visivo (es. `q-separator` + etichetta testuale con il nome della sezione), coerente con lo stile già usato altrove nel file (es. `<q-separator></q-separator>` seguito da `<div class="text-h6">...</div>` nei dialog "Query MySql" e "Prenotazione").

   **Confermato dall'utente**: il nome mostrato per ciascuna sezione deve corrispondere **esattamente** alla chiave usata come titolo della sezione nell'array PHP (es. `"sezione 1"`, `"sezione 2"`): nessuna mappatura/traduzione aggiuntiva è necessaria, basta interpolare direttamente `nomeSezione` nel template (`{{ nomeSezione }}`).

   **Confermato dall'utente**: il raggruppamento in sezioni (numero e nomi) **può variare liberamente per ogni `tipo_spedizione`**: la struttura proposta lo consente già nativamente, perché il primo `v-for` itera sulle sole sezioni definite per il `tipo_spedizione` selezionato (`azioni[selectedRowAzioni.tipo_spedizione]`), senza alcun vincolo di uniformità tra tipi di spedizione diversi.

4. La funzione `lanciaAzione(datiAzione, id_elaborazione, nomeAzione)` non necessita di modifiche, perché riceve già l'oggetto singolo `datiAzione` e il nome dell'azione: la struttura a sezioni è solo un livello di raggruppamento "di passaggio" nel template, non cambia il contratto di questa funzione.

5. Vincolo del `:key` in Vue: essendo presenti due `v-for` annidati, occorre garantire chiavi univoche in entrambi i livelli (`nomeSezione` per il livello esterno, `nomeAzione` per quello interno — già presente).

## Passaggi operativi

### Passo 1 — Backend PHP (NON applicabile da questo repository)

Aggiornare la struttura restituita per ogni `tipo_spedizione` in modo che abbia il livello intermedio "sezione", come mostrato nella sezione "Analisi del problema" punto 2.

**Esito**: non implementabile da questa sessione. `preleva_azioni_elaborazioni.php` si limita a restituire `$vg["elaborazioni"]`; l'array vero e proprio è definito in un file di configurazione (`variabili_globali.php` o equivalente) caricato dalla classe `Variabili_globali_import`, che **non è presente in questo repository** (nessuna cartella `vendor`, nessun'altra definizione della classe trovata). Questo passo dovrà essere applicato manualmente dall'utente direttamente sul server/file di configurazione reale, seguendo la struttura a sezioni mostrata nel punto 2 dell'Analisi del problema.

### Passo 2 — Frontend Vue: modifica del template (righe 321-334 di `IndexPage.vue`)

Codice attuale (righe 321-334):
```html
          <div
            v-for="(datiAzione, nomeAzione) in azioni[selectedRowAzioni.tipo_spedizione]"
            :key="nomeAzione"
          >
            <base-date-picker
              v-if="datiAzione.parametri?.includes('d')"
              v-model="dataAzioni"
            ></base-date-picker>
            <BaseBtn
              :label="nomeAzione"
              @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione, nomeAzione)"
              :loading="isActionSubmitting === nomeAzione"
            ></BaseBtn>
          </div>
```

Codice proposto (da verificare/adattare manualmente prima dell'applicazione — **aggiornato in base alle risposte dell'utente**: nessun separatore prima della prima sezione, nessuna gestione di retrocompatibilità):
```html
          <div
            v-for="(azioniSezione, nomeSezione, indexSezione) in azioni[selectedRowAzioni.tipo_spedizione]"
            :key="nomeSezione"
          >
            <q-separator v-if="indexSezione > 0" class="q-my-md" />
            <div class="text-subtitle2 text-weight-bold text-grey-8 q-mb-sm">
              {{ nomeSezione }}
            </div>
            <div
              v-for="(datiAzione, nomeAzione) in azioniSezione"
              :key="nomeAzione"
            >
              <base-date-picker
                v-if="datiAzione.parametri?.includes('d')"
                v-model="dataAzioni"
              ></base-date-picker>
              <BaseBtn
                :label="nomeAzione"
                @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione, nomeAzione)"
                :loading="isActionSubmitting === nomeAzione"
              ></BaseBtn>
            </div>
          </div>
```

Note sulla proposta (aggiornate):
- **Separatore prima della prima sezione**: come confermato dall'utente, **non è necessario**. La sintassi `v-for="(value, key, index) in object"` di Vue fornisce anche l'indice numerico dell'iterazione su un oggetto; usando `v-if="indexSezione > 0"` sul `q-separator` si evita il separatore ridondante subito dopo quello già presente sopra il blocco "Percorso salvataggio suggerito", mostrandolo solo **tra** una sezione e la successiva.
- **Nome della sezione**: `nomeSezione` viene interpolato direttamente (`{{ nomeSezione }}`) e corrisponde esattamente alla chiave dell'array PHP (es. `"sezione 1"`, `"sezione 2"`), senza bisogno di alcuna traduzione/mappatura.
- **Stile del separatore/etichetta**: l'utente ha confermato piena libertà di scelta sullo stile. La classe `text-subtitle2 text-weight-bold text-grey-8` proposta è solo un'ipotesi in linea con Quasar; può essere sostituita con altre classi già in uso nel progetto (es. `text-h6` usato nei dialog esistenti) per mantenere coerenza visiva, a discrezione di chi implementa.
- Non sono necessarie modifiche allo script (`<script setup>`), poiché `azioni`, `lanciaAzione`, `dataAzioni`, `isActionSubmitting` restano identici nella firma e nel comportamento.

**✓ ESITO: implementato.** Il codice sopra riportato come "Codice proposto" è stato applicato *esattamente com'è* alle righe 321-334 (ora 321-343) di `src/pages/IndexPage.vue`. Verificato con lint (`npx eslint src/pages/IndexPage.vue`) senza errori. Nessuna modifica allo script `<script setup>` è stata necessaria.

### Passo 3 — Sincronizzazione con il backend (nessuna retrocompatibilità richiesta)

L'utente ha confermato che **non è necessario** prevedere retrocompatibilità con la vecchia struttura "piatta": backend e frontend verranno rilasciati **sempre in modo sincronizzato**. Non è quindi richiesto alcun controllo/fallback per rilevare la vecchia struttura dell'array `azioni`; la modifica al template presuppone semplicemente che, al momento del rilascio, la fonte dati reale (fuori da questo repository) restituisca già la nuova struttura a due livelli (`tipo_spedizione` → `sezione` → `azione`).

**⚠️ ATTENZIONE OPERATIVA**: poiché il Passo 1 (backend) **non è stato applicato** (vedi sopra), a questo punto il frontend è pronto ma **si aspetta già la nuova struttura a sezioni**. Finché il file di configurazione reale (esterno al repository) non viene aggiornato manualmente dall'utente con la struttura a due livelli, il dialog "Azioni" **non mostrerà più correttamente i pulsanti** (l'array attuale, ancora "piatto", verrebbe interpretato come se ogni nome di azione fosse il nome di una sezione, e le sue proprietà `endpoint`/`output` come se fossero azioni). **Prima di rilasciare/testare in produzione, aggiornare l'array reale seguendo la struttura del punto 2 dell'Analisi del problema.**

## Rischi

- **Rischio di rottura funzionale se backend e frontend non sono sincronizzati**: la modifica del template presuppone che `preleva_azioni_elaborazioni.php` restituisca già la struttura a due livelli. Se applicata solo la modifica Vue senza l'aggiornamento PHP (in un unico rilascio sincronizzato, come confermato dall'utente), i pulsanti azione non verranno più generati correttamente (o verranno generati con etichette/comportamenti errati). **Non è previsto alcun meccanismo di retrocompatibilità**, quindi il deploy dei due file deve avvenire contestualmente.
- **Chiavi `v-for` duplicate**: se due `tipo_spedizione` diversi avessero sezioni con lo stesso nome ma comportamento diverso, non c'è impatto perché il primo `v-for` è già ambito al `tipo_spedizione` selezionato (confermato: ogni `tipo_spedizione` può avere sezioni/azioni completamente diverse); nessun rischio aggiuntivo qui.
- **Stile visivo**: l'introduzione del separatore/etichetta di sezione può necessitare di piccoli aggiustamenti di spaziatura (margini/padding) per restare coerente con lo stile del resto del dialog "Azioni" (stile libero, a discrezione di chi implementa).

## Test da eseguire

1. **Test manuale UI**: apertura del dialog "Azioni" per un'elaborazione con `tipo_spedizione` che abbia più sezioni configurate, verificando che:
   - vengano mostrati i separatori con il titolo di ciascuna sezione;
   - sotto ogni separatore compaiano solo i pulsanti delle azioni appartenenti a quella sezione;
   - l'ordine delle sezioni e delle azioni al loro interno rispetti l'ordine definito nell'array PHP.
2. **Test funzionale sui pulsanti**: verificare che il click su ciascun pulsante azione lanci ancora correttamente `lanciaAzione(datiAzione, id_elaborazione, nomeAzione)` con i parametri corretti (nessuna regressione nel comportamento esistente: download PDF/XLSX/ZIP, gestione errori, gestione parametro data `d`).
3. **Test su `tipo_spedizione` diversi con sezioni differenti**: verificare che, cambiando `tipo_spedizione`, il numero e i nomi delle sezioni mostrate cambino coerentemente con quanto definito nell'array PHP per quello specifico tipo (nessun "contaminamento" tra sezioni di tipi di spedizione diversi).
4. **Test di regressione sugli altri dialog**: verificare che le modifiche non abbiano effetti collaterali sui dialog "Query MySql" e "Prenotazione", che non vengono toccati da questa modifica ma condividono lo stesso file.
5. **Lint/build**: eseguire il linter/build del progetto Quasar (`npm run lint`, `npm run build` o equivalente) per assicurarsi che la sintassi del template modificato sia valida.

> Nota: non è previsto alcun test di retrocompatibilità con la vecchia struttura "piatta", poiché backend e frontend verranno sempre rilasciati in modo sincronizzato (confermato dall'utente).

## Esito applicazione (sessione corrente)

- **Frontend (`src/pages/IndexPage.vue`)**: ✅ **Applicato.** Template modificato con doppio `v-for` (sezioni → azioni), separatore `q-separator` mostrato solo tra sezioni (`v-if="indexSezione > 0"`) ed etichetta `{{ nomeSezione }}`. Lint eseguito con esito positivo, nessun altro riferimento nello script alla vecchia struttura piatta.
- **Backend (array `azioni` con struttura a sezioni)**: ❌ **Non applicato — non applicabile da questo repository.** Il file `preleva_azioni_elaborazioni.php` presente nel repo non contiene l'array delle azioni: si limita a leggerlo da `Variabili_globali_import::get_variabili_globali("elab")["elaborazioni"]`, una classe caricata via `vendor/autoload.php` **non presente in questo repository** (nessuna cartella `vendor`, nessuna definizione della classe altrove nel progetto). Il file di configurazione reale (probabile `variabili_globali.php`) vive quindi fuori dal repository — verosimilmente sul percorso di rete indicato nell'issue originale (`\\172.21.15.244\docker\h2h\easyapp\gea\app\ftp_input\`).
- **Azione richiesta all'utente**: per completare il piano, occorre aggiornare **manualmente** il file di configurazione reale (fuori da questo repository) trasformando l'array `elaborazioni[tipo_spedizione]` da struttura piatta a struttura a due livelli (`tipo_spedizione → sezione → azione`), seguendo l'esempio riportato nel punto 2 della sezione "Analisi del problema". **Il rilascio del frontend già modificato dovrà avvenire in modo sincronizzato con questo aggiornamento backend**, altrimenti il dialog "Azioni" mostrerà i pulsanti in modo errato (vedi "Rischi").
- **File `temp/elab.php`**: individuato nel repository, sembra una copia locale del file di configurazione, ma con una struttura (`tipo_spedizione` = `"target"`/`"massiva"`) diversa da quella riportata nell'issue originale (`"market"`). **Non è stato modificato** in questa sessione per evitare di introdurre dati non verificati/errati; l'utente può usarlo come base se conferma che è effettivamente il file sincronizzato con il server di produzione.

## Domande aperte (RISOLTE dall'utente)

1. **Nomi reali delle sezioni**: risolto — i nomi mostrati devono corrispondere **esattamente** alla chiave usata come titolo della sezione nell'array PHP (es. se l'array definisce `"sezione 1"` e `"sezione 2"`, questi sono i nomi effettivamente mostrati in UI, senza alcuna traduzione o rimappatura).
2. **Ambito della modifica backend**: risolto — l'array `azioni` viene letto **solo** in `preleva_azioni_elaborazioni.php`; non esistono altri script PHP da aggiornare in parallelo.
3. **Uniformità delle sezioni tra `tipo_spedizione`**: risolto — **no**, ogni `tipo_spedizione` può avere sezioni e azioni completamente diverse (sia in numero che in nome); la struttura proposta lo supporta nativamente.
4. **Retrocompatibilità**: risolto — **non è richiesta**. Backend e frontend verranno sempre rilasciati in modo sincronizzato.
5. **Stile del separatore/etichetta di sezione**: risolto — libero, nessun vincolo dal design system; si può seguire la proposta di stile indicata in questo piano o adattarla liberamente.
6. **Separatore prima della prima sezione**: risolto — **non è necessario**; va omesso per evitare doppioni con il separatore già presente sopra il blocco "Percorso salvataggio suggerito" (vedi implementazione con `indexSezione > 0` nel Passo 2).
