<?php
// Configura gli header CORS se necessario
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala

try {
    // Verifica se è stato inviato un file
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    $dati = $db->preleva_da_db("select id as value,nome_lavoro as label from lavori order by nome_lavoro",[],false);//se stato è maggiore di zero allora è già ordinato
    if($dati === false)
        throw new \Exception("Errore durante prelievo lavori");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lavori'=>$dati
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
