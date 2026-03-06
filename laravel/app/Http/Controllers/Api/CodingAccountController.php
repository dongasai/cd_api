<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CodingAccount;
use App\Services\CodingStatus\ChannelCodingStatusService;
use App\Services\CodingStatus\CodingStatusDriverManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Coding账户 API 控制器
 */
class CodingAccountController extends Controller
{
    protected CodingStatusDriverManager $driverManager;
    protected ChannelCodingStatusService $channelService;

    public function __construct(
        CodingStatusDriverManager $driverManager,
        ChannelCodingStatusService $channelService
    ) {
        $this->driverManager = $driverManager;
        $this->channelService = $channelService;
    }

    /**
     * 获取账户列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = CodingAccount::query();

        // 筛选
        if ($request->has('platform')) {
            $query->where('platform', $request->input('platform'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('driver_class')) {
            $query->where('driver_class', $request->input('driver_class'));
        }

        $accounts = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $accounts->items(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'last_page' => $accounts->lastPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
            ],
        ]);
    }

    /**
     * 创建账户
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'platform' => 'required|string|in:' . implode(',', array_keys(CodingAccount::getPlatforms())),
                'driver_class' => 'required|string',
                'credentials' => 'required|array',
                'quota_config' => 'nullable|array',
                'config' => 'nullable|array',
                'expires_at' => 'nullable|date',
            ]);

            // 验证驱动类是否有效
            if (!$this->driverManager->hasDriver($validated['driver_class'])) {
                return response()->json([
                    'error' => [
                        'message' => '无效的驱动类型: ' . $validated['driver_class'],
                        'code' => 'INVALID_DRIVER',
                    ],
                ], 422);
            }

            // 如果没有提供配额配置，使用默认配置
            if (empty($validated['quota_config'])) {
                $validated['quota_config'] = $this->driverManager->getDefaultConfig($validated['driver_class']);
            }

            $account = CodingAccount::create($validated);

            return response()->json([
                'data' => $account,
                'message' => 'Coding账户创建成功',
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'message' => '验证失败',
                    'errors' => $e->errors(),
                ],
            ], 422);
        }
    }

    /**
     * 获取账户详情
     */
    public function show(int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        return response()->json([
            'data' => $account,
        ]);
    }

    /**
     * 更新账户
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'platform' => 'sometimes|string|in:' . implode(',', array_keys(CodingAccount::getPlatforms())),
                'driver_class' => 'sometimes|string',
                'credentials' => 'sometimes|array',
                'quota_config' => 'sometimes|array',
                'config' => 'sometimes|array',
                'status' => 'sometimes|string|in:' . implode(',', array_keys(CodingAccount::getStatuses())),
                'expires_at' => 'nullable|date',
            ]);

            // 如果更新驱动类，验证是否有效
            if (isset($validated['driver_class']) && !$this->driverManager->hasDriver($validated['driver_class'])) {
                return response()->json([
                    'error' => [
                        'message' => '无效的驱动类型: ' . $validated['driver_class'],
                        'code' => 'INVALID_DRIVER',
                    ],
                ], 422);
            }

            $account->update($validated);

            return response()->json([
                'data' => $account,
                'message' => 'Coding账户更新成功',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'error' => [
                    'message' => '验证失败',
                    'errors' => $e->errors(),
                ],
            ], 422);
        }
    }

    /**
     * 删除账户
     */
    public function destroy(int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        // 检查是否有关联的渠道
        if ($account->channels()->count() > 0) {
            return response()->json([
                'error' => [
                    'message' => '该账户有关联的渠道，无法删除',
                    'code' => 'HAS_CHANNELS',
                ],
            ], 422);
        }

        $account->delete();

        return response()->json([
            'message' => 'Coding账户删除成功',
        ]);
    }

    /**
     * 同步配额
     */
    public function sync(int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        try {
            $driver = $this->driverManager->driverForAccount($account);
            $driver->sync();

            // 刷新模型
            $account->refresh();

            return response()->json([
                'data' => $account,
                'message' => '配额同步成功',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'message' => '同步失败: ' . $e->getMessage(),
                    'code' => 'SYNC_FAILED',
                ],
            ], 500);
        }
    }

    /**
     * 验证凭证
     */
    public function validateCredentials(int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $driver = $this->driverManager->driverForAccount($account);
        $result = $driver->validateCredentials();

        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * 获取配额信息
     */
    public function quota(int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $driver = $this->driverManager->driverForAccount($account);
        $quotaInfo = $driver->getQuotaInfo();

        return response()->json([
            'account_id' => $account->id,
            'name' => $account->name,
            'platform' => $account->platform,
            'driver' => $account->driver_class,
            'status' => $account->status,
            'quota' => $quotaInfo,
            'last_sync_at' => $account->last_sync_at,
        ]);
    }

    /**
     * 获取使用记录
     */
    public function usage(Request $request, int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $query = $account->usageLogs();

        // 时间范围筛选
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * 获取状态变更日志
     */
    public function logs(Request $request, int $id): JsonResponse
    {
        $account = CodingAccount::find($id);

        if (!$account) {
            return response()->json([
                'error' => [
                    'message' => 'Coding账户不存在',
                    'code' => 'NOT_FOUND',
                ],
            ], 404);
        }

        $query = $account->statusLogs();

        // 时间范围筛选
        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->input('start_date'));
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->input('end_date'));
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * 获取可用驱动列表
     */
    public function drivers(): JsonResponse
    {
        $drivers = $this->driverManager->getAvailableDrivers();

        return response()->json([
            'data' => $drivers,
        ]);
    }

    /**
     * 获取平台列表
     */
    public function platforms(): JsonResponse
    {
        return response()->json([
            'data' => CodingAccount::getPlatforms(),
        ]);
    }

    /**
     * 获取状态列表
     */
    public function statuses(): JsonResponse
    {
        return response()->json([
            'data' => CodingAccount::getStatuses(),
        ]);
    }
}
