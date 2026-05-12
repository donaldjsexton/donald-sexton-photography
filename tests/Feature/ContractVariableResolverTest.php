<?php

namespace Tests\Feature;

use App\Models\BookedJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\Contracts\ContractVariableResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractVariableResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_render_replaces_known_tokens_and_leaves_unknown_ones(): void
    {
        $resolver = new ContractVariableResolver;

        $output = $resolver->render(
            'Hi {{client_name}} — {{event_date}} at {{event_location}}. Code {{unknown}}.',
            array_merge(
                $resolver->variablesFor(
                    bookedJob: new BookedJob([
                        'summary' => 'Wedding',
                        'event_date' => '2026-06-12',
                        'location' => 'The Don CeSar',
                    ]),
                    contractTitle: 'Wedding Agreement',
                ),
                ['client_name' => 'Maddie & Brent'],
            ),
        );

        $this->assertStringContainsString('Maddie & Brent', $output);
        $this->assertStringContainsString('The Don CeSar', $output);
        $this->assertStringContainsString('{{unknown}}', $output);
    }

    public function test_variables_for_uses_billable_display_name_and_invoice_total(): void
    {
        $resolver = new ContractVariableResolver;
        $client = Client::factory()->make(['first_name' => 'Avery', 'last_name' => 'L.']);
        $invoice = Invoice::factory()->make(['number' => 'INV-2026-0042', 'total_cents' => 250000]);

        $vars = $resolver->variablesFor(
            billable: $client,
            invoice: $invoice,
        );

        $this->assertSame($client->displayName(), $vars['client_name']);
        $this->assertSame('INV-2026-0042', $vars['invoice_number']);
        $this->assertSame('$2,500.00', $vars['invoice_total']);
    }
}
