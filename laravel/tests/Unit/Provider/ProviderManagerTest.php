<?php

namespace Tests\Unit\Provider;

use App\Services\Provider\Driver\AnthropicProvider;
use App\Services\Provider\Driver\OpenAIProvider;
use App\Services\Provider\Exceptions\ProviderException;
use App\Services\Provider\ProviderManager;
use PHPUnit\Framework\TestCase;

class ProviderManagerTest extends TestCase
{
    private ProviderManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new ProviderManager;
    }

    public function test_register_provider(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key']);

        $result = $this->manager->register('openai', $provider);

        $this->assertSame($this->manager, $result);
        $this->assertTrue($this->manager->has('openai'));
    }

    public function test_get_provider(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key']);
        $this->manager->register('openai', $provider);

        $retrieved = $this->manager->get('openai');

        $this->assertSame($provider, $retrieved);
    }

    public function test_get_provider_resolves_callable(): void
    {
        $this->manager->register('openai', fn () => new OpenAIProvider(['api_key' => 'test-key']));

        $provider = $this->manager->get('openai');

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_get_provider_caches_resolved_instance(): void
    {
        $this->manager->register('openai', fn () => new OpenAIProvider(['api_key' => 'test-key']));

        $first = $this->manager->get('openai');
        $second = $this->manager->get('openai');

        $this->assertSame($first, $second);
    }

    public function test_get_unregistered_provider_throws_exception(): void
    {
        $this->expectException(ProviderException::class);
        $this->expectExceptionMessage("Provider 'unknown' is not registered");

        $this->manager->get('unknown');
    }

    public function test_has_provider(): void
    {
        $this->assertFalse($this->manager->has('openai'));

        $this->manager->register('openai', new OpenAIProvider(['api_key' => 'test-key']));

        $this->assertTrue($this->manager->has('openai'));
    }

    public function test_get_registered_providers(): void
    {
        $this->manager->register('openai', new OpenAIProvider(['api_key' => 'test-key']));
        $this->manager->register('anthropic', new AnthropicProvider(['api_key' => 'test-key']));

        $providers = $this->manager->getRegisteredProviders();

        $this->assertCount(2, $providers);
        $this->assertContains('openai', $providers);
        $this->assertContains('anthropic', $providers);
    }

    public function test_forget_provider(): void
    {
        $this->manager->register('openai', new OpenAIProvider(['api_key' => 'test-key']));
        $this->assertTrue($this->manager->has('openai'));

        $result = $this->manager->forget('openai');

        $this->assertSame($this->manager, $result);
        $this->assertFalse($this->manager->has('openai'));
    }

    public function test_clear_resolved(): void
    {
        $this->manager->register('openai', fn () => new OpenAIProvider(['api_key' => 'test-key']));
        $this->manager->get('openai');

        $result = $this->manager->clearResolved();

        $this->assertSame($this->manager, $result);
    }

    public function test_extend_multiple_providers(): void
    {
        $this->manager->extend([
            'openai' => new OpenAIProvider(['api_key' => 'test-key']),
            'anthropic' => new AnthropicProvider(['api_key' => 'test-key']),
        ]);

        $this->assertTrue($this->manager->has('openai'));
        $this->assertTrue($this->manager->has('anthropic'));
    }

    public function test_dynamic_method_call(): void
    {
        $provider = new OpenAIProvider(['api_key' => 'test-key']);
        $this->manager->register('openai', $provider);

        $retrieved = $this->manager->openai();

        $this->assertSame($provider, $retrieved);
    }

    public function test_dynamic_method_call_throws_for_unknown(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $this->manager->unknown();
    }
}
