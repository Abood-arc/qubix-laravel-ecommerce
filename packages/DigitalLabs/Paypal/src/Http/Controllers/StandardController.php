<?php

namespace DigitalLabs\Paypal\Http\Controllers;

use DigitalLabs\Checkout\Facades\Cart;
use DigitalLabs\Paypal\Helpers\Ipn;
use DigitalLabs\Sales\Repositories\OrderRepository;
use DigitalLabs\Sales\Transformers\OrderResource;

class StandardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected OrderRepository $orderRepository,
        protected Ipn $ipnHelper
    ) {}

    /**
     * Redirects to the paypal.
     *
     * @return \Illuminate\View\View
     */
    public function redirect()
    {
        return view('paypal::standard-redirect');
    }

    /**
     * Cancel payment from paypal.
     *
     * @return \Illuminate\Http\Response
     */
    public function cancel()
    {
        session()->flash('error', trans('shop::app.checkout.cart.paypal-payment-cancelled'));

        return redirect()->route('shop.checkout.cart.index');
    }

    /**
     * Success payment.
     *
     * @return \Illuminate\Http\Response
     */
    public function success()
    {
        $cart = Cart::getCart();

        $data = (new OrderResource($cart))->jsonSerialize();

        $order = $this->orderRepository->create($data);

        Cart::deActivateCart();

        session()->flash('order_id', $order->id);

        return redirect()->route('shop.checkout.onepage.success');
    }

    /**
     * Paypal IPN listener.
     *
     * @return \Illuminate\Http\Response
     */
    public function ipn()
    {
        $this->ipnHelper->processIpn(request()->all());
    }
}
