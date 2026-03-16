<?php

namespace Tests\Unit\Services\Shared\DTO;

use App\Services\Shared\DTO\ContentBlock;
use App\Services\Shared\DTO\Message;
use App\Services\Shared\Enums\MessageRole;
use Tests\TestCase;

class MessageTest extends TestCase
{
    /**
     * 测试 Message::toOpenAI() 方法过滤 tool_use 和 tool_result 后数组索引正确
     */
    public function test_to_openai_filters_tool_blocks_and_reindexes_array(): void
    {
        // 创建包含 tool_use 和 text 块的消息
        $message = new Message(
            role: MessageRole::User,
            content: null,
            contentBlocks: [
                new ContentBlock(
                    type: 'tool_use',
                    toolId: 'tool_123',
                    toolName: 'test_tool',
                    toolInput: ['arg' => 'value']
                ),
                new ContentBlock(
                    type: 'text',
                    text: 'Hello world'
                ),
                new ContentBlock(
                    type: 'image_url',
                    imageUrl: 'https://example.com/image.png'
                ),
            ]
        );

        $result = $message->toOpenAI();

        // 验证 role 正确
        $this->assertEquals('user', $result['role']);

        // 验证 content 是数组(不是对象)
        $this->assertIsArray($result['content']);

        // 验证数组索引从 0 开始
        $this->assertEquals([0, 1], array_keys($result['content']));

        // 验证过滤后的内容正确
        $this->assertEquals('text', $result['content'][0]['type']);
        $this->assertEquals('Hello world', $result['content'][0]['text']);

        $this->assertEquals('image_url', $result['content'][1]['type']);
        $this->assertEquals('https://example.com/image.png', $result['content'][1]['image_url']['url']);
    }

    /**
     * 测试只有 tool_use 块时 content 为 null
     */
    public function test_to_openai_with_only_tool_blocks_returns_null_content(): void
    {
        $message = new Message(
            role: MessageRole::Assistant,
            content: null,
            contentBlocks: [
                new ContentBlock(
                    type: 'tool_use',
                    toolId: 'tool_123',
                    toolName: 'test_tool',
                    toolInput: ['arg' => 'value']
                ),
                new ContentBlock(
                    type: 'tool_result',
                    toolResultId: 'tool_123',
                    toolResultContent: 'result'
                ),
            ]
        );

        $result = $message->toOpenAI();

        // 验证 content 为 null
        $this->assertNull($result['content']);
    }

    /**
     * 测试包含 tool_result 块时正确过滤
     */
    public function test_to_openai_filters_tool_result_blocks(): void
    {
        $message = new Message(
            role: MessageRole::User,
            content: null,
            contentBlocks: [
                new ContentBlock(
                    type: 'text',
                    text: 'First text'
                ),
                new ContentBlock(
                    type: 'tool_result',
                    toolResultId: 'tool_456',
                    toolResultContent: 'Tool result'
                ),
                new ContentBlock(
                    type: 'text',
                    text: 'Second text'
                ),
            ]
        );

        $result = $message->toOpenAI();

        // 验证只有两个 text 块
        $this->assertCount(2, $result['content']);

        // 验证数组索引正确
        $this->assertEquals([0, 1], array_keys($result['content']));

        // 验证内容正确
        $this->assertEquals('First text', $result['content'][0]['text']);
        $this->assertEquals('Second text', $result['content'][1]['text']);
    }

    /**
     * 测试纯文本消息转换为 OpenAI 格式
     */
    public function test_to_openai_with_plain_text(): void
    {
        $message = new Message(
            role: MessageRole::User,
            content: 'Hello, how are you?'
        );

        $result = $message->toOpenAI();

        $this->assertEquals('user', $result['role']);
        $this->assertEquals('Hello, how are you?', $result['content']);
    }

    /**
     * 测试 Tool 角色消息转换
     */
    public function test_to_openai_with_tool_role(): void
    {
        $message = new Message(
            role: MessageRole::Tool,
            content: 'Tool execution result',
            toolCallId: 'call_123'
        );

        $result = $message->toOpenAI();

        $this->assertEquals('tool', $result['role']);
        $this->assertEquals('Tool execution result', $result['content']);
        $this->assertEquals('call_123', $result['tool_call_id']);
    }

    /**
     * 测试 JSON 编码结果为数组而不是对象
     */
    public function test_json_encode_content_as_array_not_object(): void
    {
        $message = new Message(
            role: MessageRole::User,
            content: null,
            contentBlocks: [
                new ContentBlock(
                    type: 'tool_use',
                    toolId: 'tool_123',
                    toolName: 'test_tool',
                    toolInput: []
                ),
                new ContentBlock(
                    type: 'text',
                    text: 'Text block'
                ),
                new ContentBlock(
                    type: 'image_url',
                    imageUrl: 'https://example.com/img.png'
                ),
            ]
        );

        $result = $message->toOpenAI();
        $jsonContent = json_encode($result['content']);

        // 验证 JSON 字符串以 '[' 开头(数组)而不是 '{' (对象)
        $this->assertStringStartsWith('[', $jsonContent);

        // 解码并验证是数组
        $decoded = json_decode($jsonContent, true);
        $this->assertIsArray($decoded);
        $this->assertEquals([0, 1], array_keys($decoded));
    }
}
