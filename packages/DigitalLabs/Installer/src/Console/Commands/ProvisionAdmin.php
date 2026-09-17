<?php

namespace DigitalLabs\Installer\Console\Commands;

use DigitalLabs\Core\Models\CoreConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sets a brand-new client's admin user and (optionally) their brand colour.
 * Invoked by `qubix:provision` via `docker compose exec` — one dedicated
 * command, not a `tinker --execute="..."` one-liner, because a real
 * command's options are passed as separate argv entries through the
 * `Process`/`docker compose exec` layers with no shell string ever built,
 * whereas quoting arbitrary client-supplied name/email/password values
 * safely through tinker's own PHP-code-as-a-string argument, itself passed
 * through a shell command, is exactly the kind of double/triple-escaping
 * that's easy to get wrong. This command is also independently testable
 * (`artisan qubix:provision-admin ...`), which a tinker one-liner is not.
 *
 * The admin upsert mirrors `Installer::askForAdminDetails()`'s own
 * `DB::table('admins')->updateOrInsert(['id' => 1], [...])` pattern (id 1
 * is always the seeded admin row's id — see AdminsTableSeeder, which
 * StarterStoreSeeder's UserSeeder chain already runs and which itself
 * inserts a throwaway random-password id-1 row; this command's job is to
 * overwrite that row with the real, CLI-supplied credentials, exactly as
 * the interactive installer's own admin-details step overwrites it).
 *
 * The brand-colour write uses `CoreConfig::updateOrCreate()` with the
 * config code `general.design.storefront_branding.storefront_branding`
 * (group-key + field-name, not the bare group key) scoped to the `default`
 * channel — confirmed against the actual codebase this session, not the
 * stale `setConfigurationByKey()` claim from an older doc note (no such
 * method exists on this model/repository).
 */
class ProvisionAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qubix:provision-admin
        { --name= : Admin display name. }
        { --email= : Admin email address (used as the login). }
        { --password= : Plaintext password — bcrypt-hashed before storage, never itself persisted. }
        { --brand-color= : Optional hex brand colour. When given, sets general.design.storefront_branding.storefront_branding on the default channel. }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set (upsert) the id=1 admin user and, optionally, the default channel brand colour. Internal plumbing for qubix:provision — not meant to be run standalone against a live store.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = (string) $this->option('name');
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');
        $brandColor = $this->option('brand-color');

        if (blank($name) || blank($email) || blank($password)) {
            $this->error('qubix:provision-admin requires --name, --email and --password.');

            return self::FAILURE;
        }

        DB::table('admins')->updateOrInsert(
            ['id' => 1],
            [
                'name' => $name,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]),
                'role_id' => 1,
                'status' => 1,
            ]
        );

        $this->components->info("Admin user set: {$email}");

        if (filled($brandColor)) {
            CoreConfig::updateOrCreate(
                [
                    'code' => 'general.design.storefront_branding.storefront_branding',
                    'channel_code' => 'default',
                ],
                ['value' => $brandColor]
            );

            $this->components->info("Brand colour set: {$brandColor}");
        }

        return self::SUCCESS;
    }
}
