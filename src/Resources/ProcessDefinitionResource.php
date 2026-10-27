<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Resources;

use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminProcess\Models\ProcessDefinition;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Support\Labels;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The processes: the latest version of each, how many instances run, wait
 * on an incident or failed in the last day; a row opens the diagram of the
 * process with its switches and the start form. Read-only.
 */
class ProcessDefinitionResource extends Resource
{
    public static string $model = ProcessDefinition::class;

    public static string $icon = 'git-branch';

    public static ?string $group = 'Процессы';

    public static function slug(): string
    {
        return 'process-definitions';
    }

    public static function permission(): string
    {
        return Permissions::INSTANCES;
    }

    public static function label(): string
    {
        return __('Процессы');
    }

    public static function singularLabel(): string
    {
        return __('процесс');
    }

    public function fields(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('name')->label(__('Процесс'))->sort()->search()->asLink(Labels::definitionUrl()),
            TableColumn::make('version')->label(__('Версия'))->align('right')->width('80px'),
            TableColumn::make('running')->label(__('Идут'))->sort()->align('right'),
            TableColumn::make('with_incidents')->label(__('С инцидентами'))->sort()->align('right'),
            TableColumn::make('failed_day')->label(__('Упали за сутки'))->align('right'),
            TableColumn::make('created_at')->label(__('Версия от'))->asDateTime(),
        ];
    }

    public function indexQuery(): Builder
    {
        $p = (string) config('process-engine.table_prefix', 'pe_');
        $definitions = $p.'definitions';
        $count = fn (string $where) => "(select count(*) from {$p}instances i where i.definition_name = {$definitions}.name and {$where})";

        return $this->modelQuery()
            ->whereIn("{$definitions}.id", fn ($q) => $q->from($definitions)->selectRaw('max(id)')->groupBy('name'))
            ->select("{$definitions}.*")
            ->selectRaw($count("i.status = 'running'").' as running')
            ->selectRaw($count('i.has_incidents = ?').' as with_incidents', [true])
            ->selectRaw($count("i.status = 'failed' and i.updated_at >= ?").' as failed_day', [now()->subDay()->utc()->format('Y-m-d H:i:s.u')])
            ->orderBy('name');
    }

    public function transformRecord(Model $record): array
    {
        $row = parent::transformRecord($record);
        unset($row['schema']);

        return $row;
    }

    public function recordTitle(Model $row): string
    {
        return (string) $row->getAttribute('name');
    }
}
