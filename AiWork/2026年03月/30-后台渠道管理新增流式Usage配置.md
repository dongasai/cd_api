# 后台渠道管理新增流式Usage配置

## 背景

用户反馈后台渠道编辑/新增功能没有更新，无法设置 `force_stream_option_include_usage` 配置项。

## 实施内容

### 1. 配置名称优化

将配置名称从 `force_stream_options` 改为 `force_stream_option_include_usage`，更准确反映配置含义：
- **旧名称**: `force_stream_options`
- **新名称**: `force_stream_option_include_usage`
- **作用**: 流式请求时强制附加 `stream_options.include_usage=true` 参数

### 2. 语言包更新

**中文语言包** (`laravel/lang/zh_CN/admin-channel.php`):
- 添加标签: `'force_stream_option_include_usage' => '强制流式Usage'`
- 添加帮助文本: `'force_stream_option_include_usage_help' => '流式请求时强制附加 stream_options.include_usage 参数，确保返回 token 统计信息（适用于 OpenAI 格式渠道）'`

**英文语言包** (`laravel/lang/en/admin-channel.php`):
- 添加标签: `'force_stream_option_include_usage' => 'Force Stream Usage'`
- 添加帮助文本: `'force_stream_option_include_usage_help' => 'Force append stream_options.include_usage parameter for streaming requests to ensure token statistics are returned (for OpenAI format channels)'`

### 3. 后台表单更新

**文件**: [laravel/app/Admin/Controllers/ChannelController.php](laravel/app/Admin/Controllers/ChannelController.php:360-378)

在"高级配置"标签页的嵌入表单中添加新字段：

```php
$form->switch('force_stream_option_include_usage')
    ->help(admin_trans_label('force_stream_option_include_usage_help'))
    ->default(false);
```

位置：在 `body_passthrough` 字段之后添加。

### 4. 数据库更新

通过 Tinker 为所有 OpenAI 渠道自动添加配置：

```bash
php artisan tinker
```

```php
use App\Models\Channel;

$channels = Channel::where('provider', 'openai')
    ->where('status', 1)
    ->get();

foreach ($channels as $channel) {
    $config = $channel->config ?? [];
    $config['force_stream_option_include_usage'] = true;
    $channel->config = $config;
    $channel->save();
}
```

**已更新渠道**：
- ID 1: 硅基流动-Openai
- ID 3: x-aio-openai
- ID 4: Me
- ID 7: 阅里coding-Openai
- ID 9: Test Channel

## 功能说明

### 配置作用

当渠道启用 `force_stream_option_include_usage=true` 时：

1. **流式请求自动附加参数**: 系统会自动在 OpenAI 格式的流式请求中添加 `stream_options.include_usage=true`
2. **确保返回 token 统计**: 上游 API 会返回 usage 字段，包含 prompt_tokens、completion_tokens、total_tokens
3. **完善审计日志**: 审计日志中的 token 使用量字段将正确记录统计数据

### 适用范围

- ✅ **OpenAI 官方 API**
- ✅ **Azure OpenAI**
- ✅ **支持 OpenAI 格式的第三方 API**
- ❌ **Anthropic API** (自动返回，无需配置)
- ❌ **不支持该参数的 API** (避免请求失败)

## 后台界面

渠道编辑页面 → "高级配置" 标签页 → "扩展配置" 区域：

```
[开关] 强制流式Usage
帮助文本: 流式请求时强制附加 stream_options.include_usage 参数，确保返回 token 统计信息（适用于 OpenAI 格式渠道）
默认值: 关闭
```

## 修改文件

- [laravel/app/Admin/Controllers/ChannelController.php](laravel/app/Admin/Controllers/ChannelController.php)
- [laravel/lang/zh_CN/admin-channel.php](laravel/lang/zh_CN/admin-channel.php)
- [laravel/lang/en/admin-channel.php](laravel/lang/en/admin-channel.php)

## 测试验证

访问后台渠道管理页面，编辑任意 OpenAI 渠道，在"高级配置"标签页应能看到新增的"强制流式Usage"开关。