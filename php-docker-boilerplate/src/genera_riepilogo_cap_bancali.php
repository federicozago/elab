<?php
namespace indi\Classes;
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/** scarica un file xlsx con una riga per ogni terna provincia|cap|località e bancale, in 5 colonne (senza intestazione):
 *      provincia, cap, località, numero di buste, numero del bancale
 * se la stessa terna è presente su più bancali ci sono più righe, una per bancale. le buste sono i record dell'elaborazione.
 * richiede che i bancali siano già assegnati (colonna idbancale della tabella ordinati valorizzata e diversa da 0).
 * provincia, cap e località sono scritti come testo (il CAP conserva gli zeri iniziali), buste e bancale come numeri
 */
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Manca id_elaborazione');

    $vg = (new Variabili_globali_import())->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Elaborazione non trovata");
    $elaborazione = $elaborazione[0];

    foreach (["campo_cap", "campo_provincia", "campo_localita"] as $campo)
        if (empty($elaborazione[$campo]))
            throw new \Exception("Nella base dati non sono indicati i campi CAP, provincia e località");

    $q = fn($nome) => "`" . str_replace("`", "``", $nome) . "`";
    $tabella_anagrafica = "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}";
    $tabella_ordinati = "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}";

    // una riga per terna provincia|cap|località e bancale con il numero di buste
    $query = "SELECT e.{$q($elaborazione['campo_provincia'])} AS provincia,
                     e.{$q($elaborazione['campo_cap'])} AS cap,
                     e.{$q($elaborazione['campo_localita'])} AS localita,
                     o.idbancale AS idbancale,
                     COUNT(*) AS buste
              FROM {$q($tabella_anagrafica)} e
                  JOIN {$q($tabella_ordinati)} o ON e.id = o.c1
              WHERE e.id_flusso = ?
              GROUP BY e.{$q($elaborazione['campo_provincia'])}, e.{$q($elaborazione['campo_cap'])}, e.{$q($elaborazione['campo_localita'])}, o.idbancale
              ORDER BY provincia, cap, localita, o.idbancale";
    $righe = $db->preleva_da_db($query, [$elaborazione['id_flusso']], false);
    if ($righe === false)
        throw new \Exception("Errore durante l'estrazione dei dati");
    if (count($righe) === 0)
        throw new \Exception("Nessun record trovato: l'elaborazione non è stata ordinata");

    $senza_bancale = array_sum(array_map(fn($r) => empty($r['idbancale']) ? $r['buste'] : 0, $righe));
    if ($senza_bancale > 0)
        throw new \Exception("$senza_bancale record non hanno il bancale assegnato: assegnare i bancali prima di generare il file");

    $dati = [];
    foreach ($righe as $r)
        $dati[] = [trim((string)$r['provincia']), trim((string)$r['cap']), trim((string)$r['localita']), (int)$r['buste'], (int)$r['idbancale']];

    $spreadsheet = new Spreadsheet();
    $foglio = $spreadsheet->getActiveSheet();
    $foglio->setTitle('Riepilogo');
    foreach ($dati as $i => $riga) {
        $n = $i + 1;
        for ($c = 0; $c < 3; $c++)
            $foglio->setCellValueExplicit([$c + 1, $n], $riga[$c], DataType::TYPE_STRING);
        $foglio->setCellValueExplicit([4, $n], $riga[3], DataType::TYPE_NUMERIC);
        $foglio->setCellValueExplicit([5, $n], $riga[4], DataType::TYPE_NUMERIC);
    }
    foreach (range('A', 'E') as $colonna)
        $foglio->getColumnDimension($colonna)->setAutoSize(true);

    $nome_file = "ProCapComQta_" . preg_replace('/[^A-Za-z0-9_.-]+/', '_', "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}") . ".xlsx";
    $file_xlsx = tempnam(sys_get_temp_dir(), "rie_");
    try {
        (new Xlsx($spreadsheet))->save($file_xlsx);

        http_response_code(200);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome_file . '"');
        header('Content-Length: ' . filesize($file_xlsx));
        while (ob_get_level() > 0)
            ob_end_clean();
        readfile($file_xlsx);
    } finally {
        @unlink($file_xlsx);
    }
} catch (\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(400);
    }
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
