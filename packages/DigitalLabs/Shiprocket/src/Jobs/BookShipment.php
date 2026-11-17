<?php

namespace DigitalLabs\Shiprocket\Jobs;

use DigitalLabs\Sales\Models\OrderProxy;
use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Books an order with Shiprocket in the background.
 *
 * Only an unknown outcome (timeout, Shiprocket down) is retried; the booker resumes from
 * the last confirmed step. Refusals are handled by the booker (row failed, admin emailed).
 */
class BookShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Seconds to wait when another booking of the same order is running.
     */
    public const BUSY_RETRY_SECONDS = 30;

    public int $tries = 3;

    public function __construct(
        public int $orderId,
        public bool $manual = false,
    ) {}

    /**
     * @return int[] seconds before the second and third try
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(ShipmentBooker $booker): void
    {
        $order = OrderProxy::modelClass()::query()->find($this->orderId);

        if (! $order || ! Settings::enabled()) {
            return;
        }

        if ($booker->attempt($order, $this->manual) === null) {
            $this->release(self::BUSY_RETRY_SECONDS);
        }
    }

    /**
     * The last try ended without an answer: mark the booking failed and tell the admin.
     */
    public function failed(?Throwable $exception): void
    {
        app(ShipmentBooker::class)->markFailed(
            $this->orderId,
            $exception?->getMessage() ?: trans('shiprocket::app.admin.status.failed'),
        );
    }
}
