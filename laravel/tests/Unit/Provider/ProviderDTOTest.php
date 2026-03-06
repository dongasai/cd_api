<?php

namespace Tests\Unit\Provider;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\TokenUsage;
use PHPUnit\Framework\TestCase;

class ProviderDTOTest extends TestCase
{
    public function test_provider_request_from_array(): void
    {
        $request = ProviderRequest::fromArray([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ]);

        $this->assertEquals('gpt-4o', $request->model);
        $this->assertCount(1, $request->messages);
        $this->assertEquals(0.7, $request->temperature);
        $this->assertEquals(1000, $request->maxTokens);
    }

    public function test_provider_request_to_openai_format(): void
    {
        $request = new ProviderRequest(
            model: 'gpt-4o',
            messages: [['role' => 'user', 'content' => 'Hello']],
            temperature: 0.7,
            maxTokens: 1000,
            systemPrompt: 'You are helpful.'
        );

        $openai = $request->toOpenAIFormat();

        $this->assertEquals('gpt-4o', $openai['model']);
        $this->assertEquals(0.7, $openai['temperature']);
        $this->assertEquals(1000, $openai['max_tokens']);
        $this->assertCount(2, $openai['messages']);
        $this->assertEquals('system', $openai['messages'][0]['role']);
        $this->assertEquals('You are helpful.', $openai['messages'][0]['content']);
    }

    public function test_provider_request_to_anthropic_format(): void
    {
        $request = new ProviderRequest(
            model: 'claude-3-5-sonnet-20241022',
            messages: [['role' => 'user', 'content' => 'Hello']],
            temperature: 0.7,
            maxTokens: 1000,
            systemPrompt: 'You are helpful.'
        );

        $anthropic = $request->toAnthropicFormat();

        $this->assertEquals('claude-3-5-sonnet-20241022', $anthropic['model']);
        $this->assertEquals(1000, $anthropic['max_tokens']);
        $this->assertEquals('You are helpful.', $anthropic['system']);
        $this->assertEquals(0.7, $anthropic['temperature']);
    }

    public function test_provider_request_has_tools(): void
    {
        $requestWithoutTools = new ProviderRequest(model: 'gpt-4o', messages: []);
        $this->assertFalse($requestWithoutTools->hasTools());

        $requestWithTools = new ProviderRequest(
            model: 'gpt-4o',
            messages: [],
            tools: [['type' => 'function', 'function' => ['name' => 'test']]]
        );
        $this->assertTrue($requestWithTools->hasTools());
    }

    public function test_token_usage_creation(): void
    {
        $usage = new TokenUsage(100, 50, 150);

        $this->assertEquals(100, $usage->promptTokens);
        $this->assertEquals(50, $usage->completionTokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    public function test_token_usage_auto_calculate_total(): void
    {
        $usage = new TokenUsage(100, 50);

        $this->assertEquals(150, $usage->totalTokens);
    }

    public function test_token_usage_from_openai(): void
    {
        $usage = TokenUsage::fromOpenAI([
            'prompt_tokens' => 100,
            'completion_tokens' => 50,
            'total_tokens' => 150,
        ]);

        $this->assertEquals(100, $usage->promptTokens);
        $this->assertEquals(50, $usage->completionTokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    public function test_token_usage_from_anthropic(): void
    {
        $usage = TokenUsage::fromAnthropic([
            'input_tokens' => 100,
            'output_tokens' => 50,
        ]);

        $this->assertEquals(100, $usage->promptTokens);
        $this->assertEquals(50, $usage->completionTokens);
        $this->assertEquals(150, $usage->totalTokens);
    }

    public function test_token_usage_to_openai(): void
    {
        $usage = new TokenUsage(100, 50, 150);

        $openai = $usage->toOpenAI();

        $this->assertEquals(100, $openai['prompt_tokens']);
        $this->assertEquals(50, $openai['completion_tokens']);
        $this->assertEquals(150, $openai['total_tokens']);
    }

    public function test_token_usage_to_anthropic(): void
    {
        $usage = new TokenUsage(100, 50, 150);

        $anthropic = $usage->toAnthropic();

        $this->assertEquals(100, $anthropic['input_tokens']);
        $this->assertEquals(50, $anthropic['output_tokens']);
    }

    public function test_token_usage_add(): void
    {
        $usage1 = new TokenUsage(100, 50, 150);
        $usage2 = new TokenUsage(20, 30, 50);

        $result = $usage1->add($usage2);

        $this->assertEquals(120, $result->promptTokens);
        $this->assertEquals(80, $result->completionTokens);
        $this->assertEquals(200, $result->totalTokens);
    }

    public function test_provider_response_from_openai(): void
    {
        $response = ProviderResponse::fromOpenAI([
            'id' => 'chatcmpl-123',
            'model' => 'gpt-4o',
            'choices' => [
                [
                    'message' => ['content' => 'Hello!'],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => [
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
                'total_tokens' => 15,
            ],
        ]);

        $this->assertEquals('chatcmpl-123', $response->id);
        $this->assertEquals('gpt-4o', $response->model);
        $this->assertEquals('Hello!', $response->content);
        $this->assertEquals('stop', $response->finishReason);
        $this->assertEquals(10, $response->usage->promptTokens);
    }

    public function test_provider_response_from_anthropic(): void
    {
        $response = ProviderResponse::fromAnthropic([
            'id' => 'msg_123',
            'model' => 'claude-3-5-sonnet-20241022',
            'content' => [
                ['type' => 'text', 'text' => 'Hello!'],
            ],
            'stop_reason' => 'end_turn',
            'usage' => [
                'input_tokens' => 10,
                'output_tokens' => 5,
            ],
        ]);

        $this->assertEquals('msg_123', $response->id);
        $this->assertEquals('claude-3-5-sonnet-20241022', $response->model);
        $this->assertEquals('Hello!', $response->content);
        $this->assertEquals('stop', $response->finishReason);
        $this->assertEquals(10, $response->usage->promptTokens);
    }

    public function test_provider_response_to_openai(): void
    {
        $response = new ProviderResponse(
            id: 'chatcmpl-123',
            model: 'gpt-4o',
            content: 'Hello!',
            finishReason: 'stop',
            usage: new TokenUsage(10, 5, 15)
        );

        $openai = $response->toOpenAI();

        $this->assertEquals('chatcmpl-123', $openai['id']);
        $this->assertEquals('chat.completion', $openai['object']);
        $this->assertEquals('gpt-4o', $openai['model']);
        $this->assertEquals('stop', $openai['choices'][0]['finish_reason']);
    }

    public function test_provider_response_has_tool_calls(): void
    {
        $responseWithoutTools = new ProviderResponse(id: '123', model: 'gpt-4o');
        $this->assertFalse($responseWithoutTools->hasToolCalls());

        $responseWithTools = new ProviderResponse(
            id: '123',
            model: 'gpt-4o',
            toolCalls: [['id' => 'call_1', 'type' => 'function']]
        );
        $this->assertTrue($responseWithTools->hasToolCalls());
    }
}
