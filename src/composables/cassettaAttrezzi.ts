import {useQuasar} from 'quasar'
export function useCassettaAttrezzi() {
  const $q = useQuasar()
  const gestioneErrore = (error: any, msg: string) => {
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

    if (error.response) {
      console.log('Dati errore:', error.response.data)
      console.log('Status:', error.response.status)
      console.log('Headers:', error.response.headers)
    } else if (error.request) {
      console.log('Errore richiesta:', error.request)
    } else {
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
  }

  const richiediConferma = (msg: string) => {
    return $q.dialog({
      title: 'Conferma',
      message: msg,
      cancel: true,
      persistent: true,
    })
  }

  return{
    gestioneErrore,
    messaggioPositivo,
    richiediConferma,
  }
}



