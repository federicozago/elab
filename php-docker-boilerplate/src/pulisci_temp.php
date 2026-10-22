<?php
namespace indi\Classes;

/** elimina dalla cartella dei file caricati (temp/) quelli rimasti orfani: gli upload di excel/csv che per un errore fatale, un
 * riavvio del container o un wizard abbandonato non sono stati cancellati dall'endpoint che li usava.
 * considera solo i file con il nome generato dagli endpoint di upload (uniqid() . '_' . nome file originale) più vecchi di $ore ore,
 * così non tocca i file temporanei degli altri endpoint (es. csv della chiusura elaborazione) né le importazioni ancora in corso
 * @param string $cartella
 * @param int $ore età minima in ore per considerare orfano un file
 * @return int numero di file eliminati
 */
function pulisci_file_temporanei_orfani($cartella = 'temp/', $ore = 24) {
    $eliminati = 0;
    $limite = time() - $ore * 3600;
    foreach (glob(rtrim($cartella, "/") . "/*") ?: [] as $path) {
        if (!is_file($path) or !preg_match('/^[0-9a-f]{13}_/', basename($path)))
            continue;
        if (filemtime($path) < $limite and @unlink($path))
            $eliminati++;
    }
    return $eliminati;
}
