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
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    if(!isset($jsonData["id_base_dati"]) or !isset($jsonData["nome_lavoro"]) or !isset($jsonData["elaborazioni"]))
        throw new \Exception('Mancano dati in input');

    $db->blocca_db();

    if(!$baseDati = $db->preleva_da_db("select * from base_dati where id='{$jsonData["id_base_dati"]}'"))
        throw new \Exception("Errore durante prelievo base dati");

    //creo il record lavoro
    if(!$db->carica_a_db($jsonData, "lavori",null,true))
        throw new \Exception("Errore durante l'inserimento nella tabella lavori");
    $id_lavoro = $db->get_ultimo_id_inserito();

    //creo i record elaborazione del lavoro
    //$elaborazioni = json_decode($jsonData['elaborazioni'],true);//????????????????????
    $elaborazioni = $jsonData['elaborazioni'];
    foreach($elaborazioni as $key => $elaborazione){
        if(!isSqlSafe($elaborazione["where"]))
            throw new \Exception("Where non sicuro");

        $elaborazione["id_lavoro"] = $id_lavoro;
        unset($elaborazione["id"]);
        if(!$db->carica_a_db($elaborazione, "elaborazioni_lavoro",null,true))
            throw new \Exception("Errore durante l'inserimento nella tabella elaborazioni_lavoro");
    }

    $db->sblocca_db();


    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Lavoro creato con successo',
        'id_lavoro'=>$id_lavoro
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




