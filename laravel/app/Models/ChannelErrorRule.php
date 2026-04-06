<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 渠道错误处理规则模型
 *
 * 定义错误匹配规则和处理动作
 */
class ChannelErrorRule extends Model
{
    /**
     * 匹配类型常量
     */
    public const PATTERN_TYPE_STATUS_CODE = 'status_code';

    public const PATTERN_TYPE_ERROR_MESSAGE = 'error_message';

    public const PATTERN_TYPE_ERROR_TYPE = 'error_type';

    public const PATTERN_TYPE_BOTH = 'both';

    /**
     * 匹配方式常量
     */
    public const OPERATOR_EXACT = 'exact';

    public const OPERATOR_CONTAINS = 'contains';

    public const OPERATOR_REGEX = 'regex';

    /**
     * 处理动作常量
     */
    public const ACTION_PAUSE_ACCOUNT = 'pause_account';

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
     * 关联的Coding账户
     */
    public function codingAccount(): BelongsTo
    {
        return $this->belongsTo(CodingAccount::class);
    }

    /**
     * 匹配错误
     *
     * @param  int  $statusCode  HTTP状态码
     * @param  string  $errorType  错误类型
     * @param  string  $errorMessage  错误消息
     * @return bool 是否匹配
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
     * 匹配值
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
     * 获取活跃规则
     *
     * @param  CodingAccount|null  $account  账户实例
     * @param  string|null  $driverClass  驱动类名
     * @return \Illuminate\Database\Eloquent\Collection<int, self>
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
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 账户规则作用域
     */
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('coding_account_id', $accountId);
    }

    /**
     * 驱动规则作用域
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
