<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 搜索记录模型
 *
 * @property int $id
 * @property string $query 搜索查询内容
 * @property string $driver 使用的驱动名称
 * @property int|null $driver_id 驱动ID
 * @property int $result_count 返回结果数量
 * @property int $total_count 总匹配数量
 * @property bool $success 是否成功
 * @property string|null $error_message 错误信息
 * @property int|null $response_time_ms 响应时间(毫秒)
 * @property array|null $filters 过滤条件
 * @property array|null $results 搜索结果摘要
 * @property string|null $client_ip 客户端IP
 * @property string|null $api_key_id API Key ID
 * @property string|null $mcp_client_id MCP客户端ID
 * @property \Carbon\Carbon $searched_at 搜索时间
 */
class SearchLog extends Model
{
    use HasFactory;

    /**
     * 表名
     */
    protected $table = 'search_logs';

    /**
     * 不使用时间戳
     */
    public $timestamps = false;

    /**
     * 可填充字段
     */
    protected $fillable = [
        'query',
        'driver',
        'driver_id',
        'result_count',
        'total_count',
        'success',
        'error_message',
        'response_time_ms',
        'filters',
        'results',
        'client_ip',
        'api_key_id',
        'mcp_client_id',
        'searched_at',
    ];

    /**
     * 字段类型转换
     */
    protected function casts(): array
    {
        return [
            'driver_id' => 'integer',
            'result_count' => 'integer',
            'total_count' => 'integer',
            'success' => 'boolean',
            'response_time_ms' => 'integer',
            'filters' => 'array',
            'results' => 'array',
            'searched_at' => 'datetime',
        ];
    }

    /**
     * 驱动关联
     */
    public function searchDriver(): BelongsTo
    {
        return $this->belongsTo(SearchDriver::class, 'driver_id');
    }

    /**
     * 记录成功搜索
     */
    public static function recordSuccess(
        string $query,
        string $driver,
        ?int $driverId,
        int $resultCount,
        int $totalCount,
        int $responseTimeMs,
        ?array $filters = null,
        ?array $results = null,
        ?string $clientIp = null,
        ?string $apiKeyId = null,
        ?string $mcpClientId = null
    ): self {
        return static::create([
            'query' => $query,
            'driver' => $driver,
            'driver_id' => $driverId,
            'result_count' => $resultCount,
            'total_count' => $totalCount,
            'success' => true,
            'response_time_ms' => $responseTimeMs,
            'filters' => $filters,
            'results' => $results,
            'client_ip' => $clientIp,
            'api_key_id' => $apiKeyId,
            'mcp_client_id' => $mcpClientId,
            'searched_at' => now(),
        ]);
    }

    /**
     * 记录失败搜索
     */
    public static function recordFailure(
        string $query,
        string $driver,
        ?int $driverId,
        string $errorMessage,
        int $responseTimeMs,
        ?array $filters = null,
        ?string $clientIp = null,
        ?string $apiKeyId = null,
        ?string $mcpClientId = null
    ): self {
        return static::create([
            'query' => $query,
            'driver' => $driver,
            'driver_id' => $driverId,
            'result_count' => 0,
            'total_count' => 0,
            'success' => false,
            'error_message' => $errorMessage,
            'response_time_ms' => $responseTimeMs,
            'filters' => $filters,
            'client_ip' => $clientIp,
            'api_key_id' => $apiKeyId,
            'mcp_client_id' => $mcpClientId,
            'searched_at' => now(),
        ]);
    }

    /**
     * 按驱动统计
     */
    public static function statsByDriver(string $driver, int $days = 7): array
    {
        $startDate = now()->subDays($days);

        return [
            'total' => static::where('driver', $driver)
                ->where('searched_at', '>=', $startDate)
                ->count(),
            'success' => static::where('driver', $driver)
                ->where('searched_at', '>=', $startDate)
                ->where('success', true)
                ->count(),
            'failed' => static::where('driver', $driver)
                ->where('searched_at', '>=', $startDate)
                ->where('success', false)
                ->count(),
            'avg_response_time' => static::where('driver', $driver)
                ->where('searched_at', '>=', $startDate)
                ->where('success', true)
                ->avg('response_time_ms'),
        ];
    }
}