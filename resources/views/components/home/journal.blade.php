@props([
    'content',
    'eyebrow' => null,
    'title' => null,
    'copy' => null,
])

@php
    $journalPosts = $content->journalPosts();
@endphp

<section class="section home-journal" data-reveal>
    <div class="page-shell--wide page-stack">
        <x-editorial.section-heading
            :eyebrow="$eyebrow ?: 'Journal'"
            :title="$title ?: 'Ideas, places, and real wedding days.'"
            :copy="$copy ?: 'Use the journal for planning tips, venue notes, and recent stories.'"
        />

        <div class="archive-grid">
            @forelse ($journalPosts as $post)
                <x-editorial.archive-card
                    :title="$post->title"
                    :href="route('journal.show', $post->slug)"
                    :meta="$post->post_type_label.($post->published_at ? ' · '.$post->published_at->format('F j, Y') : '')"
                    :copy="method_exists($post, 'summaryText') ? $post->summaryText(24) : ($post->excerpt ?: \Illuminate\Support\Str::words(strip_tags($post->body ?? ''), 24))"
                />
            @empty
                <x-editorial.empty-state
                    eyebrow="Journal"
                    title="New journal posts are on the way."
                    copy="For now, start with the wedding stories or check availability."
                    :primary-href="route('weddings.index')"
                    primary-label="See Wedding Stories"
                />
            @endforelse
        </div>
    </div>
</section>
