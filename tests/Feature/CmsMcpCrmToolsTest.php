<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\CmsMcpCredential;
use App\Models\Inquiry;
use App\Models\Page;

class CmsMcpCrmToolsTest extends CmsMcpTestCase
{
    public function test_crm_tools_are_hidden_without_their_abilities(): void
    {
        $default = $this->toolNames($this->token(CmsMcpCredential::DEFAULT_ABILITIES));
        $inquiries = $this->toolNames($this->token([CmsMcpCredential::INQUIRIES_READ]));
        $jobs = $this->toolNames($this->token([CmsMcpCredential::JOBS_READ]));

        $this->assertEqualsCanonicalizing(['list_inquiries', 'get_inquiry', 'list_clients'], $inquiries);
        $this->assertEqualsCanonicalizing(['list_booked_jobs', 'get_booked_job', 'get_calendar_availability'], $jobs);

        foreach (['list_inquiries', 'list_clients', 'list_booked_jobs', 'get_calendar_availability'] as $tool) {
            $this->assertNotContains($tool, $default);
        }
    }

    public function test_direct_calls_to_hidden_crm_tools_return_no_data(): void
    {
        $inquiry = Inquiry::factory()->create(['email' => 'private@example.test']);
        $job = BookedJob::factory()->create();
        $client = Client::factory()->create(['email' => 'client-private@example.test']);
        $pages = $this->token([CmsMcpCredential::PAGES_WRITE, CmsMcpCredential::JOBS_READ]);

        foreach ([
            ['list_inquiries', []],
            ['get_inquiry', ['inquiry_id' => $inquiry->id]],
            ['list_clients', []],
        ] as [$name, $arguments]) {
            $response = $this->tool($pages, $name, $arguments)->assertJsonPath('error.code', -32602);
            $this->assertStringNotContainsString('private@example.test', $response->getContent());
        }

        $inquiries = $this->token([CmsMcpCredential::INQUIRIES_READ]);
        $this->tool($inquiries, 'get_booked_job', ['job_id' => $job->id])->assertJsonPath('error.code', -32602);
        $this->assertSame(0, Page::query()->count());
    }

    public function test_inquiries_and_clients_are_redacted_to_name_email_status_and_dates(): void
    {
        $inquiry = Inquiry::factory()->create([
            'primary_name' => 'Jordan Lee',
            'email' => 'jordan@example.test',
            'phone' => '555-010-7777',
            'message' => 'PRIVATE-MESSAGE-BODY',
            'utm_source' => 'tracking-source',
            'sms_consent_ip' => '203.0.113.9',
            'event_date' => '2027-05-01',
        ]);
        Inquiry::factory()->archived()->create(['primary_name' => 'Archived Person']);
        $client = Client::factory()->create([
            'first_name' => 'Casey',
            'phone' => '555-010-8888',
            'address_line_1' => '12 Secret Street',
            'notes' => 'PRIVATE-NOTE',
            'password' => 'portal-secret-pass',
        ]);
        $token = $this->token([CmsMcpCredential::INQUIRIES_READ]);

        $list = $this->tool($token, 'list_inquiries')
            ->assertJsonPath('result.structuredContent.total', 1)
            ->assertJsonPath('result.structuredContent.inquiries.0.email', 'jordan@example.test')
            ->assertJsonPath('result.structuredContent.inquiries.0.event_date', '2027-05-01');
        $get = $this->tool($token, 'get_inquiry', ['inquiry_id' => $inquiry->id])
            ->assertJsonPath('result.structuredContent.inquiry.primary_name', 'Jordan Lee');
        $clients = $this->tool($token, 'list_clients', ['search' => 'Casey'])
            ->assertJsonPath('result.structuredContent.clients.0.first_name', 'Casey');

        foreach ([$list, $get, $clients] as $response) {
            foreach (['555-010-7777', '555-010-8888', 'PRIVATE-MESSAGE-BODY', 'PRIVATE-NOTE', 'Secret Street', 'tracking-source', '203.0.113.9', 'portal-secret-pass'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }

        $this->tool($token, 'list_inquiries', ['status' => 'archived'])->assertJsonPath('result.structuredContent.total', 1);
        $this->tool($token, 'get_inquiry', ['inquiry_id' => 99999])->assertJsonPath('result.isError', true);
        $this->assertSame($client->id, (int) $clients->json('result.structuredContent.clients.0.id'));
    }

    public function test_booked_jobs_hide_notes_and_calendar_ids(): void
    {
        $job = BookedJob::factory()->create([
            'event_date' => now()->addDays(10)->toDateString(),
            'status' => 'confirmed',
            'raw_description' => 'RAW-CALENDAR-NOTES',
            'coordinator' => 'COORDINATOR-PRIVATE',
            'ceremony_notes' => 'CEREMONY-PRIVATE',
            'google_event_id' => 'google-event-private',
        ]);
        $token = $this->token([CmsMcpCredential::JOBS_READ]);

        $date = now()->addDays(10);
        $list = $this->tool($token, 'list_booked_jobs', ['year' => (int) $date->year, 'month' => (int) $date->month])
            ->assertJsonPath('result.structuredContent.jobs.0.id', $job->id)
            ->assertJsonPath('result.structuredContent.upcoming.0.id', $job->id);
        $get = $this->tool($token, 'get_booked_job', ['job_id' => $job->id])
            ->assertJsonPath('result.structuredContent.job.couple_names', $job->couple_names);

        foreach ([$list, $get] as $response) {
            foreach (['RAW-CALENDAR-NOTES', 'COORDINATOR-PRIVATE', 'CEREMONY-PRIVATE', 'google-event-private'] as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }
    }

    public function test_calendar_availability_uses_the_public_calendar_and_validates_the_month(): void
    {
        $token = $this->token([CmsMcpCredential::JOBS_READ]);
        config(['availability.mock' => true]);

        $month = now()->addMonth()->format('Y-m');
        $response = $this->tool($token, 'get_calendar_availability', ['month' => $month])
            ->assertJsonPath('result.isError', false)
            ->assertJsonPath('result.structuredContent.month', $month);
        $this->assertNotEmpty($response->json('result.structuredContent.days'));

        $this->tool($token, 'get_calendar_availability', ['month' => '2020-01'])->assertJsonPath('result.isError', true);
        $this->tool($token, 'get_calendar_availability', ['month' => 'soon'])->assertJsonPath('result.isError', true);
    }
}
