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

$vg=new Variabili_globali_import();
$vg=$vg->get_variabili_globali("elab");
$log = new Segnalazioni_e_log($vg["id_flusso"]);
$db = new Gestione_db("elab",$log);

try{
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    $tipo_spedizione = $jsonData["tipo_spedizione"];
    $jsonData = array_merge($jsonData, $jsonData[$tipo_spedizione]);//alcuni parametri sono all'interno di $jsonData[$tipo_spedizione]

    // Il nome configurazione salvato a db non è più (solo) quello digitato dal
    // cliente: è la concatenazione di più campi, per renderlo univoco e
    // descrittivo. Il campo digitato dal cliente (nome_configurazione) è
    // opzionale ed è l'ultimo pezzo, se presente.
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

    //salvo la configurazione
    if(!$db->carica_a_db($jsonData,$tipo_spedizione,null,true))
        throw new \Exception('Impossibile creare la configurazione, errore: ' . implode(", ", $db->get_errori()));
    //prelevo la configurazione creata
    $id = $db->get_ultimo_id_inserito();
    if(!$conf_inserita = $db->preleva_da_db("select id,nome_configurazione from $tipo_spedizione where id_tabella=$id "))
        throw new \Exception('Impossibile prelevare la nuova configurazione - ' . implode(", ", $db->get_errori()));

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Configurazione creata con successo',
        'id_configurazione'=>$conf_inserita[0]["id"],
        'nome_configurazione'=>$conf_inserita[0]["nome_configurazione"]
    ]);
}catch(\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile creare la configurazione - '.$e->getMessage()
    ]);
}