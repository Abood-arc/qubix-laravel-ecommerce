<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Sales\Contracts\Order;
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

    public function __construct(protected AddressNormalizer $addresses) {}

    public function weightKg(Order $order): float
    {
        $weight = $this->itemsToShip($order)
            ->sum(fn ($item) => (float) $item->weight * $item->qty_to_ship);

        return round($weight * WeightUnit::kgPerUnit(), 3);
    }

    /**
     * @throws \DigitalLabs\Shiprocket\Exceptions\ShiprocketException when an address cannot be used
     */
    public function build(Order $order): array
    {
        $items = $this->itemsToShip($order);

        $billing = $this->addresses->normalize($order->billing_address);

        $shipping = $this->addresses->normalize($order->shipping_address ?? $order->billing_address);

        return [
            'order_id' => (string) $order->increment_id,
            'order_date' => $order->created_at->format('Y-m-d H:i'),
            'pickup_location' => Settings::pickupLocation(),
            ...$this->addressFields('billing_', $billing, $order),
            'shipping_is_billing' => false,
            ...$this->addressFields('shipping_', $shipping, $order),
            'order_items' => $items->map(fn ($item) => $this->itemLine($item))->all(),
            'payment_method' => $order->payment?->method === self::COD_METHOD ? 'COD' : 'Prepaid',
            ...$this->amounts($order, $items),
            ...Settings::dimensions(),
            'weight' => $this->weightKg($order),
        ];
    }

    protected function itemsToShip(Order $order): Collection
    {
        return $order->items
            ->filter(fn ($item) => $item->qty_to_ship > 0)
            ->values();
    }

    /**
     * Shiprocket's name for the first name is `<prefix>customer_name`.
     */
    protected function addressFields(string $prefix, array $address, Order $order): array
    {
        return [
            $prefix.'customer_name' => $address['first_name'],
            $prefix.'last_name' => $address['last_name'],
            $prefix.'address' => $address['address'],
            $prefix.'address_2' => $address['address_2'],
            $prefix.'city' => $address['city'],
            $prefix.'pincode' => $address['pincode'],
            $prefix.'state' => $address['state'],
            $prefix.'country' => $address['country'],
            $prefix.'email' => $address['email'] ?: (string) $order->customer_email,
            $prefix.'phone' => $address['phone'],
        ];
    }

    /**
     * Per-unit amounts: the selling price includes the item's tax, and `tax` is its rate (%).
     * The discount is sent once, as the order's `total_discount` (see amounts()), never per
     * item as well, so Shiprocket cannot take it off twice.
     */
    protected function itemLine($item): array
    {
        return [
            'name' => $item->name,
            'sku' => $item->sku,
            'units' => (int) $item->qty_to_ship,
            'selling_price' => round(($item->total + $item->tax_amount) / $item->qty_ordered, 2),
            'discount' => 0.0,
            'tax' => (float) $item->tax_percent,
        ];
    }

    /**
     * Shiprocket collects `sub_total + shipping_charges - total_discount` on a COD parcel.
     *
     * A parcel carrying the whole order uses the order's own totals, so that amount is
     * exactly `grand_total` (sub_total + tax + shipping - discount): item tax goes into
     * `sub_total`, shipping tax into `shipping_charges`. The rest of a partly shipped,
     * refunded or cancelled order declares only the units in this parcel, without shipping.
     *
     * The item lines carry no discount, so this is the only place it is sent. Not yet
     * confirmed against Shiprocket itself: on the first live discounted COD booking, check
     * that the amount Shiprocket shows to collect equals the order's grand_total.
     */
    protected function amounts(Order $order, Collection $items): array
    {
        if ($this->isWholeOrder($order)) {
            return [
                'sub_total' => round($order->sub_total + $order->tax_amount - $order->shipping_tax_amount, 2),
                'shipping_charges' => round($order->shipping_amount + $order->shipping_tax_amount, 2),
                'total_discount' => round((float) $order->discount_amount, 2),
            ];
        }

        $share = fn ($item, $amount) => $amount * $item->qty_to_ship / $item->qty_ordered;

        return [
            'sub_total' => round($items->sum(fn ($item) => $share($item, $item->total + $item->tax_amount)), 2),
            'shipping_charges' => 0.0,
            'total_discount' => round($items->sum(fn ($item) => $share($item, $item->discount_amount)), 2),
        ];
    }

    protected function isWholeOrder(Order $order): bool
    {
        return $order->items->every(
            fn ($item) => $item->qty_shipped + $item->qty_refunded + $item->qty_canceled == 0
        );
    }
}
