<template>
  <h6>Configurazioni specifiche per Target</h6>
  <div class="form-field">
    <!-- model conterrà target, è la variabile che il padre passa alla pagine figlio. questa variabile viene usata nel formData della pagina padre per identificare i campi del figlio -->
    <BaseSelect
      v-model="model.prodotto_target"
      label="Sotto prodotto (*)"
      id="sotto-prodotto"
      :options="['BASIC', 'CREATIVE']"
      :rules="[required]"
    />

    <BaseInput
      v-model="model.buste_min"
      label="Minimo pezzi (*)"
      type="number"
      min="0"
      max="1000"
      :rules="[required, minValue(1), maxValue(1000)]"
    />
    <BaseInput
      v-model="model.buste_max"
      label="Massimo pezzi (*)"
      type="number"
      min="0"
      max="1000"
      :rules="[required, minValue(1), maxValue(1000)]"
    />

    <BaseRadio
      :elementi="['Plichi', 'Scatole']"
      label="Tipo composizione (*)"
      v-model="model.plichi"
      :rules="[required]"
    />

    <BaseToggle label="Etichette uppate" v-model="model.etichetta_n_up" />

    <BaseRadio
      label="N etichette per foglio (*)"
      v-model="model.etichette_per_foglio"
      :rules="[required]"
      :elementi="[2, 3]"
    />

    <BaseToggle label="Contiene gadget" v-model="model.contiene_gadget">
      <q-tooltip>Compare solo nelle etichette bancale</q-tooltip>
    </BaseToggle>
  </div>
</template>

<script setup>
import BaseSelect from './forms/BaseSelect.vue'
import BaseInput from 'components/forms/BaseInput.vue'
import { required, minValue, maxValue } from 'src/composables/rules.js'
import BaseToggle from 'components/forms/BaseToggle.vue'
import BaseRadio from 'components/forms/BaseRadio.vue'

//defineModel(): È una macro di Vue 3.4+ che crea automaticamente un legame bidirezionale con il v-model del padre. In questo modo, model.sottoProdotto aggiornerà correttamente formData.target nel padre senza violare le regole di Vue.
const model = defineModel({
  type: Object,
})
</script>
<style scoped></style>
