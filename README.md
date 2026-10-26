# dskripchenko/laravel-admin-process

> 🌐 **English** · [Русский](docs/ru/README.md) · [Deutsch](docs/de/README.md) · [中文](docs/zh/README.md)

Process screens for the admin panel: the instances of
[`dskripchenko/process-engine`](https://github.com/dskripchenko/process-engine),
where each one stands on a live diagram, what it waits for, its context,
effects, incidents and journal — and the operator actions, each gated by a
permission and recorded with a reason.

A sister-pack for [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-process)](https://packagist.org/packages/dskripchenko/laravel-admin-process)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-process)](LICENSE)

## Install

```bash
composer require dskripchenko/laravel-admin-process
php artisan migrate   # the engine's tables, if not migrated yet
```

The provider registers itself. Grant the permissions to the roles that need
them — that is all the setup there is.

## What you get

| Where | What |
|---|---|
| **Processes → Process instances** | every instance: process, version, status, current state, business key, incidents; filters by process, status, key, date; live refresh. Row actions: suspend, resume, cancel. |
| **Processes → Process incidents** | what needs a person, open first; resolve with a reason. |
| **Instance page** (`/screens/process-instance?id=…`) | the diagram with the instance's position, polled live, with drill-down into compound states and child processes; what the instance waits for and its timers; the context; effects; incidents and stuck events; the journal. |
| **Dashboard** | running and suspended instances, open incidents, overdue timers, stuck effects. |

## Permissions

| Key | Allows |
|---|---|
| `admin.process.instances.view` | the instance list and page, the dashboard card |
| `admin.process.incidents.view` | the incident list |
| `admin.process.operate` | suspend, resume, cancel, retry a failed effect, resolve an incident, redeliver an event |
| `admin.process.manage` | move to a state, edit the context, settle or skip an effect, retry an effect whose outcome is unknown |

Every operator action asks for a reason; the reason and the admin go into
the instance's journal.

## Documentation

- [Getting started](docs/en/getting-started.md)
- [Usage](docs/en/usage.md)

## Requirements

PHP 8.2+, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.59,
`dskripchenko/laravel-process-engine` ^1.0.

## License

MIT
