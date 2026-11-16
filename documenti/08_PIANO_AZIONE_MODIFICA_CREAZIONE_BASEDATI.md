# PIANO 08 - MODIFICA PAGINA CREAZIONE BA SEDATI

Il presente piano descrive le modifiche da apportare alla pagina `creazione_BaseDati.vue` per introdurre la distinzione tra file CSV/Testo e file Excel, gestendo la visualizzazione condizionale del separatore e la visualizzazione dell'intestazione prelevata dal file.

## 1. Modifiche al Template

### 1.1 Importazione BaseRadio
Aggiungere l'importazione del componente `BaseRadio` nello script setup.

```javascript
import BaseRadio from 'components/forms/BaseRadio.vue'
```

### 1.2 Inserimento Flag BaseRadio
Inserire il componente `BaseRadio` appena prima del `BaseInput` relativo al separatore (riga 40 circa).

```vue
<BaseRadio
  v-model="formData.tipo_file"
  label="Tipo di file"
  :elementi="['CSV/Testo', 'Excel']"
  :rules="[required]"
/>
```

### 1.3 Visualizzazione Condizionale Separatore
Modificare il componente `BaseInput` del separatore per essere visualizzato solo se `tipo_file` è 'CSV/Testo'.

```vue
<BaseInput
  v-if="formData.tipo_file === 'CSV/Testo'"
  v-model="formData.separatore"
  label="Separatore CSV/Testo"
  :rules="[required]"
/>
```

### 1.4 Visualizzazione Intestazione
Aggiungere un box testuale per mostrare l'intestazione se la variabile `intestazione` non è vuota. Questo box va inserito prima dei selettori dei campi (CAP, Località, Provincia).

```vue
<div v-if="intestazione.length > 0" class="q-pa-sm q-mb-md bg-grey-2 rounded-borders border-grey">
  <div class="text-weight-bold q-mb-xs">Intestazione rilevata:</div>
  <div class="row q-gutter-xs">
    <q-badge v-for="campo in intestazione" :key="campo" color="primary" outline>
      {{ campo }}
    </q-badge>
  </div>
</div>
```

## 2. Modifiche allo Script Setup

### 2.1 Aggiornamento formData
Inizializzare `tipo_file` nell'oggetto `formData`.

```javascript
const formData = ref({
  nome_base_dati: '',
  file_base_dati: null,
  campo_cap: '',
  campo_localita: '',
  campo_provincia: '',
  intestazione_si_no: null,
  separatore: ';',
  tipo_file: 'CSV/Testo', // Nuovo campo
  test: null,
})
```

### 2.2 Aggiornamento Reset Form
Aggiornare l'oggetto `formData` all'interno della funzione `confermaEliminaBaseDati` dopo l'eliminazione.

```javascript
formData.value = {
  nome_base_dati: '',
  file_base_dati: null,
  campo_cap: '',
  campo_localita: '',
  campo_provincia: '',
  intestazione_si_no: null,
  separatore: ';',
  tipo_file: 'CSV/Testo',
  test: null,
}
```

## Codice Completo (Blocchi modificati)

### Script Setup (Import e Data)
```javascript
// ... import esistenti ...
import BaseRadio from 'components/forms/BaseRadio.vue' // <--- Aggiunto

// ...
const formData = ref({
  nome_base_dati: '',
  file_base_dati: null,
  campo_cap: '',
  campo_localita: '',
  campo_provincia: '',
  intestazione_si_no: null,
  separatore: ';',
  tipo_file: 'CSV/Testo', // <--- Aggiunto
  test: null,
})
```

### Template (Form)
```vue
<BaseForm
  :formData="formData"
  @submit="creaBaseDati"
  labelInvia="Crea base dati"
  :loading="isSubmitting"
>
  <BaseInput
    v-model="formData.nome_base_dati"
    label="Nome nuova base dati"
    :rules="[required, maxLength(100), notInArray(basiDati)]"
  />

  <!-- NUOVO FLAG TIPO FILE -->
  <BaseRadio
    v-model="formData.tipo_file"
    label="Tipo di file"
    :elementi="['CSV/Testo', 'Excel']"
    :rules="[required]"
  />

  <BaseInput
    v-if="formData.tipo_file === 'CSV/Testo'"
    v-model="formData.separatore"
    label="Separatore CSV/Testo"
    :rules="[required]"
  />

  <BaseToggle
    label="Intestazione presente"
    v-model="formData.intestazione_si_no"
    :rules="[required]"
  />

  <BaseFile
    v-model="formData.file_base_dati"
    label="Seleziona un file"
    :rules="[required]"
    @update:model-value="uploadFile"
    :loading="isUploading"
    v-if="formData.intestazione_si_no !== null"
  />

  <!-- VISUALIZZAZIONE INTESTAZIONE RILEVATA -->
  <div v-if="intestazione.length > 0" class="q-pa-sm q-mb-md bg-grey-2 rounded-borders border-grey-4 shadow-1">
    <div class="text-caption text-grey-8 q-mb-xs">Intestazione rilevata nel file:</div>
    <div class="row q-gutter-xs">
      <q-badge v-for="campo in intestazione" :key="campo" color="secondary" label-color="white" class="q-pa-xs">
        {{ campo }}
      </q-badge>
    </div>
  </div>

  <BaseSelect
    :options="intestazione"
    label="Campo Cap"
    v-model="formData.campo_cap"
    :disable="!intestazione.length"
    :rules="[required]"
    v-if="formData.intestazione_si_no !== null"
  />
  <!-- ... restanti BaseSelect ... -->
</BaseForm>
```
