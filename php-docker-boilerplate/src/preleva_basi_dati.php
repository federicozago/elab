<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$vg=new Variabili_globali_import();
$vg=$vg->get_variabili_globali("elab");
$log = new Segnalazioni_e_log($vg["id_flusso"]);
$db = new Gestione_db("elab",$log);

$basi_dati = $db->preleva_da_db("select id as value,nome_base_dati as label,intestazione from base_dati order by nome_base_dati",[],false);
if($basi_dati === false) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile prelevare le basi dati'
    ]);
}else {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Basi dati prelevate',
        'basi_dati'=>$basi_dati
    ]);
}