<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Support\Portal;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $billable = Portal::user();

        $invoices = $billable->invoices()
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

        $upcomingBooking = $billable instanceof Client
            ? $billable->inquiry?->bookedJob
            : null;

        return view('portal.dashboard', [
            'billable' => $billable,
            'invoices' => $invoices,
            'outstandingCents' => $outstandingCents,
            'nextInstallment' => $nextInstallment,
            'upcomingBooking' => $upcomingBooking?->event_date?->isFuture() ? $upcomingBooking : null,
        ]);
    }
}
