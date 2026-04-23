<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
      <q-dialog v-model="dialogConfigurazioneVisible" persistent>
        <q-card style="min-width: 900px; max-width: 95vw">
          <q-card-section class="row items-center justify-between">
            <div class="text-h6">Nuova configurazione</div>
            <BaseBtn
              icon="close"
              flat
              round
              v-close-popup
              @click="chiudiDialogConfigurazione"
            ></BaseBtn>
          </q-card-section>

          <q-separator />

          <q-card-section>
            <ConfigurazioneForm
              v-model="configurazioneCorrente"
              :tipoSpedizione="tipoSpedizioneScelta"
              @saved="onConfigurazioneSalvata"
            />
          </q-card-section>
        </q-card>
      </q-dialog>

      <BaseForm :formData="formData" v-model:valido="formValido" @submit="creaLavoro">
        <div class="row q-gutter-x-md items-start">
          <div class="col">
            <BaseSelect v-model="formData.idBaseDati" label="Base dati" :options="basiDati" />
          </div>
          <div class="col-auto">
            <BaseBtn
              label="Nuova Base Dati"
              @click="nuovaBaseDati"
            ></BaseBtn>
          </div>
        </div>

        <BaseInput
          v-model="formData.nome_lavoro"
          label="Nome lavoro"
          :rules="[required, maxLength(50)]"
          @change="
            formData.elaborazioni.length === 1
              ? (formData.elaborazioni[0].nome_elaborazione = formData.nome_lavoro)
              : null
          "
        />

        <div v-for="elaborazione in formData.elaborazioni" :key="elaborazione.nome">
          <BaseInput
            v-model="elaborazione.nome_elaborazione"
            label="Nome sotto elaborazione"
            :rules="
              formData.elaborazioni.length > 1
                ? [required, maxLength(50), notInArray(visteEsistenti)]
                : [required, maxLength(50)]
            "
          /><!-- se c'è una multi elaborazione allora il php creerà una vista, e quindi controllo in questo caso che non venga inserita una vista già esistente -->

          <BaseSelect
            v-model="elaborazione.tipo_spedizione"
            label="Tipo spedizione"
            :options="tipi_spedizioni"
            :rules="[required]"
            @update:model-value="tipoSpedizioneCambiato(elaborazione.id)"
          />
          <BaseSelect
            v-model="elaborazione.id_configurazione"
            label="Configurazione"
            :options="listaConfigurazioni[elaborazione.id]"
            :rules="[required]"
            :disabled="!elaborazione.tipo_spedizione"
          />
          <BaseBtn
            label="Nuova configurazione"
            @click="apriDialogConfigurazione(elaborazione.id)"
          ></BaseBtn>

          <BaseInput
            v-model="elaborazione.where"
            label="Sql vista (solo contenuto where)"
            :rules="formData.elaborazioni.length > 1 ? [required, sqlSafe] : [sqlSafe]"
          >
            <q-tooltip :delay="100">
              {{ baseDatiSelezionata?.intestazione }}
            </q-tooltip>
          </BaseInput>

          <BaseBtn
            label="Elimina"
            @click="eliminaElaborazione(elaborazione.nome_elaborazione)"
            v-if="formData.elaborazioni.length > 1"
          ></BaseBtn>
        </div>

        <BaseBtn label="Aggiungi" @click="aggiungiElaborazione"></BaseBtn>

        <BaseBtn type="submit" label="Importa" :disable="!formValido"></BaseBtn>
      </BaseForm>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
const router = useRouter()
import { useRoute } from 'vue-router'
const route = useRoute()
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi'
const { gestioneErrore, messaggioPositivo } = useCassettaAttrezzi()
import { api } from 'boot/axios.js'
import BaseForm from 'components/forms/BaseForm.vue'
import BaseBtn from 'components/forms/BaseBtn.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import { required, sqlSafe, maxLength, notInArray } from 'src/composables/rules.js'
import BaseInput from 'components/forms/BaseInput.vue'
import ConfigurazioneForm from 'components/creazione_configurazione.vue'

const indiceElaborazioneInCorso = ref(null)
const dialogConfigurazioneVisible = ref(false)
const tipoSpedizioneScelta = ref(null)
const configurazioneCorrente = ref(null)
const formValido = ref(false)
const formData = ref({
  idBaseDati: route.query.idBaseDati ? route.query.idBaseDati : '', //se ritorno dalla pagina creazione_base_dati potrei voler importare i dati precedenti
  nome_lavoro: '',
  elaborazioni: [
    {
      nome_elaborazione: '',
      where: '',
      tipo_spedizione: '',
      id_configurazione: '',
      id: 0,
    },
  ],
})
const listaConfigurazioni = ref([])
const basiDati = ref([])
const visteEsistenti = ref([])
const baseDatiSelezionata = computed(() => {
  return basiDati.value.find((bd) => bd.value === formData.value.idBaseDati)
})
const idElaborazionePagina = ref(0)
const tipi_spedizioni = ref([])

function tipoSpedizioneCambiato(idElaborazione) {
  tipoSpedizioneScelta.value = formData.value.elaborazioni[idElaborazione].tipo_spedizione
  try {
    api
      .post('/preleva_configurazioni.php', {
        tipo_spedizione: formData.value.elaborazioni[idElaborazione].tipo_spedizione,
      })
      .then((response) => {
        listaConfigurazioni.value[idElaborazione] = []
        listaConfigurazioni.value[idElaborazione] = response.data.configurazioni
      })
  } catch (error) {
    gestioneErrore(error, 'Impossibile prelevare le configurazioni')
  }
}

function apriDialogConfigurazione(indexElaborazione) {
  dialogConfigurazioneVisible.value = true
  indiceElaborazioneInCorso.value = indexElaborazione
}
function chiudiDialogConfigurazione() {
  dialogConfigurazioneVisible.value = false
  configurazioneCorrente.value = null
}
function onConfigurazioneSalvata(idConfigurazione) {
  formData.value.elaborazioni[indiceElaborazioneInCorso.value].id_configurazione = idConfigurazione
}

onMounted(() => {
  // Accedi ai parametri dell'URL
  //tipoElaborazione.value = route.params.tipo
  //nomeBottone.value = route.query.nome

  //prelevo configurazioni

  //prelevo base dati
  try {
    api.post('/preleva_basi_dati.php').then((response) => {
      basiDati.value = response.data.basi_dati
    })
  } catch (error) {
    gestioneErrore(error, 'Impossibile prelevare le basi dati')
  }

  //prelevo le viste già esistenti (per non inserire doppioni di elaborazione
  try {
    api.post('/preleva_viste.php').then((response) => {
      visteEsistenti.value = response.data.viste
    })
  } catch (error) {
    gestioneErrore(error, 'Impossibile prelevare le viste esistenti')
  }

  //prelevo le spedizioni possibili
  try {
    api.post('/preleva_tipi_spedizione.php').then((response) => {
      tipi_spedizioni.value = response.data.spedizioni
    })
  } catch (error) {
    gestioneErrore(error, 'Impossibile prelevare le viste esistenti')
  }
})

function creaLavoro() {
  //creazione lavoro
  try {
    api
      .post('/crea_lavoro.php', {
        id_base_dati: formData.value.idBaseDati,
        nome_lavoro: formData.value.nome_lavoro,
        elaborazioni: JSON.stringify(formData.value.elaborazioni),
      })
      .then(() => {
        messaggioPositivo('Elaborazione caricata con successo')
        router.push({ path: '/' })
      })
  } catch (e) {
    gestioneErrore(e, 'Impossibile salvare la configurazione, controllare i dati inseriti')
  }
}

function nuovaBaseDati() {
  router.push({ path: '/creazione_BaseDati/', query: { creazioneLavoroInCorso: true } })
}

function aggiungiElaborazione() {
  formData.value.elaborazioni.push({
    id: idElaborazionePagina.value,
    nome: '',
    where: '',
  })
  idElaborazionePagina.value++
}

function eliminaElaborazione(nomeElaborazione) {
  formData.value.elaborazioni = formData.value.elaborazioni.filter(
    (elaborazione) => elaborazione.nome_elaborazione !== nomeElaborazione,
  )
}
</script>
