<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * One courier Shiprocket offers for a parcel: its id (to book exactly it), its price for this
 * parcel (with the cash on delivery fee when asked with COD) and its delivery estimate.
 */
final class Courier
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $rate,
        /** Estimated days to deliver; 0 when Shiprocket gave none. */
        public readonly int $days,
        /** Shiprocket's delivery date text, e.g. "Nov 25, 2026"; empty when it gave none. */
        public readonly string $etd,
    ) {}

    /**
     * One entry of `available_courier_companies`.
     */
    public static function fromApi(array $company): self
    {
        return new self(
            (int) ($company['courier_company_id'] ?? 0),
            trim((string) ($company['courier_name'] ?? '')),
            (float) ($company['rate'] ?? 0),
            max(0, (int) ($company['estimated_delivery_days'] ?? 0)),
            trim((string) ($company['etd'] ?? '')),
        );
    }

    /**
     * Plain values for the cache, so a cached list never depends on this class's shape.
     *
     * @return array{id: int, name: string, rate: float, days: int, etd: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'rate' => $this->rate, 'days' => $this->days, 'etd' => $this->etd];
    }

    public static function fromArray(array $values): self
    {
        return new self(
            (int) ($values['id'] ?? 0),
            (string) ($values['name'] ?? ''),
            (float) ($values['rate'] ?? 0),
            (int) ($values['days'] ?? 0),
            (string) ($values['etd'] ?? ''),
        );
    }
}
