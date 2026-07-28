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

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore:false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["tipo_spedizione"]) or !isset($jsonData["id_configurazione"]))
        throw new \Exception("Manca il tipo di spedizione o l'id della configurazione");
    $tipo_spedizione = $jsonData["tipo_spedizione"];
    $jsonData = array_merge($jsonData, $jsonData[$tipo_spedizione]);//alcuni parametri sono all'interno di $jsonData[$tipo_spedizione]

    // Stesso calcolo di crea_configurazione.php: il nome configurazione non è
    // (solo) quello digitato dal cliente, ma la concatenazione di più campi. Il
    // campo digitato dal cliente (nome_configurazione) è opzionale ed è l'ultimo
    // pezzo, se presente.
    $componenti_nome = [
        $jsonData["ragione_sociale_cliente_estesa"] ?? '',
        $tipo_spedizione,
        $jsonData["descrizione_tipo_spedizione"] ?? '',
    ];
    if ($tipo_spedizione === "target" && !empty($jsonData["prodotto_target"]))
        $componenti_nome[] = $jsonData["prodotto_target"];
    $componenti_nome[] = $jsonData["tipo_formato_postale"] ?? '';
    if (!empty($jsonData["nome_configurazione"]))
        $componenti_nome[] = $jsonData["nome_configurazione"];

    $nome_configurazione = implode("_", array_filter($componenti_nome, fn($v) => $v !== ''));
    // sicurezza: la colonna è varchar(255), taglio per non rischiare un errore db
    $jsonData["nome_configurazione"] = substr($nome_configurazione, 0, 255);

    if( ! $colonne = $db->preleva_colonne("{$vg["database_cliente"]}.$tipo_spedizione"))
        throw new \Exception("Errore durante prelievo colonne");

    //imposto query update
    $update = "";
    $valori=[];
    foreach($colonne as $colonna)
        if(array_key_exists($colonna, $jsonData)) {
            $valori[] = $jsonData[$colonna];
            $update = $update ? $update . ", $colonna = ?" : "$colonna = ?";
        }elseif(!in_array($colonna,["id","id_tabella","data_inserimento","data_aggiornamento"])) {
            $valori[] = null;
            $update = $update ? $update . ", $colonna = ?" : "$colonna = ?";
        }

    $valori[] = $jsonData["id_configurazione"];
    if(!$db->esegui_query("update $tipo_spedizione set $update where id = ?",$valori))
        throw new \Exception("Errore durante aggiornamento configurazione");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione aggiornata',
        'id_configurazione'=>$jsonData["id_configurazione"]
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile aggiornare la configurazione - ' . $e->getMessage()
    ]);
}