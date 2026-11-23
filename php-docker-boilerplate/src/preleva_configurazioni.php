<?php
namespace indi\Classes;
require 'vendor/autoload.php';
require_once __DIR__ . '/alias_target.php';

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
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    if(!isset($jsonData["tipo_spedizione"])) throw new \Exception("Manca il tipo di spedizione");
    $tipo_spedizione = $jsonData["tipo_spedizione"];
    //gli alias di target (NO_PROFIT, ...) hanno la stessa tabella, ma solo le configurazioni con quel sotto prodotto (e target le esclude)
    $filtro = "";
    $alias = alias_target();
    if(isset($alias[$tipo_spedizione])) {
        $filtro = "where prodotto_target = '$tipo_spedizione'";//la chiave arriva dalla lista degli alias
        $tipo_spedizione = "target";
    }elseif($tipo_spedizione === "target")
        $filtro = "where coalesce(prodotto_target, '') not in ('" . implode("','", array_keys($alias)) . "')";
    if( ! in_array($tipo_spedizione, array_keys($vg["elaborazioni"])))
        throw new \Exception("Tipo di spedizione non valido");

    $configurazioni = $db->preleva_da_db("select id as value,nome_configurazione as label from $tipo_spedizione $filtro",[],false);
    if($configurazioni === false)
        throw new \Exception("Errore durante prelievo configurazioni");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazioni prelevate',
        'configurazioni'=>$configurazioni
    ]);
}catch(\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile prelevare le configurazioni'
    ]);
}