<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

class AnthropicProvider extends AbstractProvider
{
    protected string $apiVersion = '2023-06-01';

    protected array $supportedModels = [
        'claude-3-5-sonnet-20241022',
        'claude-3-5-haiku-20241022',
        'claude-3-opus-20240229',
        'claude-3-sonnet-20240229',
        'claude-3-haiku-20240307',
        'claude-2.1',
        'claude-2.0',
        'claude-instant-1.2',
    ];

    public function getDefaultBaseUrl(): string
    {
        return 'https://api.anthropic.com/v1';
    }

    public function getEndpoint(ProviderRequest $request): string
    {
        return '/messages';
    }

    public function getHeaders(): array
    {
        return [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => $this->apiVersion,
            'Content-Type' => 'application/json',
        ];
    }

    public function buildRequestBody(ProviderRequest $request): array
    {
        return $request->toAnthropicFormat();
    }

    public function parseResponse(array $response): ProviderResponse
    {
        return ProviderResponse::fromAnthropic($response);
    }

    public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk
    {
        return ProviderStreamChunk::fromAnthropic($rawChunk);
    }

    public function getModels(): array
    {
        return $this->supportedModels;
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }
}
