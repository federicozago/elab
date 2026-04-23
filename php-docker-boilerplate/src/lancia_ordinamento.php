<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS se necessario
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    // Verifica se è stato inviato un file
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    //prelevo dati e configurazioni elaborazione richiesta
    if(!$elaborazione = $db->preleva_da_db("select * from elab_join where e.id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];
    if(!$configurazione = $db->preleva_da_db("select * from {$elaborazione['tipo_spedizione']} where id = ?",[$elaborazione['id_configurazione']]))
        throw new \Exception("Errore durante prelievo configurazione");
    $configurazione = $configurazione[0];

    //crea l'oggetto Elaborazione_postale
    $elab = "indi\\Classes\\Elaborazione_postale_{$elaborazione['tipo_spedizione']}";
    if( ! class_exists($elab))
        throw new \Exception("Tipo di elaborazione non supportato");
    $elab = new $elab("elab",$elaborazione["id_flusso"],$vg["database_cliente"]);
    $elab->setta_parametri(array_merge(
        $elaborazione,
        $configurazione,
        ["tabella_ordinamento" => "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}"]
    ), true);

    if(!$db->esegui_query("update elaborazioni set stato=1 where id={$elaborazione['id']}"))//aggiorno stato "in elaborazione"
        throw new \Exception("Errore durante l'aggiornamento della tabella elaborazioni");

    //ordino
    if(!$elab->ordina("{$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}"))
        throw new \Exception("Errore durante l'ordinamento");

    if(!$db->esegui_query("update elaborazioni set stato=2 where id={$elaborazione['id']}"))//aggiorno stato "elaborazione"
        throw new \Exception("Errore durante l'aggiornamento della tabella elaborazioni");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Ordinamento completato'
    ]);
}catch (\Exception $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>



