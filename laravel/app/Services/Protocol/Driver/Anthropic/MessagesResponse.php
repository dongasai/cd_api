<?php

namespace App\Services\Protocol\Driver\Anthropic;

use App\Services\Protocol\Contracts\ProtocolResponse;
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
class MessagesResponse implements ProtocolResponse
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * 停止原因常量
     */
    public const STOP_REASON_END_TURN = 'end_turn';

    public const STOP_REASON_MAX_TOKENS = 'max_tokens';

    public const STOP_REASON_STOP_SEQUENCE = 'stop_sequence';

    public const STOP_REASON_TOOL_USE = 'tool_use';

    public const STOP_REASON_PAUSE_TURN = 'pause_turn';

    public const STOP_REASON_REFUSAL = 'refusal';

    /**
     * @param  string  $id  响应 ID
     * @param  string  $type  对象类型
     * @param  string  $role  角色
     * @param  ContentBlock[]  $content  内容块列表
     * @param  string  $model  模型名称
     * @param  string|null  $stop_reason  结束原因
     * @param  string|null  $stop_sequence  停止序列
     * @param  Usage  $usage  Token 使用量
     * @param  Container|null  $container  容器信息（用于代码执行工具）
     * @param  array  $additionalData  额外字段（透传）
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
        public ?Container $container = null,
        public array $additionalData = [],
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        $validStopReasons = implode(',', [
            self::STOP_REASON_END_TURN,
            self::STOP_REASON_MAX_TOKENS,
            self::STOP_REASON_STOP_SEQUENCE,
            self::STOP_REASON_TOOL_USE,
            self::STOP_REASON_PAUSE_TURN,
            self::STOP_REASON_REFUSAL,
        ]);

        return [
            'id' => 'required|string',
            'type' => 'required|string',
            'role' => 'required|string',
            'content' => 'required|array',
            'model' => 'required|string',
            'stop_reason' => "nullable|string|in:{$validStopReasons}",
            'stop_sequence' => 'nullable|string',
            'usage' => 'nullable',
            'container' => 'nullable',
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

        // 解析 container
        $container = null;
        if (isset($data['container']) && is_array($data['container'])) {
            $container = Container::fromArray($data['container']);
        }

        // 提取已知字段
        $knownKeys = [
            'id', 'type', 'role', 'content', 'model',
            'stop_reason', 'stop_sequence', 'usage', 'container',
        ];

        $additionalData = array_diff_key($data, array_flip($knownKeys));

        return new self(
            id: $data['id'] ?? '',
            type: $data['type'] ?? 'message',
            role: $data['role'] ?? 'assistant',
            content: $content,
            model: $data['model'] ?? '',
            stop_reason: $data['stop_reason'] ?? null,
            stop_sequence: $data['stop_sequence'] ?? null,
            usage: $usage,
            container: $container,
            additionalData: $additionalData,
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

        if ($this->container !== null) {
            $result['container'] = $this->container->toArray();
        }

        // 合并额外字段（透传）
        return array_merge($result, $this->additionalData);
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

        $dto = new SharedResponse;
        $dto->id = $this->id;
        $dto->model = $this->model;
        $dto->choices = $choices;
        $dto->usage = $this->usage?->toSharedDTO();
        $dto->finishReason = $this->mapStopReason($this->stop_reason);
        $dto->container = $this->container?->toSharedDTO();

        return $dto;
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
                // 处理 SharedMessage 对象或数组
                if ($message instanceof \App\Services\Shared\DTO\Message) {
                    // SharedMessage 对象
                    $textContent = $message->content;
                    $toolCalls = $message->toolCalls;
                } else {
                    // 数组格式
                    $textContent = $message['content'] ?? null;
                    $toolCalls = $message['tool_calls'] ?? null;
                }

                // 文本内容
                if ($textContent !== null) {
                    $content[] = new ContentBlock(
                        type: 'text',
                        text: $textContent,
                    );
                }

                // 工具调用
                if ($toolCalls !== null) {
                    foreach ($toolCalls as $index => $tc) {
                        // 处理数组格式的 toolCall
                        if (is_array($tc)) {
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
        }

        // 映射 finish_reason
        $stopReason = $dto->finishReason?->value;

        // 解析 container
        $container = null;
        if ($dto->container !== null) {
            $container = Container::fromSharedDTO($dto->container);
        }

        return new self(
            id: $dto->id ?? 'msg_'.uniqid(),
            type: 'message',
            role: 'assistant',
            content: $content,
            model: $dto->model,
            stop_reason: $stopReason,
            usage: $dto->usage ? Usage::fromSharedDTO($dto->usage) : null,
            container: $container,
        );
    }

    /**
     * 映射结束原因
     */
    private function mapStopReason(?string $reason): ?FinishReason
    {
        return match ($reason) {
            self::STOP_REASON_END_TURN => FinishReason::EndTurn,
            self::STOP_REASON_MAX_TOKENS => FinishReason::MaxTokens,
            self::STOP_REASON_STOP_SEQUENCE => FinishReason::StopSequence,
            self::STOP_REASON_TOOL_USE => FinishReason::ToolUse,
            self::STOP_REASON_PAUSE_TURN => FinishReason::PauseTurn,
            self::STOP_REASON_REFUSAL => FinishReason::Refusal,
            default => null,
        };
    }

    /**
     * 获取响应 ID
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * 获取模型名称
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * 获取使用量
     */
    public function getUsage(): ?object
    {
        return $this->usage;
    }

    /**
     * 过滤响应中的 thinking 内容块
     *
     * @param  bool  $filter  是否过滤
     */
    public function filterThinking(bool $filter = true): static
    {
        if (! $filter) {
            return $this;
        }

        // 过滤 content 中的 thinking 类型块
        $this->content = array_values(
            array_filter(
                $this->content,
                fn (ContentBlock $block) => $block->type !== 'thinking'
            )
        );

        return $this;
    }
}
