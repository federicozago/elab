<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Gestisci la richiesta preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}//Quando un'applicazione web (ad esempio un frontend in Vue.js o React che risiede su un dominio diverso da quello dell'API) tenta di effettuare una richiesta HTTP "complessa" (come una POST con JSON o una richiesta che include header personalizzati), il browser invia automaticamente una prima richiesta con metodo OPTIONS prima della richiesta effettiva. Risposta di successo: Invia uno stato HTTP 200 OK (http_response_code(200)). Questo comunica al browser che il server accetta le modalità della richiesta descritte negli header inviati precedentemente (righe 6-8):

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
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    if(!isset($jsonData["id_base_dati"]) or !isset($jsonData["nome_lavoro"]) or !isset($jsonData["elaborazioni"]))
        throw new \Exception('Mancano dati in input');

    $db->blocca_db();

    //creo il record lavoro
    if(!$db->carica_a_db($jsonData, "lavori",null,true))
        throw new \Exception("Errore durante l'inserimento nella tabella lavori");
    $id_lavoro = $db->get_ultimo_id_inserito();

    //creo i record elaborazione del lavoro
    $elaborazioni = $jsonData['elaborazioni'];
    foreach($elaborazioni as $key => $elaborazione){
        if(!isSqlSafe($elaborazione["where"]))
            throw new \Exception("Where non sicuro");

        $elaborazione["id_lavoro"] = $id_lavoro;
        $elaborazione["id_configurazione"] = $elaborazione["id_configurazione"]["value"];
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
        'id_lavoro'=>(int)$id_lavoro
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




