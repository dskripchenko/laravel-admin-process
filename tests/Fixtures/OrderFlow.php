<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Fixtures;

use Dskripchenko\ProcessEngine\Builder\Builder;
use Dskripchenko\ProcessEngine\Process;

final class OrderFlow extends Process
{
    public const VERSION = 1;

    public function define(Builder $p): void
    {
        $p->state('new', fn (Builder $s) => $s
            ->on('pay', to: 'paying')
            ->after('3 days', to: 'expired')
            ->meta(label: 'New order'));
        $p->state('paying', fn (Builder $s) => $s
            ->invoke(Charge::class, into: 'charge', onDone: 'paid', onError: 'failed'));
        $p->state('review', fn (Builder $s) => $s->on('approve', to: 'paid'));
        $p->final('paid');
        $p->final('failed');
        $p->final('expired');
    }
}
