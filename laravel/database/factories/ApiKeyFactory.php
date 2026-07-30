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
            'permissions' => null,
            'allowed_models' => null,
            'model_mappings' => null,
            'allowed_groups' => null,
            'not_allowed_groups' => null,
            'allowed_tags' => null,
            'not_allowed_tags' => null,
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

    /**
     * 设置渠道分组白名单
     */
    public function withGroupWhitelist(array $slugs): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_groups' => $slugs,
        ]);
    }

    /**
     * 设置渠道分组黑名单
     */
    public function withGroupBlacklist(array $slugs): static
    {
        return $this->state(fn (array $attributes) => [
            'not_allowed_groups' => $slugs,
        ]);
    }

    /**
     * 设置渠道标签白名单
     */
    public function withTagWhitelist(array $names): static
    {
        return $this->state(fn (array $attributes) => [
            'allowed_tags' => $names,
        ]);
    }

    /**
     * 设置渠道标签黑名单
     */
    public function withTagBlacklist(array $names): static
    {
        return $this->state(fn (array $attributes) => [
            'not_allowed_tags' => $names,
        ]);
    }
}
