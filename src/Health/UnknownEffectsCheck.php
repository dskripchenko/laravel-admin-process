<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Health;

use Dskripchenko\LaravelAdminHealth\HealthResult;

/** At-most-once effects whose outcome is still unknown: not reconciled, not resolved. */
final class UnknownEffectsCheck extends ProcessCheck
{
    protected function key(): string
    {
        return 'unknown_effects';
    }

    public function name(): string
    {
        return __('Процессы: эффекты с неизвестным исходом');
    }

    public function frequency(): string
    {
        return (string) $this->setting('frequency', '5m');
    }

    protected function check(): HealthResult
    {
        $counters = $this->counters();
        if ($counters === null) {
            return HealthResult::ok('Хранилище процессов не считает эффекты с неизвестным исходом');
        }
        $failing = $counters->countUnknownEffects($this->ago((string) $this->setting('failing_after', '1 hour')));
        $warning = $counters->countUnknownEffects($this->ago((string) $this->setting('warning_after', '15 minutes')));
        $meta = ['unknown_effects' => $warning, 'path' => '/r/process-instances'];
        if ($failing > 0) {
            return HealthResult::failing('Эффектов с неизвестным исходом дольше :age: :count', $meta, ['count' => $failing, 'age' => (string) $this->setting('failing_after', '1 hour')]);
        }
        if ($warning > 0) {
            return HealthResult::warning('Эффектов с неизвестным исходом дольше :age: :count', $meta, ['count' => $warning, 'age' => (string) $this->setting('warning_after', '15 minutes')]);
        }

        return HealthResult::ok('Эффектов с неизвестным исходом нет', $meta);
    }
}
