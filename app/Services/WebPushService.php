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

    /**
     * Send a push notification to every stored subscription.
     *
     * Returns a summary so callers and diagnostics can tell what happened.
     * When delivery is skipped, `skipped` holds the reason; otherwise it is
     * null and `sent`/`failed` describe the per-subscription outcome.
     *
     * @return array{skipped: string|null, sent: int, failed: int, total: int}
     */
    public function notify(string $title, string $body, ?string $url = null): array
    {
        $subscriptions = PushSubscription::all();
        $total = $subscriptions->count();

        if ($subscriptions->isEmpty()) {
            return self::result('no_subscriptions', 0, 0, 0);
        }

        $publicKey = self::normalizeKey(config('services.webpush.public_key'));
        $privateKey = self::normalizeKey(config('services.webpush.private_key'));

        if ($publicKey === null || $privateKey === null) {
            Log::warning('WebPushService: VAPID keys are not configured; skipping notification.');

            return self::result('missing_keys', 0, 0, $total);
        }

        if (! self::isValidKey($publicKey, self::PUBLIC_KEY_BYTES)) {
            Log::warning('WebPushService: VAPID_PUBLIC_KEY is not a valid base64url-encoded P-256 public key (65 bytes when decoded); skipping notification.');

            return self::result('invalid_public_key', 0, 0, $total);
        }

        if (! self::isValidKey($privateKey, self::PRIVATE_KEY_BYTES)) {
            Log::warning('WebPushService: VAPID_PRIVATE_KEY is not a valid base64url-encoded P-256 private key (32 bytes when decoded); skipping notification.');

            return self::result('invalid_private_key', 0, 0, $total);
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

        $sent = 0;
        $failed = 0;

        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;

                continue;
            }

            $failed++;

            if ($report->isSubscriptionExpired()) {
                PushSubscription::query()
                    ->where('endpoint', $report->getEndpoint())
                    ->delete();

                continue;
            }

            // A live subscription the push service rejected (bad VAPID, payload,
            // rate limit, etc.). Without this the failure is invisible and the
            // notification just silently never arrives.
            Log::warning('WebPushService: push delivery failed.', [
                'endpoint' => $report->getEndpoint(),
                'status' => $report->getResponse()?->getStatusCode(),
                'reason' => $report->getReason(),
            ]);
        }

        return self::result(null, $sent, $failed, $total);
    }

    /**
     * @return array{skipped: string|null, sent: int, failed: int, total: int}
     */
    private static function result(?string $skipped, int $sent, int $failed, int $total): array
    {
        return [
            'skipped' => $skipped,
            'sent' => $sent,
            'failed' => $failed,
            'total' => $total,
        ];
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
