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

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);//da usare quando il front end manda i dati con axios senza headers: { 'Content-Type': 'multipart/form-data' }
    // Verifica se è stato inviato un file
    if (!isset($jsonData["nome_base_dati"]) or !isset($jsonData["campo_cap"]) or !isset($jsonData["campo_provincia"]) or !isset($jsonData["campo_localita"]) or !isset($jsonData["file_base_dati"]) or !isset($jsonData["intestazione_si_no"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    //verifica esistenza base dati
    $jsonData['nome_base_dati'] = strtolower($jsonData['nome_base_dati']);
    if($db->verifica_esistenza_tabella("{$vg["database_cliente"]}.{$jsonData['nome_base_dati']}"))
        throw new \Exception("La base dati {$jsonData["nome_base_dati"]} esiste già");

    //verifico se non è già presente la base dati in tab mysql base_dati
    if($db->verifica_presenza_record("select * from base_dati where nome_base_dati='{$jsonData["nome_base_dati"]}'")){
        throw new \Exception("La base dati {$jsonData["nome_base_dati"]} è già presente a db");
    }

    //creazione sql base dati
    $sql_tab = "CREATE TABLE `{$jsonData["nome_base_dati"]}` (
  `id` char(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT (uuid()),
  `id_tabella` int NOT NULL AUTO_INCREMENT,";

    if(!is_array($jsonData["intestazione"]))
        throw new \Exception("Errore durante la creazione della tabella, intestazione non valida");
    foreach ($jsonData["intestazione"] as $c) {
        if(strtolower($c) == "id")
            $c = "id2";
        $sql_tab .= "`{$c}` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,";
    }

    $sql_tab .= "
    `nome_file_idx_input` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `id_flusso` varchar(36) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `id_elaborazione` int DEFAULT NULL,
  `totpagine_calcolate` int DEFAULT NULL,
  `data_inserimento` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `folder_z` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `lavoro` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `campo_libero_1` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `campo_libero_2` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `campo_libero_3` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `campo_libero_4` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `campo_libero_5` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  PRIMARY KEY (`id_tabella`),
  UNIQUE KEY `{$jsonData["nome_base_dati"]}_IDX` (`id`) USING BTREE,
  KEY `{$jsonData["nome_base_dati"]}_id_flusso_IDX` (`id_flusso`) USING BTREE,
  KEY `{$jsonData["nome_base_dati"]}_lavoro_IDX` (`lavoro`) USING BTREE,
  KEY `{$jsonData["nome_base_dati"]}_folder_z_IDX` (`folder_z`) USING BTREE,
  KEY `{$jsonData["nome_base_dati"]}_id_flusso2_IDX` (`id_flusso`,`id`) USING BTREE,
  KEY `idx_flusso_province_id` (`id_flusso`,`{$jsonData["campo_cap"]}`,`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;";

    if(!$db->esegui_query($sql_tab))
        throw new \Exception("Errore durante la creazione della tabella - " . $db->get_errori());

    //salvo record base_dati
    $jsonData["intestazione"] = implode("|", $jsonData["intestazione"]);
    if(!$db->carica_a_db($jsonData, "base_dati", null,true))
        throw new \Exception("Errore durante l'inserimento nella tabella base dati - " . $db->get_errori());

    $id = $db->get_ultimo_id_inserito();
        // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Base dati creata con successo',
        'id_base_dati'=>$id,
        'nome_base_dati'=>$jsonData["nome_base_dati"],
        'intestazione'=>$jsonData["intestazione"]
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




