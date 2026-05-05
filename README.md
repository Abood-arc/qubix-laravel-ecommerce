# Qubix Local Development Guide

This repository is configured to run locally with Laravel Sail (Docker).

## What This README Covers

- Clean local setup for macOS and Linux
- Run and stop commands
- Access URLs and default admin login

## Environment and URLs (this machine)

Intended workflow: you develop on **one Linux host** (bare metal, VM, or cloud) with optional SSH access from elsewhere.

- **`.env`**: With Sail, service hostnames are **`mysql`**, **`redis`**, and **`http://elasticsearch:9200`** for Elasticsearch—never `127.0.0.1` for those from inside the app container.
- **`APP_URL`**: Set this to the URL you actually open in the browser **on this machine** (for example `http://localhost:8080` when `APP_PORT=8080`). Laravel uses it for URL generation; Vite uses its **hostname** for HMR unless you set **`VITE_HMR_HOST`** (see `vite.shared.mjs`).
- **`VITE_PORT`**: Defaults to **5173** for Shop dev (published in `docker-compose.yml`). Change it only if that port is taken; update **both** `.env` and the compose mapping if you do.
- **`docker-compose.yml`**: `host.docker.internal` is mainly for Docker Desktop; it does not hurt on Linux Sail setups.

**Browser on another computer via SSH:** Forward HTTP and Vite together, for example `-L 8080:127.0.0.1:8080 -L 5173:127.0.0.1:5173`, keep **`APP_URL`** consistent with how you open the site (often `http://localhost:8080` through the tunnel), and set **`VITE_HMR_HOST=localhost`** if HMR does not connect.

If assets or HMR misbehave, compare **`APP_URL`**, **`VITE_PORT`**, published ports, and **`VITE_HMR_HOST`** before changing application code.

## Prerequisites

### macOS

- Docker Desktop
- Composer 2.x
- Git

Optional:

- Node.js + npm (only if you will rebuild frontend assets)

Install Composer on macOS (Homebrew):

```bash
brew install composer
```

### Linux

- Docker Engine + Docker Compose plugin
- Composer 2.x
- Git

Optional:

- Node.js + npm (only if you will rebuild frontend assets)

Install Docker and Composer (Ubuntu example):

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin composer git
sudo usermod -aG docker $USER
```

Log out and log in again after adding your user to the `docker` group.

## First-Time Setup

From the project root:

```bash
cp .env.example .env
composer install
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan qubix:install --skip-env-check --skip-github-star
```

If port `3306` is busy on your machine, set a different host port in `.env`:

```env
FORWARD_DB_PORT=3307
```

Then restart Sail:

```bash
./vendor/bin/sail down
./vendor/bin/sail up -d
```

### Docker image build (Launchpad / PPA blocked)

The default Laravel Sail runtime pulls PHP packages from **ondrej’s PPA on Launchpad**. Some networks (corporate firewalls, certain cloud VMs) cannot reach `ppa.launchpadcontent.net`, so `docker compose build` fails.

This repo points `laravel.test` at **`docker/8.3/Dockerfile`**, which installs **PHP 8.3 from Ubuntu 24.04’s own repositories** (no Launchpad). If you prefer the upstream Sail image again, change `docker-compose.yml` so `build.context` is `./vendor/laravel/sail/runtimes/8.3` and `dockerfile` is `Dockerfile`.

## Daily Commands

```bash
Start: ./vendor/bin/sail up -d
Stop: ./vendor/bin/sail down
Check status: ./vendor/bin/sail ps
App shell: ./vendor/bin/sail shell
```

The app container runs **Shop Vite** automatically (storefront hot reload) when `SAIL_VITE_SHOP=true` in `.env` (default). Rebuild the image after changing `docker/8.3/*` (e.g. `docker compose build laravel.test`). Set `SAIL_VITE_SHOP=false` to disable the dev server (e.g. automated tests, or to run `npm run dev:shop` yourself in `sail shell`). Admin and installer Vite are not auto-started; use `sail npm run dev:admin` or `sail npm run dev:installer` when needed, on a **different** `VITE_PORT` than the shop if both run at once.

## Access URLs

Use the host port from **`APP_PORT`** in `.env` (Sail maps `APP_PORT` → container `80`). Examples below assume `APP_PORT=8080` and **`VITE_PORT=5173`** for Shop hot reload.

- Storefront: `http://localhost:8080`
- Vite (HMR, Shop): `http://localhost:5173` (same port inside the container; required for hot reload in dev)
- Admin login: `http://localhost:8080/admin/login`
- Mailpit: `http://localhost:8025`
- Kibana: `http://localhost:5601`

## Default Admin Credentials

- Email: `admin@example.com`
- Password: `admin123`

Change this password immediately for non-local environments.

## Useful Maintenance Commands

```bash
# Follow container logs
./vendor/bin/sail logs -f

# Run artisan commands
./vendor/bin/sail artisan <command>

# Stop and remove containers + volumes (this wipes DB data)
./vendor/bin/sail down -v
```

If the storefront shows **504** on `localhost:5173` / **Outdated Optimize Dep** in the console, the Vite pre-bundle cache under `node_modules/.vite` could not be written. Common cause: `packages/DigitalLabs/Shop/node_modules` owned by **root** (e.g. `npm install` run with `sudo`). Fix ownership to match your host user (often UID/GID `1000`), then restart Sail:

```bash
sudo chown -R "$(id -u):$(id -g)" packages/DigitalLabs/Shop/node_modules node_modules
./vendor/bin/sail restart
```
