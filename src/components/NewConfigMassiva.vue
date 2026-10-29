<template>
  <h6>Configurazioni specifiche per Massiva</h6>
  <div class="form-field">
    <!-- model conterrà massiva, è la variabile che il padre passa alla pagine figlio. questa variabile viene usata nel formData della pagina padre per identificare i campi del figlio -->

    <BaseInput
      v-model="model.peso_scatola_min"
      label="Peso scatola minimo (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(7000)]"
    />
    <BaseInput
      v-model="model.peso_scatola_max"
      label="Massimo peso scatola (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(7000)]"
    />
    <BaseInput
      v-model="model.max_scatole_per_bancale"
      label="Scatole per bancale (max) (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(500)]"
    >
      <q-tooltip
        >La massiva non ha l'ordinamento in bancali: le scatole ordinate vengono divise in bancali
        di al massimo questo numero di scatole (come per il Time, di norma 56). Il bancale è
        indispensabile per l'id bancale (HU) della DU</q-tooltip
      >
    </BaseInput>
  </div>
</template>

<script setup>
import BaseInput from 'components/forms/BaseInput.vue'
import { required, minValue, maxValue } from 'src/composables/rules.js'

//defineModel(): È una macro di Vue 3.4+ che crea automaticamente un legame bidirezionale con il v-model del padre. In questo modo, model.sottoProdotto aggiornerà correttamente formData.target nel padre senza violare le regole di Vue.
const model = defineModel({
  type: Object,
})
</script>
<style scoped></style>
