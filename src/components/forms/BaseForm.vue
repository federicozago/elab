<template>
  <q-form ref="formRef" @validation-failed="onValidationFailed">
    <!--@change intercetta gli eventi nativi del browser.
A cosa serve: Viene scatenato quando un elemento del form perde il focus (evento blur) o quando viene selezionata un'opzione in un menu a tendina.
Perché non basta: Molti componenti Vue (come quelli basati su v-model) aggiornano i dati internamente senza necessariamente scatenare un evento di change standard del DOM ogni volta che digiti un singolo carattere.
per questo aggiungo il watch-->
    <slot></slot
    ><!-- Quando scrivi <BaseForm><q-input ... /></BaseForm>, Vue prende quell'input e lo "inietta" esattamente dove hai messo il tag <slot> nel file BaseForm.vue. -->

    <BaseBtn
      @click="onSubmit"
      type="submit"
      :label="labelInvia ? labelInvia : 'Invia'"
      :class="{ 'pulse-error': showValidationError }"
    />

    <BaseBtn @click="onReset" label="Reset" v-if="showResetButton" />
  </q-form>
</template>

<script setup>
import { ref } from 'vue'
import BaseBtn from 'components/forms/BaseBtn.vue'

/*
defineEmits serve a dichiarare quali eventi personalizzati il componente può lanciare verso il padre.
•Come si comporta: Crea una funzione (emit) che puoi chiamare per avvisare il genitore che è successo qualcosa.
•Perché in BaseForm: Dato che stiamo "nascondendo" il q-form dentro BaseForm, il componente padre non può più sentire direttamente l'evento @submit di Quasar. Dobbiamo quindi:
  i.Intercettare il submit di Quasar dentro BaseForm (riga 39).
  ii."Rilanciarlo" al padre usando emit('submit').
•È come se BaseForm facesse da ripetitore di segnale per gli eventi che accadono al suo interno.

Il significato di emit('update:valido', success) => Questa è la sintassi standard di Vue per gestire il v-model personalizzato.
•Significato: Invia un evento al padre dicendo: "Aggiorna la variabile legata a valido con il nuovo valore contenuto in success (true o false)".
v-model:valido="formValido": Nel genitore, questa riga dice a Vue: "Prendi la mia variabile formValido e tienila sincronizzata con la proprietà valido del componente figlio".
•Quando BaseForm esegue la validazione e trova che tutto è ok, emette true. In quel preciso istante, la variabile formValido nel genitore diventa true e il pulsante si abilita.
 */
defineProps({
  formData: { type: Object, required: true },
  labelInvia: { type: String, required: false },
  showResetButton: { type: Boolean, required: false },
})

const emit = defineEmits(['submit', 'update:valido', 'validation-failed', 'reset'])

const formRef = ref(null)

const onSubmit = async () => {
  if (formRef.value) {
    const success = await formRef.value.validate()
    if (success) {
      emit('submit')
    } else {
      onValidationFailed()
      emit('validation-failed')
    }
  }
}

const showValidationError = ref(false)
function onValidationFailed() {
  showValidationError.value = true

  // Rimuovi l'animazione dopo 2 secondi
  setTimeout(() => {
    showValidationError.value = false
  }, 2000)
}

const onReset = () => {
  emit('reset')
}

//Esporre validate e resetValidation significa dire: "Anche se sono un componente chiuso, ti permetto di usare questi due strumenti specifici del q-form che ho dentro". È una buona pratica per rendere il componente riutilizzabile in scenari più complessi.
defineExpose({
  validate: () => formRef.value?.validate(),
  resetValidation: () => formRef.value?.resetValidation(),
  onSubmit: onSubmit,
})
</script>

<style scoped>
@keyframes pulse-red {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(255, 0, 0, 0.7);
    transform: scale(1);
  }
  50% {
    box-shadow: 0 0 0 10px rgba(255, 0, 0, 0);
    transform: scale(1.05);
  }
}

.pulse-error {
  animation: pulse-red 0.5s ease-in-out 4;
  border: 2px solid red !important;
}
</style>
