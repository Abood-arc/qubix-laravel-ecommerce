<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * Converts weights in the store's unit (Configuration > General > Weight Unit) to kilograms,
 * the unit Shiprocket charges by.
 */
class WeightUnit
{
    /**
     * Kilograms per store weight unit; a missing or unknown unit counts as kg.
     */
    protected const KG_PER_UNIT = [
        'g' => 0.001,
        'kgs' => 1.0,
        'lbs' => 0.45359237,
    ];

    public static function kgPerUnit(): float
    {
        $unit = strtolower(trim((string) core()->getConfigData('general.general.locale_options.weight_unit')));

        return self::KG_PER_UNIT[$unit] ?? self::KG_PER_UNIT['kgs'];
    }
}
