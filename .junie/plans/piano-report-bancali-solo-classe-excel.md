---
sessionId: session-260713-124602-1g2z
---

# Requirements

### Overview & Goals
L'utente ha ribadito che la richiesta va applicata **al file del piano** `documenti/15_piano_azione_report_bancali.md`, non solo discussa in chat: il codice PHP dell'endpoint `genera_report_bancali.php` descritto nel piano deve usare **esclusivamente** le funzioni della classe `Excel` di `cassetta_attrezzi` confermate dall'utente — `apri()` (crea/apre il file), `scrivi_array()` (scrive un array con bordo nero automatico), `scrivi()` (scrive un singolo testo in una cella con bordo nero automatico), `chiudi()` (salva e chiude il file) — eliminando ogni chiamata diretta a `PhpOffice\PhpSpreadsheet` (`getActiveSheet()`, `fromArray()`, `getStyle()->applyFromArray()`, `getFont()->setBold()`, `getColumnDimension()->setAutoSize()`, `setTitle()`, `Writer\Xlsx`, `Style\Border`).
L'utente aveva inoltre chiesto esplicitamente: *"ti manca qualche cosa?"* — le informazioni mancanti/le funzionalità non più ottenibili con questa API ridotta restano quindi individuate ed esplicitate nel piano (sezione dedicata), invece di lasciarle implicite.
Nessun file di codice reale del progetto viene toccato: si modifica solo il documento di piano `documenti/15_piano_azione_report_bancali.md`.

### Scope
**In Scope**
- Riscrivere in `documenti/15_piano_azione_report_bancali.md` il blocco di codice PHP reale dell'endpoint `genera_report_bancali.php` (sezione "Proposed Changes") usando solo `new Excel($log)`, `->apri(...)`, `->scrivi_array(...)`, `->chiudi()` (e opzionalmente `->scrivi(...)` se necessario) per generare il file Excel.
- Rimuovere dal piano ogni riferimento/import a `PhpOffice\PhpSpreadsheet\Writer\Xlsx` e `PhpOffice\PhpSpreadsheet\Style\Border`, e ogni chiamata a metodi propri dell'oggetto `Spreadsheet`/`Worksheet` (`getActiveSheet`, `fromArray`, `setTitle`, `getStyle`, `getFont`, `getColumnDimension`).
- Aggiornare le sezioni narrative del piano (Scope, Current Implementation, Key Decisions, Risks, Step 2 dei Delivery Steps) affinché descrivano correttamente le 4 funzioni disponibili e il nuovo flusso di scrittura.
- Aggiungere nel piano una sezione esplicita "Assunzioni e punti da verificare sulla libreria Excel" che risponda alla domanda dell'utente: cosa NON è coperto da queste 4 funzioni (es. grassetto, larghezza automatica colonne, titolo del foglio) e quali firme esatte dei metodi restano da confermare in fase di implementazione (parametro di `apri()` per creare vs leggere, struttura dati richiesta da `scrivi_array()`, formato del riferimento cella per `scrivi()`).

**Out of Scope**
- Qualsiasi modifica a file di codice reale del progetto (resta tutto dentro il documento di piano).
- Verifica dell'effettiva libreria `cassetta_attrezzi` (non presente nel repo): si può solo documentare l'assunzione, non validarla staticamente.

### Functional Requirements (del contenuto del documento aggiornato)
1. Il blocco PHP in "Proposed Changes" deve istanziare `$spreadsheet = new Excel($log)`, chiamare `apri()` per creare il file al path temporaneo, `scrivi_array()` per scrivere intestazione + righe dati + riga totali in un'unica chiamata, `chiudi()` per salvare/chiudere.
2. Il bordo nero su ogni cella della tabella (intestazione, dati, totali) deve derivare **automaticamente** da `scrivi_array()`/`scrivi()` (comportamento dichiarato dall'utente), non da uno stile applicato manualmente: va quindi rimossa ogni menzione di `applyFromArray`/`Border::BORDER_THIN` come passo esplicito.
3. La riga totali (idbancale=ultimo valore, bacino='TOTALE', SUM su nplichi/npezzi/peso_gr) deve essere costruita come ultimo elemento dello stesso array associativo passato a `scrivi_array()`, per garantire la stessa struttura di colonne e un'unica chiamata di scrittura.
4. Il documento deve esplicitare che, con solo queste 4 funzioni, **non sono più ottenibili**: grassetto su intestazione/totali, larghezza automatica delle colonne, titolo del foglio (`setTitle`) — funzionalità presenti nella versione precedente del piano basata su PhpSpreadsheet diretto, ma non richieste dai requisiti funzionali originari (che chiedevano solo bordo nero + valori corretti).
5. Il salvataggio su disco e l'invio in download (`readfile`/`unlink`) restano invariati, ma senza più `Writer\Xlsx` (il salvataggio è ora responsabilità di `chiudi()`).

# Technical Design

### Current Implementation (nel piano, da correggere)
Attualmente (dopo la modifica precedente) il piano istanzia `new Excel($log)` ma poi continua a chiamare l'API di `PhpOffice\PhpSpreadsheet\Spreadsheet` (`getActiveSheet()`, `fromArray()`, `setTitle()`, `getStyle()->applyFromArray()` con `Style\Border::BORDER_THIN`, `getFont()->setBold()`, `getColumnDimension()->setAutoSize()`) e salva con `new Xlsx($spreadsheet)` — un'assunzione (documentata come tale nei Risks) che `Excel` estenda l'intera API pubblica di `Spreadsheet`. L'utente ha ora fornito l'API reale e limitata della classe: `apri()`, `scrivi_array()`, `scrivi()`, `chiudi()`. Questa assunzione va quindi rimossa e il codice riscritto di conseguenza.

### Key Decisions
1. **Uso esclusivo dell'API `Excel`**: si eliminano `getActiveSheet/fromArray/setTitle/getStyle/getFont/getColumnDimension` e gli `use` verso `PhpOffice\PhpSpreadsheet\Writer\Xlsx` e `PhpOffice\PhpSpreadsheet\Style\Border`. Il file viene creato con `apri()`, popolato con **un'unica chiamata** a `scrivi_array()` e salvato/chiuso con `chiudi()`.
2. **Un'unica chiamata a `scrivi_array()`**: per evitare ambiguità su un eventuale comportamento di sovrascrittura/append di chiamate multiple (non documentato), l'intestazione (dedotta dalle chiavi dell'array associativo), le righe dati e la riga totali vengono costruite in un solo array PHP e passate in una sola chiamata a `scrivi_array()`. Questo è coerente col pattern già visto nel progetto per `Csv::scrivi_array()` (`chiudi_elaborazione.php`, righe 57/66/79: un solo `apri()` → un solo `scrivi_array($dati)` → un solo `chiudi()`).
3. **`scrivi()` non necessaria per questo report**: `scrivi()` (testo su singola cella) resta disponibile per usi futuri (es. un titolo sopra la tabella), ma non è richiesta dai requisiti funzionali attuali, quindi il codice del piano non la usa, per mantenere il flusso più semplice possibile con `scrivi_array()`.
4. **Bordi neri automatici**: poiché l'utente ha dichiarato che sia `scrivi_array()` che `scrivi()` applicano bordo nero automaticamente a ogni cella scritta, il requisito "bordo nero su tutte le celle" è soddisfatto per costruzione, senza alcuno step di styling aggiuntivo nel codice.
5. **Cosa si perde (risposta a "ti manca qualche cosa?")**: con solo queste 4 funzioni non è possibile ottenere: (a) grassetto su intestazione/riga totali, (b) larghezza automatica delle colonne, (c) titolo del foglio di lavoro. Queste tre funzionalità erano presenti nella versione precedente del piano ma **non erano requisiti funzionali originari** (l'unico requisito di stile esplicito era il bordo nero); vengono quindi rimosse dal codice e la loro assenza va segnalata esplicitamente nel documento anziché lasciata implicita.
6. **Punti da verificare in fase di implementazione (assunzioni esplicite)**:
   - `apri($path, $con_intestazione)`: si assume che il secondo parametro booleano (già visto in lettura in `upload_file_per_nuova_base_dati.php` come `$intestazioneSiNo`) indichi in scrittura se la prima riga passata a `scrivi_array()` va trattata come intestazione (derivata dalle chiavi dell'array associativo).
   - `scrivi_array($dati)`: si assume accetti un array di array associativi con le stesse chiavi (stessa struttura usata per `Csv::scrivi_array()` nel progetto), scrivendo automaticamente riga di intestazione + tutte le righe.
   - `scrivi($testo, $cella)`: si assume che `$cella` sia una coordinata stile Excel (es. `"A1"`); non usata nel codice attuale ma documentata come disponibile.
   - Nessuna di queste firme è verificabile staticamente (libreria non presente nel repo, caricata solo via vendor/Docker): il piano lo segnala come rischio da confermare in implementazione.

### Proposed Changes — nuovo blocco di codice PHP reale (sostituisce l'attuale sezione "Proposed Changes")
```php
<?php
namespace indi\Classes;
require 'vendor/autoload.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Expose-Headers: Content-Disposition");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    if (!isset($jsonData["id_elaborazione"]))
        throw new \Exception('Mancano dati in input');

    $vg = new Variabili_globali_import();
    $vg = $vg->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db = new Gestione_db("elab", $log);

    if (!$elaborazione = $db->preleva_da_db("select * from elab_join where id_elaborazione = ?", [$jsonData["id_elaborazione"]]))
        throw new \Exception("Errore durante prelievo elaborazione");
    $elaborazione = $elaborazione[0];

    $nome_elaborazione = "{$elaborazione['nome_lavoro']}_{$elaborazione['nome_elaborazione']}";
    $tabella_ordinati = "ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}";

    $query = "
        WITH scatole AS (
            SELECT
                idbancale,
                idplico,
                CASE
                    WHEN SUM(CASE WHEN bacino = 'ZCARTI' THEN 1 ELSE 0 END) > 0 THEN 'MIX'
                    WHEN COUNT(DISTINCT bacino) = 1
                        THEN MAX(bacino)
                    ELSE 'MIX'
                END AS bacino_bancale
            FROM `$tabella_ordinati`
            WHERE idbancale IS NOT NULL
            GROUP BY
                idbancale,
                idplico
        )
        SELECT
            s.idbancale,
            s.bacino_bancale,
            COUNT(DISTINCT s.idplico) AS nplichi,
            COUNT(*) AS npezzi,
            SUM(t.peso_plico) AS peso_gr
        FROM scatole s
        JOIN `$tabella_ordinati` t
            ON t.idbancale = s.idbancale
            AND t.idplico = s.idplico
        GROUP BY
            s.idbancale,
            s.bacino_bancale
        ORDER BY
            s.idbancale,
            s.bacino_bancale
    ";

    $righe = $db->preleva_da_db($query, [], false);
    if ($righe === false)
        throw new \Exception("Errore durante l'esecuzione della query");
    if (count($righe) === 0)
        throw new \Exception("Nessun bancale trovato per questa elaborazione");

    // --- calcolo totali e costruzione dell'array da scrivere in un'unica chiamata ---
    $totale_nplichi = 0;
    $totale_npezzi = 0;
    $totale_peso_gr = 0;
    $ultimo_idbancale = 0;

    $dati_excel = [];
    foreach ($righe as $riga) {
        $totale_nplichi += (int)$riga['nplichi'];
        $totale_npezzi  += (int)$riga['npezzi'];
        $totale_peso_gr += (int)$riga['peso_gr'];
        $ultimo_idbancale = (int)$riga['idbancale']; // query ordinata per idbancale crescente

        $dati_excel[] = [
            'ID Bancale' => (int)$riga['idbancale'],
            'Bacino'     => $riga['bacino_bancale'],
            'N. Plichi'  => (int)$riga['nplichi'],
            'N. Pezzi'   => (int)$riga['npezzi'],
            'Peso (gr)'  => (int)$riga['peso_gr'],
        ];
    }

    // riga totali: stessa struttura di colonne dell'array, appesa come ultimo elemento
    $dati_excel[] = [
        'ID Bancale' => $ultimo_idbancale,
        'Bacino'     => 'TOTALE',
        'N. Plichi'  => $totale_nplichi,
        'N. Pezzi'   => $totale_npezzi,
        'Peso (gr)'  => $totale_peso_gr,
    ];

    // --- generazione file Excel usando ESCLUSIVAMENTE la classe Excel di cassetta_attrezzi ---
    $temp_dir = __DIR__ . "/temp_excel/";
    if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

    $nome_base = "report_bancali_{$nome_elaborazione}_{$elaborazione['id_flusso']}";
    $nome_univoco = $nome_base . "_" . uniqid() . ".xlsx";
    $path_temporanea = $temp_dir . $nome_univoco;

    $spreadsheet = new Excel($log);
    if (!$spreadsheet->apri($path_temporanea, true)) // true = la prima riga scritta è intestazione
        throw new \Exception("Errore durante la creazione del file Excel");

    // scrivi_array scrive intestazione (dalle chiavi) + righe dati + riga totali, con bordo nero automatico su ogni cella
    if (!$spreadsheet->scrivi_array($dati_excel))
        throw new \Exception("Errore durante la scrittura dei dati nel file Excel");

    if (!$spreadsheet->chiudi())
        throw new \Exception("Errore durante il salvataggio/chiusura del file Excel");

    // --- invio del file al client ---
    if (file_exists($path_temporanea)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nome_base . '.xlsx"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path_temporanea));

        if (ob_get_length()) ob_clean();
        flush();

        readfile($path_temporanea);
        unlink($path_temporanea);
        exit;
    } else {
        throw new \Exception("File Excel non generato");
    }
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
```
Nota: nessuna `use` verso `PhpOffice\PhpSpreadsheet\*` resta nel file: la classe `Excel` (namespace `indi\Classes`, stesso namespace dell'endpoint) incapsula completamente lettura/scrittura/salvataggio.

### Sezione da aggiungere: "Assunzioni e punti da verificare sulla libreria Excel"
Nuova sottosezione (dopo Risks) che risponde esplicitamente a "ti manca qualche cosa?":
- Non verificabile staticamente (libreria non nel repo): firma esatta di `apri()` in scrittura (nome/ordine parametri, valore di ritorno in caso di percorso non scrivibile), struttura dati esatta richiesta da `scrivi_array()` (array di array associativi come ipotizzato, per analogia con `Csv::scrivi_array()`), formato del riferimento cella accettato da `scrivi()`.
- Funzionalità **non ottenibili** con solo `apri/scrivi_array/scrivi/chiudi`: grassetto, larghezza automatica colonne, titolo foglio — rimosse dal codice del piano perché non richieste dai requisiti funzionali originari (solo bordo nero era richiesto, e quello è garantito automaticamente).
- Se in fase di implementazione emergesse che `scrivi_array()` non applica intestazione automatica dalle chiavi, la riga di intestazione andrà scritta con chiamate a `scrivi()` cella per cella prima della riga dati — questa alternativa va annotata come piano B nel documento.

### Risks (aggiornamento)
- Sostituire il rischio esistente ("si assume che Excel estenda l'API pubblica di Spreadsheet") con: "si assume che `Excel::scrivi_array()` accetti un array di righe associative applicando bordo nero e intestazione automatica, per analogia con `Csv::scrivi_array()` già usato nel progetto (`chiudi_elaborazione.php`); se la firma reale differisce, va adattata solo la costruzione di `$dati_excel` e l'eventuale fallback a chiamate multiple di `scrivi()`."
- Rischio invariato sull'interpolazione del nome tabella `ordinati_*` (mitigato derivandolo da `elab_join`, non da input utente).
- Rischio invariato sulla configurazione remota delle azioni (`temp/elab.php` è solo un esempio locale).

# Delivery Steps

###   Step 1: Aggiornare le sezioni narrative del piano per l'API reale della classe Excel
documenti/15_piano_azione_report_bancali.md descrive correttamente le 4 funzioni disponibili (apri/scrivi_array/scrivi/chiudi) invece di assumere l'API completa di PhpSpreadsheet.
- Aggiornare `Scope` (In Scope) per menzionare esplicitamente `apri()`, `scrivi_array()`, `scrivi()`, `chiudi()` come uniche funzioni usate.
- Riscrivere `Current Implementation` per rimuovere l'assunzione che `Excel` estenda l'API pubblica di `Spreadsheet` e descrivere invece il pattern reale `apri()->scrivi_array()->chiudi()` già visto per `Csv` in `chiudi_elaborazione.php`.
- Riscrivere `Key Decisions` punto 3 (e aggiungere un nuovo punto) per motivare l'uso esclusivo di queste 4 funzioni, l'unica chiamata a `scrivi_array()` con header+dati+totali in un solo array, e l'automaticità del bordo nero.
- Aggiornare `Risks` sostituendo il rischio sull'assunzione "Excel estende Spreadsheet" con il nuovo rischio sulla struttura dati attesa da `scrivi_array()`/`scrivi()`.

###   Step 2: Riscrivere il blocco di codice PHP e aggiungere la sezione sulle assunzioni mancanti
Il blocco `Proposed Changes` del documento contiene il nuovo codice PHP reale che usa solo `new Excel($log)` + `apri()`/`scrivi_array()`/`chiudi()`, senza alcun riferimento a PhpOffice\PhpSpreadsheet.
- Sostituire l'intero blocco di codice PHP in `Proposed Changes` con la versione che rimuove `use PhpOffice\PhpSpreadsheet\Writer\Xlsx;`, `use PhpOffice\PhpSpreadsheet\Style\Border;`, `getActiveSheet()`, `fromArray()`, `setTitle()`, `getStyle()->applyFromArray()`, `getFont()->setBold()`, `getColumnDimension()->setAutoSize()`, `new Xlsx($spreadsheet)`.
- Introdurre la costruzione dell'array `$dati_excel` (righe dati + riga totali con le stesse chiavi) e le tre chiamate `apri($path_temporanea, true)` → `scrivi_array($dati_excel)` → `chiudi()`.
- Aggiungere nel documento la nuova sottosezione "Assunzioni e punti da verificare sulla libreria Excel" (dopo `Risks`) che elenca esplicitamente cosa non è verificabile staticamente e quali funzionalità (grassetto, autosize colonne, titolo foglio) non sono più ottenibili con questa API ridotta.
- Aggiornare la descrizione dello `Step 2` nei `Delivery Steps` del documento affinché rifletta il nuovo flusso `apri/scrivi_array/chiudi` al posto dei precedenti riferimenti a `getStyle()`/`Border::BORDER_THIN`/`Writer\Xlsx`.