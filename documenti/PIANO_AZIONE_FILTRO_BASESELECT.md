### Piano d'Azione: Implementazione Filtro Testuale in BaseSelect.vue

Questo documento descrive le modifiche necessarie per aggiungere la funzionalità di ricerca (filtro) nel componente `BaseSelect.vue`, utilizzando le proprietà native di Quasar `q-select`.

#### Obiettivo
Permettere all'utente di digitare del testo all'interno del componente `BaseSelect` per filtrare le opzioni disponibili nel menu a tendina.

#### Modifiche Proposte

1.  **Template (`q-select`)**:
    *   Aggiunta prop `use-input`: permette l'inserimento di testo.
    *   Aggiunta prop `input-debounce="0"`: per una risposta immediata al filtro.
    *   Aggiunta prop `:options="filteredOptions"`: per visualizzare le opzioni filtrate invece di quelle originali.
    *   Aggiunta evento `@filter="filterFn"`: per gestire la logica di filtraggio.
    *   Aggiunta slot `no-option`: per mostrare un messaggio quando non ci sono risultati.

2.  **Script (`<script setup>`)**:
    *   Aggiunta della prop `options` in `defineProps` (o ricezione tramite `$attrs`). Dato che `BaseSelect` usa `v-bind="$attrs"`, le opzioni arrivano normalmente dal genitore, ma per filtrarle localmente è meglio gestirle esplicitamente.
    *   Creazione di una ref `filteredOptions` per memorizzare le opzioni filtrate.
    *   Implementazione della funzione `filterFn`.

#### Codice Completo Proposto (`BaseSelect.vue`)

```vue
<template>
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
```

#### Note Tecniche
*   **v-bind="$attrs"**: Continua a funzionare, ma abbiamo esplicitato `options` nei props per poterlo manipolare nella funzione di filtro.
*   **v.label || v**: La logica di filtro gestisce sia array di stringhe `['A', 'B']` sia array di oggetti `[{label: 'A', value: 1}]`.
*   **fill-input e hide-selected**: Queste proprietà sono spesso usate con `use-input` per migliorare l'esperienza utente durante il filtraggio (opzionali a seconda del design desiderato).
