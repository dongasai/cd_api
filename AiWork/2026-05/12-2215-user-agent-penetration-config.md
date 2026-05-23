# User-Agent 穿透配置说明

## 配置位置

在 **渠道(Channel)管理** 页面的 **高级配置** tab 中,有一个 `forward_headers` 字段可以配置要穿透的请求头。

路径: 后台管理 → 渠道管理 → 编辑渠道 → 高级配置 → 转发请求头

## 配置方法

### 1. 精确匹配

直接指定要穿透的header名称:

```
user-agent
```

### 2. 通配符匹配

支持通配符模式:

- **前缀匹配**: `user-agent*` (匹配以 user-agent 开头的所有header)
- **后缀匹配**: `*agent` (匹配以 agent 结尾的所有header)
- **完整通配**: `*` (穿透所有header,慎用)

### 3. 多个header穿透

可以配置多个header进行穿透,例如:

```
user-agent
x-real-ip
x-forwarded-for
authorization
```

## 实际配置示例

### User-Agent穿透配置

在后台管理界面:

1. 进入渠道编辑页面
2. 点击 **高级配置** tab
3. 在 **转发请求头** 字段中添加:
   ```
   user-agent
   ```
4. 保存渠道配置

### 配置效果

配置后,系统会自动:
- 从客户端原始请求中提取 `User-Agent` header
- 在转发请求到上游API时,将该header添加到请求中
- 上游API能看到真实的客户端User-Agent

## 工作原理

### 请求处理流程

```
客户端请求 → ProxyServer → ProviderManager → Provider Driver → 上游API
```

1. **ProxyServer** (第280行)
   ```php
   $provider = $this->providerManager->getForChannel(
       $this->selectedChannel,
       $request->headers->all()  // 传递所有客户端headers
   );
   ```

2. **ProviderManager** (第106行)
   ```php
   $config = [
       'forward_headers' => $channel->getForwardHeaderNames(),  // 从渠道配置读取
       'client_headers'  => $clientHeaders,  // 客户端原始headers
   ];
   ```

3. **AbstractProvider** (第160-191行)
   - 根据 `forward_headers` 配置匹配要转发的header
   - 过滤黑名单中的敏感header
   - 合并到请求headers中发送给上游

### Header匹配逻辑

AbstractProvider支持三种匹配模式:

```php
protected function matchHeaderPattern(string $pattern, string $headerName): bool
{
    // 前缀匹配: user-agent* 匹配 user-agent, user-agent-custom 等
    if (str_ends_with($pattern, '*')) {
        $prefix = substr($pattern, 0, -1);
        return str_starts_with($headerName, $prefix);
    }

    // 后缀匹配: *agent 匹配 user-agent, custom-agent 等
    if (str_starts_with($pattern, '*')) {
        $suffix = substr($pattern, 1);
        return str_ends_with($headerName, $suffix);
    }

    // 精确匹配: user-agent 只匹配 user-agent
    return $pattern === $headerName;
}
```

### 黑名单机制

某些敏感header不会被穿透,即使配置了也会被过滤:

```php
protected array $headerBlacklist = [];
```

可以通过渠道的高级配置添加黑名单:

```php
// 在渠道 config 字段中配置
'header_blacklist' => ['authorization', 'cookie']
```

## 相关数据库字段

Channel模型:
- `forward_headers`: array类型,存储要转发的header名称列表
- `config`: array类型,存储高级配置(包括header_blacklist等)

## 代码位置参考

1. **配置界面**: `laravel/app/Admin/Controllers/ChannelController.php:377`
2. **数据模型**: `laravel/app/Models/Channel.php:53,70`
3. **传递逻辑**: `laravel/app/Services/Provider/ProviderManager.php:106-107`
4. **匹配实现**: `laravel/app/Services/Provider/Driver/AbstractProvider.php:160-280`

## 注意事项

1. **安全性**: 谨慎配置header穿透,避免泄露敏感信息(如Authorization, Cookie等)
2. **大小写**: header匹配不区分大小写(内部转换为小写比较)
3. **优先级**: Provider的自定义header优先级高于穿透header(不会被覆盖)
4. **性能**: 大量header穿透可能影响性能,建议只配置必要的header

## 常见应用场景

1. **User-Agent穿透**: 让上游API识别真实客户端类型
2. **IP穿透**: 配置 `x-real-ip`, `x-forwarded-for` 传递真实IP
3. **自定义标识**: 穿透自定义header用于追踪或认证

## 快速配置步骤

1. 登录后台: http://192.168.4.107:32126/admin
2. 进入: 渠道管理 → 选择渠道 → 编辑
3. 高级配置 → 转发请求头 → 添加 `user-agent`
4. 保存并测试

---
**文档日期**: 2026-05-12
**适用版本**: CdApi (Laravel 12)