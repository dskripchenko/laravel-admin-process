---
title: 使用
locale: zh
status: stable
---

# 使用

## 实例列表

列：ID（打开实例页面）、流程、版本、状态、当前状态节点、业务键、是否有未关闭的
incident、创建时间和更新时间。当前状态节点是活动的叶子状态，或已结束实例的终止状态。

筛选：流程、状态、业务键、"With incidents"、创建时间区间。
列表每隔 `refresh.list` 秒自动刷新。

行操作——**Suspend**、**Resume**、**Cancel**——需要 `admin.process.operate`
权限并填写原因。Cancel 可以先对已完成的步骤进行补偿。

## Incident 列表

未关闭的 incident 排在前面，其余按从新到旧排列。实例列会打开实例页面，
问题的原因在那里处理——incident 只记录有事项需要人工介入。
**Resolve incident**（需要 `admin.process.operate`）填写处理结果和原因后将其关闭。

## 实例页面

`/admin/screens/process-instance?id=<uuid>`。

顶部摘要：状态、当前状态节点及其标签、业务键、主体对象、父流程、时间戳，
以及最近一次推动实例前进的步骤。

标签页：

| 标签页 | 内容 |
|---|---|
| **Diagram** | 标出实例位置的流程图：已经过的状态、当前状态、已走过的转换、问题（结果未知的 effect、未关闭的 incident）。每隔 `refresh.flow` 秒轮询一次。复合状态和子流程可就地展开。 |
| **Waiting for** | 实例当前接受的事件（附目标状态，以及是否由 guard 决定）和它的定时器。 |
| **Context** | 以 JSON 形式显示的流程数据。 |
| **Effects** | 所有 effect，按从新到旧排列：状态、语义、尝试次数、下次尝试时间、错误。 |
| **Incidents** | incident，以及正在等待或已被拒绝的事件。 |
| **Journal** | 最新的日志条目，按从新到旧排列：发生了什么、谁、为什么。 |

## 流程及其流程图

**Processes** 列出每个流程的最新版本，以及正在运行、因 incident 而等待和最近一天内
失败的实例数。点击一行会打开流程页面 `/admin/screens/process-definition?name=<process>`：

- **Diagram**——整个流程，以及每个状态中的实例数。可关闭的阶段在其节点上带有开关：
  关闭它（需要 `admin.process.switches` 权限）会在所有地方关闭该阶段，停留在其中的
  实例会离开它。
- **Stages**——所有可关闭的阶段：开启或关闭、原因、操作者和时间；自动关闭时显示错误
  消息，并以系统作为操作者。
- **Instances**——最新的二十个实例，每个都链接到其页面。
- **Start**（需要 `admin.process.start` 权限）——用于 `admin-process.startable` 中
  列出的流程：以 JSON 形式填写启动上下文，并选择本次运行要关闭的阶段。随后会打开新
  实例的页面。

```php
// config/admin-process.php
'startable' => [
    OsmHazards::class,                         // starts with an empty context
    GarUpdate::class => ['regions' => []],     // the form opens with this context
],
```

## 实例页面上的批次

**Batches** 标签页列出实例的批次阶段——状态、`finished / total` 形式的元素数及各项
计数、错误——以及失败和延后的元素及其原因和尝试次数。流程图在正在运行的批次的节点上
显示其进度。两个运维操作（需要 `admin.process.operate` 权限，并填写原因）：
**Retry batch elements**（正在运行的批次中失败、延后或未找到的元素）和
**Skip the rest of the batch**。

## 运维操作

页面的命令栏。每个操作都要求填写原因，原因与管理员一起写入日志。

| 操作 | 权限 | 引擎调用 |
|---|---|---|
| Suspend / Resume | operate | `suspend` / `resume`——事件等待，effect 不启动 |
| Cancel | operate | `cancel`，可选择先进行补偿 |
| Retry effect | operate | `retryEffect`，用于失败、已取消或结果未知的 effect |
| ……勾选 "I take responsibility" | **manage** | `retryEffect(confirmUnsafe: true)`——结果未知的 at-most-once effect |
| Resolve incident | operate | `resolveIncident` |
| Redeliver | operate | 对被拒绝的事件执行 `redeliver` |
| Move to state | manage | `transitionTo`，可选择是否执行退出和进入动作 |
| Edit context | manage | `patchContext`——JSON 对象 `{"key": value}` 设置顶层键；JSON Patch 数组（RFC 6902）可完成其他任何修改 |
| Settle an effect | manage | `resolveEffect`——以某个结果标记为成功，或以某个错误码标记为失败 |
| Skip effect | manage | `skipEffect`——按成功处理并继续 |
| Retry batch elements | operate | `retryBatchItems`——把正在运行的批次中失败、延后或未找到的元素放回其队列 |
| Skip the rest of the batch | operate | `skipBatch`——跳过仍在等待的部分，状态继续推进 |

在流程图上，选中状态的抽屉面板中有 **Move to this state**（manage），需要确认。

引擎会把手动操作与实例的其他事件按顺序处理。使用同步队列时，面板返回响应时操作已经执行完毕，
因此拒绝（effect 并未失败、实例已结束）会显示为错误。使用真正的队列时，面板报告操作已被接受，
结果会出现在日志中。

## 安全

页面的方法绑定到带权限的操作上，因此即使通过 API 直接调用，面板也会拒绝没有相应权限的用户
执行运维方法。本包的测试会检查页面的每个运维方法对只读用户都被拒绝。

## 自定义

仪表盘卡片是普通的 widget：当你把它放到自己的仪表盘上时，可以用
`ProcessOverviewWidget::make()->permission(...)` 限制谁能看到它。
刷新间隔和日志条数在配置中设置。
