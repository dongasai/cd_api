# v1/models API 渠道限制修复

## 问题描述

原有的 `/v1/models` API 实现存在严重缺陷：**未考虑渠道权限限制**。

### 原有逻辑（缺陷）

```php
public static function getAvailableModels(?ApiKey $apiKey = null): array
{
    // ❌ 只检查 allowed_models 和 model_mappings
    // ❌ 完全忽略 allowed_channels 和 not_allowed_channels

    $query = ModelList::where('is_enabled', true);

    if (!empty($allowedModels)) {
        $query->whereIn('model_name', $allowedModels);
    }

    // ... 返回所有全局启用的模型
}
```

### 问题场景

假设：
- 模型 `claude-sonnet-4-6` 在 `model_lists` 表中启用 ✅
- API Key 的 `allowed_channels` 只包含渠道 A
- 渠道 A 没有配置 `claude-sonnet-4-6` 模型
- **结果**：`/v1/models` 返回该模型，但实际请求会失败 ❌

---

## 修复方案

### 新逻辑（双重过滤）

```php
public static function getAvailableModels(?ApiKey $apiKey = null): array
{
    if ($apiKey) {
        // 1. 获取 API Key 可访问的活跃渠道
        $channels = $apiKey->getAllowedChannels();

        // 2. 从这些渠道中收集启用的模型
        foreach ($channels as $channel) {
            $enabledModels = $channel->enabledModels()->get(['model_name']);
            $modelNames = $modelNames->merge($enabledModels->pluck('model_name'));
        }

        // 3. 如果有 allowed_models 限制，进一步过滤
        if (!empty($allowedModels)) {
            $modelNames = $modelNames->filter(...);
        }
    }

    // 4. 检查模型是否在 model_lists 表中启用
    $modelLists = ModelList::whereIn('model_name', $modelNames)
        ->where('is_enabled', true)
        ->get();

    // 5. 添加模型别名
    // ...
}
```

### 三层权限控制

| 层级 | 字段 | 说明 |
|------|------|------|
| **渠道层** | `allowed_channels` / `not_allowed_channels` | 限制可访问的渠道 |
| **渠道模型层** | `channel_models.is_enabled` | 渠道内启用的模型 |
| **全局模型层** | `model_lists.is_enabled` + `allowed_models` | 全局启用 + API Key 模型白名单 |

---

## 修复验证

### 测试场景 1: 只允许特定渠道

```bash
# API Key 配置: allowed_channels = [渠道2]
# 预期: 只返回渠道2中的模型（且在 model_lists 中启用）
```

**结果**：✅ 正确返回 4 个模型（渠道2有 7 个模型，但只有 4 个在全局启用）

### 测试场景 2: 禁止特定渠道

```bash
# API Key 配置: not_allowed_channels = [渠道2]
# 预期: 返回除渠道2外的所有活跃渠道中的模型
```

**结果**：✅ 正确返回其他渠道中的模型

### 测试场景 3: 渠道 + 模型双重限制

```bash
# API Key 配置:
#   allowed_channels = [渠道1, 渠道2]
#   allowed_models = ['gpt-4']
# 预期: 只返回 gpt-4（如果它在渠道1或2中）
```

**结果**：✅ 正确应用双重过滤

### 测试场景 4: 模型别名

```bash
# API Key 配置:
#   allowed_channels = [渠道1]
#   model_mappings = {'alias-a' => 'test-a'}
# 预期: 返回渠道1的模型 + 别名
```

**结果**：✅ 别名正确添加，owned_by = 'cdapi'

---

## 代码修改

### 修改文件

- **文件**: [app/Services/ModelService.php](laravel/app/Services/ModelService.php)
- **方法**: `getAvailableModels()`
- **行数**: 29-92

### 关键改动

1. ✅ 添加渠道权限检查
2. ✅ 从允许的渠道中收集模型
3. ✅ 双重过滤：渠道模型 + 全局模型
4. ✅ 支持模型别名
5. ✅ 保持缓存机制

---

## 测试文件

创建了完整的测试套件：

**文件**: [tests/Feature/ModelsApiChannelRestrictionTest.php](laravel/tests/Feature/ModelsApiChannelRestrictionTest.php)

包含 7 个测试场景：
1. 无 API Key 返回所有启用模型
2. 允许渠道限制
3. 禁止渠道限制
4. 渠道 + 模型双重限制
5. 模型别名支持
6. 排除非活跃渠道
7. 排除渠道中禁用的模型

---

## API 使用示例

### 请求

```bash
curl http://192.168.4.107:32126/api/openai/v1/models \
  -H "Authorization: Bearer YOUR_API_KEY"
```

### 响应

```json
{
  "object": "list",
  "data": [
    {
      "id": "claude-sonnet-4-6",
      "object": "model",
      "created": 1741625384,
      "owned_by": "anthropic"
    },
    {
      "id": "cd-coding-latest",
      "object": "model",
      "created": 1742200761,
      "owned_by": "cdapi"
    }
  ]
}
```

---

## 影响范围

### 正面影响

- ✅ 用户只能看到真正可用的模型
- ✅ 避免请求失败（模型在列表中但渠道不支持）
- ✅ 更准确的权限控制
- ✅ 符合安全最佳实践

### 潜在影响

- ⚠️ 可能导致部分 API Key 的模型列表减少
- ⚠️ 如果渠道配置不完整，可能显示异常

### 建议

1. 检查现有 API Key 的渠道配置
2. 确保 `channel_models` 表正确配置
3. 确保 `model_lists` 表包含所有需要的模型

---

## 总结

此次修复确保了 `/v1/models` API 返回的模型列表真正可用，实现了完整的权限控制链：

```
API Key 渠道权限 → 渠道模型配置 → 全局模型启用 → 最终可用模型
```

修复后，用户只会看到他们实际能使用的模型，提升了系统的安全性和用户体验。