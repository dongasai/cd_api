<?php

namespace Tests\Unit\Models;

use App\Models\UserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAgentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_match_user_agent_with_single_pattern()
    {
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
            'is_enabled' => true,
        ]);

        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36'));
        $this->assertFalse($userAgent->matches('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_can_match_user_agent_with_multiple_patterns()
    {
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+', 'CriOS\/[0-9]+', 'Mobile.*Chrome'],
            'is_enabled' => true,
        ]);

        // 匹配桌面Chrome
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0 Safari/537.36'));

        // 匹配移动Chrome (CriOS)
        $this->assertTrue($userAgent->matches('Mozilla/5.0 CriOS/120.0.6099.119 Mobile/15E148'));

        // 匹配移动Chrome (Mobile.*Chrome)
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Mobile Chrome/120.0.0.0'));

        // 不匹配Firefox
        $this->assertFalse($userAgent->matches('Mozilla/5.0 Firefox/121.0'));
    }

    /** @test */
    public function it_does_not_match_when_disabled()
    {
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
            'is_enabled' => false,
        ]);

        $this->assertFalse($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_does_not_match_when_patterns_empty()
    {
        $userAgent = UserAgent::create([
            'name' => 'Empty',
            'patterns' => [],
            'is_enabled' => true,
        ]);

        $this->assertFalse($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_can_record_hit()
    {
        $userAgent = UserAgent::create([
            'name' => 'Chrome',
            'patterns' => ['Chrome\/[0-9]+'],
            'hit_count' => 0,
        ]);

        $userAgent->recordHit();

        $this->assertEquals(1, $userAgent->hit_count);
        $this->assertNotNull($userAgent->last_hit_at);
    }

    /** @test */
    public function it_handles_invalid_regex_gracefully()
    {
        $userAgent = UserAgent::create([
            'name' => 'Mixed',
            'patterns' => ['Invalid[Regex', 'Chrome\/[0-9]+'],
            'is_enabled' => true,
        ]);

        // 第一个正则无效，但第二个正则有效且匹配
        $this->assertTrue($userAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));

        // 两个正则都无效
        $invalidUserAgent = UserAgent::create([
            'name' => 'Invalid',
            'patterns' => ['Invalid[Regex', 'Another[Invalid'],
            'is_enabled' => true,
        ]);

        // 应该返回false而不是抛出异常
        $this->assertFalse($invalidUserAgent->matches('Mozilla/5.0 Chrome/120.0.0.0'));
    }

    /** @test */
    public function it_validates_patterns_on_save()
    {
        $this->expectException(\InvalidArgumentException::class);

        UserAgent::create([
            'name' => 'Invalid',
            'patterns' => ['Invalid[Regex'],
        ]);
    }

    /** @test */
    public function it_can_get_pattern_count()
    {
        $userAgent = UserAgent::create([
            'name' => 'Multi',
            'patterns' => ['Chrome\/[0-9]+', 'CriOS\/[0-9]+', 'Mobile.*Chrome'],
        ]);

        $this->assertEquals(3, $userAgent->getPatternCount());
    }
}
