<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminProcess\Tests\Feature;

use Dskripchenko\LaravelAdminProcess\Permissions;
use Dskripchenko\LaravelAdminProcess\Tests\TestCase;

final class InstanceResourceTest extends TestCase
{
    public function test_the_list_shows_the_leaf_state_instead_of_the_snapshot(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $id = $this->order();

        $rows = $this->postResourceSearch('process-instances')->assertOk()->json('payload.data');

        $this->assertCount(1, $rows);
        $this->assertSame($id, $rows[0]['id']);
        $this->assertSame('new', $rows[0]['snapshot']);
        $this->assertSame('running', $rows[0]['status']);
        $this->assertStringNotContainsString('context', (string) json_encode($rows[0]));
    }

    public function test_the_list_needs_the_view_permission(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::INCIDENTS_VIEW]);

        $this->postResourceSearch('process-instances')->assertForbidden();
    }

    public function test_an_operator_suspends_and_resumes_with_a_reason(): void
    {
        $this->actingAsAdmin(['name' => 'Olga'], [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->order();

        $this->postResourceAction('process-instances', 'action', ['key' => 'suspend', 'ids' => [$id], 'payload' => ['reason' => 'Client asked']])
            ->assertOk()->assertJsonPath('payload.message', 'Done');
        $this->assertSame('suspended', $this->engine()->get($id)->status());

        $journal = $this->engine()->get($id)->journal();
        $last = end($journal);
        $this->assertSame('manual.suspend', $last->type);
        $this->assertSame('Client asked', $last->reason);
        $this->assertSame('Olga', $last->actor['display'] ?? null);

        $this->postResourceAction('process-instances', 'action', ['key' => 'resume', 'ids' => [$id], 'payload' => ['reason' => 'Back']])->assertOk();
        $this->assertSame('running', $this->engine()->get($id)->status());
    }

    public function test_an_action_without_a_reason_is_refused(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->order();

        $this->postResourceAction('process-instances', 'action', ['key' => 'suspend', 'ids' => [$id], 'payload' => ['reason' => '']])->assertStatus(422);
        $this->assertSame('running', $this->engine()->get($id)->status());
    }

    public function test_a_viewer_cannot_operate(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW]);
        $id = $this->order();

        $this->postResourceAction('process-instances', 'action', ['key' => 'cancel', 'ids' => [$id], 'payload' => ['reason' => 'x']])->assertForbidden();
        $this->assertSame('running', $this->engine()->get($id)->status());
    }

    public function test_cancel_ends_the_instance(): void
    {
        $this->actingAsAdmin(permissions: [Permissions::VIEW, Permissions::OPERATE]);
        $id = $this->order();

        $this->postResourceAction('process-instances', 'action', ['key' => 'cancel', 'ids' => [$id], 'payload' => ['reason' => 'Duplicate', 'compensate' => false]])->assertOk();

        $this->assertSame('cancelled', $this->engine()->get($id)->status());
    }
}
