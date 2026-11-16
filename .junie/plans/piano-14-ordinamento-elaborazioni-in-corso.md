---
sessionId: session-260701-114645-1k0n
---

# Requirements

### Overview & Goals
L'endpoint `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php` restituisce le elaborazioni attive (stato ≠ 255) raggruppate per lavoro, per popolare la tabella "Elaborazioni in corso" in `src/pages/IndexPage.vue`. Attualmente la query SQL ordina i dati per `id_lavoro, id_elaborazione`, quindi il PHP cicla i risultati e crea un record "lavoro" ogni volta che cambia il `nome_lavoro`. Il risultato è un array di lavori ordinato per lavoro (sostanzialmente per `id_lavoro`), non per recency.

Obiettivo: far sì che in cima alla tabella frontend compaia il **lavoro che contiene l'elaborazione inserita più di recente**, senza rompere la logica di raggruppamento esistente né modificare realmente il codice del progetto in questa fase — questa è una fase di **pianificazione**: va prodotto un documento `documenti/14_...md` con l'analisi e tutto il codice proposto (backend + verifica frontend), e va aggiornato `documenti/SINTESI_PROGETTO.md` per riflettere la nuova logica. Nessun file applicativo (`.php`/`.vue`) deve essere effettivamente modificato in questa fase.

### Scope
**In Scope:**
- Creare `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_IN_CORSO.md` con: descrizione del problema, causa, codice PHP completo della modifica proposta per `preleva_elaborazioni_in_corso.php`, e analisi/eventuale codice frontend.
- Aggiornare `documenti/SINTESI_PROGETTO.md` (sezione API `preleva_elaborazioni_in_corso.php`, righe ~492-496) per documentare la nuova logica di ordinamento.

**Out of Scope:**
- Modificare realmente `preleva_elaborazioni_in_corso.php` o `IndexPage.vue`.
- Modificare la vista SQL `elab_join` o altre query che la utilizzano (usata anche da `crea_elaborazione.php`, `chiudi_elaborazione.php`, `lancia_ordinamento.php`, ecc.).
- Modificare la struttura delle tabelle `elaborazioni` / `lavori`.

### User Stories
- Come utente che monitora le elaborazioni in corso in `IndexPage.vue`, voglio vedere in cima alla tabella il lavoro su cui è stata caricata l'ultima elaborazione, così da individuare subito il lavoro più recente senza scorrere l'elenco.

### Functional Requirements
- L'array `elaborazioni` restituito da `preleva_elaborazioni_in_corso.php` deve avere i lavori ordinati in modo che il lavoro contenente l'`id_elaborazione` più alto (= elaborazione creata più di recente, essendo `elaborazioni.id` una chiave auto-incrementante) sia il primo elemento dell'array.
- Il raggruppamento lavoro → dettagli elaborazioni deve restare corretto (nessuna elaborazione persa o duplicata, nessun lavoro spezzato in più gruppi).
- Il filtro esistente `stato != 255` (elaborazioni non chiuse) non deve cambiare.
- I `row-id` (usati come `row-key` da Quasar `q-table`) devono restare univoci e progressivi anche dopo il riordino.
- Il documento di piano deve contenere il codice PHP completo/porzione di file da inserire, pronto per essere applicato in una fase successiva.

# Technical Design

### Current Implementation
File: `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php` (78 righe).

```php
$dati = $db->preleva_da_db("select *  from elab_join where stato != 255 order by id_lavoro,id_elaborazione",[],false);
...
foreach ($dati as $key => $elaborazione) {
    if($elaborazione["nome_lavoro"] != $nome_lavoro){
        if($key) $indice_dati_out++;
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
(righe 22-61 del file attuale). Il raggruppamento funziona solo perché i dati arrivano dalla query già ordinati per `id_lavoro` (righe consecutive con lo stesso lavoro). Cambiare l'`ORDER BY` della query in modo naive (es. per `id_elaborazione desc`) romperebbe il raggruppamento, perché le elaborazioni dello stesso lavoro non sarebbero più consecutive.

Frontend consumer: `src/pages/IndexPage.vue`
- riga 516-533: `prelevaElaborazioniInCorso()` fa `POST /preleva_elaborazioni_in_corso.php` e assegna direttamente `elaborazioniInCorso.value = response.data.elaborazioni` (nessun riordino lato client).
- righe 69-77: il `q-table` esterno usa `:rows="elaborazioniInCorso" :columns="columnsGruppiElaborazioniInCorso" row-key="row-id"` **senza** prop `sort-by`/pagination iniziale → Quasar visualizza le righe nell'ordine esatto dell'array ricevuto dal backend.
- riga 443-451: `columnsGruppiElaborazioniInCorso` ha la colonna `nomeGruppo` (`field: 'nome_lavoro'`) con `sortable: true`: l'utente può comunque ordinare manualmente per nome, ma l'ordine di default resta quello dell'array.

Conferma provenienza di `id_elaborazione`: in `crea_elaborazione.php` (righe 125-127) ogni nuova elaborazione viene inserita nella tabella `elaborazioni` e il suo id recuperato con `$db->get_ultimo_id_inserito()` → è una PK auto-incrementante, quindi **l'id più alto corrisponde sempre all'elaborazione creata più di recente** (documentato anche in `SINTESI_PROGETTO.md`, sezione "Struttura Database" → tabella `elaborazioni`, righe 330-336).

### Key Decisions
- **Riordino lato PHP dopo il raggruppamento (scelto)**: si lascia invariata la query SQL e il ciclo di raggruppamento esistente (nessun rischio di romperli), e si aggiunge un `usort()` sull'array `$dati_out` già costruito, usando come chiave il massimo `id_elaborazione` tra i dettagli di ciascun lavoro. Pro: modifica minima, isolata, non tocca la vista condivisa `elab_join` usata da molti altri endpoint. Contro: un piccolo overhead O(n log n) su un array comunque piccolo (elaborazioni attive, tipicamente poche decine).
- **Alternativa scartata — riordino via SQL con subquery correlata**: modificare la query in qualcosa come `ORDER BY (SELECT MAX(id_elaborazione) FROM elab_join sub WHERE sub.id_lavoro = ej.id_lavoro) DESC, id_lavoro, id_elaborazione`. Scartata perché più invasiva (tocca la query condivisa), meno leggibile, e il beneficio prestazionale è trascurabile per il volume di dati in gioco.
- **Nessuna modifica al frontend**: dato che `IndexPage.vue` non applica un `sort-by` di default al `q-table` esterno, l'ordine dell'array restituito dal backend è già quello mostrato a video. Si documenta questa verifica nel piano invece di introdurre codice frontend non necessario.

### Proposed Changes (codice da inserire nel documento di piano)
Modifica a `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php`: aggiungere il blocco seguente **subito dopo la chiusura del foreach di raggruppamento (dopo l'attuale riga 61) e prima del commento `// Restituisci una risposta di successo` (attuale riga 63)**:

```php
// --- Riordino: il lavoro con l'elaborazione più recente (id_elaborazione più alto) va in cima ---
// id_elaborazione è la PK auto-incrementante di "elaborazioni" (vedi crea_elaborazione.php,
// get_ultimo_id_inserito()): il valore più alto = elaborazione creata per ultima.
// Il ciclo di raggruppamento sopra resta invariato: riordiniamo solo il risultato finale.
usort($dati_out, function ($lavoroA, $lavoroB) {
    $ultimaElabA = max(array_column($lavoroA["dettagli"], "id_elaborazione"));
    $ultimaElabB = max(array_column($lavoroB["dettagli"], "id_elaborazione"));
    return $ultimaElabB <=> $ultimaElabA; // decrescente: id più alto (più recente) per primo
});

// Dopo il riordino i "row-id" assegnati durante il raggruppamento non sono più progressivi:
// li ricalcoliamo per mantenerli univoci (usati come row-key dalle q-table di Quasar).
$indice_riga = 0;
foreach ($dati_out as &$lavoro) {
    $lavoro["row-id"] = $indice_riga++;
    foreach ($lavoro["dettagli"] as &$elaborazione) {
        $elaborazione["row-id"] = $indice_riga++;
    }
}
unset($lavoro, $elaborazione);
// --- fine blocco riordino ---
```

Il resto del file (query SQL, ciclo di raggruppamento, blocco try/catch, risposta JSON) resta identico.

### Frontend — verifica (nessun codice da modificare)
Da includere nel documento come sezione di analisi:
- `src/pages/IndexPage.vue` righe 69-77 (q-table esterno) e 516-533 (`prelevaElaborazioniInCorso`): l'array `elaborazioniInCorso` viene assegnato così come arriva dal backend, senza ulteriore ordinamento o `sort-by` iniziale → il riordino lato backend si riflette automaticamente in cima alla tabella.
- Nota su `columnsGruppiElaborazioniInCorso[0].sortable = true` (riga 448): l'utente può comunque cliccare l'header "Nome" per ordinare alfabeticamente; è un comportamento interattivo normale di Quasar e non contrasta con l'ordinamento di default richiesto.
- Enhancement opzionale (da citare come nota, non da implementare ora): eventualmente evidenziare il lavoro più recente con un badge/icona nella riga (richiederebbe una piccola modifica al template del `q-td` in `IndexPage.vue`), ma non è richiesto dai requisiti.

### File Structure
- `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_IN_CORSO.md` — nuovo file, con la struttura usata dagli altri piani (`documenti/03_...md`, `documenti/13_...md`): titolo, problema rilevato, obiettivo, modifiche proposte con codice, note tecniche.
- `documenti/SINTESI_PROGETTO.md` — aggiornamento della sezione "API Backend" relativa a `preleva_elaborazioni_in_corso.php` (righe 492-496 attuali).

### Risks
- Se in futuro venissero ammesse elaborazioni con `id_elaborazione` non strettamente crescente nel tempo (es. import massivo con id manuali), il criterio "id più alto = più recente" andrebbe rivisto; ad oggi non risulta questo caso (id sempre generato da auto-increment).
- `usort` con comparatore che ricalcola `max()` ad ogni confronto ha costo leggermente maggiore di un pre-calcolo delle chiavi (es. `array_multisort` con array di chiavi precalcolate); dato il volume atteso di lavori attivi, l'impatto è trascurabile — da citare come nota tecnica nel documento, non necessariamente da ottimizzare subito.

# Delivery Steps

###   Step 1: Creare il documento documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_IN_CORSO.md con l'analisi e il codice backend proposto
Il nuovo file markdown esiste in `documenti/` e contiene l'intera analisi del problema e la soluzione backend pronta da applicare in una fase futura.
- Creare `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_IN_CORSO.md` seguendo lo stile degli altri piani (`documenti/03_PIANO_AZIONE_MULTI_ELABORAZIONE.md`, `documenti/13_PIANO_AZIONE_SUGGERIMENTI_MULTI_PAROLA.md`): titolo, problema rilevato, obiettivo, modifiche proposte.
- Riportare la spiegazione del problema attuale: la query di `preleva_elaborazioni_in_corso.php` ordina per `id_lavoro, id_elaborazione` e il ciclo PHP raggruppa per lavoro, producendo un array ordinato per lavoro e non per recency.
- Inserire il blocco di codice PHP completo da aggiungere dopo il ciclo di raggruppamento (uso di `usort` sul massimo `id_elaborazione` per lavoro, decrescente, con ricalcolo dei `row-id`), con riferimento preciso alle righe del file esistente dove va inserito.
- Documentare nel piano perché non si è scelta la modifica via SQL (subquery correlata su `elab_join`) e perché si preferisce il riordino in PHP.
- Non modificare realmente `php-docker-boilerplate/src/preleva_elaborazioni_in_corso.php`: il codice va solo scritto nel documento.

###   Step 2: Aggiungere al piano 14 la sezione di verifica frontend e aggiornare SINTESI_PROGETTO.md
Il documento di piano include la sezione di analisi frontend e `SINTESI_PROGETTO.md` descrive la nuova logica di ordinamento dell'endpoint.
- Aggiungere al file `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_IN_CORSO.md` una sezione "Frontend" che spiega, con riferimenti a righe precise di `src/pages/IndexPage.vue` (69-77, 443-451, 516-533), perché non serve alcuna modifica al codice Vue affinché l'ordine restituito dal backend si rifletta in cima alla tabella "Elaborazioni in corso".
- Citare nella stessa sezione l'eventuale enhancement opzionale (badge/icona per il lavoro più recente) come nota, senza implementarlo.
- Aggiornare `documenti/SINTESI_PROGETTO.md` nella sezione dedicata a `preleva_elaborazioni_in_corso.php` (attualmente righe 492-496) aggiungendo la descrizione della nuova logica di ordinamento (raggruppamento per lavoro seguito da riordino basato sull'`id_elaborazione` più alto per mostrare per primo il lavoro con l'elaborazione più recente).
- Non applicare modifiche a nessun file `.php` o `.vue` del progetto: solo ai due file markdown in `documenti/`.