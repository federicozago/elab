### PIANO DI AZIONE 14: ORDINAMENTO ELABORAZIONI IN CORSO PER ULTIMA INSERITA

**Problema rilevato:**
L'endpoint `preleva_elaborazioni_in_corso.php` preleva tutte le elaborazioni non chiuse (`stato != 255`) e le raggruppa per lavoro, in modo che ogni riga della tabella "Elaborazioni in corso" (in `IndexPage.vue`) rappresenti un lavoro con il dettaglio espandibile delle sue elaborazioni.

Per poter raggruppare i dati con un semplice scorrimento (senza dover cercare ogni volta il gruppo giusto), la query preleva i record ordinati per `id_lavoro, id_elaborazione`:

```php
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);
```

Questo ordinamento serve **solo** a garantire che le righe dello stesso lavoro siano consecutive nel resultset, così da poter creare un nuovo elemento in `$dati_out` ogni volta che cambia `nome_lavoro`. Di conseguenza l'array finale `$dati_out` (e quindi `elaborazioni` nella risposta JSON) risulta ordinato per `id_lavoro` crescente, cioè per ordine di **creazione del lavoro**, e NON per ordine di inserimento dell'ultima elaborazione.

Il frontend (`src/pages/IndexPage.vue`) non applica alcun ordinamento proprio: la `q-table` principale (righe 69-84) non ha le prop `pagination`/`sort-by` impostate, e la funzione `prelevaElaborazioniInCorso()` (righe 516-533) assegna semplicemente `elaborazioniInCorso.value = response.data.elaborazioni` senza riordinare nulla. Quindi la tabella mostra i lavori esattamente nell'ordine restituito dal backend.

**Obiettivo:**
Far comparire in cima alla tabella "Elaborazioni in corso" il **lavoro che contiene l'elaborazione inserita più di recente**, mantenendo però l'attuale logica di raggruppamento (che deve restare invariata perché dipende dall'ordinamento `id_lavoro, id_elaborazione` del resultset).

---

### Analisi Tecnica

**File:** `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php`

Codice attuale (righe 22, 26-61):

```php
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

    //creo il gruppo e alimento la relativa tabella dettagli
    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;

    $nome_lavoro = $elaborazione["nome_lavoro"];
}
```

Punti chiave:
- La tabella `elaborazioni` (istanze) ha `id` auto-incrementale (`id_elaborazione` nella vista `elab_join`): un id più alto corrisponde a una riga inserita più di recente (confermato in `crea_elaborazione.php`, che usa `get_ultimo_id_inserito()` subito dopo ogni INSERT).
- Un lavoro può avere più elaborazioni: quindi "l'ultima elaborazione inserita per un lavoro" corrisponde al valore massimo di `id_elaborazione` fra tutte le elaborazioni di quel lavoro.
- Il raggruppamento a scorrimento richiede che i dati arrivino già ordinati per `id_lavoro`, quindi **non possiamo semplicemente cambiare l'`ORDER BY` della query** senza rompere la logica di raggruppamento (altrimenti le righe dello stesso lavoro non sarebbero più consecutive).

---

### Soluzione Proposta - Backend

**Decisione:** calcolare l'ordinamento finale in PHP, dopo il raggruppamento, senza toccare la query esistente (che deve restare ordinata per `id_lavoro, id_elaborazione` per garantire il corretto raggruppamento).

Durante il ciclo si traccia, per ogni gruppo/lavoro, l'`id_elaborazione` più alto incontrato (`id_ultima_elaborazione`). Al termine del ciclo, l'array `$dati_out` viene riordinato con `usort()` in base a questo valore, in ordine decrescente, così che il lavoro con l'elaborazione più recente compaia per primo.

```php
// --- PRIMA ---
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

    //creo il gruppo e alimento la relativa tabella dettagli
    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;

    $nome_lavoro = $elaborazione["nome_lavoro"];
}


// --- DOPO ---
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);//ordine necessario solo per il raggruppamento a scorrimento
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
            "id_ultima_elaborazione" => 0, // NUOVO: tiene traccia dell'id_elaborazione più alto del gruppo
            "dettagli" => []
        ];
    }

    //creo il gruppo e alimento la relativa tabella dettagli
    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;

    // NUOVO: aggiorno l'id più alto (= elaborazione più recente) del gruppo corrente
    $dati_out[$indice_dati_out]["id_ultima_elaborazione"] = max(
        $dati_out[$indice_dati_out]["id_ultima_elaborazione"],
        $elaborazione["id_elaborazione"]
    );

    $nome_lavoro = $elaborazione["nome_lavoro"];
}

// NUOVO: riordino i lavori mettendo per primo quello con l'elaborazione inserita più di recente
usort($dati_out, function ($a, $b) {
    return $b["id_ultima_elaborazione"] <=> $a["id_ultima_elaborazione"];
});
```

Motivazione della scelta:
- Modifica minima e isolata al solo file dell'endpoint, senza toccare la vista condivisa `elab_join` né altri endpoint che la utilizzano (`lancia_ordinamento.php`, `chiudi_elaborazione.php`, `crea_elaborazione.php`, `preleva_elaborazioni_concluse.php`, ecc.).
- Coerente con lo stile del file esistente: un'unica query, post-processing in PHP.
- Nessun impatto sulle performance rilevante: il calcolo del massimo avviene già durante il ciclo esistente, e `usort` opera su un array di dimensione pari al numero di lavori attivi (tipicamente piccolo).

---

### Alternativa Considerata (non raccomandata)

Spostare l'ordinamento direttamente in SQL, con una subquery che calcola il `MAX(id_elaborazione)` per ogni `id_lavoro`:

```sql
SELECT ej.*
FROM elab_join ej
INNER JOIN (
    SELECT id_lavoro, MAX(id_elaborazione) AS id_ultima_elaborazione
    FROM elab_join
    WHERE stato != 255
    GROUP BY id_lavoro
) ultimi ON ultimi.id_lavoro = ej.id_lavoro
WHERE ej.stato != 255
ORDER BY ultimi.id_ultima_elaborazione DESC, ej.id_elaborazione ASC
```

- **Pro:** un'unica query già ordinata correttamente, nessun `usort` lato PHP.
- **Contro:** aggiunge un self-join su `elab_join`, che è già una vista che aggrega più tabelle; risulta più invasiva e più difficile da leggere/mantenere rispetto alla soluzione PHP, a fronte di un beneficio marginale.

Per questi motivi si raccomanda la **soluzione PHP** descritta sopra.

---

### Considerazioni ed Eventuali Modifiche Frontend

**File:** `src/pages/IndexPage.vue`

Nessuna modifica è strettamente necessaria: la `q-table` principale (righe 69-84) non ha le prop `pagination`/`sort-by` impostate, quindi mostra le righe nell'esatto ordine dell'array `elaborazioniInCorso` restituito dal backend (assegnato senza riordini in `prelevaElaborazioniInCorso()`, righe 516-533). È quindi sufficiente la modifica lato backend per ottenere il comportamento desiderato.

Come miglioramento opzionale di UX (facoltativo, da valutare separatamente), si potrebbe aggiungere una colonna informativa che mostri l'`id_ultima_elaborazione`, per rendere visibile all'utente il criterio di ordinamento adottato:

```javascript
const columnsGruppiElaborazioniInCorso = [
  {
    name: 'nomeGruppo',
    label: 'Nome',
    field: 'nome_lavoro',
    sortable: true,
    align: 'left',
  },
  {
    name: 'idUltimaElaborazione',
    label: 'Ultima elaborazione (ID)',
    field: 'id_ultima_elaborazione',
    sortable: true,
    align: 'right',
  },
]
```

Nota: se l'utente clicca sull'header "Nome" (colonna già `sortable: true`), la tabella si riordinerà alfabeticamente per scelta esplicita dell'utente; questo è un comportamento standard di `q-table` e non è oggetto di questa modifica (l'ordinamento per ultima elaborazione è solo l'ordine "di default" al caricamento).

---

### Test e Validazione (checklist manuale)

- Creare (o verificare in ambiente di test) due o più lavori con elaborazioni inserite in momenti diversi.
- Chiamare `preleva_elaborazioni_in_corso.php` e verificare che il lavoro contenente l'elaborazione con `id_elaborazione` più alto compaia per primo nell'array `elaborazioni`.
- Aggiungere una nuova elaborazione a un lavoro "vecchio" (tramite `crea_elaborazione.php`) e verificare che quel lavoro torni in cima alla lista.
- Verificare che, all'interno di un lavoro, l'ordine delle elaborazioni nel dettaglio (`dettagli`) non cambi (deve restare quello attuale, per `id_elaborazione` crescente).
- Verificare che il click sull'header "Nome" nella tabella principale continui a riordinare alfabeticamente i lavori (comportamento standard di `q-table`, non impattato da questa modifica).
- Verificare che lavori senza elaborazioni attive (tutte chiuse, `stato = 255`) non compaiano nell'elenco, come già accade oggi.

---

### Riepilogo Modifiche

| File | Tipo modifica | Rischio |
|---|---|---|
| `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php` | Aggiunta tracciamento `id_ultima_elaborazione` per gruppo + `usort` finale | Basso: modifica isolata, nessun impatto su altri endpoint |
| `src/pages/IndexPage.vue` | Nessuna modifica obbligatoria; colonna `id_ultima_elaborazione` opzionale | Nullo/Basso |

### Note Finali
- Il codice riportato in questo documento è **proposto** e non è stato applicato al progetto: nessun file `.php` o `.vue` reale è stato modificato.
- L'implementazione può essere applicata direttamente copiando il blocco "DOPO" nel file `preleva_elaborazioni_in_corso.php`, sostituendo l'attuale blocco di raggruppamento.
