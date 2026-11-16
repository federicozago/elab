# Piano d'azione 20 - Alias di target: PUBBL. INFORMATIVA NO PROFIT e PERIODICO ROC

## Obiettivo
Oltre a **NO_PROFIT** (alias di target: ordinamento come creative, sulle etichette la dicitura PROMOZIONE NO PROFIT) servono altri due alias, con lo stesso comportamento:
- **PUBBL_INFORMATIVA_NO_PROFIT** → dicitura "PUBBL. INFORMATIVA NO PROFIT"
- **PERIODICO_ROC** → dicitura "PERIODICO ROC"

Nei menu compaiono come tipi di spedizione a sé (subito dopo target, con il nome tecnico come NO_PROFIT); in database sono configurazioni `target` con `prodotto_target` = nome dell'alias
(`varchar(50)`, nessuna modifica al db; i nomi `tipo_spedizione` salvati restano `target`).

## Regole
- Ordinamento come CREATIVE (cap + località), come NO_PROFIT.
- Etichette scatola e bancale: dicitura fissa dell'alias (`descrizione_tipo_spedizione`, bloccata nel form e reimpostata dal backend); sull'etichetta scatola "Prodotto: <dicitura>".
- **Tipo formato creative** (compatto, normalizzato, voluminoso): compare solo sull'etichetta scatola (`genera_etichette_scatole`) e non serve a nient'altro. Per i tre alias non si chiede più nel form, non si salva
  e non si stampa (anche per le configurazioni NO_PROFIT già esistenti). Per CREATIVE, BASIC e MAGAZINE resta com'era.
- Datamatrix (solo se si stampa): gamma **E** (Editoria, guida Poste tabella 1) per tutti e tre; tipo prodotto postale suggerito **Q** per NO_PROFIT (promozioni no profit) e **W** per le pubblicazioni informative
  (tabella 3); per il periodico ROC la guida non indica un valore (esiste Y "Periodico" e U "Stampe periodiche in regime libero"), nessun suggerimento: si compila a mano se mai servirà.
- Pallet: codice servizio del codice HU **93** (Editoria, guida Poste codice HU) proposto per tutti e tre, no profit compreso (prima il target proponeva 98).

## Cosa è stato fatto
- `php-docker-boilerplate/src/alias_target.php` (nuovo): elenco degli alias (dicitura e gamma), usato dagli endpoint. Va tenuto allineato con `ALIAS_TARGET` in `creazione_configurazione.vue` e in `Elaborazione_postale_target.php`.
- Endpoint: `preleva_tipi_spedizione.php` (menu), `preleva_configurazioni.php` (ogni alias vede solo le sue configurazioni, target le esclude tutte), `crea_lavoro.php` / `aggiorna_lavoro.php` (alias → target),
  `preleva_lavoro.php` (target → alias in base al `prodotto_target`), `crea_configurazione.php` / `aggiorna_configurazione.php` (gamma E, nome configurazione "<cliente>_<alias>", dicitura fissa, niente tipo formato).
- Libreria `Elaborazione_postale_target`: costante `ALIAS_TARGET`; ordinamento come creative; "Prodotto: <dicitura>"; la riga "Tipo formato" solo per i prodotti non alias.
- Form: `creazione_configurazione.vue` (mappa `ALIAS_TARGET`, descrizione bloccata, gamma, tipo prodotto postale e pallet suggeriti, tipo formato non inviato per gli alias), `NewConfigTarget.vue` (sotto prodotto, menu del tipo formato nascosto per gli alias).

## Test eseguiti (db di sviluppo)
- `preleva_tipi_spedizione.php`: target, NO_PROFIT, PUBBL_INFORMATIVA_NO_PROFIT, PERIODICO_ROC, massiva.
- `preleva_configurazioni.php`: ogni alias vede solo le sue; target non vede quelle degli alias.
- `crea_configurazione.php` con PERIODICO_ROC: dicitura "PERIODICO ROC", tipo formato nullo, nome "<cliente>_PERIODICO_ROC_P".
- Etichette scatola (4 PDF generati da tabelle di prova): CREATIVE stampa "Tipo formato: COMPATTO"; i tre alias no, con "Prodotto: <dicitura>" e la dicitura grande. Con "PUBBL. INFORMATIVA NO PROFIT" il
  "Prodotto:" va a capo su due righe (la casella è larga 55 mm).
- Lint (eslint e php -l) ok. Non provato da interfaccia.

## Test manuali da fare
- [ ] Menu: compaiono NO_PROFIT, PUBBL_INFORMATIVA_NO_PROFIT, PERIODICO_ROC dopo target (creazione lavoro, configurazioni).
- [ ] Nuova configurazione per ognuno: descrizione fissa, niente tipo formato creative, gamma E con il datamatrix, pallet proposto 93; salvataggio, modifica, riapertura.
- [ ] Lavoro con un'elaborazione di ogni tipo: la configurazione proposta è solo quella dell'alias; ordinamento e etichette scatola/bancale con la dicitura giusta.
- [ ] Un lavoro NO_PROFIT esistente: ricompare come NO_PROFIT; l'etichetta scatola non ha più "Tipo formato".
- [ ] Un target normale (creative/basic): etichetta con "Tipo formato" come prima.
