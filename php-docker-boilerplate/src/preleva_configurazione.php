<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["tipo_spedizione"]) or !isset($jsonData["id_configurazione"]))
        throw new \Exception("Mancano dati in input");
    $tipo_spedizione = $jsonData["tipo_spedizione"];

    if(!$configurazione = $db->preleva_da_db("select * from $tipo_spedizione where id = ?", [$jsonData["id_configurazione"]]))
        throw new \Exception("Errore durante prelievo configurazioni");

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
        'message' => 'Impossibile prelevare la configurazione'
    ]);
}