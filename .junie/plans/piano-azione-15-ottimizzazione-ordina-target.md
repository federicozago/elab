---
sessionId: session-260701-153720-12s8
---

# Requirements

### Overview & Goals
Produrre un documento **"PIANO DI AZIONE 15"** (nuovo file in `documenti/`, sullo stesso modello del file già presente `documenti/14_PIANO_AZIONE_ORDINAMENTO_ELABORAZIONI_PER_ULTIMA_INSERITA.md`) che riporti **tutto il codice PRIMA/DOPO** necessario per applicare, sulla funzione `ordina()` di `Elaborazione_postale_target.php`, le tre ottimizzazioni con il maggior impatto individuate nell'analisi precedente:
1. eliminare la query UPDATE per singolo record (righe 111-114);
2. eliminare la duplicazione dei dati in array paralleli nei blocchi A-E (righe 144-151, 236-248, 321-329, 403-410, 507-510) usando `&` in modo corretto stavolta (normalizzazione **in place** dentro `$res`, non lettura);
3. ridurre le colonne selezionate dalle query (`select *` → colonne specifiche) nei blocchi che caricano `ordinati_a/b/c/d/e`.

**Nessun file del progetto verrà modificato**: l'unico output è un documento markdown con proposte di codice, esattamente come richiesto ("senza modificare il codice del progetto").

> **Nota sul processo (perché il file non è ancora stato scritto):** questo documento che stai leggendo è il **Piano** (fase di pianificazione), non l'esecuzione. In questa fase l'agente può creare/modificare **solo il file del piano stesso**, non file del progetto — quindi non può ancora creare `documenti/15_PIANO_AZIONE_OTTIMIZZAZIONE_ORDINAMENTO_TARGET.md`. La creazione effettiva di quel file avviene nella fase successiva di **implementazione/delivery**, eseguendo gli Step elencati più sotto (Delivery Steps), che va richiesta esplicitamente (es. approvando il piano con l'opzione "implementa"). Una volta approvato il piano in questa modalità, il file verrà scritto in `documenti/` seguendo esattamente le sezioni e i blocchi di codice descritti qui sotto.

### Scope
**In Scope**
- Creazione del file `documenti/15_PIANO_AZIONE_OTTIMIZZAZIONE_ORDINAMENTO_TARGET.md` (nome indicativo, stesso stile del piano 14).
- Sezioni: Problema rilevato, Analisi Tecnica, Soluzione Proposta (una per ognuna delle 3 ottimizzazioni, con blocchi di codice PRIMA/DOPO reali, presi dal file `Elaborazione_postale_target.php` con numeri di riga), Alternative considerate (query non bufferizzate), Test e Validazione, Riepilogo Modifiche, Note Finali.
- Il codice "DOPO" deve essere plausibile e compatibile con il resto della funzione (stessa firma dei metodi di `Gestione_db`, stessa struttura delle tabelle temporanee), ma resta una **proposta**, non viene applicata.

**Out of Scope**
- Modificare realmente `Elaborazione_postale_target.php` o qualunque altro file del progetto.
- Testare il codice proposto su un database reale.
- Occuparsi del piano 14 (già completo, usato solo come riferimento di formato).

### Functional Requirements
- Il documento deve citare i numeri di riga esatti del file originale per ogni blocco toccato.
- Ogni ottimizzazione deve avere un blocco "PRIMA" (codice attuale, copiato fedelmente) e un blocco "DOPO" (codice proposto, completo e coerente).
- Deve essere spiegato perché, nel blocco 2 (array paralleli), l'uso di `&` è qui corretto ed efficace — a differenza di quanto detto nell'analisi precedente per i cicli di sola lettura — perché stavolta si scrive **in-place** dentro `$res` invece di creare 6 array scalari duplicati.

# Technical Design

### Current Implementation (riferimento)
File: `php-docker-boilerplate/src/vendor/elaborazione_postale/elaborazione_postale/app/Classes/Elaborazione_postale_target.php`, funzione `ordina()` (righe 31-563). I tre punti da documentare nel piano 15:

---

### 1) Batch UPDATE invece di query per-record (righe 90-119)

**PRIMA** (righe 90-118):
```php
foreach($res as $i => $riga){
    $a_cap[] = trim(strtoupper($riga["cap"]));
    $a_localita[] = trim(strtoupper(($riga["localita"])));
    $a_pv[] = trim(strtoupper($riga["prov"]));
    $a_idr[] = trim(strtoupper($riga["IDR"]));
}
$elem_array_tot = count($a_cap);
$i = 0;
foreach ($a_cap as $cap) {
    if (substr($cap, 0, 2) == '07' or substr($cap, 0, 2) == '08' or (substr($cap, 0, 2) == '09' and ($cap < 9100 or $cap > 9134))) {
        cap_sardegna(trim($cap), trim($a_localita[$i]),false);
    } else {
        cap_assoc(trim($cap),false);
    }
    $idr = $a_idr[$i];
    $pv_orig = trim(strtoupper($a_pv[$i]));

    if ($GLOBALS["bacino"] == " " or $GLOBALS["provincia"] <> $pv_orig) {
        $GLOBALS["bacino"] = "ZCARTI";
        $GLOBALS["tariffa"] = "ZCARTI";
    }

    $query_updt = "update `{$this->database_ordinamenti}`.`temp_{$this->id_flusso}_$tabella` set bacino = '{$GLOBALS["bacino"]}', tariffa = '{$GLOBALS["tariffa"]}' where idr = $idr";
    if(!$this->gestione_db->esegui_query($query_updt))
        return false;

    $i++;
    if($i%10000==0) $this->log->scrivi_log("Assegnazione bacino tariffa fino al record numero $i");
}
unset($a_cap, $a_localita, $a_pv, $a_idr);
```

**DOPO** (proposto):
```php
$batch_size = 2000; // numero di record per singola UPDATE
$case_bacino = [];
$case_tariffa = [];
$idr_batch = [];
$n_res = count($res);
$i = 0;

foreach($res as $riga){
    $cap = trim(strtoupper($riga["cap"]));
    $localita = trim(strtoupper($riga["localita"]));
    $pv_orig = trim(strtoupper($riga["prov"]));
    $idr = (int) trim($riga["IDR"]);

    if (substr($cap, 0, 2) == '07' or substr($cap, 0, 2) == '08' or (substr($cap, 0, 2) == '09' and ($cap < 9100 or $cap > 9134))) {
        cap_sardegna($cap, $localita, false);
    } else {
        cap_assoc($cap, false);
    }

    if ($GLOBALS["bacino"] == " " or $GLOBALS["provincia"] <> $pv_orig) {
        $GLOBALS["bacino"] = "ZCARTI";
        $GLOBALS["tariffa"] = "ZCARTI";
    }

    $case_bacino[] = "when $idr then '{$GLOBALS["bacino"]}'";
    $case_tariffa[] = "when $idr then '{$GLOBALS["tariffa"]}'";
    $idr_batch[] = $idr;
    $i++;

    $is_last = ($i == $n_res);
    if (count($idr_batch) >= $batch_size or $is_last) {
        $lista_idr = implode(",", $idr_batch);
        $query_updt = "update `{$this->database_ordinamenti}`.`temp_{$this->id_flusso}_$tabella`
            set bacino = case idr " . implode(" ", $case_bacino) . " end,
                tariffa = case idr " . implode(" ", $case_tariffa) . " end
            where idr in ($lista_idr)";
        if(!$this->gestione_db->esegui_query($query_updt))
            return false;

        $case_bacino = [];
        $case_tariffa = [];
        $idr_batch = [];
    }

    if($i%10000==0) $this->log->scrivi_log("Assegnazione bacino tariffa fino al record numero $i");
}
```
Effetto: con 800.000 record e `batch_size = 2000` si passa da **800.000 query** a **~400 query**, eliminando anche i 4 array paralleli `$a_cap/$a_localita/$a_pv/$a_idr` (si legge direttamente da `$riga`).

---

### 2) Eliminare gli array paralleli nei blocchi A-E usando `&` correttamente (es. blocco A, righe 144-202)

Qui, a differenza dei cicli di sola lettura analizzati prima, si propone di usare `foreach (... as &$riga)` per **normalizzare i valori in place** dentro `$res`, evitando di creare `$a_bacino_o, $a_tariffa_o, $a_cap_o, $a_localita_o, $a_pv_o, $a_progr_o`.

**PRIMA** (righe 144-200, semplificato):
```php
foreach($res as $i => $riga){
    $a_bacino_o[] = trim(strtoupper($riga["bacino"]));
    $a_tariffa_o[] = trim(strtoupper($riga["tariffa"]));
    $a_cap_o[] = trim(strtoupper($riga["cap"]));
    $a_localita_o[] = trim(strtoupper($riga["localita"]));
    $a_pv_o[] = trim(strtoupper($riga["prov"]));
    $a_progr_o[] = trim(strtoupper($riga["progr"]));
}

$i = 0;
$elem_array_a = count($a_progr_o);
if ($elem_array_a > 0) {
    foreach ($a_progr_o as $progr) {
        if ($this->prodotto_target == "CREATIVE") {
            $valore = $a_cap_o[$i] . $a_localita_o[$i];
            if ($i > 0) $valore_prec = $a_cap_o[$i - 1] . $a_localita_o[$i - 1];
        } else {
            $valore = $a_cap_o[$i];
            if ($i > 0) $valore_prec = $a_cap_o[$i - 1];
        }
        // ... resto della logica invariata ...
        $i++;
    }
}
unset($a_bacino_o, $a_tariffa_o, $a_cap_o, $a_localita_o, $a_pv_o, $a_progr_o);
```

**DOPO** (proposto):
```php
// normalizzo in place dentro $res: qui il riferimento & è corretto perché SCRIVO nell'array,
// non lo leggo soltanto (a differenza dei foreach di sola lettura visti nell'analisi precedente)
foreach ($res as &$riga) {
    $riga["bacino"] = trim(strtoupper($riga["bacino"]));
    $riga["tariffa"] = trim(strtoupper($riga["tariffa"]));
    $riga["cap"] = trim(strtoupper($riga["cap"]));
    $riga["localita"] = trim(strtoupper($riga["localita"]));
    $riga["prov"] = trim(strtoupper($riga["prov"]));
    $riga["progr"] = trim(strtoupper($riga["progr"]));
}
unset($riga); // fondamentale: distrugge il riferimento residuo dopo un foreach con &

$elem_array_a = count($res);
if ($elem_array_a > 0) {
    foreach ($res as $i => $riga) {
        $progr = $riga["progr"];

        if ($this->prodotto_target == "CREATIVE") {
            $valore = $riga["cap"] . $riga["localita"];
            if ($i > 0) $valore_prec = $res[$i - 1]["cap"] . $res[$i - 1]["localita"];
        } else {
            $valore = $riga["cap"];
            if ($i > 0) $valore_prec = $res[$i - 1]["cap"];
        }
        // ... resto della logica invariata, sostituendo $a_cap_o[$i]->$riga["cap"], $a_cap_o[$i-1]->$res[$i-1]["cap"], ecc. ...
    }
}
unset($res);
```
#### Blocco B (righe 236-290, con caso speciale tariffa EU → PROV/CITT)

**PRIMA:**
```php
foreach($res as $i => $riga){
    $a_bacino_o[] = trim(strtoupper($riga["bacino"]));
    # Raggruppo AM E CP come citta'. EU e' provincia
    if (trim(strtoupper($riga["tariffa"])) == "EU") {
        $a_tariffa_o[] = "PROV";
    } else {
        $a_tariffa_o[] = "CITT";
    }
    $a_cap_o[] = trim(strtoupper($riga["cap"]));
    $a_localita_o[] = trim(strtoupper($riga["localita"]));
    $a_pv_o[] = trim(strtoupper($riga["prov"]));
    $a_progr_o[] = trim(strtoupper($riga["progr"]));
}

$i = 0;
$progr_start = 1;
$progr_scatola = 1;
$elem_array_b = count($a_progr_o);
if ($elem_array_b > 0) {
    foreach ($a_progr_o as $progr) {
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($a_pv_o[$i] . $a_tariffa_o[$i] == $a_pv_o[$i - 1] . $a_tariffa_o[$i - 1]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_b && $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
        $i++;
    }
}
unset($a_bacino_o, $a_tariffa_o, $a_cap_o, $a_localita_o, $a_pv_o, $a_progr_o);
```

**DOPO:**
```php
// normalizzo in place dentro $res, incluso il calcolo speciale tariffa EU -> PROV/CITT
foreach ($res as &$riga) {
    $riga["bacino"] = trim(strtoupper($riga["bacino"]));
    $riga["tariffa"] = (trim(strtoupper($riga["tariffa"])) == "EU") ? "PROV" : "CITT";
    $riga["cap"] = trim(strtoupper($riga["cap"]));
    $riga["localita"] = trim(strtoupper($riga["localita"]));
    $riga["prov"] = trim(strtoupper($riga["prov"]));
    $riga["progr"] = trim(strtoupper($riga["progr"]));
}
unset($riga);

$progr_start = 1;
$progr_scatola = 1;
$elem_array_b = count($res);
if ($elem_array_b > 0) {
    foreach ($res as $i => $riga) {
        $progr = $riga["progr"];
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($riga["prov"] . $riga["tariffa"] == $res[$i - 1]["prov"] . $res[$i - 1]["tariffa"]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_b && $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'B' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
    }
}
unset($res);
```

#### Blocco C (righe 321-372)

**PRIMA:**
```php
foreach($res as $i => $riga){
    $a_bacino_o[] = trim(strtoupper($riga["bacino"]));
    $a_tariffa_o[] = trim(strtoupper($riga["tariffa"]));
    $a_cap_o[] = trim(strtoupper($riga["cap"]));
    $a_localita_o[] = trim(strtoupper($riga["localita"]));
    $a_pv_o[] = trim(strtoupper($riga["prov"]));
    $a_progr_o[] = trim(strtoupper($riga["progr"]));
}

$i = 0;
$progr_start = 1;
$progr_scatola = 1;
$elem_array_c = count($a_progr_o);
if ($elem_array_c > 0) {
    foreach ($a_progr_o as $progr) {
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($a_pv_o[$i] == $a_pv_o[$i - 1]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_c and $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
        $i++;
    }
}
unset($a_bacino_o, $a_tariffa_o, $a_cap_o, $a_localita_o, $a_pv_o, $a_progr_o);
```

**DOPO:**
```php
foreach ($res as &$riga) {
    $riga["bacino"] = trim(strtoupper($riga["bacino"]));
    $riga["tariffa"] = trim(strtoupper($riga["tariffa"]));
    $riga["cap"] = trim(strtoupper($riga["cap"]));
    $riga["localita"] = trim(strtoupper($riga["localita"]));
    $riga["prov"] = trim(strtoupper($riga["prov"]));
    $riga["progr"] = trim(strtoupper($riga["progr"]));
}
unset($riga);

$progr_start = 1;
$progr_scatola = 1;
$elem_array_c = count($res);
if ($elem_array_c > 0) {
    foreach ($res as $i => $riga) {
        $progr = $riga["progr"];
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($riga["prov"] == $res[$i - 1]["prov"]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_c and $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'C' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
    }
}
unset($res);
```

#### Blocco D (righe 403-449)

**PRIMA:**
```php
foreach($res as $i => $riga){
    $a_bacino_o[] = trim(strtoupper($riga["bacino"]));
    $a_tariffa_o[] = trim(strtoupper($riga["tariffa"]));
    $a_cap_o[] = trim(strtoupper($riga["cap"]));
    $a_localita_o[] = trim(strtoupper($riga["localita"]));
    $a_pv_o[] = trim(strtoupper($riga["prov"]));
    $a_progr_o[] = trim(strtoupper($riga["progr"]));
}

$i = 0;
$progr_start = 1;
$progr_scatola = 1;
$elem_array_d = count($a_progr_o);
if ($elem_array_d > 0) {
    foreach ($a_progr_o as $progr) {
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($a_bacino_o[$i] == $a_bacino_o[$i - 1]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_d && $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
        $i++;
    }
}
unset($a_bacino_o, $a_tariffa_o, $a_cap_o, $a_localita_o, $a_pv_o, $a_progr_o);
```

**DOPO:**
```php
foreach ($res as &$riga) {
    $riga["bacino"] = trim(strtoupper($riga["bacino"]));
    $riga["tariffa"] = trim(strtoupper($riga["tariffa"]));
    $riga["cap"] = trim(strtoupper($riga["cap"]));
    $riga["localita"] = trim(strtoupper($riga["localita"]));
    $riga["prov"] = trim(strtoupper($riga["prov"]));
    $riga["progr"] = trim(strtoupper($riga["progr"]));
}
unset($riga);

$progr_start = 1;
$progr_scatola = 1;
$elem_array_d = count($res);
if ($elem_array_d > 0) {
    foreach ($res as $i => $riga) {
        $progr = $riga["progr"];
        $progr_scatola++;
        if ($i == 0) {
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($riga["bacino"] == $res[$i - 1]["bacino"]) {
            if ($progr_scatola > $this->buste_max) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr < $progr"))
                    return false;
                $progr_scatola = 1;
                $progr_start = $progr;
            } elseif ($i + 1 == $elem_array_d && $progr_scatola > $this->buste_min) {
                $n_plico++;
                if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr <= $progr"))
                    return false;
            }
        } elseif ($progr_scatola > $this->buste_min) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'D' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } else {
            $progr_scatola = 1;
            $progr_start = $progr;
        }
    }
}
unset($res);
```

#### Blocco E (righe 507-542, solo `progr` e `bacino`)

**PRIMA:**
```php
foreach($res as $i => $riga) {
    $a_progr_o[] = trim(strtoupper($riga["progr"]));
    $a_bacino_o[] = trim(strtoupper($riga["bacino"]));##bacino
}

$i = 0;
$progr_start = 1;
$progr_scatola = 0;
$elem_array_e = count($a_progr_o);
$bacino_precedente = "";
if ($elem_array_e > 0) {
    foreach ($a_progr_o as $progr) {
        $progr_scatola++;
        if (($progr_scatola > $this->buste_max) || ($a_bacino_o[$i] != $bacino_precedente and $progr_scatola > $this->buste_min and isset($this->min_scatole_per_bancale))) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($i + 1 == $elem_array_e) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where progr >= $progr_start and progr <= $progr"))
                return false;
        }
        $bacino_precedente = $a_bacino_o[$i];##bacino
        $i++;
    }
}
$n_plico++;
if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where idplico is null"))
    return false;

unset($a_progr_o);
```

**DOPO:**
```php
foreach ($res as &$riga) {
    $riga["progr"] = trim(strtoupper($riga["progr"]));
    $riga["bacino"] = trim(strtoupper($riga["bacino"]));
}
unset($riga);

$progr_start = 1;
$progr_scatola = 0;
$elem_array_e = count($res);
$bacino_precedente = "";
if ($elem_array_e > 0) {
    foreach ($res as $i => $riga) {
        $progr = $riga["progr"];
        $progr_scatola++;
        if (($progr_scatola > $this->buste_max) || ($riga["bacino"] != $bacino_precedente and $progr_scatola > $this->buste_min and isset($this->min_scatole_per_bancale))) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where progr >= $progr_start and progr < $progr"))
                return false;
            $progr_scatola = 1;
            $progr_start = $progr;
        } elseif ($i + 1 == $elem_array_e) {
            $n_plico++;
            if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where progr >= $progr_start and progr <= $progr"))
                return false;
        }
        $bacino_precedente = $riga["bacino"];
    }
}
$n_plico++;
if(!$this->gestione_db->esegui_query("update `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` set idplico = '$n_plico', criterio = 'E' where idplico is null"))
    return false;

unset($res);
```

Effetto complessivo (blocchi A-E): si elimina il picco di memoria "doppio" (dati completi in `$res` + 6 array scalari duplicati) per ognuno dei 5 blocchi, sostituendo in tutti i casi gli array `_o` con lettura/scrittura diretta su `$res`/`$riga`.

---

### 3) Selezionare solo le colonne necessarie (righe 142, 233, 318, 400, 504)

**PRIMA** (es. riga 142):
```php
if( ! $res = $this->gestione_db->preleva_da_db("select * from `ordinati_a_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```

**DOPO** (proposto):
```php
if( ! $res = $this->gestione_db->preleva_da_db("select progr, cap, localita, prov, bacino, tariffa from `ordinati_a_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```
Stessa logica applicata a tutte le altre query `select *` della funzione:

**Riga 233 (`ordinati_b`) — PRIMA:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select * from `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```
**DOPO:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select progr, cap, localita, prov, bacino, tariffa from `{$this->database_ordinamenti}`.`ordinati_b_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```

**Riga 318 (`ordinati_c`) — PRIMA:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select * from `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```
**DOPO:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select progr, cap, localita, prov, bacino, tariffa from `{$this->database_ordinamenti}`.`ordinati_c_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```

**Riga 400 (`ordinati_d`) — PRIMA:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select * from `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```
**DOPO:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select progr, cap, localita, prov, bacino, tariffa from `{$this->database_ordinamenti}`.`ordinati_d_{$tabella}_{$this->id_flusso}` order by progr"))
    return false;
```

**Riga 504 (`ordinati_e`) — PRIMA:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select * from `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` order by progr"))##bacino
    return false;
```
**DOPO:**
```php
if( ! $res = $this->gestione_db->preleva_da_db("select progr, bacino from `{$this->database_ordinamenti}`.`ordinati_e_{$tabella}_{$this->id_flusso}` order by progr"))##bacino
    return false;
```

Si esclude sempre `c1 char(36)`, non usato nei cicli PHP (serve solo per l'INSERT finale via SQL puro, righe 545-556).

### Alternativa considerata (da citare, non raccomandata nel piano)
Query non bufferizzate (`PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false`) per evitare di materializzare l'intero resultset in `$res`: richiederebbe modificare `preleva_da_db()` in `Gestione_db.php` per esporre un cursore/generator, quindi impatto più ampio del previsto — da segnalare come possibile step futuro, non da sviluppare in questo piano.

### File Structure
- Nuovo file: `documenti/15_PIANO_AZIONE_OTTIMIZZAZIONE_ORDINAMENTO_TARGET.md` (unico file creato/modificato in questo task).
- Nessun file `.php` del progetto viene toccato.

# Delivery Steps

###   Step 1: Redigere Problema rilevato e Analisi Tecnica
Il documento 15 contiene le sezioni introduttive che inquadrano il problema, sullo stesso modello del piano 14.
- Scrivere la sezione "Problema rilevato": riepilogo del collo di bottiglia N+1 query (righe 111-114) e della duplicazione dei dati in array paralleli nei 5 blocchi (A-E).
- Scrivere la sezione "Analisi Tecnica": citare i numeri di riga esatti di `Elaborazione_postale_target.php` per ciascun blocco coinvolto (90-119, 144-202, 236-290, 321-372, 403-449, 504-542) e il comportamento di `preleva_da_db()` in `Gestione_db.php` (righe 288-343).
- Indicare esplicitamente che nessun file del progetto sarà modificato.

###   Step 2: Documentare la soluzione 1: batch UPDATE via CASE WHEN
Il documento contiene il blocco PRIMA/DOPO completo per eliminare la query UPDATE per singolo record.
- Riportare fedelmente il codice PRIMA (righe 90-118 di `Elaborazione_postale_target.php`).
- Scrivere il codice DOPO proposto: accumulo di `idr`/`bacino`/`tariffa` in array temporanei, costruzione di una `UPDATE ... SET bacino = CASE idr WHEN ... END, tariffa = CASE idr WHEN ... END WHERE idr IN (...)` a batch (es. 2000 record), flush finale sull'ultimo batch parziale.
- Spiegare l'impatto quantitativo: da 800.000 a circa 400 query con batch_size=2000.

###   Step 3: Documentare la soluzione 2: normalizzazione in place con riferimenti & nei blocchi A-E
Il documento contiene il blocco PRIMA/DOPO che elimina gli array paralleli usando correttamente `foreach (... as &$riga)`.
- Riportare il codice PRIMA per il blocco A (righe 144-202) come caso di riferimento.
- Scrivere il codice DOPO: singolo `foreach ($res as &$riga)` che normalizza (`trim`/`strtoupper`) i campi direttamente dentro `$res`, seguito da `unset($riga)`, poi il ciclo di calcolo plichi che legge da `$riga`/`$res[$i-1]` invece che dagli array `_o`.
- Ripetere lo stesso pattern adattato per i blocchi B (righe 236-290, con la regola speciale EU→PROV/CITT), C (321-372), D (403-449) ed E (507-542, solo progr/bacino).
- Includere una nota che spiega perché qui l'uso di `&` è corretto ed efficace (si scrive in-place), a differenza dei cicli di sola lettura discussi nell'analisi precedente.

###   Step 4: Documentare la soluzione 3: riduzione delle colonne selezionate
Il documento contiene il blocco PRIMA/DOPO per sostituire `select *` con le sole colonne necessarie.
- Riportare il codice PRIMA e DOPO per la query di riga 142 (`ordinati_a`), 233 (`ordinati_b`), 318 (`ordinati_c`), 400 (`ordinati_d`) e 504 (`ordinati_e`).
- Specificare per ciascuna query l'elenco minimo di colonne necessarie ai cicli PHP successivi (escludendo sempre `c1`).
- Aggiungere la sezione "Alternativa considerata (non raccomandata)" sulle query non bufferizzate PDO, spiegando perché non viene inclusa come modifica proposta in questo piano.

###   Step 5: Completare e salvare il documento finale
Il file `documenti/15_PIANO_AZIONE_OTTIMIZZAZIONE_ORDINAMENTO_TARGET.md` è creato e completo con tutte le sezioni.
- Scrivere la sezione "Test e Validazione": checklist manuale per verificare che il numero di plichi/criteri prodotti dal codice DOPO coincida con quello del codice PRIMA su un set di dati di prova.
- Scrivere la tabella "Riepilogo Modifiche" con file coinvolto, tipo di modifica proposta per ciascuna delle 3 soluzioni e rischio stimato.
- Scrivere le "Note Finali" ribadendo che il codice è solo proposto e nessun file `.php` del progetto è stato modificato.
- Salvare il documento in `documenti/15_PIANO_AZIONE_OTTIMIZZAZIONE_ORDINAMENTO_TARGET.md`, seguendo la struttura del piano 14 già presente nella stessa cartella.