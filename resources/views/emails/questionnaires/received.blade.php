<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Wedding Questionnaire</title>
    <style>
        body { font-family: Georgia, serif; color: #2a2320; margin: 0; padding: 24px; background: #fbf6f0; }
        .wrap { max-width: 720px; margin: 0 auto; background: #fff; padding: 32px; border: 1px solid #efe3d7; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        .meta { color: #6b6157; font-size: 13px; margin: 0 0 24px; }
        .q-view__section { margin: 0 0 24px; }
        .q-view__section-title { font-size: 13px; letter-spacing: .1em; text-transform: uppercase; color: #8a7a6a; margin: 0 0 12px; border-bottom: 1px solid #efe3d7; padding-bottom: 6px; }
        .q-view__list { margin: 0; }
        .q-view__row { display: table-row; }
        .q-view__row dt, .q-view__row dd { display: table-cell; padding: 6px 12px 6px 0; vertical-align: top; font-size: 14px; }
        .q-view__row dt { color: #6b6157; width: 40%; }
        .q-view__row dd { margin: 0; color: #2a2320; }
    </style>
</head>
<body>
    <div class="wrap">
        <h1>Wedding Questionnaire Submitted</h1>
        <p class="meta">
            From {{ $inquiry->primary_name }} ({{ $inquiry->email }}){{ $inquiry->event_date ? ' · '.$inquiry->event_date->format('F j, Y') : '' }}<br>
            Submitted {{ $questionnaire->submitted_at?->format('F j, Y g:i A') }}
        </p>

        @include('questionnaires._responses', ['questionnaire' => $questionnaire, 'schema' => $schema])
    </div>
</body>
</html>
