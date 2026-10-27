<?php
namespace indi\Classes;

/** costruisce la query che estrae i record di un'elaborazione con TUTTE le colonne elencate una per una ("e.`campo` AS `campo`"),
 * così l'operatore può sostituire il valore di ogni campo a mano (es. per creare record civetta). le colonne di anagrafica (e) e
 * ordinati (o) con lo stesso nome vengono prese solo dall'anagrafica. file di sola libreria: usato da preleva_query_sql.php
 * (query mostrata dal pulsante SQL) ed esporta_query_csv.php (stessa query esportata in csv)
 * @param Gestione_db $db
 * @param int|string $id_elaborazione
 * @return array ["query" => string, "completa" => bool, "elaborazione" => riga di elab_join]
 * @throws \Exception se l'elaborazione non esiste
 */
function costruisci_query_sql_elaborazione($db, $id_elaborazione)
{
    if (!$el = $db->preleva_da_db("select nome_lavoro, nome_elaborazione, tipo_spedizione, nome_base_dati, id_flusso from elab_join where id_elaborazione = ?", [$id_elaborazione]))
        throw new \Exception("Elaborazione non trovata");
    $el = $el[0];

    $tabella_anagrafica = "{$el['nome_lavoro']}_{$el['nome_elaborazione']}";
    $tabella_ordinati = "ordinati_{$el['tipo_spedizione']}_{$el['nome_base_dati']}";

    $query_colonne = "select column_name as nome from information_schema.columns where table_schema = database() and table_name = ? order by ordinal_position";
    $colonne_e = array_column($db->preleva_da_db($query_colonne, [$tabella_anagrafica], false) ?: [], "nome");
    $colonne_o = array_column($db->preleva_da_db($query_colonne, [$tabella_ordinati], false) ?: [], "nome");

    $q = fn($nome) => "`" . str_replace("`", "``", $nome) . "`";

    $campi = [];
    $presenti = [];
    foreach ($colonne_e as $c) {
        $campi[] = "    e.{$q($c)} AS {$q($c)}";
        $presenti[strtolower($c)] = true;
    }
    foreach ($colonne_o as $c) {
        if (isset($presenti[strtolower($c)]))
            continue; // stesso nome nell'anagrafica: vale la colonna dell'anagrafica
        $campi[] = "    o.{$q($c)} AS {$q($c)}";
    }
    // se non riesco a leggere le colonne (es. vista non valida) ricado su select *
    $elenco_campi = $campi ? implode(",\n", $campi) : "    *";

    $query = "SELECT\n$elenco_campi\n"
        . "FROM `elab`.{$q($tabella_anagrafica)} e\n"
        . "    JOIN `elab`.{$q($tabella_ordinati)} o\n"
        . "         ON e.id=o.c1\n"
        . "WHERE e.id_flusso = '" . addslashes($el['id_flusso']) . "'\n"
        . "ORDER BY o.progr";

    return ["query" => $query, "completa" => (bool)$campi, "elaborazione" => $el];
}

/** esegue la query in streaming su una connessione dedicata: i record si leggono uno alla volta con fetch() senza caricarli tutti in
 * memoria (Gestione_db li carica tutti e limita ogni query a 2 minuti, inadatto agli export di centinaia di migliaia di righe)
 * @param array $vg variabili globali "elab" (ip_db, porta_db, user_db, pass_db, database_cliente)
 * @param string $query
 * @return \PDOStatement
 */
function esegui_query_streaming($vg, $query)
{
    $porta = $vg["porta_db"] ?? "";
    if ($porta != "" and strpos($porta, "port=") === false)
        $porta = "port=" . $porta;
    $conn = new \PDO("mysql:host={$vg['ip_db']};dbname={$vg['database_cliente']};charset=utf8;$porta;", $vg["user_db"], $vg["pass_db"], [
        \PDO::ATTR_PERSISTENT => false,
        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ]);
    $conn->exec("set names 'utf8'");
    $conn->exec("SET SESSION MAX_EXECUTION_TIME=0");
    return $conn->query($query);
}
