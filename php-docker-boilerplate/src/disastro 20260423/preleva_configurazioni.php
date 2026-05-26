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
    if(!isset($jsonData["tipo_spedizione"])) throw new \Exception("Manca il tipo di spedizione");
    $tipo_spedizione = $jsonData["tipo_spedizione"];
    if( ! in_array($tipo_spedizione, array_keys($vg["azioni_elaborazioni"])))
        throw new \Exception("Tipo di spedizione non valido");

    if(!$configurazioni = $db->preleva_da_db("select id as value,nome_configurazione as label from $tipo_spedizione"))
        throw new \Exception("Errore durante prelievo configurazioni");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazioni prelevate',
        'configurazioni'=>$configurazioni
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile prelevare le configurazioni'
    ]);
}