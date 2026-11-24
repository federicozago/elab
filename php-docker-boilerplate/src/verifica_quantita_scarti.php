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

/** percentuale di scarti (record con bacino ZCARTI) oltre la quale l'esito è negativo. sostituibile con la chiave soglia_scarti_percentuale di variabili_globali/elab.php.
 * stima del 2026-11-23: le elaborazioni a db hanno 0-2,8% di scarti, AMT massiva il 20,8% (vedi documenti/21_PIANO_AZIONE_VERIFICA_SCARTI.md) */
const SOGLIA_SCARTI_PERCENTUALE = 5.0;

/**
 * Dopo l'ordinamento calcola quanti record dell'elaborazione sono scarti (colonna bacino = 'ZCARTI' della tabella ordinati) e la loro percentuale sul totale.
 * L'ordinamento è comunque riuscito: anche con esito negativo la risposta è http 200 ("esito" false), il frontend mostra solo un'etichetta di avviso. Non modifica niente a db.
 */
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Manca id_elaborazione');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Elaborazione non trovata");
    $elaborazione = $elaborazione[0];

    $soglia = isset($vg["soglia_scarti_percentuale"]) && is_numeric($vg["soglia_scarti_percentuale"]) ? (float)$vg["soglia_scarti_percentuale"] : SOGLIA_SCARTI_PERCENTUALE;
    $q = fn($nome) => "`" . str_replace("`", "``", $nome) . "`";
    $nome_tabella_ordinati = "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}";
    $tabella_ordinati = $q($vg["database_cliente"]) . "." . $q($nome_tabella_ordinati);

    //non tutti i prodotti hanno la colonna bacino nella tabella ordinati: la verifica non si applica
    $ha_bacino = (bool)$db->preleva_da_db_un_singolo_valore(
        "select count(*) from information_schema.columns where table_schema = ? and table_name = ? and column_name = 'bacino'",
        [$vg["database_cliente"], $nome_tabella_ordinati]
    );
    if (!$ha_bacino) {
        http_response_code(200);
        echo json_encode(['success' => true, 'esito' => true, 'applicabile' => false, 'message' => 'Verifica scarti non applicabile a questo prodotto']);
        exit;
    }

    $nome_elaborazione = "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}";
    $conteggi = $db->preleva_da_db(
        "select count(*) as totale, coalesce(sum(bacino = 'ZCARTI'), 0) as scarti from $tabella_ordinati where id_flusso = ? and nome_elaborazione = ?",
        [$elaborazione["id_flusso"], $nome_elaborazione],
        false
    );
    if ($conteggi === false)
        throw new \Exception("Errore durante il conteggio degli scarti");
    $totale = (int)$conteggi[0]["totale"];
    $scarti = (int)$conteggi[0]["scarti"];
    if ($totale === 0)
        throw new \Exception("Non ci sono record ordinati per questa elaborazione");

    $percentuale = round(100 * $scarti / $totale, 1);
    $esito = (100 * $scarti / $totale) <= $soglia;

    $risposta = [
        'success' => true,
        'esito' => $esito,
        'applicabile' => true,
        'totale' => $totale,
        'scarti' => $scarti,
        'percentuale' => $percentuale,
        'soglia' => $soglia,
        'message' => $esito
            ? "Scarti nella norma: $scarti su $totale ($percentuale%)"
            : "Ordinamento eseguito ma con molti scarti: $scarti su $totale ($percentuale%, soglia $soglia%)",
    ];

    //con esito negativo cerco il motivo più frequente: provincia vuota nell'anagrafica. se la query non riesce il dettaglio manca e basta
    if (!$esito && !empty($elaborazione["campo_provincia"])) {
        $vuoti = $db->preleva_da_db_un_singolo_valore(
            "select count(*) from " . $q($vg["database_cliente"]) . "." . $q($nome_elaborazione) . " e
             join $tabella_ordinati o on o.c1 = e.id
             where e.id_flusso = ? and o.id_flusso = ? and o.nome_elaborazione = ? and o.bacino = 'ZCARTI'
               and (e.{$q($elaborazione['campo_provincia'])} is null or trim(e.{$q($elaborazione['campo_provincia'])}) = '')",
            [$elaborazione["id_flusso"], $elaborazione["id_flusso"], $nome_elaborazione],
            false
        );
        if ($vuoti !== false)
            $risposta['provincia_vuota'] = (int)$vuoti;
    }

    http_response_code(200);
    echo json_encode($risposta);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
