### Piano d'Azione: GIF di Caricamento su tutti i pulsanti Submit di BaseForm.vue

Questo documento descrive le modifiche necessarie per visualizzare un indicatore di caricamento (GIF o Spinner) su tutti i pulsanti di tipo "submit" all'interno del componente `BaseForm.vue`. La gestione dello stato di caricamento (`isSubmitting`) viene fatta esplicitamente in ogni pagina/componente genitore per avere il massimo controllo sul ciclo di vita delle chiamate asincrone.

---

#### 1. Obiettivo
Migliorare l'esperienza utente fornendo un feedback visivo standardizzato su tutti i form del progetto durante l'invio dei dati, gestendo lo stato `loading` in modo esplicito tramite variabili locali nelle pagine.

---

#### 2. Modifiche ai Componenti Base

##### A. Modifica di `BaseBtn.vue`
Aggiungeremo il supporto alla proprietà `loading` nativa di Quasar, che disabilita il pulsante e mostra uno spinner.

```vue
<!-- src/components/forms/BaseBtn.vue -->
<template>
  <q-btn 
    :label="label" 
    v-bind="$attrs" 
    :class="$attrs.class" 
    :type="$attrs.type"
    :loading="loading"
  >
    <template v-slot:loading>
      <q-spinner-gears />
    </template>
  </q-btn>
</template>

<script setup>
defineProps({
  label: String,
  loading: Boolean // Nuova prop
})
</script>
```

##### B. Modifica di `BaseForm.vue`
Aggiungeremo la prop `loading` che verrà passata al pulsante di submit principale.

```vue
<!-- src/components/forms/BaseForm.vue -->
<template>
  <q-form ref="formRef" @validation-failed="onValidationFailed">
    <slot></slot>

    <BaseBtn
      @click="onSubmit"
      type="submit"
      :label="labelInvia ? labelInvia : 'Invia'"
      :class="{ 'pulse-error': showValidationError }"
      :loading="loading" 
    />

    <BaseBtn @click="onReset" label="Reset" v-if="showResetButton && !loading" />
  </q-form>
</template>

<script setup>
// ... (altri import) ...

defineProps({
  formData: { type: Object, required: true },
  labelInvia: { type: String, required: false },
  showResetButton: { type: Boolean, required: false },
  loading: { type: Boolean, default: false } // Nuova prop
})

// ... restanti funzioni onSubmit, onReset, ecc ...
</script>
```

---

#### 3. Implementazione nelle Pagine e Componenti

Per ogni file che utilizza `BaseForm`, aggiungeremo una variabile `isSubmitting = ref(false)` e gestiremo il ciclo `try/finally` nelle funzioni di invio.

##### I. `src/pages/IndexPage.vue` (Importa dati)
```vue
<!-- Template -->
<BaseForm 
  :formData="formData" 
  @submit="creaElaborazione" 
  labelInvia="Importa dati"
  :loading="isSubmitting"
>

<!-- Script -->
const isSubmitting = ref(false)

async function creaElaborazione() {
  isSubmitting.value = true
  const uploadData = new FormData()
  // ... (popolamento uploadData) ...
  try {
    await api.post('/crea_elaborazione.php', uploadData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    messaggioPositivo('Elaborazione creata')
    prelevaElaborazioniInCorso()
  } catch (e) {
    gestioneErrore(e, 'Impossibile creare elaborazione - ' + e.response.data.message)
  } finally {
    isSubmitting.value = false
  }
}
```

##### II. `src/pages/creazione_BaseDati.vue`
```vue
<!-- Template -->
<BaseForm 
  :formData="formData" 
  @submit="creaBaseDati" 
  labelInvia="Crea base dati"
  :loading="isSubmitting"
>

<!-- Script -->
const isSubmitting = ref(false)

async function creaBaseDati() {
  isSubmitting.value = true
  const dati = { ...formData.value, intestazione: intestazione.value }
  dati.file_base_dati = dati.file_base_dati.name

  try {
    const response = await api.post('/crea_base_dati.php', dati)
    messaggioPositivo('Base dati creata con successo')
    if (creazioneLavoroInCorso) {
      router.replace({
        path: '/creazione_lavoro/',
        query: {
          id_base_dati: response.data.id_base_dati,
          nome_base_dati: response.data.nome_base_dati,
          intestazione: response.data.intestazione,
        },
      })
    }
  } catch (e) {
    gestioneErrore(e, 'Impossibile creare la base dati - ' + e.response.data.message)
  } finally {
    isSubmitting.value = false
  }
}
```

##### III. `src/pages/creazione_lavoro.vue`
```vue
<!-- Template -->
<BaseForm 
  :formData="formData" 
  @submit="creaLavoro" 
  labelInvia="Salva lavoro"
  :loading="isSubmitting"
>

<!-- Script -->
const isSubmitting = ref(false)

async function creaLavoro() {
  isSubmitting.value = true
  try {
    const response = await api.post(isModifica.value ? '/aggiorna_lavoro.php' : '/crea_lavoro.php', {
      id_base_dati: formData.value.id_base_dati.value,
      nome_lavoro: formData.value.nome_lavoro,
      elaborazioni: formData.value.elaborazioni,
      id_lavoro: isModifica.value ? lavoro.value.value : null,
    })
    messaggioPositivo(isModifica.value ? 'Elaborazione aggiornata' : 'Elaborazione caricata')
    if (!isModifica.value) {
      router.replace({
        path: '/',
        query: { id_lavoro: response.data.id_lavoro, nome_lavoro: formData.value.nome_lavoro },
      })
    }
  } catch (e) {
    gestioneErrore(e, (isModifica.value ? 'Impossibile aggiornare' : 'Impossibile creare') + ' il lavoro')
  } finally {
    isSubmitting.value = false
  }
}
```

##### IV. `src/components/creazione_configurazione.vue`
```vue
<!-- Template -->
<BaseForm
  ref="baseFormRef"
  :formData="formData"
  @submit="inviaDati"
  :labelInvia="configurazioneEdit ? 'Aggiorna Configurazione' : 'Salva Configurazione'"
  :loading="isSubmitting"
>

<!-- Script -->
const isSubmitting = ref(false)

async function inviaDati() {
  isSubmitting.value = true
  const datiDaInviare = { ...formData.value }
  // ... (logica pulizia datiDaInviare) ...
  
  try {
    const endpoint = configurazioneEdit.value ? '/aggiorna_configurazione.php' : '/crea_configurazione.php'
    const response = await api.post(endpoint, datiDaInviare)
    messaggioPositivo('Configurazione ' + (configurazioneEdit.value ? 'aggiornata' : 'creata'))
    emit('saved', {
      value: response.data.id_configurazione,
      label: response.data.nome_configurazione,
    })
  } catch (e) {
    gestioneErrore(e, 'Errore salvataggio configurazione')
  } finally {
    isSubmitting.value = false
  }
}
```

---

#### 4. Vantaggi della Soluzione
1. **Controllo Totale:** Ogni pagina decide esattamente quando attivare e disattivare il caricamento.
2. **Standardizzazione UI:** Il pulsante e il form si comportano sempre allo stesso modo visivamente.
3. **Robustezza:** L'uso di `finally` garantisce che il pulsante torni sempre abilitato anche in caso di errore.
