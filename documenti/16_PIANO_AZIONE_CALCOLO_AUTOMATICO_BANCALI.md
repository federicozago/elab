# Piano d'azione — Calcolo automatico min/max scatole per bancale (target)

## Contesto e problema

Nella configurazione `target`, l'utente doveva inserire manualmente `min_scatole_per_bancale` e
`max_scatole_per_bancale`, due valori tecnici difficili da stimare a priori perché dipendono
dall'ingombro fisico reale di buste/scatole e dal numero medio di buste che finiscono in ciascun
plico/scatola — numero che l'algoritmo di ordinamento decide da solo in base a bacino/tariffa, e
che quindi si conosce solo **dopo** aver ordinato i dati reali di una specifica elaborazione.

Sono stati esclusi dallo scope, con relativa motivazione:
- **Massiva**: usa un modello a peso cumulativo (`peso_scatola_min`/`peso_scatola_max`), non un
  conteggio di buste per scatola — il peso della singola busta non è fisso come in target, quindi il
  modello di calcolo qui descritto non si applica.
- **Altri prodotti/altri processi bancali** (`imposta_bancali()`, usato da
  `Elaborazione_postale_contest`/`_time`/`Gestione_sailpost`, che possono lavorare a **numero di
  pagine**, spesso non presente a db): non toccati. Il calcolo è **opt-in**: si attiva solo se le
  nuove dimensioni fisiche (busta o scatola) sono valorizzate; altrimenti il comportamento esistente
  (valore passato direttamente) resta invariato.

## Modello di calcolo

Eseguito interamente in `Elaborazione_postale::calcola_min_max_scatole_per_bancale()`, chiamato da
`aggiungi_ordinamento_bancali_diretti()` subito dopo `setta_dati_elaborazione()`.

1. **Numero medio reale di buste per plico/scatola** di questa specifica elaborazione, calcolato sui
   dati già ordinati (`AVG(COUNT(*) per idplico)` sulla tabella ordinamenti).
2. **Vincolo peso** (bancale 200–500 kg): `peso_unità = (peso_busta_vuota + peso_inserto) × n_buste_medio + tara_unità`,
   dove `tara_unità = tara_scatola (grammi)` **solo in modalità Scatole** (un plico è un fascio di buste
   legate, non ha una tara fisica propria — verificato confrontando i pesi reali dei bancali di un
   vecchio programma dismesso: il peso reale combaciava esattamente con `n_invii × peso_per_busta`,
   senza alcuna tara aggiuntiva sui plichi). Poi `min/max_per_peso = (200000|500000 - tara_pallet) / peso_unità`.
3. **Vincolo ingombro** (base bancale **80×120cm**, pallet standard indicato dalla produzione e dalla guida Poste; prima del 2026-11 era 100×120, altezza massima merce **1,10m per plichi / 1,40m per
   scatole**): `unità_per_strato` calcolato provando entrambi gli orientamenti della base,
   `strati_max = altezza_massima / altezza_unità` (per i plichi l'altezza unità è lo spessore di una
   busta × n_buste_medio; per le scatole è l'altezza fissa della scatola), `max_per_ingombro = unità_per_strato × strati_max`.
4. **Vincolo volume minimo** (1 m³): `min_per_volume = 1.000.000 cm³ / volume_unità`.
5. Peso e ingombro/volume sono vincoli **indipendenti (OR, non AND)**: basta superare/soddisfare **una
   delle due soglie** per considerare il bancale pieno (massimo) o abbastanza pieno (minimo) — quindi si
   prende sempre la soglia raggiunta con **meno scatole**, cioè il vincolo che scatta per primo:
   `max_scatole_per_bancale = min(max_per_peso, max_per_ingombro)`,
   `min_scatole_per_bancale = min(min_per_peso, min_per_volume)` (clampato a `max` nel solo caso limite
   in cui risultasse comunque superiore, come rete di sicurezza).
6. Il risultato **non viene salvato a db**: viene solo loggato con `$this->log->scrivi_log(...)` e
   usato in memoria per il resto dell'esecuzione di `aggiungi_ordinamento_bancali_diretti()`, che per
   il resto è rimasta identica (stessa logica di raggruppamento bacino/soglie invariata).

## Modifiche implementate

### Libreria `elaborazione_postale` (repo canonico + copia vendored in elab)
- `Elaborazione_postale.php`: nuove proprietà `larghezza_busta`, `altezza_busta`, `profondita_busta`,
  `larghezza_scatola`, `lunghezza_scatola`, `altezza_scatola`; nuovo metodo protetto
  `calcola_min_max_scatole_per_bancale($tabella)`; chiamata inserita in
  `aggiungi_ordinamento_bancali_diretti()`.

### Frontend
- `NewConfigTarget.vue`: aggiunti i campi dimensione busta (larghezza/altezza/profondità, cm) quando
  `Plichi` è selezionato (la profondità busta si inserisce in **millimetri**: il form la converte, a db e nel calcolo resta in cm), o dimensione scatola (larghezza/lunghezza/altezza, cm) quando `Scatole` è
  selezionato — subito dopo il radio "Tipo composizione" già esistente.
- `creazione_configurazione.vue`: rimossi i due campi manuali "Minimo/Massimo scatole per bancale"
  (e i relativi valori iniziali/pulizia in invio); `tara_pallet` resta invariato.

### Database
Le colonne vengono rimosse dalla tabella `target` (non più valorizzate da nessuno):

```sql
ALTER TABLE `elab`.`target`
  DROP COLUMN `min_scatole_per_bancale`,
  DROP COLUMN `max_scatole_per_bancale`;

ALTER TABLE `elab`.`target`
  ADD COLUMN `larghezza_busta`    FLOAT NULL AFTER `plichi`,
  ADD COLUMN `altezza_busta`      FLOAT NULL AFTER `larghezza_busta`,
  ADD COLUMN `profondita_busta`   FLOAT NULL AFTER `altezza_busta`,
  ADD COLUMN `larghezza_scatola`  FLOAT NULL AFTER `profondita_busta`,
  ADD COLUMN `lunghezza_scatola`  FLOAT NULL AFTER `larghezza_scatola`,
  ADD COLUMN `altezza_scatola`    FLOAT NULL AFTER `lunghezza_scatola`;
```

> Nota: `carica_a_db()` (in `Gestione_db`) filtra automaticamente i campi ricevuti dal frontend in
> base alle colonne esistenti nella tabella (via `INFORMATION_SCHEMA.COLUMNS`), quindi
> `crea_configurazione.php`/`aggiorna_configurazione.php` non richiedono modifiche: bastano le
> colonne nuove/rimosse per allineare cosa viene salvato.

## Checklist di test manuale

1. Eseguire le due query `ALTER TABLE` sopra su `elab.target` (dev, poi prod dopo verifica).
2. Creare/modificare una configurazione target: verificare che compaiano i campi dimensione corretti
   in base a Plichi/Scatole, e che i vecchi campi min/max scatole per bancale non siano più presenti.
3. Salvare la configurazione e verificare a db che le nuove colonne siano valorizzate e che
   `min_scatole_per_bancale`/`max_scatole_per_bancale` non esistano più come colonne.
4. Lanciare "Aggiungi ordinamento bancali diretti" su un'elaborazione target già ordinata: verificare
   nel log (`scrivi_log`) la riga "Calcolo automatico bancali [...]" con i valori calcolati, e che il
   raggruppamento in bancali avvenga (nessuna regressione nel comportamento).
5. Controllare che una configurazione target creata prima di questa modifica (quindi con eventuali
   dimensioni non compilate) non causi errori: se `larghezza_busta`/`larghezza_scatola` sono null, il
   calcolo viene saltato (nessun crash) — in tal caso `min/max_scatole_per_bancale` restano `null` per
   tutta l'esecuzione, quindi va richiesto all'utente di completare la configurazione con le nuove
   dimensioni prima di lanciare l'ordinamento bancali su elaborazioni esistenti.
