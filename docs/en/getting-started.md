---
title: Getting started
locale: en
status: stable
---

# Getting started

## 1. Install

```bash
composer require dskripchenko/laravel-admin-process
```

The pack depends on `dskripchenko/laravel-process-engine`; if the engine is
new to the application, publish its config and run its migrations
(see [the engine's installation guide](https://github.com/dskripchenko/laravel-process-engine/blob/main/docs/en/installation.md)):

```bash
php artisan vendor:publish --tag=process-engine-config
php artisan migrate
```

The service provider is discovered automatically and registers the plugin in
`config('admin.plugins')`.

## 2. Grant the permissions

The keys appear in the panel's role editor under **Processes**:

| Key | For |
|---|---|
| `admin.process.instances.view` | everyone who follows processes |
| `admin.process.incidents.view` | support |
| `admin.process.operate` | operators: routine actions |
| `admin.process.manage` | senior operators: actions that bypass the process logic |

A super admin (`*`) has them all.

## 3. Open the panel

**Processes → Process instances** lists every instance. A click on the ID
opens the instance page. The dashboard gets a **Processes** card.

## 4. Optional: the config

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
