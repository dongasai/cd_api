<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelModel;
use App\Models\ModelList;
use App\Services\ModelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelsApiChannelRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建测试模型
        ModelList::create([
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'provider' => 'openai',
            'is_enabled' => true,
        ]);

        ModelList::create([
            'model_name' => 'claude-3-opus',
            'display_name' => 'Claude 3 Opus',
            'provider' => 'anthropic',
            'is_enabled' => true,
        ]);

        ModelList::create([
            'model_name' => 'deepseek-chat',
            'display_name' => 'DeepSeek Chat',
            'provider' => 'deepseek',
            'is_enabled' => true,
        ]);
    }

    public function test_models_api_without_api_key_returns_all_enabled_models(): void
    {
        $models = ModelService::getAvailableModels(null);

        $this->assertCount(3, $models);

        $modelIds = collect($models)->pluck('id')->toArray();
        $this->assertContains('gpt-4', $modelIds);
        $this->assertContains('claude-3-opus', $modelIds);
        $this->assertContains('deepseek-chat', $modelIds);
    }

    public function test_models_api_respects_allowed_channels(): void
    {
        // 创建渠道
        $channel1 = Channel::create([
            'name' => 'Channel 1',
            'slug' => 'channel-1',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        $channel2 = Channel::create([
            'name' => 'Channel 2',
            'slug' => 'channel-2',
            'provider' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'status' => 'active',
        ]);

        // 为渠道1添加模型
        ChannelModel::create([
            'channel_id' => $channel1->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        // 为渠道2添加模型
        ChannelModel::create([
            'channel_id' => $channel2->id,
            'model_name' => 'claude-3-opus',
            'display_name' => 'Claude 3 Opus',
            'is_enabled' => true,
        ]);

        // 创建只允许访问渠道1的 API Key
        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => [$channel1->id],
            'not_allowed_channels' => null,
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该只返回渠道1中的模型（gpt-4）
        $this->assertCount(1, $models);
        $this->assertEquals('gpt-4', $models[0]['id']);
        $this->assertNotContains('claude-3-opus', collect($models)->pluck('id')->toArray());
    }

    public function test_models_api_respects_not_allowed_channels(): void
    {
        // 创建渠道
        $channel1 = Channel::create([
            'name' => 'Channel 1',
            'slug' => 'channel-1',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        $channel2 = Channel::create([
            'name' => 'Channel 2',
            'slug' => 'channel-2',
            'provider' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'status' => 'active',
        ]);

        // 为渠道添加模型
        ChannelModel::create([
            'channel_id' => $channel1->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        ChannelModel::create([
            'channel_id' => $channel2->id,
            'model_name' => 'claude-3-opus',
            'display_name' => 'Claude 3 Opus',
            'is_enabled' => true,
        ]);

        // 创建禁止访问渠道2的 API Key
        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => null,
            'not_allowed_channels' => [$channel2->id],
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该只返回渠道1中的模型（gpt-4），不包含渠道2的模型
        $this->assertCount(1, $models);
        $this->assertEquals('gpt-4', $models[0]['id']);
    }

    public function test_models_api_respects_allowed_models_and_channels(): void
    {
        // 创建渠道
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        // 为渠道添加多个模型
        ChannelModel::create([
            'channel_id' => $channel->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        ChannelModel::create([
            'channel_id' => $channel->id,
            'model_name' => 'gpt-3.5-turbo',
            'display_name' => 'GPT-3.5 Turbo',
            'is_enabled' => true,
        ]);

        // 创建同时限制渠道和模型的 API Key
        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => [$channel->id],
            'allowed_models' => ['gpt-4'], // 只允许 gpt-4
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该只返回 gpt-4（既在允许的渠道中，又在允许的模型列表中）
        $this->assertCount(1, $models);
        $this->assertEquals('gpt-4', $models[0]['id']);
    }

    public function test_models_api_includes_model_aliases(): void
    {
        // 创建渠道
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        // 为渠道添加模型
        ChannelModel::create([
            'channel_id' => $channel->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        // 创建有模型映射的 API Key
        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => [$channel->id],
            'model_mappings' => [
                'cd-coding-latest' => 'gpt-4',
                'cd-coding-fast' => 'gpt-3.5-turbo',
            ],
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该包含实际模型和别名
        $modelIds = collect($models)->pluck('id')->toArray();
        $this->assertContains('gpt-4', $modelIds);
        $this->assertContains('cd-coding-latest', $modelIds);
        $this->assertContains('cd-coding-fast', $modelIds);

        // 验证别名的 owned_by 字段
        $aliasModel = collect($models)->firstWhere('id', 'cd-coding-latest');
        $this->assertEquals('cdapi', $aliasModel['owned_by']);
    }

    public function test_models_api_excludes_inactive_channels(): void
    {
        // 创建活跃和非活跃渠道
        $activeChannel = Channel::create([
            'name' => 'Active Channel',
            'slug' => 'active-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        $inactiveChannel = Channel::create([
            'name' => 'Inactive Channel',
            'slug' => 'inactive-channel',
            'provider' => 'anthropic',
            'base_url' => 'https://api.anthropic.com/v1',
            'status' => 'disabled',
        ]);

        // 为渠道添加模型
        ChannelModel::create([
            'channel_id' => $activeChannel->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        ChannelModel::create([
            'channel_id' => $inactiveChannel->id,
            'model_name' => 'claude-3-opus',
            'display_name' => 'Claude 3 Opus',
            'is_enabled' => true,
        ]);

        // 创建无限制的 API Key
        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => null,
            'not_allowed_channels' => null,
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该只返回活跃渠道中的模型
        $modelIds = collect($models)->pluck('id')->toArray();
        $this->assertContains('gpt-4', $modelIds);
        $this->assertNotContains('claude-3-opus', $modelIds);
    }

    public function test_models_api_excludes_disabled_channel_models(): void
    {
        // 创建渠道
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'status' => 'active',
        ]);

        // 添加启用的模型
        ChannelModel::create([
            'channel_id' => $channel->id,
            'model_name' => 'gpt-4',
            'display_name' => 'GPT-4',
            'is_enabled' => true,
        ]);

        // 添加禁用的模型
        ChannelModel::create([
            'channel_id' => $channel->id,
            'model_name' => 'gpt-3.5-turbo',
            'display_name' => 'GPT-3.5 Turbo',
            'is_enabled' => false,
        ]);

        $apiKey = ApiKey::factory()->create([
            'allowed_channels' => [$channel->id],
        ]);

        $models = ModelService::getAvailableModels($apiKey);

        // 应该只返回启用的模型
        $this->assertCount(1, $models);
        $this->assertEquals('gpt-4', $models[0]['id']);
    }
}
