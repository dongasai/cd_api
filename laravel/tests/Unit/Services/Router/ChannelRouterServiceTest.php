<?php

namespace Tests\Unit\Services\Router;

use App\Models\Channel;
use App\Services\Router\ChannelRouterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChannelRouterServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChannelRouterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ChannelRouterService::class);
    }

    /** @test */
    public function it_filters_channels_with_passthrough_protocol_mismatch()
    {
        // 创建 OpenAI 格式渠道（未开启透传）
        $openaiChannel = Channel::factory()->create([
            'name' => 'OpenAI Channel',
            'provider' => 'openai',
            'status' => 'active',
            'config' => ['body_passthrough' => false],
        ]);

        // 创建 Anthropic 渠道（开启透传）
        $anthropicPassthroughChannel = Channel::factory()->create([
            'name' => 'Anthropic Passthrough Channel',
            'provider' => 'anthropic',
            'status' => 'active',
            'config' => ['body_passthrough' => true],
        ]);

        // 创建 OpenAI 渠道（开启透传）
        $openaiPassthroughChannel = Channel::factory()->create([
            'name' => 'OpenAI Passthrough Channel',
            'provider' => 'openai',
            'status' => 'active',
            'config' => ['body_passthrough' => true],
        ]);

        // 创建模型映射
        \App\Models\ChannelModel::create([
            'channel_id' => $openaiChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);
        \App\Models\ChannelModel::create([
            'channel_id' => $anthropicPassthroughChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);
        \App\Models\ChannelModel::create([
            'channel_id' => $openaiPassthroughChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);

        // 清除缓存
        Cache::flush();

        // 测试：源协议为 openai，应该排除 Anthropic 透传渠道
        $selectedChannel = $this->service->selectChannel('gpt-4', [
            'source_protocol' => 'openai',
        ]);

        // 应该选择 OpenAI 渠道（优先选择权重高的）
        $this->assertNotNull($selectedChannel);
        $this->assertNotEquals($anthropicPassthroughChannel->id, $selectedChannel->id);
        $this->assertTrue(in_array($selectedChannel->id, [
            $openaiChannel->id,
            $openaiPassthroughChannel->id,
        ]));
    }

    /** @test */
    public function it_allows_channels_without_passthrough_regardless_of_protocol()
    {
        // 创建 OpenAI 渠道（未开启透传）
        $openaiChannel = Channel::factory()->create([
            'name' => 'OpenAI Channel',
            'provider' => 'openai',
            'status' => 'active',
            'config' => ['body_passthrough' => false],
            'priority' => 10,
        ]);

        // 创建 Anthropic 渠道（未开启透传）
        $anthropicChannel = Channel::factory()->create([
            'name' => 'Anthropic Channel',
            'provider' => 'anthropic',
            'status' => 'active',
            'config' => ['body_passthrough' => false],
            'priority' => 5,
        ]);

        // 创建模型映射
        \App\Models\ChannelModel::create([
            'channel_id' => $openaiChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);
        \App\Models\ChannelModel::create([
            'channel_id' => $anthropicChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);

        // 清除缓存
        Cache::flush();

        // 测试：源协议为 openai，应该可以选择任意未开启透传的渠道
        $selectedChannel = $this->service->selectChannel('gpt-4', [
            'source_protocol' => 'openai',
        ]);

        $this->assertNotNull($selectedChannel);
        // OpenAI 渠道优先级更高，应该被选中
        $this->assertEquals($openaiChannel->id, $selectedChannel->id);
    }

    /** @test */
    public function it_matches_protocol_for_passthrough_channels()
    {
        // 创建 Anthropic 渠道（开启透传）
        $anthropicPassthroughChannel = Channel::factory()->create([
            'name' => 'Anthropic Passthrough',
            'provider' => 'anthropic',
            'status' => 'active',
            'config' => ['body_passthrough' => true],
        ]);

        // 创建模型映射
        \App\Models\ChannelModel::create([
            'channel_id' => $anthropicPassthroughChannel->id,
            'model_name' => 'claude-3-opus',
            'is_enabled' => true,
        ]);

        // 清除缓存
        Cache::flush();

        // 测试：源协议为 anthropic，应该可以选择 Anthropic 透传渠道
        $selectedChannel = $this->service->selectChannel('claude-3-opus', [
            'source_protocol' => 'anthropic',
        ]);

        $this->assertNotNull($selectedChannel);
        $this->assertEquals($anthropicPassthroughChannel->id, $selectedChannel->id);
    }

    /** @test */
    public function it_throws_exception_when_no_matching_channels_due_to_passthrough_filter()
    {
        // 只创建一个 Anthropic 渠道（开启透传）
        $anthropicPassthroughChannel = Channel::factory()->create([
            'name' => 'Anthropic Passthrough Only',
            'provider' => 'anthropic',
            'status' => 'active',
            'config' => ['body_passthrough' => true],
        ]);

        // 创建模型映射
        \App\Models\ChannelModel::create([
            'channel_id' => $anthropicPassthroughChannel->id,
            'model_name' => 'gpt-4',
            'is_enabled' => true,
        ]);

        // 清除缓存
        Cache::flush();

        // 测试：源协议为 openai，但没有匹配的渠道
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No available channel for model: gpt-4');

        $this->service->selectChannel('gpt-4', [
            'source_protocol' => 'openai',
        ]);
    }
}
