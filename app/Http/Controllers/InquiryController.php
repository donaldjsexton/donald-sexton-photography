<?php

namespace App\Http\Controllers;

use App\Mail\InquiryAcknowledgment;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\Testimonial;
use App\Services\InquiryAvailabilityService;
use App\Services\WebPushService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function create(Request $request): View
    {
        $featuredTestimonials = Testimonial::query()
            ->where('is_featured', true)
            ->orderBy('sort_order')
            ->limit(1)
            ->get();

        return view('inquiries.create', [
            'prefill' => [
                'primary_name' => trim((string) $request->query('primary_name', '')),
                'email' => trim((string) $request->query('email', '')),
                'event_date' => trim((string) $request->query('event_date', '')),
            ],
            'featuredTestimonials' => $featuredTestimonials,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (filled($request->input('website'))) {
            return redirect()->route('inquiry.thank-you');
        }

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
            'coverage_interest' => ['nullable', 'array'],
            'coverage_interest.*' => ['string', 'max:255'],
            'heard_about' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
            'sms_opt_in_transactional' => ['nullable', 'boolean'],
            'sms_opt_in_marketing' => ['nullable', 'boolean'],
        ]);

        $utm = [
            'utm_source' => $request->string('utm_source')->toString() ?: null,
            'utm_medium' => $request->string('utm_medium')->toString() ?: null,
            'utm_campaign' => $request->string('utm_campaign')->toString() ?: null,
        ];

        // Upsert by email: if this address already has an open inquiry, append
        // the new submission as an inbound message rather than creating a duplicate.
        $existing = Inquiry::query()
            ->where('email', $validated['email'])
            ->whereNotIn('status', ['archived', 'booked'])
            ->latest()
            ->first();

        if ($existing) {
            $existing->messages()->create([
                'direction' => 'inbound',
                'body' => $validated['message'] ?? '(No message — re-submitted via inquiry form.)',
                'sender_name' => $validated['primary_name'],
                'sender_email' => $validated['email'],
                'sent_at' => now(),
            ]);

            $this->notifyStudio($existing);
            $this->pushNotify($existing);

            return redirect()->route('inquiry.thank-you')->with('inquiry_submitted', true);
        }

        $smsConsent = [];
        if (! empty($validated['sms_opt_in_transactional']) || ! empty($validated['sms_opt_in_marketing'])) {
            $smsConsent = [
                'sms_consent_at' => now(),
                'sms_consent_ip' => $request->ip(),
            ];
        }

        $inquiry = Inquiry::create($validated + [
            'status' => 'new',
            'source' => 'site_form',
        ] + $utm + $smsConsent);

        $this->notifyStudio($inquiry);
        $this->acknowledgeClient($inquiry);
        $this->pushNotify($inquiry);

        return redirect()->route('inquiry.thank-you')->with('inquiry_submitted', true);
    }

    public function thankYou(): View
    {
        return view('inquiries.thank-you');
    }

    private function notifyStudio(Inquiry $inquiry): void
    {
        $recipient = trim((string) config('mail.inquiry_to'));

        if ($recipient === '') {
            return;
        }

        try {
            Mail::to($recipient)->send(new InquiryReceived($inquiry->loadMissing('venue')));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function acknowledgeClient(Inquiry $inquiry): void
    {
        try {
            $availability = app(InquiryAvailabilityService::class)->forDate($inquiry->event_date);

            Mail::to($inquiry->email, $inquiry->primary_name)
                ->send(new InquiryAcknowledgment($inquiry, $availability));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    private function pushNotify(Inquiry $inquiry): void
    {
        try {
            app(WebPushService::class)->notify(
                'New inquiry from '.$inquiry->primary_name,
                $inquiry->event_date
                    ? $inquiry->event_date->format('F j, Y').' · '.$inquiry->email
                    : $inquiry->email,
                route('admin.inquiries.edit', $inquiry),
            );
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
