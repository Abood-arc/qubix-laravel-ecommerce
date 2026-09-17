<?php

namespace DigitalLabs\Installer\Database\Seeders\User;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        // This row is the CLI install's starting point regardless of path —
        // Installer::getSeederConfiguration() never sets `skip_admin_creation`,
        // so this always runs. What happens to it next depends on how the
        // install proceeds:
        //   - Interactive (`askForAdminDetails()` runs): the operator is
        //     prompted for name/email/password and that step overwrites this
        //     row outright. That prompt's own default is now a fresh random
        //     string too (see Installer::askForAdminDetails()), not the old
        //     literal `admin123`, so even a bare Enter through the prompt no
        //     longer produces a guessable password.
        //   - Non-interactive (`--skip-admin-creation`, e.g. the two
        //     Playwright CI workflows, or the documented future
        //     fleet-automation path): nothing overwrites this row, so
        //     whatever password lands here is the live one.
        //
        // QUBIX_INSTALL_ADMIN_PASSWORD lets a non-interactive caller supply a
        // known password instead (CI sets it to `admin123` to match its
        // Playwright fixtures; Phase 4's fleet automation can inject its own
        // generated one the same way, rather than scraping the file below).
        // Falls back to a random password, recorded to a local file, when
        // unset.
        //
        // Deliberately env() and not config(), and this only works if the
        // caller sets it as a real process-level environment variable (as
        // both CI workflows do via the step's own `env:` block) — never via a
        // `.env` file line. If `artisan config:cache` has already run by the
        // time this seeder executes, Laravel skips loading `.env` entirely,
        // so an override that only exists there would silently vanish and
        // this would fall back to the random branch with no warning. Phase
        // 4's fleet automation must set this as a true environment variable
        // and must run before any `config:cache` step, not after.
        $overridePassword = env('QUBIX_INSTALL_ADMIN_PASSWORD');

        $usedOverride = filled($overridePassword);

        $password = $usedOverride ? $overridePassword : Str::random(20);

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

        // Only the random-password branch needs recording anywhere — an
        // env-supplied password is already known to whoever supplied it.
        if ($usedOverride) {
            return;
        }

        // Advisory only — nothing programmatic reads this file. It lets
        // whoever ran a non-interactive install recover the seeded password.
        // Written under storage/app, which storage/app/.gitignore excludes
        // wholesale (`*`), so it never reaches git. If the interactive flow
        // overwrote this admin row afterward, this file is simply stale.
        $written = Storage::disk('local')->put(
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

        // config/filesystems.php sets `throw => false` on the `local` disk,
        // so a failed write returns false here instead of throwing — and
        // this is the ONLY record of the password in the no-override branch.
        // Silently continuing would report a successful install with an
        // admin account nobody can log into. Surface it as loudly as
        // possible and stop the install rather than let that pass unnoticed.
        if (! $written) {
            $message = 'Qubix install: failed to write the auto-generated admin '.
                'password to storage/app/qubix-install-admin-password.txt — the '.
                'seeded admin account (id 1, email '.$email.') has no password '.
                'recorded anywhere. Check storage/app permissions, then reset '.
                'the password manually (e.g. via `php artisan tinker`) before '.
                'relying on this install.';

            // This seeder is invoked directly via
            // `app(QubixDatabaseSeeder::class)->run(...)` in the real
            // `qubix:install` path, not through Artisan's `db:seed` command
            // wrapper, so `$this->command` is never set and must not be
            // assumed to be usable.
            Log::error($message);

            if (isset($this->command)) {
                $this->command->error($message);
            }

            throw new \RuntimeException($message);
        }
    }
}
