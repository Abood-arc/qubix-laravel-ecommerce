<?php

namespace DigitalLabs\Shiprocket\Support;

/**
 * The single place that reads Shiprocket's admin settings and .env values.
 */
class Settings
{
    protected const KEY = 'sales.shipping.shiprocket.';

    /**
     * On in admin and the API user is set in .env.
     */
    public static function enabled(): bool
    {
        return (bool) static::setting('active')
            && filled(static::email())
            && filled(static::password());
    }

    public static function pickupLocation(): string
    {
        return trim((string) static::setting('pickup_location'));
    }

    public static function pickupPin(): string
    {
        return trim((string) static::setting('pickup_pin'));
    }

    /**
     * @return array{length: float, breadth: float, height: float}
     */
    public static function dimensions(): array
    {
        return [
            'length' => (float) static::setting('length'),
            'breadth' => (float) static::setting('breadth'),
            'height' => (float) static::setting('height'),
        ];
    }

    public static function autoSend(): bool
    {
        return (bool) static::setting('auto_send');
    }

    public static function pinCheck(): bool
    {
        return (bool) static::setting('pin_check');
    }

    public static function maxWeightKg(): float
    {
        $max = (float) static::setting('max_weight');

        return $max > 0 ? $max : 10.0;
    }

    public static function email(): string
    {
        return (string) config('shiprocket.email');
    }

    public static function password(): string
    {
        return (string) config('shiprocket.password');
    }

    public static function webhookToken(): string
    {
        return (string) config('shiprocket.webhook_token');
    }

    public static function fake(): bool
    {
        return (bool) config('shiprocket.fake');
    }

    public static function trackingUrl(string $awb): string
    {
        return config('shiprocket.tracking_url').rawurlencode($awb);
    }

    /**
     * @return string[]
     */
    public static function prepaidMethods(): array
    {
        return (array) config('shiprocket.prepaid_methods', []);
    }

    protected static function setting(string $field): mixed
    {
        return core()->getConfigData(static::KEY.$field);
    }
}
