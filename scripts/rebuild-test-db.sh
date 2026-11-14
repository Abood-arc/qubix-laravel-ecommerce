#!/usr/bin/env bash
# Rebuild the isolated Pest database (`testing`) from scratch to the state CI tests against.
#
# Run inside the app container, as sail (never as root):
#   docker exec -u sail qubix-laravel-ecommerce-laravel.test-1 scripts/rebuild-test-db.sh
#
# Mirrors .github/workflows/pest_tests.yml, which runs `qubix:install` against a fresh MySQL
# using .env.example's APP_LOCALE=en / APP_CURRENCY=USD: one `default` channel, en, USD.
# It does NOT call `qubix:install` itself: that command reads DB_* from the .env FILE
# (ignoring the env override below), then runs `db:wipe` and `key:generate` — locally that
# would wipe the everyday `qubix` database and rewrite APP_KEY.
#
# The `testing` database and the `testing%` grant for the app's DB user are created by
# Sail's MySQL init script (vendor/laravel/sail/database/mysql/create-testing-database.sh).
set -euo pipefail
cd "$(dirname "$0")/.."

export APP_ENV=testing DB_DATABASE=testing

php artisan config:clear --quiet

# Refuse to touch anything that is not the test database (e.g. if config were cached).
db="$(php artisan tinker --execute='echo "DB=".DB::connection()->getDatabaseName();' | sed -n 's/^DB=//p')"
if [[ "$db" != testing* ]]; then
  echo "refusing to rebuild: connected to '$db', expected 'testing'" >&2
  exit 1
fi

php artisan migrate:fresh --force

php artisan tinker --execute='app(DigitalLabs\Installer\Database\Seeders\DatabaseSeeder::class)->run([
    "default_locale" => "en", "allowed_locales" => ["en"],
    "default_currency" => "USD", "allowed_currencies" => ["USD"],
]); echo "seeded".PHP_EOL;'

echo "test database '$db' rebuilt"
