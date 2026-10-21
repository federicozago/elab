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

/** restituisce la query (mostrata dal pulsante SQL) che estrae i record di un'elaborazione con TUTTE le colonne elencate
 * una per una ("e.`campo` AS `campo`"), così l'operatore può sostituire il valore di ogni campo a mano (es. per creare
 * record civetta). le colonne di anagrafica (e) e ordinati (o) con lo stesso nome vengono prese solo dall'anagrafica.
 */
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Manca id_elaborazione');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    if (!$el = $db->preleva_da_db("select nome_lavoro, nome_elaborazione, tipo_spedizione, nome_base_dati, id_flusso from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Elaborazione non trovata");
    $el = $el[0];

    $tabella_anagrafica = "{$el['nome_lavoro']}_{$el['nome_elaborazione']}";
    $tabella_ordinati = "ordinati_{$el['tipo_spedizione']}_{$el['nome_base_dati']}";

    $query_colonne = "select column_name as nome from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position";
    $colonne_e = array_column($db->preleva_da_db($query_colonne, [$tabella_anagrafica], false) ?: [], "nome");
    $colonne_o = array_column($db->preleva_da_db($query_colonne, [$tabella_ordinati], false) ?: [], "nome");

    $q = fn($nome) => "`" . str_replace("`", "``", $nome) . "`";

    $campi = [];
    $presenti = [];
    foreach ($colonne_e as $c) {
        $campi[] = "    e.{$q($c)} AS {$q($c)}";
        $presenti[strtolower($c)] = true;
    }
    foreach ($colonne_o as $c) {
        if (isset($presenti[strtolower($c)]))
            continue; // stesso nome nell'anagrafica: vale la colonna dell'anagrafica
        $campi[] = "    o.{$q($c)} AS {$q($c)}";
    }
    // se non riesco a leggere le colonne (es. vista non valida) ricado su select *
    $elenco_campi = $campi ? implode(",\n", $campi) : "    *";

    $query = "SELECT\n$elenco_campi\n"
        . "FROM `elab`.{$q($tabella_anagrafica)} e\n"
        . "    JOIN `elab`.{$q($tabella_ordinati)} o\n"
        . "         ON e.id=o.c1\n"
        . "WHERE e.id_flusso = '" . addslashes($el['id_flusso']) . "'\n"
        . "ORDER BY o.progr";

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'query' => $query,
        'completa' => (bool)$campi,
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
