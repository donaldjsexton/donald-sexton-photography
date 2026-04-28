<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $siteName }} — Journal</title>
    <subtitle>Wedding stories, planning advice, and venue notes from Donald Sexton Photography.</subtitle>
    <link rel="self" type="application/atom+xml" href="{{ route('journal.feed') }}"/>
    <link rel="alternate" type="text/html" href="{{ route('journal.index') }}"/>
    <id>{{ route('journal.feed') }}</id>
    <updated>{{ $updated }}</updated>
    <author>
        <name>{{ $siteName }}</name>
        <uri>{{ route('home') }}</uri>
    </author>
    @foreach ($posts as $post)
        @php
            $entryUrl = $post->canonical_url ?: route('journal.show', $post->slug);
            $entryUpdated = ($post->updated_at ?? $post->published_at)?->toAtomString();
            $entryPublished = $post->published_at?->toAtomString();
            $summary = \App\Http\Controllers\JournalFeedController::summarize($post);
            $heroUrl = $post->heroMedia?->publicUrl();
        @endphp
        <entry>
            <title>{{ $post->title }}</title>
            <link rel="alternate" type="text/html" href="{{ $entryUrl }}"/>
            <id>{{ $entryUrl }}</id>
            @if ($entryUpdated)
                <updated>{{ $entryUpdated }}</updated>
            @endif
            @if ($entryPublished)
                <published>{{ $entryPublished }}</published>
            @endif
            @if ($post->author_name)
                <author>
                    <name>{{ $post->author_name }}</name>
                </author>
            @endif
            @if ($summary !== '')
                <summary type="text">{{ $summary }}</summary>
            @endif
            @if ($heroUrl)
                <link rel="enclosure" type="image/jpeg" href="{{ url($heroUrl) }}"/>
            @endif
        </entry>
    @endforeach
</feed>
