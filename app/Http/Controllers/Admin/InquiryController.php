<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InquiryReply;
use App\Models\Inquiry;
use App\Models\Venue;
use App\Models\WeddingQuestionnaire;
use App\Services\BookedJobSync;
use App\Services\CalendarSyncOutcome;
use App\Services\ClientFromInquirySync;
use App\Services\GoogleCalendar;
use App\Services\VenueReferral\ReferralIntroDraft;
use App\Services\VenueReferral\VenueReferralIngestor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function __construct(
        private readonly GoogleCalendar $calendar,
        private readonly BookedJobSync $bookedJobSync,
        private readonly ClientFromInquirySync $clientSync,
    ) {}

    public function index(Request $request): View
    {
        $currentStatus = $request->string('status')->toString() ?: 'all';
        $search = trim($request->string('search')->toString());
        $statusOptions = Inquiry::statusOptions();

        if ($currentStatus !== 'all' && ! array_key_exists($currentStatus, $statusOptions)) {
            $currentStatus = 'all';
        }

        $query = Inquiry::query()->with('venue');

        if ($currentStatus === 'all') {
            $query->where('status', '!=', 'archived');
        } else {
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

        $statusCounts = ['all' => Inquiry::query()->where('status', '!=', 'archived')->count()];

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
        $inquiry->loadMissing(['venue', 'messages', 'questionnaire']);

        $awaitingApproval = in_array($inquiry->source, [
            VenueReferralIngestor::SOURCE_GATED,
            VenueReferralIngestor::SOURCE_PENDING,
        ], true) && ! $inquiry->first_responded_at;

        return view('admin.inquiries.edit', [
            'inquiry' => $inquiry,
            'statusOptions' => Inquiry::statusOptions(),
            'awaitingApproval' => $awaitingApproval,
            'isGatedReferral' => $inquiry->source === VenueReferralIngestor::SOURCE_GATED,
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

    public function showQuestionnaire(Inquiry $inquiry): View
    {
        $questionnaire = $inquiry->questionnaire ?? abort(404);

        return view('admin.inquiries.questionnaire', [
            'inquiry' => $inquiry,
            'questionnaire' => $questionnaire,
            'schema' => WeddingQuestionnaire::schema(),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Inquiry::statusOptions()))],
        ]);

        $inquiry->update($validated);

        $redirect = redirect()->route('admin.inquiries.edit', $inquiry);

        if ($inquiry->status === 'booked') {
            $outcome = $this->calendar->upsertBookingEvent($inquiry);
            $inquiry = $inquiry->refresh();
            $this->bookedJobSync->syncFromInquiry($inquiry);
            $this->clientSync->syncFromInquiry($inquiry);

            return $redirect->with('status', $this->bookedFlashMessage($outcome));
        }

        return $redirect->with('status', 'Inquiry updated.');
    }

    private function bookedFlashMessage(CalendarSyncOutcome $outcome): string
    {
        return match ($outcome) {
            CalendarSyncOutcome::Synced => 'Inquiry updated and synced to Google Calendar.',
            CalendarSyncOutcome::MissingEventDate => 'Inquiry updated. Add an event date to sync this booking to Google Calendar.',
            CalendarSyncOutcome::NotConnected => 'Inquiry updated. Connect Google Calendar to sync booked events.',
            CalendarSyncOutcome::Failed => 'Inquiry updated, but Google Calendar sync failed. Check the logs and retry.',
        };
    }

    public function destroy(Inquiry $inquiry): RedirectResponse
    {
        $inquiry->delete();

        return redirect()
            ->route('admin.inquiries.index')
            ->with('status', 'Inquiry deleted.');
    }

    public function draftReply(Inquiry $inquiry, ReferralIntroDraft $draft): RedirectResponse
    {
        return redirect()
            ->route('admin.inquiries.edit', $inquiry)
            ->with('draft_body', $draft->for($inquiry->loadMissing('venue')))
            ->with('status', 'Draft generated. Review and edit before sending.');
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
