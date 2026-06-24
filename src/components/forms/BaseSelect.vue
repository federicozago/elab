<template>
  <!--A. Centralizzazione dello stile (DRY - Don't Repeat Yourself)
Immagina di avere 50 input nel tuo progetto. Se un giorno decidessi che tutti gli input devono essere outlined, dense e con un colore specifico, dovresti modificare 50 file. Con BaseInput, lo cambi in un unico punto
Puoi nascondere la complessità di Quasar. Invece di dover ricordare ogni volta tutte le proprietà di q-input, usi un'interfaccia più semplice e pulita che hai creato tu, esponendo solo quello che ti serve davvero.
-->
  <div class="form-field q-pb-md">
    <q-select
      :label="label"
      v-bind="$attrs"
      v-model="model"
      :rules="rules"
      outlined
      dense
      hide-bottom-space
      use-input
      fill-input
      hide-selected
      input-debounce="0"
      :options="filteredOptions"
      @filter="filterFn"
    >
      <template v-slot:no-option>
        <q-item>
          <q-item-section class="text-grey">
            Nessun risultato
          </q-item-section>
        </q-item>
      </template>
      <slot></slot>
    </q-select>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const model = defineModel()
const props = defineProps({
  label: String,
  rules: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] }
})

const filteredOptions = ref(props.options)

// Sincronizza filteredOptions se le opzioni originali cambiano
watch(() => props.options, (newOptions) => {
  filteredOptions.value = newOptions
})

const filterFn = (val, update) => {
  if (val === '') {
    update(() => {
      filteredOptions.value = props.options
    })
    return
  }

  update(() => {
    const needle = val.toLowerCase()
    filteredOptions.value = props.options.filter(
      v => (v.label || v).toLowerCase().indexOf(needle) > -1
    )
  })
}
</script>
