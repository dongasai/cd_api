<?php

namespace App\Services\Provider\Driver;

use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\DTO\ProviderStreamChunk;
use App\Services\Provider\Exceptions\ProviderException;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractProvider implements ProviderInterface
{
    protected string $baseUrl;

    protected string $apiKey;

    protected array $config;

    protected ?string $lastErrorMessage = null;

    protected int $timeout = 60;

    protected int $connectTimeout = 10;

    protected int $maxRetries = 3;

    protected int $retryDelay = 1000;

    protected float $retryMultiplier = 2.0;

    protected int $circuitFailureThreshold = 5;

    protected int $circuitResetTimeout = 60;

    protected int $circuitFailures = 0;

    protected ?int $circuitOpenTime = null;

    protected string $circuitState = 'closed';

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->baseUrl = $config['base_url'] ?? $this->getDefaultBaseUrl();
        $this->apiKey = $config['api_key'] ?? '';
        $this->timeout = $config['timeout'] ?? 60;
        $this->connectTimeout = $config['connect_timeout'] ?? 10;
        $this->maxRetries = $config['max_retries'] ?? 3;
        $this->retryDelay = $config['retry_delay'] ?? 1000;
        $this->retryMultiplier = $config['retry_multiplier'] ?? 2.0;
        $this->circuitFailureThreshold = $config['circuit_failure_threshold'] ?? 5;
        $this->circuitResetTimeout = $config['circuit_reset_timeout'] ?? 60;
    }

    abstract public function getDefaultBaseUrl(): string;

    abstract public function buildRequestBody(ProviderRequest $request): array;

    abstract public function parseResponse(array $response): ProviderResponse;

    abstract public function parseStreamChunk(string $rawChunk): ?ProviderStreamChunk;

    abstract public function getEndpoint(ProviderRequest $request): string;

    abstract public function getHeaders(): array;

    public function send(ProviderRequest $request): ProviderResponse
    {
        $this->checkCircuitBreaker();

        $lastException = null;

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->executeRequest($request);
            } catch (ProviderException $e) {
                $lastException = $e;

                if ($e->shouldSkipRetry()) {
                    throw $e;
                }

                if ($attempt < $this->maxRetries && $e->isRetryable()) {
                    $delay = (int) ($this->retryDelay * pow($this->retryMultiplier, $attempt));
                    usleep($delay * 1000);

                    continue;
                }

                throw $e;
            } catch (ConnectionException $e) {
                $lastException = ProviderException::networkError($e->getMessage(), $e);
                $this->recordFailure();

                if ($attempt < $this->maxRetries) {
                    $delay = (int) ($this->retryDelay * pow($this->retryMultiplier, $attempt));
                    usleep($delay * 1000);

                    continue;
                }
            }
        }

        $this->recordFailure();
        throw $lastException ?? ProviderException::networkError('Unknown error occurred');
    }

    public function sendStream(ProviderRequest $request): Generator
    {
        $this->checkCircuitBreaker();

        $request->stream = true;
        $body = $this->buildRequestBody($request);
        $endpoint = $this->getEndpoint($request);
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        try {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout($this->timeout)
                ->connectTimeout($this->connectTimeout)
                ->withOptions(['stream' => true])
                ->post($url, $body);

            $this->recordSuccess();

            $buffer = '';
            $stream = $response->toPsrResponse()->getBody();

            while (! $stream->eof()) {
                $chunk = $stream->read(1024);
                $buffer .= $chunk;

                while (($pos = strpos($buffer, "\n\n")) !== false) {
                    $rawChunk = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 2);

                    $parsed = $this->parseStreamChunk($rawChunk);
                    if ($parsed !== null) {
                        yield $parsed;
                    }
                }
            }

            if (! empty($buffer)) {
                $parsed = $this->parseStreamChunk($buffer);
                if ($parsed !== null) {
                    yield $parsed;
                }
            }
        } catch (ConnectionException $e) {
            $this->recordFailure();
            throw ProviderException::networkError($e->getMessage(), $e);
        }
    }

    protected function executeRequest(ProviderRequest $request): ProviderResponse
    {
        $body = $this->buildRequestBody($request);
        $endpoint = $this->getEndpoint($request);
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($endpoint, '/');

        $response = Http::withHeaders($this->getHeaders())
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->post($url, $body);

        if (! $response->ok()) {
            throw $this->createErrorFromResponse($response);
        }

        $this->recordSuccess();

        return $this->parseResponse($response->json());
    }

    protected function createErrorFromResponse($response): ProviderException
    {
        $statusCode = $response->status();
        $body = $response->json();

        $this->lastErrorMessage = $body['error']['message'] ?? $response->body();

        return match ($statusCode) {
            401 => ProviderException::authError($this->lastErrorMessage, $body),
            403 => ProviderException::authError($this->lastErrorMessage, $body),
            429 => ProviderException::rateLimit($this->lastErrorMessage, $body),
            400 => ProviderException::invalidRequest($this->lastErrorMessage, $body),
            404 => ProviderException::modelNotFound('', $body),
            default => ProviderException::serverError($this->lastErrorMessage, $statusCode, $body),
        };
    }

    protected function checkCircuitBreaker(): void
    {
        if ($this->circuitState === 'open') {
            $elapsed = time() - ($this->circuitOpenTime ?? 0);

            if ($elapsed >= $this->circuitResetTimeout) {
                $this->circuitState = 'half-open';
            } else {
                throw ProviderException::circuitOpen($this->getProviderName());
            }
        }
    }

    protected function recordFailure(): void
    {
        $this->circuitFailures++;

        if ($this->circuitFailures >= $this->circuitFailureThreshold) {
            $this->circuitState = 'open';
            $this->circuitOpenTime = time();

            Log::warning("Circuit breaker opened for provider: {$this->getProviderName()}");
        }
    }

    protected function recordSuccess(): void
    {
        $this->circuitFailures = 0;
        $this->circuitState = 'closed';
        $this->lastErrorMessage = null;
    }

    public function healthCheck(): bool
    {
        try {
            $models = $this->getModels();

            return ! empty($models);
        } catch (\Throwable $e) {
            $this->lastErrorMessage = $e->getMessage();

            return false;
        }
    }

    public function isAvailable(): bool
    {
        return $this->circuitState !== 'open' && ! empty($this->apiKey);
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }

    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);

        return $this;
    }

    protected function safeJsonDecode(string $json, ?array $default = null): ?array
    {
        try {
            return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $default;
        }
    }
}
