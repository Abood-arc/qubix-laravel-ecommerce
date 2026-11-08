<?php

namespace DigitalLabs\Razorpay\Http\Controllers;

use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Payment\Razorpay;
use DigitalLabs\Razorpay\Services\RazorpayClient;
use DigitalLabs\Sales\Transformers\OrderResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class RazorpayController extends Controller
{
    public function __construct(protected RazorpayClient $client) {}

    /**
     * Freeze the order and open the payment popup.
     */
    public function redirect(): View|RedirectResponse
    {
        $cart = Cart::getCart();

        if (! $this->isReadyToPay($cart)) {
            return redirect()->route('shop.checkout.cart.index');
        }

        $method = app(Razorpay::class);

        if (! $method->isAvailable()) {
            return $this->backToCheckout('razorpay::app.errors.unavailable');
        }

        $amount = (int) round($cart->grand_total * 100);

        try {
            $razorpayOrder = $this->client->createOrder($amount, $cart->cart_currency_code, 'cart-'.$cart->id, [
                'cart_id' => (string) $cart->id,
            ]);
        } catch (RazorpayException $exception) {
            report($exception);

            return $this->backToCheckout('razorpay::app.errors.gateway');
        }

        $snapshot = (new OrderResource($cart))->jsonSerialize();

        $snapshot['payment']['additional'] = ['razorpay_order_id' => $razorpayOrder['id']];

        $payment = RazorpayPayment::create([
            'cart_id' => $cart->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $amount,
            'currency' => $cart->cart_currency_code,
            'status' => RazorpayPayment::STATUS_CREATED,
            'snapshot' => $snapshot,
        ]);

        return view('razorpay::redirect', [
            'payment' => $payment,
            'checkoutOptions' => $this->checkoutOptions($method, $payment),
        ]);
    }

    /**
     * The customer closed the popup without paying.
     */
    public function cancel(): RedirectResponse
    {
        session()->flash('error', trans('razorpay::app.errors.cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Whether the cart has everything an order needs and pays with Razorpay.
     */
    protected function isReadyToPay($cart): bool
    {
        return $cart
            && ! Cart::hasError()
            && $cart->payment?->method === 'razorpay'
            && $cart->billing_address
            && (! $cart->haveStockableItems() || $cart->selected_shipping_rate);
    }

    /**
     * Options passed to Razorpay's checkout popup. Never add a secret here.
     */
    protected function checkoutOptions(Razorpay $method, RazorpayPayment $payment): array
    {
        $billing = $payment->snapshot['billing_address'];

        return [
            'key' => $method->getConfigData('key_id'),
            'order_id' => $payment->razorpay_order_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'name' => core()->getCurrentChannel()->name,
            'description' => trans('razorpay::app.redirect.description'),
            'timeout' => 900,
            'prefill' => [
                'name' => trim($billing['first_name'].' '.$billing['last_name']),
                'email' => $billing['email'],
                'contact' => $billing['phone'],
            ],
        ];
    }

    /**
     * Return to checkout with an error message.
     */
    protected function backToCheckout(string $message): RedirectResponse
    {
        session()->flash('error', trans($message));

        return redirect()->route('shop.checkout.onepage.index');
    }
}
