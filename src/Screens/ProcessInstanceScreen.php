<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Screens;

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Code;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Infolist\BadgeEntry;
use Dskripchenko\LaravelAdmin\Infolist\RepeatableEntry;
use Dskripchenko\LaravelAdmin\Infolist\TextEntry;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Permission\PermissionCheck;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Support\Labels;
use Dskripchenko\LaravelAdminProcess\Support\Operator;
use Dskripchenko\ProcessEngine\Actor;
use Dskripchenko\ProcessEngine\Definition\Definition;
use Dskripchenko\ProcessEngine\Definition\StateType;
use Dskripchenko\ProcessEngine\Engine;
use Dskripchenko\ProcessEngine\Export\FlowExporter;
use Dskripchenko\ProcessEngine\Query\InstanceView;
use Dskripchenko\ProcessEngine\Store\BatchRecord;
use Dskripchenko\ProcessEngine\Store\EffectRecord;
use Dskripchenko\ProcessEngine\Store\InboxRecord;
use Dskripchenko\ProcessEngine\Store\IncidentRecord;
use Dskripchenko\ProcessEngine\Store\JournalRecord;

/**
 * One process instance: where it stands on the diagram (polled live), what
 * it waits for, its context, effects, incidents, timers and journal, and
 * the operator actions.
 *
 * The page is addressed as `/screens/process-instance?id=<uuid>`. Every
 * operator method is bound to an action with a permission, so the panel
 * refuses it to a user without that permission (a screen method no action
 * names would be open to anyone who sees the page).
 */
class ProcessInstanceScreen extends Screen
{
    /** @var array<string, InstanceView|null> */
    private array $views = [];

    public static function slug(): string
    {
        return 'process-instance';
    }

    public function name(): string
    {
        $view = $this->view();

        return $view === null ? __('Экземпляр процесса') : ($view->definition()->root->label() ?? $view->process()).' · '.($view->businessKey() ?? substr($view->id(), 0, 8));
    }

    public function description(): ?string
    {
        $view = $this->view();

        return $view === null ? null : __('Версия :version, экземпляр :id', ['version' => $view->version(), 'id' => $view->id()]);
    }

    public function permission(): string
    {
        return Permissions::VIEW;
    }

    public function query(mixed ...$params): array
    {
        $view = $this->view();
        if ($view === null) {
            return ['instance_id' => null, 'found' => false];
        }
        $leaves = $view->isFinished() ? array_filter([$view->final()]) : $view->leaves();
        $label = $view->label();
        $why = $view->why();

        return self::flatten([
            'instance_id' => $view->id(),
            'found' => true,
            'summary' => [
                'process' => $view->process(),
                'version' => $view->version(),
                'status' => $view->status(),
                'states' => implode(', ', $leaves),
                'label' => is_array($label) ? implode(', ', $label) : $label,
                'business_key' => $view->businessKey(),
                'subject' => implode(' #', array_filter($view->subject(), fn ($v) => $v !== null && $v !== '')),
                'parent' => $view->parentId(),
                'archived' => $view->isArchived(),
                'created_at' => $view->record()->createdAt?->format(DATE_ATOM),
                'updated_at' => $view->record()->updatedAt?->format(DATE_ATOM),
                'why' => $why === null ? null : self::describe($why),
            ],
            'graph' => (new FlowExporter)->graph($view->definition()),
            'expects' => array_map(fn ($e) => [
                'event' => $e->event,
                'label' => $e->label ?? $e->event,
                'state' => $e->state,
                'target' => $e->target,
                'kind' => $e->kind,
                'conditional' => $e->conditional ? __('да') : '',
            ], $view->expects()),
            'effects' => array_map(fn (EffectRecord $e) => [
                'id' => $e->id,
                'class' => $e->class,
                'kind' => $e->kind,
                'semantics' => $e->semantics,
                'status' => $e->status,
                'attempt' => $e->attempt,
                'next_attempt_at' => $e->nextAttemptAt?->format(DATE_ATOM),
                'error' => $e->error === null ? null : (string) json_encode($e->error, JSON_UNESCAPED_UNICODE),
                'key' => $e->key,
            ], array_reverse($view->effects())),
            'incidents' => array_map(fn (IncidentRecord $i) => [
                'id' => $i->id,
                'type' => $i->type,
                'status' => $i->status,
                'message' => $i->message,
                'effect_id' => $i->effectId,
                'created_at' => $i->createdAt->format(DATE_ATOM),
                'resolution' => $i->resolution,
            ], $view->incidents(null)),
            'batches' => array_map(fn (BatchRecord $b) => [
                'id' => $b->id,
                'state' => self::stateLabel($view, $b->state),
                'status' => $b->status,
                'progress' => self::progress($b),
                'error' => $b->error === null ? null : ($b->error['code'] ?? '').': '.($b->error['message'] ?? ''),
                'created_at' => $b->createdAt?->format(DATE_ATOM),
                'finished_at' => $b->finishedAt?->format(DATE_ATOM),
            ], array_reverse($view->batches())),
            'batch_items' => self::problemItems($view),
            'timers' => array_map(fn ($t) => [
                'event' => $t->event,
                'fire_at' => $t->fireAt->format(DATE_ATOM),
                'activation' => $t->activation,
            ], $view->timers()),
            'inbox' => array_map(fn (InboxRecord $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'status' => $m->status,
                'reason' => $m->reason,
                'received_at' => $m->receivedAt->format(DATE_ATOM),
            ], array_values(array_filter($view->inbox(), fn (InboxRecord $m) => $m->status !== InboxRecord::PROCESSED))),
            'journal' => array_map(fn (JournalRecord $j) => [
                'seq' => $j->seq,
                'at' => $j->at->format(DATE_ATOM),
                'type' => $j->type,
                'actor' => self::actorName($j->actor),
                'reason' => $j->reason,
                'summary' => self::describe($j),
            ], self::tail($view)),
        ]);
    }

    public function layout(): array
    {
        $view = $this->view();
        if ($view === null) {
            return [Layout::markdown(__('Экземпляр не найден. Откройте его из списка экземпляров.'))];
        }

        return [
            Layout::infolist([
                BadgeEntry::make('summary_status')->label(__('Статус'))->colors(Labels::statusColors())->labels(Labels::statuses()),
                TextEntry::make('summary_states')->label(__('Состояние')),
                TextEntry::make('summary_label')->label(__('Этап')),
                TextEntry::make('summary_business_key')->label(__('Бизнес-ключ'))->copyable(),
                TextEntry::make('summary_subject')->label(__('Объект')),
                TextEntry::make('summary_parent')->label(__('Родитель'))->copyable(),
                TextEntry::make('summary_created_at')->label(__('Создан'))->asDateTime(),
                TextEntry::make('summary_updated_at')->label(__('Изменён'))->asDateTime(),
                TextEntry::make('summary_why')->label(__('Последний шаг')),
            ])->layout('grid'),
            Layout::tabs([
                __('Схема') => [
                    Layout::flow('graph')
                        ->poll('flowState', max(1, (int) config('admin-process.refresh.flow', 5)))
                        ->subgraphs('subprocess')
                        ->instanceFrom('instance_id')
                        ->height(560)
                        ->details()
                        ->actions([
                            Button::make(__('Перевести в это состояние'))->method('moveHere')
                                ->permission(Permissions::MANAGE)
                                ->confirm(__('Перевести экземпляр в выбранное состояние в обход логики процесса?')),
                        ]),
                ],
                __('Ожидает') => [Layout::infolist([
                    RepeatableEntry::make('expects')->label(__('События, которые сейчас примет процесс'))->entries([
                        TextEntry::make('label')->label(__('Событие')),
                        TextEntry::make('state')->label(__('В состоянии')),
                        TextEntry::make('target')->label(__('Переход в')),
                        TextEntry::make('kind')->label(__('Вид')),
                        TextEntry::make('conditional')->label(__('С условием')),
                    ]),
                    RepeatableEntry::make('timers')->label(__('Таймеры'))->entries([
                        TextEntry::make('event')->label(__('Событие')),
                        TextEntry::make('fire_at')->label(__('Сработает'))->asDateTime(),
                    ]),
                ])],
                __('Контекст') => [
                    Layout::code((string) json_encode($view->contextArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json')
                        ->maxHeight(600)->wrap(),
                ],
                __('Пакеты') => [Layout::infolist([
                    RepeatableEntry::make('batches')->label(__('Пакетные этапы'))->entries([
                        TextEntry::make('state')->label(__('Этап')),
                        BadgeEntry::make('status')->label(__('Статус'))->colors(['running' => 'info', 'done' => 'success', 'failed' => 'danger', 'cancelled' => 'secondary'])->labels(Labels::batchStatuses()),
                        TextEntry::make('progress')->label(__('Элементы')),
                        TextEntry::make('error')->label(__('Ошибка')),
                        TextEntry::make('created_at')->label(__('Начат'))->asDateTime(),
                        TextEntry::make('finished_at')->label(__('Закончен'))->asDateTime(),
                    ]),
                    RepeatableEntry::make('batch_items')->label(__('Элементы с ошибкой и отложенные (последние 200)'))->entries([
                        TextEntry::make('state')->label(__('Этап')),
                        TextEntry::make('key')->label(__('Элемент'))->copyable(),
                        TextEntry::make('status')->label(__('Статус')),
                        TextEntry::make('attempts')->label(__('Попыток')),
                        TextEntry::make('reason')->label(__('Причина')),
                    ]),
                ])],
                __('Эффекты') => [Layout::infolist([
                    RepeatableEntry::make('effects')->label(__('Эффекты'))->entries([
                        TextEntry::make('class')->label(__('Класс')),
                        BadgeEntry::make('status')->label(__('Статус'))->colors(Labels::effectStatusColors()),
                        TextEntry::make('semantics')->label(__('Семантика')),
                        TextEntry::make('attempt')->label(__('Попытка')),
                        TextEntry::make('next_attempt_at')->label(__('Следующая попытка'))->asDateTime(),
                        TextEntry::make('error')->label(__('Ошибка')),
                        TextEntry::make('id')->label(__('ID'))->copyable(),
                    ]),
                ])],
                __('Инциденты') => [Layout::infolist([
                    RepeatableEntry::make('incidents')->label(__('Инциденты'))->entries([
                        BadgeEntry::make('status')->label(__('Статус'))->colors(['open' => 'danger', 'resolved' => 'success'])->labels(Labels::incidentStatuses()),
                        TextEntry::make('type')->label(__('Тип')),
                        TextEntry::make('message')->label(__('Сообщение')),
                        TextEntry::make('created_at')->label(__('Открыт'))->asDateTime(),
                        TextEntry::make('resolution')->label(__('Решение')),
                    ]),
                    RepeatableEntry::make('inbox')->label(__('Необработанные и отклонённые события'))->entries([
                        TextEntry::make('name')->label(__('Событие')),
                        TextEntry::make('status')->label(__('Статус')),
                        TextEntry::make('reason')->label(__('Причина')),
                        TextEntry::make('received_at')->label(__('Получено'))->asDateTime(),
                    ]),
                ])],
                __('Журнал') => [Layout::infolist([
                    RepeatableEntry::make('journal')->label(__('Журнал'))->entries([
                        TextEntry::make('at')->label(__('Время'))->asDateTime(),
                        TextEntry::make('type')->label(__('Запись')),
                        TextEntry::make('summary')->label(__('Что произошло')),
                        TextEntry::make('actor')->label(__('Кто')),
                        TextEntry::make('reason')->label(__('Причина')),
                    ]),
                ])],
            ]),
        ];
    }

    public function commandBar(): array
    {
        $reason = fn () => Textarea::make('reason')->title(__('Причина'))->required();

        return [
            ModalAction::make(__('Приостановить'))->withName('suspend')->method('suspend')
                ->permission(Permissions::OPERATE)->fields([$reason()]),
            ModalAction::make(__('Возобновить'))->withName('resume')->method('resume')
                ->permission(Permissions::OPERATE)->fields([$reason()]),
            ModalAction::make(__('Отменить'))->withName('cancel')->method('cancel')
                ->permission(Permissions::OPERATE)->fields([
                    $reason(),
                    Switcher::make('compensate')->title(__('Сначала компенсировать выполненные шаги')),
                ]),
            ModalAction::make(__('Повторить элементы пакета'))->withName('retry_batch')->method('retryBatch')
                ->permission(Permissions::OPERATE)->fields([
                    Select::make('batch')->title(__('Пакет'))->options($this->batchOptions())->required(),
                    Select::make('statuses')->title(__('Какие элементы'))->options(['failed' => __('с ошибкой'), 'deferred' => __('отложенные'), 'not_found' => __('не найденные')])->multiple()->default(['failed', 'deferred']),
                    $reason(),
                ]),
            ModalAction::make(__('Пропустить остаток пакета'))->withName('skip_batch')->method('skipBatch')
                ->permission(Permissions::OPERATE)->fields([
                    Select::make('batch')->title(__('Пакет'))->options($this->batchOptions())->required(),
                    $reason(),
                ]),
            ModalAction::make(__('Повторить эффект'))->withName('retry_effect')->method('retryEffect')
                ->permission(Permissions::OPERATE)->fields([
                    Select::make('effect')->title(__('Эффект'))->options($this->effectOptions([EffectRecord::FAILED, EffectRecord::UNKNOWN, EffectRecord::CANCELLED]))->required(),
                    Switcher::make('confirm_unsafe')->title(__('Исход неизвестен: беру ответственность за повторный вызов')),
                    $reason(),
                ]),
            ModalAction::make(__('Закрыть инцидент'))->withName('resolve_incident')->method('resolveIncident')
                ->permission(Permissions::OPERATE)->fields([
                    Select::make('incident')->title(__('Инцидент'))->options($this->incidentOptions())->required(),
                    Input::make('resolution')->title(__('Решение'))->default('resolved'),
                    $reason(),
                ]),
            ModalAction::make(__('Доставить повторно'))->withName('redeliver')->method('redeliver')
                ->permission(Permissions::OPERATE)->fields([
                    Select::make('inbox')->title(__('Событие'))->options($this->failedInboxOptions())->required(),
                    $reason(),
                ]),
            ModalAction::make(__('Перевести в состояние'))->withName('transition')->method('transition')
                ->permission(Permissions::MANAGE)->fields([
                    Select::make('state')->title(__('Состояние'))->options($this->stateOptions())->searchable()->required(),
                    Switcher::make('run_exit')->title(__('Выполнить действия выхода'))->default(true),
                    Switcher::make('run_entry')->title(__('Выполнить действия входа'))->default(true),
                    $reason(),
                ]),
            ModalAction::make(__('Изменить контекст'))->withName('patch_context')->method('patchContext')
                ->permission(Permissions::MANAGE)->fields([
                    Code::make('patch')->title(__('Изменения: JSON-объект «ключ → значение» или JSON Patch (RFC 6902)'))->language('json')->default('{}')->required(),
                    $reason(),
                ]),
            ModalAction::make(__('Задать исход эффекта'))->withName('resolve_effect')->method('resolveEffect')
                ->permission(Permissions::MANAGE)->fields([
                    Select::make('effect')->title(__('Эффект'))->options($this->effectOptions([EffectRecord::UNKNOWN, EffectRecord::RECONCILING, EffectRecord::FAILED]))->required(),
                    Select::make('outcome')->title(__('Исход'))->options(['succeeded' => __('Выполнен'), 'failed' => __('Не выполнен')])->default('succeeded')->required(),
                    Code::make('result')->title(__('Результат (JSON)'))->language('json')->default('null'),
                    Input::make('code')->title(__('Код ошибки (для «не выполнен»)')),
                    $reason(),
                ]),
            ModalAction::make(__('Пропустить эффект'))->withName('skip_effect')->method('skipEffect')
                ->permission(Permissions::MANAGE)->fields([
                    Select::make('effect')->title(__('Эффект'))->options($this->effectOptions([EffectRecord::PENDING, EffectRecord::WAITING, EffectRecord::FAILED, EffectRecord::UNKNOWN]))->required(),
                    Code::make('result')->title(__('Результат (JSON)'))->language('json')->default('null'),
                    $reason(),
                ]),
        ];
    }

    /**
     * The summary also as flat keys (`summary_status`): an entry of the
     * infolist reads a top-level key of the state, not a dotted path.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function flatten(array $state): array
    {
        foreach ((array) ($state['summary'] ?? []) as $key => $value) {
            $state['summary_'.$key] = $value;
        }

        return $state;
    }

    // The flow ----------------------------------------------------------------

    /**
     * The monitoring snapshot of the level the user has drilled into.
     *
     * @param  array<string, mixed>  $context  {path, ref, instance}
     * @return array<string, mixed>
     */
    public function flowState(array $context = []): array
    {
        $view = $this->view((string) ($context['instance'] ?? ''));
        if ($view === null) {
            return ['nodes' => [], 'edges' => [], 'path' => [], 'current' => []];
        }
        try {
            return (new FlowExporter)->snapshot($view, self::path($context));
        } catch (\InvalidArgumentException) {
            // A level of a referenced child process: this instance has no
            // positions there.
            return ['nodes' => [], 'edges' => [], 'path' => [], 'current' => []];
        }
    }

    /**
     * The graph of a child process a state invokes, by its `name@version`.
     *
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function subprocess(string $ref, array $node = []): array
    {
        [$name, $version] = array_pad(explode('@', $ref, 2), 2, null);
        foreach (app(Engine::class)->definitions($name) as $record) {
            if ($version === null || $record->version === (int) $version) {
                return (new FlowExporter)->graph(new Definition($record->schema));
            }
        }
        throw new ActionFailedException(__('Определение :ref не зарегистрировано', ['ref' => $ref]));
    }

    /**
     * The drawer's action: move the instance into the selected state.
     *
     * @param  array<string, mixed>  $selection  {kind, id, path, instance}
     * @return array<string, mixed>
     */
    public function moveHere(array $selection): array
    {
        $id = (string) ($selection['instance'] ?? '');
        $state = (string) ($selection['id'] ?? '');
        if (($selection['kind'] ?? null) !== 'node' || $state === '') {
            throw new ActionFailedException(__('Выберите состояние на схеме'));
        }
        $this->assertState($id, $state);

        return ['message' => Operator::run($id, 'transition', __('Перевод из схемы в панели'),
            fn (Engine $e, Actor $by, string $reason) => $e->transitionTo($id, [$state], $by, $reason))];
    }

    // Operator actions --------------------------------------------------------

    /** @param array<string, mixed> $state */
    public function suspend(array $state): array
    {
        return $this->operate($state, 'suspend', fn (Engine $e, string $id, Actor $by, string $r) => $e->suspend($id, $by, $r));
    }

    /** @param array<string, mixed> $state */
    public function resume(array $state): array
    {
        return $this->operate($state, 'resume', fn (Engine $e, string $id, Actor $by, string $r) => $e->resume($id, $by, $r));
    }

    /** @param array<string, mixed> $state */
    public function cancel(array $state): array
    {
        $compensate = (bool) ($state['compensate'] ?? false);

        return $this->operate($state, 'cancel', fn (Engine $e, string $id, Actor $by, string $r) => $e->cancel($id, $by, $r, $compensate));
    }

    /** @param array<string, mixed> $state */
    public function retryBatch(array $state): array
    {
        $batch = $this->batchOf($state);
        $statuses = array_values(array_map('strval', (array) ($state['statuses'] ?? ['failed', 'deferred']))) ?: ['failed', 'deferred'];

        return $this->operate($state, 'retry_batch', fn (Engine $e, string $id, Actor $by, string $r) => $e->retryBatchItems($id, $batch, $by, $r, $statuses));
    }

    /** @param array<string, mixed> $state */
    public function skipBatch(array $state): array
    {
        $batch = $this->batchOf($state);

        return $this->operate($state, 'skip_batch', fn (Engine $e, string $id, Actor $by, string $r) => $e->skipBatch($id, $batch, $by, $r));
    }

    /** @param array<string, mixed> $state */
    public function retryEffect(array $state): array
    {
        $effect = $this->effectOf($state);
        $unsafe = (bool) ($state['confirm_unsafe'] ?? false);
        if ($unsafe && ! PermissionCheck::allows(Permissions::MANAGE)) {
            throw new ActionFailedException(__('Повтор эффекта с неизвестным исходом требует права на вмешательство'));
        }

        return $this->operate($state, 'retry_effect', fn (Engine $e, string $id, Actor $by, string $r) => $e->retryEffect($effect, $by, $r, $unsafe));
    }

    /** @param array<string, mixed> $state */
    public function resolveIncident(array $state): array
    {
        $incident = (string) ($state['incident'] ?? '');
        $id = $this->instanceId($state);
        if (! in_array($incident, array_map(fn (IncidentRecord $i) => $i->id, $this->view($id)?->incidents() ?? []), true)) {
            throw new ActionFailedException(__('Выберите открытый инцидент этого экземпляра'));
        }
        $resolution = trim((string) ($state['resolution'] ?? '')) ?: 'resolved';

        return $this->operate($state, 'resolve_incident', fn (Engine $e, string $id, Actor $by, string $r) => $e->resolveIncident($incident, $by, $r, $resolution));
    }

    /** @param array<string, mixed> $state */
    public function redeliver(array $state): array
    {
        $inbox = (int) ($state['inbox'] ?? 0);

        return $this->operate($state, 'redeliver', fn (Engine $e, string $id, Actor $by, string $r) => $e->redeliver($id, $inbox, $by, $r));
    }

    /** @param array<string, mixed> $state */
    public function transition(array $state): array
    {
        $target = (string) ($state['state'] ?? '');
        $this->assertState($this->instanceId($state), $target);
        $exit = (bool) ($state['run_exit'] ?? true);
        $entry = (bool) ($state['run_entry'] ?? true);

        return $this->operate($state, 'transition', fn (Engine $e, string $id, Actor $by, string $r) => $e->transitionTo($id, [$target], $by, $r, $exit, $entry));
    }

    /** @param array<string, mixed> $state */
    public function patchContext(array $state): array
    {
        $patch = self::json($state['patch'] ?? '{}', __('Изменения'));
        if (! is_array($patch)) {
            throw new ActionFailedException(__('Изменения — JSON-объект «ключ → значение» или JSON Patch'));
        }
        if (! array_is_list($patch)) {
            // A plain object sets top-level keys: `add` replaces an existing member.
            $patch = array_map(fn (string|int $key, mixed $value) => [
                'op' => 'add',
                'path' => '/'.str_replace(['~', '/'], ['~0', '~1'], (string) $key),
                'value' => $value,
            ], array_keys($patch), $patch);
        }
        if ($patch === []) {
            throw new ActionFailedException(__('Нет изменений'));
        }

        return $this->operate($state, 'patch', fn (Engine $e, string $id, Actor $by, string $r) => $e->patchContext($id, $patch, $by, $r));
    }

    /** @param array<string, mixed> $state */
    public function resolveEffect(array $state): array
    {
        $effect = $this->effectOf($state);
        $succeeded = ($state['outcome'] ?? 'succeeded') !== 'failed';
        $result = self::json($state['result'] ?? 'null', __('Результат'));
        $code = trim((string) ($state['code'] ?? '')) ?: null;

        return $this->operate($state, 'resolve_effect', fn (Engine $e, string $id, Actor $by, string $r) => $e->resolveEffect($effect, $by, $r, $succeeded, $result, $code));
    }

    /** @param array<string, mixed> $state */
    public function skipEffect(array $state): array
    {
        $effect = $this->effectOf($state);
        $result = self::json($state['result'] ?? 'null', __('Результат'));

        return $this->operate($state, 'skip_effect', fn (Engine $e, string $id, Actor $by, string $r) => $e->skipEffect($effect, $by, $r, $result));
    }

    // Helpers -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $state
     * @param  callable(Engine, string, Actor, string): mixed  $call
     * @return array<string, mixed>
     */
    private function operate(array $state, string $operation, callable $call): array
    {
        $id = $this->instanceId($state);
        $message = Operator::run($id, $operation, $state['reason'] ?? null, fn (Engine $e, Actor $by, string $r) => $call($e, $id, $by, $r));

        return ['message' => $message, 'refresh' => true];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function instanceId(array $state): string
    {
        $id = (string) ($state['instance_id'] ?? '');
        if ($id === '' || $this->view($id) === null) {
            throw new ActionFailedException(__('Экземпляр не найден'));
        }

        return $id;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function effectOf(array $state): string
    {
        $effect = (string) ($state['effect'] ?? '');
        $view = $this->view($this->instanceId($state));
        if (! in_array($effect, array_map(fn (EffectRecord $e) => $e->id, $view?->effects() ?? []), true)) {
            throw new ActionFailedException(__('Выберите эффект этого экземпляра'));
        }

        return $effect;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function batchOf(array $state): string
    {
        $batch = (string) ($state['batch'] ?? '');
        $view = $this->view($this->instanceId($state));
        foreach ($view?->batches() ?? [] as $record) {
            if ($record->id === $batch && $record->isRunning()) {
                return $batch;
            }
        }
        throw new ActionFailedException(__('Выберите идущий пакет этого экземпляра'));
    }

    /**
     * The running batches of the instance, for the operator actions.
     *
     * @return array<string, string>
     */
    private function batchOptions(): array
    {
        $options = [];
        foreach ($this->view()?->batches() ?? [] as $batch) {
            if ($batch->isRunning()) {
                $options[$batch->id] = $batch->state.' · '.self::progress($batch);
            }
        }

        return $options;
    }

    /** A state by its label, its id when it has none. */
    private static function stateLabel(InstanceView $view, string $state): string
    {
        $definition = $view->definition();

        return $definition->has($state) ? ($definition->node($state)->label() ?? $state) : $state;
    }

    private static function progress(BatchRecord $batch): string
    {
        $s = $batch->summary();
        $finished = $s['done'] + $s['not_found'] + $s['failed'] + $s['deferred'] + $s['skipped'];
        $parts = [__('готово :n', ['n' => $s['done']])];
        foreach (['not_found' => 'не найдено :n', 'failed' => 'с ошибкой :n', 'deferred' => 'отложено :n', 'skipped' => 'пропущено :n'] as $key => $label) {
            if ($s[$key] > 0) {
                $parts[] = __($label, ['n' => $s[$key]]);
            }
        }

        return $finished.' / '.($batch->total ?? $s['total']).' — '.implode(', ', $parts);
    }

    /**
     * Failed and deferred elements of the instance's batches, newest batch first.
     *
     * @return list<array<string, mixed>>
     */
    private static function problemItems(InstanceView $view): array
    {
        $engine = app(Engine::class);
        $rows = [];
        foreach (array_reverse($view->batches()) as $batch) {
            foreach (['failed', 'deferred'] as $status) {
                foreach ($engine->batchItems($batch->id, $status, 200 - count($rows)) as $item) {
                    $rows[] = ['state' => self::stateLabel($view, $batch->state), 'key' => $item->key, 'status' => $item->status, 'attempts' => $item->attempts, 'reason' => $item->reason];
                }
                if (count($rows) >= 200) {
                    return $rows;
                }
            }
        }

        return $rows;
    }

    private function assertState(string $instanceId, string $state): void
    {
        $view = $this->view($instanceId) ?? throw new ActionFailedException(__('Экземпляр не найден'));
        if (! array_key_exists($state, self::states($view->definition()))) {
            throw new ActionFailedException(__('В процессе нет состояния :state', ['state' => $state]));
        }
    }

    /**
     * The instance on this page: from the page's query string, or from the
     * state a command method received. Read once per request.
     */
    private function view(?string $id = null): ?InstanceView
    {
        $id = $id !== null && $id !== '' ? $id : self::requestedId();
        if ($id === '') {
            return null;
        }
        if (! array_key_exists($id, $this->views)) {
            $this->views[$id] = app(Engine::class)->find($id);
        }

        return $this->views[$id];
    }

    private static function requestedId(): string
    {
        $request = request();
        foreach ([$request->query('id'), $request->input('payload.instance_id'), $request->input('parameters.0.instance')] as $candidate) {
            // Instance ids are UUIDs. The panel describes every screen while it
            // builds its manifest, with the query string of whatever page is
            // open — another screen's `?id=1` must not reach the database,
            // where PostgreSQL rejects it and aborts the request's transaction.
            if (is_string($candidate) && \Illuminate\Support\Str::isUuid($candidate)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * The states an operator may move the instance into: everything but
     * pseudo-states and the root.
     *
     * @return array<string, string>
     */
    private static function states(Definition $definition): array
    {
        $states = [];
        foreach ($definition->nodes() as $id => $node) {
            if ($node->parent === null || in_array($node->type, [StateType::Choice, StateType::History], true)) {
                continue;
            }
            $label = $node->label();
            $states[$id] = $label !== null && $label !== $id ? $id.' — '.$label : $id;
        }

        return $states;
    }

    /** @return array<string, string> */
    private function stateOptions(): array
    {
        $view = $this->view();

        return $view === null ? [] : self::states($view->definition());
    }

    /**
     * @param  list<string>  $statuses
     * @return array<string, string>
     */
    private function effectOptions(array $statuses): array
    {
        $options = [];
        foreach ($this->view()?->effects($statuses) ?? [] as $effect) {
            $options[$effect->id] = self::short($effect->class).' · '.$effect->status.' · '.substr($effect->id, 0, 8);
        }

        return $options;
    }

    /** @return array<string, string> */
    private function incidentOptions(): array
    {
        $options = [];
        foreach ($this->view()?->incidents() ?? [] as $incident) {
            $options[$incident->id] = $incident->message;
        }

        return $options;
    }

    /** @return array<string, string> */
    private function failedInboxOptions(): array
    {
        $options = [];
        foreach ($this->view()?->inbox(InboxRecord::FAILED) ?? [] as $entry) {
            $options[(string) $entry->id] = $entry->name.' · '.$entry->receivedAt->format('Y-m-d H:i:s');
        }

        return $options;
    }

    /**
     * The latest journal entries, newest first.
     *
     * @return list<JournalRecord>
     */
    private static function tail(InstanceView $view): array
    {
        $limit = max(1, (int) config('admin-process.journal_limit', 200));
        $entries = $view->journal(0, PHP_INT_MAX);

        return array_reverse(array_slice($entries, -$limit));
    }

    private static function describe(JournalRecord $entry): string
    {
        $d = $entry->data;
        $parts = [];
        if (isset($d['event']) && is_string($d['event'])) {
            $parts[] = __('событие :event', ['event' => $d['event']]);
        }
        if (isset($d['entered']) && is_array($d['entered']) && $d['entered'] !== []) {
            $parts[] = __('вход: :states', ['states' => implode(', ', array_map('strval', $d['entered']))]);
        }
        if (isset($d['operation']) && is_string($d['operation'])) {
            $parts[] = $d['operation'];
        }
        if (isset($d['error']) && is_string($d['error'])) {
            $parts[] = $d['error'];
        }
        if (isset($d['ignored']) && $d['ignored']) {
            $parts[] = __('проигнорировано');
        }

        return $parts === [] ? $entry->type : implode('; ', $parts);
    }

    /**
     * @param  array<string, mixed>|null  $actor
     */
    private static function actorName(?array $actor): ?string
    {
        $actor = Actor::fromArray($actor);
        if ($actor === null) {
            return null;
        }

        return $actor->display ?? trim($actor->type.' '.($actor->id ?? ''));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    private static function path(array $context): array
    {
        return array_values(array_filter(array_map(fn ($p) => is_scalar($p) ? (string) $p : '', (array) ($context['path'] ?? [])), fn (string $p) => $p !== ''));
    }

    private static function json(mixed $value, string $field): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        try {
            return json_decode($value === '' ? 'null' : $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ActionFailedException(__(':field: некорректный JSON', ['field' => $field]));
        }
    }

    private static function short(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }
}
