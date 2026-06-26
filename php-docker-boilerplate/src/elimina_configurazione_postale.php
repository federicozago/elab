<?php
namespace indi\Classes;

require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    $jsonData = json_decode(file_get_contents('php://input'), true);

    if (!isset($jsonData["id_configurazione"]) || !isset($jsonData["tipo_spedizione"])) {
        throw new \Exception('Parametri mancanti: id_configurazione e tipo_spedizione sono richiesti.');
    }

    $id_configurazione = $jsonData["id_configurazione"];
    $tipo_spedizione = $jsonData["tipo_spedizione"]; // Corrisponde al nome della tabella

    // 1. Verifica se ci sono record in elaborazioni_lavoro con id_configurazione uguale a quello passato
    // Questo controllo copre anche il requisito di non eliminare se usata in definizioni di lavori
    $presenza_lavoro = $db->verifica_presenza_record(
        "SELECT id FROM elaborazioni_lavoro WHERE id_configurazione = ?",
        [$id_configurazione]
    );

    if ($presenza_lavoro) {
        throw new \Exception('Impossibile eliminare: la configurazione è utilizzata in una o più definizioni di lavoro.');
    }

    // 2. Verifica se nella tabella elaborazioni ci sono record con stato diverso da 255 
    // che hanno id_elaborazione_lavoro che corrisponde a id della tabella elaborazioni_lavoro 
    // che hanno id configurazione uguale a quello che si deve eliminare.
    // NOTA: Se il controllo precedente (punto 1) passa, questo è tecnicamente già garantito 
    // (se non c'è record in elaborazioni_lavoro, non può esserci in elaborazioni collegato).
    

    // 3. Eliminazione della configurazione dalla tabella specifica (es. target, massiva)
    
    $db->esegui_query(
        "DELETE FROM `$tipo_spedizione` WHERE id = ?",
        [$id_configurazione]
    );

    echo json_encode([
        'success' => true,
        'message' => 'Configurazione eliminata con successo'
    ]);

} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
