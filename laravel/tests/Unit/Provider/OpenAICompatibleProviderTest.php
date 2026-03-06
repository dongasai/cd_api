<?php

namespace Tests\Unit\Provider;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\Driver\OpenAICompatibleProvider;
use PHPUnit\Framework\TestCase;

class OpenAICompatibleProviderTest extends TestCase
{
    public function test_create_deepseek_provider(): void
    {
        $provider = OpenAICompatibleProvider::createDeepSeek('test-api-key');

        $this->assertEquals('deepseek', $provider->getProviderName());
        $this->assertContains('deepseek-chat', $provider->getModels());
        $this->assertContains('deepseek-coder', $provider->getModels());
    }

    public function test_create_zhipu_provider(): void
    {
        $provider = OpenAICompatibleProvider::createZhipu('test-api-key');

        $this->assertEquals('zhipu', $provider->getProviderName());
        $this->assertContains('glm-4', $provider->getModels());
        $this->assertContains('glm-4-flash', $provider->getModels());
    }

    public function test_create_moonshot_provider(): void
    {
        $provider = OpenAICompatibleProvider::createMoonshot('test-api-key');

        $this->assertEquals('moonshot', $provider->getProviderName());
        $this->assertContains('moonshot-v1-8k', $provider->getModels());
    }

    public function test_create_local_provider(): void
    {
        $provider = OpenAICompatibleProvider::createLocal('http://localhost:8080', 'local-key');

        $this->assertEquals('local', $provider->getProviderName());
    }

    public function test_create_ollama_provider(): void
    {
        $provider = OpenAICompatibleProvider::createOllama('http://localhost:11434');

        $this->assertEquals('ollama', $provider->getProviderName());
    }

    public function test_custom_base_url(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
        ]);

        $this->assertEquals('custom', $provider->getProviderName());
    }

    public function test_custom_headers(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
            'headers' => [
                'X-Custom-Header' => 'custom-value',
            ],
        ]);

        $headers = $provider->getHeaders();

        $this->assertArrayHasKey('X-Custom-Header', $headers);
        $this->assertEquals('custom-value', $headers['X-Custom-Header']);
    }

    public function test_custom_auth_header(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
            'auth_header' => 'X-API-Key',
            'auth_prefix' => '',
        ]);

        $headers = $provider->getHeaders();

        $this->assertArrayHasKey('X-API-Key', $headers);
        $this->assertEquals('test-key', $headers['X-API-Key']);
    }

    public function test_get_endpoint(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
        ]);

        $request = new ProviderRequest(model: 'custom-model', messages: []);
        $endpoint = $provider->getEndpoint($request);

        $this->assertEquals('/v1/chat/completions', $endpoint);
    }

    public function test_build_request_body_uses_openai_format(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
        ]);

        $request = new ProviderRequest(
            model: 'custom-model',
            messages: [['role' => 'user', 'content' => 'Hello']],
            temperature: 0.5,
            maxTokens: 500
        );

        $body = $provider->buildRequestBody($request);

        $this->assertEquals('custom-model', $body['model']);
        $this->assertIsArray($body['messages']);
        $this->assertEquals(0.5, $body['temperature']);
        $this->assertEquals(500, $body['max_tokens']);
    }

    public function test_custom_models(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
            'models' => ['model-a', 'model-b', 'model-c'],
        ]);

        $models = $provider->getModels();

        $this->assertCount(3, $models);
        $this->assertContains('model-a', $models);
        $this->assertContains('model-b', $models);
        $this->assertContains('model-c', $models);
    }

    public function test_is_available_with_api_key(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => 'test-key',
        ]);

        $this->assertTrue($provider->isAvailable());
    }

    public function test_is_not_available_without_api_key(): void
    {
        $provider = new OpenAICompatibleProvider([
            'name' => 'custom',
            'base_url' => 'https://api.custom.com',
            'api_key' => '',
        ]);

        $this->assertFalse($provider->isAvailable());
    }
}
