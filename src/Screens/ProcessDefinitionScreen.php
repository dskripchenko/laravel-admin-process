<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Screens;

use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Code;
use Dskripchenko\LaravelAdmin\Field\Select;
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
use Dskripchenko\LaravelProcessEngine\Models\ProcessInstance;
use Dskripchenko\ProcessEngine\Definition\Definition;
use Dskripchenko\ProcessEngine\Engine;
use Dskripchenko\ProcessEngine\Export\FlowExporter;
use Dskripchenko\ProcessEngine\Interpreter\FlagProvider;
use Dskripchenko\ProcessEngine\Interpreter\SwitchProvider;
use Dskripchenko\ProcessEngine\Process;
use Dskripchenko\ProcessEngine\Store\DefinitionRecord;
use Dskripchenko\ProcessEngine\Store\InstanceQuery;

/**
 * A process as a whole: its diagram with how many instances stand in each
 * state, the switches of its stages (on the nodes, with a reason that goes
 * into the switch's history), the start form for processes started by hand,
 * and its latest instances.
 *
 * The page is addressed as `/screens/process-definition?name=<process>`.
 */
class ProcessDefinitionScreen extends Screen
{
    private ?DefinitionRecord $record = null;

    private bool $loaded = false;

    public static function slug(): string
    {
        return 'process-definition';
    }

    public function name(): string
    {
        $record = $this->record();

        return $record === null ? __('Процесс') : ((new Definition($record->schema))->root->label() ?? $record->name);
    }

    public function description(): ?string
    {
        $record = $this->record();

        return $record === null ? null : $record->name.' · '.__('Версия :version', ['version' => $record->version]);
    }

    public function permission(): string
    {
        return Permissions::VIEW;
    }

    public function query(mixed ...$params): array
    {
        $record = $this->record();
        if ($record === null) {
            return ['definition_name' => null, 'found' => false];
        }
        $definition = new Definition($record->schema);

        return ProcessInstanceScreen::flatten([
            'definition_name' => $record->name,
            'found' => true,
            'summary' => [
                'name' => $definition->root->label() ?? $record->name,
                'version' => $record->version,
                'running' => ProcessInstance::query()->where('definition_name', $record->name)->where('status', 'running')->count(),
                'startable' => $this->processClass($record->name) !== null,
            ],
            'graph' => (new FlowExporter($this->switches()))->graph($definition),
            'switches' => array_map(fn (array $s) => [
                'label' => $s['label'],
                'switch' => $s['switch'],
                'on' => $s['on'] ? 'on' : 'off',
                'reason' => $s['reason'],
                'by' => $s['by'],
                'at' => $s['at'],
            ], $this->switchRows($definition)),
        ]);
    }

    public function layout(): array
    {
        if ($this->record() === null) {
            return [Layout::markdown(__('Процесс не найден. Откройте его из списка процессов.'))];
        }

        return [
            Layout::infolist([
                TextEntry::make('summary_name')->label(__('Процесс')),
                TextEntry::make('summary_version')->label(__('Версия')),
                TextEntry::make('summary_running')->label(__('Идут')),
            ])->layout('grid'),
            Layout::tabs([
                __('Схема') => [
                    Layout::flow('graph')
                        ->poll('overview', max(1, (int) config('admin-process.refresh.flow', 5)))
                        ->onToggle('toggleSwitch', Permissions::SWITCHES)
                        ->subgraphs('subprocess')
                        ->height(560)
                        ->details(),
                ],
                __('Этапы') => [Layout::infolist([
                    RepeatableEntry::make('switches')->label(__('Выключаемые этапы'))->entries([
                        TextEntry::make('label')->label(__('Этап')),
                        BadgeEntry::make('on')->label(__('Состояние'))->colors(['on' => 'success', 'off' => 'danger'])->labels(['on' => __('включён'), 'off' => __('выключен')]),
                        TextEntry::make('reason')->label(__('Причина')),
                        TextEntry::make('by')->label(__('Кто')),
                        TextEntry::make('at')->label(__('Когда'))->asDateTime(),
                        TextEntry::make('switch')->label(__('Переключатель'))->copyable(),
                    ]),
                ])],
                __('Экземпляры') => [Layout::markdown($this->instancesTable())],
            ]),
        ];
    }

    public function commandBar(): array
    {
        $record = $this->record();
        if ($record === null || $this->processClass($record->name) === null) {
            return [];
        }
        $switches = [];
        foreach ($this->switchRows(new Definition($record->schema)) as $row) {
            $switches[$row['switch']] = $row['label'];
        }

        return [
            ModalAction::make(__('Запустить'))->withName('start')->method('start')
                ->permission(Permissions::START)->fields(array_filter([
                    Code::make('context')->title(__('Контекст запуска (JSON)'))->language('json')
                        ->default((string) json_encode((object) $this->startContext($record->name), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    $switches !== [] ? Select::make('disabled')->title(__('Выключить на этот запуск'))->options($switches)->multiple() : null,
                ])),
        ];
    }

    // The flow ----------------------------------------------------------------

    /**
     * Instances per state at the level the user has drilled into.
     *
     * @param  array<string, mixed>  $context  {path, ref, instance}
     * @return array<string, mixed>
     */
    public function overview(array $context = []): array
    {
        $record = $this->record((string) ($context['instance'] ?? ''));
        if ($record === null) {
            return ['nodes' => [], 'edges' => []];
        }
        $path = array_values(array_map('strval', (array) ($context['path'] ?? [])));
        try {
            return (new FlowExporter)->overview(new Definition($record->schema), app(Engine::class)->stateCounts($record->name, $record->version), $path);
        } catch (\InvalidArgumentException) {
            return ['nodes' => [], 'edges' => []];
        }
    }

    /**
     * A switch on the diagram changed: the stage goes off or on everywhere.
     *
     * @param  array<string, mixed>  $change  {kind, id, enabled, path}
     * @return array<string, mixed>
     */
    public function toggleSwitch(array $change): array
    {
        // The panel guards declared methods; the layout of a missing process declares none.
        if (! PermissionCheck::allows(Permissions::SWITCHES)) {
            throw new ActionFailedException(__('Нет права выключать этапы'));
        }
        $record = $this->record() ?? throw new ActionFailedException(__('Процесс не найден'));
        $node = (string) ($change['id'] ?? '');
        $switch = null;
        foreach ($this->switchRows(new Definition($record->schema)) as $row) {
            if ($row['state'] === $node) {
                $switch = $row['switch'];
            }
        }
        if (($change['kind'] ?? null) !== 'node' || $switch === null) {
            throw new ActionFailedException(__('Этот этап не выключается'));
        }
        $engine = app(Engine::class);
        $by = Operator::actor();
        (bool) ($change['enabled'] ?? true)
            ? $engine->switchOn($switch, $by, __('Включено на схеме процесса'))
            : $engine->switchOff($switch, $by, __('Выключено на схеме процесса'));

        return ['message' => __('Готово'), 'refresh' => true];
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
                return (new FlowExporter($this->switches()))->graph(new Definition($record->schema));
            }
        }
        throw new ActionFailedException(__('Определение :ref не зарегистрировано', ['ref' => $ref]));
    }

    // Start -------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function start(array $state): array
    {
        if (! PermissionCheck::allows(Permissions::START)) {
            throw new ActionFailedException(__('Нет права запускать процессы'));
        }
        $record = $this->record((string) ($state['definition_name'] ?? '')) ?? throw new ActionFailedException(__('Процесс не найден'));
        $class = $this->processClass($record->name) ?? throw new ActionFailedException(__('Этот процесс не запускается вручную'));
        $context = json_decode(trim((string) ($state['context'] ?? '')) ?: '{}', true);
        if (! is_array($context) || ($context !== [] && array_is_list($context))) {
            throw new ActionFailedException(__('Контекст запуска — JSON-объект'));
        }
        $disabled = array_values(array_filter(array_map('strval', (array) ($state['disabled'] ?? []))));
        if ($disabled !== []) {
            $context['disabled'] = array_values(array_unique([...(array) ($context['disabled'] ?? []), ...$disabled]));
        }
        try {
            $view = app(Engine::class)->start($class, $context, by: Operator::actor());
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            throw new ActionFailedException($e->getMessage(), previous: $e);
        }

        return ['message' => __('Запущен экземпляр :id', ['id' => $view->id()]), 'redirect_url' => Labels::instanceUrl($view->id())];
    }

    // Helpers -----------------------------------------------------------------

    /**
     * The latest instances as a table of links to their pages.
     */
    private function instancesTable(): string
    {
        $record = $this->record();
        if ($record === null) {
            return '';
        }
        $instances = app(Engine::class)->instances(new InstanceQuery(definitionName: $record->name, limit: 20));
        if ($instances === []) {
            return __('Экземпляров пока нет.');
        }
        $statuses = Labels::statuses();
        $lines = ['| '.__('Экземпляр').' | '.__('Статус').' | '.__('Состояние').' | '.__('Изменён').' |', '|---|---|---|---|'];
        foreach ($instances as $v) {
            $states = implode(', ', $v->isFinished() ? array_filter([$v->final()]) : $v->leaves());
            $lines[] = sprintf('| [%s](%s) | %s | %s | %s |',
                $v->businessKey() ?? substr($v->id(), 0, 8), Labels::instanceUrl($v->id()),
                $statuses[$v->status()] ?? $v->status(), $states, $v->record()->updatedAt?->format('Y-m-d H:i') ?? '');
        }

        return implode("\n", $lines);
    }

    /**
     * The switchable states of a definition with the state of their switches.
     *
     * @return list<array{state: string, switch: string, label: string, on: bool, reason: ?string, by: ?string, at: ?string}>
     */
    private function switchRows(Definition $definition): array
    {
        $switches = $this->switches();
        $rows = [];
        foreach ($definition->nodes() as $node) {
            $spec = $node->meta['switch'] ?? null;
            if (! is_array($spec) || ! is_string($spec['name'] ?? null)) {
                continue;
            }
            $state = $switches?->state($spec['name']) ?? ['on' => true, 'reason' => null, 'by' => null, 'at' => null];
            $rows[] = [
                'state' => $node->id,
                'switch' => $spec['name'],
                'label' => (string) ($spec['label'] ?? $node->label() ?? $node->id),
                'on' => $state['on'],
                'reason' => $state['reason'],
                'by' => is_array($state['by']) ? (string) ($state['by']['name'] ?? $state['by']['type'] ?? '') : null,
                'at' => $state['at']?->format(DATE_ATOM),
            ];
        }

        return $rows;
    }

    private function switches(): ?SwitchProvider
    {
        return self::asSwitches(app(FlagProvider::class));
    }

    /**
     * The host may bind any FlagProvider: switches only when it keeps them.
     */
    private static function asSwitches(FlagProvider $flags): ?SwitchProvider
    {
        return $flags instanceof SwitchProvider ? $flags : null;
    }

    /**
     * The class configured to be started by hand under this process name.
     *
     * @return class-string<Process>|null
     */
    private function processClass(string $name): ?string
    {
        foreach ((array) config('admin-process.startable', []) as $key => $value) {
            $class = is_string($key) ? $key : (string) $value;
            if (is_subclass_of($class, Process::class) && $class::processName() === $name) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function startContext(string $name): array
    {
        $class = $this->processClass($name);
        $startable = (array) config('admin-process.startable', []);

        return $class !== null && isset($startable[$class]) && is_array($startable[$class]) ? $startable[$class] : [];
    }

    /**
     * The process on this page: from the query string, or from the state a
     * command method received.
     */
    private function record(?string $name = null): ?DefinitionRecord
    {
        $name = $name !== null && $name !== '' ? $name : self::requestedName();
        if ($name === '') {
            return null;
        }
        if (! $this->loaded || ($this->record !== null && $this->record->name !== $name)) {
            $this->loaded = true;
            $records = app(Engine::class)->definitions($name);
            $this->record = $records === [] ? null : $records[array_key_last($records)];
        }

        return $this->record;
    }

    private static function requestedName(): string
    {
        $request = request();
        foreach ([$request->query('name'), $request->input('payload.definition_name'), $request->input('parameters.0.instance')] as $candidate) {
            if (is_string($candidate) && $candidate !== '' && strlen($candidate) <= 191) {
                return $candidate;
            }
        }

        return '';
    }
}
