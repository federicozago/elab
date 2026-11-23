<?php
namespace indi\Classes;

/** campi della configurazione che finiscono nel datamatrix o nella DU e devono essere in maiuscolo (il datamatrix accetta solo cifre e lettere maiuscole).
 * non sono inclusi utenza_ps_online (utenza di PsOnline, può avere minuscole) e le tabelle dei codici (nomi di tabelle mysql) */
const CAMPI_DATAMATRIX_DU_MAIUSCOLI = [
    "descrittivo_gamma", "sap", "codice_cliente_postale", "classe_postale", "tipo_prodotto_postale", "cap_mittente",
    "codice_identificativo_stampatore", "omologa", "tipologia_prodotto", "codice_servizio_accessorio", "tipologia_codice_di_tracciatura",
];

/** prepara i dati di crea_configurazione.php / aggiorna_configurazione.php prima del salvataggio:
 * - i valori inviati come oggetto {label, value} (menu con opzioni etichetta/valore) diventano il solo valore: un array non può essere salvato in una colonna
 *   (Gestione_db::carica_a_db andava in errore con "Array to string conversion")
 * - con il datamatrix i campi di CAMPI_DATAMATRIX_DU_MAIUSCOLI sono salvati in maiuscolo e senza spazi ai lati
 * @param array $dati dati già uniti al sotto oggetto del tipo di spedizione
 * @return array
 */
function normalizza_dati_configurazione(array $dati): array
{
    foreach ($dati as $campo => $valore)
        if (is_array($valore) && array_key_exists("value", $valore) && array_key_exists("label", $valore))
            $dati[$campo] = $valore["value"];

    if (!empty($dati["stampa_datamatrix"]))
        foreach (CAMPI_DATAMATRIX_DU_MAIUSCOLI as $campo)
            if (isset($dati[$campo]) && is_string($dati[$campo]))
                $dati[$campo] = mb_strtoupper(trim($dati[$campo]), "UTF-8");

    return $dati;
}
