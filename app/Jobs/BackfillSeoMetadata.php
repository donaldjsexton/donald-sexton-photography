<?php

namespace App\Jobs;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Services\Seo\GeneratedSeo;
use App\Services\Seo\JournalPostSeoGenerator;
use App\Services\Seo\PageSeoGenerator;
use App\Services\Seo\VenueSeoGenerator;
use App\Services\Seo\WeddingStorySeoGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate and persist any missing SEO title/description for a single content
 * record. Only blank fields are touched, so hand-edited metadata is preserved.
 * Saved quietly to avoid re-triggering the observer that dispatched this job.
 */
class BackfillSeoMetadata implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly Model $model) {}

    public function uniqueId(): string
    {
        return $this->model::class.':'.$this->model->getKey();
    }

    public function handle(
        PageSeoGenerator $pageGenerator,
        WeddingStorySeoGenerator $storyGenerator,
        JournalPostSeoGenerator $postGenerator,
        VenueSeoGenerator $venueGenerator,
    ): void {
        $model = $this->model->fresh();

        if ($model === null) {
            return;
        }

        if (! $this->isBlank($model->seo_title) && ! $this->isBlank($model->seo_description)) {
            return;
        }

        $result = match (true) {
            $model instanceof Page => $pageGenerator->generate($model),
            $model instanceof WeddingStory => $storyGenerator->generate($model),
            $model instanceof JournalPost => $postGenerator->generate($model),
            $model instanceof Venue => $venueGenerator->generate($model),
            default => null,
        };

        if (! $result instanceof GeneratedSeo) {
            return;
        }

        $updates = [];

        if ($this->isBlank($model->seo_title)) {
            $updates['seo_title'] = $result->title;
        }

        if ($this->isBlank($model->seo_description)) {
            $updates['seo_description'] = $result->description;
        }

        if ($updates === []) {
            return;
        }

        $model->forceFill($updates)->saveQuietly();
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
