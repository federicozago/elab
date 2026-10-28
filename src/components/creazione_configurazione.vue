<template>
  <div>
    <div class="row items-center q-gutter-x-sm">
      <BaseSelect
        class="col"
        label="Lista configurazioni esistenti"
        v-model="idConfigurazioneSelezionata"
        :options="configurazioni"
        @update:model-value="configurazioneCambiata"
        :disable="!props.tipoSpedizione"
      ></BaseSelect>
      <div class="col-auto q-mb-md" v-if="idConfigurazioneSelezionata">
        <q-btn
          color="negative"
          icon="delete"
          round
          flat
          @click="confermaEliminaConfigurazione"
        >
          <q-tooltip>Elimina questa configurazione</q-tooltip>
        </q-btn>
      </div>
    </div>
    <BaseToggle
      v-model="configurazioneEdit"
      label="Modifica configurazione"
      :disable="!idConfigurazioneSelezionata"
      v-if="!props.creazioneNuovaConfigurazione"
    >
      <q-tooltip
        >Attivo: "Salva" modifica la configurazione selezionata. Disattivo: crea una nuova
        configurazione con i dati indicati</q-tooltip
      >
    </BaseToggle>

    <q-dialog v-model="dialogModificaONuovaVisibile" persistent>
      <q-card style="min-width: 380px">
        <q-card-section>
          <div class="text-h6">
            {{ idConfigurazioneSelezionata ? idConfigurazioneSelezionata.label : '' }}
          </div>
          <div class="q-mt-sm">
            Vuoi modificare questa configurazione o crearne una nuova con gli stessi dati?
          </div>
        </q-card-section>
        <q-card-actions align="right">
          <BaseBtn label="Modifica" @click="scegliModificaConfigurazione" />
          <BaseBtn label="Nuova configurazione" @click="scegliNuovaConfigurazione" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <BaseForm
      ref="baseFormRef"
      :formData="formData"
      @submit="inviaDati"
      labelInvia="SALVA CONFIGURAZIONE"
      @reset="reset"
      :showResetButton="true"
      :loading="isSubmitting"
      v-if="props.tipoSpedizione"
    >
      <h3>
        {{ configurazioneEdit ? 'Modifica' : 'Nuova' }} Configurazione {{ props.tipoSpedizione }}
        {{ idConfigurazioneSelezionata ? idConfigurazioneSelezionata.label : '' }}
      </h3>

      <h6>Dettagli configurazione</h6>
      <BaseInput
        v-model="formData.nome_configurazione"
        label="Info aggiuntive nome configurazione"
        :rules="[maxLength(100)]"
      >
        <q-tooltip
          >Facoltativo: viene accodato al nome generato automaticamente (ragione sociale, tipo
          spedizione, descrizione prodotto, formato postale)</q-tooltip
        >
      </BaseInput>

      <BaseInput
        v-model="formData.ragione_sociale_cliente_estesa"
        label="Ragione Sociale cliente estesa (*)"
        :rules="[required, maxLength(100)]"
      >
        <q-tooltip>Compare in sma e etichette bancale</q-tooltip>
      </BaseInput>

      <BaseSelect
        v-if="tipoEffettivo === 'target'"
        label="Descrizione prodotto spedizione (*)"
        v-model="formData.descrizione_tipo_spedizione"
        :options="descrizioniProdottoTarget"
        :rules="[required]"
        :disable="formData.target.prodotto_target === 'NO_PROFIT'"
      >
        <q-tooltip
          >Compare nelle etichette scatole e bancale. Con "PROMOZIONE NO PROFIT" sull'etichetta scatola il
          prodotto riporta questa dicitura al posto di creative/basic</q-tooltip
        >
      </BaseSelect>
      <BaseInput
        v-else
        label="Descrizione prodotto spedizione (*)"
        v-model="formData.descrizione_tipo_spedizione"
        :rules="[required, maxLength(255)]"
      >
        <q-tooltip>Compare nelle etichette scatole e bancale</q-tooltip>
      </BaseInput>

      <BaseInput
        v-model="formData.n_conto_contrattuale"
        label="Numero conto contrattuale (*)"
        :rules="[required, maxLength(12), minLength(12)]"
        ><q-tooltip>Inserire sap e numero contrattuale divisi da trattino</q-tooltip></BaseInput
      >
      <BaseInput
        v-model="formData.sap"
        label="Sap (*)"
        :rules="[required, maxLength(8), minLength(8)]"
      />

      <BaseToggle
        label="Gestione bancali"
        v-model="formData.gestione_bancali"
        v-if="tipoEffettivo === 'target'"
      ></BaseToggle>
      <BaseInput
        v-model="formData.tara_pallet"
        label="Tara pallet (gr) (*)"
        v-if="tipoEffettivo === 'target' && formData.gestione_bancali"
        :rules="[required, minValue(0)]"
      />

      <BaseToggle label="Con prenotazione" v-model="formData.con_prenotazione">
        <q-tooltip
          >Abilita la prenotazione postale, altrimenti viene salvata solamente la data di
          spedizione</q-tooltip
        >
      </BaseToggle>

      <BaseInput
        v-if="!taraScatolaNascosta"
        v-model="formData.tara_scatola"
        :label="tipoEffettivo === 'target' ? 'Tara scatola (gr) (*)' : 'Tara scatola (Kg) (*)'"
        :rules="[taraScatolaValida]"
      >
        <q-tooltip v-if="tipoEffettivo === 'target'"
          >Peso della scatola in grammi (di norma 200). Per i plichi è sempre 0</q-tooltip
        >
      </BaseInput>

      <BaseSelect
        v-model="formData.tipo_formato_postale"
        label="Formato postale (*)"
        :options="['P', 'M']"
      />

      <BaseSelect
        v-model="formData.cmp"
        label="CMP"
        :options="[
          'CMP ANCONA',
          'CMP BARI',
          'CMP BOLOGNA',
          'CMP CAGLIARI',
          'CMP CATANIA',
          'CMP FIRENZE',
          'CMP GENOVA',
          'CMP LAMEZIA TERME',
          'CMP MILANO BORROMEO',
          'CMP MILANO ROSERIO',
          'CMP NAPOLI',
          'CMP PADOVA',
          'CMP PALERMO',
          'CMP ROMA FIUMICINO',
          'CMP TORINO ROMOLI',
          'CMP VERONA',
        ]"
      />

      <BaseToggle label="Prenotazione con DU" v-model="formData.prenotazione_con_du" />
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Codice cliente postale (*)"
        v-model="formData.codice_cliente_postale"
        :rules="[required, maxLength(3)]"
      >
        <q-tooltip>Codice a 3 caratteri che compare nella DU</q-tooltip>
      </BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Cap mittente (*)"
        v-model="formData.cap_mittente"
        :rules="[required, maxLength(5)]"
      ></BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Utenza ps online (*)"
        v-model="formData.utenza_ps_online"
        :rules="[required, maxLength(255)]"
      ></BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Tipologia prodotto (*)"
        v-model="formData.tipologia_prodotto"
        :rules="[required, maxLength(1)]"
      >
        <q-tooltip>Compare nella DU. Può essere V se Time, B se Contest, R se Market</q-tooltip>
      </BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Codice prodotto (*)"
        v-model="formData.codice_prodotto"
        :rules="[required, minValue(0), maxValue(299)]"
      >
        <q-tooltip
          >Compare nella DU. Può essere 80 se Time, 125 se Contest, 233 se Market</q-tooltip
        >
      </BaseInput>
      <BaseSelect
        label="Codice servizio accessorio (*)"
        v-if="formData.prenotazione_con_du"
        v-model="formData.codice_servizio_accessorio"
        :options="[
          { label: 'Qui e Ora + Resi Report', value: 'CT' },
          { label: 'In Consegna + Resi Report', value: 'TT' },
          { label: 'Infodelivery', value: 'IDS' },
        ]"
      >
        <q-tooltip
          >Compare nella DU. Per Kpm si usa In Consegna + Resi Report. Dipende dal contratto fatto
          dal cliente. Vedere la guida di Poste per la generazione della DU, tabella 2</q-tooltip
        >
      </BaseSelect>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Codice identificativo pallet (*)"
        v-model="formData.codice_identificativo_pallet"
        :rules="[required, minValue(0), maxValue(100)]"
      >
        <q-tooltip>Compare nella DU. Solitamente è 95 per Market e Contest, 91 per Time</q-tooltip>
      </BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Tipologia codice di tracciatura (*)"
        v-model="formData.tipologia_codice_di_tracciatura"
        :rules="[required, minValue(1), maxValue(3)]"
      >
        <q-tooltip
          >Compare nella DU. Solitamente è 1 quando c'è il datamatrix, 2 quando c'è il barcode
          raccomandata</q-tooltip
        >
      </BaseInput>
      <BaseInput
        v-if="formData.prenotazione_con_du"
        label="Campi spare"
        v-model="formData.campi_spare"
      >
        <q-tooltip
          >Inserire i campi divisi da virgola (E' possibile inserire i campi del file del cliente o
          i campi dell'ordinamento come tariffa,peso_plico ecc...)</q-tooltip
        >
      </BaseInput>

      <BaseToggle label="Stampa datamatrix" v-model="formData.stampa_datamatrix"></BaseToggle>
      <BaseInput
        v-if="formData.stampa_datamatrix"
        label="Descrittivo gamma"
        :model-value="gammaAutomatico"
        readonly
      >
        <q-tooltip
          >Compare nel datamatrix. Non è modificabile, dipende dal tipo di spedizione: V per Time, E
          per No Profit, T per Target, B per Massiva</q-tooltip
        >
      </BaseInput>
      <BaseInput
        v-if="formData.stampa_datamatrix"
        label="Classe postale (*)"
        v-model="formData.classe_postale"
        :rules="[required, minValue(1), maxValue(2)]"
      >
        <q-tooltip
          >Compare nel datamatrix, solitamente è 1 (Ordinaria). Si può mettere anche 2 in caso di
          Prioritaria</q-tooltip
        >
      </BaseInput>
      <BaseInput
        v-if="formData.stampa_datamatrix"
        label="Tipo prodotto postale (*)"
        v-model="formData.tipo_prodotto_postale"
        :rules="[required, maxLength(1)]"
      >
        <q-tooltip
          >Compare nel datamatrix. Per massiva mettere W, per Time Kpm T, per No Profit solitamente
          è Q ma verificare, per il PT Creative è W, per il PT Card è U, per il PT Basic è S,
          verificare comunque guardando guida postale tabella 3 pagina 7</q-tooltip
        >
      </BaseInput>
      <BaseInput
        v-if="formData.stampa_datamatrix"
        label="Codice identificativo stampatore (*)"
        v-model="formData.codice_identificativo_stampatore"
        :rules="[required, maxLength(2)]"
      >
        <q-tooltip>Compare nel datamatrix, se stampatore è Indi allora BC, se kpm SE</q-tooltip>
      </BaseInput>
      <BaseInput
        v-if="formData.stampa_datamatrix"
        label="Campi cliente da inserire sul barcode (*)"
        v-model="formData.barcode_campi_cliente"
        :rules="[required, maxLength(255)]"
      >
        <q-tooltip
          >Inseserire il campo che si desidera sia presente nel datamatrix. Se più di uno scrivere
          una cosa tipo concat(campo1,id_tabella). N commessa: id_flusso, id: id_tabella
        </q-tooltip>
      </BaseInput>

      <BaseSelect
        v-if="formData.stampa_datamatrix"
        label="Tabella codici figli (*)"
        v-model="formData.tabella_codici_figli"
        :options="tabelleFigli"
        :rules="[required]"
      >
        <q-tooltip
          >Tabella mysql da cui il programma preleva il codice figlio (prenotazione) che compare nel
          datamatrix. Un codice viene consumato a ogni ordinamento. Sono elencate le tabelle di
          import_file ed elab che iniziano per figli_</q-tooltip
        >
      </BaseSelect>

      <BaseInput
        :label="formData.stampa_datamatrix ? 'Autorizzazione postale (*)' : 'Autorizzazione postale'"
        v-model="formData.autorizzazione_postale"
        :rules="formData.stampa_datamatrix ? [required] : []"
      ></BaseInput>

      <BaseInput
        :label="formData.stampa_datamatrix ? 'Omologa (*)' : 'Omologa'"
        v-model="formData.omologa"
        :rules="formData.stampa_datamatrix ? [required] : []"
      ></BaseInput>

      <BaseRadio
        label="N etichette per foglio (*)"
        v-model="formData.etichette_per_foglio"
        :rules="[required]"
        :elementi="[2, 3]"
      />

      <BaseToggle label="Etichette uppate" v-model="formData.etichetta_n_up" />

      <BaseInput
        label="Peso busta vuota(*)"
        v-model="formData.peso_busta_vuota"
        :rules="[minValue(0)]"
      >
        <q-tooltip
          >Inserire 0 se non c'è busta ma è solo autoimbustante (ad es. suzuki). Se c'è l'inserto
          inserire il relativo peso in "peso inserto"</q-tooltip
        >
      </BaseInput>

      <div class="row q-gutter-x-md items-center">
        <div class="col-auto">
          <BaseToggle label="Inserto" v-model="formData.contiene_inserto" />
        </div>
        <div class="col">
          <BaseInput
            v-if="formData.contiene_inserto"
            label="Peso inserto (*)"
            v-model="formData.peso_inserto"
            :rules="[required, minValue(0)]"
          />
        </div>
      </div>

      <!-- dettagli configurazione tipo spedizione specifica (target, massiva...)-->
      <component
        :is="formAttuale"
        v-model="formData[tipoEffettivo]"
        :gestione-bancali="formData.gestione_bancali"
        v-bind="tipoEffettivo === 'target' ? { bloccaProdotto: isNoProfit } : {}"
      /><!--Il parametro :is dice a Vue: "Non renderizzare un tag HTML standard, ma renderizza il componente che ti sto passando in questa variabile".-->
    </BaseForm>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import BaseInput from 'components/forms/BaseInput.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import BaseBtn from 'components/forms/BaseBtn.vue'
import NewConfigTarget from 'components/NewConfigTarget.vue'
import { required, minLength, maxLength, minValue, maxValue } from 'src/composables/rules.js'
import { api } from 'boot/axios.js'
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi.ts'
const { gestioneErrore, messaggioPositivo, richiediConferma } = useCassettaAttrezzi()
import BaseForm from 'components/forms/BaseForm.vue'
import BaseToggle from 'components/forms/BaseToggle.vue'
import NewConfigMassiva from 'components/NewConfigMassiva.vue'
import BaseRadio from 'components/forms/BaseRadio.vue'

const idConfigurazioneSelezionata = ref('')
const configurazioni = ref([])
const configurazioneEdit = ref(false)
const isSubmitting = ref(false)

const baseFormRef = ref(null)

const props = defineProps({
  tipoSpedizione: {
    type: String,
    required: true,
  },
  nomeConfigurazione: {
    type: String,
    required: false,
  },
  modelValue: {
    type: Object,
    default: null,
  },
  creazioneNuovaConfigurazione: {
    type: Boolean,
    default: false,
  },
})

// NO_PROFIT è un alias di target: stessa tabella e stesso form, con sotto prodotto NO_PROFIT e descrizione prodotto fissi
const isNoProfit = computed(() => props.tipoSpedizione === 'NO_PROFIT')
const tipoEffettivo = computed(() => (isNoProfit.value ? 'target' : props.tipoSpedizione))

const emit = defineEmits(['update:modelValue', 'saved', 'cancel'])

onMounted(() => {})

const codici_age = {
  'CMP ANCONA': 'AGE02149',
  'CMP BARI': 'AGE07143',
  'CMP BOLOGNA': 'AGE11167',
  'CMP CAGLIARI': 'AGE13334',
  'CMP CATANIA': 'AGE17177',
  'CMP FIRENZE': 'AGE25290',
  'CMP GENOVA': 'AGE28419',
  'CMP LAMEZIA TERME': 'AGE18304',
  'CMP MILANO BORROMEO': 'AGE38594',
  'CMP MILANO ROSERIO': 'AGE38615',
  'CMP NAPOLI': 'AGE40158',
  'CMP PADOVA': 'AGE42177',
  'CMP PALERMO': 'AGE43202',
  'CMP ROMA FIUMICINO': 'AGE55962',
  'CMP TORINO ROMOLI': 'AGE63583',
  'CMP VERONA': 'AGE68172',
}

const dialogModificaONuovaVisibile = ref(false)
const configurazioneCaricata = ref(null)

// l'utente vuole modificare la configurazione selezionata
function scegliModificaConfigurazione() {
  configurazioneEdit.value = true
  // in modifica il nome viene ricalcolato dal backend (campi + "info aggiuntive"): ricavo dal nome salvato le info
  // aggiuntive digitate in precedenza, altrimenti salvando andrebbero perse
  const c = configurazioneCaricata.value
  const prefisso = (
    c.prodotto_target === 'NO_PROFIT' && tipoEffettivo.value === 'target'
      ? [c.ragione_sociale_cliente_estesa, 'NO_PROFIT', c.tipo_formato_postale]
      : [
          c.ragione_sociale_cliente_estesa,
          tipoEffettivo.value,
          c.descrizione_tipo_spedizione,
          tipoEffettivo.value === 'target' ? c.prodotto_target : '',
          c.tipo_formato_postale,
        ]
  )
    .filter((v) => v !== '' && v !== null && v !== undefined)
    .join('_')
  const nomeSalvato = c.nome_configurazione || ''
  formData.value.nome_configurazione = nomeSalvato.startsWith(prefisso + '_')
    ? nomeSalvato.slice(prefisso.length + 1)
    : ''
  dialogModificaONuovaVisibile.value = false
}

// l'utente vuole creare una nuova configurazione partendo dai dati di quella selezionata (che resta invariata)
function scegliNuovaConfigurazione() {
  configurazioneEdit.value = false
  dialogModificaONuovaVisibile.value = false
}

function configurazioneCambiata(val) {
  //prelevo dati configurazione
  api
    .post('/preleva_configurazione.php', {
      id_configurazione: val.value,
      tipo_spedizione: tipoEffettivo.value,
    })
    .then((response) => {
      //setto il formData con i dati prelevati
      Object.keys(response.data.configurazione).forEach((key) => {
        // nome_configurazione non va mai copiato dalla configurazione selezionata: è
        // legato all'elaborazione corrente (viene precompilato con il suo nome in
        // getInitialFormData) e viene accodato al nome finale generato dal backend -
        // se lo sovrascrivessimo qui, copiando una configurazione precedente
        // perderemmo il collegamento con l'elaborazione corrente.
        if (key === 'nome_configurazione') return

        //controllo se il valore dovrebbe essere boolean (da php torna 0/1 per indicare true/false
        let valorePrec = ''
        var valore = response.data.configurazione[key]

        if (key in formData.value) valorePrec = formData.value[key]
        else if (key in formData.value[tipoEffettivo.value])
          valorePrec = formData.value[tipoEffettivo.value][key]

        if (typeof valorePrec == 'boolean') {
          //converto 0=>false 1=>true
          if (valore) valore = true
          else valore = false
        }

        if (key in formData.value) {
          //se il valore è a livello principale di formData
          formData.value[key] = valore
        } else if (key in formData.value[tipoEffettivo.value])
          //se il valore è a livello secondario di formData
          formData.value[tipoEffettivo.value][key] = valore
      })

      // dalla pagina configurazioni (non dal dialog di creazione lavoro) selezionando una configurazione esistente chiedo
      // all'utente se vuole modificarla o crearne una nuova a partire dai suoi dati: la risposta imposta il flag "Modifica configurazione"
      if (!props.creazioneNuovaConfigurazione) {
        configurazioneCaricata.value = response.data.configurazione
        dialogModificaONuovaVisibile.value = true
      }
    })
    .catch((e) => {
      gestioneErrore(e, 'Impossibile prelevare la configurazione, controllare i dati inseriti')
    })
}

const age = computed(() => codici_age[formData.value.cmp])

// descrittivo gamma del datamatrix (guida Poste, tabella 1): non si sceglie, dipende dal tipo di spedizione.
// V time, E no profit, T target, B massiva (e per gli altri prodotti bulk mail)
const gammaAutomatico = computed(() => {
  if (tipoEffettivo.value === 'time') return 'V'
  if (tipoEffettivo.value === 'target') return isNoProfit.value ? 'E' : 'T'
  return 'B'
})

// tabelle dei codici figli (figli_* in import_file ed elab) selezionabili quando si stampa il datamatrix
const tabelleFigli = ref([])
async function caricaTabelleFigli() {
  try {
    const response = await api.post('/preleva_tabelle_figli.php')
    tabelleFigli.value = response.data.tabelle
  } catch (e) {
    gestioneErrore(e, 'Impossibile prelevare le tabelle dei codici figli')
  }
}

// target: la descrizione prodotto si sceglie fra due valori (con PROMOZIONE NO PROFIT l'etichetta scatola cambia la dicitura del prodotto)
const descrizioniProdottoTarget = ['TARGET', 'PROMOZIONE NO PROFIT']

// target: la tara scatola è in grammi (di norma 200) e per i plichi è sempre 0, quindi il campo non serve. massiva: resta in kg
const taraScatolaNascosta = computed(
  () => tipoEffettivo.value === 'target' && formData.value.target.plichi === 'Plichi',
)
const taraScatolaValida = (val) => {
  if (val === null || val === undefined || val === '') return 'Campo obbligatorio'
  const n = Number(val)
  if (tipoEffettivo.value !== 'target') return n >= 0 || 'Il valore deve essere almeno 0'
  return n === 0 || n >= 10 || 'Valore in grammi: inserire 0 oppure almeno 10 (es. 200)'
}

let tipologia_prodotto_consigliato = ''
let codice_prodotto_consigliato = ''
let codice_identificativo_pallet_consigliato = ''
let tipologia_codice_di_tracciatura_consigliato = ''
let descrittivo_gamma_consigliato = ''
let tipo_prodotto_postale_consigliato = ''

const componentiForm = {
  target: NewConfigTarget,
  massiva: NewConfigMassiva,
}

const formAttuale = computed(() => componentiForm[tipoEffettivo.value])

if (tipoEffettivo.value === 'time') {
  tipologia_prodotto_consigliato = 'V'
  codice_prodotto_consigliato = 80
  codice_identificativo_pallet_consigliato = 91
  tipologia_codice_di_tracciatura_consigliato = 1
  descrittivo_gamma_consigliato = 'V'
  //tipo_prodotto_postale è gestito dal watch sotto
} else if (tipoEffettivo.value === 'contest') {
  tipologia_prodotto_consigliato = 'B'
  codice_prodotto_consigliato = 125
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 1
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = 'S'
} else if (tipoEffettivo.value === 'market') {
  tipologia_prodotto_consigliato = 'R'
  codice_prodotto_consigliato = 233
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 2
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = '' //non saprei
} else if (tipoEffettivo.value === 'racc_smart') {
  tipologia_prodotto_consigliato = 'R'
  codice_prodotto_consigliato = 225
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 2
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = '' //non saprei
} else if (tipoEffettivo.value === 'target') {
  tipologia_prodotto_consigliato = 'T'
  //codice_prodotto viene aggiornato dal watch sotto
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 1
  descrittivo_gamma_consigliato = 'T'
  //tipo_prodotto_postale_consigliato gestito dal watch sotto
}

// Funzione che restituisce i valori iniziali del form
function getInitialFormData() {
  return {
    stampa_datamatrix: false,
    tabella_codici_figli: '',
    prenotazione_con_du: false,
    gestione_bancali: false,
    contiene_inserto: false,
    nome_configurazione: props.nomeConfigurazione ? props.nomeConfigurazione : '',
    ragione_sociale_cliente_estesa: '',
    peso_busta_vuota: 5,
    peso_inserto: 0,
    descrizione_tipo_spedizione:
      tipoEffettivo.value === 'target'
        ? isNoProfit.value
          ? 'PROMOZIONE NO PROFIT'
          : 'TARGET'
        : tipoEffettivo.value,
    tipo_formato_postale: 'P',
    cmp: 'CMP VERONA',
    n_conto_contrattuale: '',
    sap: '',
    tara_pallet: 22500,
    con_prenotazione: false,
    tara_scatola: tipoEffettivo.value === 'target' ? 200 : 0.2,
    codice_cliente_postale: '',
    cap_mittente: '',
    utenza_ps_online: '',
    tipologia_prodotto: tipologia_prodotto_consigliato,
    codice_prodotto: codice_prodotto_consigliato,
    codice_servizio_accessorio: '',
    codice_identificativo_pallet: codice_identificativo_pallet_consigliato,
    tipologia_codice_di_tracciatura: tipologia_codice_di_tracciatura_consigliato,
    campi_spare: '',
    descrittivo_gamma: descrittivo_gamma_consigliato,
    classe_postale: '1',
    tipo_prodotto_postale: tipo_prodotto_postale_consigliato,
    codice_identificativo_stampatore: 'BC',
    barcode_campi_cliente: 'id_tabella',
    autorizzazione_postale: '',
    omologa: '',
    etichette_per_foglio: 3,
    etichetta_n_up: true,

    target: {
      prodotto_target: isNoProfit.value ? 'NO_PROFIT' : '',
      buste_min: 0,
      buste_max: 0,
      plichi: '',
      contiene_gadget: false,
      tipo_formato_creative:'NORMALIZZATO',
      larghezza_busta: null,
      altezza_busta: null,
      profondita_busta: null,
      larghezza_scatola: null,
      lunghezza_scatola: null,
      altezza_scatola: null,
    },
    massiva: {
      peso_scatola_min: 0,
      peso_scatola_max: 0,
    },
    time: {
      prodotto_time: '',
      kpm: false,
    },
  }
}

const formData = ref(getInitialFormData())
watch(
  () => formData.value.stampa_datamatrix,
  (stampa) => {
    if (stampa && !tabelleFigli.value.length) caricaTabelleFigli()
  },
  { immediate: true },
)
watch(
  () => formData.value.target.prodotto_target,
  (nuovoValore) => {
    if (tipoEffettivo.value === 'target') {
      if (nuovoValore === 'BASIC') {
        formData.value.codice_prodotto = 75
        formData.value.tipo_prodotto_postale = 'S'
      } else if (nuovoValore === 'CREATIVE') {
        formData.value.codice_prodotto = 77
        formData.value.tipo_prodotto_postale = 'W'
      } else if (nuovoValore === 'GOLD') {
        formData.value.codice_prodotto = 68
        formData.value.tipo_prodotto_postale = 'Q'
      } else if (nuovoValore === 'NO_PROFIT') {
        formData.value.descrizione_tipo_spedizione = 'PROMOZIONE NO PROFIT'
      } else if (nuovoValore === 'INVITO ALLA PROVA') {
        formData.value.codice_prodotto = 123
        formData.value.tipo_prodotto_postale = 'Y'
      }
    }
  },
)

// plichi: la tara scatola è sempre 0 (il campo viene nascosto); passando da plichi a scatole ripropongo 200 gr se era a 0
watch(
  () => formData.value.target.plichi,
  (nuovo, vecchio) => {
    if (tipoEffettivo.value !== 'target') return
    if (nuovo === 'Plichi') formData.value.tara_scatola = 0
    else if (nuovo === 'Scatole' && vecchio === 'Plichi' && !Number(formData.value.tara_scatola))
      formData.value.tara_scatola = 200
  },
)

watch(
  () => formData.value.time.prodotto_time,
  (nuovoValore) => {
    if (tipoEffettivo.value === 'time') {
      if (nuovoValore === 'BASE') formData.value.tipo_prodotto_postale = 'Q'
      else if (nuovoValore === 'ORA') formData.value.tipo_prodotto_postale = 'S'
      else if (nuovoValore === 'OPERATORI') formData.value.tipo_prodotto_postale = 'T'
    }
  },
)

watch(
  () => formData.value.time.kpm,
  (nuovoValore) => {
    if (tipoEffettivo.value === 'time') {
      if (nuovoValore === true) formData.value.codice_identificativo_stampatore = 'SE'
      else formData.value.codice_identificativo_stampatore = 'BC'
    }
  },
)

watch(
  () => formData.value.n_conto_contrattuale,
  (nuovoValore) => {
    formData.value.sap = nuovoValore.slice(0, 8)
  },
)

watch(
  () => props.modelValue,
  (nuovoValore) => {
    if (nuovoValore) {
      // se il padre passa un oggetto, lo copiamo nel nostro form
      formData.value = { ...formData.value, ...nuovoValore }
    } else {
      // se il padre passa null (es. chiusura dialog), resettiamo
      reset()
    }
  },
)

// ricarica l'elenco del menu "Lista configurazioni esistenti": va richiamata ogni volta che le configurazioni cambiano
async function caricaConfigurazioni() {
  try {
    const response = await api.post('/preleva_configurazioni.php', {
      tipo_spedizione: props.tipoSpedizione,
    })
    configurazioni.value = response.data.configurazioni
  } catch (e) {
    gestioneErrore(e, 'Impossibile prelevare la configurazione, controllare i dati inseriti')
  }
}

watch(
  () => props.tipoSpedizione,
  (nuovoTipo) => {
    idConfigurazioneSelezionata.value = ''
    configurazioneEdit.value = false
    reset()
    if (nuovoTipo) caricaConfigurazioni()
  },
  { immediate: true }, // immediate: true serve a farlo eseguire anche al primo caricamento (onMounted)
)

async function inviaDati() {
  const datiDaInviare = { ...formData.value }
  datiDaInviare.age = age.value
  datiDaInviare.tipo_spedizione = tipoEffettivo.value

  if (datiDaInviare.stampa_datamatrix) {
    datiDaInviare.descrittivo_gamma = gammaAutomatico.value
  } else {
    delete datiDaInviare.descrittivo_gamma
    delete datiDaInviare.classe_postale
    delete datiDaInviare.tipo_prodotto_postale
    delete datiDaInviare.codice_identificativo_stampatore
    delete datiDaInviare.barcode_campi_cliente
    delete datiDaInviare.tabella_codici_figli
  }

  if (!datiDaInviare.prenotazione_con_du) {
    delete datiDaInviare.codice_cliente_postale
    delete datiDaInviare.cap_mittente
    delete datiDaInviare.utenza_ps_online
    delete datiDaInviare.tipologia_prodotto
    delete datiDaInviare.codice_prodotto
    delete datiDaInviare.codice_servizio_accessorio
    delete datiDaInviare.codice_identificativo_pallet
    delete datiDaInviare.tipologia_codice_di_tracciatura
    delete datiDaInviare.campi_spare
  }

  if (!datiDaInviare.gestione_bancali) {
    delete datiDaInviare.tara_pallet
  }

  if (!datiDaInviare.contiene_inserto) {
    delete datiDaInviare.peso_inserto
  }

  isSubmitting.value = true
  try {
    if (configurazioneEdit.value) {
      if (idConfigurazioneSelezionata.value === '') throw new Error('Selezionare una configurazione')
      datiDaInviare.id_configurazione = idConfigurazioneSelezionata.value.value
      const response = await api.post('/aggiorna_configurazione.php', datiDaInviare)
      messaggioPositivo('Configurazione aggiornata con successo')
      await caricaConfigurazioni()
      // il nome può essere cambiato: riallineo l'etichetta della configurazione selezionata
      idConfigurazioneSelezionata.value =
        configurazioni.value.find((c) => c.value === datiDaInviare.id_configurazione) ||
        idConfigurazioneSelezionata.value
      emit('saved', {
        value: response.data.id_configurazione,
        label: response.data.nome_configurazione,
      })
    } else {
      const response = await api.post('/crea_configurazione.php', datiDaInviare)
      messaggioPositivo('Configurazione creata con successo')
      await caricaConfigurazioni()
      emit('saved', {
        value: response.data.id_configurazione,
        label: response.data.nome_configurazione,
      })
    }
    emit('update:modelValue', formData.value)
  } catch (e) {
    gestioneErrore(
      e,
      (configurazioneEdit.value
        ? 'Impossibile aggiornare la configurazione'
        : 'Impossibile salvare la configurazione') + ', controllare i dati inseriti',
    )
  } finally {
    isSubmitting.value = false
  }
}

function reset() {
  formData.value = getInitialFormData()
}

async function confermaEliminaConfigurazione() {
  if (!idConfigurazioneSelezionata.value) return

  richiediConferma(`Sei sicuro di voler eliminare la configurazione "${idConfigurazioneSelezionata.value.label}"?`)
    .onOk(async () => {
      try {
        const response = await api.post('/elimina_configurazione_postale.php', {
          id_configurazione: idConfigurazioneSelezionata.value.value,
          tipo_spedizione: tipoEffettivo.value
        })

        if (response.data.success) {
          messaggioPositivo('Configurazione eliminata con successo')

          // Ricarica la lista delle configurazioni
          await caricaConfigurazioni()

          // Reset selezione e form
          idConfigurazioneSelezionata.value = ''
          configurazioneEdit.value = false
          reset()
        }
      } catch (e) {
        gestioneErrore(e, 'Errore durante l\'eliminazione della configurazione')
      }
    })
}
</script>
