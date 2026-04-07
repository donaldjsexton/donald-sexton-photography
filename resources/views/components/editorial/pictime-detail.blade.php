@props([
    'record',
    'presentation' => null,
    'bodyHtml' => null,
    'context' => 'story',
    'returnHref' => null,
    'returnLabel' => null,
])

@php
    $pictime = $presentation instanceof \App\Support\PicTimeDetailPresentation
        ? $presentation
        : \App\Support\PicTimeDetailPresentation::for($record, $bodyHtml, $context);
@endphp

@if ($pictime->showNarrativeSection())
    <x-editorial.reading-section>
        @foreach ($pictime->visibleNarrativeBlocks() as $paragraph)
            <p>{{ $paragraph }}</p>
        @endforeach
    </x-editorial.reading-section>
@endif

@if ($pictime->showEmbedSection())
    <x-editorial.pictime-embed-fallback
        eyebrow="Gallery"
        :title="$pictime->embedTitle()"
        :copy="$pictime->embedCopy()"
        :embed="$pictime->embedMarkup()"
    />
@endif

@if ($pictime->showExternalFallback())
    <x-editorial.external-gallery-fallback
        :eyebrow="$pictime->fallbackEyebrow()"
        :title="$pictime->fallbackTitle()"
        :copy="$pictime->fallbackCopy()"
        :href="method_exists($record, 'externalGalleryUrl') ? $record->externalGalleryUrl() : null"
        :label="$pictime->fallbackLabel()"
        :secondary-href="$returnHref"
        :secondary-label="$returnLabel"
    />
@endif
