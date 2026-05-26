<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
      <BaseForm :formData="formData" @submit="creaElaborazione" labelInvia="Elabora">
        <div class="row q-gutter-x-md items-start">
          <div class="col">
            <BaseSelect
              v-model="formData.lavoro"
              :options="lavori"
              label="Seleziona lavoro"
              :rules="[required]"
            />
          </div>
          <div class="col-auto">
            <BaseBtn label="Nuovo lavoro" @click="creaNuovoLavoro" />
          </div>
        </div>
        <!-- spiegazione classi div precedenti
        row: Crea un contenitore Flexbox orizzontale (i figli vengono disposti in riga)
•
q-gutter-x-md: Aggiunge uno spaziamento orizzontale medio tra gli elementi
•
items-center: Allinea verticalmente gli elementi al centro (utile se hanno altezze diverse)
•
col: Il BaseSelect occupa tutto lo spazio disponibile rimanente
•
col-auto: Il BaseBtn occupa solo lo spazio necessario per il suo contenuto
-->
        <BaseFile v-model="formData.fileBaseDati" label="Seleziona un file" :rules="[required]" />
        <BaseInput
          v-model="formData.folder_z"
          label="Folder z (path completa)"
          :rules="[required]"
        />
        <BaseInput
          v-model="formData.id_flusso"
          label="Commessa"
          :rules="[required, maxLength(10)]"
        />
      </BaseForm>
    </div>

    <br /><br />

    <div class="q-gutter-y-md">
      <q-card>
        <q-tabs
          v-model="tab"
          dense
          class="text-grey"
          active-color="primary"
          indicator-color="primary"
          align="left"
          narrow-indicator
        >
          <q-tab name="elaborazioni" label="Elaborazioni in corso" />
          <q-tab name="elaborazioni_concluse" label="Elaborazioni concluse" />
        </q-tabs>

        <q-separator />

        <q-tab-panels v-model="tab" animated>
          <q-tab-panel name="elaborazioni">
            <q-table
              flat
              bordered
              title="Elaborazioni in corso"
              :rows="elaborazioniInCorso"
              :columns="columnsGruppiElaborazioniInCorso"
              row-key="row-id"
              :filter="filter"
            >
              <template v-slot:top-right>
                <q-input borderless dense debounce="300" v-model="filter" placeholder="Cerca">
                  <template v-slot:append>
                    <q-icon name="search" />
                  </template>
                </q-input>
              </template>

              <template v-slot:header="props">
                <q-tr :props="props">
                  <q-th auto-width /><!-- colonna del pulsante per espandere le righe -->
                  <q-th v-for="col in props.cols" :key="col.name" :props="props">
                    {{ col.label }}
                  </q-th>
                </q-tr>
              </template>

              <template v-slot:body="props">
                <q-tr :props="props">
                  <q-td auto-width>
                    <BaseBtn
                      size="sm"
                      color="accent"
                      round
                      dense
                      @click="props.expand = !props.expand"
                      :icon="props.expand ? 'remove' : 'add'"
                    />
                  </q-td>
                  <q-td v-for="col in props.cols" :key="col.name" :props="props">
                    {{ col.value }}
                  </q-td>
                </q-tr>

                <q-tr v-show="props.expand" :props="props"
                  ><!-- The difference is that an element with v-show will always be rendered and remain in the DOM; v-show only toggles the display CSS property of the element. -->
                  <q-td colspan="100%">
                    <q-table
                      flat
                      bordered
                      dense
                      :rows="props.row.dettagli"
                      :columns="columnsElaborazioniInCorso"
                      row-key="row-id"
                      :filter="filter"
                    >
                      <template v-slot:body-cell-ordinato="props">
                        <q-td :props="props">
                          <div v-if="props.value === 0">
                            <BaseBtn
                              label="Ordina"
                              size="sm"
                              @click="lanciaElaborazione(props.row.id)"
                            />
                          </div>
                          <div v-if="props.value === 1">In elaborazione</div>
                          <div v-if="props.value != 0 && props.value != 1">Elaborato</div>
                        </q-td>
                      </template>

                      <template v-slot:body-cell-sql="props">
                        <q-td :props="props">
                          <div>
                            <BaseBtn label="sql" @click="dialogSqlVisible = true" />

                            <q-dialog v-model="dialogSqlVisible">
                              <q-card style="max-width: 700px">
                                <q-card-section class="row items-center justify-between">
                                  <div class="text-h6">Query MySql</div>

                                  <div class="row q-gutter-sm">
                                    <BaseBtn
                                      dense
                                      flat
                                      color="primary"
                                      icon="content_copy"
                                      label="Copia"
                                      @click="
                                        copiaQuery(
                                          'SELECT * FROM `' +
                                            props.row.nome_base_dati +
                                            '_' +
                                            props.row.nome_elaborazione +
                                            '` e JOIN `ordinati_' +
                                            props.row.tipo_spedizione +
                                            '_' +
                                            props.row.nome_base_dati +
                                            '` o ON e.id=o.c1 ORDER BY progr',
                                        )
                                      "
                                    ></BaseBtn>
                                    <BaseBtn
                                      dense
                                      flat
                                      color="negative"
                                      icon="close"
                                      label="Chiudi"
                                      v-close-popup
                                    ></BaseBtn>
                                  </div>
                                </q-card-section>

                                <q-separator></q-separator>

                                <q-card-section>
                                  <pre class="sql-code">
  SELECT * FROM `{{ props.row.nome_base_dati }}_{{ props.row.nome_elaborazione }}` e
      JOIN `ordinati_{{ props.row.tipo_spedizione }}_{{ props.row.nome_base_dati }}` o
           ON e.id=o.c1
  ORDER BY progr
                              </pre
                                  >
                                </q-card-section>
                              </q-card>
                            </q-dialog>
                          </div>
                        </q-td>
                      </template>

                      <template v-slot:body-cell-da_prenotare="props">
                        <q-td :props="props">
                          <div v-if="props.value === 2">
                            <BaseDatePicker
                              v-model="dataPrenotazione[props.row.id]"
                            ></BaseDatePicker>
                            <BaseBtn
                              label="Prenota"
                              size="sm"
                              @click="lanciaPrenotazione(props.row.id)"
                            />
                          </div>
                          <div v-if="props.value === 3">In attesa di ok da Poste</div>
                          <div v-if="props.value === 4">Prenotato</div>
                        </q-td>
                      </template>

                      <template v-slot:body-cell-azioni="props">
                        <q-td :props="props">
                          <BaseBtn label="azioni" @click="dialogAzioniVisible = true"></BaseBtn>

                          <q-dialog v-model="dialogAzioniVisible">
                            <q-card flat bordered>
                              <q-card-section class="row items-center justify-between">
                                <div class="text-h6">Azioni</div>

                                <div class="row q-gutter-sm">
                                  <BaseBtn
                                    dense
                                    flat
                                    color="negative"
                                    icon="close"
                                    label="Chiudi"
                                    v-close-popup
                                  ></BaseBtn>
                                </div>
                              </q-card-section>

                              <q-separator></q-separator>
                              <q-card-section>
                                <div
                                  v-for="(datiAzione, nomeAzione) in azioni[
                                    props.row.tipo_spedizione
                                  ]"
                                  :key="nomeAzione"
                                >
                                  <base-date-picker
                                    v-if="datiAzione.parametri?.includes('d') ? true : false"
                                    v-model="dataPrenotazione[props.row.id]"
                                  ></base-date-picker>
                                  <BaseBtn
                                    :label="nomeAzione"
                                    @click="lanciaAzione(datiAzione, props.row.id)"
                                  ></BaseBtn>
                                </div>
                              </q-card-section>
                            </q-card>
                          </q-dialog>
                        </q-td>
                      </template>
                    </q-table>
                  </q-td>
                </q-tr>
              </template>

              <template v-slot:body-cell-chiudi="props">
                <q-td :props="props">
                  <BaseBtn label="Chiudi" @click="chiudiElaborazione(props.row.id)"></BaseBtn>
                </q-td>
              </template>
            </q-table>
          </q-tab-panel>

          <q-tab-panel name="elaborazioni_concluse">
            <q-table
              flat
              bordered
              dense
              :rows="elaborazioniConcluse"
              :columns="columnsElaborazioniConcluse"
              row-key="id"
              :filter="filterElabConcluse"
            >
              <template v-slot:body-cell-riapri="props">
                <q-td :props="props">
                  <BaseBtn label="Riapri" size="sm" @click="riapriElaborazione(props.row.id)" />
                </q-td>
              </template>
            </q-table>
          </q-tab-panel>
        </q-tab-panels>
      </q-card>
    </div>
  </q-page>
</template>

<script setup>
import { useRouter } from 'vue-router'
const router = useRouter()
import { useRoute } from 'vue-router'
const route = useRoute()
import BaseBtn from 'components/forms/BaseBtn.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import BaseForm from 'components/forms/BaseForm.vue'
import { api } from 'boot/axios.js'
import { onMounted, ref } from 'vue'
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi'
import BaseDatePicker from 'components/forms/BaseDatePicker.vue'
import { maxLength, required } from 'src/composables/rules.js'
import BaseInput from 'components/forms/BaseInput.vue'
import BaseFile from 'components/forms/BaseFile.vue'
const { gestioneErrore, messaggioPositivo } = useCassettaAttrezzi()
import { useFileStore } from 'src/stores/fileStore'
const fileStore = useFileStore()

const formData = ref({
  lavoro: route.query.id_lavoro
    ? { value: route.query.id_lavoro, label: route.query.nome_lavoro }
    : null,
  fileBaseDati: fileStore.hasFile ? fileStore.selectedFile : null,
  folder_z: '',
  id_flusso: null,
})
//svuoto array query per non far comparire sul menu la base dati creata in precedenza
router.replace({
  path: route.path,
  query: {},
})
const lavori = ref([])

// function market() {
//   LocalStorage.set('tipoSpedizione', 'market')
//   router.push({
//     path: '/creazione_elaborazione/',
//     //path: '/scelta_elaborazione/market',
//     //query: { nome: 'Market' }
//   })
// }
//
// function massiva() {
//   LocalStorage.set('tipoSpedizione', 'massiva')
//   router.push({
//     path: '/creazione_elaborazione',
//   })
// }
//
// function target() {
//   LocalStorage.set('tipoSpedizione', 'target')
//   router.push({
//     path: '/creazione_elaborazione',
//   })
// }

function creaNuovoLavoro() {
  router.push({
    path: '/creazione_lavoro',
  })
}

function creaElaborazione() {
  const uploadData = new FormData()
  uploadData.append('file_to_upload', formData.value.fileBaseDati) //(se non è già stato caricato quando si è creato la base dati che allora è ancora sul server viene caricata solo la stringa della path del file sul server che andrà in automatico nell'array $_POST e non nell'array $_FILES
  uploadData.append('folder_z', formData.value.folder_z)
  uploadData.append('id_flusso', formData.value.id_flusso)
  uploadData.append('id_lavoro', formData.value.lavoro.value)
  //creazione elaborazione
  api
    .post('/crea_elaborazione.php', uploadData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    .then(() => {
      messaggioPositivo('Elaborazione creata')
      prelevaElaborazioniInCorso()
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile creare elaborazione - ' + e.response.data.message)
    })
}

const tab = ref('elaborazioni')
const azioni = ref({})
const filter = ref('')
const filterElabConcluse = ref('')
const dataPrenotazione = ref([])
const dialogAzioniVisible = ref(false)
const dialogSqlVisible = ref(false)
const elaborazioniInCorso = ref([])
const elaborazioniConcluse = ref([])
const columnsGruppiElaborazioniInCorso = [
  {
    name: 'nomeGruppo',
    label: 'Nome',
    field: 'nome_lavoro',
    sortable: true,
  },
]
const columnsElaborazioniInCorso = [
  {
    name: 'id_elaborazione',
    label: 'ID',
    field: 'id_elaborazione',
    sortable: true,
  },
  {
    name: 'nome_elaborazione',
    label: 'Nome elaborazione',
    field: 'nome_elaborazione',
    sortable: true,
  },
  { name: 'tipo_spedizione', label: 'Tipo spedizione', field: 'tipo_spedizione', sortable: true },
  { name: 'nome_base_dati', label: 'Base Dati', field: 'nome_base_dati', sortable: true },
  { name: 'folder_z', label: 'Folder Z', field: 'folder_z', sortable: true },
  { name: 'ordinato', label: 'Ordinato', field: 'ordinato', sortable: true },
  { name: 'sql', label: 'Sql', field: 'sql', sortable: false },
  { name: 'da_prenotare', label: 'Da prenotare', field: 'da_prenotare', sortable: true },
  { name: 'Azioni', label: 'Azioni', field: 'azioni', sortable: true },
  { name: 'chiudi', label: 'Chiudi Elab', field: 'chiudi', sortable: false },
]

const columnsElaborazioniConcluse = [
  {
    name: 'id',
    label: 'ID',
    field: 'id',
    sortable: true,
  },
  {
    name: 'nome_elaborazione',
    label: 'Nome elaborazione',
    field: 'nome_elaborazione',
    sortable: true,
  },
  { name: 'tipo_spedizione', label: 'Tipo spedizione', field: 'tipo_spedizione', sortable: true },
  { name: 'Azioni', label: 'Azioni', field: 'azioni', sortable: true },
]

onMounted(() => {
  api
    .post('preleva_lavori.php')
    .then((response) => {
      lavori.value = response.data.lavori
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare lavori - ' + e.response.data.message)
    })

  prelevaElaborazioniInCorso()
  prelevaElaborazioniConcluse()

  api
    .post('/preleva_azioni_elaborazioni.php')
    .then((response) => {
      azioni.value = response.data.azioni
    })
    .catch((e) => {
      gestioneErrore(
        e,
        'Impossibile prelevare le azioni delle elaborazioni - ' + e.response.data.message,
      )
    })
})
function prelevaElaborazioniInCorso() {
  api
    .post('/preleva_elaborazioni_in_corso.php')
    .then((response) => {
      elaborazioniInCorso.value = response.data.elaborazioni
    })
    .catch((e) => {
      gestioneErrore(
        e,
        'Impossibile prelevare le elaborazioni in corso - ' + e.response.data.message,
      )
    })
}
function prelevaElaborazioniConcluse() {
  api
    .post('/preleva_elaborazioni_concluse.php')
    .then((response) => {
      elaborazioniConcluse.value = response.data.elaborazioni
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare elaborazioni concluse - ' + e.response.data.message)
    })
}

function lanciaElaborazione(id_elaborazione) {
  api
    .post('/lancia_ordinamento.php', {
      id_elaborazione: id_elaborazione,
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile lanciare ordinamento - ' + e.response.data.message)
    })
  //setto stato a 1
  elaborazioniInCorso.value.filter(
    (elaborazione) => elaborazione.id === id_elaborazione,
  )[0].ordinato = 1
}

async function copiaQuery(query) {
  try {
    await navigator.clipboard.writeText(query)
    messaggioPositivo('Query copiata negli appunti')
  } catch (error) {
    gestioneErrore(error, 'Impossibile copiare la query')
  }
}

function lanciaPrenotazione(id_elaborazione) {
  if (!dataPrenotazione.value[id_elaborazione]) {
    gestioneErrore(null, 'Data prenotazione non inserita')
    return
  }

  api
    .post('/prenotazione.php', {
      data_prenotazione: dataPrenotazione.value[id_elaborazione],
      id_elaborazione: id_elaborazione,
    })
    .then(() => {
      messaggioPositivo('Prenotazione effettuata')
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile lanciare prenotazione - ' + e.response.data.message)
    })
}

function lanciaAzione(datiAzione, id_elaborazione) {
  if (datiAzione.parametri.includes('d')) {
    if (!dataPrenotazione.value[id_elaborazione]) {
      gestioneErrore(null, 'Data prenotazione non inserita')
      return
    }
  }

  var dati = {
    data_spedizione: datiAzione.parametri?.includes('d')
      ? dataPrenotazione.value[id_elaborazione]
      : null,
    id_elaborazione: id_elaborazione,
  }

  api
    .post('/' + datiAzione.endpoint, dati)
    .then(() => {
      messaggioPositivo('Azione eseguita')
    })
    .catch((e) => {
      gestioneErrore(
        e,
        'Impossibile eseguire  azione ' + datiAzione.endpoint + ' - ' + e.response.data.message,
      )
    })
}

function chiudiElaborazione(id_elaborazione) {
  api
    .post('/chiudi_elaborazione.php', { id_elaborazione: id_elaborazione })
    .then(() => {
      messaggioPositivo('Elaborazione chiusa')
      prelevaElaborazioniInCorso()
      prelevaElaborazioniConcluse()
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile chiudere elaborazione - ' + e.response.data.message)
    })
}

function riapriElaborazione(id_elaborazione) {
  api
    .post('/riapri_elaborazione.php', { id_elaborazione: id_elaborazione })
    .then(() => {
      messaggioPositivo('Elaborazione riaperta')
      prelevaElaborazioniInCorso()
      prelevaElaborazioniConcluse()
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile riaprire elaborazione - ' + e.response.data.message)
    })
}
</script>
<style scoped>
.sql-code {
  background-color: #f5f5f5;
  padding: 15px;
  border-radius: 4px;
  border: 1px solid #ddd;
  font-family: 'Courier New', Courier, monospace;
  white-space: pre-wrap; /* Mantiene gli a capo ma evita lo scroll orizzontale infinito */
  word-wrap: break-word;
  max-height: 500px;
  overflow: auto;
}
</style>
