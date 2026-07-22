<?php

namespace Database\Factories;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'url' => fake()->unique()->url(),
            'title' => fake()->sentence(),
            'body' => fake()->paragraphs(3, true),
            'excerpt' => fake()->paragraph(),
            'author' => fake()->name(),
            'published_at' => fake()->dateTimeBetween('-1 year'),
            'og_image_url' => fake()->imageUrl(),
            'status' => ArticleStatus::Scraped,
            'error_message' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Pending,
            'title' => null,
            'body' => null,
            'excerpt' => null,
            'author' => null,
            'published_at' => null,
            'og_image_url' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ArticleStatus::Failed,
            'error_message' => 'Could not extract readable content.',
        ]);
    }
}
