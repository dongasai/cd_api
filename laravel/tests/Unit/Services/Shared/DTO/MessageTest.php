<?php

namespace Tests\Unit\Services\Shared\DTO;

use App\Services\Shared\DTO\ContentBlock;
use App\Services\Shared\DTO\Message;
use App\Services\Shared\Enums\MessageRole;
use Tests\TestCase;

class MessageTest extends TestCase
{
    /**
     * 测试 Message 可以正确创建
     */
    public function test_message_can_be_created(): void
    {
        $message = new Message;
        $message->role = MessageRole::User;
        $message->content = 'Hello, how are you?';

        $this->assertEquals('user', $message->role->value);
        $this->assertEquals('Hello, how are you?', $message->content);
    }

    /**
     * 测试 getTextContent 方法
     */
    public function test_get_text_content(): void
    {
        $message = new Message;
        $message->role = MessageRole::User;
        $message->content = 'Hello world';

        $this->assertEquals('Hello world', $message->getTextContent());
    }

    /**
     * 测试 getTextContent 方法从 contentBlocks 获取内容
     */
    public function test_get_text_content_from_content_blocks(): void
    {
        $block1 = new ContentBlock;
        $block1->type = 'text';
        $block1->text = 'First text';

        $block2 = new ContentBlock;
        $block2->type = 'text';
        $block2->text = ' Second text';

        $message = new Message;
        $message->role = MessageRole::User;
        $message->content = null;
        $message->contentBlocks = [$block1, $block2];

        $this->assertEquals('First text Second text', $message->getTextContent());
    }

    /**
     * 测试 isMultimodal 方法
     */
    public function test_is_multimodal(): void
    {
        $message = new Message;
        $message->role = MessageRole::User;
        $message->content = 'Plain text';

        $this->assertFalse($message->isMultimodal());

        $block = new ContentBlock;
        $block->type = 'text';
        $block->text = 'Text';

        $messageWithBlocks = new Message;
        $messageWithBlocks->role = MessageRole::User;
        $messageWithBlocks->content = null;
        $messageWithBlocks->contentBlocks = [$block];

        $this->assertTrue($messageWithBlocks->isMultimodal());
    }

    /**
     * 测试属性访问
     */
    public function test_property_access(): void
    {
        $message = new Message;
        $message->role = MessageRole::User;
        $message->content = 'Hello world';
        $message->name = 'test_user';

        $this->assertEquals('user', $message->role->value);
        $this->assertEquals('Hello world', $message->content);
        $this->assertEquals('test_user', $message->name);
    }
}
