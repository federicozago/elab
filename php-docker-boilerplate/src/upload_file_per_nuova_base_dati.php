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
    $intestazioneSiNo = filter_var($_POST['intestazione_si_no'], FILTER_VALIDATE_BOOLEAN);//converte in booleano

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
    $log = new Segnalazioni_e_log($vg["id_flusso"]);

    //apertura file
    $estensione_file = pathinfo($targetPath, PATHINFO_EXTENSION);
    if($estensione_file == "csv"){
        $file = new Csv($log);
    }else{
        $file = new Excel($log);
    }
    if(!$file->apri($targetPath, $_POST["intestazione_si_no"]))
        throw new \Exception("Errore durante l'apertura del file");

    //estrazione intestazione (inizialmente estraggo prima riga, anche se non è l'intestazione
    if (!$intestazione_temp = $file->estrai_intestazione())
        throw new \Exception("Errore durante l'estrazione dell'intestazione");

    if( ! $_POST["intestazione_si_no"]){
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
    // In caso di errore
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>