# syntax=docker/dockerfile:1

# Questo file crea un'unica immagine che contiene sia il frontend (SPA Quasar)
# sia il backend PHP.
#   - Il frontend viene compilato con `quasar build` (output in dist/spa) e servito
#     da Apache come root del sito.
#   - Le API PHP sono raggiungibili sotto /api. In sviluppo lo stesso prefisso /api
#     è servito dal dev-server Quasar (vedi devServer.proxy in quasar.config.js).

# ---------------------------------------------------------------------------
# Stage 1 - build del frontend Quasar -> genera /app/dist/spa
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS frontend-builder

WORKDIR /app

# Abilita Yarn 4 tramite corepack (versione presa da "packageManager" in package.json)
RUN corepack enable

# Copia i sorgenti (node_modules, dist e .quasar sono esclusi da .dockerignore,
# così vengono ricostruiti in modo pulito dentro l'immagine)
COPY . .

# Installa le dipendenze e costruisce la SPA (script "build" = "quasar build")
RUN yarn install --immutable \
    && yarn build

# ---------------------------------------------------------------------------
# Stage 2 - runtime PHP + Apache che serve la SPA e il backend
# ---------------------------------------------------------------------------
FROM php:8.2.9-apache

# Dipendenze di sistema necessarie alle estensioni PHP
# (con retry sull'update per tollerare errori di rete transitori)
RUN apt-get update || (sleep 10 && apt-get update) \
    && apt-get install -y --no-install-recommends \
        libonig-dev \
        libzip-dev \
        libcurl4-openssl-dev \
        libssl-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        zip \
        unzip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Estensioni PHP
RUN docker-php-ext-install zip pdo_mysql sockets curl fileinfo mbstring \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd

# Xdebug (utile in sviluppo)
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configurazione PHP e virtual host Apache (SPA come root + /api verso il backend)
COPY ./php-docker-boilerplate/php.ini "$PHP_INI_DIR/php.ini"
COPY ./php-docker-boilerplate/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
RUN a2enmod rewrite

# Frontend: la SPA compilata diventa la root del sito servita da Apache
COPY --from=frontend-builder /app/dist/spa/ /var/www/html/

# Backend: gli endpoint PHP (con la cartella vendor/) vengono serviti sotto /api
COPY ./php-docker-boilerplate/src/ /var/www/html/api/

# Cartelle di lavoro usate dagli endpoint (escluse dal build context, vanno ricreate)
RUN mkdir -p /var/www/html/api/temp \
             /var/www/html/api/temp_excel \
             /var/www/html/api/temp_pdf \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html
