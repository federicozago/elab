<?php
namespace indi\Classes;
require 'vendor/autoload.php';

// Configura gli header CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

// Gestisci la richiesta preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verifica se la cartella esiste, altrimenti creala

try {

    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    // Verifica se è stato inviato un file
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);
    //prelevo dati e configurazioni elaborazione richiesta
    if(!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazioni");
    $elaborazione = $elaborazione[0];
    if(!$configurazione = $db->preleva_da_db("select * from {$elaborazione['tipo_spedizione']} where id = ?",[$elaborazione['id_configurazione']]))
        throw new \Exception("Errore durante prelievo configurazione");
    $configurazione = $configurazione[0];

    $elab = "indi\\Classes\\Elaborazione_postale_{$elaborazione['tipo_spedizione']}";
    if (!class_exists($elab))
        throw new \Exception("Tipo di elaborazione non supportato");
    $elab = new $elab("elab", $elaborazione["id_flusso"], $vg["database_cliente"], $log);
    $nome_elaborazione = "{$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}";
    $elab->setta_parametri(array_merge(
        $elaborazione,
        $configurazione,
        [
            "tabella_ordinamento" => "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}",
            "associazione_campi"=>[
                "cap"=>$elaborazione["campo_cap"],
                "prov"=>$elaborazione["campo_provincia"],
                "localita"=>$elaborazione["campo_localita"],
            ],
            "elaborazioni"=>[
                $nome_elaborazione => []
            ]
        ]
    ), true);

    //l'assegnazione dei bancali può cambiare idplico e progressivi, quindi va eseguita una sola volta: la "prenoto" passando da stato 5 (ordinata,
    //bancali da assegnare) a 6 (bancali in corso) con un'unica update, così un secondo click (o un doppio click) trova lo stato già cambiato e viene rifiutato.
    //se l'assegnazione si interrompe l'elaborazione resta a 6 (l'operazione non è transazionale e i dati potrebbero essere a metà): si riparte con Cancella dati ordinati
    $record_aggiornati = $db->esegui_query("update elaborazioni set stato=6 where id=? and stato=5", [$elaborazione["id_elaborazione"]], true);
    if($record_aggiornati === false)
        throw new \Exception("Impossibile assegnare i bancali");
    if($record_aggiornati === 0)
        throw new \Exception("I bancali di questa elaborazione sono già stati assegnati o non è il momento di assegnarli. Per rifare l'operazione usare Cancella dati ordinati e riordinare");

    if(!$elab->aggiungi_ordinamento_bancali_diretti($nome_elaborazione))
        throw new \Exception("Errore durante l'assegnazione dei bancali");

    if(!$db->esegui_query("update elaborazioni set stato=2 where id=?", [$elaborazione["id_elaborazione"]]))
        throw new \Exception("Errore durante l'aggiornamento dello stato dell'elaborazione");

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Ordinamento completato'
    ]);
}catch (\Throwable $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>



