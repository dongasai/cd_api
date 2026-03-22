<?php

namespace App\Services\Protocol\Driver\OpenAI;

use App\Services\Protocol\Driver\Concerns\Convertible;
use App\Services\Protocol\Driver\Concerns\JsonSerializiable;
use App\Services\Protocol\Driver\Concerns\Validatable;
use App\Services\Shared\DTO\Response as SharedResponse;
use App\Services\Shared\Enums\FinishReason;

/**
 * OpenAI Chat Completions API 响应结构体
 *
 * @see https://platform.openai.com/docs/api-reference/chat/object
 */
class ChatCompletionResponse
{
    use Convertible;
    use JsonSerializiable;
    use Validatable;

    /**
     * @param  string  $id  响应 ID
     * @param  string  $object  对象类型
     * @param  int  $created  创建时间戳
     * @param  string  $model  模型名称
     * @param  Choice[]  $choices  选择列表
     * @param  Usage|null  $usage  Token 使用量
     * @param  string|null  $system_fingerprint  系统指纹
     */
    public function __construct(
        public string $id = '',
        public string $object = 'chat.completion',
        public int $created = 0,
        public string $model = '',
        public array $choices = [],
        public ?Usage $usage = null,
        public ?string $system_fingerprint = null,
    ) {}

    /**
     * 验证规则
     */
    public function validationRules(): array
    {
        return [
            'id' => 'required|string',
            'object' => 'required|string',
            'created' => 'required|integer',
            'model' => 'required|string',
            'choices' => 'required|array|min:1',
            'usage' => 'nullable|array',
            'system_fingerprint' => 'nullable|string',
        ];
    }

    /**
     * 从数组创建
     */
    public static function fromArray(array $data): static
    {
        // 解析 choices
        $choices = [];
        foreach ($data['choices'] ?? [] as $choice) {
            $choices[] = Choice::fromArray($choice);
        }

        // 解析 usage
        $usage = null;
        if (isset($data['usage']) && is_array($data['usage'])) {
            $usage = Usage::fromArray($data['usage']);
        }

        return new self(
            id: $data['id'] ?? '',
            object: $data['object'] ?? 'chat.completion',
            created: $data['created'] ?? time(),
            model: $data['model'] ?? '',
            choices: $choices,
            usage: $usage,
            system_fingerprint: $data['system_fingerprint'] ?? null,
        );
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        $result = [
            'id' => $this->id,
            'object' => $this->object,
            'created' => $this->created,
            'model' => $this->model,
            'choices' => array_map(fn (Choice $choice) => $choice->toArray(), $this->choices),
        ];

        if ($this->usage !== null) {
            $result['usage'] = $this->usage->toArray();
        }

        if ($this->system_fingerprint !== null) {
            $result['system_fingerprint'] = $this->system_fingerprint;
        }

        return $result;
    }

    /**
     * 转换为 Shared\DTO
     */
    public function toSharedDTO(): SharedResponse
    {
        // 转换 choices
        $sharedChoices = [];
        $finishReason = null;

        foreach ($this->choices as $index => $choice) {
            $message = $choice->message ?? $choice->delta;

            if ($message !== null) {
                $sharedChoices[] = [
                    'index' => $index,
                    'message' => $message->toSharedDTO(),
                    'finish_reason' => $choice->finish_reason,
                ];
            }

            // 提取第一个 finish_reason
            if ($finishReason === null && $choice->finish_reason !== null) {
                $finishReason = $this->mapFinishReason($choice->finish_reason);
            }
        }

        return new SharedResponse(
            id: $this->id,
            model: $this->model,
            choices: $sharedChoices,
            usage: $this->usage?->toSharedDTO(),
            finishReason: $finishReason,
            systemFingerprint: $this->system_fingerprint,
            created: $this->created,
        );
    }

    /**
     * 从 Shared\DTO 创建
     */
    public static function fromSharedDTO(object $dto): static
    {
        // 转换 choices
        $choices = [];
        foreach ($dto->choices as $choiceData) {
            $message = null;
            if (isset($choiceData['message'])) {
                $message = Message::fromSharedDTO($choiceData['message']);
            }

            $finishReason = $choiceData['finish_reason'] ?? null;
            if ($finishReason instanceof FinishReason) {
                $finishReason = $finishReason->toOpenAI();
            }

            $choices[] = new Choice(
                index: $choiceData['index'] ?? 0,
                message: $message,
                finish_reason: $finishReason,
            );
        }

        return new self(
            id: $dto->id ?? 'chatcmpl-'.uniqid(),
            object: 'chat.completion',
            created: $dto->created ?? time(),
            model: $dto->model,
            choices: $choices,
            usage: $dto->usage ? Usage::fromSharedDTO($dto->usage) : null,
            system_fingerprint: $dto->systemFingerprint ?? null,
        );
    }

    /**
     * 映射结束原因
     */
    private function mapFinishReason(string $reason): ?FinishReason
    {
        return match ($reason) {
            'stop' => FinishReason::Stop,
            'length' => FinishReason::MaxTokens,
            'tool_calls' => FinishReason::ToolUse,
            'content_filter' => null,
            default => null,
        };
    }
}
