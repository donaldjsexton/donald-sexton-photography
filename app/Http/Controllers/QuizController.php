<?php

namespace App\Http\Controllers;

use App\Mail\QuizResult;
use App\Models\QuizLead;
use App\Services\Quiz\CoverageQuizScorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizController extends Controller
{
    public function show(): View
    {
        return view('quiz.show', ['moments' => CoverageQuizScorer::MOMENTS]);
    }

    public function store(Request $request, CoverageQuizScorer $scorer): JsonResponse
    {
        if (filled($request->input('website'))) {
            return response()->json(['ok' => true, 'tier' => null]);
        }

        $v = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['accepted'],
            'day' => ['required', Rule::in(['elopement', 'small', 'full', 'unsure'])],
            'guests' => ['nullable', Rule::in(['u25', '25-75', '75-150', '150plus'])],
            'locations' => ['nullable', Rule::in(['same', 'short', 'far'])],
            'moments' => ['nullable', 'array'],
            'moments.*' => [Rule::in(array_keys(CoverageQuizScorer::MOMENTS))],
            'start' => ['nullable', Rule::in(['morning', 'afternoon', 'evening'])],
            'worry' => ['nullable', 'string', 'max:80'],
            'second' => ['nullable', Rule::in(['yes', 'no', 'unsure'])],
            'wedding_date' => ['nullable', 'string', 'max:80'],
            'venue' => ['nullable', 'string', 'max:160'],
        ]);

        $scored = $scorer->score($v);
        $lead = QuizLead::create([
            'first_name' => trim($v['first_name']),
            'email' => strtolower(trim($v['email'])),
            'result' => $scored['result'],
            'score' => $scored['score'],
            'answers' => collect($v)->except(['first_name', 'email', 'consent'])->all(),
            'wedding_date_text' => $v['wedding_date'] ?? null,
            'venue_text' => $v['venue'] ?? null,
            'consent_at' => now(),
            'consent_ip' => $request->ip(),
            'unsubscribe_token' => Str::random(48),
        ]);

        $isElope = $scored['result'] === 'elopement';
        $tier = $isElope ? config('quiz.elopement') : config('quiz.tiers.'.$scored['result']);
        $why = $isElope ? '' : $scorer->why($scored);

        $inquiryUrl = url('/inquire').'?'.http_build_query(array_filter([
            'primary_name' => $lead->first_name,
            'email' => $lead->email,
            'event_type' => $isElope ? 'elopement' : 'wedding',
            'venue_name' => $lead->venue_text,
            'message' => $isElope
                ? 'Found you through the coverage quiz. Looking at a small ceremony.'
                : 'Found you through the coverage quiz. Recommendation: '.$tier['hours'].' hours ('.$tier['label'].').'
                    .($lead->wedding_date_text ? ' Date: '.$lead->wedding_date_text.'.' : '')
                    .(! empty($v['guests']) ? ' Guests: '.$v['guests'].'.' : ''),
        ]));

        // Result email is sent for wedding results; elopements get the on-page link only.
        if (! $isElope) {
            try {
                Mail::to($lead->email)->send(new QuizResult($lead, $tier, $why, $scored['second'], $inquiryUrl));
                $lead->update(['result_emailed_at' => now()]);
            } catch (\Throwable $e) {
                Log::warning('Quiz result email failed', ['lead' => $lead->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'ok' => true,
            'tier' => $tier,
            'result' => $scored['result'],
            'why' => $why,
            'second' => $scored['second'] ? config('quiz.second_photographer') : null,
            'inquiry_url' => $inquiryUrl,
            'emailed' => $lead->result_emailed_at !== null,
        ]);
    }

    public function unsubscribe(string $token): View
    {
        $lead = QuizLead::query()->where('unsubscribe_token', $token)->first();
        if ($lead && ! $lead->unsubscribed_at) {
            $lead->update(['unsubscribed_at' => now()]);
        }

        return view('quiz.unsubscribed');
    }
}
