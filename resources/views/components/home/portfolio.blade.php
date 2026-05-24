@props([
    'content',
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
])

@php
    $portfolioStories = $content->portfolioStories();
    $leadStory = $portfolioStories->get(0) ?? $content->leadStory();
@endphp

<section class="home-portfolio" data-reveal>
    <div class="page-shell--wide page-stack">
        <x-editorial.section-heading
            class="section-header--centered home-portfolio__header"
            :eyebrow="$eyebrow ?: 'Wedding Stories'"
            :title="$title ?: 'A few good places to start.'"
            :copy="$copy ?: 'These stories show how a full wedding day can feel in photos.'"
        />

        <div class="portfolio-list home-portfolio__list">
            @if ($leadStory)
                <x-editorial.story-feature
                    class="story-feature--lead home-portfolio__feature"
                    :story="$leadStory"
                    ratio="cinema"
                    show-excerpt
                />
            @endif

            @foreach ($portfolioStories->slice(1) as $story)
                <x-editorial.story-feature
                    class="home-portfolio__feature"
                    :story="$story"
                    :reverse="$loop->odd"
                    ratio="{{ $loop->even ? 'landscape' : 'cinema' }}"
                />
            @endforeach

            @if ($portfolioStories->isEmpty())
                <x-editorial.empty-state
                    eyebrow="Portfolio"
                    title="New wedding stories are on the way."
                    copy="You can still look through the journal or check availability."
                    :primary-href="route('weddings.index')"
                    primary-label="See Wedding Stories"
                    :secondary-href="route('inquiry.create')"
                    secondary-label="Check Availability"
                />
            @endif
        </div>
    </div>
</section>
