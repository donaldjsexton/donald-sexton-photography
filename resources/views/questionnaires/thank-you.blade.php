@extends('layouts.app')

@section('title', 'Questionnaire Received')

@section('content')
    <x-editorial.page-hero
        eyebrow="Questionnaire Received"
        title="Thank you."
        copy="Your responses are in. I will review before our next call."
        shell="tight"
    >
        <div class="cta-row">
            <a class="cta-secondary" href="{{ route('home') }}">Return Home</a>
        </div>
    </x-editorial.page-hero>
@endsection
