<?php

namespace App\Models;

use App\Services\ChannelError\ChannelErrorHandlingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道错误处理规则模型
 *
 * 定义错误匹配规则和处理动作，支持基于 HTTP 状态码、错误类型、错误消息的多维度匹配。
 * 规则支持三个层级：账户级、驱动级、全局级，按优先级从高到低匹配。
 *
 * 数据表结构 (channel_error_rules):
 * ┌──────────────────────────┬──────────────────────┬─────────────────────────────────────────┐
 * │ 字段名                    │ 类型                  │ 说明                                     │
 * ├──────────────────────────┼──────────────────────┼─────────────────────────────────────────┤
 * │ id                       │ bigint unsigned       │ 主键，自增                                │
 * │ name                     │ varchar(255)          │ 规则名称                                  │
 * │ coding_account_id        │ bigint unsigned       │ 账户 ID（账户级规则）                      │
 * │ driver_class             │ varchar(255)          │ 驱动类名（驱动级规则）                     │
 * │ pattern_type             │ enum                  │ 匹配类型：status_code/error_message/      │
 * │                          │                       │           error_type/both                │
 * │ pattern_value            │ varchar(255)          │ 匹配值                                    │
 * │ pattern_operator         │ enum                  │ 匹配方式：exact/contains/regex            │
 * │ action                   │ enum                  │ 处理动作：pause_account/alert_only        │
 * │ pause_duration_minutes   │ int unsigned          │ 暂停时长（分钟）                           │
 * │ priority                 │ int unsigned          │ 优先级（越大越优先）                       │
 * │ is_enabled               │ tinyint(1)            │ 是否启用                                  │
 * │ metadata                 │ json                  │ 扩展配置                                  │
 * │ created_at               │ timestamp             │ 创建时间                                  │
 * │ updated_at               │ timestamp             │ 更新时间                                  │
 * └──────────────────────────┴──────────────────────┴─────────────────────────────────────────┘
 *
 * 索引：
 * - INDEX (coding_account_id, is_enabled) - 账户级规则查询
 * - INDEX (driver_class, is_enabled) - 驱动级规则查询
 * - INDEX (priority) - 优先级排序
 *
 * 迁移历史：
 * - 2026_04_06_140902: 初始创建表
 *
 * 核心功能：
 * 1. 多维度匹配：支持状态码、错误类型、错误消息、组合匹配
 * 2. 多种匹配方式：精确匹配、包含匹配、正则匹配
 * 3. 分层规则：账户级 > 驱动级 > 全局级
 * 4. 优先级控制：数值越大优先级越高
 * 5. 处理动作：暂停账户或仅告警
 *
 * 规则层级说明：
 * - 账户级规则：coding_account_id 不为空，仅对该账户生效
 * - 驱动级规则：driver_class 不为空，coding_account_id 为空，对该驱动所有账户生效
 * - 全局规则：coding_account_id 和 driver_class 均为空，对所有账户生效
 *
 * @property int $id 主键
 * @property string $name 规则名称
 * @property int|null $coding_account_id 账户 ID（账户级规则）
 * @property string|null $driver_class 驱动类名（驱动级规则）
 * @property string $pattern_type 匹配类型：status_code/error_message/error_type/both
 * @property string $pattern_value 匹配值
 * @property string $pattern_operator 匹配方式：exact/contains/regex
 * @property string $action 处理动作：pause_account/alert_only
 * @property int $pause_duration_minutes 暂停时长（分钟）
 * @property int $priority 优先级
 * @property bool $is_enabled 是否启用
 * @property array|null $metadata 扩展配置
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read CodingAccount|null $codingAccount 关联的 Coding 账户
 *
 * @see ChannelErrorHandlingLog 错误处理日志模型
 * @see ChannelErrorHandlingService 错误处理服务
 */
class ChannelErrorRule extends Model
{
    /**
     * 匹配类型常量：HTTP 状态码
     */
    public const PATTERN_TYPE_STATUS_CODE = 'status_code';

    /**
     * 匹配类型常量：错误消息
     */
    public const PATTERN_TYPE_ERROR_MESSAGE = 'error_message';

    /**
     * 匹配类型常量：错误类型
     */
    public const PATTERN_TYPE_ERROR_TYPE = 'error_type';

    /**
     * 匹配类型常量：状态码或错误消息（组合匹配）
     */
    public const PATTERN_TYPE_BOTH = 'both';

    /**
     * 匹配方式常量：精确匹配
     */
    public const OPERATOR_EXACT = 'exact';

    /**
     * 匹配方式常量：包含匹配
     */
    public const OPERATOR_CONTAINS = 'contains';

    /**
     * 匹配方式常量：正则匹配
     */
    public const OPERATOR_REGEX = 'regex';

    /**
     * 处理动作常量：暂停账户
     */
    public const ACTION_PAUSE_ACCOUNT = 'pause_account';

    /**
     * 处理动作常量：仅告警
     */
    public const ACTION_ALERT_ONLY = 'alert_only';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'coding_account_id',
        'driver_class',
        'pattern_type',
        'pattern_value',
        'pattern_operator',
        'action',
        'pause_duration_minutes',
        'priority',
        'is_enabled',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * 关联的 Coding 账户
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class);
    }

    /**
     * 匹配错误（核心方法）
     *
     * 根据 pattern_type 选择匹配策略：
     * - status_code: 仅匹配 HTTP 状态码
     * - error_message: 仅匹配错误消息
     * - error_type: 仅匹配错误类型
     * - both: 状态码、错误类型、错误消息任一匹配即可
     *
     * @param  int  $statusCode  HTTP 状态码
     * @param  string  $errorType  错误类型
     * @param  string  $errorMessage  错误消息
     * @return bool 是否匹配
     *
     * @see ChannelErrorHandlingService::findMatchingRule() 使用此方法匹配规则
     */
    public function matchesError(int $statusCode, string $errorType, string $errorMessage): bool
    {
        return match ($this->pattern_type) {
            self::PATTERN_TYPE_STATUS_CODE => $this->matchStatusCode($statusCode),
            self::PATTERN_TYPE_ERROR_MESSAGE => $this->matchValue($errorMessage),
            self::PATTERN_TYPE_ERROR_TYPE => $this->matchValue($errorType),
            self::PATTERN_TYPE_BOTH => $this->matchStatusCode($statusCode) || $this->matchValue($errorType) || $this->matchValue($errorMessage),
            default => false,
        };
    }

    /**
     * 匹配状态码
     *
     * 根据 pattern_operator 选择匹配方式：
     * - exact: 精确匹配数值
     * - contains: 字符串包含匹配
     * - regex: 正则匹配
     *
     * @param  int  $statusCode  HTTP 状态码
     */
    protected function matchStatusCode(int $statusCode): bool
    {
        $patternValue = (int) $this->pattern_value;

        return match ($this->pattern_operator) {
            self::OPERATOR_EXACT => $statusCode === $patternValue,
            self::OPERATOR_CONTAINS => str_contains((string) $statusCode, $this->pattern_value),
            self::OPERATOR_REGEX => (bool) preg_match($this->pattern_value, (string) $statusCode),
            default => false,
        };
    }

    /**
     * 匹配字符串值（错误类型或错误消息）
     *
     * @param  string  $value  待匹配的值
     */
    protected function matchValue(string $value): bool
    {
        return match ($this->pattern_operator) {
            self::OPERATOR_EXACT => $value === $this->pattern_value,
            self::OPERATOR_CONTAINS => str_contains($value, $this->pattern_value),
            self::OPERATOR_REGEX => (bool) preg_match($this->pattern_value, $value),
            default => false,
        };
    }

    /**
     * 获取活跃规则（核心方法）
     *
     * 查询逻辑（按优先级从高到低）：
     * 1. 账户级规则：coding_account_id = 指定账户ID
     * 2. 驱动级规则：driver_class = 指定驱动类名，且 coding_account_id 为空
     * 3. 全局规则：coding_account_id 和 driver_class 均为空
     *
     * @param  CodingAccount|null  $account  账户实例
     * @param  string|null  $driverClass  驱动类名
     * @return Collection<int, self>
     *
     * @see ChannelErrorHandlingService::findMatchingRule() 使用此方法获取规则列表
     */
    public static function getActiveRules(?CodingAccount $account, ?string $driverClass)
    {
        return static::enabled()
            ->where(function ($query) use ($account, $driverClass) {
                $query->where(function ($q) use ($account) {
                    // 账户级规则
                    if ($account) {
                        $q->where('coding_account_id', $account->id);
                    }
                })->orWhere(function ($q) use ($driverClass) {
                    // 驱动级规则
                    if ($driverClass) {
                        $q->where('driver_class', $driverClass)
                            ->whereNull('coding_account_id');
                    }
                })->orWhere(function ($q) {
                    // 全局规则
                    $q->whereNull('coding_account_id')
                        ->whereNull('driver_class');
                });
            })
            ->orderByDesc('priority')
            ->get();
    }

    /**
     * 启用规则作用域
     *
     * @return Builder
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 账户规则作用域
     *
     * @param  int  $accountId  账户 ID
     * @return Builder
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('coding_account_id', $accountId);
    }

    /**
     * 驱动规则作用域
     *
     * @param  string  $driverClass  驱动类名
     * @return Builder
     */
    public function scopeForDriver($query, string $driverClass)
    {
        return $query->where('driver_class', $driverClass);
    }

    /**
     * 获取匹配类型选项
     *
     * @return array<string, string>
     */
    public static function getPatternTypeOptions(): array
    {
        return [
            self::PATTERN_TYPE_STATUS_CODE => 'HTTP状态码',
            self::PATTERN_TYPE_ERROR_MESSAGE => '错误消息',
            self::PATTERN_TYPE_ERROR_TYPE => '错误类型',
            self::PATTERN_TYPE_BOTH => '状态码或错误消息',
        ];
    }

    /**
     * 获取匹配方式选项
     *
     * @return array<string, string>
     */
    public static function getOperatorOptions(): array
    {
        return [
            self::OPERATOR_EXACT => '精确匹配',
            self::OPERATOR_CONTAINS => '包含匹配',
            self::OPERATOR_REGEX => '正则匹配',
        ];
    }

    /**
     * 获取处理动作选项
     *
     * @return array<string, string>
     */
    public static function getActionOptions(): array
    {
        return [
            self::ACTION_PAUSE_ACCOUNT => '暂停账户',
            self::ACTION_ALERT_ONLY => '仅告警',
        ];
    }
}
