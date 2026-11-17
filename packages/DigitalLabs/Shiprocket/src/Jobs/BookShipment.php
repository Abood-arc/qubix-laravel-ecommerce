<?php

namespace DigitalLabs\Shiprocket\Jobs;

use DigitalLabs\Shiprocket\Services\ShipmentBooker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LogicException;

/**
 * Books an order with Shiprocket in the background (Task 6).
 */
class BookShipment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $orderId,
        public bool $manual = false,
    ) {}

    public function handle(ShipmentBooker $booker): void
    {
        throw new LogicException('Not implemented yet: Task 6');
    }
}
