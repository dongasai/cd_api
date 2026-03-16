<?php

namespace Tests\Unit\Services\ChannelAffinity;

use App\Models\ApiKey;
use App\Models\Channel;
use App\Models\ChannelAffinityRule;
use App\Models\SystemSetting;
use App\Services\ChannelAffinity\ChannelAffinityCache;
use App\Services\ChannelAffinity\ChannelAffinityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ChannelAffinityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ChannelAffinityService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(ChannelAffinityService::class);

        SystemSetting::updateOrCreate(
            ['group' => 'channel_affinity', 'key' => 'enabled'],
            ['type' => 'boolean', 'label' => '启用', 'value' => '1']
        );
        SystemSetting::updateOrCreate(
            ['group' => 'channel_affinity', 'key' => 'switch_on_success'],
            ['type' => 'boolean', 'label' => '成功后切换', 'value' => '1']
        );
    }

    public function test_get_preferred_channel_returns_null_when_disabled(): void
    {
        SystemSetting::where('key', 'enabled')->update(['value' => '0']);

        $request = Request::create('/v1/chat/completions', 'POST');

        $result = $this->service->getPreferredChannel($request, 'gpt-4');

        $this->assertFalse($result->isHit);
    }

    public function test_get_preferred_channel_returns_null_when_no_rule_matches(): void
    {
        $request = Request::create('/v1/chat/completions', 'POST');

        $result = $this->service->getPreferredChannel($request, 'gpt-4');

        $this->assertFalse($result->isHit);
    }

    public function test_get_preferred_channel_returns_cached_channel(): void
    {
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'status' => 'active',
        ]);

        $rule = ChannelAffinityRule::create([
            'name' => 'test-rule',
            'model_patterns' => '/^gpt-.*$/',
            'key_sources' => [['type' => 'api_key']],
            'key_combine_strategy' => 'first',
            'ttl_seconds' => 120,
            'is_enabled' => true,
            'priority' => 100,
        ]);

        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key' => 'sk-test123',
            'status' => 'active',
        ]);

        $request = Request::create('/v1/chat/completions', 'POST');
        $request->attributes->set('api_key', $apiKey);

        $cache = app(ChannelAffinityCache::class);
        $keyHash = substr(md5($apiKey->key), 0, 16);
        $cache->put($rule->id, $keyHash, [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'rule_id' => $rule->id,
            'key_hint' => 'sk-t***',
        ], 120);

        $result = $this->service->getPreferredChannel($request, 'gpt-4');

        $this->assertTrue($result->isHit);
        $this->assertEquals($channel->id, $result->channel->id);
    }

    public function test_record_affinity_stores_in_cache(): void
    {
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'status' => 'active',
        ]);

        ChannelAffinityRule::create([
            'name' => 'test-rule',
            'model_patterns' => '/^gpt-.*$/',
            'key_sources' => [['type' => 'api_key']],
            'key_combine_strategy' => 'first',
            'ttl_seconds' => 120,
            'is_enabled' => true,
            'priority' => 100,
        ]);

        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key' => 'sk-test123',
            'status' => 'active',
        ]);

        $request = Request::create('/v1/chat/completions', 'POST');
        $request->attributes->set('api_key', $apiKey);

        $this->service->recordAffinity($request, $channel, 'gpt-4');

        $keyHash = substr(md5($apiKey->key), 0, 16);
        $cache = app(ChannelAffinityCache::class);
        $cached = $cache->get(1, $keyHash);

        $this->assertNotNull($cached);
        $this->assertEquals($channel->id, $cached['channel_id']);
    }

    public function test_should_skip_retry_returns_true_when_configured(): void
    {
        $channel = Channel::create([
            'name' => 'Test Channel',
            'slug' => 'test-channel',
            'provider' => 'openai',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'status' => 'active',
        ]);

        $rule = ChannelAffinityRule::create([
            'name' => 'test-rule',
            'model_patterns' => '/^gpt-.*$/',
            'key_sources' => [['type' => 'api_key']],
            'key_combine_strategy' => 'first',
            'ttl_seconds' => 120,
            'skip_retry_on_failure' => true,
            'is_enabled' => true,
            'priority' => 100,
        ]);

        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key' => 'sk-test123',
            'status' => 'active',
        ]);

        $request = Request::create('/v1/chat/completions', 'POST');
        $request->attributes->set('api_key', $apiKey);

        $cache = app(ChannelAffinityCache::class);
        $keyHash = substr(md5($apiKey->key), 0, 16);
        $cache->put($rule->id, $keyHash, [
            'channel_id' => $channel->id,
            'channel_name' => $channel->name,
            'rule_id' => $rule->id,
            'key_hint' => 'sk-t***',
        ], 120);

        $this->service->getPreferredChannel($request, 'gpt-4');

        $this->assertTrue($this->service->shouldSkipRetry($request));
    }
}
