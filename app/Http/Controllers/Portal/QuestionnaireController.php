<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Mail\WeddingQuestionnaireReceived;
use App\Models\PortalActivity;
use App\Models\WeddingQuestionnaire;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class QuestionnaireController extends Controller
{
    public function index(Request $request): View
    {
        return view('portal.questionnaires.index', [
            'questionnaires' => Portal::user()->questionnaires()
                ->with('inquiry')
                ->orderByDesc('wedding_questionnaires.created_at')
                ->get(),
        ]);
    }

    public function show(Request $request, string $questionnaire): View
    {
        $model = $this->locate($questionnaire);

        PortalActivity::record(Portal::user(), PortalActivity::TYPE_QUESTIONNAIRE_VIEWED, $request, $model);

        return view('portal.questionnaires.show', [
            'questionnaire' => $model,
            'schema' => WeddingQuestionnaire::schema(),
        ]);
    }

    public function update(Request $request, string $questionnaire): RedirectResponse
    {
        $model = $this->locate($questionnaire);

        if ($model->isSubmitted()) {
            return redirect()
                ->route('portal.questionnaires.show', ['questionnaire' => $model->token])
                ->with('status', 'This questionnaire has already been submitted.');
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

        $model->update([
            'responses' => $responses,
            'submitted_at' => now(),
        ]);

        PortalActivity::record(Portal::user(), PortalActivity::TYPE_QUESTIONNAIRE_SUBMITTED, $request, $model);

        $this->notifyStudio($model->fresh()->load('inquiry'));

        return redirect()
            ->route('portal.questionnaires.show', ['questionnaire' => $model->token])
            ->with('status', 'Thank you — your questionnaire has been submitted.');
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

    private function locate(string $token): WeddingQuestionnaire
    {
        $questionnaire = Portal::user()->questionnaires()
            ->where('wedding_questionnaires.token', $token)
            ->with('inquiry')
            ->first();

        if (! $questionnaire) {
            throw new NotFoundHttpException;
        }

        return $questionnaire;
    }
}
