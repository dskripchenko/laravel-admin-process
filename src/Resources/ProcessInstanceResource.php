<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Resources;

use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Filter\DateRangeFilter;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Filter\SwitcherFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Support\Labels;
use Dskripchenko\LaravelAdminProcess\Support\Operator;
use Dskripchenko\LaravelAdminProcess\Support\StateNames;
use Dskripchenko\LaravelProcessEngine\Models\ProcessInstance;
use Dskripchenko\ProcessEngine\Actor;
use Dskripchenko\ProcessEngine\Engine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The list of process instances: filters by process, status, business key
 * and incidents; a row opens the instance page. Read-only — instances
 * change through the engine, so the row actions are operator actions with
 * a reason that goes into the journal.
 *
 * Permissions:
 *   - admin.process.instances.view
 *   - admin.process.operate — suspend, resume, cancel
 */
class ProcessInstanceResource extends Resource
{
    public static string $model = ProcessInstance::class;

    public static string $icon = 'workflow';

    public static ?string $group = 'Процессы';

    private ?StateNames $states = null;

    public static function slug(): string
    {
        return 'process-instances';
    }

    public static function permission(): string
    {
        return Permissions::INSTANCES;
    }

    public static function label(): string
    {
        return __('Экземпляры процессов');
    }

    public static function singularLabel(): string
    {
        return __('экземпляр процесса');
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->label(__('ID'))->copyable()->asLink(Labels::instanceUrl()),
            TableColumn::make('definition_name')->label(__('Процесс'))->sort()->search(),
            TableColumn::make('definition_version')->label(__('Версия'))->sort()->align('right')->width('80px'),
            TableColumn::make('status')->label(__('Статус'))->sort()->asBadge(Labels::statusColors(), Labels::statuses()),
            // The snapshot carries the whole context: the cell shows only the states.
            TableColumn::make('snapshot')->label(__('Состояние'))
                ->format(fn (mixed $snapshot, array $row) => $this->states((string) ($row['definition_id'] ?? ''), $snapshot)),
            TableColumn::make('business_key')->label(__('Бизнес-ключ'))->search()->copyable(),
            TableColumn::make('has_incidents')->label(__('Инциденты'))->asBoolean(),
            TableColumn::make('created_at')->label(__('Создан'))->sort()->asDateTime(),
            TableColumn::make('updated_at')->label(__('Изменён'))->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            OptionsFilter::for('definition_name')->label(__('Процесс'))->options(self::processes()),
            OptionsFilter::for('status')->label(__('Статус'))->options(Labels::statuses()),
            InputFilter::for('business_key')->label(__('Бизнес-ключ')),
            SwitcherFilter::for('has_incidents')->label(__('С инцидентами')),
            DateRangeFilter::for('created_at')->label(__('Создан')),
        ];
    }

    public function actions(): array
    {
        $reason = fn () => Textarea::make('reason')->title(__('Причина'))->required()->rules(['required', 'string', 'max:1000']);

        return [
            ModalAction::make(__('Приостановить'))->withName('suspend')->method('suspend')
                ->position(['row'])->permission(Permissions::OPERATE)
                ->fields([$reason()]),
            ModalAction::make(__('Возобновить'))->withName('resume')->method('resume')
                ->position(['row'])->permission(Permissions::OPERATE)
                ->fields([$reason()]),
            ModalAction::make(__('Отменить'))->withName('cancel')->method('cancel')
                ->position(['row'])->permission(Permissions::OPERATE)
                ->fields([
                    $reason(),
                    Switcher::make('compensate')->title(__('Сначала компенсировать выполненные шаги')),
                ]),
        ];
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function suspend(array $ids, array $payload = []): string
    {
        return $this->each($ids, 'suspend', $payload, fn (Engine $e, string $id, Actor $by, string $reason) => $e->suspend($id, $by, $reason));
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function resume(array $ids, array $payload = []): string
    {
        return $this->each($ids, 'resume', $payload, fn (Engine $e, string $id, Actor $by, string $reason) => $e->resume($id, $by, $reason));
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function cancel(array $ids, array $payload = []): string
    {
        $compensate = (bool) ($payload['compensate'] ?? false);

        return $this->each($ids, 'cancel', $payload, fn (Engine $e, string $id, Actor $by, string $reason) => $e->cancel($id, $by, $reason, $compensate));
    }

    public function transformRecord(Model $record): array
    {
        $row = parent::transformRecord($record);
        $row['snapshot'] = $this->states((string) ($row['definition_id'] ?? ''), $row['snapshot'] ?? []);

        return $row;
    }

    private function states(string $definitionId, mixed $snapshot): string
    {
        $this->states ??= new StateNames;

        return $this->states->of($definitionId, is_array($snapshot) ? $snapshot : []);
    }

    public function recordTitle(Model $row): string
    {
        return (string) $row->getAttribute('definition_name').' · '.($row->getAttribute('business_key') ?? $row->getKey());
    }

    public function indexQuery(): Builder
    {
        return $this->modelQuery()->orderByDesc('updated_at');
    }

    public function polling(): ?int
    {
        $seconds = (int) config('admin-process.refresh.list', 15);

        return $seconds > 0 ? $seconds : null;
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     * @param  callable(Engine, string, Actor, string): mixed  $call
     */
    private function each(array $ids, string $operation, array $payload, callable $call): string
    {
        $message = '';
        foreach ($ids as $id) {
            $id = (string) $id;
            $message = Operator::run($id, $operation, $payload['reason'] ?? null, fn (Engine $e, Actor $by, string $reason) => $call($e, $id, $by, $reason));
        }

        return $message;
    }

    /**
     * The registered process names, for the filter.
     *
     * @return array<string, string>
     */
    private static function processes(): array
    {
        $names = [];
        foreach (app(Engine::class)->definitions() as $record) {
            $names[$record->name] = $record->name;
        }
        ksort($names);

        return $names;
    }
}
