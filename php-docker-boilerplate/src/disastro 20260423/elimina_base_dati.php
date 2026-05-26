<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Imposta l'header per indicare che la risposta è in formato JSON
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

try{
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["id_elaborazione"]))
        throw new \Exception("Manca id_elaborazione");

    if(!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];

    //verifico elaborazioni ancora in corso per l'attuale base dati
    if($db->verifica_presenza_record("select * from elab_join where e.stato!=255 and id_base_dati='{$elaborazione['id_base_dati']}'"))
        throw new \Exception("Impossibile eliminare dati, elaborazione in corso");

    //elimino base dati
    if(!$db->esegui_query("drop table {$elaborazione['nome_base_dati']}"))
        throw new \Exception("Errore durante eliminazione tabella dati");

    //elimino tabelle ordinati
    if(!$db->esegui_query("drop table ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}"))
        throw new \Exception("Errore durante eliminazione tabella ordinati");
    if($db->verifica_esistenza_tabella("elab.ordinati_light_{$elaborazione['nome_base_dati']}"))
        if(!$db->esegui_query("drop table ordinati_light_{$elaborazione['nome_base_dati']}"))
            throw new \Exception("Errore durante eliminazione tabella ordinati light");

    if(!$db->esegui_query("delete from base_dati where id = ?", [$jsonData["id_base_dati"]]))
        throw new \Exception("Errore durante eliminazione base dati");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Base dati eliminata con successo'
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile prelevare la configurazione'
    ]);
}