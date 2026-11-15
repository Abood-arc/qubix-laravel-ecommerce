<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    /**
     * Whether the test database has been confirmed to be installed (checked once per process).
     */
    private static bool $testDatabaseInstalled = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->abortUnlessUsingTestDatabase();
    }

    /**
     * Hard guard: the suite must only ever run against the isolated `testing*` database
     * selected in phpunit.xml. Anything else (cached config, a stray DB_DATABASE, a
     * missing phpunit.xml) would write test data into a real database, so stop the
     * whole run instead of failing test by test. Exiting closes the connection, which
     * rolls back the transaction DatabaseTransactions just opened.
     */
    private function abortUnlessUsingTestDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if (! str_starts_with($database, 'testing')) {
            fwrite(STDERR, PHP_EOL."ABORTING TEST RUN: connected to database '{$database}', expected 'testing'.".PHP_EOL
                .'Tests must never run against a real database. Check phpunit.xml (DB_DATABASE) and run `php artisan config:clear`.'.PHP_EOL);

            exit(1);
        }

        if (! self::$testDatabaseInstalled) {
            if (! Schema::hasTable('channels') || ! DB::table('channels')->exists()) {
                fwrite(STDERR, PHP_EOL."ABORTING TEST RUN: test database '{$database}' is not installed.".PHP_EOL
                    .'Build it with: docker exec -u sail <app-container> scripts/rebuild-test-db.sh'.PHP_EOL);

                exit(1);
            }

            self::$testDatabaseInstalled = true;
        }
    }
}
