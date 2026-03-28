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

    public string $type = 'text';

    public ?string $text = null;

    public ?array $citations = null;

    public ?array $source = null;

    public ?string $id = null;

    public ?string $name = null;

    public ?array $input = null;

    public ?array $caller = null;

    public ?string $tool_use_id = null;

    public string|array|null $content = null;

    public ?bool $is_error = null;

    public ?string $thinking = null;

    public ?string $signature = null;

    public ?array $cache_control = null;

    public array $additionalData = [];

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
            'cache_control' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        $instance = new self;
        $instance->type = $data['type'] ?? 'text';
        $instance->text = $data['text'] ?? null;
        $instance->citations = $data['citations'] ?? null;
        $instance->source = $data['source'] ?? null;
        $instance->id = $data['id'] ?? null;
        $instance->name = $data['name'] ?? null;
        $instance->input = $data['input'] ?? null;
        $instance->caller = $data['caller'] ?? null;
        $instance->tool_use_id = $data['tool_use_id'] ?? null;
        $instance->content = $data['content'] ?? null;
        $instance->is_error = $data['is_error'] ?? null;
        $instance->thinking = $data['thinking'] ?? null;
        $instance->signature = $data['signature'] ?? null;
        $instance->cache_control = $data['cache_control'] ?? null;

        // 提取已知字段
        $knownKeys = [
            'type', 'text', 'citations', 'source', 'id', 'name', 'input', 'caller',
            'tool_use_id', 'content', 'is_error', 'thinking', 'signature', 'cache_control',
        ];
        $instance->additionalData = array_diff_key($data, array_flip($knownKeys));

        return $instance;
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

        if ($this->cache_control !== null) {
            $result['cache_control'] = $this->cache_control;
        }

        // 合并额外字段（透传）
        return array_merge($result, $this->additionalData);
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedContentBlock
    {
        // 直接构建 DTO，不调用 fromAnthropic
        $dto = new SharedContentBlock;
        $dto->type = $this->type;
        $dto->text = $this->text;
        $dto->citations = $this->citations;
        $dto->source = $this->source;
        $dto->toolId = $this->id;
        $dto->toolName = $this->name;
        $dto->toolInput = $this->input;
        $dto->caller = $this->caller;
        $dto->toolResultId = $this->tool_use_id;
        $dto->toolResultContent = is_array($this->content) ? json_encode($this->content) : $this->content;
        $dto->toolResultIsError = $this->is_error;
        $dto->thinking = $this->thinking;
        $dto->signature = $this->signature;
        $dto->cacheControl = $this->cache_control;

        return $dto;
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        $data = match ($dto->type) {
            'text' => [
                'type' => 'text',
                'text' => $dto->text ?? '',
                'citations' => $dto->citations,
                'cache_control' => $dto->cacheControl,
            ],
            'image', 'image_url' => [
                'type' => 'image',
                'source' => $dto->source ?? [
                    'type' => 'url',
                    'url' => $dto->imageUrl,
                ],
                'cache_control' => $dto->cacheControl,
            ],
            'tool_use' => [
                'type' => 'tool_use',
                'id' => $dto->toolId,
                'name' => $dto->toolName,
                'input' => $dto->toolInput ?? [],
                'caller' => $dto->caller,
                'cache_control' => $dto->cacheControl,
            ],
            'tool_result' => [
                'type' => 'tool_result',
                'tool_use_id' => $dto->toolResultId,
                'content' => $dto->toolResultContent,
                'is_error' => $dto->toolResultIsError,
                'cache_control' => $dto->cacheControl,
            ],
            'thinking' => [
                'type' => 'thinking',
                'thinking' => $dto->thinking ?? '',
                'signature' => $dto->signature,
                'cache_control' => $dto->cacheControl,
            ],
            default => [
                'type' => $dto->type,
                'text' => $dto->text,
                'cache_control' => $dto->cacheControl,
            ],
        };

        return self::fromArray($data);
    }
}
