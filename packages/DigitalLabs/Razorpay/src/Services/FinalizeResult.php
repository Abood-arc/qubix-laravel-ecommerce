<?php

namespace DigitalLabs\Razorpay\Services;

use DigitalLabs\Sales\Contracts\Order;

final readonly class FinalizeResult
{
    public const ORDERED = 'ordered';

    public const REFUNDED = 'refunded';

    public const REJECTED = 'rejected';

    public const PENDING = 'pending';

    private function __construct(
        public string $status,
        public ?Order $order = null,
        public bool $refundStarted = false,
    ) {}

    public static function ordered(Order $order): self
    {
        return new self(self::ORDERED, $order);
    }

    public static function refunded(bool $refundStarted = false): self
    {
        return new self(self::REFUNDED, refundStarted: $refundStarted);
    }

    public static function rejected(): self
    {
        return new self(self::REJECTED);
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }
}
