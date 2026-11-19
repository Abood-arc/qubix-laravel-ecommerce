<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * The shipping method codes of the Shiprocket carrier, e.g. "shiprocket_standard_c58_p94":
 * the tier the customer chose, the courier they paid for (booking reads it from the order)
 * and the quoted price. The backup price has no courier: "shiprocket_backup_p120".
 *
 * The quoted price is part of the code because the stored rate cannot keep it: a free-shipping
 * cart rule zeroes the stored price and shipping tax rewrites it. So "is the price the customer
 * saw still Shiprocket's price?" is simply "is the same code still offered?".
 */
final class RateCode
{
    public const CARRIER = 'shiprocket';

    public const STANDARD = 'standard';

    public const EXPRESS = 'express';

    public const BACKUP = 'backup';

    protected const GST = 1.18;

    public static function make(string $tier, ?int $courierId, float $price): string
    {
        $courier = $courierId === null ? '' : '_c'.$courierId;

        return self::CARRIER.'_'.$tier.$courier.'_p'.self::formatPrice($price);
    }

    /**
     * @return array{tier: string, courier_id: ?int, price: float}|null null when the code is not ours
     */
    public static function parse(?string $code): ?array
    {
        $pattern = '/^'.self::CARRIER.'_(?:(?<tier>'.self::STANDARD.'|'.self::EXPRESS.')_c(?<courier>\d+)|(?<backup>'.self::BACKUP.'))_p(?<price>\d+(?:\.\d{1,2})?)$/';

        if ($code === null || ! preg_match($pattern, $code, $match)) {
            return null;
        }

        $backup = ($match['backup'] ?? '') !== '';

        return [
            'tier' => $backup ? self::BACKUP : $match['tier'],
            'courier_id' => $backup ? null : (int) $match['courier'],
            'price' => (float) $match['price'],
        ];
    }

    /**
     * 94 → "94", 120.5 → "120.5": short, and the same price always gives the same code.
     */
    protected static function formatPrice(float $price): string
    {
        return rtrim(rtrim(number_format(max(0, $price), 2, '.', ''), '0'), '.');
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
