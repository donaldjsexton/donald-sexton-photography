<?php

namespace Tests\Feature;

use App\Models\ContractTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractTemplatePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_manage_templates(): void
    {
        $user = User::factory()->create();
        $template = ContractTemplate::factory()->create();

        $this->assertTrue($user->can('viewAny', ContractTemplate::class));
        $this->assertTrue($user->can('create', ContractTemplate::class));
        $this->assertTrue($user->can('view', $template));
        $this->assertTrue($user->can('update', $template));
        $this->assertTrue($user->can('delete', $template));
    }
}
