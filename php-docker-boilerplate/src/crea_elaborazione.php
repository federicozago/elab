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
        $content = file_get_contents($targetPath);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        file_put_contents($targetPath, $content);

        $file = new Csv($log);
        $file->setta_parametri([
            "intestazione"=>$dati_lavoro["intestazione_si_no"]
        ]);
        if(!$file->apri($targetPath, $dati_lavoro["intestazione_si_no"]))
            throw new \Exception("Errore durante l'apertura del file");
        $file->setta_parametri([
            "separatore" => $dati_lavoro["separatore"] ?? ";"
        ], true);
    }


    $intestazione_temp = explode("|", $dati_lavoro["intestazione"]);
    $intestazione = [];
    foreach($intestazione_temp as $i => $campo){
        $intestazione[$campo] = $i;
    }

    if(!$dati = $file->converti_in_array($intestazione,ignora_intestazione: $dati_lavoro["intestazione_si_no"]))
        throw new \Exception("Errore durante la conversione del file");

    $folder_z = str_replace("\\","/", $_POST["folder_z"]);
    $folder_cliente = dirname($folder_z) . "/";
    $folder_z = basename($folder_z);

    //importo i dati
    $a = new Array_php();
    $dati = $a->array_add_column($dati, "nome_file_idx_input", basename($targetPath));
    $dati = $a->array_add_column($dati, "id_flusso", $id_flusso);
    $dati = $a->array_add_column($dati, "folder_z", $folder_z);
    $dati = $a->array_add_column($dati, "lavoro", $dati_lavoro["nome_lavoro"]);
    if(!$db->carica_a_db($dati, "`{$dati_lavoro["nome_base_dati"]}`"))
        throw new \Exception("Errore durante l'inserimento nella tabella dati");

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
}catch (\Exception $e) {
    if(file_exists($targetPath))
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




