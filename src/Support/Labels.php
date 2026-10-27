<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Support;

use Dskripchenko\ProcessEngine\Interpreter\Snapshot;
use Dskripchenko\ProcessEngine\Store\EffectRecord;
use Dskripchenko\ProcessEngine\Store\IncidentRecord;

/**
 * Captions and badge colours shared by the lists and the instance page.
 * Captions are source strings: core translates them per request.
 */
final class Labels
{
    /** @return array<string, string> */
    public static function statuses(): array
    {
        return [
            Snapshot::RUNNING => 'Выполняется',
            Snapshot::SUSPENDED => 'Приостановлен',
            Snapshot::COMPLETED => 'Завершён',
            Snapshot::FAILED => 'Сбой',
            Snapshot::CANCELLED => 'Отменён',
        ];
    }

    /** @return array<string, string> */
    public static function statusColors(): array
    {
        return [
            Snapshot::RUNNING => 'info',
            Snapshot::SUSPENDED => 'warning',
            Snapshot::COMPLETED => 'success',
            Snapshot::FAILED => 'danger',
            Snapshot::CANCELLED => 'neutral',
        ];
    }

    /** @return array<string, string> */
    public static function incidentStatuses(): array
    {
        return [
            IncidentRecord::OPEN => 'Открыт',
            IncidentRecord::RESOLVED => 'Решён',
        ];
    }

    /** @return array<string, string> */
    public static function incidentTypes(): array
    {
        return [
            IncidentRecord::EFFECT_EXHAUSTED => 'Попытки эффекта исчерпаны',
            IncidentRecord::EFFECT_FAILED => 'Эффект завершился ошибкой',
            IncidentRecord::EFFECT_UNKNOWN => 'Исход эффекта неизвестен',
            IncidentRecord::COMPENSATION_FAILED => 'Компенсация не удалась',
            IncidentRecord::COMPENSATION_BLOCKED => 'Компенсация заблокирована',
            IncidentRecord::MICROSTEP_LIMIT => 'Зацикливание переходов',
            IncidentRecord::STEP_ERROR => 'Ошибка шага',
            IncidentRecord::HANDLER_MISSING => 'Нет обработчика',
            IncidentRecord::CONTEXT_TOO_LARGE => 'Контекст слишком велик',
            IncidentRecord::POLL_FAILING => 'Опрос не проходит',
            IncidentRecord::MIGRATION_FAILED => 'Миграция не удалась',
        ];
    }

    /** @return array<string, string> */
    public static function effectStatusColors(): array
    {
        return [
            EffectRecord::PENDING => 'neutral',
            EffectRecord::WAITING => 'neutral',
            EffectRecord::RUNNING => 'info',
            EffectRecord::SUCCEEDED => 'success',
            EffectRecord::FAILED => 'danger',
            EffectRecord::UNKNOWN => 'warning',
            EffectRecord::RECONCILING => 'warning',
            EffectRecord::CANCELLED => 'neutral',
            EffectRecord::COMPENSATED => 'neutral',
        ];
    }

    /**
     * The address of the instance page in the panel, with an `{id}`
     * placeholder for a list's link template.
     */
    /**
     * @return array<string, string>
     */
    public static function batchStatuses(): array
    {
        return [
            'running' => __('идёт'),
            'done' => __('завершён'),
            'failed' => __('остановлен с ошибкой'),
            'cancelled' => __('отменён'),
        ];
    }

    public static function definitionUrl(string $name = '{name}'): string
    {
        $path = trim((string) config('admin.path', 'admin'), '/');

        return ($path === '' ? '' : '/'.$path).'/screens/process-definition?name='.$name;
    }

    public static function instanceUrl(string $id = '{id}'): string
    {
        $path = trim((string) config('admin.path', 'admin'), '/');

        return ($path === '' ? '' : '/'.$path).'/screens/process-instance?id='.$id;
    }
}
