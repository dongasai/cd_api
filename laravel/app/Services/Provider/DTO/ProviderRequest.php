<?php

namespace App\Services\Provider\DTO;

class ProviderRequest
{
    public function __construct(
        public string $model,
        public array $messages,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public bool $stream = false,
        public array $parameters = [],
        public ?string $systemPrompt = null,
        public ?float $topP = null,
        public ?array $stop = null,
        public ?array $tools = null,
        public mixed $toolChoice = null,
        public ?string $user = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            model: $data['model'] ?? '',
            messages: $data['messages'] ?? [],
            temperature: $data['temperature'] ?? null,
            maxTokens: $data['max_tokens'] ?? $data['maxTokens'] ?? null,
            stream: $data['stream'] ?? false,
            parameters: $data['parameters'] ?? [],
            systemPrompt: $data['system'] ?? $data['systemPrompt'] ?? null,
            topP: $data['top_p'] ?? $data['topP'] ?? null,
            stop: $data['stop'] ?? null,
            tools: $data['tools'] ?? null,
            toolChoice: $data['tool_choice'] ?? $data['toolChoice'] ?? null,
            user: $data['user'] ?? null,
        );
    }

    public function toArray(): array
    {
        $result = [
            'model' => $this->model,
            'messages' => $this->messages,
        ];

        if ($this->temperature !== null) {
            $result['temperature'] = $this->temperature;
        }
        if ($this->maxTokens !== null) {
            $result['max_tokens'] = $this->maxTokens;
        }
        if ($this->stream) {
            $result['stream'] = true;
        }
        if ($this->systemPrompt !== null) {
            $result['system'] = $this->systemPrompt;
        }
        if ($this->topP !== null) {
            $result['top_p'] = $this->topP;
        }
        if ($this->stop !== null) {
            $result['stop'] = $this->stop;
        }
        if ($this->tools !== null) {
            $result['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $result['tool_choice'] = $this->toolChoice;
        }
        if ($this->user !== null) {
            $result['user'] = $this->user;
        }

        return array_merge($this->parameters, $result);
    }

    public function toOpenAIFormat(): array
    {
        $request = $this->toArray();

        if ($this->systemPrompt !== null) {
            array_unshift($request['messages'], [
                'role' => 'system',
                'content' => $this->systemPrompt,
            ]);
            unset($request['system']);
        }

        return $request;
    }

    public function toAnthropicFormat(): array
    {
        $request = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens ?? 4096,
            'messages' => $this->messages,
        ];

        if ($this->systemPrompt !== null) {
            $request['system'] = $this->systemPrompt;
        }
        if ($this->temperature !== null) {
            $request['temperature'] = $this->temperature;
        }
        if ($this->topP !== null) {
            $request['top_p'] = $this->topP;
        }
        if ($this->stop !== null) {
            $request['stop_sequences'] = $this->stop;
        }
        if ($this->stream) {
            $request['stream'] = true;
        }
        if ($this->tools !== null) {
            $request['tools'] = $this->tools;
        }
        if ($this->toolChoice !== null) {
            $request['tool_choice'] = $this->toolChoice;
        }

        return $request;
    }

    public function getMessageCount(): int
    {
        return count($this->messages);
    }

    public function hasTools(): bool
    {
        return $this->tools !== null && count($this->tools) > 0;
    }
}
