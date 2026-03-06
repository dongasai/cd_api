<?php

namespace App\Services\Provider\DTO;

class ProviderStreamChunk
{
    public function __construct(
        public string $event = '',
        public array $data = [],
        public string $delta = '',
        public ?string $id = null,
        public ?string $model = null,
        public ?string $finishReason = null,
        public ?TokenUsage $usage = null,
    ) {}

    public static function fromOpenAI(string $rawEvent): ?self
    {
        $lines = explode("\n", trim($rawEvent));
        $event = '';
        $data = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($data) || $data === '[DONE]') {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $id = $parsed['id'] ?? null;
        $model = $parsed['model'] ?? null;
        $delta = '';
        $finishReason = null;
        $usage = null;

        $choices = $parsed['choices'] ?? [];
        $choice = $choices[0] ?? [];

        if (isset($choice['delta'])) {
            $delta = $choice['delta']['content'] ?? '';
        }

        if (isset($choice['finish_reason'])) {
            $finishReason = $choice['finish_reason'];
        }

        if (isset($parsed['usage'])) {
            $usage = TokenUsage::fromOpenAI($parsed['usage']);
        }

        return new self(
            event: $event,
            data: $parsed,
            delta: $delta,
            id: $id,
            model: $model,
            finishReason: $finishReason,
            usage: $usage,
        );
    }

    public static function fromAnthropic(string $rawEvent): ?self
    {
        $lines = explode("\n", trim($rawEvent));
        $event = '';
        $data = '';

        foreach ($lines as $line) {
            if (str_starts_with($line, 'event:')) {
                $event = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = trim(substr($line, 5));
            }
        }

        if (empty($data)) {
            return null;
        }

        $parsed = json_decode($data, true);
        if ($parsed === null) {
            return null;
        }

        $delta = '';
        $id = null;
        $model = null;
        $finishReason = null;
        $usage = null;

        switch ($event) {
            case 'message_start':
                $message = $parsed['message'] ?? [];
                $id = $message['id'] ?? null;
                $model = $message['model'] ?? null;
                if (isset($message['usage'])) {
                    $usage = TokenUsage::fromAnthropic($message['usage']);
                }
                break;

            case 'content_block_delta':
                $delta = $parsed['delta']['text'] ?? '';
                break;

            case 'message_delta':
                $usage = isset($parsed['usage'])
                    ? TokenUsage::fromAnthropic($parsed['usage'])
                    : null;
                $finishReason = $parsed['delta']['stop_reason'] ?? null;
                break;

            case 'content_block_stop':
            case 'message_stop':
                break;
        }

        return new self(
            event: $event,
            data: $parsed,
            delta: $delta,
            id: $id,
            model: $model,
            finishReason: $finishReason,
            usage: $usage,
        );
    }

    public function toOpenAIChunk(string $id, string $model): string
    {
        $chunk = [
            'id' => $id ?: 'chatcmpl-'.uniqid(),
            'object' => 'chat.completion.chunk',
            'created' => time(),
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'delta' => $this->delta ? ['content' => $this->delta] : [],
                    'finish_reason' => $this->finishReason,
                ],
            ],
        ];

        if ($this->usage !== null) {
            $chunk['usage'] = $this->usage->toOpenAI();
        }

        return "data: ".json_encode($chunk, JSON_UNESCAPED_UNICODE)."\n\n";
    }

    public function toAnthropicEvent(): string
    {
        $output = "event: {$this->event}\n";
        $output .= 'data: '.json_encode($this->data, JSON_UNESCAPED_UNICODE)."\n\n";

        return $output;
    }

    public function isEmpty(): bool
    {
        return empty($this->delta) && empty($this->finishReason) && empty($this->usage);
    }

    public function isDone(): bool
    {
        return $this->event === 'message_stop' || $this->finishReason !== null;
    }

    public function toArray(): array
    {
        return [
            'event' => $this->event,
            'data' => $this->data,
            'delta' => $this->delta,
            'id' => $this->id,
            'model' => $this->model,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
        ];
    }
}
