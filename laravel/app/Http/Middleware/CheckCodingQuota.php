<?php

namespace App\Http\Middleware;

use App\Services\CodingStatus\ChannelCodingStatusService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coding配额检查中间件
 *
 * 在请求处理前检查Coding配额，配额不足时返回429错误
 */
class CheckCodingQuota
{
    protected ChannelCodingStatusService $codingStatusService;

    public function __construct(ChannelCodingStatusService $codingStatusService)
    {
        $this->codingStatusService = $codingStatusService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 从请求中获取渠道信息
        // 渠道信息可能在请求属性中，或者从路由参数获取
        $channel = $request->attributes->get('channel');

        if (!$channel) {
            // 如果没有渠道信息，尝试从路由参数获取
            $channelId = $request->route('channel_id');
            if ($channelId) {
                $channel = \App\Models\Channel::find($channelId);
            }
        }

        // 如果没有渠道信息，直接放行
        if (!$channel) {
            return $next($request);
        }

        // 检查渠道是否绑定Coding账户
        if (!$channel->hasCodingAccount()) {
            return $next($request);
        }

        // 构建检查上下文
        $context = $this->buildContext($request);

        // 检查配额
        $checkResult = $this->codingStatusService->checkRequestAllowed($channel, $context);

        if (!$checkResult['allowed']) {
            // 配额不足，返回429错误
            return response()->json([
                'error' => [
                    'message' => $checkResult['message'],
                    'code' => $checkResult['code'] ?? 'QUOTA_EXCEEDED',
                    'type' => 'coding_quota_exceeded',
                ],
            ], 429);
        }

        // 配额充足，继续处理请求
        $response = $next($request);

        // 请求成功后，异步记录配额使用
        $this->recordUsage($request, $response, $channel);

        return $response;
    }

    /**
     * 构建配额检查上下文
     */
    protected function buildContext(Request $request): array
    {
        $context = [
            'model' => $request->input('model', ''),
            'requests' => 1, // 默认1次请求
        ];

        // 尝试估算Token数量
        $messages = $request->input('messages', []);
        if (!empty($messages)) {
            $estimatedTokens = $this->estimateTokens($messages);
            $context['tokens_input'] = $estimatedTokens['input'];
            $context['tokens_output'] = $estimatedTokens['output'];
        }

        // 如果是completion请求，计算prompts
        if ($request->has('messages') || $request->has('prompt')) {
            $context['prompts'] = 1;
        }

        return $context;
    }

    /**
     * 估算Token数量
     *
     * 简单的估算方法：每4个字符约等于1个token
     */
    protected function estimateTokens(array $messages): array
    {
        $inputChars = 0;
        $outputChars = 0;

        foreach ($messages as $message) {
            $content = $message['content'] ?? '';
            $role = $message['role'] ?? 'user';

            if ($role === 'user' || $role === 'system') {
                $inputChars += strlen($content);
            } else {
                $outputChars += strlen($content);
            }
        }

        // 简单估算：4个字符 ≈ 1个token
        return [
            'input' => (int) ceil($inputChars / 4),
            'output' => (int) ceil($outputChars / 4),
        ];
    }

    /**
     * 记录配额使用
     */
    protected function recordUsage(Request $request, Response $response, $channel): void
    {
        // 只记录成功的请求
        if ($response->getStatusCode() !== 200) {
            return;
        }

        try {
            $usage = [
                'request_id' => $request->header('X-Request-ID') ?: uniqid(),
                'model' => $request->input('model', ''),
                'status' => \App\Models\CodingUsageLog::STATUS_SUCCESS,
            ];

            // 尝试从响应中获取实际使用量
            $responseData = json_decode($response->getContent(), true);
            if ($responseData && isset($responseData['usage'])) {
                $usage['tokens_input'] = $responseData['usage']['prompt_tokens'] ?? 0;
                $usage['tokens_output'] = $responseData['usage']['completion_tokens'] ?? 0;
            } else {
                // 使用估算值
                $context = $this->buildContext($request);
                $usage['tokens_input'] = $context['tokens_input'] ?? 0;
                $usage['tokens_output'] = $context['tokens_output'] ?? 0;
            }

            // 记录prompts
            if ($request->has('messages') || $request->has('prompt')) {
                $usage['prompts'] = 1;
            }

            // 异步记录使用
            // 使用dispatch或事件来异步处理
            dispatch(function () use ($channel, $usage) {
                app(ChannelCodingStatusService::class)->recordUsage($channel, $usage);
            })->afterResponse();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('记录Coding配额使用失败', [
                'channel_id' => $channel->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
