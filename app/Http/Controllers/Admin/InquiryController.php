<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InquiryReply;
use App\Models\Inquiry;
use App\Models\Venue;
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
            'inquiry' => $inquiry->loadMissing(['venue', 'messages', 'questionnaire']),
            'statusOptions' => Inquiry::statusOptions(),
        ]);
    }

    public function create(): View
    {
        return view('admin.inquiries.create', [
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => Inquiry::statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'primary_name' => ['required', 'string', 'max:255'],
            'partner_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'instagram_handle' => ['nullable', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:100'],
            'event_date' => ['nullable', 'date'],
            'venue_name' => ['nullable', 'string', 'max:255'],
            'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
            'location_city' => ['nullable', 'string', 'max:255'],
            'guest_count_range' => ['nullable', 'string', 'max:255'],
            'budget_range' => ['nullable', 'string', 'max:255'],
            'heard_about' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(array_keys(Inquiry::statusOptions()))],
        ]);

        $inquiry = Inquiry::create($validated + [
            'status' => $validated['status'] ?? 'new',
            'source' => 'admin',
        ]);

        return redirect()
            ->route('admin.inquiries.edit', $inquiry)
            ->with('status', 'Lead created.');
    }

    public function generateQuestionnaire(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->ensureQuestionnaire();

        return redirect()
            ->route('admin.inquiries.edit', $inquiry)
            ->with('status', 'Questionnaire link ready.');
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

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()
            ->route('admin.inquiries.index')
            ->with('status', 'Inquiry deleted.');
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
