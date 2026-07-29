<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 渠道标签模型
 *
 * 用于对渠道进行分类和标记，支持颜色标识和描述。
 * 通过中间表 channel_tag_pivot 与 Channel 建立多对多关系。
 *
 * 数据表结构 (channel_tags):
 * ┌──────────────────┬───────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 类型          │ 说明                                     │
 * ├──────────────────┼───────────────┼─────────────────────────────────────────┤
 * │ id               │ bigint unsigned│ 主键，自增                               │
 * │ name             │ varchar(100)  │ 标签名称（唯一）                          │
 * │ color            │ varchar(7)    │ 标签颜色（默认 #666666）                  │
 * │ description      │ varchar(255)  │ 标签描述                                  │
 * │ created_at       │ timestamp     │ 创建时间                                  │
 * │ updated_at       │ timestamp     │ 更新时间                                  │
 * │ deleted_at       │ timestamp     │ 软删除时间                                │
 * └──────────────────┴───────────────┴─────────────────────────────────────────┘
 *
 * 中间表 (channel_tag_pivot):
 * ┌──────────────────┬───────────────┬─────────────────────────────────────────┐
 * │ 字段名            │ 类型          │ 说明                                     │
 * ├──────────────────┼───────────────┼─────────────────────────────────────────┤
 * │ channel_id       │ bigint unsigned│ 渠道 ID                                  │
 * │ tag_id           │ bigint unsigned│ 标签 ID                                  │
 * │ created_at       │ timestamp     │ 创建时间                                  │
 * │ updated_at       │ timestamp     │ 更新时间                                  │
 * │ deleted_at       │ timestamp     │ 软删除时间                                │
 * └──────────────────┴───────────────┴─────────────────────────────────────────┘
 * 复合主键：(channel_id, tag_id)
 *
 * 索引：
 * - PRIMARY: (channel_id, tag_id) - 中间表复合主键
 * - UNIQUE: (name) - 标签名称唯一约束
 * - INDEX: (name) - 标签名称索引
 *
 * 迁移历史：
 * - 2026_03_07_083342: 初始创建表（含中间表 channel_tag_pivot）
 *
 * @property int $id 主键
 * @property string $name 标签名称（唯一）
 * @property string $color 标签颜色（默认 #666666）
 * @property string|null $description 标签描述
 * @property Carbon|null $created_at 创建时间
 * @property Carbon|null $updated_at 更新时间
 * @property Carbon|null $deleted_at 软删除时间
 *
 * @see Channel 渠道模型
 */
class ChannelTag extends Model
{
    use HasFactory;

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'color',
        'description',
    ];

    /**
     * 拥有此标签的渠道（多对多）
     *
     * 通过 channel_tag_pivot 中间表关联
     *
     * @see Channel
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_tag_pivot', 'tag_id', 'channel_id');
    }
}
