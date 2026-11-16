<?php
namespace indi\Classes;
require 'vendor/autoload.php';
require_once 'query_sql_elaborazione.php';

use PhpOffice\PhpSpreadsheet\Shared\OLE;
use PhpOffice\PhpSpreadsheet\Shared\OLE\PPS\File;
use PhpOffice\PhpSpreadsheet\Shared\OLE\PPS\Root;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

const MAX_RIGHE_XLS = 65536;   // limite del formato xls (BIFF8) per foglio, intestazione compresa
const MAX_COLONNE_XLS = 256;   // limite del formato xls per foglio
const MAX_CARATTERI_CELLA = 4000; // un record xls non supera 8224 byte: le celle più lunghe vengono troncate

// parti fisse del file (record BIFF8 di un foglio vuoto prodotto da PhpSpreadsheet, in base64): intestazione del libro con font e stili
// (XF 15 = normale, XF 16 = grassetto per l'intestazione), fine dell'intestazione del libro, intestazione del foglio e coda del foglio
const XLS_LIBRO_INIZIO = 'CQgQAAAGBQC7DcwH0QABAAYEAABCAAIAsAQ9ABIAAAAAALwlchU4AAAAAAABAFgCIgACAAAAMQAeANwAAAAIAJABAAAAAAAABwFDAGEAbABpAGIAcgBpADEAHgDcAAAACAC8AgAAAAAAAAcBQwBhAGwAaQBiAHIAaQDgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAPX/IAAAwAAAAAAAAAAACQTgABQAAAAAAAEAIAAAwAAAAAAAAAAACQTgABQAAQAAAAEAIAAAyAAAAAAAAAAACQSTAgQAAIAA/5IA4gA4AAAAAAD///8A/wAAAAD/AAAAAP8A//8AAP8A/wAA//8AgAAAAACAAAAAAIAAgIAAAIAAgAAAgIAAwMDAAICAgACZmf8AmTNmAP//zADM//8AZgBmAP+AgAAAZswAzMz/AAAAgAD/AP8A//8AAAD//wCAAIAAgAAAAACAgAAAAP8AAMz/AMz//wDM/8wA//+ZAJnM/wD/mcwAzJn/AP/MmQAzZv8AM8zMAJnMAAD/zAAA/5kAAP9mAABmZpkAlpaWAAAzZgAzmWYAADMAADMzAACZMwAAmTNmADMzmQAzMzMA';
const XLS_LIBRO_FINE = 'wQEIAMEBAABn5gEArgEEAAEAAQQXAAgAAQAAAAAAAAA=';
const XLS_FOGLIO_INIZIO = 'CQgQAAAGEAC7DcwH0QABAAYEAAAqAAIAAAArAAIAAACCAAIAAQCAAAgAAAAAAAAAAACBAAIAwQQUAAMAAAABFQADAAAAAYMAAgAAAIQAAgAAACYACABmZmZmZmbmPycACABmZmZmZmbmPygACAAAAAAAAADoPykACAAAAAAAAADoP6EAIgABAGQAAQABAAEAAgBYAlgCMzMzMzMz0z8zMzMzMzPTPwEAVQACAAgA';
const XLS_FOGLIO_FINE = 'PgISALYGAAAAAEAAAAAAAGQAAAAAAIsIEACLCAAAAAAAAAAAAABkAAAAHQAPAAMAAAAAAAABAAAAAAAAAWcIFwBnCAAAAAAAAAAAAAACAAH/////A0QAAAoAAAA=';

/** record BIFF8: tipo, lunghezza, corpo */
function record_xls(int $tipo, string $corpo): string
{
    return pack('vv', $tipo, strlen($corpo)) . $corpo;
}

/** record LABEL (testo scritto direttamente nella cella). se tutti i caratteri sono Latin-1 il testo è salvato su un byte per carattere */
function cella_testo_xls(int $riga, int $colonna, int $xf, string $testo): string
{
    $testo = mb_substr(mb_scrub($testo, 'UTF-8'), 0, MAX_CARATTERI_CELLA, 'UTF-8');
    if (!preg_match('/[^\x00-\xFF]/u', $testo)) {
        $flag = "\x00";
        $caratteri = mb_convert_encoding($testo, 'ISO-8859-1', 'UTF-8');
    } else {
        $flag = "\x01";
        $caratteri = mb_convert_encoding($testo, 'UTF-16LE', 'UTF-8');
    }
    $n = $flag === "\x00" ? strlen($caratteri) : intdiv(strlen($caratteri), 2);
    return record_xls(0x0204, pack('vvvv', $riga, $colonna, $xf, $n) . $flag . $caratteri);
}

/** esporta in xls (Excel 97-2003, BIFF8) i record estratti dalla query mostrata dal pulsante SQL (stessa query di esporta_query_csv.php).
 * il file è costruito a mano, senza tenere in memoria tutte le celle come farebbe PhpSpreadsheet (con decine di migliaia di righe per
 * decine di colonne esaurirebbe la memoria): i record BIFF8 delle celle sono accodati a una stringa e il libro è poi inserito in un
 * contenitore OLE con le classi di PhpSpreadsheet. il formato xls ammette al massimo 65.536 righe e 256 colonne: oltre si usa l'esportazione
 * xlsx o csv. tutti i valori sono scritti come testo così CAP, barcode e codici con zeri iniziali restano identici al database.
 */
$file_xls = null;
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

    $nomi_colonne = [];
    for ($i = 0; $i < $stm->columnCount(); $i++)
        $nomi_colonne[] = $stm->getColumnMeta($i)["name"];
    $n_colonne = count($nomi_colonne);
    if ($n_colonne > MAX_COLONNE_XLS)
        throw new \Exception("Le colonne superano il limite del formato xls (" . MAX_COLONNE_XLS . "): usare l'esportazione XLSX o CSV");

    $celle = "";
    foreach ($nomi_colonne as $c => $nome)
        $celle .= cella_testo_xls(0, $c, 16, (string)$nome); // XF 16 = grassetto

    $n_righe = 1;
    while ($riga = $stm->fetch(\PDO::FETCH_NUM)) {
        if ($n_righe >= MAX_RIGHE_XLS)
            throw new \Exception("I record superano il limite di righe del formato xls (" . (MAX_RIGHE_XLS - 1) . "): usare l'esportazione XLSX o CSV");
        foreach ($riga as $c => $valore) {
            if ($valore === null or $valore === '')
                continue; // cella vuota: non occorre scriverla
            $celle .= cella_testo_xls($n_righe, $c, 15, (string)$valore); // XF 15 = normale
        }
        $n_righe++;
    }

    // foglio: intestazione, dimensioni, celle, coda. libro: intestazione, elenco fogli con l'offset del foglio, fine
    $foglio = base64_decode(XLS_FOGLIO_INIZIO)
        . record_xls(0x0200, pack('VVvvv', 0, $n_righe, 0, $n_colonne, 0))
        . $celle
        . base64_decode(XLS_FOGLIO_FINE);
    $libro_inizio = base64_decode(XLS_LIBRO_INIZIO);
    $libro_fine = base64_decode(XLS_LIBRO_FINE);
    $lunghezza_boundsheet = 4 + 8 + 2 * strlen("Dati"); // record + offset, flag, nome compresso (cch + flag + utf-16)
    $offset_foglio = strlen($libro_inizio) + $lunghezza_boundsheet + strlen($libro_fine) + 4; // + record EOF del libro
    $boundsheet = record_xls(0x0085, pack('Vvcc', $offset_foglio, 0, strlen("Dati"), 1) . mb_convert_encoding("Dati", 'UTF-16LE', 'UTF-8'));
    $libro = $libro_inizio . $boundsheet . $libro_fine . record_xls(0x000A, "") . $foglio;
    unset($celle, $foglio);
    // sotto i 4096 byte l'OLE di PhpSpreadsheet userebbe i "mini blocchi", che con alcune lunghezze perdono la coda del file: si arrotonda a 4096 byte con zeri dopo l'ultimo record (ignorati da Excel)
    if (strlen($libro) < 4096)
        $libro = str_pad($libro, 4096, chr(0));

    $workbook = new File(OLE::ascToUcs('Workbook'));
    $workbook->append($libro);
    unset($libro);
    $adesso = time();
    $root = new Root($adesso, $adesso, [$workbook]);

    $file_xls = tempnam(sys_get_temp_dir(), "xls_");
    $handle = fopen($file_xls, "wb");
    $root->save($handle);
    fclose($handle);

    $nome_file = preg_replace('/[^A-Za-z0-9_.-]+/', '_', "{$el['nome_lavoro']}_{$el['nome_elaborazione']}") . ".xls";

    http_response_code(200);
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $nome_file . '"');
    header('Content-Length: ' . filesize($file_xls));
    while (ob_get_level() > 0)
        ob_end_clean();
    readfile($file_xls);
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
    if ($file_xls and is_file($file_xls))
        @unlink($file_xls);
}
