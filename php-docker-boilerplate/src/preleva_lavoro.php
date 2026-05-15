<?php
// Configura gli header CORS se necessario
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

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["id_lavoro"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    $dati = $db->preleva_da_db("
select 
    nome_lavoro,id_base_dati, nome_base_dati,nome_elaborazione,`where`,tipo_spedizione  
from 
    lavori 
        join base_dati on id_base_dati = base_dati.id 
        join elaborazioni_lavoro on id_lavoro = lavoro.id
where
    lavoro.id = ?
order by elaborazioni_lavoro.id",[$jsonData["id_lavoro"]]);//se stato è maggiore di zero allora è già ordinato
    if($dati === false)
        throw new \Exception("Errore durante prelievo lavori");

    foreach($dati as $key => $elaborazione){
        if(!$nome_configurazione = $db->preleva_da_db_un_singolo_valore("select nome_configurazione from {$elaborazione["tipo_spedizione"]} where id = ?",[$elaborazione["id_configurazione"]]))
            throw new \Exception("Errore durante prelievo configurazioni");
        $dati[$key]["nome_configurazione"] = $nome_configurazione;
    }

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'lavori'=>$dati
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
