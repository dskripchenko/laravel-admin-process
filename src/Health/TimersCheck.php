<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Health;

use Dskripchenko\LaravelAdminHealth\HealthResult;

/** Timers that did not fire in time: the tick (the scheduler) does not run. */
final class TimersCheck extends ProcessCheck
{
    protected function key(): string
    {
        return 'timers';
    }

    public function name(): string
    {
        return __('Процессы: просроченные таймеры');
    }

    protected function check(): HealthResult
    {
        $overdue = (int) ($this->engine()->health()['overdue_timers'] ?? 0);
        $meta = ['overdue_timers' => $overdue];
        if ($overdue >= (int) $this->setting('failing', 1)) {
            return HealthResult::failing('Просроченных таймеров: :count — запущен ли планировщик (process-engine:tick)?', $meta, ['count' => $overdue]);
        }

        return HealthResult::ok('Таймеры срабатывают вовремя', $meta);
    }
}
