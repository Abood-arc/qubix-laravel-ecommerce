<?php

namespace DigitalLabs\Installer\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Provisions a brand-new client's entire stack end-to-end, non-interactively:
 * generates their per-client compose file (Task 4.1's generator), writes
 * their `.env`, brings the stack up, migrates, seeds a starter store (Task
 * 4.2's seeder), creates their admin user, and optionally sets their brand
 * colour (Phase 3). Caddy site-block registration is Task 4.4 — deliberately
 * not implemented here (see the comment at that step below).
 *
 * This command assumes it is being invoked with the current working
 * directory already being the client's dedicated checkout (e.g.
 * /opt/qubix-acme) — exactly the same precondition `qubix:install` makes
 * about a pre-written `.env` (`--skip-env-check`). Getting a fresh checkout
 * onto disk is someone else's job (a wrapper script, or eventually n8n).
 *
 * Production invocation model — Docker-outside-of-Docker (DooD): this
 * process IS the container with the Docker CLI and the host's Docker socket
 * bind-mounted in (see docker/provisioner/Dockerfile and
 * scripts/provision-client.sh), because `docker compose`/`docker` in this
 * codebase always run on the host shell, never from inside a container, and
 * there's a chicken-and-egg problem otherwise: you can't `docker compose
 * exec app-acme ...` before docker-compose.acme.yml (which defines
 * app-acme) exists yet. So every "run inside the app container" step below
 * shells out to `docker compose ... exec -T app-{slug} ...` rather than
 * calling the equivalent artisan command in-process.
 *
 * Exit codes (extends Task 4.1's generator contract — do not invent a new
 * scheme):
 *   0      success
 *   1      invalid input (missing/malformed required option)
 *   2      slug collision — propagated verbatim from the Task 4.1 generator
 *   3      disk full (this command's own preflight)
 *   10     everything else — Docker/environment unreachable, or any other
 *          failure. Default-to-transient policy, deliberate: Task 4.5's
 *          bounded retry logic keys off this distinction, and a
 *          misclassified transient failure only costs one wasted retry,
 *          while a misclassified terminal failure treated as transient
 *          would burn all retry attempts on something that could never
 *          succeed. Bias toward transient when genuinely unsure.
 *
 * "SSH dropped" (mentioned in the plan's own text) is not representable by
 * this command's own exit code — SSH is the transport whoever invokes this
 * over (n8n, Task 4.5) uses to reach the VPS at all. If it drops mid-command
 * this process never delivers an exit code to the caller in the first
 * place. That's Task 4.5's own connection-timeout concern.
 */
class Provision extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'qubix:provision
        { --slug= : Lowercase client slug, e.g. acme — used for the compose project/service names, the DB name, and the digital-labs.ai subdomain. Lowercase start, lowercase alphanumeric/hyphens only, 2-21 chars total. }
        { --client-name= : Internal reference name for this client (not customer-facing). }
        { --business-name= : Customer-facing store name — becomes .env APP_NAME. }
        { --admin-email= : Email address for the initial admin user. }
        { --admin-name= : Name for the initial admin user. Defaults to --client-name. }
        { --admin-password= : Plaintext password for the initial admin user. If omitted, a random one is generated via Str::random(20) and printed once. }
        { --brand-color= : Optional hex brand colour (e.g. #1a2b3c). Left unset — the neutral BrandPalette default — if omitted. }
        { --locale=en : Default and sole locale for the new store. }
        { --currency=USD : Default and sole currency for the new store. }
    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision a brand-new client stack end-to-end (compose file, .env, containers, migrations, starter seed data, admin user, optional brand colour). Non-interactive — designed to run inside the docker/provisioner image via scripts/provision-client.sh.';

    /**
     * Task 4.1's own slug validation regex — reused verbatim, never
     * redefined, so the two tools can never disagree about what a valid
     * slug looks like.
     */
    private const SLUG_REGEX = '/^[a-z][a-z0-9-]{1,20}$/';

    private const EXIT_SUCCESS = 0;

    private const EXIT_INVALID_INPUT = 1;

    private const EXIT_SLUG_COLLISION = 2;

    private const EXIT_DISK_FULL = 3;

    /**
     * Default-to-transient: anything that isn't one of the specific
     * terminal conditions above lands here. See the class docblock.
     */
    private const EXIT_TRANSIENT = 10;

    /**
     * 500MB floor for the disk-space preflight. This is a coarse
     * "stop before we're clearly in trouble" guard, not a sized-to-fit
     * capacity budget: the shared base image alone is roughly a gigabyte,
     * and a fresh per-client build layer plus the bind-mounted checkout
     * consume real space before a single byte of the client's own data
     * exists. 500MB comfortably covers what an empty client stack needs to
     * come up while still catching a genuinely low-disk host up front,
     * deterministically, rather than parsing a failed write's error string
     * after the fact (which is fragile — see Task 4.1's own generator for
     * the same "check first" philosophy applied to the collision check).
     */
    private const MIN_FREE_DISK_BYTES = 500 * 1024 * 1024;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $slug = (string) $this->option('slug');
        $clientName = (string) $this->option('client-name');
        $businessName = (string) $this->option('business-name');
        $adminEmail = (string) $this->option('admin-email');
        $adminName = $this->option('admin-name') ?: $clientName;
        $locale = (string) $this->option('locale');
        $currency = (string) $this->option('currency');
        $brandColorRaw = $this->option('brand-color');

        // --- Step 1: validate required input -------------------------------
        foreach ([
            'slug' => $slug,
            'client-name' => $clientName,
            'business-name' => $businessName,
            'admin-email' => $adminEmail,
        ] as $optionName => $value) {
            if (blank($value)) {
                $this->error("--{$optionName} is required.");

                return self::EXIT_INVALID_INPUT;
            }
        }

        if (! preg_match(self::SLUG_REGEX, $slug)) {
            $this->error("Invalid --slug '{$slug}' — must match ^[a-z][a-z0-9-]{1,20}\$ (lowercase start, lowercase alphanumeric/hyphens, 2-21 chars total).");

            return self::EXIT_INVALID_INPUT;
        }

        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid --admin-email '{$adminEmail}'.");

            return self::EXIT_INVALID_INPUT;
        }

        $brandColor = null;

        if (filled($brandColorRaw)) {
            if (! preg_match('/^#?[0-9a-fA-F]{6}$/', $brandColorRaw)) {
                $this->error("Invalid --brand-color '{$brandColorRaw}' — expected a 6-digit hex colour, e.g. #1a2b3c.");

                return self::EXIT_INVALID_INPUT;
            }

            $brandColor = Str::start($brandColorRaw, '#');
        }

        $passwordWasGenerated = blank($this->option('admin-password'));
        $adminPassword = $passwordWasGenerated
            ? Str::random(20)
            : (string) $this->option('admin-password');

        // --- Step 2: disk-space preflight, before anything writes a byte ---
        $freeBytes = disk_free_space(base_path());

        if ($freeBytes === false) {
            $this->warn('Could not determine free disk space (disk_free_space() returned false) — proceeding, since this is informational-only and not itself a proof of a full disk.');
        } elseif ($freeBytes < self::MIN_FREE_DISK_BYTES) {
            $this->error(sprintf(
                'Disk-space preflight failed: %s free at %s, below the %s floor.',
                $this->formatBytes($freeBytes),
                base_path(),
                $this->formatBytes(self::MIN_FREE_DISK_BYTES)
            ));

            return self::EXIT_DISK_FULL;
        }

        $composeFile = "docker-compose.{$slug}.yml";
        $project = "qubix-{$slug}";
        $appService = "app-{$slug}";

        // --- Step 3: generate the compose file ------------------------------
        $this->components->info("Generating {$composeFile} via scripts/generate-client-compose.sh");

        $generate = Process::path(base_path())
            ->timeout(60)
            ->run(['bash', base_path('scripts/generate-client-compose.sh'), '--slug', $slug]);

        if (! $generate->successful()) {
            $this->error('scripts/generate-client-compose.sh failed:');
            $this->line($generate->errorOutput());

            // Propagated verbatim, per Task 4.1's own contract — do not
            // reclassify (1 = invalid input, 2 = collision, 10 = docker
            // unreachable; Task 4.1 already got this right).
            return $generate->exitCode() ?? self::EXIT_TRANSIENT;
        }

        file_put_contents(base_path($composeFile), $generate->output());

        // --- Step 4: write .env ---------------------------------------------
        $this->components->info('Writing .env');

        $envSource = file_exists(base_path('.env.production.example'))
            ? base_path('.env.production.example')
            : base_path('.env.example');

        $env = file_get_contents($envSource);

        if ($env === false) {
            $this->error("Could not read env template at {$envSource}.");

            return self::EXIT_TRANSIENT;
        }

        $dbPassword = Str::random(32);
        $redisPassword = Str::random(32);
        $appKey = 'base64:'.base64_encode(random_bytes(32));

        $env = $this->setEnvValue($env, 'APP_NAME', $businessName);
        $env = $this->setEnvValue($env, 'APP_URL', "https://{$slug}.digital-labs.ai");
        $env = $this->setEnvValue($env, 'APP_KEY', $appKey);
        $env = $this->setEnvValue($env, 'APP_LOCALE', $locale);
        $env = $this->setEnvValue($env, 'APP_CURRENCY', $currency);
        $env = $this->setEnvValue($env, 'DB_DATABASE', 'qubix');
        $env = $this->setEnvValue($env, 'DB_USERNAME', 'qubix');
        $env = $this->setEnvValue($env, 'DB_PASSWORD', $dbPassword);
        $env = $this->setEnvValue($env, 'REDIS_PASSWORD', $redisPassword);

        if (file_put_contents(base_path('.env'), $env) === false) {
            $this->error('Failed to write .env.');

            return self::EXIT_TRANSIENT;
        }

        // --- Step 5: bring up the stack --------------------------------------
        $this->components->info("Bringing up the stack ({$project})");

        $up = Process::path(base_path())
            ->timeout(900)
            ->run(['docker', 'compose', '-f', $composeFile, '-p', $project, 'up', '-d', '--build']);

        if (! $up->successful()) {
            $this->error("'docker compose up' failed for '{$slug}':");
            $this->line($up->errorOutput());

            return self::EXIT_TRANSIENT;
        }

        // --- Step 6: wait for mysql to report healthy ------------------------
        $this->components->info('Waiting for mysql to report healthy');

        if (! $this->waitForMysqlHealthy($composeFile, $project)) {
            $this->error("mysql never reported healthy for '{$slug}' within the bounded retry window.");

            return self::EXIT_TRANSIENT;
        }

        // --- Step 7: migrate ---------------------------------------------------
        $this->components->info('Running migrations');

        $migrate = $this->execInApp($composeFile, $project, $appService, ['php', 'artisan', 'migrate', '--force']);

        if (! $migrate->successful()) {
            $this->error('Migration failed:');
            $this->line($migrate->errorOutput());

            return self::EXIT_TRANSIENT;
        }

        // --- Step 8: seed the starter store -------------------------------------
        $this->components->info('Seeding the starter store');

        // db:seed --class= calls the seeder's run() with no arguments (see
        // Illuminate\Database\Console\Seeds\SeedCommand::handle(), which
        // invokes __invoke() with nothing), so locale/currency can't be
        // threaded through a plain `db:seed --class=StarterStoreSeeder`
        // call — Laravel seeders don't take CLI params directly. Rather
        // than editing Task 4.2's already-committed StarterStoreSeeder (to
        // read from the environment as a fallback) this uses a tiny
        // dedicated command, qubix:seed-starter-store, that builds the
        // parameters array from its own --locale/--currency options and
        // invokes the seeder directly. See that command's own docblock.
        $seed = $this->execInApp($composeFile, $project, $appService, [
            'php', 'artisan', 'qubix:seed-starter-store',
            '--locale', $locale,
            '--currency', $currency,
        ]);

        if (! $seed->successful()) {
            $this->error('Starter store seeding failed:');
            $this->line($seed->errorOutput());

            return self::EXIT_TRANSIENT;
        }

        // --- Step 9 & 10: admin user + brand colour --------------------------
        // Caddy site-block registration: Task 4.4, not yet implemented.

        $this->components->info('Creating the admin user'.($brandColor ? ' and setting the brand colour' : ''));

        $adminCommand = [
            'php', 'artisan', 'qubix:provision-admin',
            '--name', $adminName,
            '--email', $adminEmail,
            '--password', $adminPassword,
        ];

        if ($brandColor) {
            $adminCommand[] = '--brand-color';
            $adminCommand[] = $brandColor;
        }

        $admin = $this->execInApp($composeFile, $project, $appService, $adminCommand);

        if (! $admin->successful()) {
            $this->error('Admin/brand-colour provisioning failed:');
            $this->line($admin->errorOutput());

            return self::EXIT_TRANSIENT;
        }

        // --- Step 12: final summary --------------------------------------------
        $this->newLine();
        $this->components->info("Provisioned '{$slug}' successfully.");
        $this->line("  URL:          https://{$slug}.digital-labs.ai (DNS/Caddy not wired yet — Task 4.4)");
        $this->line("  Compose:      {$composeFile} (project {$project})");
        $this->line("  Admin email:  {$adminEmail}");

        if ($passwordWasGenerated) {
            $this->warn("  Admin password (generated, shown once): {$adminPassword}");
        }

        return self::EXIT_SUCCESS;
    }

    /**
     * Run a command inside the client's app container via `docker compose
     * exec`. Every argument is a separate array element (no shell string
     * is ever built), so nothing here needs quoting/escaping regardless of
     * what's in $adminName/$businessName/etc.
     */
    private function execInApp(string $composeFile, string $project, string $appService, array $command): \Illuminate\Process\ProcessResult
    {
        return Process::path(base_path())
            ->timeout(300)
            ->run([
                'docker', 'compose', '-f', $composeFile, '-p', $project,
                'exec', '-T', $appService,
                ...$command,
            ]);
    }

    /**
     * Poll `mysql`'s health status with bounded retries and exponential
     * backoff (2s, 4s, 8s, capped at 15s), rather than sleeping a fixed
     * guess-and-hope duration. 20 attempts against that schedule bounds the
     * total wait at a little over 4.5 minutes, generous enough for a cold
     * image pull/build plus MySQL's own first-boot initialisation, without
     * hanging forever if the container never comes up healthy.
     */
    private function waitForMysqlHealthy(string $composeFile, string $project): bool
    {
        $maxAttempts = 20;
        $delay = 2;
        $maxDelay = 15;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $idResult = Process::path(base_path())
                ->timeout(30)
                ->run(['docker', 'compose', '-f', $composeFile, '-p', $project, 'ps', '-q', 'mysql']);

            $containerId = trim($idResult->output());

            if ($idResult->successful() && $containerId !== '') {
                $health = Process::timeout(30)->run([
                    'docker', 'inspect', '--format', '{{.State.Health.Status}}', $containerId,
                ]);

                if ($health->successful() && trim($health->output()) === 'healthy') {
                    return true;
                }
            }

            if ($attempt < $maxAttempts) {
                sleep($delay);
                $delay = min($delay * 2, $maxDelay);
            }
        }

        return false;
    }

    /**
     * Set (or append, if absent) a KEY=value line in an in-memory .env
     * file's contents. Values containing whitespace are double-quoted —
     * same convention as Installer::updateEnvVariable().
     */
    private function setEnvValue(string $content, string $key, string $value): string
    {
        $line = $key.'='.(preg_match('/\s/', $value) ? '"'.$value.'"' : $value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content)) {
            return preg_replace($pattern, $line, $content, 1);
        }

        return rtrim($content).PHP_EOL.$line.PHP_EOL;
    }

    private function formatBytes(float $bytes): string
    {
        return round($bytes / 1024 / 1024, 1).' MiB';
    }
}
