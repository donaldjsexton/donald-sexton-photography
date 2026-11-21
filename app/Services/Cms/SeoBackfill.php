<?php

namespace App\Services\Cms;

use App\Models\JournalPost;
use App\Models\Page;
use App\Models\Venue;
use App\Models\WeddingStory;
use App\Services\Seo\GeneratedSeo;
use App\Services\Seo\JournalPostSeoGenerator;
use App\Services\Seo\PageSeoGenerator;
use App\Services\Seo\VenueSeoGenerator;
use App\Services\Seo\WeddingStorySeoGenerator;
use Illuminate\Database\Eloquent\Model;

/**
 * Runs the same generators as the seo:generate-* commands for one record and
 * writes only blank fields unless an overwrite is requested.
 */
class SeoBackfill
{
    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return ['page', 'journal_post', 'venue', 'wedding_story'];
    }

    /**
     * @return class-string<Model>
     */
    public static function modelFor(string $type): string
    {
        return match ($type) {
            'page' => Page::class,
            'journal_post' => JournalPost::class,
            'venue' => Venue::class,
            'wedding_story' => WeddingStory::class,
        };
    }

    /**
     * @return array{generated: bool, updated: list<string>, seo_title: ?string, seo_description: ?string}
     */
    public function run(Model $model, bool $overwrite): array
    {
        $result = $this->generate($model);

        if (! $result instanceof GeneratedSeo) {
            return ['generated' => false, 'updated' => [], 'seo_title' => $model->seo_title, 'seo_description' => $model->seo_description];
        }

        $updates = [];

        if ($overwrite || $this->isBlank($model->seo_title)) {
            $updates['seo_title'] = $result->title;
        }

        if ($overwrite || $this->isBlank($model->seo_description)) {
            $updates['seo_description'] = $result->description;
        }

        if ($updates !== []) {
            $model->forceFill($updates);
            $model instanceof Venue ? $model->saveQuietly() : $model->save();
        }

        return [
            'generated' => true,
            'updated' => array_keys($updates),
            'seo_title' => $model->seo_title,
            'seo_description' => $model->seo_description,
        ];
    }

    private function generate(Model $model): ?GeneratedSeo
    {
        return match (true) {
            $model instanceof Page => app(PageSeoGenerator::class)->generate($model),
            $model instanceof JournalPost => app(JournalPostSeoGenerator::class)->generate($model),
            $model instanceof Venue => app(VenueSeoGenerator::class)->generate($model),
            $model instanceof WeddingStory => app(WeddingStorySeoGenerator::class)->generate($model),
            default => null,
        };
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
