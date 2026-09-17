<?php

namespace DigitalLabs\Installer\Console\Commands;

use DigitalLabs\Installer\Database\Seeders\StarterStoreSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs Task 4.2's StarterStoreSeeder with an explicit locale/currency,
 * something a plain `db:seed --class=StarterStoreSeeder` cannot do:
 * `Illuminate\Database\Console\Seeds\SeedCommand::handle()` calls
 * `$this->getSeeder()->__invoke()` with no arguments, and `Seeder::__invoke()`
 * resolves `run($parameters = [])` via the container with that empty array —
 * there is no CLI flag on `db:seed` that reaches a seeder's own `run()`
 * parameters. StarterStoreSeeder's `run()` parameters aren't type-hinted
 * (so Container::call() can't resolve them from the container either), just
 * merged with defaults ('en'/'USD'), so a bare `db:seed --class=` invocation
 * always seeds the English/USD defaults regardless of what a client actually
 * asked for.
 *
 * This is a tiny dedicated wrapper command rather than either (a) editing
 * Task 4.2's already-committed StarterStoreSeeder to read locale/currency
 * from the environment as a fallback, which would blur that task's own
 * already-reviewed scope, or (b) trying to pass a parameters array through
 * `db:seed`'s CLI surface, which doesn't support it at all. A dedicated
 * command is also independently testable and self-documenting via --help,
 * unlike an environment-variable fallback baked into the seeder itself.
 */
class SeedStarterStore extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qubix:seed-starter-store
        { --locale=en : Default and sole allowed locale for the new store. }
        { --currency=USD : Default and sole allowed currency for the new store. }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a brand-new client stack via StarterStoreSeeder, threading --locale/--currency through (db:seed --class= cannot pass parameters to a seeder\'s run()).';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $locale = (string) $this->option('locale');
        $currency = (string) $this->option('currency');

        $parameters = [
            'default_locale' => $locale,
            'allowed_locales' => [$locale],
            'default_currency' => $currency,
            'allowed_currencies' => [$currency],
        ];

        Model::unguarded(function () use ($parameters) {
            app(StarterStoreSeeder::class)
                ->setContainer($this->laravel)
                ->setCommand($this)
                ->__invoke($parameters);
        });

        return self::SUCCESS;
    }
}
