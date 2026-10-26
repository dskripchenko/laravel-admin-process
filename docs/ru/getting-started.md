---
title: Быстрый старт
locale: ru
status: stable
---

# Быстрый старт

## 1. Установка

```bash
composer require dskripchenko/laravel-admin-process
```

Пакет зависит от `dskripchenko/laravel-process-engine`; если движок в
приложении новый, опубликуйте его конфиг и выполните его миграции
(см. [руководство по установке движка](https://github.com/dskripchenko/laravel-process-engine/blob/main/docs/ru/installation.md)):

```bash
php artisan vendor:publish --tag=process-engine-config
php artisan migrate
```

Сервис-провайдер подключается автоматически и регистрирует плагин в
`config('admin.plugins')`.

## 2. Выдайте права

Ключи появляются в редакторе ролей панели в группе **Процессы**:

| Ключ | Для кого |
|---|---|
| `admin.process.instances.view` | все, кто следит за процессами |
| `admin.process.incidents.view` | поддержка |
| `admin.process.operate` | операторы: рутинные действия |
| `admin.process.manage` | старшие операторы: действия в обход логики процесса |

У суперадминистратора (`*`) есть все права.

## 3. Откройте панель

**Процессы → Экземпляры процессов** показывает все экземпляры. Клик по ID
открывает страницу экземпляра. На дашборде появляется карточка **Процессы**.

## 4. Необязательно: конфиг

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
