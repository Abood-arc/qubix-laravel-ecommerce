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

    /**
     * Followed by a hash of the API user's email, so a new user never reuses an old token.
     */
    protected const TOKEN_CACHE_KEY = 'shiprocket:token:';

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

    /**
     * The order search row's status field, and the values that mean the order was cancelled.
     */
    protected const ORDER_STATUS_FIELD = 'status';

    protected const ORDER_CANCELLED = '/^\s*cancel+ed\s*$/i';

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
            throw $this->refusal($response);
        }

        return ['order_id' => (int) $body['order_id'], 'shipment_id' => (int) $body['shipment_id']];
    }

    public function findOrderByChannelOrderId(string $channelOrderId): ?array
    {
        $response = $this->call('GET', self::PATH_ORDERS, ['search' => $channelOrderId]);

        $rows = $this->data($response)['data'] ?? [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (
                (string) ($row['channel_order_id'] ?? '') !== $channelOrderId
                || preg_match(self::ORDER_CANCELLED, (string) ($row[self::ORDER_STATUS_FIELD] ?? ''))
            ) {
                continue;
            }

            $shipmentId = $row['shipments'][0]['id'] ?? null;

            if (empty($row['id']) || empty($shipmentId)) {
                throw $this->refusal($response);
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
            throw new ShiprocketException($reason ?? $this->fallbackMessage($response));
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
            throw $this->refusal($response);
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
            throw $this->refusal($response);
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
            Cache::forget($this->tokenCacheKey());

            $response = $this->send($method, $path, $data, $this->token());
        }

        return $response;
    }

    /**
     * The cached API token, logging in when there is none.
     */
    protected function token(): string
    {
        $token = Cache::get($this->tokenCacheKey());

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $response = $this->send('POST', self::PATH_LOGIN, [
            'email' => Settings::email(),
            'password' => Settings::password(),
        ]);

        $token = $this->data($response)['token'] ?? null;

        if (! is_string($token) || $token === '') {
            throw $this->refusal($response);
        }

        Cache::put($this->tokenCacheKey(), $token, now()->addDays(self::TOKEN_DAYS));

        return $token;
    }

    protected function tokenCacheKey(): string
    {
        return self::TOKEN_CACHE_KEY.sha1(Settings::email());
    }

    /**
     * Send one request. No answer, a failure on Shiprocket's side, or a rate limit (the request
     * was not carried out, so a later retry is right) leaves the outcome unknown.
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
            throw new ShiprocketUnknownOutcomeException('Shiprocket error (no response)', 0, $exception);
        }

        if ($response->serverError() || $response->status() === 429) {
            throw new ShiprocketUnknownOutcomeException($this->fallbackMessage($response));
        }

        return $response;
    }

    /**
     * The decoded body of a successful response; a refusal throws with Shiprocket's reason.
     */
    protected function data(Response $response): array
    {
        if ($response->failed()) {
            throw $this->refusal($response);
        }

        return (array) $response->json();
    }

    /**
     * A refusal with Shiprocket's reason, or the HTTP status when it gave none.
     */
    protected function refusal(Response $response): ShiprocketException
    {
        return new ShiprocketException($this->message($response) ?? $this->fallbackMessage($response));
    }

    /**
     * Neutral text for logs and admin emails, no translation needed.
     */
    protected function fallbackMessage(Response $response): string
    {
        return 'Shiprocket error (HTTP '.$response->status().')';
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
