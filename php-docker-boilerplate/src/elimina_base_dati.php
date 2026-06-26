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
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_base_dati"])) {
        throw new \Exception('Parametro mancante: id_base_dati è richiesto.');
    }

    $id_base_dati = $jsonData["id_base_dati"];

    // 1. Verifica che non vi siano nella tabella lavori record che hanno il campo id_base_dati uguale all'id passato
    $presenza_lavoro = $db->verifica_presenza_record(
        "SELECT id FROM lavori WHERE id_base_dati = ?",
        [$id_base_dati]
    );

    if ($presenza_lavoro) {
        throw new \Exception('Impossibile eliminare: la base dati è utilizzata in uno o più lavori.');
    }

    // 2. Recupero nome tabella per eventuale pulizia (opzionale ma consigliato per coerenza con il sistema)
    $dati_base = $db->preleva_da_db("SELECT nome_base_dati FROM base_dati WHERE id = ?", [$id_base_dati]);
    if ($dati_base) {
        $nome_tabella = $dati_base[0]['nome_base_dati'];
        // Se si volesse eliminare anche la tabella fisica:
        if(!$db->esegui_query("DROP TABLE IF EXISTS `$nome_tabella` ")) throw new \Exception("Impossibile eliminare la tabella fisica: $nome_tabella");
    }

    // 3. Eliminazione dalla tabella mysql base_dati il record con id uguale a quello passato
    $db->esegui_query(
        "DELETE FROM base_dati WHERE id = ?",
        [$id_base_dati]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Base dati eliminata con successo'
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}