<?php

namespace App\Observers;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\WeddingStory;
use App\Services\IndexNow;
use Illuminate\Database\Eloquent\Model;

class IndexNowObserver
{
    public function __construct(private readonly IndexNow $indexNow) {}

    public function saved(Model $model): void
    {
        if (! $this->isPublished($model)) {
            return;
        }

        $url = $this->urlFor($model);

        if ($url === null) {
            return;
        }

        $this->indexNow->submit([$url]);
    }

    private function isPublished(Model $model): bool
    {
        if (($model->status ?? null) !== 'published') {
            return false;
        }

        $publishedAt = $model->published_at ?? null;

        return $publishedAt === null || $publishedAt->lessThanOrEqualTo(now());
    }

    private function urlFor(Model $model): ?string
    {
        return match (true) {
            $model instanceof JournalPost => route('journal.show', $model->slug),
            $model instanceof WeddingStory => route('weddings.show', $model->slug),
            $model instanceof Page => $this->pageUrl($model),
            default => null,
        };
    }

    private function pageUrl(Page $page): ?string
    {
        return match ($page->slug) {
            'about' => route('pages.about'),
            default => $page->template === 'location'
                ? route('pages.location', $page->slug)
                : null,
        };
    }
}
