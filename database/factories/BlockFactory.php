<?php

namespace Database\Factories;

use App\Models\Block;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'blockable_id' => Page::factory(),
            'blockable_type' => (new Page)->getMorphClass(),
            'type' => 'rich_text',
            'heading' => $this->faker->optional()->sentence(3),
            'body' => '<p>'.$this->faker->paragraph().'</p>',
            'data' => null,
            'is_visible' => true,
            'sort_order' => 0,
        ];
    }

    public function type(string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => ['is_visible' => false]);
    }
}
