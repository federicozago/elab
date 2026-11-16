### Piano d'Azione 06 - Eliminazione Configurazione Postale

Questo documento descrive le modifiche necessarie per aggiungere la funzionalità di eliminazione di una configurazione postale esistente.

#### Obiettivi
1. Aggiungere un pulsante di eliminazione nel componente `creazione_configurazione.vue`.
2. Creare l'endpoint backend `elimina_configurazione_postale.php` per gestire l'eliminazione con i relativi controlli di sicurezza.

---

### 1. Modifiche Frontend

#### File: `src/components/creazione_configurazione.vue`

Aggiunta del pulsante "ELIMINA" subito dopo il `BaseSelect` della lista configurazioni. Il pulsante compare solo se una configurazione è selezionata.

**Codice da inserire nel template (intorno alla riga 9):**

```vue
    <div class="row items-center q-gutter-x-sm">
      <BaseSelect
        class="col"
        label="Lista configurazioni esistenti"
        v-model="idConfigurazioneSelezionata"
        :options="configurazioni"
        @update:model-value="configurazioneCambiata"
        :disable="!props.tipoSpedizione"
      ></BaseSelect>
      <div class="col-auto q-mb-md" v-if="idConfigurazioneSelezionata">
        <q-btn
          color="negative"
          icon="delete"
          round
          flat
          @click="confermaEliminaConfigurazione"
        >
          <q-tooltip>Elimina questa configurazione</q-tooltip>
        </q-btn>
      </div>
    </div>
```

**Codice da aggiungere alla sezione `<script setup>`:**

```javascript
// Aggiungere ai metodi esistenti
async function confermaEliminaConfigurazione() {
  if (!idConfigurazioneSelezionata.value) return

  richiediConferma(`Sei sicuro di voler eliminare la configurazione "${idConfigurazioneSelezionata.value.label}"?`)
    .onOk(async () => {
      try {
        const response = await api.post('/elimina_configurazione_postale.php', {
          id_configurazione: idConfigurazioneSelezionata.value.value,
          tipo_spedizione: props.tipoSpedizione
        })

        if (response.data.success) {
          messaggioPositivo('Configurazione eliminata con successo')
          
          // Ricarica la lista delle configurazioni
          const res = await api.post('/preleva_configurazioni.php', { tipo_spedizione: props.tipoSpedizione })
          configurazioni.value = res.data.configurazioni
          
          // Reset selezione e form
          idConfigurazioneSelezionata.value = ''
          reset()
        }
      } catch (e) {
        gestioneErrore(
          e,
          'Errore durante l\'eliminazione della configurazione: ' + (e.response?.data?.message || e.message)
        )
      }
    })
}
```

---

### 2. Modifiche Backend

#### Nuovo File: `php-docker-boilerplate/src/elimina_configurazione_postale.php`

Questo endpoint riceve l'ID della configurazione e il tipo di spedizione. Esegue i controlli sulle tabelle `elaborazioni` e `elaborazioni_lavoro` prima di procedere all'eliminazione dalla tabella specifica del tipo di spedizione.

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
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_configurazione"]) || !isset($jsonData["tipo_spedizione"])) {
        throw new \Exception('Parametri mancanti: id_configurazione e tipo_spedizione sono richiesti.');
    }

    $id_configurazione = $jsonData["id_configurazione"];
    $tipo_spedizione = $jsonData["tipo_spedizione"]; // Corrisponde al nome della tabella

    // 1. Verifica se ci sono record in elaborazioni_lavoro con id_configurazione uguale a quello passato
    // Questo controllo copre anche il requisito di non eliminare se usata in definizioni di lavori
    $presenza_lavoro = $db->verifica_presenza_record(
        "SELECT id FROM elaborazioni_lavoro WHERE id_configurazione = ?",
        [$id_configurazione]
    );

    if ($presenza_lavoro) {
        throw new \Exception('Impossibile eliminare: la configurazione è utilizzata in una o più definizioni di lavoro.');
    }

    // 2. Verifica se nella tabella elaborazioni ci sono record con stato diverso da 255 
    // che hanno id_elaborazione_lavoro che corrisponde a id della tabella elaborazioni_lavoro 
    // che hanno id configurazione uguale a quello che si deve eliminare.
    // NOTA: Se il controllo precedente (punto 1) passa, questo è tecnicamente già garantito 
    // (se non c'è record in elaborazioni_lavoro, non può esserci in elaborazioni collegato).
    

    // 3. Eliminazione della configurazione dalla tabella specifica (es. target, massiva)
    
    $db->esegui_query(
        "DELETE FROM `$tipo_spedizione` WHERE id = ?",
        [$id_configurazione]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Configurazione eliminata con successo'
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
- L'input `tipo_spedizione` viene prelevato correttamente dal componente padre (riga 3 di `pages/creazione_configurazione.vue`).
- L'eliminazione avviene solo se la configurazione non è referenziata in `elaborazioni_lavoro`, garantendo l'integrità referenziale del sistema.
