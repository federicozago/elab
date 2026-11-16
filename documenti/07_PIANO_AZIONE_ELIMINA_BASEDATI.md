### Piano d'Azione 07 - Eliminazione Base Dati

Questo documento descrive le modifiche necessarie per aggiungere la funzionalità di eliminazione di una base dati esistente.

#### Obiettivi
1. Aggiungere un pulsante di eliminazione nella pagina `creazione_BaseDati.vue`.
2. Implementare l'endpoint backend `elimina_base_dati.php` per gestire l'eliminazione con i relativi controlli di sicurezza (verifica assenza riferimenti nella tabella `lavori`).

---

### 1. Modifiche Frontend

#### File: `src/pages/creazione_BaseDati.vue`

Aggiunta del pulsante di eliminazione accanto al `BaseSelect` delle basi dati esistenti. Il pulsante compare solo quando una base dati è selezionata.

**Codice da inserire nel template (intorno alla riga 5):**

```vue
    <div class="row items-center q-gutter-x-sm" v-if="!creazioneLavoroInCorso">
      <BaseSelect
        class="col"
        v-model="idBaseDati"
        :options="basiDati"
        label="Basi dati create"
        @update:model-value="baseDatiCambiata"
      />
      <div class="col-auto q-mb-md" v-if="idBaseDati">
        <q-btn
          color="negative"
          icon="delete"
          round
          flat
          @click="confermaEliminaBaseDati"
        >
          <q-tooltip>Elimina questa base dati</q-tooltip>
        </q-btn>
      </div>
    </div>
```

**Codice da aggiungere alla sezione `<script setup>`:**

```javascript
// Aggiungere ai metodi esistenti
async function confermaEliminaBaseDati() {
  if (!idBaseDati.value) return

  richiediConferma(`Sei sicuro di voler eliminare la base dati "${idBaseDati.value.label}"? Tutti i dati associati verranno rimossi permanentemente.`)
    .onOk(async () => {
      try {
        const response = await api.post('/elimina_base_dati.php', {
          id_base_dati: idBaseDati.value.value
        })

        if (response.data.success) {
          messaggioPositivo('Base dati eliminata con successo')
          
          // Ricarica la lista delle basi dati
          const res = await api.post('/preleva_basi_dati.php')
          basiDati.value = res.data.basi_dati
          
          // Reset selezione e form
          idBaseDati.value = null
          formData.value = {
            nome_base_dati: '',
            file_base_dati: null,
            campo_cap: '',
            campo_localita: '',
            campo_provincia: '',
            intestazione_si_no: null,
            separatore: ';',
            test: null,
          }
          intestazione.value = []
        }
      } catch (e) {
        gestioneErrore(
          e,
          'Errore durante l\'eliminazione della base dati: ' + (e.response?.data?.message || 'errore sconosciuto')
        )
      }
    })
}
```

---

### 2. Modifiche Backend

#### File: `php-docker-boilerplate/src/elimina_base_dati.php`

L'endpoint viene riscritto per soddisfare i requisiti di sicurezza richiesti: verifica che non ci siano lavori associati alla base dati prima dell'eliminazione.

```php
<?php
namespace indi\Classes;

require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_base_dati"])) {
        throw new \Exception('Parametro mancante: id_base_dati è richiesto.');
    }

    $id_base_dati = $jsonData["id_base_dati"];

    // 1. Verifica che non vi siano nella tabella lavori record che hanno il campo id_base_dati uguale all'id passato
    $presenza_lavoro = $db->verifica_presenza_record(
        "SELECT id FROM lavori WHERE id_base_dati = ?",
        [$id_base_dati]
    );

    if ($presenza_lavoro) {
        throw new \Exception('Impossibile eliminare: la base dati è utilizzata in uno o più lavori.');
    }

    // 2. Recupero nome tabella per eventuale pulizia (opzionale ma consigliato per coerenza con il sistema)
    $dati_base = $db->preleva_da_db("SELECT nome_base_dati FROM base_dati WHERE id = ?", [$id_base_dati]);
    if ($dati_base) {
        $nome_tabella = $dati_base[0]['nome_base_dati'];
        // Se si volesse eliminare anche la tabella fisica:
        if(!$db->esegui_query("DROP TABLE IF EXISTS `$nome_tabella` ")) throw new \Exception('Impossibile eliminare la tabella fisica: $nome_tabella;
    }

    // 3. Eliminazione dalla tabella mysql base_dati il record con id uguale a quello passato
    $db->esegui_query(
        "DELETE FROM base_dati WHERE id = ?",
        [$id_base_dati]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Base dati eliminata con successo'
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

---

### Note sul Funzionamento
- Il pulsante compare solo se l'utente non è nel flusso di creazione lavoro rapida (gestito tramite `creazioneLavoroInCorso`).
- L'eliminazione è protetta: se la base dati è "padre" di un lavoro, l'azione viene interrotta con un messaggio di errore.
- Dopo l'eliminazione, la lista delle basi dati nel dropdown viene aggiornata automaticamente.
