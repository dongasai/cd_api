<?php

namespace Tests\Unit\Models;

use App\Models\Channel;
use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelUserAgentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_allows_user_agent_when_matched()
    {
        $channel = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
        ]);

        $channel->allowedUserAgents()->attach($userAgent);

        $this->assertTrue($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));
        $this->assertFalse($channel->isUserAgentAllowed('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_allows_all_user_agents_when_no_restriction()
    {
        $channel = Channel::factory()->create([
            'has_user_agent_restriction' => false,
            'status' => 'active',
        ]);

        $this->assertTrue($channel->isUserAgentAllowed('Any User-Agent'));
        $this->assertTrue($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_denies_when_restriction_enabled_but_no_patterns()
    {
        $channel = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);

        $this->assertFalse($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_records_hit_when_user_agent_matches()
    {
        $channel = Channel::factory()->create([
            'has_user_agent_restriction' => true,
            'status' => 'active',
        ]);
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
            'hit_count' => 0,
        ]);

        $channel->allowedUserAgents()->attach($userAgent);

        $this->assertEquals(0, $userAgent->hit_count);

        $channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0');

        $userAgent->refresh();
        $this->assertEquals(1, $userAgent->hit_count);
        $this->assertNotNull($userAgent->last_hit_at);
    }

    /** @test */
    public function it_checks_has_user_agent_restriction()
    {
        $channelWithRestriction = Channel::factory()->create([
            'has_user_agent_restriction' => true,
        ]);
        $channelWithoutRestriction = Channel::factory()->create([
            'has_user_agent_restriction' => false,
        ]);

        $this->assertTrue($channelWithRestriction->hasUserAgentRestriction());
        $this->assertFalse($channelWithoutRestriction->hasUserAgentRestriction());
    }

    /** @test */
    public function it_can_attach_multiple_user_agents()
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

        // 匹配Chrome
        $this->assertTrue($channel->isUserAgentAllowed('Mozilla/5.0 Chrome/120.0.0.0'));

        // 匹配Firefox
        $this->assertTrue($channel->isUserAgentAllowed('Mozilla/5.0 Firefox/121.0'));

        // 不匹配Safari
        $this->assertFalse($channel->isUserAgentAllowed('Mozilla/5.0 Safari/604.1'));
    }
}
