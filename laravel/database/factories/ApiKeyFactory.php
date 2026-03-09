<?php

namespace Database\Factories;

use App\Models\ApiKey;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiKey>
 */
class ApiKeyFactory extends Factory
{
    protected $model = ApiKey::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'sk-'.Str::random(48);

        return [
            'name' => fake()->name(),
            'key' => $key,
            'key_hash' => hash('sha256', $key),
            'key_prefix' => substr($key, 0, 8),
            'permissions' => null,
            'allowed_models' => null,
            'model_mappings' => null,
            'rate_limit' => null,
            'expires_at' => null,
            'last_used_at' => null,
            'status' => 'active',
        ];
    }

    /**
     * Indicate that the API key is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'revoked',
        ]);
    }

    /**
     * Indicate that the API key is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'expires_at' => now()->subDay(),
        ]);
    }
}
