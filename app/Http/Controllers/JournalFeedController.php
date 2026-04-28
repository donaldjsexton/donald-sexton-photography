<?php

namespace App\Http\Controllers;

use App\Models\JournalPost;
use App\Models\WeddingStory;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class JournalFeedController extends Controller
{
    private const FEED_LIMIT = 25;

    public function __invoke(): Response
    {
        $posts = JournalPost::published()
            ->with('heroMedia')
            ->whereNotIn('slug', WeddingStory::published()->select('slug'))
            ->orderByRaw('CASE WHEN published_at IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(self::FEED_LIMIT)
            ->get();

        $latestUpdate = $posts
            ->map(fn (JournalPost $post) => $post->updated_at ?? $post->published_at)
            ->filter()
            ->sortDesc()
            ->first();

        return response()
            ->view('feeds.journal', [
                'posts' => $posts,
                'updated' => $latestUpdate?->toAtomString() ?? now()->toAtomString(),
                'siteName' => (string) config('app.name', 'Donald Sexton Photography'),
            ])
            ->header('Content-Type', 'application/atom+xml; charset=UTF-8');
    }

    public static function summarize(JournalPost $post, int $words = 60): string
    {
        $value = trim((string) (
            $post->seo_description
            ?: $post->excerpt
            ?: $post->summaryText($words)
            ?: ''
        ));

        if ($value === '') {
            return '';
        }

        $value = preg_replace('/\s+/u', ' ', strip_tags($value)) ?? $value;

        return Str::words($value, $words);
    }
}
