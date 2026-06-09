<template>
  <div>
    <BaseSelect
      label="Lista configurazioni esistenti"
      v-model="idConfigurazioneSelezionata"
      :options="configurazioni"
      @update:model-value="configurazioneCambiata"
      :disable="!props.tipoSpedizione"
    ></BaseSelect>
    <BaseToggle
      v-model="configurazioneEdit"
      label="Modifica configurazione"
      :disable="!idConfigurazioneSelezionata"
      v-if="!props.creazioneNuovaConfigurazione"
    ></BaseToggle>

    <BaseForm
      ref="baseFormRef"
      :formData="formData"
      @submit="inviaDati"
      labelInvia="SALVA CONFIGURAZIONE"
      @reset="reset"
      :showResetButton="true"
      v-if="props.tipoSpedizione"
    >
      <h3>
        {{ configurazioneEdit ? 'Modifica' : 'Nuova' }} Configurazione {{ props.tipoSpedizione }}
        {{ idConfigurazioneSelezionata ? idConfigurazioneSelezionata.label : '' }}
      </h3>

      <h6>Dettagli configurazione</h6>
      <BaseInput
        v-model="formData.nome_configurazione"
        label="Nome breve configurazione (*)"
        :rules="[required, maxLength(100)]"
      />

      <BaseInput
        v-model="formData.ragione_sociale_cliente_estesa"
        label="Ragione Sociale cliente estesa (*)"
        :rules="[required, maxLength(100)]"
      >
        <q-tooltip>Compare in sma e etichette bancale</q-tooltip>
      </BaseInput>

      <BaseInput
        label="Descrizione spedizione (*)"
        v-model="formData.descrizione_tipo_spedizione"
        :rules="[required, maxLength(255)]"
      >
        <q-tooltip>Compare nelle etichette scatole</q-tooltip>
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

      <BaseToggle label="Gestione bancali" v-model="formData.gestione_bancali"></BaseToggle>
      <BaseInput
        v-model="formData.min_scatole_per_bancale"
        label="Minimo scatole per bancale (*)"
        type="number"
        min="0"
        max="1000"
        v-if="formData.gestione_bancali"
        :rules="[required, minValue(1), maxValue(1000)]"
      />
      <BaseInput
        v-model="formData.max_scatole_per_bancale"
        label="Massimo scatole per bancale (*)"
        type="number"
        min="0"
        max="1000"
        v-if="formData.gestione_bancali"
        :rules="[required, minValue(2), maxValue(1000)]"
      />
      <BaseInput
        v-model="formData.tara_pallet"
        label="Tara pallet (gr) (*)"
        v-if="formData.gestione_bancali"
        :rules="[required, minValue(0)]"
      />

      <BaseToggle label="Con prenotazione" v-model="formData.con_prenotazione">
        <q-tooltip
          >Abilita la prenotazione postale, altrimenti viene salvata solamente la data di
          spedizione</q-tooltip
        >
      </BaseToggle>

      <BaseInput
        v-model="formData.tara_scatola"
        label="Tara scatola (Kg) (*)"
        :rules="[required, minValue(0)]"
      />

      <BaseSelect
        v-model="formData.tipo_formato_postale"
        label="Formato postale (*)"
        :options="['P', 'M']"
      />

      <BaseSelect v-model="formData.cmp" label="CMP" :options="['CMP VERONA', 'CMP BOLOGNA']" />

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
          dal cliente</q-tooltip
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
        label="Descrittivo gamma (*)"
        v-model="formData.descrittivo_gamma"
        :rules="[required, minValue(1), maxValue(3)]"
      >
        <q-tooltip
          >Compare nel datamatrix, per il Time è V, per il target è V altrimenti solitamente è
          B</q-tooltip
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
          >Compare nel datamatrix, per il time dipende se è ora, base operatori (modifica il sotto
          prodotto per capire). Per le raccomandate non saprei</q-tooltip
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

      <BaseInput
        label="Autorizzazione postale"
        v-model="formData.autorizzazione_postale"
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
        v-model="formData[props.tipoSpedizione]"
      /><!--Il parametro :is dice a Vue: "Non renderizzare un tag HTML standard, ma renderizza il componente che ti sto passando in questa variabile".-->
    </BaseForm>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import BaseInput from 'components/forms/BaseInput.vue'
import BaseSelect from 'components/forms/BaseSelect.vue'
import NewConfigTarget from 'components/NewConfigTarget.vue'
import { required, minLength, maxLength, minValue, maxValue } from 'src/composables/rules.js'
import { api } from 'boot/axios.js'
import { useCassettaAttrezzi } from 'src/composables/cassettaAttrezzi.ts'
const { gestioneErrore, messaggioPositivo } = useCassettaAttrezzi()
import BaseForm from 'components/forms/BaseForm.vue'
import BaseToggle from 'components/forms/BaseToggle.vue'
import NewConfigMassiva from 'components/NewConfigMassiva.vue'
import BaseRadio from 'components/forms/BaseRadio.vue'

const idConfigurazioneSelezionata = ref('')
const configurazioni = ref([])
const configurazioneEdit = ref(false)

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

const emit = defineEmits(['update:modelValue', 'saved', 'cancel'])

onMounted(() => {})

const codici_age = {
  'CMP VERONA': 'AGE68172',
  'CMP BOLOGNA': 'AGE11167',
}

function configurazioneCambiata(val) {
  //prelevo dati configurazione
  api
    .post('/preleva_configurazione.php', {
      id_configurazione: val.value,
      tipo_spedizione: props.tipoSpedizione,
    })
    .then((response) => {
      //setto il formData con i dati prelevati
      Object.keys(response.data.configurazione).forEach((key) => {
        //controllo se il valore dovrebbe essere boolean (da php torna 0/1 per indicare true/false
        let valorePrec = ''
        var valore = response.data.configurazione[key]

        if (key in formData.value) valorePrec = formData.value[key]
        else if (key in formData.value[props.tipoSpedizione])
          valorePrec = formData.value[props.tipoSpedizione][key]

        if (typeof valorePrec == 'boolean') {
          //converto 0=>false 1=>true
          if (valore) valore = true
          else valore = false
        }

        if (key in formData.value) {
          //se il valore è a livello principale di formData
          formData.value[key] = valore
        } else if (key in formData.value[props.tipoSpedizione])
          //se il valore è a livello secondario di formData
          formData.value[props.tipoSpedizione][key] = valore
      })
    })
    .catch((e) => {
      gestioneErrore(
        e,
        'Impossibile prelevare la configurazione, controllare i dati inseriti - ' +
          e.response.data.message,
      )
    })
}

const age = computed(() => codici_age[formData.value.cmp])

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

const formAttuale = computed(() => componentiForm[props.tipoSpedizione])

if (props.tipoSpedizione === 'time') {
  tipologia_prodotto_consigliato = 'V'
  codice_prodotto_consigliato = 80
  codice_identificativo_pallet_consigliato = 91
  tipologia_codice_di_tracciatura_consigliato = 1
  descrittivo_gamma_consigliato = 'V'
  //tipo_prodotto_postale è gestito dal watch sotto
} else if (props.tipoSpedizione === 'contest') {
  tipologia_prodotto_consigliato = 'B'
  codice_prodotto_consigliato = 125
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 1
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = 'S'
} else if (props.tipoSpedizione === 'market') {
  tipologia_prodotto_consigliato = 'R'
  codice_prodotto_consigliato = 233
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 2
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = '' //non saprei
} else if (props.tipoSpedizione === 'racc_smart') {
  tipologia_prodotto_consigliato = 'R'
  codice_prodotto_consigliato = 225
  codice_identificativo_pallet_consigliato = 95
  tipologia_codice_di_tracciatura_consigliato = 2
  descrittivo_gamma_consigliato = 'B'
  tipo_prodotto_postale_consigliato = '' //non saprei
} else if (props.tipoSpedizione === 'target') {
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
    prenotazione_con_du: false,
    gestione_bancali: false,
    contiene_inserto: false,
    nome_configurazione: props.nomeConfigurazione ? props.nomeConfigurazione : '',
    ragione_sociale_cliente_estesa: '',
    peso_busta_vuota: 5,
    peso_inserto: 0,
    descrizione_tipo_spedizione: props.nomeConfigurazione ? props.nomeConfigurazione : '',
    tipo_formato_postale: 'P',
    cmp: 'CMP VERONA',
    n_conto_contrattuale: '',
    sap: '',
    min_scatole_per_bancale: null,
    max_scatole_per_bancale: null,
    tara_pallet: 22500,
    con_prenotazione: false,
    tara_scatola: 0.2,
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
    etichette_per_foglio: 3,
    etichetta_n_up: true,

    target: {
      prodotto_target: '',
      buste_min: 0,
      buste_max: 0,
      plichi: '',
      contiene_gadget: false,
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
  () => formData.value.target.prodotto_target,
  (nuovoValore) => {
    if (props.tipoSpedizione === 'target') {
      if (nuovoValore === 'BASIC') {
        formData.value.codice_prodotto = 75
        formData.value.tipo_prodotto_postale = 'S'
      } else if (nuovoValore === 'CREATIVE') {
        formData.value.codice_prodotto = 77
        formData.value.tipo_prodotto_postale = 'W'
      } else if (nuovoValore === 'GOLD') {
        formData.value.codice_prodotto = 68
        formData.value.tipo_prodotto_postale = 'Q'
      } else if (nuovoValore === 'INVITO ALLA PROVA') {
        formData.value.codice_prodotto = 123
        formData.value.tipo_prodotto_postale = 'Y'
      }
    }
  },
)

watch(
  () => formData.value.time.prodotto_time,
  (nuovoValore) => {
    if (props.tipoSpedizione === 'time') {
      if (nuovoValore === 'BASE') formData.value.tipo_prodotto_postale = 'Q'
      else if (nuovoValore === 'ORA') formData.value.tipo_prodotto_postale = 'S'
      else if (nuovoValore === 'OPERATORI') formData.value.tipo_prodotto_postale = 'T'
    }
  },
)

watch(
  () => formData.value.time.kpm,
  (nuovoValore) => {
    if (props.tipoSpedizione === 'time') {
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

watch(
  () => props.tipoSpedizione,
  (nuovoTipo) => {
    idConfigurazioneSelezionata.value = ''
    reset()
    if (nuovoTipo) {
      api
        .post('/preleva_configurazioni.php', { tipo_spedizione: props.tipoSpedizione })
        .then((response) => (configurazioni.value = response.data.configurazioni))
        .catch((e) => {
          gestioneErrore(
            e,
            'Impossibile prelevare la configurazione, controllare i dati inseriti - ' +
              e.response.data.message,
          )
        })
    }
  },
  { immediate: true }, // immediate: true serve a farlo eseguire anche al primo caricamento (onMounted)
)

function inviaDati() {
  const datiDaInviare = { ...formData.value }
  datiDaInviare.age = age.value
  datiDaInviare.tipo_spedizione = props.tipoSpedizione

  if (!datiDaInviare.stampa_datamatrix) {
    delete datiDaInviare.descrittivo_gamma
    delete datiDaInviare.classe_postale
    delete datiDaInviare.tipo_prodotto_postale
    delete datiDaInviare.codice_identificativo_stampatore
    delete datiDaInviare.barcode_campi_cliente
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
    delete datiDaInviare.min_scatole_per_bancale
    delete datiDaInviare.max_scatole_per_bancale
    delete datiDaInviare.tara_pallet
  }

  if (!datiDaInviare.contiene_inserto) {
    delete datiDaInviare.peso_inserto
  }

  if (configurazioneEdit.value) {
    if (idConfigurazioneSelezionata.value === '') throw new Error('Selezionare una configurazione')
    datiDaInviare.id_configurazione = idConfigurazioneSelezionata.value.value
    api
      .post('/aggiorna_configurazione.php', datiDaInviare)
      .then((response) => {
        messaggioPositivo('Configurazione aggiornata con successo')
        emit('saved', {
          value: response.data.id_configurazione,
          label: response.data.nome_configurazione,
        })
      })
      .catch((e) => {
        gestioneErrore(
          e,
          'Impossibile aggiornare la configurazione, controllare i dati inseriti - ' +
            e.response.data.message,
        )
      })
  } else {
    api
      .post('/crea_configurazione.php', datiDaInviare)
      .then((response) => {
        messaggioPositivo('Configurazione creata con successo')
        emit('saved', {
          value: response.data.id_configurazione,
          label: response.data.nome_configurazione,
        })
      })
      .catch((e) => {
        gestioneErrore(
          e,
          'Impossibile salvare la configurazione, controllare i dati inseriti - ' +
            e.response.data.message,
        )
      })
  }
  emit('update:modelValue', formData.value)
}

function reset() {
  formData.value = getInitialFormData()
}
</script>
