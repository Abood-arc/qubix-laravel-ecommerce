<?php

namespace DigitalLabs\Shiprocket\Jobs;

use DateTimeInterface;
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
 * The job carries the booking generation it was sent for, so a retry left over from
 * before a cancel does nothing.
 *
 * Time budget: production runs `queue:work redis --tries=3` with retry_after 90 s. The job
 * is stopped after 85 s ($timeout), before the queue would hand it to a second worker.
 * Each Shiprocket call is capped at 12 s (ShiprocketClient), so a first booking (login,
 * order, courier, pickup, manifest, label: 6 × 12 s) plus the 3 s lock wait takes at most
 * 75 s. A resumed one (one more lookup) can reach 87 s, and a token renewal adds more; the
 * job is then stopped during the manifest or label, which come after the store Shipment is
 * saved, so the next try finds the booking done (the label is fetched when the admin
 * downloads it). A stop earlier on is resumed by the next try, since every step is saved.
 */
class BookShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Seconds to wait when another booking of the same order is running.
     */
    public const BUSY_RETRY_SECONDS = 30;

    /**
     * How long the job keeps being tried, counted from when it was queued.
     */
    public const RETRY_MINUTES = 30;

    public int $timeout = 85;

    /**
     * Failed tries (an unknown outcome) before the job gives up.
     *
     * Not $tries: with retryUntil() the queue ignores $tries (and --tries), and a try that
     * only found the order busy (released, no exception) must not use one up.
     */
    public int $maxExceptions = 3;

    public function __construct(
        public int $orderId,
        public bool $manual = false,
        public ?int $generation = null,
    ) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(self::RETRY_MINUTES);
    }

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

        if ($booker->attempt($order, $this->manual, $this->generation) === null) {
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
            $this->generation,
        );
    }
}
