<?php
namespace indi\Classes;
require 'vendor/autoload.php';
require_once 'query_sql_elaborazione.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/** esporta in csv (separatore ";", utf-8 con BOM per Excel) i record estratti dalla query mostrata dal pulsante SQL.
 * la query viene ricostruita lato server (query_sql_elaborazione.php) e non ricevuta dal client. i record vengono letti
 * e scritti uno alla volta (query non bufferizzata) perché le elaborazioni possono avere centinaia di migliaia di righe.
 */
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Manca id_elaborazione');

    set_time_limit(0);

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $risultato = costruisci_query_sql_elaborazione($db, $jsonData["id_elaborazione"]);
    $el = $risultato["elaborazione"];

    // connessione dedicata alla lettura in streaming (Gestione_db carica tutto in memoria e limita le query a 2 minuti)
    $porta = $vg["porta_db"] ?? "";
    if ($porta != "" and strpos($porta, "port=") === false)
        $porta = "port=" . $porta;
    $conn = new \PDO("mysql:host={$vg['ip_db']};dbname={$vg['database_cliente']};charset=utf8;$porta;", $vg["user_db"], $vg["pass_db"], [
        \PDO::ATTR_PERSISTENT => false,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    $conn->exec("set names 'utf8'");
    $conn->exec("SET SESSION MAX_EXECUTION_TIME=0");

    // la query parte prima degli header: se fallisce la risposta è un normale errore json
    $stm = $conn->query($risultato["query"]);

    $nome_file = preg_replace('/[^A-Za-z0-9_.-]+/', '_', "{$el['nome_lavoro']}_{$el['nome_elaborazione']}") . ".csv";

    http_response_code(200);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nome_file . '"');
    header('X-Accel-Buffering: no');
    while (ob_get_level() > 0)
        ob_end_clean();

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    $intestazione_scritta = false;
    while ($riga = $stm->fetch(\PDO::FETCH_ASSOC)) {
        if (!$intestazione_scritta) {
            fputcsv($out, array_keys($riga), ';', '"', '\\', "\r\n");
            $intestazione_scritta = true;
        }
        fputcsv($out, array_map(fn($v) => $v ?? '', array_values($riga)), ';', '"', '\\', "\r\n");
    }
    if (!$intestazione_scritta) {
        // nessun record: almeno le intestazioni delle colonne
        for ($i = 0; $i < $stm->columnCount(); $i++)
            $nomi[] = $stm->getColumnMeta($i)["name"];
        fputcsv($out, $nomi ?? [], ';', '"', '\\', "\r\n");
    }
    fclose($out);
} catch (\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(400);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
