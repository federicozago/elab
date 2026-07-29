import {useQuasar} from 'quasar'
import {useNotificationHistoryStore} from 'src/stores/notificationHistoryStore'
export function useCassettaAttrezzi() {
  const $q = useQuasar()
  const notificationHistory = useNotificationHistoryStore()
  // Estrae in modo sicuro il dettaglio di un errore axios/JS. Ritorna null quando
  // non c'è un dettaglio utile (così i messaggi di sola validazione non vengono
  // "sporcati"). NB: non legge i Blob (responseType 'blob'): in quel caso il
  // chiamante deve leggere il blob e passare il testo come 3° argomento.
  const dettaglioErrore = (error: any): string | null => {
    if (!error) return null
    const data = error?.response?.data
    if (data && typeof data === 'object' && typeof data.message === 'string' && data.message.length)
      return data.message
    if (typeof error?.message === 'string' && error.message.length) return error.message
    return null
  }

  // contesto: descrizione dell'operazione (es. "Impossibile creare elaborazione").
  // Il dettaglio dell'errore viene ricavato in automatico e in sicurezza; per casi
  // particolari (es. risposta Blob) lo si può passare esplicitamente come 3° arg.
  const gestioneErrore = (error: any, contesto: string = '', dettaglioEsplicito: string | null = null) => {
      const dettaglio = dettaglioEsplicito ?? dettaglioErrore(error)
      const msg = contesto
        ? (dettaglio ? `${contesto} - ${dettaglio}` : contesto)
        : (dettaglio || 'Errore sconosciuto')
      let timeoutId: NodeJS.Timeout | null = null
      let dismiss: (() => void) | null = null
      const timeoutDuration = 2500

      // Funzione per avviare il timeout
      const startTimeout = () => {
        if (timeoutId) clearTimeout(timeoutId)
        timeoutId = setTimeout(() => {
          if (dismiss) dismiss()
        }, timeoutDuration)
      }

      // Funzione per fermare il timeout
      const stopTimeout = () => {
        if (timeoutId) {
          clearTimeout(timeoutId)
          timeoutId = null
        }
      }

      // Crea la notifica con timeout disabilitato (lo gestiamo manualmente)
      dismiss = $q.notify({
        type: 'negative',
        message: msg,
        position: 'top',
        timeout: 0, // Disabilitiamo il timeout automatico
        actions: [{ icon: 'close', color: 'white', round: true }],
        attrs: {
          onmouseenter: stopTimeout,
          onmouseleave: startTimeout,
        },
      })

      // Avvia il timeout iniziale
      startTimeout()

      notificationHistory.addEvent('negative', msg)

    if (error?.response) {
      console.log('Dati errore:', error.response.data)
      console.log('Status:', error.response.status)
      console.log('Headers:', error.response.headers)
    } else if (error?.request) {
      console.log('Errore richiesta:', error.request)
    } else if (error) {
      console.log('Errore:', error.message)
    }
  }

  const messaggioPositivo = (msg: string) => {
    $q.notify({
      type:"positive",
      message:msg,
      timeout:2500,
      actions: [{ icon: 'close', color: 'white', round: true }],
    })
    notificationHistory.addEvent('positive', msg)
  }

  const richiediConferma = (msg: string) => {
    return $q.dialog({
      title: 'Conferma',
      message: msg,
      cancel: true,
      persistent: true,
    })
  }

  // Copia il testo negli appunti. navigator.clipboard esiste solo in un
  // "contesto sicuro" (HTTPS, o localhost): su http:// con un IP/hostname
  // qualsiasi (es. accesso diretto al server di produzione senza certificato)
  // è undefined. In quel caso si usa il vecchio execCommand('copy'), che non ha
  // questo vincolo.
  const copiaNegliAppunti = async (testo: string) => {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      await navigator.clipboard.writeText(testo)
      return
    }
    const textarea = document.createElement('textarea')
    textarea.value = testo
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.focus()
    textarea.select()
    try {
      if (!document.execCommand('copy')) throw new Error('Copia negli appunti non riuscita')
    } finally {
      document.body.removeChild(textarea)
    }
  }

  return{
    gestioneErrore,
    dettaglioErrore,
    messaggioPositivo,
    richiediConferma,
    copiaNegliAppunti,
  }
}



