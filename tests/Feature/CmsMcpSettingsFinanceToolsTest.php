<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\Client;
use App\Models\CmsMcpCredential;
use App\Models\Contract;
use App\Models\HomepageSetting;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Payment;
use App\Models\SiteSetting;

class CmsMcpSettingsFinanceToolsTest extends CmsMcpTestCase
{
    public function test_finance_and_settings_tools_are_not_in_the_default_grant(): void
    {
        $default = $this->toolNames($this->token(CmsMcpCredential::DEFAULT_ABILITIES));

        foreach (['list_invoices', 'get_invoice', 'list_contracts', 'get_contract', 'list_proposals', 'get_site_settings', 'update_site_settings', 'update_homepage_settings', 'create_homepage_block'] as $tool) {
            $this->assertNotContains($tool, $default);
        }

        $this->assertEqualsCanonicalizing(
            ['list_invoices', 'get_invoice', 'list_contracts', 'get_contract', 'list_proposals'],
            $this->toolNames($this->token([CmsMcpCredential::FINANCE_READ])),
        );
        $this->assertEqualsCanonicalizing(
            ['get_site_settings', 'update_site_settings', 'update_homepage_settings', 'create_homepage_block', 'update_homepage_block', 'reorder_homepage_blocks'],
            $this->toolNames($this->token([CmsMcpCredential::SETTINGS_WRITE])),
        );
        $this->assertContains('get_homepage', $this->toolNames($this->token([CmsMcpCredential::SITE_READ])));
    }

    public function test_finance_tools_are_read_only_and_redact_secret_fields(): void
    {
        $client = Client::factory()->create(['first_name' => 'Morgan', 'last_name' => 'Fields']);
        $invoice = Invoice::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'number' => 'INV-100',
            'status' => Invoice::STATUS_SENT,
            'total_cents' => 250000,
            'internal_notes' => 'INTERNAL-INVOICE-NOTE',
        ]);
        InvoiceLineItem::query()->create(['invoice_id' => $invoice->id, 'description' => 'Wedding coverage', 'quantity' => 1, 'unit_price_cents' => 250000, 'subtotal_cents' => 250000, 'total_cents' => 250000]);
        Payment::factory()->create(['invoice_id' => $invoice->id, 'gateway_payment_id' => 'SQUARE-PAYMENT-SECRET']);
        $contract = Contract::factory()->create([
            'billable_type' => Client::class,
            'billable_id' => $client->id,
            'invoice_id' => $invoice->id,
            'internal_notes' => 'INTERNAL-CONTRACT-NOTE',
            'signer_ip' => '203.0.113.77',
            'signer_user_agent' => 'SECRET-USER-AGENT',
        ]);
        $token = $this->token([CmsMcpCredential::FINANCE_READ]);

        $responses = [
            $this->tool($token, 'list_invoices')->assertJsonPath('result.structuredContent.invoices.0.number', 'INV-100'),
            $this->tool($token, 'get_invoice', ['invoice_id' => $invoice->id])
                ->assertJsonPath('result.structuredContent.invoice.client_name', $client->displayName())
                ->assertJsonPath('result.structuredContent.invoice.line_items.0.description', 'Wedding coverage'),
            $this->tool($token, 'list_contracts')->assertJsonPath('result.structuredContent.total', 1),
            $this->tool($token, 'get_contract', ['contract_id' => $contract->id])
                ->assertJsonPath('result.structuredContent.contract.title', 'Wedding Photography Agreement'),
            $this->tool($token, 'list_proposals')->assertJsonPath('result.structuredContent.proposals.0.invoice.number', 'INV-100'),
        ];

        foreach ($responses as $response) {
            $content = $response->getContent();

            foreach (['INTERNAL-INVOICE-NOTE', 'INTERNAL-CONTRACT-NOTE', '203.0.113.77', 'SECRET-USER-AGENT', 'SQUARE-PAYMENT-SECRET', $invoice->uuid, $contract->uuid] as $secret) {
                $this->assertStringNotContainsString((string) $secret, $content);
            }
        }

        $this->tool($token, 'get_invoice', ['invoice_id' => 99999])->assertJsonPath('result.isError', true);
        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(Invoice::STATUS_SENT, $invoice->refresh()->status);
    }

    public function test_finance_tools_are_hidden_without_the_ability(): void
    {
        $invoice = Invoice::factory()->create(['number' => 'INV-HIDDEN']);
        $token = $this->token([CmsMcpCredential::SITE_READ, CmsMcpCredential::SETTINGS_WRITE]);

        $response = $this->tool($token, 'get_invoice', ['invoice_id' => $invoice->id])->assertJsonPath('error.code', -32602);
        $this->assertStringNotContainsString('INV-HIDDEN', $response->getContent());
    }

    public function test_site_settings_reads_redact_secrets_and_writes_use_the_allowlist(): void
    {
        $settings = SiteSetting::query()->create([
            'google_analytics_measurement_id' => 'G-OLD123',
            'google_refresh_token' => 'GOOGLE-REFRESH-SECRET',
            'google_access_token' => 'GOOGLE-ACCESS-SECRET',
            'google_connected_email' => 'owner@example.test',
            'square_access_token' => 'SQUARE-ACCESS-SECRET',
            'square_merchant_id' => 'MERCHANT',
            'indexnow_key' => 'abcdef0123456789',
            'business_phone' => '555-0100',
        ]);
        $token = $this->token([CmsMcpCredential::SETTINGS_WRITE]);

        $read = $this->tool($token, 'get_site_settings')
            ->assertJsonPath('result.structuredContent.settings.business_phone', '555-0100')
            ->assertJsonPath('result.structuredContent.connections.google_connected', true)
            ->assertJsonPath('result.structuredContent.connections.square_connected', true)
            ->assertJsonPath('result.structuredContent.connections.indexnow_key_set', true);

        foreach (['GOOGLE-REFRESH-SECRET', 'GOOGLE-ACCESS-SECRET', 'SQUARE-ACCESS-SECRET', 'abcdef0123456789', 'owner@example.test'] as $secret) {
            $this->assertStringNotContainsString($secret, $read->getContent());
        }

        $this->tool($token, 'update_site_settings', [
            'google_analytics_measurement_id' => 'g-new456',
            'instagram_url' => 'https://instagram.com/example',
            'indexnow_key' => 'ffffffffffffffff',
            'square_access_token' => 'attacker-token',
            'google_refresh_token' => 'attacker-refresh',
            'gbp_location_name' => 'accounts/1/locations/2',
        ])->assertJsonPath('result.structuredContent.updated', ['google_analytics_measurement_id', 'instagram_url']);

        $settings->refresh();
        $this->assertSame('G-NEW456', $settings->google_analytics_measurement_id);
        $this->assertSame('https://instagram.com/example', $settings->instagram_url);
        $this->assertSame('abcdef0123456789', $settings->indexnow_key);
        $this->assertSame('SQUARE-ACCESS-SECRET', $settings->square_access_token);
        $this->assertSame('GOOGLE-REFRESH-SECRET', $settings->google_refresh_token);
        $this->assertNull($settings->gbp_location_name);

        $this->tool($token, 'update_site_settings', ['instagram_url' => 'not a url'])->assertJsonPath('result.isError', true);
        $this->tool($token, 'update_site_settings', ['indexnow_key' => 'ffffffffffffffff'])->assertJsonPath('result.isError', true);
        $this->tool($this->token([CmsMcpCredential::SITE_READ]), 'update_site_settings', ['instagram_url' => 'https://evil.example'])
            ->assertJsonPath('error.code', -32602);
        $this->assertSame('https://instagram.com/example', $settings->refresh()->instagram_url);
    }

    public function test_homepage_settings_and_blocks_need_settings_write(): void
    {
        $writer = $this->token([CmsMcpCredential::SETTINGS_WRITE, CmsMcpCredential::SITE_READ]);
        $reader = $this->token([CmsMcpCredential::SITE_READ]);
        $type = array_key_first(array_filter((array) config('blocks.types'), fn (array $d): bool => ($d['context'] ?? 'page') === 'homepage'));

        $this->tool($reader, 'update_homepage_settings', ['hero_heading' => 'Hidden'])->assertJsonPath('error.code', -32602);
        $this->tool($reader, 'create_homepage_block', ['type' => $type])->assertJsonPath('error.code', -32602);
        $this->assertSame(0, HomepageSetting::query()->count());

        $this->tool($writer, 'update_homepage_settings', ['hero_heading' => 'Welcome', 'final_cta_body' => 'Call us'])
            ->assertJsonPath('result.structuredContent.homepage.hero_heading', 'Welcome');
        $this->tool($writer, 'update_homepage_settings', ['final_cta_heading' => 'Book'])
            ->assertJsonPath('result.structuredContent.homepage.hero_heading', 'Welcome')
            ->assertJsonPath('result.structuredContent.homepage.final_cta_body', 'Call us');
        $this->tool($writer, 'update_homepage_settings', ['hero_media_id' => 99999])->assertJsonPath('result.isError', true);
        $this->tool($writer, 'update_homepage_settings', [])->assertJsonPath('result.isError', true);

        $a = (int) $this->tool($writer, 'create_homepage_block', ['type' => $type, 'heading' => 'A'])->json('result.structuredContent.block.id');
        $b = (int) $this->tool($writer, 'create_homepage_block', ['type' => $type, 'heading' => 'B'])->json('result.structuredContent.block.id');
        $this->tool($writer, 'update_homepage_block', ['block_id' => $a, 'type' => $type, 'heading' => 'A2', 'is_visible' => false])
            ->assertJsonPath('result.structuredContent.block.heading', 'A2')
            ->assertJsonPath('result.structuredContent.block.is_visible', false);
        $this->tool($writer, 'reorder_homepage_blocks', ['block_ids' => [$b, $a]]);
        $home = $this->tool($reader, 'get_homepage');
        $this->assertSame([$b, $a], array_column($home->json('result.structuredContent.homepage.blocks'), 'id'));

        $this->tool($writer, 'create_homepage_block', ['type' => 'nope'])->assertJsonPath('result.isError', true);
        $foreign = Block::factory()->create();
        $this->tool($writer, 'update_homepage_block', ['block_id' => $foreign->id, 'type' => $type])->assertJsonPath('result.isError', true);
    }

    public function test_issue_command_grants_new_abilities_only_when_asked(): void
    {
        $this->artisan('cms-mcp:issue', ['name' => 'Default'])->assertSuccessful();
        $default = CmsMcpCredential::query()->where('name', 'Default')->firstOrFail();
        $this->assertSame(CmsMcpCredential::DEFAULT_ABILITIES, $default->abilities);

        $this->artisan('cms-mcp:issue', ['name' => 'Finance', '--ability' => ['finance:read', 'site:read']])->assertSuccessful();
        $finance = CmsMcpCredential::query()->where('name', 'Finance')->firstOrFail();
        $this->assertSame(['finance:read', 'site:read'], $finance->abilities);

        $this->artisan('cms-mcp:issue', ['name' => 'Bad', '--ability' => ['pages:delete']])->assertFailed();
        $this->assertSame(0, CmsMcpCredential::query()->where('name', 'Bad')->count());
    }
}
