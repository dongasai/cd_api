<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\CodingStatus\ChannelCodingStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 渠道Coding状态 API 控制器
 */
class ChannelCodingStatusController extends Controller
{
    protected ChannelCodingStatusService $codingStatusService;

    public function __construct(ChannelCodingStatusService $codingStatusService)
    {
        $this->codingStatusService = $codingStatusService;
    }

    /**
     * 获取渠道Coding状态
     */
    public function show(int $channelId): JsonResponse
    {
        $channel = Channel::find($channelId);

        if (!$channel) {
            return response()->json([
                'error' => [
                    'message' => '渠道不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $status = $this->codingStatusService->getChannelCodingStatus($channel);

        return response()->json($status);
    }

    /**
     * 更新渠道Coding配置
     */
    public function update(Request $request, int $channelId): JsonResponse
    {
        $channel = Channel::find($channelId);

        if (!$channel) {
            return response()->json([
                'error' => [
                    'message' => '渠道不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        try {
            $validated = $request->validate([
                'coding_account_id' => 'nullable|integer|exists:coding_accounts,id',
                'auto_disable' => 'sometimes|boolean',
                'auto_enable' => 'sometimes|boolean',
                'disable_threshold' => 'sometimes|numeric|between:0,1',
                'warning_threshold' => 'sometimes|numeric|between:0,1',
                'priority' => 'sometimes|integer',
                'fallback_channel_id' => 'nullable|integer|exists:channels,id',
            ]);

            // 更新Coding账户关联
            if (isset($validated['coding_account_id'])) {
                $channel->update(['coding_account_id' => $validated['coding_account_id']]);
            }

            // 更新覆盖配置
            $override = $channel->getCodingStatusOverride();
            foreach (['auto_disable', 'auto_enable', 'disable_threshold', 'warning_threshold', 'priority', 'fallback_channel_id'] as $key) {
                if (isset($validated[$key])) {
                    $override[$key] = $validated[$key];
                }
            }
            $channel->update(['coding_status_override' => $override]);

            return response()->json([
                'data' => $this->codingStatusService->getChannelCodingStatus($channel),
                'message' => '渠道Coding配置更新成功',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => [
                    'message' => '验证失败',
                    'errors' => $e->errors(),
                ],
            ], 422);
        }
    }

    /**
     * 手动禁用渠道
     */
    public function disable(Request $request, int $channelId): JsonResponse
    {
        $channel = Channel::find($channelId);

        if (!$channel) {
            return response()->json([
                'error' => [
                    'message' => '渠道不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $userId = $request->user()?->id;
        $reason = $request->input('reason', '手动禁用');

        $result = $this->codingStatusService->manualDisableChannel($channel, $userId, $reason);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'error' => [
                    'message' => $result['message'],
                    'code' => 'DISABLE_FAILED',
                ],
            ], 422);
        }
    }

    /**
     * 手动启用渠道
     */
    public function enable(Request $request, int $channelId): JsonResponse
    {
        $channel = Channel::find($channelId);

        if (!$channel) {
            return response()->json([
                'error' => [
                    'message' => '渠道不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $userId = $request->user()?->id;
        $reason = $request->input('reason', '手动启用');

        $result = $this->codingStatusService->manualEnableChannel($channel, $userId, $reason);

        if ($result['success']) {
            return response()->json([
                'message' => $result['message'],
            ]);
        } else {
            return response()->json([
                'error' => [
                    'message' => $result['message'],
                    'code' => 'ENABLE_FAILED',
                    'quota_status' => $result['quota_status'] ?? null,
                ],
            ], 422);
        }
    }

    /**
     * 检查请求配额
     */
    public function checkQuota(Request $request, int $channelId): JsonResponse
    {
        $channel = Channel::find($channelId);

        if (!$channel) {
            return response()->json([
                'error' => [
                    'message' => '渠道不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $context = [
            'model' => $request->input('model', ''),
            'requests' => $request->input('requests', 1),
            'tokens_input' => $request->input('tokens_input', 0),
            'tokens_output' => $request->input('tokens_output', 0),
            'prompts' => $request->input('prompts', 1),
        ];

        $result = $this->codingStatusService->checkRequestAllowed($channel, $context);

        return response()->json($result);
    }

    /**
     * 批量检查渠道状态
     */
    public function batchCheck(): JsonResponse
    {
        $results = $this->codingStatusService->batchCheckAndUpdate();

        return response()->json($results);
    }
}
