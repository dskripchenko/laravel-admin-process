<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Health;

use Dskripchenko\LaravelAdminHealth\HealthResult;

/** Open incidents: instances waiting for an operator. */
final class IncidentsCheck extends ProcessCheck
{
    protected function key(): string
    {
        return 'incidents';
    }

    public function name(): string
    {
        return __('Процессы: открытые инциденты');
    }

    public function frequency(): string
    {
        return (string) $this->setting('frequency', '5m');
    }

    protected function check(): HealthResult
    {
        $open = (int) ($this->engine()->health()['open_incidents'] ?? 0);
        $meta = ['open_incidents' => $open, 'path' => '/r/process-incidents'];
        $replace = ['count' => $open];
        if ($open >= (int) $this->setting('failing', 10)) {
            return HealthResult::failing('Открытых инцидентов: :count — разберите их в разделе «Инциденты процессов»', $meta, $replace);
        }
        if ($open >= (int) $this->setting('warning', 1)) {
            return HealthResult::warning('Открытых инцидентов: :count — разберите их в разделе «Инциденты процессов»', $meta, $replace);
        }

        return HealthResult::ok('Открытых инцидентов нет', $meta);
    }
}
