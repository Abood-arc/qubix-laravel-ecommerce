<?php

namespace DigitalLabs\Shiprocket\Services;

use DigitalLabs\Shiprocket\Contracts\ShiprocketApi;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketException;
use DigitalLabs\Shiprocket\Exceptions\ShiprocketUnknownOutcomeException;
use DigitalLabs\Shiprocket\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Shiprocket's external API.
 *
 * Every path and field name Shiprocket defines lives in the constants and the one method per
 * call below, so they can be corrected in one place after the live read-only checks.
 */
class ShiprocketClient implements ShiprocketApi
{
    protected const BASE_URL = 'https://apiv2.shiprocket.in/v1/external/';

    protected const PATH_LOGIN = 'auth/login';

    protected const PATH_SERVICEABILITY = 'courier/serviceability/';

    protected const PATH_CREATE_ORDER = 'orders/create/adhoc';

    protected const PATH_ORDERS = 'orders';

    protected const PATH_ASSIGN_AWB = 'courier/assign/awb';

    protected const PATH_SHIPMENT = 'shipments/';

    protected const PATH_PICKUP = 'courier/generate/pickup';

    protected const PATH_LABEL = 'courier/generate/label';

    protected const PATH_CANCEL = 'orders/cancel';

    protected const TOKEN_CACHE_KEY = 'shiprocket:token';

    /**
     * Shiprocket tokens last 10 days; renew a day early.
     */
    protected const TOKEN_DAYS = 9;

    /**
     * Sized so one booking (login + four calls, one 401 retry) stays inside the worker's 90 s retry_after.
     */
    protected const CONNECT_TIMEOUT = 5;

    protected const TIMEOUT = 20;

    /**
     * How Shiprocket words "this was done already" for an AWB and for a pickup.
     */
    protected const AWB_ALREADY_ASSIGNED = '/already\s+(been\s+)?(assigned|generated)/i';

    protected const PICKUP_ALREADY_SCHEDULED = '/already/i';

    public function checkServiceability(string $pickupPin, string $deliveryPin, float $weightKg, bool $cod): array
    {
        $response = $this->call('GET', self::PATH_SERVICEABILITY, [
            'pickup_postcode' => $pickupPin,
            'delivery_postcode' => $deliveryPin,
            'weight' => $weightKg,
            'cod' => $cod ? 1 : 0,
        ]);

        // "No courier" comes back as a 404, or a 422 that is not a validation error.
        if ($response->status() === 404 || ($response->status() === 422 && $response->json('errors') === null)) {
            return ['deliverable' => false, 'cod' => false];
        }

        $companies = $this->data($response)['data']['available_courier_companies'] ?? [];

        $companies = is_array($companies) ? $companies : [];

        return [
            'deliverable' => $companies !== [],
            'cod' => collect($companies)->contains(fn ($company) => (int) ($company['cod'] ?? 0) === 1),
        ];
    }

    public function createOrder(array $payload): array
    {
        $response = $this->call('POST', self::PATH_CREATE_ORDER, $payload);

        $body = $this->data($response);

        if (empty($body['order_id']) || empty($body['shipment_id'])) {
            throw new ShiprocketException($this->message($response) ?? 'Shiprocket did not return an order and shipment id.');
        }

        return ['order_id' => (int) $body['order_id'], 'shipment_id' => (int) $body['shipment_id']];
    }

    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        $rows = $this->data($this->call('GET', self::PATH_ORDERS, ['search' => $channelOrderId]))['data'] ?? [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if ((string) ($row['channel_order_id'] ?? '') !== $channelOrderId) {
                continue;
            }

            $shipmentId = $row['shipments'][0]['id'] ?? null;

            if (empty($row['id']) || empty($shipmentId)) {
                throw new ShiprocketException("Shiprocket order {$channelOrderId} has no shipment.");
            }

            return ['order_id' => (int) $row['id'], 'shipment_id' => (int) $shipmentId];
        }

        return null;
    }

    public function assignAwb(int $shipmentId): array
    {
        $response = $this->call('POST', self::PATH_ASSIGN_AWB, ['shipment_id' => $shipmentId]);

        $error = $response->json('response.data.awb_assign_error');
        $error = is_string($error) && trim($error) !== '' ? trim($error) : null;

        $reason = $error ?? $this->message($response);

        if ($reason !== null && preg_match(self::AWB_ALREADY_ASSIGNED, $reason)) {
            return $this->assignedAwb($shipmentId);
        }

        $data = $this->data($response)['response']['data'] ?? [];

        if (empty($data['awb_code'])) {
            throw new ShiprocketException($reason ?? 'Shiprocket did not assign an AWB.');
        }

        return ['awb_code' => (string) $data['awb_code'], 'courier_name' => (string) ($data['courier_name'] ?? '')];
    }

    public function requestPickup(int $shipmentId): void
    {
        $response = $this->call('POST', self::PATH_PICKUP, ['shipment_id' => [$shipmentId]]);

        if ($response->failed() && preg_match(self::PICKUP_ALREADY_SCHEDULED, (string) $this->message($response))) {
            return;
        }

        $this->data($response);
    }

    public function generateLabel(int $shipmentId): string
    {
        $response = $this->call('POST', self::PATH_LABEL, ['shipment_id' => [$shipmentId]]);

        $url = $this->data($response)['label_url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new ShiprocketException($this->message($response) ?? 'Shiprocket did not create the label.');
        }

        return $url;
    }

    public function cancelOrder(int $shiprocketOrderId): void
    {
        $this->data($this->call('POST', self::PATH_CANCEL, ['ids' => [$shiprocketOrderId]]));
    }

    /**
     * The AWB and courier of a shipment whose AWB was assigned earlier.
     *
     * @return array{awb_code: string, courier_name: string}
     */
    protected function assignedAwb(int $shipmentId): array
    {
        $response = $this->call('GET', self::PATH_SHIPMENT.$shipmentId);

        $data = $this->data($response)['data'] ?? [];

        if (empty($data['awb'])) {
            throw new ShiprocketException($this->message($response) ?? 'Shiprocket says an AWB is assigned but did not return it.');
        }

        return ['awb_code' => (string) $data['awb'], 'courier_name' => (string) ($data['courier'] ?? '')];
    }

    /**
     * Send an authenticated request. A rejected token is renewed once and the request retried once.
     */
    protected function call(string $method, string $path, array $data = []): Response
    {
        $response = $this->send($method, $path, $data, $this->token());

        if ($response->status() === 401) {
            Cache::forget(self::TOKEN_CACHE_KEY);

            $response = $this->send($method, $path, $data, $this->token());
        }

        return $response;
    }

    /**
     * The cached API token, logging in when there is none.
     */
    protected function token(): string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = $this->send('POST', self::PATH_LOGIN, [
            'email' => Settings::email(),
            'password' => Settings::password(),
        ]);

        $token = $this->data($response)['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw new ShiprocketException($this->message($response) ?? 'Shiprocket login returned no token.');
        }

        Cache::put(self::TOKEN_CACHE_KEY, $token, now()->addDays(self::TOKEN_DAYS));

        return $token;
    }

    /**
     * Send one request. No answer, or a failure on Shiprocket's side, leaves the outcome unknown.
     */
    protected function send(string $method, string $path, array $data = [], ?string $token = null): Response
    {
        $request = Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::TIMEOUT);

        if ($token !== null) {
            $request->withToken($token);
        }

        try {
            $response = $request->send($method, $path, $method === 'GET' ? ['query' => $data] : ['json' => $data]);
        } catch (ConnectionException $exception) {
            throw new ShiprocketUnknownOutcomeException('Shiprocket could not be reached.', 0, $exception);
        }

        if ($response->serverError()) {
            throw new ShiprocketUnknownOutcomeException('Shiprocket API error '.$response->status().' (outcome unknown).');
        }

        return $response;
    }

    /**
     * The decoded body of a successful response; a refusal throws with Shiprocket's reason.
     */
    protected function data(Response $response): array
    {
        if ($response->failed()) {
            throw new ShiprocketException($this->message($response) ?? 'Shiprocket refused the request (HTTP '.$response->status().').');
        }

        return (array) $response->json();
    }

    /**
     * Shiprocket's own explanation: its message, followed by any validation errors.
     */
    protected function message(Response $response): ?string
    {
        $parts = [];

        $message = $response->json('message');

        if (is_string($message) && trim($message) !== '') {
            $parts[] = trim($message);
        }

        $errors = $response->json('errors');

        foreach (is_array($errors) ? Arr::flatten($errors) : [$errors] as $error) {
            if (is_scalar($error) && trim((string) $error) !== '') {
                $parts[] = trim((string) $error);
            }
        }

        return $parts === [] ? null : implode(' ', $parts);
    }
}
