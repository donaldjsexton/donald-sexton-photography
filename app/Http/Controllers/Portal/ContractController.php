<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SignContractRequest;
use App\Mail\ContractSigned;
use App\Models\Contract;
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

        return redirect()
            ->route('portal.contracts.show', ['contract' => $model->uuid])
            ->with('status', 'Thanks — your contract has been signed.');
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
