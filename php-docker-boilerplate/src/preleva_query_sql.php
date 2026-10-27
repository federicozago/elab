<?php
namespace indi\Classes;
require 'vendor/autoload.php';
require_once 'query_sql_elaborazione.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/** restituisce la query (mostrata dal pulsante SQL) che estrae i record di un'elaborazione con TUTTE le colonne elencate
 * una per una ("e.`campo` AS `campo`"), così l'operatore può sostituire il valore di ogni campo a mano (es. per creare
 * record civetta). la costruzione della query è in query_sql_elaborazione.php.
 */
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Manca id_elaborazione');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $risultato = costruisci_query_sql_elaborazione($db, $jsonData["id_elaborazione"]);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'query' => $risultato["query"],
        'completa' => $risultato["completa"],
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
