<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Fixtures;

use Dskripchenko\ProcessEngine\Builder\Builder;
use Dskripchenko\ProcessEngine\Process;

final class Pipeline extends Process
{
    public const VERSION = 1;

    public function define(Builder $p): void
    {
        $p->state('lookup', fn (Builder $s) => $s
            ->switchable('pipeline.lookup', skipTo: 'merge', label: 'Lookup')
            ->batch(Lookup::class, items: Numbers::class, chunk: 4, itemAttempts: 1, onDone: 'merge'));
        $p->state('merge', fn (Builder $s) => $s->on('finish', to: 'done'));
        $p->final('done');
    }
}
