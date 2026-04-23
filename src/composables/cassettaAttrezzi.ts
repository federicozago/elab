import {useQuasar} from 'quasar'
export function useCassettaAttrezzi() {
  const $q = useQuasar()
  const gestioneErrore = (error: any,msg: string) => {
    if (error.response) {
      // La richiesta è stata effettuata e il server ha risposto con un codice di stato
      $q.notify({
        type: 'negative',
        message: msg,
        position: 'top',
        timeout: 2500,
        actions: [{ icon: 'close', color: 'white', round: true }],
      })
      console.log('Dati errore:', error.response.data)
      console.log('Status:', error.response.status)
      console.log('Headers:', error.response.headers)
    } else if (error.request) {
      // La richiesta è stata effettuata ma non è stata ricevuta alcuna risposta
      console.log('Errore richiesta:', error.request)
    } else {
      // Si è verificato un errore durante l'impostazione della richiesta
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

  return{
    gestioneErrore,
    messaggioPositivo
  }
}



