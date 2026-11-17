<?php

namespace DigitalLabs\Shiprocket\Console\Commands;

use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Sales\Models\OrderProxy;
use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Services\AddressNormalizer;
use DigitalLabs\Shiprocket\Services\FakeShiprocketClient;
use DigitalLabs\Shiprocket\Services\ParcelBuilder;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Checks the Shiprocket setup against the real account: the API user, the pickup location
 * and serviceability. Read-only, except --create-test-order, which creates one order under
 * a test number ({increment_id}-{YmdHis}, digits only as Shiprocket's docs advise), reads it back and cancels it. It never assigns a courier (AWB), requests
 * a pickup or makes a manifest or label, so nothing is booked or charged to the wallet.
 *
 * Works while Shiprocket is switched off in admin: only the API user in .env is needed.
 */
class CheckCommand extends Command
{
    protected $signature = 'shiprocket:check
        {--pin=* : Delivery PIN code to check (repeatable; default 110001, 400001, 560001, 700001)}
        {--order= : A store order number, to build its Shiprocket payload}
        {--payload : With --order: print the payload as JSON and stop}
        {--create-test-order : With --order: create it in Shiprocket under a test number, read it back and cancel it}
        {--force : With --create-test-order: do not ask first}';

    protected $description = 'Check the Shiprocket API user, pickup location and serviceability (and, on request, a test order)';

    protected const DEFAULT_PINS = ['110001', '400001', '560001', '700001'];

    protected const WEIGHT_KG = 0.5;

    /**
     * What "Get Specific Order Details" may hold about the amount Shiprocket will collect,
     * shown as returned ("(not returned)" when absent). Dots reach into nested objects.
     */
    protected const RECORDED_FIELDS = [
        'status', 'payment_method', 'cod', 'total', 'sub_total', 'net_total', 'cod_amount',
        'discount', 'tax', 'other_charges', 'others.shipping_charges',
    ];

    /**
     * Whether every step passed so far; the exit code.
     */
    protected bool $passed = true;

    public function handle(ShiprocketApi $api, ParcelBuilder $parcels): int
    {
        if ($problem = $this->optionProblem()) {
            $this->error($problem);

            return self::FAILURE;
        }

        $order = null;

        if (filled($this->option('order'))) {
            $order = OrderProxy::modelClass()::query()->where('increment_id', $this->option('order'))->first();

            if (! $order) {
                $this->error('No store order '.$this->option('order').'.');

                return self::FAILURE;
            }
        }

        // The payload needs no API call, so it is the same whatever answers for Shiprocket.
        if ($this->option('payload')) {
            return $this->printPayload($parcels, $order);
        }

        if ($api instanceof FakeShiprocketClient) {
            $this->error('FAKE MODE: SHIPROCKET_FAKE=true, so a stand-in answers instead of Shiprocket. Nothing was checked. Set SHIPROCKET_FAKE=false in .env to check the real account.');

            return self::FAILURE;
        }

        if (Settings::fake()) {
            $this->warn('SHIPROCKET_FAKE=true is ignored in production: this checks the real Shiprocket account.');
        }

        if (! $this->checkLogin($api)) {
            return self::FAILURE;
        }

        $location = $this->checkPickupLocation($api);

        $this->checkServiceability($api, $location);

        if ($order) {
            $this->checkTestOrder($api, $parcels, $order, $location);
        }

        return $this->passed ? self::SUCCESS : self::FAILURE;
    }

    protected function optionProblem(): ?string
    {
        $order = filled($this->option('order'));

        return match (true) {
            ! $order && ($this->option('payload') || $this->option('create-test-order')) => '--payload and --create-test-order need --order=<store order number>.',
            $order && ! $this->option('payload') && ! $this->option('create-test-order') => '--order needs --payload (print the payload) or --create-test-order (create, read back and cancel a test order in Shiprocket).',
            default => null,
        };
    }

    protected function printPayload(ParcelBuilder $parcels, Order $order): int
    {
        try {
            $payload = $parcels->build($order);
        } catch (ShiprocketException $exception) {
            $this->error('No payload: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }

    protected function checkLogin(ShiprocketApi $api): bool
    {
        $this->heading('1. API user');

        if (blank(Settings::email()) || blank(Settings::password())) {
            $this->problem('SHIPROCKET_EMAIL and SHIPROCKET_PASSWORD must both be set in .env.');

            return false;
        }

        try {
            $api->checkLogin();
        } catch (ShiprocketException $exception) {
            $this->problem('Login failed: '.$exception->getMessage());

            return false;
        }

        $this->info('API user OK ('.Settings::email().')');

        return true;
    }

    /**
     * The account's pickup locations, and whether the one in the settings is among them
     * (Shiprocket matches the name exactly) with the same PIN.
     *
     * @return array{pickup_location: string, pin_code: string}|null the configured location
     */
    protected function checkPickupLocation(ShiprocketApi $api): ?array
    {
        $this->heading('2. Pickup locations');

        try {
            $locations = $api->listPickupLocations();
        } catch (ShiprocketException $exception) {
            $this->problem('Could not list the pickup locations: '.$exception->getMessage());

            return null;
        }

        $this->table(['Pickup location', 'PIN'], array_map(fn ($location) => [
            $location['pickup_location'], $location['pin_code'],
        ], $locations));

        $name = Settings::pickupLocation();

        if ($name === '') {
            $this->problem('No pickup location is set in the Shiprocket settings.');

            return null;
        }

        $location = Arr::first($locations, fn ($location) => $location['pickup_location'] === $name);

        if (! $location) {
            $similar = Arr::first($locations, fn ($location) => strcasecmp(trim($location['pickup_location']), $name) === 0);

            $this->problem('Pickup location "'.$name.'" in the settings is not in Shiprocket'
                .($similar ? ' (Shiprocket has "'.$similar['pickup_location'].'"; the name must match exactly, case included)' : '')
                .'.');

            return null;
        }

        $this->info('Pickup location "'.$name.'" in the settings: found in Shiprocket.');

        $pin = Settings::pickupPin();

        if ($pin === trim($location['pin_code'])) {
            $this->info('Pickup PIN '.$pin.' in the settings matches that location.');
        } else {
            $this->problem('Pickup PIN '.($pin ?: '(not set)').' in the settings differs from that location\'s PIN '.$location['pin_code'].' in Shiprocket.');
        }

        return $location;
    }

    /**
     * Ask Shiprocket itself for every PIN, prepaid and COD (no cache: ServiceabilityChecker
     * is bypassed).
     */
    protected function checkServiceability(ShiprocketApi $api, ?array $location): void
    {
        $from = Settings::pickupPin() ?: (string) ($location['pin_code'] ?? '');

        $this->heading('3. Serviceability from '.($from ?: '?').' ('.self::WEIGHT_KG.' kg)');

        if (! AddressNormalizer::isValidPin($from)) {
            $this->problem('No valid pickup PIN to check from: set it in the Shiprocket settings.');

            return;
        }

        $rows = [];

        foreach ($this->option('pin') ?: self::DEFAULT_PINS as $pin) {
            $pin = trim((string) $pin);

            if (! AddressNormalizer::isValidPin($pin)) {
                $rows[] = [$pin, 'not a 6-digit PIN', ''];

                continue;
            }

            try {
                $prepaid = $api->checkServiceability($from, $pin, self::WEIGHT_KG, false);
                $cod = $api->checkServiceability($from, $pin, self::WEIGHT_KG, true);

                $rows[] = [$pin, $this->yesNo($prepaid['deliverable']), $this->yesNo($cod['cod'])];
            } catch (ShiprocketException $exception) {
                $this->passed = false;

                $rows[] = [$pin, 'error: '.$exception->getMessage(), ''];
            }
        }

        $this->table(['PIN', 'Deliverable', 'COD available'], $rows);
    }

    /**
     * Create the store order in Shiprocket under a test number ({increment_id}-{YmdHis}, digits only as Shiprocket's docs advise), show what Shiprocket
     * recorded next to the store's amounts, and cancel it. No courier, pickup, manifest or
     * label is ever asked for.
     */
    protected function checkTestOrder(ShiprocketApi $api, ParcelBuilder $parcels, Order $order, ?array $location): void
    {
        $this->heading('4. Test order for store order '.$order->increment_id);

        if (! $location) {
            $this->problem('Skipped: Shiprocket would refuse an order without a pickup location it knows (see 2).');

            return;
        }

        try {
            $payload = $parcels->build($order);
        } catch (ShiprocketException $exception) {
            $this->problem('No payload: '.$exception->getMessage());

            return;
        }

        $channelOrderId = $order->increment_id.'-'.now()->format('YmdHis');

        $payload['order_id'] = $channelOrderId;

        $sent = round($payload['sub_total'] + $payload['shipping_charges'], 2);

        $this->table(['Store', 'Amount'], [
            ['Store grand_total', $this->amount($order->grand_total)],
            ['Sent sub_total', $this->amount($payload['sub_total'])],
            ['Sent shipping_charges', $this->amount($payload['shipping_charges'])],
            ['Sent payment_method', $payload['payment_method']],
        ]);

        $question = 'Create test order '.$channelOrderId.' in Shiprocket? It is cancelled straight after; no courier is booked.';

        if (! $this->option('force') && ! $this->confirm($question)) {
            $this->problem('No test order created.');

            return;
        }

        try {
            $ids = $api->createOrder($payload);
        } catch (ShiprocketUnknownOutcomeException $exception) {
            $this->problem('No answer from Shiprocket ('.$exception->getMessage().'). If order '.$channelOrderId.' shows in the Shiprocket panel, cancel it there.');

            return;
        } catch (ShiprocketException $exception) {
            $this->problem('Shiprocket refused the test order: '.$exception->getMessage());

            return;
        }

        $this->info('Created Shiprocket order '.$ids['order_id'].' (shipment '.$ids['shipment_id'].') as '.$channelOrderId.'.');

        try {
            $this->showRecorded($api->getOrder($ids['order_id']), $sent);
        } catch (ShiprocketException $exception) {
            $this->problem('Could not read the test order back: '.$exception->getMessage());
        } finally {
            $this->cancelTestOrder($api, $ids['order_id'], $channelOrderId);
        }
    }

    protected function showRecorded(array $recorded, float $sent): void
    {
        $this->table(['Shiprocket recorded', 'Value'], array_map(
            fn ($field) => [$field, $this->value(Arr::has($recorded, $field) ? Arr::get($recorded, $field) : null, Arr::has($recorded, $field))],
            self::RECORDED_FIELDS
        ));

        if (is_array($recorded['products'] ?? null) && $recorded['products'] !== []) {
            $this->table(['Product', 'quantity', 'selling_price', 'discount'], array_map(fn ($product) => [
                $this->value($product['name'] ?? null),
                $this->value($product['quantity'] ?? null),
                $this->value($product['selling_price'] ?? null),
                $this->value($product['discount'] ?? null),
            ], $recorded['products']));
        }

        $total = $recorded['total'] ?? null;

        if (! is_numeric($total)) {
            $this->problem('Shiprocket returned no total to compare with the amount sent ('.$this->amount($sent).').');
        } elseif (abs((float) $total - $sent) < 0.005) {
            $this->info('Shiprocket\'s total '.$this->amount($total).' equals the amount sent (sub_total + shipping_charges) '.$this->amount($sent).'.');
        } else {
            $this->problem('Shiprocket\'s total '.$this->amount($total).' differs from the amount sent (sub_total + shipping_charges) '.$this->amount($sent).': check what it would collect before going live.');
        }
    }

    protected function cancelTestOrder(ShiprocketApi $api, int $shiprocketOrderId, string $channelOrderId): void
    {
        try {
            $api->cancelOrder($shiprocketOrderId);
        } catch (ShiprocketException $exception) {
            $this->problem('CANCEL FAILED for Shiprocket order '.$shiprocketOrderId.' ('.$channelOrderId.'): '.$exception->getMessage().'. Please cancel it by hand in the Shiprocket panel.');

            return;
        }

        try {
            $status = $api->getOrder($shiprocketOrderId)['status'] ?? null;
        } catch (ShiprocketException) {
            $status = null;
        }

        $this->info('Cancelled test order '.$channelOrderId.' (Shiprocket order '.$shiprocketOrderId.')'
            .($status ? '; Shiprocket now shows it as '.$status : '').'.');
    }

    protected function heading(string $title): void
    {
        $this->newLine();
        $this->line('<options=bold>'.$title.'</>');
    }

    protected function problem(string $message): void
    {
        $this->passed = false;

        $this->error($message);
    }

    protected function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    /**
     * Rupees without trailing zeros: 1850, 1950.5, 925.25.
     */
    protected function amount(mixed $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    /**
     * A value as Shiprocket returned it.
     */
    protected function value(mixed $value, bool $present = true): string
    {
        return match (true) {
            ! $present => '(not returned)',
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        };
    }
}
