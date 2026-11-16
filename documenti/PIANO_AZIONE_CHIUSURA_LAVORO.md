### Piano d'Azione: Implementazione Chiusura Elaborazione e Gestione Dati

Il presente piano descrive le attività necessarie per implementare la chiusura dell'elaborazione con generazione di archivio ZIP, il download lato frontend, l'aggiornamento della UI e la gestione delle elaborazioni concluse (riesportazione ed eliminazione).

---

#### 1. Backend: Modifica `chiudi_elaborazione.php`

**Obiettivo:** Gestire l'esportazione dei dati, l'archiviazione ZIP e la chiusura logica dell'elaborazione, garantendo la robustezza del codice di produzione.

- **Esportazione CSV:** L'endpoint genera file CSV temporanei dai dati del database (dati anagrafici, ordinati e opzionalmente light), filtrati per `nome_elaborazione` e `id_flusso`.
- **Archiviazione ZIP:** Si utilizza `ZipStream` per l'archiviazione in streaming.
- **Gestione Errori:** Utilizzo di `throw` per bloccare l'esecuzione in caso di fallimento delle operazioni sui file.
- **Pulizia Server:** Eliminazione automatica dei file temporanei dopo lo streaming.

**Codice Implementativo (Produzione):**

```php
<?php
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    if(!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];
    
    $nome_lavoro_completo = "{$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}";
    $id_flusso = $elaborazione["id_flusso"];

    // Nomi file temporanei univoci
    $uid = uniqid();
    $temp_dir = __DIR__ . "/temp/";
    if (!is_dir($temp_dir)) {
        if(!mkdir($temp_dir, 0777, true))
            throw new \Exception("Impossibile creare cartella temporanea");
    }

    $file_anagrafica = $temp_dir . "anagrafica_" . $uid . ".csv";
    $file_ordinati = $temp_dir . "ordinati_" . $uid . ".csv";
    $file_light = $temp_dir . "ordinati_light_" . $uid . ".csv";
    
    $files_da_eliminare = [];

    // 1. Esportazione Anagrafica
    $dati_anag = $db->preleva_da_db("select * from `{$nome_lavoro_completo}` where id_flusso = ?", [$id_flusso]);
    if ($dati_anag === false) throw new \Exception("Errore prelievo dati anagrafica");
    
    $csv = new Csv();
    if(!$csv->apri($file_anagrafica, "w")) throw new \Exception("Errore apertura file anagrafica");
    if(!$csv->scrivi_array($dati_anag)) throw new \Exception("Errore scrittura file anagrafica");
    if(!$csv->chiudi()) throw new \Exception("Errore chiusura file anagrafica");
    $files_da_eliminare[] = $file_anagrafica;

    // 2. Esportazione Ordinati
    $dati_ord = $db->preleva_da_db("select * from `ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}` where nome_elaborazione=? and id_flusso=?", [$nome_lavoro_completo, $id_flusso]);
    if ($dati_ord === false) throw new \Exception("Errore prelievo dati ordinati");
    
    if(!$csv->apri($file_ordinati, "w")) throw new \Exception("Errore apertura file ordinati");
    if(!$csv->scrivi_array($dati_ord)) throw new \Exception("Errore scrittura file ordinati");
    if(!$csv->chiudi()) throw new \Exception("Errore chiusura file ordinati");
    $files_da_eliminare[] = $file_ordinati;

    // 3. Esportazione Ordinati Light (se presenti)
    $has_light = false;
    $tabella_light = "ordinati_light_{$elaborazione['nome_base_dati']}";
    // Verifica se la tabella light esiste
    $check_light = $db->preleva_da_db("SHOW TABLES LIKE ?", [$tabella_light]);
    if ($check_light) {
        $dati_light = $db->preleva_da_db("select * from `{$tabella_light}` where nome_elaborazione=? and id_flusso=?", [$nome_lavoro_completo, $id_flusso]);
        if ($dati_light && count($dati_light) > 0) {
            if(!$csv->apri($file_light, "w")) throw new \Exception("Errore apertura file light");
            if(!$csv->scrivi_array($dati_light)) throw new \Exception("Errore scrittura file light");
            if(!$csv->chiudi()) throw new \Exception("Errore chiusura file light");
            $files_da_eliminare[] = $file_light;
            $has_light = true;
        }
    }

    // 4. Creazione ZIP e Streaming
    $nome_zip = $elaborazione["nome_elaborazione"] . "_" . $id_flusso . "_" . date("Ymd_His") . ".zip";
    
    if (ob_get_length()) ob_clean();

    $zip = new \ZipStream\ZipStream(
        outputName: $nome_zip,
        sendHttpHeaders: true
    );

    $zip->addFileFromPath("anagrafica.csv", $file_anagrafica);
    $zip->addFileFromPath("ordinati.csv", $file_ordinati);
    if($has_light) $zip->addFileFromPath("ordinati_light.csv", $file_light);

    $zip->finish();

    // 5. Pulizia e Chiusura logica
    foreach($files_da_eliminare as $f) {
        if(file_exists($f)) unlink($f);
    }

    if(!$db->esegui_query("update elaborazioni set stato=255 where id=?", [$jsonData['id_elaborazione']]))
        throw new \Exception("Errore aggiornamento stato elaborazione");

    exit;

} catch (\Exception $e) {
    // In caso di errore durante lo streaming, ZipStream potrebbe aver già inviato header.
    // Il catch gestisce gli errori prima dell'inizio dello streaming.
    if (!headers_sent()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        // Logga l'errore se gli header sono già stati inviati
        if(isset($log)) $log->scrivi_log("Errore post-header in chiudi_elaborazione: " . $e->getMessage(), "ERROR");
    }
}
?>
```

---

#### 2. Backend: Nuovo Endpoint `elimina_elaborazione.php`

**Obiettivo:** Eliminare in sicurezza i record relativi a un'elaborazione specifica, identificata da ID e ID Flusso.

**Codice Implementativo (Produzione):**

```php
<?php
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('ID elaborazione mancante');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    // Prelevo info elaborazione
    $res = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]);
    if (!$res) throw new \Exception("Elaborazione non trovata");
    $el = $res[0];
    
    $nome_lavoro_completo = "{$el['nome_lavoro']}_{$el['nome_elaborazione']}";
    $id_flusso = $el['id_flusso'];

    $db->blocca_db();

    // 1. Eliminazione dai dati originali
    if(!$db->esegui_query("DELETE FROM `{$el['nome_base_dati']}` WHERE id_elaborazione = ? AND id_flusso = ?", [$jsonData["id_elaborazione"], $id_flusso]))
        throw new \Exception("Errore durante eliminazione dati originali");

    // 2. Eliminazione dai dati ordinati
    if(!$db->esegui_query("DELETE FROM `ordinati_{$el['tipo_spedizione']}_{$el['nome_base_dati']}` WHERE nome_elaborazione = ? AND id_flusso = ?", [$nome_lavoro_completo, $id_flusso]))
        throw new \Exception("Errore durante eliminazione dati ordinati");

    // 3. Eliminazione dati light (se tabella esiste)
    $tabella_light = "ordinati_light_{$el['nome_base_dati']}";
    $check_light = $db->preleva_da_db("SHOW TABLES LIKE ?", [$tabella_light]);
    if ($check_light) {
        $db->esegui_query("DELETE FROM `{$tabella_light}` WHERE nome_elaborazione = ? AND id_flusso = ?", [$nome_lavoro_completo, $id_flusso]);
    }

    $db->sblocca_db();

    echo json_encode(['success' => true, 'message' => 'Dati eliminati correttamente']);

} catch (\Exception $e) {
    if(isset($db)) $db->sblocca_db(true);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
```

---

#### 3. Frontend: Modifiche a `IndexPage.vue`

**Aggiunta funzioni Riesporta e Elimina:**

```javascript
function riesporta(row) {
  // Richiama l'endpoint di chiusura/esportazione
  const datiAzione = {
    endpoint: 'chiudi_elaborazione.php',
    output: 'zip'
  }
  lanciaAzione(datiAzione, row.id)
}

function confermaElimina(row) {
  $q.dialog({
    title: 'Conferma eliminazione',
    message: `Sei sicuro di voler eliminare i dati dell'elaborazione ${row.nome_elaborazione}? L'operazione è irreversibile e i dati verranno rimossi dal database.`,
    cancel: true,
    persistent: true
  }).onOk(() => {
    api.post('/elimina_elaborazione.php', { id_elaborazione: row.id })
      .then(() => {
        messaggioPositivo('Dati eliminati con successo')
        prelevaElaborazioniConcluse()
      })
      .catch((e) => {
        gestioneErrore(e, 'Errore durante l\'eliminazione dati')
      })
  })
}
```

**Aggiornamento `lanciaAzione` per gestire ZIP:**

```javascript
function lanciaAzione(datiAzione, id_elaborazione) {
  // ... logica esistente per parametri ...
  
  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const config = (isPdf || isZip) ? { responseType: 'blob' } : {}

  api.post('/' + datiAzione.endpoint, dati, config)
    .then((response) => {
      if (isPdf || isZip) {
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url
        
        let filename = isZip ? 'esportazione.zip' : 'etichette.pdf'
        const contentDisposition = response.headers['content-disposition']
        if (contentDisposition) {
          const fileNameMatch = contentDisposition.match(/filename="?(.+)"?/)
          if (fileNameMatch && fileNameMatch.length === 2) filename = fileNameMatch[1]
        }
        
        link.setAttribute('download', filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        
        if (datiAzione.endpoint === 'chiudi_elaborazione.php') {
          prelevaElaborazioniInCorso()
          prelevaElaborazioniConcluse()
        }
      } else {
        messaggioPositivo('Azione eseguita')
      }
    })
}
```

**Modifiche al Template (Tabella Elaborazioni Concluse):**

```html
<template v-slot:body-cell-azioni="props">
  <q-td :props="props">
    <q-btn flat round color="primary" icon="download" @click="riesporta(props.row)">
      <q-tooltip>Riesporta (ZIP)</q-tooltip>
    </q-btn>
    <q-btn flat round color="negative" icon="delete" @click="confermaElimina(props.row)">
      <q-tooltip>ELIMINA DATI</q-tooltip>
    </q-btn>
    <q-btn flat round color="orange" icon="refresh" @click="riapriElaborazione(props.row.id)">
      <q-tooltip>Riapri</q-tooltip>
    </q-btn>
  </q-td>
</template>
```

---

#### 4. Documentazione e Test

- Aggiornare `SINTESI_PROGETTO.md` per riflettere l'uso di `chiudi_elaborazione.php` e del nuovo `elimina_elaborazione.php`.
- Verificare il download del file ZIP e il contenuto dei CSV.
- Verificare che i file temporanei vengano rimossi dal server.
- Confermare che l'eliminazione rimuova effettivamente i record dalle tabelle MySQL.