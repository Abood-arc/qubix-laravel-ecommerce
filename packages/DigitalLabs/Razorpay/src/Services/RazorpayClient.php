<?php

namespace DigitalLabs\Razorpay\Services;

use DigitalLabs\Razorpay\Exceptions\RazorpayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class RazorpayClient
{
    protected const BASE_URL = 'https://api.razorpay.com/v1';

    /**
     * Late bank authorisations after this window are returned by Razorpay.
     */
    protected const CAPTURE_WINDOW_MINUTES = 15;

    /**
     * Create a Razorpay order.
     */
    public function createOrder(int $amount, string $currency, string $receipt, array $notes): array
    {
        return $this->send('POST', 'orders', [
            'amount' => $amount,
            'currency' => $currency,
            'receipt' => $receipt,
            'notes' => $notes,
            'payment' => [
                'capture' => 'automatic',
                'capture_options' => [
                    'automatic_expiry_period' => self::CAPTURE_WINDOW_MINUTES,
                    'manual_expiry_period' => self::CAPTURE_WINDOW_MINUTES,
                    'refund_speed' => 'normal',
                ],
            ],
        ]);
    }

    /**
     * Fetch a payment.
     */
    public function fetchPayment(string $paymentId): array
    {
        return $this->send('GET', 'payments/'.rawurlencode($paymentId));
    }

    /**
     * Capture an authorised payment.
     */
    public function capturePayment(string $paymentId, int $amount, string $currency): array
    {
        return $this->send('POST', 'payments/'.rawurlencode($paymentId).'/capture', [
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    /**
     * Refund a captured payment. Razorpay treats the receipt as the refund's
     * idempotency key, so a repeated key is rejected instead of refunding twice.
     */
    public function refundPayment(string $paymentId, int $amount, string $idempotencyKey): array
    {
        return $this->send('POST', 'payments/'.rawurlencode($paymentId).'/refund', [
            'amount' => $amount,
            'receipt' => $idempotencyKey,
        ], ['X-Refund-Idempotency' => $idempotencyKey]);
    }

    /**
     * Check the signature the checkout popup returned.
     */
    public function isPaymentSignatureValid(string $orderId, string $paymentId, string $signature): bool
    {
        return $this->isSignatureValid($orderId.'|'.$paymentId, $signature, 'key_secret');
    }

    /**
     * Check the signature of a webhook's raw body.
     */
    public function isWebhookSignatureValid(string $body, string $signature): bool
    {
        return $this->isSignatureValid($body, $signature, 'webhook_secret');
    }

    /**
     * Compare a signature. A blank secret never validates.
     */
    protected function isSignatureValid(string $payload, string $signature, string $secretField): bool
    {
        $secret = (string) $this->setting($secretField);

        if ($secret === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    /**
     * Send a request to the Razorpay API.
     */
    protected function send(string $method, string $path, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::baseUrl(self::BASE_URL)
                ->withBasicAuth((string) $this->setting('key_id'), (string) $this->setting('key_secret'))
                ->withHeaders($headers)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(15)
                ->send($method, $path, $data ? ['json' => $data] : []);
        } catch (ConnectionException $exception) {
            throw new RazorpayException('Razorpay could not be reached.', 0, $exception);
        }

        if ($response->failed()) {
            throw new RazorpayException('Razorpay API error '.$response->status().': '.$response->json('error.code'));
        }

        return $response->json();
    }

    /**
     * Read a Razorpay setting.
     */
    protected function setting(string $field): mixed
    {
        return core()->getConfigData('sales.payment_methods.razorpay.'.$field);
    }
}
