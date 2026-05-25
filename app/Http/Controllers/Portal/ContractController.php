<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\DeclineContractRequest;
use App\Http\Requests\Portal\SignContractRequest;
use App\Mail\ContractDeclined;
use App\Mail\ContractSigned;
use App\Models\Contract;
use App\Models\PortalActivity;
use App\Services\Contracts\ContractPdfRenderer;
use App\Support\Portal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ContractController extends Controller
{
    public function index(Request $request): View
    {
        $billable = Portal::user();

        return view('portal.contracts.index', [
            'contracts' => $billable->contracts()
                ->whereNotIn('status', [Contract::STATUS_DRAFT])
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function show(Request $request, string $contract): View
    {
        $model = $this->locate($contract);

        if ($model->viewed_at === null) {
            $model->forceFill(['viewed_at' => now()])->save();
        }

        PortalActivity::record(Portal::user(), PortalActivity::TYPE_CONTRACT_VIEWED, $request, $model);

        return view('portal.contracts.show', [
            'contract' => $model->load(['billable', 'bookedJob', 'invoice']),
        ]);
    }

    public function downloadPdf(Request $request, string $contract, ContractPdfRenderer $renderer)
    {
        return $renderer->build($this->locate($contract))->download();
    }

    public function sign(SignContractRequest $request, string $contract): RedirectResponse
    {
        $model = $this->locate($contract);

        if (! $model->isAwaitingSignature()) {
            return redirect()
                ->route('portal.contracts.show', ['contract' => $model->uuid])
                ->with('status', 'This contract is no longer awaiting a signature.');
        }

        if ($model->hasExpired()) {
            return redirect()
                ->route('portal.contracts.show', ['contract' => $model->uuid])
                ->with('status', 'This offer has expired. Please reach out to renegotiate.');
        }

        $model->forceFill([
            'status' => Contract::STATUS_SIGNED,
            'signer_name' => $request->validated('signer_name'),
            'signer_email' => $model->billableEmail(),
            'signer_ip' => $request->ip(),
            'signer_user_agent' => substr((string) $request->userAgent(), 0, 512),
            'signed_at' => now(),
        ])->save();

        Mail::to(config('payments.business.email') ?: config('mail.from.address'))
            ->send(new ContractSigned(contract: $model));

        if ($model->isProposal()) {
            return redirect()
                ->route('portal.proposals.show', ['contract' => $model->uuid])
                ->with('status', 'Thanks — your agreement is signed. One step left: pay the deposit below.');
        }

        return redirect()
            ->route('portal.contracts.show', ['contract' => $model->uuid])
            ->with('status', 'Thanks — your contract has been signed.');
    }

    public function decline(DeclineContractRequest $request, string $contract): RedirectResponse
    {
        $model = $this->locate($contract);

        if (! $model->isAwaitingSignature()) {
            return redirect()
                ->route('portal.contracts.show', ['contract' => $model->uuid])
                ->with('status', 'This contract is no longer awaiting a response.');
        }

        $reason = $request->validated('reason');

        $model->forceFill([
            'status' => Contract::STATUS_DECLINED,
            'declined_at' => now(),
            'internal_notes' => trim(($model->internal_notes ? $model->internal_notes."\n\n" : '').'Declined by client'.($reason ? ': '.$reason : '.')),
        ])->save();

        Mail::to(config('payments.business.email') ?: config('mail.from.address'))
            ->send(new ContractDeclined(contract: $model, reason: $reason));

        return redirect()
            ->route('portal.contracts.show', ['contract' => $model->uuid])
            ->with('status', 'Thanks for letting us know. We will be in touch.');
    }

    private function locate(string $uuid): Contract
    {
        $billable = Portal::user();

        $contract = Contract::where('uuid', $uuid)
            ->where('billable_type', $billable::class)
            ->where('billable_id', $billable->id)
            ->whereNotIn('status', [Contract::STATUS_DRAFT])
            ->first();

        if (! $contract) {
            throw new NotFoundHttpException;
        }

        return $contract;
    }
}
