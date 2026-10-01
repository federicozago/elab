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
}

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

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore:false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["id_lavoro"]))
        throw new \Exception("Manca il tipo di lavoro");
    $db->blocca_db();

    // prelevo nome_lavoro e nome_elaborazione ATTUALI (prima di sovrascriverli) per poter
    // eliminare le viste vecchie nel caso in cui nome_lavoro/nome_elaborazione cambino
    if(! $vecchio_lavoro = $db->preleva_da_db("select nome_lavoro from lavori where id = ?", [$jsonData["id_lavoro"]]))
        throw new \Exception("Lavoro non trovato");
    $vecchio_nome_lavoro = $vecchio_lavoro[0]["nome_lavoro"];
    $vecchie_elaborazioni = $db->preleva_da_db("select id, nome_elaborazione from elaborazioni_lavoro where id_lavoro = ?", [$jsonData["id_lavoro"]]);
    $vecchio_nome_elaborazione_per_id = [];
    foreach(($vecchie_elaborazioni ?: []) as $vecchia_elaborazione)
        $vecchio_nome_elaborazione_per_id[$vecchia_elaborazione["id"]] = $vecchia_elaborazione["nome_elaborazione"];

    //prelevo nome base dati (serve per ricreare le viste con la base dati aggiornata)
    if( ! $nome_base_dati = $db->preleva_da_db_un_singolo_valore("select nome_base_dati from base_dati where id=?",[$jsonData["id_base_dati"]]))
        throw new \Exception("Errore durante prelievo nome base dati");

    //aggiorno i dati del lavoro
    $dati_lavoro = $jsonData;
    unset($dati_lavoro["elaborazioni"]);
    unset($dati_lavoro["elaborazioni"]);
    if(!$db->esegui_query("update lavori set nome_lavoro = :nome_lavoro, id_base_dati=:id_base_dati where id = :id_lavoro",$dati_lavoro))
        throw new \Exception("Errore durante l'aggiornamento del lavoro");

    $elaborazioni = $jsonData['elaborazioni'];
    $query_viste = [];
    $query_drop_viste = [];
    foreach($elaborazioni as $key => $elaborazione){
        $elaborazione["id_configurazione"] = $elaborazione["id_configurazione"]["value"];
        if(!isSqlSafe($elaborazione["where"]))
            throw new \Exception("Where non sicuro");
        if(!$db->esegui_query("update elaborazioni_lavoro set nome_elaborazione = :nome_elaborazione, `where`= :where, tipo_spedizione = :tipo_spedizione, id_configurazione=:id_configurazione where id=:id",$elaborazione))
            throw new \Exception("Errore durante l'aggiornamento dell'elaborazione");

        // se nome_lavoro o nome_elaborazione sono cambiati, la vista vecchia va eliminata
        // perché verrebbe altrimenti create con un nome diverso, lasciando quella vecchia orfana
        $vecchio_nome_elaborazione = $vecchio_nome_elaborazione_per_id[$elaborazione["id"]] ?? $elaborazione["nome_elaborazione"];
        $vecchio_nome_vista = "{$vecchio_nome_lavoro}_{$vecchio_nome_elaborazione}";
        $nuovo_nome_vista = "{$jsonData["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}";
        if($vecchio_nome_vista !== $nuovo_nome_vista)
            $query_drop_viste[] = "drop view if exists `$vecchio_nome_vista`";

        //ricreo la vista dei dati (nome base dati + where possono essere cambiati)
        $where = $elaborazione["where"] ? "where {$elaborazione['where']}" : "";
        $query_viste[] = "create OR REPLACE ALGORITHM = UNDEFINED VIEW `$nuovo_nome_vista` as select * from `$nome_base_dati` $where";
    }

    $db->sblocca_db();

    //elimino le viste vecchie (se nome_lavoro/nome_elaborazione sono cambiati) e ricreo quelle aggiornate
    foreach($query_drop_viste as $query)
        if(!$db->esegui_query($query))
            throw new \Exception("Errore durante l'eliminazione della vecchia vista $query");
    foreach($query_viste as $query)
        if(!$db->esegui_query($query))
            throw new \Exception("Errore durante la creazione della vista $query");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione aggiornata'
    ]);
}catch(\Exception $e) {
    if(isset($db))
        $db->sblocca_db(true);
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile aggiornare la configurazione - ' . $e->getMessage()
    ]);
}