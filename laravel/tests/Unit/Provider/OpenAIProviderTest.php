<?php

namespace Tests\Unit\Provider;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\Driver\OpenAIProvider;
use PHPUnit\Framework\TestCase;

class OpenAIProviderTest extends TestCase
{
    private OpenAIProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OpenAIProvider(['api_key' => 'test-api-key']);
    }

    public function test_get_provider_name(): void
    {
        $this->assertEquals('openai', $this->provider->getProviderName());
    }

    public function test_get_models(): void
    {
        $models = $this->provider->getModels();

        $this->assertIsArray($models);
        $this->assertContains('gpt-4o', $models);
        $this->assertContains('gpt-4-turbo', $models);
        $this->assertContains('gpt-3.5-turbo', $models);
    }

    public function test_is_available(): void
    {
        $this->assertTrue($this->provider->isAvailable());
    }

    public function test_is_not_available_without_api_key(): void
    {
        $provider = new OpenAIProvider(['api_key' => '']);

        $this->assertFalse($provider->isAvailable());
    }

    public function test_build_request_body(): void
    {
        $request = new ProviderRequest(
            model: 'gpt-4o',
            messages: [['role' => 'user', 'content' => 'Hello']],
            temperature: 0.7,
            maxTokens: 1000
        );

        $body = $this->provider->buildRequestBody($request);

        $this->assertEquals('gpt-4o', $body['model']);
        $this->assertIsArray($body['messages']);
        $this->assertEquals(0.7, $body['temperature']);
        $this->assertEquals(1000, $body['max_tokens']);
    }

    public function test_parse_response(): void
    {
        $rawResponse = [
            'id' => 'chatcmpl-123',
            'object' => 'chat.completion',
            'created' => 1234567890,
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => 'Hello! How can I help you?',
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 20,
                'total_tokens' => 30,
            ],
        ];

        $response = $this->provider->parseResponse($rawResponse);

        $this->assertInstanceOf(ProviderResponse::class, $response);
        $this->assertEquals('chatcmpl-123', $response->id);
        $this->assertEquals('gpt-4o', $response->model);
        $this->assertEquals('Hello! How can I help you?', $response->content);
        $this->assertEquals('stop', $response->finishReason);
        $this->assertEquals(10, $response->usage->promptTokens);
        $this->assertEquals(20, $response->usage->completionTokens);
        $this->assertEquals(30, $response->usage->totalTokens);
    }

    public function test_parse_response_with_tool_calls(): void
    {
        $rawResponse = [
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'message' => [
                        'role' => 'assistant',
                        'content' => null,
                        'tool_calls' => [
                            [
                                'id' => 'call_123',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_weather',
                                    'arguments' => '{"location": "Beijing"}',
                                ],
                            ],
                        ],
                    ],
                    'finish_reason' => 'tool_calls',
                ],
            ],
        ];

        $response = $this->provider->parseResponse($rawResponse);

        $this->assertTrue($response->hasToolCalls());
        $this->assertCount(1, $response->toolCalls);
        $this->assertEquals('call_123', $response->toolCalls[0]['id']);
        $this->assertEquals('tool_calls', $response->finishReason);
    }

    public function test_default_base_url(): void
    {
        $this->assertEquals('https://api.openai.com/v1', $this->provider->getDefaultBaseUrl());
    }

    public function test_custom_base_url(): void
    {
        $provider = new OpenAIProvider([
            'api_key' => 'test-key',
            'base_url' => 'https://custom.openai.com/v1',
        ]);

        $this->assertEquals('https://custom.openai.com/v1', $provider->getConfig('base_url'));
    }

    public function test_get_endpoint(): void
    {
        $request = new ProviderRequest(model: 'gpt-4o', messages: []);

        $endpoint = $this->provider->getEndpoint($request);

        $this->assertEquals('/chat/completions', $endpoint);
    }

    public function test_get_headers(): void
    {
        $headers = $this->provider->getHeaders();

        $this->assertArrayHasKey('Authorization', $headers);
        $this->assertEquals('Bearer test-api-key', $headers['Authorization']);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }
}
