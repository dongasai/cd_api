<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModelList extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     *
     * 监听 saving 事件，自动同步别名到关联模型，保证对称性
     */
    protected static function booted(): void
    {
        static::saving(function (self $model) {
            // 如果 aliases 字段发生变化，同步到关联模型
            if ($model->isDirty('aliases')) {
                $oldAliases = $model->getOriginal('aliases') ?? [];
                $newAliases = $model->aliases ?? [];

                // 获取当前模型名称
                $currentModelName = $model->model_name;

                // 计算新增和移除的别名
                $addedAliases = array_diff($newAliases, $oldAliases);
                $removedAliases = array_diff($oldAliases, $newAliases);

                // 同步新增的别名：将当前模型名添加到关联模型的 aliases
                foreach ($addedAliases as $aliasName) {
                    // 跳过自引用（别名不能是自己的模型名）
                    if ($aliasName === $currentModelName) {
                        continue;
                    }

                    // 查找别名对应的模型
                    $aliasModel = static::where('model_name', $aliasName)->first();
                    if ($aliasModel) {
                        $aliasModelAliases = $aliasModel->aliases ?? [];

                        // 添加当前模型名到该模型的 aliases（如果不存在）
                        if (! in_array($currentModelName, $aliasModelAliases)) {
                            $aliasModelAliases[] = $currentModelName;
                            $aliasModel->aliases = array_unique($aliasModelAliases);

                            // 避免事件循环，直接更新数据库
                            $aliasModel->timestamps = false;
                            $aliasModel->saveQuietly();
                        }
                    }
                }

                // 同步移除的别名：从关联模型的 aliases 中移除当前模型名
                foreach ($removedAliases as $aliasName) {
                    // 查找别名对应的模型
                    $aliasModel = static::where('model_name', $aliasName)->first();
                    if ($aliasModel) {
                        $aliasModelAliases = $aliasModel->aliases ?? [];

                        // 移除当前模型名
                        $aliasModelAliases = array_filter($aliasModelAliases, function ($name) use ($currentModelName) {
                            return $name !== $currentModelName;
                        });

                        $aliasModel->aliases = array_values(array_unique($aliasModelAliases));

                        // 阿静事件循环，直接更新数据库
                        $aliasModel->timestamps = false;
                        $aliasModel->saveQuietly();
                    }
                }
            }
        });
    }

    protected $fillable = [
        'model_name',
        'display_name',
        'provider',
        'hugging_face_id',
        'common_name',
        'aliases',
        'description',
        'capabilities',
        'context_length',
        'pricing_prompt',
        'pricing_completion',
        'pricing_input_cache_read',
        'is_enabled',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => 'array',
            'config' => 'array',
            'aliases' => 'array',
            'is_enabled' => 'boolean',
            'context_length' => 'integer',
            'pricing_prompt' => 'decimal:6',
            'pricing_completion' => 'decimal:6',
            'pricing_input_cache_read' => 'decimal:6',
        ];
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled === true;
    }

    public function getDisplayName(): string
    {
        return $this->display_name ?? $this->model_name;
    }

    /**
     * 获取模型别名列表
     *
     * @return array 别名数组，如果没有别名则返回空数组
     */
    public function getAliases(): array
    {
        return $this->aliases ?? [];
    }

    /**
     * 获取模型名称和所有别名的合集
     *
     * 用于模型匹配和路由降级，包含模型自身名称和所有别名
     *
     * @return array 名称数组，格式: ['glm-5', 'GLM-5', 'z-ai/glm-5', ...]
     */
    public function getAllNames(): array
    {
        $names = [$this->model_name];
        $aliases = $this->getAliases();

        return array_unique(array_merge($names, $aliases));
    }
}
