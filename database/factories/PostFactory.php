<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\PostStatus;
use App\Models\Article;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'platform' => fake()->randomElement(Platform::cases()),
            'body' => fake()->sentence(),
            'status' => PostStatus::Draft,
            'platform_post_id' => null,
            'platform_url' => null,
            'error_message' => null,
            'published_at' => null,
        ];
    }

    public function forPlatform(Platform $platform): static
    {
        return $this->state(fn (): array => ['platform' => $platform]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Published,
            'platform_post_id' => (string) fake()->randomNumber(9, true),
            'platform_url' => fake()->url(),
            'published_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => PostStatus::Failed,
            'error_message' => 'The remote API rejected the request.',
        ]);
    }
}
