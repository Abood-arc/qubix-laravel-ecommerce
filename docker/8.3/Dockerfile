# Sail-compatible runtime using Ubuntu 24.04 packages only (no ondrej/Launchpad PPA).
# Use when ppa.launchpadcontent.net is unreachable from the build host (common on some clouds).

FROM ubuntu:24.04

LABEL maintainer="Laravel Sail (Ubuntu repo variant)"

ARG WWWGROUP=1000

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg gosu git zip unzip supervisor sqlite3 \
        libcap2-bin libpng-dev mysql-client \
        php8.3-cli php8.3-common php8.3-mysql php8.3-mbstring php8.3-xml \
        php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl php8.3-gd php8.3-sqlite3 \
        php8.3-soap php8.3-readline php8.3-ldap php8.3-redis php8.3-pgsql \
        php8.3-imap php8.3-mongodb php8.3-imagick php8.3-msgpack php8.3-igbinary \
        php8.3-memcached php8.3-pcov \
        nodejs npm \
    && curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer \
    && setcap "cap_net_bind_service=+ep" /usr/bin/php8.3 \
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
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/run-vite-shop.sh

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
