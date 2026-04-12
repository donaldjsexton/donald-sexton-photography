<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InquiryReply;
use App\Models\Inquiry;
use App\Services\GoogleCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function __construct(private readonly GoogleCalendar $calendar) {}

    public function index(Request $request): View
    {
        $currentStatus = $request->string('status')->toString() ?: 'all';
        $search = trim($request->string('search')->toString());
        $statusOptions = Inquiry::statusOptions();

        if ($currentStatus !== 'all' && ! array_key_exists($currentStatus, $statusOptions)) {
            $currentStatus = 'all';
        }

        $query = Inquiry::query()->with('venue');

        if ($currentStatus !== 'all') {
            $query->where('status', $currentStatus);
        }

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('primary_name', 'like', "%{$search}%")
                    ->orWhere('partner_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('venue_name', 'like', "%{$search}%")
                    ->orWhere('location_city', 'like', "%{$search}%");
            });
        }

        $statusCounts = ['all' => Inquiry::query()->count()];

        foreach (array_keys($statusOptions) as $status) {
            $statusCounts[$status] = Inquiry::query()->where('status', $status)->count();
        }

        return view('admin.inquiries.index', [
            'inquiries' => $query->adminOrdered()->paginate(30)->withQueryString(),
            'currentStatus' => $currentStatus,
            'search' => $search,
            'statusOptions' => $statusOptions,
            'statusCounts' => $statusCounts,
            'statusSummary' => [
                [
                    'label' => 'New',
                    'value' => $statusCounts['new'],
                    'meta' => 'Fresh leads waiting for a first response',
                ],
                [
                    'label' => 'Active',
                    'value' => $statusCounts['active'],
                    'meta' => 'Conversations currently in progress',
                ],
                [
                    'label' => 'Follow Up',
                    'value' => $statusCounts['follow_up'],
                    'meta' => 'Leads that need another touchpoint',
                ],
                [
                    'label' => 'Booked',
                    'value' => $statusCounts['booked'],
                    'meta' => 'Won opportunities already in the pipeline',
                ],
            ],
        ]);
    }

    public function edit(Inquiry $inquiry): View
    {
        return view('admin.inquiries.edit', [
            'inquiry' => $inquiry->loadMissing(['venue', 'messages']),
            'statusOptions' => Inquiry::statusOptions(),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Inquiry::statusOptions()))],
        ]);

        $wasBooked = $inquiry->status !== 'booked' && ($validated['status'] ?? '') === 'booked';

        $inquiry->update($validated);

        if ($wasBooked) {
            $this->calendar->upsertBookingEvent($inquiry);
        }

        return redirect()
            ->route('admin.inquiries.edit', $inquiry)
            ->with('status', 'Inquiry updated.');
    }

    public function reply(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $message = $inquiry->messages()->create([
            'direction' => 'outbound',
            'body' => $validated['body'],
            'sender_name' => $request->user()->name,
            'sender_email' => config('mail.from.address'),
            'sent_at' => now(),
        ]);

        try {
            Mail::to($inquiry->email, $inquiry->primary_name)
                ->send(new InquiryReply($inquiry, $message));
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()
                ->route('admin.inquiries.edit', $inquiry)
                ->with('error', 'Message saved but email delivery failed.');
        }

        if (! $inquiry->first_responded_at) {
            $inquiry->update(['first_responded_at' => $message->sent_at]);
        }

        if ($inquiry->status === 'new') {
            $inquiry->update(['status' => 'active']);
        }

        return redirect()
            ->route('admin.inquiries.edit', $inquiry)
            ->with('status', 'Reply sent.');
    }
}
