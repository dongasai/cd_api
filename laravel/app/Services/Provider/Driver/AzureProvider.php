<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;

class AzureProvider extends AbstractProvider
{
    protected string $deploymentName;
    protected string $apiVersion;

    protected array $supportedModels = [
        'gpt-4o',
        'gpt-4o-mini',
        'gpt-4',
        'gpt-4-32k',
        'gpt-35-turbo',
        'gpt-35-turbo-16k',
    ];

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        $this->deploymentName = $config['deployment_name'] ?? $config['deployment'] ?? '';
        $this->apiVersion = $config['api_version'] ?? '2024-02-15-preview';
    }

    public function getDefaultBaseUrl(): string
    {
        return '';
    }

    public function getEndpoint(ProviderRequest $request): string
    {
        $deployment = $this->deploymentName ?: $request->model;

        return "/openai/deployments/{$deployment}/chat/completions";
    }

    public function getHeaders(): array
    {
        return [
            'api-key' => $this->apiKey,
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
        return 'azure';
    }

    protected function executeRequest(ProviderRequest $request): ProviderResponse
    {
        $body = $this->buildRequestBody($request);
        $endpoint = $this->getEndpoint($request);

        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');
        $url .= '?api-version='.$this->apiVersion;

        $response = \Illuminate\Support\Facades\Http::withHeaders($this->getHeaders())
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->post($url, $body);

        if (! $response->ok()) {
            throw $this->createErrorFromResponse($response);
        }

        $this->recordSuccess();

        return $this->parseResponse($response->json());
    }
}
