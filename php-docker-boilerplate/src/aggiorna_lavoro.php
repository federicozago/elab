<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Gestisci la richiesta preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore:false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["id_lavoro"]))
        throw new \Exception("Manca il tipo di lavoro");
    $db->blocca_db();

    //aggiorno i dati del lavoro
    $dati_lavoro = $jsonData;
    unset($dati_lavoro["elaborazioni"]);
    unset($dati_lavoro["elaborazioni"]);
    if(!$db->esegui_query("update lavori set nome_lavoro = :nome_lavoro, id_base_dati=:id_base_dati where id = :id_lavoro",$dati_lavoro))
        throw new \Exception("Errore durante l'aggiornamento del lavoro");

    $elaborazioni = $jsonData['elaborazioni'];
    foreach($elaborazioni as $key => $elaborazione){
        $elaborazione["id_configurazione"] = $elaborazione["id_configurazione"]["value"];
        if(!$db->esegui_query("update elaborazioni_lavoro set nome_elaborazione = :nome_elaborazione, `where`= :where, tipo_spedizione = :tipo_spedizione, id_configurazione=:id_configurazione where id=:id",$elaborazione))
            throw new \Exception("Errore durante l'aggiornamento dell'elaborazione");
    }

    $db->sblocca_db();
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione aggiornata'
    ]);
}catch(\Exception $e) {
    if(isset($db))
        $db->sblocca_db(true);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile aggiornare la configurazione - ' . $e->getMessage()
    ]);
}