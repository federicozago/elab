<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

// Gestisci la richiesta preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
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


    $file_anagrafica = $temp_dir . "{$elaborazione["nome_elaborazione"]}_{$id_flusso}_anagrafica_" . $uid . ".csv";
    $file_ordinati = $temp_dir . "{$elaborazione["nome_elaborazione"]}_{$id_flusso}_ordinati_" . $uid . ".csv";
    $file_light = $temp_dir . "{$elaborazione["nome_elaborazione"]}_{$id_flusso}_ordinati_light_" . $uid . ".csv";
    
    $files_da_eliminare = [];

    // 1. Esportazione Anagrafica
    $dati_anag = $db->preleva_da_db("select * from `{$nome_lavoro_completo}` where id_flusso = ?", [$id_flusso]);
    if ($dati_anag === false) throw new \Exception("Errore prelievo dati anagrafica");
    //fopen("/var/www/html/temp/anagrafica_6a22c9e3af330.csv","w+");
    $csv = new Csv();
    if(!$csv->apri($file_anagrafica, mode:"w+")) throw new \Exception("Errore apertura file anagrafica");
    if(!$csv->scrivi_array($dati_anag)) throw new \Exception("Errore scrittura file anagrafica");
    if(!$csv->chiudi()) throw new \Exception("Errore chiusura file anagrafica");
    $files_da_eliminare[] = $file_anagrafica;

    // 2. Esportazione Ordinati
    $dati_ord = $db->preleva_da_db("select * from `ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}` where nome_elaborazione=? and id_flusso=?", [$nome_lavoro_completo, $id_flusso]);
    if ($dati_ord === false) throw new \Exception("Errore prelievo dati ordinati");
    
    if(!$csv->apri($file_ordinati, mode:"w")) throw new \Exception("Errore apertura file ordinati");
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
            if(!$csv->apri($file_light, mode:"w")) throw new \Exception("Errore apertura file light");
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

    $zip->addFileFromPath(basename($file_anagrafica), $file_anagrafica);
    $zip->addFileFromPath(basename($file_ordinati), $file_ordinati);
    if($has_light) $zip->addFileFromPath(basename($file_light), $file_light);

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



