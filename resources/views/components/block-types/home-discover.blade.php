@props(['block'])
<x-home.discover :content="app(\App\Support\HomeContent::class)" :copy="$block->body ?: null" />
