# Sail-compatible runtime using Ubuntu 24.04 packages only (no ondrej/Launchpad PPA).
# Use when ppa.launchpadcontent.net is unreachable from the build host (common on some clouds).

FROM ubuntu:24.04

LABEL maintainer="Laravel Sail (Ubuntu repo variant)"

ARG WWWGROUP=1000

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
# Required by vendor/laravel/sail/runtimes/8.3/start-container's own sanity
# check, independent of our supervisord.conf (which no longer references it).
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg gosu git zip unzip supervisor sqlite3 \
        libcap2-bin libpng-dev mysql-client nginx \
        php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring php8.3-xml \
        php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-sqlite3 \
        php8.3-soap php8.3-readline php8.3-ldap php8.3-redis php8.3-pgsql \
        php8.3-imap php8.3-mongodb php8.3-imagick php8.3-msgpack php8.3-igbinary \
        php8.3-memcached php8.3-pcov php8.3-fpm \
        nodejs npm \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && setcap "cap_net_bind_service=+ep" /usr/bin/php8.3 \
    && sed -i 's/^user www-data;/user sail;/' /etc/nginx/nginx.conf \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN userdel -r ubuntu 2>/dev/null || true \
    && groupadd --force -g ${WWWGROUP} sail \
    && useradd -ms /bin/bash --no-user-group -g ${WWWGROUP} -u 1337 sail \
    && git config --global --add safe.directory /var/www/html \
    && mkdir -p /var/www/html/storage/app/public \
    && chown -R sail:sail /var/www/html/storage

COPY vendor/laravel/sail/runtimes/8.3/start-container /usr/local/bin/start-container
COPY docker/8.3/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/8.3/run-vite-shop.sh /usr/local/bin/run-vite-shop.sh
COPY vendor/laravel/sail/runtimes/8.3/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
COPY docker/php/php.ini /etc/php/8.3/cli/conf.d/999-qubix.ini
COPY docker/php/php.ini /etc/php/8.3/fpm/conf.d/999-qubix.ini
COPY docker/php/fpm-pool.conf /etc/php/8.3/fpm/pool.d/www.conf
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/run-vite-shop.sh

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
