<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Tests\Fixtures\Charge;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;

final class IncidentResourceTest extends TestCase
{
    public function test_an_unknown_outcome_shows_up_and_is_resolved(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::INCIDENTS_VIEW, Permissions::OPERATE]);
        Charge::$timeout = true;
        $id = $this->order(pay: true);

        $rows = $this->postResourceSearch('process-incidents')->assertOk()->json('payload.data');
        $this->assertCount(1, $rows);
        $this->assertSame('effect_unknown', $rows[0]['type']);
        $this->assertSame($id, $rows[0]['instance_id']);

        $this->postResourceAction('process-incidents', 'action', ['key' => 'resolve', 'ids' => [$rows[0]['id']], 'payload' => ['reason' => 'Checked by phone', 'resolution' => 'checked']])
            ->assertOk();

        $this->assertSame([], $this->engine()->get($id)->incidents());
    }
}
