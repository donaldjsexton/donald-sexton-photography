<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNow
{
    private const ENDPOINT = 'https://api.indexnow.org/IndexNow';

    /**
     * Notify IndexNow that one or more URLs were created or updated.
     * Returns true when the request reached IndexNow with a 2xx response.
     * Silently no-ops when no key is configured so callers don't have to guard.
     *
     * @param  array<int, string>  $urls
     */
    public function submit(array $urls): bool
    {
        $urls = array_values(array_filter(array_map('trim', $urls), fn ($url) => $url !== ''));

        if ($urls === []) {
            return false;
        }

        $key = trim((string) (SiteSetting::current()->indexnow_key ?? ''));

        if ($key === '') {
            return false;
        }

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: null;

        if (! $host) {
            return false;
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->post(self::ENDPOINT, [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => 'https://'.$host.'/'.$key.'.txt',
                    'urlList' => $urls,
                ]);

            if (! $response->successful()) {
                Log::info('IndexNow submission rejected', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('IndexNow submission error: '.$e->getMessage());

            return false;
        }
    }
}
