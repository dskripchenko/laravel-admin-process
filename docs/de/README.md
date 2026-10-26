# dskripchenko/laravel-admin-process

> 🌐 [English](../../README.md) · [Русский](../ru/README.md) · **Deutsch** · [中文](../zh/README.md)

Prozess-Screens für das Admin-Panel: die Instanzen von
[`dskripchenko/process-engine`](https://github.com/dskripchenko/process-engine) –
wo jede auf einem Live-Diagramm steht, worauf sie wartet, ihr Kontext,
ihre Effekte, Incidents und ihr Journal – und die Operator-Aktionen, jede
durch eine Berechtigung geschützt und mit einer Begründung protokolliert.

Ein Sister-Pack für [`dskripchenko/laravel-admin`](https://github.com/dskripchenko/laravel-admin).

[![Packagist](https://img.shields.io/packagist/v/dskripchenko/laravel-admin-process)](https://packagist.org/packages/dskripchenko/laravel-admin-process)
[![License](https://img.shields.io/packagist/l/dskripchenko/laravel-admin-process)](../../LICENSE)

## Installation

```bash
composer require dskripchenko/laravel-admin-process
php artisan migrate   # the engine's tables, if not migrated yet
```

Der Provider registriert sich selbst. Vergeben Sie die Berechtigungen an
die Rollen, die sie brauchen – mehr Einrichtung gibt es nicht.

## Was Sie bekommen

| Wo | Was |
|---|---|
| **Processes → Process instances** | alle Instanzen: Prozess, Version, Status, aktueller Zustand, Business Key, Incidents; Filter nach Prozess, Status, Key, Datum; Live-Aktualisierung. Zeilenaktionen: Anhalten, Fortsetzen, Abbrechen. |
| **Processes → Process incidents** | was eine Person braucht, offene zuerst; Auflösen mit Begründung. |
| **Instanzseite** (`/screens/process-instance?id=…`) | das Diagramm mit der Position der Instanz, live per Polling, mit Drill-down in zusammengesetzte Zustände und Kindprozesse; worauf die Instanz wartet und ihre Timer; der Kontext; Effekte; Incidents und hängende Events; das Journal. |
| **Dashboard** | laufende und angehaltene Instanzen, offene Incidents, überfällige Timer, hängende Effekte. |

## Berechtigungen

| Key | Erlaubt |
|---|---|
| `admin.process.instances.view` | Instanzliste und -seite, die Dashboard-Karte |
| `admin.process.incidents.view` | die Incident-Liste |
| `admin.process.operate` | anhalten, fortsetzen, abbrechen, einen fehlgeschlagenen Effekt wiederholen, einen Incident auflösen, ein Event erneut zustellen |
| `admin.process.manage` | in einen Zustand versetzen, den Kontext bearbeiten, einen Effekt abschließen oder überspringen, einen Effekt mit unbekanntem Ausgang wiederholen |

Jede Operator-Aktion verlangt eine Begründung; Begründung und Admin
landen im Journal der Instanz.

## Dokumentation

- [Erste Schritte](getting-started.md)
- [Verwendung](usage.md)

## Anforderungen

PHP 8.2+, Laravel 11 / 12 / 13, `dskripchenko/laravel-admin` ^1.59,
`dskripchenko/laravel-process-engine` ^1.0.

## Lizenz

MIT
