<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try {
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab",$log);

    if(!$viste = $db->preleva_da_db_un_singolo_valore_array("SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = '{$vg['database_cliente']}'
AND TABLE_TYPE = 'VIEW'"))
        throw new \Exception('Impossibile prelevare le viste');
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Viste prelevate',
        'viste'=>$viste
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
