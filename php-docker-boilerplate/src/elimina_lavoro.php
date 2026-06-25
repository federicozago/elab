<?php
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_lavoro"])) {
        throw new \Exception("ID lavoro mancante");
    }

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $id_lavoro = $jsonData["id_lavoro"];

    // 1. Verifica se ci sono elaborazioni aperte (stato != 255)
    $checkQuery = "
        SELECT COUNT(*) 
        FROM elaborazioni e
        JOIN elaborazioni_lavoro el ON e.id_elaborazione_lavoro = el.id
        WHERE el.id_lavoro = ? AND e.stato != 255
    ";
    
    $elaborazioniAperte = $db->preleva_da_db_un_singolo_valore($checkQuery, [$id_lavoro]);

    if ($elaborazioniAperte > 0) {
        throw new \Exception("Impossibile eliminare il lavoro: ci sono {$elaborazioniAperte} elaborazioni non ancora concluse.");
    }

    // Recupero dati per eliminazione viste (nome lavoro e nomi elaborazioni)
    $lavoroDati = $db->preleva_da_db("
        SELECT l.nome_lavoro, el.nome_elaborazione 
        FROM lavori l
        JOIN elaborazioni_lavoro el ON l.id = el.id_lavoro
        WHERE l.id = ?
    ", [$id_lavoro]);

    $db->blocca_db();

    // 3. Eliminazione record tabella 'elaborazioni'
    $deleteElabQuery = "
        DELETE e FROM elaborazioni e
        JOIN elaborazioni_lavoro el ON e.id_elaborazione_lavoro = el.id
        WHERE el.id_lavoro = ?
    ";
    $db->esegui_query($deleteElabQuery, [$id_lavoro]);

    // 4. Eliminazione record tabella 'elaborazioni_lavoro'
    $db->esegui_query("DELETE FROM elaborazioni_lavoro WHERE id_lavoro = ?", [$id_lavoro]);

    // 5. Eliminazione record tabella 'lavori'
    $db->esegui_query("DELETE FROM lavori WHERE id = ?", [$id_lavoro]);

    $db->sblocca_db();

    // 2. Eliminazione Viste MySQL
    if ($lavoroDati) {
        foreach ($lavoroDati as $row) {
            $vistaBase = $row['nome_lavoro'] . "_" . $row['nome_elaborazione'];
            $vistaElaborato = $vistaBase . "_elaborato";

            $db->esegui_query("DROP VIEW IF EXISTS `$vistaBase`", []);
            $db->esegui_query("DROP VIEW IF EXISTS `$vistaElaborato`", []);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lavoro e relative viste eliminati con successo'
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
