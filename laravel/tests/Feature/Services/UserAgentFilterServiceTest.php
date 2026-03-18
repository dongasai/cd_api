<?php

namespace Tests\Feature\Services;

use App\Models\Channel;
use App\Models\UserAgent;
use App\Services\Router\UserAgentFilterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAgentFilterServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_filters_channels_by_user_agent()
    {
        // 创建渠道
        $channel1 = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'name' => 'Channel 1',
            'status' => 'active',
        ]);
        $channel2 = Channel::factory()->create([
            'has_user_agent_restriction' => false,
            'name' => 'Channel 2',
            'status' => 'active',
        ]);
        $channel3 = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'name' => 'Channel 3',
            'status' => 'active',
        ]);

        // 创建User-Agent规则
        $chrome = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
        ]);

        // 关联规则
        $channel1->allowedUserAgents()->attach($chrome); // 只允许Chrome
        $channel3->allowedUserAgents()->attach($chrome); // 只允许Chrome

        // 测试过滤
        $service = app(UserAgentFilterService::class);
        $channels = collect([$channel1, $channel2, $channel3]);

        // Chrome请求：应该保留所有渠道
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Chrome/120.0.0.0');
        $this->assertCount(3, $filtered);

        // Firefox请求：应该只保留channel2（无限制）
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Firefox/121.0');
        $this->assertCount(1, $filtered);
        $this->assertEquals('Channel 2', $filtered->first()->name);
    }

    /** @test */
    public function it_does_not_filter_when_user_agent_is_empty()
    {
        $channel1 = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);

        $chrome = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
        ]);
        $channel1->allowedUserAgents()->attach($chrome);

        $service = app(UserAgentFilterService::class);
        $channels = collect([$channel1]);

        // 空User-Agent不应该过滤
        $filtered = $service->filterChannels($channels, '');
        $this->assertCount(1, $filtered);
    }

    /** @test */
    public function it_returns_empty_collection_when_all_channels_filtered()
    {
        $channel1 = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);
        $channel2 = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);

        $chrome = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
        ]);

        $channel1->allowedUserAgents()->attach($chrome);
        $channel2->allowedUserAgents()->attach($chrome);

        $service = app(UserAgentFilterService::class);
        $channels = collect([$channel1, $channel2]);

        // Firefox请求：所有渠道都应该被过滤掉
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Firefox/121.0');
        $this->assertCount(0, $filtered);
    }

    /** @test */
    public function it_handles_multiple_user_agent_rules()
    {
        $channel = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);

        $chrome = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
        ]);
        $firefox = UserAgent::create([
            'name' => 'Firefox',
            'patterns' => ['Firefox\/[0-9]+'],
        ]);

        $channel->allowedUserAgents()->attach([$chrome->id, $firefox->id]);

        $service = app(UserAgentFilterService::class);
        $channels = collect([$channel]);

        // Chrome请求
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Chrome/120.0.0.0');
        $this->assertCount(1, $filtered);

        // Firefox请求
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Firefox/121.0');
        $this->assertCount(1, $filtered);

        // Safari请求
        $filtered = $service->filterChannels($channels, 'Mozilla/5.0 Safari/604.1');
        $this->assertCount(0, $filtered);
    }
}
