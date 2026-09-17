# Qubix — per-client storefront stack template. Rendered via
# scripts/generate-client-compose.sh, which substitutes {{CLIENT_SLUG}}
# and nothing else (docker-compose interpolation like ${DB_PASSWORD} below
# is left untouched for Compose itself to resolve at `up` time).
# STANDALONE (never layered over docker-compose.prod.yml).
#
# Usage on the VPS, from /opt/qubix-{{CLIENT_SLUG}}:
#   docker compose -p qubix-{{CLIENT_SLUG}} -f docker-compose.{{CLIENT_SLUG}}.yml build
#   docker compose -p qubix-{{CLIENT_SLUG}} -f docker-compose.{{CLIENT_SLUG}}.yml up -d
#
# The `qubix` network is EXTERNAL and shared with the primary stack so the shared
# Caddy can reach this app. Only app-{{CLIENT_SLUG}} joins it; the datastores stay
# private on `qubix-{{CLIENT_SLUG}}`.
#
# The service is named `app-{{CLIENT_SLUG}}`, NOT `app`. Compose aliases services by
# name on shared networks — a second `app` would collide with another client's in
# DNS and Caddy would round-robin between the two sites.
#
# json-file log driver has no default size cap — nginx's in-container access/error
# logs now flow to stdout (docker/nginx/default.conf) alongside app/FPM output, so
# every service gets a bounded rotation instead of unbounded growth on disk.
x-logging: &default-logging
  driver: json-file
  options:
    max-size: '10m'
    max-file: '5'

services:
  app-{{CLIENT_SLUG}}:
    build:
      context: .
      dockerfile: docker/8.3/Dockerfile
      args:
        WWWGROUP: '1000'
    image: qubix/app-{{CLIENT_SLUG}}
    restart: unless-stopped
    extra_hosts:
      - 'host.docker.internal:host-gateway'
    environment:
      WWWUSER: '1337'
      LARAVEL_SAIL: 1
      SAIL_VITE_SHOP: 'false'
      XDEBUG_MODE: 'off'
    volumes:
      - '.:/var/www/html'
    networks: [qubix-{{CLIENT_SLUG}}, qubix]
    logging: *default-logging
    depends_on:
      mysql: { condition: service_healthy }
      redis: { condition: service_started }

  queue-{{CLIENT_SLUG}}:
    image: qubix/app-{{CLIENT_SLUG}}
    restart: unless-stopped
    command: php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
    environment:
      WWWUSER: '1337'
      LARAVEL_SAIL: 1
      SAIL_VITE_SHOP: 'false'
    volumes:
      - '.:/var/www/html'
    networks: [qubix-{{CLIENT_SLUG}}]
    logging: *default-logging
    depends_on: [app-{{CLIENT_SLUG}}, redis, mysql]

  scheduler-{{CLIENT_SLUG}}:
    image: qubix/app-{{CLIENT_SLUG}}
    restart: unless-stopped
    command: php artisan schedule:work
    environment:
      WWWUSER: '1337'
      LARAVEL_SAIL: 1
      SAIL_VITE_SHOP: 'false'
    volumes:
      - '.:/var/www/html'
    networks: [qubix-{{CLIENT_SLUG}}]
    logging: *default-logging
    depends_on: [app-{{CLIENT_SLUG}}, mysql]

  # --- MySQL (internal only; import via `docker compose -p qubix-{{CLIENT_SLUG}} exec -T mysql ...`) ---
  mysql:
    image: 'mysql/mysql-server:8.0'
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: '${DB_PASSWORD}'
      MYSQL_ROOT_HOST: '%'
      MYSQL_DATABASE: '${DB_DATABASE}'
      MYSQL_USER: '${DB_USERNAME}'
      MYSQL_PASSWORD: '${DB_PASSWORD}'
    volumes:
      - 'qubix-{{CLIENT_SLUG}}-mysql:/var/lib/mysql'
      # Fleet tuning (Task 2.2) — full replacement of the image's /etc/my.cnf;
      # see docker/mysql/my.cnf's own header for why it's a full file, not an include.
      - './docker/mysql/my.cnf:/etc/my.cnf:ro'
    networks: [qubix-{{CLIENT_SLUG}}]
    logging: *default-logging
    healthcheck:
      test: ['CMD', 'mysqladmin', 'ping', '-p${DB_PASSWORD}']
      retries: 3
      timeout: 5s

  # --- Redis (cache / session / queue; internal only) ---
  # Password-protected by default for every new client stack.
  redis:
    image: 'redis:alpine'
    restart: unless-stopped
    command: redis-server --requirepass '${REDIS_PASSWORD}'
    volumes:
      - 'qubix-{{CLIENT_SLUG}}-redis:/data'
    networks: [qubix-{{CLIENT_SLUG}}]
    logging: *default-logging
    healthcheck:
      test: ['CMD', 'redis-cli', '-a', '${REDIS_PASSWORD}', 'ping']
      retries: 3
      timeout: 5s

networks:
  qubix-{{CLIENT_SLUG}}:
    driver: bridge
  qubix:
    external: true
    # The real network name isn't "qubix" — Compose prefixes every
    # non-external, non-`name:`-pinned network with its project name, so
    # docker-compose.prod.yml's `qubix:` network (declared without a project
    # flag, from /opt/qubix) actually comes up as `qubix_qubix`. Verified on
    # the VPS on 2026-08-31 via `docker network ls`. Keeping the key here as
    # `qubix` (matching how the design doc and CLAUDE.md talk about "the
    # shared qubix network") while pointing `name:` at the real one avoids
    # having to rename every `networks: [..., qubix]` reference below.
    name: qubix_qubix

volumes:
  qubix-{{CLIENT_SLUG}}-mysql: {}
  qubix-{{CLIENT_SLUG}}-redis: {}
