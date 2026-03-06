<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

class OpenAIProvider extends AbstractProvider
{
    protected array $supportedModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4-turbo',
        'gpt-4-turbo-preview',
        'gpt-4',
        'gpt-4-32k',
        'gpt-3.5-turbo',
        'gpt-3.5-turbo-16k',
        'o1',
        'o1-mini',
        'o1-preview',
    ];

    public function getDefaultBaseUrl(): string
    {
        return 'https://api.openai.com/v1';
    }

    public function getEndpoint(ProviderRequest $request): string
    {
        return '/chat/completions';
    }

    public function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }

    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toOpenAIFormat();
    }

    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromOpenAI($response);
    }

    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromOpenAI($rawChunk);
    }

    public function getModels(): array
    {
        return $this->supportedModels;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }
}
