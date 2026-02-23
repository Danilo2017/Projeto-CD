FROM php:8.1-apache

RUN apt-get update && apt-get install -y \
    locales \
    wget \
    git \
    libzip-dev \
    libicu-dev \
    libpng-dev \
    libjpeg-dev \
    libpq-dev \
    libfreetype6-dev \
    unzip \
    zlib1g-dev \
    freetds-bin \
    freetds-dev \
    freetds-common \
    libsybdb5 && \
    ln -s /usr/lib/x86_64-linux-gnu/libsybdb.so /usr/lib/ && \
    docker-php-ext-install pdo pdo_dblib && \
    docker-php-ext-configure pdo_dblib --with-libdir=lib/x86_64-linux-gnu && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*
RUN set -eux; \
    apt-get update; \
    if apt-get install -y --no-install-recommends libaio1; then \
        echo "libaio1 instalado"; \
    else \
        apt-get install -y --no-install-recommends libaio1t64; \
        if [ -e /usr/lib/x86_64-linux-gnu/libaio.so.1t64 ] && [ ! -e /usr/lib/x86_64-linux-gnu/libaio.so.1 ]; then \
            ln -s /usr/lib/x86_64-linux-gnu/libaio.so.1t64 /usr/lib/x86_64-linux-gnu/libaio.so.1; \
        fi; \
    fi; \
    rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install intl mysqli pdo pdo_mysql

RUN apt-get update && apt-get install -y bash

RUN echo "pt_BR.UTF-8 UTF-8" >> /etc/locale.gen && \
    locale-gen "pt_BR.UTF-8" && \
    dpkg-reconfigure --frontend=noninteractive locales && \
    update-locale LANG="pt_BR.UTF-8"

RUN ln -fs /usr/share/zoneinfo/America/Sao_Paulo /etc/localtime && \
    dpkg-reconfigure -f noninteractive tzdata

RUN apt update && apt install -y wget unzip && rm -rf /var/lib/apt/lists/*
RUN cd /tmp/ && wget https://download.oracle.com/otn_software/linux/instantclient/216000/instantclient-basic-linux.x64-21.6.0.0.0dbru.zip && \
    wget https://download.oracle.com/otn_software/linux/instantclient/216000/instantclient-sdk-linux.x64-21.6.0.0.0dbru.zip && \
    unzip '*.zip' && \
    rm *.zip
RUN docker-php-ext-configure oci8 --with-oci8=instantclient,/tmp/instantclient_21_6/
RUN docker-php-ext-install oci8 && \
    echo /tmp/instantclient_21_6 > /etc/ld.so.conf.d/oracle.conf && \
    ldconfig
RUN docker-php-ext-configure pdo_oci --with-pdo-oci=instantclient,/tmp/instantclient_21_6/
RUN docker-php-ext-install pdo_oci

RUN apt-get install -y libpq-dev \
    && docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo pdo_pgsql pgsql

RUN docker-php-ext-configure gd --with-jpeg --with-freetype && \
    docker-php-ext-install gd

RUN a2enmod rewrite

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/usr/local/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

COPY . /var/www/html
WORKDIR /var/www/html

RUN mkdir -p logs
RUN chmod -R 777 logs

RUN composer install --ignore-platform-reqs

RUN wget https://github.com/DataDog/dd-trace-php/releases/latest/download/datadog-setup.php -O datadog-setup.php
RUN php datadog-setup.php --php-bin all

RUN docker-php-ext-install sockets
RUN docker-php-ext-install bcmath

RUN rm /usr/local/etc/php/php*

ADD .docker/php/php.ini /usr/local/etc/php/php.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

RUN pecl install xdebug && docker-php-ext-enable xdebug
RUN echo "xdebug.mode= develop,debug,coverage" >> "/usr/local/etc/php/conf.d/xdebug.ini" \
&& echo "xdebug.log = '/tmp/xdebug.log'" >> "/usr/local/etc/php/conf.d/xdebug.ini" \
&& echo "xdebug.client_port= 9000" >> "/usr/local/etc/php/conf.d/xdebug.ini" \
&& echo "xdebug.client_host = host.docker.internal" >> "/usr/local/etc/php/conf.d/xdebug.ini" \
&& echo "xdebug.start_with_request= yes" >> "/usr/local/etc/php/conf.d/xdebug.ini"

# Adicionar certificado SSL
RUN echo "openssl.cafile=/etc/ssl/certs/ca-certificates.crt" >> /usr/local/etc/php/conf.d/security.ini

EXPOSE 80
