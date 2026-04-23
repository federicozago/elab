<template>
  <!--A. Centralizzazione dello stile (DRY - Don't Repeat Yourself)
Immagina di avere 50 input nel tuo progetto. Se un giorno decidessi che tutti gli input devono essere outlined, dense e con un colore specifico, dovresti modificare 50 file. Con BaseInput, lo cambi in un unico punto
Puoi nascondere la complessità di Quasar. Invece di dover ricordare ogni volta tutte le proprietà di q-input, usi un'interfaccia più semplice e pulita che hai creato tu, esponendo solo quello che ti serve davvero.
-->

  <!-- q-pb-md aggiunge un po' di spazio vuoto sotto il componente per evitare che i campi siano appiccicati l'uno all'altro.-->
  <div class="form-field q-pb-md">
    <div class="q-mb-xs text-caption">{{ label }}</div>
    <q-option-group
      v-model="model"
      :options="opzioniFormattate"
      :rules="rules"
      v-bind="$attrs"
      type="radio"
      inline
    />
  </div>
  <!--
    Grazie all'uso di v-bind="$attrs" che abbiamo inserito nel tuo BaseInput.vue: Tutto quello che scrivi su BaseInput (come type="number", maxlength="50", step="1", ecc.) viene "passato" automaticamente al q-input interno di Quasar.
    outlined: Disegna un bordo completo attorno all'input (invece della sola linea in basso).
dense: Riduce l'altezza dell'input e i margini interni, rendendo il form più compatto (molto utile se hai tanti campi).
hide-bottom-space: Normalmente Quasar lascia uno spazio vuoto sotto l'input per mostrare eventuali messaggi di errore. Se non ci sono errori, quello spazio rimane vuoto e "allunga" il form inutilmente. Questa proprietà nasconde quello spazio, facendolo apparire solo se c'è effettivamente un errore da mostrare.-->
</template>

<script setup>
//defineProps (Sola Lettura): Serve per ricevere dati dal padre che il figlio non deve modificare. È come un regalo che ricevi: puoi guardarlo e usarlo, ma non puoi cambiarlo per chi te lo ha regalato.
//defineModel (Bidirezionale): Serve per creare un legame "scrivibile". Se il figlio cambia il valore, quel valore cambia automaticamente anche nel padre. È quello che si usa quasi sempre per gli input dei form (v-model), perché l'obiettivo è proprio che il componente scriva dei dati nel formData del genitore.
import { computed } from 'vue'

const model = defineModel()
const props = defineProps({
  label: String,
  elementi: { type: Array, default: () => [] },
  rules: { type: Array, default: () => [] },
})

const opzioniFormattate = computed(()=>{
  return props.elementi.map(el=>({
    label:el,value:el
  }))
})
</script>
