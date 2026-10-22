<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS se necessario
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

function isSqlSafe($where) {
    if (empty($where)) return true;

    // Lista di parole chiave o caratteri pericolosi
    $forbidden = [';', '--', 'DROP', 'DELETE', 'TRUNCATE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'WHERE'];

    $upperWhere = strtoupper($where);
    foreach ($forbidden as $word) {
        if (strpos($upperWhere, $word) !== false) {
            return false;
        }
    }
    return true;
}

// l'importazione di file molto grandi può richiedere diversi minuti: nessun limite di tempo, e si prosegue anche se il browser chiude la connessione
set_time_limit(0);
ignore_user_abort(true);

/** numero di record inseriti a db con una sola chiamata a carica_a_db (il file viene letto a blocchi, senza caricarlo tutto in memoria) */
const RECORD_PER_BLOCCO = 5000;
/** ogni quanti record scrivere nel log l'avanzamento dell'importazione */
const RECORD_PER_LOG_AVANZAMENTO = 100000;

/** sostituisce i ritorni a capo CRLF e CR con LF leggendo il file a pezzi (file_get_contents su un csv enorme esaurirebbe la memoria) */
function normalizza_ritorni_a_capo($path) {
    $path_temp = $path . ".tmp";
    $in = fopen($path, "rb");
    $out = fopen($path_temp, "wb");
    if (!$in or !$out)
        throw new \Exception("Errore durante la normalizzazione del file csv");
    $cr_in_sospeso = false;//l'ultimo carattere del pezzo precedente era un CR: se il pezzo successivo inizia con LF è un CRLF
    while (($pezzo = fread($in, 1048576)) !== false && $pezzo !== "") {
        if ($cr_in_sospeso and $pezzo[0] === "\n")
            $pezzo = substr($pezzo, 1);
        $cr_in_sospeso = substr($pezzo, -1) === "\r";
        fwrite($out, str_replace(["\r\n", "\r"], "\n", $pezzo));
    }
    fclose($in);
    fclose($out);
    if (!rename($path_temp, $path))
        throw new \Exception("Errore durante la normalizzazione del file csv");
}

try {
    if(!isset($_FILES["file_to_upload"]) || !isset($_POST["folder_z"]) || !isset($_POST["id_lavoro"]) || !isset($_POST["id_flusso"])){
        throw new \Exception('Impossibile importare il file, non è presente sul server');
    }

    $file = $_FILES['file_to_upload'];
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new \Exception('Errore durante il salvataggio del file');
    }

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);
    $id_flusso = $_POST["id_flusso"];


    $db->blocca_db();

    //prelevo elaborazioni (elab join è la vista che joina lavori elaborazioni e base dati
    if(!$elaborazioni = $db->preleva_da_db("select * from elab_join where id_lavoro= ? ",[$_POST["id_lavoro"]]))
        throw new \Exception("Errore durante prelievo lavoro");
    $dati_lavoro = $elaborazioni[0];

    //verifico univocità commessa
    if($db->verifica_presenza_record("select * from `{$dati_lavoro['nome_base_dati']}` where id_flusso=?  or nome_file_idx_input = ?",[$_POST["id_flusso"], basename($targetPath)]))
        throw new \Exception("Id commessa già utilizzato o dati già importati");

    //apertura file
    $estensione_file = pathinfo($targetPath, PATHINFO_EXTENSION);
    if($estensione_file == "xlsx" or $estensione_file == "xls"){
        $file = new Excel($log);
        $file->setta_parametri([
            "intestazione"=>$dati_lavoro["intestazione_si_no"]
        ]);
        if(!$file->apri($targetPath, $dati_lavoro["intestazione_si_no"]))
            throw new \Exception("Errore durante l'apertura del file");
    }else{
        // sostituzione caratteri di ritorno a capo (php 8 non permette più di usare @ini_set("auto_detect_line_endings", true);
        normalizza_ritorni_a_capo($targetPath);

        $file = new Csv($log);
        $file->setta_parametri([
            "intestazione"=>$dati_lavoro["intestazione_si_no"]
        ]);
        if(!$file->apri($targetPath, $dati_lavoro["intestazione_si_no"]))
            throw new \Exception("Errore durante l'apertura del file");
        $file->setta_parametri([
            "separatore" => $dati_lavoro["separatore"] ?? ";",
            "qualificatore_testo" => '"'
        ], true);
    }


    $intestazione_temp = explode("|", $dati_lavoro["intestazione"]);
    $intestazione = [];
    foreach($intestazione_temp as $i => $campo){
        $intestazione[$campo] = $i;
    }

    $folder_z = str_replace("\\","/", $_POST["folder_z"]);
    $folder_cliente = dirname($folder_z) . "/";
    $folder_z = basename($folder_z);

    //numero della riga del file originale: la lettura è contigua (si ferma alla prima riga vuota), quindi il primo record è alla
    //riga 2 se c'è l'intestazione, altrimenti alla 1. la colonna c'è solo nelle basi dati create dopo l'introduzione di numero_riga_csv
    $riga_iniziale = $dati_lavoro["intestazione_si_no"] ? 2 : 1;
    $tabella_ha_numero_riga = $db->verifica_presenza_record("select 1 from information_schema.columns where table_schema = database() and table_name = ? and column_name = 'numero_riga_csv'", [$dati_lavoro["nome_base_dati"]]);
    //in crea_base_dati.php l'eventuale colonna id viene rinominata in id2
    $rinomina_id = array_key_exists("id", $intestazione);

    //importo i dati leggendo il file un record alla volta e inserendoli a blocchi (il file può avere centinaia di migliaia di righe)
    $nome_file_input = basename($targetPath);
    $log->scrivi_log("Importazione file $nome_file_input nella tabella {$dati_lavoro["nome_base_dati"]} (id_flusso $id_flusso): inizio lettura");
    $blocco = [];
    $totale_record = 0;
    foreach($file->converti_in_array_streaming($intestazione, ignora_intestazione: $dati_lavoro["intestazione_si_no"]) as $record){
        if(!isset($record[$dati_lavoro["campo_cap"]]))
            throw new \Exception("Errore durante importazione dati, campo cap non trovato");
        $record[$dati_lavoro["campo_cap"]] = str_pad($record[$dati_lavoro["campo_cap"]], 5, '0', STR_PAD_LEFT);
        if($tabella_ha_numero_riga)
            $record["numero_riga_csv"] = $riga_iniziale + $totale_record;
        $record["nome_file_idx_input"] = $nome_file_input;
        $record["id_flusso"] = $id_flusso;
        $record["folder_z"] = $folder_z;
        $record["lavoro"] = $dati_lavoro["nome_lavoro"];
        if($rinomina_id) {
            $record["id2"] = $record["id"];
            unset($record["id"]);
        }
        $blocco[] = $record;
        $totale_record++;

        if(count($blocco) >= RECORD_PER_BLOCCO) {
            if(!$db->carica_a_db($blocco, "`{$dati_lavoro["nome_base_dati"]}`"))
                throw new \Exception("Errore durante l'inserimento nella tabella dati (record da " . ($totale_record - count($blocco) + 1) . " a $totale_record)");
            $blocco = [];
            if($totale_record % RECORD_PER_LOG_AVANZAMENTO == 0)
                $log->scrivi_log("Importazione file $nome_file_input: inseriti $totale_record record");
        }
    }
    if($blocco) {
        if(!$db->carica_a_db($blocco, "`{$dati_lavoro["nome_base_dati"]}`"))
            throw new \Exception("Errore durante l'inserimento nella tabella dati (record da " . ($totale_record - count($blocco) + 1) . " a $totale_record)");
        $blocco = [];
    }
    if(!$totale_record)
        throw new \Exception("Errore durante la conversione del file");
    $log->scrivi_log("Importazione file $nome_file_input: inseriti $totale_record record, creazione elaborazioni");

    //creo i record elaborazione
    $query_viste = [];
    foreach($elaborazioni as $key => $elaborazione){
        $dati_elaborazione = [
            "id_elaborazione_lavoro" => $elaborazione["id_elaborazione_lavoro"],
            "stato" => 0,
            "folder_z"=>$folder_z,
            "nome_file_idx_input" => basename($targetPath),
            "id_flusso" => $id_flusso,
            "folder_cliente" => $folder_cliente
        ];

        //se ci sono record per questa elaborazione
        if($db->verifica_presenza_record("select * from `{$dati_lavoro["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}` where id_flusso=?",[$id_flusso]))
        {
            //creo nuovo record elaborazione
            if (!$db->carica_a_db($dati_elaborazione, "elaborazioni"))
                throw new \Exception("Errore durante l'inserimento nella tabella elaborazioni");
            $id_elaborazione = $db->get_ultimo_id_inserito();
            $where = $elaborazione["where"] ? "and {$elaborazione['where']}" : "";
            if (!isSqlSafe($where))
                throw new \Exception("Where non sicuro");
            //aggiorno record dati con nome elaborazione
            if (!$db->esegui_query("update `{$dati_lavoro["nome_base_dati"]}` set id_elaborazione=? where id_flusso='$id_flusso' $where ", [$id_elaborazione]))
                throw new \Exception("Errore durante l'aggiornamento della tabella dati");
        }
    }
    //verifico presenza id_elaborazione a null (vuol dire che le where impostate dall'utente non coprono tutte le casistiche
    if($db->verifica_presenza_record("select * from `{$dati_lavoro["nome_base_dati"]}` where id_elaborazione is null and id_flusso=?",[$id_flusso]))
        throw new \Exception("Errore durante l'aggiornamento della tabella dati, non sono compresi nelle elaborazioni tutti i record");

    //elimina il file temporaneo
    unlink($targetPath);

    $db->sblocca_db();

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Elaborazione creata con successo',
        'id_flusso'=>$id_flusso
    ]);
}catch (\Throwable $e) {
    if(isset($log))
        $log->scrivi_log("Importazione fallita: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")", "ERROR");
    if(isset($targetPath) and file_exists($targetPath))
        unlink($targetPath);
    if(isset($db))
        $db->sblocca_db(true);
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>




