<?php
// Configura gli header CORS se necessario
use indi\Classes\Gestione_db;
use indi\Classes\Segnalazioni_e_log;
use indi\Classes\Variabili_globali_import;
use indi\Classes\Read_idx;

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

try {
    // Verifica se è stato inviato un file
    if (!isset($_FILES['fileToUpload'])) {
        throw new Exception('Nessun file inviato');//throw invia questo messaggio al catch
    }
    if(!isset($_POST["baseDati"]))
        throw new Exception('Manca il nome della base dati');

    $file = $_FILES['fileToUpload'];
    $intestazioneSiNo = $_FILES['intestazioneSiNo'];

    // Verifica eventuali errori durante l'upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Errore durante l\'upload: ' . $file['error']);
    }

    // Genera un nome file univoco per evitare sovrascritture
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $uploadDir . $fileName;

    // Opzionale: verifica il tipo di file
    //$allowedTypes = ['image/jpeg', 'image/png', 'application/pdf']; // Esempio di tipi consentiti
    //if (!in_array($file['type'], $allowedTypes)) {
    //    throw new Exception('Tipo di file non consentito');
    //}

    // Opzionale: verifica la dimensione del file (esempio: max 5MB)
    //$maxSize = 5 * 1024 * 1024; // 5MB in bytes
    //if ($file['size'] > $maxSize) {
    //    throw new Exception('File troppo grande (max 5MB)');
    //}

    // Sposta il file nella cartella di destinazione
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Errore durante il salvataggio del file');
    }

    $vg=new Variabili_globali_import();
    $vg=$vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab",$log);
    $read_idx = new Read_idx($log,"elab",$uploadDir);

    //estrazione intestazione
    if (!$intestazione = $read_idx->estrai_intestazione()) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Impossibile estrarre l\'intestazione'
        ]);
        throw new Exception("Errore durante l'estrazione dell'intestazione");
    }
    if( ! $intestazioneSiNo){
        $intestazione_temp = [];
        foreach($intestazione as $i => $campo){
            $intestazione_temp[] = "Colonna $i";
        }
        $intestazione = $intestazione_temp;
    }


    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File caricato con successo',
        'fileName' => $fileName,
        'intestazione'=>$intestazione
    ]);

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'File caricato con successo',
        'filePath' => $targetPath
    ]);

} catch (Exception $e) {
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>