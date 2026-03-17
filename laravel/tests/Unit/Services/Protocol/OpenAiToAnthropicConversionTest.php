<?php

namespace Tests\Unit\Services\Protocol;

use App\Services\Protocol\Driver\OpenAiChatCompletionsDriver;
use App\Services\Protocol\ProtocolConverter;
use Tests\TestCase;

class OpenAiToAnthropicConversionTest extends TestCase
{
    protected ProtocolConverter $converter;

    protected OpenAiChatCompletionsDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->converter = app(ProtocolConverter::class);
        $this->driver = app(OpenAiChatCompletionsDriver::class);
    }

    /**
     * 测试 OpenAI system 消息提取
     */
    public function test_extracts_system_message_from_openai_format(): void
    {
        $rawRequest = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
            'temperature' => 0.7,
        ];

        $standardRequest = $this->driver->parseRequest($rawRequest);

        // 验证 system 消息已提取到 system 字段
        $this->assertEquals('You are a helpful assistant.', $standardRequest->system);

        // 验证 messages 数组中不再包含 system 消息
        $this->assertCount(1, $standardRequest->messages);
        $this->assertEquals('user', $standardRequest->messages[0]->role->value);
        $this->assertEquals('Hello!', $standardRequest->messages[0]->content);
    }

    /**
     * 测试独立的 system 字段优先级
     */
    public function test_prioritizes_standalone_system_field(): void
    {
        $rawRequest = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'System from messages'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
            'system' => 'Standalone system field',
        ];

        $standardRequest = $this->driver->parseRequest($rawRequest);

        // 独立 system 字段应优先
        $this->assertEquals('Standalone system field', $standardRequest->system);
    }

    /**
     * 测试转换到 Anthropic 格式
     */
    public function test_converts_to_anthropic_format_correctly(): void
    {
        $rawRequest = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
            'temperature' => 0.7,
            'stream' => true,
        ];

        $standardRequest = $this->driver->parseRequest($rawRequest);
        $anthropicFormat = $standardRequest->toAnthropic(true);

        // 验证 Anthropic 格式有 system 字段
        $this->assertArrayHasKey('system', $anthropicFormat);
        $this->assertEquals('You are a helpful assistant.', $anthropicFormat['system']);

        // 验证 model 字段
        $this->assertEquals('gpt-4', $anthropicFormat['model']);

        // 验证 messages 数量（只有 user 消息）
        $this->assertCount(1, $anthropicFormat['messages']);
        $this->assertEquals('user', $anthropicFormat['messages'][0]['role']);

        // 验证 stream 字段
        $this->assertTrue($anthropicFormat['stream']);
    }

    /**
     * 测试多模态 system 消息提取
     */
    public function test_handles_multimodal_system_message(): void
    {
        $rawRequest = [
            'model' => 'gpt-4',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'text', 'text' => 'Part 1'],
                        ['type' => 'text', 'text' => 'Part 2'],
                    ],
                ],
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        ];

        $standardRequest = $this->driver->parseRequest($rawRequest);

        // 验证多模态 system 消息被合并为文本
        $this->assertEquals("Part 1\nPart 2", $standardRequest->system);
    }

    /**
     * 测试没有 system 消息的情况
     */
    public function test_handles_no_system_message(): void
    {
        $rawRequest = [
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello!'],
                ['role' => 'assistant', 'content' => 'Hi!'],
                ['role' => 'user', 'content' => 'How are you?'],
            ],
        ];

        $standardRequest = $this->driver->parseRequest($rawRequest);

        // 验证 system 字段为 null
        $this->assertNull($standardRequest->system);

        // 验证所有消息都被保留
        $this->assertCount(3, $standardRequest->messages);
    }

    /**
     * 测试完整协议转换流程
     */
    public function test_full_protocol_conversion_flow(): void
    {
        $openaiRequest = [
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'system', 'content' => 'You are Roo, a highly skilled software engineer.'],
                ['role' => 'user', 'content' => '<task>编辑AA.md</task>'],
            ],
            'temperature' => 0,
            'stream' => true,
        ];

        // 标准化请求
        $standardRequest = $this->converter->normalizeRequest($openaiRequest, 'openai');

        // 验证标准化结果
        $this->assertEquals('deepseek-chat', $standardRequest->model);
        $this->assertEquals('You are Roo, a highly skilled software engineer.', $standardRequest->system);
        $this->assertCount(1, $standardRequest->messages);

        // 转换为 Anthropic 格式
        $anthropicRequest = $standardRequest->toAnthropic(true);

        // 验证 Anthropic 格式
        $this->assertEquals('deepseek-chat', $anthropicRequest['model']);
        $this->assertArrayHasKey('system', $anthropicRequest);
        $this->assertCount(1, $anthropicRequest['messages']);
        $this->assertEquals('user', $anthropicRequest['messages'][0]['role']);
    }
}
