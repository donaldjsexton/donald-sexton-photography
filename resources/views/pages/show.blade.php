@extends('layouts.app')

@section('title', $page->seo_title ?: $page->title)
@section('meta_description', $page->seo_description ?: $page->excerpt ?: '')
@section('canonical_url', $page->canonical_url ?: url()->current())

@push('json_ld')
    @if (! empty($breadcrumbs ?? null))
        @php
            $breadcrumbSchema = \App\Support\StructuredData::breadcrumbList(
                collect($breadcrumbs)
                    ->map(fn (array $item) => [
                        'name' => $item['name'],
                        'url' => $item['url'] !== '' ? $item['url'] : url()->current(),
                    ])
                    ->all()
            );
        @endphp
        <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    @if (! empty($personSchema ?? null))
        <script type="application/ld+json">{!! json_encode($personSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
    @php $pageFaqs = $page->structuredFaqs(); @endphp
    @if (! empty($pageFaqs))
        <script type="application/ld+json">{!! json_encode(\App\Support\StructuredData::faqPage($pageFaqs), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endpush

@section('content')
    @if (! empty($breadcrumbs ?? null))
        <x-editorial.breadcrumbs :items="$breadcrumbs" />
    @endif

    <x-editorial.page-hero
        :eyebrow="$eyebrow ?? null"
        :title="$page->title"
        :copy="$page->excerpt"
        :media="$page->heroMedia"
        ratio="portrait"
    />

    @if ($page->blocks->isNotEmpty())
        <x-blocks :blocks="$page->blocks" />
    @elseif ($page->body)
        <x-editorial.reading-section>
            {!! $page->body !!}
        </x-editorial.reading-section>
    @endif

    @php $pageFaqs = $page->structuredFaqs(); @endphp
    @if (! empty($pageFaqs))
        <section class="section" aria-labelledby="page-faqs-heading">
            <div class="page-shell--tight page-stack">
                <x-editorial.section-heading
                    eyebrow="FAQ"
                    title="Questions couples ask."
                />

                <dl class="venue-faqs">
                    @foreach ($pageFaqs as $faq)
                        <div class="venue-faqs__item">
                            <dt class="venue-faqs__question">{{ $faq['question'] }}</dt>
                            <dd class="venue-faqs__answer">{{ $faq['answer'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </section>
    @endif

    @if (! empty($relatedVenues ?? null) && $relatedVenues->isNotEmpty())
        <section class="section" aria-labelledby="location-venues-heading">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Venues"
                    title="Wedding venues in this area."
                />

                <div class="archive-grid">
                    @foreach ($relatedVenues as $venue)
                        @php
                            $venueMeta = collect([$venue->city, $venue->state])->filter()->implode(', ');
                        @endphp
                        <x-editorial.archive-card
                            :title="$venue->name"
                            :href="route('venues.show', $venue->slug)"
                            :meta="$venueMeta ?: null"
                            :copy="$venue->summary"
                            :media="$venue->heroMedia"
                            :media-alt="$venue->name"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($relatedStories ?? null) && $relatedStories->isNotEmpty())
        <section class="section" aria-labelledby="location-stories-heading">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="Wedding Stories"
                    title="Recent weddings in this area."
                />

                <div class="archive-grid">
                    @foreach ($relatedStories as $story)
                        @php
                            $storyMeta = collect([
                                $story->venue?->name ?? $story->location_name,
                                $story->event_date?->format('F Y'),
                            ])->filter()->implode(' · ');
                        @endphp
                        <x-editorial.archive-card
                            :title="$story->title"
                            :href="route('weddings.show', $story->slug)"
                            :meta="$storyMeta ?: null"
                            :copy="method_exists($story, 'summaryText') ? $story->summaryText(24) : $story->excerpt"
                            :media="$story->heroMedia"
                            :media-src="method_exists($story, 'featuredImageUrl') ? $story->featuredImageUrl() : null"
                            :media-alt="$story->title"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if (! empty($relatedPosts ?? null) && $relatedPosts->isNotEmpty())
        <section class="section" aria-labelledby="location-posts-heading">
            <div class="page-shell--wide page-stack">
                <x-editorial.section-heading
                    eyebrow="From the Journal"
                    title="Planning notes for this area."
                />

                <div class="archive-grid">
                    @foreach ($relatedPosts as $post)
                        @php
                            $postMeta = collect([
                                $post->post_type_label,
                                $post->published_at?->format('F j, Y'),
                            ])->filter()->implode(' · ');
                        @endphp
                        <x-editorial.archive-card
                            :title="$post->title"
                            :href="route('journal.show', $post->slug)"
                            :meta="$postMeta ?: null"
                            :copy="method_exists($post, 'summaryText') ? $post->summaryText(24) : $post->excerpt"
                            :media="$post->heroMedia"
                            :media-src="method_exists($post, 'featuredImageUrl') ? $post->featuredImageUrl() : null"
                            :media-alt="$post->title"
                        />
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @unless ($page->blocks->contains(fn ($block) => $block->type === 'cta'))
        <x-editorial.page-closing
            eyebrow="Next Step"
            title="Want to keep going?"
            copy="You can keep looking through the work, or you can send your date and venue."
            :primary-href="route('inquiry.create')"
            primary-label="Check Availability"
            :secondary-href="route('weddings.index')"
            secondary-label="See Wedding Stories"
        />
    @endunless
@endsection
