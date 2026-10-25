<?php

namespace Tests\Feature;

use App\Services\GoogleBusinessProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleReviewsAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_ratings_with_an_aria_label_expose_an_image_role(): void
    {
        $this->mock(GoogleBusinessProfile::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andReturn([
                'rating' => 4.9,
                'reviewCount' => 42,
                'recentReviews' => [
                    [
                        'author' => 'Avery K.',
                        'rating' => 5,
                        'excerpt' => 'Calm, kind, and the photos are unreal.',
                        'date' => 'Mar 5, 2026',
                    ],
                ],
            ]);
            $mock->shouldReceive('publicListingUrl')->andReturn('https://www.google.com/search?q=test');
        });

        $markup = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="google-reviews__rating" role="img" aria-label="4.9 out of 5 stars"', $markup);
        $this->assertStringContainsString('class="google-reviews__card-stars" role="img" aria-label="5 out of 5 stars"', $markup);
    }
}
