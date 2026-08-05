<?php

namespace App\Models;

use App\Services\Search\Contracts\SearchDriverContract;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 搜索记录模型
 *
 * 记录搜索服务的查询记录，包括搜索内容、驱动信息、结果统计和性能指标
 *
 * ## 字段说明
 *
 * | 字段名            | 类型           | 可空   | 默认值        | 说明                   |
 * |-------------------|----------------|--------|---------------|------------------------|
 * | id                | bigint unsigned| 否     | auto_increment| 主键ID                |
 * | query             | varchar(500)   | 否     | -             | 搜索查询内容           |
 * | driver            | varchar(50)    | 否     | -             | 使用的驱动名称         |
 * | driver_id         | bigint unsigned| 是     | null          | 关联的SearchDriver ID  |
 * | result_count      | int unsigned   | 否     | 0             | 返回结果数量           |
 * | total_count       | int unsigned   | 否     | 0             | 总匹配数量             |
 * | success           | tinyint(1)     | 否     | 1             | 是否成功               |
 * | error_message     | text           | 是     | null          | 错误信息               |
 * | response_time_ms  | int unsigned   | 是     | null          | 响应时间(毫秒)         |
 * | filters           | json           | 是     | null          | 过滤条件               |
 * | results           | json           | 是     | null          | 搜索结果摘要(前3条)    |
 * | client_ip         | varchar(45)    | 是     | null          | 客户端IP               |
 * | api_key_id        | varchar(255)   | 是     | null          | API Key ID             |
 * | searched_at       | timestamp      | 否     | CURRENT_TIMESTAMP | 搜索时间            |
 *
 * ## 索引说明
 *
 * | 索引名                    | 字段       | 类型   | 说明                  |
 * |---------------------------|------------|--------|-----------------------|
 * | PRIMARY                   | id         | BTREE  | 主键                  |
 * | search_logs_driver_index  | driver     | BTREE  | 驱动名称查询          |
 * | search_logs_driver_id_index| driver_id | BTREE  | 驱动ID关联查询        |
 * | search_logs_query_index   | query      | BTREE  | 搜索内容查询          |
 * | search_logs_searched_at_index | searched_at | BTREE | 时间范围查询      |
 * | search_logs_success_index | success    | BTREE  | 成功状态筛选          |
 *
 * ## 迁移历史
 *
 * - 2026_04_06_153501: 创建 search_logs 表
 *
 * ## 核心功能
 *
 * 1. 搜索记录存储：保存每次搜索的完整上下文
 * 2. 性能监控：记录响应时间和结果数量
 * 3. 错误追踪：记录失败原因和错误信息
 * 4. 统计分析：按驱动、时间段统计搜索指标
 *
 * @property int $id 主键ID
 * @property string $query 搜索查询内容
 * @property string $driver 使用的驱动名称
 * @property int|null $driver_id 关联的SearchDriver ID
 * @property int $result_count 返回结果数量
 * @property int $total_count 总匹配数量
 * @property bool $success 是否成功
 * @property string|null $error_message 错误信息
 * @property int|null $response_time_ms 响应时间(毫秒)
 * @property array|null $filters 过滤条件
 * @property array|null $results 搜索结果摘要
 * @property string|null $client_ip 客户端IP
 * @property string|null $api_key_id API Key ID
 * @property Carbon $searched_at 搜索时间
 * @property-read SearchDriver|null $searchDriver 关联的搜索驱动
 *
 * @see SearchDriver 搜索驱动模型
 * @see SearchDriverContract 搜索驱动契约
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
     * 关联搜索驱动
     *
     * 多对一关联：一条搜索记录属于一个搜索驱动
     *
     * @see SearchDriver
     */
    public function searchDriver(): BelongsTo
    {
        return $this->belongsTo(SearchDriver::class, 'driver_id');
    }

    /**
     * 记录成功的搜索
     *
     * 创建一条成功的搜索记录，包含完整的搜索上下文和结果统计
     *
     * @param  string  $query  搜索查询内容
     * @param  string  $driver  使用的驱动名称
     * @param  int|null  $driverId  关联的SearchDriver ID
     * @param  int  $resultCount  返回结果数量
     * @param  int  $totalCount  总匹配数量
     * @param  int  $responseTimeMs  响应时间(毫秒)
     * @param  array|null  $filters  过滤条件
     * @param  array|null  $results  搜索结果摘要
     * @param  string|null  $clientIp  客户端IP
     * @param  string|null  $apiKeyId  API Key ID
     * @return self 新创建的搜索记录实例
     *
     * @see self::recordFailure() 记录失败的搜索
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
        ?string $apiKeyId = null
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
            'searched_at' => now(),
        ]);
    }

    /**
     * 记录失败的搜索
     *
     * 创建一条失败的搜索记录，包含错误信息和请求上下文
     *
     * @param  string  $query  搜索查询内容
     * @param  string  $driver  使用的驱动名称
     * @param  int|null  $driverId  关联的SearchDriver ID
     * @param  string  $errorMessage  错误信息
     * @param  int  $responseTimeMs  响应时间(毫秒)
     * @param  array|null  $filters  过滤条件
     * @param  string|null  $clientIp  客户端IP
     * @param  string|null  $apiKeyId  API Key ID
     * @return self 新创建的搜索记录实例
     *
     * @see self::recordSuccess() 记录成功的搜索
     */
    public static function recordFailure(
        string $query,
        string $driver,
        ?int $driverId,
        string $errorMessage,
        int $responseTimeMs,
        ?array $filters = null,
        ?string $clientIp = null,
        ?string $apiKeyId = null
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
            'searched_at' => now(),
        ]);
    }

    /**
     * 按驱动统计搜索指标
     *
     * 统计指定驱动在指定天数内的搜索数据，包括总量、成功数、失败数和平均响应时间
     *
     * @param  string  $driver  驱动名称
     * @param  int  $days  统计天数（默认7天）
     * @return array 统计数据包含：total, success, failed, avg_response_time
     *
     * @see SearchDriver 搜索驱动模型
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
