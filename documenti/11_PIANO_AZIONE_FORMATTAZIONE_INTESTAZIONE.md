# PIANO 11 - FORMATTAZIONE INTESTAZIONE (STRING TO ARRAY)

Il presente piano descrive le modifiche necessarie per risolvere l'errore di validazione delle prop nel componente `BaseSelect`. Il problema nasce dal fatto che il backend restituisce l'intestazione come una stringa separata da pipe (`|`) invece di un array, mentre il componente Vue si aspetta un array.

## 1. Analisi del Problema
L'errore `[Vue warn]: Invalid prop: type check failed for prop "options". Expected Array, got String` indica che la variabile `intestazione` viene popolata con una stringa. Questo accade nella funzione `baseDatiCambiata` quando riceve i dati da `/preleva_base_dati.php`.

## 2. Modifiche Proposte (Frontend)

### 2.1 Aggiornamento funzione `baseDatiCambiata` in `creazione_BaseDati.vue`
La soluzione più semplice e robusta consiste nel trasformare la stringa in array direttamente nel frontend. Modificheremo la logica di assegnazione intorno alla riga 162.

**Codice da modificare:**
```javascript
// Vecchio codice (Piano 10)
if (response.data.base_dati.intestazione) {
  intestazione.value = response.data.base_dati.intestazione
} else {
  intestazione.value = []
}

// Nuovo codice (Piano 11)
if (response.data.base_dati.intestazione) {
  const rawIntestazione = response.data.base_dati.intestazione
  // Se è una stringa, la divido usando il separatore '|'
  if (typeof rawIntestazione === 'string') {
    intestazione.value = rawIntestazione.split('|').filter(item => item !== '')
  } else if (Array.isArray(rawIntestazione)) {
    intestazione.value = rawIntestazione
  } else {
    intestazione.value = []
  }
} else {
  intestazione.value = []
}
```

## 3. Modifiche Proposte (Backend - Opzionale ma consigliata)
Sebbene la modifica frontend risolva il problema, per pulizia architetturale sarebbe opportuno che l'endpoint `preleva_base_dati.php` restituisse già il dato nel formato corretto.

**Esempio di modifica in PHP (preleva_base_dati.php):**
```php
// Prima dell'invio del JSON
if (isset($base_dati['intestazione']) && is_string($base_dati['intestazione'])) {
    $base_dati['intestazione'] = explode('|', $base_dati['intestazione']);
}
```

## 4. Risultato Atteso
1. Scomparsa dell'errore `Invalid prop: type check failed` in console.
2. Corretta visualizzazione dei badge dell'intestazione.
3. Popolamento corretto delle tendine `BaseSelect` (CAP, Località, Provincia) con i singoli nomi dei campi estratti dalla stringa.
