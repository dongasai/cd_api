<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Shared\DTO\Response as SharedResponse;
use App\Services\Shared\Enums\FinishReason;

/**
 * Anthropic Messages API 响应结构体
 *
 * @see https://docs.anthropic.com/en/api/messages#response-body
 */
class MessagesResponse
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * @param  string  $id  响应 ID
     * @param  string  $type  对象类型
     * @param  string  $role  角色
     * @param  ContentBlock[]  $content  内容块列表
     * @param  string  $model  模型名称
     * @param  string|null  $stop_reason  结束原因
     * @param  string|null  $stop_sequence  停止序列
     * @param  Usage  $usage  Token 使用量
     */
    public function __construct(
        public string $id = '',
        public string $type = 'message',
        public string $role = 'assistant',
        public array $content = [],
        public string $model = '',
        public ?string $stop_reason = null,
        public ?string $stop_sequence = null,
        public ?Usage $usage = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'type' => 'required|string',
            'role' => 'required|string',
            'content' => 'required|array',
            'model' => 'required|string',
            'stop_reason' => 'nullable|string|in:end_turn,max_tokens,stop_sequence,tool_use',
            'stop_sequence' => 'nullable|string',
            'usage' => 'nullable|array',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 解析 content
        $content = [];
        foreach ($data['content'] ?? [] as $block) {
            if (is_array($block)) {
                $content[] = ContentBlock::fromArray($block);
            }
        }

        // 解析 usage
        $usage = null;
        if (isset($data['usage']) && is_array($data['usage'])) {
            $usage = Usage::fromArray($data['usage']);
        }

        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? 'message',
            role: $data['role'] ?? 'assistant',
            content: $content,
            model: $data['model'] ?? '',
            stop_reason: $data['stop_reason'] ?? null,
            stop_sequence: $data['stop_sequence'] ?? null,
            usage: $usage,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'role' => $this->role,
            'content' => array_map(fn (ContentBlock $block) => $block->toArray(), $this->content),
            'model' => $this->model,
        ];

        if ($this->stop_reason !== null) {
            $result['stop_reason'] = $this->stop_reason;
        }

        if ($this->stop_sequence !== null) {
            $result['stop_sequence'] = $this->stop_sequence;
        }

        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toArray();
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedResponse
    {
        // 转换 content 为 choices 格式
        $choices = [];
        $textContent = '';
        $toolCalls = null;

        foreach ($this->content as $block) {
            if ($block->type === 'text' && $block->text !== null) {
                $textContent .= $block->text;
            } elseif ($block->type === 'tool_use') {
                $toolCalls[] = [
                    'id' => $block->id,
                    'type' => 'function',
                    'function' => [
                        'name' => $block->name,
                        'arguments' => json_encode($block->input ?? []),
                    ],
                ];
            }
        }

        // 构建单个 choice
        $choices[] = [
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => $textContent ?: null,
                'tool_calls' => $toolCalls,
            ],
            'finish_reason' => $this->stop_reason,
        ];

        return new SharedResponse(
            id: $this->id,
            model: $this->model,
            choices: $choices,
            usage: $this->usage?->toSharedDTO(),
            finishReason: $this->mapStopReason($this->stop_reason),
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 转换 choices 为 Anthropic content 格式
        $content = [];

        foreach ($dto->choices as $choiceData) {
            $message = $choiceData['message'] ?? null;

            if ($message !== null) {
                // 文本内容
                $textContent = $message['content'] ?? null;
                if ($textContent !== null) {
                    $content[] = new ContentBlock(
                        type: 'text',
                        text: $textContent,
                    );
                }

                // 工具调用
                $toolCalls = $message['tool_calls'] ?? null;
                if ($toolCalls !== null) {
                    foreach ($toolCalls as $index => $tc) {
                        $content[] = new ContentBlock(
                            type: 'tool_use',
                            id: $tc['id'] ?? "toolu_{$index}",
                            name: $tc['function']['name'] ?? '',
                            input: json_decode($tc['function']['arguments'] ?? '{}', true),
                        );
                    }
                }
            }
        }

        // 映射 finish_reason
        $stopReason = $dto->finishReason?->toAnthropic();

        return new self(
            id: $dto->id ?? 'msg_'.uniqid(),
            type: 'message',
            role: 'assistant',
            content: $content,
            model: $dto->model,
            stop_reason: $stopReason,
            usage: $dto->usage ? Usage::fromSharedDTO($dto->usage) : null,
        );
    }

    /**
     * 映射结束原因
     */
    private function mapStopReason(?string $reason): ?FinishReason
    {
        return match ($reason) {
            'end_turn' => FinishReason::EndTurn,
            'max_tokens' => FinishReason::MaxTokens,
            'stop_sequence' => FinishReason::StopSequence,
            'tool_use' => FinishReason::ToolUse,
            default => null,
        };
    }
}
