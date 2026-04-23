// rules.js
export const required = (val) => !!val || 'Campo obbligatorio'
export const minLength = (min) => (val) => (val && val.length >= min) || `Minimo ${min} caratteri`
export const isEmail = (val) => /.+@.+\..+/.test(val) || 'Email non valida'
export const maxLength = (max) => (val) => !val || val.length <= max || `Massimo ${max} caratteri`
export const minValue = (min) => (val) =>
  val === null ||
  val === undefined ||
  val === '' ||
  Number(val) >= min ||
  `Il valore deve essere almeno ${min}`
export const maxValue = (max) => (val) =>
  val === null ||
  val === undefined ||
  val === '' ||
  Number(val) <= max ||
  `Il valore deve essere massimo ${max}`

export const sqlSafe = (val) => {
  if (!val) return true // Se vuoto, la regola 'required' se ne occuperà se necessario
  const forbidden = [
    ';',
    '--',
    'DROP',
    'DELETE',
    'TRUNCATE',
    'UPDATE',
    'INSERT',
    'ALTER',
    'CREATE',
    'GRANT',
    'REVOKE',
    'WHERE',
  ]
  const upperVal = val.toUpperCase()

  // Controlla se una delle parole proibite è presente
  const found = forbidden.find((word) => upperVal.includes(word))

  return !found || `La clausola contiene parole non permesse: ${found}`
}

/*
Quasar si aspetta che ogni regola dentro l'array :rules sia una funzione che accetta un unico parametro: il valore attuale del campo (val).
Se scrivessimo semplicemente maxValue(val), come farebbe la funzione a sapere qual è il numero massimo consentito? Avrebbe bisogno di due parametri: val (da Quasar) e max (da te).
Ecco come "legge" il codice JavaScript:
1.
Prima freccia (max) => ...: Questa è la funzione che chiami tu nel componente (es. maxValue(10)). Tu le passi il limite (10).
2.
Seconda freccia (val) => ...: Questa è la funzione "figlia" che viene restituita. È questa la funzione che Quasar userà internamente. Lei "si ricorda" che il max era 10 grazie a un concetto chiamato Closure.
3.
Logica Number(val) <= max || ...:
◦
Number(val): Converte l'input (che spesso è una stringa) in numero per poter fare il confronto.
◦
<= max: Controlla se è minore o uguale al limite.
◦
|| 'messaggio': Se il controllo fallisce (restituisce false), JavaScript restituisce la stringa dopo l'operatore ||, che Quasar visualizzerà come errore.
In sintesi: Usi la prima funzione per configurare la regola (passando il limite 10) e la seconda funzione serve a Quasar per eseguire la validazione sul valore digitato dall'utente.
Senza questa struttura, dovresti scrivere una funzione diversa per ogni numero (es. max10, max20, max100), mentre così ne hai una sola universale.
 */

export const notInArray =
  (array, message = 'Il valore è già presente') =>
  (val) => {
    const list = (array?.value || array || []).map((v) => String(v).toLowerCase())
    return !list.includes(String(val).toLowerCase()) || message
  }

