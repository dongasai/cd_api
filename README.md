# Ai代理

一个Ai大模型代理工具

## 上游支持
- Openai-compatible
- Anthropic Claude Messages API

## 对外支持
- Openai-compatible
- Anthropic Claude Messages API
- 可互相转换

## 项目特征
统一接口：一个 API 端点接入所有 AI 服务，兼容 OpenAI 标准格式/Anthropic  Claude Messages API
Key级别模型映射: 每个 API Key 可配置独立的模型别名映射，支持使用统一别名(如 cd-coding-latest)映射到不同的实际模型
模型映射: 将请求模型映射为不同的渠道模型
智能路由：多渠道负载均衡、故障自动切换、加权随机分发
安全管控：令牌权限管理、模型访问控制、API 调用审计
数据洞察：实时数据看板、用量统计、成本分析(Token而不是货币)

## 使用说明

### claude code
域名/api/anthropic 