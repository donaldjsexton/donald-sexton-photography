<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var Client $client */
        $client = Auth::guard('client')->user();

        $invoices = $client->invoices()
            ->whereNotIn('status', [Invoice::STATUS_DRAFT])
            ->with('installments')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->get();

        $outstandingCents = $invoices->sum(fn (Invoice $i) => $i->amountDueCents());

        $nextInstallment = InvoiceInstallment::query()
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->whereIn('status', [InvoiceInstallment::STATUS_PENDING, InvoiceInstallment::STATUS_PARTIALLY_PAID, InvoiceInstallment::STATUS_OVERDUE])
            ->whereNotNull('due_date')
            ->orderBy('due_date')
            ->first();

        $upcomingBooking = $client->inquiry?->bookedJob;

        return view('portal.dashboard', [
            'client' => $client,
            'invoices' => $invoices,
            'outstandingCents' => $outstandingCents,
            'nextInstallment' => $nextInstallment,
            'upcomingBooking' => $upcomingBooking?->event_date?->isFuture() ? $upcomingBooking : null,
        ]);
    }
}
