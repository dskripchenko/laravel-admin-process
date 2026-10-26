# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).


## [Unreleased]

### Fixed
- **The instance page took another screen's `?id=1` for an instance id.** The
  panel describes every screen while it builds its manifest, with the query
  string of the page that is open; the id reached PostgreSQL's uuid column,
  which rejected it — and inside a host's request transaction that aborted
  every later query. Only UUIDs are taken for an instance id now.

## [1.0.0] - 2026-10-26

### Added

- The instance list with filters by process, status, business key,
  incidents and date, live refresh, and suspend / resume / cancel row actions.
- The incident list, open incidents first, with a resolve action.
- The instance page: summary, the diagram (`Layout::flow`) polled live with
  drill-down into compound states and child processes, expected events and
  timers, context, effects, incidents and stuck events, the journal.
- Operator actions on the page: suspend, resume, cancel (optionally with
  compensation), retry an effect, resolve an incident, redeliver an event,
  move to a state (also from the diagram's drawer), edit the context (an
  object or a JSON Patch), settle or skip an effect.
- Permissions `admin.process.instances.view`, `admin.process.incidents.view`,
  `admin.process.operate`, `admin.process.manage`.
- A dashboard card with the engine's health.
- Russian source strings with an English translation.

[1.0.0]: https://github.com/dskripchenko/laravel-admin-process/releases/tag/v1.0.0
