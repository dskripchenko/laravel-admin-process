<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Resources;

use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Dskripchenko\LaravelAdminProcess\Models\ProcessIncident;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Support\Labels;
use Dskripchenko\LaravelAdminProcess\Support\Operator;
use Dskripchenko\ProcessEngine\Actor;
use Dskripchenko\ProcessEngine\Engine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The incidents of all instances, open ones first: what needs a person.
 * The instance column opens the instance page, where the cause (an effect
 * with an unknown outcome, exhausted retries) is settled.
 *
 * Permissions:
 *   - admin.process.incidents.view
 *   - admin.process.operate — resolve an incident
 */
class ProcessIncidentResource extends Resource
{
    public static string $model = ProcessIncident::class;

    public static string $icon = 'siren';

    public static ?string $group = 'Процессы';

    public static function slug(): string
    {
        return 'process-incidents';
    }

    public static function permission(): string
    {
        return Permissions::INCIDENTS;
    }

    public static function label(): string
    {
        return __('Инциденты процессов');
    }

    public static function singularLabel(): string
    {
        return __('инцидент');
    }

    public function columns(): array
    {
        return [
            TableColumn::make('status')->label(__('Статус'))->sort()->asBadge(['open' => 'danger', 'resolved' => 'success'], Labels::incidentStatuses()),
            TableColumn::make('type')->label(__('Тип'))->sort()->asBadge([], Labels::incidentTypes()),
            TableColumn::make('message')->label(__('Сообщение'))->search(),
            TableColumn::make('instance_id')->label(__('Экземпляр'))->copyable()->asLink(Labels::instanceUrl('{instance_id}')),
            TableColumn::make('resolution')->label(__('Решение'))->defaultHidden(),
            TableColumn::make('created_at')->label(__('Открыт'))->sort()->asDateTime(),
            TableColumn::make('resolved_at')->label(__('Решён'))->sort()->asDateTime()->defaultHidden(),
        ];
    }

    public function filters(): array
    {
        return [
            OptionsFilter::for('status')->label(__('Статус'))->options(Labels::incidentStatuses()),
            OptionsFilter::for('type')->label(__('Тип'))->options(Labels::incidentTypes()),
            InputFilter::for('instance_id')->label(__('Экземпляр')),
        ];
    }

    public function actions(): array
    {
        return [
            ModalAction::make(__('Закрыть инцидент'))->withName('resolve')->method('resolve')
                ->position(['row'])->permission(Permissions::OPERATE)
                ->fields([
                    Input::make('resolution')->title(__('Решение'))->default('resolved')->rules(['nullable', 'string', 'max:191']),
                    Textarea::make('reason')->title(__('Причина'))->required()->rules(['required', 'string', 'max:1000']),
                ]),
        ];
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $ids, array $payload = []): string
    {
        $message = '';
        foreach ($ids as $id) {
            $incident = ProcessIncident::query()->find($id) ?? throw new ActionFailedException(__('Инцидент не найден'));
            $resolution = trim((string) ($payload['resolution'] ?? '')) ?: 'resolved';
            $message = Operator::run($incident->instance_id, 'resolve_incident', $payload['reason'] ?? null,
                fn (Engine $e, Actor $by, string $reason) => $e->resolveIncident((string) $id, $by, $reason, $resolution));
        }

        return $message;
    }

    public function recordTitle(Model $row): string
    {
        return (string) $row->getAttribute('message');
    }

    public function indexQuery(): Builder
    {
        // Open first, then the newest.
        return $this->modelQuery()->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('created_at');
    }

    public function polling(): ?int
    {
        $seconds = (int) config('admin-process.refresh.list', 15);

        return $seconds > 0 ? $seconds : null;
    }
}
