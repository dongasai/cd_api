<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Channel>
 */
class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company.' Channel',
            'slug' => fake()->unique()->slug,
            'provider' => fake()->randomElement(['openai', 'anthropic', 'google']),
            'base_url' => fake()->url,
            'api_key' => 'sk-'.fake()->uuid,
            'api_key_hash' => substr(hash('sha256', 'sk-'.fake()->uuid), 0, 8),
            'weight' => fake()->numberBetween(1, 10),
            'priority' => fake()->numberBetween(1, 10),
            'status' => 'active',
            'failure_count' => 0,
            'success_count' => 0,
            'total_requests' => 0,
            'total_tokens' => 0,
            'total_cost' => '0.000000',
            'avg_latency_ms' => 0,
            'success_rate' => '1.0000',
            'has_user_agent_restriction' => false,
        ];
    }
}
