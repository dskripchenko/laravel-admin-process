# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

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
