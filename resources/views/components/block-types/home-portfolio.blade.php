@props(['block'])
<x-home.portfolio
    :content="app(\App\Support\HomeContent::class)"
    :eyebrow="$block->subheading ?: null"
    :title="$block->heading ?: null"
    :copy="$block->body ?: null"
/>
