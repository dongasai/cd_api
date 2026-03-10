<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelMultiplier extends Model
{
    use HasFactory;

    /**
     * 分类常量
     */
    public const CATEGORY_BASIC = 'basic';

    public const CATEGORY_STANDARD = 'standard';

    public const CATEGORY_ADVANCED = 'advanced';

    public const CATEGORY_REASONING = 'reasoning';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'platform',
        'model_pattern',
        'multiplier',
        'category',
        'description',
        'is_active',
        'priority',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * 获取分类列表
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_BASIC => '基础模型',
            self::CATEGORY_STANDARD => '标准模型',
            self::CATEGORY_ADVANCED => '高级模型',
            self::CATEGORY_REASONING => '推理模型',
        ];
    }

    /**
     * 获取分类颜色
     */
    public function getCategoryColor(): string
    {
        return match ($this->category) {
            self::CATEGORY_BASIC => 'gray',
            self::CATEGORY_STANDARD => 'primary',
            self::CATEGORY_ADVANCED => 'warning',
            self::CATEGORY_REASONING => 'danger',
            default => 'gray',
        };
    }

    /**
     * 检查模型是否匹配模式
     */
    public function matchesModel(string $model): bool
    {
        $pattern = $this->model_pattern;

        // 将通配符转换为正则表达式
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace('\*', '.*', $pattern);
        $pattern = '#^'.$pattern.'$#';

        return preg_match($pattern, $model) === 1;
    }

    /**
     * 根据模型名称查找倍数
     */
    public static function findMultiplier(?string $platform, string $model): float
    {
        $query = self::where('is_active', true)
            ->orderByDesc('priority')
            ->orderBy('id');

        if ($platform !== null) {
            $query->where(function ($q) use ($platform) {
                $q->whereNull('platform')
                    ->orWhere('platform', $platform);
            });
        }

        $multipliers = $query->get();

        foreach ($multipliers as $multiplier) {
            if ($multiplier->matchesModel($model)) {
                return (float) $multiplier->multiplier;
            }
        }

        return 1.0;
    }

    /**
     * 范围: 激活的
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * 范围: 按平台
     */
    public function scopeForPlatform($query, ?string $platform)
    {
        if ($platform === null) {
            return $query->whereNull('platform');
        }

        return $query->where(function ($q) use ($platform) {
            $q->whereNull('platform')
                ->orWhere('platform', $platform);
        });
    }
}
