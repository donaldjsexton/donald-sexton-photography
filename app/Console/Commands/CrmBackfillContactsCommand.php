<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Inquiry;
use App\Models\QuizLead;
use App\Services\Crm\ContactDirectory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:backfill-contacts
    {--dry-run : Report what would change without writing}
')]
#[Description('Create contacts for existing inquiries, portal clients and quiz leads, and link them by email.')]
class CrmBackfillContactsCommand extends Command
{
    public function handle(ContactDirectory $directory): int
    {
        $dry = (bool) $this->option('dry-run');
        $counts = ['inquiries' => 0, 'clients' => 0, 'quiz_leads' => 0, 'suppressed' => 0];

        foreach (Inquiry::query()->whereNull('contact_id')->whereNotNull('email')->cursor() as $inquiry) {
            $counts['inquiries']++;
            if ($dry) {
                continue;
            }
            $contact = $directory->findOrCreate($inquiry->email, 'site_form', [
                'type' => $inquiry->status === 'booked' ? 'past_couple' : 'couple',
                'stage' => $inquiry->status === 'booked' ? 'booked' : 'inquiry',
                'name' => $inquiry->primary_name,
                'partner_name' => $inquiry->partner_name,
                'phone' => $inquiry->phone,
                'shoot_type' => $inquiry->event_type,
                'event_date' => $inquiry->event_date,
                'source_detail' => $inquiry->lead_source ?: null,
                'first_contact_at' => $inquiry->created_at,
            ]);
            $inquiry->forceFill(['contact_id' => $contact->id])->save();
        }

        foreach (Client::query()->whereNull('contact_id')->whereNotNull('email')->cursor() as $client) {
            $counts['clients']++;
            if ($dry) {
                continue;
            }
            $contact = $directory->findOrCreate($client->email, 'client_portal', [
                'type' => 'couple',
                'name' => trim($client->first_name.' '.$client->last_name),
                'phone' => $client->phone,
            ]);
            $contact->update(['client_id' => $client->id]);
            $client->forceFill(['contact_id' => $contact->id])->save();
        }

        foreach (QuizLead::query()->whereNull('contact_id')->cursor() as $lead) {
            $counts['quiz_leads']++;
            if ($dry) {
                continue;
            }
            $contact = $directory->findOrCreate($lead->email, 'coverage_quiz', [
                'type' => 'couple',
                'name' => $lead->first_name,
            ]);
            $lead->forceFill(['contact_id' => $contact->id])->save();

            if ($lead->unsubscribed_at) {
                $directory->suppress($lead->email, 'unsubscribe', 'Unsubscribed from the coverage quiz email', $lead->unsubscribed_at);
                $counts['suppressed']++;
            }
        }

        $this->table(['Record', ($dry ? 'Would link' : 'Linked')], collect($counts)->map(fn ($n, $k) => [$k, $n])->values()->all());

        return self::SUCCESS;
    }
}
