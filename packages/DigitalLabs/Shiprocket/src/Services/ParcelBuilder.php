<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Support\Settings;
use DigitalLabs\Shiprocket\Support\WeightUnit;
use Illuminate\Support\Collection;

/**
 * Builds the Shiprocket order payload (orders/create/adhoc) and the parcel weight.
 *
 * Only top-level order items are used (`$order->items` leaves out a configurable's
 * child row, which repeats the parent's weight), with the quantity still to ship.
 */
class ParcelBuilder
{
    protected const COD_METHOD = 'cashondelivery';

    /**
     * Each side of the parcel must be more than this (Create Custom Order: length, breadth
     * and height "Must be more than 0.5").
     */
    protected const MIN_SIDE_CM = 0.5;

    public function __construct(protected AddressNormalizer $addresses) {}

    public function weightKg(Order $order): float
    {
        $weight = $this->itemsToShip($order)
            ->sum(fn ($item) => (float) $item->weight * $item->qty_to_ship);

        return round($weight * WeightUnit::kgPerUnit(), 3);
    }

    /**
     * The payload for Shiprocket's "Create Custom Order".
     *
     * @throws ShiprocketException when an address or the parcel size cannot be used
     */
    public function build(Order $order): array
    {
        $dimensions = $this->dimensions();

        $items = $this->itemsToShip($order);

        $billing = $this->addresses->normalize($order->billing_address);

        $shipping = $this->addresses->normalize($order->shipping_address ?? $order->billing_address);

        [$subTotal, $shippingCharges] = $this->amounts($order, $items);

        return [
            'order_id' => (string) $order->increment_id,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => Settings::pickupLocation(),
            ...$this->addressFields('billing_', $billing, $order),
            'shipping_is_billing' => false,
            ...$this->addressFields('shipping_', $shipping, $order),
            'order_items' => $this->itemLines($items, $subTotal),
            'payment_method' => $order->payment?->method === self::COD_METHOD ? 'COD' : 'Prepaid',
            'sub_total' => $subTotal,
            'shipping_charges' => $shippingCharges,
            'total_discount' => 0.0,
            ...$dimensions,
            'weight' => $this->weightKg($order),
        ];
    }

    /**
     * The parcel size from the settings. Shiprocket wants each side "more than 0.5" cm and
     * otherwise refuses with a bare "Invalid Data", so a wrong setting fails here, by name.
     *
     * @return array{length: float, breadth: float, height: float}
     *
     * @throws ShiprocketException
     */
    protected function dimensions(): array
    {
        $dimensions = Settings::dimensions();

        foreach ($dimensions as $size) {
            if ($size <= self::MIN_SIDE_CM) {
                throw new ShiprocketException(sprintf(
                    'The parcel size in the Shiprocket settings must be more than %s cm on every side (length %s, breadth %s, height %s).',
                    self::MIN_SIDE_CM,
                    ...array_values($dimensions),
                ));
            }
        }

        return $dimensions;
    }

    protected function itemsToShip(Order $order): Collection
    {
        return $order->items
            ->filter(fn ($item) => $item->qty_to_ship > 0)
            ->values();
    }

    /**
     * Shiprocket's name for the first name is `<prefix>customer_name`. Its PIN codes and
     * phone numbers are integers, and the normalizer has made both digits only.
     */
    protected function addressFields(string $prefix, array $address, Order $order): array
    {
        return [
            $prefix.'customer_name' => $address['first_name'],
            $prefix.'last_name' => $address['last_name'],
            $prefix.'address' => $address['address'],
            $prefix.'address_2' => $address['address_2'],
            $prefix.'city' => $address['city'],
            $prefix.'pincode' => (int) $address['pincode'],
            $prefix.'state' => $address['state'],
            $prefix.'country' => $address['country'],
            $prefix.'email' => $address['email'] ?: (string) $order->customer_email,
            $prefix.'phone' => (int) $address['phone'],
        ];
    }

    /**
     * The amount Shiprocket collects on a COD parcel and shows on its invoice. Shiprocket
     * adds nothing up itself ("the total is not calculated automatically"), so the payload
     * says it plainly: `sub_total` is the goods after discount, with tax, and
     * `shipping_charges` the shipping the customer paid (after a shipping discount, with
     * tax). No discount is sent anywhere else (`total_discount` and every item's `discount`
     * are 0), so none can be taken off twice.
     *
     * A parcel carrying the whole order sends `grand_total - shipping_charges` as the sub
     * total, so the two add up to the grand total exactly. The rest of a partly shipped,
     * refunded or cancelled order declares only the units in this parcel, without shipping.
     *
     * @return array{0: float, 1: float} the sub total and the shipping charges
     */
    protected function amounts(Order $order, Collection $items): array
    {
        if ($this->isWholeOrder($order)) {
            $shipping = round(max(0, $order->shipping_amount + $order->shipping_tax_amount - $order->shipping_discount_amount), 2);

            return [round($order->grand_total - $shipping, 2), $shipping];
        }

        return [round($items->sum(fn ($item) => $this->netAmount($item)), 2), 0.0];
    }

    /**
     * One line per item, each unit at its net price: its share of the item's discount taken
     * off, its tax included (`tax` is the rate, %). The paise lost rounding unit prices go on
     * the last single-unit line (else the last line), so the lines add up to the sub total;
     * only when every line has several units can a paisa or two per line remain.
     */
    protected function itemLines(Collection $items, float $subTotal): array
    {
        $lines = $items->map(fn ($item) => [
            'name' => $item->name,
            'sku' => $item->sku,
            'units' => (int) $item->qty_to_ship,
            'selling_price' => round($this->netAmount($item) / $item->qty_to_ship, 2),
            'discount' => 0.0,
            'tax' => (float) $item->tax_percent,
        ])->values()->all();

        if ($lines === []) {
            return $lines;
        }

        $paise = fn (float $amount) => (int) round($amount * 100);

        $remainder = $paise($subTotal) - array_sum(array_map(
            fn ($line) => $paise($line['selling_price']) * $line['units'],
            $lines
        ));

        if ($remainder !== 0) {
            $singles = array_keys(array_filter($lines, fn ($line) => $line['units'] === 1));

            $last = $singles !== [] ? end($singles) : array_key_last($lines);

            $perUnit = (int) round($remainder / $lines[$last]['units']);

            $lines[$last]['selling_price'] = ($paise($lines[$last]['selling_price']) + $perUnit) / 100;
        }

        return $lines;
    }

    /**
     * What the customer pays for the units of an item in this parcel: their share of the
     * item's total and tax, less their share of its discount.
     */
    protected function netAmount($item): float
    {
        return ($item->total + $item->tax_amount - $item->discount_amount) * $item->qty_to_ship / $item->qty_ordered;
    }

    protected function isWholeOrder(Order $order): bool
    {
        return $order->items->every(
            fn ($item) => $item->qty_shipped + $item->qty_refunded + $item->qty_canceled == 0
        );
    }
}
