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

const MAX_RIGHE_EXCEL = 1048576; // limite di Excel per foglio, intestazione compresa

/** nome della colonna excel (0 => A, 25 => Z, 26 => AA ...) */
function nome_colonna_excel(int $indice): string
{
    $nome = "";
    for ($n = $indice + 1; $n > 0; $n = intdiv($n - 1, 26))
        $nome = chr(65 + ($n - 1) % 26) . $nome;
    return $nome;
}

/** testo sicuro per xml: toglie i caratteri di controllo non ammessi e fa l'escape */
function testo_xml($valore): string
{
    $valore = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$valore);
    return htmlspecialchars($valore, ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');
}

/** esporta in xlsx i record estratti dalla query mostrata dal pulsante SQL (stessa query di esporta_query_csv.php).
 * il file è costruito a mano (xml scritto riga per riga su file temporaneo e poi compresso con ZipArchive) perché PhpSpreadsheet
 * terrebbe in memoria tutte le celle: con centinaia di migliaia di righe esaurirebbe la memoria. tutti i valori sono scritti come testo
 * così CAP, barcode e codici con zeri iniziali restano identici al database.
 */
$file_temporanei = [];
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

    $stm = esegui_query_streaming($vg, $risultato["query"]);

    $file_foglio = tempnam(sys_get_temp_dir(), "xlsx_");
    $file_xlsx = tempnam(sys_get_temp_dir(), "xlsx_");
    $file_temporanei = [$file_foglio, $file_xlsx];

    $foglio = fopen($file_foglio, "w");
    $nomi_colonne = [];
    for ($i = 0; $i < $stm->columnCount(); $i++)
        $nomi_colonne[] = $stm->getColumnMeta($i)["name"];
    $lettere = array_map('indi\Classes\nome_colonna_excel', array_keys($nomi_colonne));

    $scrivi_riga = function (int $n_riga, array $valori, bool $intestazione = false) use ($foglio, $lettere) {
        $xml = "<row r=\"$n_riga\">";
        foreach (array_values($valori) as $i => $valore) {
            $valore = (string)($valore ?? '');
            if ($valore === '' and !$intestazione)
                continue; // cella vuota: non occorre scriverla
            $xml .= "<c r=\"{$lettere[$i]}$n_riga\" t=\"inlineStr\"" . ($intestazione ? " s=\"1\"" : "") . "><is><t xml:space=\"preserve\">" . testo_xml($valore) . "</t></is></c>";
        }
        fwrite($foglio, $xml . "</row>");
    };

    $ultima_colonna = nome_colonna_excel(max(count($nomi_colonne), 1) - 1);
    fwrite($foglio, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/><sheetData>');
    $scrivi_riga(1, $nomi_colonne, true);

    $n_riga = 1;
    while ($riga = $stm->fetch(\PDO::FETCH_NUM)) {
        if (++$n_riga > MAX_RIGHE_EXCEL)
            throw new \Exception("I record superano il limite di righe di un foglio Excel (" . (MAX_RIGHE_EXCEL - 1) . "): usare l'esportazione CSV");
        $scrivi_riga($n_riga, $riga);
    }
    fwrite($foglio, "</sheetData><autoFilter ref=\"A1:{$ultima_colonna}1\"/></worksheet>");
    fclose($foglio);

    $zip = new \ZipArchive();
    if ($zip->open($file_xlsx, \ZipArchive::OVERWRITE) !== true)
        throw new \Exception("Impossibile creare il file xlsx");
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Dati" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>');
    // stile 0 = normale, stile 1 = intestazione in grassetto
    $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>');
    $zip->addFile($file_foglio, 'xl/worksheets/sheet1.xml');
    if (!$zip->close())
        throw new \Exception("Impossibile completare il file xlsx");

    $nome_file = preg_replace('/[^A-Za-z0-9_.-]+/', '_', "{$el['nome_lavoro']}_{$el['nome_elaborazione']}") . ".xlsx";

    http_response_code(200);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nome_file . '"');
    header('Content-Length: ' . filesize($file_xlsx));
    while (ob_get_level() > 0)
        ob_end_clean();
    readfile($file_xlsx);
} catch (\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code(400);
    }
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    foreach ($file_temporanei as $f)
        if (is_file($f))
            @unlink($f);
}
