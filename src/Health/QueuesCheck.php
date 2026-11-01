<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Health;

use Dskripchenko\LaravelAdminHealth\HealthResult;

/** Events nobody processed and due effects nobody took up: the workers stand. */
final class QueuesCheck extends ProcessCheck
{
    protected function key(): string
    {
        return 'queues';
    }

    public function name(): string
    {
        return __('Процессы: необработанные события и эффекты');
    }

    protected function check(): HealthResult
    {
        $counters = $this->counters();
        if ($counters === null) {
            return HealthResult::ok('Хранилище процессов не считает необработанные события');
        }
        $count = function (string $age) use ($counters): int {
            $before = $this->ago($age);

            return $counters->countStaleInbox($before) + $counters->countStaleOutbox($before);
        };
        $failingAge = (string) $this->setting('failing_after', '15 minutes');
        $warningAge = (string) $this->setting('warning_after', '5 minutes');
        $failing = $count($failingAge);
        $warning = $count($warningAge);
        $meta = ['stale' => $warning];
        if ($failing > 0) {
            return HealthResult::failing('Событий и эффектов, ждущих дольше :age: :count — работают ли воркеры очереди?', $meta, ['count' => $failing, 'age' => $failingAge]);
        }
        if ($warning > 0) {
            return HealthResult::warning('Событий и эффектов, ждущих дольше :age: :count — работают ли воркеры очереди?', $meta, ['count' => $warning, 'age' => $warningAge]);
        }

        return HealthResult::ok('Очереди процессов разбираются', $meta);
    }
}
