### PIANO 04 - ELIMINAZIONE LAVORO

Questo piano descrive l'implementazione della funzionalità di eliminazione di un lavoro dalla pagina `creazione_lavoro.vue`, includendo la creazione dell'endpoint backend necessario e le logiche di sicurezza per l'integrità dei dati.

---

### 1. Backend: Nuovo Endpoint `elimina_lavoro.php`

Verrà creato un nuovo file in `php-docker-boilerplate/src/elimina_lavoro.php`.

**Logica di eliminazione:**
1. Verificare se esistono record nella tabella `elaborazioni` collegati al lavoro (tramite `id_elaborazione_lavoro`) che hanno un `stato != 255`.
2. Se esistono elaborazioni non concluse, bloccare l'operazione e restituire un errore.
3. Se tutte sono concluse (o non esistono):
    - Eliminare i record dalla tabella `elaborazioni` collegati alle `elaborazioni_lavoro` del lavoro.
    - Eliminare i record dalla tabella `elaborazioni_lavoro` collegati al lavoro.
    - Eliminare il record dalla tabella `lavori`.

**Codice proposto per `elimina_lavoro.php`:**

```php
<?php
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_lavoro"])) {
        throw new \Exception("ID lavoro mancante");
    }

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], false);
    $db = new Gestione_db("elab", $log);

    $id_lavoro = $jsonData["id_lavoro"];

    // 1. Verifica se ci sono elaborazioni aperte (stato != 255)
    // Dobbiamo controllare nella tabella 'elaborazioni' filtrando per le 'elaborazioni_lavoro' appartenenti a questo lavoro
    $checkQuery = "
        SELECT COUNT(*) 
        FROM elaborazioni e
        JOIN elaborazioni_lavoro el ON e.id_elaborazione_lavoro = el.id
        WHERE el.id_lavoro = ? AND e.stato != 255
    ";
    
    $elaborazioniAperte = $db->preleva_da_db_un_singolo_valore($checkQuery, [$id_lavoro]);

    if ($elaborazioniAperte > 0) {
        throw new \Exception("Impossibile eliminare il lavoro: ci sono {$elaborazioniAperte} elaborazioni non ancora concluse.");
    }

    $db->blocca_db();

    // 2. Eliminazione record tabella 'elaborazioni'
    $deleteElabQuery = "
        DELETE e FROM elaborazioni e
        JOIN elaborazioni_lavoro el ON e.id_elaborazione_lavoro = el.id
        WHERE el.id_lavoro = ?
    ";
    $db->esegui_query($deleteElabQuery, [$id_lavoro]);

    // 3. Eliminazione record tabella 'elaborazioni_lavoro'
    $db->esegui_query("DELETE FROM elaborazioni_lavoro WHERE id_lavoro = ?", [$id_lavoro]);

    // 4. Eliminazione record tabella 'lavori'
    $db->esegui_query("DELETE FROM lavori WHERE id = ?", [$id_lavoro]);

    $db->sblocca_db();

    echo json_encode([
        'success' => true,
        'message' => 'Lavoro eliminato con successo'
    ]);

} catch (\Exception $e) {
    if (isset($db)) $db->sblocca_db(true);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
```

---

### 2. Frontend: Modifiche a `creazione_lavoro.vue`

**Obiettivo:** Aggiungere un pulsante "Elimina Lavoro" che appare solo quando viene selezionato un lavoro esistente dal menu a tendina in modalità modifica.

**Modifiche al Template:**

Inserire il pulsante vicino al titolo o alla selezione del lavoro. Ad esempio, dopo il `BaseSelect` della lista lavori.

```html
<!-- Intorno alla riga 37 di creazione_lavoro.vue -->
<div class="row items-center q-gutter-x-md">
  <div class="col">
    <BaseSelect
      label="Lista lavori esistenti"
      v-model="lavoro"
      :options="lavori"
      @update:model-value="lavoroCambiato"
      v-if="isModifica"
    ></BaseSelect>
  </div>
  <div class="col-auto" v-if="isModifica && lavoro">
    <BaseBtn 
      label="Elimina lavoro" 
      color="negative" 
      icon="delete" 
      @click="confermaEliminaLavoro"
    />
  </div>
</div>
```

**Modifiche allo Script:**

Aggiungere la funzione di eliminazione e la conferma.

```javascript
// Aggiungere nelle importazioni di cassettaAttrezzi se necessario
// const { messaggioPositivo, gestioneErrore, confermaAzione } = useCassettaAttrezzi()

async function confermaEliminaLavoro() {
  if (!lavoro.value) return

  const confermato = await confermaAzione(
    `Sei sicuro di voler eliminare il lavoro "${lavoro.value.label}"? L'operazione eliminerà anche tutte le definizioni e i dati associati.`
  )

  if (confermato) {
    eliminaLavoro()
  }
}

async function eliminaLavoro() {
  try {
    isSubmitting.value = true
    const response = await api.post('/elimina_lavoro.php', {
      id_lavoro: lavoro.value.value
    })

    if (response.data.success) {
      messaggioPositivo('Lavoro eliminato con successo')
      // Reset del form o reindirizzamento
      router.push('/')
    }
  } catch (error) {
    gestioneErrore(error, 'Errore durante l\'eliminazione del lavoro')
  } finally {
    isSubmitting.value = false
  }
}
```

---

### 3. Aggiornamento Documentazione

- [x] Aggiornato `SINTESI_PROGETTO.md` per includere il nuovo endpoint `elimina_lavoro.php` e le relative logiche.

---

### Note sull'integrità dei dati
L'eliminazione a cascata manuale (senza affidarsi esclusivamente ai `FOREIGN KEY ON DELETE CASCADE` del DB) permette di inserire il controllo sullo stato delle elaborazioni (stato 255), garantendo che non vengano eliminati lavori con processi ancora attivi.
