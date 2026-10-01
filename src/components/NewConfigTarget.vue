<template>
  <h6>Configurazioni specifiche per Target</h6>
  <div class="form-field">
    <!-- model conterrà target, è la variabile che il padre passa alla pagine figlio. questa variabile viene usata nel formData della pagina padre per identificare i campi del figlio -->
    <BaseSelect
      v-model="model.prodotto_target"
      label="Sotto prodotto (*)"
      id="sotto-prodotto"
      :options="['BASIC', 'CREATIVE', 'MAGAZINE']"
      :rules="[required]"
    />

    <BaseSelect
      v-model="model.tipo_formato_creative"
      label="Tipo formato creative (*)"
      id="tipo-formato-creative"
      :options="['NORMALIZZATO','COMPATTO','VOLUMINOSO']"
      :rules="[required]"
      />

    <BaseInput
      v-model="model.buste_min"
      label="Minimo pezzi (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(1000)]"
    />
    <BaseInput
      v-model="model.buste_max"
      label="Massimo pezzi (*)"
      type="number"
      :rules="[required, minValue(1), maxValue(1000)]"
    />

    <BaseRadio
      :elementi="['Plichi', 'Scatole']"
      label="Tipo composizione (*)"
      v-model="model.plichi"
      :rules="[required]"
    />

    <template v-if="props.gestioneBancali && model.plichi === 'Plichi'">
      <BaseInput
        v-model="model.larghezza_busta"
        label="Larghezza busta (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      />
      <BaseInput
        v-model="model.altezza_busta"
        label="Altezza busta (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      />
      <BaseInput
        v-model="model.profondita_busta"
        label="Profondità busta (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip>Spessore di una singola busta, usato per calcolare l'altezza del plico</q-tooltip>
      </BaseInput>
    </template>

    <template v-if="props.gestioneBancali && model.plichi === 'Scatole'">
      <BaseInput
        v-model="model.larghezza_scatola"
        label="Larghezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      />
      <BaseInput
        v-model="model.lunghezza_scatola"
        label="Lunghezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      />
      <BaseInput
        v-model="model.altezza_scatola"
        label="Altezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      />
    </template>

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

const props = defineProps({
  gestioneBancali: {
    type: Boolean,
    default: false,
  },
})
</script>
<style scoped></style>
