<?php

namespace Tests\Unit\Models;

use App\Enums\ChannelHealthStatus;
use App\Enums\ChannelStatus;
use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\ChannelTag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ApiKey 分组/标签访问控制方法测试
 */
class ApiKeyGroupTagTest extends TestCase
{
    use RefreshDatabase;

    // ─── getter 方法测试 ────────────────────────────────────────────

    public function test_get_allowed_group_slugs_returns_string_array(): void
    {
        $apiKey = ApiKey::factory()->withGroupWhitelist(['group-a', 'group-b'])->create();

        $result = $apiKey->getAllowedGroupSlugs();

        $this->assertSame(['group-a', 'group-b'], $result);
    }

    public function test_get_not_allowed_group_slugs_returns_string_array(): void
    {
        $apiKey = ApiKey::factory()->withGroupBlacklist(['blocked-group'])->create();

        $result = $apiKey->getNotAllowedGroupSlugs();

        $this->assertSame(['blocked-group'], $result);
    }

    public function test_get_allowed_tag_names_returns_string_array(): void
    {
        $apiKey = ApiKey::factory()->withTagWhitelist(['tag-x', 'tag-y'])->create();

        $result = $apiKey->getAllowedTagNames();

        $this->assertSame(['tag-x', 'tag-y'], $result);
    }

    public function test_get_not_allowed_tag_names_returns_string_array(): void
    {
        $apiKey = ApiKey::factory()->withTagBlacklist(['bad-tag'])->create();

        $result = $apiKey->getNotAllowedTagNames();

        $this->assertSame(['bad-tag'], $result);
    }

    // ─── null 安全测试 ──────────────────────────────────────────────

    public function test_null_fields_return_empty_arrays(): void
    {
        $apiKey = ApiKey::factory()->create([
            'allowed_groups' => null,
            'not_allowed_groups' => null,
            'allowed_tags' => null,
            'not_allowed_tags' => null,
        ]);

        $this->assertSame([], $apiKey->getAllowedGroupSlugs());
        $this->assertSame([], $apiKey->getNotAllowedGroupSlugs());
        $this->assertSame([], $apiKey->getAllowedTagNames());
        $this->assertSame([], $apiKey->getNotAllowedTagNames());
    }

    // ─── has* 方法测试 ──────────────────────────────────────────────

    public function test_has_group_whitelist(): void
    {
        $withWhitelist = ApiKey::factory()->withGroupWhitelist(['g1'])->create();
        $without = ApiKey::factory()->create();

        $this->assertTrue($withWhitelist->hasGroupWhitelist());
        $this->assertFalse($without->hasGroupWhitelist());
    }

    public function test_has_group_blacklist(): void
    {
        $withBlacklist = ApiKey::factory()->withGroupBlacklist(['g1'])->create();
        $without = ApiKey::factory()->create();

        $this->assertTrue($withBlacklist->hasGroupBlacklist());
        $this->assertFalse($without->hasGroupBlacklist());
    }

    public function test_has_tag_whitelist(): void
    {
        $withWhitelist = ApiKey::factory()->withTagWhitelist(['t1'])->create();
        $without = ApiKey::factory()->create();

        $this->assertTrue($withWhitelist->hasTagWhitelist());
        $this->assertFalse($without->hasTagWhitelist());
    }

    public function test_has_tag_blacklist(): void
    {
        $withBlacklist = ApiKey::factory()->withTagBlacklist(['t1'])->create();
        $without = ApiKey::factory()->create();

        $this->assertTrue($withBlacklist->hasTagBlacklist());
        $this->assertFalse($without->hasTagBlacklist());
    }

    public function test_has_group_restriction(): void
    {
        $whitelist = ApiKey::factory()->withGroupWhitelist(['g1'])->create();
        $blacklist = ApiKey::factory()->withGroupBlacklist(['g2'])->create();
        $none = ApiKey::factory()->create();

        $this->assertTrue($whitelist->hasGroupRestriction());
        $this->assertTrue($blacklist->hasGroupRestriction());
        $this->assertFalse($none->hasGroupRestriction());
    }

    public function test_has_tag_restriction(): void
    {
        $whitelist = ApiKey::factory()->withTagWhitelist(['t1'])->create();
        $blacklist = ApiKey::factory()->withTagBlacklist(['t2'])->create();
        $none = ApiKey::factory()->create();

        $this->assertTrue($whitelist->hasTagRestriction());
        $this->assertTrue($blacklist->hasTagRestriction());
        $this->assertFalse($none->hasTagRestriction());
    }

    public function test_has_any_restriction(): void
    {
        $groupOnly = ApiKey::factory()->withGroupWhitelist(['g1'])->create();
        $tagOnly = ApiKey::factory()->withTagBlacklist(['t1'])->create();
        $none = ApiKey::factory()->create();

        $this->assertTrue($groupOnly->hasAnyRestriction());
        $this->assertTrue($tagOnly->hasAnyRestriction());
        $this->assertFalse($none->hasAnyRestriction());
    }

    // ─── is* 方法测试 ───────────────────────────────────────────────

    public function test_is_group_allowed_with_blacklist(): void
    {
        $apiKey = ApiKey::factory()->withGroupBlacklist(['blocked'])->create();

        $this->assertFalse($apiKey->isGroupAllowed('blocked'));
        $this->assertTrue($apiKey->isGroupAllowed('other'));
    }

    public function test_is_group_allowed_with_whitelist(): void
    {
        $apiKey = ApiKey::factory()->withGroupWhitelist(['allowed'])->create();

        $this->assertTrue($apiKey->isGroupAllowed('allowed'));
        $this->assertFalse($apiKey->isGroupAllowed('other'));
    }

    public function test_is_group_allowed_no_restriction(): void
    {
        $apiKey = ApiKey::factory()->create();

        $this->assertTrue($apiKey->isGroupAllowed('anything'));
    }

    public function test_is_tag_allowed_with_blacklist(): void
    {
        $apiKey = ApiKey::factory()->withTagBlacklist(['bad'])->create();

        $this->assertFalse($apiKey->isTagAllowed('bad'));
        $this->assertTrue($apiKey->isTagAllowed('good'));
    }

    public function test_is_tag_allowed_with_whitelist(): void
    {
        $apiKey = ApiKey::factory()->withTagWhitelist(['good'])->create();

        $this->assertTrue($apiKey->isTagAllowed('good'));
        $this->assertFalse($apiKey->isTagAllowed('other'));
    }

    public function test_is_tag_allowed_no_restriction(): void
    {
        $apiKey = ApiKey::factory()->create();

        $this->assertTrue($apiKey->isTagAllowed('anything'));
    }

    // ─── isChannelAllowedWithGroupsAndTags 测试 ─────────────────────

    public function test_is_channel_allowed_with_groups_and_tags(): void
    {
        $group = ChannelGroup::create(['name' => 'G1', 'slug' => 'g1']);
        $tag = ChannelTag::create(['name' => 'T1']);

        $channel = Channel::factory()->create([
            'status' => ChannelStatus::ACTIVE,
            'status2' => ChannelHealthStatus::NORMAL,
        ]);
        $channel->groups()->attach($group);
        $channel->tags()->attach($tag);

        // 无限制 → 允许
        $apiKey = ApiKey::factory()->create();
        $this->assertTrue($apiKey->isChannelAllowedWithGroupsAndTags($channel));

        // 分组白名单匹配 → 允许
        $apiKeyWhitelist = ApiKey::factory()->withGroupWhitelist(['g1'])->create();
        $this->assertTrue($apiKeyWhitelist->isChannelAllowedWithGroupsAndTags($channel));

        // 标签白名单匹配 → 允许
        $apiKeyTagWhitelist = ApiKey::factory()->withTagWhitelist(['T1'])->create();
        $this->assertTrue($apiKeyTagWhitelist->isChannelAllowedWithGroupsAndTags($channel));
    }

    public function test_is_channel_denied_by_group_blacklist(): void
    {
        $group = ChannelGroup::create(['name' => 'Blocked', 'slug' => 'blocked']);
        $channel = Channel::factory()->create([
            'status' => ChannelStatus::ACTIVE,
            'status2' => ChannelHealthStatus::NORMAL,
        ]);
        $channel->groups()->attach($group);

        $apiKey = ApiKey::factory()->withGroupBlacklist(['blocked'])->create();

        $this->assertFalse($apiKey->isChannelAllowedWithGroupsAndTags($channel));
    }

    public function test_is_channel_denied_by_tag_blacklist(): void
    {
        $tag = ChannelTag::create(['name' => 'BadTag']);
        $channel = Channel::factory()->create([
            'status' => ChannelStatus::ACTIVE,
            'status2' => ChannelHealthStatus::NORMAL,
        ]);
        $channel->tags()->attach($tag);

        $apiKey = ApiKey::factory()->withTagBlacklist(['BadTag'])->create();

        $this->assertFalse($apiKey->isChannelAllowedWithGroupsAndTags($channel));
    }
}
