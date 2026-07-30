<?php

namespace App\Http\Middleware;

use App\Services\RateLimit\DTO\RateLimitContext;
use App\Services\RateLimit\DTO\RateLimitResult;
use App\Services\RateLimit\Exceptions\RateLimitExceededException;
use App\Services\RateLimit\RateLimitManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 限流中间件
 *
 * 对 API 请求进行限流检查
 */
class RateLimitMiddleware
{
    /**
     * @param  RateLimitManager  $rateLimitManager  限流管理器
     */
    public function __construct(
        protected RateLimitManager $rateLimitManager,
    ) {}

    /**
     * 处理请求
     *
     * @param  Request  $request  HTTP 请求
     * @param  Closure  $next  下一个处理器
     *
     * @throws RateLimitExceededException
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 构建限流上下文
        $context = $this->buildContext($request);

        try {
            // 执行限流检查
            $result = $this->rateLimitManager->check($context);

            // 获取响应
            $response = $next($request);

            // 添加限流响应头
            if (config('rate_limit.headers.enabled', true)) {
                $response = $this->addRateLimitHeaders($response, $result);
            }

            return $response;

        } catch (RateLimitExceededException $e) {
            // 返回限流错误响应
            return $this->buildErrorResponse($e);
        }
    }

    /**
     * 构建限流上下文
     */
    protected function buildContext(Request $request): RateLimitContext
    {
        // 从请求属性中获取 API Key ID
        $apiKeyId = $request->attributes->get('api_key_id');

        // 从请求属性中获取渠道 ID
        $channelId = $request->attributes->get('channel_id');

        // 从请求中获取模型
        $model = $this->extractModel($request);

        return new RateLimitContext(
            apiKeyId: $apiKeyId,
            channelId: $channelId,
            model: $model,
            metadata: [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'route' => $request->route()?->getName(),
            ],
        );
    }

    /**
     * 从请求中提取模型名称
     */
    protected function extractModel(Request $request): ?string
    {
        // 从请求体中获取模型
        $model = $request->input('model');

        if ($model !== null) {
            return $model;
        }

        // 从路由参数获取
        return $request->route('model');
    }

    /**
     * 添加限流响应头
     */
    protected function addRateLimitHeaders(Response $response, RateLimitResult $result): Response
    {
        if ($result->isDenied()) {
            return $response;
        }

        $remaining = $result->getRemaining();
        $limits = $result->limits;

        if ($remaining !== null) {
            $response->headers->set(
                config('rate_limit.headers.remaining_header', 'X-RateLimit-Remaining'),
                $remaining
            );
        }

        if (isset($limits['max'])) {
            $response->headers->set(
                config('rate_limit.headers.limit_header', 'X-RateLimit-Limit'),
                $limits['max']
            );
        }

        return $response;
    }

    /**
     * 构建限流错误响应
     */
    protected function buildErrorResponse(RateLimitExceededException $e): Response
    {
        $response = response()->json(
            $e->toArray(),
            $e->getStatusCode()
        );

        // 添加 Retry-After 头
        $retryAfter = $e->getRetryAfter();
        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', $retryAfter);
        }

        return $response;
    }
}
