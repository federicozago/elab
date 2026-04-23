# PHP Docker Boilerplate

Un ambiente di sviluppo minimo per applicazioni PHP utilizzando Docker e Caddy.

## Requisiti

- [Docker](https://www.docker.com/products/docker-desktop)
- [Docker Compose](https://docs.docker.com/compose/install/) (incluso in Docker Desktop per Windows e Mac)

## Struttura del Progetto

```
php-docker-boilerplate/
├── Caddyfile              # Configurazione del server web Caddy
├── Dockerfile             # Configurazione dell'immagine PHP
├── docker-compose.yml     # Configurazione dei servizi Docker
├── php.ini                # Configurazione di PHP
├── composer.json          # Configurazione di Composer
└── src/                   # Directory dei file sorgente PHP
    └── index.php          # Pagina PHP di esempio
```

## Caratteristiche

- PHP 8.0 con FPM
- Caddy come server web (con supporto HTTPS automatico)
- Configurazione minima e pronta all'uso
- Supporto per le estensioni PHP comuni (zip, pdo_mysql)

## Come Iniziare

### 1. Clona il Repository

```bash
git clone https://github.com/indisrl/php-docker-boilerplate.git
cd php-docker-boilerplate
```

### 2. Avvia l'Ambiente Docker

```bash
docker-compose up -d
```

Questo comando avvierà i container Docker in modalità detached (in background).

### 3. Accedi all'Applicazione

Apri il browser e vai a:

- http://localhost

Dovresti vedere la pagina di esempio PHP con informazioni sul server.

### 4. Ferma l'Ambiente

Per fermare i container:

```bash
docker-compose down
```

## Sviluppo

Tutti i file PHP devono essere posizionati nella directory `src/`. Questa directory è montata come volume nei container, quindi qualsiasi modifica ai file sarà immediatamente visibile senza necessità di riavviare i container.

## Personalizzazione

### Aggiungere Estensioni PHP

Per aggiungere altre estensioni PHP, modifica il `Dockerfile` e aggiungi i comandi di installazione necessari a `docker-php-ext-install`.

### Configurare PHP

Il file `php.ini` nella root del progetto viene montato nel container PHP. Puoi modificare questo file per personalizzare la configurazione di PHP senza dover ricostruire l'immagine Docker. Dopo aver modificato il file, riavvia i container con `docker-compose restart` per applicare le modifiche.

