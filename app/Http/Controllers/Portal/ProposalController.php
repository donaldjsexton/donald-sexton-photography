<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\PortalActivity;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProposalController extends Controller
{
    public function show(Request $request, string $contract): View
    {
        $model = $this->locate($contract);

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        PortalActivity::record(Portal::user(), PortalActivity::TYPE_CONTRACT_VIEWED, $request, $model);

        $invoice = $model->invoice;
        $invoice?->load(['lineItems', 'installments', 'payments']);

        return view('portal.proposals.show', [
            'contract' => $model->load(['billable', 'bookedJob']),
            'invoice' => $invoice,
        ]);
    }

    private function locate(string $uuid): Contract
    {
        $billable = Portal::user();

        $contract = Contract::where('uuid', $uuid)
            ->where('billable_type', $billable::class)
            ->where('billable_id', $billable->id)
            ->whereNotNull('invoice_id')
            ->whereNotIn('status', [Contract::STATUS_DRAFT])
            ->first();

        if (! $contract) {
            throw new NotFoundHttpException;
        }

        return $contract;
    }
}
