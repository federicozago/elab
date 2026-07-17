<?php
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

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    //prelevo dati elaborazione richiesta
    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazione");
    $elaborazione = $elaborazione[0];

    if ($elaborazione['tipo_spedizione'] !== 'target')
        throw new \Exception("Il report per criterio è disponibile solo per le elaborazioni target");

    $tabella_ordinamento = "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}";
    $nome_elaborazione = "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}";

    if (!$db->verifica_esistenza_tabella("{$vg['database_cliente']}.{$tabella_ordinamento}"))
        throw new \Exception("L'elaborazione non è ancora stata ordinata, nessun dato da riportare");

    // Parto dai 5 criteri possibili (A-E) e ci faccio un LEFT JOIN sui dati
    // ordinati di QUESTA elaborazione: così un criterio senza record compare
    // comunque nel report con conteggio 0, invece di sparire dalla GROUP BY.
    $query = "
        SELECT c.criterio, COUNT(t.criterio) AS conteggio
        FROM (SELECT 'A' AS criterio UNION ALL SELECT 'B' UNION ALL SELECT 'C' UNION ALL SELECT 'D' UNION ALL SELECT 'E') c
        LEFT JOIN `{$vg['database_cliente']}`.`{$tabella_ordinamento}` t
            ON t.criterio = c.criterio AND t.id_flusso = ? AND t.nome_elaborazione = ?
        GROUP BY c.criterio
        ORDER BY c.criterio
    ";
    if (($righe = $db->preleva_da_db($query, [$elaborazione['id_flusso'], $nome_elaborazione], false)) === false)
        throw new \Exception("Errore durante l'esecuzione della query di report - " . implode(", ", $db->get_errori()));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'report' => $righe
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
