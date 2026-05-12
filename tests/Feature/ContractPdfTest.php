<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use App\Services\Contracts\ContractPdfRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelPdf\Facades\Pdf;
use Tests\TestCase;

class ContractPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pdf_route_requires_auth(): void
    {
        $contract = Contract::factory()->create();

        $this->get(route('admin.contracts.pdf', $contract))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_pdf_route_invokes_renderer(): void
    {
        Pdf::fake();
        $admin = User::factory()->create();
        $contract = Contract::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.contracts.pdf', $contract))
            ->assertOk();

        Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'contracts.pdf');
    }

    public function test_public_show_requires_signed_url(): void
    {
        $contract = Contract::factory()->create();

        $this->get(route('contracts.public.show', ['contract' => $contract->uuid]))
            ->assertForbidden();
    }

    public function test_public_show_with_signed_url_displays_contract_and_marks_viewed(): void
    {
        $contract = Contract::factory()->create([
            'viewed_at' => null,
            'body' => 'Wedding day coverage.',
        ]);

        $url = URL::temporarySignedRoute(
            'contracts.public.show',
            now()->addDays(30),
            ['contract' => $contract->uuid],
        );

        $this->get($url)
            ->assertOk()
            ->assertSee($contract->number)
            ->assertSee('Wedding day coverage.');

        $this->assertNotNull($contract->fresh()->viewed_at);
    }

    public function test_renderer_filename_uses_contract_number(): void
    {
        $contract = Contract::factory()->create();
        $renderer = new ContractPdfRenderer;

        $this->assertSame('contract-'.$contract->number.'.pdf', $renderer->filename($contract));
    }

    public function test_renderer_view_data_includes_brand_and_sign_url(): void
    {
        config(['payments.business.name' => 'Test Studio']);
        $contract = Contract::factory()->create();

        $data = (new ContractPdfRenderer)->viewData($contract);

        $this->assertSame('Test Studio', $data['brandName']);
        $this->assertSame($contract->id, $data['contract']->id);
        $this->assertStringContainsString($contract->uuid, $data['signUrl']);
        $this->assertStringContainsString('signature=', $data['signUrl']);
    }
}
