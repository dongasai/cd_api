<?php

namespace Database\Factories;

use App\Models\ChannelModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChannelModel>
 */
class ChannelModelFactory extends Factory
{
    protected $model = ChannelModel::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modelName = fake()->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-opus', 'claude-3-sonnet']);

        return [
            'channel_id' => 1, // 需要在创建时指定
            'model_name' => $modelName,
            'display_name' => strtoupper($modelName),
            'mapped_model' => null,
            'is_default' => false,
            'is_enabled' => true,
            'rpm_limit' => null,
            'context_length' => null,
            'multiplier' => '1.0000',
            'config' => [],
        ];
    }

    /**
     * 设置所属渠道
     *
     * @param  int  $channelId  渠道 ID
     */
    public function forChannel(int $channelId): static
    {
        return $this->state(fn (array $attributes) => [
            'channel_id' => $channelId,
        ]);
    }

    /**
     * 设置为默认模型
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * 设置为禁用状态
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_enabled' => false,
        ]);
    }
}
