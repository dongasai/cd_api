<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\JsonSerializiable;

/**
 * Anthropic 工具定义结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#request-body-tools
 */
class Tool
{
    use JsonSerializiable;

    /**
     * @param  string  $name  工具名称（必需）
     * @param  array  $input_schema  输入 JSON Schema（必需）
     * @param  string|null  $description  工具描述
     * @param  string|null  $type  工具类型
     * @param  array|null  $cache_control  缓存控制
     * @param  array|null  $allowed_callers  允许的调用者
     * @param  bool|null  $defer_loading  是否延迟加载
     * @param  bool|null  $eager_input_streaming  是否启用急切输入流
     * @param  array|null  $input_examples  输入示例
     * @param  bool|null  $strict  是否严格模式
     * @param  array  $additionalData  额外字段（透传）
     */
    public function __construct(
        public string $name = '',
        public array $input_schema = [],
        public ?string $description = null,
        public ?string $type = null,
        public ?array $cache_control = null,
        public ?array $allowed_callers = null,
        public ?bool $defer_loading = null,
        public ?bool $eager_input_streaming = null,
        public ?array $input_examples = null,
        public ?bool $strict = null,
        public array $additionalData = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'name' => 'required|string',
            'input_schema' => 'required|array',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'cache_control' => 'nullable|array',
            'allowed_callers' => 'nullable|array',
            'defer_loading' => 'nullable|boolean',
            'eager_input_streaming' => 'nullable|boolean',
            'input_examples' => 'nullable|array',
            'strict' => 'nullable|boolean',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 提取已知字段
        $knownKeys = [
            'name', 'input_schema', 'description', 'type',
            'cache_control', 'allowed_callers', 'defer_loading',
            'eager_input_streaming', 'input_examples', 'strict',
        ];

        $additionalData = array_diff_key($data, array_flip($knownKeys));

        return new self(
            name: $data['name'] ?? '',
            input_schema: $data['input_schema'] ?? [],
            description: $data['description'] ?? null,
            type: $data['type'] ?? null,
            cache_control: $data['cache_control'] ?? null,
            allowed_callers: $data['allowed_callers'] ?? null,
            defer_loading: $data['defer_loading'] ?? null,
            eager_input_streaming: $data['eager_input_streaming'] ?? null,
            input_examples: $data['input_examples'] ?? null,
            strict: $data['strict'] ?? null,
            additionalData: $additionalData,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'name' => $this->name,
            'input_schema' => $this->input_schema,
        ];

        if ($this->description !== null) {
            $result['description'] = $this->description;
        }

        if ($this->type !== null) {
            $result['type'] = $this->type;
        }

        if ($this->cache_control !== null) {
            $result['cache_control'] = $this->cache_control;
        }

        if ($this->allowed_callers !== null) {
            $result['allowed_callers'] = $this->allowed_callers;
        }

        if ($this->defer_loading !== null) {
            $result['defer_loading'] = $this->defer_loading;
        }

        if ($this->eager_input_streaming !== null) {
            $result['eager_input_streaming'] = $this->eager_input_streaming;
        }

        if ($this->input_examples !== null) {
            $result['input_examples'] = $this->input_examples;
        }

        if ($this->strict !== null) {
            $result['strict'] = $this->strict;
        }

        // 合并额外字段（透传）
        return array_merge($result, $this->additionalData);
    }
}
