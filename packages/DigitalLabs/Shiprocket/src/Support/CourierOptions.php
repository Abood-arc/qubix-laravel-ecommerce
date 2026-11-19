<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * The two delivery choices a customer gets from one courier list: Standard (the cheapest
 * courier) and Express (the fastest, offered only when it is really faster than Standard).
 */
final class CourierOptions
{
    /**
     * @param  list<Courier>  $couriers
     */
    public function __construct(protected array $couriers) {}

    /**
     * The cheapest courier; on equal prices the faster, then the lower id (so the choice
     * never changes between two identical answers).
     */
    public function standard(): ?Courier
    {
        return collect($this->couriers)
            ->sortBy([
                fn (Courier $a, Courier $b) => $a->rate <=> $b->rate,
                fn (Courier $a, Courier $b) => $this->speed($a) <=> $this->speed($b),
                fn (Courier $a, Courier $b) => $a->id <=> $b->id,
            ])
            ->first();
    }

    /**
     * The fastest courier, when it delivers in fewer days than Standard; on equal days the
     * cheaper, then the lower id. A courier without an estimate is never Express.
     */
    public function express(): ?Courier
    {
        $standard = $this->standard();

        $fastest = collect($this->couriers)
            ->filter(fn (Courier $courier) => $courier->days > 0)
            ->sortBy([
                fn (Courier $a, Courier $b) => $a->days <=> $b->days,
                fn (Courier $a, Courier $b) => $a->rate <=> $b->rate,
                fn (Courier $a, Courier $b) => $a->id <=> $b->id,
            ])
            ->first();

        if (! $standard || ! $fastest || $fastest->days >= $this->speed($standard)) {
            return null;
        }

        return $fastest;
    }

    /**
     * The courier of one tier (RateCode::STANDARD or RateCode::EXPRESS).
     */
    public function get(string $tier): ?Courier
    {
        return match ($tier) {
            RateCode::STANDARD => $this->standard(),
            RateCode::EXPRESS => $this->express(),
            default => null,
        };
    }

    /**
     * Days to deliver, an unknown estimate counting as slowest.
     */
    protected function speed(Courier $courier): int
    {
        return $courier->days > 0 ? $courier->days : PHP_INT_MAX;
    }
}
