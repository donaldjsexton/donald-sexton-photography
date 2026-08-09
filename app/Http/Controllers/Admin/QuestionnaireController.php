<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WeddingQuestionnaire;
use Illuminate\View\View;

class QuestionnaireController extends Controller
{
    public function show(WeddingQuestionnaire $questionnaire): View
    {
        return view('admin.inquiries.questionnaire', [
            'inquiry' => $questionnaire->inquiry,
            'questionnaire' => $questionnaire,
            'schema' => WeddingQuestionnaire::schema(),
        ]);
    }
}
