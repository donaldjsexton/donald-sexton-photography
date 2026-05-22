<?php

namespace Tests\Feature;

use App\Models\HomepageSetting;
use App\Models\Page;
use App\Models\User;
use App\Support\HomepageBlocksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class HomepageBlocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_renders_classic_layout_when_no_blocks_exist(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Start Your Inquiry')
            ->assertSee('Photography');
    }

    public function test_homepage_renders_through_blocks_once_seeded(): void
    {
        HomepageBlocksSeeder::seed();

        $this->get('/')
            ->assertOk()
            ->assertSee('Start Your Inquiry')
            ->assertSee('Photography');
    }

    public function test_seed_command_creates_default_sections_idempotently(): void
    {
        Artisan::call('home:seed-blocks');

        $settings = HomepageSetting::query()->first();
        $this->assertNotNull($settings);
        $this->assertSame(
            HomepageBlocksSeeder::DEFAULT_TYPES,
            $settings->allBlocks()->pluck('type')->all(),
        );

        Artisan::call('home:seed-blocks');
        $this->assertCount(count(HomepageBlocksSeeder::DEFAULT_TYPES), $settings->allBlocks()->get());
    }

    public function test_home_block_types_are_hidden_from_the_page_palette(): void
    {
        $user = User::factory()->create();
        $page = Page::factory()->create();

        $this->actingAs($user)->get(route('admin.pages.edit', $page))
            ->assertOk()
            ->assertDontSee('Home · Hero')
            ->assertSee('Rich Text');
    }

    public function test_homepage_editor_exposes_home_block_palette(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.homepage.edit'))
            ->assertOk()
            ->assertSee('Home · Hero')
            ->assertSee('Homepage Sections');
    }

    public function test_homepage_block_management_requires_authentication(): void
    {
        $this->post(route('admin.homepage.blocks.seed'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_seed_and_edit_homepage_blocks(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('admin.homepage.blocks.seed'))
            ->assertRedirect(route('admin.homepage.edit'));

        $settings = HomepageSetting::query()->first();
        $block = $settings->allBlocks()->where('type', 'home_inquiry')->firstOrFail();

        $this->actingAs($user)->put(route('admin.homepage.blocks.update', $block), [
            'type' => 'home_inquiry',
            'heading' => 'Lock in your date today',
            'body' => 'Tell me about your day.',
            'is_visible' => '1',
        ])->assertRedirect(route('admin.homepage.edit'));

        $this->assertSame('Lock in your date today', $block->refresh()->heading);

        $this->get('/')
            ->assertOk()
            ->assertSee('Lock in your date today');
    }
}
