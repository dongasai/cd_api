<?php

namespace App\Models;

use App\Enums\OperationSource;
use App\Enums\OperationTarget;
use App\Enums\OperationType;
use Illuminate\Database\Eloquent\Model;

/**
 * 操作日志模型
 */
class OperationLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'target',
        'target_id',
        'target_name',
        'source',
        'user_id',
        'username',
        'description',
        'reason',
        'before_data',
        'after_data',
        'ip',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => OperationType::class,
            'target' => OperationTarget::class,
            'source' => OperationSource::class,
            'before_data' => 'array',
            'after_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取类型标签
     */
    public function getTypeLabel(): string
    {
        return $this->type?->label() ?? $this->type;
    }

    /**
     * 获取来源标签
     */
    public function getSourceLabel(): string
    {
        return $this->source?->label() ?? $this->source;
    }

    /**
     * 获取对象标签
     */
    public function getTargetLabel(): string
    {
        return $this->target?->label() ?? $this->target;
    }
}
