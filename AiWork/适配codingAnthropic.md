# 阿里云codingApi适配 anthropic 格式

> 阿里云codingApi是支持anthropic格式的,不要怀疑,支持,支持支持,不要怀疑
> 渠道ID: 6

## 核心要素
1. request_log 中从'api/anthropic/v1/messages'Api接收的的,是claude code的标准格式是对的,不应质疑
2. 目前在测试/修复的是 anthropic To anthropic 的转发,request_log(客户端的) 和 channel_request_logs(发给渠道的) 的body应该是一致的


## 问题
anthropic To anthropic应该request_log(客户端的) 和 channel_request_logs(发给渠道的) 的body应该是一致的
但是目前转换后丢失/增加东西了

## 问题分析

### 参考日志
1. 审计日志 1453

## 可用工具
```bash
# 分析指定审计日志ID的request和channel_request_logs 差异
php artisan analyze:request-diff {审计日志ID} --limit 10
# 重放客户端请求(得到新的审计ID)
php artisan request:replay-direct --audit-id={审计日志ID}
# 重放后应使用最新的 审计日志ID,来进行比对
```