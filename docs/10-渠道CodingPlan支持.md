# 渠道 CodingPlan 支持

## 一、功能概述

```
┌─────────────────────────────────────────────────────────────────────┐
│                      渠道扩展功能架构                                │
└─────────────────────────────────────────────────────────────────────┘

渠道扩展功能提供智能化的渠道管理能力：
├─── Coding Plan 集成：对接 Coding 平台的计费计划
├─── 自动配额管理：根据计费计划自动限制渠道使用
├─── 自动禁用/启用：配额耗尽自动禁用，重置后自动启用
├─── 多计划支持：支持多种计费计划类型
└─── 实时同步：与 Coding 平台实时同步配额状态

核心价值:
├─── 降低成本：自动控制 AI 服务费用
├─── 防止超支：配额耗尽自动停止服务
├─── 灵活计费：支持多种计费模式
└─── 无缝集成：与 Coding 平台深度整合
```

---

## 二、主流 AI Coding 工具计费参考

### 2.1 GitHub Copilot 定价 (2025)

```
┌─────────────────────────────────────────────────────────────────────┐
│                    GitHub Copilot 定价体系                           │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额                                   │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Free         │ $0/月        │ 50 premium requests/月                 │
│              │              │ 2,000 completions/月                   │
│              │              │ 50 chat messages/月                    │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ $10/月       │ 300 premium requests/月                │
│ (Most Popular)│ $100/年     │ 无限 completions                       │
│              │              │ 无限 agent mode (GPT-5 mini)           │
│              │              │ CLI 支持                               │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro+         │ $39/月       │ 1,500 premium requests/月              │
│              │ $390/年      │ 无限 completions                       │
│              │              │ Coding Agent (第三方代理)              │
│              │              │ 优先支持                               │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Business     │ $19/月/用户  │ 300 premium requests/月/用户           │
│              │              │ 团队管理                               │
│              │              │ IP 保护                                │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ $39/月/用户  │ 1,000 premium requests/月/用户         │
│              │              │ 私有知识库索引                         │
│              │              │ 自定义模型微调                         │
│              │              │ SSO 集成                               │
└──────────────┴──────────────┴────────────────────────────────────────┘

Premium Request 说明:
┌─────────────────────────────────────────────────────────────────────┐
│ Premium Request = Prompt (用户发出的请求)                           │
├─────────────────────────────────────────────────────────────────────┤
│ 1 个 Premium Request = 1 次用户提问/请求                            │
│                                                                     │
│ 触发场景:                                                           │
│ ├─── Chat 对话: 每次用户发送消息                                    │
│ ├─── Agent 模式: 每次 Agent 任务执行                                │
│ ├─── Code Review: 每次代码审查请求                                  │
│ ├─── Coding Agent: 每次代理任务                                     │
│ └──   Copilot CLI: 每次命令行请求                                   │
│                                                                     │
│ 不消耗 Premium Request:                                             │
│ ├─── Inline Code Completions (代码补全): 无限                       │
│ └──   Agent mode with GPT-5 mini: 无限                             │
│                                                                     │
│ 模型消耗倍数 (Multiplier):                                          │
│ ┌─────────────────────────┬────────────────────────────────────────┐
│ │ 模型                    │ 消耗倍数                               │
│ ├─────────────────────────┼────────────────────────────────────────┤
│ │ GPT-5 mini              │ 1x (基础模型)                          │
│ │ GPT-4o                  │ 2x                                     │
│ │ Claude 3.5 Sonnet       │ 2x                                     │
│ │ o1-preview              │ 10x                                    │
│ │ o1-mini                 │ 5x                                     │
│ └─────────────────────────┴────────────────────────────────────────┘
│                                                                     │
│ 例: 使用 o1-preview 发送 1 个 prompt = 消耗 10 个 premium requests │
└─────────────────────────────────────────────────────────────────────┘

额外购买: $0.04/premium request
```

### 2.2 Cursor 定价 (2025)

```
┌─────────────────────────────────────────────────────────────────────┐
│                       Cursor 定价体系                                │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额                                   │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Free         │ $0/月        │ 2,000 completions/月                   │
│              │              │ 50 slow requests/月                    │
│              │              │ 基础模型访问                           │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ $20/月       │ 无限 requests (有速率限制)             │
│              │ $192/年      │ 或旧模式: 500 fast requests/月         │
│              │              │ 全模型访问                             │
│              │              │ Claude 3.5 Sonnet, GPT-4o              │
│              │              │ 取消工具调用限制                       │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Ultra        │ $200/月      │ Pro 的 20 倍使用量                     │
│              │              │ 所有 API 模型                          │
│              │              │ 最高优先级                             │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Business     │ $40/月/用户  │ 500 fast requests/月/用户              │
│              │              │ 团队管理                               │
│              │              │ 优先支持                               │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ 定制         │ 定制配额                               │
│              │              │ SSO 集成                               │
│              │              │ 私有部署                               │
└──────────────┴──────────────┴────────────────────────────────────────┘

Request 说明:
┌─────────────────────────────────────────────────────────────────────┐
│ Request = Prompt (用户发出的内容)                                   │
├─────────────────────────────────────────────────────────────────────┤
│ 1 个 Request = 1 次用户提问/请求                                    │
│                                                                     │
│ Fast Requests:                                                      │
│ ├─── 使用高级模型 (Claude 3.5 Sonnet, GPT-4o)                      │
│ ├─── 优先响应队列                                                   │
│ └─── Pro 用户: 500次/月 或 切换到无限模式(有速率限制)              │
│                                                                     │
│ Slow Requests:                                                      │
│ ├─── 使用基础模型                                                   │
│ ├─── 排队等待可用资源                                               │
│ └─── Free 用户: 50次/月, Pro 用户: 无限                            │
│                                                                     │
│ 速率限制 (Rate Limits):                                             │
│ ├─── 无限模式仍有后台速率限制                                       │
│ ├─── 基于模型类型和后台 API 用量动态调整                           │
│ └──   例如: DeepSeek 模型有 60k 上下文限制                         │
└─────────────────────────────────────────────────────────────────────┘

2025年6月更新:
├─── Pro 计划默认改为无限速率限制模型
├─── 取消工具调用 (Tool Calling) 的所有限制
├─── 新增 Ultra 计划 ($200/月)
└──   用户可选择切换回旧的 500 fast requests 模式
```

### 2.3 Windsurf (Codeium) 定价 (2025)

```
┌─────────────────────────────────────────────────────────────────────┐
│                    Windsurf 定价体系                                 │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额                                   │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Free         │ $0/月        │ 25 prompt credits/月                   │
│              │              │ 无限 tab completions                   │
│              │              │ 基础模型访问                           │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ $15/月       │ 500 prompt credits/月                  │
│              │              │ 全模型访问 (OpenAI, Claude, Gemini)    │
│              │              │ Fast Context 功能                      │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Teams        │ $30/月/用户  │ 500 prompt credits/月/用户             │
│              │              │ 团队管理面板                           │
│              │              │ 优先支持                               │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ 定制         │ 1,000 prompt credits/月/用户           │
│              │              │ SSO + RBAC                             │
│              │              │ 混合部署选项                           │
└──────────────┴──────────────┴────────────────────────────────────────┘

额外购买: $10/250 prompt credits
```

### 2.4 国内 AI Coding Plan 套餐对比 (2026)

```
┌─────────────────────────────────────────────────────────────────────┐
│                   国内主流 Coding Plan 套餐汇总 (2026年2月)          │
└─────────────────────────────────────────────────────────────────────┘

┌───────────────┬────────────────┬──────────────┬─────────────────────┐
│ 平台          │ 入门月价       │ 计费单位     │ 核心额度 (入门档)   │
├───────────────┼────────────────┼──────────────┼─────────────────────┤
│ 阿里云百炼    │ ¥40 (首月¥7.9) │ API请求次数  │ 1200次/5h, 18K/月   │
│ 火山方舟(字节)│ ¥40 (首月¥8.9) │ API请求次数  │ 1200次/5h, 18K/月   │
│ 智谱GLM       │ ¥49            │ Prompt次数   │ 80次/5h, 400次/周   │
│ Kimi(月暗)    │ ¥49            │ Token计量    │ 按Token, 活动3倍    │
│ MiniMax       │ ¥29 (首月¥9.9) │ Prompt次数   │ 40次/5h, 无周限额   │
│ 无问芯穹      │ ¥19.9          │ API请求次数  │ 1000次/5h           │
│ 百度千帆      │ ¥? (首月¥9.9)  │ API请求次数  │ 待公布              │
│ 摩尔线程      │ 定制           │ GPU算力      │ 国产GPU底座         │
└───────────────┴────────────────┴──────────────┴─────────────────────┘
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   阿里云百炼 Coding Plan 定价体系                    │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Lite         │ ¥40/月       │ 1200次/5小时, 9000次/周, 18000次/月    │
│ (入门档)     │ 首月¥7.9     │ 8大模型: Qwen3.5, GLM-5, Kimi K2.5等  │
│              │              │ 支持: Claude Code, Cline, OpenClaw等   │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ ¥200/月      │ Lite档5倍额度                          │
│ (专业档)     │ 首月¥39      │ 高级模型优先访问                       │
│              │              │ 更稳定的服务质量                       │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ 定制         │ 私有化部署                             │
│              │              │ 企业知识库                             │
│              │              │ 专属支持                               │
└──────────────┴──────────────┴────────────────────────────────────────┘

支持模型: Qwen3.5-Plus, Qwen3-Coder-Next, GLM-4.7, Kimi-K2.5, MiniMax M2.5等
适配工具: Claude Code, Cline, OpenClaw等4+工具
特点: 首月价格最低, 模型种类丰富, 依托阿里云基础设施稳定性强
槽点: 仅支持主账号不支持RAM子用户, 配置文档不完善, 无退订退款政策
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   火山方舟 (字节豆包) Coding Plan 定价体系            │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Lite         │ ¥40/月       │ 1200次/5小时, 9000次/周, 18000次/月    │
│ (入门档)     │ 首月¥8.91    │ 7+模型: Doubao-Seed-Code, DeepSeek等   │
│              │              │ Auto模式智能调度                       │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ ¥200/月      │ Lite档5倍额度                          │
│ (专业档)     │              │ 优先响应                               │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ 定制         │ 私有化部署                             │
│              │              │ 企业知识库                             │
└──────────────┴──────────────┴────────────────────────────────────────┘

支持模型: Doubao-Seed-Code, DeepSeek-V3.2, GLM-4.7, Kimi-K2.5 (Auto智能调度)
适配工具: Claude Code, Cursor, Cline, Codex CLI等7+工具
特点: 模型数量最多, Auto模式自动匹配最优模型, 配置简单(两步完成)
槽点: 存在超售问题响应缓慢, 频繁400/429错误, 退款条款苛刻
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   智谱 GLM Coding Plan 定价体系                      │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Lite         │ ¥49/月       │ 每5小时: ~80次 prompts                 │
│ (入门档)     │ 无首月优惠   │ 每周: ~400次 prompts                   │
│              │ (2月涨价30%) │ GLM-4.7 全套餐支持                     │
│              │              │ 免费MCP工具链                          │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ ¥?/月        │ 每5小时: ~400次 prompts                │
│ (专业档)     │              │ 每周: ~2000次 prompts                  │
│              │              │ GLM-5 (754B参数) 支持                  │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Max          │ ¥?/月        │ 每5小时: ~1600次 prompts               │
│ (高级档)     │              │ 每周: ~8000次 prompts                  │
│              │              │ GLM-5高峰期3倍/非高峰期2倍Token消耗    │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Enterprise   │ 定制         │ 私有化部署                             │
│              │              │ 企业知识库                             │
└──────────────┴──────────────┴────────────────────────────────────────┘

计费说明 (官方):
┌─────────────────────────────────────────────────────────────────────┐
│ 本质: Token计费 (官网"Prompt次数"为估算值)                          │
├─────────────────────────────────────────────────────────────────────┤
│ ├─── 1次Prompt = 1次用户提问                                        │
│ ├──   每次Prompt预计调用模型 15-20 次                               │
│ ├──   每月可用额度按API定价折算, 相当于月订阅费用的15-30倍          │
│ ├──   每5小时额度动态刷新 (请求消耗5小时后重置)                      │
│ └──   每周限额自下单时开启, 以7天为周期刷新重置                      │
└─────────────────────────────────────────────────────────────────────┘

支持模型: GLM-4.7 (全套餐), GLM-5 (仅Max套餐, 754B参数)
适配工具: Claude Code, Cline, CodeGeeX等20+工具
特点: 纯自研模型, 工具适配最广, 免费MCP工具链, GLM-5性能强劲
槽点: 涨价后性价比下降30%+, GLM-5额度抵扣规则复杂, 新用户每周限额严格
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   Kimi (月之暗面) Coding Plan 定价体系               │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Andante      │ ¥49/月       │ Token计量 (2026.1.28切换)              │
│ (入门档)     │ 限时3倍额度  │ 缓存命中率影响实际额度                 │
│              │              │ Kimi K2.5 (1T参数, 国内最大)           │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Allegretto   │ ¥199/月      │ 更高Token额度                          │
│ (高级档)     │              │ Agent集群支持                          │
│              │              │ 长上下文 (256K)                        │
└──────────────┴──────────────┴────────────────────────────────────────┘

支持模型: Kimi K2.5 (原生多模态, 1T参数, 国内最大)
适配工具: Kimi Code CLI, VSCode插件等3-4个 (限制严格)
特点: 原生多模态支持截图输入Vibe Coding, 长上下文能力强(256K)
槽点: 工具适配少非指定工具可能封号, 仅限个人禁止企业, Token计量受缓存影响大
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   MiniMax Coding Plan 定价体系                       │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Starter      │ ¥29/月       │ 40次Prompt/5小时                       │
│ (入门档)     │ 首月¥9.9     │ 无每周/每月限额                        │
│              │              │ MiniMax M2.5, M2.5-highspeed           │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ ¥?/月        │ 更高额度                               │
│ (高级档)     │              │ 极速版 100+ TPS                        │
└──────────────┴──────────────┴────────────────────────────────────────┘

支持模型: MiniMax M2.5, M2.5-highspeed (极速版专属)
适配工具: Claude Code, Cline等2+工具
特点: 入门价最低, 无每周限额适合连续高强度使用, 极速版100+TPS响应快
槽点: 模型规模最小(10B参数)仅适合轻量级脚本任务, 套餐档位多选择复杂
```

```
┌─────────────────────────────────────────────────────────────────────┐
│                   无问芯穹 (Infini) Coding Plan 定价体系             │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────┬──────────────┬────────────────────────────────────────┐
│ 计划         │ 价格         │ 配额/功能                              │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Lite         │ ¥19.9/月     │ 1000次/5小时                           │
│ (入门档)     │ 无首月优惠   │ 多模型聚合: DeepSeek, MiniMax, Kimi等  │
├──────────────┼──────────────┼────────────────────────────────────────┤
│ Pro          │ ¥?/月        │ 更高额度                               │
└──────────────┴──────────────┴────────────────────────────────────────┘

支持模型: DeepSeek, MiniMax, Kimi, GLM (多模型聚合)
适配工具: 主流编程工具兼容4+
特点: 月费最低, 多模型聚合性价比极高, 单次复杂任务成本仅8分钱
槽点: 品牌知名度低, 模型更新速度慢, 无首月优惠
```

### 2.5 计费单位换算说明

```
┌─────────────────────────────────────────────────────────────────────┐
│                   Coding Plan 计费单位换算                           │
└─────────────────────────────────────────────────────────────────────┘

五种计费单位对比:

1. Premium Requests (GitHub Copilot)
   ├─── 1次 Premium Request = 1次用户提问 (Prompt)
   ├─── 不同模型消耗不同倍数 (1x-10x)
   └─── 例: 使用 o1-preview 发送1个prompt = 消耗10个 premium requests

2. Fast/Slow Requests (Cursor)
   ├─── 1次 Request = 1次用户提问 (Prompt)
   ├─── Fast: 使用高级模型, 优先响应
   ├─── Slow: 使用基础模型, 排队等待
   └──   Pro用户: 无限requests (有速率限制) 或 500 fast requests/月

3. Prompt Credits (Windsurf/Codeium)
   ├─── 1次 Prompt = 1次用户提问
   ├─── 不同模型消耗不同积分
   └──   例: Free 25 credits/月, Pro 500 credits/月

4. API请求次数 (阿里云百炼、火山方舟、无问芯穹)
   ├─── 1次用户提问 = 后台触发 5-30次模型调用
   ├─── 1次调用 = 1次API请求
   └──   例: 1200次/5小时 ≈ 40-240次用户提问

5. Prompt次数 (MiniMax、智谱GLM)
   ├─── 1次Prompt = 1次用户提问
   ├─── 智谱GLM: 每次Prompt预计调用模型 15-20 次
   ├──   智谱GLM: 本质Token计费, 官网显示为估算值
   └──   MiniMax: 1次Prompt ≈ 1200-1600次API请求

6. Token计量 (Kimi)
   ├─── 按输入输出Token计费
   ├─── 仅统计未命中缓存的Token
   ├──   缓存命中率直接影响实际额度
   └──   官网展示的"Prompt次数"为估算值，实际按Token消耗

换算参考:
┌─────────────────────────────────────────────────────────────────────┐
│ 国外:                                                               │
│ ├─── 1 Premium Request (Copilot) ≈ 1 Prompt (Cursor) ≈ 1 Credit   │
│                                                                     │
│ 国内:                                                               │
│ ├─── 智谱GLM: 80次Prompt/5h ≈ 官网估算值 (实际Token计费)           │
│ ├──   MiniMax: 1次Prompt ≈ 1200-1600次API请求                      │
│ └──   百炼/火山: 1200次API请求/5h ≈ 40-240次用户提问               │
└─────────────────────────────────────────────────────────────────────┘
```

### 2.6 国内外计费模式对比

```
┌─────────────────────────────────────────────────────────────────────┐
│                   国内外 AI Coding 计费模式对比                       │
└─────────────────────────────────────────────────────────────────────┘

┌─────────────┬──────────────────┬──────────────────────────────────────┐
│ 维度        │ 国外工具         │ 国内工具                             │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 计费单位    │ 积分/请求次数    │ 许可证数/用户数                     │
│             │ (Credits/Requests)│ (License/User)                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 个人版价格  │ $10-20/月        │ 免费 - ¥59/月                       │
│             │ (¥70-140/月)     │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 企业版价格  │ $19-39/用户/月   │ ¥79-159/用户/月                     │
│             │ (¥135-275/用户/月)│                                     │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 模型差异化  │ 明确倍数机制     │ 较少公开                            │
│             │ (1x-10x)         │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 额外购买    │ 按量付费         │ 较少支持                            │
│             │ ($0.04/request)  │                                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 免费层      │ 有限配额         │ 多数免费或限免                      │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 私有部署    │ Enterprise 定制  │ 企业版支持                          │
├─────────────┼──────────────────┼──────────────────────────────────────┤
│ 知识库      │ Enterprise 功能  │ 企业版标配                          │
└─────────────┴──────────────────┴──────────────────────────────────────┘

国内特色:
├─── 更注重企业知识库功能
├──   私有化部署需求更强
├──   价格更亲民 (免费策略普及)
├──   中文场景优化
└──   与云厂商生态深度绑定
```

### 2.6 行业趋势总结

```
┌─────────────────────────────────────────────────────────────────────┐
│                    AI Coding 计费趋势                                │
└─────────────────────────────────────────────────────────────────────┘

核心计费模式:
├─── 积分制 (Credits): Windsurf 的 prompt credits
├─── 请求次数制: GitHub 的 premium requests, Cursor 的 fast requests
├─── 模型差异化: 不同模型消耗不同倍数积分
└─── 分层定价: Free → Pro → Teams → Enterprise

共同特点:
├─── 免费层: 有限配额，吸引用户
├─── 个人层: 月费 $10-20，适合个人开发者
├─── 团队层: 月费 $30-40/用户，团队协作功能
├─── 企业层: 定制价格，安全合规功能
├─── 额外购买: 超出配额可按量付费
└─── 周期重置: 月度周期，配额每月重置

模型消耗倍数 (参考):
┌─────────────────────────┬────────────────────────────────────────────┐
│ 模型级别                │ 建议倍数                                   │
├─────────────────────────┼────────────────────────────────────────────┤
│ 基础模型 (GPT-3.5等)    │ 1x                                         │
│ 标准模型 (GPT-4o等)     │ 2x                                         │
│ 高级模型 (Claude 3.5)   │ 2-3x                                       │
│ 推理模型 (o1系列)       │ 5-10x                                      │
└─────────────────────────┴────────────────────────────────────────────┘
```

---

## 三、Coding Plan 设计

### 3.1 计费计划类型

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Coding Plan 计费类型                            │
└─────────────────────────────────────────────────────────────────────┘

计划类型:
┌─────────────────┬──────────────────────────────────────────────────┐
│ 类型            │ 说明                                             │
├─────────────────┼──────────────────────────────────────────────────┤
│ free            │ 免费计划，有严格限制                             │
│ pro             │ 专业计划，适合个人用户                           │
│ pro_plus        │ 专业增强版，高级模型访问                         │
│ teams           │ 团队计划，多人协作                               │
│ enterprise      │ 企业计划，自定义配额                             │
│ pay_as_you_go   │ 按量付费，无固定限制                             │
│ trial           │ 试用计划，时间限制                               │
└─────────────────┴──────────────────────────────────────────────────┘

配额维度:
┌─────────────────┬──────────────────────────────────────────────────┐
│ 维度            │ 说明                                             │
├─────────────────┼──────────────────────────────────────────────────┤
│ credits         │ 积分配额 (核心计费单位)                          │
│ requests        │ 请求次数限制                                     │
│ tokens          │ Token 数量限制                                   │
│ cost            │ 金额限制 (美元)                                  │
│ models          │ 可用模型限制                                     │
│ features        │ 功能限制 (streaming, vision, agent, etc.)        │
│ time            │ 时间限制 (试用期)                                │
└─────────────────┴──────────────────────────────────────────────────┘
```

### 3.2 数据库设计

```
┌─────────────────────────────────────────────────────────────────────┐
│                      计费计划表设计                                  │
└─────────────────────────────────────────────────────────────────────┘

计费计划表 (coding_plans):
CREATE TABLE coding_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coding_plan_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'Coding 平台计划 ID',
    tenant_id BIGINT UNSIGNED NULL COMMENT '关联租户 ID',
    name VARCHAR(255) NOT NULL COMMENT '计划名称',
    type ENUM('free', 'pro', 'pro_plus', 'teams', 'enterprise', 'pay_as_you_go', 'trial') NOT NULL,
    status ENUM('active', 'suspended', 'expired', 'cancelled') DEFAULT 'active',
    
    -- 积分配额 (核心计费单位)
    credits_limit INT UNSIGNED DEFAULT 0 COMMENT '积分上限',
    credits_used INT UNSIGNED DEFAULT 0 COMMENT '已用积分',
    credits_remaining INT UNSIGNED DEFAULT 0 COMMENT '剩余积分',
    
    -- 配额定义
    quota_definition JSON NOT NULL COMMENT '配额定义',
    
    -- 使用情况
    quota_used JSON DEFAULT NULL COMMENT '已用配额',
    quota_remaining JSON DEFAULT NULL COMMENT '剩余配额',
    
    -- 时间相关
    billing_cycle ENUM('daily', 'weekly', 'monthly', 'yearly', 'none') DEFAULT 'monthly',
    period_start TIMESTAMP NULL COMMENT '当前周期开始时间',
    period_end TIMESTAMP NULL COMMENT '当前周期结束时间',
    expires_at TIMESTAMP NULL COMMENT '计划过期时间',
    
    -- 同步相关
    last_sync_at TIMESTAMP NULL COMMENT '最后同步时间',
    sync_token VARCHAR(255) NULL COMMENT '同步令牌',
    
    -- 扩展配置
    config JSON DEFAULT NULL COMMENT '扩展配置',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_coding_plan_id (coding_plan_id),
    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status),
    INDEX idx_period (period_start, period_end),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Coding 计费计划表';

模型消耗倍数表 (model_multipliers):
CREATE TABLE model_multipliers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_pattern VARCHAR(100) NOT NULL COMMENT '模型匹配模式 (支持通配符)',
    multiplier DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT '消耗倍数',
    category VARCHAR(50) DEFAULT 'standard' COMMENT '模型分类: basic/standard/advanced/reasoning',
    description VARCHAR(255) NULL COMMENT '描述',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_model_pattern (model_pattern),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模型消耗倍数表';

-- 预置模型消耗倍数
INSERT INTO model_multipliers (model_pattern, multiplier, category, description) VALUES
('gpt-3.5-*', 1.00, 'basic', 'GPT-3.5 系列基础模型'),
('gpt-4o-mini*', 1.00, 'basic', 'GPT-4o-mini 基础模型'),
('gpt-4o*', 2.00, 'standard', 'GPT-4o 标准模型'),
('gpt-4-turbo*', 2.00, 'standard', 'GPT-4 Turbo 标准模型'),
('gpt-4-*', 2.00, 'standard', 'GPT-4 标准模型'),
('claude-3-5-sonnet*', 2.00, 'standard', 'Claude 3.5 Sonnet 标准模型'),
('claude-3-5-haiku*', 1.00, 'basic', 'Claude 3.5 Haiku 基础模型'),
('claude-3-opus*', 3.00, 'advanced', 'Claude 3 Opus 高级模型'),
('o1-preview*', 10.00, 'reasoning', 'o1 推理模型'),
('o1-mini*', 5.00, 'reasoning', 'o1-mini 推理模型'),
('gemini-1.5-flash*', 1.00, 'basic', 'Gemini Flash 基础模型'),
('gemini-1.5-pro*', 2.00, 'standard', 'Gemini Pro 标准模型');

渠道-计划关联表 (channel_plan_pivot):
CREATE TABLE channel_plan_pivot (
    channel_id BIGINT UNSIGNED NOT NULL,
    plan_id BIGINT UNSIGNED NOT NULL,
    priority INT UNSIGNED DEFAULT 1 COMMENT '优先级',
    enabled BOOLEAN DEFAULT TRUE COMMENT '是否启用',
    
    -- 渠道级别的配额覆盖
    quota_override JSON DEFAULT NULL COMMENT '配额覆盖配置',
    
    -- 自动控制配置
    auto_disable BOOLEAN DEFAULT TRUE COMMENT '配额耗尽是否自动禁用',
    auto_enable BOOLEAN DEFAULT TRUE COMMENT '配额重置是否自动启用',
    disable_threshold DECIMAL(5,2) DEFAULT 0.95 COMMENT '禁用阈值 (95%)',
    warning_threshold DECIMAL(5,2) DEFAULT 0.80 COMMENT '警告阈值 (80%)',
    
    PRIMARY KEY (channel_id, plan_id),
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE CASCADE,
    FOREIGN KEY (plan_id) REFERENCES coding_plans(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='渠道计划关联表';

配额使用记录表 (quota_usage_logs):
CREATE TABLE quota_usage_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id BIGINT UNSIGNED NOT NULL,
    channel_id BIGINT UNSIGNED NULL,
    request_id VARCHAR(36) NULL,
    
    -- 使用量
    credits_used INT UNSIGNED DEFAULT 1 COMMENT '消耗积分',
    requests INT UNSIGNED DEFAULT 1,
    tokens_input INT UNSIGNED DEFAULT 0,
    tokens_output INT UNSIGNED DEFAULT 0,
    cost DECIMAL(10, 6) DEFAULT 0,
    
    -- 模型信息
    model VARCHAR(100) NULL,
    model_multiplier DECIMAL(5,2) DEFAULT 1.00 COMMENT '使用的模型倍数',
    
    -- 时间
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_plan_created (plan_id, created_at),
    INDEX idx_channel_created (channel_id, created_at),
    INDEX idx_model_created (model, created_at),
    FOREIGN KEY (plan_id) REFERENCES coding_plans(id) ON DELETE CASCADE,
    FOREIGN KEY (channel_id) REFERENCES channels(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='配额使用记录表';
```

### 3.3 配额定义结构

```
┌─────────────────────────────────────────────────────────────────────┐
│                      配额定义 JSON 结构                              │
└─────────────────────────────────────────────────────────────────────┘

quota_definition 示例:
{
    "credits": {
        "limit": 500,
        "period": "monthly",
        "reset_day": 1,
        "rollover": false
    },
    "requests": {
        "limit": 10000,
        "period": "monthly",
        "reset_day": 1
    },
    "tokens": {
        "input_limit": 5000000,
        "output_limit": 2500000,
        "total_limit": 7500000,
        "period": "monthly"
    },
    "cost": {
        "limit": 100.00,
        "currency": "USD",
        "period": "monthly"
    },
    "models": {
        "allowed": ["gpt-4o", "gpt-3.5-turbo", "claude-3-5-sonnet"],
        "restricted": ["o1-preview", "claude-3-opus"],
        "multiplier_override": {
            "gpt-4o": 1.5
        }
    },
    "features": {
        "streaming": true,
        "function_calling": true,
        "vision": false,
        "agent_mode": true,
        "code_review": false
    },
    "rate_limits": {
        "requests_per_minute": 60,
        "tokens_per_minute": 10000
    },
    "time": {
        "trial_days": 14,
        "expires_at": "2024-12-31T23:59:59Z"
    }
}

quota_used 示例:
{
    "credits": 350,
    "requests": 8500,
    "tokens": {
        "input": 4000000,
        "output": 2000000,
        "total": 6000000
    },
    "cost": 85.50,
    "models": {
        "gpt-4o": {"credits": 200, "requests": 3000},
        "claude-3-5-sonnet": {"credits": 150, "requests": 5500}
    },
    "last_updated": "2024-01-15T10:30:00Z"
}

quota_remaining 示例:
{
    "credits": 150,
    "requests": 1500,
    "tokens": {
        "input": 1000000,
        "output": 500000,
        "total": 1500000
    },
    "cost": 14.50,
    "percentage": 0.30
}
```

---

## 四、积分计算核心逻辑

### 4.1 积分消耗计算

```
┌─────────────────────────────────────────────────────────────────────┐
│                      积分消耗计算逻辑                                │
└─────────────────────────────────────────────────────────────────────┘

计算公式:
credits_used = base_credits × model_multiplier × feature_multiplier

基础积分 (base_credits):
├─── Chat 请求: 1 credit
├─── Completion 请求: 1 credit  
├─── Agent 模式: 2 credits
├─── Code Review: 2 credits
└─── Embedding: 0.1 credits

模型消耗倍数 (model_multiplier):
┌─────────────────────────┬────────────────────────────────────────────┐
│ 模型分类                │ 倍数范围                                   │
├─────────────────────────┼────────────────────────────────────────────┤
│ basic (基础)            │ 1x                                         │
│ standard (标准)         │ 2x                                         │
│ advanced (高级)         │ 3x                                         │
│ reasoning (推理)        │ 5-10x                                      │
└─────────────────────────┴────────────────────────────────────────────┘

功能消耗倍数 (feature_multiplier):
├─── 普通请求: 1.0x
├─── 流式输出: 1.0x
├─── Vision: 1.5x
├─── Function Calling: 1.2x
└─── 长上下文 (>32k): 2.0x
```

### 4.2 积分计算服务

```php
<?php

namespace App\Services;

use App\Models\CodingPlan;
use App\Models\ModelMultiplier;

class CreditCalculator
{
    protected array $baseCredits = [
        'chat' => 1,
        'completion' => 1,
        'agent' => 2,
        'code_review' => 2,
        'embedding' => 0.1,
    ];

    protected array $featureMultipliers = [
        'vision' => 1.5,
        'function_calling' => 1.2,
        'long_context' => 2.0,
        'streaming' => 1.0,
    ];

    public function calculateCredits(
        string $requestType,
        string $model,
        array $features = []
    ): int {
        $baseCredits = $this->baseCredits[$requestType] ?? 1;
        $modelMultiplier = $this->getModelMultiplier($model);
        $featureMultiplier = $this->getFeatureMultiplier($features);

        return (int) ceil($baseCredits * $modelMultiplier * $featureMultiplier);
    }

    protected function getModelMultiplier(string $model): float
    {
        $multiplier = ModelMultiplier::where('is_active', true)
            ->where(function ($query) use ($model) {
                $query->where('model_pattern', $model)
                    ->orWhereRaw('? LIKE REPLACE(model_pattern, "*", "%")', [$model]);
            })
            ->orderByRaw('LENGTH(model_pattern) DESC')
            ->first();

        return $multiplier?->multiplier ?? 1.0;
    }

    protected function getFeatureMultiplier(array $features): float
    {
        $multiplier = 1.0;

        foreach ($features as $feature => $enabled) {
            if ($enabled && isset($this->featureMultipliers[$feature])) {
                $multiplier *= $this->featureMultipliers[$feature];
            }
        }

        return $multiplier;
    }
}
```

### 4.3 配额检查服务

```php
<?php

namespace App\Services;

use App\Models\CodingPlan;
use App\Models\Channel;
use Illuminate\Support\Facades\Redis;

class QuotaChecker
{
    public function checkQuota(CodingPlan $plan, int $creditsNeeded): array
    {
        $cacheKey = "cdapi:quota:{$plan->id}:credits";
        $cachedCredits = Redis::get($cacheKey);
        
        $remainingCredits = $cachedCredits !== null 
            ? (int) $cachedCredits 
            : $plan->credits_remaining;

        if ($remainingCredits < $creditsNeeded) {
            return [
                'allowed' => false,
                'reason' => 'insufficient_credits',
                'remaining' => $remainingCredits,
                'needed' => $creditsNeeded,
            ];
        }

        $usagePercentage = 1 - ($remainingCredits - $creditsNeeded) / $plan->credits_limit;

        return [
            'allowed' => true,
            'remaining' => $remainingCredits,
            'needed' => $creditsNeeded,
            'usage_percentage' => $usagePercentage,
            'warning' => $usagePercentage >= 0.8,
        ];
    }

    public function consumeCredits(CodingPlan $plan, int $credits, string $model): void
    {
        $plan->increment('credits_used', $credits);
        $plan->decrement('credits_remaining', $credits);

        Redis::decrby("cdapi:quota:{$plan->id}:credits", $credits);

        $this->recordUsage($plan, $credits, $model);
    }

    protected function recordUsage(CodingPlan $plan, int $credits, string $model): void
    {
        $usageKey = "cdapi:usage:{$plan->id}:" . date('Y-m-d');
        Redis::hincrby($usageKey, "credits", $credits);
        Redis::hincrby($usageKey, "model:{$model}:credits", $credits);
        Redis::expire($usageKey, 86400 * 90);
    }
}
```

---

## 五、自动禁用/启用机制

### 5.1 状态流转

```
┌─────────────────────────────────────────────────────────────────────┐
│                      渠道自动状态流转                                │
└─────────────────────────────────────────────────────────────────────┘

状态机:
                                    配额重置
    ┌─────────────┐ ◀─────────────────────────────────────┐
    │   active    │                                        │
    │   (正常)    │                                        │
    └──────┬──────┘                                        │
           │                                               │
           │ 配额使用 >= warning_threshold (80%)           │
           ▼                                               │
    ┌─────────────┐                                        │
    │   warning   │ ───── 发送警告通知 ────▶               │
    │   (警告)    │                                        │
    └──────┬──────┘                                        │
           │                                               │
           │ 配额使用 >= disable_threshold (95%)           │
           ▼                                               │
    ┌─────────────┐                                        │
    │  throttled  │ ───── 开始限流 ────▶                   │
    │  (限流)     │                                        │
    └──────┬──────┘                                        │
           │                                               │
           │ 配额耗尽                                       │
           ▼                                               │
    ┌─────────────┐                                        │
    │  disabled   │ ───── 自动禁用渠道 ────▶               │
    │  (禁用)     │                                        │
    └─────────────┘                                        │
           │                                               │
           │ 配额重置 (新周期)                              │
           └───────────────────────────────────────────────┘

状态说明:
┌─────────────────┬──────────────────────────────────────────────────┐
│ 状态            │ 行为                                             │
├─────────────────┼──────────────────────────────────────────────────┤
│ active          │ 正常服务，无限制                                 │
│ warning         │ 正常服务，发送警告通知                           │
│ throttled       │ 限流服务，降低请求速率                           │
│ disabled        │ 停止服务，返回配额超限错误                       │
└─────────────────┴──────────────────────────────────────────────────┘
```

### 5.2 自动控制配置

```
┌─────────────────────────────────────────────────────────────────────┐
│                      自动控制配置详解                                │
└─────────────────────────────────────────────────────────────────────┘

配置项:
┌─────────────────────┬──────────────────────────────────────────────┐
│ 配置项              │ 说明                                         │
├─────────────────────┼──────────────────────────────────────────────┤
│ auto_disable        │ 配额耗尽时是否自动禁用渠道                   │
│ auto_enable         │ 配额重置时是否自动启用渠道                   │
│ disable_threshold   │ 自动禁用阈值 (0.0-1.0)                       │
│ warning_threshold   │ 警告通知阈值 (0.0-1.0)                       │
│ throttle_threshold  │ 限流阈值 (0.0-1.0)                           │
│ throttle_rate       │ 限流比例 (0.0-1.0)                           │
│ grace_period        │ 宽限期 (秒)，超过阈值后的缓冲时间            │
│ cooldown_period     │ 冷却期 (秒)，禁用后需等待的时间              │
└─────────────────────┴──────────────────────────────────────────────┘

配置示例:
{
    "auto_disable": true,
    "auto_enable": true,
    "disable_threshold": 0.95,
    "warning_threshold": 0.80,
    "throttle_threshold": 0.90,
    "throttle_rate": 0.5,
    "grace_period": 300,
    "cooldown_period": 3600,
    "notifications": {
        "warning": {
            "channels": ["email", "webhook"],
            "recipients": ["admin@example.com"]
        },
        "disabled": {
            "channels": ["email", "sms", "webhook"],
            "recipients": ["admin@example.com", "ops@example.com"]
        }
    }
}
```

### 5.3 状态检测流程

```
┌─────────────────────────────────────────────────────────────────────┐
│                      配额状态检测流程                                │
└─────────────────────────────────────────────────────────────────────┘

每次请求前执行:
┌─────────────────────────────────────────────────────────────────────┐
│                                                                     │
│ 1. 获取渠道关联的计费计划                                           │
│    │                                                                │
│    ▼                                                                │
│ 2. 检查计划状态                                                     │
│    ├─── expired → 返回错误: 计划已过期                              │
│    ├─── suspended → 返回错误: 计划已暂停                            │
│    └─── active → 继续                                               │
│    │                                                                │
│    ▼                                                                │
│ 3. 检查配额使用率                                                   │
│    │                                                                │
│    ├─── 使用率 >= disable_threshold                                 │
│    │    ├─── 检查宽限期                                             │
│    │    │    ├─── 在宽限期内 → 允许请求                             │
│    │    │    └─── 超过宽限期 → 触发禁用                             │
│    │    │                                                           │
│    │    └─── 触发禁用流程                                           │
│    │         ├─── 更新渠道状态为 disabled                           │
│    │         ├─── 记录禁用日志                                      │
│    │         ├─── 发送通知                                          │
│    │         └─── 返回错误: 配额已耗尽                              │
│    │                                                                │
│    ├─── 使用率 >= throttle_threshold                                │
│    │    ├─── 检查是否需要限流                                       │
│    │    ├─── 应用限流策略                                           │
│    │    └─── 允许请求 (可能延迟)                                    │
│    │                                                                │
│    ├─── 使用率 >= warning_threshold                                 │
│    │    ├─── 检查是否已发送警告                                     │
│    │    ├─── 发送警告通知 (首次)                                    │
│    │    └─── 允许请求                                               │
│    │                                                                │
│    └─── 使用率 < warning_threshold                                  │
│         └─── 允许请求                                               │
│    │                                                                │
│    ▼                                                                │
│ 4. 执行请求                                                         │
│    │                                                                │
│    ▼                                                                │
│ 5. 更新配额使用量                                                   │
│    ├─── 累加 requests                                               │
│    ├─── 累加 tokens                                                 │
│    ├─── 累加 cost                                                   │
│    └─── 更新 quota_used 和 quota_remaining                         │
│    │                                                                │
│    ▼                                                                │
│ 6. 检查是否需要状态变更                                             │
│    └─── 如需变更，触发相应流程                                      │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 六、Coding API 集成

### 6.1 API 接口设计

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Coding API 集成                                │
└─────────────────────────────────────────────────────────────────────┘

获取计费计划:
GET /api/coding/plans/{plan_id}
响应:
{
    "id": "plan_xxx",
    "name": "Pro Plan",
    "type": "pro",
    "status": "active",
    "quota": {
        "requests": {"limit": 10000, "used": 8500},
        "tokens": {"limit": 7500000, "used": 6000000},
        "cost": {"limit": 100.00, "used": 85.50}
    },
    "period": {
        "start": "2024-01-01T00:00:00Z",
        "end": "2024-01-31T23:59:59Z"
    }
}

同步配额使用:
POST /api/coding/plans/{plan_id}/sync
请求:
{
    "usage": {
        "requests": 100,
        "tokens_input": 5000,
        "tokens_output": 2500,
        "cost": 0.5
    },
    "request_id": "uuid-xxx",
    "timestamp": "2024-01-15T10:30:00Z"
}

配额预警回调:
POST /api/coding/webhooks/quota-warning
请求:
{
    "event": "quota_warning",
    "plan_id": "plan_xxx",
    "usage_percentage": 0.85,
    "timestamp": "2024-01-15T10:30:00Z"
}

配额耗尽回调:
POST /api/coding/webhooks/quota-exhausted
请求:
{
    "event": "quota_exhausted",
    "plan_id": "plan_xxx",
    "exhausted_at": "2024-01-15T10:30:00Z"
}

配额重置回调:
POST /api/coding/webhooks/quota-reset
请求:
{
    "event": "quota_reset",
    "plan_id": "plan_xxx",
    "new_period": {
        "start": "2024-02-01T00:00:00Z",
        "end": "2024-02-29T23:59:59Z"
    }
}
```

### 6.2 同步策略

```
┌─────────────────────────────────────────────────────────────────────┐
│                      同步策略设计                                    │
└─────────────────────────────────────────────────────────────────────┘

实时同步:
├─── 每次请求后推送使用量到 Coding
├─── 使用消息队列异步推送
└─── 失败时重试 3 次

定时同步:
├─── 每 5 分钟批量同步使用量
├─── 对比本地和远程数据
└─── 差异超过阈值时告警

全量同步:
├─── 每日凌晨 2:00 执行
├─── 同步计划状态、配额定义
└─── 修正使用量偏差

同步配置:
{
    "sync": {
        "realtime": true,
        "batch_interval": 300,
        "full_sync_time": "02:00",
        "retry_attempts": 3,
        "retry_delay": 1000,
        "timeout": 10000
    },
    "cache": {
        "enabled": true,
        "ttl": 60
    }
}
```

### 6.3 认证与安全

```
┌─────────────────────────────────────────────────────────────────────┐
│                      认证与安全设计                                  │
└─────────────────────────────────────────────────────────────────────┘

认证方式:
├─── API Key: 用于服务端调用
├─── JWT Token: 用于用户身份验证
└─── Webhook Signature: 用于回调验证

API Key 配置:
{
    "coding_api": {
        "base_url": "https://api.coding.net",
        "api_key": "xxx",
        "api_secret": "xxx",
        "timeout": 10000
    }
}

Webhook 验证:
├─── 验证请求来源 IP
├─── 验证签名 (HMAC-SHA256)
├─── 验证时间戳 (防重放)
└─── 验证事件类型

签名验证示例:
function verifyWebhook(payload, signature, secret) {
    const expected = crypto
        .createHmac('sha256', secret)
        .update(JSON.stringify(payload))
        .digest('hex');
    return crypto.timingSafeEqual(
        Buffer.from(signature),
        Buffer.from(expected)
    );
}
```

---

## 七、限流策略

### 7.1 限流模式

```
┌─────────────────────────────────────────────────────────────────────┐
│                      限流模式设计                                    │
└─────────────────────────────────────────────────────────────────────┘

模式一: 拒绝请求
├─── 超过限流阈值直接拒绝
├─── 返回 429 错误
└─── 适用场景: 严格限制

模式二: 排队等待
├─── 超过限流阈值进入队列
├─── 等待可用配额
├─── 超时返回错误
└─── 适用场景: 允许延迟

模式三: 降级服务
├─── 超过限流阈值降级
├─── 切换到低成本模型
├─── 或返回缓存结果
└─── 适用场景: 保证可用性

模式四: 智能调度
├─── 根据配额剩余动态调整
├─── 高优先级请求优先处理
├─── 低优先级请求延迟处理
└─── 适用场景: 复杂业务

配置示例:
{
    "throttle_strategy": "queue",
    "throttle_config": {
        "mode": "queue",
        "queue_size": 100,
        "queue_timeout": 30,
        "priority_levels": 3
    }
}
```

### 7.2 限流实现

```
┌─────────────────────────────────────────────────────────────────────┐
│                      限流实现设计                                    │
└─────────────────────────────────────────────────────────────────────┘

Redis 限流 Key:
┌─────────────────────────────────────────────────────────────────────┐
│ Key                                   │ 说明                        │
├───────────────────────────────────────┼────────────────────────────┤
│ cdapi:quota:{plan_id}:requests        │ 计划请求计数               │
│ cdapi:quota:{plan_id}:tokens          │ 计划 Token 计数            │
│ cdapi:quota:{plan_id}:cost            │ 计划成本计数               │
│ cdapi:throttle:{plan_id}:queue        │ 限流队列                   │
│ cdapi:throttle:{plan_id}:rate         │ 当前限流速率               │
└─────────────────────────────────────────────────────────────────────┘

限流算法:
基于配额剩余动态调整限流阈值:

function calculateThrottleRate(quotaRemaining, quotaLimit) {
    const percentage = quotaRemaining / quotaLimit;
    
    if (percentage > 0.9) {
        return 1.0;  // 正常速率
    } else if (percentage > 0.5) {
        return 0.8;  // 降低 20%
    } else if (percentage > 0.2) {
        return 0.5;  // 降低 50%
    } else {
        return 0.2;  // 降低 80%
    }
}
```

---

## 八、通知系统

### 8.1 通知类型

```
┌─────────────────────────────────────────────────────────────────────┐
│                      通知类型设计                                    │
└─────────────────────────────────────────────────────────────────────┘

通知事件:
┌─────────────────────┬──────────────────────────────────────────────┐
│ 事件                │ 触发条件                                     │
├─────────────────────┼──────────────────────────────────────────────┤
│ quota_warning       │ 配额使用达到警告阈值 (80%)                   │
│ quota_critical      │ 配额使用达到临界阈值 (90%)                   │
│ quota_exhausted     │ 配额耗尽                                     │
│ quota_reset         │ 配额重置                                     │
│ channel_disabled    │ 渠道自动禁用                                 │
│ channel_enabled     │ 渠道自动启用                                 │
│ plan_expired        │ 计划过期                                     │
│ plan_upgraded       │ 计划升级                                     │
└─────────────────────┴──────────────────────────────────────────────┘

通知渠道:
├─── Email: 邮件通知
├─── SMS: 短信通知
├─── Webhook: HTTP 回调
├─── WebSocket: 实时推送
├─── 站内信: 系统通知
└─── Slack/钉钉: 即时通讯
```

### 8.2 通知模板

```
┌─────────────────────────────────────────────────────────────────────┐
│                      通知模板设计                                    │
└─────────────────────────────────────────────────────────────────────┘

配额警告模板:
{
    "event": "quota_warning",
    "title": "AI 服务配额即将耗尽",
    "content": "您的 {{plan_name}} 计划配额已使用 {{usage_percentage}}%，\n剩余配额: {{remaining}}。\n请及时充值或升级计划。",
    "channels": ["email", "webhook"],
    "priority": "high"
}

配额耗尽模板:
{
    "event": "quota_exhausted",
    "title": "AI 服务配额已耗尽",
    "content": "您的 {{plan_name}} 计划配额已耗尽，\n渠道 {{channel_name}} 已自动禁用。\n请充值或等待下个计费周期。",
    "channels": ["email", "sms", "webhook"],
    "priority": "critical"
}

配额重置模板:
{
    "event": "quota_reset",
    "title": "AI 服务配额已重置",
    "content": "您的 {{plan_name}} 计划配额已重置，\n渠道 {{channel_name}} 已自动启用。\n新周期: {{period_start}} 至 {{period_end}}。",
    "channels": ["email", "webhook"],
    "priority": "normal"
}
```

---

## 九、定时任务

### 9.1 任务设计

```
┌─────────────────────────────────────────────────────────────────────┐
│                      定时任务设计                                    │
└─────────────────────────────────────────────────────────────────────┘

任务列表:
┌─────────────────────┬──────────────────────────────────────────────┐
│ 任务                │ 执行频率                                     │
├─────────────────────┼──────────────────────────────────────────────┤
│ SyncQuotaUsage      │ 每 5 分钟                                    │
│ CheckQuotaThreshold │ 每 1 分钟                                    │
│ ResetPeriodQuota    │ 每日 00:00                                   │
│ SyncPlanStatus      │ 每小时                                       │
│ CleanupUsageLogs    │ 每日 03:00                                   │
│ SendQuotaReport     │ 每周一 09:00                                 │
└─────────────────────┴──────────────────────────────────────────────┘

任务详情:

SyncQuotaUsage (同步配额使用):
├─── 批量推送本地使用量到 Coding
├─── 处理失败的重试队列
└─── 更新同步状态

CheckQuotaThreshold (检查配额阈值):
├─── 检查所有活跃计划的配额使用率
├─── 触发状态变更
├─── 发送通知
└─── 记录检查日志

ResetPeriodQuota (重置周期配额):
├─── 查找需要重置的计划
├─── 重置 quota_used
├─── 更新 period_start/period_end
├─── 自动启用被禁用的渠道
└─── 发送重置通知

SyncPlanStatus (同步计划状态):
├─── 从 Coding 拉取计划状态
├─── 更新本地计划信息
├─── 处理过期计划
└─── 处理升级/降级

CleanupUsageLogs (清理使用日志):
├─── 删除 90 天前的日志
├─── 归档到冷存储
└─── 更新统计表

SendQuotaReport (发送配额报告):
├─── 生成周报
├─── 发送给管理员
└─── 记录发送状态
```

### 9.2 Laravel 任务配置

```
┌─────────────────────────────────────────────────────────────────────┐
│                      Laravel 任务配置                                │
└─────────────────────────────────────────────────────────────────────┘

// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // 每 5 分钟同步配额
    $schedule->job(new SyncQuotaUsage)
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->onOneServer();
    
    // 每分钟检查阈值
    $schedule->job(new CheckQuotaThreshold)
        ->everyMinute()
        ->withoutOverlapping()
        ->onOneServer();
    
    // 每日重置配额
    $schedule->job(new ResetPeriodQuota)
        ->dailyAt('00:00')
        ->withoutOverlapping()
        ->onOneServer();
    
    // 每小时同步计划状态
    $schedule->job(new SyncPlanStatus)
        ->hourly()
        ->withoutOverlapping()
        ->onOneServer();
    
    // 每日清理日志
    $schedule->job(new CleanupUsageLogs)
        ->dailyAt('03:00')
        ->withoutOverlapping()
        ->onOneServer();
    
    // 每周发送报告
    $schedule->job(new SendQuotaReport)
        ->weekly()
        ->mondays()
        ->at('09:00')
        ->withoutOverlapping()
        ->onOneServer();
}
```

---

## 十、API 接口

### 10.1 管理接口

```
┌─────────────────────────────────────────────────────────────────────┐
│                      管理 API 接口                                   │
└─────────────────────────────────────────────────────────────────────┘

获取计费计划列表:
GET /api/v1/coding-plans
参数: tenant_id, status, type
响应:
{
    "data": [
        {
            "id": 1,
            "coding_plan_id": "plan_xxx",
            "name": "Pro Plan",
            "type": "pro",
            "status": "active",
            "quota_usage": {
                "requests": {"used": 8500, "limit": 10000, "percentage": 0.85},
                "tokens": {"used": 6000000, "limit": 7500000, "percentage": 0.80},
                "cost": {"used": 85.50, "limit": 100.00, "percentage": 0.855}
            },
            "period": {...}
        }
    ],
    "meta": {...}
}

获取计费计划详情:
GET /api/v1/coding-plans/{id}
响应包含: 基本信息、配额定义、使用情况、关联渠道

创建计费计划:
POST /api/v1/coding-plans
{
    "coding_plan_id": "plan_xxx",
    "tenant_id": 1,
    "name": "Pro Plan",
    "type": "pro",
    "quota_definition": {...},
    "billing_cycle": "monthly"
}

更新计费计划:
PUT /api/v1/coding-plans/{id}

关联渠道:
POST /api/v1/coding-plans/{plan_id}/channels
{
    "channel_id": 1,
    "priority": 1,
    "quota_override": null,
    "auto_disable": true,
    "auto_enable": true,
    "disable_threshold": 0.95
}

手动重置配额:
POST /api/v1/coding-plans/{id}/reset
{
    "reason": "用户请求重置"
}

手动同步:
POST /api/v1/coding-plans/{id}/sync
```

### 10.2 查询接口

```
┌─────────────────────────────────────────────────────────────────────┐
│                      查询 API 接口                                   │
└─────────────────────────────────────────────────────────────────────┘

获取配额使用情况:
GET /api/v1/coding-plans/{id}/usage
响应:
{
    "plan_id": 1,
    "period": {
        "start": "2024-01-01",
        "end": "2024-01-31"
    },
    "usage": {
        "requests": {"used": 8500, "remaining": 1500, "percentage": 0.85},
        "tokens": {"used": 6000000, "remaining": 1500000, "percentage": 0.80},
        "cost": {"used": 85.50, "remaining": 14.50, "percentage": 0.855}
    },
    "daily_usage": [
        {"date": "2024-01-01", "requests": 500, "tokens": 200000, "cost": 5.00},
        ...
    ],
    "model_breakdown": {
        "gpt-4": {"requests": 3000, "tokens": 3000000, "cost": 60.00},
        "gpt-3.5-turbo": {"requests": 5500, "tokens": 3000000, "cost": 25.50}
    }
}

获取渠道配额状态:
GET /api/v1/channels/{id}/quota-status
响应:
{
    "channel_id": 1,
    "plans": [
        {
            "plan_id": 1,
            "plan_name": "Pro Plan",
            "usage_percentage": 0.85,
            "status": "warning",
            "auto_disable": true,
            "threshold": 0.95
        }
    ],
    "channel_status": "active",
    "will_disable_at": null
}

获取配额使用历史:
GET /api/v1/coding-plans/{id}/history
参数: start_date, end_date, granularity (daily/hourly)
```

---

## 十一、监控与告警

### 11.1 监控指标

```
┌─────────────────────────────────────────────────────────────────────┐
│                      监控指标设计                                    │
└─────────────────────────────────────────────────────────────────────┘

配额指标:
cdapi_quota_usage_percentage{plan, type}
cdapi_quota_remaining{plan, type}
cdapi_quota_exhausted_total{plan}

渠道状态指标:
cdapi_channel_quota_status{channel, plan}
cdapi_channel_auto_disabled_total{channel, plan}
cdapi_channel_auto_enabled_total{channel, plan}

同步指标:
cdapi_quota_sync_success_total{plan}
cdapi_quota_sync_failure_total{plan}
cdapi_quota_sync_latency_seconds{plan}

通知指标:
cdapi_quota_notification_sent_total{plan, event, channel}
cdapi_quota_notification_failed_total{plan, event, channel}
```

### 11.2 告警规则

```
┌─────────────────────────────────────────────────────────────────────┐
│                      告警规则设计                                    │
└─────────────────────────────────────────────────────────────────────┘

配额即将耗尽:
ALERT QuotaNearExhaustion
  IF cdapi_quota_usage_percentage > 0.9
  FOR 1m
  LABELS { severity: "warning" }
  ANNOTATIONS {
    summary: "计划 {{ $labels.plan }} 配额即将耗尽"
  }

配额已耗尽:
ALERT QuotaExhausted
  IF cdapi_quota_remaining == 0
  FOR 1m
  LABELS { severity: "critical" }
  ANNOTATIONS {
    summary: "计划 {{ $labels.plan }} 配额已耗尽"
  }

同步失败:
ALERT QuotaSyncFailure
  IF rate(cdapi_quota_sync_failure_total[5m]) > 0
  FOR 5m
  LABELS { severity: "warning" }
  ANNOTATIONS {
    summary: "计划 {{ $labels.plan }} 配额同步失败"
  }

渠道自动禁用:
ALERT ChannelAutoDisabled
  IF cdapi_channel_quota_status == 0
  FOR 1m
  LABELS { severity: "warning" }
  ANNOTATIONS {
    summary: "渠道 {{ $labels.channel }} 因配额耗尽已自动禁用"
  }
```

---

## 十二、最佳实践

### 12.1 配置建议

```
┌─────────────────────────────────────────────────────────────────────┐
│                      配置最佳实践                                    │
└─────────────────────────────────────────────────────────────────────┘

阈值设置建议:
┌─────────────────────┬──────────────────────────────────────────────┐
│ 场景                │ 建议配置                                     │
├─────────────────────┼──────────────────────────────────────────────┤
│ 生产环境            │ warning: 0.80, throttle: 0.90, disable: 0.95│
│ 开发环境            │ warning: 0.90, throttle: 0.95, disable: 1.00│
│ 严格限制            │ warning: 0.70, throttle: 0.85, disable: 0.90│
│ 宽松限制            │ warning: 0.90, disable: 1.00, auto_disable: false │
└─────────────────────┴──────────────────────────────────────────────┘

多渠道配置:
├─── 主渠道: 高配额计划，正常使用
├─── 备用渠道: 中等配额，主渠道禁用时启用
├─── 降级渠道: 低成本模型，配额紧张时切换
└─── 兜底渠道: 免费计划，仅限紧急情况

通知配置:
├─── 警告通知: 仅 Email + Webhook
├─── 临界通知: Email + Webhook + 站内信
├─── 耗尽通知: Email + SMS + Webhook + 站内信
└─── 重置通知: Email + Webhook
```

### 12.2 运维建议

```
┌─────────────────────────────────────────────────────────────────────┐
│                      运维最佳实践                                    │
└─────────────────────────────────────────────────────────────────────┘

日常运维:
├─── 每日检查配额使用报告
├─── 监控自动禁用/启用事件
├─── 检查同步状态
└─── 处理用户反馈

容量规划:
├─── 根据历史数据预测用量
├─── 提前升级计划或增加配额
├─── 配置备用渠道
└─── 设置合理的阈值

故障处理:
├─── 同步失败: 检查网络和认证
├─── 误禁用: 手动启用并调整阈值
├─── 配额不准: 触发全量同步
└─── 通知失败: 检查通知渠道配置

安全建议:
├─── 定期轮换 API Key
├─── 限制 Webhook 来源 IP
├─── 加密存储敏感信息
└─── 审计所有操作日志
```
