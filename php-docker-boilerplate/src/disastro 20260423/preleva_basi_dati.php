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

if(!$base_dati = $db->preleva_da_db("select id,nome from base_dati group by base_dati order by nome"))
    echo json_encode(["esito"=>"ko"]);
else
    echo json_encode(["listaBaseDati"=>$base_dati]);
