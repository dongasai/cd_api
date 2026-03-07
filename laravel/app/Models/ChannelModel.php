<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelModel extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'channel_models';

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // 如果设置为默认模型，取消该渠道其他模型的默认状态
            if ($model->is_default) {
                static::where('channel_id', $model->channel_id)
                    ->where('id', '!=', $model->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'channel_id',
        'model_name',
        'display_name',
        'mapped_model',
        'is_default',
        'is_enabled',
        'rpm_limit',
        'context_length',
        'multiplier',
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
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
            'multiplier' => 'decimal:4',
            'config' => 'array',
        ];
    }

    /**
     * 所属渠道
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'channel_id');
    }

    /**
     * 获取显示名称
     */
    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }

    /**
     * 获取实际调用的模型名称
     */
    public function getMappedModel(): string
    {
        return $this->mapped_model ?? $this->model_name;
    }

    /**
     * 作用域：只查询启用的模型
     */
    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    /**
     * 作用域：只查询默认模型
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
