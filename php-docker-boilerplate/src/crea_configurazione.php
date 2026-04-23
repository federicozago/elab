<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// All'inizio del file, dopo gli header
//file_put_contents('debug.log', "Richiesta ricevuta: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
//file_put_contents('debug.log', "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);
//file_put_contents('debug.log', "Input: " . file_get_contents('php://input') . "\n", FILE_APPEND);

$vg=new Variabili_globali_import();
$vg=$vg->get_variabili_globali("elab");
$log = new Segnalazioni_e_log($vg["id_flusso"]);
$db = new Gestione_db("elab",$log);
$jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

$tipo_spedizione = $jsonData["tipo_spedizione"];
$jsonData = array_merge($jsonData, $jsonData[$tipo_spedizione]);//alcuni parametri sono all'interno di $jsonData[$tipo_spedizione]

if(!$db->carica_a_db($jsonData,$tipo_spedizione,null,true)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile creare la configurazione'
    ]);
}else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione creata con successo',
        'id_configurazione'=>$db->get_ultimo_id_inserito()
    ]);
}