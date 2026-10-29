<?php

namespace indi\Classes;
require 'vendor/autoload.php';

header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/** elenca le tabelle dei codici padri (nome che inizia per padri_) presenti negli schemi import_file ed elab, nella forma schema.tabella:
 * è il valore da salvare in tabella_codici_padri della configurazione (il programma di ordinamento lavora sullo schema elab, quindi lo schema serve sempre)
 */
try {
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $tabelle = $db->preleva_da_db(
        "select concat(table_schema, '.', table_name) as tabella from information_schema.tables where table_schema in ('import_file', 'elab') and table_name like 'padri\\_%' order by table_schema, table_name",
        [],
        false
    ) ?: [];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'tabelle' => array_column($tabelle, "tabella"),
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
