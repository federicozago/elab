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
}//Quando un'applicazione web (ad esempio un frontend in Vue.js o React che risiede su un dominio diverso da quello dell'API) tenta di effettuare una richiesta HTTP "complessa" (come una POST con JSON o una richiesta che include header personalizzati), il browser invia automaticamente una prima richiesta con metodo OPTIONS prima della richiesta effettiva. Risposta di successo: Invia uno stato HTTP 200 OK (http_response_code(200)). Questo comunica al browser che il server accetta le modalità della richiesta descritte negli header inviati precedentemente (righe 6-8):

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["tipo_spedizione"]) or !isset($jsonData["id_configurazione"]))
        throw new \Exception("Mancano dati in input");
    $tipo_spedizione = $jsonData["tipo_spedizione"];

    if(!$configurazione = $db->preleva_da_db("select * from $tipo_spedizione where id = ?", [$jsonData["id_configurazione"]]))
        throw new \Exception("Errore durante prelievo configurazioni -  " . $db->get_errori());

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione prelevata',
        'configurazione'=>$configurazione[0]
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile prelevare la configurazione - '.$e->getMessage()
    ]);
}