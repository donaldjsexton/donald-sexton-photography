<?php

namespace Database\Factories;

use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = rtrim($this->faker->unique()->sentence(3), '.');

        return [
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($title).'-'.$this->faker->unique()->numberBetween(1, 99999),
            'title' => $title,
            'description' => $this->faker->sentence(),
            'visibility' => Gallery::VISIBILITY_PRIVATE,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => [
            'visibility' => Gallery::VISIBILITY_PUBLIC,
        ]);
    }

    public function passwordProtected(string $password = 'secret'): static
    {
        return $this->state(fn (): array => [
            'password' => $password,
        ]);
    }
}
