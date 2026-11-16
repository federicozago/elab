### PIANO DI AZIONE 13: MIGLIORAMENTO SUGGERIMENTI MULTI-PAROLA IN BASEINPUT

**Problema rilevato:**
Il componente `BaseInput.vue` mostra i suggerimenti solo per la prima parola inserita. Se l'utente inserisce uno spazio e inizia a scrivere una nuova parola, il sistema di suggerimento smette di funzionare perché tenta di confrontare l'intero contenuto dell'input con le parole suggerite.

**Obiettivo:**
Modificare la logica di suggerimento affinché venga presa in considerazione solo l'ultima parola che si sta scrivendo.

---

### Modifiche Proposte

#### 1. Aggiornamento della funzione `updateSuggestion`
Dobbiamo estrarre l'ultima parola inserita e confrontarla con l'array `suggestions`.

```javascript
function updateSuggestion(val) {
  if (!val || props.suggestions.length === 0) {
    currentSuggestion.value = ''
    return
  }

  // Dividiamo il testo in parole e prendiamo l'ultima
  const words = val.split(' ')
  const lastWord = words[words.length - 1]

  if (!lastWord) {
    currentSuggestion.value = ''
    return
  }

  // Cerchiamo un match per l'ultima parola
  const match = props.suggestions.find(s =>
    s.toLowerCase().startsWith(lastWord.toLowerCase()) &&
    s.toLowerCase() !== lastWord.toLowerCase()
  )

  currentSuggestion.value = match || ''
}
```

#### 2. Aggiornamento della funzione `handleKeydown`
Quando l'utente preme "Tab", dobbiamo sostituire solo l'ultima parola con il suggerimento accettato, mantenendo il resto del testo.

```javascript
function handleKeydown(e) {
  if (e.key === 'Tab' && currentSuggestion.value) {
    e.preventDefault()
    
    const words = model.value.split(' ')
    words[words.length - 1] = currentSuggestion.value
    
    model.value = words.join(' ')
    currentSuggestion.value = ''
  }
}
```

---

### Note Tecniche
- La modifica utilizza `split(' ')` per identificare le parole separate da spazio.
- Viene mantenuta la compatibilità con il comportamento precedente per la prima parola.
- Il resto della struttura del componente rimane invariato.
