<?php

namespace App\Models;

use App\Services\Router\ChannelRouterService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 渠道分组模型
 *
 * 用于将多个渠道组织成逻辑分组，支持按组进行路由和管理。
 *
 * 数据表结构 (channel_groups):
 * ┌──────────────────┬─────────────┬─────────────────────────────────────┐
 * │ 字段名            │ 类型        │ 说明                                 │
 * ├──────────────────┼─────────────┼─────────────────────────────────────┤
 * │ id               │ bigint      │ 主键，自增                            │
 * │ name             │ varchar(255)│ 分组名称                              │
 * │ slug             │ varchar(100)│ 分组标识（唯一）                      │
 * │ description      │ text        │ 分组描述                              │
 * │ config           │ json        │ 分组配置                              │
 * │ created_at       │ timestamp   │ 创建时间                              │
 * │ updated_at       │ timestamp   │ 更新时间                              │
 * │ deleted_at       │ timestamp   │ 软删除时间                            │
 * └──────────────────┴─────────────┴─────────────────────────────────────┘
 *
 * 中间表 (channel_group_pivot):
 * ┌──────────────────┬─────────────┬─────────────────────────────────────┐
 * │ 字段名            │ 类型        │ 说明                                 │
 * ├──────────────────┼─────────────┼─────────────────────────────────────┤
 * │ channel_id       │ bigint      │ 渠道 ID                              │
 * │ group_id         │ bigint      │ 分组 ID                              │
 * │ priority         │ int         │ 组内优先级（默认1）                   │
 * │ created_at       │ timestamp   │ 创建时间                              │
 * │ updated_at       │ timestamp   │ 更新时间                              │
 * │ deleted_at       │ timestamp   │ 软删除时间                            │
 * └──────────────────┴─────────────┴─────────────────────────────────────┘
 * 复合主键：(channel_id, group_id)
 *
 * 索引：
 * - PRIMARY: (channel_id, group_id) - 复合主键
 * - UNIQUE: (slug) - 分组标识唯一约束
 *
 * 迁移历史：
 * - 2026_03_07_083341: 初始创建表（含中间表 channel_group_pivot）
 *
 * @property int $id 主键
 * @property string $name 分组名称
 * @property string $slug 分组标识（唯一）
 * @property string|null $description 分组描述
 * @property array|null $config 分组配置
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 * @property-read Collection|Channel[] $channels 分组中的渠道
 *
 * @see Channel 渠道模型
 * @see ChannelRouterService 渠道路由服务
 */
class ChannelGroup extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'config',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * 分组中的渠道（多对多）
     *
     * 通过 channel_group_pivot 中间表关联，支持组内优先级
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_group_pivot', 'group_id', 'channel_id')
            ->withPivot('priority')
            ->withTimestamps();
    }
}
