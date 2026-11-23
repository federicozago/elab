<?php
namespace indi\Classes;
require 'vendor/autoload.php';
require_once __DIR__ . '/alias_target.php';
require_once __DIR__ . '/normalizza_configurazione.php';

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
//false: un warning php (es. "Array to string conversion") diventa un'eccezione e arriva al frontend come messaggio; con il valore predefinito (true) il programma si fermava con exit(1) restituendo una risposta vuota con http 200, che il frontend scambiava per un salvataggio riuscito
$log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore:false);
$db = new Gestione_db("elab",$log);

try{
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }

    $tipo_spedizione = isset(alias_target()[$jsonData["tipo_spedizione"]]) ? "target" : $jsonData["tipo_spedizione"];//NO_PROFIT e gli altri alias sono target
    $jsonData = array_merge($jsonData, $jsonData[$tipo_spedizione]);//alcuni parametri sono all'interno di $jsonData[$tipo_spedizione]
    $jsonData = normalizza_dati_configurazione($jsonData);

    if (!empty($jsonData["stampa_datamatrix"])) {
        // descrittivo gamma del datamatrix (guida Poste, tabella 1): non modificabile, dipende dal tipo di spedizione
        $gamma = [
            "time" => "V",
            "massiva" => "B",
            "target" => alias_target()[$jsonData["prodotto_target"] ?? ""]["gamma"] ?? "T",//gli alias (no profit, periodico...) sono Editoria
        ][$tipo_spedizione] ?? null;
        if ($gamma)
            $jsonData["descrittivo_gamma"] = $gamma;
        foreach (["autorizzazione_postale" => "Autorizzazione postale", "omologa" => "Omologa", "tabella_codici_figli" => "Tabella codici figli", "tabella_codici_padri" => "Tabella codici padri"] as $campo => $descrizione)
            if (empty($jsonData[$campo]))
                throw new \Exception("$descrizione è obbligatoria quando si stampa il datamatrix");
        // la massiva non ha l'ordinamento in bancali: senza max_scatole_per_bancale l'id bancale (ID HU) della DU non verrebbe valorizzato
        if ($tipo_spedizione === "massiva" && empty($jsonData["max_scatole_per_bancale"]))
            throw new \Exception("Scatole per bancale (max) è obbligatorio quando si stampa il datamatrix");
        // target con la DU: l'id bancale (ID HU) è obbligatorio, i bancali si assegnano in automatico all'ordinamento e servono le dimensioni di busta o scatola e la tara del pallet
        if ($tipo_spedizione === "target") {
            $campi_dimensioni = ($jsonData["plichi"] ?? '') === "Scatole" ? ["larghezza_scatola", "lunghezza_scatola", "altezza_scatola"] : ["larghezza_busta", "altezza_busta", "profondita_busta"];
            foreach (array_merge($campi_dimensioni, ["tara_pallet"]) as $campo)
                if (empty($jsonData[$campo]))
                    throw new \Exception("Il campo $campo è obbligatorio quando si stampa il datamatrix (serve ai bancali della DU)");
            $jsonData["gestione_bancali"] = 0;//con la DU i bancali sono sempre automatici
        }
    }
    // il datamatrix comporta sempre la DU (il codice invio della DU è il datamatrix): la prenotazione con DU segue la stampa del datamatrix
    $jsonData["prenotazione_con_du"] = !empty($jsonData["stampa_datamatrix"]) ? 1 : 0;
    // alias di target: la descrizione prodotto (etichette scatola e bancale) è fissa e il tipo formato creative non serve (compare solo sull'etichetta scatola, non per questi prodotti)
    if ($tipo_spedizione === "target" && isset(alias_target()[$jsonData["prodotto_target"] ?? ""])) {
        $jsonData["descrizione_tipo_spedizione"] = alias_target()[$jsonData["prodotto_target"]]["etichetta"];
        unset($jsonData["tipo_formato_creative"]);
    }
    // le tabelle dei codici finiscono nelle query del programma di ordinamento: accetto solo schema.tabella con il prefisso previsto (la scelta arriva dal menu, ma il valore passa dal client)
    foreach (["tabella_codici_figli" => "figli", "tabella_codici_padri" => "padri"] as $campo => $prefisso)
        if (!empty($jsonData[$campo]) && !preg_match('/^(import_file|elab)\.' . $prefisso . '_[A-Za-z0-9_]+$/', $jsonData[$campo]))
            throw new \Exception("Valore non valido per il campo $campo");

    // Il nome configurazione salvato a db non è più (solo) quello digitato dal
    // cliente: è la concatenazione di più campi, per renderlo univoco e
    // descrittivo. Il campo digitato dal cliente (nome_configurazione) è
    // opzionale ed è l'ultimo pezzo, se presente.
    if ($tipo_spedizione === "target" && isset(alias_target()[$jsonData["prodotto_target"] ?? ""])) {
        //alias (no profit...): nel nome compare solo il nome dell'alias (niente "target", descrizione e sotto prodotto)
        $componenti_nome = [
            $jsonData["ragione_sociale_cliente_estesa"] ?? '',
            $jsonData["prodotto_target"],
        ];
    } else {
        $componenti_nome = [
            $jsonData["ragione_sociale_cliente_estesa"] ?? '',
            $tipo_spedizione,
            $jsonData["descrizione_tipo_spedizione"] ?? '',
        ];
        if ($tipo_spedizione === "target" && !empty($jsonData["prodotto_target"]))
            $componenti_nome[] = $jsonData["prodotto_target"];
    }
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
}catch(\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile creare la configurazione - '.$e->getMessage()
    ]);
}