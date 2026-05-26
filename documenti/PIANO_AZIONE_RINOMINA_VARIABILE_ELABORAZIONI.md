# Piano d'Azione: Rinomina Variabile `azioni_elaborazioni` → `elaborazioni`

## Contesto

Nel file `temp/elab.php` è stata modificata la variabile globale da `azioni_elaborazioni` a `elaborazioni`. Questa modifica richiede l'aggiornamento di tutti i riferimenti a questa variabile sia nel frontend (Vue.js) che nel backend (PHP).

## Analisi dei Riferimenti Trovati

### Totale Occorrenze: 24

---

## 1. FILE BACKEND PHP (Priorità Alta)

### 1.1 File Attivi da Modificare

#### `php-docker-boilerplate/src/preleva_azioni_elaborazioni.php`
- **Linea 23**: `'azioni' => $vg["azioni_elaborazioni"]`
- **Azione**: Cambiare in `$vg["elaborazioni"]`
- **Impatto**: Endpoint che fornisce le azioni disponibili per ogni tipo di spedizione

#### `php-docker-boilerplate/src/preleva_configurazioni.php`
- **Linea 24**: `if( ! in_array($tipo_spedizione, array_keys($vg["azioni_elaborazioni"])))`
- **Azione**: Cambiare in `$vg["elaborazioni"]`
- **Impatto**: Validazione del tipo spedizione durante il prelievo configurazioni

#### `php-docker-boilerplate/src/preleva_tipi_spedizione.php`
- **Linea 23**: `'spedizioni' => array_keys($vg["azioni_elaborazioni"])`
- **Azione**: Cambiare in `$vg["elaborazioni"]`
- **Impatto**: Endpoint che restituisce i tipi di spedizione disponibili

#### `php-docker-boilerplate/src/vendor/cassetta_attrezzi_php/cassetta_attrezzi_php/app/Classes/risorse_variabili_globali_import/elab.php`
- **Linea 12**: `"azioni_elaborazioni" => [`
- **Azione**: Cambiare in `"elaborazioni" => [`
- **Impatto**: File di esempio delle variabili globali nella libreria cassetta_attrezzi

---

## 2. FILE FRONTEND VUE.JS (Priorità Alta)

### `src/pages/IndexPage.vue`
- **Linea 442**: `.post('/preleva_azioni_elaborazioni.php')`
- **Azione**: Nessuna modifica necessaria (è solo il nome dell'endpoint, non la variabile)
- **Nota**: Verificare che l'endpoint continui a funzionare correttamente dopo le modifiche backend

---

## 3. DOCUMENTAZIONE (Priorità Media)

### `documenti/SINTESI_PROGETTO.md`

Aggiornare i seguenti riferimenti:

#### Linea 11
- **Testo attuale**: `Altri prodotti definiti nell'array 'azioni_elaborazioni' (file 'temp/elab.php')`
- **Azione**: Cambiare in `Altri prodotti definiti nell'array 'elaborazioni' (file 'temp/elab.php')`

#### Linea 24
- **Testo attuale**: `I prodotti postali sono definiti nel file 'temp/elab.php' nell'array 'azioni_elaborazioni':`
- **Azione**: Cambiare in `I prodotti postali sono definiti nel file 'temp/elab.php' nell'array 'elaborazioni':`

#### Linea 329
- **Testo attuale**: `Definito in 'temp/elab.php' → 'azioni_elaborazioni'`
- **Azione**: Cambiare in `Definito in 'temp/elab.php' → 'elaborazioni'`

#### Linea 437
- **Testo attuale**: `#### **preleva_azioni_elaborazioni.php**`
- **Azione**: Nessuna modifica (è il nome del file endpoint)

#### Linea 814
- **Testo attuale**: `Array 'azioni_elaborazioni': Definisce prodotti postali e relative azioni`
- **Azione**: Cambiare in `Array 'elaborazioni': Definisce prodotti postali e relative azioni`

#### Linea 819
- **Testo attuale**: `L'array 'azioni_elaborazioni' è la fonte di verità per i prodotti postali disponibili e le loro azioni.`
- **Azione**: Cambiare in `L'array 'elaborazioni' è la fonte di verità per i prodotti postali disponibili e le loro azioni.`

---

## 4. FILE DI LOG (Priorità Bassa)

### `php-docker-boilerplate/src/php_errors.log`

Contiene 13 occorrenze di `azioni_elaborazioni` in messaggi di errore storici (date aprile 2026).

- **Azione**: Nessuna modifica necessaria (sono log storici)
- **Nota**: I nuovi errori rifletteranno automaticamente il nuovo nome della variabile

---

## 5. FILE DI CONFIGURAZIONE GLOBALE

### `temp/elab.php`

- **Linea 12**: `"azioni_elaborazioni" => [`
- **Azione**: **GIÀ MODIFICATO** dall'utente in `"elaborazioni" => [`
- **Nota**: Questo è il file di esempio locale. Le variabili globali reali sono su un server remoto e devono essere aggiornate separatamente

---

## Sequenza di Esecuzione Consigliata

### Fase 1: Backup
1. Creare un backup completo del progetto prima di iniziare
2. Creare un commit git con lo stato attuale

### Fase 2: Modifiche Backend (Critiche)
1. **`preleva_azioni_elaborazioni.php`** - Linea 23
2. **`preleva_configurazioni.php`** - Linea 24
3. **`preleva_tipi_spedizione.php`** - Linea 23
4. **`vendor/cassetta_attrezzi_php/.../elab.php`** - Linea 12

### Fase 3: Aggiornamento Documentazione
1. **`SINTESI_PROGETTO.md`** - Aggiornare tutte le 6 occorrenze

### Fase 4: Test
1. Testare endpoint `preleva_azioni_elaborazioni.php`
2. Testare endpoint `preleva_tipi_spedizione.php`
3. Testare endpoint `preleva_configurazioni.php`
4. Verificare funzionalità frontend in `IndexPage.vue` (pulsante "Azioni")
5. Verificare creazione nuovi lavori in `creazione_lavoro.vue`

### Fase 5: Variabili Globali Remote
1. Aggiornare le variabili globali sul server remoto
2. Cambiare `"azioni_elaborazioni"` in `"elaborazioni"`
3. Verificare che il sistema carichi correttamente le nuove variabili

---

## Checklist Finale

- [ ] Backup completo effettuato
- [ ] Commit git iniziale creato
- [ ] `preleva_azioni_elaborazioni.php` modificato
- [ ] `preleva_configurazioni.php` modificato
- [ ] `preleva_tipi_spedizione.php` modificato
- [ ] `vendor/.../elab.php` modificato
- [ ] `SINTESI_PROGETTO.md` aggiornato (6 occorrenze)
- [ ] Test endpoint backend completati
- [ ] Test frontend completati
- [ ] Variabili globali remote aggiornate
- [ ] Sistema testato end-to-end
- [ ] Documentazione finale aggiornata

---

## Note Importanti

### Impatto della Modifica
Questa modifica è **breaking change** che richiede:
- Aggiornamento sincronizzato di backend e variabili globali remote
- Test completo di tutte le funzionalità che dipendono dai tipi di spedizione
- Coordinamento con il team per evitare downtime

### Rischi
- **Alto**: Se le variabili globali remote non vengono aggiornate, il sistema smetterà di funzionare
- **Medio**: Possibili errori durante la creazione di nuovi lavori
- **Medio**: Possibili errori durante l'esecuzione di azioni sulle elaborazioni

### Rollback
In caso di problemi:
1. Ripristinare il commit git precedente
2. Ripristinare le variabili globali remote al valore precedente
3. Riavviare i servizi PHP se necessario

---

## Riepilogo File da Modificare

### Backend PHP (4 file attivi)
1. `php-docker-boilerplate/src/preleva_azioni_elaborazioni.php`
2. `php-docker-boilerplate/src/preleva_configurazioni.php`
3. `php-docker-boilerplate/src/preleva_tipi_spedizione.php`
4. `php-docker-boilerplate/src/vendor/cassetta_attrezzi_php/cassetta_attrezzi_php/app/Classes/risorse_variabili_globali_import/elab.php`

### Documentazione (1 file)
1. `documenti/SINTESI_PROGETTO.md` (6 occorrenze)

### Configurazione Remota
1. Variabili globali sul server remoto (file `elab.php` centralizzato)

### Frontend
- Nessuna modifica necessaria (gli endpoint rimangono invariati)

---

**Data creazione piano**: 2026-05-26  
**Versione**: 1.0