### Piano Azione 03: Implementazione Multi Elaborazione

#### Obiettivo
Aggiungere la possibilità di definire lavori "multi elaborazione" nella pagina di creazione lavoro.

#### Modifiche in `src/pages/creazione_lavoro.vue`

1.  **Importazione Componente**
    Aggiungere l'import di `BaseToggle` nella sezione script:
    ```javascript
    import BaseToggle from 'components/forms/BaseToggle.vue'
    ```

2.  **Definizione Stato e Inizializzazione**
    Aggiungere una variabile reattiva per gestire lo stato del toggle e modificare l'inizializzazione di `formData`:
    ```javascript
    const isMultiElaborazione = ref(false)

    // Modifica alla riga 171 e seguenti
    const formData = ref({
      id_base_dati: null,
      nome_lavoro: route.query.nome_base_dati ? route.query.nome_base_dati : '',
      elaborazioni: [
        {
          // Riga 176 modificata: default 'elab' se non c'è query
          nome_elaborazione: 'elab',
          where: '',
          tipo_spedizione: '',
          id_configurazione: '',
          id: 0,
        },
      ],
    })
    ```

3.  **Logica di Controllo (Watch)**
    Aggiungere un `watch` per gestire il comportamento del campo `nome_elaborazione` e della lista elaborazioni quando cambia la modalità:
    ```javascript
    watch(isMultiElaborazione, (newVal) => {
      if (!newVal) {
        // Se disattivato, resetta a una sola elaborazione e imposta nome fisso
        if (formData.value.elaborazioni.length > 1) {
          formData.value.elaborazioni = [formData.value.elaborazioni[0]]
        }
        formData.value.elaborazioni[0].nome_elaborazione = 'elab'
      } else {
        // Se attivato, svuota il nome per permettere la definizione utente
        formData.value.elaborazioni[0].nome_elaborazione = ''
      }
    })
    ```

4.  **Modifiche al Template**
    -   Inserire `BaseToggle` appena prima del ciclo `v-for` delle elaborazioni (riga 85):
        ```html
        <BaseToggle
          v-model="isMultiElaborazione"
          label="Multi elaborazione"
        />
        ```
    -   Modificare il `BaseInput` del nome elaborazione (riga 91) per disabilitarlo se non è multi elaborazione:
        ```html
        <BaseInput
          v-model="elaborazione.nome_elaborazione"
          label="Nome sotto elaborazione"
          :disable="!isMultiElaborazione"
          :rules="[required, maxLength(50)]"
        />
        ```
    -   Modificare la visibilità del pulsante "AGGIUNGI" (riga 143):
        ```html
        <BaseBtn 
          label="Aggiungi" 
          @click="aggiungiElaborazione" 
          v-if="isMultiElaborazione"
        ></BaseBtn>
        ```

5.  **Validazione al Submit**
    Modificare la funzione `creaLavoro` per aggiungere il controllo richiesto:
    ```javascript
    async function creaLavoro() {
      // Controllo multi elaborazione
      if (isMultiElaborazione.value && formData.value.elaborazioni.length <= 1) {
        gestioneErrore(null, 'Per un lavoro multi elaborazione è necessario aggiungere almeno due elaborazioni')
        return
      }
      
      // ... resto del codice esistente
    }
    ```

#### Note
Il componente `BaseToggle` è già presente nel progetto in `src/components/forms/BaseToggle.vue`.
La stringa "elab" viene impostata automaticamente quando la modalità multi elaborazione non è attiva.
