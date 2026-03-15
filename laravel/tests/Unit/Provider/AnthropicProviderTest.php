<?php

namespace Tests\Unit\Provider;

use App\Services\Provider\Driver\AnthropicProvider;
use App\Services\Shared\DTO\Message;
use App\Services\Shared\DTO\Request;
use App\Services\Shared\DTO\Response;
use App\Services\Shared\Enums\MessageRole;
use PHPUnit\Framework\TestCase;

class AnthropicProviderTest extends TestCase
{
    private AnthropicProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new AnthropicProvider(['api_key' => 'test-api-key']);
    }

    public function test_get_provider_name(): void
    {
        $this->assertEquals('anthropic', $this->provider->getProviderName());
    }

    public function test_get_models(): void
    {
        $models = $this->provider->getModels();

        $this->assertIsArray($models);
        $this->assertContains('claude-3-5-sonnet-20241022', $models);
        $this->assertContains('claude-3-opus-20240229', $models);
        $this->assertContains('claude-3-haiku-20240307', $models);
    }

    public function test_build_request_body(): void
    {
        $request = new Request(
            model: 'claude-3-5-sonnet-20241022',
            messages: [
                new Message(
                    role: MessageRole::User,
                    content: 'Hello'
                ),
            ],
            temperature: 0.7,
            maxTokens: 1000,
            system: 'You are a helpful assistant.'
        );

        $body = $this->provider->buildRequestBody($request);

        $this->assertEquals('claude-3-5-sonnet-20241022', $body['model']);
        $this->assertEquals(1000, $body['max_tokens']);
        $this->assertIsArray($body['messages']);
        $this->assertEquals('You are a helpful assistant.', $body['system']);
        $this->assertEquals(0.7, $body['temperature']);
    }

    public function test_parse_response(): void
    {
        $rawResponse = [
            'id' => 'msg_123',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-3-5-sonnet-20241022',
            'content' => [
                [
                    'type' => 'text',
                    'text' => 'Hello! How can I help you?',
                ],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 20,
            ],
        ];

        $response = $this->provider->parseResponse($rawResponse);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('msg_123', $response->id);
        $this->assertEquals('claude-3-5-sonnet-20241022', $response->model);
        $this->assertEquals('Hello! How can I help you?', $response->getContent());
        $this->assertEquals('end_turn', $response->finishReason?->value);
        $this->assertEquals(10, $response->usage?->inputTokens);
        $this->assertEquals(20, $response->usage?->outputTokens);
    }

    public function test_parse_response_with_tool_use(): void
    {
        $rawResponse = [
            'id' => 'msg_123',
            'model' => 'claude-3-5-sonnet-20241022',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_123',
                    'name' => 'get_weather',
                    'input' => ['location' => 'Beijing'],
                ],
            ],
            'stop_reason' => 'tool_use',
        ];

        $response = $this->provider->parseResponse($rawResponse);

        $this->assertNotNull($response->toolCalls);
        $this->assertCount(1, $response->toolCalls);
        $this->assertEquals('toolu_123', $response->toolCalls[0]->id);
        $this->assertEquals('tool_use', $response->finishReason?->value);
    }

    public function test_default_base_url(): void
    {
        $this->assertEquals('https://api.anthropic.com/v1', $this->provider->getDefaultBaseUrl());
    }

    public function test_get_endpoint(): void
    {
        $request = new Request(
            model: 'claude-3-5-sonnet-20241022',
            messages: []
        );

        $endpoint = $this->provider->getEndpoint($request);

        $this->assertEquals('/messages', $endpoint);
    }

    public function test_get_headers(): void
    {
        $headers = $this->provider->getHeaders();

        $this->assertArrayHasKey('x-api-key', $headers);
        $this->assertEquals('test-api-key', $headers['x-api-key']);
        $this->assertArrayHasKey('anthropic-version', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertEquals('application/json', $headers['Content-Type']);
    }
}
