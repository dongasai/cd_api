<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $this->extractApiKey($request);

        // 调试日志：记录 MCP 请求认证信息
        if ($request->is('mcp/*')) {
            \Log::debug('MCP Request Auth', [
                'path' => $request->path(),
                'has_api_key' => ! empty($apiKey),
                'authorization_header' => $request->header('Authorization'),
                'x_api_key_header' => $request->header('X-API-Key'),
                'query_api_key' => $request->query('api_key'),
                'all_headers' => $request->headers->all(),
            ]);
        }

        if (! $apiKey) {
            return response()->json([
                'error' => [
                    'message' => 'Missing API key',
                    'type' => 'authentication_error',
                    'code' => 'missing_api_key',
                ],
            ], 401);
        }

        $keyRecord = ApiKey::where('key', $apiKey)->first();

        if (! $keyRecord) {
            return response()->json([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'authentication_error',
                    'code' => 'invalid_api_key',
                ],
            ], 401);
        }

        if (! $keyRecord->isActive()) {
            return response()->json([
                'error' => [
                    'message' => $keyRecord->isExpired() ? 'API key has expired' : 'API key is inactive',
                    'type' => 'authentication_error',
                    'code' => $keyRecord->isExpired() ? 'expired_api_key' : 'inactive_api_key',
                ],
            ], 401);
        }

        // 更新最后使用时间
        $keyRecord->update(['last_used_at' => now()]);

        // 将 API Key 记录附加到请求属性
        $request->attributes->set('api_key', $keyRecord);

        return $next($request);
    }

    /**
     * 从请求中提取 API Key
     */
    protected function extractApiKey(Request $request): ?string
    {
        // 从 Authorization 头中提取 Bearer token
        $authorization = $request->header('Authorization');
        if ($authorization && str_starts_with($authorization, 'Bearer ')) {
            return substr($authorization, 7);
        }

        // 从 X-API-Key 头中提取
        $apiKey = $request->header('X-API-Key');
        if ($apiKey) {
            return $apiKey;
        }

        // 从查询参数中提取
        return $request->query('api_key');
    }
}
