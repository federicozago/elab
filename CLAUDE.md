# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

"elab" ("elaborazioni postali") is a system for sorting mailing-list data according to Poste Italiane
(Italian postal service) product rules — Posta Massiva, Raccomandata Market, Target, etc. Users import
customer data (Excel/CSV) into MySQL as a "base dati", group it into one or more "elaborazioni" under a
"lavoro" (job), run product-specific sorting algorithms, generate labels/manifests/reports, and hand off
files to Poste Italiane via SFTP.

This is a monorepo with two halves:
- **Frontend**: Vue 3 + Quasar SPA at the repo root (`src/`).
- **Backend**: plain procedural PHP 8.2 (no framework) in `php-docker-boilerplate/src/`, one file per endpoint.

**Read [documenti/SINTESI_PROGETTO.md](documenti/SINTESI_PROGETTO.md) first** for a full domain/architecture/DB-schema
writeup — it's kept up to date alongside feature commits and is the best orientation document in the repo.
The `documenti/*.md` files (numbered `01`–`15` plus a few unnumbered `PIANO_AZIONE_*.md`) and `.junie/plans/*.md`
are historical AI-assisted "piano di intervento" (action plan) docs written before implementing each feature —
useful as precedent for how a feature was previously scoped and implemented, and for the plan-before-code
workflow this repo follows for non-trivial changes.

## Commands

Frontend (run from repo root):
```
yarn                  # install deps (Yarn 4 / Berry, pinned via packageManager)
yarn dev              # quasar dev — Vite dev server w/ HMR
yarn build            # quasar build — production build to dist/spa
yarn lint             # eslint over ./src*/**/*.{js,cjs,mjs,vue}
yarn format           # prettier --write across the repo
```
There is no frontend test suite (`yarn test` is a no-op stub) and no backend test suite or linter
(`php-docker-boilerplate/test/` contains only a stray `php.ini`, not a test harness). Verification is manual;
`documenti/*.md` plans typically end with a manual test checklist — follow that pattern rather than assuming
`phpunit`/`phpstan` exist.

Backend / full stack, via Docker:
```
docker-compose up --build      # from repo root: builds Quasar SPA + PHP-Apache into one image, port 9001
```
or, for backend-only iteration with live-reload and Xdebug:
```
cd php-docker-boilerplate && docker-compose up --build   # mounts ./src as a live volume, port 9001
```
Both compose files mount a Windows network share (CIFS) containing `variabili_globali.php` — the real
production config (DB/FTP/SMTP credentials, and the `elaborazioni` action-registry array) that lives
**outside this repo**. `temp/elab.php` is a local, git-tracked reference copy of that file's shape, kept for
documentation — it is not read by the running app. See "Secrets" below before touching either file.

In dev, the SPA proxies `/api` → `http://localhost:9001` (see `quasar.config.js` `devServer.proxy` and
`src/boot/axios.js`, which points `$api`'s baseURL at `http://localhost:9001` in dev and `/api/` in prod).

## Architecture

### Frontend (`src/`)
- `pages/IndexPage.vue` — main dashboard: create a quick "elaborazione", monitor active/closed jobs, and
  trigger per-job actions (ordina/sort, azioni like label generation, prenotazione/booking, chiudi/close,
  riapri/reopen, riesporta/re-export as ZIP, elimina dati/delete data).
- `pages/creazione_lavoro.vue` — create/edit a "lavoro" (job): pick a base dati, define one or more
  elaborazioni (sub-jobs) with a postal product type and a SQL `WHERE` filter.
- `pages/creazione_configurazione.vue` — create/edit per-product sorting configuration (e.g. `target`,
  `massiva` config tables: postage, format, customer type, etc.).
- `pages/creazione_BaseDati.vue` — import a new base dati from an Excel/CSV file, mapping required columns
  (CAP/postcode, città/city, provincia/province) and header/separator settings.
- `router/routes.js` — hash-mode router; routes map 1:1 to the pages above under `MainLayout.vue`.
- `components/forms/Base*.vue` — the shared form kit (BaseForm, BaseInput, BaseSelect, BaseFile, BaseBtn,
  BaseDatePicker, BaseToggle, BaseRadio) used across all creation/edit pages; prefer these over ad-hoc
  Quasar inputs when adding new fields.
- `composables/cassettaAttrezzi.ts` / `rules.js` — shared helpers and form-validation rules.
- `stores/` — Pinia stores (`fileStore.js`, `notificationHistoryStore.js`).
- Quasar `lang` is `'it'` — all UI copy and domain terms are Italian; match existing terminology
  (lavoro/elaborazione/base dati/ordinamento/azioni) rather than translating.

### Backend (`php-docker-boilerplate/src/`)
No router/framework: each top-level `.php` file is a standalone HTTP endpoint hit directly (e.g.
`POST /crea_elaborazione.php`), following a consistent shape:
```php
namespace indi\Classes;
require 'vendor/autoload.php';
header("Access-Control-Allow-Origin: *"); // CORS boilerplate, then OPTIONS short-circuit
try {
    $jsonData = json_decode(file_get_contents('php://input'), true);
    $vg  = (new Variabili_globali_import())->get_variabili_globali("elab");
    $log = new Segnalazioni_e_log($vg["id_flusso"], blocca_il_programma_per_qualsiasi_errore: false);
    $db  = new Gestione_db("elab", $log);
    // ... business logic, DB calls via $db, JSON response
} catch (\Throwable $e) { /* JSON error response */ }
```
Endpoints are grouped by resource: `*_lavoro.php` (job CRUD), `*_elaborazione.php` (sub-job lifecycle:
create/close/reopen/close-without-saving/delete-sorted-data), `*_base_dati.php` (data source CRUD/import),
`*_configurazione.php` (per-product config CRUD), `lancia_ordinamento.php` / `aggiungi_ordinamento_bancali_diretti.php`
(run sorting), `genera_etichette*.php` / `genera_report_bancali.php` (label/report generation), `preleva_*.php`
(read/list endpoints), `genera_du.php` (booking: generates the DU and returns it as a ZIP).

Shared infrastructure comes from private Composer packages (namespace `indi\`, see
`php-docker-boilerplate/src/composer.json`), not from this repo:
- `cassetta_attrezzi_php/cassetta_attrezzi_php` — internal utility library: `Gestione_db` (PDO/MySQL wrapper,
  no ORM), `Variabili_globali_import` (loads the external config), `Segnalazioni_e_log` (logging/notifications),
  `Csv`, `Excel`, FTP/PDF helpers. Read its vendored source under `vendor/cassetta_attrezzi_php/...` when you
  need to know an exact method signature — don't guess.
- `elaborazione_postale/elaborazione_postale` — the actual postal-sorting algorithms, one class per product
  (`Elaborazione_postale_target.php`, `_massiva.php`, `_market.php`, `_racc_pro.php`, ...).
- `gestione_automatica_lavoro_da_ftp` — FTP-driven automation variants per product type.

Other backend libs of note: PhpSpreadsheet (Excel I/O), PhpMailer, FPDF/FPDI + TCPDF barcode + picqer barcode
(labels/PDF), ZipStream (job close/export as ZIP), Smalot PdfParser, HTMLPurifier, tesseract OCR wrapper.

`php-docker-boilerplate/src/old/` holds deprecated scripts kept for reference — don't build on them.
`php-docker-boilerplate/README.md` is stale boilerplate (describes a Caddy-based setup); the real stack is
Apache, per the Dockerfiles — don't trust that README for how the container actually runs.

### Database (MySQL, no ORM)
Full schema detail lives in `documenti/SINTESI_PROGETTO.md` ("Struttura Database"); summary:
- `base_dati` — metadata for each imported data source (`nome_base_dati` = physical table name, column
  mapping for CAP/città/provincia, CSV separator).
- `lavori` — a job; FK to `base_dati`.
- `elaborazioni_lavoro` — sub-job *definitions* per lavoro: product type (`tipo_spedizione`), config id, and
  the SQL `WHERE` clause used to filter the source table.
- `elaborazioni` — sub-job *instances* (one row per actual run/upload): `id_flusso` (commessa/batch id),
  `folder_z` (output path), `stato` (0=to sort, 1=in progress, 2=processed, 255=closed).
- Per-product config tables (`target`, `massiva`, ...) — technical sorting parameters.
- Dynamically created per import: a data table named after `nome_base_dati`; a SQL **view** per elaborazione
  named `{nome_lavoro}_{nome_elaborazione}`; and, after sorting runs, a table `ordinati_{tipo}_{nome_base_dati}`
  joined back to the data table via `c1`.

When changing anything that deletes a `lavoro`/`elaborazione`/`base_dati`, check for cascading dependents
(views, `ordinati_*` tables, child `elaborazioni` rows) the way existing `elimina_*.php` endpoints do — several
`documenti/*.md` plans (e.g. the "elimina" series) document this cascade and the safety check that blocks
deleting a lavoro while any child elaborazione has `stato != 255`.

## Secrets — do not leak further

`temp/elab.php`, and both `docker-compose.yml` files (root and `php-docker-boilerplate/`), contain real
plaintext credentials (MySQL, IMAP/SMTP, Poste Italiane SFTP, and a CIFS/SMB share password). Never echo
these values back in full, never copy them into new files/logs/docs, and flag it to the user if you notice
a diff about to introduce more of them. When editing these files, change only what's asked and keep the
diff minimal.

## Working conventions

- This codebase has no CI, no automated backend tests, and no backend linter — treat `yarn lint` (frontend)
  as the only automated check available, and otherwise verify manually (run the dev server / hit the
  endpoint) before considering a change done.
- For non-trivial features, this repo's established workflow is to write a short plan doc under `documenti/`
  (numbered, Italian, `PIANO_AZIONE_*` or `NN_PIANO_AZIONE_*.md`) before implementing — check there first for
  precedent, and follow the same pattern for new non-trivial work unless told otherwise.
- Domain vocabulary is Italian and used consistently across UI, endpoint names, and docs — reuse existing
  terms (lavoro, elaborazione, base dati, ordinamento, azioni, bancali/plichi, folder_z, id_flusso) rather
  than introducing English equivalents.
