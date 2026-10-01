<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
    <div class="row items-center q-gutter-x-sm" v-if="!creazioneLavoroInCorso">
      <BaseSelect
        class="col"
        v-model="idBaseDati"
        :options="basiDati"
        label="Basi dati create"
        @update:model-value="baseDatiCambiata"
      />
      <div class="col-auto q-mb-md" v-if="idBaseDati">
        <q-btn
          color="negative"
          icon="delete"
          round
          flat
          @click="confermaEliminaBaseDati"
        >
          <q-tooltip>Elimina questa base dati</q-tooltip>
        </q-btn>
      </div>
    </div>

      <h3>Crea base dati</h3>

      <!-- form creazione base dati -->
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
        >
          <q-tooltip>Mettere nome cliente e lavoro</q-tooltip>
        </BaseInput>

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
        <BaseSelect
          :options="intestazione"
          label="Campo Località"
          v-model="formData.campo_localita"
          :disable="!intestazione.length"
          :rules="[required]"
          v-if="formData.intestazione_si_no !== null"
        />
        <BaseSelect
          :options="intestazione"
          label="Campo Provincia"
          v-model="formData.campo_provincia"
          :disable="!intestazione.length"
          :rules="[required]"
          v-if="formData.intestazione_si_no !== null"
        />
      </BaseForm>
    </div>
  </q-page>
</template>

<script setup>
import BaseSelect from 'components/forms/BaseSelect.vue'
import BaseInput from 'components/forms/BaseInput.vue'
import BaseRadio from 'components/forms/BaseRadio.vue'
import BaseFile from 'components/forms/BaseFile.vue'
import { api } from 'boot/axios.js'
import { maxLength, required, notInArray } from 'src/composables/rules.js'
import BaseForm from 'components/forms/BaseForm.vue'
import { onMounted, ref } from 'vue'
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi'
const { gestioneErrore, messaggioPositivo, richiediConferma } = useCassettaAttrezzi()
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
const isSubmitting = ref(false)
const formData = ref({
  nome_base_dati: '',
  file_base_dati: null,
  campo_cap: '',
  campo_localita: '',
  campo_provincia: '',
  intestazione_si_no: null,
  separatore: ';',
  tipo_file: 'CSV/Testo',
  test: null,
})

onMounted(() => {
  api
    .post('/preleva_basi_dati.php')
    .then((response) => {
      basiDati.value = response.data.basi_dati
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati')
    })
})

function baseDatiCambiata(idBaseDati) {
  api
    .post('/preleva_base_dati.php', {
      id_base_dati: idBaseDati,
    })
    .then((response) => {
      // VALORIZZAZIONE INTESTAZIONE (Aggiunta per Piano 10, aggiornata Piano 11)
      if (response.data.base_dati.intestazione) {
        const rawIntestazione = response.data.base_dati.intestazione
        // Se è una stringa, la divido usando il separatore '|' (Piano 11)
        if (typeof rawIntestazione === 'string') {
          intestazione.value = rawIntestazione.split('|').filter((item) => item !== '')
        } else if (Array.isArray(rawIntestazione)) {
          intestazione.value = rawIntestazione
        } else {
          intestazione.value = []
        }
      } else {
        intestazione.value = []
      }

      //vado a valorizzare i dati del form (devo verificare i tipi di dati per gestire flag o testi
      Object.keys(response.data.base_dati).forEach((key) => {
        if (key === 'intestazione_si_no') {
          if (response.data.base_dati[key] === 1) {
            formData.value[key] = true
          } else {
            formData.value[key] = false
          }
        } else if (key === 'file_base_dati') {
          formData.value[key] = [response.data.base_dati[key]]
        } else if (key in formData.value) {
          formData.value[key] = response.data.base_dati[key]
        }
      })
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati')
    })
}

async function confermaEliminaBaseDati() {
  if (!idBaseDati.value) return

  richiediConferma(`Sei sicuro di voler eliminare la base dati "${idBaseDati.value.label}"? Tutti i dati associati verranno rimossi permanentemente.`)
    .onOk(async () => {
      try {
        const response = await api.post('/elimina_base_dati.php', {
          id_base_dati: idBaseDati.value.value
        })

        if (response.data.success) {
          messaggioPositivo('Base dati eliminata con successo')

          // Ricarica la lista delle basi dati
          const res = await api.post('/preleva_basi_dati.php')
          basiDati.value = res.data.basi_dati

          // Reset selezione e form
          idBaseDati.value = null
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
          intestazione.value = []
        }
      } catch (e) {
        gestioneErrore(
          e,
          'Errore durante l\'eliminazione della base dati'
        )
      }
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
  uploadData.append('separatore', formData.value.separatore)
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
    console.log(e)
    gestioneErrore(e, 'Impossibile salvare la configurazione, controllare i dati inseriti')
  }
}

async function creaBaseDati() {
  //se bisogna importare i dati
  isSubmitting.value = true
  const dati = { ...formData.value, intestazione: intestazione.value }
  dati.file_base_dati = dati.file_base_dati.name

  try {
    const response = await api.post('/crea_base_dati.php', dati)
    // il backend può rispondere 200 con body vuoto/non valido (es. errore intercettato
    // da un handler che interrompe lo script): non trattarlo come successo.
    if (!response.data || response.data.success !== true)
      throw new Error(response.data?.message || 'La base dati non è stata creata (risposta non valida dal server)')
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
  } catch (e) {
    gestioneErrore(e, 'Impossibile creare la base dati')
    return false
  } finally {
    isSubmitting.value = false
  }
}
</script>

<style scoped></style>
