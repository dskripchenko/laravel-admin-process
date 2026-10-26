---
title: 快速开始
locale: zh
status: stable
---

# 快速开始

## 1. 安装

```bash
composer require dskripchenko/laravel-admin-process
```

本包依赖 `dskripchenko/laravel-process-engine`；如果应用中尚未使用该引擎，
请发布它的配置并运行它的迁移（参见[引擎的安装指南](https://github.com/dskripchenko/laravel-process-engine/blob/main/docs/zh/installation.md)）：

```bash
php artisan vendor:publish --tag=process-engine-config
php artisan migrate
```

Service provider 会被自动发现，并在 `config('admin.plugins')` 中注册插件。

## 2. 授予权限

这些键出现在面板角色编辑器的 **Processes** 分组下：

| 键 | 适用对象 |
|---|---|
| `admin.process.instances.view` | 所有关注流程的人 |
| `admin.process.incidents.view` | 支持人员 |
| `admin.process.operate` | 运维人员：日常操作 |
| `admin.process.manage` | 高级运维人员：绕过流程逻辑的操作 |

超级管理员（`*`）拥有全部权限。

## 3. 打开面板

**Processes → Process instances** 列出所有实例。点击 ID 打开实例页面。
仪表盘上会出现 **Processes** 卡片。

## 4. 可选：配置

```bash
php artisan vendor:publish --tag=admin-process-config
```

```php
// config/admin-process.php
return [
    'refresh' => [
        'list' => 15,    // seconds; 0 turns the lists' auto-refresh off
        'flow' => 5,     // the diagram's poll, at least 1
        'widget' => 60,  // the dashboard card
    ],
    'journal_limit' => 200, // latest journal entries on the page
];
```
