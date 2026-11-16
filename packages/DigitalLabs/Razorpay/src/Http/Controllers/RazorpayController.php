<?php

namespace DigitalLabs\Razorpay\Http\Controllers;

use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use DigitalLabs\Razorpay\Models\RazorpayPayment;
use DigitalLabs\Razorpay\Payment\Razorpay;
use DigitalLabs\Razorpay\Services\FinalizeResult;
use DigitalLabs\Razorpay\Services\PaymentFinalizer;
use DigitalLabs\Razorpay\Services\RazorpayClient;
use DigitalLabs\Sales\Transformers\OrderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RazorpayController extends Controller
{
    public function __construct(
        protected RazorpayClient $client,
        protected PaymentFinalizer $finalizer,
    ) {}

    /**
     * Freeze the order and open the payment popup.
     *
     * The page is never cached: a browser restoring it (e.g. Chrome reopening a closed tab)
     * must ask again, so a paid cart lands on the cart page instead of reopening Razorpay
     * for an order it has already been paid for.
     */
    public function redirect(): Response|RedirectResponse
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
            $razorpayOrder = $this->client->createOrder($amount, $cart->cart_currency_code, 'cart-'.$cart->id.'-'.Str::random(12), [
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

        return response()
            ->view('razorpay::redirect', [
                'payment' => $payment,
                'checkoutOptions' => $this->checkoutOptions($method, $payment),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
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
     * The popup reports a successful payment.
     */
    public function callback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id' => ['required', 'string', 'max:64'],
            'razorpay_payment_id' => ['required', 'string', 'max:64', 'regex:/^pay_\w+$/'],
            'razorpay_signature' => ['required', 'string', 'max:128'],
        ]);

        $payment = RazorpayPayment::where('razorpay_order_id', $data['razorpay_order_id'])->first();

        if (
            ! $payment
            || ! $this->client->isPaymentSignatureValid($data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])
        ) {
            return $this->returnToCart(trans('razorpay::app.errors.payment-invalid'));
        }

        $result = $this->finalizer->finalize($payment, $data['razorpay_payment_id']);

        return match ($result->status) {
            FinalizeResult::ORDERED => $this->orderPlaced($result),
            FinalizeResult::REFUNDED => $this->returnToCart($this->finalizer->customerMessage($payment->fresh())),
            FinalizeResult::REJECTED => $this->returnToCart(trans('razorpay::app.errors.payment-invalid')),
            default => response()->json(['pending' => true]),
        };
    }

    /**
     * Razorpay reports a paid order, server to server.
     */
    public function webhook(Request $request): JsonResponse
    {
        $body = $request->getContent();

        if (! $this->client->isWebhookSignatureValid($body, (string) $request->header('X-Razorpay-Signature'))) {
            // Usually a webhook secret in .env that differs from the one in Razorpay's dashboard.
            // Razorpay disables a webhook after a day of failures, so this is logged as an error:
            // production records errors only.
            Log::error('Razorpay webhook refused: its signature does not match RAZORPAY_WEBHOOK_SECRET.', [
                'secret_set' => filled(config('razorpay.webhook_secret')),
                'ip' => $request->ip(),
            ]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $event = json_decode($body, true);

        if (data_get($event, 'event') === 'refund.failed') {
            return $this->refundFailed($event);
        }

        if (data_get($event, 'event') !== 'order.paid') {
            Log::warning('Razorpay webhook for an unhandled event.', ['event' => data_get($event, 'event')]);

            return response()->json();
        }

        $razorpayOrderId = data_get($event, 'payload.order.entity.id');

        $razorpayPaymentId = data_get($event, 'payload.payment.entity.id');

        $payment = $razorpayOrderId ? RazorpayPayment::where('razorpay_order_id', $razorpayOrderId)->first() : null;

        if (! $payment || ! $razorpayPaymentId) {
            Log::warning('Razorpay webhook for an unknown order.', ['razorpay_order_id' => $razorpayOrderId]);

            return response()->json();
        }

        $result = $this->finalizer->finalize($payment, $razorpayPaymentId);

        return response()->json([], $result->status === FinalizeResult::PENDING ? 503 : 200);
    }

    /**
     * Whether the cart has everything an order needs and pays with Razorpay.
     */
    protected function isReadyToPay($cart): bool
    {
        return $cart
            && $this->isCustomerAllowed()
            && ! Cart::hasError()
            && $cart->payment?->method === 'razorpay'
            && $cart->billing_address
            && (! $cart->haveStockableItems() || $cart->selected_shipping_rate);
    }

    /**
     * Whether the logged-in customer, if any, may still place orders.
     */
    protected function isCustomerAllowed(): bool
    {
        $customer = auth()->guard('customer')->user();

        return ! $customer || (! $customer->is_suspended && $customer->status);
    }

    /**
     * Options passed to Razorpay's checkout popup. Never add a secret here.
     */
    protected function checkoutOptions(Razorpay $method, RazorpayPayment $payment): array
    {
        $billing = $payment->snapshot['billing_address'];

        return [
            'key' => config('razorpay.key_id'),
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
     * Razorpay reports that a refund it had accepted has failed.
     */
    protected function refundFailed(array $event): JsonResponse
    {
        if ($razorpayPaymentId = data_get($event, 'payload.refund.entity.payment_id')) {
            $this->finalizer->recordRefundFailure($razorpayPaymentId);
        }

        return response()->json();
    }

    /**
     * Send the customer to the success page.
     */
    protected function orderPlaced(FinalizeResult $result): JsonResponse
    {
        session()->flash('order_id', $result->order->id);

        return response()->json(['redirect_url' => route('shop.checkout.onepage.success')]);
    }

    /**
     * Send the customer back to the cart with a message.
     */
    protected function returnToCart(string $message): JsonResponse
    {
        session()->flash('error', $message);

        return response()->json(['redirect_url' => route('shop.checkout.cart.index')]);
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
