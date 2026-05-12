<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Services\Contracts\ContractPdfRenderer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractPublicController extends Controller
{
    public function show(Request $request, string $contract): View
    {
        $model = Contract::where('uuid', $contract)->firstOrFail();

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        return view('contracts.public', [
            'contract' => $model->load(['billable', 'bookedJob', 'invoice']),
        ]);
    }

    public function downloadPdf(Request $request, string $contract, ContractPdfRenderer $renderer)
    {
        $model = Contract::where('uuid', $contract)->firstOrFail();

        return $renderer->build($model)->download();
    }
}
