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
    if(!$configurazione = $db->preleva_da_db("select * from {$elaborazione['tipo_spedizione']} where id = ?",[$elaborazione['id_configurazione']]))
        throw new \Exception("Errore durante prelievo configurazione");
    $configurazione = $configurazione[0];

    $elab = "indi\\Classes\\Elaborazione_postale_{$elaborazione['tipo_spedizione']}";
    if (!class_exists($elab))
        throw new \Exception("Tipo di elaborazione non supportato");
    $elab = new $elab("elab", $elaborazione["id_flusso"], $vg["database_cliente"], $log);
    $nome_elaborazione = "{$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}";
    $elab->setta_parametri(array_merge(
        $elaborazione,
        $configurazione,
        [
            "tabella_ordinamento" => "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}",
            "tara_scatola"=>$configurazione["tara_scatola"],
            "tara_pallet"=>$configurazione["tara_pallet"],
            "associazione_campi"=>[
                "cap"=>$elaborazione["campo_cap"],
                "prov"=>$elaborazione["campo_provincia"],
                "localita"=>$elaborazione["campo_localita"]
            ],
            "elaborazioni"=>[
                $nome_elaborazione => []
            ]
        ]
    ), true);

    //genero etichette
    $temp_dir = __DIR__ . "/temp_excel/";
    if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

    $nome_base = "report_bancale_{$nome_elaborazione}_{$elaborazione['id_flusso']}";
    $nome_univoco = $nome_base . "_" . uniqid() . ".xlsx";
    $path_temporanea = $temp_dir . $nome_univoco;

    if(!$elab->genera_report_bancali($nome_elaborazione, $temp_dir, $nome_univoco))
        throw new \Exception("Errore durante la generazione delle etichette");

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
