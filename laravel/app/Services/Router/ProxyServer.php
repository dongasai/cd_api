<?php

namespace App\Services\Router;

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\RequestLog;
use App\Models\ResponseLog;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\Protocol\DTO\StandardRequest;
use App\Services\Protocol\DTO\StandardResponse;
use App\Services\Protocol\ProtocolConverter;
use App\Services\Provider\DTO\ProviderRequest;
use App\Services\Provider\DTO\ProviderResponse;
use App\Services\Provider\ProviderManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Generator;

class ProxyServer
{
    protected ProtocolConverter $protocolConverter;
    protected ProviderManager $providerManager;
    protected ChannelRouterService $channelRouter;
    protected ChannelCodingStatusService $codingStatusService;

    protected ?string $requestId = null;
    protected float $startTime;
    protected ?int $firstTokenMs = null;
    protected ?Channel $selectedChannel = null;

    public function __construct(
        ProtocolConverter $protocolConverter,
        ProviderManager $providerManager,
        ChannelRouterService $channelRouter,
        ChannelCodingStatusService $codingStatusService
    ) {
        $this->protocolConverter = $protocolConverter;
        $this->providerManager = $providerManager;
        $this->channelRouter = $channelRouter;
        $this->codingStatusService = $codingStatusService;
        $this->startTime = microtime(true);
    }

    public function proxy(Request $request, string $protocol = 'openai'): array|Generator
    {
        $this->requestId = $request->attributes->get('request_id', Str::uuid()->toString());
        $this->startTime = microtime(true);

        $rawRequest = $request->all();
        $isStream = $rawRequest['stream'] ?? false;

        $requestLog = $this->createRequestLog($request, $rawRequest, $protocol);

        try {
            $standardRequest = $this->protocolConverter->normalizeRequest($rawRequest, $protocol);

            $this->updateRequestLogModel($requestLog, $standardRequest);

            $this->selectedChannel = $this->channelRouter->selectChannel($standardRequest->model);

            $channelProtocol = $this->getChannelProtocol($this->selectedChannel);

            $providerRequest = $this->buildProviderRequest($standardRequest, $this->selectedChannel, $channelProtocol);

            $provider = $this->providerManager->getForChannel($this->selectedChannel);

            if ($isStream) {
                return $this->handleStreamRequest($request, $standardRequest, $providerRequest, $provider, $protocol, $channelProtocol, $requestLog);
            }

            return $this->handleNonStreamRequest($request, $standardRequest, $providerRequest, $provider, $protocol, $channelProtocol, $requestLog);
        } catch (\Exception $e) {
            $this->handleError($e, $request, $requestLog);

            throw $e;
        }
    }

    protected function handleNonStreamRequest(
        Request $httpRequest,
        StandardRequest $standardRequest,
        ProviderRequest $providerRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog
    ): array {
        $providerResponse = $provider->send($providerRequest);

        $latencyMs = $this->calculateLatency();

        $standardResponse = $this->normalizeProviderResponse($providerResponse, $targetProtocol);

        $response = $this->buildResponse($standardResponse, $sourceProtocol);

        $this->createAuditLog($httpRequest, $standardRequest, $standardResponse, $latencyMs, $providerResponse);

        $this->createResponseLog($requestLog, $response, $providerResponse, $latencyMs);

        $this->recordUsage($standardRequest, $standardResponse);

        return $response;
    }

    protected function handleStreamRequest(
        Request $httpRequest,
        StandardRequest $standardRequest,
        ProviderRequest $providerRequest,
        $provider,
        string $sourceProtocol,
        string $targetProtocol,
        RequestLog $requestLog
    ): Generator {
        $stream = $provider->sendStream($providerRequest);

        $fullContent = '';
        $usage = null;
        $finishReason = null;
        $firstTokenTime = null;

        foreach ($stream as $chunk) {
            if ($firstTokenTime === null) {
                $this->firstTokenMs = (int) ((microtime(true) - $this->startTime) * 1000);
                $firstTokenTime = microtime(true);
            }

            $standardEvent = $this->parseStreamChunk($chunk, $targetProtocol);
            if ($standardEvent === null) {
                continue;
            }

            if ($standardEvent->content) {
                $fullContent .= $standardEvent->content;
            }

            if ($standardEvent->usage) {
                $usage = $standardEvent->usage;
            }

            if ($standardEvent->finishReason) {
                $finishReason = $standardEvent->finishReason;
            }

            $convertedChunk = $this->convertStreamChunk($standardEvent, $sourceProtocol);

            yield $convertedChunk;
        }

        $latencyMs = $this->calculateLatency();

        $standardResponse = new StandardResponse(
            content: $fullContent,
            model: $standardRequest->model,
            usage: $usage,
            finishReason: $finishReason,
            isStream: true
        );

        $this->createAuditLog($httpRequest, $standardRequest, $standardResponse, $latencyMs, null, true);

        $this->createResponseLog($requestLog, ['stream' => true, 'content' => $fullContent], null, $latencyMs, true, $usage, $finishReason);

        $this->recordUsage($standardRequest, $standardResponse);
    }

    protected function createRequestLog(Request $request, array $rawRequest, string $protocol): RequestLog
    {
        return RequestLog::create([
            'request_id' => $this->requestId,
            'method' => $request->method(),
            'path' => $request->path(),
            'query_string' => $request->getQueryString(),
            'headers' => $this->filterSensitiveHeaders($request->headers->all()),
            'content_type' => $request->contentType(),
            'content_length' => strlen($request->getContent()),
            'body_text' => $this->truncateBody(json_encode($rawRequest)),
            'model' => $rawRequest['model'] ?? null,
            'model_params' => $this->extractModelParams($rawRequest),
            'messages' => $rawRequest['messages'] ?? null,
            'prompt' => $this->extractPrompt($rawRequest),
            'sensitive_fields' => [],
            'has_sensitive' => false,
            'metadata' => [
                'protocol' => $protocol,
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }

    protected function updateRequestLogModel(RequestLog $log, StandardRequest $request): void
    {
        $log->update([
            'model' => $request->model,
            'model_params' => [
                'temperature' => $request->temperature,
                'top_p' => $request->topP,
                'max_tokens' => $request->maxTokens,
                'stream' => $request->stream,
            ],
        ]);
    }

    protected function createAuditLog(
        Request $httpRequest,
        StandardRequest $standardRequest,
        StandardResponse $standardResponse,
        int $latencyMs,
        ?ProviderResponse $providerResponse,
        bool $isStream = false
    ): AuditLog {
        $user = $httpRequest->user();
        $apiKey = $httpRequest->attributes->get('api_key');

        $usage = $standardResponse->usage;
        $cost = $this->calculateCost($standardRequest->model, $usage);

        return AuditLog::create([
            'user_id' => $user?->id,
            'username' => $user?->name,
            'api_key_id' => $apiKey?->id,
            'api_key_name' => $apiKey?->name,
            'cached_key_prefix' => $apiKey?->key_prefix,
            'channel_id' => $this->selectedChannel?->id,
            'channel_name' => $this->selectedChannel?->name,
            'request_id' => $this->requestId,
            'request_type' => AuditLog::REQUEST_TYPE_CHAT,
            'model' => $standardRequest->model,
            'actual_model' => $standardResponse->model ?? $standardRequest->model,
            'prompt_tokens' => $usage?->promptTokens ?? 0,
            'completion_tokens' => $usage?->completionTokens ?? 0,
            'total_tokens' => $usage?->totalTokens ?? 0,
            'cost' => $cost,
            'quota' => $cost,
            'billing_source' => AuditLog::BILLING_SOURCE_QUOTA,
            'status_code' => 200,
            'latency_ms' => $latencyMs,
            'first_token_ms' => $this->firstTokenMs,
            'is_stream' => $isStream,
            'finish_reason' => $standardResponse->finishReason,
            'client_ip' => $httpRequest->ip(),
            'user_agent' => $httpRequest->userAgent(),
            'created_at' => now(),
        ]);
    }

    protected function createResponseLog(
        RequestLog $requestLog,
        array $response,
        ?ProviderResponse $providerResponse,
        int $latencyMs,
        bool $isStream = false,
        $usage = null,
        ?string $finishReason = null
    ): ResponseLog {
        return ResponseLog::create([
            'audit_log_id' => $requestLog->audit_log_id,
            'request_id' => $this->requestId,
            'request_log_id' => $requestLog->id,
            'status_code' => 200,
            'headers' => ['content-type' => 'application/json'],
            'content_type' => 'application/json',
            'content_length' => strlen(json_encode($response)),
            'body_text' => $this->truncateBody(json_encode($response)),
            'response_type' => $isStream ? 'stream' : 'json',
            'finish_reason' => $finishReason,
            'usage' => $usage ? [
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? 0,
            ] : null,
            'upstream_provider' => $this->selectedChannel?->provider,
            'upstream_model' => $providerResponse?->model,
            'upstream_latency_ms' => $latencyMs,
        ]);
    }

    protected function handleError(\Exception $e, Request $request, RequestLog $requestLog): void
    {
        $latencyMs = $this->calculateLatency();

        $user = $request->user();
        $apiKey = $request->attributes->get('api_key');

        AuditLog::create([
            'user_id' => $user?->id,
            'username' => $user?->name,
            'api_key_id' => $apiKey?->id,
            'api_key_name' => $apiKey?->name,
            'cached_key_prefix' => $apiKey?->key_prefix,
            'channel_id' => $this->selectedChannel?->id,
            'channel_name' => $this->selectedChannel?->name,
            'request_id' => $this->requestId,
            'request_type' => AuditLog::REQUEST_TYPE_CHAT,
            'status_code' => $this->getStatusCode($e),
            'latency_ms' => $latencyMs,
            'error_type' => get_class($e),
            'error_message' => $e->getMessage(),
            'client_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        Log::error('Proxy request failed', [
            'request_id' => $this->requestId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    protected function getChannelProtocol(Channel $channel): string
    {
        $provider = $channel->provider;

        if (in_array($provider, ['anthropic', 'claude'])) {
            return 'anthropic';
        }

        return 'openai';
    }

    protected function buildProviderRequest(StandardRequest $standardRequest, Channel $channel, string $targetProtocol): ProviderRequest
    {
        if ($targetProtocol === 'anthropic') {
            $requestData = $standardRequest->toAnthropic();
        } else {
            $requestData = $standardRequest->toOpenAI();
        }

        return new ProviderRequest(
            endpoint: $this->buildEndpoint($channel, $standardRequest),
            method: 'POST',
            headers: $this->buildHeaders($channel),
            body: $requestData,
            timeout: 300,
            model: $standardRequest->model
        );
    }

    protected function buildEndpoint(Channel $channel, StandardRequest $request): string
    {
        $baseUrl = rtrim($channel->base_url, '/');

        $provider = strtolower($channel->provider);

        if (in_array($provider, ['anthropic', 'claude'])) {
            return $baseUrl.'/v1/messages';
        }

        return $baseUrl.'/v1/chat/completions';
    }

    protected function buildHeaders(Channel $channel): array
    {
        $provider = strtolower($channel->provider);
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (in_array($provider, ['anthropic', 'claude'])) {
            $headers['x-api-key'] = $channel->api_key;
            $headers['anthropic-version'] = '2023-06-01';
        } else {
            $headers['Authorization'] = 'Bearer '.$channel->api_key;
        }

        return $headers;
    }

    protected function normalizeProviderResponse(ProviderResponse $response, string $protocol): StandardResponse
    {
        return $this->protocolConverter->normalizeResponse($response->data, $protocol);
    }

    protected function buildResponse(StandardResponse $standardResponse, string $protocol): array
    {
        return $this->protocolConverter->denormalizeResponse($standardResponse, $protocol);
    }

    protected function parseStreamChunk(string $chunk, string $protocol): ?object
    {
        $driver = $this->protocolConverter->driver($protocol);

        return $driver->parseStreamEvent($chunk);
    }

    protected function convertStreamChunk(object $event, string $targetProtocol): string
    {
        $driver = $this->protocolConverter->driver($targetProtocol);

        return $driver->buildStreamChunk($event);
    }

    protected function calculateLatency(): int
    {
        return (int) ((microtime(true) - $this->startTime) * 1000);
    }

    protected function calculateCost(string $model, $usage): float
    {
        if (!$usage) {
            return 0.0;
        }

        $promptTokens = $usage->promptTokens ?? 0;
        $completionTokens = $usage->completionTokens ?? 0;

        $promptRate = 0.00001;
        $completionRate = 0.00003;

        if (str_contains(strtolower($model), 'gpt-4')) {
            $promptRate = 0.00003;
            $completionRate = 0.00006;
        } elseif (str_contains(strtolower($model), 'claude-3')) {
            $promptRate = 0.000015;
            $completionRate = 0.000075;
        }

        return ($promptTokens * $promptRate) + ($completionTokens * $completionRate);
    }

    protected function recordUsage(StandardRequest $request, StandardResponse $response): void
    {
        if (!$this->selectedChannel || !$this->selectedChannel->hasCodingAccount()) {
            return;
        }

        $usage = $response->usage;
        if (!$usage) {
            return;
        }

        $this->codingStatusService->recordUsage($this->selectedChannel, [
            'requests' => 1,
            'tokens_input' => $usage->promptTokens ?? 0,
            'tokens_output' => $usage->completionTokens ?? 0,
            'model' => $request->model,
        ]);
    }

    protected function filterSensitiveHeaders(array $headers): array
    {
        $sensitiveKeys = ['authorization', 'x-api-key', 'cookie', 'set-cookie'];

        return array_filter(
            $headers,
            fn ($key) => !in_array(strtolower($key), $sensitiveKeys),
            ARRAY_FILTER_USE_KEY
        );
    }

    protected function truncateBody(?string $body, int $maxLength = 10000): ?string
    {
        if (!$body) {
            return null;
        }

        if (strlen($body) <= $maxLength) {
            return $body;
        }

        return substr($body, 0, $maxLength).'...[truncated]';
    }

    protected function extractModelParams(array $request): array
    {
        $params = [];
        $keys = ['temperature', 'top_p', 'max_tokens', 'presence_penalty', 'frequency_penalty', 'n', 'stream'];

        foreach ($keys as $key) {
            if (isset($request[$key])) {
                $params[$key] = $request[$key];
            }
        }

        return $params;
    }

    protected function extractPrompt(array $request): ?string
    {
        $messages = $request['messages'] ?? [];
        $prompt = '';

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';
            $content = $message['content'] ?? '';

            if (is_string($content)) {
                $prompt .= "[{$role}]: {$content}\n";
            }
        }

        return $prompt ?: null;
    }

    protected function getStatusCode(\Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if (method_exists($e, 'getCode') && $e->getCode() > 0) {
            return $e->getCode();
        }

        return 500;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function getSelectedChannel(): ?Channel
    {
        return $this->selectedChannel;
    }
}
