<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

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

    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazione");
    $elaborazione = $elaborazione[0];

    $nome_elaborazione = "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}";
    $tabella_ordinati = "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}";

    $query = "
        WITH scatole AS (
            SELECT
                idbancale,
                idplico,
                CASE
                    WHEN SUM(CASE WHEN bacino = 'ZCARTI' THEN 1 ELSE 0 END) > 0 THEN 'MIX'
                    WHEN COUNT(DISTINCT bacino) = 1
                        THEN MAX(bacino)
                    ELSE 'MIX'
                END AS bacino_bancale
            FROM `$tabella_ordinati`
            WHERE idbancale IS NOT NULL
            GROUP BY
                idbancale,
                idplico
        )
        SELECT
            s.idbancale,
            s.bacino_bancale,
            COUNT(DISTINCT s.idplico) AS nplichi,
            COUNT(*) AS npezzi,
            SUM(t.peso_plico) AS peso_gr
        FROM scatole s
        JOIN `$tabella_ordinati` t
            ON t.idbancale = s.idbancale
            AND t.idplico = s.idplico
        GROUP BY
            s.idbancale,
            s.bacino_bancale
        ORDER BY
            s.idbancale,
            s.bacino_bancale
    ";

    $righe = $db->preleva_da_db($query, [], false);
    if ($righe === false)
        throw new \Exception("Errore durante l'esecuzione della query");
    if (count($righe) === 0)
        throw new \Exception("Nessun bancale trovato per questa elaborazione");

    // --- calcolo totali e costruzione dell'array da scrivere in un'unica chiamata ---
    $totale_nplichi = 0;
    $totale_npezzi = 0;
    $totale_peso_gr = 0;
    $ultimo_idbancale = 0;

    $dati_excel = [];
    foreach ($righe as $riga) {
        $totale_nplichi += (int)$riga['nplichi'];
        $totale_npezzi  += (int)$riga['npezzi'];
        $totale_peso_gr += (int)$riga['peso_gr'];
        $ultimo_idbancale = (int)$riga['idbancale']; // query ordinata per idbancale crescente

        $dati_excel[] = [
            'ID Bancale' => (int)$riga['idbancale'],
            'Bacino'     => $riga['bacino_bancale'],
            'N. Plichi'  => (int)$riga['nplichi'],
            'N. Pezzi'   => (int)$riga['npezzi'],
            'Peso (gr)'  => (int)$riga['peso_gr'],
        ];
    }

    // riga totali: stessa struttura di colonne dell'array, appesa come ultimo elemento
    $dati_excel[] = [
        'ID Bancale' => $ultimo_idbancale,
        'Bacino'     => 'TOTALE',
        'N. Plichi'  => $totale_nplichi,
        'N. Pezzi'   => $totale_npezzi,
        'Peso (gr)'  => $totale_peso_gr,
    ];

    // --- generazione file Excel usando ESCLUSIVAMENTE la classe Excel di cassetta_attrezzi ---
    $temp_dir = __DIR__ . "/temp_excel/";
    if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

    $nome_base = "report_bancali_{$nome_elaborazione}_{$elaborazione['id_flusso']}";
    $nome_univoco = $nome_base . "_" . uniqid() . ".xlsx";
    $path_temporanea = $temp_dir . $nome_univoco;

    $spreadsheet = new Excel($log);
    if (!$spreadsheet->apri($path_temporanea)) // true = la prima riga scritta è intestazione
        throw new \Exception("Errore durante la creazione del file Excel");

    // scrivi_array scrive intestazione (dalle chiavi) + righe dati + riga totali, con bordo nero automatico su ogni cella
    if (!$spreadsheet->scrivi_array($dati_excel))
        throw new \Exception("Errore durante la scrittura dei dati nel file Excel");

    if (!$spreadsheet->chiudi())
        throw new \Exception("Errore durante il salvataggio/chiusura del file Excel");

    // --- invio del file al client ---
    if (file_exists($path_temporanea)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome_base . '.xlsx"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path_temporanea));

        if (ob_get_length()) ob_clean();
        flush();

        readfile($path_temporanea);
        unlink($path_temporanea);
        exit;
    } else {
        throw new \Exception("File Excel non generato");
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
