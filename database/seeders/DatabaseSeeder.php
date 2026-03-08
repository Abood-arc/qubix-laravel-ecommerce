<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DigitalLabs\Installer\Database\Seeders\DatabaseSeeder as QubixDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(QubixDatabaseSeeder::class);
    }
}
