<?php

namespace DigitalLabs\Installer\Database\Seeders\User;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminsTableSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @param  array  $parameters
     * @return void
     */
    public function run($parameters = [])
    {
        DB::table('admins')->delete();

        $defaultLocale = $parameters['default_locale'] ?? config('app.locale');

        if (isset($parameters['skip_admin_creation']) && $parameters['skip_admin_creation']) {
            return;
        }

        $email = 'admin@example.com';

        // Random, not a fixed default: this row is only ever left in place when
        // the CLI install runs with --skip-admin-creation (no interactive
        // `askForAdminDetails()` step to overwrite it afterward) — see
        // Installer::getSeederConfiguration(), which never sets
        // `skip_admin_creation` itself. A guessable fixed password here would be
        // a live credential on any install that takes that path, including the
        // documented future fleet-automation flow. In the normal interactive
        // flow this password is overwritten seconds later, so generating it
        // costs nothing.
        $password = Str::random(20);

        DB::table('admins')->insert([
            'id' => 1,
            'name' => trans('installer::app.seeders.user.users.name', [], $defaultLocale),
            'email' => $email,
            'password' => bcrypt($password),
            'api_token' => Str::random(80),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'status' => 1,
            'role_id' => 1,
        ]);

        // Advisory only — nothing programmatic reads this file. It lets whoever
        // ran a non-interactive install recover the seeded password if
        // `askForAdminDetails()` didn't run. Written under storage/app, which
        // storage/app/.gitignore excludes wholesale (`*`), so it never reaches
        // git. If the interactive flow overwrote this admin row afterward, this
        // file is simply stale.
        Storage::disk('local')->put(
            'qubix-install-admin-password.txt',
            'Qubix install — auto-generated admin credentials'.PHP_EOL.
            'Generated at: '.date('Y-m-d H:i:s').PHP_EOL.
            "Email: {$email}".PHP_EOL.
            "Password: {$password}".PHP_EOL.
            PHP_EOL.
            'This password is only live if the install ran non-interactively '.
            '(e.g. --skip-admin-creation) with no admin details entered '.
            'afterward. Log in and change it from the admin panel immediately. '.
            'If admin details WERE entered interactively, that step already '.
            'overwrote this row and this file is stale/irrelevant.'.PHP_EOL
        );
    }
}
