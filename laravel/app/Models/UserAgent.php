<?php

namespace App\Models;

use App\Services\Router\ChannelRouterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;

/**
 * User-Agent 规则模型
 *
 * 用于管理渠道访问的 User-Agent 限制规则，支持正则表达式匹配。
 * 通过多对多关联与 Channel 模型建立关系，实现基于 User-Agent 的渠道访问控制。
 *
 * ┌─────────────────┬──────────────────┬──────────────────────────────────────┐
 * │ 字段名          │ 类型             │ 说明                                 │
 * ├─────────────────┼──────────────────┼──────────────────────────────────────┤
 * │ id              │ bigint unsigned  │ 主键，自增ID                         │
 * │ name            │ varchar(100)     │ 规则名称                             │
 * │ patterns        │ json             │ 正则表达式数组（JSON格式）           │
 * │ description     │ text, nullable   │ 规则描述                             │
 * │ is_enabled      │ tinyint(1)       │ 是否启用（默认true）                 │
 * │ hit_count       │ bigint unsigned  │ 命中次数（默认0）                    │
 * │ last_hit_at     │ timestamp, null  │ 最后命中时间                         │
 * │ created_at      │ timestamp, null  │ 创建时间                             │
 * │ updated_at      │ timestamp, null  │ 更新时间                             │
 * └─────────────────┴──────────────────┴──────────────────────────────────────┘
 *
 * 索引说明：
 * - PRIMARY KEY (id) - 主键索引
 * - idx_enabled (is_enabled) - 用于快速查询启用的规则
 *
 * 关联表：
 * - channel_user_agent - 中间表，关联 channels 和 user_agents
 *   - idx_channel_id (channel_id) - 渠道查询索引
 *   - idx_user_agent_id (user_agent_id) - User-Agent查询索引
 *   - PRIMARY KEY (channel_id, user_agent_id) - 复合主键
 *
 * 迁移历史：
 * - 2026_03_17_150000: 创建 user_agents 表和 channel_user_agent 中间表
 * - 2026_03_17_150001: 独立创建 channel_user_agent 中间表（支持多对多关系）
 *
 * 核心功能：
 * 1. 正则表达式匹配：支持多条正则，任意一条命中即匹配成功
 * 2. 命中统计：自动记录规则命中次数和最后命中时间
 * 3. 启用/禁用：可动态启用或禁用规则
 * 4. 渠道关联：支持多对多关联渠道，实现渠道级UA限制
 * 5. 正则验证：保存时自动验证正则表达式有效性
 * 6. 性能警告：检测可能存在性能风险的正则模式
 *
 * @property int $id 主键ID
 * @property string $name 规则名称
 * @property array $patterns 正则表达式数组
 * @property string|null $description 规则描述
 * @property bool $is_enabled 是否启用
 * @property int $hit_count 命中次数
 * @property Carbon|null $last_hit_at 最后命中时间
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property-read Collection $channels 关联的渠道列表
 *
 * @see Channel 渠道模型
 * @see ChannelRouterService 渠道路由服务
 */
class UserAgent extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的字段
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',
        'patterns',
        'description',
        'is_enabled',
        'hit_count',
        'last_hit_at',
    ];

    /**
     * 默认属性值
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'hit_count' => 0,
        'patterns' => '[]',
    ];

    /**
     * 字段类型转换
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'patterns' => 'array', // JSON数组
            'is_enabled' => 'boolean',
            'last_hit_at' => 'datetime',
        ];
    }

    /**
     * 关联的渠道列表
     *
     * 通过 channel_user_agent 中间表建立多对多关系
     *
     *
     * @see Channel
     * @see UserAgent
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_user_agent', 'user_agent_id', 'channel_id')
            ->withTimestamps();
    }

    /**
     * 检查 User-Agent 是否匹配此规则
     *
     * 多条正则表达式中，任意一条命中即返回 true。
     * 如果规则被禁用或没有配置正则表达式，返回 false。
     * 单个正则匹配失败时会记录错误日志并继续尝试下一个。
     *
     * @param  string  $userAgent  请求的 User-Agent 字符串
     * @return bool true=匹配成功, false=不匹配或规则禁用
     *
     * @see Log::error()
     */
    public function matches(string $userAgent): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        $patterns = $this->patterns ?? [];

        // 如果没有配置任何正则表达式，返回false
        if (empty($patterns)) {
            return false;
        }

        // 遍历所有正则表达式，任意一条匹配即返回true
        foreach ($patterns as $pattern) {
            try {
                if (@preg_match($pattern, $userAgent)) {
                    return true;
                }
            } catch (\Exception $e) {
                Log::error('User-Agent正则匹配失败', [
                    'pattern' => $pattern,
                    'user_agent' => $userAgent,
                    'error' => $e->getMessage(),
                ]);

                // 继续尝试下一个正则表达式
                continue;
            }
        }

        return false;
    }

    /**
     * 记录命中
     *
     * 递增命中次数并更新最后命中时间为当前时间
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * 查询启用的规则
     *
     * Scope 查询，仅返回 is_enabled = true 的记录
     *
     * @param  Builder  $query  查询构建器
     * @return Builder
     *
     * @see Builder::where()
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 获取正则表达式数量
     *
     * @return int 正则表达式条数
     */
    public function getPatternCount(): int
    {
        return count($this->patterns ?? []);
    }

    /**
     * 模型启动事件
     *
     * 注册 saving 事件回调，在保存前验证正则表达式有效性
     *
     *
     * @see Model::boot()
     * @see Log::warning()
     */
    protected static function boot(): void
    {
        parent::boot();

        static::saving(function ($model) {
            $patterns = $model->patterns ?? [];

            // 验证每个正则表达式有效性
            foreach ($patterns as $index => $pattern) {
                if (@preg_match($pattern, '') === false) {
                    throw new \InvalidArgumentException("第{$index}条正则表达式无效: {$pattern}");
                }

                // 检测危险模式（可选）
                if (preg_match('/[\*\+]{2,}/', $pattern)) {
                    Log::warning('User-Agent正则表达式可能存在性能风险', [
                        'pattern' => $pattern,
                        'index' => $index,
                    ]);
                }
            }
        });
    }
}
