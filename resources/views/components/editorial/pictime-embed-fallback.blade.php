@props([
    'eyebrow' => 'Gallery',
    'title' => 'Gallery Preview',
    'copy' => 'You can view the gallery here, or open the full set in a new tab.',
    'embed' => null,
])

@if ($embed)
    <section class="section">
        <div class="page-shell--wide page-stack">
            <x-editorial.section-heading
                :eyebrow="$eyebrow"
                :title="$title"
                :copy="$copy"
            />

            <div class="pictime-embed-fallback rich-text" data-reveal>
                {!! $embed !!}
            </div>
        </div>
    </section>
@endif
