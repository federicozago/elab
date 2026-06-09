<?php
// Configura gli header CORS se necessario
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

function isSqlSafe($where) {
    if (empty($where)) return true;

    // Lista di parole chiave o caratteri pericolosi
    $forbidden = ['|','`',';', '--', 'DROP', 'DELETE', 'TRUNCATE', 'UPDATE', 'INSERT', 'ALTER', 'CREATE', 'GRANT', 'REVOKE', 'WHERE'];

    $upperWhere = strtoupper($where);
    foreach ($forbidden as $word) {
        if (strpos($upperWhere, $word) !== false) {
            return false;
        }
    }
    return true;
}

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    // Verifica se è stato inviato un file
    if (!isset($_FILES['file_to_upload'])) {
        throw new \Exception('Nessun file inviato');//throw invia questo messaggio al catch
    }

    $file = $_FILES['file_to_upload'];
    $intestazioneSiNo = $_POST['intestazione_si_no'];//converte in booleano
    $separatore = $_POST['separatore'] ?? ';';

    // Verifica eventuali errori durante l'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('Errore durante l\'upload: ' . $file['error']);
    }

    // Genera un nome file univoco per evitare sovrascritture
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    // Sposta il file nella cartella di destinazione
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new \Exception('Errore durante il salvataggio del file');
    }

    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"],blocca_il_programma_per_qualsiasi_errore: false);

    //apertura file
    $estensione_file = pathinfo($targetPath, PATHINFO_EXTENSION);
    if($estensione_file == "xls" or $estensione_file == "xlsx"){
        $file = new Excel($log);
        if(!$file->apri($targetPath, $intestazioneSiNo))
            throw new \Exception("Errore durante l'apertura del file");
    }else{
        // sostituzione caratteri di ritorno a capo (php 8 non permette più di usare @ini_set("auto_detect_line_endings", true);
        $content = file_get_contents($targetPath);
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        file_put_contents($targetPath, $content);

        $file = new Csv($log);
        if(!$file->apri($targetPath, $intestazioneSiNo))
            throw new \Exception("Errore durante l'apertura del file");
        $file->setta_parametri(['separatore' => $separatore],sovrascrivi: true);
    }

    //estrazione intestazione (inizialmente estraggo prima riga, anche se non è l'intestazione
    if (!$intestazione_temp = $file->estrai_intestazione())
        throw new \Exception("Errore durante l'estrazione dell'intestazione");

    if( ! $intestazioneSiNo){
        $intestazione = [];
        foreach($intestazione_temp as $i => $campo){
            $intestazione[] = "Colonna $i";
        }
    }else{
        $intestazione = $intestazione_temp;
        foreach ($intestazione as $key => $c) {
            // Rimuove tutto ciò che non è lettere, numeri o underscore
            $intestazione[$key] = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $c);
            if(!isSqlSafe($c))
                throw new \Exception("Cambiare nomi di campo, sono presenti comandi sql");
        }
    }

    if(!$file->chiudi())
        throw new \Exception("Errore durante chiusura file");

    if(!unlink($targetPath))
        throw new \Exception("Errore durante eliminazione file temporaneo");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File caricato con successo',
        'intestazione'=>$intestazione
    ]);
} catch (\Exception $e) {
    if(file_exists($targetPath))
        unlink($targetPath);
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>