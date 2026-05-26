<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
      <!-- menu di selezione base dati esistente -->
      <BaseSelect
        v-model="idBaseDati"
        :options="basiDati"
        label="Basi dati create"
        @update:model-value="baseDatiCambiata"
      />

      <h3>Crea base dati</h3>

      <!-- form creazione base dati -->
      <BaseForm :formData="formData" @submit="creaBaseDati" labelInvia="Crea base dati">
        <BaseInput
          v-model="formData.nome_base_dati"
          label="Nome nuova base dati"
          :rules="[required, maxLength(100), notInArray(basiDati)]"
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
          v-if="formData.intestazione_si_no !== ''"
        />

        <BaseSelect
          :options="intestazione"
          label="Campo Cap"
          v-model="formData.campo_cap"
          :disable="!intestazione.length"
          :rules="[required]"
        />
        <BaseSelect
          :options="intestazione"
          label="Campo Località"
          v-model="formData.campo_localita"
          :disable="!intestazione.length"
          :rules="[required]"
        />
        <BaseSelect
          :options="intestazione"
          label="Campo Provincia"
          v-model="formData.campo_provincia"
          :disable="!intestazione.length"
          :rules="[required]"
        />
      </BaseForm>
    </div>
  </q-page>
</template>

<script setup>
import BaseSelect from 'components/forms/BaseSelect.vue'
import BaseInput from 'components/forms/BaseInput.vue'
import BaseFile from 'components/forms/BaseFile.vue'
import { api } from 'boot/axios.js'
import { maxLength, required, notInArray } from 'src/composables/rules.js'
import BaseForm from 'components/forms/BaseForm.vue'
import { onMounted, ref } from 'vue'
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi'
const { gestioneErrore, messaggioPositivo } = useCassettaAttrezzi()
import { useFileStore } from 'src/stores/fileStore'
import { useRoute, useRouter } from 'vue-router'
import BaseToggle from 'components/forms/BaseToggle.vue'
const route = useRoute()
const router = useRouter()

const fileStore = useFileStore()
const intestazione = ref([])
const creazioneLavoroInCorso = route.query?.creazioneLavoroInCorso == 'true' ? true : false
const basiDati = ref([])
const idBaseDati = ref(null)
const isUploading = ref(false)
const formData = ref({
  nome_base_dati: '',
  file_base_dati: null,
  campo_cap: '',
  campo_localita: '',
  campo_provincia: '',
  intestazione_si_no: '',
  test: null,
})

onMounted(() => {
  api
    .post('/preleva_basi_dati.php')
    .then((response) => {
      basiDati.value = response.data.basi_dati
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati - ' + e.response.data.message)
    })
})

function baseDatiCambiata(idBaseDati) {
  api
    .post('/preleva_base_dati.php', {
      id_base_dati: idBaseDati,
    })
    .then((response) => {
      Object.keys(response.data.base_dati).forEach((key) => {
        if (key in formData.value) formData.value[key] = response.data.base_dati[key]
      })
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati - ' + e.response.data.message)
    })
}

/**
 * appena seleziono un file lancio uploadFile in modo da avere a disposizione l'intestazione del file
 * @returns {Promise<void>}
 */
const uploadFile = async (file) => {
  if (!formData.value.file_base_dati) return

  fileStore.setSelectedFile(file)

  const uploadData = new FormData()
  uploadData.append('file_to_upload', formData.value.file_base_dati) // Nome del campo che PHP cercherà
  uploadData.append('intestazione_si_no', formData.value.intestazione_si_no)
  isUploading.value = true
  try {
    const response = await api.post('/upload_file_per_nuova_base_dati.php', uploadData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    if (!response.data.intestazione)
      throw new Error('Impossibile salvare la configurazione, controllare i dati inseriti')

    intestazione.value = response.data.intestazione //se il file ha intestazione torna la prima riga altrimenti torna colonna 1,2 ecc....
    isUploading.value = false
  } catch (e) {
    gestioneErrore(e, 'Impossibile salvare la configurazione, controllare i dati inseriti')
  }
}

function creaBaseDati() {
  //se bisogna importare i dati
  const dati = { ...formData.value, intestazione: intestazione.value }
  dati.file_base_dati = dati.file_base_dati.name

  api
    .post('/crea_base_dati.php', dati)
    .then((response) => {
      messaggioPositivo('Base dati creata con successo')
      //ritorno alla pagina di creazione elaborazione
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
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile creare la base dati - ' + e.response.data.message)
      return false
    })
}
</script>

<style scoped></style>
