# Piano d'azione 19 - Prenotazione con DU (pulsante Prenota)

## Obiettivo
Per i lavori per cui Poste richiede la DU (Distinta Elettronica di Prenotazione Unica, un record per busta; guida "Specifiche Tecniche Distinta Elettronica di
Prenotazione Unica DU_LUG24") il pulsante **Prenota** deve: far indicare la data di spedizione, generare la DU con `Elaborazione_postale::genera_du_elaborazione`
e permettere di scaricarne lo zip. Dopo la prenotazione il passo successivo è **Chiudi elab**, che fa la stessa cosa di "chiudi elaborazione" delle Azioni.

## Regole
- **Il datamatrix comporta la DU**: il primo campo di ogni riga della DU è il datamatrix (codice invio, 72 caratteri). Nella configurazione c'è quindi solo "Stampa datamatrix"; attivandolo
  si abilita in automatico la prenotazione con DU (`prenotazione_con_du` viene impostata dal form e dal backend uguale a `stampa_datamatrix`) e compaiono i dati della DU.
- **Codice figlio e codice padre si prelevano all'ordinamento**, come nei programmi batch (`imposta_datamatrix_ordinati` → `preleva_codice_padre_figlio`): ogni "Ordina" consuma un figlio e un padre
  (se l'ordinamento fallisce la transazione li restituisce). La DU e i suoi riscarichi usano sempre il padre salvato in `ordinati.codice_padre`: la prenotazione non consuma niente.
- **Bancali**: ogni riga della DU ha il barcode del bancale (ID HU, `genera_bc_bancale` con `idbancale`): senza id bancale la DU non si genera (`genera_du.php` lo controlla).
  - Massiva: non ha l'ordinamento in bancali, quindi come per il Time le scatole ordinate vengono divise in bancali da `max_scatole_per_bancale` scatole (`imposta_bancali`, già nella classe base);
    il campo della configurazione massiva "Scatole per bancale (max)" è precompilato con 56 (valore usato dal Time) ed è obbligatorio con il datamatrix.
  - Target con datamatrix: i bancali sono **automatici** all'ordinamento (`lancia_ordinamento.php` → `assegna_bancali_con_du`, stato finale 2 invece di 5). Con il datamatrix sono obbligatorie le dimensioni di
    busta (plichi) o scatola e la tara pallet, da cui si calcolano min/max scatole per bancale (piano 16). Se c'è almeno un bacino che riempie un bancale diretto (più di `min` scatole di un solo bacino, escluso ZCARTI)
    si esegue `aggiungi_ordinamento_bancali_diretti` (riordina scatole e progressivi); altrimenti l'ordinamento standard resta e le scatole sono divise in bancali da `max` scatole (`imposta_bancali`; con poche scatole
    id bancale 1). Il toggle "Gestione bancali" sparisce con il datamatrix (il passo manuale stato 5 resta per i target senza datamatrix) e `gestione_bancali` viene salvata a 0.

## Flusso
1. Configurazione con **Stampa datamatrix** (e quindi DU): tabella codici figli, tabella codici padri, dati del datamatrix e della DU; per la massiva scatole per bancale.
2. Elaborazione ordinata (stato 2): compare **Prenota** solo se la configurazione ha la DU (altrimenti la cella dice "Ordinato" e c'è subito **Chiudi elab**).
3. **Prenota**: dialog con la data di spedizione e **Prenota e scarica DU** → `genera_du.php` → scarica lo zip `NPSO_G_DU_ID<sap>_<codice padre>_01.zip` → stato 4 (Prenotato).
4. In stato 4 restano **Scarica DU** (riscarica la DU: stesso codice padre, la data si può cambiare) e **Chiudi elab** (zip con i dati, stato 255).

## Verifica: il flag "Con prenotazione" non serve più
Confermato e rimosso dal form (la colonna `con_prenotazione` resta nelle tabelle, non viene più scritta). Il vecchio `prenotazione.php` con `con_prenotazione = true` chiamava `$elab->prenotazione()`,
**metodo che non esiste in nessuna classe della libreria** (andava in errore); con `false` salvava solo la data e portava a stato 4. `prenotazione.php` è stato sostituito da `genera_du.php`
(eliminato). Il pulsante Prenota senza DU non c'è più, come richiesto: per le configurazioni senza DU la data per i documenti (es. distinte) si indica dove serve come parametro delle azioni.
Lo stato 3 ("In attesa di ok da Poste") non viene più impostato da nessuno, resta solo il testo nella tabella.

## Cosa è stato fatto

### Libreria `elaborazione_postale` (repo `import_file\Elaborazione_postale` e copia in `vendor` di elab)
Nessuna funzione dedicata a elab: la prenotazione usa le funzioni già esistenti (`salva_data_spedizione`, `genera_du`). Cambiamenti:
- `Elaborazione_postale::genera_du_elaborazione`: nuova proprietà `comandi_windows` (default `PHP_OS_FAMILY === 'Windows'`, quindi i programmi batch non cambiano). Se false (elab nel container Linux)
  lo zip della DU si crea con `ZipArchive` e il `.DU` si cancella con `unlink`: `7z.exe` e i comandi dos di `Import_file::elimina_files` (`del /q`) su Linux non esistono.
  Il resto (anche `folder_deposito_du` e la copia in `$folder_out`) è invariato.
- `barcode_scatola` facoltativo nella riga della DU (`?? ""`: le viste `_elaborato` di target e massiva non ce l'hanno; nella DU è "ID scatola", non obbligatorio).
- `imposta_barcode_datamatrix`, `imposta_datamatrix_ordinati` e `verifica_parametri_datamatrix` stanno nella classe base (usate da target e massiva).
- `genera_bc_bancale`: il frazionario del centro di accettazione (5 cifre) non è più fisso a `68172` (Verona) ma si ricava da `age` (es. `AGE02149` → `02149`); senza `age` resta `68172` (programmi batch invariati).
  Struttura del codice HU da guida Poste: 2 servizio VAS + 8 SAP + 1 identificativo centro (0 = CMP) + 5 frazionario + 2 anno + 7 ID pallet + check digit.
- Nuovi `assegna_bancali_con_du($tabella)` (pubblico, usato da elab per il target con datamatrix) e `esistono_bacini_diretti($tabella)`.
- `aggiungi_ordinamento_bancali_diretti`: correzione di un bug esistente. Le scatole di un bacino diretto "parcheggiate" perché non bastavano a fare un bancale venivano reinserite solo all'arrivo di un plico misto che
  segue in ordine alfabetico: senza plichi misti dopo di loro restavano senza `idbancale` e senza essere riassegnate. Ora a fine ciclo vengono accodate (chiudendo il bancale al massimo).
- Refuso `preleva_codice_padre()` / `preleva_codice_figlio()` (metodi che non esistono) in `Elaborazione_postale_contest` e `_market` corretto in `preleva_codice_padre_figlio()` (il time era già stato corretto).
- Massiva: `ordina` richiama `imposta_bancali($tabella)` se `max_scatole_per_bancale` è valorizzato (come il Time); prima del datamatrix. Senza il parametro (batch) non cambia niente.
  La tabella `ordinati_massiva_*` ha già `idbancale`, la vista `_elaborato` lo espone.

### Backend (`php-docker-boilerplate/src`)
- `genera_du.php` (nuovo): controlla configurazione con DU, stato 2 o 4, base dati con campo ragione sociale e indirizzo, e che i record ordinati abbiano datamatrix, un solo codice padre e l'id bancale
  (altrimenti l'elaborazione è stata ordinata prima di questa modifica e va riordinata). Poi `salva_data_spedizione` (rifiuta le date passate) e `genera_du` in una cartella temporanea,
  risponde con lo zip (`Content-Disposition` con il nome della DU), imposta stato 4 ed elimina la cartella. `campi_spare` (testo separato da virgola) diventa un array.
- `preleva_tabelle_padri.php` (nuovo): tabelle `padri_*` di `import_file` ed `elab` nella forma `schema.tabella` (es. `import_file.padri_adv_trade`).
- `preleva_elaborazioni_in_corso.php`: ogni elaborazione ha `prenotazione_con_du` (letto dalla configurazione, con cache per configurazione) per decidere se mostrare Prenota.
- `lancia_ordinamento.php`: passa alla classe `tabella_codici_padri` come prima della DU (il padre si preleva all'ordinamento); per il target con datamatrix assegna i bancali subito dopo l'ordinamento.
- `crea_configurazione.php` / `aggiorna_configurazione.php`: con il datamatrix sono obbligatorie le tabelle figli e padri (per la massiva `max_scatole_per_bancale`, per il target dimensioni busta/scatola e `tara_pallet`); `prenotazione_con_du`
  viene impostata uguale a `stampa_datamatrix`; le tabelle dei codici devono avere la forma `import_file|elab.figli_*|padri_*` (finiscono nelle query della libreria).

### Frontend
- Configurazione target: con il datamatrix compaiono tara pallet e dimensioni di busta/scatola (obbligatorie) e sparisce il toggle "Gestione bancali".
- Configurazione: il blocco **Stampa datamatrix** (con tabella codici figli e padri) viene prima; i dati della DU compaiono sotto, in automatico, quando il datamatrix è attivo. Tolti "Con prenotazione" e
  il toggle "Prenotazione con DU". "Codice identificativo pallet" propone 96 per la massiva e 98 per il target (guida Poste codice HU: 91 Time base, 92 Time ora, 95 Contest, 96 Massiva, 98 Target;
  per il target prima il suggerimento era 95). Massiva: campo "Scatole per bancale (max)" (default 56).
- Base dati: tre campi facoltativi **Ragione sociale / Nominativo**, **Indirizzo**, **Numero civico** (il civico non è indispensabile): servono alla DU.
- IndexPage: Prenota solo con la DU; dialog "Prenotazione con DU"; la colonna **Chiudi Elab** (prima senza pulsante) ora ha il pulsante, che lancia `chiudi_elaborazione.php` come l'azione. Il codice di download
  dei file è condiviso (`scaricaFile`, `estraiMessaggioErrore`).

## Da eseguire in produzione (SQL, una volta, PRIMA di mettere in produzione la nuova versione)
```sql
ALTER TABLE elab.target  ADD COLUMN tabella_codici_padri VARCHAR(100) NULL;
ALTER TABLE elab.massiva ADD COLUMN tabella_codici_padri VARCHAR(100) NULL;
ALTER TABLE elab.base_dati ADD COLUMN campo_ragsoc VARCHAR(100) NULL,
                           ADD COLUMN campo_indirizzo VARCHAR(100) NULL,
                           ADD COLUMN campo_civico VARCHAR(100) NULL;
```
(più gli ALTER del piano 18). `max_scatole_per_bancale` esiste già in `elab.massiva`.
Le basi dati **già esistenti** non hanno i campi ragione sociale/indirizzo (la pagina base dati non ha una modifica): per usarle con la DU vanno impostati a mano, indicando i nomi delle colonne della base dati, ad es.
`UPDATE elab.base_dati SET campo_ragsoc = 'ragione_sociale', campo_indirizzo = 'indirizzo' WHERE nome_base_dati = '...';`.
Serve anche una tabella `padri_<cliente>` in `import_file` (o `elab`) con colonne `id` (auto increment) e `idpadre`, come `import_file.padri_adv_trade`.
Le elaborazioni già ordinate con il datamatrix (senza padre sui record) vanno riordinate prima di prenotarle. Le configurazioni massiva esistenti non hanno `max_scatole_per_bancale`: aprendole in modifica il campo
è obbligatorio (proposto 56).

## Test eseguiti (db di sviluppo, tabelle di prova `zz_dm_*` con 15 codici per non far partire le mail "Rimasti pochi codici", poi eliminate; lint ok)
Classe massiva, 300 record, `comandi_windows = false` (come nel container Linux):
- ordinamento con datamatrix: un padre e un figlio consumati, padre su tutti i record, barcode di 72 caratteri su tutti, `idbancale` valorizzato su tutti (8 scatole, bancali da 2 scatole, ogni scatola in un solo bancale);
- senza `max_scatole_per_bancale` (batch): nessun bancale, ordinamento invariato;
- controllo dell'endpoint (barcode e padre su tutti i record); data passata rifiutata; data salvata su tutti i record;
- `genera_du`: zip `NPSO_G_DU_ID<sap>_<padre>_01.zip` con il solo `.DU`, header corretto, 300 righe, primo campo = barcode a db di 72 caratteri, ID HU di 26 cifre distinti uguali ai bancali;
  la DU non consuma codici; riscarico: stesso zip e stesso padre; il `.DU` non resta nella cartella di lavoro.
**Non** provato: il ramo Windows (`7z.exe`) in questo ambiente, perché la copia vendorizzata di `Cassetta_attrezzi_php` in elab è più vecchia di quella del repo (in `Import_file::archivia` apre il file
di lista con la modalità sbagliata): è il codice originale invariato, ma da riprovare con i batch. **Non** provato end to end da interfaccia: servono prima gli ALTER (le colonne nuove non esistono sul db di sviluppo).

## Test manuali da fare
- [ ] Eseguiti gli ALTER; creata una tabella `padri_*` e una `figli_*` con qualche codice (almeno 10); impostati `campo_ragsoc` / `campo_indirizzo` della base dati di prova.
- [ ] Nuova configurazione: attivando "Stampa datamatrix" compaiono tabelle figli e padri (obbligatorie) e i dati della DU; massiva: "Scatole per bancale (max)" proposto a 56; salvataggio e modifica.
- [ ] Massiva con datamatrix: Ordina → compare Prenota; `select idbancale, count(*) from ordinati_massiva_<base> group by idbancale` mostra i bancali; consumati un figlio e un padre.
- [ ] Prenota con una data passata: errore chiaro. Con data valida: scarica lo zip, stato Prenotato; `select codice_padre, data_spedizione from ordinati_<tipo>_<base>`.
- [ ] Aprire il `.DU`: header e righe come da guida (tipologia prodotto, codice prodotto, servizio accessorio, frazionario, conto contrattuale, ID HU); controllo con "Invia e Controlla" di Poste.
- [ ] Scarica DU in stato Prenotato: stesso codice padre, nessun altro codice consumato. Chiudi elab: scarica lo zip e l'elaborazione passa nelle concluse (Riapri → di nuovo Prenotato).
- [ ] Target con datamatrix: Ordina → l'elaborazione passa direttamente a Ordinata (non a "assegna bancali"); `select idbancale, count(distinct idplico) from ordinati_target_<base> group by idbancale` senza id nulli;
      prova con un lavoro con bacini diretti e uno piccolo (id bancale 1); Prenota genera la DU con gli ID HU.
- [ ] Senza datamatrix (configurazione normale): dopo Ordina compare "Ordinato" e subito Chiudi elab; nessun Prenota.

## Punti da verificare con Poste (non toccati)
- Fissi nella funzione: tipologia di accettazione `SMA`, tipo posta "indescritta" (barcode bancale solo se la tipologia prodotto non è `R`); `idl`, `campi_aggiuntivi_du` e `folder_deposito_du` non sono nella configurazione.
- Kpm (DU spezzata in più file, `cdc`) non è supportato da elab: i kpm restano ai batch.

## Note sui bancali del target (guide Poste)
- `06_Allestimento_prelavorazione_gamma_Postatarget.pdf`: pallet con altezza massima 150 cm, peso massimo 700 kg (300 se impilabile), volume minimo 0,5 m³ o 200 kg per un pallet diretto; i resti in Mix Italia.
  Il calcolo min/max del piano 16 usa soglie proprie (200-500 kg, 1 m³, altezza merce 110 cm plichi / 140 cm scatole): non modificate.
- `2322_02_Scheda Tecnica Prodotto codice HU...pdf`: ID univoco pallet "nell'ambito della postalizzazione" (quindi `idbancale` che riparte da 1 a ogni elaborazione è coerente).
- I datamatrix sono calcolati prima del riordino per bancali: il progressivo oggetto (6 cifre) resta univoco ma non segue l'ordine finale delle scatole (già così nel passo manuale).
