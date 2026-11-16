# PIANO 12 - VISIBILITÀ SQL E NOME CONFIGURAZIONE

Il presente piano descrive le modifiche da apportare alla pagina `creazione_lavoro.vue` per gestire correttamente la visibilità del campo SQL vista e il pre-popolamento del nome della nuova configurazione.

## 1. Analisi delle modifiche

### 1.1 Visibilità Campo "Sql vista"
Attualmente il campo "Sql vista" è sempre visibile all'interno del ciclo delle elaborazioni. La richiesta prevede che questo campo sia visibile solo se è attivo il toggle "Multi elaborazione" (`isMultiElaborazione`).

### 1.2 Pre-popolamento "Nome nuova configurazione"
Quando si clicca su "Nuova configurazione", il sistema apre un dialog che contiene il form di creazione configurazione. Se il nome dell'elaborazione corrente è "elab", il campo del nome nella nuova configurazione deve essere vuoto, altrimenti può ereditare il nome dell'elaborazione.

## 2. Modifiche a `src/pages/creazione_lavoro.vue`

### 2.1 Modifica del Template (Visibilità SQL)
Aggiungere la direttiva `v-if="isMultiElaborazione"` al componente `BaseInput` relativo a "Sql vista".

**Codice da modificare (intorno alla riga 142):**
```vue
<BaseInput
  v-if="isMultiElaborazione"
  v-model="elaborazione.where"
  label="Sql vista (solo contenuto where)"
  :suggestions="baseDatiSelezionata?.intestazione.split('|')"
  :rules="formData.elaborazioni.length > 1 ? [required, sqlSafe] : [sqlSafe]"
>
  <q-tooltip :delay="100" :offset="[0, 20]">
    {{ baseDatiSelezionata?.intestazione }}
  </q-tooltip>
</BaseInput>
```

### 2.2 Modifica della Logica (Nome Configurazione)
Aggiornare la funzione `apriDialogConfigurazione` per gestire il valore da passare al form della configurazione.

**Codice da modificare (intorno alla riga 302):**
```javascript
function apriDialogConfigurazione(indexElaborazione) {
  // Prelevo il nome dell'elaborazione corrente
  const nomeElab = formData.value.elaborazioni[indexElaborazione].nome_elaborazione
  
  // Se il nome è "elab", passo stringa vuota, altrimenti passo il nome attuale
  nomeElaborazioneScelta.value = nomeElab === 'elab' ? '' : nomeElab
  
  // Imposto il tipo di spedizione per la configurazione
  tipoSpedizioneScelta.value = formData.value.elaborazioni[indexElaborazione].tipo_spedizione
  
  dialogConfigurazioneVisible.value = true
  indiceElaborazioneInCorso.value = indexElaborazione
}
```

*Nota: La riga 302 originale non valorizzava `nomeElaborazioneScelta.value`, che veniva invece aggiornata solo in `tipoSpedizioneCambiato`. Spostando o aggiungendo questa logica in `apriDialogConfigurazione`, garantiamo che il valore sia corretto al momento dell'apertura del dialog.*

## 3. Risultato Atteso
1. Il campo "Sql vista" scompare se "Multi elaborazione" è disattivato.
2. Cliccando su "Nuova configurazione", se l'elaborazione si chiama "elab", il campo "Nome breve configurazione" nel form sarà vuoto.
3. Se l'elaborazione ha un nome diverso (es. in modalità multi-elaborazione), quel nome verrà suggerito nel form della configurazione.
