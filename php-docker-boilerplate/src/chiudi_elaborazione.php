<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS se necessario
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    // Verifica se è stato inviato un file
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    if(!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];

    //faccio un dump del db anagrafiche
    if(!$dati = $db->preleva_da_db("select * from {$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}"))
        throw new \Exception("Errore durante prelievo dati elaborazione");
    //esporto i dati
    $csv = new Csv();
    if(!$csv->apri("{$elaborazione["folder_cliente"]} / {$elaborazione["folder_z"]} / {$elaborazione["nome_elaborazione"]}.csv"))
        throw new \Exception("Errore durante apertura file");
    if(!$csv->scrivi_array($dati))
        throw new \Exception("Errore durante scrittura file");
    if(!$csv->chiudi())
        throw new \Exception("Errore durante chiusura file");

    //faccio dump ordinati
    if(!$dati = $db->preleva_da_db("select * from ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']} where nome_elaborazione='{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}'"))
        throw new \Exception("Errore durante prelievo dati ordinati");
    //esporto i dati
    $csv = new Csv();
    if(!$csv->apri("{$elaborazione["folder_cliente"]} / {$elaborazione["folder_z"]} / {$elaborazione["nome_elaborazione"]}_ordinati.csv"))
        throw new \Exception("Errore durante apertura file");
    if(!$csv->scrivi_array($dati))
        throw new \Exception("Errore durante scrittura file");
    if(!$csv->chiudi())
        throw new \Exception("Errore durante chiusura file");

    //faccio dump ordinati light se ci sono
    /*if($elaborazione['tipo_spedizione'] == "light") {
        $dati = $db->preleva_da_db("select * from ordinati_light_{$elaborazione['nome_base_dati']} where nome_elaborazione='{$elaborazione['nome_base_dati']}_{$elaborazione['id']}'");
        if ($dati === false)
            throw new \Exception("Errore durante prelievo dati ordinati");
        if (count($dati)) {
            //esporto i dati
            $csv = new Csv();
            if (!$csv->apri("{$elaborazione["folder_cliente"]} / {$elaborazione["folder_z"]} / {$elaborazione["nome_elaborazione"]}_ordinati_light.csv"))
                throw new \Exception("Errore durante apertura file");
            if (!$csv->scrivi_array($dati))
                throw new \Exception("Errore durante scrittura file");
            if (!$csv->chiudi())
                throw new \Exception("Errore durante chiusura file");
        }
    }*/

    //segno elaborazione come chiusa
    if(!$db->esegui_query("update elaborazioni set stato=255 where id={$jsonData['id_elaborazione']}"))//aggiorno stato "elaborazione"
        throw new \Exception("Errore durante l'aggiornamento della tabella elaborazioni");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Elaborazione chiusa correttamente'
    ]);
}catch (\Exception $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>



