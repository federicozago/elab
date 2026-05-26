<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS se necessario
use indi\Classes\Gestione_db;
use indi\Classes\Segnalazioni_e_log;
use indi\Classes\Variabili_globali_import;
use indi\Classes\Read_idx;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    // Verifica se è stato inviato un file
    if (!isset($jsonData["filePath"]) or !isset($jsonData["idElaborazione"]) or !isset($jsonData["folderZ"]))
        throw new Exception('Manca il nome della base dati');

    if (!file_exists($jsonData["filePath"]))
        throw new Exception('File non trovato');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);
    $read_idx = new Read_idx($log, "elab", dirname($jsonData["filePath"]));

    if (!$dati = $read_idx->importa_dati_in_array())
        throw new Exception("Errore durante l'importazione dei dati");

    //aggiungere l'id inserito
    $a = new \indi\Classes\Array_php();
    if (!$dati = $a->array_add_column($dati, "nome_file_idx_input", basename($jsonData["filePath"])))
        throw new Exception("Errore durante l'aggiunta della nome_file_idx_input nella tabella dati");
    if (!$dati = $a->array_add_column($dati, "id_flusso", $jsonData["idElaborazione"]))
        throw new Exception("Errore durante l'aggiunta della id elaborazione nella tabella dati");
    if (!$dati = $a->array_add_column($dati, "folder_z", $jsonData["folderZ"]))
        throw new Exception("Errore durante l'aggiunta della folder_z nella tabella dati");

    $db->carica_a_db($dati, "dati");

    unlink($jsonData["filePath"]);
    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File caricato con successo',
    ]);
}catch (Exception $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>




