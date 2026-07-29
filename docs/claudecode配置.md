# Claude Code 配置指南

本文档记录项目的 Claude Code 配置，供团队成员参考。

## 目录结构

```
~/.claude/                          # 全局配置目录
├── CLAUDE.md                       # 全局指令（所有项目生效）
├── settings.json                   # 全局设置（模型、权限、hooks、插件）
├── mcp.json                        # 全局 MCP 服务器配置
├── keybindings.json                # 快捷键绑定（未配置）
├── agents/                         # 自定义 Agent 定义
│   ├── task-dev.md                 # 开发任务 Agent
│   ├── dev-experience.md           # 经验查询 Agent
│   ├── bug-analyzer.md             # Bug 分析 Agent
│   ├── code-reviewer.md            # 代码审查 Agent
│   ├── e2e-api-tester.md           # E2E API 测试 Agent
│   ├── page-analyzer.md            # 页面分析 Agent
│   ├── reviewer-kimi.md            # Kimi 审查 Agent
│   └── ...                         # 其他 Agent
├── skills/                         # 自定义 Skill 定义
│   ├── taskflow/                   # 开发任务工作流
│   ├── git-commit/                 # Git 提交 Skill
│   ├── git-review/                 # Git 审查 Skill
│   ├── proto-design/               # Protobuf 设计 Skill
│   └── ...                         # 其他 Skill
├── helper/                         # 辅助脚本
│   ├── SessionStart.js             # 会话启动 Hook
│   ├── statusline.js               # 状态栏脚本
│   ├── logger.js                   # 日志工具
│   └── hindsight/                  # Hindsight 记忆系统
└── projects/                       # 项目级配置
    └── -data-project-ai-proxy-coding-api/
        └── memory/                 # 项目记忆存储

项目根目录/
├── CLAUDE.md                       # 项目指令（仅本项目生效）
├── .mcp.json                       # 项目 MCP 服务器配置
├── .claude/
│   ├── settings.json               # 项目级设置（权限白名单）
│   ├── settings.local.json         # 本地设置（敏感信息、权限）
│   ├── plan/                       # 计划文件存储
│   └── skills/                     # 项目级 Skill
│       └── dcat-admin-i18n/        # Dcat Admin 国际化 Skill
```

## 全局设置 (`~/.claude/settings.json`)

### 环境变量

| 变量 | 值 | 说明 |
|------|-----|------|
| `DISABLE_TELEMETRY` | 1 | 禁用遥测 |
| `DISABLE_ERROR_REPORTING` | 1 | 禁用错误上报 |
| `CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS` | 1 | 启用 Agent 团队 |
| `CLAUDE_AUTOCOMPACT_PCT_OVERRIDE` | 90 | 自动压缩阈值 |
| `ENABLE_LSP_TOOL` | 1 | 启用 LSP 工具 |
| `MCP_TIMEOUT` | 60000 | MCP 超时 60s |
| `CLAUDE_CODE_EFFORT_LEVEL` | max | 最大推理力度 |
| `CLAUDE_CODE_MAX_SUBAGENTS_PER_SESSION` | 500 | 最大子 Agent 数 |

### 权限模式

- `defaultMode`: `bypassPermissions` — 跳过权限确认

### Hooks

| 事件 | 脚本 | 说明 |
|------|------|------|
| SessionStart | `SessionStart.js` | 会话初始化 |
| Stop | `hindsight/retain.js` | 会话结束时保存记忆 |
| SessionEnd | `hindsight/session_end.js` | 会话结束清理 |

### 启用的插件

| 插件 | 状态 |
|------|------|
| typescript-lsp | 启用 |
| php-lsp | 启用 |
| skill-creator | 启用 |
| ralph-loop | 启用 |
| rust-analyzer-lsp | 启用 |
| hindsight-memory | 禁用 |

### 其他设置

- **模型**: `sonnet`
- **语言**: 中文
- **主题**: `light-daltonized`
- **自动记忆**: 启用
- **文件检查点**: 启用

## 项目设置 (`.claude/settings.local.json`)

### MCP 服务器

| 名称 | 类型 | 说明 |
|------|------|------|
| laravel-boost | stdio | Laravel 数据库/文档/调试 |
| dbhub | http | 数据库管理 |
| cdapi | http | CdApi MCP 接口 |

### 权限白名单

已授权的操作（部分重要项）：

- `mcp__laravel-boost__*` — Laravel Boost 全部工具
- `mcp__playwright__browser_*` — Playwright 浏览器操作
- `Bash(vendor/bin/pint*)` — 代码格式化
- `Bash(composer *)` — Composer 操作
- `Bash(git add/commit/push)` — Git 操作
- `WebSearch` — 网络搜索
- `mcp__BailianSearch__*` — 百炼搜索

### 权限黑名单

- `mcp__playwright__browser_install` — 禁止安装浏览器

### 附加目录

- `laravel/app/Admin/Extensions`
- `.claude`
- `laravel/app/Services/Shared`
- `.claude/plan`

## 全局指令 (`~/.claude/CLAUDE.md`)

核心工作规则：

1. 始终使用中文沟通
2. 加载合适的 Skill 处理问题
3. 使用 `Explore` Agent (Haiku) 收集信息
4. 使用 `Plan` Agent 规划方案
5. 使用 `task-dev` Agent 完成开发
6. 使用 `dev-experience` Agent 查询经验
7. 并行 Agent 同时最多 2 个
8. 使用 BailianSearch 网络搜索
9. 维护 memory 记录长期指示
10. 严格遵守工作目录边界
11. 大篇幅内容创建文档

## 项目指令 (`CLAUDE.md`)

项目级指令包含：技术栈、目录结构、核心架构、开发规范、常用命令等。详见项目根目录 `CLAUDE.md`。

## 自定义 Agent

| Agent | 用途 | 模型 |
|-------|------|------|
| task-dev | 开发任务执行 | 默认 |
| dev-experience | 经验查询 | Haiku |
| Explore | 代码搜索 | Haiku |
| Plan | 方案规划 | 默认 |
| bug-analyzer | Bug 分析 | 默认 |
| code-reviewer | 代码审查 | 默认 |
| e2e-api-tester | API 测试 | 默认 |
| page-analyzer | 页面分析 | 默认 |
| reviewer-kimi | Kimi 审查 | 默认 |
| task-executor-lite | 简单任务 | 默认 |
| task-executor-glm | GLM 模型执行 | GLM |
| task-executor-kimi | Kimi 模型执行 | Kimi |
| task-executor-qwen | Qwen 模型执行 | Qwen |

## 自定义 Skill

### 全局 Skill (`~/.claude/skills/`)

| Skill | 触发方式 | 说明 |
|-------|----------|------|
| taskflow | `/taskflow` | 八阶段开发工作流 |
| taskflow-auto | `/taskflow-auto` | 自动化开发工作流 |
| git-commit | `/git-commit` | 规范化 Git 提交 |
| git-review | `/git-review` | 代码审查 |
| proto-design | `/proto-design` | Protobuf 设计 |
| plan-review | `/plan-review` | 方案审查 |
| e2e-test-case-writer | `/e2e-test-case-writer` | E2E 测试用例 |
| writing-docs | `/writing-docs` | 文档编写 |
| design-database | `/design-database` | 数据库设计 |
| mcp-manager | `/mcp-manager` | MCP 管理 |
| port-manager | `/port-manager` | 端口管理 |
| page-replicate | `/page-replicate` | 页面复制 |
| team-creator | `/team-creator` | 团队创建 |
| grilling | `/grilling` | 代码质询 |
| alipay-* | `/alipay-*` | 支付宝相关 |

### 项目 Skill (`.claude/skills/`)

| Skill | 说明 |
|-------|------|
| dcat-admin-i18n | Dcat Admin 国际化处理 |

## 记忆系统

### 文件记忆 (`~/.claude/projects/.../memory/`)

- `MEMORY.md` — 记忆索引
- 各 `.md` 文件 — 分类记忆（user/feedback/project/reference）

### Hindsight 记忆

- 通过 `hindsight` MCP 服务器管理
- 会话结束时自动 `retain`
- 支持 `recall`、`reflect`、`mental_model` 等操作

## 常用操作

### 查看配置

```bash
# 全局设置
cat ~/.claude/settings.json

# 项目设置
cat .claude/settings.local.json

# MCP 配置
cat .mcp.json
```

### 修改权限

在 `.claude/settings.local.json` 的 `permissions.allow` 数组中添加规则。

### 添加 Agent

在 `~/.claude/agents/` 目录创建 `.md` 文件，包含 frontmatter 配置。

### 添加 Skill

在 `~/.claude/skills/` 目录创建 Skill 目录和配置文件。
