<?php

namespace Tests\Unit\Services\Router;

use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\ChannelModel;
use App\Models\ChannelTag;
use App\Services\Router\ChannelRouterService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * ChannelRouterService 分组/标签过滤逻辑测试
 */
class ChannelRouterGroupTagTest extends TestCase
{
    use RefreshDatabase;

    protected ChannelRouterService $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new ChannelRouterService;
    }

    /**
     * 通过反射调用 protected 方法 applyApiKeyChannelRestrictions
     */
    protected function invokeApplyRestrictions(Collection $channels, ?ApiKey $apiKey): Collection
    {
        $method = new ReflectionMethod(ChannelRouterService::class, 'applyApiKeyChannelRestrictions');
        $method->setAccessible(true);

        return $method->invoke($this->router, $channels, $apiKey);
    }

    /**
     * 创建活跃状态的渠道
     */
    protected function createActiveChannel(array $attrs = []): Channel
    {
        return Channel::factory()->create(array_merge([
            'status' => ChannelStatus::ACTIVE,
            'status2' => ChannelHealthStatus::NORMAL,
        ], $attrs));
    }

    // ─── 分组过滤测试 ───────────────────────────────────────────────

    public function test_apply_api_key_group_blacklist_filters_channels(): void
    {
        $groupA = ChannelGroup::create(['name' => 'A', 'slug' => 'group-a']);
        $groupB = ChannelGroup::create(['name' => 'B', 'slug' => 'group-b']);

        $channel1 = $this->createActiveChannel();
        $channel1->groups()->attach($groupA);

        $channel2 = $this->createActiveChannel();
        $channel2->groups()->attach($groupB);

        $channels = Collection::make([$channel1, $channel2])->load('groups');
        $apiKey = ApiKey::factory()->withGroupBlacklist(['group-a'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(1, $result);
        $this->assertEquals($channel2->id, $result->first()->id);
    }

    public function test_apply_api_key_group_whitelist_filters_channels(): void
    {
        $groupA = ChannelGroup::create(['name' => 'A', 'slug' => 'group-a']);
        $groupB = ChannelGroup::create(['name' => 'B', 'slug' => 'group-b']);

        $channel1 = $this->createActiveChannel();
        $channel1->groups()->attach($groupA);

        $channel2 = $this->createActiveChannel();
        $channel2->groups()->attach($groupB);

        $channel3 = $this->createActiveChannel(); // 无分组

        $channels = Collection::make([$channel1, $channel2, $channel3])->load('groups');
        $apiKey = ApiKey::factory()->withGroupWhitelist(['group-a'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(1, $result);
        $this->assertEquals($channel1->id, $result->first()->id);
    }

    // ─── 标签过滤测试 ───────────────────────────────────────────────

    public function test_apply_api_key_tag_blacklist_filters_channels(): void
    {
        $tag1 = ChannelTag::create(['name' => 'production']);
        $tag2 = ChannelTag::create(['name' => 'staging']);

        $channel1 = $this->createActiveChannel();
        $channel1->tags()->attach($tag1);

        $channel2 = $this->createActiveChannel();
        $channel2->tags()->attach($tag2);

        $channels = Collection::make([$channel1, $channel2])->load('tags');
        $apiKey = ApiKey::factory()->withTagBlacklist(['production'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(1, $result);
        $this->assertEquals($channel2->id, $result->first()->id);
    }

    public function test_apply_api_key_tag_whitelist_filters_channels(): void
    {
        $tag1 = ChannelTag::create(['name' => 'fast']);
        $tag2 = ChannelTag::create(['name' => 'slow']);

        $channel1 = $this->createActiveChannel();
        $channel1->tags()->attach($tag1);

        $channel2 = $this->createActiveChannel();
        $channel2->tags()->attach($tag2);

        $channel3 = $this->createActiveChannel(); // 无标签

        $channels = Collection::make([$channel1, $channel2, $channel3])->load('tags');
        $apiKey = ApiKey::factory()->withTagWhitelist(['fast'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(1, $result);
        $this->assertEquals($channel1->id, $result->first()->id);
    }

    // ─── 组合测试 ───────────────────────────────────────────────────

    public function test_channel_and_group_filter_combined(): void
    {
        $group = ChannelGroup::create(['name' => 'VIP', 'slug' => 'vip']);

        $channel1 = $this->createActiveChannel();
        $channel1->groups()->attach($group);

        $channel2 = $this->createActiveChannel();
        $channel2->groups()->attach($group);

        // 渠道黑名单排除 channel1，分组白名单只保留 vip 组
        $channels = Collection::make([$channel1, $channel2])->load('groups');
        $apiKey = ApiKey::factory()
            ->create([
                'not_allowed_channels' => [$channel1->id],
                'allowed_groups' => ['vip'],
            ]);

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(1, $result);
        $this->assertEquals($channel2->id, $result->first()->id);
    }

    public function test_group_and_tag_filter_combined(): void
    {
        $group = ChannelGroup::create(['name' => 'G1', 'slug' => 'g1']);
        $tag = ChannelTag::create(['name' => 'T1']);

        $channel1 = $this->createActiveChannel();
        $channel1->groups()->attach($group);
        $channel1->tags()->attach($tag);

        $channel2 = $this->createActiveChannel();
        $channel2->groups()->attach($group);
        // 无标签

        $channel3 = $this->createActiveChannel();
        // 无分组，有标签
        $channel3->tags()->attach($tag);

        $channels = Collection::make([$channel1, $channel2, $channel3])->load(['groups', 'tags']);
        $apiKey = ApiKey::factory()
            ->withGroupWhitelist(['g1'])
            ->withTagWhitelist(['T1'])
            ->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        // 只有 channel1 同时满足分组白名单和标签白名单
        $this->assertCount(1, $result);
        $this->assertEquals($channel1->id, $result->first()->id);
    }

    // ─── 边缘案例 ───────────────────────────────────────────────────

    public function test_deleted_group_silently_ignored(): void
    {
        // ApiKey 引用了不存在的分组 slug
        $channel = $this->createActiveChannel();
        $channels = Collection::make([$channel])->load('groups');

        $apiKey = ApiKey::factory()->withGroupWhitelist(['nonexistent-group'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        // 白名单中无匹配，渠道被过滤掉
        $this->assertCount(0, $result);
    }

    public function test_deleted_tag_silently_ignored(): void
    {
        $channel = $this->createActiveChannel();
        $channels = Collection::make([$channel])->load('tags');

        $apiKey = ApiKey::factory()->withTagBlacklist(['nonexistent-tag'])->create();

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        // 黑名单中无匹配，渠道保留
        $this->assertCount(1, $result);
    }

    public function test_no_group_tag_restriction_returns_all_channels(): void
    {
        $channel1 = $this->createActiveChannel();
        $channel2 = $this->createActiveChannel();

        $channels = Collection::make([$channel1, $channel2])->load(['groups', 'tags']);
        $apiKey = ApiKey::factory()->create(); // 无任何限制

        $result = $this->invokeApplyRestrictions($channels, $apiKey);

        $this->assertCount(2, $result);
    }
}
