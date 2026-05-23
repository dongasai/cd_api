# 安装腾讯 CodeBuddy Code CLI 工具

## 时间
- 日期: 2026-05-23
- 时间: 14:47

## 工具信息
- 工具名称: @tencent-ai/codebuddy-code
- 版本: 2.97.5
- 可执行命令: `cbc` 和 `codebuddy`

## 安装过程

### 遇到的问题
1. **权限问题**: npm 全局安装路径 `/usr` 需要 root 权限
2. **解决方案**: 配置 npm 使用用户目录进行全局安装

### 安装步骤
1. 创建用户级全局目录: `mkdir -p ~/.npm-global`
2. 配置 npm prefix: `npm config set prefix ~/.npm-global`
3. 更新 PATH: `export PATH=~/.npm-global/bin:$PATH` (写入 ~/.bashrc)
4. 安装工具: `npm install -g @tencent-ai/codebuddy-code`

## 安装结果
- ✅ 安装成功
- 安装路径: `~/.npm-global/bin/`
- 版本: 2.97.5
- 可执行文件:
  - `cbc` (简写命令)
  - `codebuddy` (完整命令)

## 使用说明
- **当前会话**: 需使用完整路径 `~/.npm-global/bin/cbc`
- **新会话**: 可直接使用 `cbc` 命令(PATH 已配置)

## 工具特性
- AI 命令行编程工具
- 支持自然语言编程、代码重构、测试验证
- 支持 Plan模式、ACP协议、Skills功能
- 支持多种模型(GLM-4.7、GPT 5.2 Codex 等)
- 兼容 Claude Code 技能包标准