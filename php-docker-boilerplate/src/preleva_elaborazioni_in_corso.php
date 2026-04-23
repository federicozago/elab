<?php
// Configura gli header CORS se necessario
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Cartella di destinazione per l'upload
$uploadDir = 'temp/';

// Verifica se la cartella esiste, altrimenti creala

try {
    // Verifica se è stato inviato un file
    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"]);
    $db = new Gestione_db("elab", $log);

    $dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);//se stato è maggiore di zero allora è già ordinato
    if($dati === false)
        throw new \Exception("Errore durante prelievo elaborazioni");

    //raggruppo i dati per gruppo elaborazione
    $nome_lavoro = "";
    $dati_out = [];
    $indice_dati_out=0;
    $indice_riga = 0;
    foreach ($dati as $key => $elaborazione) {
        //se è cambiato il gruppo
        if($elaborazione["nome_lavoro"] != $nome_lavoro){
            if($key)//se non è il primo elemento di $dati
                $indice_dati_out++;
            $dati_out[$indice_dati_out] = [
                "nome_lavoro" => $elaborazione["nome_lavoro"],
                "row-id" => $indice_riga++,//viene assegnato 0 al primo ciclo
                "dettagli" => []
            ];
        }

        //se è da prenotare
        if($elaborazione["stato"] == 2){//elaborazione già ordinata
            $da_prenotare = $db->preleva_da_db_un_singolo_valore("select da_prenotare from {$elaborazione['tipo_spedizione']} where id={$elaborazione['id_configurazione']}");
            if($da_prenotare)
                if(!$da_prenotare = $db->verifica_presenza_record("select * from ordinati_{$elaborazione["tipo_spedizione"]}_{$elaborazione["nome_base_dati"]} where id_flusso='{$elaborazione["id_flusso"]}' and nome_elaborazione='{$elaborazione["nome_lavoro"]}_{$elaborazione["nome_elaborazione"]}' and data_spedizione is null"))
                    throw new \Exception("Errore durante prelievo elaborazioni");
            $elaborazione["da_prenotare"] = $da_prenotare;
        }else{
            $elaborazione["da_prenotare"] = 0;
        }

        //creo il gruppo e alimento la relativa tabella dettagli
        $elaborazione["row-id"] = $indice_riga++;
        $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;

        $nome_lavoro = $elaborazione["nome_elaborazione_gruppo"];

    }

    // Restituisci una risposta di successo
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'elaborazioni'=>$dati_out
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
