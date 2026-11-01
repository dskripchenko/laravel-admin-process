<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use DateTimeImmutable;
use Dskripchenko\LaravelAdminHealth\AdminHealthServiceProvider;
use Dskripchenko\LaravelAdminHealth\HealthRegistry;
use Dskripchenko\LaravelAdminProcess\Health\IncidentsCheck;
use Dskripchenko\LaravelAdminProcess\Health\QueuesCheck;
use Dskripchenko\LaravelAdminProcess\Health\TimersCheck;
use Dskripchenko\LaravelAdminProcess\Health\UnknownEffectsCheck;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Charge;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;
use Dskripchenko\ProcessEngine\Store\InboxRecord;
use Dskripchenko\ProcessEngine\Store\Store;

final class HealthChecksTest extends TestCase
{
    public function test_the_checks_join_laravel_admin_health(): void
    {
        $this->app->register(AdminHealthServiceProvider::class);
        $ids = $this->app->make(HealthRegistry::class)->ids();

        foreach (['process.incidents', 'process.unknown_effects', 'process.timers', 'process.queues'] as $id) {
            $this->assertContains($id, $ids);
        }
    }

    public function test_all_is_well_on_a_quiet_engine(): void
    {
        $this->order('A-1');

        foreach ([new IncidentsCheck, new UnknownEffectsCheck, new TimersCheck, new QueuesCheck] as $check) {
            $this->assertTrue($check->run()->isOk(), $check->id().': '.$check->run()->text());
            $this->assertSame('processes', $check->category());
        }
    }

    public function test_an_unknown_outcome_is_an_incident_then_a_warning_then_a_failure(): void
    {
        Charge::$timeout = true;
        $this->order('A-2', pay: true);

        $this->assertTrue((new IncidentsCheck)->run()->isWarning());
        $this->assertTrue((new IncidentsCheck(['warning' => 1, 'failing' => 1]))->run()->isFailing());

        $this->assertTrue((new UnknownEffectsCheck)->run()->isOk());
        $this->travel(20)->minutes();
        $this->assertTrue((new UnknownEffectsCheck)->run()->isWarning());
        $this->travel(2)->hours();
        $result = (new UnknownEffectsCheck)->run();
        $this->assertTrue($result->isFailing());
        $this->assertSame(1, $result->meta['unknown_effects']);
    }

    public function test_events_nobody_processes_mean_the_workers_stand(): void
    {
        $id = $this->order('A-3');
        app(Store::class)->insertInbox(new InboxRecord(null, $id, 'pay', [], 'stuck-1', null, InboxRecord::PENDING, new DateTimeImmutable('-10 minutes')));
        $this->assertTrue((new QueuesCheck)->run()->isWarning());
        $this->travel(10)->minutes();
        $this->assertTrue((new QueuesCheck)->run()->isFailing());
    }
}
