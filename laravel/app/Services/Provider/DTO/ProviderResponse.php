<?php

namespace App\Services\Provider\DTO;

class ProviderResponse
{
    public function __construct(
        public string $id,
        public string $model,
        public string $content = '',
        public ?string $finishReason = null,
        public ?TokenUsage $usage = null,
        public ?array $rawResponse = null,
        public ?array $toolCalls = null,
        public int $created = 0,
    ) {
        if ($this->created === 0) {
            $this->created = time();
        }
    }

    public static function fromOpenAI(array $response): self
    {
        $id = $response['id'] ?? '';
        $model = $response['model'] ?? '';
        $created = $response['created'] ?? time();

        $choices = $response['choices'] ?? [];
        $choice = $choices[0] ?? [];

        $message = $choice['message'] ?? [];
        $content = $message['content'] ?? '';
        $finishReason = $choice['finish_reason'] ?? null;

        $toolCalls = null;
        if (isset($message['tool_calls'])) {
            $toolCalls = $message['tool_calls'];
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = TokenUsage::fromOpenAI($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content ?? '',
            finishReason: $finishReason,
            usage: $usage,
            rawResponse: $response,
            toolCalls: $toolCalls,
            created: $created,
        );
    }

    public static function fromAnthropic(array $response): self
    {
        $id = $response['id'] ?? '';
        $model = $response['model'] ?? '';
        $stopReason = $response['stop_reason'] ?? null;

        $content = '';
        $toolCalls = null;

        if (isset($response['content']) && is_array($response['content'])) {
            foreach ($response['content'] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content .= $block['text'] ?? '';
                } elseif (($block['type'] ?? '') === 'tool_use') {
                    $toolCalls[] = [
                        'id' => $block['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $block['name'] ?? '',
                            'arguments' => json_encode($block['input'] ?? []),
                        ],
                    ];
                }
            }
        }

        $usage = null;
        if (isset($response['usage'])) {
            $usage = TokenUsage::fromAnthropic($response['usage']);
        }

        return new self(
            id: $id,
            model: $model,
            content: $content,
            finishReason: self::mapStopReason($stopReason),
            usage: $usage,
            rawResponse: $response,
            toolCalls: $toolCalls,
        );
    }

    public function toOpenAI(): array
    {
        $response = [
            'id' => $this->id,
            'object' => 'chat.completion',
            'created' => $this->created,
            'model' => $this->model,
            'choices' => [
                [
                    'index' => 0,
                    'message' => $this->buildMessage(),
                    'finish_reason' => $this->finishReason,
                ],
            ],
        ];

        if ($this->usage !== null) {
            $response['usage'] = $this->usage->toOpenAI();
        }

        return $response;
    }

    public function toAnthropic(): array
    {
        $response = [
            'id' => $this->id,
            'type' => 'message',
            'role' => 'assistant',
            'model' => $this->model,
            'content' => $this->buildContentBlocks(),
            'stop_reason' => $this->mapFinishReason($this->finishReason),
            'stop_sequence' => null,
        ];

        if ($this->usage !== null) {
            $response['usage'] = $this->usage->toAnthropic();
        }

        return $response;
    }

    public function hasToolCalls(): bool
    {
        return $this->toolCalls !== null && count($this->toolCalls) > 0;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'model' => $this->model,
            'content' => $this->content,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
            'tool_calls' => $this->toolCalls,
            'created' => $this->created,
        ];
    }

    private function buildMessage(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content ?: null,
        ];

        if ($this->toolCalls !== null) {
            $message['tool_calls'] = $this->toolCalls;
            if (empty($this->content)) {
                $message['content'] = null;
            }
        }

        return $message;
    }

    private function buildContentBlocks(): array
    {
        $blocks = [];

        if ($this->content) {
            $blocks[] = [
                'type' => 'text',
                'text' => $this->content,
            ];
        }

        if ($this->toolCalls !== null) {
            foreach ($this->toolCalls as $tc) {
                $blocks[] = [
                    'type' => 'tool_use',
                    'id' => $tc['id'] ?? '',
                    'name' => $tc['function']['name'] ?? '',
                    'input' => json_decode($tc['function']['arguments'] ?? '{}', true),
                ];
            }
        }

        return $blocks;
    }

    private static function mapStopReason(?string $stopReason): ?string
    {
        return match ($stopReason) {
            'end_turn' => 'stop',
            'max_tokens' => 'length',
            'stop_sequence' => 'stop',
            'tool_use' => 'tool_calls',
            null => null,
            default => $stopReason,
        };
    }

    private function mapFinishReason(?string $finishReason): ?string
    {
        return match ($finishReason) {
            'stop' => 'end_turn',
            'length' => 'max_tokens',
            'tool_calls' => 'tool_use',
            'content_filter' => 'end_turn',
            null => null,
            default => $finishReason,
        };
    }
}
