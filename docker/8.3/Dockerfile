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

# php8.3-* versions below are pinned to what was actually running in the
# fleet on 2026-09-16 (see .superpowers/sdd/2026-09-14-fleet-readiness-implementation/
# task-2.0-pre-pin-packages.txt) so every client stack rebuilds the same PHP
# runtime instead of whatever Ubuntu happens to ship that day. `apt-get
# upgrade -y` is deliberately dropped (not kept): leaving it in place would
# still let every other base-image package (openssl, curl, git, ...) drift
# between builds weeks apart, which undermines the whole point of this pin
# for everything except PHP, for no benefit beyond fast-forwarding
# already-installed base packages to whatever's latest at build time.
#
# nginx, mysql-client, nodejs and npm are pinned too (captured 2026-09-17,
# same box, same method as the php8.3-* capture above) — Task 2.0 originally
# scoped this to "PHP runtime only" and left these four floating, but nginx
# in particular is the actual request-handling layer after the Phase 0
# hardening work, so an unpinned nginx defeats the same reproducibility goal
# the PHP pins exist for.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg gosu git zip unzip supervisor sqlite3 \
        libcap2-bin libpng-dev \
        mysql-client=8.0.46-0ubuntu0.24.04.4 \
        nginx=1.24.0-2ubuntu7.18 \
        php8.3-cli=8.3.6-0ubuntu0.24.04.11 \
        php8.3-common=8.3.6-0ubuntu0.24.04.11 \
        php8.3-opcache=8.3.6-0ubuntu0.24.04.11 \
        php8.3-mysql=8.3.6-0ubuntu0.24.04.11 \
        php8.3-mbstring=8.3.6-0ubuntu0.24.04.11 \
        php8.3-xml=8.3.6-0ubuntu0.24.04.11 \
        php8.3-curl=8.3.6-0ubuntu0.24.04.11 \
        php8.3-zip=8.3.6-0ubuntu0.24.04.11 \
        php8.3-bcmath=8.3.6-0ubuntu0.24.04.11 \
        php8.3-intl=8.3.6-0ubuntu0.24.04.11 \
        php8.3-gd=8.3.6-0ubuntu0.24.04.11 \
        php8.3-sqlite3=8.3.6-0ubuntu0.24.04.11 \
        php8.3-soap=8.3.6-0ubuntu0.24.04.11 \
        php8.3-readline=8.3.6-0ubuntu0.24.04.11 \
        php8.3-ldap=8.3.6-0ubuntu0.24.04.11 \
        php8.3-redis=5.3.7+4.3.0-3ubuntu1 \
        php8.3-pgsql=8.3.6-0ubuntu0.24.04.11 \
        php8.3-imap=8.3.6-0ubuntu0.24.04.11 \
        php8.3-mongodb=1.15.0+1.11.1+1.9.2+1.7.5-1ubuntu3 \
        php8.3-imagick=3.7.0-4ubuntu3 \
        php8.3-msgpack=1:2.2.0~rc2-3ubuntu1 \
        php8.3-igbinary=3.2.13-1ubuntu3 \
        php8.3-memcached=3.2.0+2.2.0-4ubuntu3 \
        php8.3-pcov=1.0.11-5ubuntu1 \
        php8.3-fpm=8.3.6-0ubuntu0.24.04.11 \
        nodejs=18.19.1+dfsg-6ubuntu5 \
        npm=9.2.0~ds1-2 \
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

# start-container and the Sail php.ini are byte-identical copies of laravel/sail v1.53.0's
# runtimes/8.3 files, tracked under docker/8.3/sail/ on purpose: vendor/ is gitignored, so a
# fresh `git clone` (which is how every provisioned client's checkout is made) has no vendor/
# to COPY from and the image build died with "not found" before anything else could happen.
# If laravel/sail is upgraded, re-copy both files from vendor/laravel/sail/runtimes/8.3/.
COPY docker/8.3/sail/start-container /usr/local/bin/start-container
COPY docker/8.3/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/8.3/run-vite-shop.sh /usr/local/bin/run-vite-shop.sh
COPY docker/8.3/sail/php.ini /etc/php/8.3/cli/conf.d/99-sail.ini
COPY docker/php/php.ini /etc/php/8.3/cli/conf.d/999-qubix.ini
COPY docker/php/php.ini /etc/php/8.3/fpm/conf.d/999-qubix.ini
COPY docker/php/fpm-pool.conf /etc/php/8.3/fpm/pool.d/www.conf
COPY docker/nginx/default.conf /etc/nginx/sites-available/default
RUN chmod +x /usr/local/bin/start-container /usr/local/bin/run-vite-shop.sh

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
