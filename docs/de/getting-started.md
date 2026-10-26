---
title: Erste Schritte
locale: de
status: stable
---

# Erste Schritte

## 1. Installation

```bash
composer require dskripchenko/laravel-admin-process
```

Das Pack hängt von `dskripchenko/laravel-process-engine` ab; ist die Engine
neu in der Anwendung, veröffentlichen Sie ihre Konfiguration und führen
Sie ihre Migrationen aus (siehe die [Installationsanleitung der Engine](https://github.com/dskripchenko/laravel-process-engine/blob/main/docs/de/installation.md)):

```bash
php artisan vendor:publish --tag=process-engine-config
php artisan migrate
```

Der Service-Provider wird automatisch erkannt und registriert das Plugin
in `config('admin.plugins')`.

## 2. Berechtigungen vergeben

Die Keys erscheinen im Rolleneditor des Panels unter **Processes**:

| Key | Für |
|---|---|
| `admin.process.instances.view` | alle, die Prozesse verfolgen |
| `admin.process.incidents.view` | Support |
| `admin.process.operate` | Operatoren: Routineaktionen |
| `admin.process.manage` | erfahrene Operatoren: Aktionen, die die Prozesslogik umgehen |

Ein Super-Admin (`*`) hat alle.

## 3. Panel öffnen

**Processes → Process instances** listet alle Instanzen. Ein Klick auf die
ID öffnet die Instanzseite. Das Dashboard bekommt eine Karte **Processes**.

## 4. Optional: die Konfiguration

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
