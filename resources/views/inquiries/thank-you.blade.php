@extends('layouts.app')

@section('title', 'Thank You')

@section('content')
    <x-editorial.page-hero
        eyebrow="Inquiry Received"
        title="Thank you."
        copy="Your note is in. I will be in touch soon."
        shell="tight"
    >
        <div class="cta-row">
            <a class="cta-secondary" href="{{ route('weddings.index') }}">See Wedding Stories</a>
            <a class="cta-secondary" href="{{ route('journal.index') }}">Read the Journal</a>
        </div>
    </x-editorial.page-hero>
@endsection
