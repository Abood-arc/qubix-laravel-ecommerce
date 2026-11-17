<?php

namespace DigitalLabs\Shiprocket\Http\Controllers;

use DigitalLabs\Shiprocket\Mail\AdminAlert;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use DigitalLabs\Shiprocket\Services\DeliveryCompleter;
use DigitalLabs\Shiprocket\Services\StatusMapper;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Receives Shiprocket tracking updates.
 *
 * Updates can repeat and arrive out of order: the parcel status only ever moves forward,
 * and a delivered Cash on Delivery order is completed (or a return reported) only on the
 * update that first reaches that status.
 */
class WebhookController extends Controller
{
    protected const COD_METHOD = 'cashondelivery';

    public function __construct(
        protected StatusMapper $statusMapper,
        protected DeliveryCompleter $deliveryCompleter,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            // Production logs at error level only, so a refusal must be an error to be seen.
            Log::error('Shiprocket webhook refused: token mismatch.', ['ip' => $request->ip()]);

            return response()->json(['ok' => false], 401);
        }

        $payload = $request->all();

        $awb = $payload['awb'] ?? null;

        if (! is_scalar($awb) || trim((string) $awb) === '') {
            return $this->accepted();
        }

        $returned = DB::transaction(fn () => $this->apply(trim((string) $awb), $payload));

        if ($returned) {
            Mail::queue(new AdminAlert($returned->order, 'rto', ['awb' => $returned->awb_code]));
        }

        return $this->accepted();
    }

    /**
     * Record the update; returns the parcel when it has just started coming back.
     */
    protected function apply(string $awb, array $payload): ?ShiprocketShipment
    {
        $shipment = ShiprocketShipment::query()
            ->where('awb_code', $awb)
            ->lockForUpdate()
            ->first();

        if (! $shipment) {
            return null;
        }

        if (is_scalar($payload['current_status'] ?? null)) {
            $shipment->last_event = Str::limit((string) $payload['current_status'], 250, '');

            $shipment->save();
        }

        $status = $this->statusMapper->map($payload);

        if (! $status || ! $shipment->advanceTo($status)) {
            return null;
        }

        if (
            $status === ShiprocketShipment::STATUS_DELIVERED
            && $shipment->order->payment?->method === self::COD_METHOD
        ) {
            // Inside the transaction: if completing fails, the status is not saved either and
            // Shiprocket's retry of this update gets another go.
            $this->deliveryCompleter->complete($shipment->order);
        }

        return $status === ShiprocketShipment::STATUS_RTO ? $shipment : null;
    }

    protected function hasValidToken(Request $request): bool
    {
        $expected = Settings::webhookToken();

        $given = $request->header('x-api-key');

        return $expected !== ''
            && is_string($given)
            && hash_equals($expected, $given);
    }

    protected function accepted(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
