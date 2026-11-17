<?php

/*
 * Order fixtures shared by the Shiprocket webhook, delivery and tracking-link tests.
 * Loaded with require_once (this file does not end in Test.php, so Pest never runs it).
 */

use DigitalLabs\Core\Models\CoreConfig;
use DigitalLabs\Sales\Models\Invoice;
use DigitalLabs\Sales\Models\Order;
use DigitalLabs\Sales\Models\OrderAddress;
use DigitalLabs\Sales\Models\OrderItem;
use DigitalLabs\Sales\Models\OrderPayment;
use DigitalLabs\Sales\Models\Shipment;
use DigitalLabs\Sales\Models\ShipmentItem;
use DigitalLabs\Shiprocket\Models\ShiprocketShipment;
use Webkul\Faker\Helpers\Customer as CustomerFaker;
use Webkul\Faker\Helpers\Product as ProductFaker;

/**
 * Save one admin setting for the current channel, replacing any saved value.
 */
function shiprocketDeliveryConfig(string $code, mixed $value): void
{
    CoreConfig::query()->where('code', $code)->get()->each->delete();

    CoreConfig::create([
        'code' => $code,
        'value' => $value,
        'channel_code' => core()->getRequestedChannelCode(),
    ]);
}

/**
 * Switch Shiprocket on with an API user and a webhook token.
 */
function enableShiprocketForDelivery(string $token = 'hook-token'): void
{
    config([
        'shiprocket.email' => 'api@example.com',
        'shiprocket.password' => 'secret',
        'shiprocket.webhook_token' => $token,
    ]);

    shiprocketDeliveryConfig('sales.shipping.shiprocket.active', '1');
}

/**
 * A one-item order paid with $method, optionally shipped (with a store shipment) and invoiced.
 */
function shiprocketDeliveryOrder(string $method = 'cashondelivery', bool $shipped = true, ?string $invoiceState = null): Order
{
    $customer = (new CustomerFaker)->factory()->create();

    $product = (new ProductFaker)->getSimpleProductFactory()->create();

    $order = Order::factory()->create([
        'customer_id' => $customer->id,
        'customer_email' => $customer->email,
        'customer_first_name' => $customer->first_name,
        'customer_last_name' => $customer->last_name,
        'status' => $invoiceState === Invoice::STATUS_PENDING ? Order::STATUS_PENDING_PAYMENT : Order::STATUS_PROCESSING,
        'grand_total' => 1000,
        'base_grand_total' => 1000,
        'sub_total' => 1000,
        'base_sub_total' => 1000,
        'grand_total_invoiced' => 0,
        'base_grand_total_invoiced' => 0,
        'sub_total_invoiced' => 0,
        'base_sub_total_invoiced' => 0,
        'grand_total_refunded' => 0,
        'sub_total_refunded' => 0,
        'base_sub_total_refunded' => 0,
    ]);

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'type' => 'simple',
        'weight' => 1,
        'qty_ordered' => 1,
        'qty_shipped' => $shipped ? 1 : 0,
        'qty_invoiced' => $invoiceState ? 1 : 0,
        'price' => 1000,
        'base_price' => 1000,
        'total' => 1000,
        'base_total' => 1000,
        'tax_amount' => 0,
        'base_tax_amount' => 0,
        'discount_amount' => 0,
        'base_discount_amount' => 0,
    ]);

    foreach ([OrderAddress::ADDRESS_TYPE_BILLING, OrderAddress::ADDRESS_TYPE_SHIPPING] as $type) {
        OrderAddress::factory()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'address_type' => $type,
        ]);
    }

    OrderPayment::factory()->create(['order_id' => $order->id, 'method' => $method]);

    if ($invoiceState) {
        Invoice::factory()->create([
            'order_id' => $order->id,
            'state' => $invoiceState,
            'total_qty' => 1,
            'base_currency_code' => $order->base_currency_code,
            'channel_currency_code' => $order->channel_currency_code,
            'order_currency_code' => $order->order_currency_code,
            'email_sent' => 1,
            'sub_total' => 1000,
            'base_sub_total' => 1000,
            'grand_total' => 1000,
            'base_grand_total' => 1000,
        ]);
    }

    if ($shipped) {
        $shipment = shiprocketDeliveryStoreShipment($order, 'Shiprocket – Delhivery', '1900000001'.$order->id);

        // Invoicing recounts qty_shipped from the shipment items.
        ShipmentItem::create([
            'shipment_id' => $shipment->id,
            'order_item_id' => $item->id,
            'product_id' => $product->id,
            'name' => $item->name,
            'sku' => $item->sku,
            'qty' => 1,
            'price' => 1000,
            'base_price' => 1000,
            'total' => 1000,
            'base_total' => 1000,
        ]);
    }

    return $order->fresh();
}

function shiprocketDeliveryStoreShipment(Order $order, string $carrierTitle, string $trackNumber): Shipment
{
    return Shipment::factory()->create([
        'order_id' => $order->id,
        'total_qty' => 1,
        'carrier_title' => $carrierTitle,
        'track_number' => $trackNumber,
        'inventory_source_id' => 1,
        'order_address_id' => $order->shipping_address?->id,
    ]);
}

/**
 * The order's Shiprocket row, booked and handed to the courier.
 */
function shiprocketDeliveryRow(Order $order, string $status = ShiprocketShipment::STATUS_PICKUP_SCHEDULED): ShiprocketShipment
{
    return ShiprocketShipment::create([
        'order_id' => $order->id,
        'status' => $status,
        'shiprocket_order_id' => 5000 + $order->id,
        'shipment_id' => 6000 + $order->id,
        'awb_code' => 'AWB'.$order->id,
        'courier_name' => 'Delhivery',
        'store_shipment_id' => $order->shipments()->first()?->id,
    ]);
}
