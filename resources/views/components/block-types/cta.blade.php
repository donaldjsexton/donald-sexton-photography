@props([
    'block',
])

@php
    $data = $block->data ?? [];
@endphp

<x-editorial.page-closing
    :eyebrow="$block->subheading ?: 'Next Step'"
    :title="$block->heading"
    :copy="$block->body"
    :primary-href="($data['primary_url'] ?? null) ?: route('inquiry.create')"
    :primary-label="($data['primary_label'] ?? null) ?: 'Check Availability'"
    :secondary-href="$data['secondary_url'] ?? null"
    :secondary-label="$data['secondary_label'] ?? null"
/>
