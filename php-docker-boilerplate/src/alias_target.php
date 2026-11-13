<?php
namespace indi\Classes;

/**
 * Alias di target: stessa tabella (`target`), stessa classe di ordinamento e stessi ordinati, si ordinano come CREATIVE. Nei menu compaiono come tipi di spedizione a sé,
 * in database sono configurazioni target con prodotto_target = chiave dell'alias.
 *  - etichetta: dicitura fissa del prodotto sulle etichette scatola e bancale (descrizione_tipo_spedizione)
 *  - gamma: descrittivo gamma del datamatrix (guida Poste, tabella 1: E = Editoria)
 * Va tenuto allineato con ALIAS_TARGET in src/components/creazione_configurazione.vue e in Elaborazione_postale_target.php.
 * @return array
 */
function alias_target()
{
    return [
        "NO_PROFIT" => ["etichetta" => "PROMOZIONE NO PROFIT", "gamma" => "E"],
        "PUBBL_INFORMATIVA_NO_PROFIT" => ["etichetta" => "PUBBL. INFORMATIVA NO PROFIT", "gamma" => "E"],
        "PERIODICO_ROC" => ["etichetta" => "PERIODICO ROC", "gamma" => "E"],
    ];
}
