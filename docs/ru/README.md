# dskripchenko/laravel-admin-process

> 🌐 [English](../../README.md) · **Русский** · [Deutsch](../de/README.md) · [中文](../zh/README.md)

Экраны процессов для админ-панели: экземпляры
[`dskripchenko/process-engine`](https://github.com/dskripchenko/process-engine) —
где каждый из них находится на живой схеме, чего он ждёт, его контекст,
эффекты, инциденты и журнал, — и действия оператора, каждое из которых
закрыто правом и записывается с причиной.

Sister-pack для [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-process)](https://packagist.org/packages/dskripchenko/laravel-admin-process)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-process)](../../LICENSE)

## Установка

```bash
composer require dskripchenko/laravel-admin-process
php artisan migrate   # the engine's tables, if not migrated yet
```

Провайдер регистрируется сам. Выдайте права ролям, которым они нужны, —
больше настраивать нечего.

## Что вы получаете

| Где | Что |
|---|---|
| **Процессы → Экземпляры процессов** | все экземпляры: процесс, версия, статус, текущее состояние, бизнес-ключ, инциденты; фильтры по процессу, статусу, ключу, дате; автообновление. Действия в строке: приостановить, возобновить, отменить. |
| **Процессы → Инциденты процессов** | то, что требует участия человека, открытые первыми; закрытие с причиной. |
| **Страница экземпляра** (`/screens/process-instance?id=…`) | схема с позицией экземпляра, обновляемая опросом, с переходом внутрь составных состояний и дочерних процессов; чего экземпляр ждёт и его таймеры; контекст; эффекты; инциденты и зависшие события; журнал. |
| **Дашборд** | выполняющиеся и приостановленные экземпляры, открытые инциденты, просроченные таймеры, зависшие эффекты. |

## Права

| Ключ | Разрешает |
|---|---|
| `admin.process.instances.view` | список и страницу экземпляров, карточку на дашборде |
| `admin.process.incidents.view` | список инцидентов |
| `admin.process.operate` | приостановить, возобновить, отменить, повторить упавший эффект, закрыть инцидент, доставить событие повторно |
| `admin.process.manage` | перевести в состояние, изменить контекст, задать исход эффекта или пропустить его, повторить эффект с неизвестным исходом |
| `admin.process.switches` | выключать и включать этапы конвейера со схемы процесса |
| `admin.process.start` | вручную запускать процессы, перечисленные в `admin-process.startable` |

Каждое действие оператора требует причину; причина и администратор
попадают в журнал экземпляра.

## Документация

- [Быстрый старт](getting-started.md)
- [Использование](usage.md)

## Требования

PHP 8.2+, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.59,
`dskripchenko/laravel-process-engine` ^1.0.

## Лицензия

MIT
