<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    private const PUBLIC_KEY_BYTES = 65;

    private const PRIVATE_KEY_BYTES = 32;

    public function notify(string $title, string $body, ?string $url = null): void
    {
        $subscriptions = PushSubscription::all();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $publicKey = self::normalizeKey(config('services.webpush.public_key'));
        $privateKey = self::normalizeKey(config('services.webpush.private_key'));

        if ($publicKey === null || $privateKey === null) {
            Log::warning('WebPushService: VAPID keys are not configured; skipping notification.');

            return;
        }

        if (! self::isValidKey($publicKey, self::PUBLIC_KEY_BYTES)) {
            Log::warning('WebPushService: VAPID_PUBLIC_KEY is not a valid base64url-encoded P-256 public key (65 bytes when decoded); skipping notification.');

            return;
        }

        if (! self::isValidKey($privateKey, self::PRIVATE_KEY_BYTES)) {
            Log::warning('WebPushService: VAPID_PRIVATE_KEY is not a valid base64url-encoded P-256 private key (32 bytes when decoded); skipping notification.');

            return;
        }

        $auth = [
            'VAPID' => [
                'subject' => config('services.webpush.subject'),
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ];

        $webPush = new WebPush($auth);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);

        foreach ($subscriptions as $sub) {
            $webPush->queueNotification(
                Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'keys' => [
                        'p256dh' => $sub->p256dh,
                        'auth' => $sub->auth,
                    ],
                ]),
                $payload,
            );
        }

        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint', $report->getEndpoint())
                    ->delete();
            }
        }
    }

    /**
     * Normalize a VAPID key from the environment into canonical base64url form.
     *
     * Handles common transcription issues: surrounding whitespace, wrapping
     * quotes, standard base64 alphabet (`+`, `/`), and padding (`=`).
     */
    public static function normalizeKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $trimmed = trim($key);
        $trimmed = trim($trimmed, "\"'");

        if ($trimmed === '') {
            return null;
        }

        $trimmed = strtr($trimmed, '+/', '-_');
        $trimmed = rtrim($trimmed, '=');

        return $trimmed;
    }

    private static function isValidKey(string $base64UrlKey, int $expectedBytes): bool
    {
        $decoded = base64_decode(strtr($base64UrlKey, '-_', '+/'), true);

        return $decoded !== false && strlen($decoded) === $expectedBytes;
    }
}
