@props(['block'])
<x-home.inquiry :heading="$block->heading ?: null" :copy="$block->body ?: null" />
