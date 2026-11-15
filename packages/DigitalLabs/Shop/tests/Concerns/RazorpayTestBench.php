<?php

namespace DigitalLabs\Shop\Tests\Concerns;

use DigitalLabs\Checkout\Models\Cart;
use DigitalLabs\Checkout\Models\CartAddress;
use DigitalLabs\Checkout\Models\CartItem;
use DigitalLabs\Checkout\Models\CartPayment;
use DigitalLabs\Checkout\Models\CartShippingRate;
use DigitalLabs\Core\Models\Currency;
use DigitalLabs\Customer\Contracts\Customer;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Sales\Transformers\OrderResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Webkul\Faker\Helpers\Product as ProductFaker;

trait RazorpayTestBench
{
    /**
     * Configure the Razorpay method through its config fallback.
     */
    public function configureRazorpay(array $overrides = []): void
    {
        $settings = array_merge([
            'active' => true,
            'key_id' => 'rzp_test_key',
            'key_secret' => 'test_key_secret',
            'webhook_secret' => 'test_webhook_secret',
            'test_customer_email' => null,
        ], $overrides);

        foreach ($settings as $field => $value) {
            config()->set('payment_methods.razorpay.'.$field, $value);
        }
    }

    /**
     * Create a checkout-ready INR cart that pays with Razorpay.
     */
    public function createRazorpayCart(?Customer $customer = null): Cart
    {
        $product = (new ProductFaker([
            'attributes' => [5 => 'new', 26 => 'guest_checkout'],
            'attribute_value' => [
                'new' => ['boolean_value' => true],
                'guest_checkout' => ['boolean_value' => true],
            ],
        ]))->getSimpleProductFactory()->create();

        $cart = Cart::factory()->create(array_merge(
            ['shipping_method' => 'free_free'],
            $customer ? [
                'customer_id' => $customer->id,
                'is_guest' => 0,
                'customer_email' => $customer->email,
                'customer_first_name' => $customer->first_name,
                'customer_last_name' => $customer->last_name,
            ] : []
        ));

        $additional = ['product_id' => $product->id, 'rating' => '0', 'is_buy_now' => '0', 'quantity' => '1'];

        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'sku' => $product->sku,
            'quantity' => 1,
            'name' => $product->name,
            'price' => $product->price,
            'price_incl_tax' => $product->price,
            'base_price' => $product->price,
            'base_price_incl_tax' => $product->price,
            'total' => $product->price,
            'total_incl_tax' => $product->price,
            'base_total' => $product->price,
            'weight' => $product->weight ?? 0,
            'total_weight' => $product->weight ?? 0,
            'base_total_weight' => $product->weight ?? 0,
            'type' => $product->type,
            'additional' => $additional,
        ]);

        CartAddress::factory()->create(['cart_id' => $cart->id, 'address_type' => CartAddress::ADDRESS_TYPE_BILLING]);

        $shipping = CartAddress::factory()->create(['cart_id' => $cart->id, 'address_type' => CartAddress::ADDRESS_TYPE_SHIPPING]);

        CartPayment::factory()->create(['cart_id' => $cart->id, 'method' => 'razorpay', 'method_title' => 'Razorpay']);

        CartShippingRate::factory()->create([
            'carrier' => 'free',
            'carrier_title' => 'Free shipping',
            'method' => 'free_free',
            'method_title' => 'Free Shipping',
            'method_description' => 'Free Shipping',
            'cart_address_id' => $shipping->id,
            'cart_id' => $cart->id,
        ]);

        cart()->setCart($cart);

        cart()->collectTotals();

        // A fresh install (CI, the `testing` database) only has USD; jjbags.in sells in INR.
        Currency::firstOrCreate(['code' => 'INR'], ['name' => 'Indian Rupee', 'symbol' => '₹', 'decimal' => 2]);

        $cart->refresh()->update(['cart_currency_code' => 'INR']);

        cart()->setCart($cart->refresh());

        return $cart;
    }

    /**
     * Create a payment row holding a frozen copy of the cart.
     */
    public function createRazorpayPayment(Cart $cart, array $attributes = []): RazorpayPayment
    {
        $razorpayOrderId = $attributes['razorpay_order_id'] ?? 'order_'.Str::random(14);

        $snapshot = (new OrderResource($cart->fresh()))->jsonSerialize();

        $snapshot['payment']['additional'] = ['razorpay_order_id' => $razorpayOrderId];

        return RazorpayPayment::create(array_merge([
            'cart_id' => $cart->id,
            'razorpay_order_id' => $razorpayOrderId,
            'amount' => (int) round($cart->grand_total * 100),
            'currency' => 'INR',
            'status' => RazorpayPayment::STATUS_CREATED,
            'snapshot' => $snapshot,
        ], $attributes));
    }

    /**
     * Fake every Razorpay API call a payment can make.
     */
    public function fakeRazorpayApi(RazorpayPayment $payment, string $status = 'captured', int $refundStatus = 200): void
    {
        Http::fake([
            'api.razorpay.com/v1/payments/*/refund' => Http::response(['id' => 'rfnd_test', 'status' => 'processed'], $refundStatus),
            'api.razorpay.com/v1/payments/*/capture' => Http::response(['id' => 'pay_test', 'status' => 'captured']),
            'api.razorpay.com/v1/payments/*' => Http::response([
                'id' => 'pay_test',
                'order_id' => $payment->razorpay_order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => $status,
            ]),
        ]);
    }
}
