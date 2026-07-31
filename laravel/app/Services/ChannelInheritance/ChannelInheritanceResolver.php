<?php

namespace App\Services\ChannelInheritance;

use App\Enums\InheritMode;
use App\Models\Channel;
use App\Services\ChannelInheritance\Exceptions\InheritanceResolverException;

/**
 * 渠道继承解析器
 *
 * 负责解析渠道继承关系，构建继承链，合并父子渠道配置。
 * 支持两种继承模式：MERGE（合并）和 OVERRIDE（覆盖）。
 *
 * 核心功能：
 * 1. 构建继承链：从当前渠道向上遍历父渠道，检测循环和深度限制
 * 2. 解析最终配置：按继承规则合并所有父渠道配置
 * 3. 标量字段解析：base_url, api_key, provider（子空值继承父值）
 * 4. 数组字段解析：config, forward_headers（按模式合并）
 * 5. 模型列表解析：channel_models（按模式合并）
 *
 * @see InheritMode 继承模式枚举
 * @see InheritanceResolverException 继承异常
 * @see Channel::parent() 父渠道关系
 * @see Channel::children() 子渠道关系
 */
class ChannelInheritanceResolver
{
    /**
     * 最大继承深度（包含当前渠道）
     *
     * 例如：MAX_DEPTH=5 表示最多 5 级继承（根 + 4 层子渠道）
     */
    public const MAX_DEPTH = 5;

    /**
     * 可继承的标量字段列表
     *
     * 这些字段继承规则：子渠道值为空时继承父渠道值
     */
    public const SCALAR_FIELDS = ['base_url', 'api_key', 'provider'];

    /**
     * 可继承的数组字段列表
     *
     * 这些字段根据 inherit_mode 决定合并方式：
     * - MERGE: array_replace_recursive（深度合并，子覆盖父）
     * - OVERRIDE: 完全使用子渠道值
     */
    public const ARRAY_FIELDS = ['config', 'forward_headers'];

    /**
     * 构建继承链
     *
     * 从指定渠道开始，逐级向上遍历父渠道，构建从根到当前的完整继承链。
     * 返回数组格式：[root_channel, ..., parent_channel, self_channel]
     *
     * 检测机制：
     * - 循环检测：使用 visited 集合记录已访问渠道 ID
     * - 深度检测：链长度超过 MAX_DEPTH + 1 时抛出异常
     * - 软删除检测：父渠道被软删除时抛出异常
     *
     * @param  Channel  $channel  起始渠道
     * @return array<Channel> 继承链数组（从根到当前）
     *
     * @throws InheritanceResolverException 循环继承、深度超限、父渠道不存在或已删除
     */
    public function getInheritanceChain(Channel $channel): array
    {
        $chain = [];
        $visited = [];
        $current = $channel;
        $depth = 0;

        // 迭代遍历父渠道链（避免递归内存问题）
        while ($current !== null) {
            $id = $current->id;

            // 检测循环继承
            if (isset($visited[$id])) {
                $cycleChain = array_merge(array_keys($visited), [$id]);
                throw InheritanceResolverException::circularInheritance($cycleChain);
            }

            $visited[$id] = true;
            $depth++;

            // 检测深度限制（不包含当前渠道本身）
            if ($depth > self::MAX_DEPTH) {
                $chainIds = array_map(fn ($c) => $c->id, $chain);
                throw InheritanceResolverException::depthExceeded($depth, self::MAX_DEPTH, $chainIds);
            }

            // 将当前渠道插入链首（保持 root -> ... -> self 顺序）
            array_unshift($chain, $current);

            // 移动到父渠道（parent_id 为 null 或 0 表示无父渠道）
            if ($current->parent_id === null || $current->parent_id === 0) {
                break;
            }

            // 加载父渠道关系
            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $parent = $current->parent;

            // 父渠道不存在（可能已被硬删除）
            if ($parent === null) {
                throw InheritanceResolverException::parentNotFound($current->parent_id, $current->id);
            }

            // 父渠道已被软删除
            if ($parent->deleted_at !== null) {
                throw InheritanceResolverException::parentSoftDeleted($parent->id, $current->id);
            }

            $current = $parent;
        }

        return $chain;
    }

    /**
     * 解析渠道的最终配置
     *
     * 根据继承链和各级渠道的 inherit_mode，计算最终生效的配置。
     * 返回结构化数组，包含所有可继承字段的最终值。
     *
     * 解析逻辑：
     * - 从继承链的 root 开始逐级向下处理
     * - 每级根据该级的 inherit_mode 决定如何合并
     * - MERGE: 数组字段使用 array_replace_recursive 深度合并
     * - OVERRIDE: 数组字段完全使用当前级值（标量仍继承）
     *
     * @param  Channel  $channel  目标渠道
     * @return array 最终配置数组
     *
     * @throws InheritanceResolverException 继承解析错误
     */
    public function resolveConfig(Channel $channel): array
    {
        // 无父渠道，直接返回自身原始值（向后兼容）
        if ($channel->parent_id === null || $channel->parent_id === 0) {
            return $this->extractRawConfig($channel);
        }

        $chain = $this->getInheritanceChain($channel);

        // 初始化结果为 root 渠道的配置
        $result = $this->extractRawConfig($chain[0]);

        // 从第二级开始逐级合并
        $chainLength = count($chain);
        for ($i = 1; $i < $chainLength; $i++) {
            $currentChannel = $chain[$i];
            $mode = InheritMode::tryFrom($currentChannel->inherit_mode) ?? InheritMode::MERGE;

            // 获取当前渠道原始配置
            $currentConfig = $this->extractRawConfig($currentChannel);

            // 合并标量字段（子空值继承父值）
            foreach (self::SCALAR_FIELDS as $field) {
                if (empty($currentConfig[$field])) {
                    // 子值为空，继承父值（保持 result 中的值）
                    continue;
                }
                // 子值非空，使用子值
                $result[$field] = $currentConfig[$field];
            }

            // 合并数组字段（根据模式决定）
            foreach (self::ARRAY_FIELDS as $field) {
                if ($mode->isOverride()) {
                    // OVERRIDE: 完全使用子数组（不继承父值）
                    $result[$field] = $currentConfig[$field] ?? [];
                } else {
                    // MERGE: 深度合并
                    $parentValue = $result[$field] ?? [];
                    $childValue = $currentConfig[$field] ?? [];
                    // forward_headers 是索引数组，使用 array_merge 追加合并
                    if ($field === 'forward_headers') {
                        $result[$field] = array_values(array_unique(array_merge($parentValue, $childValue)));
                    } else {
                        $result[$field] = array_replace_recursive($parentValue, $childValue);
                    }
                }
            }

            // 合并模型列表（根据模式决定）
            if ($mode->isOverride()) {
                // OVERRIDE: 完全使用子渠道模型列表
                $result['channel_models'] = $currentConfig['channel_models'] ?? [];
            } else {
                // MERGE: 合并模型列表（去重）
                $parentModels = $result['channel_models'] ?? [];
                $childModels = $currentConfig['channel_models'] ?? [];
                $result['channel_models'] = $this->mergeModelLists($parentModels, $childModels);
            }
        }

        return $result;
    }

    /**
     * 解析单个标量字段
     *
     * 从当前渠道开始向上查找，返回第一个非空值。
     * 用于获取单个配置字段的最终值。
     *
     * @param  Channel  $channel  起始渠道
     * @param  string  $field  字段名（base_url, api_key, provider）
     * @return string|null 字段最终值，无继承值时返回 null
     *
     * @throws \InvalidArgumentException 字段名无效
     * @throws InheritanceResolverException 继承解析错误
     */
    public function resolveScalar(Channel $channel, string $field): ?string
    {
        if (! in_array($field, self::SCALAR_FIELDS)) {
            throw new \InvalidArgumentException("无效的标量字段: {$field}，允许的字段: ".implode(', ', self::SCALAR_FIELDS));
        }

        // 无父渠道，直接返回自身值
        if ($channel->parent_id === null || $channel->parent_id === 0) {
            return $channel->{$field};
        }

        $chain = $this->getInheritanceChain($channel);

        // 从当前渠道向 root 查找第一个非空值
        for ($i = count($chain) - 1; $i >= 0; $i--) {
            $value = $chain[$i]->{$field};
            if (! empty($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * 解析单个数组字段
     *
     * 根据继承规则合并数组字段，返回最终数组值。
     *
     * @param  Channel  $channel  起始渠道
     * @param  string  $field  字段名（config, forward_headers）
     * @return array 字段最终值
     *
     * @throws \InvalidArgumentException 字段名无效
     * @throws InheritanceResolverException 继承解析错误
     */
    public function resolveArray(Channel $channel, string $field): array
    {
        if (! in_array($field, self::ARRAY_FIELDS)) {
            throw new \InvalidArgumentException("无效的数组字段: {$field}，允许的字段: ".implode(', ', self::ARRAY_FIELDS));
        }

        // 无父渠道，直接返回自身值
        if ($channel->parent_id === null || $channel->parent_id === 0) {
            return $channel->{$field} ?? [];
        }

        $chain = $this->getInheritanceChain($channel);

        // 初始化结果为 root 渠道的值
        $result = $chain[0]->{$field} ?? [];

        // 从第二级开始逐级合并
        $chainLength = count($chain);
        for ($i = 1; $i < $chainLength; $i++) {
            $currentChannel = $chain[$i];
            $mode = InheritMode::tryFrom($currentChannel->inherit_mode) ?? InheritMode::MERGE;
            $currentValue = $currentChannel->{$field} ?? [];

            if ($mode->isOverride()) {
                // OVERRIDE: 完全使用子值
                $result = $currentValue;
            } else {
                // MERGE: 深度合并（forward_headers 使用 array_merge 追加合并）
                if ($field === 'forward_headers') {
                    $result = array_values(array_unique(array_merge($result, $currentValue)));
                } else {
                    $result = array_replace_recursive($result, $currentValue);
                }
            }
        }

        return $result;
    }

    /**
     * 解析渠道的模型列表
     *
     * 根据继承规则合并父子渠道的模型列表。
     * MERGE 模式下合并列表并去重，OVERRIDE 模式下仅使用子渠道列表。
     *
     * @param  Channel  $channel  起始渠道
     * @return array<array{model_name: string, display_name: string|null, is_enabled: bool, is_default: bool, mapped_model: string|null}> 模型列表
     *
     * @throws InheritanceResolverException 继承解析错误
     */
    public function resolveChannelModels(Channel $channel): array
    {
        // 无父渠道，直接返回自身模型列表
        if ($channel->parent_id === null || $channel->parent_id === 0) {
            return $this->extractChannelModels($channel);
        }

        $chain = $this->getInheritanceChain($channel);

        // 初始化结果为 root 渠道的模型列表
        $result = $this->extractChannelModels($chain[0]);

        // 从第二级开始逐级合并
        $chainLength = count($chain);
        for ($i = 1; $i < $chainLength; $i++) {
            $currentChannel = $chain[$i];
            $mode = InheritMode::tryFrom($currentChannel->inherit_mode) ?? InheritMode::MERGE;
            $currentModels = $this->extractChannelModels($currentChannel);

            if ($mode->isOverride()) {
                // OVERRIDE: 完全使用子渠道模型列表
                $result = $currentModels;
            } else {
                // MERGE: 合并模型列表
                $result = $this->mergeModelLists($result, $currentModels);
            }
        }

        return $result;
    }

    /**
     * 提取渠道的原始配置
     *
     * 从渠道模型中提取所有可继承字段的原始值。
     *
     * @param  Channel  $channel  渠道模型
     * @return array 原始配置数组
     */
    protected function extractRawConfig(Channel $channel): array
    {
        return [
            'base_url' => $channel->base_url,
            'api_key' => $channel->api_key,
            'provider' => $channel->provider,
            'config' => $channel->config ?? [],
            'forward_headers' => $channel->forward_headers ?? [],
            'channel_models' => $this->extractChannelModels($channel),
        ];
    }

    /**
     * 提取渠道的模型列表
     *
     * @param  Channel  $channel  渠道模型
     * @return array<array{model_name: string, display_name: string|null, is_enabled: bool, is_default: bool, mapped_model: string|null}> 模型列表
     */
    protected function extractChannelModels(Channel $channel): array
    {
        // 如果关系已加载，使用已加载的关系
        if ($channel->relationLoaded('channelModels')) {
            $models = $channel->channelModels;
        } else {
            // 未加载则查询（可能触发 N+1，调用方应预加载）
            $models = $channel->channelModels()->get();
        }

        return $models->map(fn ($model) => [
            'model_name' => $model->model_name,
            'display_name' => $model->display_name,
            'is_enabled' => $model->is_enabled,
            'is_default' => $model->is_default,
            'mapped_model' => $model->mapped_model,
        ])->all();
    }

    /**
     * 合并两个模型列表
     *
     * 合并父子渠道的模型列表，相同 model_name 的子渠道模型覆盖父渠道。
     *
     * @param  array  $parentModels  父渠道模型列表
     * @param  array  $childModels  子渠道模型列表
     * @return array 合并后的模型列表
     */
    protected function mergeModelLists(array $parentModels, array $childModels): array
    {
        // 以 model_name 为键建立索引
        $result = [];
        foreach ($parentModels as $model) {
            $result[$model['model_name']] = $model;
        }

        // 子渠道模型覆盖父渠道
        foreach ($childModels as $model) {
            $result[$model['model_name']] = $model;
        }

        return array_values($result);
    }

    /**
     * 批量解析多个渠道的配置
     *
     * 用于需要同时解析多个渠道的场景，可优化数据库查询。
     *
     * @param  array<Channel>  $channels  渠道列表
     * @return array<int, array> 渠道 ID => 配置数组 的映射
     */
    public function resolveConfigs(array $channels): array
    {
        $result = [];
        foreach ($channels as $channel) {
            $result[$channel->id] = $this->resolveConfig($channel);
        }

        return $result;
    }

    /**
     * 检查渠道是否有循环继承
     *
     * 用于表单验证等场景，不抛出异常，返回布尔值。
     *
     * @param  Channel  $channel  要检查的渠道
     * @return bool 是否存在循环继承
     */
    public function hasCircularInheritance(Channel $channel): bool
    {
        $visited = [];
        $current = $channel;

        while ($current !== null) {
            $id = $current->id;

            if (isset($visited[$id])) {
                return true;
            }

            $visited[$id] = true;

            if ($current->parent_id === null || $current->parent_id === 0) {
                break;
            }

            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $parent = $current->parent;
            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return false;
    }

    /**
     * 获取渠道的继承深度
     *
     * 返回渠道在继承树中的深度（从 0 开始计数）。
     * 根渠道（无父渠道）深度为 0。
     *
     * @param  Channel  $channel  渠道
     * @return int 继承深度
     */
    public function getInheritanceDepth(Channel $channel): int
    {
        $depth = 0;
        $current = $channel;
        $visited = [];

        while ($current !== null) {
            // 防止循环导致死循环
            if (isset($visited[$current->id])) {
                break;
            }
            $visited[$current->id] = true;

            // 无父渠道时停止
            if ($current->parent_id === null || $current->parent_id === 0) {
                break;
            }

            $depth++;

            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $parent = $current->parent;
            if ($parent === null) {
                break;
            }

            $current = $parent;
        }

        return $depth;
    }

    /**
     * 检查渠道是否超过最大继承深度
     *
     * @param  Channel  $channel  渠道
     * @return bool 是否超过最大深度
     */
    public function exceedsMaxDepth(Channel $channel): bool
    {
        return $this->getInheritanceDepth($channel) >= self::MAX_DEPTH;
    }
}
