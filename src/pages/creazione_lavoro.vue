<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
      <q-dialog v-model="dialogConfigurazioneVisible" persistent>
        <q-card style="min-width: 900px; max-width: 95vw">
          <q-card-section class="row items-center justify-between">
            <div class="text-h6">Nuova configurazione {{ tipoSpedizioneScelta }}</div>
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
              :creazioneNuovaConfigurazione="true"
              :nomeConfigurazione="nomeElaborazioneScelta"
            />
          </q-card-section>
        </q-card>
      </q-dialog>

      <BaseSelect
        label="Lista lavori esistenti"
        v-model="lavoro"
        :options="lavori"
        @update:model-value="lavoroCambiato"
        v-if="isModifica"
      ></BaseSelect>

      <h3>{{ isModifica ? 'Modifica lavoro' : 'Crea lavoro' }}</h3>

      <BaseForm :formData="formData" @submit="creaLavoro" labelInvia="Salva lavoro">
        <div class="row q-gutter-x-md items-start">
          <div class="col">
            <!--select base dati (quando cambia, se sotto è indicata un elaborazione sola autocompilo nome_elaborazione e nome lavoro-->
            <BaseSelect
              v-model="formData.id_base_dati"
              label="Base dati"
              :options="basiDati"
              :rules="[required]"
              @update:model-value="
                (newValue) => {
                  if (newValue.value) {
                    //aggiorno nome lavoro
                    if (formData.nome_lavoro === '') {
                      formData.nome_lavoro = newValue.label
                    }
                    //aggiorno nome elaborazione
                    if (
                      formData.elaborazioni.length === 1 &&
                      formData.elaborazioni[0].nome_elaborazione == ''
                    ) {
                      formData.elaborazioni[0].nome_elaborazione = newValue.label
                    }
                  }
                }
              "
            />
          </div>
          <div class="col-auto">
            <BaseBtn label="Nuova Base Dati" @click="nuovaBaseDati"></BaseBtn>
          </div>
        </div>

        <BaseInput
          v-model="formData.nome_lavoro"
          label="Nome lavoro"
          :rules="[required, maxLength(50)]"
          @change="
            formData.elaborazioni.length === 1 && formData.elaborazioni[0].nome_elaborazione == ''
              ? (formData.elaborazioni[0].nome_elaborazione = formData.nome_lavoro)
              : null
          "
        />

        <div
          style="border: 0.5px lightgrey solid; border-radius: 5px"
          class="q-pa-sm q-mb-sm"
          v-for="elaborazione in formData.elaborazioni"
          :key="elaborazione.nome"
        >
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
          <div class="row q-gutter-x-md items-start">
            <div class="col">
              <BaseSelect
                v-model="elaborazione.id_configurazione"
                label="Configurazione"
                :options="listaConfigurazioni[elaborazione.id]"
                :rules="[required]"
                :disable="!elaborazione.tipo_spedizione"
              />
            </div>
            <div class="col-auto">
              <BaseBtn
                label="Nuova configurazione"
                @click="apriDialogConfigurazione(elaborazione.id)"
                :disable="!elaborazione.tipo_spedizione"
              ></BaseBtn>
            </div>
          </div>
          <BaseInput
            v-model="elaborazione.where"
            label="Sql vista (solo contenuto where)"
            :suggestions="baseDatiSelezionata?.intestazione.split('|')"
            :rules="formData.elaborazioni.length > 1 ? [required, sqlSafe] : [sqlSafe]"
          >
            <q-tooltip :delay="100" :offset="[0, 20]">
              {{ baseDatiSelezionata?.intestazione }}
            </q-tooltip>
          </BaseInput>

          <BaseBtn
            label="Elimina"
            @click="eliminaElaborazione(elaborazione.id)"
            v-if="formData.elaborazioni.length > 1"
          ></BaseBtn>

          <BaseBtn label="Aggiungi" @click="aggiungiElaborazione"></BaseBtn>
        </div>
      </BaseForm>
    </div>
  </q-page>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
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
const formData = ref({
  id_base_dati: null, //se ritorno dalla pagina creazione_base_dati potrei voler importare i dati precedenti
  nome_lavoro: route.query.nome_base_dati ? route.query.nome_base_dati : '',
  elaborazioni: [
    {
      nome_elaborazione: route.query.nome_base_dati ? route.query.nome_base_dati : '',
      where: '',
      tipo_spedizione: '',
      id_configurazione: '',
      id: 0,
    },
  ],
})
//svuoto array query per non far comparire sul menu la base dati creata in precedenza
const listaConfigurazioni = ref([])
const basiDati = ref([])
const visteEsistenti = ref([])
const baseDatiSelezionata = ref(null)
watch(
  () => formData.value.id_base_dati,
  (newVal) => {
    baseDatiSelezionata.value = newVal
  },
)
const idElaborazionePagina = ref(0)
const tipi_spedizioni = ref([])
const nomeElaborazioneScelta = ref('')
const isModifica = computed(() => route.path.includes('/modifica_lavoro'))
const lavori = ref([])
const lavoro = ref(null)

if(route.query.id_base_dati)
  formData.value.id_base_dati = { label: route.query.nome_base_dati, value: route.query.id_base_dati, intestazione: route.query.intestazione }
router.replace({
  path: route.path,
  query: {},
})
async function tipoSpedizioneCambiato(idElaborazione) {
  tipoSpedizioneScelta.value = formData.value.elaborazioni[idElaborazione].tipo_spedizione
  nomeElaborazioneScelta.value = formData.value.elaborazioni[idElaborazione].nome_elaborazione
  try {
    await api
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

function lavoroCambiato(val) {
  //prelevo dati configurazione
  api
    .post('/preleva_lavoro.php', {
      id_lavoro: val.value,
    })
    .then((response) => {
      //setto il formData con i dati prelevati
      formData.value.nome_lavoro = response.data.lavori[0].nome_lavoro
      formData.value.id_base_dati = {
        label: response.data.lavori[0].nome_base_dati,
        value: response.data.lavori[0].id_base_dati,
        intestazione: response.data.lavori[0].intestazione,
      }

      formData.value.elaborazioni = []
      Object.keys(response.data.lavori).forEach((key) => {
        formData.value.elaborazioni.push({
          id: response.data.lavori[key].id_elaborazione,
          nome_elaborazione: response.data.lavori[key].nome_elaborazione,
          where: response.data.lavori[key].where,
          tipo_spedizione: response.data.lavori[key].tipo_spedizione,
          id_configurazione: {
            value: response.data.lavori[key].id_configurazione,
            label: response.data.lavori[key].nome_configurazione,
          },
        })
      })
    })
    .catch((e) => {
      gestioneErrore(
        e,
        'Impossibile prelevare la configurazione, controllare i dati inseriti - ' +
          e.response?.data?.message || 'errore sconosciuto',
      )
    })
}

function apriDialogConfigurazione(indexElaborazione) {
  dialogConfigurazioneVisible.value = true
  indiceElaborazioneInCorso.value = indexElaborazione
}
function chiudiDialogConfigurazione() {
  dialogConfigurazioneVisible.value = false
  configurazioneCorrente.value = null
}
function onConfigurazioneSalvata(configurazione) {
  //imposto il select configurazione
  formData.value.elaborazioni[indiceElaborazioneInCorso.value].id_configurazione = configurazione
  chiudiDialogConfigurazione()
}

onMounted(() => {
  // Accedi ai parametri dell'URL
  //tipoElaborazione.value = route.params.tipo
  //nomeBottone.value = route.query.nome

  //prelevo configurazioni

  //prelevo base dati
  api
    .post('/preleva_basi_dati.php')
    .then((response) => {
      basiDati.value = response.data.basi_dati
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare base dati - ' + e.response.data.message)
    })

  //prelevo le viste già esistenti (per non inserire doppioni di elaborazione

  api
    .post('/preleva_viste.php')
    .then((response) => {
      visteEsistenti.value = response.data.viste
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare viste - ' + e.response.data.message)
    })

  //prelevo le spedizioni possibili

  api
    .post('/preleva_tipi_spedizione.php')
    .then((response) => {
      tipi_spedizioni.value = response.data.spedizioni
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare tipi spedizione - ' + e.response.data.message)
    })

  if (isModifica.value) {
    api
      .post('/preleva_lavori.php')
      .then((response) => {
        lavori.value = response.data.lavori
      })
      .catch((e) => {
        gestioneErrore(e, 'Impossibile prelevare lavori - ' + e.response.data.message)
      })
  }
})

function creaLavoro() {
  //creazione/salvataggio lavoro
  api
    .post(isModifica.value ? '/aggiorna_lavoro.php' : '/crea_lavoro.php', {
      id_base_dati: formData.value.id_base_dati.value,
      nome_lavoro: formData.value.nome_lavoro,
      //elaborazioni: JSON.stringify(formData.value.elaborazioni),
      elaborazioni: formData.value.elaborazioni,
      id_lavoro: isModifica.value ? lavoro.value.value : null,
    })
    .then((response) => {
      messaggioPositivo(
        isModifica.value
          ? 'Elaborazione aggiornata con successo'
          : 'Elaborazione caricata con successo',
      )
      if (!isModifica.value) {
        router.replace({
          path: '/',
          query: {
            id_lavoro: response.data.id_lavoro,
            nome_lavoro: formData.value.nome_lavoro,
          },
        })
      }
    })
    .catch((e) => {
      gestioneErrore(
        e,
        isModifica.value
          ? 'Impossibile aggiornare il lavoro - ' + e.response.data.message
          : 'Impossibile creare  il lavoro - ' + e.response.data.message,
      )
    })
}

function nuovaBaseDati() {
  router.push({ path: '/creazione_BaseDati/', query: { creazioneLavoroInCorso: true } })
}

function aggiungiElaborazione() {
  idElaborazionePagina.value++
  formData.value.elaborazioni.push({
    id: idElaborazionePagina.value,
    nome: '',
    where: '',
  })
}

function eliminaElaborazione(idElaborazione) {
  formData.value.elaborazioni = formData.value.elaborazioni.filter(
    (elaborazione) => elaborazione.id !== idElaborazione,
  )
}
</script>

<style scoped></style>
