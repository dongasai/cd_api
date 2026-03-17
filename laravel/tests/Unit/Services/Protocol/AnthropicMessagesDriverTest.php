<?php

namespace Tests\Unit\Services\Protocol;

use App\Services\Protocol\Driver\AnthropicMessagesDriver;
use Tests\TestCase;

class AnthropicMessagesDriverTest extends TestCase
{
    protected AnthropicMessagesDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = app(AnthropicMessagesDriver::class);
    }

    /**
     * 测试解析 Anthropic 格式的文本消息
     */
    public function test_parses_text_message(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        ];

        $request = $this->driver->parseRequest($rawRequest);

        $this->assertCount(1, $request->messages);
        $this->assertEquals('user', $request->messages[0]->role->value);
        $this->assertEquals('Hello!', $request->messages[0]->content);
        $this->assertNull($request->messages[0]->contentBlocks);
    }

    /**
     * 测试解析多模态消息（content blocks）
     */
    public function test_parses_multimodal_message(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'What is in this image?'],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'url',
                                'url' => 'https://example.com/image.jpg',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->driver->parseRequest($rawRequest);

        $this->assertCount(1, $request->messages);
        $this->assertNotNull($request->messages[0]->contentBlocks);
        $this->assertCount(2, $request->messages[0]->contentBlocks);

        // 验证第一个 content block 是文本
        $this->assertEquals('text', $request->messages[0]->contentBlocks[0]->type);
        $this->assertEquals('What is in this image?', $request->messages[0]->contentBlocks[0]->text);

        // 验证第二个 content block 是图片
        $this->assertEquals('image', $request->messages[0]->contentBlocks[1]->type);
        $this->assertNotNull($request->messages[0]->contentBlocks[1]->source);
    }

    /**
     * 测试转换回 Anthropic 格式
     */
    public function test_converts_back_to_anthropic_format(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello!'],
                        [
                            'type' => 'image',
                            'source' => [
                                'type' => 'url',
                                'url' => 'https://example.com/image.jpg',
                            ],
                        ],
                    ],
                ],
            ],
            'stream' => true,
        ];

        $request = $this->driver->parseRequest($rawRequest);
        $anthropicFormat = $request->toAnthropic(true);

        // 验证模型
        $this->assertEquals('claude-3-5-sonnet-20241022', $anthropicFormat['model']);

        // 验证消息
        $this->assertCount(1, $anthropicFormat['messages']);
        $this->assertEquals('user', $anthropicFormat['messages'][0]['role']);

        // 验证 content blocks
        $this->assertCount(2, $anthropicFormat['messages'][0]['content']);
        $this->assertEquals('text', $anthropicFormat['messages'][0]['content'][0]['type']);
        $this->assertEquals('Hello!', $anthropicFormat['messages'][0]['content'][0]['text']);
        $this->assertEquals('image', $anthropicFormat['messages'][0]['content'][1]['type']);

        // 验证 stream
        $this->assertTrue($anthropicFormat['stream']);
    }

    /**
     * 测试 tool_use 消息
     */
    public function test_handles_tool_use_message(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'assistant',
                    'content' => [
                        [
                            'type' => 'tool_use',
                            'id' => 'toolu_123',
                            'name' => 'get_weather',
                            'input' => ['location' => 'Beijing'],
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->driver->parseRequest($rawRequest);

        $this->assertCount(1, $request->messages);
        $this->assertNotNull($request->messages[0]->contentBlocks);
        $this->assertCount(1, $request->messages[0]->contentBlocks);
        $this->assertEquals('tool_use', $request->messages[0]->contentBlocks[0]->type);
        $this->assertEquals('toolu_123', $request->messages[0]->contentBlocks[0]->toolId);
        $this->assertEquals('get_weather', $request->messages[0]->contentBlocks[0]->toolName);
    }

    /**
     * 测试 tool_result 消息
     */
    public function test_handles_tool_result_message(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'tool_result',
                            'tool_use_id' => 'toolu_123',
                            'content' => 'Weather in Beijing: 25°C, sunny',
                        ],
                    ],
                ],
            ],
        ];

        $request = $this->driver->parseRequest($rawRequest);

        $this->assertCount(1, $request->messages);
        $this->assertNotNull($request->messages[0]->contentBlocks);
        $this->assertCount(1, $request->messages[0]->contentBlocks);
        $this->assertEquals('tool_result', $request->messages[0]->contentBlocks[0]->type);
        $this->assertEquals('toolu_123', $request->messages[0]->contentBlocks[0]->toolResultId);
        $this->assertEquals('Weather in Beijing: 25°C, sunny', $request->messages[0]->contentBlocks[0]->toolResultContent);
    }

    /**
     * 测试 system 字段
     */
    public function test_handles_system_field(): void
    {
        $rawRequest = [
            'model' => 'claude-3-5-sonnet-20241022',
            'system' => 'You are a helpful assistant.',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello!'],
            ],
        ];

        $request = $this->driver->parseRequest($rawRequest);

        $this->assertEquals('You are a helpful assistant.', $request->system);
    }
}
