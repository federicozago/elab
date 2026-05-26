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
    if(!isset($_FILES["file_to_upload"]) || !isset($_POST["folder_z"]) || !isset($_POST["id_lavoro"])){
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
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    $db->blocca_db();

    if(!$elaborazioni = $db->preleva_da_db("select * from elab_join where id_lavoro= ? ",[$_POST["id_lavoro"]]))
        throw new \Exception("Errore durante prelievo lavoro");
    $dati_lavoro = $elaborazioni[0];

    //apertura file
    $estensione_file = pathinfo($targetPath, PATHINFO_EXTENSION);
    if($estensione_file == "csv"){
        $file = new Csv();
    }elseif($estensione_file == "xlsx" or $estensione_file == "xls"){
        $file = new Excel();
    }else{
        throw new \Exception("Tipo di file non supportato");
    }
    $file->setta_parametri([
        "intestazione"=>$dati_lavoro["intestazione_si_no"],
        "numero_riga_intestazione" => 0
    ]);

    if(!$dati = $file->converti_in_array(explode("|", $dati_lavoro["intestazione"])))
        throw new \Exception("Errore durante la conversione del file");

    if(! is_dir($_POST["folder_z"]) or ! file_exists($_POST["folder_z"]))
        throw new \Exception("Cartella di destinazione non valida");
    $folder_cliente = dirname($_POST["folder_z"]);
    $folder_z = basename($_POST["folder_z"]);

    //importo i dati
    //scelgo id flusso se non settato (non serve sia univoco in senso assoluto, deve essere univoco fra le varie elaborazioni di quella base dati
    if($_POST["id_flusso"])
        $id_flusso = $_POST["id_flusso"];
    else
        if(!$id_flusso = $db->preleva_da_db_un_singolo_valore("select coalesce(max(id_flusso) + 1, 1) from `{$dati_lavoro['nome_base_dati']}`"))
            throw new \Exception("Errore durante prelievo id_flusso");
    $a = new Array_php();
    $dati = $a->array_add_column($dati, "nome_file_idx_input", basename($targetPath));
    $dati = $a->array_add_column($dati, "id_flusso", $id_flusso);
    $dati = $a->array_add_column($dati, "folder_z", $folder_z);
    $dati = $a->array_add_column($dati, "lavoro", $dati_lavoro["nome_lavoro"]);
    if(!$db->carica_a_db($dati, $dati_lavoro["nome_base_dati"]))
        throw new \Exception("Errore durante l'inserimento nella tabella dati");

    //creo i record elaborazione
    $query_viste = [];
    foreach($elaborazioni as $key => $elaborazione){
        $dati_elaborazione = [
            "id_elaborazione_lavoro" => $elaborazione["id"],
            "stato" => 0,
            "folder_z"=>$folder_z,
            "nome_file_idx_input" => basename($targetPath),
            "id_flusso" => $id_flusso,
            "folder_cliente" => $folder_cliente
        ];

        if(!$db->carica_a_db($dati_elaborazione, "elaborazioni"))
            throw new \Exception("Errore durante l'inserimento nella tabella elaborazioni");
        $id_elaborazione = $db->get_ultimo_id_inserito();
        $where = $elaborazione["where"] ? "and {$elaborazione['where']}" : "";
        if(!isSqlSafe($where))
            throw new \Exception("Where non sicuro");
        if(!$db->esegui_query("update {$dati_lavoro["nome_base_dati"]} set id_elaborazione=?,nome_elaborazione= ? where id_flusso='$id_flusso' $where ", [$id_elaborazione, $elaborazione["nome"]]))
            throw new \Exception("Errore durante l'aggiornamento della tabella dati");

        //creo la vista dei dati (nome base dati + id elaborazione
        $query_viste[] = "create view {$dati_lavoro["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]} as select * from {$dati_lavoro['nome_base_dati']} where id_elaborazione=$id_elaborazione";
    }
    //verifico presenza id_elaborazione a null (vuol dire che le where impostate dall'utente non coprono tutte le casistiche
    if($db->verifica_presenza_record("select * from {$dati_lavoro["nome_base_dati"]} where id_elaborazione is null and id_flusso='$id_flusso'"))
        throw new \Exception("Errore durante l'aggiornamento della tabella dati, non sono compresi nelle elaborazioni tutti i record");

    //elimina il file temporaneo
    unlink($targetPath);

    $db->sblocca_db();

    //creo le viste delle elaborazioni
    foreach($query_viste as $query)
        if(!$db->esegui_query($query))
            throw new \Exception("Errore durante la creazione della vista $query");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Elaborazione creata con successo',
        'id_flusso'=>$id_flusso
    ]);
}catch (\Exception $e) {
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




