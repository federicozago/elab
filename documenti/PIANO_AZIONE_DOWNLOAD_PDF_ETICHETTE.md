# Piano d'Azione: Download PDF Etichette con Suggerimento Cartella

Il presente piano descrive le modifiche necessarie per far sì che l'azione "Genera etichette" restituisca il PDF direttamente al browser, consentendo all'utente di salvarlo localmente con un suggerimento sulla cartella di destinazione basato sul campo `folder_z`.

## 1. Analisi Stato Attuale

- **Backend:** `genera_etichette.php` invoca `$elab->genera_etichette_scatole(...)`. Questa funzione salva il PDF sul server nella path composta da `folder_cliente + folder_z`. Il processo termina restituendo un JSON `{ "success": true, "message": "Etichette generate" }`.
- **Frontend:** `IndexPage.vue` chiama l'endpoint tramite `api.post`. In caso di successo, mostra un messaggio positivo e chiude il dialog.

## 2. Modifiche Backend (PHP)

L'obiettivo è generare il PDF in una cartella temporanea, inviarlo al browser e poi eliminarlo dal server. Per gestire la concorrenza tra utenti, verrà usato un nome file univoco.

### File: `php-docker-boilerplate/src/genera_etichette.php`

Va modificato per:
1. Definire una cartella temporanea (es: `temp_pdf/`) all'interno di `src/`.
2. Generare un nome file univoco usando `uniqid()` unito al pattern originale.
3. Invocare `genera_etichette_scatole` passando la cartella temporanea.
4. Impostare gli header HTTP per il download del PDF.
5. Trasmettere il file con `readfile()`.
6. Eliminare il file temporaneo dopo l'invio con `unlink()`.

**Esempio di modifica logica in `genera_etichette.php`:**
```php
// ... inizializzazione ...
$temp_dir = __DIR__ . "/temp_pdf/";
if (!is_dir($temp_dir)) mkdir($temp_dir, 0777, true);

$nome_base = "etic_{$nome_elaborazione}_{$elaborazione['id_flusso']}";
$nome_univoco = $nome_base . "_" . uniqid() . ".pdf";
$path_temporanea = $temp_dir . $nome_univoco;

// Genero etichette nella cartella temporanea
if(!$elab->genera_etichette_scatole($nome_elaborazione, $temp_dir, $nome_univoco)) {
    throw new \Exception("Errore durante la generazione delle etichette");
}

if (file_exists($path_temporanea)) {
    header('Content-Description: File Transfer');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $nome_base . '.pdf"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($path_temporanea));
    
    // Pulizia buffer per evitare corruzione binaria
    ob_clean();
    flush();
    
    readfile($path_temporanea);
    
    // Elimina il file dopo l'invio
    unlink($path_temporanea);
    exit;
}
```

## 3. Modifiche Frontend (Vue.js)

### File: `src/pages/IndexPage.vue`

#### Miglioramento UI nel Dialog Azioni
All'interno del `q-dialog` delle azioni, verrà aggiunto un box di testo che mostra il percorso di destinazione consigliato (`folder_cliente + folder_z`).

1. **Conversione Percorso:** Gli slash `/` verranno convertiti in backslash `\` per compatibilità Windows.
2. **Pulsante Copia:** Accanto al percorso ci sarà un pulsante per copiarlo negli appunti.

**Esempio UI (Template):**
```html
<q-card-section>
  <div class="q-mb-md p-pa-sm bg-grey-2 rounded-borders row items-center">
    <div class="col text-caption text-weight-medium">
      Percorso salvataggio suggerito:<br/>
      <code style="word-break: break-all;">{{ convertiPathWindows(props.row.folder_cliente + props.row.folder_z) }}</code>
    </div>
    <div class="col-auto">
      <BaseBtn 
        flat round dense 
        icon="content_copy" 
        @click="copiaPercorso(props.row.folder_cliente + props.row.folder_z)"
      />
    </div>
  </div>
  <!-- ... lista azioni ... -->
</q-card-section>
```

#### Logica di Riconoscimento PDF
La determinazione se un'azione deve gestire un PDF non avverrà più tramite il nome dell'endpoint, ma controllando la proprietà `output` dell'azione.

**Modifica `lanciaAzione`:**
```javascript
function lanciaAzione(datiAzione, id_elaborazione) {
  // ...
  const isPdf = datiAzione.output === 'pdf'; // Controllo basato sui metadati dell'azione
  const config = isPdf ? { responseType: 'blob' } : {};
  
  api.post('/' + datiAzione.endpoint, dati, config)
    .then((response) => {
      if (isPdf) {
        // Logica download blob...
      } else {
        messaggioPositivo('Azione eseguita');
      }
    });
}
```

## 4. Dettaglio Implementazione Helper

```javascript
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
```

## 5. Vincoli e Note
- La cartella `temp_pdf/` sul server deve avere i permessi di scrittura per l'utente del web server.
- L'utilizzo di `uniqid()` previene sovrascritture in caso di download simultanei.
- Il frontend deve assicurarsi che l'oggetto `datiAzione` passato a `lanciaAzione` contenga la chiave `output`.
