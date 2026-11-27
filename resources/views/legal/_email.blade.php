@if (! empty($contactEmail))<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@else<a href="{{ route('inquiry.create') }}">our inquiry form</a>@endif
