<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\SocialAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialAccount>
 */
class SocialAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => Platform::X,
            'account_id' => (string) fake()->randomNumber(9, true),
            'account_handle' => fake()->userName(),
            'account_urn' => null,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'scopes' => ['tweet.read', 'tweet.write', 'users.read', 'offline.access'],
            'expires_at' => now()->addHours(2),
        ];
    }

    public function forPlatform(Platform $platform): static
    {
        return $this->state(fn (): array => ['platform' => $platform]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinutes(5)]);
    }
}
