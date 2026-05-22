@props([
    'block',
])

<x-editorial.page-hero
    :eyebrow="$block->subheading"
    :title="$block->heading"
    :copy="$block->body"
    :media="$block->media->first()"
    ratio="portrait"
/>
