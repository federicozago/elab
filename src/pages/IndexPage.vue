<template>
  <q-page class="q-pa-md">
    <div class="q-gutter-y-md">
      <BaseForm :formData="formData" @submit="creaElaborazione" labelInvia="Importa dati">
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
                      <template v-slot:body-cell-stato="props">
                        <q-td :props="props">
                          <div v-if="props.value === 0">
                            <BaseBtn
                              label="Ordina"
                              size="sm"
                              @click="lanciaElaborazione(props.row.id_elaborazione)"
                            />
                          </div>
                          <div v-if="props.value === 1">In elaborazione</div>
                          <div v-if="props.value === 2">
                            <BaseBtn
                              label="Prenota"
                              @click="
                                selectedRowPrenotazione = props.row;
                                dialogPrenotazioneVisible = true
                              "
                            />
                          </div>
                          <div v-if="props.value === 3">In attesa di ok da Poste</div>
                          <div v-if="props.value === 4">Prenotato</div>
                        </q-td>
                      </template>

                      <template v-slot:body-cell-sql="props">
                        <q-td :props="props">
                          <div>
                            <BaseBtn
                              label="sql"
                              @click="
                                selectedRowSql = props.row;
                                dialogSqlVisible = true
                              "
                            />
                          </div>
                        </q-td>
                      </template>

                      <template v-slot:body-cell-azioni="props">
                        <q-td :props="props">
                          <BaseBtn
                            label="azioni"
                            @click="
                              selectedRowAzioni = props.row;
                              dialogAzioniVisible = true
                            "
                          ></BaseBtn>
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
              <template v-slot:body-cell-azioni="props">
                <q-td :props="props">
                  <q-btn flat round color="primary" icon="download" @click="riesporta(props.row)">
                    <q-tooltip>Riesporta (ZIP)</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    color="negative"
                    icon="delete"
                    @click="confermaElimina(props.row)"
                  >
                    <q-tooltip>ELIMINA DATI</q-tooltip>
                  </q-btn>
                  <q-btn
                    flat
                    round
                    color="orange"
                    icon="refresh"
                    @click="riapriElaborazione(props.row.id_elaborazione)"
                  >
                    <q-tooltip>Riapri</q-tooltip>
                  </q-btn>
                </q-td>
              </template>
            </q-table>
          </q-tab-panel>
        </q-tab-panels>
      </q-card>
    </div>

    <!-- Dialog esterni alla tabella -->
    <q-dialog v-model="dialogSqlVisible" @hide="selectedRowSql = null">
      <q-card style="max-width: 700px" v-if="selectedRowSql">
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
                    selectedRowSql.nome_base_dati +
                    '_' +
                    selectedRowSql.nome_elaborazione +
                    '` e JOIN `ordinati_' +
                    selectedRowSql.tipo_spedizione +
                    '_' +
                    selectedRowSql.nome_base_dati +
                    '` o ON e.id=o.c1 ORDER BY progr',
                )
              "
            ></BaseBtn>
            <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
          </div>
        </q-card-section>
        <q-separator></q-separator>
        <q-card-section>
          <pre class="sql-code">
SELECT * FROM `{{ selectedRowSql.nome_base_dati }}_{{ selectedRowSql.nome_elaborazione }}` e
    JOIN `ordinati_{{ selectedRowSql.tipo_spedizione }}_{{ selectedRowSql.nome_base_dati }}` o
         ON e.id=o.c1
ORDER BY progr
          </pre>
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogPrenotazioneVisible" @hide="selectedRowPrenotazione = null">
      <q-card flat bordered v-if="selectedRowPrenotazione">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Azioni</div>
          <div class="row q-gutter-sm">
            <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
          </div>
        </q-card-section>
        <q-separator></q-separator>
        <q-card-section>
          <BaseDatePicker
            v-model="dataPrenotazione[selectedRowPrenotazione.id_elaborazione]"
            label="Data prenotazione"
          ></BaseDatePicker>
          <BaseBtn
            label="Prenota"
            size="sm"
            @click="lanciaPrenotazione(selectedRowPrenotazione.id_elaborazione)"
          />
        </q-card-section>
      </q-card>
    </q-dialog>

    <q-dialog v-model="dialogAzioniVisible" @hide="selectedRowAzioni = null">
      <q-card flat bordered v-if="selectedRowAzioni">
        <q-card-section class="row items-center justify-between">
          <div class="text-h6">Azioni</div>
          <div class="row q-gutter-sm">
            <BaseBtn dense flat color="negative" icon="close" label="Chiudi" v-close-popup></BaseBtn>
          </div>
        </q-card-section>
        <q-separator></q-separator>
        <q-card-section>
          <div class="q-mb-md q-pa-sm bg-grey-2 rounded-borders row items-center">
            <div class="col text-caption text-weight-medium">
              Percorso salvataggio suggerito:<br />
              <code style="word-break: break-all">{{
                convertiPathWindows(selectedRowAzioni.folder_cliente + selectedRowAzioni.folder_z)
              }}</code>
            </div>
            <div class="col-auto">
              <BaseBtn
                flat
                round
                dense
                icon="content_copy"
                @click="copiaPercorso(selectedRowAzioni.folder_cliente + selectedRowAzioni.folder_z)"
              />
            </div>
          </div>
          <div
            v-for="(datiAzione, nomeAzione) in azioni[selectedRowAzioni.tipo_spedizione]"
            :key="nomeAzione"
          >
            <base-date-picker
              v-if="datiAzione.parametri?.includes('d')"
              v-model="dataAzioni"
            ></base-date-picker>
            <BaseBtn
              :label="nomeAzione"
              @click="lanciaAzione(datiAzione, selectedRowAzioni.id_elaborazione)"
            ></BaseBtn>
          </div>
        </q-card-section>
      </q-card>
    </q-dialog>
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
const { gestioneErrore, messaggioPositivo,richiediConferma } = useCassettaAttrezzi()
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
const dialogPrenotazioneVisible = ref(false)
const selectedRowSql = ref(null)
const selectedRowPrenotazione = ref(null)
const selectedRowAzioni = ref(null)
const elaborazioniInCorso = ref([])
const elaborazioniConcluse = ref([])
const dataAzioni = ref({})
const columnsGruppiElaborazioniInCorso = [
  {
    name: 'nomeGruppo',
    label: 'Nome',
    field: 'nome_lavoro',
    sortable: true,
    align: 'left',
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
  { name: 'stato', label: 'Stato', field: 'stato', sortable: true },
  { name: 'sql', label: 'Sql', field: 'sql', sortable: false },
  { name: 'azioni', label: 'Azioni', field: 'azioni', sortable: true },
  { name: 'chiudi', label: 'Chiudi Elab', field: 'chiudi', sortable: false },
]

const columnsElaborazioniConcluse = [
  {
    name: 'id',
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
  { name: 'azioni', label: 'Azioni', field: 'azioni', sortable: true },
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
      for (const lavoro of elaborazioniInCorso.value) {
        for (const elaborazione of lavoro.dettagli) {
          dataPrenotazione.value[elaborazione.id_elaborazione] = null
        }
      }
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
  //console.log(elaborazioniInCorso.value)
  for (const lavoro of elaborazioniInCorso.value) {
    for (const elaborazione of lavoro.dettagli) {
      if (elaborazione.id_elaborazione === id_elaborazione) {
        elaborazione.stato = 1
        break
      }
    }
  }
  //elaborazioniInCorso.value.filter((lavoro) => lavoro.nome === id_elaborazione)[0].stato = 1
}

async function copiaQuery(query) {
  try {
    await navigator.clipboard.writeText(query)
    messaggioPositivo('Query copiata negli appunti')
  } catch (error) {
    gestioneErrore(error, 'Impossibile copiare la query')
  }
}

const convertiPathWindows = (path) => {
  return path ? path.replace(/\//g, '\\') : ''
}

const copiaPercorso = async (path) => {
  try {
    await navigator.clipboard.writeText(convertiPathWindows(path))
    messaggioPositivo('Percorso copiato')
  } catch (e) {
    gestioneErrore(e, 'Errore durante la copia')
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
      dialogPrenotazioneVisible.value = false
      prelevaElaborazioniInCorso()
      messaggioPositivo('Prenotazione effettuata')
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile lanciare prenotazione - ' + e.response.data.message)
    })
}

function riesporta(row) {
  // Richiama l'endpoint di chiusura/esportazione
  const datiAzione = {
    endpoint: 'chiudi_elaborazione.php',
    output: 'zip'
  }
  lanciaAzione(datiAzione, row.id_elaborazione)
}

function confermaElimina(row) {
  richiediConferma(`Sei sicuro di voler eliminare i dati dell'elaborazione ${row.nome_elaborazione}? L'operazione è irreversibile e i dati verranno rimossi dal database.`)
    .onOk(() => {
      api.post('/elimina_elaborazione.php', { id_elaborazione: row.id_elaborazione })
        .then(() => {
          messaggioPositivo('Dati eliminati con successo')
          prelevaElaborazioniConcluse()
        })
        .catch((e) => {
          gestioneErrore(e, 'Errore durante l\'eliminazione dati')
        })
  })
}

function lanciaAzione(datiAzione, id_elaborazione) {
  if (datiAzione.parametri?.includes('d')) {
    if (dataAzioni.value === null) {
      gestioneErrore(null, 'Data non inserita')
      return
    }
  }
  var dati = {
    data: dataAzioni.value,
    id_elaborazione: id_elaborazione,
  }

  const isPdf = datiAzione.output === 'pdf'
  const isZip = datiAzione.output === 'zip'
  const config = (isPdf || isZip) ? { responseType: 'blob' } : {}

  api
    .post('/' + datiAzione.endpoint, dati, config)
    .then((response) => {
      if (isPdf || isZip) {
        const url = window.URL.createObjectURL(new Blob([response.data]))
        const link = document.createElement('a')
        link.href = url

        let filename = isZip ? 'esportazione.zip' : 'etichette.pdf'
        const contentDisposition = response.headers['content-disposition'] || response.headers['Content-Disposition']

        if (contentDisposition) {
          // Questa regex intercetta sia filename= che filename*= gestendo UTF-8 e apici
          const fileNameMatch = contentDisposition.match(/filename\*?=['"]?(?:UTF-8'')?([^'";\n]*)['"]?/i);
          if (fileNameMatch && fileNameMatch[1]) {
            // decodeURIComponent trasforma %20 in spazio e gestisce gli altri caratteri codificati
            filename = decodeURIComponent(fileNameMatch[1]);
          }
        }

        link.setAttribute('download', filename)
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)

        if (datiAzione.endpoint === 'chiudi_elaborazione.php') {
          prelevaElaborazioniInCorso()
          prelevaElaborazioniConcluse()
        }
      } else {
        messaggioPositivo('Azione eseguita')
      }
      dataAzioni.value = null
      dialogAzioniVisible.value = false
    })
    .catch(async (e) => {
      let messaggio = 'Errore sconosciuto';

      // Se la risposta è un Blob (caso errore con responseType: 'blob')
      if (e.response?.data instanceof Blob && config?.responseType === 'blob') {
        const text = await e.response.data.text();
        const errorData = JSON.parse(text);
        messaggio = errorData.message;
      } else {
        // Caso standard (JSON già decodificato o altri errori)
        messaggio = e.response?.data?.message || e.message;
      }

      gestioneErrore(
        e,
        'Impossibile eseguire azione ' + datiAzione.endpoint + ' - ' + messaggio,
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
