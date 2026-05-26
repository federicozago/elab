<?php
// Configura gli header CORS se necessario
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

// Verifica se la cartella esiste, altrimenti creala

try {
    // Verifica se è stato inviato un file
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    $dati = $db->preleva_da_db("select * from elab_join where stato = 255",[],false);
    if($dati === false)//se stato è maggiore di zero allora è già ordinato
        throw new \Exception("Errore durante prelievo elaborazioni");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'elaborazioni'=>$dati
    ]);
}catch (\Exception $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
