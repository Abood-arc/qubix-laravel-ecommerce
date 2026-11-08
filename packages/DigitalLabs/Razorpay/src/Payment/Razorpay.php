<?php

namespace DigitalLabs\Razorpay\Payment;

use DigitalLabs\Payment\Payment\Payment;
use Illuminate\Support\Facades\Storage;

class Razorpay extends Payment
{
    /**
     * Payment method code.
     *
     * @var string
     */
    protected $code = 'razorpay';

    /**
     * Get redirect url.
     *
     * @return string
     */
    public function getRedirectUrl()
    {
        return route('razorpay.redirect');
    }

    /**
     * Is available.
     *
     * @return bool
     */
    public function isAvailable()
    {
        $cart = $this->getCart();

        return $cart
            && $this->getConfigData('active')
            && $this->getConfigData('key_id')
            && $this->getConfigData('key_secret')
            && $this->getConfigData('webhook_secret')
            && $cart->cart_currency_code === 'INR'
            && (float) $cart->grand_total >= 1
            && $this->isAllowedCustomer();
    }

    /**
     * Get payment method image.
     *
     * @return string
     */
    public function getImage()
    {
        $url = $this->getConfigData('image');

        return $url ? Storage::url($url) : qubix_asset('images/money-transfer.png', 'shop');
    }

    /**
     * While a test email is set, only that logged-in customer may pay.
     */
    protected function isAllowedCustomer(): bool
    {
        $testEmail = $this->getConfigData('test_customer_email');

        if (empty($testEmail)) {
            return true;
        }

        $customerEmail = auth()->guard('customer')->user()?->email;

        return $customerEmail && strcasecmp($customerEmail, $testEmail) === 0;
    }
}
