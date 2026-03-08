# Bagisto Local Development Guide

This repository is configured to run locally with Laravel Sail (Docker).

## What This README Covers

- Clean local setup for macOS and Linux
- Run and stop commands
- Access URLs and default admin login

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
./vendor/bin/sail artisan bagisto:install --skip-env-check --skip-github-star
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

## Daily Commands

```bash
Start: ./vendor/bin/sail up -d
Stop: ./vendor/bin/sail down
Check status: ./vendor/bin/sail ps
App shell: ./vendor/bin/sail shell
```

## Access URLs

- Storefront: `http://localhost:8000`
- Admin login: `http://localhost:8000/admin/login`
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
