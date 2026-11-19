<?php

namespace DigitalLabs\Shop\Http\Controllers\API;

use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Customer\Repositories\CustomerRepository;
use DigitalLabs\Payment\Facades\Payment;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Sales\Transformers\OrderResource;
use DigitalLabs\Shipping\Contracts\DeliveryCheck;
use DigitalLabs\Shipping\Facades\Shipping;
use DigitalLabs\Shop\Http\Requests\CartAddressRequest;
use DigitalLabs\Shop\Http\Resources\CartResource;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

class OnepageController extends APIController
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected CustomerRepository $customerRepository
    ) {}

    /**
     * Return cart summary.
     */
    public function summary(): JsonResource
    {
        $cart = Cart::getCart();

        return new CartResource($cart);
    }

    /**
     * Store address.
     */
    public function storeAddress(CartAddressRequest $cartAddressRequest): JsonResource
    {
        $params = $cartAddressRequest->all();

        if (
            ! auth()->guard('customer')->check()
            && ! Cart::getCart()->hasGuestCheckoutItems()
        ) {
            return new JsonResource([
                'redirect' => true,
                'data' => route('shop.customer.session.index'),
            ]);
        }

        if (Cart::hasError()) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.cart.index'),
            ]);
        }

        Cart::saveAddresses($params);

        $this->forgetPaymentMethod();

        $cart = Cart::getCart();

        Cart::collectTotals();

        if ($cart->haveStockableItems()) {
            if ($reason = app(DeliveryCheck::class)->check($cart->shipping_address, $cart)) {
                // Shown on the postcode the customer typed: the billing one when it doubles as shipping.
                $field = ($params['billing']['use_for_shipping'] ?? false) ? 'billing.postcode' : 'shipping.postcode';

                throw ValidationException::withMessages([$field => $reason]);
            }

            if (! $rates = Shipping::collectRates()) {
                return new JsonResource([
                    'redirect' => true,
                    'redirect_url' => route('shop.checkout.cart.index'),
                ]);
            }

            return new JsonResource([
                'redirect' => false,
                'data' => $rates,
            ]);
        }

        return new JsonResource([
            'redirect' => false,
            'data' => Payment::getSupportedPaymentMethods(includeDisabled: true),
        ]);
    }

    /**
     * Store shipping method.
     *
     * @return \Illuminate\Http\Response
     */
    public function storeShippingMethod()
    {
        $validatedData = $this->validate(request(), [
            'shipping_method' => 'required',
        ]);

        $forbidden = fn () => response()->json([
            'redirect_url' => route('shop.checkout.cart.index'),
        ], Response::HTTP_FORBIDDEN);

        if (
            Cart::hasError()
            || ! $validatedData['shipping_method']
        ) {
            return $forbidden();
        }

        $this->forgetPaymentMethod();

        // A carrier whose codes change with its prices maps an option shown earlier to its
        // current code, instead of the customer being sent back to the cart.
        $shippingMethod = Event::until('checkout.cart.shipping_method.resolve', [$validatedData['shipping_method']])
            ?? $validatedData['shipping_method'];

        if (! Cart::saveShippingMethod($shippingMethod)) {
            return $forbidden();
        }

        Cart::collectTotals();

        return response()->json(Payment::getSupportedPaymentMethods(includeDisabled: true));
    }

    /**
     * After the address or the delivery option is saved, the page asks for the payment method
     * again and shows none chosen; the cart forgets the earlier choice too. Otherwise delivery
     * priced by payment method (Shiprocket's cash on delivery fee) would follow a choice the
     * customer no longer sees.
     */
    protected function forgetPaymentMethod(): void
    {
        $cart = Cart::getCart();

        if (! $cart?->payment) {
            return;
        }

        $cart->payment->delete();

        Cart::setCart($cart->refresh());
    }

    /**
     * Store payment method.
     *
     * @return array
     */
    public function storePaymentMethod()
    {
        $validatedData = $this->validate(request(), [
            'payment' => 'required',
        ]);

        if (
            Cart::hasError()
            || ! $validatedData['payment']
            || ! Cart::savePaymentMethod($validatedData['payment'])
        ) {
            return response()->json([
                'redirect_url' => route('shop.checkout.cart.index'),
            ], Response::HTTP_FORBIDDEN);
        }

        // A shipping price can depend on how the customer pays (a cash on delivery fee).
        Event::dispatch('checkout.cart.payment.save.after', Cart::getCart());

        Cart::collectTotals();

        $cart = Cart::getCart();

        return [
            'cart' => new CartResource($cart),
        ];
    }

    /**
     * Store order
     */
    public function storeOrder()
    {
        if (Cart::hasError()) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => route('shop.checkout.cart.index'),
            ]);
        }

        Cart::collectTotals();

        try {
            $this->validateOrder();
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        $cart = Cart::getCart();

        if ($redirectUrl = Payment::getRedirectUrl($cart)) {
            return new JsonResource([
                'redirect' => true,
                'redirect_url' => $redirectUrl,
            ]);
        }

        $data = (new OrderResource($cart))->jsonSerialize();

        $order = $this->orderRepository->create($data);

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return new JsonResource([
            'redirect' => true,
            'redirect_url' => route('shop.checkout.onepage.success'),
        ]);
    }

    /**
     * Validate order before creation.
     *
     * @return void|\Exception
     */
    public function validateOrder()
    {
        $cart = Cart::getCart();

        $minimumOrderAmount = core()->getConfigData('sales.order_settings.minimum_order.minimum_order_amount') ?: 0;

        if (
            auth()->guard('customer')->check()
            && auth()->guard('customer')->user()->is_suspended
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.suspended-account-message'));
        }

        if (
            auth()->guard('customer')->user()
            && ! auth()->guard('customer')->user()->status
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.inactive-account-message'));
        }

        if (! Cart::haveMinimumOrderAmount()) {
            throw new \Exception(trans('shop::app.checkout.cart.minimum-order-message', ['amount' => core()->currency($minimumOrderAmount)]));
        }

        if ($cart->haveStockableItems() && ! $cart->shipping_address) {
            throw new \Exception(trans('shop::app.checkout.onepage.address.check-shipping-address'));
        }

        if (! $cart->billing_address) {
            throw new \Exception(trans('shop::app.checkout.onepage.address.check-billing-address'));
        }

        if (
            $cart->haveStockableItems()
            && ! $cart->selected_shipping_rate
        ) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-shipping-method'));
        }

        if (
            $cart->haveStockableItems()
            && $reason = app(DeliveryCheck::class)->check($cart->shipping_address, $cart)
        ) {
            throw new \Exception($reason);
        }

        if (! $cart->payment) {
            throw new \Exception(trans('shop::app.checkout.cart.specify-payment-method'));
        }

        $paymentMethodClass = config('payment_methods.'.$cart->payment->method.'.class');

        $paymentMethod = $paymentMethodClass ? app($paymentMethodClass) : null;

        if (! $paymentMethod?->isAvailable()) {
            throw new \Exception($paymentMethod?->getUnavailableReason() ?? trans('shop::app.checkout.cart.payment-method-unavailable'));
        }
    }
}
