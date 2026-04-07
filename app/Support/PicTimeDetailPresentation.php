<?php

namespace App\Support;

use Illuminate\Support\Collection;

class PicTimeDetailPresentation
{
    public function __construct(
        private readonly object $record,
        private readonly ?string $bodyHtml = null,
        private readonly string $context = 'story',
    ) {
    }

    public static function for(object $record, ?string $bodyHtml = null, string $context = 'story'): self
    {
        return new self($record, $bodyHtml, $context);
    }

    public function mode(): string
    {
        return method_exists($this->record, 'picTimeDetailMode')
            ? $this->record->picTimeDetailMode()
            : 'standard';
    }

    public function galleryMedia(): Collection
    {
        if (! method_exists($this->record, 'media')) {
            return collect();
        }

        $media = $this->record->relationLoaded('media')
            ? $this->record->media
            : $this->record->media()->get();

        return collect($media)
            ->filter(fn ($media) => filled($media->path ?? null))
            ->values();
    }

    public function heroMedia(): mixed
    {
        return $this->record->heroMedia ?? $this->galleryMedia()->first();
    }

    public function nativeGallery(): Collection
    {
        $heroMedia = $this->heroMedia();
        $heroId = $heroMedia?->id ?? null;

        return $this->galleryMedia()
            ->reject(fn ($media) => $heroId !== null && ($media->id ?? null) === $heroId)
            ->values();
    }

    public function showNativeGallery(): bool
    {
        if (! method_exists($this->record, 'isPicTimeSource') || ! $this->record->isPicTimeSource()) {
            return true;
        }

        return in_array($this->mode(), ['rich_local_gallery', 'gallery_first'], true);
    }

    public function showImportedGallery(?string $galleryHtml): bool
    {
        return filled($galleryHtml) && ($this->nativeGallery()->isEmpty() || ! $this->showNativeGallery());
    }

    public function narrativeBlocks(): array
    {
        return method_exists($this->record, 'picTimeNarrativeBlocks')
            ? $this->record->picTimeNarrativeBlocks()
            : [];
    }

    public function visibleNarrativeBlocks(): array
    {
        $blocks = $this->narrativeBlocks();
        $lead = method_exists($this->record, 'summaryText')
            ? $this->record->summaryText()
            : null;

        if ($blocks === [] || blank($lead)) {
            return $blocks;
        }

        $normalize = function (?string $value): string {
            return trim(preg_replace('/\s+/', ' ', strip_tags((string) $value)) ?? '');
        };

        $first = $blocks[0] ?? null;

        if ($normalize($first) !== '' && $normalize($first) === $normalize($lead)) {
            array_shift($blocks);
        }

        return array_values($blocks);
    }

    public function embedMarkup(): ?string
    {
        return method_exists($this->record, 'picTimeEmbedMarkup')
            ? $this->record->picTimeEmbedMarkup()
            : null;
    }

    public function showNarrativeSection(): bool
    {
        return blank($this->bodyHtml) && $this->visibleNarrativeBlocks() !== [];
    }

    public function showEmbedSection(): bool
    {
        return $this->mode() === 'thin_fallback' && filled($this->embedMarkup());
    }

    public function showExternalFallback(): bool
    {
        return $this->mode() === 'thin_fallback'
            && method_exists($this->record, 'needsExternalGalleryFallback')
            && $this->record->needsExternalGalleryFallback();
    }

    public function embedTitle(): string
    {
        return 'Gallery Preview';
    }

    public function embedCopy(): string
    {
        return 'You can view the full gallery here while this page stays short for now.';
    }

    public function fallbackEyebrow(): string
    {
        return 'Full Gallery';
    }

    public function fallbackTitle(): string
    {
        return 'Open the full gallery.';
    }

    public function fallbackCopy(): string
    {
        return 'If you want to see the full set of photos, you can open the full gallery in a new tab.';
    }

    public function fallbackLabel(): string
    {
        return 'Open Full Gallery';
    }
}
