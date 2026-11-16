# Piano d'azione 18 - Datamatrix postale sul target / no profit

## Obiettivo
Con il flag **Stampa datamatrix** della configurazione, l'ordinamento del target (e del no profit, che ne è un alias) deve
calcolare per ogni record il datamatrix standard unico Poste (72 caratteri, guida "specifiche tecniche codice datamatrix
standard unico per tracciatura grandi clienti", tabelle 1 e 3) e salvarlo nella tabella ordinati, colonna `barcode`.
La generazione del file DU è un passaggio successivo (non trattato qui).

## Cosa è stato fatto

### Form configurazioni (`src/components/creazione_configurazione.vue`)
- **Descrittivo gamma**: non più modificabile, calcolato dal tipo di spedizione: V time, E no profit, T target, B massiva
  (e per gli altri prodotti bulk mail). Il backend lo reimposta comunque (`crea_configurazione.php`, `aggiorna_configurazione.php`).
- **Tipo prodotto postale**: resta libero, cambiato il suggerimento (W massiva, T time kpm, Q no profit da verificare, creative W,
  card U, basic S; guida Poste tabella 3 pagina 7).
- **Tabella codici figli** (nuovo, obbligatorio con il datamatrix): menu a tendina con le tabelle `figli_*` di `import_file` ed `elab`
  (endpoint `preleva_tabelle_figli.php`). Si salva `schema.tabella` perché il programma di ordinamento lavora sullo schema `elab`.
- **Autorizzazione postale** e **Omologa**: obbligatori quando si stampa il datamatrix (controllo anche lato backend).
- Suggerimento di **Codice servizio accessorio**: aggiunto "Vedere la guida di Poste per la generazione della DU, tabella 2".

### Libreria `elaborazione_postale` (repo `import_file\Elaborazione_postale` e copia in `vendor` di elab)
- `Elaborazione_postale::imposta_barcode_datamatrix($ordina_per = null)`: parametro opzionale; senza parametro il comportamento è
  identico a prima (massiva, time...). Con `"progr"` il progressivo oggetto di 6 cifre segue `progr`.
- `Elaborazione_postale_target::ordina()`: se `stampa_datamatrix`, a fine ordinamento `imposta_datamatrix_ordinati()`:
  1. controlla lunghezza e caratteri dei dati fissi (gamma 1, sap 8, codice cliente 3, classe 1, tipo prodotto 1, cap mittente 5,
     stampatore 2, ultimi 6 caratteri dell'omologa), tutte cifre/lettere maiuscole;
  2. preleva **un codice figlio per elaborazione** da `tabella_codici_figli` (consumato; se l'ordinamento fallisce la transazione lo restituisce);
  3. calcola e salva con **un solo UPDATE** `barcode` e `codice_figlio` dei record dell'elaborazione (la stessa espressione sql calcola e salva);
  4. verifica che ogni record abbia un barcode di 72 caratteri, altrimenti l'ordinamento va in errore indicando alcuni id.
- `verifica_tabella_ordinati()`: le nuove tabelle ordinati hanno le colonne `barcode varchar(72)`, `codice_figlio varchar(5)`, `codice_padre varchar(20)`
  e l'**indice univoco su `barcode`**; la vista `{elaborazione}_elaborato` le include. Le tabelle già esistenti **non** vengono modificate dal programma
  (nessuna migrazione automatica): vanno aggiornate a mano (vedi "Da eseguire in produzione") **prima** di mettere in produzione la nuova versione,
  altrimenti la vista `_elaborato` non si può ricreare e ogni "Ordina" su quelle tabelle va in errore.
- Il flag si chiama `stampa_datamatrix` anche nella classe (prima `con_datamatrix`), come la colonna delle configurazioni: nessun rimappaggio.
  **Nessuna retrocompatibilità**: i programmi batch che nelle variabili globali usano `con_datamatrix` (es. `global_power_racc`, `reca_racc`, contest) devono passare
  a `stampa_datamatrix` prima di aggiornare la libreria (`composer update`); quelli che non vengono aggiornati continuano a usare la versione vecchia.
- Etichetta scatola: con `stampa_datamatrix` compare "LINEA EVOLUTION" (grassetto 10 pt, a destra sotto la riga "Tipo formato").

### Applicazione elab
- Nessuna modifica a `lancia_ordinamento.php` / `genera_etichette.php`: la colonna `stampa_datamatrix` della configurazione arriva direttamente
  alla proprietà omonima della classe (prima il nome diverso `con_datamatrix` faceva sì che il flag non arrivasse mai).
- **Massiva** (e quindi anche `racc_smart`, che la estende): stesso trattamento del target, vedi "Massiva" sotto.

### Massiva
- `imposta_datamatrix_ordinati()` e `verifica_parametri_datamatrix()` sono stati spostati dalla classe target alla classe base `Elaborazione_postale`, così target e massiva
  usano lo stesso codice (stessi controlli, un solo UPDATE, indice univoco).
- `Elaborazione_postale_massiva::ordina()`: con `stampa_datamatrix` il codice figlio (ed eventuale padre) viene prelevato da `tabella_codici_figli` e salvato insieme al barcode,
  con un solo UPDATE (il vecchio ciclo con un UPDATE per record è sostituito). Il ramo `if($this->du)` è stato **eliminato**: la massiva non preleva più i codici in base a `du`,
  i programmi futuri useranno `stampa_datamatrix` al posto di `du`. Un programma batch con `du` ma senza `stampa_datamatrix` non preleva più figlio/padre (da correggere nelle sue variabili globali).
- `verifica_tabella_ordinati()` della massiva: le nuove tabelle `ordinati_massiva_*` hanno `barcode`, `codice_figlio`, `codice_padre` e l'indice univoco
  `ordinati_massiva_barcode_UQ`. La vista `_elaborato` include **sempre** queste colonne: le tabelle `ordinati_massiva` già esistenti (programmi batch, `ordinati_racc_smart`)
  vanno aggiornate a mano con le colonne prima di fare `composer update` su quel programma, altrimenti la vista non si ricrea.
- In `elab` non esistono tabelle `ordinati_massiva_*` (verificato in produzione), quindi per la massiva non serve nessun ALTER sulle tabelle ordinati di elab.
- Differenze rispetto a prima per i batch con `stampa_datamatrix`: l'ordinamento si ferma (invece di salvare un barcode sbagliato o con codice figlio `ZZZZZ`) se
  i dati fissi non hanno lunghezza/caratteri previsti, se manca `tabella_codici_figli`/`tabella_codici_padri_figli` o se il codice figlio non è di 5 caratteri;
  il progressivo oggetto segue `progr` (prima l'ordine di lettura della vista, che era già per `progr`).
- La colonna `du` della configurazione (`prenotazione_con_du`) resta non collegata: servirà nel passaggio successivo (generazione del file DU).

## Compatibilità con i programmi batch (repo originale)
Le variabili globali con `con_datamatrix` vanno rinominate in `stampa_datamatrix` quando si aggiorna la libreria su quel programma (non c'è alias).
Per il target le tabelle `ordinati_target*` e la vista `_elaborato` hanno le nuove colonne: una tabella batch già esistente va aggiornata a mano come in produzione.
Il salvataggio dei file e la lettura delle variabili globali non sono toccati. Un programma batch che volesse il datamatrix sul target
deve impostare nelle variabili globali `stampa_datamatrix`, `tabella_codici_figli` (con schema), `descrittivo_gamma`, `classe_postale`,
`tipo_prodotto_postale`, `sap`, `codice_cliente_postale`, `cap_mittente`, `omologa`, `barcode_campi_cliente`.

## Da eseguire in produzione (SQL, una volta)
Le colonne mancano nelle tabelle di configurazione. Finché non vengono create `crea/aggiorna_configurazione` non salvano il valore
di `tabella_codici_figli` (salvano solo le colonne esistenti) e l'ordinamento con il datamatrix si ferma per mancanza della tabella.

```sql
ALTER TABLE elab.target  ADD COLUMN tabella_codici_figli VARCHAR(100) NULL;
ALTER TABLE elab.massiva ADD COLUMN tabella_codici_figli VARCHAR(100) NULL,
                         ADD COLUMN omologa VARCHAR(10) NULL; -- omologa mancava su massiva
```

### Tabelle ordinati esistenti (da fare PRIMA di mettere in produzione la nuova versione)
Gli `ALTER` si possono lanciare in qualsiasi momento: la versione attuale li ignora. Per ogni tabella `ordinati_target_<base dati>` (oggi in `elab`:
`coopi_natale_2026`, `save_natale`, `save_the_children_gdpr`, `save_the_children_pmi`):

```sql
ALTER TABLE elab.ordinati_target_<base_dati>
  ADD COLUMN barcode varchar(72) DEFAULT NULL,
  ADD COLUMN codice_figlio varchar(5) DEFAULT NULL,
  ADD COLUMN codice_padre varchar(20) DEFAULT NULL;
ALTER TABLE elab.ordinati_target_<base_dati> ADD UNIQUE KEY ordinati_target_barcode_UQ (barcode);
```

## Note operative
- Ogni "Ordina" consuma un codice figlio: "Cancella dati ordinati" + "Ordina" ne brucia un altro (la tabella avvisa sotto i 10 codici).
- Il datamatrix è salvato su `ordinati_target_*.barcode`; la stampa/uso del barcode non è ancora parte di questo piano.
- Il campo cliente del barcode (`barcode_campi_cliente`, di solito `id_tabella`) viene allineato a destra con spazi fino a 17 caratteri.
- Il cap viene riempito con zeri a sinistra all'importazione (`crea_elaborazione.php`): un cap non di 5 caratteri (es. non numerico o più lungo) ferma l'ordinamento con l'elenco degli id.
- Ricordarsi di `git add` del file nuovo `preleva_tabelle_figli.php` e di commit/push del repo `Elaborazione_postale` prima di ricostruire l'immagine (il `vendor` di elab è gitignored).

## Test eseguiti (db di sviluppo, tabelle di prova poi eliminate)
- Tabella ordinati nuova: colonne e indice univoco presenti; vista con le nuove colonne.
- 2.000 record: barcode di 72 caratteri uguali a quelli ricalcolati a parte (progressivo = `progr`), tutti diversi, un solo codice figlio consumato.
- Barcode doppio rifiutato dall'indice univoco; cap di 2 caratteri e sap errato fermano l'ordinamento; senza tabella figli si ferma.
- Tabella ordinati "vecchia" (copia struttura di una esistente): senza gli ALTER la vista non si crea (atteso), con gli ALTER sopra funziona.

- Massiva (stesse tabelle di prova): ordina() completo su 600 record con datamatrix (barcode uguali ai calcolati a parte, tutti diversi, un solo figlio consumato);
  senza datamatrix nessun barcode e nessun figlio consumato (anche con `du`); tabella ordinati senza le colonne: la vista non si crea (atteso, ALTER manuale);
  cap non valido ferma l'ordinamento.

## Test manuali da fare
- [ ] Eseguiti gli ALTER su `elab.target` / `elab.massiva` e sulle tabelle `ordinati_target_*` esistenti.
- [ ] Nuova configurazione target con datamatrix: gamma T non modificabile, no profit E; campi obbligatori; menu tabelle figli con `import_file.figli_kpm`.
- [ ] Modifica di una configurazione esistente: il valore di `tabella_codici_figli` viene ricaricato.
- [ ] Ordina un'elaborazione con datamatrix: `select barcode, codice_figlio from ordinati_target_<base> where id_flusso = ...` (72 caratteri, tutti diversi); confronto con i barcode di esempio.
- [ ] Ordina un'elaborazione **massiva** con datamatrix (tipo prodotto W, gamma B): `select barcode, codice_figlio from ordinati_massiva_<base> where id_flusso = ...`; confronto con il barcode di esempio massiva.
- [ ] Etichette scatola: compare "LINEA EVOLUTION"; senza datamatrix no.
- [ ] Ordina un'elaborazione **senza** datamatrix su una tabella ordinati esistente (già aggiornata con gli ALTER): nessun errore, le altre funzioni (etichette, bancali, report) invariate.
