FROM php:8.2.9-apache

# Aggiorniamo i repository e installiamo le dipendenze con retry in caso di errori di rete
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

# Installiamo le estensioni PHP una alla volta per identificare eventuali problemi
RUN docker-php-ext-install zip
RUN docker-php-ext-install pdo_mysql
RUN docker-php-ext-install sockets
# Aggiungi queste righe dopo le altre estensioni
RUN docker-php-ext-install curl
RUN docker-php-ext-install fileinfo
RUN docker-php-ext-install mbstring
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Installa Xdebug
RUN pecl install xdebug \
    && docker-php-ext-enable xdebug

# Configuriamo PHP per un ambiente di sviluppo
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Configurazione di Apache per PHP
# COPY php-apache.conf /etc/apache2/conf-available/
# RUN a2enconf php-apache
# RUN a2enmod php

#sovrascrivo il php ini con il mio
COPY php.ini "$PHP_INI_DIR/php.ini"

# Impostiamo la directory di lavoro
WORKDIR /var/www/html

# Copy the app files from the app directory (una sola volta)
COPY ./src /var/www/html
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Cambiamo l'utente DOPO aver completato tutte le operazioni che richiedono privilegi root
#USER www-data