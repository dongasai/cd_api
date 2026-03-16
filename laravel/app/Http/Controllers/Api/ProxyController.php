<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ModelService;
use App\Services\Provider\Exceptions\ProviderException;
use App\Services\Router\ProxyServer;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\StreamedResponse;
use Illuminate\Support\Facades\Log;

class ProxyController extends Controller
{
    protected ProxyServer $proxyServer;

    public function __construct(ProxyServer $proxyServer)
    {
        $this->proxyServer = $proxyServer;
    }

    public function chatCompletions(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->handleRequest($request, 'openai');
    }

    public function completions(Request $request): JsonResponse|StreamedResponse
    {
        return $this->handleRequest($request, 'openai');
    }

    public function embeddings(Request $request): JsonResponse
    {
        try {
            $result = $this->proxyServer->proxy($request, 'openai');

            if ($result instanceof Generator) {
                return response()->json([
                    'error' => [
                        'message' => 'Embeddings do not support streaming',
                        'type' => 'invalid_request_error',
                    ],
                ], 400);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e);
        }
    }

    /**
     * 可用模型
     */
    public function models(Request $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        // 使用 ModelService 获取可用模型列表
        $data = ModelService::getAvailableModels($apiKey);

        return response()->json([
            'object' => 'list',
            'data' => $data,
        ]);
    }

    public function anthropicMessages(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        return $this->handleRequest($request, 'anthropic');
    }

    protected function handleRequest(Request $request, string $protocol): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        try {
            $result = $this->proxyServer->proxy($request, $protocol);

            if ($result instanceof Generator) {
                return $this->streamResponse($result, $protocol);
            }

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $protocol);
        }
    }

    protected function streamResponse(Generator $generator, string $protocol): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];

        if ($protocol === 'anthropic') {
            $headers['anthropic-version'] = '2023-06-01';
        }

        return response()->stream(function () use ($generator) {
            try {
                foreach ($generator as $chunk) {
                    Log::debug("streamResponsetoClient \n".$chunk);
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } finally {
                // 确保 Generator 被关闭，触发 ProxyServer 中的 finally 块
                // 这样即使客户端断开连接，审计日志也会被正确更新
                if ($generator instanceof Generator) {
                    // 如果 Generator 还没有完成，强制关闭它
                    if ($generator->valid()) {
                        try {
                            // 尝试获取 Generator 的返回值（这会触发 finally 块）
                            $generator->getReturn();
                        } catch (\Exception $e) {
                            // 忽略异常，重点是触发 finally 块
                        }
                    }
                }
            }
        }, 200, $headers);
    }

    protected function handleException(\Exception $e, string $protocol = 'openai'): JsonResponse
    {
        // 如果是 ProviderException（渠道错误），记录详细日志，返回通用错误
        if ($e instanceof ProviderException && $e->isChannelError()) {
            Log::error('Channel error', [
                'error_type' => $e->getErrorType(),
                'error_message' => $e->getMessage(),
                'raw_error' => $e->getRawError(),
                'trace' => $e->getTraceAsString(),
            ]);

            // 返回通用的渠道错误信息，不暴露具体错误细节
            $error = $this->buildChannelErrorResponse($protocol);

            return response()->json($error, 500);
        }

        $statusCode = $this->getStatusCode($e);

        $error = $this->buildErrorResponse($e, $protocol);

        return response()->json($error, $statusCode);
    }

    /**
     * 构建渠道错误的通用响应（不暴露具体错误细节）
     */
    protected function buildChannelErrorResponse(string $protocol): array
    {
        if ($protocol === 'anthropic') {
            return [
                'type' => 'error',
                'error' => [
                    'type' => 'api_error',
                    'message' => 'An error occurred while processing your request. Please try again later.',
                ],
            ];
        }

        return [
            'error' => [
                'message' => 'An error occurred while processing your request. Please try again later.',
                'type' => 'api_error',
                'code' => null,
            ],
        ];
    }

    protected function getStatusCode(\Exception $e): int
    {
        if (method_exists($e, 'getStatusCode')) {
            return $e->getStatusCode();
        }

        if ($e instanceof \Illuminate\Validation\ValidationException) {
            return 422;
        }

        if ($e instanceof \RuntimeException) {
            return 503;
        }

        return 500;
    }

    protected function buildErrorResponse(\Exception $e, string $protocol): array
    {
        $message = $e->getMessage();
        $type = $this->getErrorType($e);

        if ($protocol === 'anthropic') {
            return [
                'type' => 'error',
                'error' => [
                    'type' => $type,
                    'message' => $message,
                ],
            ];
        }

        return [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $this->getErrorCode($e),
            ],
        ];
    }

    protected function getErrorType(\Exception $e): string
    {
        $className = get_class($e);

        return match (true) {
            str_contains($className, 'Validation') => 'invalid_request_error',
            str_contains($className, 'Authentication') => 'authentication_error',
            str_contains($className, 'Permission') => 'permission_error',
            str_contains($className, 'NotFound') => 'not_found_error',
            str_contains($className, 'RateLimit') => 'rate_limit_error',
            str_contains($className, 'Quota') => 'insufficient_quota_error',
            default => 'api_error',
        };
    }

    protected function getErrorCode(\Exception $e): ?string
    {
        $className = get_class($e);

        return match (true) {
            str_contains($className, 'Validation') => 'invalid_request',
            str_contains($className, 'Authentication') => 'invalid_api_key',
            str_contains($className, 'RateLimit') => 'rate_limit_exceeded',
            str_contains($className, 'Quota') => 'insufficient_quota',
            default => null,
        };
    }
}
