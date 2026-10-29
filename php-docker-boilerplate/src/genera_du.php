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

/** elimina la cartella di lavoro della DU con quello che contiene */
function elimina_cartella_du($cartella)
{
    if (!is_dir($cartella))
        return;
    foreach (scandir($cartella) as $voce) {
        if ($voce === "." or $voce === "..")
            continue;
        is_dir("$cartella/$voce") ? elimina_cartella_du("$cartella/$voce") : unlink("$cartella/$voce");
    }
    rmdir($cartella);
}

/**
 * Prenotazione con DU di una elaborazione ordinata (pulsante "Prenota"): salva la data di spedizione, assegna il codice padre (prelevato dalla tabella indicata
 * nella configurazione, se non l'ha già), genera la DU (Distinta Elettronica di Prenotazione Unica, un record per busta) e la restituisce in uno zip da scaricare.
 * Alla fine l'elaborazione passa in stato 4 (prenotato), da cui si può chiudere. Si può rilanciare su un'elaborazione già prenotata per riscaricare la DU
 * (stesso codice padre, la data può essere cambiata). Se qualcosa fallisce il db torna com'era (codice padre non consumato).
 */
$cartella_lavoro = null;
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_elaborazione"]) or !isset($jsonData["data_prenotazione"]))
        throw new \Exception('Mancano dati in input');

    $data_spedizione = \DateTime::createFromFormat('Y/m/d', $jsonData["data_prenotazione"]);
    if (!$data_spedizione)
        throw new \Exception('Data di prenotazione non valida');
    $data_spedizione = $data_spedizione->format('Ymd');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    //prelevo dati e configurazioni elaborazione richiesta
    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];
    if (!$configurazione = $db->preleva_da_db("select * from {$elaborazione['tipo_spedizione']} where id = ?", [$elaborazione['id_configurazione']]))
        throw new \Exception("Errore durante prelievo configurazione");
    $configurazione = $configurazione[0];

    if (empty($configurazione["prenotazione_con_du"]))
        throw new \Exception("La configurazione di questa elaborazione non prevede la prenotazione con DU");
    if (!in_array((int)$elaborazione["stato"], [2, 4]))
        throw new \Exception("L'elaborazione deve essere ordinata per poter essere prenotata");

    //la DU contiene ragione sociale e indirizzo di ogni destinatario: la base dati deve indicare in quali colonne si trovano
    $campi_base_dati = $db->preleva_da_db("select campo_ragsoc, campo_indirizzo, campo_civico from base_dati where id = ?", [$elaborazione["id_base_dati"]]);
    if (!$campi_base_dati)
        throw new \Exception("Impossibile leggere i campi ragione sociale e indirizzo della base dati (verificare che la tabella base_dati abbia le colonne campo_ragsoc, campo_indirizzo, campo_civico)");
    $campi_base_dati = $campi_base_dati[0];
    if (empty($campi_base_dati["campo_ragsoc"]) or empty($campi_base_dati["campo_indirizzo"]))
        throw new \Exception("La base dati {$elaborazione['nome_base_dati']} non ha i campi ragione sociale e indirizzo, necessari per generare la DU: vanno indicati nella creazione della base dati");

    $associazione_campi = [
        "cap" => $elaborazione["campo_cap"],
        "prov" => $elaborazione["campo_provincia"],
        "localita" => $elaborazione["campo_localita"],
        "ragsoc" => $campi_base_dati["campo_ragsoc"],
        "indirizzo" => $campi_base_dati["campo_indirizzo"],
    ];
    if (!empty($campi_base_dati["campo_civico"]))
        $associazione_campi["numero_civico"] = $campi_base_dati["campo_civico"];

    //campi spare della DU: nella configurazione sono scritti separati da virgola, la libreria vuole un array
    $configurazione["campi_spare"] = array_values(array_filter(array_map('trim', explode(",", (string)($configurazione["campi_spare"] ?? "")))));

    //cartella di lavoro: la libreria scrive la DU in folder_temp/distinte_elettroniche, lo zip finale viene copiato in $cartella_zip
    $cartella_lavoro = sys_get_temp_dir() . "/du_" . uniqid();
    $cartella_zip = "$cartella_lavoro/zip/";
    if (!mkdir($cartella_zip, 0777, true))
        throw new \Exception("Impossibile creare la cartella temporanea");

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
            "database_ordinamenti" => $vg["database_cliente"],
            "associazione_campi" => $associazione_campi,
            "folder_temp" => "$cartella_lavoro/lavoro",
            "elaborazioni" => [
                $nome_elaborazione => []
            ]
        ]
    ), true);

    //codice invio (datamatrix) e codice padre li ha già assegnati l'ordinamento: se mancano l'elaborazione è stata ordinata prima del datamatrix e va riordinata
    $controllo = $db->preleva_da_db(
        "select count(*) as totale, sum(barcode is null or barcode = '') as senza_barcode, sum(codice_padre is null or codice_padre = '') as senza_padre, count(distinct codice_padre) as padri
         from `{$vg['database_cliente']}`.`ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}` where id_flusso = ? and nome_elaborazione = ?",
        [$elaborazione["id_flusso"], $nome_elaborazione]
    );
    if (!$controllo)
        throw new \Exception("Impossibile verificare i dati ordinati: l'elaborazione va riordinata con la stampa del datamatrix attiva");
    $controllo = $controllo[0];
    if (!$controllo["totale"])
        throw new \Exception("Non ci sono record ordinati per questa elaborazione");
    if ($controllo["senza_barcode"] or $controllo["senza_padre"] or $controllo["padri"] != 1)
        throw new \Exception("I record non hanno il datamatrix e il codice padre: l'elaborazione va riordinata con la stampa del datamatrix attiva");

    //salvo la data di spedizione in ordinati (rifiuta le date passate) e genero la DU nello zip
    if (!$elab->salva_data_spedizione($data_spedizione, $nome_elaborazione))
        throw new \Exception("Impossibile salvare la data di spedizione (la data deve essere oggi o futura)");
    if (!$elab->genera_du($nome_elaborazione, $cartella_zip))
        throw new \Exception("Errore durante la generazione della DU");

    $file_zip = glob($cartella_zip . "*.zip");
    if (count($file_zip) !== 1)
        throw new \Exception("DU non generata");
    $file_zip = $file_zip[0];

    if (!$db->esegui_query("update elaborazioni set stato=4 where id=?", [$elaborazione["id_elaborazione"]]))//prenotato
        throw new \Exception("Errore durante l'aggiornamento della tabella elaborazioni");

    header('Content-Description: File Transfer');
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . basename($file_zip) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($file_zip));

    // Pulizia buffer per evitare corruzione binaria
    if (ob_get_length()) ob_clean();
    flush();

    readfile($file_zip);
    elimina_cartella_du($cartella_lavoro);
    exit;
} catch (\Throwable $e) {
    if ($cartella_lavoro)
        elimina_cartella_du($cartella_lavoro);
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
