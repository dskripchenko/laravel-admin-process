<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Widgets;

use Dskripchenko\LaravelAdmin\Widget\StatsOverviewWidget;
use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\ProcessEngine\Engine;

/**
 * The engine at a glance, on the dashboard: how many instances run and
 * wait, and what needs a person — open incidents, timers the ticker has not
 * fired (the scheduler is not running), effects whose worker died.
 */
class ProcessOverviewWidget extends StatsOverviewWidget
{
    protected array|string|null $permission = Permissions::VIEW;

    public function __construct()
    {
        $this->title('Процессы')->size(6);
        $seconds = (int) config('admin-process.refresh.widget', 60);
        if ($seconds > 0) {
            $this->refresh($seconds);
        }
    }

    public static function slug(): string
    {
        return 'admin.process.overview';
    }

    public function data(): array
    {
        $health = app(Engine::class)->health();

        $this->stat(__('Выполняются'), $health['running'], 'info', 'play');
        $this->stat(__('Приостановлены'), $health['suspended'], $health['suspended'] > 0 ? 'warning' : 'neutral', 'pause');
        $this->stat(__('Открытые инциденты'), $health['open_incidents'], $health['open_incidents'] > 0 ? 'danger' : 'success', 'siren');
        if ($health['overdue_timers'] > 0) {
            $this->stat(__('Просроченные таймеры'), $health['overdue_timers'], 'danger', 'timer-off');
        }
        if ($health['expired_effect_leases'] > 0) {
            $this->stat(__('Зависшие эффекты'), $health['expired_effect_leases'], 'warning', 'hourglass');
        }

        return parent::data();
    }
}
