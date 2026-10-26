<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests;

use Dskripchenko\LaravelAdmin\Testing\Concerns\ActsAsAdmin;
use Dskripchenko\LaravelAdmin\Testing\Concerns\InteractsWithAdminResources;
use Dskripchenko\LaravelAdmin\Testing\PackageTestCase;
use Dskripchenko\LaravelAdminProcess\AdminProcessServiceProvider;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Charge;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\OrderFlow;
use Dskripchenko\LaravelProcessEngine\ProcessEngineServiceProvider;
use Dskripchenko\ProcessEngine\Engine;

abstract class TestCase extends PackageTestCase
{
    use ActsAsAdmin;
    use InteractsWithAdminResources;

    protected function setUp(): void
    {
        parent::setUp();
        Charge::$timeout = false;
    }

    protected function additionalProviders(): array
    {
        return [ProcessEngineServiceProvider::class, AdminProcessServiceProvider::class];
    }

    protected function defineAdditionalEnvironment($app): void
    {
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('process-engine.schedule.enabled', false);
    }

    protected function engine(): Engine
    {
        return $this->app->make(Engine::class);
    }

    /**
     * An order instance; `pay` sends it into the charge.
     */
    protected function order(string $key = 'A-1', bool $pay = false): string
    {
        $id = $this->engine()->start(OrderFlow::class, [], key: $key)->id();
        if ($pay) {
            $this->engine()->send($id, 'pay');
        }

        return $id;
    }
}
