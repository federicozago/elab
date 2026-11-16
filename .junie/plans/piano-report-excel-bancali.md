---
sessionId: session-260710-111723-1pj1
---

# Requirements

### Overview & Goals
L'utente chiede **solo** la produzione di un documento di piano d'azione (markdown) nella cartella `documenti/`, che descriva come realizzare un nuovo endpoint PHP per generare un report Excel dei bancali, partendo dalla query SQL fornita. Nessun file di codice del progetto (`.php`, `.vue`, ecc.) deve essere modificato: l'unico artefatto da produrre è il documento di piano, secondo lo stesso formato già usato per i piani precedenti (`documenti/01_...md` … `documenti/14_...md`).

> **Nota sul processo (perché `documenti/15_PIANO_AZIONE_REPORT_BANCALI_EXCEL.md` non esiste ancora):** questo documento che stai leggendo è il **Piano** prodotto dalla fase di pianificazione (agente di sola pianificazione, senza permessi di scrittura sul progetto). La creazione fisica del file `documenti/15_PIANO_AZIONE_REPORT_BANCALI_EXCEL.md`, con il contenuto descritto nelle sezioni seguenti e negli step di delivery, avviene nella fase di **implementazione** successiva, quando questo piano viene approvato ed eseguito (un agente con permessi di scrittura crea davvero il file in `documenti/`). Il piano stesso non modifica alcun file del progetto: definisce solo cosa scrivere e dove.

### Scope
**In Scope**
- Creare `documenti/15_PIANO_AZIONE_REPORT_BANCALI_EXCEL.md` con: analisi del problema, analisi tecnica del progetto esistente, progettazione del nuovo endpoint PHP, **codice PHP reale e completo** (query + generazione Excel, non pseudocodice), struttura del foglio Excel (intestazioni, bordi neri su ogni cella, riga dei totali), integrazione con il meccanismo `lanciaAzione` del frontend, checklist di test manuali, riepilogo modifiche.
- Il piano deve riutilizzare i pattern già presenti nel backend (`php-docker-boilerplate/src/genera_etichette_bancale.php`, `aggiungi_ordinamento_bancali_diretti.php`) per input, CORS, gestione errori e download del file generato.
- Il piano deve usare direttamente `PhpOffice\PhpSpreadsheet` (dipendenza sottostante della libreria `cassetta_attrezzi`, come documentato in `SINTESI_PROGETTO.md`) per costruire il file, con bordi neri su tutte le celle della tabella e riga dei totali in fondo.
- Il piano deve documentare come questa nuova azione si integra nel meccanismo generico `lanciaAzione()` di `src/pages/IndexPage.vue` (riga 632), già usato per lanciare tutte le altre azioni sulle elaborazioni.

**Out of Scope**
- Implementazione/modifica reale di qualsiasi file del progetto (`.php`, `.vue`, `temp/elab.php`): tutto il codice richiesto (endpoint + adattamento `lanciaAzione` + voce di configurazione azione) viene scritto **per intero, reale, non pseudocodice**, ma solo dentro il documento di piano `documenti/15_...md`.
- Modifica delle variabili globali remote (il file `temp/elab.php` nel repo è solo un esempio locale; la configurazione reale delle azioni vive su un server remoto, come già documentato in `documenti/PIANO_AZIONE_RINOMINA_VARIABILE_ELABORAZIONI.md`): il piano documenta la voce da aggiungere, ma non può e non deve modificarla.

### Functional Requirements (del futuro endpoint, descritte nel documento)
1. L'endpoint riceve in input `id_elaborazione` (pattern coerente con gli altri endpoint "bancale").
2. Deriva il nome della tabella `ordinati_{tipo_spedizione}_{nome_base_dati}` dai dati dell'elaborazione (vista `elab_join`).
3. Esegue la query CTE fornita dall'utente (WITH scatole ... ) sulla tabella derivata.
4. Genera un file `.xlsx` con:
   - riga di intestazione: ID Bancale, Bacino, N. Plichi, N. Pezzi, Peso (gr)
   - una riga per ogni combinazione bancale/bacino
   - una riga finale di totali: SUM per N. Plichi, N. Pezzi, Peso; per ID Bancale viene riportato l'**ultimo** idbancale (indica il numero di bancali presenti)
   - **bordo nero su tutte le celle** della tabella (intestazione, dati, totali)
5. Il file generato viene restituito in download al chiamante (stessa logica di `genera_etichette_bancale.php`: file temporaneo, header di download, `readfile`, cancellazione file temporaneo).
6. Gestione errori con risposta JSON `{success:false, message:...}` in caso di eccezione, coerente con tutti gli altri endpoint.
7. L'endpoint viene invocato dal frontend **esattamente come le altre azioni**, tramite la funzione generica `lanciaAzione()` già presente in `src/pages/IndexPage.vue` (definita a riga 632, invocata dal template a riga 331 e da `riesporta()` a riga 609): serve quindi (a) una voce nell'array di configurazione `elaborazioni` (quello letto da `preleva_azioni_elaborazioni.php`, esempio locale in `temp/elab.php`) con `endpoint: "genera_report_bancali.php"` e un output type dedicato (`"xlsx"`), e (b) un piccolo adattamento di `lanciaAzione()` per riconoscere questo nuovo output type e trattarlo come download binario (blob), sullo stesso modello di `isPdf`/`isZip`.

# Technical Design

### Current Implementation (rilevata nel progetto)
- Tutti gli endpoint PHP vivono in `php-docker-boilerplate/src/*.php`, seguono il pattern: header CORS + gestione OPTIONS, `try { ... } catch (\Throwable $e) { json error }`.
- Gli endpoint che operano su un bancale/elaborazione specifica (`aggiungi_ordinamento_bancali_diretti.php`, `genera_etichette_bancale.php`) prendono in input `id_elaborazione`, interrogano `elab_join` per ottenere `tipo_spedizione`, `nome_base_dati`, `nome_lavoro`, `nome_elaborazione`, `id_flusso`, e da qui derivano il nome della tabella ordinati: `"ordinati_{$elaborazione['tipo_spedizione']}_{$elaborazione['nome_base_dati']}"` — esattamente la tabella `ordinati_target_save_the_children` usata nella query dell'utente.
- Il download di file generati (PDF, ZIP) segue il pattern di `genera_etichette_bancale.php` (righe 60-91): cartella temp dedicata (`temp_pdf/`), nome file con `uniqid()`, generazione file, header `Content-Disposition: attachment`, `readfile()`, `unlink()`, `exit`.
- La libreria `cassetta_attrezzi` (non presente nel repo, caricata via vendor/Docker) espone una classe `Excel.php` documentata in `SINTESI_PROGETTO.md` (righe 735-743) per **lettura** di file Excel (`apri`, `converti_in_array`, `leggi_riga`), e una classe distinta `Esporta_excel.php` (righe 763-768) per l'esportazione, che cita genericamente "formattazione celle (colori, bordi, font)" ma senza firme di metodi documentate. Entrambe sono basate su **PhpSpreadsheet** (`Libreria sottostante: PhpSpreadsheet`), che è quindi garantita disponibile via `vendor/autoload.php` in ogni endpoint.
- **Meccanismo di invocazione azioni dal frontend** (`src/pages/IndexPage.vue`): il pulsante "Azioni" (dialog `dialogAzioniVisible`) itera `azioni[selectedRowAzioni.tipo_spedizione]` (oggetto caricato in `onMounted` da `POST /preleva_azioni_elaborazioni.php`, che restituisce `$vg["elaborazioni"]`) e per ognuno chiama `lanciaAzione(datiAzione, id_elaborazione, nomeAzione)` (template, riga 331). La struttura di ogni azione, come mostrato nell'esempio locale `temp/elab.php` (righe 12-37), è `"Nome pulsante" => ["endpoint" => "file.php", "output" => "pdf"|"zip", "parametri" => ["d"] (opzionale)]`. La funzione `lanciaAzione` (riga 632) distingue il tipo di risposta così (righe 646-648, 657):
```javascript
  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const config = (isPdf || isZip) ? { responseType: 'blob' } : {}
  ...
      let filename = isZip ? 'esportazione.zip' : 'etichette.pdf'
```
Attualmente gestisce solo `pdf` e `zip`: un nuovo output `xlsx` non verrebbe trattato come blob e il download del file Excel non funzionerebbe senza un piccolo adattamento (vedi Proposed Changes).

### Key Decisions
1. **Nome e input dell'endpoint**: `genera_report_bancali.php`, POST, body JSON `{id_elaborazione}` — coerente con `genera_etichette_bancale.php` e `aggiungi_ordinamento_bancali_diretti.php`.
2. **Derivazione tabella `ordinati_*`**: dedotta da `elab_join` invece che passata come parametro libero, per evitare SQL injection su nomi tabella e per restare coerente con gli endpoint esistenti.
3. **Scrittura Excel — decisione definitiva**: si usa **direttamente `PhpOffice\PhpSpreadsheet`** (`Spreadsheet`, `Writer\Xlsx`, `Style\Border`) invece della classe `Excel`/`Esporta_excel` di cassetta_attrezzi, perché nessuna delle due espone firme di metodi di scrittura/stile verificabili nel repo (libreria caricata solo a runtime via vendor/Docker), mentre PhpSpreadsheet è la dipendenza sottostante garantita e la sua API pubblica è stabile e nota. Questo elimina l'ambiguità: il codice riportato in "Proposed Changes" è reale e completo, non pseudocodice.
4. **Riga totali**: colonna `idbancale` → ultimo valore di `idbancale` incontrato (la query è già ordinata per `idbancale` crescente, quindi corrisponde alla riga finale); colonna `bacino_bancale` → etichetta `TOTALE`; colonne `nplichi`, `npezzi`, `peso_gr` → `SUM` calcolata in PHP durante il ciclo sui risultati.
5. **Bordi neri**: applicati a *tutte* le celle della tabella (intestazione + righe dati + riga totali), non solo al perimetro esterno, tramite un unico `applyFromArray` sul range `A1:E{ultima_riga}`.
6. **Integrazione con `lanciaAzione`**: si introduce un terzo output type `"xlsx"` (accanto a `"pdf"` e `"zip"`), riusando lo stesso meccanismo di download a blob già esistente, con una modifica minima e localizzata a `lanciaAzione()` (righe 646-648 e 657) — nessuna riscrittura della funzione.

### Proposed Changes (contenuto da inserire nel documento di piano)
Il documento conterrà il **codice PHP reale e completo** del nuovo endpoint (non pseudocodice):
```php
<?php
namespace indi\Classes;
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;

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

    // --- calcolo totali ---
    $totale_nplichi = 0;
    $totale_npezzi = 0;
    $totale_peso_gr = 0;
    $ultimo_idbancale = 0;

    foreach ($righe as $riga) {
        $totale_nplichi += (int)$riga['nplichi'];
        $totale_npezzi  += (int)$riga['npezzi'];
        $totale_peso_gr += (int)$riga['peso_gr'];
        $ultimo_idbancale = (int)$riga['idbancale']; // query ordinata per idbancale crescente
    }

    // --- generazione file Excel ---
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Report Bancali');

    $sheet->fromArray(['ID Bancale', 'Bacino', 'N. Plichi', 'N. Pezzi', 'Peso (gr)'], null, 'A1');

    $numero_riga = 2;
    foreach ($righe as $riga) {
        $sheet->fromArray([
            (int)$riga['idbancale'],
            $riga['bacino_bancale'],
            (int)$riga['nplichi'],
            (int)$riga['npezzi'],
            (int)$riga['peso_gr'],
        ], null, "A{$numero_riga}");
        $numero_riga++;
    }

    // riga totali: idbancale => ultimo idbancale (n. bancali), bacino => 'TOTALE', resto => SUM
    $riga_totali = $numero_riga;
    $sheet->fromArray([
        $ultimo_idbancale,
        'TOTALE',
        $totale_nplichi,
        $totale_npezzi,
        $totale_peso_gr,
    ], null, "A{$riga_totali}");

    // bordo nero su OGNI cella della tabella (intestazione + dati + totali)
    $sheet->getStyle("A1:E{$riga_totali}")->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000'],
            ],
        ],
    ]);

    $sheet->getStyle("A1:E1")->getFont()->setBold(true);
    $sheet->getStyle("A{$riga_totali}:E{$riga_totali}")->getFont()->setBold(true);

    foreach (range('A', 'E') as $colonna)
        $sheet->getColumnDimension($colonna)->setAutoSize(true);

    // --- salvataggio su file temporaneo e invio in download ---
    $temp_dir = __DIR__ . "/temp_excel/";
    if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

    $nome_base = "report_bancali_{$nome_elaborazione}_{$elaborazione['id_flusso']}";
    $nome_univoco = $nome_base . "_" . uniqid() . ".xlsx";
    $path_temporanea = $temp_dir . $nome_univoco;

    $writer = new Xlsx($spreadsheet);
    $writer->save($path_temporanea);

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

### Integrazione con `lanciaAzione` (frontend) — codice reale
Per poter lanciare `genera_report_bancali.php` con lo stesso pulsante "Azioni" usato dagli altri endpoint, il documento riporterà anche il diff reale (non applicato) di `src/pages/IndexPage.vue`:

1) Estensione di `lanciaAzione()` (righe 646-648) per riconoscere il nuovo output `xlsx` come blob:
```javascript
// PRIMA (righe 646-648)
  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const config = (isPdf || isZip) ? { responseType: 'blob' } : {}

// DOPO
  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const isExcel = datiAzione.output === 'xlsx'
  const config = (isPdf || isZip || isExcel) ? { responseType: 'blob' } : {}
```

2) Aggiornamento del ramo di download (righe 652 e 657) per includere `isExcel` e il filename di default:
```javascript
// PRIMA (riga 652)
    if (isPdf || isZip) {

// DOPO
    if (isPdf || isZip || isExcel) {
```
```javascript
// PRIMA (riga 657)
      let filename = isZip ? 'esportazione.zip' : 'etichette.pdf'

// DOPO
      let filename = isZip ? 'esportazione.zip' : (isExcel ? 'report_bancali.xlsx' : 'etichette.pdf')
```

3) Voce da aggiungere nella configurazione delle azioni (`"elaborazioni" => ["target" => [...]]`), come mostrato nell'esempio locale `temp/elab.php` (righe 12-26) — da replicare anche nella configurazione remota reale, secondo lo stesso schema già usato per `"Chiudi elaborazione"`:
```php
"Report bancali" => [
    "endpoint" => "genera_report_bancali.php",
    "output" => "xlsx"
],
```

### Data Models / Contracts
- **Input**: `{ "id_elaborazione": <int> }`
- **Output (successo)**: file binario `.xlsx` in streaming (`Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, `Content-Disposition: attachment; filename="report_bancali_<nome_elaborazione>.xlsx"`)
- **Output (errore)**: `{ success: false, message: string }`
- **Struttura foglio Excel**:
 ID Bancale | Bacino | N. Plichi | N. Pezzi | Peso (gr) |
---|---|---|---|---|
 1 | ANCONA | 280 | 12379 | 396128 |
 ... | ... | ... | ... | ... |
 **11** (ultimo idbancale) | TOTALE | SUM | SUM | SUM |

### File Structure
- Unico file reale prodotto da questa attività: `documenti/15_PIANO_AZIONE_REPORT_BANCALI_EXCEL.md` (nuovo).
- Nel documento si riporterà per intero, come codice reale non applicato, il futuro file `php-docker-boilerplate/src/genera_report_bancali.php`, il diff su `src/pages/IndexPage.vue` (righe 646-648, 652, 657) e la voce di configurazione da aggiungere in `temp/elab.php` / variabili globali remote.

### Risks
- La configurazione reale delle azioni (`"elaborazioni"`) vive su un server remoto (`temp/elab.php` nel repo è solo un esempio locale, come già chiarito in `documenti/PIANO_AZIONE_RINOMINA_VARIABILE_ELABORAZIONI.md`): senza aggiungere la voce `"Report bancali"` lato remoto, il pulsante non comparirà mai nel dialog "Azioni", anche se sia backend che frontend fossero già implementati.
- Il nome tabella `ordinati_*` viene interpolato nella query (necessario perché è un identificatore, non parametrizzabile con prepared statement): il piano raccomanda di derivarlo solo da dati di configurazione (`elab_join`), mai da input utente diretto, per evitare SQL injection — pattern già usato negli endpoint esistenti.
- Se in futuro la libreria cassetta_attrezzi dovesse esporre nel vendor reale metodi di scrittura Excel con bordi (es. in `Esporta_excel.php`), si potrebbe eventualmente sostituire la creazione manuale con quella classe; la scelta di PhpSpreadsheet diretto resta comunque valida e sicura indipendentemente da questo.

# Delivery Steps

###   Step 1: Redigere analisi tecnica e progettazione query nel documento di piano
Il file `documenti/15_PIANO_AZIONE_REPORT_BANCALI_EXCEL.md` viene creato con intestazione, problema/obiettivo e analisi tecnica della query e della derivazione tabella.
- Creare il nuovo file markdown seguendo il formato dei piani esistenti (titolo `### PIANO DI AZIONE 15: ...`, sezione Problema/Obiettivo).
- Documentare l'analisi tecnica: pattern degli endpoint esistenti (`genera_etichette_bancale.php`, `aggiungi_ordinamento_bancali_diretti.php`), derivazione della tabella `ordinati_{tipo_spedizione}_{nome_base_dati}` da `elab_join` tramite `id_elaborazione`.
- Riportare la query SQL CTE fornita dall'utente, integrata con il nome tabella dinamico (interpolazione sicura basata su dati di configurazione, non input utente).
- Specificare il contratto di input/output dell'endpoint `genera_report_bancali.php` (JSON `{id_elaborazione}` in ingresso, file `.xlsx` in download in uscita).

###   Step 2: Redigere nel documento il codice PHP reale e completo del foglio Excel (bordi e riga totali)
Il documento contiene il codice PHP reale (non pseudocodice) di generazione del foglio Excel, inclusi bordi neri su ogni cella e riga totali.
- Descrivere la struttura del foglio: riga intestazione (ID Bancale, Bacino, N. Plichi, N. Pezzi, Peso gr) e righe dati derivate dal resultset della query.
- Riportare il codice PHP reale di calcolo dei totali durante il ciclo sui risultati: SUM su `nplichi`, `npezzi`, `peso_gr`; per `idbancale` viene tenuto l'ultimo valore incontrato (numero di bancali presenti); etichetta `TOTALE` nella cella `bacino_bancale` della riga finale.
- Riportare il codice PHP reale di creazione del foglio con `PhpOffice\PhpSpreadsheet\Spreadsheet`, scrittura celle con `fromArray()`, applicazione bordo nero (`Style\Border::BORDER_THIN`) su tutto il range `A1:E{ultima_riga}` in un'unica chiamata `applyFromArray()`, grassetto su intestazione e riga totali, larghezza colonne automatica.
- Riportare il codice PHP reale di salvataggio su `temp_excel/` tramite `Writer\Xlsx`.

###   Step 3: Redigere nel documento l'integrazione con `lanciaAzione`, il download, la gestione errori e la checklist di test
Il documento viene completato con l'integrazione frontend, il flusso di risposta HTTP, la gestione errori e una checklist di test manuali, e salvato definitivamente in `documenti/`.
- Riportare il codice PHP reale di invio del file al client: cartella temporanea `temp_excel/`, header di download (`Content-Type` xlsx, `Content-Disposition attachment`), `readfile()` e cancellazione (`unlink`) del file temporaneo, seguendo il pattern di `genera_etichette_bancale.php`.
- Riportare il codice reale (diff, non applicato) dell'estensione di `lanciaAzione()` in `src/pages/IndexPage.vue` (righe 646-648, 652, 657) per supportare l'output `xlsx` come blob, e la voce da aggiungere nella configurazione `elaborazioni` (esempio `temp/elab.php`, righe 12-26) per registrare l'azione "Report bancali".
- Riportare il codice PHP reale di gestione degli errori con blocco `try/catch (\Throwable $e)` e risposta JSON `{success:false, message}` coerente con gli altri endpoint.
- Redigere la checklist di test manuali (es. verifica presenza bordi su ogni cella, verifica riga totali con SUM corrette e ultimo idbancale, verifica download corretto del file dal pulsante "Azioni" tramite `lanciaAzione`, verifica gestione errore per `id_elaborazione` inesistente).
- Aggiungere la sezione di Riepilogo Modifiche e la Nota Finale che chiarisce che nessun file di codice reale del progetto è stato creato o modificato, solo il documento di piano.