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
     * @param  string  $type  类型（text|image|tool_use|tool_result|thinking）
     * @param  string|null  $text  文本内容
     * @param  array|null  $source  图片源
     * @param  string|null  $id  工具调用 ID
     * @param  string|null  $name  工具名称
     * @param  array|null  $input  工具输入
     * @param  string|null  $tool_use_id  工具结果 ID
     * @param  string|array|null  $content  工具结果内容
     * @param  bool|null  $is_error  是否错误
     * @param  string|null  $thinking  推理内容
     * @param  string|null  $signature  签名
     */
    public function __construct(
        public string $type = 'text',
        public ?string $text = null,
        public ?array $source = null,
        public ?string $id = null,
        public ?string $name = null,
        public ?array $input = null,
        public ?string $tool_use_id = null,
        public string|array|null $content = null,
        public ?bool $is_error = null,
        public ?string $thinking = null,
        public ?string $signature = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'type' => 'required|string|in:text,image,tool_use,tool_result,thinking',
            'text' => 'required_if:type,text|nullable|string',
            'source' => 'required_if:type,image|nullable|array',
            'id' => 'required_if:type,tool_use|nullable|string',
            'name' => 'required_if:type,tool_use|nullable|string',
            'input' => 'nullable|array',
            'tool_use_id' => 'required_if:type,tool_result|nullable|string',
            'content' => 'nullable',
            'is_error' => 'nullable|boolean',
            'thinking' => 'required_if:type,thinking|nullable|string',
            'signature' => 'nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        return new self(
            type: $data['type'] ?? 'text',
            text: $data['text'] ?? null,
            source: $data['source'] ?? null,
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            input: $data['input'] ?? null,
            tool_use_id: $data['tool_use_id'] ?? null,
            content: $data['content'] ?? null,
            is_error: $data['is_error'] ?? null,
            thinking: $data['thinking'] ?? null,
            signature: $data['signature'] ?? null,
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

        return $result;
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
