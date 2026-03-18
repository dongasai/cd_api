<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Log;

/**
 * User-Agent规则模型
 *
 * @property int $id
 * @property string $name 规则名称
 * @property array $patterns 正则表达式数组
 * @property string|null $description 描述
 * @property bool $is_enabled 是否启用
 * @property int $hit_count 命中次数
 * @property \Carbon\Carbon|null $last_hit_at 最后命中时间
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class UserAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'patterns',
        'description',
        'is_enabled',
        'hit_count',
        'last_hit_at',
    ];

    protected $attributes = [
        'is_enabled' => true,
        'hit_count' => 0,
        'patterns' => '[]',
    ];

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
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_user_agent', 'user_agent_id', 'channel_id')
            ->withTimestamps();
    }

    /**
     * 检查User-Agent是否匹配此规则（多条正则，任意一条命中即可）
     *
     * @param  string  $userAgent  请求的User-Agent字符串
     * @return bool true=匹配, false=不匹配
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
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
        $this->update(['last_hit_at' => now()]);
    }

    /**
     * 查询启用的规则
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 获取正则表达式数量
     */
    public function getPatternCount(): int
    {
        return count($this->patterns ?? []);
    }

    /**
     * 模型保存时验证正则表达式
     */
    protected static function boot()
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
