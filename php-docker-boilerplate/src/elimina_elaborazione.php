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
        throw new \Exception('ID elaborazione mancante');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    // Prelevo info elaborazione
    $res = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]);
    if (!$res) throw new \Exception("Elaborazione non trovata");
    $el = $res[0];
    
    $nome_lavoro_completo = "{$el['nome_lavoro']}_{$el['nome_elaborazione']}";
    $id_flusso = $el['id_flusso'];

    $db->blocca_db();

    // 1. Eliminazione dai dati originali
    if(!$db->esegui_query("DELETE FROM `{$el['nome_base_dati']}` WHERE id_elaborazione = ? AND id_flusso = ?", [$jsonData["id_elaborazione"], $id_flusso]))
        throw new \Exception("Errore durante eliminazione dati originali");

    // 2. Eliminazione dai dati ordinati
    if(!$db->esegui_query("DELETE FROM `ordinati_{$el['tipo_spedizione']}_{$el['nome_base_dati']}` WHERE nome_elaborazione = ? AND id_flusso = ?", [$nome_lavoro_completo, $id_flusso]))
        throw new \Exception("Errore durante eliminazione dati ordinati");

    // 3. Eliminazione dati light (se tabella esiste)
    $tabella_light = "ordinati_light_{$el['nome_base_dati']}";
    $check_light = $db->preleva_da_db("SHOW TABLES LIKE ?", [$tabella_light]);
    if ($check_light) {
        $db->esegui_query("DELETE FROM `{$tabella_light}` WHERE nome_elaborazione = ? AND id_flusso = ?", [$nome_lavoro_completo, $id_flusso]);
    }

    //elimino elaborazione
    if(!$db->esegui_query("DELETE FROM `elaborazioni` WHERE id = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante eliminazione elaborazione");

    $db->sblocca_db();

    echo json_encode(['success' => true, 'message' => 'Dati eliminati correttamente']);

} catch (\Exception $e) {
    if(isset($db)) $db->sblocca_db(true);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>