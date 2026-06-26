<?php

namespace Database\Factories;

use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Photo>
 */
class PhotoFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'uuid' => $uuid,
            'disk' => 's3',
            'path' => 'galleries/'.$uuid.'.jpg',
            'original_name' => $this->faker->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => $this->faker->numberBetween(500_000, 8_000_000),
            'sha256' => hash('sha256', $uuid),
            'width' => 6000,
            'height' => 4000,
            'camera' => $this->faker->randomElement(['Canon EOS R5', 'Sony A7 IV', 'Nikon Z6 II']),
            'taken_at' => $this->faker->dateTimeBetween('-2 years'),
        ];
    }
}
