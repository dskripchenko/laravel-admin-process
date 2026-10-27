# dskripchenko/laravel-admin-process

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · [Deutsch](../de/README.md) · **中文**

管理面板的流程页面：展示
[`dskripchenko/process-engine`](https://github.com/dskripchenko/process-engine)
的实例——每个实例在实时流程图上的位置、它在等待什么、它的上下文、
effect、incident 和日志——以及运维操作，每个操作都受权限控制，并连同原因一起记录。

[`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin) 的 sister-pack。

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-process)](https://packagist.org/packages/dskripchenko/laravel-admin-process)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-process)](../../LICENSE)

## 安装

```bash
composer require dskripchenko/laravel-admin-process
php artisan migrate   # the engine's tables, if not migrated yet
```

Provider 会自动注册。把权限授予需要它们的角色——这就是全部配置。

## 提供的功能

| 位置 | 内容 |
|---|---|
| **Processes → Process instances** | 所有实例：流程、版本、状态、当前状态节点、业务键、incident；可按流程、状态、键、日期筛选；实时刷新。行操作：暂停、恢复、取消。 |
| **Processes → Process incidents** | 需要人工处理的事项，未关闭的排在前面；填写原因后关闭。 |
| **实例页面**（`/screens/process-instance?id=…`） | 标出实例位置的流程图，实时轮询，可深入复合状态和子流程；实例在等待什么以及它的定时器；上下文；effect；incident 和滞留事件；日志。 |
| **仪表盘** | 运行中和已暂停的实例、未关闭的 incident、逾期定时器、滞留 effect。 |

## 权限

| 键 | 允许 |
|---|---|
| `admin.process.instances.view` | 实例列表和实例页面、仪表盘卡片 |
| `admin.process.incidents.view` | incident 列表 |
| `admin.process.operate` | 暂停、恢复、取消、重试失败的 effect、关闭 incident、重新投递事件 |
| `admin.process.manage` | 转移到某个状态、编辑上下文、设定或跳过 effect、重试结果未知的 effect |
| `admin.process.switches` | 在流程图上关闭和开启流水线的阶段 |
| `admin.process.start` | 手动启动 `admin-process.startable` 中列出的流程 |

每个运维操作都要求填写原因；原因和操作的管理员会写入实例日志。

## 文档

- [快速开始](getting-started.md)
- [使用](usage.md)

## 要求

PHP 8.2+、Laravel 11 / 12 / 13、`dskripchenko/laravel-admin` ^1.59、
`dskripchenko/laravel-process-engine` ^1.0。

## 许可证

MIT
