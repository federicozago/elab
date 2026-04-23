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
        throw new \Exception("Manca il tipo di spedizione o l'id della configurazione");
    $tipo_spedizione = $jsonData["tipo_spedizione"];
    $jsonData = array_merge($jsonData, $jsonData[$tipo_spedizione]);//alcuni parametri sono all'interno di $jsonData[$tipo_spedizione]


    if( ! $colonne = $db->preleva_colonne("{$vg["database_cliente"]}.$tipo_spedizione"))
        throw new \Exception("Errore durante prelievo colonne");

    //imposto query update
    $update = "";
    $valori=[];
    foreach($colonne as $colonna)
        if(array_key_exists($colonna, $jsonData)) {
            $valori[] = $jsonData[$colonna];
            $update = $update ? $update . ", $colonna = ?" : "$colonna = ?";
        }

    $valori[] = $jsonData["id_configurazione"];
    if(!$db->esegui_query("update $tipo_spedizione set $update where id = ?",$valori))
        throw new \Exception("Errore durante aggiornamento configurazione");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione aggiornata',
        'id_configurazione'=>$jsonData["id_configurazione"]
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile aggiornare la configurazione'
    ]);
}