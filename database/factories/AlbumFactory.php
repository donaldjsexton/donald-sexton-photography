<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Album>
 */
class AlbumFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'name' => rtrim($this->faker->unique()->sentence(2), '.'),
            'description' => $this->faker->sentence(),
            'visibility' => Album::VISIBILITY_PRIVATE,
            'sort_order' => 0,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => [
            'visibility' => Album::VISIBILITY_PUBLIC,
        ]);
    }
}
