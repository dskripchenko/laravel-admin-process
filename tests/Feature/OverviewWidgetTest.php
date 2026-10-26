<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Charge;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;
use Dskripchenko\LaravelAdminProcess\Widgets\ProcessOverviewWidget;

final class OverviewWidgetTest extends TestCase
{
    public function test_the_widget_counts_running_instances_and_incidents(): void
    {
        $this->order('A-1');
        Charge::$timeout = true;
        $this->order('A-2', pay: true);

        $stats = array_column((new ProcessOverviewWidget)->data()['stats'], 'value', 'label');

        $this->assertSame(2, $stats['Running']);
        $this->assertSame(1, $stats['Open incidents']);
    }
}
