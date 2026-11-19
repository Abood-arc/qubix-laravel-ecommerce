<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * The shipping method codes of the Shiprocket carrier. The code is stored on the order, so it
 * carries what booking needs later: the tier the customer chose and the courier they paid
 * for, e.g. "shiprocket_standard_c58". The backup price has no courier: "shiprocket_backup".
 */
final class RateCode
{
    public const CARRIER = 'shiprocket';

    public const STANDARD = 'standard';

    public const EXPRESS = 'express';

    public const BACKUP = 'backup';

    protected const GST = 1.18;

    public static function make(string $tier, ?int $courierId = null): string
    {
        return $courierId === null
            ? self::CARRIER.'_'.$tier
            : self::CARRIER.'_'.$tier.'_c'.$courierId;
    }

    /**
     * @return array{tier: string, courier_id: ?int}|null null when the code is not ours
     */
    public static function parse(?string $code): ?array
    {
        if ($code === null) {
            return null;
        }

        if ($code === self::make(self::BACKUP)) {
            return ['tier' => self::BACKUP, 'courier_id' => null];
        }

        if (! preg_match('/^'.self::CARRIER.'_('.self::STANDARD.'|'.self::EXPRESS.')_c(\d+)$/', $code, $match)) {
            return null;
        }

        return ['tier' => $match[1], 'courier_id' => (int) $match[2]];
    }

    /**
     * What the customer pays for a courier's rate: with 18% GST when the rates exclude it,
     * rounded up to the whole rupee (the store never charges less than it is billed).
     */
    public static function price(float $rate, bool $addGst): int
    {
        // Rounded to 4 places first, so float noise (100 × 1.18 = 118.00000000000001) is not
        // rounded up to another rupee.
        return (int) ceil(round($rate * ($addGst ? self::GST : 1), 4));
    }
}
