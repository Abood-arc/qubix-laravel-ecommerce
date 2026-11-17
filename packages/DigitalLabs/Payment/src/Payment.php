<?php

namespace DigitalLabs\Payment;

use Illuminate\Support\Facades\Config;

class Payment
{
    /**
     * Returns all supported payment methods
     *
     * @param  bool  $includeDisabled  Also list active methods that cannot take this order, greyed out with their reason (Shop checkout only).
     * @return array
     */
    public function getSupportedPaymentMethods(bool $includeDisabled = false)
    {
        return [
            'payment_methods' => $this->getPaymentMethods($includeDisabled),
        ];
    }

    /**
     * Returns all supported payment methods
     *
     * @param  bool  $includeDisabled  Also list active methods that cannot take this order, greyed out with their reason (Shop checkout only).
     * @return array
     */
    public function getPaymentMethods(bool $includeDisabled = false)
    {
        $paymentMethods = [];

        foreach (Config::get('payment_methods') as $paymentMethodConfig) {
            $paymentMethod = app($paymentMethodConfig['class']);

            if ($paymentMethod->isAvailable()) {
                $paymentMethods[] = $this->describe($paymentMethod) + ['disabled' => false];

                continue;
            }

            if (
                $includeDisabled
                && ($reason = $paymentMethod->getUnavailableReason()) !== null
            ) {
                $paymentMethods[] = $this->describe($paymentMethod) + ['disabled' => true, 'reason' => $reason];
            }
        }

        usort($paymentMethods, function ($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }

            return ($a['sort'] < $b['sort']) ? -1 : 1;
        });

        return $paymentMethods;
    }

    /**
     * The fields a payment method list shows for one method.
     *
     * @param  \DigitalLabs\Payment\Payment\Payment  $paymentMethod
     */
    protected function describe($paymentMethod): array
    {
        return [
            'method' => $paymentMethod->getCode(),
            'method_title' => $paymentMethod->getTitle(),
            'description' => $paymentMethod->getDescription(),
            'sort' => $paymentMethod->getSortOrder(),
            'image' => $paymentMethod->getImage(),
        ];
    }

    /**
     * Returns payment redirect url if have any
     *
     * @param  \DigitalLabs\Checkout\Contracts\Cart  $cart
     * @return string
     */
    public function getRedirectUrl($cart)
    {
        $payment = app(Config::get('payment_methods.'.$cart->payment->method.'.class'));

        return $payment->getRedirectUrl();
    }

    /**
     * Returns payment method additional information
     *
     * @param  string  $code
     * @return array
     */
    public static function getAdditionalDetails($code)
    {
        $paymentMethodClass = app(Config::get('payment_methods.'.$code.'.class'));

        return $paymentMethodClass->getAdditionalDetails();
    }
}
