<?php

namespace App\Http\Controllers;

use App\Mail\WeddingQuestionnaireReceived;
use App\Models\WeddingQuestionnaire;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class QuestionnaireController extends Controller
{
    public function show(WeddingQuestionnaire $questionnaire): View
    {
        return view('questionnaires.show', [
            'questionnaire' => $questionnaire,
            'schema' => WeddingQuestionnaire::schema(),
        ]);
    }

    public function update(Request $request, WeddingQuestionnaire $questionnaire): RedirectResponse
    {
        if ($questionnaire->isSubmitted()) {
            return redirect()->route('questionnaire.thank-you');
        }

        $keys = array_keys(WeddingQuestionnaire::fieldLabels());
        $responses = [];

        foreach ($keys as $key) {
            $value = $request->input($key);

            if (is_array($value)) {
                $value = array_values(array_filter($value, fn ($v) => $v !== null && $v !== ''));
            } elseif (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '' || $value === null || $value === []) {
                continue;
            }

            $responses[$key] = $value;
        }

        $questionnaire->update([
            'responses' => $responses,
            'submitted_at' => now(),
        ]);

        $this->notifyStudio($questionnaire->fresh()->load('inquiry'));

        return redirect()->route('questionnaire.thank-you');
    }

    public function thankYou(): View
    {
        return view('questionnaires.thank-you');
    }

    private function notifyStudio(WeddingQuestionnaire $questionnaire): void
    {
        $recipient = trim((string) config('mail.inquiry_to'));

        if ($recipient === '') {
            return;
        }

        try {
            Mail::to($recipient)->send(new WeddingQuestionnaireReceived($questionnaire));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
