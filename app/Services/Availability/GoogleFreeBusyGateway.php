<?php

namespace App\Services\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads Google Calendar free/busy ranges and nothing else.
 *
 * The token is requested with https://www.googleapis.com/auth/calendar.freebusy
 * only, and the only Calendar call is freeBusy.query.
 */
class GoogleFreeBusyGateway
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const FREE_BUSY_URL = 'https://www.googleapis.com/calendar/v3/freeBusy';

    private const FREE_BUSY_SCOPE = 'https://www.googleapis.com/auth/calendar.freebusy';

    public function configured(): bool
    {
        return $this->serviceAccount() !== null || $this->oauthConfigured();
    }

    /**
     * @param  list<string>  $calendarIds
     * @return array<string, list<BusyInterval>>
     */
    public function busyRanges(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, array $calendarIds): array
    {
        $calendarIds = array_values(array_unique(array_filter($calendarIds, fn (string $id): bool => $id !== '')));

        if ($calendarIds === []) {
            return [];
        }

        $response = $this->http()
            ->withToken($this->accessToken())
            ->acceptJson()
            ->post(self::FREE_BUSY_URL, [
                'timeMin' => $rangeStart->toRfc3339String(),
                'timeMax' => $rangeEnd->toRfc3339String(),
                'timeZone' => $rangeStart->getTimezone()->getName(),
                'items' => array_map(fn (string $id): array => ['id' => $id], $calendarIds),
            ]);

        if (! $response->successful()) {
            Log::warning('Availability free/busy request failed.', ['status' => $response->status()]);

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        return $this->rangesFromResponse($response, $calendarIds);
    }

    /**
     * @param  list<string>  $calendarIds
     * @return array<string, list<BusyInterval>>
     */
    private function rangesFromResponse(Response $response, array $calendarIds): array
    {
        $calendars = $response->json('calendars');

        if (! is_array($calendars)) {
            Log::warning('Availability free/busy response was missing calendars.');

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        $ranges = [];

        foreach ($calendarIds as $calendarId) {
            $entry = $calendars[$calendarId] ?? null;
            $errors = is_array($entry) ? ($entry['errors'] ?? []) : null;

            if (! is_array($entry) || (is_array($errors) && $errors !== [])) {
                Log::warning('Availability free/busy could not be read for a calendar.', [
                    'reason' => is_array($errors) && is_array($errors[0] ?? null) ? ($errors[0]['reason'] ?? null) : null,
                ]);

                throw new AvailabilityLookupFailed('Availability could not be checked.');
            }

            $busy = $entry['busy'] ?? [];

            if (! is_array($busy)) {
                throw new AvailabilityLookupFailed('Availability could not be checked.');
            }

            $ranges[$calendarId] = [];

            foreach ($busy as $row) {
                if (! is_array($row) || ! is_string($row['start'] ?? null) || ! is_string($row['end'] ?? null)) {
                    throw new AvailabilityLookupFailed('Availability could not be checked.');
                }

                try {
                    $start = CarbonImmutable::parse($row['start']);
                    $end = CarbonImmutable::parse($row['end']);
                } catch (\Throwable) {
                    throw new AvailabilityLookupFailed('Availability could not be checked.');
                }

                $ranges[$calendarId][] = new BusyInterval($start, $end);
            }
        }

        return $ranges;
    }

    private function accessToken(): string
    {
        $account = $this->serviceAccount();

        if ($account !== null) {
            return $this->cachedToken(
                'sa:'.hash('sha256', $account['client_email'].$account['private_key']),
                fn (): array => $this->requestServiceAccountToken($account),
            );
        }

        if ($this->oauthConfigured()) {
            return $this->cachedToken(
                'oauth:'.hash('sha256', (string) config('availability.google.client_id').(string) config('availability.google.refresh_token')),
                fn (): array => $this->requestRefreshToken(),
            );
        }

        throw new AvailabilityLookupFailed('Availability is not configured.');
    }

    /**
     * @param  callable(): array{access_token: string, expires_in: int}  $fetch
     */
    private function cachedToken(string $suffix, callable $fetch): string
    {
        $key = 'availability:google-token:'.$suffix;
        $cached = Cache::get($key);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $fresh = $fetch();
        Cache::put($key, $fresh['access_token'], max(60, $fresh['expires_in'] - 120));

        return $fresh['access_token'];
    }

    /**
     * @param  array{client_email: string, private_key: string}  $account
     * @return array{access_token: string, expires_in: int}
     */
    private function requestServiceAccountToken(array $account): array
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $this->serviceAccountAssertion($account['client_email'], $account['private_key']),
        ]);

        return $this->tokenFromResponse($response);
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    private function requestRefreshToken(): array
    {
        $response = $this->http()->asForm()->post(self::TOKEN_URL, [
            'grant_type' => 'refresh_token',
            'client_id' => config('availability.google.client_id'),
            'client_secret' => config('availability.google.client_secret'),
            'refresh_token' => config('availability.google.refresh_token'),
        ]);

        return $this->tokenFromResponse($response);
    }

    /**
     * @return array{access_token: string, expires_in: int}
     */
    private function tokenFromResponse(Response $response): array
    {
        if (! $response->successful()) {
            Log::warning('Availability Google token request failed.', ['status' => $response->status()]);

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            Log::warning('Availability Google token response was missing an access token.');

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        return [
            'access_token' => $token,
            'expires_in' => (int) $response->json('expires_in', 3600),
        ];
    }

    private function serviceAccountAssertion(string $email, string $privateKey): string
    {
        $now = time();
        $header = $this->base64Url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $claims = $this->base64Url((string) json_encode([
            'iss' => $email,
            'scope' => self::FREE_BUSY_SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        $key = openssl_pkey_get_private($privateKey);

        if ($key === false || openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256) !== true) {
            Log::warning('Availability service account key could not be used.');

            throw new AvailabilityLookupFailed('Availability could not be checked.');
        }

        return $unsigned.'.'.$this->base64Url($signature);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function serviceAccount(): ?array
    {
        return once(function (): ?array {
            $raw = trim((string) config('availability.google.service_account_json'));

            if ($raw === '') {
                return null;
            }

            try {
                if (! str_starts_with($raw, '{')) {
                    if (! is_file($raw) || ! is_readable($raw)) {
                        Log::warning('Availability service account could not be read.');

                        return null;
                    }

                    $raw = (string) file_get_contents($raw);
                }

                $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                Log::warning('Availability service account could not be read.');

                return null;
            }

            $email = is_array($decoded) ? ($decoded['client_email'] ?? null) : null;
            $privateKey = is_array($decoded) ? ($decoded['private_key'] ?? null) : null;

            if (! is_string($email) || $email === '' || ! is_string($privateKey) || $privateKey === '') {
                Log::warning('Availability service account is missing a client email or private key.');

                return null;
            }

            return [
                'client_email' => $email,
                'private_key' => $privateKey,
            ];
        });
    }

    private function oauthConfigured(): bool
    {
        return filled(config('availability.google.client_id'))
            && filled(config('availability.google.client_secret'))
            && filled(config('availability.google.refresh_token'));
    }

    private function http(): PendingRequest
    {
        return Http::timeout(8)
            ->connectTimeout(3)
            ->retry(2, 200, function (\Throwable $exception): bool {
                return $exception instanceof ConnectionException
                    || ($exception instanceof RequestException && $exception->response->serverError());
            }, throw: false);
    }
}
