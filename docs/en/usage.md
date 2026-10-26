---
title: Usage
locale: en
status: stable
---

# Usage

## The instance list

Columns: ID (opens the instance page), process, version, status, current
state, business key, whether there are open incidents, created and updated.
The current state is the active leaf states, or the final state of a finished
instance.

Filters: process, status, business key, "with incidents", created between.
The list refreshes itself every `refresh.list` seconds.

Row actions — **Suspend**, **Resume**, **Cancel** — need
`admin.process.operate` and a reason. Cancel can compensate the completed
steps first.

## The incident list

Open incidents first, then the newest. The instance column opens the
instance page, where the cause is settled — an incident only records that
something needs a person. **Resolve incident** (with `admin.process.operate`)
closes it with a resolution and a reason.

## The instance page

`/admin/screens/process-instance?id=<uuid>`.

The summary on top: status, current state and its label, business key,
subject, parent, timestamps, and the last step that moved the instance.

Tabs:

| Tab | Content |
|---|---|
| **Diagram** | the process graph with the instance's position: visited states, the current ones, traversed transitions, problems (an effect with an unknown outcome, an open incident). Polled every `refresh.flow` seconds. Compound states and child processes open in place. |
| **Waiting for** | the events the instance accepts now (with the target state, and whether a guard decides), and its timers. |
| **Context** | the process data as JSON. |
| **Effects** | every effect, newest first: status, semantics, attempt, next attempt, error. |
| **Incidents** | incidents and the events that wait or were rejected. |
| **Journal** | the latest entries, newest first: what happened, who, why. |

## Operator actions

The page's command bar. Each asks for a reason, which goes into the journal
together with the admin.

| Action | Permission | Engine call |
|---|---|---|
| Suspend / Resume | operate | `suspend` / `resume` — events wait, effects do not start |
| Cancel | operate | `cancel`, optionally compensating first |
| Retry effect | operate | `retryEffect` for a failed, cancelled or unknown effect |
| … with "I take responsibility" | **manage** | `retryEffect(confirmUnsafe: true)` — an at-most-once effect whose outcome is unknown |
| Resolve incident | operate | `resolveIncident` |
| Redeliver | operate | `redeliver` a rejected event |
| Move to state | manage | `transitionTo`, with or without the exit and entry actions |
| Edit context | manage | `patchContext` — a JSON object `{"key": value}` sets top-level keys; a JSON Patch array (RFC 6902) does anything else |
| Settle an effect | manage | `resolveEffect` — succeeded with a result, or failed with a code |
| Skip effect | manage | `skipEffect` — continue as if it succeeded |

On the diagram, the drawer of a selected state has **Move to this state**
(manage), with a confirmation.

The engine applies a manual operation in order with the instance's other
events. With a synchronous queue it has run by the time the panel answers,
so a refusal (the effect is not failed, the instance is finished) shows as
an error. With a real queue the panel reports the operation as accepted,
and the outcome appears in the journal.

## Security

The page's methods are bound to actions with permissions, so the panel
refuses an operator method to a user without its permission even when it is
called directly through the API. The pack's tests check that every
operator method of the page is refused to a viewer.

## Customizing

The dashboard card is a regular widget: narrow who sees it with
`ProcessOverviewWidget::make()->permission(...)` when you place it on a
dashboard of your own. The refresh intervals and the journal length are in
the config.
