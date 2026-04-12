<?php

namespace App\Http\Controllers;

use App\Mail\InquiryAcknowledgment;
use App\Mail\InquiryReceived;
use App\Models\Inquiry;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class InquiryController extends Controller
{
    public function create(): View
    {
        return view('inquiries.create', [
            'venues' => Venue::query()->orderBy('name')->get(['id', 'name']),
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
            'coverage_interest' => ['nullable', 'array'],
            'coverage_interest.*' => ['string', 'max:255'],
            'heard_about' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string'],
        ]);

        $inquiry = Inquiry::create($validated + [
            'status' => 'new',
            'source' => 'site_form',
            'utm_source' => $request->string('utm_source')->toString() ?: null,
            'utm_medium' => $request->string('utm_medium')->toString() ?: null,
            'utm_campaign' => $request->string('utm_campaign')->toString() ?: null,
        ]);

        $this->notifyStudio($inquiry);
        $this->acknowledgeClient($inquiry);

        return redirect()->route('inquiry.thank-you');
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
            Mail::to($inquiry->email, $inquiry->primary_name)
                ->send(new InquiryAcknowledgment($inquiry));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
