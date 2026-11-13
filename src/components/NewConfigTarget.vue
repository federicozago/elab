<template>
  <h6>Configurazioni specifiche per Target</h6>
  <div class="form-field">
    <!-- model conterrà target, è la variabile che il padre passa alla pagine figlio. questa variabile viene usata nel formData della pagina padre per identificare i campi del figlio -->
    <BaseSelect
      v-model="model.prodotto_target"
      label="Sotto prodotto (*)"
      id="sotto-prodotto"
      :options="['BASIC', 'CREATIVE', 'MAGAZINE', 'NO_PROFIT', 'PUBBL_INFORMATIVA_NO_PROFIT', 'PERIODICO_ROC']"
      :disable="bloccaProdotto"
      :rules="[required]"
    />

    <!-- il tipo formato compare solo sull'etichetta scatola e non serve per gli alias (no profit, periodico...) -->
    <BaseSelect
      v-if="!bloccaProdotto"
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
      >
        <q-tooltip>Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip>
      </BaseInput>
      <BaseInput
        v-model="model.altezza_busta"
        label="Altezza busta (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip>Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip>
      </BaseInput>
      <BaseInput
        v-model="profonditaBustaMm"
        label="Profondità busta (mm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip
          >Spessore di una singola busta in millimetri (es. 2 per 0,2 cm), usato per calcolare l'altezza del plico. Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip
        >
      </BaseInput>
    </template>

    <template v-if="props.gestioneBancali && model.plichi === 'Scatole'">
      <BaseInput
        v-model="model.larghezza_scatola"
        label="Larghezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip>Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip>
      </BaseInput>
      <BaseInput
        v-model="model.lunghezza_scatola"
        label="Lunghezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip>Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip>
      </BaseInput>
      <BaseInput
        v-model="model.altezza_scatola"
        label="Altezza scatola (cm) (*)"
        type="number"
        :rules="[required, minValue(0)]"
      >
        <q-tooltip>Obbligatorio con la prenotazione con DU (datamatrix) e con la gestione bancali: serve a calcolare quante scatole stanno in un bancale (id bancale della DU)</q-tooltip>
      </BaseInput>
    </template>

    <BaseToggle label="Contiene gadget" v-model="model.contiene_gadget">
      <q-tooltip>Compare solo nelle etichette bancale</q-tooltip>
    </BaseToggle>
  </div>
</template>

<script setup>
import BaseSelect from './forms/BaseSelect.vue'
import BaseInput from 'components/forms/BaseInput.vue'
import { computed } from 'vue'
import { required, minValue, maxValue } from 'src/composables/rules.js'
import BaseToggle from 'components/forms/BaseToggle.vue'
import BaseRadio from 'components/forms/BaseRadio.vue'

//defineModel(): È una macro di Vue 3.4+ che crea automaticamente un legame bidirezionale con il v-model del padre. In questo modo, model.sottoProdotto aggiornerà correttamente formData.target nel padre senza violare le regole di Vue.
const model = defineModel({
  type: Object,
})

// la profondità busta si inserisce in millimetri (le altre dimensioni sono in cm), ma nel form e a db resta in centimetri: la conversione è solo qui
const profonditaBustaMm = computed({
  get: () => {
    const cm = model.value.profondita_busta
    return cm === null || cm === undefined || cm === '' ? '' : Number((Number(cm) * 10).toFixed(2))
  },
  set: (mm) => {
    model.value.profondita_busta =
      mm === null || mm === undefined || mm === '' ? null : Number((Number(mm) / 10).toFixed(3))
  },
})

const props = defineProps({
  gestioneBancali: {
    type: Boolean,
    default: false,
  },
  // true quando si è scelto un alias di target (NO_PROFIT, ...): il sotto prodotto è fisso
  bloccaProdotto: {
    type: Boolean,
    default: false,
  },
})
</script>
<style scoped></style>
