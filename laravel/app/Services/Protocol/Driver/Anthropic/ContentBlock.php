<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Shared\DTO\ContentBlock as SharedContentBlock;

/**
 * Anthropic 内容块结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#response-body-content
 */
class ContentBlock
{
    use Convertible;
    use JsonSerializiable;

    /**
     * 支持的内容块类型
     */
    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_TOOL_USE = 'tool_use';

    public const TYPE_TOOL_RESULT = 'tool_result';

    public const TYPE_THINKING = 'thinking';

    public const TYPE_REDACTED_THINKING = 'redacted_thinking';

    public const TYPE_SERVER_TOOL_USE = 'server_tool_use';

    public const TYPE_WEB_SEARCH_TOOL_RESULT = 'web_search_tool_result';

    public const TYPE_WEB_FETCH_TOOL_RESULT = 'web_fetch_tool_result';

    public const TYPE_CODE_EXECUTION_TOOL_RESULT = 'code_execution_tool_result';

    public const TYPE_BASH_CODE_EXECUTION_TOOL_RESULT = 'bash_code_execution_tool_result';

    public const TYPE_TEXT_EDITOR_CODE_EXECUTION_TOOL_RESULT = 'text_editor_code_execution_tool_result';

    public const TYPE_TOOL_SEARCH_TOOL_RESULT = 'tool_search_tool_result';

    public const TYPE_CONTAINER_UPLOAD = 'container_upload';

    /**
     * @param  string  $type  类型
     * @param  string|null  $text  文本内容 (text 类型)
     * @param  array|null  $citations  文本引用列表 (text 类型，支持 PDF、纯文本、内容块的引用)
     * @param  array|null  $source  图片源 (image 类型)
     * @param  string|null  $id  工具调用 ID (tool_use 类型)
     * @param  string|null  $name  工具名称 (tool_use 类型)
     * @param  array|null  $input  工具输入 (tool_use 类型)
     * @param  array|null  $caller  调用者信息 (tool_use 类型，包含 type: direct|server_tool 等信息)
     * @param  string|null  $tool_use_id  工具结果 ID (tool_result 类型)
     * @param  string|array|null  $content  工具结果内容 (tool_result 类型)
     * @param  bool|null  $is_error  是否错误 (tool_result 类型)
     * @param  string|null  $thinking  推理内容 (thinking 类型)
     * @param  string|null  $signature  签名 (thinking 类型)
     * @param  array  $additionalData  额外字段（用于透传新字段）
     */
    public function __construct(
        public string $type = 'text',
        public ?string $text = null,
        public ?array $citations = null,
        public ?array $source = null,
        public ?string $id = null,
        public ?string $name = null,
        public ?array $input = null,
        public ?array $caller = null,
        public ?string $tool_use_id = null,
        public string|array|null $content = null,
        public ?bool $is_error = null,
        public ?string $thinking = null,
        public ?string $signature = null,
        public array $additionalData = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        $validTypes = implode(',', [
            self::TYPE_TEXT,
            self::TYPE_IMAGE,
            self::TYPE_TOOL_USE,
            self::TYPE_TOOL_RESULT,
            self::TYPE_THINKING,
            self::TYPE_REDACTED_THINKING,
            self::TYPE_SERVER_TOOL_USE,
            self::TYPE_WEB_SEARCH_TOOL_RESULT,
            self::TYPE_WEB_FETCH_TOOL_RESULT,
            self::TYPE_CODE_EXECUTION_TOOL_RESULT,
            self::TYPE_BASH_CODE_EXECUTION_TOOL_RESULT,
            self::TYPE_TEXT_EDITOR_CODE_EXECUTION_TOOL_RESULT,
            self::TYPE_TOOL_SEARCH_TOOL_RESULT,
            self::TYPE_CONTAINER_UPLOAD,
        ]);

        return [
            'type' => "required|string|in:{$validTypes}",
            'text' => 'nullable|string',
            'citations' => 'nullable|array',
            'source' => 'nullable|array',
            'id' => 'nullable|string',
            'name' => 'nullable|string',
            'input' => 'nullable|array',
            'caller' => 'nullable|array',
            'tool_use_id' => 'nullable|string',
            'content' => 'nullable',
            'is_error' => 'nullable|boolean',
            'thinking' => 'nullable|string',
            'signature' => 'nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 提取已知字段
        $knownKeys = [
            'type', 'text', 'citations', 'source', 'id', 'name', 'input', 'caller',
            'tool_use_id', 'content', 'is_error', 'thinking', 'signature',
        ];

        $additionalData = array_diff_key($data, array_flip($knownKeys));

        return new self(
            type: $data['type'] ?? 'text',
            text: $data['text'] ?? null,
            citations: $data['citations'] ?? null,
            source: $data['source'] ?? null,
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            input: $data['input'] ?? null,
            caller: $data['caller'] ?? null,
            tool_use_id: $data['tool_use_id'] ?? null,
            content: $data['content'] ?? null,
            is_error: $data['is_error'] ?? null,
            thinking: $data['thinking'] ?? null,
            signature: $data['signature'] ?? null,
            additionalData: $additionalData,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'type' => $this->type,
        ];

        if ($this->text !== null) {
            $result['text'] = $this->text;
        }

        if ($this->citations !== null) {
            $result['citations'] = $this->citations;
        }

        if ($this->source !== null) {
            $result['source'] = $this->source;
        }

        if ($this->id !== null) {
            $result['id'] = $this->id;
        }

        if ($this->name !== null) {
            $result['name'] = $this->name;
        }

        if ($this->input !== null) {
            $result['input'] = $this->input;
        }

        if ($this->caller !== null) {
            $result['caller'] = $this->caller;
        }

        if ($this->tool_use_id !== null) {
            $result['tool_use_id'] = $this->tool_use_id;
        }

        if ($this->content !== null) {
            $result['content'] = $this->content;
        }

        if ($this->is_error !== null) {
            $result['is_error'] = $this->is_error;
        }

        if ($this->thinking !== null) {
            $result['thinking'] = $this->thinking;
        }

        if ($this->signature !== null) {
            $result['signature'] = $this->signature;
        }

        // 合并额外字段（透传）
        return array_merge($result, $this->additionalData);
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedContentBlock
    {
        return SharedContentBlock::fromAnthropic($this->toArray());
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $data = $dto->toAnthropic();

        return self::fromArray($data);
    }
}
