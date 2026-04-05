<?php

namespace App\Services\Protocol\Driver\OpenAIResponses;

/**
 * Responses API 协议上下文
 *
 * 携带请求阶段的状态信息，传递到响应阶段用于存储
 */
class ResponsesContext
{
    public function __construct(
        public ?string $previousResponseId = null,
        public array $fullMessages = [],
        public ?int $apiKeyId = null,
    ) {}
}
