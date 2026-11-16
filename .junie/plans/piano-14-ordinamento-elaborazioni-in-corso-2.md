---
sessionId: session-260701-115827-c2io
---

# Requirements

### Overview & Goals
Creare il documento `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md`, seguendo lo stesso stile e la stessa struttura dei piani d'azione già presenti nella cartella `documenti/` (es. `13_PIANO_AZIONE_SUGGERIMENTI_MULTI_PAROLA.md`, `PIANO_AZIONE_FIX_STATO_ELABORAZIONE.md`).

Il documento deve descrivere in dettaglio come modificare la logica dell'endpoint `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php` affinché, nella tabella "Elaborazioni in corso" di `IndexPage.vue`, il **lavoro che contiene l'elaborazione inserita più di recente** compaia in cima alla tabella, invece dell'attuale ordinamento per `id_lavoro`/`nome_lavoro`.

Il documento deve contenere tutto il codice (backend, ed eventualmente frontend) proposto per la modifica, così che possa essere applicato in un secondo momento da chi implementerà la modifica.

### Scope
**In Scope**
- Creazione del file `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md` con: analisi del problema, codice PHP di soluzione (prima/dopo), soluzione alternativa via SQL, considerazioni frontend, checklist di test.
- Aggiornamento di `documenti/SINTESI_PROGETTO.md` per documentare la logica di raggruppamento/ordinamento di `preleva_elaborazioni_in_corso.php` (sezione endpoint, righe ~492-495).

**Out of Scope**
- Non verrà modificato il codice reale di `preleva_elaborazioni_in_corso.php` né di `IndexPage.vue`: il codice proposto viene scritto **solo** all'interno del nuovo documento di piano, come richiesto esplicitamente.
- Non verranno eseguiti test funzionali/automatici sul progetto (nessun ambiente DB disponibile in questa fase), solo una checklist di verifica manuale da includere nel documento.

### Functional Requirements
- Il documento deve spiegare **perché** l'attuale logica produce un ordinamento per lavoro (in base a `id_lavoro`) invece che per "ultima elaborazione inserita".
- Deve fornire il codice PHP completo (prima/dopo) per `preleva_elaborazioni_in_corso.php` che ordina i gruppi lavoro in base al `MAX(id_elaborazione)` di ciascun gruppo, in ordine decrescente.
- Deve discutere e scartare/consigliare un'alternativa via SQL (subquery con `MAX(id_elaborazione) GROUP BY id_lavoro`).
- Deve chiarire l'impatto (nullo o opzionale) sul frontend, dato che `IndexPage.vue` non applica un ordinamento di default sulla tabella dei gruppi.
- Deve aggiornare `SINTESI_PROGETTO.md` per riflettere la nuova logica di raggruppamento + ordinamento, cosicché la documentazione di progetto resti accurata.

# Technical Design

### Current Implementation
**File:** `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php` (78 righe)

```php
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);
...
$nome_lavoro = "";
$dati_out = [];
$indice_dati_out=0;
$indice_riga = 0;
foreach ($dati as $key => $elaborazione) {
    if($elaborazione["nome_lavoro"] != $nome_lavoro){
        if($key)
            $indice_dati_out++;
        $dati_out[$indice_dati_out] = [
            "nome_lavoro" => $elaborazione["nome_lavoro"],
            "row-id" => $indice_riga++,
            "dettagli" => []
        ];
    }
    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;
    $nome_lavoro = $elaborazione["nome_lavoro"];
}
```
- La query ordina per `id_lavoro, id_elaborazione`: questo ordine serve **solo** a garantire che le righe dello stesso lavoro siano consecutive, così da poter rilevare il "cambio gruppo" scorrendo il resultset una volta sola.
- Conseguenza: `$dati_out` (e quindi `elaborazioni` nella risposta JSON) risulta ordinato per `id_lavoro` crescente (= ordine di creazione del lavoro), non per data/ordine di inserimento dell'ultima elaborazione.
- **Frontend:** `src/pages/IndexPage.vue` (righe 69-84, 443-451, 516-533) consuma l'array così com'è: la `q-table` principale non ha prop `pagination`/`sort-by` di default, quindi mostra le righe nell'ordine restituito dal backend. Basta quindi sistemare l'ordine lato backend.
- La tabella `elaborazioni` (istanze) ha `id` auto-incrementale (`id_elaborazione` nella vista `elab_join`): un id più alto = riga inserita più di recente (confermato da `crea_elaborazione.php`, che usa `get_ultimo_id_inserito()` dopo ogni INSERT).

### Key Decisions
**Decisione: calcolare l'ordinamento in PHP dopo il raggruppamento, non in SQL.**
- Si mantiene invariata la query attuale (`order by id_lavoro, id_elaborazione`), necessaria per il raggruppamento a scorrimento singolo.
- Durante il ciclo si traccia, per ogni gruppo, l'`id_elaborazione` massimo incontrato (`id_ultima_elaborazione`).
- Dopo il ciclo, si riordina `$dati_out` con `usort()` in base a `id_ultima_elaborazione` decrescente.
- **Motivazione:** modifica minima e isolata al solo file dell'endpoint, nessun impatto sulla vista condivisa `elab_join` né su altri endpoint che la usano (`lancia_ordinamento.php`, `chiudi_elaborazione.php`, `crea_elaborazione.php`, ecc.), coerente con lo stile del file (post-processing in PHP dopo un'unica SELECT).
- **Alternativa scartata (da documentare comunque nel piano):** spostare l'ordinamento in SQL con una subquery aggregata (`MAX(id_elaborazione) GROUP BY id_lavoro`) joinata a `elab_join`. Scartata perché più invasiva (self-join su una vista che già aggrega 4 tabelle) a fronte di un beneficio marginale.

### Proposed Backend Code (da inserire nel documento di piano)
```php
// --- PRIMA ---
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);
if($dati === false)
    throw new \Exception("Errore durante prelievo elaborazioni");

$nome_lavoro = "";
$dati_out = [];
$indice_dati_out=0;
$indice_riga = 0;
foreach ($dati as $key => $elaborazione) {
    if($elaborazione["nome_lavoro"] != $nome_lavoro){
        if($key)
            $indice_dati_out++;
        $dati_out[$indice_dati_out] = [
            "nome_lavoro" => $elaborazione["nome_lavoro"],
            "row-id" => $indice_riga++,
            "dettagli" => []
        ];
    }
    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;
    $nome_lavoro = $elaborazione["nome_lavoro"];
}

// --- DOPO ---
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);//ordine necessario solo per il raggruppamento
if($dati === false)
    throw new \Exception("Errore durante prelievo elaborazioni");

$nome_lavoro = "";
$dati_out = [];
$indice_dati_out=0;
$indice_riga = 0;
foreach ($dati as $key => $elaborazione) {
    if($elaborazione["nome_lavoro"] != $nome_lavoro){
        if($key)
            $indice_dati_out++;
        $dati_out[$indice_dati_out] = [
            "nome_lavoro" => $elaborazione["nome_lavoro"],
            "row-id" => $indice_riga++,
            "id_ultima_elaborazione" => 0, // NUOVO: traccia l'id_elaborazione piu' alto del gruppo
            "dettagli" => []
        ];
    }

    $elaborazione["row-id"] = $indice_riga++;
    $dati_out[$indice_dati_out]["dettagli"][] = $elaborazione;

    // NUOVO: aggiorno l'id massimo (= elaborazione piu' recente) del gruppo corrente
    $dati_out[$indice_dati_out]["id_ultima_elaborazione"] = max(
        $dati_out[$indice_dati_out]["id_ultima_elaborazione"],
        $elaborazione["id_elaborazione"]
    );

    $nome_lavoro = $elaborazione["nome_lavoro"];
}

// NUOVO: riordino i lavori mettendo per primo quello con l'elaborazione inserita piu' di recente
usort($dati_out, function ($a, $b) {
    return $b["id_ultima_elaborazione"] <=> $a["id_ultima_elaborazione"];
});
```

### Alternative Considered (SQL-based, da documentare come alternativa)
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
Pro: un'unica query già ordinata, nessun `usort` lato PHP. Contro: self-join aggiuntivo su una vista già complessa, più difficile da leggere/mantenere: nel piano verrà indicata come alternativa, con la soluzione PHP come raccomandata.

### Frontend — Impatto ed eventuali modifiche opzionali
**File:** `src/pages/IndexPage.vue`
- Nessuna modifica obbligatoria: la `q-table` (righe 69-84) non ha `pagination`/`sort-by` impostati, quindi rispetta l'ordine dell'array `elaborazioniInCorso` restituito dal backend.
- Modifica opzionale (da proporre nel documento, non da applicare): aggiungere una colonna informativa che mostri `id_ultima_elaborazione`, per rendere visibile all'utente il criterio di ordinamento:
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
- Nota da inserire nel documento: se l'utente clicca sull'header "Nome" (colonna `sortable: true`) la tabella si riordinerà alfabeticamente per scelta esplicita dell'utente; questo è un comportamento standard di `q-table` e non è oggetto di questa modifica.

### File Structure
- **Nuovo file:** `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md`
- **File aggiornato:** `documenti/SINTESI_PROGETTO.md` (sezione `#### **preleva_elaborazioni_in_corso.php**`, righe ~492-495)
- Nessun file di codice sorgente del progetto (`.php`/`.vue`) verrà modificato in questa fase.

### SINTESI_PROGETTO.md — Testo proposto per l'aggiornamento
Sostituire il blocco attuale:
```
#### **preleva_elaborazioni_in_corso.php**
**Scopo:** Restituisce tutte le elaborazioni attive raggruppate per lavoro.
- **Output:** Array di lavori con dettagli elaborazioni (stato ordinamento, prenotazione, folder Z, ecc.)
- **Utilizzo:** Popola la tabella "Elaborazioni in corso" in IndexPage
```
con:
```
#### **preleva_elaborazioni_in_corso.php**
**Scopo:** Restituisce tutte le elaborazioni attive (stato != 255) raggruppate per lavoro.
- **Query:** `select * from elab_join where stato != 255 order by id_lavoro, id_elaborazione` — l'ordinamento per `id_lavoro, id_elaborazione` serve solo a rilevare il cambio di gruppo durante il ciclo PHP (le righe dello stesso lavoro sono consecutive).
- **Raggruppamento:** Il PHP scorre il resultset e ogni volta che cambia `nome_lavoro` crea un nuovo elemento in `$dati_out` con struttura `{ nome_lavoro, row-id, dettagli: [...] }`.
- **Ordinamento del risultato:** Per ciascun lavoro viene calcolato l'`id_elaborazione` massimo tra le sue elaborazioni (`id_ultima_elaborazione`), che rappresenta l'elaborazione inserita più di recente per quel lavoro. L'array dei lavori viene poi riordinato in base a questo valore, decrescente, così che il lavoro con l'elaborazione più recente compaia per primo.
- **Output:** Array di lavori (ordinati per ultima elaborazione inserita, più recente prima) con dettagli elaborazioni (stato ordinamento, prenotazione, folder Z, ecc.)
- **Utilizzo:** Popola la tabella "Elaborazioni in corso" in IndexPage; il frontend non applica un ordinamento di default, quindi mostra i lavori nell'ordine restituito dal backend.
```

# Delivery Steps

###   Step 1: Scrivere analisi del problema e soluzione backend nel documento di piano
Il file `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md` viene creato con titolo, problema rilevato e soluzione backend completa.

- Creare il file con intestazione `### PIANO DI AZIONE 14: ORDINAMENTO ELABORAZIONI IN CORSO PER ULTIMA INSERITA`.
- Sezione "Problema Rilevato": spiegare che `preleva_elaborazioni_in_corso.php` raggruppa le elaborazioni per lavoro scorrendo i dati ordinati per `id_lavoro, id_elaborazione`, producendo un array ordinato per lavoro invece che per elaborazione più recente.
- Sezione "Analisi Tecnica": riportare il codice attuale (righe 22 e 26-61 di `preleva_elaborazioni_in_corso.php`) con riferimento a righe/file.
- Sezione "Soluzione Proposta - Backend": inserire il codice PHP completo prima/dopo (tracciamento `id_ultima_elaborazione` per gruppo + `usort` finale decrescente).
- Sezione "Alternativa Considerata": inserire la query SQL con subquery `MAX(id_elaborazione) GROUP BY id_lavoro` e spiegare perché si preferisce la soluzione PHP.

###   Step 2: Aggiungere considerazioni frontend, checklist di test e chiudere il documento
Il documento `14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md` viene completato con le sezioni relative al frontend, alla validazione e al riepilogo finale.

- Sezione "Considerazioni Frontend": spiegare che `IndexPage.vue` non necessita modifiche obbligatorie perché la `q-table` non ha `sort-by` di default e rispetta l'ordine ricevuto dal backend.
- Inserire il codice opzionale per aggiungere la colonna `id_ultima_elaborazione` a `columnsGruppiElaborazioniInCorso` come miglioramento facoltativo di UX.
- Sezione "Test e Validazione": checklist manuale (es. creare due lavori con elaborazioni in tempi diversi, verificare che il lavoro con l'elaborazione più recente compaia per primo, verificare che il click sull'header "Nome" continui a riordinare alfabeticamente).
- Sezione "Riepilogo Modifiche" (file coinvolti, impatto, rischio) e "Note Finali" che ribadiscono che il codice è proposto ma non applicato al progetto.

###   Step 3: Documentare la logica di raggruppamento/ordinamento in SINTESI_PROGETTO.md
`documenti/SINTESI_PROGETTO.md` riporta la logica aggiornata di raggruppamento e ordinamento dell'endpoint `preleva_elaborazioni_in_corso.php`.

- Individuare la sezione `#### **preleva_elaborazioni_in_corso.php**` (circa righe 492-495) all'interno di `SINTESI_PROGETTO.md`.
- Sostituirla con la versione estesa che descrive: la query di prelievo, la logica di raggruppamento per `nome_lavoro`, e il nuovo criterio di ordinamento dei gruppi basato su `MAX(id_elaborazione)` per lavoro.
- Verificare che la nuova descrizione sia coerente con il resto del documento (es. sezione "Stati Elaborazione" e "Concetti Chiave") senza duplicare informazioni già presenti altrove.