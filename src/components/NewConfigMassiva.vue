<template>
  <h6>Configurazioni specifiche per Massiva</h6>
  <div class="form-field">
    <!-- model conterrà massiva, è la variabile che il padre passa alla pagine figlio. questa variabile viene usata nel formData della pagina padre per identificare i campi del figlio -->

    <BaseInput
      v-model="model.peso_scatola_min"
      label="Peso scatola minimo (gr) (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(7000)]"
    >
      <q-tooltip
        >In grammi (es. 5000 = 5 kg). Sotto questo peso la scatola è considerata riempita
        parzialmente. Il peso è quello delle buste, senza la tara della scatola</q-tooltip
      >
    </BaseInput>
    <BaseInput
      v-model="model.peso_scatola_max"
      label="Massimo peso scatola (gr) (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(7000)]"
    >
      <q-tooltip
        >In grammi (max 7000, cioè 7 kg). Una scatola viene chiusa quando le buste si avvicinano a
        questo peso. Il peso è quello delle buste, senza la tara della scatola</q-tooltip
      >
    </BaseInput>
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
    <BaseSelect
      v-model="model.tipo_pagamento"
      label="Tipo di pagamento (*)"
      :options="['contestuale', 'anticipato', 'posticipato']"
      :rules="[required]"
    >
      <q-tooltip
        >Compare nella SMA (distinta di spedizione), dove "posticipato" corrisponde alla voce
        "Differito"</q-tooltip
      >
    </BaseSelect>
    <BaseInput
      v-model="model.indirizzo_cliente"
      label="Indirizzo cliente"
      :rules="[maxLength(255)]"
    >
      <q-tooltip>Facoltativo. Compare nella SMA (distinta di spedizione)</q-tooltip>
    </BaseInput>
    <BaseInput
      v-model="model.telefono_cliente"
      label="Telefono cliente"
      :rules="[maxLength(30)]"
    >
      <q-tooltip>Facoltativo. Compare nella SMA (distinta di spedizione)</q-tooltip>
    </BaseInput>
  </div>
</template>

<script setup>
import BaseInput from 'components/forms/BaseInput.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import { required, minValue, maxValue, maxLength } from 'src/composables/rules.js'

//defineModel(): È una macro di Vue 3.4+ che crea automaticamente un legame bidirezionale con il v-model del padre. In questo modo, model.sottoProdotto aggiornerà correttamente formData.target nel padre senza violare le regole di Vue.
const model = defineModel({
  type: Object,
})
</script>
<style scoped></style>
