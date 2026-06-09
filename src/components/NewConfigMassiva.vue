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
