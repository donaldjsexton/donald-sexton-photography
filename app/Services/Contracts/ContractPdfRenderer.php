<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

class ContractPdfRenderer
{
    public function build(Contract $contract): PdfBuilder
    {
        $contract->loadMissing(['billable', 'bookedJob', 'invoice']);

        return Pdf::view('contracts.pdf', $this->viewData($contract))
            ->name($this->filename($contract));
    }

    public function filename(Contract $contract): string
    {
        return 'contract-'.$contract->number.'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function viewData(Contract $contract): array
    {
        return [
            'contract' => $contract,
            'brandName' => config('payments.business.name'),
            'brandEmail' => config('payments.business.email'),
            'brandPhone' => config('payments.business.phone'),
            'brandAddress' => config('payments.business.address'),
            'signUrl' => $this->signedSignUrl($contract),
        ];
    }

    public function signedSignUrl(Contract $contract): string
    {
        $ttl = (int) config('contracts.signed_url_ttl_days', 90);

        return URL::temporarySignedRoute(
            'contracts.public.show',
            now()->addDays($ttl),
            ['contract' => $contract->uuid],
        );
    }
}
