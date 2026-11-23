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
}

try{
    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore:false);
    $db = new Gestione_db("elab",$log);
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    if(!isset($jsonData["tipo_spedizione"]) or !isset($jsonData["id_configurazione"]))
        throw new \Exception("Manca il tipo di spedizione o l'id della configurazione");
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

    // Stesso calcolo di crea_configurazione.php: il nome configurazione non è
    // (solo) quello digitato dal cliente, ma la concatenazione di più campi. Il
    // campo digitato dal cliente (nome_configurazione) è opzionale ed è l'ultimo
    // pezzo, se presente.
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
        'id_configurazione'=>$jsonData["id_configurazione"],
        'nome_configurazione'=>$jsonData["nome_configurazione"]
    ]);
}catch(\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Impossibile aggiornare la configurazione - ' . $e->getMessage()
    ]);
}