# 项目概述

一个Ai大模型Api网关工具,名字'CdApi'

- laravel 12框架,位于laravel
- docs目录,存放设计文档/规划文档
- 不允许使用cdn资源
- 不使用vite进行资源构建
- 网址 http://192.168.4.107:32126
- work目录存在工作日志

## 名词释义

1. 渠道亲和性:指来自指定来源的请求匹配同一渠道

## 工作注意

- 完成一项工作后，在work目录创建工作记录，`work/{年月}/{日}-{时分}-{主题}.md`文件
- 使用 playwright-laravel Agent进行浏览器测试
- 使用dbhub mcp操作数据库
- cdapi这个mcp是本站的mcp